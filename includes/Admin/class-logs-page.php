<?php
namespace AutoQuill\Admin;

use AutoQuill\Core\Constants as C;
use AutoQuill\Core\Logger;

class LogsPage {
    const ACTION_CLEAR  = 'auto_quill_logs_clear';
    const ACTION_EXPORT = 'auto_quill_logs_export';

    public static function boot(): void {
        add_action('admin_post_' . self::ACTION_CLEAR,  [self::class, 'handle_clear']);
        add_action('admin_post_' . self::ACTION_EXPORT, [self::class, 'handle_export']);
    }

    public static function handle_clear(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Zugriff verweigert', 'auto-quill'));
        }
        check_admin_referer(self::ACTION_CLEAR);
        Logger::clear();
        wp_safe_redirect(add_query_arg(['page' => C::LOGS_PAGE_SLUG, 'cleared' => '1'], admin_url('admin.php')));
        exit;
    }

    public static function handle_export(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Zugriff verweigert', 'auto-quill'));
        }
        check_admin_referer(self::ACTION_EXPORT);
        $logs = Logger::query(['limit' => 500]);
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="auto-quill-logs-' . gmdate('Ymd-His') . '.json"');
        echo wp_json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Zugriff verweigert', 'auto-quill'));
        }

        $level   = isset($_GET['level'])  ? sanitize_text_field((string) $_GET['level'])  : '';
        $source  = isset($_GET['source']) ? sanitize_text_field((string) $_GET['source']) : '';
        $window  = isset($_GET['window']) ? sanitize_text_field((string) $_GET['window']) : '24h';

        $since = '';
        switch ($window) {
            case '1h':  $since = gmdate('Y-m-d H:i:s', time() - HOUR_IN_SECONDS); break;
            case '7d':  $since = gmdate('Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS); break;
            case 'all': $since = ''; break;
            case '24h':
            default:    $since = gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS); break;
        }

        $logs = Logger::query([
            'level'  => $level,
            'source' => $source,
            'since'  => $since,
            'limit'  => 500,
        ]);

        $sources       = Logger::known_sources();
        $debug_enabled = Logger::is_debug_enabled();
        $cleared       = !empty($_GET['cleared']);
        ?>
        <div class="wrap auto-quill-logs">
            <h1><?php esc_html_e('AutoQuill Logs', 'auto-quill'); ?></h1>

            <?php if ($cleared): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Alle Logs wurden gelöscht.', 'auto-quill'); ?></p></div>
            <?php endif; ?>

            <?php if (!$debug_enabled): ?>
                <div class="notice notice-warning">
                    <p>
                        <?php
                        $settings_url = admin_url('admin.php?page=' . C::SETTINGS_PAGE_SLUG);
                        printf(
                            /* translators: %s: settings url */
                            wp_kses(__('Debug-Logging ist <strong>deaktiviert</strong>. Es werden nur Warnungen und Fehler aufgezeichnet. <a href="%s">In den Einstellungen aktivieren</a>.', 'auto-quill'), ['strong' => [], 'a' => ['href' => []]]),
                            esc_url($settings_url)
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <form method="get" style="margin: 16px 0; display: flex; gap: 8px; align-items: end; flex-wrap: wrap;">
                <input type="hidden" name="page" value="<?php echo esc_attr(C::LOGS_PAGE_SLUG); ?>">
                <label>
                    <?php esc_html_e('Level', 'auto-quill'); ?><br>
                    <select name="level">
                        <option value=""><?php esc_html_e('Alle', 'auto-quill'); ?></option>
                        <?php foreach (['error', 'warning', 'info', 'debug'] as $lv): ?>
                            <option value="<?php echo esc_attr($lv); ?>" <?php selected($level, $lv); ?>><?php echo esc_html(ucfirst($lv)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <?php esc_html_e('Quelle', 'auto-quill'); ?><br>
                    <select name="source">
                        <option value=""><?php esc_html_e('Alle', 'auto-quill'); ?></option>
                        <?php foreach ($sources as $src): ?>
                            <option value="<?php echo esc_attr($src); ?>" <?php selected($source, $src); ?>><?php echo esc_html($src); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <?php esc_html_e('Zeitraum', 'auto-quill'); ?><br>
                    <select name="window">
                        <option value="1h"  <?php selected($window, '1h'); ?>><?php esc_html_e('Letzte Stunde', 'auto-quill'); ?></option>
                        <option value="24h" <?php selected($window, '24h'); ?>><?php esc_html_e('Letzte 24 h', 'auto-quill'); ?></option>
                        <option value="7d"  <?php selected($window, '7d'); ?>><?php esc_html_e('Letzte 7 Tage', 'auto-quill'); ?></option>
                        <option value="all" <?php selected($window, 'all'); ?>><?php esc_html_e('Alle', 'auto-quill'); ?></option>
                    </select>
                </label>
                <button type="submit" class="button"><?php esc_html_e('Filtern', 'auto-quill'); ?></button>
            </form>

            <div style="display: flex; gap: 8px; margin-bottom: 16px;">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Wirklich alle Logs löschen?', 'auto-quill')); ?>');">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION_CLEAR); ?>">
                    <?php wp_nonce_field(self::ACTION_CLEAR); ?>
                    <button type="submit" class="button button-secondary"><?php esc_html_e('Alle Logs löschen', 'auto-quill'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION_EXPORT); ?>">
                    <?php wp_nonce_field(self::ACTION_EXPORT); ?>
                    <button type="submit" class="button"><?php esc_html_e('Als JSON exportieren', 'auto-quill'); ?></button>
                </form>
            </div>

            <p class="description">
                <?php
                printf(
                    /* translators: %d: number of log entries */
                    esc_html(_n('%d Eintrag.', '%d Einträge.', count($logs), 'auto-quill')),
                    count($logs)
                );
                ?>
                <?php esc_html_e('Tipp: Browser-Konsole öffnen — Logs werden auf jeder AutoQuill-Seite live gestreamt.', 'auto-quill'); ?>
            </p>

            <?php if (empty($logs)): ?>
                <p><em><?php esc_html_e('Keine Einträge im gewählten Zeitraum.', 'auto-quill'); ?></em></p>
            <?php else: ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th style="width: 160px;"><?php esc_html_e('Zeit', 'auto-quill'); ?></th>
                            <th style="width: 80px;"><?php esc_html_e('Level', 'auto-quill'); ?></th>
                            <th style="width: 120px;"><?php esc_html_e('Quelle', 'auto-quill'); ?></th>
                            <th><?php esc_html_e('Nachricht', 'auto-quill'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_reverse($logs) as $log): ?>
                            <?php $color = self::level_color($log['level']); ?>
                            <tr>
                                <td><code><?php echo esc_html($log['created_at']); ?></code></td>
                                <td><span style="display:inline-block;padding:2px 8px;border-radius:3px;background:<?php echo esc_attr($color); ?>;color:#fff;font-weight:600;font-size:11px;text-transform:uppercase;"><?php echo esc_html($log['level']); ?></span></td>
                                <td><code><?php echo esc_html($log['source']); ?></code></td>
                                <td>
                                    <div><?php echo esc_html($log['message']); ?></div>
                                    <?php if (!empty($log['context'])): ?>
                                        <details style="margin-top:4px;">
                                            <summary style="cursor:pointer;color:#2271b1;"><?php esc_html_e('Context anzeigen', 'auto-quill'); ?></summary>
                                            <pre style="white-space:pre-wrap;background:#f6f7f7;padding:8px;border:1px solid #dcdcde;font-size:11px;max-height:300px;overflow:auto;"><?php echo esc_html(wp_json_encode($log['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></pre>
                                        </details>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function level_color(string $level): string {
        switch ($level) {
            case 'error':   return '#b32d2e';
            case 'warning': return '#dba617';
            case 'info':    return '#2271b1';
            case 'debug':   return '#646970';
            default:        return '#646970';
        }
    }
}
