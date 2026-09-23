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
✅ **WordPress Cron** - Automatische tägliche Updates zu frei wählbaren Uhrzeiten  
✅ **REST API** - Volle API-Integration  
✅ **OpenAI & Claude Support** - Flexible KI-Provider, Modellauswahl per Dropdown direkt vom Anbieter  
✅ **Sichere Konfiguration** - Sichere Speicherung von API-Keys  
✅ **Tagesbericht per E-Mail** - Einmal täglich, mit einstellbaren Empfängern und Uhrzeit  
✅ **Backup** - Tägliche Sicherung von Einstellungen und RSS-Quellen, Wiederherstellen, Download und Import  

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

1. **Automatisches Sammeln** (täglich, Standard 00:00 — einstellbar unter *Einstellungen → Feeds & Zeitplan*)
   - Plugin holt alle Artikel aus den konfigurierten RSS-Feeds
   - Speichert neue, nicht-doppelte Artikel in der Datenbank

2. **KI-Analyse** (täglich, Standard 01:00 — einstellbar unter *Einstellungen → Feeds & Zeitplan*)
   - OpenAI/Claude analysiert alle Artikel des Tages
   - Wählt die 5 interessantesten Themen aus
   - Speichert die Auswahl im Admin-Dashboard

3. **Manuelle Auswahl** (Benutzer im Admin)
   - Du siehst die Top-Themen im Dashboard, jeweils mit Bewertung (0–100) und kurzer Begründung
   - Alternativ wechselst du auf den Tab *Alle Feed-Einträge* und wählst einen beliebigen gecrawlten Artikel
   - Ein Klick auf „Blog-Post generieren" öffnet die Generierungs-Seite und startet die KI
   - Die KI schreibt einen vollständigen, originalen Post (~800-1200 Wörter, über den Prompt einstellbar)
   - Am Ende des Beitrags wird automatisch ein Link auf den Originalartikel gesetzt

4. **Gegenprüfen** (auf der Generierungs-Seite)
   - Links steht der Originaltext, umschaltbar zwischen *Quelltext für die KI* (exakt das, was das
     Modell bekommen hat) und *Roh-HTML* (der gespeicherte Seitenquelltext)
   - So lässt sich Satz für Satz prüfen, ob im Beitrag nur steht, was auch in der Quelle steht

5. **Veröffentlichung**
   - Vorschau des generierten Posts
   - Klicke "Veröffentlichen" → Post wird als Entwurf oder direkt veröffentlicht

### Admin-Seiten

- **Startseite**: Die Übersicht über die volle Breite, mit zwei Tabs — *Top-Themen* (bewertet,
  absteigend sortiert) und *Alle Feed-Einträge* (Tabelle mit Quellen-Filter, Suche,
  „nur ohne Blog-Post" und Paginierung). Aus beiden Tabs führt ein Klick auf
  „Blog-Post generieren" zur Generierungs-Seite.
- **Blog-Post erstellen**: Eigene Ansicht ohne Menüeintrag
  (`admin.php?page=auto-quill&aq_view=generate`), nur über die Übersicht erreichbar.
  Links steht der Originaltext, rechts entsteht der Beitrag. Während die KI arbeitet, läuft ein
  Spinner mit Sekundenzähler.
- **RSS Quellen**: Feed-Verwaltung (hinzufügen/löschen)
- **Einstellungen**: KI-Provider, API-Keys, Modellauswahl, Veröffentlichung, Zeitplan, Quellenangabe, Prompts,
  Benachrichtigungen, Backup, Updates, Debug

## Konfiguration

### Einstellungen im Admin-Panel

| Option | Tab | Beschreibung | Standard |
|--------|-----|-------------|----------|
| **KI-Provider** | KI-Provider | OpenAI oder Claude | OpenAI |
| **API-Schlüssel** | KI-Provider | Dein API-Schlüssel | - |
| **OpenAI-/Claude-Modell** | KI-Provider | Dropdown; die Liste wird mit dem API-Schlüssel beim Anbieter abgerufen (12 h zwischengespeichert, „Modelle neu laden" erzwingt einen Abruf) | `gpt-4o-mini` / `claude-sonnet-4-6` |
| **RSS-Abruf** | Feeds & Zeitplan | Uhrzeit des täglichen Feed-Abrufs (Ortszeit) | 00:00 |
| **Themenauswahl** | Feeds & Zeitplan | Uhrzeit der täglichen KI-Themenauswahl (Ortszeit), sollte nach dem Abruf liegen | 01:00 |
| **RSS-Rückblick (Tage)** | Feeds & Zeitplan | Zeitfenster für Feed-Artikel, 0 = unbegrenzt | 7 |
| **Prompts** | Prompts | Vorgaben für Titel, Beitragstext, Auszug, Kategorie | siehe Tab |
| **Post-Status** | Veröffentlichung | draft, publish, pending | draft |
| **Automatisch veröffentlichen** | Veröffentlichung | Jeden Beitrag sofort veröffentlichen, überschreibt den Post-Status | Nein |
| **Link zum Originalartikel** | Veröffentlichung | Quellenhinweis an jeden Beitrag anhängen | An |
| **Text des Quellenhinweises** | Veröffentlichung | Reiner Text mit Platzhaltern | `Quelle: {source_link}` |
| **Pixabay-API-Key** | Bilder | Optional, für die Beitragsbild-Suche | - |
| **Tagesbericht** | Benachrichtigungen | Täglich eine Zusammenfassung per E-Mail | Aus |
| **Uhrzeit** | Benachrichtigungen | Wann der Bericht verschickt wird (Ortszeit) | 08:00 |
| **Inhalte** | Benachrichtigungen | Top-Themen, Fehler und Warnungen, erstellte Posts | alle drei |
| **Empfänger: Benutzer** | Benachrichtigungen | Auswahl aus den Administratoren | – |
| **Weitere Adressen** | Benachrichtigungen | Zusätzliche Adressen, eine pro Zeile | – |
| **Automatische Sicherung** | Backup | Einstellungen und RSS-Quellen täglich sichern | An |
| **Uhrzeit** | Backup | Wann die Sicherung läuft (Ortszeit) | 03:00 |
| **Aufbewahren** | Backup | Anzahl aufgehobener Sicherungen (1–100), ältere werden gelöscht | 7 |
| **Beta-Modus** | System | Updates vom `main`-Branch statt nur aus Releases | Aus |
| **Debug-Logging** | System | Info-/Debug-Einträge mitschreiben; darunter das Status-Panel | Aus |

#### Tagesbericht

Der Bericht fasst zusammen, was seit der letzten Mail passiert ist. **Gibt es nichts zu berichten,
wird auch nichts verschickt.** Wiederholte Fehlermeldungen werden gebündelt („13× OpenAI API-Fehler")
statt einzeln aufgelistet.

Zwei Dinge sind gut zu wissen:

- Die Empfängerauswahl speichert **Benutzer-IDs**, nicht Adressen — eine geänderte Mailadresse wirkt
  also sofort, und gelöschte oder degradierte Benutzer fallen automatisch heraus. Für Verteiler ohne
  WordPress-Konto ist das Freitextfeld da.
- Der Fehler-Abschnitt kann nur berichten, was noch im Log steht. Das Log hält maximal 7 Tage bzw.
  500 Einträge und räumt **älteste zuerst** ab, unabhängig vom Level — bei aktivem Debug-Logging
  kann das die Fehlerhistorie verdrängen. Der Bericht weist darauf hin, wenn er abgeschnitten wurde.

Ob die Mail tatsächlich ankommt, hängt an der Mail-Konfiguration der Seite. Der Knopf
**„Test-Mail an alle Empfänger senden"** im selben Tab prüft das sofort, statt bis zum nächsten
Morgen zu warten.

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
POST /wp-json/auto-quill/v1/models            # {provider, api_key?, refresh?} → verfügbare Modelle
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
| `auto_quill_daily_digest` | – | Versendet den Tagesbericht |

### Filter

| Hook | Parameter | Beschreibung |
|---|---|---|
| `auto_quill_max_tokens` | `$tokens, $words, $body_prompt` | Token-Budget der Blog-Post-Generierung überschreiben |

## Updates

AutoQuill nutzt den [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker)
und bezieht Updates aus GitHub Releases. WordPress prüft automatisch und zeigt neue Versionen unter
**Dashboard → Aktualisierungen**. Mit aktivem Beta-Modus (Einstellungen → System) folgt das Plugin
stattdessen dem `main`-Branch.

## Changelog

### [1.5.0] — 2026-09-23

#### Added
- Die Modelle unter *Einstellungen → KI-Provider* werden per Dropdown gewählt. Die Liste wird
  direkt beim Anbieter abgerufen (OpenAI `GET /v1/models`, gefiltert auf Chat-Modelle; Anthropic
  `GET /v1/models`) und 12 Stunden zwischengespeichert. Ein frisch eingetippter, noch nicht
  gespeicherter Schlüssel wird für den Abruf bereits verwendet. Es wird nur das Modell des
  gewählten Providers angezeigt.
- Neuer Einstellungs-Tab „Feeds & Zeitplan": Uhrzeit für den RSS-Abruf und für die Themenauswahl frei
  wählbar (Ortszeit der Seite). Liegt die Auswahl nicht bis zu 12 Stunden nach dem Abruf, gibt es beim
  Speichern einen Hinweis.
- Neuer REST-Endpoint `POST /wp-json/auto-quill/v1/models`.
- Neuer Einstellungs-Tab „Backup": tägliche Sicherung aller Einstellungen und der RSS-Quellen zu
  einstellbarer Uhrzeit, Anzahl der aufbewahrten Sicherungen wählbar (Standard 7). Dazu „Jetzt
  sichern", Wiederherstellen (der Stand davor wird automatisch mitgesichert), Download als JSON
  (ohne API-Schlüssel) und Import. Artikel, Themen und Logs sind nicht Teil der Sicherung.
- Das Status-Panel zeigt die nächste Sicherung und maskiert jetzt auch den Pixabay-Schlüssel.

#### Changed
- Abruf und Themenauswahl laufen nicht mehr als `daily`-Events ab dem Aktivierungszeitpunkt,
  sondern als selbst verkettete Einzel-Events zur eingestellten Uhrzeit — robust gegen die
  Zeitumstellung. Bestehende Installationen werden beim ersten Seitenaufruf automatisch umgestellt.
- Einstellungsseite neu sortiert: *KI-Provider · Feeds & Zeitplan · Prompts · Veröffentlichung ·
  Bilder · Benachrichtigungen · Backup · System*. Der Pixabay-Schlüssel hat einen eigenen Tab „Bilder"
  statt unter „KI-Provider" zu stehen, der RSS-Rückblick wanderte von „Veröffentlichung" zu
  „Feeds & Zeitplan", „Updates" und „Debug" sind zu „System" zusammengefasst. Alte Links
  (`#tab-debug`, `#tab-updates`) führen weiterhin zum richtigen Tab, und nach dem Speichern bleibt
  der zuletzt geöffnete Tab aktiv.
- Der Cron-Abruf stößt die Themenauswahl nicht mehr zusätzlich an; sie lief dadurch zweimal täglich.
- OpenAI-Anfragen senden `max_completion_tokens` statt `max_tokens` und lassen `temperature` bei
  Reasoning-Modellen (o-Serie, gpt-5) weg — diese lehnen beides sonst ab.

### [1.4.0] — 2026-09-22

#### Added
- Tagesbericht per E-Mail mit neuem Einstellungs-Tab „Benachrichtigungen": Empfänger als Auswahl
  aus den Administratoren plus Freitextfeld für weitere Adressen, frei wählbare Uhrzeit und
  Auswahl der Inhalte (neue Top-Themen, Fehler und Warnungen, erstellte Blog-Posts).
- Knopf „Test-Mail an alle Empfänger senden", damit sich der Versand sofort prüfen lässt.
- Das Status-Panel zeigt die nächste Berichts-Laufzeit und die Zahl der gültigen Empfänger.
- Neue Post-Meta `_auto_quill_generated_at` als verlässlicher Marker für „von AutoQuill erstellt".
  **Nicht rückwirkend** — der erste Bericht kennt nur Beiträge, die nach diesem Update entstanden sind.

#### Changed
- `Logger::query()` versteht jetzt eine Level-Liste und eine Sortierrichtung; neu ist `Logger::count()`.

#### Fixed
- `Logger::cleanup()` bildete die Altersgrenze mit `gmdate()`, verglich sie aber gegen lokal
  geschriebene Zeitstempel — Einträge wurden um den UTC-Versatz der Seite zu spät gelöscht.

### [1.3.1] — 2026-09-22

#### Fixed
- Die Generierungs-Seite lehnte jeden Aufruf mit „Du bist leider nicht berechtigt, auf diese Seite
  zuzugreifen" ab — auch als Administrator. Sie war als Untermenü registriert und der Menüeintrag
  anschließend per `remove_submenu_page()` entfernt; WordPress ermittelt die Berechtigung aber über
  genau diese Menüliste und fand die Seite dadurch nicht mehr wieder. Die Generierung ist jetzt
  eine Ansicht der Dashboard-Seite und braucht weder eine eigene Registrierung noch das Entfernen
  eines Menüeintrags.

### [1.3.0] — 2026-09-22

#### Added
- Eigene Seite für die Post-Generierung. Links der Originaltext, rechts der entstehende Beitrag,
  darunter Titel, Auszug, Kategorien und Beitragsbild.
- Umschaltbare Quellansicht: *Quelltext für die KI* zeigt exakt den Text, den das Modell erhalten
  hat, *Roh-HTML* den gespeicherten Seitenquelltext. Beides als Text, damit nichts Fremdes im
  Backend ausgeführt oder nachgeladen wird.
- Sichtbarer Fortschritt während der Generierung: rotierender Ring, Statuszeile und Sekundenzähler,
  mit `prefers-reduced-motion`-Unterstützung.
- Hinweis, wenn zu einem Feed-Eintrag kein Seiteninhalt gespeichert wurde — das erklärt einen
  dünnen oder ungenauen Beitrag.
- Warnung beim Verlassen der Seite, solange ein generierter Beitrag noch nicht gespeichert ist.

#### Changed
- Die Übersicht nutzt die volle Bildschirmbreite; die Themen-Karten stehen nebeneinander.
- „Blog-Post generieren" ist jetzt ein echter Link statt eines JavaScript-Buttons — Mittelklick und
  „In neuem Tab öffnen" funktionieren.
- Nach dem Veröffentlichen lädt die Seite nicht mehr neu, sondern zeigt einen Kasten mit
  „Post bearbeiten" und „Zurück zur Übersicht". Ein Reload hätte die Generierung erneut ausgelöst.
- Die Generierungs-Anfrage bricht clientseitig nach 120 Sekunden mit eigener Meldung ab, und PHP
  bekommt für den Aufruf mehr Zeit (`set_time_limit`, soweit der Host das zulässt).
- Das Admin-JavaScript ist aufgeteilt: `admin.js` für die Übersicht, `generate.js` nur auf der
  Generierungs-Seite.

#### Fixed
- Der Ladeindikator war unsichtbar: Die zugehörige CSS-Animation enthielt ein fehlerhaftes
  Inline-SVG (unbalancierte Tags, konstante Animationswerte) und rendert in gängigen Browsern
  nichts. Ersetzt durch einen reinen CSS-Ring.
- Das Öffnen der Bildauswahl löste **zwei** KI-Aufrufe für die Suchbegriffe aus: Der Handler war
  sowohl delegiert als auch direkt an den Button gebunden. Der doppelte Bind stammte aus einem
  Diagnose-Commit und ist entfernt.

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
- [x] Admin-Benachrichtigungen per Email
- [ ] Artikel-Kategorisierung
- [ ] Multi-Language-Support

---

**Entwickelt mit ❤️ für Content-Creator**