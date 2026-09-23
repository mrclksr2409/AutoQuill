# Logs und Debugging

## Log-Seite

**AutoQuill → Logs** zeigt, was das Plugin getan hat und woran es gescheitert ist.

| Filter | Werte |
|---|---|
| **Level** | *Error*, *Warning*, *Info*, *Debug* |
| **Quelle** | `fetcher`, `selector`, `writer`, `client`, `models`, `pixabay`, `posts`, `notifier`, `backup`, `db.*` |
| **Zeitraum** | letzte Stunde, letzte 24 h, letzte 7 Tage, alle |

- **Context anzeigen** klappt die Detaildaten eines Eintrags auf (HTTP-Status, Dauer, Modell,
  gekürzte API-Antwort …).
- **Als JSON exportieren** lädt die gefilterten Einträge herunter – praktisch für Fehlermeldungen.
- **Alle Logs löschen** leert das Log.

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

Auf jeder AutoQuill-Seite werden neue Log-Einträge **live in die Browser-Konsole** gestreamt
(F12 → Konsole), farblich nach Level. Zusätzlich stehen dort Befehle bereit:

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
| **Tabellen** | Existenz und Zeilenzahl der vier AutoQuill-Tabellen |
| **auto_quill_settings** | Die gespeicherten Einstellungen als JSON, API-Schlüssel maskiert |
| **Nächster Fetch / Themen-Auswahl** | Nächster geplanter Lauf; liegt er in der Vergangenheit, läuft WP-Cron nicht |
| **Nächster Tagesbericht** | Termin und Zahl der gültigen Empfänger |
| **Nächste Sicherung** | Termin und Anzahl gespeicherter Sicherungen |

## Vorgehen bei einem Fehler

1. **Logs** öffnen, Level *Error* und *Warning*, Zeitraum *letzte 24 h*.
2. **Context anzeigen** beim relevanten Eintrag – dort stehen HTTP-Status und die Antwort des Dienstes.
3. Reicht das nicht: **Debug-Logging** einschalten, Vorgang wiederholen, erneut ins Log schauen.
4. Häufige Ursachen und Lösungen: **[Fehlerbehebung](Fehlerbehebung)**.
5. Für einen Fehlerbericht: Logs als JSON exportieren und dem [Issue](https://github.com/mrclksr2409/autoquill/issues)
   anhängen (vorher auf persönliche Daten prüfen).
