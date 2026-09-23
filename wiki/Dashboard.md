# Dashboard (Übersicht)

**AutoQuill → Übersicht** ist die Startseite des Plugins. Sie besteht aus einer Werkzeugleiste und
zwei Tabs.

## Werkzeugleiste

| Element | Funktion |
|---|---|
| **RSS-Feed auswählen** | *Alle aktiven Feeds* oder eine einzelne Quelle |
| **Feeds neu holen + Topics neu wählen** | Ruft den/die Feed(s) jetzt ab und wählt anschließend die Themen neu. Die Seite lädt danach neu. |
| **Nur Topics neu wählen** | Wählt die heutigen Themen neu, ohne abzurufen |

> Beide Aktionen können je nach Anzahl der Feeds und dem KI-Modell ein bis zwei Minuten dauern –
> jede neue Artikelseite wird einzeln geladen.

## Tab „Top-Themen“

Die heutige Themenauswahl, sortiert nach Bewertung:

- **Bewertung** 0–100 als farbiges Abzeichen – *hoch* ab 75, *mittel* ab 50, sonst *niedrig* –
  mit der Begründung der KI
- **Titel** und **Zusammenfassung** des Themas
- **Quelle** und Link zum **Originalartikel**
- **Blog-Post generieren** öffnet die [Generierungs-Seite](Blog-Post-erstellen)

Ob zu einem Artikel schon ein Beitrag existiert, siehst du im Tab *Alle Feed-Einträge*.

Steht dort „Noch keine Themen für heute verfügbar“, ist die Themenauswahl heute noch nicht gelaufen
oder hat keine neuen Artikel gefunden → siehe [Fehlerbehebung](Fehlerbehebung#keine-themen-im-dashboard).

## Tab „Alle Feed-Einträge“

Eine Tabelle aller gespeicherten Artikel – unabhängig davon, ob die KI sie ausgewählt hat. Damit
kannst du auch über Themen schreiben, die es nicht in die Top 5 geschafft haben.

| Filter | Wirkung |
|---|---|
| **Quelle filtern** | nur Einträge einer RSS-Quelle |
| **Feed-Einträge durchsuchen** | Volltextsuche in Titel und Beschreibung (Enter oder *Filtern*) |
| **Nur ohne Blog-Post** | blendet Einträge aus, zu denen es schon einen Beitrag gibt |

Die Liste lädt erst beim ersten Öffnen des Tabs und ist mit *Zurück* / *Weiter* paginiert
(20 Einträge pro Seite). Pro Zeile: Datum, Titel mit Link zum Original, Quelle, verknüpfter Beitrag
(falls vorhanden) und **Blog-Post generieren**.

> Tipp: Der Link `admin.php?page=auto-quill#dash-articles` öffnet direkt diesen Tab.
