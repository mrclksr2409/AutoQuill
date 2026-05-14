<?php
namespace AutoQuill\AI;

use AutoQuill\Core\Constants as C;
use AutoQuill\Core\Logger;
use AutoQuill\Database\ArticlesRepository;
use AutoQuill\Database\TopicsRepository;

class Selector {
    public static function select_top_topics(): void {
        Logger::info('selector', 'Topic-Selection startet');

        $articles = (new ArticlesRepository())->recent(24, 50);

        if (empty($articles)) {
            Logger::warning('selector', 'Keine neuen Artikel zum Analysieren gefunden (Zeitfenster: 24h)');
            return;
        }

        Logger::info('selector', 'Artikel geladen', ['count' => count($articles)]);

        $topics = self::analyze_articles($articles);

        if (!$topics || is_wp_error($topics)) {
            $msg = is_wp_error($topics) ? $topics->get_error_message() : 'leer';
            Logger::error('selector', 'Fehler beim Analysieren der Artikel', ['reason' => $msg]);
            return;
        }

        $today = current_time('Y-m-d');
        $ok    = (new TopicsRepository())->upsert_for_date($today, $topics);

        Logger::info('selector', 'Topics gespeichert', [
            'date'         => $today,
            'topics_count' => count($topics),
            'db_ok'        => (bool) $ok,
        ]);

        do_action('auto_quill_topics_selected', $topics);
    }

    private static function analyze_articles(array $articles) {
        $settings    = get_option(C::OPTION_KEY, C::defaults());
        $ai_provider = is_array($settings) ? ($settings['ai_provider'] ?? 'openai') : 'openai';

        if ($ai_provider !== 'openai' && $ai_provider !== 'claude') {
            Logger::warning('selector', 'Kein gültiger AI-Provider konfiguriert, Fallback-Selektion aktiv', ['ai_provider' => $ai_provider]);
            return self::fallback_analyze($articles);
        }

        $articles_text = '';
        foreach ($articles as $article) {
            $description = mb_substr((string) $article->description, 0, 400);
            $articles_text .= "ID: {$article->id}\n";
            $articles_text .= "Titel: {$article->title}\n";
            $articles_text .= "Beschreibung: {$description}\n";
            $articles_text .= "---\n\n";
        }

        $prompt = "Analysiere die folgenden Artikel und wähle die 5 interessantesten Themen aus.\n"
            . "Wähle für jedes Thema genau einen Artikel aus der Liste und gib dessen ID zurück.\n"
            . "Antworte AUSSCHLIESSLICH mit einem gültigen JSON-Objekt (kein Markdown, kein Codeblock) mit der Struktur:\n"
            . "{\"topics\": [{\"article_id\": <int aus der obigen Liste>, \"title\": \"...\", \"summary\": \"...\", \"reason\": \"...\"}]}\n\n"
            . $articles_text;

        $raw = (new Client())->chat(
            'Du bist ein hilfreicher Content-Analyzer. Antworte ausschließlich mit gültigem JSON.',
            $prompt,
            ['max_tokens' => 1500, 'temperature' => 0.7, 'timeout' => 30, 'json_shape' => 'object']
        );

        if (is_wp_error($raw)) {
            return $raw;
        }

        $topics = self::parse_topics((string) $raw);
        if (!is_array($topics)) {
            Logger::error('selector', 'KI-Antwort konnte nicht als JSON geparst werden', [
                'json_error'  => JsonExtractor::last_error(),
                'raw_excerpt' => mb_substr((string) $raw, 0, 1000),
            ]);
            return new \WP_Error('ai_parse_failed', 'KI-Antwort konnte nicht als JSON geparst werden');
        }

        Logger::info('selector', 'Topics aus KI-Antwort extrahiert', ['parsed_count' => count($topics)]);

        return self::attach_article_ids($topics, $articles);
    }

    private static function parse_topics(string $content): ?array {
        $obj = JsonExtractor::extract_object($content);
        if (is_array($obj) && isset($obj['topics']) && is_array($obj['topics'])) {
            return $obj['topics'];
        }
        // Fallback: some providers may still return a top-level array.
        $arr = JsonExtractor::extract_array($content);
        if (is_array($arr)) {
            return $arr;
        }
        return null;
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
