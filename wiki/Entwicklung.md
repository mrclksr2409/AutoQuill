# Entwicklung

Diese Seite gibt einen Überblick. Die ausführlichen Entwickler-Notizen – inklusive aller
Stolperfallen und „Verträge“ zwischen den Komponenten – stehen in
[`DEVELOPMENT.md`](https://github.com/mrclksr2409/autoquill/blob/main/DEVELOPMENT.md).

## Architektur

```
                     ┌──────────────┐
  WP-Cron ──────────►│  Scheduler   │── fetch ──► RSS\Fetcher ──► articles
                     │  Backup      │── select ─► AI\Selector ──► topics
                     │  Notifier    │── digest ─► wp_mail
                     └──────────────┘
                                         AI\Client ◄── Selector / Writer / KeywordSuggester
  Admin-Seiten ──► Admin\* ──► REST (Rest\*) ──► AI\Writer ──► Vorschau
                                              ──► PostsService ──► wp_insert_post + Meta
```

## Verzeichnisstruktur

```
auto-quill/
├── auto-quill.php            Bootstrap: Header, Konstanten, Autoloader
├── uninstall.php             Aufräumen beim Löschen
├── includes/
│   ├── Core/                 Plugin, Constants, Scheduler, Backup, Notifier, Logger, Updater
│   ├── Database/             Schema + Repositories (sources, articles, topics)
│   ├── RSS/                  Fetcher (SimplePie)
│   ├── AI/                   Client, Selector, Writer, ModelCatalog, KeywordSuggester, JsonExtractor
│   ├── Image/                PixabayClient
│   ├── Rest/                 REST-Controller und Services
│   └── Admin/                Menü, Dashboard, Generierungs-Seite, Settings, Backup, Logs, Status
├── assets/                   admin.js/.css, generate.js, auto-quill-debug.js
├── lib/plugin-update-checker vendored
└── wiki/                     Quelle dieses Wikis
```

**Autoloader:** `AutoQuill\Sub\ClassName` → `includes/Sub/class-class-name.php`. Neue Klassen müssen
dieser Konvention folgen, sonst werden sie stillschweigend nicht geladen.

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

- Syntax: `find includes -name '*.php' -exec php -l {} \;`
- JavaScript: `node --check assets/admin.js`
- Cron-Läufe auslösen: `wp cron event run auto_quill_daily_fetch`
- Debug-Logging einschalten und die Browser-Konsole beobachten

## Release

1. Version in `auto-quill.php` (Header **und** `AUTO_QUILL_VERSION`) und `composer.json` erhöhen
2. Changelog im README ergänzen
3. Auf `main` mergen, Tag `vX.Y.Z` setzen und ein GitHub-Release erstellen – Installationen erhalten
   das Update automatisch, Beta-Installationen schon ab dem Merge

## Dieses Wiki bearbeiten

Die Wiki-Seiten liegen im Ordner [`wiki/`](https://github.com/mrclksr2409/autoquill/tree/main/wiki)
des Repositorys. Eine GitHub Action (`.github/workflows/wiki-sync.yml`) spiegelt sie bei jedem Push
auf `main` ins Wiki. **Änderungen direkt im Wiki werden dabei überschrieben** – bitte immer im
Repository bearbeiten.

- Dateiname = Seitenname (`Blog-Post-erstellen.md` → Seite *Blog Post erstellen*)
- Links ohne `.md`: `[Text](Seitenname)`
- `_Sidebar.md` und `_Footer.md` sind Navigation und Fußzeile
