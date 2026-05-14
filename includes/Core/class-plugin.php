<?php
namespace AutoQuill\Core;

use AutoQuill\Admin\AdminMenu;
use AutoQuill\Admin\Dashboard;
use AutoQuill\Admin\Settings;
use AutoQuill\Admin\SourcesController;
use AutoQuill\Database\Schema;
use AutoQuill\Rest\RestController;

class Plugin {
    public static function boot(): void {
        add_action('init', [self::class, 'load_textdomain']);
        add_action('admin_init', [Schema::class, 'ensure_tables'], 1);

        Settings::boot();
        SourcesController::boot();
        Dashboard::boot();
        AdminMenu::boot();
        RestController::boot();

        add_action(Constants::CRON_FETCH,  ['\AutoQuill\RSS\Fetcher',  'fetch_feeds']);
        add_action(Constants::CRON_SELECT, ['\AutoQuill\AI\Selector',  'select_top_topics']);

        if (!wp_next_scheduled(Constants::CRON_FETCH)) {
            wp_schedule_event(time(), 'daily', Constants::CRON_FETCH);
        }
        if (!wp_next_scheduled(Constants::CRON_SELECT)) {
            wp_schedule_event(time() + 3600, 'daily', Constants::CRON_SELECT);
        }
    }

    public static function load_textdomain(): void {
        load_plugin_textdomain(
            'auto-quill',
            false,
            dirname(plugin_basename(AUTO_QUILL_PLUGIN_DIR . 'auto-quill.php')) . '/languages'
        );
    }
}
