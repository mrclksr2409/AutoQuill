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

        // The constant always wins, exactly as it does for the actual requests.
        if ($api_key === '' || C::ai_api_key_from_constant()) {
            $api_key = C::ai_api_key();
        }

        $models = ModelCatalog::fetch($provider, $api_key, $refresh);

        if (is_wp_error($models)) {
            return new \WP_REST_Response(['error' => $models->get_error_message()], 502);
        }

        return new \WP_REST_Response(['models' => $models]);
    }
}
