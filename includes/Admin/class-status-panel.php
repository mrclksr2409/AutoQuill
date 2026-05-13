<?php
namespace AutoQuill\Admin;

use AutoQuill\Core\Constants as C;
use AutoQuill\Database\Schema;

class StatusPanel {
    public static function render(): void {
        $tables  = Schema::table_status();
        $version = get_option(C::DB_VERSION_KEY, '–');
        $option  = get_option(C::OPTION_KEY, []);
        if (!is_array($option)) {
            $option = [];
        }
        $masked = $option;
        if (!empty($masked['ai_api_key'])) {
            $masked['ai_api_key'] = self::mask((string) $masked['ai_api_key']);
        }

        $next_fetch  = wp_next_scheduled(C::CRON_FETCH);
        $next_select = wp_next_scheduled(C::CRON_SELECT);
        ?>
        <h2 style="margin-top:2em;"><?php esc_html_e('Status', 'auto-quill'); ?></h2>
        <table class="widefat striped" style="max-width:720px;">
            <tbody>
                <tr>
                    <th style="width:220px;"><?php esc_html_e('DB-Version', 'auto-quill'); ?></th>
                    <td>
                        <code><?php echo esc_html((string) $version); ?></code>
                        <?php if ((string) $version !== C::DB_VERSION): ?>
                            <span style="color:#a00;">
                                (<?php echo esc_html__('erwartet', 'auto-quill') . ' ' . esc_html(C::DB_VERSION); ?>)
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php foreach ($tables as $t): ?>
                    <tr>
                        <th><code><?php echo esc_html($t['name']); ?></code></th>
                        <td>
                            <?php if ($t['exists']): ?>
                                <span style="color:green;">✓</span>
                                <?php echo esc_html(sprintf(_n('%d Zeile', '%d Zeilen', $t['count'], 'auto-quill'), $t['count'])); ?>
                            <?php else: ?>
                                <span style="color:#a00;">✗ <?php esc_html_e('fehlt', 'auto-quill'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <th><?php echo esc_html(C::OPTION_KEY); ?></th>
                    <td><pre style="white-space:pre-wrap;margin:0;"><?php
                        echo esc_html(wp_json_encode($masked, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    ?></pre></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Nächster Fetch', 'auto-quill'); ?></th>
                    <td>
                        <?php echo $next_fetch ? esc_html(date_i18n('Y-m-d H:i', $next_fetch)) : esc_html__('nicht geplant', 'auto-quill'); ?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Nächste Themen-Auswahl', 'auto-quill'); ?></th>
                    <td>
                        <?php echo $next_select ? esc_html(date_i18n('Y-m-d H:i', $next_select)) : esc_html__('nicht geplant', 'auto-quill'); ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    private static function mask(string $key): string {
        $len = strlen($key);
        if ($len <= 6) {
            return str_repeat('•', $len);
        }
        return substr($key, 0, 3) . str_repeat('•', max(3, $len - 6)) . substr($key, -3);
    }
}
