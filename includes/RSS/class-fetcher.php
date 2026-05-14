<?php
namespace AutoQuill\RSS;

use AutoQuill\Core\Constants as C;
use AutoQuill\Core\Logger;
use AutoQuill\Database\ArticlesRepository;
use AutoQuill\Database\SourcesRepository;

class Fetcher {
    public static function fetch_feeds(): void {
        $sources = (new SourcesRepository())->active();
        if (empty($sources)) {
            Logger::warning('fetcher', 'Keine aktiven RSS-Quellen konfiguriert');
            return;
        }

        Logger::info('fetcher', 'RSS-Fetch-Run startet', ['source_count' => count($sources)]);

        foreach ($sources as $source) {
            self::fetch_feed((int) $source->id, (string) $source->feed_url);
        }

        $settings = get_option(C::OPTION_KEY, C::defaults());
        $lookback = (int) ($settings['rss_lookback_days'] ?? 7);
        if ($lookback > 0) {
            $deleted = (new ArticlesRepository())->delete_older_than($lookback);
            Logger::info('fetcher', 'Alte Artikel bereinigt', ['lookback_days' => $lookback, 'deleted' => $deleted]);
        }

        Logger::info('fetcher', 'RSS-Fetch-Run abgeschlossen, Topic-Selection wird angestoßen');
        do_action(C::CRON_SELECT);
    }

    public static function fetch_feed(int $source_id, string $feed_url): void {
        require_once ABSPATH . WPINC . '/class-simplepie.php';

        $started = microtime(true);
        Logger::info('fetcher', 'Feed wird abgerufen', ['source_id' => $source_id, 'feed_url' => $feed_url]);

        $feed = new \SimplePie();
        $feed->set_feed_url($feed_url);
        $feed->init();

        if ($feed->error()) {
            Logger::error('fetcher', 'SimplePie-Fehler beim Fetchen', [
                'source_id' => $source_id,
                'feed_url'  => $feed_url,
                'error'     => (string) $feed->error(),
            ]);
            return;
        }

        $articles = new ArticlesRepository();
        $items    = $feed->get_items(0, 20);

        $settings = get_option(C::OPTION_KEY, C::defaults());
        $lookback = (int) ($settings['rss_lookback_days'] ?? 7);
        $cutoff   = $lookback > 0 ? (time() - $lookback * DAY_IN_SECONDS) : 0;

        $stats = ['items' => count($items), 'inserted' => 0, 'duplicates' => 0, 'too_old' => 0, 'failed' => 0];

        foreach ($items as $item) {
            $link = (string) $item->get_link();
            $hash = md5($feed_url . $link);

            if ($cutoff > 0) {
                $ts = (int) $item->get_date('U');
                if ($ts > 0 && $ts < $cutoff) {
                    $stats['too_old']++;
                    continue;
                }
            }

            if ($articles->exists_by_hash($hash)) {
                $stats['duplicates']++;
                continue;
            }

            $author_obj = $item->get_author();
            $content    = self::fetch_article_content($link);
            $insert_id  = $articles->insert([
                'source_id'      => $source_id,
                'title'          => mb_substr((string) $item->get_title(), 0, 255),
                'description'    => mb_substr(strip_tags((string) $item->get_description()), 0, 1000),
                'content'        => $content,
                'author'         => $author_obj ? (string) $author_obj->name : 'Unknown',
                'published_date' => (string) $item->get_date('Y-m-d H:i:s'),
                'article_url'    => $link,
                'article_hash'   => $hash,
            ]);

            if ($insert_id) {
                $stats['inserted']++;
            } else {
                $stats['failed']++;
            }
        }

        $stats['duration_ms'] = (int) round((microtime(true) - $started) * 1000);
        $stats['source_id']   = $source_id;
        $stats['feed_url']    = $feed_url;
        Logger::info('fetcher', 'Feed-Verarbeitung fertig', $stats);

        $feed->__destruct();
        unset($feed);
    }

    private static function fetch_article_content(string $link): string {
        if ($link === '') {
            return '';
        }
        $response = wp_safe_remote_get($link, [
            'timeout'     => 10,
            'redirection' => 3,
            'user-agent'  => 'AutoQuill/' . AUTO_QUILL_VERSION,
        ]);
        if (is_wp_error($response)) {
            Logger::warning('fetcher', 'Artikel-Content-Fetch fehlgeschlagen', [
                'url'      => $link,
                'wp_error' => $response->get_error_message(),
            ]);
            return '';
        }
        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status !== 200) {
            Logger::warning('fetcher', 'Artikel-Content nicht 200 OK', ['url' => $link, 'status' => $status]);
            return '';
        }
        return mb_substr((string) wp_remote_retrieve_body($response), 0, 50000);
    }
}
