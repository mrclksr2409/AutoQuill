<?php
namespace AutoQuill\AI;

use AutoQuill\Core\Constants as C;
use AutoQuill\Database\TopicsRepository;

class Writer {
    public static function generate_post(\WP_REST_Request $request): \WP_REST_Response {
        $params   = $request->get_json_params() ?: [];
        $topic_id = (int) ($params['topic_id'] ?? 0);
        $title    = (string) ($params['title'] ?? '');

        if ($topic_id <= 0) {
            return new \WP_REST_Response(['error' => 'Topic ID erforderlich'], 400);
        }

        $repo  = new TopicsRepository();
        $topic = $repo->find($topic_id);

        if (!$topic) {
            return new \WP_REST_Response(['error' => 'Topic nicht gefunden'], 404);
        }

        $topics_data    = json_decode($topic->topics, true) ?: [];
        $selected_topic = null;
        foreach ($topics_data as $t) {
            if (($t['title'] ?? '') === $title) {
                $selected_topic = $t;
                break;
            }
        }

        if (!$selected_topic) {
            return new \WP_REST_Response(['error' => 'Ausgewähltes Thema nicht gefunden'], 404);
        }

        $post_content = self::write_blog_post($selected_topic);

        if (is_wp_error($post_content)) {
            return new \WP_REST_Response(['error' => $post_content->get_error_message()], 500);
        }

        $repo->mark_generated($topic_id, $topic_id, (string) $selected_topic['title']);

        return new \WP_REST_Response([
            'success'      => true,
            'post_content' => $post_content,
            'topic'        => $selected_topic,
            'topic_id'     => $topic_id,
        ]);
    }

    private static function write_blog_post(array $topic) {
        $settings    = get_option(C::OPTION_KEY, C::defaults());
        $ai_provider = $settings['ai_provider'] ?? 'openai';

        $prompt = "Schreibe einen ausführlichen, professionellen Blog-Post über das Thema: '{$topic['title']}'\n\n"
            . "Kontext: " . ($topic['summary'] ?? '') . "\n\n"
            . "Der Post sollte:\n"
            . "- 800-1200 Wörter lang sein\n"
            . "- Mit einer ansprechenden Einleitung beginnen\n"
            . "- 3-4 Hauptabschnitte mit Zwischenüberschriften haben\n"
            . "- Mit einem Fazit enden\n"
            . "- HTML-Formatierung verwenden (aber ohne <html>, <body> etc.)\n\n"
            . "Antworte nur mit dem Blog-Post-Inhalt, keine zusätzlichen Erklärungen.";

        if ($ai_provider === 'openai') {
            return self::call_openai_write($prompt);
        }
        if ($ai_provider === 'claude') {
            return self::call_claude_write($prompt);
        }
        return self::generate_basic_post($topic);
    }

    private static function call_openai_write(string $prompt) {
        $settings = get_option(C::OPTION_KEY, C::defaults());
        $api_key  = $settings['ai_api_key'] ?? '';

        if (empty($api_key)) {
            return new \WP_Error('no_api_key', 'API Key nicht konfiguriert');
        }

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'       => 'gpt-3.5-turbo',
                'messages'    => [
                    ['role' => 'system', 'content' => 'Du bist ein professioneller Blog-Autor und erstellst hochwertige, informative Inhalte.'],
                    ['role' => 'user',   'content' => $prompt],
                ],
                'temperature' => 0.8,
                'max_tokens'  => 2000,
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

    private static function call_claude_write(string $prompt) {
        $settings = get_option(C::OPTION_KEY, C::defaults());
        $api_key  = $settings['ai_api_key'] ?? '';

        if (empty($api_key)) {
            return new \WP_Error('no_api_key', 'API Key nicht konfiguriert');
        }

        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'timeout' => 60,
            'headers' => [
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'      => 'claude-sonnet-4-6',
                'max_tokens' => 4096,
                'system'     => 'Du bist ein professioneller Blog-Autor und erstellst hochwertige, informative Inhalte.',
                'messages'   => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['content'][0]['text'])) {
            return $body['content'][0]['text'];
        }

        return new \WP_Error('api_error', 'Claude API-Fehler beim Schreiben des Posts');
    }

    private static function generate_basic_post(array $topic): string {
        $post  = '<h1>' . esc_html((string) $topic['title']) . '</h1>';
        $post .= '<p><strong>Einleitung:</strong> ' . esc_html((string) ($topic['summary'] ?? '')) . '</p>';
        $post .= '<p>Dies ist ein automatisch erstellter Blog-Post basierend auf aktuellen Nachrichten.</p>';
        return $post;
    }
}
