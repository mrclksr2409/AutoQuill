<!-- AutoQuill Development Notes -->

# Entwicklungs-Notizen

Stand: Version 1.3.0 / DB-Version 1.4

## Projektstruktur

```
auto-quill/
├── auto-quill.php                      # Bootstrap: Header, Konstanten, Autoloader, Hooks
├── uninstall.php                       # Tabellen, Optionen, Post-Meta und Cron entfernen
├── composer.json                       # PSR-4 (AutoQuill\ → includes/), Dev-Tools
├── README.md                           # Nutzer-Dokumentation + Changelog
│
├── includes/
│   ├── class-autoloader.php            # CamelCase → kebab-case Autoloader
│   │
│   ├── Core/
│   │   ├── class-plugin.php            # boot(): hängt alle Komponenten und Cron ein
│   │   ├── class-constants.php         # Option-Keys, Tabellen, Defaults, Default-Prompts
│   │   ├── class-activator.php
│   │   ├── class-deactivator.php
│   │   ├── class-logger.php            # Logging in wp_auto_quill_logs
│   │   └── class-updater.php           # Plugin Update Checker (GitHub Releases / main)
│   │
│   ├── Database/
│   │   ├── class-schema.php            # dbDelta + Migrations-Verifikation
│   │   ├── class-sources-repository.php
│   │   ├── class-articles-repository.php
│   │   └── class-topics-repository.php
│   │
│   ├── RSS/
│   │   └── class-fetcher.php           # SimplePie-Crawler + Retention
│   │
│   ├── AI/
│   │   ├── class-client.php            # OpenAI- und Claude-HTTP-Client
│   │   ├── class-selector.php          # Themenauswahl inkl. Rating
│   │   ├── class-writer.php            # Post-Generierung
│   │   ├── class-keyword-suggester.php # Suchbegriffe für die Bildsuche
│   │   └── class-json-extractor.php    # Tolerantes JSON-Parsing der KI-Antworten
│   │
│   ├── Image/
│   │   └── class-pixabay-client.php
│   │
│   ├── Rest/
│   │   ├── class-rest-controller.php   # Routen-Registrierung
│   │   ├── class-articles-service.php  # GET /articles
│   │   ├── class-posts-service.php     # GET /topics, POST /publish-post
│   │   ├── class-images-service.php
│   │   └── class-logs-service.php
│   │
│   └── Admin/
│       ├── class-admin-menu.php        # Menü + Asset-Enqueue + wp_localize_script
│       ├── class-dashboard.php         # Übersicht (Tabs, Themen, Feed-Liste)
│       ├── class-generate-page.php     # Generierungs-Seite (versteckt)
│       ├── class-settings.php          # Settings-API, 5 Tabs
│       ├── class-sources-controller.php
│       ├── class-post-meta-box.php     # Box „AutoQuill-Quelle" im Post-Editor
│       ├── class-logs-page.php
│       ├── class-status-panel.php
│       └── class-notices.php
│
├── assets/
│   ├── admin.css
│   ├── admin.js                        # Übersicht: AutoQuill, DashboardTabs, SettingsTabs
│   ├── generate.js                     # nur auf der Generierungs-Seite: Generate, SourceTabs
│   └── auto-quill-debug.js
│
└── lib/plugin-update-checker/          # vendored, YahnisElsts
```

**Autoloader:** `includes/class-autoloader.php` bildet `AutoQuill\Sub\ClassName` auf
`includes/Sub/class-class-name.php` ab (CamelCase → kebab-case). Neue Klassen müssen dieser
Konvention folgen, sonst wird die Datei stillschweigend nicht geladen — es gibt keinen Fehler.

## Komponenten

### RSS Fetcher (`RSS/class-fetcher.php`)
- `fetch_feeds()` — alle aktiven Quellen abrufen, danach Retention und `do_action(CRON_SELECT)`
- `fetch_feed(int $source_id, string $feed_url)` — ein Feed, max. 20 Items, Dedup über
  `article_hash = md5(feed_url . link)`
- `fetch_article_content(string $link)` — lädt die Artikelseite (roh, auf 50 000 Zeichen gekürzt)

**Retention:** `ArticlesRepository::delete_older_than()` läuft am Ende jedes Fetch-Laufs.
Zwei Regeln, die nicht aufgeweicht werden dürfen:
1. `post_id IS NULL` — verknüpfte Artikel werden nie gelöscht. Sonst wird der `article_hash` frei,
   der Eintrag beim nächsten Abruf neu angelegt und erscheint als unbenutzt → doppelter Beitrag.
2. Fällt auf `fetched_at` zurück, wenn `published_date` leer ist. Undatierte Feed-Items wären sonst
   älter als jedes Cutoff und würden sofort wieder verschwinden.

### AI Selector (`AI/class-selector.php`)
- `select_top_topics()` — analysiert die letzten 50 Artikel aus 24 h und speichert 5 Themen
- `normalize_topics()` — repariert `article_id`, normalisiert `rating`, sortiert
- `normalize_rating()` — akzeptiert `85`, `"85"`, `85.0`, `"85%"`; **fehlend bleibt `null`**, nicht `0`
- `sort_by_rating()` — absteigend, unbewertete Themen zuletzt, stabil bei Gleichstand
- `fallback_analyze()` — ohne gültigen Provider: Heuristik nach Aktualität (85/70/55)

**Wichtig:** Sortiert wird **vor** dem Speichern. Die gespeicherte Reihenfolge ist die angezeigte
Reihenfolge, und der Index in diesem Array ist der `topic_index`, den das Dashboard zurückschickt.
Wer erst beim Rendern sortiert, generiert das falsche Thema.

**Token-Budget:** 2500. Rating und Begründung über fünf Themen sprengen die früheren 1500; der
Client liefert dann `WP_Error('truncated')` und die tägliche Selektion bricht kommentarlos ab.

### AI Writer (`AI/class-writer.php`)
- `generate_post()` — REST-Endpoint
- `resolve_source(array $params)` — zwei Einstiegspunkte, siehe unten
- `run_combined_step()` — eine KI-Anfrage für Titel, Text, Auszug und Kategorien, mit einem
  Retry bei unparsebarem JSON
- `chat_with_token_retry()` — wiederholt einmal mit 1,6-fachem Budget bei `WP_Error('truncated')`
- `estimate_max_tokens()` / `extract_target_words()` — Budget aus der Wortzahl des Body-Prompts
- `append_source_link()` — der garantierte Quellenhinweis
- `generate_basic_post()` — Fallback ohne gültigen KI-Provider

### Admin Dashboard (`Admin/class-dashboard.php`)
- `render()` — die Übersicht über die volle Breite, zwei Tabs in einem Panel
- `render_feed_rows(array $items)` — `<tbody>`-Zeilen; von `render()` **und** vom REST-Endpoint genutzt
- `resolve_linked_posts(array $items)` — Beiträge im Bulk auflösen (eine Meta-Query, ein `get_posts()`)
- `rating_class(int $rating)` — `is-rating-high|mid|low`

## Verträge, die man kennen muss

### Eingabe der Post-Generierung

`POST /generate-post` kennt zwei Wege, genau einer muss greifen:

| Aufrufer | Payload | Effekt |
|---|---|---|
| Tab *Alle Feed-Einträge* | `{article_id}` | Pseudo-Topic aus der Artikel-Zeile, **kein** `mark_generated()` |
| Tab *Top-Themen* | `{topic_id, topic_index}` | exakter Zugriff auf `topics[topic_index]` |
| Legacy (gecachtes `admin.js` < 1.2.0) | `{topic_id, title}` | Titel-Vergleich, eine Release lang geduldet |

Die Antwort trägt immer `article_id`, `topic_id` und `topic_index`. `admin.js` merkt sich
`article_id` und schickt es an `publish-post` — dadurch entfällt dort jede erneute Auflösung.

### Topics-JSON (`wp_auto_quill_topics.topics`)

```json
[
  {
    "article_id": 42,
    "title": "…",
    "summary": "…",
    "rating": 87,
    "rating_reason": "Kurze Begründung"
  }
]
```

`rating` ist `int 0–100` **oder `null`** (unbewertet). Zeilen, die vor 1.2.0 geschrieben wurden,
haben weder `rating` noch `rating_reason` — jeder Leser muss das aushalten.

### Post-Meta-Vertrag

| Meta-Key | Inhalt |
|---|---|
| `_auto_quill_source_url` | URL des Originalartikels — die kanonische Rückreferenz |
| `_auto_quill_article_id` | ID der Artikel-Zeile (darf verwaisen) |
| `_auto_quill_article_title` | Titel des Originalartikels |
| `_auto_quill_feed_name` | Name der RSS-Quelle |

Alle mit Unterstrich, also protected meta, nicht in REST. Geschrieben in
`PostsService::link_source_article()`, entfernt in `uninstall.php` per `delete_post_meta_by_key()`.

Verwaisen kann es in beide Richtungen, beides wird **lazy** aufgelöst, ohne Cleanup-Job:
- Artikel-Zeile weg → die Post-Meta trägt die Provenienz weiter.
- Beitrag gelöscht oder im Papierkorb → `get_posts(['post_status' => 'any'])` liefert ihn nicht
  zurück, die Zeile erscheint wieder als unverknüpft.

### Token-Budget

`estimate_max_tokens()` liest die größte Wortzahl aus dem aufgelösten Body-Prompt
(Bereiche wie `800-1200 Wörter`, deutsche Tausenderpunkte wie `1.500 Wörter`), kein Treffer → 1200.

```
tokens = woerter * 4 + 1000, geklemmt auf [6500, 16000]
```

Die Untergrenze 6500 ist der früher hartkodierte Wert: Der Standard-Prompt (1200 Wörter ⇒ 5800)
landet darauf, das Verhalten ändert sich also für bestehende Installationen nicht.

Die Obergrenze 16000 liegt unter dem Ausgabelimit von `gpt-4o-mini` (16 384). **Achtung:** Die
Modellfelder in den Einstellungen sind Freitext. Wer ein Modell mit kleinerem Ausgabelimit einträgt
(`gpt-4`: 8192, `gpt-3.5-turbo`: 4096), bekommt statt einer gekürzten Antwort einen HTTP 400 —
dafür gibt es den Filter `auto_quill_max_tokens`.

Ebenfalls erwähnenswert: `Client::call_openai()` sendet `max_tokens` und eine abweichende
`temperature` bedingungslos. Neuere OpenAI-Modelle erwarten stattdessen `max_completion_tokens`
und lehnen abweichende `temperature`-Werte ab.

### Quellenhinweis

`append_source_link()` hängt den Hinweis **nach** `parse_combined_response()` an — also nach
`wp_kses_post()`. Dadurch steht er schon in der Vorschau und wird nicht wegsanitisiert.

Das Template ist reiner Text; `sanitize_textarea_field()` entfernt `<`. Platzhalter:
`{source_link}`, `{article_title}`, `{source_url}`, `{feed_name}`.

**Escaping-Reihenfolge:** erst das Template mit `esc_html()`, dann den fertigen Anchor einsetzen.
Andersherum zerlegt es das Markup.

Der Round-Trip bis `wp_insert_post()` funktioniert, weil `publishPost()` den Server-String aus
`$btn.data('post-content')` zurückschickt, nicht das gerenderte DOM. Eine editierbare Vorschau
würde das brechen.

## Datenbank-Schema

`Schema::ensure_tables()` hängt an `admin_init` (Priorität 1). REST-Requests und WP-Cron feuern das
**nicht** — Endpoints, die `post_id` anfassen (`ArticlesService::list_articles()`,
`PostsService::publish_post()`), rufen es deshalb selbst auf. Der `static $checked`-Guard macht
Wiederholungen zum No-op.

`create_tables()` verifiziert nach `dbDelta()` die erwarteten Spalten und setzt
`auto_quill_db_version` **nur bei Erfolg** hoch. `dbDelta()` wirft nie und schluckt Fehler; ohne
diese Prüfung würde eine fehlgeschlagene Migration dauerhaft als erledigt gelten.

### wp_auto_quill_sources
```sql
id          BIGINT UNSIGNED PK AUTO_INCREMENT
title       VARCHAR(255)
feed_url    TEXT
is_active   TINYINT(1) DEFAULT 1
created_at  DATETIME
updated_at  DATETIME
KEY feed_active (is_active)
```

### wp_auto_quill_articles
```sql
id              BIGINT UNSIGNED PK AUTO_INCREMENT
source_id       BIGINT UNSIGNED          -- logischer FK, kein DB-Constraint
title           VARCHAR(255)
description     LONGTEXT                 -- strip_tags, max. 1000 Zeichen
content         LONGTEXT                 -- rohes HTML, max. 50 000 Zeichen
author          VARCHAR(255)
published_date  DATETIME
article_url     TEXT
article_hash    VARCHAR(64) UNIQUE       -- md5(feed_url . link)
post_id         BIGINT UNSIGNED NULL     -- seit DB 1.4; NULL = unverknüpft
fetched_at      DATETIME
KEY source_id, KEY published_date, KEY post_id
```

`content` gehört **nie** in eine Listen-Abfrage — 20 Zeilen wären rund 1 MB.
`ArticlesRepository::paginate()` selektiert deshalb eine explizite Spaltenliste.

### wp_auto_quill_topics
```sql
id                   BIGINT UNSIGNED PK AUTO_INCREMENT
topic_date           DATE UNIQUE          -- eine Zeile pro Tag
topics               LONGTEXT             -- JSON, Schema siehe oben
selected_topic_id    INT                  -- Index des zuletzt generierten Themas
selected_topic_title VARCHAR(255)
post_id              BIGINT UNSIGNED      -- nur der zuletzt veröffentlichte Beitrag des Tages
status               VARCHAR(20)          -- pending | generated | published
created_at, updated_at DATETIME
```

`topics.post_id` kann pro Tag nur einen Beitrag abbilden. Die belastbare Verknüpfung ist
`articles.post_id` plus die Post-Meta; diese Spalte bleibt nur aus Kompatibilitätsgründen bestehen.

### wp_auto_quill_logs
```sql
id, created_at, level VARCHAR(10), source VARCHAR(40), message TEXT, context LONGTEXT
```

## REST API

Namespace `auto-quill/v1`, alle Routen mit `permission_callback` → `current_user_can('manage_options')`.

| Methode | Route | Callback |
|---|---|---|
| GET | `/topics` | `PostsService::get_today_topics` |
| GET | `/articles` | `ArticlesService::list_articles` |
| POST | `/generate-post` | `Writer::generate_post` |
| POST | `/publish-post` | `PostsService::publish_post` |
| GET | `/search-images` | `ImagesService::search` |
| POST | `/suggest-image-keywords` | `ImagesService::suggest_keywords` |
| GET/DELETE | `/logs` | `LogsService` |

### GET /articles

Parameter (alle mit `sanitize_callback` bzw. `enum` deklariert):
`page` (int, 1), `per_page` (int, 20, max 100), `source_id` (int, 0 = alle),
`search` (string), `linked` (`all` | `linked` | `unlinked`).

```json
{ "html": "<tr>…</tr>", "total": 137, "page": 1, "pages": 7, "per_page": 20 }
```

`html` ist serverseitig gerendert. Das ist Absicht: Artikel-Titel werden beim Insert nur gekürzt,
Beschreibungen nur von Tags befreit, und `article_url` ist genau das, was der Feed geliefert hat.
Escaping gehört in PHP, wo `esc_html()`/`esc_url()`/`esc_attr()` ohnehin die Konvention sind, nicht
in handgebauten DOM-Code im Browser.

## Admin-Assets

`AdminMenu::enqueue_assets()` lädt CSS/JS auf allen Plugin-Seiten und legt zwei Objekte an:
`autoQuill` (`apiUrl`, `nonce`, `restNonce`, `publishButtonLabel`, `i18n`) und `autoQuillDebug`.

`admin.js` (alle Plugin-Seiten) enthält:
- `AutoQuill` — Feed-Liste, Recrawl/Reselect, `showAlert`, `errorMessage`
- `DashboardTabs` — `.auto-quill-dashboard-tabs` / `.auto-quill-dash-panel`, wertet `#dash-<tab>` aus
- `SettingsTabs` — `.auto-quill-settings-tabs` / `.auto-quill-tab-panel`

`generate.js` (nur auf der Generierungs-Seite, mit `admin.js` als Dependency) enthält:
- `Generate` — Auto-Start, Busy-Overlay, Veröffentlichen, Bildauswahl
- `SourceTabs` — `.auto-quill-source-tabs` / `.auto-quill-source-panel`

Geteilt wird über eine kleine, explizite Oberfläche, die `admin.js` setzt:
`window.AutoQuill = { t, showAlert, errorMessage, apiUrl, restNonce }`.

**Die drei Tab-Objekte dürfen keine Klassennamen teilen.** `SettingsTabs.activate()` versteckt
`.auto-quill-tab-panel` global, worauf die Einstellungsseite angewiesen ist (ihr StatusPanel liegt
außerhalb des `<form>`). Würden sich zwei Seiten dieselbe Klasse teilen, versteckten sie sich
gegenseitig — deshalb hat jedes Widget ein eigenes Klassenpaar.

**Cache-Busting:** `AUTO_QUILL_VERSION` ist der Versions-Parameter der Assets. Wer JS-seitige
Änderungen ohne Versions-Bump ausliefert, bekommt bei Nutzern die alte `admin.js` — und die lässt
beispielsweise `article_id` aus dem Publish-Payload weg, wodurch die Verknüpfung fehlerfrei und
unbemerkt nie geschrieben wird.

## WordPress Hooks & Filters

### Actions
- `auto_quill_daily_fetch` — RSS-Fetch
- `auto_quill_daily_select` — Themen-Selektion
- `auto_quill_topics_selected` — nach der Selektion, Parameter `$topics`

### Filters
- `auto_quill_max_tokens` — `($tokens, $words, $body_prompt)`

## Sicherheit

- **API-Keys:** in `wp_options`; `AUTO_QUILL_AI_KEY` und `AUTO_QUILL_PIXABAY_KEY` in `wp-config.php`
  haben Vorrang. Leere Eingabefelder überschreiben gespeicherte Schlüssel nicht.
- **Nonces:** Admin-AJAX über `check_ajax_referer(C::NONCE_SCOPE)`, REST über den Cookie-Nonce.
  Ein abgelaufener Nonce kommt als nackter 403 zurück; `AutoQuill.errorMessage()` übersetzt das.
- **Capability:** durchgehend `manage_options`.
- **Feed-Daten sind nicht vertrauenswürdig.** Titel, Beschreibungen und URLs kommen von Dritten.
  Jede Ausgabe läuft durch `esc_html()`/`esc_url()`/`esc_attr()`; `esc_url()` leert Schemata wie
  `javascript:`, weshalb an beiden Stellen auf ein leeres Ergebnis geprüft wird, statt ein totes
  `<a href="">` zu erzeugen.
- **SQL:** ausschließlich `$wpdb->prepare()` mit `esc_like()` für Suchmuster. `prepare()` mit leerem
  Args-Array löst `_doing_it_wrong` aus — `paginate()` fängt den ungefilterten Fall ab.

## Testen

Das Repo hat **keine automatisierten Tests und keinen Build-Schritt**. Vor einem Commit mindestens:

```bash
find includes -name '*.php' -exec php -l {} \;
php -l auto-quill.php && php -l uninstall.php
node --check assets/admin.js
```

Dazu prüfen, ob Klassenname und Dateiname zur Autoloader-Konvention passen.

### Manuelle Szenarien

1. **Migration** — Dashboard öffnen, `SHOW COLUMNS FROM wp_auto_quill_articles` enthält `post_id`,
   `auto_quill_db_version` ist `1.4`, bestehende Artikel sind unverändert vorhanden.
2. **Rating** — „Feeds neu holen + Topics neu wählen"; Themen absteigend sortiert mit Badge.
   Themen-Zeilen von vor 1.2.0 rendern ohne Badge und ohne Fehler.
3. **Feed-Liste** — Tab wechseln, Quellen-Filter, Suche, „nur ohne Blog-Post" und Paginierung prüfen.
4. **Generierung aus der Liste** — Vorschau füllt sich, der Beitrag endet mit dem Quellen-Absatz.
5. **Verknüpfung** — veröffentlichen, Box „AutoQuill-Quelle" im Editor prüfen, im Dashboard zeigt
   die Zeile den Beitrag. Danach „Feeds neu holen" auslösen: die Zeile darf nicht verschwinden und
   nicht als neue, unverknüpfte Zeile wieder auftauchen.
6. **Papierkorb** — Beitrag löschen, die Zeile erscheint wieder als unverknüpft, ohne Notice.
7. **Länge** — Body-Prompt auf `2000-2500 Wörter` ändern; im Log steht bei „OpenAI Request startet"
   ein `max_tokens` von ~11000 statt 6500, kein `truncated`-Fehler.

## Generierungs-Seite (`Admin/class-generate-page.php`)

Erreichbar unter `admin.php?page=auto-quill-generate` mit `article_id` **oder**
`topic_id` + `topic_index`, nie über das Menü.

**Versteckt, aber korrekt eingebunden.** `AdminMenu::register()` legt die Seite als echtes
Submenu an und `hide_generate_page()` entfernt den Eintrag auf `admin_menu` mit Priorität 999.
`remove_submenu_page()` löscht nur den `$submenu`-Eintrag; `$_registered_pages` und
`$_parent_pages` bleiben, deshalb routet die Seite weiter. Eine Registrierung mit `null` als
Parent wäre schlechter: Dann liefert `get_admin_page_parent()` einen Leerstring und das
AutoQuill-Menü hebt sich gar nicht mehr hervor.

Daraus folgen drei Dinge, die bewusst mitgelöst sind:
- `submenu_file`-Filter, damit überhaupt ein Eintrag als aktiv markiert wird.
- Das `<h1>` ist **hartkodiert**; `get_admin_page_title()` fiele ohne `$submenu`-Eintrag auf den
  Titel des Elternmenüs („AutoQuill") zurück. Für den Browser-Tab tut das der `admin_title`-Filter.
- Der explizite `current_user_can('manage_options')`-Check bleibt Pflicht, weil die Absicherung
  über das Elternmenü nur ein Nebeneffekt ist.

**Quelltext serverseitig.** `Writer::source_preview()` ist der einzige öffentliche Zugang zur
Quell-Pipeline; `resolve_source()` und `build_source_block()` bleiben privat. Der Text wird beim
Seitenaufbau gerendert, nicht per REST geholt — nur so steht er schon da, während die KI noch
schreibt. Die Quelle ist ohnehin unveränderlich: `articles.content` schreibt der Fetcher einmal.

**Beide Quell-Tabs zeigen escapten Text, kein gerendertes HTML.** `wp_kses_post()` wäre hier
falsch: Es entfernt `<script>`/`<style>`-Tags, behält aber deren Textinhalt (eine gescrapte Seite
erschiene als Wand aus minifiziertem JS), `<img>` überlebt und zöge Fremdbilder in den Admin, und
relative URLs lösen gegen die eigene Domain auf. Wer die gerenderte Seite braucht, nimmt den Link
„Originalartikel öffnen".

**Nonce und Auto-Start.** Eine Generierung kostet einen bezahlten API-Call, deshalb trägt der Link
aus der Übersicht einen Nonce (`C::NONCE_GENERATE`). Gültig → Start beim Laden; fehlt oder
abgelaufen → nur ein Button „Jetzt generieren". Direkt nach dem Start entfernt `generate.js` den
Nonce per `history.replaceState()` aus der Adressleiste, damit Reload, Zurück-Taste und Lesezeichen
keinen zweiten bezahlten Aufruf auslösen.

**Veröffentlichen ohne Reload.** `publish-post` liefert `edit_url` und `post_status`; die Seite
zeigt einen Erfolgskasten statt neu zu laden. Ein Reload würde die Generierung erneut anstoßen.

## Offene Punkte

- [ ] Verschlüsselung der API-Keys
- [ ] Unit Tests / PHPCS in CI
- [ ] WP-CLI-Kommandos
- [ ] `posts_per_day` ist in den Defaults vorhanden, hat aber keine UI und wird nirgends gelesen
- [ ] `autoQuill.fetchAction` wird lokalisiert, aber von keinem Skript gelesen
- [ ] `Fetcher::fetch_feeds()` ruft am Ende `do_action(CRON_SELECT)` auf, wodurch die Selektion an
      einem Cron-Tag zweimal läuft
- [ ] `fetch_article_content()` speichert rohes HTML ohne Readability-Extraktion
- [ ] Mehrsprachigkeit, weitere KI-Provider
