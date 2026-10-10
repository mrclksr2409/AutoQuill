# Dashboard (Übersicht)

Ein Klick auf **AutoQuill** im WordPress-Menü öffnet die Übersicht – die Startseite des Plugins
(`admin.php?page=auto-quill`). Sie besteht aus dem Seitenkopf und zwei Tabs.

## Seitenkopf

Wie alle AutoQuill-Seiten hat die Übersicht einen einheitlichen Kopf mit Titel, Untertitel
(„Tägliche Top-Themen aus deinen RSS-Feeds“) und der installierten Versionsnummer. Rechts steht der
Button **Interview starten**, der zur [Interview-Seite](Interview) führt.

## Tab „Top-Themen“

### Werkzeugleiste

| Element | Funktion |
|---|---|
| **RSS-Feed auswählen** | *Alle aktiven Feeds* oder eine einzelne aktive Quelle |
| **Feeds neu holen + Topics neu wählen** | Ruft den/die Feed(s) jetzt ab und wählt anschließend die Themen neu. Die Seite lädt danach neu. |
| **Nur Topics neu wählen** | Wählt die heutigen Themen neu, ohne abzurufen. Die Seite lädt danach neu. |

> Beide Aktionen können je nach Anzahl der Feeds und dem KI-Modell ein bis zwei Minuten dauern –
> jede neue Artikelseite wird einzeln geladen.

### Themenkarten

Die heutige Themenauswahl, sortiert nach Bewertung, als Karten nebeneinander:

- **Titel** des Themas
- **Bewertung** 0–100 als farbiges Abzeichen – *hoch* ab 75, *mittel* ab 50, sonst *niedrig* –
  mit der Begründung der KI (Themen aus Versionen vor der Bewertung haben kein Abzeichen)
- **Zusammenfassung** (gekürzt auf 200 Zeichen)
- **Quelle** (Name der RSS-Quelle) und Link zum **Originalartikel**
- **Blog-Post generieren** öffnet die [Generierungs-Seite](Blog-Post-erstellen)

Ob zu einem Artikel schon ein Beitrag existiert, siehst du im Tab *Alle Feed-Einträge*.

Steht dort „Noch keine Themen für heute verfügbar“, ist die Themenauswahl heute noch nicht gelaufen
oder hat keine neuen Artikel gefunden → siehe [Fehlerbehebung](Fehlerbehebung#keine-themen-im-dashboard).

## Tab „Alle Feed-Einträge“

Eine Tabelle aller gespeicherten Artikel – unabhängig davon, ob die KI sie ausgewählt hat. Damit
kannst du auch über Themen schreiben, die es nicht in die Top 5 geschafft haben.

| Filter | Wirkung |
|---|---|
| **Quelle** | nur Einträge einer RSS-Quelle (*Alle Quellen* oder eine beliebige, auch inaktive Quelle) |
| **Suche** | Volltextsuche in Titel und Beschreibung (Enter oder *Filtern*) |
| **Nur ohne Blog-Post** | blendet Einträge aus, zu denen es schon einen Beitrag gibt |

Die Liste lädt erst beim ersten Öffnen des Tabs und ist mit *Zurück* / *Weiter* paginiert
(20 Einträge pro Seite). Die Spalten:

| Spalte | Inhalt |
|---|---|
| **Titel** | Link zum Originalartikel, darunter der Anfang der Beschreibung |
| **Feed** | Name der RSS-Quelle |
| **Datum** | Veröffentlichungsdatum laut Feed |
| **Blog-Post** | Der verknüpfte Beitrag (Link zum Bearbeiten) mit Status-Abzeichen, sonst „–“ |
| *(Aktion)* | **Blog-Post generieren** – oder **Erneut generieren**, wenn es schon einen Beitrag gibt |

Ein Beitrag wird über die Spalte `post_id` des Artikels gefunden und – für ältere Verknüpfungen –
über die Post-Meta `_auto_quill_source_url`. Gelöschte oder im Papierkorb liegende Beiträge
erscheinen nicht.

> Tipp: Der Link `admin.php?page=auto-quill#dash-articles` öffnet direkt diesen Tab. Kommst du von
> einem Feed-Eintrag auf die Generierungs-Seite, führt *Zurück zur Übersicht* ebenfalls hierher.
