# Zeitplan und Cron

AutoQuill erledigt vier Aufgaben zeitgesteuert:

| Aufgabe | Cron-Hook | Einstellung | Standard | Immer aktiv? |
|---|---|---|---|---|
| RSS-Abruf | `auto_quill_daily_fetch` | Feeds & Zeitplan → RSS-Abruf | 00:00 | ja |
| Themenauswahl | `auto_quill_daily_select` | Feeds & Zeitplan → Themenauswahl | 01:00 | ja |
| Sicherung | `auto_quill_daily_backup` | Backup → Uhrzeit | 03:00 | nur wenn eingeschaltet |
| Tagesbericht | `auto_quill_daily_digest` | Benachrichtigungen → Uhrzeit | 08:00 | nur wenn eingeschaltet |

Alle Zeiten gelten in der **Zeitzone der Seite** (Einstellungen → Allgemein → Zeitzone).

## Wie AutoQuill plant

Jede Aufgabe wird als **einzelner Termin** zur nächsten eingestellten Uhrzeit geplant. Beim Start
des Laufs plant sie als Erstes den Termin für den nächsten Tag – so reißt die Kette auch bei einem
Fehler nicht ab.

Warum nicht einfach „täglich“? WordPress' `daily` wiederholt sich stur alle 86 400 Sekunden. Nach
der Zeitumstellung würde aus 01:00 dauerhaft 00:00 oder 02:00. Mit einzelnen Terminen bleibt die
Uhrzeit korrekt.

- Eine **geänderte Uhrzeit** wird beim Speichern sofort übernommen.
- Fehlt ein Termin (z. B. nach einer Datenbank-Migration), legt AutoQuill ihn beim nächsten
  Seitenaufruf automatisch neu an.
- Die nächsten Termine zeigt **Einstellungen → System → Status**.

## WP-Cron verstehen

WordPress hat keinen echten Hintergrunddienst. **WP-Cron läuft nur, wenn jemand die Seite aufruft.**
Ist um 00:00 niemand auf der Seite, läuft der Abruf erst beim nächsten Besuch – bei wenig
besuchten Seiten also eventuell Stunden später.

### Empfehlung: echter Server-Cron

1. In der `wp-config.php` den Seitenaufruf-Trigger abschalten:
   ```php
   define('DISABLE_WP_CRON', true);
   ```
2. Beim Hoster einen Cronjob anlegen, der alle 5 Minuten läuft:
   ```bash
   */5 * * * * curl -s https://deine-seite.de/wp-cron.php?doing_wp_cron > /dev/null 2>&1
   ```
   oder mit WP-CLI:
   ```bash
   */5 * * * * cd /pfad/zu/wordpress && wp cron event run --due-now > /dev/null 2>&1
   ```

Die meisten Hoster bieten dafür im Kundenmenü einen Punkt *Cronjobs* an.

### Prüfen, ob Cron läuft

- **Einstellungen → System → Status**: Liegt „Nächster Fetch“ in der Vergangenheit, läuft WP-Cron nicht.
- Mit WP-CLI: `wp cron event list | grep auto_quill`
- Plugin *WP Crontrol* zeigt alle Termine im Admin und kann sie manuell auslösen.

### Aufgaben per WP-CLI sofort auslösen

```bash
wp cron event run auto_quill_daily_fetch
wp cron event run auto_quill_daily_select
wp cron event run auto_quill_daily_backup
```

## Reihenfolge und Abstände

- **Themenauswahl nach dem Abruf**: Die Auswahl betrachtet nur Artikel, die in den letzten
  24 Stunden **neu abgerufen** wurden. Liegt sie vor dem Abruf, arbeitet sie mit den Artikeln des
  Vortags. AutoQuill warnt, wenn die Auswahl nicht innerhalb von 12 Stunden nach dem Abruf liegt
  oder genau gleichzeitig läuft.
- **Abstand einplanen**: Der Abruf lädt jede neue Artikelseite einzeln. Bei vielen Feeds kann das
  mehrere Minuten dauern – eine Stunde Abstand ist ein guter Standard.
- **Tagesbericht nach der Auswahl**: So enthält der Bericht die Themen des Tages.

## Nach einem Update von Version < 1.5.0

Ältere Versionen haben Abruf und Auswahl als `daily`-Termine zum Aktivierungszeitpunkt geplant.
Beim ersten Seitenaufruf nach dem Update werden sie automatisch auf die eingestellten Uhrzeiten
umgestellt – es ist nichts zu tun.
