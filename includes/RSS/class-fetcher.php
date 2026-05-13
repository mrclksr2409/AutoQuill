<?php
namespace AutoQuill\RSS;

use AutoQuill\Database;

class Fetcher {
    public static function fetch_feeds() {
        $db = Database::getInstance();

        // Alle aktiven RSS Quellen abrufen
        $sources = $db->get_results('sources', ['is_active' => '1']);

        if (empty($sources)) {
            return;
        }

        foreach ($sources as $source) {
            self::fetch_feed($source->id, $source->feed_url);
        }

        // Nach dem Fetch: Artikel analysieren und Topics generieren
        do_action('auto_quill_daily_select');
    }

    public static function fetch_feed($source_id, $feed_url) {
        $db = Database::getInstance();

        // SimplePie für RSS-Parsing verwenden
        require_once ABSPATH . WPINC . '/class-simplepie.php';

        $feed = new \SimplePie();
        $feed->set_feed_url($feed_url);
        $feed->init();

        if ($feed->error()) {
            error_log('AutoQuill: Fehler beim Fetchen von ' . $feed_url . ' - ' . $feed->error());
            return;
        }

        $items = $feed->get_items(0, 20); // Letzten 20 Artikel

        foreach ($items as $item) {
            $article_hash = md5($feed_url . $item->get_link());

            // Prüfe ob Artikel bereits existiert
            $existing = $db->get_row('articles', ['article_hash' => $article_hash]);
            if ($existing) {
                continue;
            }

            // Neuen Artikel speichern
            $db->insert('articles', [
                'source_id' => $source_id,
                'title' => substr($item->get_title(), 0, 255),
                'description' => substr(strip_tags($item->get_description()), 0, 1000),
                'content' => wp_remote_retrieve_body(wp_remote_get($item->get_link())),
                'author' => $item->get_author() ? $item->get_author()->name : 'Unknown',
                'published_date' => $item->get_date('Y-m-d H:i:s'),
                'article_url' => $item->get_link(),
                'article_hash' => $article_hash,
            ]);
        }

        // Feed Speicher freigeben
        $feed->__destruct();
        unset($feed);
    }

    public static function get_articles_since($hours = 24) {
        global $wpdb;
        $table = $wpdb->prefix . 'auto_quill_articles';
        $time_ago = date('Y-m-d H:i:s', time() - ($hours * 3600));

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE fetched_at >= %s ORDER BY published_date DESC",
                $time_ago
            )
        );

        return $results;
    }

    public static function add_feed_source($title, $feed_url) {
        $db = Database::getInstance();

        // URL validieren
        if (!filter_var($feed_url, FILTER_VALIDATE_URL)) {
            return new \WP_Error('invalid_url', 'Ungültige Feed-URL');
        }

        // Prüfe ob URL gültig ist
        $response = wp_remote_get($feed_url, ['timeout' => 10]);
        if (is_wp_error($response)) {
            return $response;
        }

        $db->insert('sources', [
            'title' => sanitize_text_field($title),
            'feed_url' => esc_url($feed_url),
            'is_active' => 1,
        ]);

        return true;
    }

    public static function get_sources() {
        $db = Database::getInstance();
        return $db->get_results('sources', ['is_active' => '1']);
    }
}
