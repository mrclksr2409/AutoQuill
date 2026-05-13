<?php
/**
 * Plugin Name: AutoQuill
 * Plugin URI: https://github.com/AutoQuill
 * Description: Sammelt RSS-Feed Artikel, selektiert täglich die Top-Themen via KI und erstellt automatisch Blog-Posts
 * Version: 1.0.0
 * Author: AutoQuill
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: auto-quill
 * Domain Path: /languages
 * Requires at least: 5.9
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin-Konstanten
define('AUTO_QUILL_VERSION', '1.0.0');
define('AUTO_QUILL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AUTO_QUILL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AUTO_QUILL_INC_DIR', AUTO_QUILL_PLUGIN_DIR . 'includes/');

// Autoloader
require_once AUTO_QUILL_INC_DIR . 'class-autoloader.php';

// Plugin aktivieren/deaktivieren
register_activation_hook(__FILE__, ['\AutoQuill\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['\AutoQuill\Deactivator', 'deactivate']);

// Plugin initialisieren
add_action('plugins_loaded', function () {
    AutoQuill\Core\Plugin::init();
});
