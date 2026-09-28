<?php
namespace AutoQuill\AI;

use AutoQuill\Core\Constants as C;
use AutoQuill\Core\Logger;
use AutoQuill\Database\InterviewsRepository;

/**
 * The AI side of an interview: asks one question per round, the way an
 * editor would, and says when there is enough material for a post.
 *
 * The provider API is called single-turn on purpose: the conversation so far
 * travels inside the prompt as a transcript, so Client stays unchanged and
 * both providers behave the same.
 */
class Interviewer {
    /** Transcript budget for asking the next question. */
    const QUESTION_TRANSCRIPT_LIMIT = 20000;

    /** Transcript budget for writing the post - the answers are the material. */
    const WRITE_TRANSCRIPT_LIMIT = 40000;

    const QUESTION_MAX_CHARS = 1000;

    /**
     * @param array<int, array{role:string, text:string, skipped?:bool}> $messages
     * @return array{question:string, enough:bool}|\WP_Error
     */
    public static function next_question(string $topic, string $notes, array $messages) {
        $settings = get_option(C::OPTION_KEY, C::defaults());
        if (!is_array($settings)) {
            $settings = C::defaults();
        }
        $settings = array_merge(C::defaults(), $settings);

        $persona = trim((string) $settings['prompt_interview']);
        if ($persona === '') {
            $persona = C::default_prompt_interview();
        }
        $target  = self::target_questions($settings);
        $answers = InterviewsRepository::answer_count($messages);

        $system = $persona . "\n\nAntworte immer im geforderten JSON-Format.";
        $prompt = self::build_prompt($topic, $notes, $messages, $target, $answers);

        $client = new Client();
        $opts   = [
            'max_tokens'  => 800,
            'temperature' => 0.8,
            'timeout'     => 60,
            'json_shape'  => 'object',
        ];

        $raw = $client->chat($system, $prompt, $opts);
        if (is_wp_error($raw)) {
            return $raw;
        }

        $parsed = self::parse($raw);
        if ($parsed === null) {
            Logger::warning('interview', 'Interview-Frage nicht parsebar – starte Retry', [
                'json_error'  => JsonExtractor::last_error(),
                'raw_excerpt' => mb_substr($raw, 0, 500),
            ]);

            $opts['temperature'] = 0.3;
            $raw = $client->chat(
                $system,
                "Deine vorherige Antwort war kein gültiges JSON. Antworte JETZT ausschließlich mit dem JSON-Objekt.\n\n" . $prompt,
                $opts
            );
            if (is_wp_error($raw)) {
                return $raw;
            }
            $parsed = self::parse($raw);
        }

        if ($parsed === null) {
            Logger::error('interview', 'Interview-Frage konnte auch im Retry nicht geparst werden', [
                'json_error'  => JsonExtractor::last_error(),
                'raw_excerpt' => mb_substr($raw, 0, 500),
            ]);
            return new \WP_Error('ai_parse_failed', __('Die KI-Antwort für die nächste Frage konnte nicht verarbeitet werden.', 'auto-quill'));
        }

        // The model's judgement is a hint; the configured target is a promise.
        if ($answers >= $target) {
            $parsed['enough'] = true;
        }
        if ($answers < C::INTERVIEW_MIN_ANSWERS) {
            $parsed['enough'] = false;
        }

        return $parsed;
    }

    /**
     * The conversation as plain text, oldest first. When it exceeds $limit the
     * middle goes: the opening question sets the frame and the latest rounds
     * carry the thread, so both are kept.
     *
     * @param array<int, array{role:string, text:string, skipped?:bool}> $messages
     */
    public static function transcript(array $messages, int $limit): string {
        $lines = [];
        foreach ($messages as $message) {
            if (($message['role'] ?? '') === 'user') {
                $lines[] = !empty($message['skipped'])
                    ? 'Autor: (Frage übersprungen)'
                    : 'Autor: ' . trim((string) $message['text']);
            } else {
                $lines[] = 'Redakteur: ' . trim((string) $message['text']);
            }
        }

        $length = static fn(array $l): int => (int) array_sum(array_map(
            static fn($line) => function_exists('mb_strlen') ? mb_strlen($line) + 2 : strlen($line) + 2,
            $l
        ));

        $cut = false;
        while (count($lines) > 3 && $length($lines) > $limit) {
            array_splice($lines, 1, 1);
            $cut = true;
        }
        if ($cut) {
            array_splice($lines, 1, 0, ['[… frühere Teile des Gesprächs gekürzt …]']);
        }

        return implode("\n\n", $lines);
    }

    public static function target_questions(array $settings): int {
        $target = (int) ($settings['interview_questions'] ?? C::defaults()['interview_questions']);
        return max(C::INTERVIEW_QUESTIONS_MIN, min(C::INTERVIEW_QUESTIONS_MAX, $target));
    }

    /**
     * @param array<int, array{role:string, text:string, skipped?:bool}> $messages
     */
    private static function build_prompt(string $topic, string $notes, array $messages, int $target, int $answers): string {
        $prompt  = "Thema des Interviews: {$topic}\n";
        if (trim($notes) !== '') {
            $prompt .= "Hinweise des Autors (Zielgruppe, Stichpunkte, Schwerpunkte):\n" . trim($notes) . "\n";
        }
        $prompt .= "\nGeplanter Umfang: etwa {$target} Fragen. Bisher beantwortet: {$answers}.\n\n";

        if (empty($messages)) {
            $prompt .= "Das Gespräch hat noch nicht begonnen. Stelle die erste Frage.\n\n";
        } else {
            $prompt .= "Bisheriges Gespräch:\n\n" . self::transcript($messages, self::QUESTION_TRANSCRIPT_LIMIT) . "\n\n";
            $prompt .= "Stelle jetzt die nächste Frage. Knüpfe, wo sinnvoll, an die letzte Antwort an.\n\n";
        }

        $prompt .= "Setze \"enough\" auf true, sobald genug Material für einen fundierten, konkreten Blog-Beitrag vorhanden ist "
            . "(frühestens nach " . C::INTERVIEW_MIN_ANSWERS . " Antworten). Stelle auch dann eine weitere, vertiefende Frage – "
            . "der Autor entscheidet, ob er sie noch beantwortet.\n\n";
        $prompt .= "--- Antwortformat ---\n";
        $prompt .= "Antworte AUSSCHLIESSLICH mit einem einzigen gültigen JSON-Objekt (kein Markdown, kein Text davor oder danach):\n";
        $prompt .= "{\"question\": \"<die nächste Frage>\", \"enough\": <true|false>}";

        return $prompt;
    }

    /**
     * @return array{question:string, enough:bool}|null
     */
    private static function parse(string $raw): ?array {
        $decoded = JsonExtractor::extract_object($raw);
        if (!is_array($decoded) || !isset($decoded['question']) || !is_string($decoded['question'])) {
            return null;
        }

        $question = trim(sanitize_textarea_field($decoded['question']));
        if ($question === '') {
            return null;
        }
        if (function_exists('mb_substr')) {
            $question = mb_substr($question, 0, self::QUESTION_MAX_CHARS);
        }

        $enough = $decoded['enough'] ?? false;
        if (is_string($enough)) {
            $enough = in_array(strtolower($enough), ['true', '1', 'yes', 'ja'], true);
        }

        return [
            'question' => $question,
            'enough'   => (bool) $enough,
        ];
    }
}
