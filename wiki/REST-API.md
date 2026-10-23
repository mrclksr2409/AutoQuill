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
| POST | [`/posts/{id}/featured-image`](#post-postsidfeatured-image) | Beitragsbild nachträglich setzen |
| POST | [`/suggest-image-keywords`](#post-suggest-image-keywords) | Bild-Suchbegriffe von der KI |
| GET | [`/search-images`](#get-search-images) | Pixabay-Suche |
| POST | [`/models`](#post-models) | Verfügbare Modelle des Anbieters |
| GET / DELETE | [`/logs`](#get--delete-logs) | Log lesen / leeren |
| GET / POST | [`/interviews`](#interviews) | Interviews auflisten / neues Interview starten |
| GET / DELETE | [`/interviews/{id}`](#interviews) | Interview lesen / löschen |
| POST | [`/interviews/{id}/answer`](#interviews) | Antwort senden (oder überspringen), nächste Frage erhalten |
| POST | [`/interviews/{id}/question`](#interviews) | Fehlende Frage erneut anfordern oder offene Frage ersetzen |
| POST | [`/interviews/{id}/write`](#interviews) | Beitrag aus dem Interview schreiben (speichert noch nichts) |

Fehler kommen als JSON mit Feld `error` und passendem HTTP-Status. Fehlt die Berechtigung oder ist
der Nonce abgelaufen, antwortet WordPress selbst (z. B. `rest_cookie_invalid_nonce`, HTTP 403).

---

## GET /topics

Antwort:

```json
{
  "topics": [
    { "article_id": 42, "title": "…", "summary": "…", "rating": 87, "rating_reason": "…" }
  ],
  "status": "pending"
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
  "topic": { "article_id": 42, "title": "…", "summary": "…", "rating": 87, "rating_reason": "…" },
  "article_id": 42,
  "topic_id": 12,
  "topic_index": 0
}
```

Bei `article_id` sind `topic_id` = 0 und `topic_index` = -1. Dauert je nach Modell 20–90 Sekunden
(Timeout 90 s pro KI-Anfrage, bei abgeschnittener Antwort eine Wiederholung). Es wird **kein
Beitrag** gespeichert; bei einem Thema wird lediglich in der Tagesauswahl vermerkt, welches Thema
generiert wurde.

| Status | Bedeutung |
|---|---|
| 400 | Weder `article_id` noch `topic_id`, kein API-Schlüssel oder eigener Endpunkt unvollständig |
| 404 | Feed-Eintrag, Tagesauswahl oder Thema nicht gefunden |
| 502 | Fehler beim KI-Provider oder Antwort nicht verwertbar |

## POST /publish-post

```json
{
  "post_title": "…",
  "post_content": "…",
  "post_excerpt": "…",
  "category_ids": [3, 7],
  "article_id": 42,
  "topic_id": 12,
  "interview_id": 0,
  "image_url": "https://pixabay.com/get/…",
  "image_alt": "…"
}
```

`post_title` und `post_content` sind Pflicht (sonst `400`). Legt den Beitrag mit dem eingestellten
Status an (Autor: der anfragende Benutzer), lädt optional das Bild in die Mediathek und setzt es als
Beitragsbild, verknüpft Beitrag und Feed-Artikel bzw. – mit `interview_id` – Beitrag und Interview.
Antwort `201`: `success`, `post_id`, `post_status`, `article_id`, `edit_url`, `image`, `message`.

`image` beschreibt das Beitragsbild:

```json
{ "status": "failed", "attachment_id": 0, "attempts": 3, "error": "Pixabay hat zu viele Anfragen gemeldet (HTTP 429). …" }
```

`status` ist `none` (kein Bild gesendet), `set` oder `failed`. Vorübergehende Fehler (Timeout,
HTTP 408, 425, 429, 5xx) werden bis zu dreimal versucht, mit 2 bzw. 5 Sekunden Pause. Scheitert
das Bild endgültig, wird der Beitrag trotzdem angelegt; jeder Versuch steht im Log `posts`.

## POST /posts/{id}/featured-image

```json
{ "image_url": "https://pixabay.com/get/…", "image_alt": "…" }
```

Lädt das Bild (mit denselben Wiederholungen) und setzt es als Beitragsbild des Beitrags `id`.
Braucht zusätzlich `edit_post` für diesen Beitrag. Antwort `200`: `success`, `post_id`, `image`;
scheitert das Bild, `502` mit `error` und `image`.

## POST /suggest-image-keywords

```json
{ "title": "…", "excerpt": "…" }
```

Antwort: `{ "keywords": ["…", "…", "…"] }` – bis zu drei von der KI vorgeschlagene Suchbegriffe.
Schlägt der Vorschlag fehl, ist die Liste leer (kein Fehlerstatus).

## GET /search-images

| Parameter | Beschreibung |
|---|---|
| `query` | Suchbegriff (Pflicht) |
| `page` | Seite, ab 1 |
| `per_page` | 3–50, Standard 20 |

Sucht Fotos auf Pixabay (SafeSearch an, Sprache Deutsch). Ergebnisse werden eine Stunde
zwischengespeichert. Erfordert einen Pixabay-Schlüssel (sonst `400`).

```json
{
  "images": [
    { "id": 123, "preview_url": "…", "large_url": "…", "page_url": "…",
      "user": "…", "tags": "…", "width": 640, "height": 427 }
  ],
  "total": 500, "total_hits": 500, "page": 1, "per_page": 20
}
```

## POST /models

| Parameter | Beschreibung |
|---|---|
| `provider` | `openai`, `claude`, `ionos` oder `custom` (Pflicht) |
| `api_key` | Optional; ohne Angabe wird der gespeicherte Schlüssel des Providers verwendet. Ist `AUTO_QUILL_AI_KEY` gesetzt, gewinnt immer die Konstante. |
| `base_url` | Nur bei `custom`: Basis-URL des Endpunkts (z. B. noch nicht gespeichert, aus dem Formular) |
| `refresh` | `true` umgeht den 12-Stunden-Cache |

```json
{ "models": [ { "id": "claude-sonnet-4-6", "label": "Claude Sonnet 4.6 (claude-sonnet-4-6)" } ] }
```

POST statt GET, damit ein mitgeschickter Schlüssel nicht in URLs oder Server-Logs landet. Fehler
beim Anbieter (z. B. abgelehnter Schlüssel) kommen als `502` mit `error`.

## GET / DELETE /logs

GET-Parameter: `level`, `source`, `since_id`, `since`, `limit` (Standard 100, höchstens 500).
Antwort: `logs`, `server_time`, `debug_enabled`. DELETE leert das Log (`{"success": true}`).

---

## Interviews

- `GET /interviews` – Parameter `page`, `per_page` (1–100, Standard 20). Antwort: `interviews`
  (Kurzform mit `id`, `topic`, `status`, `answers`, `post_id`, `edit_url`, `created_at`,
  `updated_at`), `total`, `pages`, `page`.
- `POST /interviews` – `{"topic": "…", "notes": "…"}` (Thema max. 200, Hinweise max. 2 000
  Zeichen). Legt das Interview an und stellt die erste Frage. Antwort `201` mit `interview`.
- `GET /interviews/{id}` – Antwort `{"success": true, "interview": …}`; `404`, wenn es das
  Interview nicht gibt.
- `DELETE /interviews/{id}` – löscht das Interview (ein gespeicherter Beitrag bleibt).
- `POST /interviews/{id}/answer` – `{"answer": "…"}` (max. 4 000 Zeichen) oder `{"skip": true}`. Nur möglich, wenn eine
  Frage offen ist (sonst `409`). Die Antwort wird **vor** dem KI-Aufruf gespeichert; scheitert die
  nächste Frage, kommt `502` mit `error` **und** dem gespeicherten `interview` zurück.
- `POST /interviews/{id}/question` – ohne Body: fehlende Frage nachholen. `{"replace": true}`:
  offene Frage durch eine andere ersetzen.
- `POST /interviews/{id}/write` – ab 3 Antworten (sonst `400`). Antwort im selben Format wie
  [`/generate-post`](#post-generate-post) plus `interview_id`. Zum Speichern `interview_id` an
  [`/publish-post`](#post-publish-post) mitschicken – dann wird der Beitrag mit dem Interview verknüpft.

Das `interview`-Objekt enthält die Felder der Kurzform plus `notes`, `messages` (Verlauf, siehe
[Datenbank](Datenbank#wp_auto_quill_interviews--interviews-seit-db-version-15)), `target`
(Richtwert), `min_answers`, `max_chars` (Antwortlänge), `awaiting` (`answer` oder `question`) und
`enough`.

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
