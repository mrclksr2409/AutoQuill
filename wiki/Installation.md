# Installation

## Voraussetzungen

| Komponente | Mindestens | Hinweis |
|---|---|---|
| WordPress | 5.9 | |
| PHP | 8.0 | |
| HTTPS-Verbindungen nach außen | – | Der Server muss den KI-Anbieter (`api.openai.com`, `api.anthropic.com`, `openai.inference.de-txl.ionos.com` oder deinen eigenen Endpunkt), die RSS-Feeds samt Artikelseiten, optional `pixabay.com` und für Updates GitHub erreichen |
| KI-API-Schlüssel | – | OpenAI, Anthropic (Claude) oder IONOS, kostenpflichtig beim Anbieter. Beim eigenen OpenAI-kompatiblen Endpunkt (z. B. lokal mit Ollama) ist der Schlüssel optional |
| Pixabay-API-Schlüssel | – | Optional, kostenlos, nur für die Beitragsbild-Suche |
| Funktionierender WP-Cron | – | Siehe [Zeitplan und Cron](Zeitplan-und-Cron) |

Das Admin-Design (*WP-Backend UI*) und der *Plugin Update Checker* sind im Plugin enthalten
(`lib/`), es muss nichts zusätzlich installiert werden.

## Plugin installieren

### Variante A: ZIP über den WordPress-Admin

1. Im [Repository](https://github.com/mrclksr2409/AutoQuill) über **Code → Download ZIP** den
   aktuellen Stand des Branches `main` herunterladen.
2. Die ZIP entpacken und den enthaltenen Ordner (z. B. `AutoQuill-main`) in **`auto-quill`**
   umbenennen, dann wieder als `auto-quill.zip` packen.
3. In WordPress **Plugins → Installieren → Plugin hochladen** wählen, die ZIP-Datei auswählen,
   **Jetzt installieren**, danach **Aktivieren**.

### Variante B: Manuell per FTP/SSH

1. Den Plugin-Ordner nach `wp-content/plugins/auto-quill/` kopieren.
2. Unter **Plugins** auf **Aktivieren** klicken.

> Der Ordnername sollte `auto-quill` sein – das ist der Slug, unter dem die automatischen Updates
> das Plugin führen (siehe [Updates](Updates)).

## Was beim Aktivieren passiert

- Fünf Datenbanktabellen werden angelegt (siehe [Datenbank](Datenbank)).
- Die Standard-Einstellungen werden gespeichert, sofern noch keine existieren.
- RSS-Abruf (00:00) und Themenauswahl (01:00) werden eingeplant; die tägliche Sicherung (03:00)
  plant AutoQuill beim nächsten Seitenaufruf ein.
- Der Tagesbericht ist **aus**, bis du ihn einschaltest.

Im WordPress-Menü erscheint der Eintrag **AutoQuill** (Symbol: RSS) mit diesen Unterseiten:

| Menüeintrag | Seite |
|---|---|
| **AutoQuill** | Die [Übersicht](Dashboard) mit Top-Themen und allen Feed-Einträgen |
| **Interview** | Der [Interview-Modus](Interview) |
| **RSS Quellen** | [Feed-Verwaltung](RSS-Quellen) |
| **Einstellungen** | Alle [Einstellungen](Einstellungen) in neun Tabs |
| **Logs** | [Log-Seite](Logs-und-Debugging) |

Alle Seiten erfordern Administratorrechte (`manage_options`). In der Plugin-Liste steht neben
AutoQuill zusätzlich ein Link **Wiki**, der direkt zu dieser Dokumentation führt.

## API-Schlüssel sicher hinterlegen (empfohlen)

Statt im Einstellungsformular können die Schlüssel in der `wp-config.php` stehen. Sie haben dann
Vorrang vor dem Formularfeld und landen nie in der Datenbank:

```php
define('AUTO_QUILL_AI_KEY', 'sk-...');            // gilt für jeden KI-Provider
define('AUTO_QUILL_PIXABAY_KEY', '12345678-...'); // optional
```

Die Felder in den Einstellungen werden dann gesperrt und zeigen einen entsprechenden Hinweis.
`AUTO_QUILL_AI_KEY` gilt für **jeden** Provider – wer zwischen Providern wechselt, muss die
Konstante entsprechend anpassen.

## Deaktivieren und Deinstallieren

| Aktion | Folge |
|---|---|
| **Deaktivieren** | Alle geplanten Aufgaben (Abruf, Auswahl, Bericht, Backup) werden entfernt. Daten und Einstellungen bleiben erhalten. |
| **Löschen** | Entfernt alle fünf Tabellen, die Einstellungen, die DB-Version, alle Sicherungen, den Zeitpunkt des letzten Tagesberichts, die zwischengespeicherten Modelllisten und die AutoQuill-Post-Meta. **Die erzeugten Beiträge selbst bleiben erhalten.** |

> Vor dem Löschen lohnt sich ein Download der letzten Sicherung – siehe
> [Backup und Wiederherstellung](Backup-und-Wiederherstellung).

Weiter mit dem **[Schnellstart](Schnellstart)**.
