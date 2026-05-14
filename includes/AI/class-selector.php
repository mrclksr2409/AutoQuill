<?php
namespace AutoQuill\AI;

use AutoQuill\Core\Constants as C;
use AutoQuill\Database\ArticlesRepository;
use AutoQuill\Database\TopicsRepository;

class Selector {
    public static function select_top_topics(): void {
        $articles = (new ArticlesRepository())->recent(24, 50);

        if (empty($articles)) {
            error_log('AutoQuill: Keine neuen Artikel zum Analysieren gefunden');
            return;
        }

        $topics = self::analyze_articles($articles);

        if (!$topics || is_wp_error($topics)) {
            $msg = is_wp_error($topics) ? $topics->get_error_message() : 'leer';
            error_log('AutoQuill: Fehler beim Analysieren der Artikel: ' . $msg);
            return;
        }

        $today = current_time('Y-m-d');
        (new TopicsRepository())->upsert_for_date($today, $topics);

        do_action('auto_quill_topics_selected', $topics);
    }

    private static function analyze_articles(array $articles) {
        $settings    = get_option(C::OPTION_KEY, C::defaults());
        $ai_provider = $settings['ai_provider'] ?? 'openai';

        $articles_text = '';
        foreach ($articles as $article) {
            $description = (string) $article->description;
            if (function_exists('mb_substr')) {
                $description = mb_substr($description, 0, 400);
            } else {
                $description = substr($description, 0, 400);
            }
            $articles_text .= "ID: {$article->id}\n";
            $articles_text .= "Titel: {$article->title}\n";
            $articles_text .= "Beschreibung: {$description}\n";
            $articles_text .= "---\n\n";
        }

        $prompt = "Analysiere die folgenden Artikel und wähle die 5 interessantesten Themen aus.\n"
            . "Wähle für jedes Thema genau einen Artikel aus der Liste und gib dessen ID zurück.\n"
            . "Antworte AUSSCHLIESSLICH mit einem gültigen JSON-Array (kein Markdown, kein Codeblock) mit der Struktur:\n"
            . "[{\"article_id\": <int aus der obigen Liste>, \"title\": \"...\", \"summary\": \"...\", \"reason\": \"...\"}]\n\n"
            . $articles_text;

        if ($ai_provider === 'openai') {
            $topics = self::call_openai($prompt);
        } elseif ($ai_provider === 'claude') {
            $topics = self::call_claude($prompt);
        } else {
            return self::fallback_analyze($articles);
        }

        if (!is_array($topics)) {
            return $topics;
        }

        return self::attach_article_ids($topics, $articles);
    }

    /**
     * Stellt sicher, dass jedes Topic eine gültige article_id hat.
     * Fällt bei fehlender/ungültiger ID auf Title-Match zurück.
     */
    private static function attach_article_ids(array $topics, array $articles): array {
        $by_id = [];
        foreach ($articles as $a) {
            $by_id[(int) $a->id] = $a;
        }
        $repo = new ArticlesRepository();

        $normalized = [];
        foreach ($topics as $topic) {
            if (!is_array($topic)) {
                continue;
            }
            $article_id = (int) ($topic['article_id'] ?? 0);
            if ($article_id <= 0 || !isset($by_id[$article_id])) {
                $matched = $repo->find_by_title((string) ($topic['title'] ?? ''));
                $article_id = $matched ? (int) $matched->id : 0;
            }
            $topic['article_id'] = $article_id;
            $normalized[] = $topic;
        }
        return $normalized;
    }

    private static function call_openai(string $prompt) {
        $api_key = C::ai_api_key();

        if ($api_key === '') {
            return new \WP_Error('no_api_key', 'API Key nicht konfiguriert');
        }

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'       => 'gpt-3.5-turbo',
                'messages'    => [
                    ['role' => 'system', 'content' => 'Du bist ein hilfreicher Content-Analyzer. Antworte ausschließlich mit gültigem JSON.'],
                    ['role' => 'user',   'content' => $prompt],
                ],
                'temperature' => 0.7,
                'max_tokens'  => 1000,
            ]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['choices'][0]['message']['content'])) {
            $content = $body['choices'][0]['message']['content'];
            if (preg_match('/\[.*\]/s', $content, $matches)) {
                return json_decode($matches[0], true);
            }
        }

        return new \WP_Error('api_error', 'OpenAI API-Fehler');
    }

    private static function call_claude(string $prompt) {
        $api_key = C::ai_api_key();

        if ($api_key === '') {
            return new \WP_Error('no_api_key', 'API Key nicht konfiguriert');
        }

        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'timeout' => 30,
            'headers' => [
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'      => 'claude-sonnet-4-6',
                'max_tokens' => 1500,
                'system'     => 'Du bist ein hilfreicher Content-Analyzer. Antworte ausschließlich mit gültigem JSON.',
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
            $content = $body['content'][0]['text'];
            if (preg_match('/\[.*\]/s', $content, $matches)) {
                return json_decode($matches[0], true);
            }
        }

        return new \WP_Error('api_error', 'Claude API-Fehler');
    }

    private static function fallback_analyze(array $articles): array {
        $topics = [];
        foreach (array_slice($articles, 0, 5) as $article) {
            $topics[] = [
                'title'      => $article->title,
                'summary'    => substr((string) $article->description, 0, 200),
                'reason'     => 'Hochaktueller Artikel',
                'article_id' => (int) $article->id,
            ];
        }
        return $topics;
    }
}
