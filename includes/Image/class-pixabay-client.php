<?php
namespace AutoQuill\Image;

use AutoQuill\Core\Constants as C;
use AutoQuill\Core\Logger;

class PixabayClient {
    const ENDPOINT      = 'https://pixabay.com/api/';
    const CACHE_GROUP   = 'auto_quill_pixabay_';
    const CACHE_TTL     = HOUR_IN_SECONDS;
    const MAX_PER_PAGE  = 50;
    const MIN_PER_PAGE  = 3;

    /**
     * @return array{images: array<int, array<string, mixed>>, total: int, total_hits: int, page: int, per_page: int}|\WP_Error
     */
    public function search(string $query, int $page = 1, int $per_page = 20) {
        $api_key = C::pixabay_api_key();
        if ($api_key === '') {
            return new \WP_Error(
                'no_pixabay_key',
                __('Pixabay-API-Key fehlt. Bitte in Einstellungen hinterlegen.', 'auto-quill')
            );
        }

        $query    = trim($query);
        $page     = max(1, $page);
        $per_page = max(self::MIN_PER_PAGE, min(self::MAX_PER_PAGE, $per_page));

        $cache_key = self::CACHE_GROUP . md5($query . '|' . $page . '|' . $per_page);
        $cached    = get_transient($cache_key);
        if (is_array($cached)) {
            Logger::info('pixabay', 'Treffer aus Cache', [
                'query'    => $query,
                'page'     => $page,
                'per_page' => $per_page,
            ]);
            return $cached;
        }

        $url = add_query_arg(
            [
                'key'         => $api_key,
                'q'           => $query,
                'image_type'  => 'photo',
                'safesearch'  => 'true',
                'per_page'    => $per_page,
                'page'        => $page,
                'lang'        => 'de',
            ],
            self::ENDPOINT
        );

        $started = microtime(true);
        Logger::info('pixabay', 'Suche startet', [
            'query'    => $query,
            'page'     => $page,
            'per_page' => $per_page,
        ]);

        $response = wp_remote_get($url, [
            'timeout' => 20,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        $duration_ms = (int) round((microtime(true) - $started) * 1000);

        if (is_wp_error($response)) {
            Logger::error('pixabay', 'HTTP-Fehler', [
                'query'       => $query,
                'duration_ms' => $duration_ms,
                'wp_error'    => $response->get_error_message(),
            ]);
            return $response;
        }

        $status   = (int) wp_remote_retrieve_response_code($response);
        $raw_body = (string) wp_remote_retrieve_body($response);
        $body     = json_decode($raw_body, true);

        if ($status >= 400 || !is_array($body) || !isset($body['hits'])) {
            Logger::error('pixabay', 'API-Fehler', [
                'query'        => $query,
                'status'       => $status,
                'duration_ms'  => $duration_ms,
                'body_excerpt' => mb_substr($raw_body, 0, 500),
            ]);
            return new \WP_Error(
                'pixabay_api_error',
                sprintf(
                    /* translators: %d: HTTP status code */
                    __('Pixabay-API-Fehler (HTTP %d)', 'auto-quill'),
                    $status
                )
            );
        }

        $images = [];
        foreach ((array) $body['hits'] as $hit) {
            if (!is_array($hit)) {
                continue;
            }
            $images[] = [
                'id'          => (int) ($hit['id'] ?? 0),
                'preview_url' => (string) ($hit['previewURL'] ?? ''),
                'large_url'   => (string) ($hit['webformatURL'] ?? ''),
                'page_url'    => (string) ($hit['pageURL'] ?? ''),
                'user'        => (string) ($hit['user'] ?? ''),
                'tags'        => (string) ($hit['tags'] ?? ''),
                'width'       => (int) ($hit['webformatWidth'] ?? 0),
                'height'      => (int) ($hit['webformatHeight'] ?? 0),
            ];
        }

        $result = [
            'images'     => $images,
            'total'      => (int) ($body['total'] ?? 0),
            'total_hits' => (int) ($body['totalHits'] ?? 0),
            'page'       => $page,
            'per_page'   => $per_page,
        ];

        Logger::info('pixabay', 'Antwort erhalten', [
            'query'       => $query,
            'status'      => $status,
            'duration_ms' => $duration_ms,
            'hits'        => count($images),
            'total_hits'  => $result['total_hits'],
        ]);

        set_transient($cache_key, $result, self::CACHE_TTL);

        return $result;
    }
}
