<?php
namespace AutoQuill\Core;

class Plugin {
    private static $instance;

    public static function init() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->setup_hooks();
        \AutoQuill\Database\Schema::maybe_upgrade();
    }

    private function setup_hooks() {
        // Admin Pages
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Settings API + Persistenz-Handler
        add_action('admin_init', ['\AutoQuill\Admin\AdminPage', 'register_settings']);
        add_action('admin_post_auto_quill_add_source',    ['\AutoQuill\Admin\AdminPage', 'handle_add_source']);
        add_action('admin_post_auto_quill_delete_source', ['\AutoQuill\Admin\AdminPage', 'handle_delete_source']);
        add_action('wp_ajax_auto_quill_fetch_now',        ['\AutoQuill\Admin\AdminPage', 'handle_fetch_now']);

        // WordPress Cron
        add_action('auto_quill_daily_fetch', ['\AutoQuill\RSS\Fetcher', 'fetch_feeds']);
        add_action('auto_quill_daily_select', ['\AutoQuill\AI\Selector', 'select_top_topics']);

        // Aktiviere Cron beim Aktivieren
        if (!wp_next_scheduled('auto_quill_daily_fetch')) {
            wp_schedule_event(time(), 'daily', 'auto_quill_daily_fetch');
        }
        if (!wp_next_scheduled('auto_quill_daily_select')) {
            wp_schedule_event(time() + 3600, 'daily', 'auto_quill_daily_select');
        }

        // Rest API
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    private function load_dependencies() {
        // Core Classes
        require_once AUTO_QUILL_INC_DIR . 'class-database.php';
        require_once AUTO_QUILL_INC_DIR . 'Database/class-schema.php';

        // RSS Classes
        require_once AUTO_QUILL_INC_DIR . 'RSS/class-fetcher.php';

        // AI Classes
        require_once AUTO_QUILL_INC_DIR . 'AI/class-selector.php';
        require_once AUTO_QUILL_INC_DIR . 'AI/class-writer.php';

        // Admin Classes
        require_once AUTO_QUILL_INC_DIR . 'Admin/class-admin-page.php';
    }

    public function register_admin_menu() {
        add_menu_page(
            __('AutoQuill', 'auto-quill'),
            __('AutoQuill', 'auto-quill'),
            'manage_options',
            'auto-quill',
            ['\AutoQuill\Admin\AdminPage', 'render_main_page'],
            'dashicons-rss',
            90
        );

        add_submenu_page(
            'auto-quill',
            __('RSS Quellen', 'auto-quill'),
            __('RSS Quellen', 'auto-quill'),
            'manage_options',
            'auto-quill-sources',
            ['\AutoQuill\Admin\AdminPage', 'render_sources_page']
        );

        add_submenu_page(
            'auto-quill',
            __('Einstellungen', 'auto-quill'),
            __('Einstellungen', 'auto-quill'),
            'manage_options',
            'auto-quill-settings',
            ['\AutoQuill\Admin\AdminPage', 'render_settings_page']
        );
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'auto-quill') === false) {
            return;
        }

        wp_enqueue_style(
            'auto-quill-admin',
            AUTO_QUILL_PLUGIN_URL . 'assets/admin.css',
            [],
            AUTO_QUILL_VERSION
        );

        wp_enqueue_script(
            'auto-quill-admin',
            AUTO_QUILL_PLUGIN_URL . 'assets/admin.js',
            ['jquery'],
            AUTO_QUILL_VERSION,
            true
        );

        wp_localize_script('auto-quill-admin', 'autoQuill', [
            'apiUrl' => rest_url('auto-quill/v1/'),
            'nonce' => wp_create_nonce('auto-quill-nonce'),
        ]);
    }

    public function register_rest_routes() {
        register_rest_route('auto-quill/v1', '/topics', [
            'methods' => 'GET',
            'callback' => ['\AutoQuill\Admin\AdminPage', 'get_today_topics'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('auto-quill/v1', '/generate-post', [
            'methods' => 'POST',
            'callback' => ['\AutoQuill\AI\Writer', 'generate_post'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('auto-quill/v1', '/publish-post', [
            'methods' => 'POST',
            'callback' => ['\AutoQuill\Admin\AdminPage', 'publish_post'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
    }
}
