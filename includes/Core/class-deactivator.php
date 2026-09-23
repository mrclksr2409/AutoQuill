<?php
namespace AutoQuill\Core;

class Deactivator {
    public static function deactivate(): void {
        wp_clear_scheduled_hook(Constants::CRON_FETCH);
        wp_clear_scheduled_hook(Constants::CRON_SELECT);
        wp_clear_scheduled_hook(Constants::CRON_DIGEST);
        wp_clear_scheduled_hook(Constants::CRON_BACKUP);
        flush_rewrite_rules();
    }
}
