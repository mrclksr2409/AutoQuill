<?php
namespace AutoQuill\Database;

class Schema {
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // RSS Sources Tabelle
        $sources_table = $wpdb->prefix . 'auto_quill_sources';
        $sources_sql = "CREATE TABLE IF NOT EXISTS $sources_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            feed_url TEXT NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY feed_active (is_active)
        ) $charset_collate;";

        // RSS Articles Tabelle
        $articles_table = $wpdb->prefix . 'auto_quill_articles';
        $articles_sql = "CREATE TABLE IF NOT EXISTS $articles_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            source_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            description LONGTEXT,
            content LONGTEXT,
            author VARCHAR(255),
            published_date DATETIME,
            article_url TEXT NOT NULL,
            article_hash VARCHAR(64) UNIQUE,
            fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY source_id (source_id),
            KEY published_date (published_date),
            FOREIGN KEY (source_id) REFERENCES $sources_table(id) ON DELETE CASCADE
        ) $charset_collate;";

        // Topics Tabelle (täglich generiert)
        $topics_table = $wpdb->prefix . 'auto_quill_topics';
        $topics_sql = "CREATE TABLE IF NOT EXISTS $topics_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            topic_date DATE NOT NULL UNIQUE,
            topics JSON NOT NULL,
            selected_topic_id INT,
            selected_topic_title VARCHAR(255),
            post_id BIGINT,
            status ENUM('pending', 'selected', 'generated', 'published') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY topic_date (topic_date),
            KEY status (status)
        ) $charset_collate;";

        // AI Settings Tabelle
        $settings_table = $wpdb->prefix . 'auto_quill_settings';
        $settings_sql = "CREATE TABLE IF NOT EXISTS $settings_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            option_name VARCHAR(255) NOT NULL UNIQUE,
            option_value LONGTEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY option_name (option_name)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sources_sql);
        dbDelta($articles_sql);
        dbDelta($topics_sql);
        dbDelta($settings_table);
    }

    public static function drop_tables() {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}auto_quill_articles");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}auto_quill_sources");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}auto_quill_topics");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}auto_quill_settings");
    }
}
