<?php
namespace AutoQuill\AI;

use AutoQuill\Core\Constants as C;
use AutoQuill\Core\Logger;
use AutoQuill\Database\ArticlesRepository;
use AutoQuill\Database\SourcesRepository;
use AutoQuill\Database\TopicsRepository;

class Writer {
    /** Fallback word target when the body prompt names no word count. */
    const DEFAULT_TARGET_WORDS = 1200;

    /** Never go below the value this plugin used to hardcode - no regression. */
    const MAX_TOKENS_FLOOR = 6500;

    /** Stays under gpt-4o-mini's 16384 output cap. */
    const MAX_TOKENS_CAP = 16000;

    /** How much of the scraped article text is handed to the model. */
    const SOURCE_CONTENT_LIMIT = 8000;

    public static function generate_post(\WP_REST_Request $request): \WP_REST_Response {
        // One call runs with a 90s timeout and can be retried once on
        // truncation, so PHP needs more headroom than a default
        // max_execution_time of 30. Best effort - hosts may disable this.
        @set_time_limit(200);

        $params = $request->get_json_params() ?: [];

        $source = self::resolve_source($params);
        if (is_wp_error($source)) {
            $data   = $source->get_error_data();
            $status = (int) (is_array($data) && isset($data['status']) ? $data['status'] : 400);
            Logger::warning('writer', 'Quelle für die Generierung nicht auflösbar', [
                'code'    => $source->get_error_code(),
                'message' => $source->get_error_message(),
                'params'  => array_intersect_key($params, array_flip(['topic_id', 'topic_index', 'article_id', 'title'])),
            ]);
            return new \WP_REST_Response(['error' => $source->get_error_message()], $status);
        }

        $article     = $source['article'];
        $topic       = $source['topic'];
        $topic_id    = $source['topic_id'];
        $topic_index = $source['topic_index'];

        Logger::info('writer', 'generate_post-Request', [
            'topic_id'    => $topic_id,
            'topic_index' => $topic_index,
            'article_id'  => $article ? (int) $article->id : 0,
            'title'       => (string) ($topic['title'] ?? ''),
            'has_content' => $article && !empty($article->content),
        ]);

        $available_categories = self::get_available_categories();
        $result = self::write_blog_post($topic, $available_categories, $article);

        if (is_wp_error($result)) {
            $code = $result->get_error_code() === 'no_api_key' ? 400 : 502;
            Logger::error('writer', 'Blog-Post-Generierung fehlgeschlagen', [
                'topic_id'   => $topic_id,
                'article_id' => $article ? (int) $article->id : 0,
                'code'       => $result->get_error_code(),
                'message'    => $result->get_error_message(),
            ]);
            return new \WP_REST_Response(['error' => $result->get_error_message()], $code);
        }

        // Only a topics-backed generation has a row to mark; generating straight
        // from a feed entry must not synthesize one.
        if ($topic_id > 0) {
            (new TopicsRepository())->mark_generated(
                $topic_id,
                max(0, $topic_index),
                (string) ($topic['title'] ?? '')
            );
        }

        Logger::info('writer', 'Blog-Post generiert', [
            'topic_id'     => $topic_id,
            'article_id'   => $article ? (int) $article->id : 0,
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
            'topic'                => $topic,
            'topic_id'             => $topic_id,
            'topic_index'          => $topic_index,
            // Carried back so publish-post can record the provenance without
            // re-running the fragile title match against the topics JSON blob.
            'article_id'           => $article ? (int) $article->id : 0,
        ]);
    }

    /**
     * Everything the generate screen needs to show what the model works from.
     *
     * The only public seam into the source pipeline: resolve_source() and
     * build_source_block() stay private, because the latter is prompt-assembly
     * detail (field labels, the character cut) that moves whenever the prompt
     * moves. One purpose-shaped method is the stabler contract.
     *
     * @param array{article_id?:int, topic_id?:int, topic_index?:int, title?:string} $params
     * @return array{article: ?object, topic: array, topic_id: int, topic_index: int,
     *               ai_source: string, raw_html: string, truncated: bool}|\WP_Error
     */
    public static function source_preview(array $params) {
        $source = self::resolve_source($params);
        if (is_wp_error($source)) {
            return $source;
        }

        $article  = $source['article'];
        $raw_html = $article ? (string) $article->content : '';
        $stripped = wp_strip_all_tags($raw_html);
        $length   = function_exists('mb_strlen') ? mb_strlen($stripped) : strlen($stripped);

        return [
            'article'     => $article,
            'topic'       => $source['topic'],
            'topic_id'    => $source['topic_id'],
            'topic_index' => $source['topic_index'],
            'ai_source'   => self::build_source_block($article, $source['topic']),
            'raw_html'    => $raw_html,
            'truncated'   => $length > self::SOURCE_CONTENT_LIMIT,
        ];
    }

    /**
     * Resolves what to write about, from either of two entry points.
     *
     * The feed tab sends {article_id}; the topics tab sends
     * {topic_id, topic_index}. {topic_id, title} is still accepted so a browser
     * holding a cached pre-1.2.0 admin.js keeps working for one release.
     *
     * @return array{article: ?object, topic: array, topic_id: int, topic_index: int}|\WP_Error
     */
    private static function resolve_source(array $params) {
        $article_id = (int) ($params['article_id'] ?? 0);

        if ($article_id > 0) {
            $article = (new ArticlesRepository())->find($article_id);
            if (!$article) {
                return new \WP_Error(
                    'article_not_found',
                    __('Feed-Eintrag nicht gefunden', 'auto-quill'),
                    ['status' => 404]
                );
            }

            return [
                'article' => $article,
                'topic'   => [
                    'title'      => (string) $article->title,
                    'summary'    => (string) $article->description,
                    'article_id' => $article_id,
                ],
                'topic_id'    => 0,
                'topic_index' => -1,
            ];
        }

        $topic_id = (int) ($params['topic_id'] ?? 0);
        if ($topic_id <= 0) {
            return new \WP_Error(
                'missing_source',
                __('topic_id oder article_id erforderlich', 'auto-quill'),
                ['status' => 400]
            );
        }

        $row = (new TopicsRepository())->find($topic_id);
        if (!$row) {
            return new \WP_Error(
                'topic_not_found',
                __('Topic nicht gefunden', 'auto-quill'),
                ['status' => 404]
            );
        }

        $topics_data = json_decode($row->topics, true) ?: [];
        $index       = array_key_exists('topic_index', $params) ? (int) $params['topic_index'] : -1;
        $selected    = null;

        if ($index >= 0 && isset($topics_data[$index]) && is_array($topics_data[$index])) {
            $selected = $topics_data[$index];
        } else {
            $title = (string) ($params['title'] ?? '');
            foreach ($topics_data as $i => $candidate) {
                if (is_array($candidate) && (string) ($candidate['title'] ?? '') === $title) {
                    $selected = $candidate;
                    $index    = (int) $i;
                    break;
                }
            }
        }

        if (!$selected) {
            Logger::warning('writer', 'Ausgewähltes Thema nicht in topics-Daten gefunden', [
                'topic_id'         => $topic_id,
                'topic_index'      => $index,
                'title'            => (string) ($params['title'] ?? ''),
                'available_titles' => array_map(
                    static fn($t) => is_array($t) ? ($t['title'] ?? '') : '',
                    $topics_data
                ),
            ]);
            return new \WP_Error(
                'topic_entry_not_found',
                __('Ausgewähltes Thema nicht gefunden', 'auto-quill'),
                ['status' => 404]
            );
        }

        return [
            'article'     => self::load_source_article($selected),
            'topic'       => $selected,
            'topic_id'    => $topic_id,
            'topic_index' => $index,
        ];
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

        $result = self::run_combined_step(
            $client,
            $system,
            $settings,
            $defaults,
            $topic,
            $source_block,
            $categories_list,
            $available_categories
        );

        if (is_wp_error($result)) {
            return $result;
        }

        // Appended after parse_combined_response() ran wp_kses_post(), so the
        // link is already visible in the dashboard preview and cannot be
        // sanitized away. The round trip survives to wp_insert_post() because
        // publishPost() sends back the server's own string from
        // data('post-content'), not the rendered DOM - making the preview
        // editable later would break that.
        $result['content'] = self::append_source_link($result['content'], $article);

        return $result;
    }

    /**
     * Appends the mandatory link to the original article. Done server-side on
     * purpose: the source URL is in the prompt, but nothing makes the model
     * emit it, so a link in the generated HTML would be incidental at best.
     */
    private static function append_source_link(string $content, $article): string {
        if (!$article || empty($article->article_url)) {
            return $content;
        }

        // Merge over the defaults: installs upgrading from an earlier version
        // have no source_link_* keys stored yet, and the feature must be on for
        // them without requiring a settings save first.
        $stored   = get_option(C::OPTION_KEY, []);
        $settings = array_merge(C::defaults(), is_array($stored) ? $stored : []);

        if (empty($settings['source_link_enabled'])) {
            return $content;
        }

        $url = (string) $article->article_url;
        if (strpos($content, $url) !== false) {
            return $content;
        }

        // Feed-supplied URLs are untrusted; esc_url() blanks out schemes like
        // javascript:. Rather than emit a dead <a href="">, drop the block.
        $safe_url = esc_url($url);
        if ($safe_url === '') {
            return $content;
        }

        $article_title = trim((string) $article->title);
        if ($article_title === '') {
            $article_title = $url;
        }

        $feed_name = '';
        $source_id = (int) ($article->source_id ?? 0);
        if ($source_id > 0) {
            $source = (new SourcesRepository())->find($source_id);
            if ($source) {
                $feed_name = (string) $source->title;
            }
        }

        $template = trim((string) ($settings['source_link_template'] ?? ''));
        if ($template === '') {
            $template = C::DEFAULT_SOURCE_LINK_TEMPLATE;
        }

        $anchor = sprintf(
            '<a href="%s" target="_blank" rel="nofollow noopener">%s</a>',
            $safe_url,
            esc_html($article_title)
        );

        // The template is plain text (sanitize_textarea_field strips "<"), so
        // escape it first and only then splice in the already escaped anchor.
        // Escaping after substitution would mangle the markup.
        $line = strtr(esc_html($template), [
            '{source_link}'   => $anchor,
            '{article_title}' => esc_html($article_title),
            '{source_url}'    => $safe_url,
            '{feed_name}'     => esc_html($feed_name),
        ]);

        return $content . "\n" . '<p class="auto-quill-source"><em>' . $line . '</em></p>';
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
        $prompt     = self::build_combined_prompt($settings, $defaults, $topic, $source_block, $categories_list);
        $max_tokens = self::estimate_max_tokens(self::resolve_prompt($settings, $defaults, 'prompt_body'));

        $raw = self::chat_with_token_retry($client, $system, $prompt, $max_tokens, 0.7);
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

        $raw_retry = self::chat_with_token_retry($client, $system, $retry_prompt, $max_tokens, 0.3);
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

    /**
     * One chat call, retried once with a larger budget if the model hit the
     * token ceiling. Client returns WP_Error('truncated') for both providers
     * (OpenAI finish_reason=length, Claude stop_reason=max_tokens), so the
     * estimate below only has to be roughly right.
     *
     * @return string|\WP_Error
     */
    private static function chat_with_token_retry(Client $client, string $system, string $prompt, int $max_tokens, float $temperature) {
        $opts = [
            'max_tokens'  => $max_tokens,
            'temperature' => $temperature,
            'timeout'     => 90,
            'json_shape'  => 'object',
        ];

        $raw = $client->chat($system, $prompt, $opts);

        if (!is_wp_error($raw) || $raw->get_error_code() !== 'truncated') {
            return $raw;
        }

        $retry_tokens = min((int) round($max_tokens * 1.6), self::MAX_TOKENS_CAP);
        if ($retry_tokens <= $max_tokens) {
            return $raw;
        }

        Logger::warning('writer', 'Antwort abgeschnitten – Wiederholung mit höherem Token-Budget', [
            'max_tokens'   => $max_tokens,
            'retry_tokens' => $retry_tokens,
        ]);

        $opts['max_tokens'] = $retry_tokens;
        return $client->chat($system, $prompt, $opts);
    }

    /**
     * Derives the completion budget from the word count the body prompt asks
     * for, so raising the target length in the settings does not turn into a
     * hard WP_Error('truncated').
     */
    private static function estimate_max_tokens(string $body_prompt): int {
        $words = self::extract_target_words($body_prompt);

        // German runs roughly 1.5-2.5 tokens per word; on top of that come HTML
        // tags, JSON string escaping, the title, the excerpt and category_ids.
        $tokens = (int) ceil($words * 4) + 1000;
        $tokens = max(self::MAX_TOKENS_FLOOR, min(self::MAX_TOKENS_CAP, $tokens));

        /**
         * Filters the completion token budget for blog post generation.
         * Needed for models whose output cap differs from gpt-4o-mini's, since
         * the model fields in the settings accept free text.
         *
         * @param int    $tokens      Derived budget.
         * @param int    $words       Word count parsed out of the body prompt.
         * @param string $body_prompt The resolved body prompt.
         */
        return (int) apply_filters('auto_quill_max_tokens', $tokens, $words, $body_prompt);
    }

    /**
     * Largest word count mentioned in the body prompt. Tolerates ranges
     * ("800-1200 Wörter") and German thousands separators ("1.500 Wörter").
     */
    private static function extract_target_words(string $body_prompt): int {
        $pattern = '/(\d[\d.\x{202F}\x{00A0} ]{0,6})\s*(?:[-\x{2013}\x{2014}]\s*(\d[\d.\x{202F}\x{00A0} ]{0,6})\s*)?(?:W\x{00F6}rtern|W\x{00F6}rter|Worte|words)/iu';

        if (!preg_match_all($pattern, $body_prompt, $matches, PREG_SET_ORDER)) {
            return self::DEFAULT_TARGET_WORDS;
        }

        $max = 0;
        foreach ($matches as $set) {
            foreach ([1, 2] as $group) {
                if (!isset($set[$group]) || $set[$group] === '') {
                    continue;
                }
                $value = (int) preg_replace('/\D/', '', $set[$group]);
                if ($value > $max) {
                    $max = $value;
                }
            }
        }

        // A custom prompt without any word count must land on the floor, not below it.
        return $max > 0 ? $max : self::DEFAULT_TARGET_WORDS;
    }

    private static function build_source_block($article, array $topic): string {
        $title       = $article ? (string) $article->title       : (string) ($topic['title']   ?? '');
        $description = $article ? (string) $article->description : (string) ($topic['summary'] ?? '');
        $content     = $article ? (string) $article->content     : '';

        $description = wp_strip_all_tags($description);
        $content     = wp_strip_all_tags($content);

        if (function_exists('mb_substr')) {
            $content = mb_substr($content, 0, self::SOURCE_CONTENT_LIMIT);
        } else {
            $content = substr($content, 0, self::SOURCE_CONTENT_LIMIT);
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
            'content'      => self::append_source_link($content, $article),
            'excerpt'      => $excerpt,
            'category_ids' => [],
        ];
    }
}
