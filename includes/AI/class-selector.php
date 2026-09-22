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
            . "Bewerte jedes Thema mit \"rating\" von 0 bis 100 danach, wie lohnend ein eigener Blog-Beitrag dazu wäre "
            . "(Relevanz für ein breites Publikum, Aktualität, inhaltliche Substanz). "
            . "Begründe die Bewertung in \"rating_reason\" mit einem kurzen Satz.\n"
            . "Antworte AUSSCHLIESSLICH mit einem gültigen JSON-Objekt (kein Markdown, kein Codeblock) mit der Struktur:\n"
            . "{\"topics\": [{\"article_id\": <int aus der obigen Liste>, \"title\": \"...\", \"summary\": \"...\", "
            . "\"rating\": <int 0-100>, \"rating_reason\": \"...\"}]}\n\n"
            . $articles_text;

        // 2500, not 1500: rating + rating_reason across five topics would blow
        // the old ceiling, and Client turns that into WP_Error('truncated'),
        // which would silently stop the daily topic selection altogether.
        $raw = (new Client())->chat(
            'Du bist ein hilfreicher Content-Analyzer. Antworte ausschließlich mit gültigem JSON.',
            $prompt,
            ['max_tokens' => 2500, 'temperature' => 0.7, 'timeout' => 30, 'json_shape' => 'object']
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

        return self::normalize_topics($topics, $articles);
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
     * Guarantees a valid article_id and a normalized rating on every topic, and
     * returns them in display order.
     */
    private static function normalize_topics(array $topics, array $articles): array {
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

            $topic['rating'] = self::normalize_rating($topic['rating'] ?? null);

            $reason = sanitize_text_field((string) ($topic['rating_reason'] ?? ''));
            $topic['rating_reason'] = mb_substr($reason, 0, 200);

            $normalized[] = $topic;
        }

        return self::sort_by_rating($normalized);
    }

    /**
     * Accepts 85, "85", 85.0 and "85%". A missing rating stays null rather than
     * becoming 0 - an unrated topic is not a badly rated one.
     */
    private static function normalize_rating($raw): ?int {
        if ($raw === null || $raw === '' || is_array($raw) || is_bool($raw)) {
            return null;
        }

        if (is_string($raw)) {
            if (!preg_match('/-?\d+(?:[.,]\d+)?/', $raw, $m)) {
                return null;
            }
            $raw = str_replace(',', '.', $m[0]);
        }

        if (!is_numeric($raw)) {
            return null;
        }

        return max(0, min(100, (int) round((float) $raw)));
    }

    /**
     * Sorted here, before persisting, so the stored order is also the displayed
     * order. Sorting at render time instead would desynchronize the array index
     * the dashboard sends back as topic_index, and the wrong topic would be
     * generated.
     *
     * @param array<int, array> $topics
     * @return array<int, array>
     */
    public static function sort_by_rating(array $topics): array {
        $indexed = [];
        foreach ($topics as $i => $topic) {
            $indexed[] = [$i, $topic];
        }

        usort($indexed, static function ($a, $b) {
            $ra = $a[1]['rating'] ?? null;
            $rb = $b[1]['rating'] ?? null;

            // Unrated entries go last but keep their original relative order.
            if ($ra === null && $rb === null) {
                return $a[0] <=> $b[0];
            }
            if ($ra === null) {
                return 1;
            }
            if ($rb === null) {
                return -1;
            }
            if ($ra === $rb) {
                return $a[0] <=> $b[0];
            }
            return $rb <=> $ra;
        });

        return array_map(static fn($pair) => $pair[1], $indexed);
    }

    private static function fallback_analyze(array $articles): array {
        $now    = time();
        $topics = [];

        foreach (array_slice($articles, 0, 5) as $article) {
            $published = strtotime((string) $article->published_date);
            $age       = $published ? $now - $published : PHP_INT_MAX;

            if ($age <= 6 * HOUR_IN_SECONDS) {
                $rating = 85;
            } elseif ($age <= DAY_IN_SECONDS) {
                $rating = 70;
            } else {
                $rating = 55;
            }

            $topics[] = [
                'title'      => $article->title,
                'summary'    => substr((string) $article->description, 0, 200),
                'article_id' => (int) $article->id,
                'rating'     => $rating,
                // Spelled out so the UI never passes recency off as an AI verdict.
                'rating_reason' => __('Automatische Auswahl nach Aktualität (kein KI-Provider konfiguriert)', 'auto-quill'),
            ];
        }

        return self::sort_by_rating($topics);
    }
}
