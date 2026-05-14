<?php
namespace AutoQuill\AI;

class JsonExtractor {
    private static string $last_error = '';

    public static function last_error(): string {
        return self::$last_error;
    }

    public static function extract_object(string $raw): ?array {
        return self::extract($raw, '{', '}');
    }

    public static function extract_array(string $raw): ?array {
        return self::extract($raw, '[', ']');
    }

    private static function extract(string $raw, string $open, string $close): ?array {
        self::$last_error = '';

        $s = self::preclean($raw);
        $candidate = self::find_balanced($s, $open, $close);

        if ($candidate === null) {
            self::$last_error = 'no_balanced_' . ($open === '{' ? 'object' : 'array') . '_found';
            return null;
        }

        $decoded = json_decode($candidate, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $repaired = self::repair($candidate);
        if ($repaired !== $candidate) {
            $decoded = json_decode($repaired, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        self::$last_error = json_last_error_msg();
        return null;
    }

    private static function preclean(string $raw): string {
        $s = trim($raw);

        // Strip UTF-8 BOM
        if (strncmp($s, "\xEF\xBB\xBF", 3) === 0) {
            $s = substr($s, 3);
        }

        // Remove markdown code fences anywhere (```json, ```JSON, ```)
        $s = preg_replace('/```[a-zA-Z]*\s*/', '', $s);
        $s = (string) preg_replace('/```/', '', (string) $s);

        return trim((string) $s);
    }

    /**
     * Scans for the first balanced JSON object/array, skipping bracket-like
     * characters that appear inside string literals.
     */
    private static function find_balanced(string $s, string $open, string $close): ?string {
        $len = strlen($s);
        $start = strpos($s, $open);
        if ($start === false) {
            return null;
        }

        $depth = 0;
        $in_string = false;
        $escaped = false;

        for ($i = $start; $i < $len; $i++) {
            $c = $s[$i];

            if ($in_string) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($c === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($c === '"') {
                    $in_string = false;
                }
                continue;
            }

            if ($c === '"') {
                $in_string = true;
                continue;
            }

            if ($c === $open) {
                $depth++;
            } elseif ($c === $close) {
                $depth--;
                if ($depth === 0) {
                    return substr($s, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }

    /**
     * Attempts light repairs for common LLM JSON mistakes:
     *  - smart quotes -> straight quotes
     *  - trailing commas before } or ]
     *  - raw newlines / tabs inside string literals
     */
    private static function repair(string $s): string {
        $s = strtr($s, [
            "\xE2\x80\x9C" => '"', // “
            "\xE2\x80\x9D" => '"', // ”
            "\xE2\x80\x98" => "'", // ‘
            "\xE2\x80\x99" => "'", // ’
        ]);

        $s = (string) preg_replace('/,(\s*[}\]])/', '$1', $s);

        $s = self::escape_raw_controls_in_strings($s);

        return $s;
    }

    /**
     * Walk the string and escape raw \n, \r, \t that appear inside JSON string
     * literals. Models often include real newlines in long HTML content fields.
     */
    private static function escape_raw_controls_in_strings(string $s): string {
        $out = '';
        $len = strlen($s);
        $in_string = false;
        $escaped = false;

        for ($i = 0; $i < $len; $i++) {
            $c = $s[$i];

            if ($in_string) {
                if ($escaped) {
                    $out .= $c;
                    $escaped = false;
                    continue;
                }
                if ($c === '\\') {
                    $out .= $c;
                    $escaped = true;
                    continue;
                }
                if ($c === '"') {
                    $out .= $c;
                    $in_string = false;
                    continue;
                }
                if ($c === "\n") {
                    $out .= '\\n';
                    continue;
                }
                if ($c === "\r") {
                    $out .= '\\r';
                    continue;
                }
                if ($c === "\t") {
                    $out .= '\\t';
                    continue;
                }
                $out .= $c;
                continue;
            }

            if ($c === '"') {
                $in_string = true;
            }
            $out .= $c;
        }

        return $out;
    }
}
