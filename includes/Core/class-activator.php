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

        Scheduler::ensure_scheduled();

        flush_rewrite_rules();
    }
}
