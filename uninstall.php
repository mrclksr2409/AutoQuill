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

foreach ([
    \AutoQuill\Core\Constants::META_ARTICLE_ID,
    \AutoQuill\Core\Constants::META_SOURCE_URL,
    \AutoQuill\Core\Constants::META_ARTICLE_TITLE,
    \AutoQuill\Core\Constants::META_FEED_NAME,
] as $meta_key) {
    delete_post_meta_by_key($meta_key);
}

wp_clear_scheduled_hook(\AutoQuill\Core\Constants::CRON_FETCH);
wp_clear_scheduled_hook(\AutoQuill\Core\Constants::CRON_SELECT);
