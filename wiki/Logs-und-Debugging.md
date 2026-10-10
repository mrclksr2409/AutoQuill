# Logs und Debugging

## Log-Seite

**AutoQuill → Logs** („AutoQuill Logs“) zeigt, was das Plugin getan hat und woran es gescheitert
ist – als Tabelle mit Zeit, Level (farbiges Abzeichen), Quelle und Nachricht, höchstens 500 Einträge.

| Filter | Werte |
|---|---|
| **Level** | *Alle*, *Error*, *Warning*, *Info*, *Debug* |
| **Quelle** | *Alle* oder eine der Quellen, die im Log vorkommen (siehe unten) |
| **Zeitraum** | letzte Stunde, letzte 24 h (Standard), letzte 7 Tage, alle |

| Quelle | Bereich |
|---|---|
| `fetcher` | RSS-Abruf und Artikelseiten |
| `selector` | Themenauswahl |
| `writer` | Beitragsgenerierung |
| `interview` | Interview-Fragen und Interview-Beiträge |
| `client` | Anfragen an den KI-Provider |
| `models` | Abruf der Modellliste |
| `pixabay` | Bildsuche und Bild-Suchbegriffe |
| `posts` | Anlegen der Beiträge, Beitragsbild |
| `notifier` | Tagesbericht und Test-Mail |
| `backup` | Sicherungen |
| `db.*` | Datenbank (`db.schema`, `db.articles`, `db.sources`, `db.topics`, `db.interviews`) |

- **Context anzeigen** klappt die Detaildaten eines Eintrags auf (HTTP-Status, Dauer, Modell,
  gekürzte API-Antwort …). Felder, die nach Schlüssel, Passwort oder Token aussehen, werden als
  `***redacted***` gespeichert, lange Texte auf 2 000 Zeichen gekürzt.
- **Als JSON exportieren** lädt die neuesten bis zu 500 Einträge herunter – unabhängig von den
  gesetzten Filtern. Praktisch für Fehlermeldungen.
- **Alle Logs löschen** leert das Log (mit Sicherheitsabfrage).

### Was wird aufgezeichnet?

| Level | Wann |
|---|---|
| **Error**, **Warning** | immer |
| **Info**, **Debug** | nur bei aktivem **Debug-Logging** (Einstellungen → System) |

Das Log hält maximal **7 Tage** bzw. **500 Einträge**; ältere werden automatisch entfernt, die
ältesten zuerst – unabhängig vom Level.

> Debug-Logging nur zur Fehlersuche einschalten: Die vielen Info-Einträge können ältere Fehler aus
> dem Log verdrängen, die dann auch im [Tagesbericht](Tagesbericht) fehlen.

## Browser-Konsole

Auf jeder AutoQuill-Seite schreibt das Plugin beim Laden die letzten 20 Log-Einträge in die
**Browser-Konsole** (F12 → Konsole), farblich nach Level, und meldet, ob Debug-Logging aktiv ist.
Solange eine AutoQuill-Anfrage läuft (Generierung, Interview, Feed-Liste …), werden neue Einträge
alle 2 Sekunden **live nachgeladen**; 6 Sekunden nach der letzten Anfrage endet das wieder.
Zusätzlich stehen dort Befehle bereit:

```js
AutoQuill.debug.tail(50)       // die letzten 50 Einträge ausgeben
AutoQuill.debug.download()     // aktuelle Logs als JSON herunterladen
AutoQuill.debug.clear()        // Logs serverseitig leeren
AutoQuill.debug.stopPolling()  // Live-Stream anhalten
AutoQuill.debug.startPolling() // Live-Stream wieder starten
```

Besonders hilfreich während einer Generierung: Man sieht in Echtzeit, wann die Anfrage an die KI
startet, wie lange sie dauert und was zurückkommt.

## Status-Panel

**Einstellungen → System**, unterhalb der Einstellungen:

| Zeile | Aussage |
|---|---|
| **DB-Version** | Erwartete und tatsächliche Schema-Version – rot, wenn eine Migration aussteht oder scheiterte |
| **Tabellen** | Existenz und Zeilenzahl von `…_sources`, `…_articles`, `…_topics` und `…_interviews` (die Log-Tabelle wird hier nicht aufgeführt) |
| **auto_quill_settings** | Die gespeicherten Einstellungen als JSON, API-Schlüssel maskiert |
| **Nächster Fetch / Themen-Auswahl** | Nächster geplanter Lauf; liegt er in der Vergangenheit, läuft WP-Cron nicht |
| **Nächster Tagesbericht** | Termin und Zahl der gültigen Empfänger – oder „Benachrichtigungen sind deaktiviert“ |
| **Nächste Sicherung** | Termin und „N von max. M gespeichert“ – oder „Automatische Sicherung ist deaktiviert“ |

## Vorgehen bei einem Fehler

1. **Logs** öffnen, Level *Error* und *Warning*, Zeitraum *letzte 24 h*.
2. **Context anzeigen** beim relevanten Eintrag – dort stehen HTTP-Status und die Antwort des Dienstes.
3. Reicht das nicht: **Debug-Logging** einschalten, Vorgang wiederholen, erneut ins Log schauen.
4. Häufige Ursachen und Lösungen: **[Fehlerbehebung](Fehlerbehebung)**.
5. Für einen Fehlerbericht: Logs als JSON exportieren und dem [Issue](https://github.com/mrclksr2409/AutoQuill/issues)
   anhängen (vorher auf persönliche Daten prüfen).
