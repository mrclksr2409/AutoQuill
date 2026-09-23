# Installation

## Voraussetzungen

| Komponente | Mindestens | Hinweis |
|---|---|---|
| WordPress | 5.9 | |
| PHP | 8.0 | |
| HTTPS-Verbindungen nach außen | – | Der Server muss `api.openai.com` bzw. `api.anthropic.com`, die RSS-Feeds und optional `pixabay.com` erreichen |
| KI-API-Schlüssel | – | OpenAI **oder** Anthropic (Claude), kostenpflichtig beim Anbieter |
| Pixabay-API-Schlüssel | – | Optional, kostenlos, nur für die Beitragsbild-Suche |
| Funktionierender WP-Cron | – | Siehe [Zeitplan und Cron](Zeitplan-und-Cron) |

## Plugin installieren

### Variante A: ZIP über den WordPress-Admin

1. Unter [Releases](https://github.com/mrclksr2409/autoquill/releases) beim neuesten Release
   **Source code (zip)** herunterladen.
2. Die ZIP entpacken und den enthaltenen Ordner (z. B. `autoquill-1.5.0`) in **`auto-quill`**
   umbenennen, dann wieder als `auto-quill.zip` packen.
3. In WordPress **Plugins → Installieren → Plugin hochladen** wählen, die ZIP-Datei auswählen,
   **Jetzt installieren**, danach **Aktivieren**.

### Variante B: Manuell per FTP/SSH

1. Den Plugin-Ordner nach `wp-content/plugins/auto-quill/` kopieren.
2. Unter **Plugins** auf **Aktivieren** klicken.

> Der Ordnername sollte `auto-quill` sein, damit die automatischen Updates ihn wiedererkennen.

## Was beim Aktivieren passiert

- Vier Datenbanktabellen werden angelegt (siehe [Datenbank](Datenbank)).
- Die Standard-Einstellungen werden gespeichert, sofern noch keine existieren.
- RSS-Abruf (00:00) und Themenauswahl (01:00) werden eingeplant, außerdem die tägliche Sicherung (03:00).
- Der Tagesbericht ist **aus**, bis du ihn einschaltest.

Im WordPress-Menü erscheint der Eintrag **AutoQuill** mit den Unterseiten *Übersicht*,
*RSS Quellen*, *Einstellungen* und *Logs*. Alle Seiten erfordern Administratorrechte
(`manage_options`).

## API-Schlüssel sicher hinterlegen (empfohlen)

Statt im Einstellungsformular können die Schlüssel in der `wp-config.php` stehen. Sie haben dann
Vorrang vor dem Formularfeld und landen nie in der Datenbank:

```php
define('AUTO_QUILL_AI_KEY', 'sk-...');          // OpenAI- oder Anthropic-Schlüssel
define('AUTO_QUILL_PIXABAY_KEY', '12345678-...'); // optional
```

Das Feld in den Einstellungen wird dann ausgegraut und zeigt einen entsprechenden Hinweis.

## Deaktivieren und Deinstallieren

| Aktion | Folge |
|---|---|
| **Deaktivieren** | Alle geplanten Aufgaben (Abruf, Auswahl, Bericht, Backup) werden entfernt. Daten und Einstellungen bleiben erhalten. |
| **Löschen** | Entfernt alle vier Tabellen, die Einstellungen, alle Sicherungen, die zwischengespeicherten Modelllisten und die AutoQuill-Post-Meta. **Die erzeugten Beiträge selbst bleiben erhalten.** |

> Vor dem Löschen lohnt sich ein Download der letzten Sicherung – siehe
> [Backup und Wiederherstellung](Backup-und-Wiederherstellung).

Weiter mit dem **[Schnellstart](Schnellstart)**.
