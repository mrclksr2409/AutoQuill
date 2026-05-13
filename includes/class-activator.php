<?php
namespace AutoQuill;

class Activator {
    public static function activate() {
        Database\Schema::create_tables();

        if (!get_option('auto_quill_settings')) {
            update_option('auto_quill_settings', [
                'ai_provider' => 'openai',
                'ai_api_key' => '',
                'posts_per_day' => 1,
                'auto_publish' => false,
                'post_status' => 'draft',
            ]);
        }

        // Rewrite-Regeln flush
        flush_rewrite_rules();
    }
}
