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
    \AutoQuill\Core\Constants::META_GENERATED_AT,
] as $meta_key) {
    delete_post_meta_by_key($meta_key);
}

delete_option(\AutoQuill\Core\Constants::OPTION_LAST_DIGEST);

// Cached model lists (ModelCatalog), keyed by a hash of the API key.
global $wpdb;
$wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
    $wpdb->esc_like('_transient_' . \AutoQuill\Core\Constants::MODELS_CACHE_PREFIX) . '%',
    $wpdb->esc_like('_transient_timeout_' . \AutoQuill\Core\Constants::MODELS_CACHE_PREFIX) . '%'
));

wp_clear_scheduled_hook(\AutoQuill\Core\Constants::CRON_FETCH);
wp_clear_scheduled_hook(\AutoQuill\Core\Constants::CRON_SELECT);
wp_clear_scheduled_hook(\AutoQuill\Core\Constants::CRON_DIGEST);
