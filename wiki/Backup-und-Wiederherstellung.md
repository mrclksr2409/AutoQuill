# Backup und Wiederherstellung

AutoQuill sichert seine **Konfiguration** automatisch und lässt sich mit einem Klick auf einen
früheren Stand zurücksetzen. Alles unter **Einstellungen → Backup**.

## Was gesichert wird – und was nicht

| Gesichert | Nicht gesichert |
|---|---|
| Alle Einstellungen aller Tabs (inkl. Prompts, Uhrzeiten, Empfänger) | Gecrawlte Artikel |
| API-Schlüssel (nur in internen Sicherungen, **nicht** im Download) | Themenlisten |
| RSS-Quellen (Name, URL, aktiv) | Logs |
| | Die erzeugten WordPress-Beiträge (die sichert dein normales WordPress-Backup) |

## Einstellungen

| Einstellung | Beschreibung | Standard |
|---|---|---|
| **Automatische Sicherung** | Täglich eine Sicherung anlegen | an |
| **Uhrzeit** | Zeitpunkt (Ortszeit der Seite) | 03:00 |
| **Aufbewahren** | Wie viele Sicherungen behalten werden, 1–100 | 7 |

Die Anzahl gilt für **alle** Sicherungen zusammen – automatische, manuelle, importierte und die vor
einer Wiederherstellung. Ältere werden gelöscht; wird die Zahl verkleinert, sofort.

Liegt die letzte automatische Sicherung weniger als eine Stunde zurück (etwa weil WP-Cron einen
Termin doppelt ausgelöst hat), wird die neue übersprungen.

## Die Sicherungsliste

Unter den Einstellungen stehen alle vorhandenen Sicherungen, neueste zuerst:

| Spalte | Bedeutung |
|---|---|
| **Datum** | Zeitpunkt der Sicherung |
| **Anlass** | *Automatisch*, *Manuell*, *Vor Wiederherstellung* oder *Import* |
| **Plugin-Version** | AutoQuill-Version beim Sichern |
| **RSS-Quellen** | Anzahl der enthaltenen Quellen |

**Jetzt sichern** legt sofort eine manuelle Sicherung an – empfehlenswert vor größeren Änderungen
an den Prompts.

## Wiederherstellen

**Wiederherstellen** (mit Sicherheitsabfrage):

1. Der **aktuelle Stand** wird zuerst als eigene Sicherung *Vor Wiederherstellung* abgelegt – eine
   Wiederherstellung lässt sich also selbst wieder rückgängig machen.
2. Die Einstellungen werden übernommen und dabei genauso geprüft wie beim Speichern des Formulars.
   Ungültige Werte fallen auf den bisherigen Wert zurück.
3. RSS-Quellen werden über ihre **URL** abgeglichen: fehlende werden angelegt, vorhandene erhalten
   Name und Aktiv-Status aus der Sicherung. **Es wird keine Quelle gelöscht.**
4. Geänderte Uhrzeiten werden sofort neu eingeplant.

Die Erfolgsmeldung nennt, wie viele Quellen neu angelegt und wie viele aktualisiert wurden.

Sicherungen aus Versionen vor 1.7.0 enthalten noch einen gemeinsamen API-Schlüssel (`ai_api_key`);
beim Wiederherstellen wird er dem in der Sicherung gewählten Provider zugeordnet.

## Herunterladen

**Herunterladen** speichert eine Sicherung als JSON-Datei
(`auto-quill-backup-JJJJ-MM-TT-HHMMSS.json`). Aus Sicherheitsgründen **ohne API-Schlüssel** – die
Datei kann also gefahrlos abgelegt oder weitergegeben werden.

Aufbau:

```json
{
  "plugin": "auto-quill",
  "format": 1,
  "created": "2026-10-10T01:00:00+00:00",
  "version": "1.8.1",
  "settings": { "ai_provider": "claude", "prompt_body": "…", "fetch_time": "00:00", "…": "…" },
  "sources": [
    { "title": "Heise", "feed_url": "https://www.heise.de/rss/heise-atom.xml", "is_active": 1 }
  ]
}
```

## Importieren

Eine heruntergeladene Datei (max. 1 MB) auswählen und **Importieren**. Die Datei wird geprüft
(`"plugin": "auto-quill"` und ein `settings`-Objekt müssen vorhanden sein) und erscheint dann als
Sicherung mit Anlass *Import* in der Liste – **angewendet wird sie erst mit „Wiederherstellen“**.

Beim Import werden nur bekannte Einstellungen übernommen, API-Schlüssel in der Datei ignoriert und
Quellen ohne gültige `http`/`https`-URL verworfen. Die aktuell hinterlegten API-Schlüssel bleiben
beim Wiederherstellen erhalten.

Typische Anwendungen:

- **Umzug** auf eine neue WordPress-Installation: alt herunterladen → neu importieren →
  wiederherstellen → API-Schlüssel eintragen.
- **Staging → Live**: Prompts auf der Testseite feinschleifen, dann übertragen.
- **Mehrere Seiten** mit gleicher Grundkonfiguration aufsetzen.

## Wo die Sicherungen liegen

In der WordPress-Datenbank, Option `auto_quill_backups`, **nicht** automatisch geladen. Bewusst
keine Dateien im Upload-Ordner: Die wären öffentlich abrufbar – mitsamt API-Schlüsseln.

Beim Löschen des Plugins werden alle Sicherungen mit entfernt. Wer sie behalten will, lädt vorher
eine herunter.
