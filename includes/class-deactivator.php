<?php
namespace AutoQuill;

class Deactivator {
    public static function deactivate() {
        // WordPress Cron Events deaktivieren
        wp_clear_scheduled_hook('auto_quill_daily_fetch');
        wp_clear_scheduled_hook('auto_quill_daily_select');

        // Rewrite-Regeln flush
        flush_rewrite_rules();
    }
}
