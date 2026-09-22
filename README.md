# AutoQuill - WordPress RSS zu Blog-Post KI-Plugin

Ein intelligentes WordPress-Plugin, das automatisch RSS-Feeds überwacht, täglich die interessantesten Themen selektiert und mithilfe von KI professionelle Blog-Posts generiert.

## Features

✅ **RSS-Feed Management** - Verwalte mehrere RSS-Quellen  
✅ **AI-Themenauswahl mit Rating** - Top-Themen des Tages, von der KI mit 0–100 bewertet und absteigend sortiert  
✅ **Feed-Übersicht** - Alle gecrawlten Einträge durchsuchbar, filterbar und paginiert im Dashboard  
✅ **Artikel ↔ Post-Verknüpfung** - Jeder erzeugte Beitrag bleibt dauerhaft mit seinem Feed-Eintrag verbunden  
✅ **Quellenangabe** - Jeder Blog-Beitrag endet mit einem garantierten Link auf den Originalartikel  
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
   - Du siehst die Top-Themen im Dashboard, jeweils mit Bewertung (0–100) und kurzer Begründung
   - Alternativ wechselst du auf den Tab *Alle Feed-Einträge* und wählst einen beliebigen gecrawlten Artikel
   - Klickst auf einen Button, um einen Blog-Post zu generieren
   - Die KI schreibt einen vollständigen, originalen Post (~800-1200 Wörter, über den Prompt einstellbar)
   - Am Ende des Beitrags wird automatisch ein Link auf den Originalartikel gesetzt

4. **Veröffentlichung**
   - Vorschau des generierten Posts
   - Klicke "Veröffentlichen" → Post wird als Entwurf oder direkt veröffentlicht

### Admin-Seiten

- **Startseite**: Zwei Tabs — *Top-Themen* (bewertet, absteigend sortiert) und *Alle Feed-Einträge*
  (Tabelle mit Quellen-Filter, Suche, „nur ohne Blog-Post" und Paginierung). Aus beiden Tabs lässt sich
  direkt ein Blog-Post erzeugen; die Vorschau rechts bleibt beim Tab-Wechsel erhalten.
- **RSS Quellen**: Feed-Verwaltung (hinzufügen/löschen)
- **Einstellungen**: KI-Provider, API-Keys, Veröffentlichung, Quellenangabe, Prompts, Updates, Debug

## Konfiguration

### Einstellungen im Admin-Panel

| Option | Tab | Beschreibung | Standard |
|--------|-----|-------------|----------|
| **KI-Provider** | KI-Provider | OpenAI oder Claude | OpenAI |
| **API-Schlüssel** | KI-Provider | Dein API-Schlüssel | - |
| **Pixabay-API-Key** | KI-Provider | Optional, für die Beitragsbild-Suche | - |
| **Post-Status** | Veröffentlichung | draft, publish, pending | draft |
| **Auto Publish** | Veröffentlichung | Posts automatisch veröffentlichen | Nein |
| **RSS-Rückblick (Tage)** | Veröffentlichung | Zeitfenster für Feed-Artikel, 0 = unbegrenzt | 7 |
| **Link zum Originalartikel** | Veröffentlichung | Quellenhinweis an jeden Beitrag anhängen | An |
| **Text des Quellenhinweises** | Veröffentlichung | Reiner Text mit Platzhaltern | `Quelle: {source_link}` |
| **Prompts** | Prompts | Vorgaben für Titel, Beitragstext, Auszug, Kategorie | siehe Tab |
| **Beta-Modus** | Updates | Updates vom `main`-Branch statt nur aus Releases | Aus |
| **Debug-Logging** | Debug | Info-/Debug-Einträge mitschreiben | Aus |

#### Platzhalter im Quellenhinweis

Der Text ist **reines Plaintext** — HTML wird beim Speichern entfernt, das Markup liefert der Platzhalter:

| Platzhalter | Inhalt |
|---|---|
| `{source_link}` | Fertiger Link auf den Artikeltitel (`rel="nofollow noopener"`, `target="_blank"`) |
| `{article_title}` | Titel des Originalartikels |
| `{source_url}` | URL des Originalartikels |
| `{feed_name}` | Name der RSS-Quelle |

Beispiel: `Mehr dazu bei {feed_name}: {source_link}`

#### Beitragslänge

Die Länge wird **nicht** über ein eigenes Feld gesteuert, sondern im Tab *Prompts* unter
„Prompt: Beitragstext" (Standard: `800-1200 Wörter`). AutoQuill liest die dort genannte Wortzahl
aus und leitet daraus das Token-Budget der KI-Anfrage ab, damit längere Vorgaben nicht an einem
Abschneide-Fehler scheitern. Der Filter `auto_quill_max_tokens` erlaubt eine manuelle Korrektur —
nötig etwa bei Modellen, deren Ausgabelimit unter dem von `gpt-4o-mini` liegt.

### Datenbank-Tabellen

Das Plugin erstellt 4 Tabellen:

- `wp_auto_quill_sources` - RSS-Quellen
- `wp_auto_quill_articles` - Gecrawlte Artikel (inkl. `post_id` für die Verknüpfung, seit DB-Version 1.4)
- `wp_auto_quill_topics` - Tägliche Themen-Auswahl
- `wp_auto_quill_logs` - Diagnose-Logs

Die Plugin-Einstellungen werden in der `wp_options`-Tabelle unter dem Key `auto_quill_settings` gespeichert.

**Verknüpfung Artikel ↔ Post.** Ein erzeugter Beitrag wird in beide Richtungen festgehalten:
`wp_auto_quill_articles.post_id` zeigt auf den Beitrag, und der Beitrag trägt die Provenienz als
geschützte Post-Meta (`_auto_quill_source_url`, `_auto_quill_article_id`, `_auto_quill_article_title`,
`_auto_quill_feed_name`). Die Post-Meta ist die dauerhafte Seite: Artikel-Zeilen werden nach Ablauf
des RSS-Rückblicks aufgeräumt, der Beitrag bleibt. Umgekehrt sind verknüpfte Artikel von diesem
Aufräumen **ausgenommen** — sonst würden sie beim nächsten Abruf als neu eingestuft und ein zweites
Mal zur Generierung angeboten. Auf dem Post-Bearbeiten-Screen zeigt die Box „AutoQuill-Quelle" den
Weg zurück zum Originalartikel.

## API-Integration

### REST Endpoints

Alle Endpoints erfordern die Capability `manage_options`.

```
GET  /wp-json/auto-quill/v1/topics
GET  /wp-json/auto-quill/v1/articles          # page, per_page (max 100), source_id, search, linked
POST /wp-json/auto-quill/v1/generate-post     # {article_id} ODER {topic_id, topic_index}
POST /wp-json/auto-quill/v1/publish-post
GET  /wp-json/auto-quill/v1/search-images
POST /wp-json/auto-quill/v1/suggest-image-keywords
GET  /wp-json/auto-quill/v1/logs
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

## Hooks & Filter

### Actions

| Hook | Parameter | Beschreibung |
|---|---|---|
| `auto_quill_daily_fetch` | – | Startet den RSS-Abruf |
| `auto_quill_daily_select` | – | Startet die Themen-Selektion |
| `auto_quill_topics_selected` | `$topics` | Läuft nach der Themen-Selektion |

### Filter

| Hook | Parameter | Beschreibung |
|---|---|---|
| `auto_quill_max_tokens` | `$tokens, $words, $body_prompt` | Token-Budget der Blog-Post-Generierung überschreiben |

## Updates

AutoQuill nutzt den [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker)
und bezieht Updates aus GitHub Releases. WordPress prüft automatisch und zeigt neue Versionen unter
**Dashboard → Aktualisierungen**. Mit aktivem Beta-Modus (Einstellungen → Updates) folgt das Plugin
stattdessen dem `main`-Branch.

## Changelog

### [1.2.0] — 2026-09-22

#### Added
- Bewertung der Tagesthemen: Die KI vergibt pro Thema einen Score von 0–100 mit kurzer Begründung;
  das Dashboard sortiert absteigend und zeigt ein farbiges Badge.
- Dashboard-Tab **Alle Feed-Einträge**: paginierte Tabelle aller gecrawlten Artikel mit
  Quellen-Filter, Volltextsuche und Filter „nur ohne Blog-Post".
- Blog-Posts lassen sich jetzt aus jedem beliebigen Feed-Eintrag erzeugen, nicht mehr nur aus den
  fünf Tagesthemen.
- Dauerhafte Verknüpfung zwischen Feed-Eintrag und erzeugtem Beitrag, sichtbar in beide Richtungen
  (Spalte „Blog-Post" in der Feed-Liste, Meta-Box „AutoQuill-Quelle" im Post-Editor).
- Garantierter Quellenhinweis am Ende jedes generierten Beitrags, abschaltbar und mit frei
  konfigurierbarem Text.
- Neuer REST-Endpoint `GET /wp-json/auto-quill/v1/articles`.
- Neuer Filter `auto_quill_max_tokens`.

#### Changed
- Das Token-Budget der Blog-Post-Generierung wird aus der im Body-Prompt genannten Wortzahl
  abgeleitet statt fest auf 6500 zu stehen; bei einer abgeschnittenen Antwort wird einmal mit
  größerem Budget wiederholt. Für den Standard-Prompt bleibt das Verhalten unverändert.
- Das Dashboard sendet beim Generieren den exakten Themen-Index statt eines Titel-Vergleichs.
  Gleichnamige Themen oder abweichende Leerzeichen führen nicht mehr zu einem Fehlschlag.
- Die Themen-Selektion bekommt ein größeres Token-Budget (2500 statt 1500), da Bewertung und
  Begründung zusätzlichen Platz brauchen.
- DB-Version 1.4: neue Spalte `post_id` in `wp_auto_quill_articles`.

#### Fixed
- Artikel, aus denen bereits ein Beitrag entstanden ist, werden vom Aufräum-Job nicht mehr gelöscht.
  Zuvor wurden sie beim nächsten Abruf als neu eingestuft und erneut zur Generierung angeboten, was
  zu doppelten Beiträgen führte.
- Feed-Einträge ohne verwertbares Datum wurden beim nächsten Abruf sofort wieder gelöscht; das
  Aufräumen fällt jetzt auf den Abrufzeitpunkt zurück.
- Ein fehlgeschlagenes Datenbank-Update setzt die gespeicherte DB-Version nicht mehr trotzdem hoch,
  wodurch die Migration dauerhaft übersprungen wurde.
- Ein abgelaufener REST-Nonce zeigt jetzt eine verständliche Meldung statt eines generischen Fehlers.

### [1.1.0]

- Pixabay-Bildauswahl für Beitragsbilder, Logs-Seite, Prompt-Templates.

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