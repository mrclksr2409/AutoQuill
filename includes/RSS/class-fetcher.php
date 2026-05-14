<?php
namespace AutoQuill\RSS;

use AutoQuill\Core\Constants as C;
use AutoQuill\Database\ArticlesRepository;
use AutoQuill\Database\SourcesRepository;

class Fetcher {
    public static function fetch_feeds(): void {
        $sources = (new SourcesRepository())->active();
        if (empty($sources)) {
            return;
        }

        foreach ($sources as $source) {
            self::fetch_feed((int) $source->id, (string) $source->feed_url);
        }

        $settings = get_option(C::OPTION_KEY, C::defaults());
        $lookback = (int) ($settings['rss_lookback_days'] ?? 7);
        if ($lookback > 0) {
            (new ArticlesRepository())->delete_older_than($lookback);
        }

        do_action(C::CRON_SELECT);
    }

    public static function fetch_feed(int $source_id, string $feed_url): void {
        require_once ABSPATH . WPINC . '/class-simplepie.php';

        $feed = new \SimplePie();
        $feed->set_feed_url($feed_url);
        $feed->init();

        if ($feed->error()) {
            error_log('AutoQuill: Fehler beim Fetchen von ' . $feed_url . ' - ' . $feed->error());
            return;
        }

        $articles = new ArticlesRepository();
        $items    = $feed->get_items(0, 20);

        $settings = get_option(C::OPTION_KEY, C::defaults());
        $lookback = (int) ($settings['rss_lookback_days'] ?? 7);
        $cutoff   = $lookback > 0 ? (time() - $lookback * DAY_IN_SECONDS) : 0;

        foreach ($items as $item) {
            $link = (string) $item->get_link();
            $hash = md5($feed_url . $link);

            if ($cutoff > 0) {
                $ts = (int) $item->get_date('U');
                if ($ts > 0 && $ts < $cutoff) {
                    continue;
                }
            }

            if ($articles->exists_by_hash($hash)) {
                continue;
            }

            $author_obj = $item->get_author();
            $articles->insert([
                'source_id'      => $source_id,
                'title'          => mb_substr((string) $item->get_title(), 0, 255),
                'description'    => mb_substr(strip_tags((string) $item->get_description()), 0, 1000),
                'content'        => (string) wp_remote_retrieve_body(wp_remote_get($link)),
                'author'         => $author_obj ? (string) $author_obj->name : 'Unknown',
                'published_date' => (string) $item->get_date('Y-m-d H:i:s'),
                'article_url'    => $link,
                'article_hash'   => $hash,
            ]);
        }

        $feed->__destruct();
        unset($feed);
    }
}
