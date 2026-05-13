# AutoQuill - WordPress RSS zu Blog-Post KI-Plugin

Ein intelligentes WordPress-Plugin, das automatisch RSS-Feeds überwacht, täglich die interessantesten Themen selektiert und mithilfe von KI professionelle Blog-Posts generiert.

## Features

✅ **RSS-Feed Management** - Verwalte mehrere RSS-Quellen  
✅ **AI-Themenauswahl** - Automatische Auswahl der Top-Themen des Tages  
✅ **KI-Text-Generierung** - Generiert vollständige, professionelle Blog-Posts  
✅ **Admin Dashboard** - Benutzerfreundliches Interface zur Verwaltung  
✅ **WordPress Cron** - Automatische tägliche Updates  
✅ **REST API** - Volle API-Integration  
✅ **OpenAI & Claude Support** - Flexible KI-Provider  
✅ **Sichere Konfiguration** - Sichere Speicherung von API-Keys  

## Installation

1. **Plugin-Datei hochladen**
   - Kopiere den `AutoQuill`-Ordner in `/wp-content/plugins/`
   - Oder: Komprimiere den Ordner zu `auto-quill.zip` und laden über WordPress Admin hoch

2. **Plugin aktivieren**
   - Gehe zu **Plugins** im WordPress-Admin
   - Klicke auf **Aktivieren** neben "AutoQuill"

3. **Konfigurieren**
   - Gehe zu **AutoQuill → Einstellungen**
   - Gib deine OpenAI API-Key (oder Claude) ein
   - Speichere die Einstellungen

4. **RSS-Quellen hinzufügen**
   - Gehe zu **AutoQuill → RSS Quellen**
   - Füge neue RSS-Feeds hinzu (z.B. von News-Websites)

## Verwendung

### Workflow

1. **Automatisches Sammeln** (täglich um Mitternacht)
   - Plugin holt alle Artikel aus den konfigurierten RSS-Feeds
   - Speichert neue, nicht-doppelte Artikel in der Datenbank

2. **KI-Analyse** (1 Stunde nach dem Fetch)
   - OpenAI/Claude analysiert alle Artikel des Tages
   - Wählt die 5 interessantesten Themen aus
   - Speichert die Auswahl im Admin-Dashboard

3. **Manuelle Auswahl** (Benutzer im Admin)
   - Du siehst die Top-Themen im Dashboard
   - Klickst auf einen Button, um einen Blog-Post zu generieren
   - Die KI schreibt einen vollständigen, originalen Post (~800-1200 Wörter)

4. **Veröffentlichung**
   - Vorschau des generierten Posts
   - Klicke "Veröffentlichen" → Post wird als Entwurf oder direkt veröffentlicht

### Admin-Seiten

- **Startseite**: Heute's Top-Themen + Post-Generierung
- **RSS Quellen**: Feed-Verwaltung (hinzufügen/löschen)
- **Einstellungen**: KI-Provider, API-Keys, Post-Status

## Konfiguration

### Einstellungen im Admin-Panel

| Option | Beschreibung | Standard |
|--------|-------------|----------|
| **KI-Provider** | OpenAI oder Claude | OpenAI |
| **API-Schlüssel** | Dein API-Schlüssel | - |
| **Post-Status** | draft, publish, pending | draft |
| **Auto Publish** | Posts automatisch veröffentlichen | Nein |

### Datenbank-Tabellen

Das Plugin erstellt 4 neue Tabellen:

- `wp_auto_quill_sources` - RSS-Quellen
- `wp_auto_quill_articles` - Gecrawlte Artikel
- `wp_auto_quill_topics` - Tägliche Themen-Auswahl
- `wp_auto_quill_settings` - Plugin-Einstellungen

## API-Integration

### REST Endpoints

```
GET  /wp-json/auto-quill/v1/topics
POST /wp-json/auto-quill/v1/generate-post
POST /wp-json/auto-quill/v1/publish-post
```

### Beispiel-API-Aufruf

```bash
# Heute's Themen abrufen
curl -X GET http://example.com/wp-json/auto-quill/v1/topics \
  -H "Authorization: Bearer YOUR_TOKEN"

# Blog-Post generieren
curl -X POST http://example.com/wp-json/auto-quill/v1/generate-post \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"topic_id": 1, "title": "Interessantes Thema"}'
```

## Anforderungen

- **PHP**: 8.0+
- **WordPress**: 5.9+
- **SSL/TLS**: Für sichere API-Anfragen
- **API-Key**: OpenAI oder Claude API-Key

## FAQ

**F: Welche RSS-Feeds sollte ich hinzufügen?**  
A: Alle relevanten Quellen für deine Nische (Tech-News, Business, Lifestyle, etc.)

**F: Kann ich mehrere KI-Provider nutzen?**  
A: Derzeit unterstützt das Plugin einen Provider pro Installation. Ein Provider wird in den Einstellungen ausgewählt.

**F: Wie oft werden Posts generiert?**  
A: Der Prozess läuft täglich ab. Ein Post pro Tag wird empfohlen (konfigurierbar).

**F: Sind die generierten Posts wirklich original?**  
A: Ja! Die KI schreibt neue, originale Posts basierend auf den Artikel-Zusammenfassungen.

**F: Was kostet das Plugin?**  
A: Das Plugin ist kostenlos. Du brauchst nur einen API-Key von OpenAI/Claude (bezahlpflichtig).

## Fehlerbehebung

### WordPress Cron funktioniert nicht

```php
// In wp-config.php hinzufügen für lokale Tests:
define('DISABLE_WP_CRON', false);
define('ALTERNATE_WP_CRON', true);
```

### API-Fehler

- Prüfe deinen API-Key in den Einstellungen
- Stelle sicher, dass dein Server HTTPS unterstützt
- Überprüfe die API-Quotas auf der OpenAI/Claude Website

### Keine neuen Artikel

- Überprüfe, ob RSS-Quellen in den Einstellungen aktiviert sind
- Teste die Feed-URL manuell in einem Browser
- Schau in WordPress-Logs nach Fehlern

## Lizenz

GPL v2 oder später. Siehe `LICENSE` für Details.

## Support

- Öffne einen Issue auf [GitHub](https://github.com/AutoQuill/AutoQuill)
- Dokumentation: [Wiki](https://github.com/AutoQuill/AutoQuill/wiki)

## Roadmap

- [ ] Mehrere KI-Provider gleichzeitig
- [ ] Social-Media-Sharing
- [ ] Custom Prompt-Templates
- [ ] Admin-Benachrichtigungen per Email
- [ ] Artikel-Kategorisierung
- [ ] Multi-Language-Support

---

**Entwickelt mit ❤️ für Content-Creator**