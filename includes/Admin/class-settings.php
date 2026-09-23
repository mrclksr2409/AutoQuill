<?php
namespace AutoQuill\Admin;

use AutoQuill\AI\ModelCatalog;
use AutoQuill\Core\Constants as C;
use AutoQuill\Core\Notifier;

class Settings {
    public static function boot(): void {
        add_action('admin_init', [self::class, 'register']);
        // Handler lives here, not in Notifier: Admin may depend on Core, not
        // the other way round. Same admin_post_ pattern as SourcesController.
        add_action('admin_post_' . C::ACTION_TEST_MAIL, [self::class, 'handle_test_mail']);
    }

    public static function handle_test_mail(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Zugriff verweigert', 'auto-quill'));
        }
        check_admin_referer(C::NONCE_TEST_MAIL);

        $result = Notifier::send_test();

        if ($result['total'] === 0) {
            Notices::error(__('Es ist kein gültiger Empfänger konfiguriert — es wurde nichts versendet.', 'auto-quill'));
        } elseif ($result['sent'] === 0) {
            Notices::error(__('Der Versand ist fehlgeschlagen. Details stehen unter AutoQuill → Logs.', 'auto-quill'));
        } else {
            Notices::success(sprintf(
                /* translators: 1: delivered count, 2: total recipients */
                __('Test-Mail an %1$d von %2$d Empfängern versendet.', 'auto-quill'),
                $result['sent'],
                $result['total']
            ));
        }

        wp_safe_redirect(admin_url('admin.php?page=' . C::SETTINGS_PAGE_SLUG) . '#tab-notify');
        exit;
    }

    public static function register(): void {
        register_setting(
            C::SETTINGS_GROUP,
            C::OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [self::class, 'sanitize'],
                'default'           => C::defaults(),
                'show_in_rest'      => false,
            ]
        );
    }

    public static function sanitize($input): array {
        $prev = get_option(C::OPTION_KEY, C::defaults());
        if (!is_array($prev)) {
            $prev = C::defaults();
        }
        if (!is_array($input)) {
            return $prev;
        }

        $clean = $prev;

        if (isset($input['ai_provider'])) {
            $clean['ai_provider'] = in_array($input['ai_provider'], ['openai', 'claude'], true)
                ? $input['ai_provider']
                : ($prev['ai_provider'] ?? 'openai');
        }

        if (array_key_exists('ai_api_key', $input)) {
            $new_key = sanitize_text_field((string) $input['ai_api_key']);
            if ($new_key !== '') {
                $clean['ai_api_key'] = $new_key;
            }
        }

        if (array_key_exists('openai_model', $input)) {
            $model = self::sanitize_model_id($input['openai_model']);
            $clean['openai_model'] = $model !== '' ? $model : C::DEFAULT_OPENAI_MODEL;
        }

        if (array_key_exists('claude_model', $input)) {
            $model = self::sanitize_model_id($input['claude_model']);
            $clean['claude_model'] = $model !== '' ? $model : C::DEFAULT_CLAUDE_MODEL;
        }

        if (array_key_exists('pixabay_api_key', $input)) {
            $new_key = sanitize_text_field((string) $input['pixabay_api_key']);
            if ($new_key !== '') {
                $clean['pixabay_api_key'] = $new_key;
            }
        }

        if (isset($input['post_status'])) {
            $clean['post_status'] = in_array($input['post_status'], ['draft', 'publish', 'pending'], true)
                ? $input['post_status']
                : ($prev['post_status'] ?? 'draft');
        }

        $clean['auto_publish'] = !empty($input['auto_publish']);

        $clean['source_link_enabled'] = !empty($input['source_link_enabled']);

        if (array_key_exists('source_link_template', $input)) {
            $template = sanitize_textarea_field((string) $input['source_link_template']);
            $clean['source_link_template'] = trim($template) !== ''
                ? $template
                : C::DEFAULT_SOURCE_LINK_TEMPLATE;
        }

        if (isset($input['posts_per_day'])) {
            $clean['posts_per_day'] = max(1, min(10, (int) $input['posts_per_day']));
        }

        if (isset($input['rss_lookback_days'])) {
            $v = (int) $input['rss_lookback_days'];
            if ($v < 0)   { $v = 0; }
            if ($v > 365) { $v = 365; }
            $clean['rss_lookback_days'] = $v;
        }

        if (array_key_exists('backup_enabled', $input)) {
            $clean['backup_enabled'] = !empty($input['backup_enabled']);
        }
        if (isset($input['backup_keep'])) {
            $clean['backup_keep'] = max(1, min(C::BACKUP_KEEP_MAX, (int) $input['backup_keep']));
        }

        foreach (['fetch_time', 'select_time', 'backup_time'] as $time_key) {
            if (array_key_exists($time_key, $input)) {
                $clean[$time_key] = self::sanitize_time(
                    $input[$time_key],
                    (string) ($prev[$time_key] ?? C::defaults()[$time_key])
                );
            }
        }

        // Selection looks at the last 24h of articles, so it should follow the
        // fetch closely. Measured across midnight: 23:00 -> 00:30 is fine.
        $to_minutes = static fn(string $t): int => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);
        $gap = ($to_minutes((string) ($clean['select_time'] ?? C::defaults()['select_time']))
              - $to_minutes((string) ($clean['fetch_time'] ?? C::defaults()['fetch_time'])) + 1440) % 1440;
        if ($gap === 0 || $gap > 720) {
            add_settings_error(
                C::OPTION_KEY,
                'auto_quill_select_before_fetch',
                __('Die Themenauswahl liegt nicht kurz nach dem RSS-Abruf. Sie arbeitet dann mit Artikeln eines älteren Abrufs — oder läuft gleichzeitig mit ihm.', 'auto-quill'),
                'warning'
            );
        }

        if (array_key_exists('prompt_title', $input)) {
            $title_tpl = sanitize_textarea_field((string) $input['prompt_title']);
            $clean['prompt_title'] = trim($title_tpl) !== ''
                ? $title_tpl
                : C::defaults()['prompt_title'];
        }

        if (array_key_exists('prompt_body', $input)) {
            $body = sanitize_textarea_field((string) $input['prompt_body']);
            $clean['prompt_body'] = trim($body) !== ''
                ? $body
                : C::defaults()['prompt_body'];
        }

        if (array_key_exists('prompt_excerpt', $input)) {
            $excerpt = sanitize_textarea_field((string) $input['prompt_excerpt']);
            $clean['prompt_excerpt'] = trim($excerpt) !== ''
                ? $excerpt
                : C::defaults()['prompt_excerpt'];
        }

        if (array_key_exists('prompt_category', $input)) {
            $category_tpl = sanitize_textarea_field((string) $input['prompt_category']);
            $clean['prompt_category'] = trim($category_tpl) !== ''
                ? $category_tpl
                : C::defaults()['prompt_category'];
        }

        $clean['debug_logging'] = !empty($input['debug_logging']);
        $clean['beta_mode']     = !empty($input['beta_mode']);

        self::sanitize_notifications($input, $prev, $clean);

        // An enabled-but-nobody-listening state is the real trap; say so instead
        // of silently never sending.
        if (!empty($clean['notify_enabled'])
            && empty($clean['notify_users'])
            && empty($clean['notify_emails'])
        ) {
            add_settings_error(
                C::OPTION_KEY,
                'auto_quill_notify_no_recipients',
                __('Benachrichtigungen sind aktiv, aber es ist kein Empfänger hinterlegt — es wird nichts versendet.', 'auto-quill'),
                'warning'
            );
        }

        add_settings_error(
            C::OPTION_KEY,
            'auto_quill_settings_updated',
            __('Einstellungen gespeichert.', 'auto-quill'),
            'updated'
        );

        return $clean;
    }

    /**
     * Notification keys.
     *
     * Gated on array_key_exists or an explicit marker rather than the
     * unconditional `!empty($input[k])` used for the older checkboxes: that is
     * only safe because nothing else writes this option today.
     *
     * @param array $clean Modified in place.
     */
    private static function sanitize_notifications(array $input, array $prev, array &$clean): void {
        $defaults = C::defaults();

        if (array_key_exists('notify_enabled', $input)) {
            $clean['notify_enabled'] = !empty($input['notify_enabled']);
        }

        if (array_key_exists('notify_time', $input)) {
            $clean['notify_time'] = self::sanitize_time(
                $input['notify_time'],
                (string) ($prev['notify_time'] ?? $defaults['notify_time'])
            );
        }

        // A checkbox group with nothing ticked submits no key at all. Without
        // the marker, `$clean = $prev` would keep the old selection and the
        // last recipient could never be removed.
        if (!empty($input['notify_users_present'])) {
            $ids = isset($input['notify_users']) && is_array($input['notify_users'])
                ? $input['notify_users']
                : [];
            $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
            $ids = array_slice($ids, 0, 50);

            $clean['notify_users'] = array_values(array_filter(
                $ids,
                static fn($id) => (bool) get_userdata($id)
            ));
        }

        if (array_key_exists('notify_emails', $input)) {
            $clean['notify_emails'] = self::parse_email_list((string) $input['notify_emails']);
        }

        if (!empty($input['notify_events_present'])) {
            $events = isset($input['notify_events']) && is_array($input['notify_events'])
                ? $input['notify_events']
                : [];
            // Intersect in the constant's order so the stored order is stable.
            $clean['notify_events'] = array_values(array_intersect(C::NOTIFY_EVENTS, $events));
        }

        // Markers are form plumbing and must never reach the stored option.
        unset($clean['notify_users_present'], $clean['notify_events_present']);
    }

    /**
     * <input type="time"> submits HH:MM, some browsers HH:MM:SS. Anything
     * else - including empty - keeps the previous value instead of silently
     * becoming 00:00.
     */
    private static function sanitize_time($raw, string $fallback): string {
        $time = trim((string) $raw);
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/', $time, $m)) {
            return $m[1] . ':' . $m[2];
        }
        return $fallback;
    }

    /** Model IDs from both providers only ever use this character set. */
    private static function sanitize_model_id($raw): string {
        $model = trim(sanitize_text_field((string) $raw));
        return preg_match('/^[A-Za-z0-9._:\/-]{1,100}$/', $model) ? $model : '';
    }

    /**
     * Splits a textarea into validated, de-duplicated addresses.
     *
     * @return string[]
     */
    private static function parse_email_list(string $raw): array {
        $parts = preg_split('/[\r\n,;]+/', $raw) ?: [];
        $out   = [];

        foreach ($parts as $part) {
            $email = sanitize_email(trim($part));
            if ($email === '' || !is_email($email)) {
                continue;
            }
            $key = strtolower($email);
            if (!isset($out[$key])) {
                $out[$key] = $email;
            }
        }

        return array_slice(array_values($out), 0, 50);
    }

    /**
     * A dropdown instead of free text. The options come from the provider
     * (ModelCatalog, loaded by admin.js); the saved model and the default are
     * always present, so the field works without the list and never silently
     * changes the stored value.
     */
    private static function render_model_row(string $provider, string $label, string $current, string $default, string $active_provider): void {
        $field   = $provider . '_model';
        $options = [];
        foreach (ModelCatalog::cached($provider) as $model) {
            $options[$model['id']] = $model['label'];
        }
        if (!isset($options[$current])) {
            $options = [$current => $current] + $options;
        }
        if (!isset($options[$default])) {
            $options[$default] = $default;
        }
        ?>
        <tr class="auto-quill-model-row" data-provider="<?php echo esc_attr($provider); ?>"
            <?php echo $provider !== $active_provider ? 'style="display:none;"' : ''; ?>>
            <th scope="row">
                <label for="<?php echo esc_attr($field); ?>"><?php echo esc_html($label); ?></label>
            </th>
            <td>
                <select id="<?php echo esc_attr($field); ?>"
                        class="auto-quill-model-select"
                        data-provider="<?php echo esc_attr($provider); ?>"
                        name="<?php echo esc_attr(C::OPTION_KEY); ?>[<?php echo esc_attr($field); ?>]"
                        style="min-width: 300px;">
                    <?php foreach ($options as $id => $option_label): ?>
                        <option value="<?php echo esc_attr((string) $id); ?>" <?php selected($current, (string) $id); ?>>
                            <?php echo esc_html((string) $option_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="button auto-quill-models-refresh" data-provider="<?php echo esc_attr($provider); ?>">
                    <?php esc_html_e('Modelle neu laden', 'auto-quill'); ?>
                </button>
                <span class="spinner auto-quill-models-spinner"></span>
                <p class="description auto-quill-models-status" aria-live="polite"></p>
                <p class="description"><?php
                    /* translators: %s: default model name */
                    printf(esc_html__('Die Liste wird direkt beim Anbieter mit dem API-Schlüssel abgerufen. Standard: %s', 'auto-quill'), '<code>' . esc_html($default) . '</code>');
                ?></p>
            </td>
        </tr>
        <?php
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Zugriff verweigert', 'auto-quill'));
        }

        $settings = get_option(C::OPTION_KEY, C::defaults());
        if (!is_array($settings)) {
            $settings = C::defaults();
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php settings_errors(C::OPTION_KEY); ?>
            <?php Notices::flush(); ?>

            <form method="post" action="options.php">
                <?php settings_fields(C::SETTINGS_GROUP); ?>

                <h2 class="nav-tab-wrapper auto-quill-settings-tabs">
                    <a href="#tab-ki"      class="nav-tab nav-tab-active" data-tab="ki"><?php esc_html_e('KI-Provider', 'auto-quill'); ?></a>
                    <a href="#tab-publish" class="nav-tab"                data-tab="publish"><?php esc_html_e('Veröffentlichung', 'auto-quill'); ?></a>
                    <a href="#tab-schedule" class="nav-tab"               data-tab="schedule"><?php esc_html_e('Zeitplan', 'auto-quill'); ?></a>
                    <a href="#tab-prompts" class="nav-tab"                data-tab="prompts"><?php esc_html_e('Prompts', 'auto-quill'); ?></a>
                    <a href="#tab-notify"  class="nav-tab"                data-tab="notify"><?php esc_html_e('Benachrichtigungen', 'auto-quill'); ?></a>
                    <a href="#tab-backup"  class="nav-tab"                data-tab="backup"><?php esc_html_e('Backup', 'auto-quill'); ?></a>
                    <a href="#tab-updates" class="nav-tab"                data-tab="updates"><?php esc_html_e('Updates', 'auto-quill'); ?></a>
                    <a href="#tab-debug"   class="nav-tab"                data-tab="debug"><?php esc_html_e('Debug', 'auto-quill'); ?></a>
                </h2>

                <div class="auto-quill-tab-panel" data-tab="ki">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="ai_provider"><?php esc_html_e('KI-Provider', 'auto-quill'); ?></label>
                            </th>
                            <td>
                                <select id="ai_provider" name="<?php echo esc_attr(C::OPTION_KEY); ?>[ai_provider]">
                                    <option value="openai" <?php selected($settings['ai_provider'] ?? '', 'openai'); ?>>OpenAI</option>
                                    <option value="claude" <?php selected($settings['ai_provider'] ?? '', 'claude'); ?>>Claude (Anthropic)</option>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="ai_api_key"><?php esc_html_e('API-Schlüssel', 'auto-quill'); ?></label>
                            </th>
                            <td>
                                <?php
                                $key_from_const = C::ai_api_key_from_constant();
                                $has_stored_key = !empty($settings['ai_api_key']);
                                $placeholder    = $has_stored_key
                                    ? esc_attr__('Gespeicherter Schlüssel — leer lassen, um ihn zu behalten', 'auto-quill')
                                    : esc_attr__('sk-…', 'auto-quill');
                                ?>
                                <input type="password" id="ai_api_key"
                                       name="<?php echo esc_attr(C::OPTION_KEY); ?>[ai_api_key]"
                                       value=""
                                       placeholder="<?php echo $placeholder; ?>"
                                       autocomplete="new-password"
                                       <?php disabled($key_from_const); ?>
                                       style="width: 300px;">
                                <p class="description">
                                    <?php if ($key_from_const): ?>
                                        <?php esc_html_e('Schlüssel wird aus der Konstante AUTO_QUILL_AI_KEY in wp-config.php geladen und hat Vorrang vor diesem Feld.', 'auto-quill'); ?>
                                    <?php else: ?>
                                        <?php esc_html_e('Für mehr Sicherheit kann der Schlüssel auch in wp-config.php als AUTO_QUILL_AI_KEY definiert werden.', 'auto-quill'); ?>
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>

                        <?php
                        self::render_model_row('openai', __('OpenAI-Modell', 'auto-quill'), (string) ($settings['openai_model'] ?? C::DEFAULT_OPENAI_MODEL), C::DEFAULT_OPENAI_MODEL, (string) ($settings['ai_provider'] ?? 'openai'));
                        self::render_model_row('claude', __('Claude-Modell', 'auto-quill'), (string) ($settings['claude_model'] ?? C::DEFAULT_CLAUDE_MODEL), C::DEFAULT_CLAUDE_MODEL, (string) ($settings['ai_provider'] ?? 'openai'));
                        ?>

                        <tr>
                            <th scope="row">
                                <label for="pixabay_api_key"><?php esc_html_e('Pixabay-API-Key', 'auto-quill'); ?></label>
                            </th>
                            <td>
                                <?php
                                $pixabay_from_const = C::pixabay_api_key_from_constant();
                                $has_pixabay_key    = !empty($settings['pixabay_api_key']);
                                $pixabay_placeholder = $has_pixabay_key
                                    ? esc_attr__('Gespeicherter Schlüssel — leer lassen, um ihn zu behalten', 'auto-quill')
                                    : esc_attr__('z. B. 12345678-abcdef…', 'auto-quill');
                                ?>
                                <input type="password" id="pixabay_api_key"
                                       name="<?php echo esc_attr(C::OPTION_KEY); ?>[pixabay_api_key]"
                                       value=""
                                       placeholder="<?php echo $pixabay_placeholder; ?>"
                                       autocomplete="new-password"
                                       <?php disabled($pixabay_from_const); ?>
                                       style="width: 300px;">
                                <p class="description">
                                    <?php if ($pixabay_from_const): ?>
                                        <?php esc_html_e('Schlüssel wird aus der Konstante AUTO_QUILL_PIXABAY_KEY in wp-config.php geladen und hat Vorrang vor diesem Feld.', 'auto-quill'); ?>
                                    <?php else: ?>
                                        <?php
                                        printf(
                                            /* translators: %s: link to Pixabay API docs */
                                            esc_html__('Optional. Wird für die Beitragsbild-Suche im Dashboard verwendet. Kostenlosen Key anfordern unter %s.', 'auto-quill'),
                                            '<a href="https://pixabay.com/api/docs/" target="_blank" rel="noopener noreferrer">pixabay.com/api/docs</a>'
                                        );
                                        ?>
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="auto-quill-tab-panel" data-tab="publish" style="display:none;">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="post_status"><?php esc_html_e('Standard Post-Status', 'auto-quill'); ?></label>
                            </th>
                            <td>
                                <select id="post_status" name="<?php echo esc_attr(C::OPTION_KEY); ?>[post_status]">
                                    <option value="draft" <?php selected($settings['post_status'] ?? '', 'draft'); ?>>Entwurf</option>
                                    <option value="publish" <?php selected($settings['post_status'] ?? '', 'publish'); ?>>Veröffentlicht</option>
                                    <option value="pending" <?php selected($settings['post_status'] ?? '', 'pending'); ?>>Genehmigung ausstehend</option>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label>
                                    <input type="hidden"
                                           name="<?php echo esc_attr(C::OPTION_KEY); ?>[auto_publish]"
                                           value="0">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr(C::OPTION_KEY); ?>[auto_publish]"
                                           value="1"
                                           <?php checked(!empty($settings['auto_publish'])); ?>>
                                    <?php esc_html_e('Posts automatisch veröffentlichen', 'auto-quill'); ?>
                                </label>
                            </th>
                        </tr>

                        <tr>
                            <th scope="row">
                                <?php esc_html_e('Link zum Originalartikel', 'auto-quill'); ?>
                            </th>
                            <td>
                                <label>
                                    <input type="hidden"
                                           name="<?php echo esc_attr(C::OPTION_KEY); ?>[source_link_enabled]"
                                           value="0">
                                    <input type="checkbox"
                                           id="source_link_enabled"
                                           name="<?php echo esc_attr(C::OPTION_KEY); ?>[source_link_enabled]"
                                           value="1"
                                           <?php checked($settings['source_link_enabled'] ?? true); ?>>
                                    <?php esc_html_e('Jedem Blog-Beitrag einen Quellenhinweis anhängen', 'auto-quill'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Der Hinweis wird serverseitig ans Ende des Beitrags gesetzt und ist damit garantiert vorhanden – unabhängig davon, ob die KI einen Link ausgibt. Er erscheint bereits in der Vorschau.', 'auto-quill'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="source_link_template"><?php esc_html_e('Text des Quellenhinweises', 'auto-quill'); ?></label>
                            </th>
                            <td>
                                <textarea id="source_link_template" rows="2" class="large-text code"
                                          name="<?php echo esc_attr(C::OPTION_KEY); ?>[source_link_template]"><?php
                                    echo esc_textarea($settings['source_link_template'] ?? C::DEFAULT_SOURCE_LINK_TEMPLATE);
                                ?></textarea>
                                <p class="description">
                                    <?php esc_html_e('Reiner Text mit Platzhaltern (kein HTML – das Markup liefert der Platzhalter):', 'auto-quill'); ?>
                                    <code>{source_link}</code> <?php esc_html_e('(fertiger Link auf den Artikeltitel)', 'auto-quill'); ?>,
                                    <code>{article_title}</code>,
                                    <code>{source_url}</code>,
                                    <code>{feed_name}</code>.
                                    <br>
                                    <?php
                                    printf(
                                        /* translators: %s: default template string */
                                        esc_html__('Standard: %s', 'auto-quill'),
                                        '<code>' . esc_html(C::DEFAULT_SOURCE_LINK_TEMPLATE) . '</code>'
                                    );
                                    ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="rss_lookback_days"><?php esc_html_e('RSS-Rückblick (Tage)', 'auto-quill'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="rss_lookback_days" min="0" max="365" step="1"
                                       name="<?php echo esc_attr(C::OPTION_KEY); ?>[rss_lookback_days]"
                                       value="<?php echo esc_attr((string) ($settings['rss_lookback_days'] ?? 7)); ?>"
                                       style="width: 100px;">
                                <p class="description"><?php esc_html_e('Wie viele Tage zurück sollen Feed-Artikel berücksichtigt werden? 0 = unbegrenzt. Ältere Artikel werden auch aus der Datenbank entfernt.', 'auto-quill'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="auto-quill-tab-panel" data-tab="schedule" style="display:none;">
                    <p class="description" style="margin: 1em 0;">
                        <?php
                        printf(
                            /* translators: %s: site timezone name */
                            esc_html__('Beide Zeiten gelten in der Ortszeit der Seite (%s). Die tatsächliche Ausführung hängt an WP-Cron und kann sich verzögern, wenn die Seite wenig besucht wird.', 'auto-quill'),
                            '<code>' . esc_html(wp_timezone_string()) . '</code>'
                        );
                        ?>
                    </p>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="fetch_time"><?php esc_html_e('RSS-Abruf', 'auto-quill'); ?></label>
                            </th>
                            <td>
                                <input type="time" id="fetch_time"
                                       name="<?php echo esc_attr(C::OPTION_KEY); ?>[fetch_time]"
                                       value="<?php echo esc_attr((string) ($settings['fetch_time'] ?? C::defaults()['fetch_time'])); ?>">
                                <p class="description"><?php esc_html_e('Wann täglich alle aktiven RSS-Quellen abgerufen werden.', 'auto-quill'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="select_time"><?php esc_html_e('Themenauswahl', 'auto-quill'); ?></label>
                            </th>
                            <td>
                                <input type="time" id="select_time"
                                       name="<?php echo esc_attr(C::OPTION_KEY); ?>[select_time]"
                                       value="<?php echo esc_attr((string) ($settings['select_time'] ?? C::defaults()['select_time'])); ?>">
                                <p class="description"><?php esc_html_e('Wann die KI täglich die Top-Themen aus den Artikeln der letzten 24 Stunden wählt. Sollte nach dem RSS-Abruf liegen.', 'auto-quill'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="auto-quill-tab-panel" data-tab="prompts" style="display:none;">
                    <p class="description" style="margin: 1em 0;">
                        <?php esc_html_e('Die vier Vorgaben unten werden zu einer einzigen KI-Anfrage zusammengeführt. Die KI antwortet mit einem gemeinsamen JSON-Objekt, das Titel, Beitragstext, Auszug und Kategorien enthält. Quelltext und Kategorienliste werden automatisch ergänzt – die Felder unten sollten nur die inhaltlichen Vorgaben pro Bestandteil beschreiben (kein eigenes JSON-Schema und keine "Antworte mit JSON …"-Hinweise mehr nötig).', 'auto-quill'); ?>
                    </p>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="prompt_title"><?php esc_html_e('Prompt: Titel', 'auto-quill'); ?></label>
                            </th>
                            <td>
                                <textarea id="prompt_title" rows="8" class="large-text code"
                                          name="<?php echo esc_attr(C::OPTION_KEY); ?>[prompt_title]"><?php
                                    echo esc_textarea($settings['prompt_title'] ?? C::defaults()['prompt_title']);
                                ?></textarea>
                                <p class="description">
                                    <?php esc_html_e('Inhaltliche Vorgaben für den Beitragstitel. Unterstützter Platzhalter:', 'auto-quill'); ?>
                                    <code>{topic_title}</code>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="prompt_body"><?php esc_html_e('Prompt: Beitragstext', 'auto-quill'); ?></label>
                            </th>
                            <td>
                                <textarea id="prompt_body" rows="10" class="large-text code"
                                          name="<?php echo esc_attr(C::OPTION_KEY); ?>[prompt_body]"><?php
                                    echo esc_textarea($settings['prompt_body'] ?? C::defaults()['prompt_body']);
                                ?></textarea>
                                <p class="description">
                                    <?php esc_html_e('Inhaltliche Vorgaben für den Beitragstext (Länge, Struktur, Stil). Quelltext wird automatisch in der kombinierten Anfrage ergänzt.', 'auto-quill'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="prompt_excerpt"><?php esc_html_e('Prompt: Social-Media-Auszug', 'auto-quill'); ?></label>
                            </th>
                            <td>
                                <textarea id="prompt_excerpt" rows="6" class="large-text code"
                                          name="<?php echo esc_attr(C::OPTION_KEY); ?>[prompt_excerpt]"><?php
                                    echo esc_textarea($settings['prompt_excerpt'] ?? C::defaults()['prompt_excerpt']);
                                ?></textarea>
                                <p class="description">
                                    <?php esc_html_e('Inhaltliche Vorgaben für den Social-Media-Auszug (Länge, Tonalität, Hook).', 'auto-quill'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="prompt_category"><?php esc_html_e('Prompt: Kategorie', 'auto-quill'); ?></label>
                            </th>
                            <td>
                                <textarea id="prompt_category" rows="8" class="large-text code"
                                          name="<?php echo esc_attr(C::OPTION_KEY); ?>[prompt_category]"><?php
                                    echo esc_textarea($settings['prompt_category'] ?? C::defaults()['prompt_category']);
                                ?></textarea>
                                <p class="description">
                                    <?php esc_html_e('Vorgaben für die Kategorienzuordnung. Unterstützter Platzhalter:', 'auto-quill'); ?>
                                    <code>{categories_list}</code>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="auto-quill-tab-panel" data-tab="notify" style="display:none;">
                    <?php
                    $notify_enabled = $settings['notify_enabled'] ?? C::defaults()['notify_enabled'];
                    $notify_time    = (string) ($settings['notify_time'] ?? C::defaults()['notify_time']);
                    $notify_users   = is_array($settings['notify_users'] ?? null) ? $settings['notify_users'] : [];
                    $notify_emails  = is_array($settings['notify_emails'] ?? null) ? $settings['notify_emails'] : [];
                    $notify_events  = is_array($settings['notify_events'] ?? null)
                        ? $settings['notify_events']
                        : C::defaults()['notify_events'];

                    $admin_users = get_users([
                        'capability' => 'manage_options',
                        'fields'     => ['ID', 'display_name', 'user_email'],
                        'number'     => 200,
                        'orderby'    => 'display_name',
                    ]);

                    $event_labels = [
                        'topics' => __('Neue Top-Themen', 'auto-quill'),
                        'errors' => __('Fehler und Warnungen', 'auto-quill'),
                        'posts'  => __('Erstellte Blog-Posts', 'auto-quill'),
                    ];
                    ?>

                    <p class="description" style="margin: 1em 0;">
                        <?php esc_html_e('AutoQuill kann einmal täglich zusammenfassen, was seit der letzten Mail passiert ist. Gibt es nichts zu berichten, wird auch nichts verschickt. Ob die Mail ankommt, hängt von der Mail-Konfiguration der Seite ab — im Zweifel ein SMTP-Plugin einrichten und den Test unten nutzen.', 'auto-quill'); ?>
                    </p>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Tagesbericht', 'auto-quill'); ?></th>
                            <td>
                                <label>
                                    <input type="hidden"
                                           name="<?php echo esc_attr(C::OPTION_KEY); ?>[notify_enabled]"
                                           value="0">
                                    <input type="checkbox"
                                           id="notify_enabled"
                                           name="<?php echo esc_attr(C::OPTION_KEY); ?>[notify_enabled]"
                                           value="1"
                                           <?php checked(!empty($notify_enabled)); ?>>
                                    <?php esc_html_e('Täglich eine Zusammenfassung per E-Mail senden', 'auto-quill'); ?>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="notify_time"><?php esc_html_e('Uhrzeit', 'auto-quill'); ?></label>
                            </th>
                            <td>
                                <input type="time" id="notify_time"
                                       name="<?php echo esc_attr(C::OPTION_KEY); ?>[notify_time]"
                                       value="<?php echo esc_attr($notify_time); ?>">
                                <p class="description">
                                    <?php
                                    printf(
                                        /* translators: %s: site timezone name */
                                        esc_html__('Ortszeit der Seite (%s). Die tatsächliche Ausführung hängt an WP-Cron und kann sich verzögern, wenn die Seite wenig besucht wird.', 'auto-quill'),
                                        '<code>' . esc_html(wp_timezone_string()) . '</code>'
                                    );
                                    ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Inhalte', 'auto-quill'); ?></th>
                            <td>
                                <?php /* Marker: a checkbox group with nothing ticked submits no key. */ ?>
                                <input type="hidden"
                                       name="<?php echo esc_attr(C::OPTION_KEY); ?>[notify_events_present]"
                                       value="1">
                                <?php foreach ($event_labels as $event_key => $label): ?>
                                    <label style="display:block; margin-bottom:4px;">
                                        <input type="checkbox"
                                               name="<?php echo esc_attr(C::OPTION_KEY); ?>[notify_events][]"
                                               value="<?php echo esc_attr($event_key); ?>"
                                               <?php checked(in_array($event_key, $notify_events, true)); ?>>
                                        <?php echo esc_html($label); ?>
                                    </label>
                                <?php endforeach; ?>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Empfänger: Benutzer', 'auto-quill'); ?></th>
                            <td>
                                <input type="hidden"
                                       name="<?php echo esc_attr(C::OPTION_KEY); ?>[notify_users_present]"
                                       value="1">
                                <?php if (empty($admin_users)): ?>
                                    <p class="description"><?php esc_html_e('Keine Benutzer mit Administratorrechten gefunden.', 'auto-quill'); ?></p>
                                <?php else: ?>
                                    <div class="auto-quill-user-list">
                                        <?php foreach ($admin_users as $user): ?>
                                            <label>
                                                <input type="checkbox"
                                                       name="<?php echo esc_attr(C::OPTION_KEY); ?>[notify_users][]"
                                                       value="<?php echo (int) $user->ID; ?>"
                                                       <?php checked(in_array((int) $user->ID, array_map('intval', $notify_users), true)); ?>>
                                                <?php echo esc_html($user->display_name); ?>
                                                <span class="description"><?php echo esc_html($user->user_email); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="description">
                                        <?php esc_html_e('Die Adresse wird beim Versand frisch aus dem Benutzerkonto gelesen — eine geänderte Mailadresse wirkt sofort.', 'auto-quill'); ?>
                                        <?php if (count($admin_users) >= 200): ?>
                                            <br><?php esc_html_e('Hinweis: Es werden nur die ersten 200 Benutzer angezeigt.', 'auto-quill'); ?>
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="notify_emails"><?php esc_html_e('Weitere Adressen', 'auto-quill'); ?></label>
                            </th>
                            <td>
                                <textarea id="notify_emails" rows="4" class="large-text code"
                                          name="<?php echo esc_attr(C::OPTION_KEY); ?>[notify_emails]"><?php
                                    echo esc_textarea(implode("\n", $notify_emails));
                                ?></textarea>
                                <p class="description">
                                    <?php esc_html_e('Eine Adresse pro Zeile, auch für Verteiler ohne WordPress-Konto. Ungültige Einträge werden beim Speichern verworfen.', 'auto-quill'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="auto-quill-tab-panel" data-tab="backup" style="display:none;">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Automatische Sicherung', 'auto-quill'); ?></th>
                            <td>
                                <label>
                                    <input type="hidden"
                                           name="<?php echo esc_attr(C::OPTION_KEY); ?>[backup_enabled]"
                                           value="0">
                                    <input type="checkbox"
                                           id="backup_enabled"
                                           name="<?php echo esc_attr(C::OPTION_KEY); ?>[backup_enabled]"
                                           value="1"
                                           <?php checked(!empty($settings['backup_enabled'] ?? C::defaults()['backup_enabled'])); ?>>
                                    <?php esc_html_e('Einstellungen und RSS-Quellen täglich sichern', 'auto-quill'); ?>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="backup_time"><?php esc_html_e('Uhrzeit', 'auto-quill'); ?></label>
                            </th>
                            <td>
                                <input type="time" id="backup_time"
                                       name="<?php echo esc_attr(C::OPTION_KEY); ?>[backup_time]"
                                       value="<?php echo esc_attr((string) ($settings['backup_time'] ?? C::defaults()['backup_time'])); ?>">
                                <p class="description">
                                    <?php
                                    printf(
                                        /* translators: %s: site timezone name */
                                        esc_html__('Ortszeit der Seite (%s).', 'auto-quill'),
                                        '<code>' . esc_html(wp_timezone_string()) . '</code>'
                                    );
                                    ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="backup_keep"><?php esc_html_e('Aufbewahren', 'auto-quill'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="backup_keep" min="1" max="<?php echo (int) C::BACKUP_KEEP_MAX; ?>" step="1"
                                       name="<?php echo esc_attr(C::OPTION_KEY); ?>[backup_keep]"
                                       value="<?php echo esc_attr((string) ($settings['backup_keep'] ?? C::defaults()['backup_keep'])); ?>"
                                       style="width: 100px;">
                                <?php esc_html_e('Sicherungen', 'auto-quill'); ?>
                                <p class="description">
                                    <?php esc_html_e('Wie viele Sicherungen aufgehoben werden (automatische, manuelle und importierte zusammen). Ältere werden gelöscht — beim Verkleinern sofort.', 'auto-quill'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="auto-quill-tab-panel" data-tab="updates" style="display:none;">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <?php esc_html_e('Beta-Modus', 'auto-quill'); ?>
                            </th>
                            <td>
                                <label>
                                    <input type="hidden"
                                           name="<?php echo esc_attr(C::OPTION_KEY); ?>[beta_mode]"
                                           value="0">
                                    <input type="checkbox"
                                           id="beta_mode"
                                           name="<?php echo esc_attr(C::OPTION_KEY); ?>[beta_mode]"
                                           value="1"
                                           <?php checked(!empty($settings['beta_mode'])); ?>>
                                    <?php esc_html_e('Beta-Updates aktivieren (folgt dem main-Branch)', 'auto-quill'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Bei aktiviertem Beta-Modus prüft AutoQuill den main-Branch auf neue Commits und installiert diese als Updates. Ist der Beta-Modus deaktiviert, werden ausschließlich offizielle Releases als Updates angeboten.', 'auto-quill'); ?>
                                </p>
                                <p class="description">
                                    <?php
                                    printf(
                                        /* translators: %s: repository URL */
                                        esc_html__('Quelle: %s', 'auto-quill'),
                                        '<code>' . esc_html(C::UPDATE_REPO_URL) . '</code>'
                                    );
                                    ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="auto-quill-tab-panel" data-tab="debug" style="display:none;">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <?php esc_html_e('Debug-Logging', 'auto-quill'); ?>
                            </th>
                            <td>
                                <label>
                                    <input type="hidden"
                                           name="<?php echo esc_attr(C::OPTION_KEY); ?>[debug_logging]"
                                           value="0">
                                    <input type="checkbox"
                                           id="debug_logging"
                                           name="<?php echo esc_attr(C::OPTION_KEY); ?>[debug_logging]"
                                           value="1"
                                           <?php checked(!empty($settings['debug_logging'])); ?>>
                                    <?php esc_html_e('Ausführliches Logging aktivieren (Info/Debug)', 'auto-quill'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Wenn aktiv, werden auch Info- und Debug-Einträge inkl. (gekürzter) API-Payloads aufgezeichnet. Standard: nur Warnungen und Fehler. Logs sind unter „AutoQuill → Logs" und in der Browser-Konsole sichtbar.', 'auto-quill'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button(); ?>
            </form>

            <div class="auto-quill-tab-panel" data-tab="notify" style="display:none;">
                <div class="auto-quill-test-mail">
                    <h2><?php esc_html_e('Versand testen', 'auto-quill'); ?></h2>
                    <?php $current_recipients = Notifier::recipients(); ?>
                    <p class="description">
                        <?php esc_html_e('Sendet den Bericht der letzten 24 Stunden sofort — oder, wenn es nichts zu berichten gibt, eine kurze Bestätigung. Die Einstellungen müssen dafür gespeichert sein.', 'auto-quill'); ?>
                    </p>
                    <p>
                        <strong><?php esc_html_e('Aktuelle Empfänger:', 'auto-quill'); ?></strong>
                        <?php if (empty($current_recipients)): ?>
                            <span style="color:#a00;"><?php esc_html_e('keine', 'auto-quill'); ?></span>
                        <?php else: ?>
                            <?php echo esc_html(implode(', ', $current_recipients)); ?>
                        <?php endif; ?>
                    </p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field(C::NONCE_TEST_MAIL); ?>
                        <input type="hidden" name="action" value="<?php echo esc_attr(C::ACTION_TEST_MAIL); ?>">
                        <button type="submit" class="button" <?php disabled(empty($current_recipients)); ?>>
                            <?php esc_html_e('Test-Mail an alle Empfänger senden', 'auto-quill'); ?>
                        </button>
                    </form>
                </div>
            </div>

            <div class="auto-quill-tab-panel" data-tab="backup" style="display:none;">
                <?php BackupController::render_panel(); ?>
            </div>

            <div class="auto-quill-tab-panel" data-tab="debug" style="display:none;">
                <?php StatusPanel::render(); ?>
            </div>
        </div>
        <?php
    }
}
