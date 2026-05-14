<?php
namespace AutoQuill\AI;

use AutoQuill\Core\Constants as C;
use AutoQuill\Database\ArticlesRepository;
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

        $article = self::load_source_article($selected_topic);

        $available_categories = self::get_available_categories();
        $result = self::write_blog_post($selected_topic, $available_categories, $article);

        if (is_wp_error($result)) {
            $code = $result->get_error_code() === 'no_api_key' ? 400 : 502;
            return new \WP_REST_Response(['error' => $result->get_error_message()], $code);
        }

        $repo->mark_generated($topic_id, $topic_id, (string) $selected_topic['title']);

        return new \WP_REST_Response([
            'success'              => true,
            'post_content'         => $result['content'],
            'post_excerpt'         => $result['excerpt'],
            'category_ids'         => $result['category_ids'],
            'available_categories' => $available_categories,
            'topic'                => $selected_topic,
            'topic_id'             => $topic_id,
        ]);
    }

    private static function load_source_article(array $topic) {
        $repo       = new ArticlesRepository();
        $article_id = (int) ($topic['article_id'] ?? 0);
        if ($article_id > 0) {
            $article = $repo->find($article_id);
            if ($article) {
                return $article;
            }
        }
        return $repo->find_by_title((string) ($topic['title'] ?? ''));
    }

    /**
     * @return array<int, array{id:int,name:string}>
     */
    private static function get_available_categories(): array {
        $terms = get_categories(['hide_empty' => false]);
        $out   = [];
        foreach ($terms as $term) {
            $out[] = [
                'id'   => (int) $term->term_id,
                'name' => (string) $term->name,
            ];
        }
        return $out;
    }

    /**
     * @param array<int, array{id:int,name:string}> $available_categories
     * @return array{content:string, excerpt:string, category_ids:int[]}|\WP_Error
     */
    private static function write_blog_post(array $topic, array $available_categories, $article = null) {
        $settings    = get_option(C::OPTION_KEY, C::defaults());
        $ai_provider = $settings['ai_provider'] ?? 'openai';

        if ($ai_provider !== 'openai' && $ai_provider !== 'claude') {
            return self::generate_basic_post($topic, $article);
        }

        $categories_list = '';
        foreach ($available_categories as $cat) {
            $categories_list .= "- ID {$cat['id']}: {$cat['name']}\n";
        }
        if ($categories_list === '') {
            $categories_list = "(keine Kategorien vorhanden)\n";
        }

        $source_block = self::build_source_block($article, $topic);

        $placeholders = [
            '{title}'           => (string) ($topic['title'] ?? ''),
            '{source_block}'    => $source_block,
            '{categories_list}' => $categories_list,
        ];

        $defaults       = C::defaults();
        $body_tpl       = isset($settings['prompt_body']) && trim((string) $settings['prompt_body']) !== ''
            ? (string) $settings['prompt_body']
            : $defaults['prompt_body'];
        $excerpt_tpl    = isset($settings['prompt_excerpt']) && trim((string) $settings['prompt_excerpt']) !== ''
            ? (string) $settings['prompt_excerpt']
            : $defaults['prompt_excerpt'];

        $body_section    = strtr($body_tpl, $placeholders);
        $excerpt_section = strtr($excerpt_tpl, $placeholders);

        $prompt = $body_section . "\n\n"
            . "Auszug-Anweisungen:\n"
            . $excerpt_section . "\n\n"
            . "Verfügbare Kategorien (wähle 1-3 passende IDs aus dieser Liste):\n"
            . $categories_list . "\n"
            . "Antworte AUSSCHLIESSLICH mit einem gültigen JSON-Objekt (kein Markdown, kein Codeblock) mit folgenden Feldern:\n"
            . "{\n"
            . "  \"content\": \"<HTML des Blog-Posts gemäß den obigen Anweisungen>\",\n"
            . "  \"excerpt\": \"<Auszug gemäß den obigen Auszug-Anweisungen>\",\n"
            . "  \"category_ids\": [<1 bis 3 IDs aus der obigen Liste>]\n"
            . "}";

        $raw = $ai_provider === 'openai'
            ? self::call_openai_write($prompt)
            : self::call_claude_write($prompt);

        if (is_wp_error($raw)) {
            return $raw;
        }

        return self::parse_ai_response($raw, $available_categories);
    }

    private static function build_source_block($article, array $topic): string {
        $title       = $article ? (string) $article->title       : (string) ($topic['title']   ?? '');
        $description = $article ? (string) $article->description : (string) ($topic['summary'] ?? '');
        $content     = $article ? (string) $article->content     : '';

        $description = wp_strip_all_tags($description);
        $content     = wp_strip_all_tags($content);

        if (function_exists('mb_substr')) {
            $content = mb_substr($content, 0, 8000);
        } else {
            $content = substr($content, 0, 8000);
        }

        $block  = "Quelltext (Originalartikel):\n";
        $block .= "Titel: {$title}\n";
        if ($description !== '') {
            $block .= "Beschreibung: {$description}\n";
        }
        if ($content !== '') {
            $block .= "Inhalt:\n{$content}\n";
        }
        if ($article && !empty($article->article_url)) {
            $block .= "Quelle: {$article->article_url}\n";
        }
        $block .= "\n";
        return $block;
    }

    /**
     * @param array<int, array{id:int,name:string}> $available_categories
     * @return array{content:string, excerpt:string, category_ids:int[]}|\WP_Error
     */
    private static function parse_ai_response(string $raw, array $available_categories) {
        $cleaned = trim($raw);
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', $cleaned);
        $cleaned = preg_replace('/\s*```$/', '', (string) $cleaned);
        $cleaned = trim((string) $cleaned);

        $decoded = json_decode($cleaned, true);
        if (!is_array($decoded) && preg_match('/\{.*\}/s', $cleaned, $m)) {
            $decoded = json_decode($m[0], true);
        }

        if (!is_array($decoded) || !isset($decoded['content']) || !is_string($decoded['content']) || $decoded['content'] === '') {
            error_log('AutoQuill Writer: KI-Antwort konnte nicht als JSON-Post geparst werden: ' . substr($raw, 0, 500));
            return new \WP_Error(
                'ai_parse_failed',
                'Die KI-Antwort konnte nicht verarbeitet werden. Bitte erneut versuchen oder Modell/Prompt prüfen.'
            );
        }

        $valid_ids = array_map(static fn($c) => (int) $c['id'], $available_categories);
        $picked    = [];
        foreach ((array) ($decoded['category_ids'] ?? []) as $cid) {
            $cid = (int) $cid;
            if (in_array($cid, $valid_ids, true)) {
                $picked[] = $cid;
            }
        }
        return [
            'content'      => (string) $decoded['content'],
            'excerpt'      => isset($decoded['excerpt']) ? (string) $decoded['excerpt'] : '',
            'category_ids' => array_values(array_unique($picked)),
        ];
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
                    ['role' => 'system', 'content' => 'Du bist ein professioneller Blog-Autor und erstellst hochwertige, informative Inhalte. Antworte immer im geforderten JSON-Format.'],
                    ['role' => 'user',   'content' => $prompt],
                ],
                'temperature' => 0.8,
                'max_tokens'  => 2500,
            ]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['choices'][0]['message']['content'])) {
            return (string) $body['choices'][0]['message']['content'];
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
                'system'     => 'Du bist ein professioneller Blog-Autor und erstellst hochwertige, informative Inhalte. Antworte immer im geforderten JSON-Format.',
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
            return (string) $body['content'][0]['text'];
        }

        return new \WP_Error('api_error', 'Claude API-Fehler beim Schreiben des Posts');
    }

    /**
     * @return array{content:string, excerpt:string, category_ids:int[]}
     */
    private static function generate_basic_post(array $topic, $article = null): array {
        $title   = (string) ($topic['title'] ?? '');
        $summary = (string) ($topic['summary'] ?? '');

        if ($article && !empty($article->content)) {
            $content  = '<h1>' . esc_html($title) . '</h1>';
            $content .= wp_kses_post((string) $article->content);
            if (!empty($article->article_url)) {
                $content .= '<p><em>Quelle: <a href="' . esc_url($article->article_url) . '" rel="nofollow noopener">'
                    . esc_html($article->article_url) . '</a></em></p>';
            }
            $excerpt_source = !empty($article->description) ? (string) $article->description : $summary;
        } else {
            $content  = '<h1>' . esc_html($title) . '</h1>';
            $content .= '<p><strong>Einleitung:</strong> ' . esc_html($summary) . '</p>';
            $content .= '<p>Dies ist ein automatisch erstellter Blog-Post basierend auf aktuellen Nachrichten.</p>';
            $excerpt_source = $summary;
        }

        $excerpt = $excerpt_source !== ''
            ? mb_substr(wp_strip_all_tags($excerpt_source), 0, 240)
            : $title;

        return [
            'content'      => $content,
            'excerpt'      => $excerpt,
            'category_ids' => [],
        ];
    }
}
