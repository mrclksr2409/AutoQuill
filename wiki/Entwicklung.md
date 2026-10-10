# Entwicklung

Diese Seite gibt einen Überblick. Die ausführlichen Entwickler-Notizen – inklusive aller
Stolperfallen und „Verträge“ zwischen den Komponenten – stehen in
[`DEVELOPMENT.md`](https://github.com/mrclksr2409/AutoQuill/blob/main/DEVELOPMENT.md).

## Architektur

```
                     ┌──────────────┐
  WP-Cron ──────────►│  Scheduler   │── fetch ──► RSS\Fetcher ──► articles
                     │              │── select ─► AI\Selector ──► topics ──► auto_quill_topics_selected
                     │  Backup      │── backup ─► auto_quill_backups (Option)
                     │  Notifier    │── digest ─► wp_mail
                     └──────────────┘
                                         AI\Client ◄── Selector / Writer / Interviewer / KeywordSuggester
  Admin-Seiten ──► Admin\* ──► REST (Rest\*) ──► AI\Writer ──────► Vorschau
                                              ──► AI\Interviewer ─► interviews
                                              ──► PostsService ──► wp_insert_post + Meta
```

`AI\Client` spricht die Anthropic-API direkt und alle anderen Provider (OpenAI, IONOS, eigener
Endpunkt) über die OpenAI-kompatible Schnittstelle `/chat/completions`.

## Verzeichnisstruktur

```
auto-quill/
├── auto-quill.php            Bootstrap: Header, Konstanten, Autoloader, WP-Backend UI laden
├── uninstall.php             Aufräumen beim Löschen
├── composer.json             PSR-4 (AutoQuill\ → includes/), Dev-Tools
├── includes/
│   ├── Core/                 Plugin, Constants, Scheduler, Backup, Notifier, Logger, Updater,
│   │                         Activator, Deactivator
│   ├── Database/             Schema + Repositories (sources, articles, topics, interviews)
│   ├── RSS/                  Fetcher (SimplePie)
│   ├── AI/                   Client, Selector, Writer, Interviewer, ModelCatalog,
│   │                         KeywordSuggester, JsonExtractor
│   ├── Image/                PixabayClient
│   ├── Rest/                 REST-Controller und Services (articles, posts, images, models,
│   │                         logs, interviews)
│   └── Admin/                Menü, Dashboard, Generierungs-Seite, Interview-Seite, Settings,
│                             Quellen, Backup, Logs, Status-Panel, Post-Meta-Box, Notices
├── assets/                   admin.js/.css, post-editor.js, generate.js, interview.js,
│                             auto-quill-debug.js
├── lib/
│   ├── plugin-update-checker/  vendored (YahnisElsts)
│   └── wp-backend-ui/          vendored, gemeinsames Admin-Design (nicht ändern)
└── wiki/                     Quelle dieses Wikis
```

**Autoloader:** `AutoQuill\Sub\ClassName` → `includes/Sub/class-class-name.php`. Neue Klassen müssen
dieser Konvention folgen, sonst werden sie stillschweigend nicht geladen.

## Admin-Design: WP-Backend UI

Seit Version 1.8.0 nutzen alle AutoQuill-Seiten das mitgelieferte Design-System
**WP-Backend UI** (`lib/wp-backend-ui/`, Version 1.0.2), das AutoQuill mit anderen Plugins teilt.

- Geladen wird es in `auto-quill.php` auf oberster Ebene; bringen mehrere Plugins die Bibliothek
  mit, gewinnt automatisch die neueste Version.
- `AdminMenu::register()` meldet die Seiten `auto-quill`, `auto-quill-interview`,
  `auto-quill-sources`, `auto-quill-settings` und `auto-quill-logs` per `wpb_admin_ui_register()` an.
- Verwendet werden u. a. `WPB_Admin_UI::header()` (Seitenkopf mit Icon und Versionsnummer),
  `WPB_Admin_UI::badge()`, `WPB_Admin_UI::alert()`, Karten (`wpb-card`), Raster, Toolbars,
  Code-Blöcke, das Busy-Overlay und der native `<dialog>` für die Bildauswahl; die Quell-Tabs der
  Generierungs-Seite laufen über `data-wpb-tabs`.
- `assets/admin.css` enthält nur noch AutoQuill-spezifisches Layout (Chat, Bildraster, Bewertung,
  Feed-Liste) und nutzt die `--wpb-*`-Design-Tokens.

## Konventionen

- PHP 8.0, Namespace `AutoQuill\…`, statische Services wo kein Zustand nötig ist
- **Core darf nicht von Admin abhängen** (Admin → Core ist erlaubt)
- Oberfläche und Log-Meldungen auf Deutsch, Code und Kommentare auf Englisch
- Einstellungen: neuer Schlüssel immer in `Constants::defaults()` **und** in `Settings::sanitize()`;
  Lesen stets mit Fallback auf die Defaults, weil bestehende Installationen neue Schlüssel nicht haben
- Zeitgesteuertes: Einzel-Events mit Neuverkettung über `Scheduler::next_occurrence()`, nie `daily`
- Admin-Aktionen über `admin_post_*` mit Nonce und `manage_options`
- JS-Änderungen immer mit Versionssprung ausliefern (Cache-Busting über `AUTO_QUILL_VERSION`)

## Lokal testen

Es gibt keine automatisierten Tests und keinen Build-Schritt. Vor einem Commit mindestens:

```bash
find includes -name '*.php' -exec php -l {} \;
php -l auto-quill.php && php -l uninstall.php
node --check assets/admin.js
```

- Cron-Läufe auslösen: `wp cron event run auto_quill_daily_fetch`
- Debug-Logging einschalten und die Browser-Konsole beobachten
- Manuelle Testszenarien stehen in `DEVELOPMENT.md`

## Release

1. Version in `auto-quill.php` (Header **und** `AUTO_QUILL_VERSION`) und `composer.json` erhöhen
2. Changelog im README ergänzen (und die [Changelog](Changelog)-Seite dieses Wikis)
3. Auf `beta` pushen – Beta-Installationen erhalten das Update
4. `beta` nach `main` übernehmen – alle Installationen erhalten das Update automatisch
   (ein GitHub-Release ist dafür nicht nötig, Releases und Tags werden ignoriert)

## Dieses Wiki bearbeiten

Die Wiki-Seiten liegen im Ordner [`wiki/`](https://github.com/mrclksr2409/AutoQuill/tree/main/wiki)
des Repositorys. Eine GitHub Action (`.github/workflows/wiki-sync.yml`) spiegelt sie bei jedem Push
auf `main`, der den Ordner `wiki/` ändert, ins Wiki (manuell auch per *workflow_dispatch*).
**Änderungen direkt im Wiki werden dabei überschrieben** – bitte immer im Repository bearbeiten.

- Dateiname = Seitenname (`Blog-Post-erstellen.md` → Seite *Blog Post erstellen*)
- Links ohne `.md`: `[Text](Seitenname)`
- `_Sidebar.md` und `_Footer.md` sind Navigation und Fußzeile
- Neue Seiten auch in `Home.md` und `_Sidebar.md` eintragen
