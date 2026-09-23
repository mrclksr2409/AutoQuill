<?php
namespace AutoQuill\Core;

use AutoQuill\Database\SourcesRepository;

/**
 * Snapshots of the plugin configuration: the settings option plus the RSS
 * source list. Articles, topics and logs are data, not configuration, and are
 * deliberately left out.
 *
 * Stored in a non-autoloaded option rather than as files: a file under
 * wp-content/uploads would be publicly reachable and carries the API keys.
 */
class Backup {
    /** wp-cron.php can be entered concurrently by two visitors. */
    const DOUBLE_FIRE_GUARD = 3600;

    /** Never part of a downloaded file. */
    const SECRET_KEYS = ['ai_api_key', 'pixabay_api_key'];

    const TRIGGERS = ['auto', 'manual', 'pre-restore', 'import'];

    public static function boot(): void {
        add_action(Constants::CRON_BACKUP, [self::class, 'run_scheduled']);
        add_action('update_option_' . Constants::OPTION_KEY, [self::class, 'on_settings_updated'], 10, 2);
    }

    private static function settings(): array {
        $stored = get_option(Constants::OPTION_KEY, []);
        return array_merge(Constants::defaults(), is_array($stored) ? $stored : []);
    }

    public static function is_enabled(): bool {
        return !empty(self::settings()['backup_enabled']);
    }

    public static function keep(): int {
        return max(1, min(Constants::BACKUP_KEEP_MAX, (int) self::settings()['backup_keep']));
    }

    // ------------------------------------------------------------ scheduling

    public static function next_run_timestamp(?int $from = null): int {
        return Scheduler::next_occurrence(
            (string) (self::settings()['backup_time'] ?? ''),
            Constants::defaults()['backup_time'],
            $from
        );
    }

    public static function reschedule(): void {
        wp_clear_scheduled_hook(Constants::CRON_BACKUP);
        if (self::is_enabled()) {
            wp_schedule_single_event(self::next_run_timestamp(), Constants::CRON_BACKUP);
        }
    }

    /** Self-heal from Plugin::boot(), gated on the setting. */
    public static function ensure_scheduled(): void {
        $scheduled = wp_next_scheduled(Constants::CRON_BACKUP);

        if (!self::is_enabled()) {
            if ($scheduled) {
                wp_clear_scheduled_hook(Constants::CRON_BACKUP);
            }
            return;
        }

        if (!$scheduled) {
            wp_schedule_single_event(self::next_run_timestamp(), Constants::CRON_BACKUP);
        }
    }

    public static function on_settings_updated($old, $new): void {
        $old      = is_array($old) ? $old : [];
        $new      = is_array($new) ? $new : [];
        $defaults = Constants::defaults();
        $changed  = static fn(string $k): bool => ($old[$k] ?? $defaults[$k]) !== ($new[$k] ?? $defaults[$k]);

        if ($changed('backup_enabled') || $changed('backup_time')) {
            self::reschedule();
        }
        // A lowered limit applies now, not only after the next backup.
        if ($changed('backup_keep')) {
            self::prune();
        }
    }

    public static function run_scheduled(): void {
        $now = time();

        // Re-chain FIRST: a fatal further down must not break the schedule.
        if (!self::is_enabled()) {
            return;
        }
        wp_schedule_single_event(self::next_run_timestamp($now + 60), Constants::CRON_BACKUP);

        foreach (self::all() as $backup) {
            if ($backup['trigger'] === 'auto' && ($now - (int) $backup['created']) < self::DOUBLE_FIRE_GUARD) {
                Logger::info('backup', 'Automatische Sicherung übersprungen (zu kurz nach der letzten)');
                return;
            }
        }

        self::create('auto');
    }

    // --------------------------------------------------------------- storage

    /**
     * @return array<int, array{id: string, created: int, trigger: string, version: string, settings: array, sources: array}>
     */
    public static function all(): array {
        $list = get_option(Constants::OPTION_BACKUPS, []);
        return is_array($list) ? array_values(array_filter($list, 'is_array')) : [];
    }

    public static function find(string $id): ?array {
        foreach (self::all() as $backup) {
            if ((string) ($backup['id'] ?? '') === $id) {
                return $backup;
            }
        }
        return null;
    }

    /** Snapshot of the current configuration. */
    public static function snapshot(): array {
        $settings = get_option(Constants::OPTION_KEY, []);

        $sources = [];
        foreach ((new SourcesRepository())->all() as $row) {
            $sources[] = [
                'title'     => (string) $row->title,
                'feed_url'  => (string) $row->feed_url,
                'is_active' => (int) $row->is_active ? 1 : 0,
            ];
        }

        return [
            'settings' => is_array($settings) ? $settings : [],
            'sources'  => $sources,
        ];
    }

    /**
     * @param array|null $data settings/sources to store instead of the current state (import).
     */
    public static function create(string $trigger, ?array $data = null): array {
        $data   = $data ?? self::snapshot();
        $backup = [
            'id'       => gmdate('YmdHis') . '-' . strtolower(wp_generate_password(6, false, false)),
            'created'  => time(),
            'trigger'  => in_array($trigger, self::TRIGGERS, true) ? $trigger : 'manual',
            'version'  => defined('AUTO_QUILL_VERSION') ? AUTO_QUILL_VERSION : '',
            'settings' => (array) ($data['settings'] ?? []),
            'sources'  => (array) ($data['sources'] ?? []),
        ];

        $list = self::all();
        array_unshift($list, $backup);
        self::store($list);

        Logger::info('backup', 'Sicherung erstellt', [
            'id'      => $backup['id'],
            'trigger' => $backup['trigger'],
            'sources' => count($backup['sources']),
        ]);

        return $backup;
    }

    public static function delete(string $id): bool {
        $list = self::all();
        $kept = array_values(array_filter($list, static fn($b) => (string) ($b['id'] ?? '') !== $id));
        if (count($kept) === count($list)) {
            return false;
        }
        self::store($kept);
        return true;
    }

    public static function prune(): void {
        self::store(self::all());
    }

    private static function store(array $list): void {
        usort($list, static fn($a, $b) => (int) ($b['created'] ?? 0) <=> (int) ($a['created'] ?? 0));
        $list = array_slice($list, 0, self::keep());
        // autoload=false: loading every backup on every request would be waste.
        update_option(Constants::OPTION_BACKUPS, $list, false);
    }

    /** Backup as a downloadable document, API keys removed. */
    public static function export(array $backup): array {
        $settings = (array) ($backup['settings'] ?? []);
        foreach (self::SECRET_KEYS as $key) {
            unset($settings[$key]);
        }

        return [
            'plugin'   => 'auto-quill',
            'format'   => 1,
            'created'  => gmdate('c', (int) ($backup['created'] ?? time())),
            'version'  => (string) ($backup['version'] ?? ''),
            'settings' => $settings,
            'sources'  => (array) ($backup['sources'] ?? []),
        ];
    }

    /**
     * Parses an uploaded export. Only structure is checked here; the values
     * go through Settings::sanitize() when the backup is restored.
     *
     * @return array{settings: array, sources: array}|\WP_Error
     */
    public static function parse_export(string $json) {
        $data = json_decode($json, true);

        if (!is_array($data) || ($data['plugin'] ?? '') !== 'auto-quill' || !is_array($data['settings'] ?? null)) {
            return new \WP_Error('invalid_backup', __('Die Datei ist keine gültige AutoQuill-Sicherung.', 'auto-quill'));
        }

        $settings = array_intersect_key($data['settings'], Constants::defaults());
        foreach (self::SECRET_KEYS as $key) {
            unset($settings[$key]);
        }

        $sources = [];
        foreach ((array) ($data['sources'] ?? []) as $source) {
            if (!is_array($source)) {
                continue;
            }
            $url = esc_url_raw((string) ($source['feed_url'] ?? ''), ['http', 'https']);
            if ($url === '') {
                continue;
            }
            $sources[] = [
                'title'     => sanitize_text_field((string) ($source['title'] ?? '')) ?: $url,
                'feed_url'  => $url,
                'is_active' => !empty($source['is_active']) ? 1 : 0,
            ];
        }

        return ['settings' => $settings, 'sources' => $sources];
    }
}
