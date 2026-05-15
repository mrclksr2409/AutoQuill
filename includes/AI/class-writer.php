<?php
namespace AutoQuill\AI;

use AutoQuill\Core\Constants as C;
use AutoQuill\Core\Logger;
use AutoQuill\Database\ArticlesRepository;
use AutoQuill\Database\TopicsRepository;

class Writer {
    public static function generate_post(\WP_REST_Request $request): \WP_REST_Response {
        $params   = $request->get_json_params() ?: [];
        $topic_id = (int) ($params['topic_id'] ?? 0);
        $title    = (string) ($params['title'] ?? '');

        Logger::info('writer', 'generate_post-Request', ['topic_id' => $topic_id, 'title' => $title]);

        if ($topic_id <= 0) {
            Logger::warning('writer', 'Topic ID fehlt im Request');
            return new \WP_REST_Response(['error' => 'Topic ID erforderlich'], 400);
        }

        $repo  = new TopicsRepository();
        $topic = $repo->find($topic_id);

        if (!$topic) {
            Logger::warning('writer', 'Topic nicht gefunden', ['topic_id' => $topic_id]);
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
            Logger::warning('writer', 'Ausgewähltes Thema nicht in topics-Daten gefunden', [
                'topic_id'         => $topic_id,
                'title'            => $title,
                'available_titles' => array_map(static fn($t) => $t['title'] ?? '', $topics_data),
            ]);
            return new \WP_REST_Response(['error' => 'Ausgewähltes Thema nicht gefunden'], 404);
        }

        $article = self::load_source_article($selected_topic);
        Logger::info('writer', 'Quellartikel geladen', [
            'topic_id'   => $topic_id,
            'article_id' => $article ? (int) $article->id : 0,
            'has_content' => $article && !empty($article->content),
        ]);

        $available_categories = self::get_available_categories();
        $result = self::write_blog_post($selected_topic, $available_categories, $article);

        if (is_wp_error($result)) {
            $code = $result->get_error_code() === 'no_api_key' ? 400 : 502;
            Logger::error('writer', 'Blog-Post-Generierung fehlgeschlagen', [
                'topic_id' => $topic_id,
                'code'     => $result->get_error_code(),
                'message'  => $result->get_error_message(),
            ]);
            return new \WP_REST_Response(['error' => $result->get_error_message()], $code);
        }

        $repo->mark_generated($topic_id, $topic_id, (string) $selected_topic['title']);

        Logger::info('writer', 'Blog-Post generiert', [
            'topic_id'     => $topic_id,
            'title'        => $result['title'],
            'content_len'  => strlen($result['content']),
            'excerpt_len'  => strlen($result['excerpt']),
            'category_ids' => $result['category_ids'],
        ]);

        return new \WP_REST_Response([
            'success'              => true,
            'post_title'           => $result['title'],
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
     * @return array{title:string, content:string, excerpt:string, category_ids:int[]}|\WP_Error
     */
    private static function write_blog_post(array $topic, array $available_categories, $article = null) {
        $settings    = get_option(C::OPTION_KEY, C::defaults());
        $ai_provider = $settings['ai_provider'] ?? 'openai';

        if ($ai_provider !== 'openai' && $ai_provider !== 'claude') {
            return self::generate_basic_post($topic, $article);
        }

        $source_block    = self::build_source_block($article, $topic);
        $categories_list = self::format_categories_list($available_categories);
        $defaults        = C::defaults();
        $client          = new Client();
        $system          = 'Du bist ein professioneller Blog-Autor und erstellst hochwertige, informative Inhalte. Antworte immer im geforderten JSON-Format.';

        return self::run_combined_step(
            $client,
            $system,
            $settings,
            $defaults,
            $topic,
            $source_block,
            $categories_list,
            $available_categories
        );
    }

    /**
     * @param array<int, array{id:int,name:string}> $available_categories
     */
    private static function format_categories_list(array $available_categories): string {
        $list = '';
        foreach ($available_categories as $cat) {
            $list .= "- ID {$cat['id']}: {$cat['name']}\n";
        }
        if ($list === '') {
            $list = "(keine Kategorien vorhanden)\n";
        }
        return $list;
    }

    private static function resolve_prompt(array $settings, array $defaults, string $key): string {
        $custom = isset($settings[$key]) ? trim((string) $settings[$key]) : '';
        return $custom !== '' ? (string) $settings[$key] : (string) $defaults[$key];
    }

    private static function build_combined_prompt(array $settings, array $defaults, array $topic, string $source_block, string $categories_list): string {
        $replace = [
            '{topic_title}'     => (string) ($topic['title'] ?? ''),
            '{source_block}'    => $source_block,
            '{categories_list}' => $categories_list,
            '{title}'           => '',
            '{content_excerpt}' => '',
        ];

        $section_title    = strtr(self::resolve_prompt($settings, $defaults, 'prompt_title'),    $replace);
        $section_body     = strtr(self::resolve_prompt($settings, $defaults, 'prompt_body'),     $replace);
        $section_excerpt  = strtr(self::resolve_prompt($settings, $defaults, 'prompt_excerpt'),  $replace);
        $section_category = strtr(self::resolve_prompt($settings, $defaults, 'prompt_category'), $replace);

        $prompt  = "Du erstellst einen Blog-Beitrag aus folgendem Quelltext.\n\n";
        $prompt .= $source_block;
        $prompt .= "Verfügbare Kategorien:\n{$categories_list}\n";
        $prompt .= "--- Vorgaben Titel ---\n{$section_title}\n\n";
        $prompt .= "--- Vorgaben Beitragstext ---\n{$section_body}\n\n";
        $prompt .= "--- Vorgaben Auszug ---\n{$section_excerpt}\n\n";
        $prompt .= "--- Vorgaben Kategorien ---\n{$section_category}\n\n";
        $prompt .= "--- Antwortformat ---\n";
        $prompt .= "Antworte AUSSCHLIESSLICH mit einem einzigen gültigen JSON-Objekt (kein Markdown, keine Codeblöcke, kein Text davor oder danach) nach folgendem Schema:\n";
        $prompt .= "{\n";
        $prompt .= "  \"title\": \"<string>\",\n";
        $prompt .= "  \"content\": \"<string mit HTML>\",\n";
        $prompt .= "  \"excerpt\": \"<string>\",\n";
        $prompt .= "  \"category_ids\": [<int>, ...]\n";
        $prompt .= "}\n";
        $prompt .= "Wichtig: Innerhalb des JSON-Strings für \"content\" müssen Anführungszeichen und Zeilenumbrüche korrekt escaped sein (\\\" und \\n).";

        return $prompt;
    }

    /**
     * @param array<int, array{id:int,name:string}> $available_categories
     * @return array{title:string, content:string, excerpt:string, category_ids:int[]}|\WP_Error
     */
    private static function run_combined_step(Client $client, string $system, array $settings, array $defaults, array $topic, string $source_block, string $categories_list, array $available_categories) {
        $prompt = self::build_combined_prompt($settings, $defaults, $topic, $source_block, $categories_list);

        $raw = $client->chat($system, $prompt, [
            'max_tokens'  => 6500,
            'temperature' => 0.7,
            'timeout'     => 90,
            'json_shape'  => 'object',
        ]);
        if (is_wp_error($raw)) {
            return $raw;
        }

        $parsed = self::parse_combined_response($raw, $available_categories);
        if ($parsed !== null) {
            Logger::info('writer', 'Blog-Post (combined) generiert', [
                'ai_step'      => 'combined',
                'title_len'    => strlen($parsed['title']),
                'content_len'  => strlen($parsed['content']),
                'excerpt_len'  => strlen($parsed['excerpt']),
                'category_ids' => $parsed['category_ids'],
            ]);
            return $parsed;
        }

        Logger::warning('writer', 'KI-Antwort (combined) nicht parsebar – starte Retry', [
            'ai_step'     => 'combined',
            'json_error'  => JsonExtractor::last_error(),
            'raw_excerpt' => mb_substr($raw, 0, 1000),
        ]);

        $retry_prompt = "Deine vorherige Antwort war kein gültiges JSON oder hat Pflichtfelder ausgelassen.\n"
            . "Antworte JETZT ausschließlich mit einem einzigen gültigen JSON-Objekt (kein Markdown, keine Codeblöcke, kein Text davor oder danach) "
            . "und stelle sicher, dass alle Felder (title, content, excerpt, category_ids) vorhanden und korrekt escaped sind.\n\n"
            . $prompt;

        $raw_retry = $client->chat($system, $retry_prompt, [
            'max_tokens'  => 6500,
            'temperature' => 0.3,
            'timeout'     => 90,
            'json_shape'  => 'object',
        ]);
        if (is_wp_error($raw_retry)) {
            return $raw_retry;
        }

        $parsed_retry = self::parse_combined_response($raw_retry, $available_categories);
        if ($parsed_retry !== null) {
            Logger::info('writer', 'Blog-Post (combined) nach Retry generiert', [
                'ai_step'      => 'combined_retry',
                'title_len'    => strlen($parsed_retry['title']),
                'content_len'  => strlen($parsed_retry['content']),
                'excerpt_len'  => strlen($parsed_retry['excerpt']),
                'category_ids' => $parsed_retry['category_ids'],
            ]);
            return $parsed_retry;
        }

        Logger::error('writer', 'KI-Antwort (combined) konnte auch im Retry nicht geparst werden', [
            'ai_step'     => 'combined_retry',
            'json_error'  => JsonExtractor::last_error(),
            'raw_excerpt' => mb_substr($raw_retry, 0, 1000),
        ]);
        return new \WP_Error('ai_parse_failed', 'Die KI-Antwort für den Blog-Post konnte nicht verarbeitet werden.');
    }

    /**
     * @param array<int, array{id:int,name:string}> $available_categories
     * @return array{title:string, content:string, excerpt:string, category_ids:int[]}|null
     */
    private static function parse_combined_response(string $raw, array $available_categories): ?array {
        $decoded = JsonExtractor::extract_object($raw);
        if (!is_array($decoded)) {
            return null;
        }

        $title = isset($decoded['title']) && is_string($decoded['title']) ? trim($decoded['title']) : '';
        if ($title === '') {
            return null;
        }

        $content = isset($decoded['content']) && is_string($decoded['content']) ? $decoded['content'] : '';
        if (trim($content) === '') {
            return null;
        }

        $excerpt = isset($decoded['excerpt']) && is_string($decoded['excerpt']) ? trim($decoded['excerpt']) : '';
        if ($excerpt === '') {
            return null;
        }

        $picked = [];
        if (!empty($available_categories) && isset($decoded['category_ids']) && is_array($decoded['category_ids'])) {
            $valid_ids = array_map(static fn($c) => (int) $c['id'], $available_categories);
            foreach ($decoded['category_ids'] as $cid) {
                $cid = (int) $cid;
                if (in_array($cid, $valid_ids, true)) {
                    $picked[] = $cid;
                }
            }
            $picked = array_values(array_unique($picked));
        }

        return [
            'title'        => sanitize_text_field($title),
            'content'      => wp_kses_post($content),
            'excerpt'      => sanitize_textarea_field($excerpt),
            'category_ids' => $picked,
        ];
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
     * @return array{title:string, content:string, excerpt:string, category_ids:int[]}
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
            'title'        => $title,
            'content'      => $content,
            'excerpt'      => $excerpt,
            'category_ids' => [],
        ];
    }
}
