<?php
namespace AutoQuill\Core;

use AutoQuill\Database\TopicsRepository;

/**
 * Daily digest mail: what happened since the last one.
 *
 * Nothing is accumulated anywhere. The report is assembled at send time from
 * data that already exists - the topics table, the log table and posts carrying
 * Constants::META_GENERATED_AT - so there is no parallel event store to keep in
 * sync, migrate or clean up.
 */
class Notifier {
    /** wp-cron.php can be entered concurrently by two visitors. */
    const DOUBLE_FIRE_GUARD = 3600;

    const MAX_ERROR_GROUPS = 15;
    const MAX_POSTS        = 50;
    const MAX_RECIPIENTS   = 50;
    const LOG_FETCH_LIMIT  = 500;

    public static function boot(): void {
        add_action(Constants::CRON_DIGEST, [self::class, 'run_digest']);

        // The wp_next_scheduled guard in ensure_scheduled() cannot notice a
        // CHANGED time - an event is still scheduled, just at the old hour. So
        // any settings write reschedules unconditionally.
        add_action('update_option_' . Constants::OPTION_KEY, [self::class, 'reschedule']);
        add_action('add_option_' . Constants::OPTION_KEY, [self::class, 'reschedule']);
    }

    // ---------------------------------------------------------------- config

    private static function settings(): array {
        $stored = get_option(Constants::OPTION_KEY, []);
        return array_merge(Constants::defaults(), is_array($stored) ? $stored : []);
    }

    public static function is_enabled(): bool {
        return !empty(self::settings()['notify_enabled']);
    }

    /**
     * Next occurrence of the configured local time, as a UTC timestamp.
     *
     * wp_schedule_single_event() wants UTC while the setting is local, so the
     * conversion goes through wp_timezone(). Deliberately NOT a 'daily'
     * recurring event: that is a fixed 86400s interval, so an 08:00 digest
     * would permanently become 07:00 after the autumn DST shift.
     *
     * @param int|null $from Reference point, for testing.
     */
    public static function next_run_timestamp(?int $from = null): int {
        return Scheduler::next_occurrence(
            (string) (self::settings()['notify_time'] ?? ''),
            Constants::defaults()['notify_time'],
            $from
        );
    }

    /** Clear and re-plan. Called on every settings write. */
    public static function reschedule(): void {
        wp_clear_scheduled_hook(Constants::CRON_DIGEST);

        if (!self::is_enabled()) {
            return;
        }

        wp_schedule_single_event(self::next_run_timestamp(), Constants::CRON_DIGEST);
    }

    /**
     * Self-heal from Plugin::boot(). Unlike the fetch/select crons this is
     * gated on the setting, so an upgrade never starts mailing by itself.
     */
    public static function ensure_scheduled(): void {
        $scheduled = wp_next_scheduled(Constants::CRON_DIGEST);

        if (!self::is_enabled()) {
            if ($scheduled) {
                wp_clear_scheduled_hook(Constants::CRON_DIGEST);
            }
            return;
        }

        if (!$scheduled) {
            wp_schedule_single_event(self::next_run_timestamp(), Constants::CRON_DIGEST);
        }
    }

    // ------------------------------------------------------------ recipients

    /**
     * Stored user IDs are never trusted: accounts get deleted and roles change.
     * Reading the address live is the whole reason IDs are stored, not addresses.
     *
     * @return string[]
     */
    public static function recipients(): array {
        $settings = self::settings();
        $out      = [];

        $user_ids = is_array($settings['notify_users'] ?? null) ? $settings['notify_users'] : [];
        foreach ($user_ids as $id) {
            $user = get_userdata((int) $id);

            if (!$user) {
                Logger::warning('notifier', 'Empfänger-Benutzer existiert nicht mehr', ['user_id' => (int) $id]);
                continue;
            }
            // A digest carries error text and post titles; it must not keep
            // flowing to someone who was demoted.
            if (!user_can($user, 'manage_options')) {
                Logger::warning('notifier', 'Empfänger hat keine Administratorrechte mehr', ['user_id' => (int) $id]);
                continue;
            }

            $email = (string) $user->user_email;
            if ($email !== '' && is_email($email)) {
                $out[strtolower($email)] = $email;
            }
        }

        $extra = is_array($settings['notify_emails'] ?? null) ? $settings['notify_emails'] : [];
        foreach ($extra as $email) {
            $email = (string) $email;
            if (is_email($email)) {
                $out[strtolower($email)] = $email;
            }
        }

        return array_slice(array_values($out), 0, self::MAX_RECIPIENTS);
    }

    // ---------------------------------------------------------------- digest

    public static function run_digest(): void {
        $now = time();

        // Re-chain FIRST: a fatal further down must not break the schedule.
        if (self::is_enabled()) {
            wp_schedule_single_event(self::next_run_timestamp($now + 60), Constants::CRON_DIGEST);
        } else {
            return;
        }

        $last = (int) get_option(Constants::OPTION_LAST_DIGEST, 0);
        if ($last > 0 && ($now - $last) < self::DOUBLE_FIRE_GUARD) {
            Logger::info('notifier', 'Tagesbericht übersprungen (zu kurz nach dem letzten Lauf)', [
                'seconds_since_last' => $now - $last,
            ]);
            return;
        }

        self::dispatch($last, $now);
    }

    private static function dispatch(int $last, int $now): void {
        $window_start = $last > 0
            ? max($last, $now - (Constants::DIGEST_MAX_WINDOW_DAYS * DAY_IN_SECONDS))
            : $now - DAY_IN_SECONDS;

        $data = self::collect($window_start);

        // Advanced BEFORE sending. wp_mail() can throw inside an SMTP plugin,
        // and nobody watches cron - losing one digest beats re-reporting the
        // same window forever.
        update_option(Constants::OPTION_LAST_DIGEST, $now, false);

        if (self::is_empty($data)) {
            return;
        }

        $recipients = self::recipients();
        if (!$recipients) {
            Logger::error('notifier', 'Tagesbericht nicht versendet: kein gültiger Empfänger konfiguriert');
            return;
        }

        $sent = self::send(
            self::subject($now),
            self::render_plain($data, $window_start, $now),
            $recipients
        );

        Logger::info('notifier', 'Tagesbericht versendet', [
            'recipients' => count($recipients),
            'sent'       => $sent,
        ]);
    }

    /**
     * @return array{topics: array, errors: array, error_total: int,
     *               error_capped: bool, posts: array}
     */
    public static function collect(int $since_ts): array {
        $settings = self::settings();
        $events   = is_array($settings['notify_events'] ?? null)
            ? $settings['notify_events']
            : Constants::NOTIFY_EVENTS;

        // created_at is written with current_time('mysql'), so the comparison
        // value has to be local too - gmdate() would re-report the site's UTC
        // offset worth of already-sent entries every single day.
        $since_local = wp_date('Y-m-d H:i:s', $since_ts);

        $out = [
            'topics'       => [],
            'errors'       => [],
            'error_total'  => 0,
            'error_capped' => false,
            'posts'        => [],
        ];

        if (in_array('topics', $events, true)) {
            foreach ((new TopicsRepository())->since($since_local) as $row) {
                $topics = json_decode((string) $row->topics, true);
                $out['topics'][] = [
                    'date'  => (string) $row->topic_date,
                    'items' => is_array($topics) ? $topics : [],
                ];
            }
        }

        if (in_array('errors', $events, true)) {
            $filters = [
                'levels' => [Logger::LEVEL_ERROR, Logger::LEVEL_WARNING],
                'since'  => $since_local,
            ];
            $rows = Logger::query($filters + ['limit' => self::LOG_FETCH_LIMIT, 'order' => 'desc']);

            $out['error_total']  = Logger::count($filters);
            $out['error_capped'] = $out['error_total'] > count($rows);
            $out['errors']       = self::group_log_rows($rows);
        }

        if (in_array('posts', $events, true)) {
            $out['posts'] = self::collect_posts($since_ts);
        }

        return $out;
    }

    private static function is_empty(array $data): bool {
        return empty($data['topics']) && empty($data['errors']) && empty($data['posts']);
    }

    /**
     * Collapses repeated log lines. An expired API key produces the same entry
     * dozens of times; the digest should say "42×", not fill a page.
     */
    private static function group_log_rows(array $rows): array {
        $groups = [];

        foreach ($rows as $row) {
            $key = $row['level'] . '|' . $row['source'] . '|' . $row['message'];

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'level'   => (string) $row['level'],
                    'source'  => (string) $row['source'],
                    'message' => (string) $row['message'],
                    'count'   => 0,
                    'first'   => (string) $row['created_at'],
                    'last'    => (string) $row['created_at'],
                ];
            }

            $groups[$key]['count']++;
            if ($row['created_at'] < $groups[$key]['first']) {
                $groups[$key]['first'] = (string) $row['created_at'];
            }
            if ($row['created_at'] > $groups[$key]['last']) {
                $groups[$key]['last'] = (string) $row['created_at'];
            }
        }

        // Errors before warnings, then by frequency.
        uasort($groups, static function ($a, $b) {
            if ($a['level'] !== $b['level']) {
                return $a['level'] === Logger::LEVEL_ERROR ? -1 : 1;
            }
            return $b['count'] <=> $a['count'];
        });

        return array_values($groups);
    }

    private static function collect_posts(int $since_ts): array {
        $ids = get_posts([
            'post_type'      => 'post',
            'post_status'    => ['publish', 'draft', 'pending', 'future', 'private'],
            'posts_per_page' => self::MAX_POSTS,
            'no_found_rows'  => true,
            'fields'         => 'ids',
            'orderby'        => 'ID',
            'order'          => 'DESC',
            // A UTC integer keeps this free of timezone reasoning; date_query
            // would compare against post_date, which is local.
            'meta_query'     => [[
                'key'     => Constants::META_GENERATED_AT,
                'value'   => $since_ts,
                'compare' => '>=',
                'type'    => 'NUMERIC',
            ]],
        ]);

        $out = [];
        foreach ($ids as $id) {
            $id    = (int) $id;
            $title = wp_specialchars_decode((string) get_the_title($id), ENT_QUOTES);

            $out[] = [
                'id'     => $id,
                'title'  => $title !== '' ? $title : sprintf('#%d', $id),
                'status' => (string) get_post_status($id),
                // get_edit_post_link() checks current_user_can('edit_post') and
                // returns null in cron, where there is no logged-in user.
                'url'    => admin_url('post.php?post=' . $id . '&action=edit'),
            ];
        }

        return $out;
    }

    // ------------------------------------------------------------------ mail

    private static function subject(int $ts): string {
        // get_bloginfo('name') is HTML-encoded: "Müller &amp; Söhne".
        $name    = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
        $subject = sprintf(
            /* translators: 1: site name, 2: date */
            __('[%1$s] AutoQuill Tagesbericht – %2$s', 'auto-quill'),
            $name,
            wp_date('d.m.Y', $ts)
        );

        // Feed content reaches titles and log messages; never let a newline
        // into a mail header.
        return trim((string) preg_replace('/[\r\n]+/', ' ', $subject));
    }

    /**
     * Plain text on purpose: HTML would need the global wp_mail_content_type
     * filter, which drags every other plugin's mail along if it is ever left
     * hanging. The body is NOT escaped - entities would show up literally.
     */
    public static function render_plain(array $data, int $since_ts, int $until_ts): string {
        $lines = [];

        $lines[] = sprintf(
            /* translators: 1: start, 2: end */
            __('Zeitraum: %1$s bis %2$s', 'auto-quill'),
            wp_date('d.m.Y H:i', $since_ts),
            wp_date('d.m.Y H:i', $until_ts)
        );
        $lines[] = '';

        if (!empty($data['topics'])) {
            $lines[] = self::heading(__('Neue Top-Themen', 'auto-quill'));
            foreach ($data['topics'] as $day) {
                $lines[] = sprintf('%s:', wp_date('d.m.Y', strtotime($day['date'] . ' 12:00:00')));
                foreach ($day['items'] as $topic) {
                    $rating = (isset($topic['rating']) && $topic['rating'] !== null)
                        ? sprintf('[%d] ', (int) $topic['rating'])
                        : '';
                    $lines[] = '  - ' . $rating . self::plain((string) ($topic['title'] ?? ''));
                }
            }
            $lines[] = '';
            $lines[] = '  ' . admin_url('admin.php?page=' . Constants::MENU_SLUG);
            $lines[] = '';
        }

        if (!empty($data['posts'])) {
            $lines[] = self::heading(__('Erstellte Blog-Posts', 'auto-quill'));
            foreach ($data['posts'] as $post) {
                $lines[] = sprintf('  - %s (%s)', self::plain($post['title']), self::status_label($post['status']));
                $lines[] = '    ' . $post['url'];
            }
            $lines[] = '';
        }

        if (!empty($data['errors'])) {
            $lines[] = self::heading(__('Fehler und Warnungen', 'auto-quill'));

            foreach (array_slice($data['errors'], 0, self::MAX_ERROR_GROUPS) as $group) {
                $lines[] = sprintf(
                    '  - %dx [%s] %s: %s',
                    $group['count'],
                    strtoupper($group['level']),
                    $group['source'],
                    self::plain($group['message'])
                );
                $lines[] = sprintf('    %s – %s', $group['first'], $group['last']);
            }

            $remaining = count($data['errors']) - self::MAX_ERROR_GROUPS;
            if ($remaining > 0) {
                /* translators: %d: number of further error groups */
                $lines[] = '  ' . sprintf(__('… und %d weitere Arten von Meldungen.', 'auto-quill'), $remaining);
            }

            if (!empty($data['error_capped'])) {
                $lines[] = '  ' . sprintf(
                    /* translators: %d: number of entries */
                    __('Mindestens %d Einträge insgesamt; ältere wurden bereits aus dem Log entfernt.', 'auto-quill'),
                    (int) $data['error_total']
                );
            }

            $lines[] = '';
            $lines[] = '  ' . admin_url('admin.php?page=' . Constants::LOGS_PAGE_SLUG);
            $lines[] = '';
        }

        $lines[] = str_repeat('-', 50);
        $lines[] = __('Diese Nachricht wurde automatisch von AutoQuill erstellt.', 'auto-quill');
        $lines[] = admin_url('admin.php?page=' . Constants::SETTINGS_PAGE_SLUG) . '#tab-notify';

        return implode("\n", $lines);
    }

    private static function heading(string $text): string {
        return $text . "\n" . str_repeat('=', mb_strlen($text));
    }

    /** Strips markup and newlines out of anything coming from a feed. */
    private static function plain(string $text): string {
        $text = wp_specialchars_decode(wp_strip_all_tags($text), ENT_QUOTES);
        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    private static function status_label(string $status): string {
        $object = get_post_status_object($status);
        return $object && !empty($object->label) ? (string) $object->label : $status;
    }

    /**
     * One mail per recipient. A shared To: would show every admin's address to
     * all the others, and some hosts cap recipients per message.
     *
     * @param string[] $recipients
     * @return int Number of accepted messages.
     */
    public static function send(string $subject, string $body, array $recipients): int {
        $failure = '';
        $capture = static function ($error) use (&$failure) {
            $failure = is_wp_error($error) ? $error->get_error_message() : 'unbekannt';
        };
        add_action('wp_mail_failed', $capture);

        $sent = 0;

        try {
            foreach ($recipients as $to) {
                $failure = '';
                try {
                    // wp_mail() can return false without firing wp_mail_failed,
                    // and can throw outright under some SMTP plugins.
                    if (wp_mail($to, $subject, $body)) {
                        $sent++;
                    } else {
                        Logger::error('notifier', 'Mail-Versand fehlgeschlagen', [
                            'to'     => $to,
                            'reason' => $failure,
                        ]);
                    }
                } catch (\Throwable $e) {
                    Logger::error('notifier', 'Mail-Versand warf eine Exception', [
                        'to'        => $to,
                        'exception' => $e->getMessage(),
                    ]);
                }
            }
        } finally {
            remove_action('wp_mail_failed', $capture);
        }

        return $sent;
    }

    // ------------------------------------------------------------- test mail

    /**
     * Sends the last 24 hours as a digest, or a short confirmation when there
     * is nothing to report.
     *
     * @return array{sent:int, total:int, empty:bool}
     */
    public static function send_test(): array {
        $now        = time();
        $recipients = self::recipients();

        if (!$recipients) {
            return ['sent' => 0, 'total' => 0, 'empty' => false];
        }

        $since = $now - DAY_IN_SECONDS;
        $data  = self::collect($since);
        $empty = self::is_empty($data);

        $body = $empty
            ? __('Test-Mail von AutoQuill. Der Versand funktioniert. In den letzten 24 Stunden gab es nichts zu berichten.', 'auto-quill')
                . "\n\n" . admin_url('admin.php?page=' . Constants::SETTINGS_PAGE_SLUG) . '#tab-notify'
            : __('Test-Mail von AutoQuill — so sieht der Tagesbericht aus:', 'auto-quill')
                . "\n\n" . self::render_plain($data, $since, $now);

        $sent = self::send(self::subject($now), $body, $recipients);

        return ['sent' => $sent, 'total' => count($recipients), 'empty' => $empty];
    }
}
