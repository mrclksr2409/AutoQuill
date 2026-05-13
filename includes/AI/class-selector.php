<?php
namespace AutoQuill\AI;

use AutoQuill\Database;

class Selector {
    public static function select_top_topics() {
        $db = Database::getInstance();

        // Artikel der letzten 24 Stunden abrufen
        $articles = self::get_recent_articles();

        if (empty($articles)) {
            error_log('AutoQuill: Keine neuen Artikel zum Analysieren gefunden');
            return;
        }

        // KI aufrufen, um Top-Themen zu selektieren
        $topics = self::analyze_articles($articles);

        if (!$topics || is_wp_error($topics)) {
            error_log('AutoQuill: Fehler beim Analysieren der Artikel: ' . $topics->get_error_message());
            return;
        }

        // Themen in DB speichern
        $today = date('Y-m-d');
        $existing_topic = $db->get_row('topics', ['topic_date' => $today]);

        if ($existing_topic) {
            $db->update(
                'topics',
                [
                    'topics' => wp_json_encode($topics),
                    'status' => 'pending',
                    'updated_at' => current_time('mysql'),
                ],
                ['topic_date' => $today]
            );
        } else {
            $db->insert('topics', [
                'topic_date' => $today,
                'topics' => wp_json_encode($topics),
                'status' => 'pending',
                'created_at' => current_time('mysql'),
            ]);
        }

        do_action('auto_quill_topics_selected', $topics);
    }

    private static function analyze_articles($articles) {
        $settings = get_option('auto_quill_settings', []);
        $ai_provider = $settings['ai_provider'] ?? 'openai';

        // Artikel zusammenfassen für KI
        $articles_text = '';
        foreach ($articles as $article) {
            $articles_text .= "Titel: {$article->title}\n";
            $articles_text .= "Beschreibung: {$article->description}\n";
            $articles_text .= "---\n\n";
        }

        $prompt = "Analysiere die folgenden Artikel und wähle die 5 interessantesten Themen aus. 
        Antworte als JSON-Array mit Struktur: [{\"title\": \"...\", \"summary\": \"...\", \"reason\": \"...\"}]\n\n" . $articles_text;

        // Placeholder: KI-Integration
        if ($ai_provider === 'openai') {
            return self::call_openai($prompt);
        } else {
            // Fallback: Einfache Kategorisierung
            return self::fallback_analyze($articles);
        }
    }

    private static function call_openai($prompt) {
        $settings = get_option('auto_quill_settings', []);
        $api_key = $settings['ai_api_key'] ?? '';

        if (empty($api_key)) {
            return new \WP_Error('no_api_key', 'OpenAI API Key nicht konfiguriert');
        }

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Du bist ein hilfreicher Content-Analyzer. Antworte ausschließlich mit gültigem JSON.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.7,
                'max_tokens' => 1000,
            ]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['choices'][0]['message']['content'])) {
            $content = $body['choices'][0]['message']['content'];
            // JSON aus der Response extrahieren
            preg_match('/\[.*\]/s', $content, $matches);
            if (!empty($matches[0])) {
                return json_decode($matches[0], true);
            }
        }

        return new \WP_Error('api_error', 'OpenAI API-Fehler');
    }

    private static function fallback_analyze($articles) {
        // Einfache Fallback-Analyse wenn keine KI verfügbar
        $topics = [];
        foreach (array_slice($articles, 0, 5) as $article) {
            $topics[] = [
                'title' => $article->title,
                'summary' => substr($article->description, 0, 200),
                'reason' => 'Hochaktueller Artikel',
                'article_id' => $article->id,
            ];
        }
        return $topics;
    }

    private static function get_recent_articles($hours = 24) {
        global $wpdb;
        $table = $wpdb->prefix . 'auto_quill_articles';
        $time_ago = date('Y-m-d H:i:s', time() - ($hours * 3600));

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE fetched_at >= %s ORDER BY published_date DESC LIMIT 50",
                $time_ago
            )
        );

        return $results;
    }
}
