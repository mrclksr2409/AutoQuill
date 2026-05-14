<?php
namespace AutoQuill\AI;

use AutoQuill\Core\Constants as C;

class Client {
    /**
     * @param array{
     *     max_tokens?: int,
     *     temperature?: float,
     *     timeout?: int,
     *     openai_model?: string,
     *     claude_model?: string,
     * } $opts
     * @return string|\WP_Error
     */
    public function chat(string $system, string $user, array $opts = []) {
        $api_key = C::ai_api_key();
        if ($api_key === '') {
            return new \WP_Error('no_api_key', 'API Key nicht konfiguriert');
        }

        $settings = get_option(C::OPTION_KEY, C::defaults());
        $provider = is_array($settings) ? ($settings['ai_provider'] ?? 'openai') : 'openai';

        if ($provider === 'claude') {
            return $this->call_claude($api_key, $system, $user, $opts);
        }
        return $this->call_openai($api_key, $system, $user, $opts);
    }

    /**
     * @return string|\WP_Error
     */
    private function call_openai(string $api_key, string $system, string $user, array $opts) {
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => (int) ($opts['timeout'] ?? 60),
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'       => (string) ($opts['openai_model'] ?? 'gpt-3.5-turbo'),
                'messages'    => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user',   'content' => $user],
                ],
                'temperature' => (float) ($opts['temperature'] ?? 0.7),
                'max_tokens'  => (int)   ($opts['max_tokens']  ?? 1500),
            ]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['choices'][0]['message']['content'])) {
            return (string) $body['choices'][0]['message']['content'];
        }
        return new \WP_Error('api_error', 'OpenAI API-Fehler');
    }

    /**
     * @return string|\WP_Error
     */
    private function call_claude(string $api_key, string $system, string $user, array $opts) {
        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'timeout' => (int) ($opts['timeout'] ?? 60),
            'headers' => [
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'      => (string) ($opts['claude_model'] ?? 'claude-sonnet-4-6'),
                'max_tokens' => (int)    ($opts['max_tokens']   ?? 1500),
                'system'     => $system,
                'messages'   => [
                    ['role' => 'user', 'content' => $user],
                ],
            ]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['content'][0]['text'])) {
            return (string) $body['content'][0]['text'];
        }
        return new \WP_Error('api_error', 'Claude API-Fehler');
    }
}
