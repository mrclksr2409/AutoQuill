# Fehlerbehebung

Erster Schritt fast immer: **AutoQuill → Logs**, Level *Error*/*Warning*, und bei einem Eintrag
**Context anzeigen**. Wie man das Log liest: [Logs und Debugging](Logs-und-Debugging).

---

## KI & API

### „API Key nicht konfiguriert“
Kein Schlüssel gespeichert. **Einstellungen → KI-Provider → API-Schlüssel** eintragen und speichern
(oder `AUTO_QUILL_AI_KEY` in der `wp-config.php` setzen).

### „Der API-Schlüssel wurde abgelehnt“ / HTTP 401
- Schlüssel und **Provider passen nicht zusammen** (OpenAI-Schlüssel beginnen mit `sk-`,
  Anthropic-Schlüssel mit `sk-ant-`).
- Schlüssel wurde beim Anbieter widerrufen oder hat einen Tippfehler.

### HTTP 429 – Rate Limit / Quota
Zu viele Anfragen oder **kein Guthaben** beim Anbieter. Im Dashboard des Anbieters Guthaben und
Limits prüfen. Kurz warten und erneut versuchen.

### HTTP 400 / 404 bei der Generierung
Meist ein **Modellproblem**: Das Modell existiert nicht (mehr) oder akzeptiert die Parameter nicht.
Unter **KI-Provider** auf **Modelle neu laden** klicken und ein gelistetes Modell wählen. Steht
beim Modell „aktuell gespeichert, nicht in der Liste“, ist es beim Anbieter nicht mehr verfügbar.

### „Antwort wurde abgeschnitten – bitte max_tokens erhöhen“
Der Beitrag war länger als das Antwort-Budget. AutoQuill hat bereits einmal mit mehr Budget
wiederholt. Lösungen:
- Wortzahl im [Prompt Beitragstext](Prompts) senken
- Bei Reasoning-Modellen (o-Serie, `gpt-5*`) ein klassisches Chat-Modell wählen
- Budget über den [Filter `auto_quill_max_tokens`](Hooks-und-Filter) erhöhen

### Modellliste lädt nicht
- Schlüssel prüfen (siehe 401 oben)
- Server erreicht `api.openai.com` / `api.anthropic.com` nicht (Firewall des Hosters)
- Das gespeicherte Modell bleibt trotzdem nutzbar

---

## Generierung

### Generierung bricht nach ~30 oder ~60 Sekunden ab / „Die Anfrage hat zu lange gedauert“
Die KI braucht für lange Beiträge bis zu 90 Sekunden. Häufig beendet vorher etwas anderes die Anfrage:
- **PHP `max_execution_time`** – mindestens 120 setzen
- **Webserver-/Proxy-Timeout** (nginx `fastcgi_read_timeout`, Cloudflare 100 s, Load Balancer)
- Abhilfe auch: schnelleres Modell oder kürzere Beiträge

### „Die Sitzung ist abgelaufen“
Die Seite war lange offen und der Sicherheits-Nonce ist abgelaufen. Seite neu laden.

### Beitrag ist dünn oder ungenau
Auf der Generierungs-Seite links den **Quelltext für die KI** ansehen:
- Steht dort nur Titel und Beschreibung, konnte die Artikelseite nicht geladen werden (Paywall,
  Bot-Schutz, JavaScript-Seite). Das Log (`fetcher`) zeigt „Artikel-Content nicht 200 OK“.
- Ist der Text abgeschnitten, sieht die KI nur die ersten 8 000 Zeichen – oft Navigation und
  Cookie-Banner statt Artikel.
- Andere Quelle mit vollständigen, frei zugänglichen Artikeln wählen.

### „KI-Antwort (combined) nicht parsebar“ im Log
Das Modell hat nicht im geforderten JSON-Format geantwortet; AutoQuill fragt einmal automatisch nach
(Warnung). Scheitert auch das, folgt „… konnte auch im Retry nicht geparst werden“ (Fehler).
Passiert es häufig: Prompts auf eigene Format-Anweisungen prüfen („antworte mit …“) und diese
entfernen – das Format ist fest eingebaut.

### Kategorien fehlen oder passen nicht
Die KI wählt nur aus **vorhandenen** WordPress-Kategorien. Sinnvolle Kategorien anlegen,
[Prompt Kategorie](Prompts) schärfen oder vor dem Speichern von Hand korrigieren.

---

## Themen & Feeds

### Keine Themen im Dashboard
„Noch keine Themen für heute verfügbar“ hat meist einen dieser Gründe:

| Ursache | Erkennbar an | Lösung |
|---|---|---|
| Themenauswahl heute noch nicht gelaufen | Status-Panel: nächster Termin liegt in der Zukunft oder Vergangenheit | warten bzw. [Cron prüfen](Zeitplan-und-Cron#prüfen-ob-cron-läuft) |
| Keine **neuen** Artikel in den letzten 24 h | Log `selector`: „Keine neuen Artikel zum Analysieren gefunden“ | Feeds prüfen, mehr Quellen, Rückblick erhöhen |
| Auswahl liegt vor dem Abruf | Warnung beim Speichern | Uhrzeiten anpassen |
| KI-Fehler | Log `selector` / `client` | siehe *KI & API* oben |

Sofort-Lösung: **Übersicht → Feeds neu holen + Topics neu wählen**.

### Ein Feed liefert nichts
- Feed-URL im Browser öffnen – es muss XML erscheinen.
- Log `fetcher`: „SimplePie-Fehler beim Fetchen“ enthält den Grund (ungültiges XML, Timeout, 403).
- Alle Einträge älter als der RSS-Rückblick? Log-Kontext zeigt `too_old`.
- Alles schon bekannt? `duplicates` im Log-Kontext.

### Artikelseiten werden nicht geladen (403 / 429)
Manche Seiten blockieren Server-Abrufe. Der Feed-Eintrag wird trotzdem gespeichert, die KI hat dann
aber nur den Anreißer. Keine Umgehung vorgesehen – ggf. andere Quelle wählen.

---

## Bilder

### Bildsuche fehlt oder meldet einen Fehler
- **Einstellungen → Bilder**: Pixabay-Schlüssel hinterlegt?
- Log `pixabay` für den HTTP-Status prüfen.

### Beitrag angelegt, aber ohne Beitragsbild
Das Herunterladen in die Mediathek ist gescheitert. Log `posts` („Featured-Image-Sideload fehlgeschlagen“) zeigt den Grund – meist fehlende
Schreibrechte in `wp-content/uploads` oder blockierte ausgehende Verbindung. Der Beitrag selbst ist
trotzdem gespeichert; das Bild lässt sich im Editor nachtragen.

---

## Zeitplan, Mail, Backup

### Abruf/Auswahl läuft nicht oder zu spät
WP-Cron läuft nur bei Seitenbesuchen. Siehe [Zeitplan und Cron](Zeitplan-und-Cron) – dort steht, wie
man einen echten Server-Cron einrichtet.

Für lokale Testumgebungen ohne Loopback-Verbindung hilft oft:
```php
define('ALTERNATE_WP_CRON', true);
```

### Tagesbericht kommt nicht an
Siehe [Tagesbericht → Die Mail kommt nicht an](Tagesbericht#die-mail-kommt-nicht-an).

### Import: „keine gültige AutoQuill-Sicherung“
Nur Dateien aus **Herunterladen** im Backup-Tab werden akzeptiert (JSON mit `"plugin": "auto-quill"`,
max. 1 MB). Datei nicht von Hand in einem Editor mit anderer Kodierung speichern.

### Nach Wiederherstellung fehlen API-Schlüssel
Importierte Sicherungen enthalten bewusst keine Schlüssel; die bisherigen bleiben erhalten. War auf
der Seite noch keiner hinterlegt, jetzt unter **KI-Provider** / **Bilder** eintragen.

---

## Admin

### „Zugriff verweigert“
Alle AutoQuill-Seiten erfordern Administratorrechte (`manage_options`).

### Status-Panel zeigt die DB-Version rot
Eine Datenbank-Migration steht aus oder ist fehlgeschlagen. Eine Admin-Seite neu laden (die Migration
wird wiederholt) und im Log (`db.schema`) nach dem Grund suchen – meist fehlende `ALTER`-Rechte des
Datenbankbenutzers.

---

Nicht dabei? Ein [Issue](https://github.com/mrclksr2409/autoquill/issues) eröffnen – mit
WordPress- und PHP-Version, AutoQuill-Version, Provider/Modell und dem Log-Export.
