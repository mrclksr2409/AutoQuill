<?php
namespace AutoQuill\Database;

class Schema {
    const DB_VERSION = '1.1';

    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sources_table = $wpdb->prefix . 'auto_quill_sources';
        $sources_sql = "CREATE TABLE $sources_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            feed_url TEXT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY feed_active (is_active)
        ) $charset_collate;";

        $articles_table = $wpdb->prefix . 'auto_quill_articles';
        $articles_sql = "CREATE TABLE $articles_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            description LONGTEXT,
            content LONGTEXT,
            author VARCHAR(255),
            published_date DATETIME,
            article_url TEXT NOT NULL,
            article_hash VARCHAR(64),
            fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY article_hash (article_hash),
            KEY source_id (source_id),
            KEY published_date (published_date)
        ) $charset_collate;";

        $topics_table = $wpdb->prefix . 'auto_quill_topics';
        $topics_sql = "CREATE TABLE $topics_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            topic_date DATE NOT NULL,
            topics LONGTEXT NOT NULL,
            selected_topic_id INT,
            selected_topic_title VARCHAR(255),
            post_id BIGINT UNSIGNED,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY topic_date (topic_date),
            KEY status (status)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sources_sql);
        dbDelta($articles_sql);
        dbDelta($topics_sql);

        update_option('auto_quill_db_version', self::DB_VERSION);
    }

    public static function maybe_upgrade() {
        if (get_option('auto_quill_db_version') !== self::DB_VERSION) {
            self::create_tables();
        }
    }

    public static function drop_tables() {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}auto_quill_articles");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}auto_quill_sources");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}auto_quill_topics");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}auto_quill_settings");
        delete_option('auto_quill_db_version');
    }
}
