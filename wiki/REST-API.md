# REST-API

Namespace: **`/wp-json/auto-quill/v1/`**

Alle Endpunkte erfordern einen angemeldeten Benutzer mit `manage_options`. Aus dem Browser heraus
funktioniert das über den Cookie plus Header `X-WP-Nonce` (`wp_create_nonce('wp_rest')`), von
außen z. B. über [Anwendungspasswörter](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/)
(Benutzer → Profil → Anwendungspasswörter).

| Methode | Route | Zweck |
|---|---|---|
| GET | [`/topics`](#get-topics) | Heutige Top-Themen |
| GET | [`/articles`](#get-articles) | Feed-Einträge, paginiert |
| POST | [`/generate-post`](#post-generate-post) | Beitrag von der KI schreiben lassen (speichert noch nichts) |
| POST | [`/publish-post`](#post-publish-post) | Beitrag in WordPress anlegen |
| POST | [`/suggest-image-keywords`](#post-suggest-image-keywords) | Bild-Suchbegriffe von der KI |
| GET | [`/search-images`](#get-search-images) | Pixabay-Suche |
| POST | [`/models`](#post-models) | Verfügbare Modelle des Anbieters |
| GET / DELETE | [`/logs`](#get--delete-logs) | Log lesen / leeren |

Fehler kommen als JSON mit Feld `error` und passendem HTTP-Status.

---

## GET /topics

Antwort:

```json
{
  "topics": [
    { "article_id": 42, "title": "…", "summary": "…", "rating": 87, "rating_reason": "…" }
  ],
  "status": "…"
}
```

`rating` ist 0–100 oder `null` (unbewertet). Die Reihenfolge ist die Anzeige-Reihenfolge. Gibt es
heute noch keine Auswahl, ist `topics` leer. Für `/generate-post` nimmst du am einfachsten die
`article_id` eines Themas.

## GET /articles

| Parameter | Typ | Standard | Beschreibung |
|---|---|---|---|
| `page` | int | 1 | Seite |
| `per_page` | int | 20 | 1–100 |
| `source_id` | int | 0 | Nur diese RSS-Quelle (0 = alle) |
| `search` | string | – | Suche in Titel und Beschreibung |
| `linked` | string | `all` | `all`, `linked` (mit Beitrag), `unlinked` (ohne Beitrag) |

Antwort: `total`, `page`, `pages`, `per_page` und `html` – die fertig gerenderten, escapten
Tabellenzeilen für das Dashboard. (Bewusst HTML statt Rohdaten: Feed-Inhalte sind fremder Inhalt und
werden serverseitig escaped.)

## POST /generate-post

Genau **eine** der beiden Varianten – ein Feed-Eintrag oder ein Thema aus der Themenliste
(`topic_id` = Zeile der Tagesauswahl, `topic_index` = Position im Array):

```json
{ "article_id": 42 }
```
```json
{ "topic_id": 12, "topic_index": 0 }
```

Antwort (gekürzt):

```json
{
  "success": true,
  "post_title": "…",
  "post_content": "<p>…</p><h2>…</h2>…<p>Quelle: <a …>…</a></p>",
  "post_excerpt": "…",
  "category_ids": [3, 7],
  "available_categories": [{ "id": 3, "name": "Technik" }],
  "article_id": 42,
  "topic_id": 12,
  "topic_index": 0
}
```

Dauert je nach Modell 20–90 Sekunden (serverseitiges Timeout 90 s pro KI-Anfrage). Es wird **nichts**
gespeichert.

## POST /publish-post

```json
{
  "post_title": "…",
  "post_content": "…",
  "post_excerpt": "…",
  "category_ids": [3, 7],
  "article_id": 42,
  "topic_id": 12,
  "image_url": "https://pixabay.com/get/…",
  "image_alt": "…"
}
```

Legt den Beitrag mit dem eingestellten Status an, lädt optional das Bild in die Mediathek und setzt
es als Beitragsbild, verknüpft Beitrag und Feed-Artikel. Antwort: `success`, `post_id`,
`post_status`, `article_id`, `edit_url`, `message`.

## POST /suggest-image-keywords

```json
{ "title": "…", "excerpt": "…" }
```

Antwort: `{ "keywords": ["…", "…", "…"] }` – bis zu drei von der KI vorgeschlagene Suchbegriffe.

## GET /search-images

| Parameter | Beschreibung |
|---|---|
| `query` | Suchbegriff (Pflicht) |
| `page` | Seite, ab 1 |
| `per_page` | 3–50, Standard 20 |

Sucht Fotos auf Pixabay (SafeSearch an, Sprache Deutsch). Ergebnisse werden eine Stunde
zwischengespeichert. Erfordert einen Pixabay-Schlüssel.

## POST /models

| Parameter | Beschreibung |
|---|---|
| `provider` | `openai` oder `claude` (Pflicht) |
| `api_key` | Optional; ohne Angabe wird der gespeicherte Schlüssel verwendet. Ist `AUTO_QUILL_AI_KEY` gesetzt, gewinnt immer die Konstante. |
| `refresh` | `true` umgeht den 12-Stunden-Cache |

```json
{ "models": [ { "id": "claude-sonnet-4-6", "label": "Claude Sonnet 4.6 (claude-sonnet-4-6)" } ] }
```

POST statt GET, damit ein mitgeschickter Schlüssel nicht in URLs oder Server-Logs landet.

## GET / DELETE /logs

GET-Parameter: `level`, `source`, `since_id`, `since`, `limit` (Standard 100). Antwort: `logs`,
`server_time`, `debug_enabled`. DELETE leert das Log.

---

## Beispiel mit Anwendungspasswort

```bash
# Heutige Themen
curl -u "admin:xxxx xxxx xxxx xxxx xxxx xxxx" \
  https://example.com/wp-json/auto-quill/v1/topics

# Beitrag zum besten Thema generieren …
ARTICLE_ID=$(curl -s -u "admin:xxxx xxxx xxxx xxxx xxxx xxxx" \
  https://example.com/wp-json/auto-quill/v1/topics | jq '.topics[0].article_id')

curl -u "admin:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d "{\"article_id\": $ARTICLE_ID}" \
  https://example.com/wp-json/auto-quill/v1/generate-post > entwurf.json

# … und als Entwurf anlegen
jq '{post_title, post_content, post_excerpt, category_ids, article_id, topic_id}' entwurf.json |
curl -u "admin:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" -d @- \
  https://example.com/wp-json/auto-quill/v1/publish-post
```
