<?php
namespace AutoQuill\Database;

use AutoQuill\Core\Constants as C;

class Schema {
    public static function ensure_tables(): void {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        global $wpdb;
        $needed = [C::TABLE_SOURCES, C::TABLE_ARTICLES, C::TABLE_TOPICS, C::TABLE_LOGS];

        foreach ($needed as $slug) {
            $full = $wpdb->prefix . $slug;
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $full)) === $full;
            if (!$exists) {
                self::create_tables();
                return;
            }
        }

        if (get_option(C::DB_VERSION_KEY) !== C::DB_VERSION) {
            self::create_tables();
        }
    }

    public static function create_tables(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sources_table  = $wpdb->prefix . C::TABLE_SOURCES;
        $articles_table = $wpdb->prefix . C::TABLE_ARTICLES;
        $topics_table   = $wpdb->prefix . C::TABLE_TOPICS;
        $logs_table     = $wpdb->prefix . C::TABLE_LOGS;

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

        $logs_sql = "CREATE TABLE $logs_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            level VARCHAR(10) NOT NULL,
            source VARCHAR(40) NOT NULL,
            message TEXT NOT NULL,
            context LONGTEXT,
            PRIMARY KEY  (id),
            KEY level (level),
            KEY source (source),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sources_sql);
        dbDelta($articles_sql);
        dbDelta($topics_sql);
        dbDelta($logs_sql);

        update_option(C::DB_VERSION_KEY, C::DB_VERSION);
    }

    public static function drop_tables(): void {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}" . C::TABLE_ARTICLES);
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}" . C::TABLE_SOURCES);
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}" . C::TABLE_TOPICS);
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}" . C::TABLE_LOGS);
        delete_option(C::DB_VERSION_KEY);
    }

    public static function table_status(): array {
        global $wpdb;
        $out = [];
        foreach ([C::TABLE_SOURCES, C::TABLE_ARTICLES, C::TABLE_TOPICS] as $slug) {
            $full   = $wpdb->prefix . $slug;
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $full)) === $full;
            $count  = $exists ? (int) $wpdb->get_var("SELECT COUNT(*) FROM `$full`") : 0;
            $out[] = ['name' => $full, 'exists' => $exists, 'count' => $count];
        }
        return $out;
    }
}
