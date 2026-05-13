<!-- AutoQuill Development Notes -->

# Entwicklungs-Notizen

## Projektstruktur

```
auto-quill/
├── auto-quill.php              # Haupt-Plugin-Datei
├── install.php                 # Installations-Skript
├── README.md                   # Dokumentation
├── LICENSE                     # GPL v2 Lizenz
│
├── includes/
│   ├── class-autoloader.php    # PSR-4 Autoloader
│   ├── class-activator.php     # Aktivierungs-Hook
│   ├── class-deactivator.php   # Deaktivierungs-Hook
│   ├── class-database.php      # DB-Wrapper
│   │
│   ├── Core/
│   │   └── class-plugin.php    # Haupt-Plugin-Klasse
│   │
│   ├── Database/
│   │   └── class-schema.php    # Datenbank-Schema
│   │
│   ├── RSS/
│   │   └── class-fetcher.php   # RSS-Crawler
│   │
│   ├── AI/
│   │   ├── class-selector.php  # Themen-Analyzer
│   │   └── class-writer.php    # Post-Generierung
│   │
│   └── Admin/
│       └── class-admin-page.php # Admin-Interface
│
├── assets/
│   ├── admin.css               # Admin-Styling
│   └── admin.js                # Admin-JavaScript
│
└── admin/
    └── [weitere Admin-Templates]
```

## Komponenten

### 1. RSS Fetcher (class-fetcher.php)
- Liest RSS-Feeds aus konfigurierten Quellen
- Speichert Artikel mit Hash-Deduplication
- Läuft täglich über WordPress Cron

**Hauptfunktionen:**
- `fetch_feeds()` - Holt alle aktiven RSS-Feeds
- `fetch_feed()` - Verarbeitet einen einzelnen Feed
- `add_feed_source()` - Neue Quelle hinzufügen
- `get_articles_since()` - Artikel der letzten X Stunden

### 2. AI Selector (class-selector.php)
- Analysiert Artikel der letzten 24 Stunden
- Ruft OpenAI/Claude auf für KI-Analyse
- Selektiert Top 5 Themen
- Speichert als JSON für Admin-UI

**Hauptfunktionen:**
- `select_top_topics()` - Tägliche Topic-Selektion
- `analyze_articles()` - KI-Analyse
- `call_openai()` - OpenAI API Integration
- `fallback_analyze()` - Fallback ohne KI

### 3. AI Writer (class-writer.php)
- Generiert vollständige Blog-Posts
- Nutzt gewähltes Thema als Basis
- Erstellt ~800-1200 Wort Blog-Posts
- REST API Endpoint für Frontend

**Hauptfunktionen:**
- `generate_post()` - REST API Endpoint
- `write_blog_post()` - Schreibt den Post
- `call_openai_write()` - OpenAI Text-Generation
- `generate_basic_post()` - Fallback-Post

### 4. Admin Page (class-admin-page.php)
- Haupt-Dashboard mit heute's Themen
- RSS-Quellen Verwaltung
- Plugin-Einstellungen
- REST API Endpoints

**Admin-Seiten:**
- `/admin.php?page=auto-quill` - Startseite
- `/admin.php?page=auto-quill-sources` - RSS-Verwaltung
- `/admin.php?page=auto-quill-settings` - Einstellungen

## Workflow Ablauf

```
┌─────────────────────────────────────────────────────────┐
│         WordPress Täglich (um 00:00 UTC)                │
└────────────────────┬────────────────────────────────────┘
                     │
        ┌────────────▼────────────┐
        │   auto_quill_daily_fetch │
        │  (RSS Fetcher startet)  │
        └────────────┬────────────┘
                     │
          ┌──────────▼──────────┐
          │ Liest alle RSS-Feeds│
          │ Speichert Artikel   │
          └──────────┬──────────┘
                     │
        ┌────────────▼────────────┐
        │ auto_quill_daily_select  │
        │ (KI Selector startet)   │
        └────────────┬────────────┘
                     │
          ┌──────────▼──────────────┐
          │ Analysiert 50 letzte    │
          │ Artikel der letzten 24h │
          └──────────┬──────────────┘
                     │
          ┌──────────▼──────────────┐
          │ Ruft OpenAI/Claude auf  │
          │ Top 5 Themen selektieren│
          └──────────┬──────────────┘
                     │
          ┌──────────▼──────────────┐
          │ Speichert in DB         │
          │ Status: "pending"       │
          └──────────┬──────────────┘
                     │
          ┌──────────▼──────────────┐
          │ Admin sieht Themen      │
          │ im Dashboard            │
          └──────────┬──────────────┘
                     │
       ┌─────────────▼─────────────┐
       │ Benutzer wählt ein Thema  │
       │ Klickt "Blog Post gen."   │
       └──────┬────────────────────┘
              │
    ┌─────────▼───────────┐
    │ Ruft /generate-post │
    │ REST API Endpoint   │
    └──────┬──────────────┘
           │
    ┌──────▼────────────────┐
    │ OpenAI schreibt Post  │
    │ (800-1200 Wörter)     │
    └──────┬─────────────────┘
           │
    ┌──────▼────────────────┐
    │ Post in Vorschau      │
    │ anzeigen              │
    └──────┬─────────────────┘
           │
    ┌──────▼────────────────┐
    │ Benutzer klickt       │
    │ "Veröffentlichen"     │
    └──────┬─────────────────┘
           │
    ┌──────▼────────────────┐
    │ Post wird als Entwurf │
    │ oder published        │
    │ erstellt (wp_posts)   │
    └──────────────────────┘
```

## Datenbank Schema

### wp_auto_quill_sources
```sql
id              BIGINT PRIMARY KEY
title           VARCHAR(255)
feed_url        TEXT
is_active       TINYINT(1)
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

### wp_auto_quill_articles
```sql
id              BIGINT PRIMARY KEY
source_id       BIGINT FK
title           VARCHAR(255)
description     LONGTEXT
content         LONGTEXT
author          VARCHAR(255)
published_date  DATETIME
article_url     TEXT
article_hash    VARCHAR(64) UNIQUE
fetched_at      TIMESTAMP
```

### wp_auto_quill_topics
```sql
id                  BIGINT PRIMARY KEY
topic_date          DATE UNIQUE
topics              JSON [
                      {title, summary, reason, article_id}
                    ]
selected_topic_id   INT
selected_topic_title VARCHAR(255)
post_id             BIGINT FK (wp_posts)
status              ENUM (pending|selected|generated|published)
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

### wp_auto_quill_settings
```sql
id              BIGINT PRIMARY KEY
option_name     VARCHAR(255) UNIQUE
option_value    LONGTEXT
updated_at      TIMESTAMP
```

## REST API Endpoints

### GET /wp-json/auto-quill/v1/topics
Gibt heute's Themen zurück

**Response:**
```json
{
  "topics": [
    {
      "title": "Interessantes Thema",
      "summary": "Kurze Zusammenfassung...",
      "reason": "Warum es interessant ist"
    }
  ],
  "status": "pending"
}
```

### POST /wp-json/auto-quill/v1/generate-post
Generiert einen Blog-Post

**Request:**
```json
{
  "topic_id": 1,
  "title": "Titel des Themas"
}
```

**Response:**
```json
{
  "success": true,
  "post_content": "<h1>...</h1>...",
  "topic": {...}
}
```

### POST /wp-json/auto-quill/v1/publish-post
Veröffentlicht einen Post

**Request:**
```json
{
  "post_title": "Blog Post Titel",
  "post_content": "<h1>...</h1>..."
}
```

**Response:**
```json
{
  "success": true,
  "post_id": 123,
  "message": "Post XYZ als draft erstellt"
}
```

## WordPress Hooks & Filters

### Actions
- `auto_quill_daily_fetch` - RSS-Fetch triggern
- `auto_quill_daily_select` - Topic-Selektion triggern
- `auto_quill_topics_selected` - Nach Topic-Selektion

### Filters
- `auto_quill_fetch_args` - RSS-Fetch Argumente
- `auto_quill_post_content` - Post-Inhalt filtern

## Sicherheit

### API-Key Speicherung
- API-Keys werden in wp_options gespeichert
- Sollte in Zukunft verschlüsselt werden
- Verwende `wp_remote_post()` für sichere Requests

### Nonce-Verifikation
- Alle Admin-Forms mit WordPress Nonces schützen
- REST API mit Capability-Check schützen

### Input Sanitization
- `sanitize_text_field()` für Text-Input
- `esc_url()` für URLs
- `wp_kses_post()` für Post-Content

## Performance

### Caching Opportunities
- Themen-Cache für 24h
- Artikel-Cache (deduplicated via hash)
- API-Response Caching

### Datenbank Optimierungen
- Indices auf `is_active`, `published_date`, `status`
- Soft-Delete statt harter Löschung
- Artikel-Archivierung nach 30 Tagen

## Zu Implementieren

- [ ] Claude API Integration
- [ ] Weitere KI-Provider (Google, etc.)
- [ ] Custom Prompt Templates
- [ ] Email-Benachrichtigungen
- [ ] Social Media Sharing
- [ ] Artikel-Kategorisierung
- [ ] Multi-Language Support
- [ ] API-Key Verschlüsselung
- [ ] Admin Settings-Seite
- [ ] Unit Tests
- [ ] WP-CLI Commands

## Testen

### Local Development Setup

```bash
# WordPress lokal starten (z.B. mit Docker)
docker-compose up

# Plugin aktivieren
wp plugin activate auto-quill

# Manuell testen
wp auto-quill fetch-feeds
wp auto-quill select-topics

# API testen
curl -X GET http://localhost/wp-json/auto-quill/v1/topics
```

### Test-Szenarien

1. **RSS-Fetch Test**
   - Neue RSS-Quelle hinzufügen
   - Manuell trigger: `wp auto-quill fetch-feeds`
   - Überprüfe wp_auto_quill_articles

2. **KI-Integration Test**
   - OpenAI API-Key einrichten
   - Trigger: `wp auto-quill select-topics`
   - Überprüfe wp_auto_quill_topics

3. **Post-Generierung Test**
   - Topic wählen im Admin
   - Blog-Post generieren
   - Vorschau überprüfen
   - Veröffentlichen

4. **REST API Test**
   - GET /topics Endpoint testen
   - POST /generate-post testen
   - POST /publish-post testen

---

**Last Updated:** 2024
