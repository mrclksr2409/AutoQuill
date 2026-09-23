# Datenbank

AutoQuill legt vier eigene Tabellen an (Präfix je nach Installation, meist `wp_`) und nutzt einige
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
| `status`, `post_id`, `selected_topic_*` | Status der Tagesauswahl |
| `created_at`, `updated_at` | Zeitstempel |

```json
[{ "article_id": 42, "title": "…", "summary": "…", "rating": 87, "rating_reason": "…" }]
```

### `wp_auto_quill_logs` – Diagnose-Log

`created_at`, `level` (`error`/`warning`/`info`/`debug`), `source`, `message`, `context` (JSON).
Maximal 7 Tage bzw. 500 Einträge.

## Optionen (`wp_options`)

| Option | Inhalt |
|---|---|
| `auto_quill_settings` | Alle Einstellungen als Array |
| `auto_quill_db_version` | Schema-Version (aktuell `1.4`) |
| `auto_quill_backups` | Sicherungen, nicht automatisch geladen |
| `auto_quill_last_digest` | Zeitpunkt des letzten Tagesberichts (UTC) |
| `_transient_auto_quill_models_*` | Zwischengespeicherte Modelllisten (12 h) |

## Post-Meta der erzeugten Beiträge

| Meta-Key | Inhalt |
|---|---|
| `_auto_quill_source_url` | URL des Originalartikels |
| `_auto_quill_article_id` | ID der Artikel-Zeile (darf nach dem Aufräumen ins Leere zeigen) |
| `_auto_quill_article_title` | Titel des Originalartikels |
| `_auto_quill_feed_name` | Name der RSS-Quelle |
| `_auto_quill_generated_at` | Zeitpunkt der Erstellung (UTC) – bei **jedem** AutoQuill-Beitrag gesetzt |

Die Felder sind geschützt (Unterstrich) und in der WordPress-REST-API nicht sichtbar. Im Editor
zeigt die Box **„AutoQuill-Quelle“** sie an.

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
