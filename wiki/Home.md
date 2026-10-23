# AutoQuill Wiki

**AutoQuill** ist ein WordPress-Plugin, das deine RSS-Feeds täglich abruft, per KI die
interessantesten Themen des Tages auswählt und daraus auf Knopfdruck vollständige, eigenständige
Blog-Beiträge schreibt – inklusive Titel, Social-Media-Auszug, Kategorien, Beitragsbild und
garantiertem Quellenhinweis. Alternativ interviewt dich die KI als Redakteur, und aus deinen
Antworten entsteht ein Beitrag.

> Aktuelle Version: **1.8.2** · Voraussetzungen: WordPress 5.9+, PHP 8.0+, ein API-Schlüssel von
> OpenAI, Anthropic (Claude) oder IONOS – oder ein eigener OpenAI-kompatibler Endpunkt
>
> Autor: [Marcel Kaiser](https://github.com/mrclksr2409) · Repository:
> [mrclksr2409/AutoQuill](https://github.com/mrclksr2409/AutoQuill)

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
| [Dashboard](Dashboard) | Übersicht: Top-Themen, alle Feed-Einträge, manuelle Auslöser |
| [Blog-Post erstellen](Blog-Post-erstellen) | Generierungs-Seite, Gegenprüfen, Bild, Veröffentlichen |
| [Interview](Interview) | Die KI fragt als Redakteur, aus deinen Antworten entsteht ein Beitrag |
| [RSS-Quellen](RSS-Quellen) | Feeds hinzufügen und verwalten |
| [Einstellungen](Einstellungen) | Alle neun Tabs im Detail |
| [Prompts](Prompts) | Schreibstil, Titel, Text, Auszug und Kategorien steuern |
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
| [Updates](Updates) | Updates vom `main`-Branch, Beta-Modus |
| [Changelog](Changelog) | Was sich in welcher Version geändert hat |
| [Fehlerbehebung](Fehlerbehebung) | Typische Probleme und Lösungen |
| [FAQ](FAQ) | Häufige Fragen |
| [Entwicklung](Entwicklung) | Architektur, Code-Struktur, Mitarbeit |

## Funktionen im Überblick

- **RSS-Feed-Verwaltung** – beliebig viele Quellen, Duplikate werden automatisch erkannt
- **KI-Themenauswahl mit Bewertung** – täglich die 5 lohnendsten Themen, 0–100 bewertet und begründet
- **Feed-Übersicht** – alle gecrawlten Einträge durchsuchbar, filterbar, paginiert
- **KI-Text-Generierung** – vollständige Beiträge (Standard 800–1200 Wörter) mit Überschriften und Fazit
- **Interview-Modus** – die KI stellt dir als Redakteur Fragen, aus den Antworten wird ein Beitrag
- **Schreibstil** – eine globale Vorgabe für Tonalität, Anrede und Wortwahl aller Texte
- **Gegenprüfen** – Originaltext direkt neben dem generierten Beitrag
- **Beitragsbild** – optional über Pixabay, Suchbegriffe schlägt die KI vor
- **Quellenhinweis** – serverseitig garantiert am Ende jedes Beitrags aus einem Feed-Eintrag
- **Artikel ↔ Beitrag-Verknüpfung** – dauerhaft, in beide Richtungen
- **Vier KI-Provider** – OpenAI, Claude, IONOS AI Model Hub oder ein eigener OpenAI-kompatibler
  Endpunkt (Mistral, Groq, OpenRouter, Ollama …); eigener Schlüssel je Provider, Modellauswahl
  direkt vom Anbieter
- **Frei wählbare Uhrzeiten** für Abruf, Themenauswahl, Tagesbericht und Backup
- **Tagesbericht per E-Mail** – nur wenn es etwas zu berichten gibt
- **Backup** – tägliche Sicherung von Einstellungen und Quellen, mit Wiederherstellen und Import
- **Einheitliche Oberfläche** – alle Admin-Seiten nutzen das mitgelieferte Design-System
  *WP-Backend UI* (Seitenkopf mit Versionsnummer, Karten, Badges, Hinweise)
- **Automatische Updates** direkt vom GitHub-Branch `main` (im Beta-Modus vom Branch `beta`)
