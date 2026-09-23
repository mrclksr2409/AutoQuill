# AutoQuill Wiki

**AutoQuill** ist ein WordPress-Plugin, das deine RSS-Feeds täglich abruft, per KI die
interessantesten Themen des Tages auswählt und daraus auf Knopfdruck vollständige, eigenständige
Blog-Beiträge schreibt – inklusive Titel, Social-Media-Auszug, Kategorien, Beitragsbild und
garantiertem Quellenhinweis.

> Aktuelle Version: **1.5.0** · Voraussetzungen: WordPress 5.9+, PHP 8.0+, ein API-Schlüssel von
> OpenAI oder Anthropic (Claude)

---

## So funktioniert AutoQuill in einem Satz

```
RSS-Feeds ──► täglicher Abruf ──► KI wählt Top-Themen (0–100 bewertet)
          ──► du klickst „Blog-Post generieren“ ──► KI schreibt ──► du prüfst ──► Veröffentlichen
```

Mehr dazu unter **[Arbeitsablauf](Arbeitsablauf)**.

## Einstieg

| Seite | Wofür |
|---|---|
| [Installation](Installation) | Plugin hochladen, aktivieren, Voraussetzungen |
| [Schnellstart](Schnellstart) | In 10 Minuten zum ersten Beitrag |
| [Arbeitsablauf](Arbeitsablauf) | Was täglich automatisch passiert und was du tust |

## Bedienung

| Seite | Wofür |
|---|---|
| [Dashboard](Dashboard) | Top-Themen, alle Feed-Einträge, manuelle Auslöser |
| [Blog-Post erstellen](Blog-Post-erstellen) | Generierungs-Seite, Gegenprüfen, Bild, Veröffentlichen |
| [RSS-Quellen](RSS-Quellen) | Feeds hinzufügen und verwalten |
| [Einstellungen](Einstellungen) | Alle acht Tabs im Detail |
| [Prompts](Prompts) | Titel, Text, Auszug und Kategorien steuern |
| [Zeitplan und Cron](Zeitplan-und-Cron) | Uhrzeiten, WP-Cron, echter Server-Cron |
| [Tagesbericht](Tagesbericht) | Zusammenfassung per E-Mail |
| [Backup und Wiederherstellung](Backup-und-Wiederherstellung) | Sicherungen, Download, Import |
| [Logs und Debugging](Logs-und-Debugging) | Log-Seite, Browser-Konsole, Status-Panel |

## Referenz

| Seite | Wofür |
|---|---|
| [Sicherheit und Datenschutz](Sicherheit-und-Datenschutz) | API-Schlüssel, Rechte, welche Daten wohin gehen |
| [REST-API](REST-API) | Alle Endpunkte mit Parametern |
| [Hooks und Filter](Hooks-und-Filter) | Erweitern ohne Code-Änderung |
| [Datenbank](Datenbank) | Tabellen, Optionen, Post-Meta |
| [Updates](Updates) | GitHub-Releases, Beta-Modus |
| [Fehlerbehebung](Fehlerbehebung) | Typische Probleme und Lösungen |
| [FAQ](FAQ) | Häufige Fragen |
| [Entwicklung](Entwicklung) | Architektur, Code-Struktur, Mitarbeit |

## Funktionen im Überblick

- **RSS-Feed-Verwaltung** – beliebig viele Quellen, Duplikate werden automatisch erkannt
- **KI-Themenauswahl mit Bewertung** – täglich die 5 lohnendsten Themen, 0–100 bewertet und begründet
- **Feed-Übersicht** – alle gecrawlten Einträge durchsuchbar, filterbar, paginiert
- **KI-Text-Generierung** – vollständige Beiträge (Standard 800–1200 Wörter) mit Überschriften und Fazit
- **Gegenprüfen** – Originaltext direkt neben dem generierten Beitrag
- **Beitragsbild** – optional über Pixabay, Suchbegriffe schlägt die KI vor
- **Quellenhinweis** – serverseitig garantiert am Ende jedes Beitrags
- **Artikel ↔ Beitrag-Verknüpfung** – dauerhaft, in beide Richtungen
- **OpenAI & Claude** – Modellauswahl per Dropdown, direkt vom Anbieter geladen
- **Frei wählbare Uhrzeiten** für Abruf, Themenauswahl, Tagesbericht und Backup
- **Tagesbericht per E-Mail** – nur wenn es etwas zu berichten gibt
- **Backup** – tägliche Sicherung von Einstellungen und Quellen, mit Wiederherstellen und Import
- **Automatische Updates** über GitHub-Releases
