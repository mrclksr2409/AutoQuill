# Datenbank

AutoQuill legt fünf eigene Tabellen an (Präfix je nach Installation, meist `wp_`) und nutzt einige
Optionen und Post-Meta-Felder.

## Tabellen

### `wp_auto_quill_sources` – RSS-Quellen

| Spalte | Inhalt |
|---|---|
| `id` | Primärschlüssel |
| `title` | Anzeigename |
| `feed_url` | Feed-URL |
| `is_active` | 1 = wird abgerufen |
| `created_at`, `updated_at` | Zeitstempel |

### `wp_auto_quill_articles` – Gecrawlte Artikel

| Spalte | Inhalt |
|---|---|
| `id` | Primärschlüssel |
| `source_id` | Verweis auf die Quelle |
| `title`, `description` | Aus dem Feed (Beschreibung ohne HTML, max. 1 000 Zeichen) |
| `content` | Roh-HTML der Artikelseite (max. 50 000 Zeichen) |
| `author`, `published_date` | Aus dem Feed |
| `article_url` | Link zum Original |
| `article_hash` | `md5(feed_url . link)` – eindeutig, erkennt Duplikate |
| `post_id` | Verknüpfter WordPress-Beitrag (oder leer) |
| `fetched_at` | Zeitpunkt des Abrufs |

Einträge älter als der RSS-Rückblick werden bei jedem Abruf gelöscht – **außer** sie sind mit einem
Beitrag verknüpft. Ohne diese Ausnahme würde der Artikel beim nächsten Abruf erneut als „neu“
erscheinen und könnte ein zweites Mal verarbeitet werden.

### `wp_auto_quill_topics` – Tägliche Themenauswahl

| Spalte | Inhalt |
|---|---|
| `topic_date` | Datum, **eindeutig** – eine Zeile pro Tag |
| `topics` | JSON-Array der Themen (siehe unten) |
| `status` | `pending` (ausgewählt), `generated` (zu einem Thema wurde ein Beitrag generiert), `published` (gespeichert) |
| `post_id`, `selected_topic_id`, `selected_topic_title` | Zuletzt gespeicherter Beitrag bzw. zuletzt generiertes Thema des Tages |
| `created_at`, `updated_at` | Zeitstempel |

```json
[{ "article_id": 42, "title": "…", "summary": "…", "rating": 87, "rating_reason": "…" }]
```

### `wp_auto_quill_logs` – Diagnose-Log

`created_at`, `level` (`error`/`warning`/`info`/`debug`), `source`, `message`, `context` (JSON).
Maximal 7 Tage bzw. 500 Einträge.

### `wp_auto_quill_interviews` – Interviews (seit DB-Version 1.5)

| Spalte | Inhalt |
|---|---|
| `topic` | Thema |
| `notes` | Hinweise für den Redakteur (optional) |
| `messages` | Gesprächsverlauf als JSON, siehe unten |
| `status` | `open` (läuft), `drafted` (Beitrag entworfen), `published` (als Beitrag gespeichert) |
| `post_id` | Der gespeicherte Beitrag (zuletzt gespeicherter, falls mehrfach) |
| `user_id` | Wer das Interview angelegt hat |
| `created_at`, `updated_at` | Ortszeit der Seite |

```json
[{ "role": "ai", "text": "Wie bist du zum Thema gekommen?", "at": "2026-09-28 10:00:00" },
 { "role": "user", "text": "…", "at": "…" },
 { "role": "user", "text": "", "skipped": true, "at": "…" },
 { "role": "ai", "text": "…", "enough": true, "at": "…" }]
```

Interviews werden nicht automatisch aufgeräumt und sind nicht Teil der Sicherung.

## Optionen (`wp_options`)

| Option | Inhalt |
|---|---|
| `auto_quill_settings` | Alle Einstellungen als Array |
| `auto_quill_db_version` | Schema-Version (aktuell `1.5`) |
| `auto_quill_backups` | Sicherungen, nicht automatisch geladen |
| `auto_quill_last_digest` | Zeitpunkt des letzten Tagesberichts (UTC) |
| `_transient_auto_quill_models_*` | Zwischengespeicherte Modelllisten (12 h), benannt nach einem Hash des Schlüssels |
| `_transient_auto_quill_pixabay_*` | Zwischengespeicherte Pixabay-Suchergebnisse (1 h) |
| `_transient_auto_quill_notice_*` | Kurzlebige Admin-Hinweise je Benutzer (30 s) |

### Schlüssel in `auto_quill_settings`

| Schlüssel | Einstellung | Standard |
|---|---|---|
| `ai_provider` | KI-Provider: `openai`, `claude`, `ionos`, `custom` | `openai` |
| `openai_api_key`, `claude_api_key`, `ionos_api_key`, `custom_api_key` | API-Schlüssel je Provider | leer |
| `openai_model`, `claude_model`, `ionos_model`, `custom_model` | Modell je Provider | `gpt-4o-mini`, `claude-sonnet-4-6`, `meta-llama/Llama-3.3-70B-Instruct`, leer |
| `custom_base_url` | Basis-URL des eigenen Endpunkts | leer |
| `pixabay_api_key` | Pixabay-Schlüssel | leer |
| `post_status` | `draft`, `publish` oder `pending` | `draft` |
| `auto_publish` | Automatisch veröffentlichen | `false` |
| `source_link_enabled`, `source_link_template` | Quellenhinweis | `true`, `Quelle: {source_link}` |
| `rss_lookback_days` | RSS-Rückblick in Tagen (0–365) | `7` |
| `fetch_time`, `select_time` | Uhrzeiten Abruf und Themenauswahl (`HH:MM`, Ortszeit) | `00:00`, `01:00` |
| `writing_style` | Schreibstil | leer |
| `prompt_title`, `prompt_body`, `prompt_excerpt`, `prompt_category` | Die vier Prompts | siehe [Prompts](Prompts#standard-prompts) |
| `interview_style` | `first_person`, `editorial` oder `qa` | `first_person` |
| `interview_questions` | Richtwert Fragen (3–15) | `6` |
| `prompt_interview` | Prompt: Redakteur | siehe Tab *Interview* |
| `notify_enabled`, `notify_time` | Tagesbericht an/aus, Uhrzeit | `false`, `08:00` |
| `notify_users` | Benutzer-IDs der Empfänger | leer |
| `notify_emails` | Weitere Adressen | leer |
| `notify_events` | Inhalte: `topics`, `errors`, `posts` | alle drei |
| `backup_enabled`, `backup_time`, `backup_keep` | Automatische Sicherung | `true`, `03:00`, `7` |
| `beta_mode` | Beta-Modus | `false` |
| `debug_logging` | Debug-Logging | `false` |
| `posts_per_day` | Altlast ohne Funktion (wird gespeichert, aber nirgends ausgewertet) | `1` |

Bestehende Installationen bekommen neue Schlüssel nicht automatisch in die Option geschrieben;
AutoQuill liest deshalb immer mit Rückfall auf diese Standardwerte. Ein gemeinsamer Schlüssel
`ai_api_key` aus Versionen vor 1.7.0 wird beim ersten Laden automatisch dem damals gewählten
Provider zugeordnet und entfernt.

## Post-Meta der erzeugten Beiträge

| Meta-Key | Inhalt |
|---|---|
| `_auto_quill_source_url` | URL des Originalartikels |
| `_auto_quill_article_id` | ID der Artikel-Zeile (darf nach dem Aufräumen ins Leere zeigen) |
| `_auto_quill_article_title` | Titel des Originalartikels |
| `_auto_quill_feed_name` | Name der RSS-Quelle |
| `_auto_quill_generated_at` | Zeitpunkt der Erstellung (UTC) – bei **jedem** AutoQuill-Beitrag gesetzt |
| `_auto_quill_interview_id` | ID des Interviews, aus dem der Beitrag entstand |

Die Felder sind geschützt (Unterstrich) und in der WordPress-REST-API nicht sichtbar. Im Editor
zeigt die Seitenbox **„AutoQuill-Quelle“** Feed-Name und Originalartikel an – nur bei Beiträgen
mit `_auto_quill_source_url`, also nicht bei Interview-Beiträgen.

### Alle AutoQuill-Beiträge finden

```php
$posts = get_posts([
    'post_type'   => 'post',
    'post_status' => 'any',
    'numberposts' => -1,
    'meta_key'    => '_auto_quill_generated_at',
]);
```

```bash
wp post list --meta_key=_auto_quill_generated_at --post_status=any
```

## Migrationen

Das Schema wird beim Aufruf einer Admin-Seite automatisch aktualisiert, wenn sich die DB-Version
geändert hat. AutoQuill prüft danach, ob alle erwarteten Spalten existieren, und schreibt die neue
Version erst dann. Schlägt eine Migration fehl (z. B. fehlende Rechte), zeigt das Status-Panel die
DB-Version rot an und der Versuch wird beim nächsten Aufruf wiederholt.
