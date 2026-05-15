<?php
namespace AutoQuill\AI;

use AutoQuill\Core\Logger;

class KeywordSuggester {
    /**
     * Bittet die KI um 2–3 prägnante Suchbegriffe, die den Post visuell
     * repräsentieren (z. B. für eine Stockfoto-Suche).
     *
     * @return string[] Liste von Keywords. Leeres Array bei Fehlern – ein
     *                  fehlender Vorschlag soll den Bild-Picker nicht blockieren.
     */
    public function suggest(string $title, string $excerpt): array {
        $title   = trim($title);
        $excerpt = trim($excerpt);

        if ($title === '' && $excerpt === '') {
            return [];
        }

        $system = "Du erzeugst Suchbegriffe für eine Stockfoto-Datenbank (Pixabay).\n"
            . "Antworte ausschließlich mit JSON in der Form:\n"
            . '{"keywords": ["...","..."]}' . "\n"
            . "Regeln:\n"
            . "- genau 2 bis 3 Begriffe\n"
            . "- deutsche Stichwörter, jeweils 1–2 Wörter\n"
            . "- visuell konkret (Objekte, Szenen, Stimmungen) – keine abstrakten Begriffe\n"
            . "- keine Eigennamen, Marken, Personen- oder Ortsnamen\n"
            . "- keine ganzen Sätze, keine Erklärungen außerhalb des JSON";

        $user = "Titel: " . $title . "\n"
            . "Auszug: " . $excerpt . "\n\n"
            . "Liefere passende Suchbegriffe als JSON.";

        $client   = new Client();
        $response = $client->chat($system, $user, [
            'max_tokens'  => 150,
            'temperature' => 0.4,
            'json_mode'   => true,
        ]);

        if (is_wp_error($response)) {
            Logger::warning('pixabay', 'Keyword-Vorschlag fehlgeschlagen', [
                'wp_error' => $response->get_error_message(),
            ]);
            return [];
        }

        $decoded = JsonExtractor::extract_object((string) $response);
        if (!is_array($decoded) || !isset($decoded['keywords']) || !is_array($decoded['keywords'])) {
            Logger::warning('pixabay', 'Keyword-Antwort nicht parsbar', [
                'json_error'    => JsonExtractor::last_error(),
                'raw_excerpt'   => mb_substr((string) $response, 0, 300),
            ]);
            return [];
        }

        $keywords = [];
        foreach ($decoded['keywords'] as $kw) {
            $kw = trim((string) $kw);
            if ($kw === '') {
                continue;
            }
            $keywords[] = $kw;
            if (count($keywords) >= 3) {
                break;
            }
        }

        return $keywords;
    }
}
