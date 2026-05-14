<?php
namespace AutoQuill\AI;

use AutoQuill\Core\Constants as C;
use AutoQuill\Core\Logger;

class Client {
    /**
     * @param array{
     *     max_tokens?: int,
     *     temperature?: float,
     *     timeout?: int,
     *     openai_model?: string,
     *     claude_model?: string,
     *     json_mode?: bool,
     * } $opts
     * @return string|\WP_Error
     */
    public function chat(string $system, string $user, array $opts = []) {
        $api_key = C::ai_api_key();
        if ($api_key === '') {
            Logger::error('client', 'API Key nicht konfiguriert', ['system_len' => strlen($system), 'user_len' => strlen($user)]);
            return new \WP_Error('no_api_key', 'API Key nicht konfiguriert');
        }

        $settings = get_option(C::OPTION_KEY, C::defaults());
        if (!is_array($settings)) {
            $settings = C::defaults();
        }
        $provider = $settings['ai_provider'] ?? 'openai';

        if (!isset($opts['openai_model'])) {
            $opts['openai_model'] = $settings['openai_model'] ?? C::DEFAULT_OPENAI_MODEL;
        }
        if (!isset($opts['claude_model'])) {
            $opts['claude_model'] = $settings['claude_model'] ?? C::DEFAULT_CLAUDE_MODEL;
        }

        if ($provider === 'claude') {
            return $this->call_claude($api_key, $system, $user, $opts);
        }
        return $this->call_openai($api_key, $system, $user, $opts);
    }

    /**
     * @return string|\WP_Error
     */
    private function call_openai(string $api_key, string $system, string $user, array $opts) {
        $model   = (string) ($opts['openai_model'] ?? C::DEFAULT_OPENAI_MODEL);
        $started = microtime(true);

        Logger::info('client', 'OpenAI Request startet', [
            'model'       => $model,
            'timeout'     => (int) ($opts['timeout'] ?? 60),
            'max_tokens'  => (int) ($opts['max_tokens']  ?? 1500),
            'temperature' => (float) ($opts['temperature'] ?? 0.7),
            'system_len'  => strlen($system),
            'user_len'    => strlen($user),
        ]);

        $payload = [
            'model'       => $model,
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $user],
            ],
            'temperature' => (float) ($opts['temperature'] ?? 0.7),
            'max_tokens'  => (int)   ($opts['max_tokens']  ?? 1500),
        ];
        if (!empty($opts['json_mode'])) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => (int) ($opts['timeout'] ?? 60),
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($payload),
        ]);

        $duration_ms = (int) round((microtime(true) - $started) * 1000);

        if (is_wp_error($response)) {
            Logger::error('client', 'OpenAI HTTP-Fehler', [
                'model'       => $model,
                'duration_ms' => $duration_ms,
                'wp_error'    => $response->get_error_message(),
            ]);
            return $response;
        }

        $status   = (int) wp_remote_retrieve_response_code($response);
        $raw_body = (string) wp_remote_retrieve_body($response);
        $body     = json_decode($raw_body, true);

        if ($status >= 400 || !isset($body['choices'][0]['message']['content'])) {
            Logger::error('client', 'OpenAI API-Fehler', [
                'model'       => $model,
                'status'      => $status,
                'duration_ms' => $duration_ms,
                'body_excerpt' => mb_substr($raw_body, 0, 1000),
            ]);
            return new \WP_Error('api_error', 'OpenAI API-Fehler (HTTP ' . $status . ')');
        }

        $content       = (string) $body['choices'][0]['message']['content'];
        $finish_reason = (string) ($body['choices'][0]['finish_reason'] ?? '');

        if ($finish_reason === 'length') {
            Logger::error('client', 'OpenAI Antwort abgeschnitten (max_tokens)', [
                'model'       => $model,
                'duration_ms' => $duration_ms,
                'content_len' => strlen($content),
                'usage'       => $body['usage'] ?? null,
            ]);
            return new \WP_Error('truncated', 'Antwort wurde abgeschnitten – bitte max_tokens erhöhen');
        }

        Logger::info('client', 'OpenAI Antwort erhalten', [
            'model'         => $model,
            'status'        => $status,
            'duration_ms'   => $duration_ms,
            'content_len'   => strlen($content),
            'usage'         => $body['usage'] ?? null,
        ]);
        return $content;
    }

    /**
     * @return string|\WP_Error
     */
    private function call_claude(string $api_key, string $system, string $user, array $opts) {
        $model   = (string) ($opts['claude_model'] ?? C::DEFAULT_CLAUDE_MODEL);
        $started = microtime(true);

        Logger::info('client', 'Claude Request startet', [
            'model'      => $model,
            'timeout'    => (int) ($opts['timeout'] ?? 60),
            'max_tokens' => (int) ($opts['max_tokens']   ?? 1500),
            'system_len' => strlen($system),
            'user_len'   => strlen($user),
        ]);

        $messages = [
            ['role' => 'user', 'content' => $user],
        ];
        if (!empty($opts['json_mode'])) {
            $messages[] = ['role' => 'assistant', 'content' => '{'];
        }

        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'timeout' => (int) ($opts['timeout'] ?? 60),
            'headers' => [
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'      => $model,
                'max_tokens' => (int)    ($opts['max_tokens']   ?? 1500),
                'system'     => $system,
                'messages'   => $messages,
            ]),
        ]);

        $duration_ms = (int) round((microtime(true) - $started) * 1000);

        if (is_wp_error($response)) {
            Logger::error('client', 'Claude HTTP-Fehler', [
                'model'       => $model,
                'duration_ms' => $duration_ms,
                'wp_error'    => $response->get_error_message(),
            ]);
            return $response;
        }

        $status   = (int) wp_remote_retrieve_response_code($response);
        $raw_body = (string) wp_remote_retrieve_body($response);
        $body     = json_decode($raw_body, true);

        if ($status >= 400 || !isset($body['content'][0]['text'])) {
            Logger::error('client', 'Claude API-Fehler', [
                'model'        => $model,
                'status'       => $status,
                'duration_ms'  => $duration_ms,
                'body_excerpt' => mb_substr($raw_body, 0, 1000),
            ]);
            return new \WP_Error('api_error', 'Claude API-Fehler (HTTP ' . $status . ')');
        }

        $content     = (string) $body['content'][0]['text'];
        $stop_reason = (string) ($body['stop_reason'] ?? '');

        if ($stop_reason === 'max_tokens') {
            Logger::error('client', 'Claude Antwort abgeschnitten (max_tokens)', [
                'model'       => $model,
                'duration_ms' => $duration_ms,
                'content_len' => strlen($content),
                'usage'       => $body['usage'] ?? null,
            ]);
            return new \WP_Error('truncated', 'Antwort wurde abgeschnitten – bitte max_tokens erhöhen');
        }

        if (!empty($opts['json_mode'])) {
            $content = '{' . $content;
        }

        Logger::info('client', 'Claude Antwort erhalten', [
            'model'       => $model,
            'status'      => $status,
            'duration_ms' => $duration_ms,
            'content_len' => strlen($content),
            'usage'       => $body['usage'] ?? null,
        ]);
        return $content;
    }
}
