# Hooks und Filter

Eigener Code gehört in ein kleines eigenes Plugin oder die `functions.php` des (Child-)Themes –
nicht in die AutoQuill-Dateien, sonst ist er beim nächsten Update weg.

## Actions

| Hook | Parameter | Wann |
|---|---|---|
| `auto_quill_daily_fetch` | – | Geplanter RSS-Abruf (Cron) |
| `auto_quill_daily_select` | – | Geplante Themenauswahl (Cron) |
| `auto_quill_daily_backup` | – | Geplante Sicherung (Cron, nur wenn eingeschaltet) |
| `auto_quill_daily_digest` | – | Geplanter Tagesbericht (Cron, nur wenn eingeschaltet) |
| `auto_quill_topics_selected` | `array $topics` | Nach jeder erfolgreichen Themenauswahl, auch manuell ausgelöst |

### Beispiel: Slack-Nachricht bei neuen Themen

```php
add_action('auto_quill_topics_selected', function (array $topics) {
    $lines = array_map(
        fn($t) => sprintf('• [%s] %s', $t['rating'] ?? '–', $t['title']),
        $topics
    );
    wp_remote_post('https://hooks.slack.com/services/XXX/YYY/ZZZ', [
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => wp_json_encode(['text' => "Neue Top-Themen:\n" . implode("\n", $lines)]),
    ]);
});
```

### Beispiel: Themenauswahl per WP-CLI auslösen

```bash
wp cron event run auto_quill_daily_select
```

## Filter

| Filter | Parameter | Rückgabe | Zweck |
|---|---|---|---|
| `auto_quill_max_tokens` | `int $tokens`, `int $words`, `string $body_prompt` | `int` | Antwort-Budget der Beitragsgenerierung |

`$tokens` ist der berechnete Wert (`Wörter × 4 + 1000`, begrenzt auf 6 500–16 000), `$words` die aus
dem Prompt gelesene Wortzahl, `$body_prompt` der Prompt *Beitragstext*.

### Beispiel: Modell mit kleinem Ausgabelimit

```php
// z. B. ein Modell mit max. 8 192 Ausgabe-Tokens
add_filter('auto_quill_max_tokens', fn($tokens) => min($tokens, 8000));
```

### Beispiel: Mehr Luft für Reasoning-Modelle

```php
add_filter('auto_quill_max_tokens', fn($tokens) => max($tokens, 32000));
```

## Konstanten (`wp-config.php`)

| Konstante | Wirkung |
|---|---|
| `AUTO_QUILL_AI_KEY` | KI-API-Schlüssel, hat Vorrang vor der Einstellung |
| `AUTO_QUILL_PIXABAY_KEY` | Pixabay-Schlüssel, hat Vorrang vor der Einstellung |
