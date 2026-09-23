<?php
namespace AutoQuill\Core;

use AutoQuill\Admin\AdminMenu;
use AutoQuill\Admin\Dashboard;
use AutoQuill\Admin\LogsPage;
use AutoQuill\Admin\PostMetaBox;
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
        LogsPage::boot();
        PostMetaBox::boot();
        Notifier::boot();
        RestController::boot();
        Updater::boot();

        Scheduler::boot();
        Scheduler::ensure_scheduled();

        // Gated on the setting, unlike fetch and selection: an upgrade must never
        // start sending mail on its own.
        Notifier::ensure_scheduled();
    }

    public static function load_textdomain(): void {
        load_plugin_textdomain(
            'auto-quill',
            false,
            dirname(plugin_basename(AUTO_QUILL_PLUGIN_DIR . 'auto-quill.php')) . '/languages'
        );
    }
}
