# Changelog

Eine Zusammenfassung der Versionen. Die vollständige, technische Fassung steht im
[README](https://github.com/mrclksr2409/AutoQuill#changelog). Wie Updates ankommen, beschreibt
die Seite [Updates](Updates).

| Version | Datum | Schwerpunkt |
|---|---|---|
| [1.8.2](#182--2026-10-23) | 2026-10-23 | Beitragsbild: automatische Wiederholung und Rückmeldung |
| [1.8.1](#181--2026-10-10) | 2026-10-10 | Autor Marcel Kaiser, Wiki-Link in der Plugin-Liste |
| [1.8.0](#180--2026-10-09) | 2026-10-09 | Einheitliches Admin-Design mit WP-Backend UI |
| [1.7.2](#172--2026-10-03) | 2026-10-03 | Updates vom `main`-Branch, Beta folgt `beta` |
| [1.7.1](#171--2026-10-03) | 2026-10-03 | Plugin Update Checker 5.7 |
| [1.7.0](#170--2026-09-28) | 2026-09-28 | Schreibstil, IONOS, eigener Endpunkt, Schlüssel je Provider |
| [1.6.0](#160--2026-09-28) | 2026-09-28 | Interview-Modus |
| [1.5.0](#150--2026-09-23) | 2026-09-23 | Modell-Dropdown, freie Uhrzeiten, Backup |
| [1.4.0](#140--2026-09-22) | 2026-09-22 | Tagesbericht per E-Mail |
| [1.3.1](#131--2026-09-22) | 2026-09-22 | Fix: Zugriff auf die Generierungs-Seite |
| [1.3.0](#130--2026-09-22) | 2026-09-22 | Eigene Generierungs-Seite mit Originaltext |
| [1.2.0](#120--2026-09-22) | 2026-09-22 | Bewertung, Feed-Liste, Quellenhinweis, Verknüpfung |
| [1.1.0](#110) | – | Pixabay-Bildauswahl, Logs-Seite, Prompt-Vorlagen |

---

## 1.8.2 – 2026-10-23

- **Beitragsbild zuverlässiger:** Scheitert der Download von Pixabay vorübergehend (z. B.
  „Too Many Requests“), versucht AutoQuill es bis zu dreimal mit kurzer Pause.
- **Rückmeldung:** Fehlt das Bild nach dem Speichern trotzdem, zeigt der Erfolgskasten den Grund
  und den Button **Bild erneut übernehmen**.
- Neuer REST-Endpunkt `POST /posts/{id}/featured-image`; `publish-post` liefert zusätzlich `image`.

## 1.8.1 – 2026-10-10

- Autor ist jetzt **Marcel Kaiser**; Author URI und Plugin URI zeigen auf GitHub
  ([mrclksr2409](https://github.com/mrclksr2409) bzw.
  [mrclksr2409/AutoQuill](https://github.com/mrclksr2409/AutoQuill)).
- Neuer Link **Wiki** in der Zeile des Plugins unter *Plugins*.

## 1.8.0 – 2026-10-09

- **Einheitliches Admin-Design:** Übersicht, Blog-Post erstellen, Interview, RSS Quellen,
  Einstellungen und Logs nutzen das mitgelieferte Design-System *WP-Backend UI* 1.0.2
  (`lib/wp-backend-ui/`): Seitenkopf mit Icon und Versionsnummer, Karten, Abzeichen für Beitrags-,
  Interview-, Feed- und Log-Status, Hinweise, Toasts, Ladeindikator, Code-Blöcke für Logs und
  Quelltext.
- Die Pixabay-Bildauswahl ist ein nativer Dialog (schließen mit *Esc* oder Klick daneben).
- Die Quell-Tabs auf der Generierungs-Seite (*Quelltext für die KI* / *Roh-HTML*) laufen über die
  Tabs der Bibliothek.
- Siehe [Entwicklung → Admin-Design](Entwicklung#admin-design-wp-backend-ui).

## 1.7.2 – 2026-10-03

- Updates kommen direkt vom Branch **`main`**; GitHub-Releases und Tags werden ignoriert.
- Der **Beta-Modus** folgt dem Branch `beta`. Beim Umschalten wird die zwischengespeicherte
  Update-Info verworfen, damit sofort der neue Branch geprüft wird.

## 1.7.1 – 2026-10-03

- Mitgelieferter Plugin Update Checker auf Version 5.7 aktualisiert.

## 1.7.0 – 2026-09-28

- **Schreibstil** unter *Einstellungen → Prompts*: globale Vorgaben für Tonalität, Anrede,
  Satzbau und Wortwahl, gilt für Titel, Text und Auszug aller Beiträge (RSS und Interview).
- **IONOS AI Model Hub** als KI-Provider (OpenAI-kompatibel, Rechenzentren in Deutschland).
- **Eigener OpenAI-kompatibler Endpunkt** mit Basis-URL, optionalem Schlüssel und Modell-ID.
- API-Schlüssel werden **je Provider** gespeichert. Der bisherige gemeinsame Schlüssel wird beim
  Update automatisch dem gewählten Provider zugeordnet, ebenso beim Wiederherstellen alter
  Sicherungen.

## 1.6.0 – 2026-09-28

- **[Interview-Modus](Interview)** unter *AutoQuill → Interview*: Die KI fragt als Redakteur,
  aus den Antworten entsteht ein Beitrag (ab drei Antworten). Interviews werden gespeichert und
  lassen sich fortsetzen (neue Tabelle `wp_auto_quill_interviews`, DB-Version 1.5).
- Neuer Einstellungs-Tab **Interview** (Perspektive, Richtwert Fragen, Redakteurs-Prompt).
- Neue REST-Endpunkte unter `/interviews`; `/publish-post` akzeptiert `interview_id`.
- Button **Interview starten** auf der Übersicht.

## 1.5.0 – 2026-09-23

- Modelle werden per Dropdown gewählt, die Liste kommt direkt vom Anbieter (12 h Cache).
- Neuer Tab **Feeds & Zeitplan**: Uhrzeiten für Abruf und Themenauswahl frei wählbar.
- Neuer Tab **Backup**: tägliche Sicherung, Wiederherstellen, Download, Import.
- Einstellungen neu sortiert (*KI-Provider · Feeds & Zeitplan · Prompts · Veröffentlichung ·
  Bilder · Benachrichtigungen · Backup · System*); alte Tab-Links funktionieren weiter.
- Abruf und Auswahl laufen als verkettete Einzeltermine zur eingestellten Uhrzeit (robust gegen
  die Zeitumstellung); die Auswahl läuft nicht mehr doppelt.
- OpenAI-Reasoning-Modelle (o-Serie, gpt-5) werden korrekt angesprochen.

## 1.4.0 – 2026-09-22

- **[Tagesbericht](Tagesbericht)** per E-Mail mit Tab *Benachrichtigungen* und Test-Mail.
- Post-Meta `_auto_quill_generated_at` als Marker für von AutoQuill erstellte Beiträge
  (nicht rückwirkend).
- Fix: Das Log-Aufräumen rechnete mit der falschen Zeitzone.

## 1.3.1 – 2026-09-22

- Fix: Die Generierungs-Seite verweigerte auch Administratoren den Zugriff. Sie ist jetzt eine
  Ansicht der Übersichtsseite.

## 1.3.0 – 2026-09-22

- Eigene **Generierungs-Seite**: links der Originaltext (*Quelltext für die KI* / *Roh-HTML*),
  rechts der Beitrag; sichtbarer Fortschritt, Warnung bei ungespeichertem Beitrag.
- „Blog-Post generieren“ ist ein echter Link; nach dem Speichern lädt die Seite nicht neu.
- Fixes: unsichtbarer Ladeindikator, doppelter KI-Aufruf beim Öffnen der Bildauswahl.

## 1.2.0 – 2026-09-22

- **Bewertung** der Tagesthemen (0–100 mit Begründung), Tab **Alle Feed-Einträge**, Beiträge aus
  jedem Feed-Eintrag.
- Dauerhafte **Verknüpfung** Feed-Eintrag ↔ Beitrag, Box „AutoQuill-Quelle“.
- Garantierter **Quellenhinweis** mit eigenem Text.
- Token-Budget aus der Wortzahl im Prompt, Filter `auto_quill_max_tokens`, Endpunkt `GET /articles`.
- DB-Version 1.4; verknüpfte Artikel werden nicht mehr aufgeräumt.

## 1.1.0

- Pixabay-Bildauswahl für Beitragsbilder, Logs-Seite, Prompt-Vorlagen.
