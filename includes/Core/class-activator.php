<?php
namespace AutoQuill\Core;

use AutoQuill\Database\Schema;

class Activator {
    public static function activate(): void {
        Schema::create_tables();

        $existing = get_option(Constants::OPTION_KEY);
        if (!is_array($existing) || empty($existing)) {
            update_option(Constants::OPTION_KEY, Constants::defaults());
        }

        if (!wp_next_scheduled(Constants::CRON_FETCH)) {
            wp_schedule_event(time(), 'daily', Constants::CRON_FETCH);
        }
        if (!wp_next_scheduled(Constants::CRON_SELECT)) {
            wp_schedule_event(time() + 3600, 'daily', Constants::CRON_SELECT);
        }

        flush_rewrite_rules();
    }
}
