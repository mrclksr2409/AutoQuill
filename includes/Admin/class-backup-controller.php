<?php
namespace AutoQuill\Admin;

use AutoQuill\Core\Backup;
use AutoQuill\Core\Constants as C;
use AutoQuill\Core\Logger;
use AutoQuill\Database\SourcesRepository;

/**
 * Backup actions on the settings page. Same admin_post_ pattern as
 * SourcesController; the storage itself lives in Core\Backup.
 */
class BackupController {
    const MAX_IMPORT_BYTES = 1048576;

    public static function boot(): void {
        add_action('admin_post_' . C::ACTION_BACKUP_NOW,      [self::class, 'handle_now']);
        add_action('admin_post_' . C::ACTION_BACKUP_RESTORE,  [self::class, 'handle_restore']);
        add_action('admin_post_' . C::ACTION_BACKUP_DELETE,   [self::class, 'handle_delete']);
        add_action('admin_post_' . C::ACTION_BACKUP_DOWNLOAD, [self::class, 'handle_download']);
        add_action('admin_post_' . C::ACTION_BACKUP_IMPORT,   [self::class, 'handle_import']);
    }

    private static function guard(string $action): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Zugriff verweigert', 'auto-quill'));
        }
        check_admin_referer($action);
    }

    private static function back(): void {
        wp_safe_redirect(admin_url('admin.php?page=' . C::SETTINGS_PAGE_SLUG) . '#tab-backup');
        exit;
    }

    private static function requested_id(): string {
        return sanitize_key(wp_unslash($_REQUEST['backup_id'] ?? ''));
    }

    public static function handle_now(): void {
        self::guard(C::ACTION_BACKUP_NOW);
        Backup::create('manual');
        Notices::success(__('Sicherung erstellt.', 'auto-quill'));
        self::back();
    }

    public static function handle_delete(): void {
        self::guard(C::ACTION_BACKUP_DELETE);
        if (Backup::delete(self::requested_id())) {
            Notices::success(__('Sicherung gelöscht.', 'auto-quill'));
        } else {
            Notices::error(__('Sicherung nicht gefunden.', 'auto-quill'));
        }
        self::back();
    }

    public static function handle_restore(): void {
        self::guard(C::ACTION_BACKUP_RESTORE);

        $backup = Backup::find(self::requested_id());
        if (!$backup) {
            Notices::error(__('Sicherung nicht gefunden.', 'auto-quill'));
            self::back();
        }

        // The current state first, so a restore can itself be undone.
        Backup::create('pre-restore');

        self::apply_settings((array) ($backup['settings'] ?? []));
        $stats = self::apply_sources((array) ($backup['sources'] ?? []));

        Logger::info('backup', 'Sicherung wiederhergestellt', ['id' => $backup['id']] + $stats);

        Notices::success(sprintf(
            /* translators: 1: date of the backup, 2: feeds added, 3: feeds updated */
            __('Sicherung vom %1$s wiederhergestellt (RSS-Quellen: %2$d neu, %3$d aktualisiert). Der vorherige Stand wurde als eigene Sicherung abgelegt.', 'auto-quill'),
            wp_date('d.m.Y H:i', (int) $backup['created']),
            $stats['inserted'],
            $stats['updated']
        ));
        self::back();
    }

    /**
     * Goes through Settings::sanitize() (register_setting hooks it into
     * update_option), so a restored or imported value is validated exactly
     * like a form submission. The array is reshaped into what the form posts.
     */
    private static function apply_settings(array $settings): void {
        $input = $settings;

        if (isset($input['notify_emails']) && is_array($input['notify_emails'])) {
            $input['notify_emails'] = implode("\n", array_map('strval', $input['notify_emails']));
        }
        if (array_key_exists('notify_users', $settings)) {
            $input['notify_users_present'] = 1;
        }
        if (array_key_exists('notify_events', $settings)) {
            $input['notify_events_present'] = 1;
        }
        // Missing or empty keys keep the current ones (Settings::sanitize).
        foreach (Backup::SECRET_KEYS as $key) {
            if (!isset($input[$key])) {
                $input[$key] = '';
            }
        }

        update_option(C::OPTION_KEY, $input);
    }

    /** @return array{inserted: int, updated: int, failed: int} */
    private static function apply_sources(array $sources): array {
        $repo  = new SourcesRepository();
        $stats = ['inserted' => 0, 'updated' => 0, 'failed' => 0];

        foreach ($sources as $source) {
            $url = esc_url_raw((string) ($source['feed_url'] ?? ''), ['http', 'https']);
            if ($url === '') {
                continue;
            }
            $title  = sanitize_text_field((string) ($source['title'] ?? '')) ?: $url;
            $result = $repo->upsert_by_url($title, $url, !empty($source['is_active']));
            $stats[$result]++;
        }

        return $stats;
    }

    public static function handle_download(): void {
        self::guard(C::ACTION_BACKUP_DOWNLOAD);

        $backup = Backup::find(self::requested_id());
        if (!$backup) {
            Notices::error(__('Sicherung nicht gefunden.', 'auto-quill'));
            self::back();
        }

        $filename = 'auto-quill-backup-' . wp_date('Y-m-d-His', (int) $backup['created']) . '.json';

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo wp_json_encode(Backup::export($backup), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function handle_import(): void {
        self::guard(C::ACTION_BACKUP_IMPORT);

        $file = $_FILES['backup_file'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || !is_uploaded_file((string) $file['tmp_name'])
        ) {
            Notices::error(__('Es wurde keine Datei hochgeladen.', 'auto-quill'));
            self::back();
        }
        if ((int) $file['size'] > self::MAX_IMPORT_BYTES) {
            Notices::error(__('Die Datei ist zu groß (max. 1 MB).', 'auto-quill'));
            self::back();
        }

        $data = Backup::parse_export((string) file_get_contents((string) $file['tmp_name']));
        if (is_wp_error($data)) {
            Notices::error($data->get_error_message());
            self::back();
        }

        Backup::create('import', $data);
        Notices::success(__('Sicherung importiert. Sie steht jetzt in der Liste und kann dort wiederhergestellt werden.', 'auto-quill'));
        self::back();
    }

    // ---------------------------------------------------------------- render

    private static function trigger_label(string $trigger): string {
        switch ($trigger) {
            case 'auto':
                return __('Automatisch', 'auto-quill');
            case 'pre-restore':
                return __('Vor Wiederherstellung', 'auto-quill');
            case 'import':
                return __('Import', 'auto-quill');
            default:
                return __('Manuell', 'auto-quill');
        }
    }

    private static function action_form(string $action, string $id, string $label, string $class = 'button', string $confirm = ''): void {
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;"
              <?php if ($confirm !== ''): ?>onsubmit="return confirm(<?php echo esc_attr(wp_json_encode($confirm)); ?>);"<?php endif; ?>>
            <?php wp_nonce_field($action); ?>
            <input type="hidden" name="action" value="<?php echo esc_attr($action); ?>">
            <input type="hidden" name="backup_id" value="<?php echo esc_attr($id); ?>">
            <button type="submit" class="<?php echo esc_attr($class); ?>"><?php echo esc_html($label); ?></button>
        </form>
        <?php
    }

    /** Lives outside the settings <form>: forms cannot nest. */
    public static function render_panel(): void {
        $backups = Backup::all();
        ?>
        <div class="auto-quill-test-mail auto-quill-backups">
            <h2><?php esc_html_e('Vorhandene Sicherungen', 'auto-quill'); ?></h2>
            <p class="description">
                <?php esc_html_e('Gesichert werden alle Einstellungen (inkl. API-Schlüssel) und die RSS-Quellen — keine Artikel, Themen oder Logs. Beim Wiederherstellen werden fehlende RSS-Quellen angelegt und vorhandene aktualisiert, aber keine gelöscht. Der Stand davor wird automatisch als eigene Sicherung abgelegt.', 'auto-quill'); ?>
            </p>

            <p>
                <?php self::action_form(C::ACTION_BACKUP_NOW, '', __('Jetzt sichern', 'auto-quill'), 'button button-secondary'); ?>
            </p>

            <?php if (empty($backups)): ?>
                <p><?php esc_html_e('Noch keine Sicherungen vorhanden.', 'auto-quill'); ?></p>
            <?php else: ?>
                <table class="widefat striped" style="max-width:900px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Datum', 'auto-quill'); ?></th>
                            <th><?php esc_html_e('Anlass', 'auto-quill'); ?></th>
                            <th><?php esc_html_e('Plugin-Version', 'auto-quill'); ?></th>
                            <th><?php esc_html_e('RSS-Quellen', 'auto-quill'); ?></th>
                            <th><?php esc_html_e('Aktionen', 'auto-quill'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backups as $backup):
                            $id       = (string) ($backup['id'] ?? '');
                            $date     = wp_date('d.m.Y H:i', (int) ($backup['created'] ?? 0));
                            $download = wp_nonce_url(
                                admin_url('admin-post.php?action=' . C::ACTION_BACKUP_DOWNLOAD . '&backup_id=' . rawurlencode($id)),
                                C::ACTION_BACKUP_DOWNLOAD
                            );
                            ?>
                            <tr>
                                <td><?php echo esc_html($date); ?></td>
                                <td><?php echo esc_html(self::trigger_label((string) ($backup['trigger'] ?? ''))); ?></td>
                                <td><code><?php echo esc_html((string) ($backup['version'] ?? '–')); ?></code></td>
                                <td><?php echo (int) count((array) ($backup['sources'] ?? [])); ?></td>
                                <td class="auto-quill-backup-actions">
                                    <?php
                                    self::action_form(
                                        C::ACTION_BACKUP_RESTORE,
                                        $id,
                                        __('Wiederherstellen', 'auto-quill'),
                                        'button button-primary',
                                        /* translators: %s: backup date */
                                        sprintf(__('Einstellungen und RSS-Quellen auf den Stand vom %s zurücksetzen?', 'auto-quill'), $date)
                                    );
                                    ?>
                                    <a class="button" href="<?php echo esc_url($download); ?>"><?php esc_html_e('Herunterladen', 'auto-quill'); ?></a>
                                    <?php
                                    self::action_form(
                                        C::ACTION_BACKUP_DELETE,
                                        $id,
                                        __('Löschen', 'auto-quill'),
                                        'button button-link-delete',
                                        __('Diese Sicherung endgültig löschen?', 'auto-quill')
                                    );
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h2 style="margin-top:1.5em;"><?php esc_html_e('Sicherung importieren', 'auto-quill'); ?></h2>
            <p class="description">
                <?php esc_html_e('Heruntergeladene Dateien enthalten aus Sicherheitsgründen keine API-Schlüssel. Beim Wiederherstellen einer importierten Sicherung bleiben die aktuell hinterlegten Schlüssel erhalten.', 'auto-quill'); ?>
            </p>
            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field(C::ACTION_BACKUP_IMPORT); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr(C::ACTION_BACKUP_IMPORT); ?>">
                <input type="file" name="backup_file" accept="application/json,.json" required>
                <button type="submit" class="button"><?php esc_html_e('Importieren', 'auto-quill'); ?></button>
            </form>
        </div>
        <?php
    }
}
