<?php
namespace AutoQuill\AI;

use AutoQuill\Database;

class Writer {
    public static function generate_post($request) {
        $params = $request->get_json_params();
        $topic_id = intval($params['topic_id'] ?? 0);

        if (!$topic_id) {
            return new \WP_REST_Response(['error' => 'Topic ID erforderlich'], 400);
        }

        $db = Database::getInstance();
        $topic = $db->get_row('topics', ['id' => $topic_id]);

        if (!$topic) {
            return new \WP_REST_Response(['error' => 'Topic nicht gefunden'], 404);
        }

        // Thema dekodieren
        $topics_data = json_decode($topic->topics, true);
        $selected_topic = null;

        foreach ($topics_data as $t) {
            if ($t['title'] === $params['title'] ?? '') {
                $selected_topic = $t;
                break;
            }
        }

        if (!$selected_topic) {
            return new \WP_REST_Response(['error' => 'Ausgewähltes Thema nicht gefunden'], 404);
        }

        // KI aufrufen, um Blog-Post zu schreiben
        $post_content = self::write_blog_post($selected_topic);

        if (is_wp_error($post_content)) {
            return new \WP_REST_Response(['error' => $post_content->get_error_message()], 500);
        }

        // Thema als ausgewählt markieren
        $db->update(
            'topics',
            [
                'selected_topic_id' => $topic_id,
                'selected_topic_title' => $selected_topic['title'],
                'status' => 'generated',
            ],
            ['id' => $topic_id]
        );

        return new \WP_REST_Response([
            'success' => true,
            'post_content' => $post_content,
            'topic' => $selected_topic,
        ]);
    }

    private static function write_blog_post($topic) {
        $settings = get_option('auto_quill_settings', []);
        $ai_provider = $settings['ai_provider'] ?? 'openai';

        $prompt = "Schreibe einen ausführlichen, professionellen Blog-Post über das Thema: '{$topic['title']}'
        
        Kontext: {$topic['summary']}
        
        Der Post sollte:
        - 800-1200 Wörter lang sein
        - Mit einer ansprechenden Einleitung beginnen
        - 3-4 Hauptabschnitte mit Zwischenüberschriften haben
        - Mit einem Fazit enden
        - HTML-Formatierung verwenden (aber ohne <html>, <body> etc.)
        
        Antworte nur mit dem Blog-Post-Inhalt, keine zusätzlichen Erklärungen.";

        if ($ai_provider === 'openai') {
            return self::call_openai_write($prompt);
        } else {
            return self::generate_basic_post($topic);
        }
    }

    private static function call_openai_write($prompt) {
        $settings = get_option('auto_quill_settings', []);
        $api_key = $settings['ai_api_key'] ?? '';

        if (empty($api_key)) {
            return new \WP_Error('no_api_key', 'OpenAI API Key nicht konfiguriert');
        }

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Du bist ein professioneller Blog-Autor und erstellst hochwertige, informative Inhalte.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.8,
                'max_tokens' => 2000,
            ]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['choices'][0]['message']['content'])) {
            return $body['choices'][0]['message']['content'];
        }

        return new \WP_Error('api_error', 'OpenAI API-Fehler beim Schreiben des Posts');
    }

    private static function generate_basic_post($topic) {
        $post = "<h1>{$topic['title']}</h1>";
        $post .= "<p><strong>Einleitung:</strong> " . $topic['summary'] . "</p>";
        $post .= "<p>Dies ist ein automatisch erstellter Blog-Post basierend auf aktuellen Nachrichten.</p>";
        return $post;
    }
}
