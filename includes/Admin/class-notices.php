<?php
namespace AutoQuill\Admin;

use AutoQuill\Core\Constants as C;

class Notices {
    public static function success(string $message): void {
        self::set('success', $message);
    }

    public static function error(string $message): void {
        self::set('error', $message);
    }

    public static function info(string $message): void {
        self::set('info', $message);
    }

    private static function set(string $type, string $message): void {
        $key = sprintf(C::NOTICE_KEY_FMT, get_current_user_id());
        set_transient($key, ['type' => $type, 'msg' => $message], 30);
    }

    public static function flush(): void {
        $key = sprintf(C::NOTICE_KEY_FMT, get_current_user_id());
        $n = get_transient($key);
        if (!$n) {
            return;
        }
        delete_transient($key);
        $type = in_array($n['type'] ?? 'info', ['success', 'error', 'warning', 'info'], true)
            ? $n['type'] : 'info';
        printf(
            '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
            esc_attr($type),
            esc_html($n['msg'] ?? '')
        );
    }
}
