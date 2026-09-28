<?php
namespace AutoQuill\Rest;

use AutoQuill\AI\Interviewer;
use AutoQuill\AI\Writer;
use AutoQuill\Core\Constants as C;
use AutoQuill\Core\Logger;
use AutoQuill\Database\InterviewsRepository;
use AutoQuill\Database\Schema;

/**
 * REST side of the interview mode. Every write goes to the database first,
 * so a failed AI call never loses what the author typed: the client can ask
 * for the missing question again via POST /interviews/{id}/question.
 */
class InterviewsService {
    const TOPIC_MAX_CHARS = 200;
    const NOTES_MAX_CHARS = 2000;

    public static function list_interviews(\WP_REST_Request $request): \WP_REST_Response {
        Schema::ensure_tables();

        $per_page = max(1, min(100, (int) $request->get_param('per_page')));
        $page     = max(1, (int) $request->get_param('page'));
        $repo     = new InterviewsRepository();
        $total    = $repo->count();

        $items = array_map(
            static fn($row) => self::summary($row),
            $repo->list_recent($per_page, ($page - 1) * $per_page)
        );

        return new \WP_REST_Response([
            'interviews' => $items,
            'total'      => $total,
            'pages'      => (int) max(1, ceil($total / $per_page)),
            'page'       => $page,
        ], 200);
    }

    public static function create_interview(\WP_REST_Request $request): \WP_REST_Response {
        Schema::ensure_tables();
        @set_time_limit(120);

        $params = $request->get_json_params() ?: [];
        $topic  = self::clip(sanitize_text_field((string) ($params['topic'] ?? '')), self::TOPIC_MAX_CHARS);
        $notes  = self::clip(sanitize_textarea_field((string) ($params['notes'] ?? '')), self::NOTES_MAX_CHARS);

        if (trim($topic) === '') {
            return new \WP_REST_Response(['error' => __('Bitte ein Thema angeben.', 'auto-quill')], 400);
        }

        $repo = new InterviewsRepository();
        $id   = $repo->create($topic, $notes, [], get_current_user_id());
        if ($id <= 0) {
            return new \WP_REST_Response(['error' => __('Das Interview konnte nicht gespeichert werden.', 'auto-quill')], 500);
        }

        Logger::info('interview', 'Interview angelegt', ['id' => $id, 'topic' => $topic]);

        // The row exists either way; a failed first question is recoverable.
        return self::ask_and_respond($repo, $id, 201);
    }

    public static function get_interview(\WP_REST_Request $request): \WP_REST_Response {
        Schema::ensure_tables();

        $row = (new InterviewsRepository())->find((int) $request['id']);
        if (!$row) {
            return self::not_found();
        }
        return new \WP_REST_Response(['success' => true, 'interview' => self::payload($row)], 200);
    }

    public static function answer(\WP_REST_Request $request): \WP_REST_Response {
        Schema::ensure_tables();
        @set_time_limit(120);

        $repo = new InterviewsRepository();
        $id   = (int) $request['id'];
        $row  = $repo->find($id);
        if (!$row) {
            return self::not_found();
        }

        $messages = InterviewsRepository::messages($row);
        $last     = end($messages);
        if (!$last || $last['role'] !== 'ai') {
            // Answering twice, or answering before a question exists, would
            // leave the transcript out of step with the questions.
            return new \WP_REST_Response([
                'error'     => __('Es gibt gerade keine offene Frage. Bitte zuerst eine neue Frage anfordern.', 'auto-quill'),
                'interview' => self::payload($row),
            ], 409);
        }

        $params = $request->get_json_params() ?: [];
        $skip   = !empty($params['skip']);
        $answer = trim(sanitize_textarea_field((string) ($params['answer'] ?? '')));

        if (!$skip && $answer === '') {
            return new \WP_REST_Response(['error' => __('Bitte eine Antwort eingeben.', 'auto-quill')], 400);
        }

        $message = [
            'role' => 'user',
            'text' => $skip ? '' : self::clip($answer, C::INTERVIEW_ANSWER_MAX_CHARS),
            'at'   => current_time('mysql'),
        ];
        if ($skip) {
            $message['skipped'] = true;
        }
        $messages[] = $message;

        if (!$repo->save_messages($id, $messages)) {
            return new \WP_REST_Response(['error' => __('Die Antwort konnte nicht gespeichert werden.', 'auto-quill')], 500);
        }

        return self::ask_and_respond($repo, $id, 200);
    }

    /**
     * Asks for the next question when none is open (after a failed call), or
     * with {replace: true} swaps the open question for a different one.
     */
    public static function question(\WP_REST_Request $request): \WP_REST_Response {
        Schema::ensure_tables();
        @set_time_limit(120);

        $repo = new InterviewsRepository();
        $id   = (int) $request['id'];
        $row  = $repo->find($id);
        if (!$row) {
            return self::not_found();
        }

        $params   = $request->get_json_params() ?: [];
        $messages = InterviewsRepository::messages($row);
        $last     = end($messages);

        if ($last && $last['role'] === 'ai') {
            if (empty($params['replace'])) {
                return new \WP_REST_Response(['success' => true, 'interview' => self::payload($row)], 200);
            }
            array_pop($messages);
            $repo->save_messages($id, $messages);
        }

        return self::ask_and_respond($repo, $id, 200);
    }

    public static function write(\WP_REST_Request $request): \WP_REST_Response {
        Schema::ensure_tables();
        // Same budget as Writer::generate_post: one 90s call, maybe one retry.
        @set_time_limit(200);

        $repo = new InterviewsRepository();
        $id   = (int) $request['id'];
        $row  = $repo->find($id);
        if (!$row) {
            return self::not_found();
        }

        $messages = InterviewsRepository::messages($row);
        $answers  = InterviewsRepository::answer_count($messages);
        if ($answers < C::INTERVIEW_MIN_ANSWERS) {
            return new \WP_REST_Response([
                'error' => sprintf(
                    /* translators: %d: minimum number of answers */
                    __('Für einen Beitrag braucht es mindestens %d beantwortete Fragen.', 'auto-quill'),
                    C::INTERVIEW_MIN_ANSWERS
                ),
            ], 400);
        }

        Logger::info('interview', 'Beitrag aus Interview wird geschrieben', ['id' => $id, 'answers' => $answers]);

        $result = Writer::write_from_interview((string) $row->topic, (string) $row->notes, $messages);
        if (is_wp_error($result)) {
            $code = in_array($result->get_error_code(), ['no_api_key', 'not_configured'], true) ? 400 : 502;
            Logger::error('interview', 'Beitrag aus Interview fehlgeschlagen', [
                'id'      => $id,
                'code'    => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ]);
            return new \WP_REST_Response(['error' => $result->get_error_message()], $code);
        }

        $repo->mark_drafted($id);

        Logger::info('interview', 'Beitrag aus Interview generiert', [
            'id'          => $id,
            'title'       => $result['title'],
            'content_len' => strlen($result['content']),
        ]);

        // Same shape as generate-post, so the shared post editor needs no
        // second code path.
        return new \WP_REST_Response([
            'success'              => true,
            'post_title'           => $result['title'],
            'post_content'         => $result['content'],
            'post_excerpt'         => $result['excerpt'],
            'category_ids'         => $result['category_ids'],
            'available_categories' => $result['available_categories'],
            'topic'                => ['title' => (string) $row->topic],
            'interview_id'         => $id,
        ], 200);
    }

    public static function delete_interview(\WP_REST_Request $request): \WP_REST_Response {
        Schema::ensure_tables();

        $repo = new InterviewsRepository();
        $id   = (int) $request['id'];
        if (!$repo->find($id)) {
            return self::not_found();
        }
        if (!$repo->delete($id)) {
            return new \WP_REST_Response(['error' => __('Das Interview konnte nicht gelöscht werden.', 'auto-quill')], 500);
        }

        Logger::info('interview', 'Interview gelöscht', ['id' => $id]);
        return new \WP_REST_Response(['success' => true, 'id' => $id], 200);
    }

    /**
     * Asks the AI for the next question, stores it and answers with the full
     * interview. On an AI failure the stored state is returned alongside the
     * error, so the client can offer a retry without losing anything.
     */
    private static function ask_and_respond(InterviewsRepository $repo, int $id, int $success_status): \WP_REST_Response {
        $row      = $repo->find($id);
        $messages = InterviewsRepository::messages($row);

        $next = Interviewer::next_question((string) $row->topic, (string) $row->notes, $messages);
        if (is_wp_error($next)) {
            Logger::error('interview', 'Nächste Interview-Frage fehlgeschlagen', [
                'id'      => $id,
                'code'    => $next->get_error_code(),
                'message' => $next->get_error_message(),
            ]);
            return new \WP_REST_Response([
                'error'     => $next->get_error_message(),
                'interview' => self::payload($row),
            ], in_array($next->get_error_code(), ['no_api_key', 'not_configured'], true) ? 400 : 502);
        }

        $message = [
            'role' => 'ai',
            'text' => $next['question'],
            'at'   => current_time('mysql'),
        ];
        if ($next['enough']) {
            $message['enough'] = true;
        }
        $messages[] = $message;

        if (!$repo->save_messages($id, $messages)) {
            return new \WP_REST_Response([
                'error'     => __('Die Frage konnte nicht gespeichert werden.', 'auto-quill'),
                'interview' => self::payload($row),
            ], 500);
        }

        return new \WP_REST_Response([
            'success'   => true,
            'interview' => self::payload($repo->find($id)),
        ], $success_status);
    }

    /**
     * Everything the chat screen needs.
     */
    public static function payload($row): array {
        $stored   = get_option(C::OPTION_KEY, []);
        $settings = array_merge(C::defaults(), is_array($stored) ? $stored : []);
        $messages = InterviewsRepository::messages($row);
        $last     = end($messages);

        return array_merge(self::summary($row), [
            'notes'        => (string) $row->notes,
            'messages'     => $messages,
            'target'       => Interviewer::target_questions($settings),
            'min_answers'  => C::INTERVIEW_MIN_ANSWERS,
            'max_chars'    => C::INTERVIEW_ANSWER_MAX_CHARS,
            'awaiting'     => $last && $last['role'] === 'ai' ? 'answer' : 'question',
            'enough'       => $last && !empty($last['enough']),
        ]);
    }

    private static function summary($row): array {
        $messages = InterviewsRepository::messages($row);
        $post_id  = (int) ($row->post_id ?? 0);
        $edit_url = '';
        if ($post_id > 0 && get_post($post_id)) {
            $edit_url = (string) get_edit_post_link($post_id, 'raw');
        }

        return [
            'id'         => (int) $row->id,
            'topic'      => (string) $row->topic,
            'status'     => (string) $row->status,
            'answers'    => InterviewsRepository::answer_count($messages),
            'post_id'    => $post_id,
            'edit_url'   => $edit_url,
            'created_at' => (string) $row->created_at,
            'updated_at' => (string) $row->updated_at,
        ];
    }

    private static function not_found(): \WP_REST_Response {
        return new \WP_REST_Response(['error' => __('Interview nicht gefunden', 'auto-quill')], 404);
    }

    private static function clip(string $text, int $max): string {
        return function_exists('mb_substr') ? mb_substr($text, 0, $max) : substr($text, 0, $max);
    }
}
