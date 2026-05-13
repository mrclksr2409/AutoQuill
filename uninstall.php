<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (!defined('AUTO_QUILL_INC_DIR')) {
    define('AUTO_QUILL_INC_DIR', __DIR__ . '/includes/');
}

require_once AUTO_QUILL_INC_DIR . 'class-autoloader.php';

\AutoQuill\Database\Schema::drop_tables();
delete_option(\AutoQuill\Core\Constants::OPTION_KEY);
delete_option(\AutoQuill\Core\Constants::DB_VERSION_KEY);

wp_clear_scheduled_hook(\AutoQuill\Core\Constants::CRON_FETCH);
wp_clear_scheduled_hook(\AutoQuill\Core\Constants::CRON_SELECT);
