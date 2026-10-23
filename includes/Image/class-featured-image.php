<?php
namespace AutoQuill\Image;

use AutoQuill\Core\Logger;

/**
 * Downloads a remote image into the media library and sets it as a post's
 * featured image, retrying transient failures.
 *
 * Pixabay answers bursts of requests with "429 Too Many Requests" and
 * occasionally with 5xx; download_url() reports every non-200 status as
 * "http_404" and keeps the real status in the error data, so the retry
 * decision looks at that data and not at the error code.
 */
class FeaturedImage {
    const MAX_ATTEMPTS = 3;

    /**
     * Seconds to wait before attempt 2, 3, ... Kept short: the caller is a
     * REST request the user is waiting on.
     */
    const RETRY_DELAYS = [2, 5];

    /**
     * @return array{status: string, attachment_id: int, attempts: int, error: string}
     *         status is 'set' or 'failed'.
     */
    public static function attach(int $post_id, string $image_url, string $image_alt = ''): array {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attempt  = 0;
        $sideload = null;

        while ($attempt < self::MAX_ATTEMPTS) {
            if ($attempt > 0) {
                $delays = self::RETRY_DELAYS;
                $delay  = $delays[min($attempt, count($delays)) - 1];
                if (function_exists('set_time_limit')) {
                    @set_time_limit(60);
                }
                sleep((int) $delay);
            }
            $attempt++;

            $sideload = media_sideload_image($image_url, $post_id, $image_alt !== '' ? $image_alt : null, 'id');

            if (!is_wp_error($sideload)) {
                break;
            }

            $retry = $attempt < self::MAX_ATTEMPTS && self::is_retryable($sideload);
            Logger::warning('posts', 'Featured-Image-Sideload fehlgeschlagen', [
                'post_id'   => $post_id,
                'image_url' => $image_url,
                'attempt'   => $attempt,
                'code'      => $sideload->get_error_code(),
                'status'    => self::http_status($sideload),
                'message'   => $sideload->get_error_message(),
                'retry'     => $retry,
            ]);

            if (!$retry) {
                break;
            }
        }

        if (is_wp_error($sideload)) {
            return [
                'status'        => 'failed',
                'attachment_id' => 0,
                'attempts'      => $attempt,
                'error'         => self::describe($sideload),
            ];
        }

        $attachment_id = (int) $sideload;
        set_post_thumbnail($post_id, $attachment_id);
        if ($image_alt !== '') {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $image_alt);
        }
        Logger::info('posts', 'Featured Image gesetzt', [
            'post_id'       => $post_id,
            'attachment_id' => $attachment_id,
            'attempts'      => $attempt,
        ]);

        return [
            'status'        => 'set',
            'attachment_id' => $attachment_id,
            'attempts'      => $attempt,
            'error'         => '',
        ];
    }

    /**
     * Timeouts, connection errors, 408, 425, 429 and 5xx are worth another
     * try; a real 404 or 403 (expired Pixabay link) or an invalid file is not.
     */
    private static function is_retryable(\WP_Error $error): bool {
        if ($error->get_error_code() === 'http_request_failed') {
            return true;
        }

        $status = self::http_status($error);
        if ($status === 0) {
            return stripos($error->get_error_message(), 'Too Many Requests') !== false;
        }

        return in_array($status, [408, 425, 429], true) || $status >= 500;
    }

    private static function http_status(\WP_Error $error): int {
        $data = $error->get_error_data();
        return is_array($data) && isset($data['code']) ? (int) $data['code'] : 0;
    }

    /**
     * A message the user can act on, shown on the generate and interview screen.
     */
    private static function describe(\WP_Error $error): string {
        $status = self::http_status($error);

        if ($status === 429 || stripos($error->get_error_message(), 'Too Many Requests') !== false) {
            return __('Pixabay hat zu viele Anfragen gemeldet (HTTP 429). Bitte kurz warten und das Bild erneut übernehmen.', 'auto-quill');
        }
        if (in_array($status, [403, 404, 410], true)) {
            return __('Der Bild-Link ist nicht mehr gültig. Bitte im WordPress-Editor ein anderes Beitragsbild setzen.', 'auto-quill');
        }
        if ($status > 0) {
            return sprintf(
                /* translators: 1: HTTP status code, 2: error message */
                __('Das Bild konnte nicht geladen werden (HTTP %1$d: %2$s).', 'auto-quill'),
                $status,
                $error->get_error_message()
            );
        }

        return sprintf(
            /* translators: %s: error message */
            __('Das Bild konnte nicht geladen werden: %s', 'auto-quill'),
            $error->get_error_message()
        );
    }
}
