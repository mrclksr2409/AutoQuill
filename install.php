<?php
/**
 * Installationsskript für AutoQuill
 * Erstellt beispielhafte Datenbank-Einträge und initialisiert das Plugin
 */

if (!function_exists('wp_get_current_user')) {
    require_once(ABSPATH . 'wp-load.php');
}

if (!current_user_can('manage_options')) {
    wp_die('Nur Administratoren können dieses Skript ausführen.');
}

require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once plugin_dir_path(__FILE__) . 'includes/Database/class-schema.php';

// Datenbanktabellen erstellen
\AutoQuill\Database\Schema::create_tables();

// Default-Einstellungen
$default_settings = [
    'ai_provider' => 'openai',
    'ai_api_key' => '',
    'posts_per_day' => 1,
    'auto_publish' => false,
    'post_status' => 'draft',
];

update_option('auto_quill_settings', $default_settings);

// Beispiel RSS-Quellen hinzufügen (optional)
$sample_sources = [
    [
        'title' => 'TechCrunch',
        'feed_url' => 'https://techcrunch.com/feed/',
    ],
    [
        'title' => 'Hacker News',
        'feed_url' => 'https://news.ycombinator.com/rss',
    ],
    [
        'title' => 'Product Hunt',
        'feed_url' => 'https://www.producthunt.com/feed',
    ],
];

global $wpdb;
$sources_table = $wpdb->prefix . 'auto_quill_sources';

foreach ($sample_sources as $source) {
    $wpdb->insert($sources_table, [
        'title' => $source['title'],
        'feed_url' => $source['feed_url'],
        'is_active' => 1,
        'created_at' => current_time('mysql'),
    ]);
}

echo '<div class="wrap notice notice-success"><p>✅ AutoQuill wurde erfolgreich installiert! Gehe zu AutoQuill → Einstellungen, um deine API-Key einzugeben.</p></div>';
