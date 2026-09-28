<?php
namespace AutoQuill\Rest;

use AutoQuill\AI\ModelCatalog;
use AutoQuill\Core\Constants as C;

class ModelsService {
    /**
     * POST, not GET: it may carry a freshly typed, not yet saved API key,
     * which must not end up in a URL or an access log.
     */
    public static function list_models(\WP_REST_Request $request): \WP_REST_Response {
        $provider = (string) $request->get_param('provider');
        $api_key  = trim((string) $request->get_param('api_key'));
        $refresh  = (bool) $request->get_param('refresh');

        if (!in_array($provider, C::AI_PROVIDERS, true)) {
            return new \WP_REST_Response(['error' => __('Unbekannter KI-Provider.', 'auto-quill')], 400);
        }

        // The constant always wins, exactly as it does for the actual requests.
        if ($api_key === '' || C::ai_api_key_from_constant()) {
            $api_key = C::ai_api_key($provider);
        }

        // Only the custom endpoint has a configurable URL; a not yet saved
        // one may come from the form.
        $base_url = '';
        if ($provider === 'custom') {
            $base_url = rtrim(esc_url_raw(trim((string) $request->get_param('base_url')), ['http', 'https']), '/');
        }

        $models = ModelCatalog::fetch($provider, $api_key, $refresh, $base_url);

        if (is_wp_error($models)) {
            return new \WP_REST_Response(['error' => $models->get_error_message()], 502);
        }

        return new \WP_REST_Response(['models' => $models]);
    }
}
