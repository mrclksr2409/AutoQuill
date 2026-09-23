<?php
namespace AutoQuill\AI;

use AutoQuill\Core\Constants as C;
use AutoQuill\Core\Logger;

/**
 * The models a provider offers for the given key, for the settings dropdown.
 *
 * Cached per provider and key hash: the list barely changes, and the settings
 * page asks for it on every visit.
 */
class ModelCatalog {
    const PROVIDERS = ['openai', 'claude'];

    /**
     * OpenAI's /v1/models lists everything the key can reach, including
     * embeddings, audio, image and moderation models that /chat/completions
     * rejects.
     */
    const OPENAI_INCLUDE = '/^(gpt-|chatgpt-|o\d)/';
    const OPENAI_EXCLUDE = '/(audio|realtime|tts|transcribe|search|image|embedding|instruct|moderation|codex|computer-use|deep-research)/';

    /**
     * @return array<int, array{id: string, label: string}>|\WP_Error
     */
    public static function fetch(string $provider, string $api_key, bool $refresh = false) {
        if (!in_array($provider, self::PROVIDERS, true)) {
            return new \WP_Error('invalid_provider', __('Unbekannter KI-Provider.', 'auto-quill'));
        }
        if ($api_key === '') {
            return new \WP_Error('no_api_key', __('Kein API-Schlüssel hinterlegt. Bitte zuerst einen Schlüssel eingeben.', 'auto-quill'));
        }

        $cache_key = self::cache_key($provider, $api_key);
        if (!$refresh) {
            $cached = get_transient($cache_key);
            if (is_array($cached) && !empty($cached)) {
                return $cached;
            }
        }

        $models = $provider === 'claude'
            ? self::fetch_claude($api_key)
            : self::fetch_openai($api_key);

        if (is_wp_error($models)) {
            return $models;
        }

        set_transient($cache_key, $models, C::MODELS_CACHE_TTL);
        return $models;
    }

    /**
     * Cached list for the configured key, without any HTTP request - so the
     * settings page can render a full dropdown before its script runs.
     *
     * @return array<int, array{id: string, label: string}>
     */
    public static function cached(string $provider): array {
        $api_key = C::ai_api_key();
        if ($api_key === '') {
            return [];
        }
        $cached = get_transient(self::cache_key($provider, $api_key));
        return is_array($cached) ? $cached : [];
    }

    private static function cache_key(string $provider, string $api_key): string {
        // Hashed: the key itself must never end up in wp_options.
        return C::MODELS_CACHE_PREFIX . $provider . '_' . substr(md5($api_key), 0, 12);
    }

    /**
     * @return array<int, array{id: string, label: string}>|\WP_Error
     */
    private static function fetch_openai(string $api_key) {
        $body = self::get_json('https://api.openai.com/v1/models', [
            'Authorization' => 'Bearer ' . $api_key,
        ], 'OpenAI');

        if (is_wp_error($body)) {
            return $body;
        }

        $rows = [];
        foreach ((array) ($body['data'] ?? []) as $model) {
            $id = (string) ($model['id'] ?? '');
            if ($id === '' || !preg_match(self::OPENAI_INCLUDE, $id) || preg_match(self::OPENAI_EXCLUDE, $id)) {
                continue;
            }
            $rows[] = ['id' => $id, 'created' => (int) ($model['created'] ?? 0)];
        }

        // Newest first, the same order Anthropic returns.
        usort($rows, static fn($a, $b) => [$b['created'], $a['id']] <=> [$a['created'], $b['id']]);

        return array_map(static fn($r) => ['id' => $r['id'], 'label' => $r['id']], $rows);
    }

    /**
     * @return array<int, array{id: string, label: string}>|\WP_Error
     */
    private static function fetch_claude(string $api_key) {
        $body = self::get_json('https://api.anthropic.com/v1/models?limit=1000', [
            'x-api-key'         => $api_key,
            'anthropic-version' => '2023-06-01',
        ], 'Claude');

        if (is_wp_error($body)) {
            return $body;
        }

        $out = [];
        foreach ((array) ($body['data'] ?? []) as $model) {
            $id = (string) ($model['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $name  = (string) ($model['display_name'] ?? '');
            $out[] = [
                'id'    => $id,
                'label' => $name !== '' && $name !== $id ? $name . ' (' . $id . ')' : $id,
            ];
        }

        return $out;
    }

    /**
     * @return array|\WP_Error
     */
    private static function get_json(string $url, array $headers, string $label) {
        $response = wp_remote_get($url, ['timeout' => 15, 'headers' => $headers]);

        if (is_wp_error($response)) {
            Logger::error('models', $label . ' Modellliste: HTTP-Fehler', ['wp_error' => $response->get_error_message()]);
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $raw    = (string) wp_remote_retrieve_body($response);
        $body   = json_decode($raw, true);

        if ($status === 401 || $status === 403) {
            return new \WP_Error('unauthorized', sprintf(
                /* translators: %s: provider name */
                __('Der API-Schlüssel wurde von %s abgelehnt. Passt er zum gewählten Provider?', 'auto-quill'),
                $label
            ));
        }

        if ($status >= 400 || !is_array($body) || !isset($body['data'])) {
            Logger::error('models', $label . ' Modellliste: API-Fehler', [
                'status'       => $status,
                'body_excerpt' => mb_substr($raw, 0, 500),
            ]);
            return new \WP_Error('api_error', sprintf(
                /* translators: 1: provider name, 2: HTTP status */
                __('Modellliste von %1$s konnte nicht geladen werden (HTTP %2$d).', 'auto-quill'),
                $label,
                $status
            ));
        }

        return $body;
    }
}
