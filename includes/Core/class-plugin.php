<?php
namespace AutoQuill\Core;

use AutoQuill\Admin\AdminMenu;
use AutoQuill\Admin\BackupController;
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
        add_filter('plugin_row_meta', [self::class, 'row_meta'], 10, 2);

        // Before Settings registers its sanitize callback, so the move is a
        // plain write and not a round trip through the form validation.
        Constants::maybe_migrate_api_key();
        add_action('admin_init', [Schema::class, 'ensure_tables'], 1);

        Settings::boot();
        SourcesController::boot();
        BackupController::boot();
        Dashboard::boot();
        AdminMenu::boot();
        LogsPage::boot();
        PostMetaBox::boot();
        Notifier::boot();
        RestController::boot();
        Updater::boot();

        Scheduler::boot();
        Scheduler::ensure_scheduled();

        Backup::boot();
        Backup::ensure_scheduled();

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

    /** Wiki link in the plugin's row on the Plugins screen. */
    public static function row_meta($links, $file) {
        if ($file === plugin_basename(AUTO_QUILL_PLUGIN_DIR . 'auto-quill.php')) {
            $links[] = sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                esc_url('https://github.com/mrclksr2409/AutoQuill/wiki'),
                esc_html__('Wiki', 'auto-quill')
            );
        }
        return $links;
    }
}
