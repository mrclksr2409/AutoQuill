# Blog-Post erstellen

Die Generierungs-Seite (`admin.php?page=auto-quill&aq_view=generate`) hat keinen eigenen
Menüeintrag – du erreichst sie über **Blog-Post generieren** im [Dashboard](Dashboard).
Die Generierung startet beim Öffnen automatisch.

## Aufbau der Seite

```
┌───────────────────────────┬──────────────────────────────────┐
│ Originaltext              │ Generierter Beitrag              │
│  [Quelltext für die KI]   │  Titel                           │
│  [Roh-HTML]               │  Beitragstext (Vorschau)         │
│                           │  Social-Media-Auszug             │
│  Link: Originalartikel    │  Kategorien                      │
│                           │  Beitragsbild (optional)         │
│                           │  [Speichern / Veröffentlichen]   │
└───────────────────────────┴──────────────────────────────────┘
```

### Linke Seite: Originaltext

Zwei Ansichten zum Umschalten:

- **Quelltext für die KI** – exakt der Text, den das Modell bekommen hat. *Was hier nicht steht,
  darf im Beitrag nicht auftauchen.* Wurde der Text gekürzt (Limit 8 000 Zeichen), steht ein
  Hinweis darüber.
- **Roh-HTML** – der gespeicherte Seitenquelltext der Artikelseite, als Text dargestellt.

Konnte beim Abruf keine Artikelseite geladen werden, bekommt die KI nur Titel und RSS-Beschreibung.
Die Seite weist dann ausdrücklich darauf hin – ein dünner Beitrag ist in dem Fall erklärbar.

### Rechte Seite: Generierter Beitrag

Während die KI schreibt, läuft ein Spinner mit Sekundenzähler. Danach erscheinen:

| Feld | Herkunft |
|---|---|
| **Titel** | KI, nach [Prompt: Titel](Prompts) |
| **Beitragstext** | KI, nach [Prompt: Beitragstext](Prompts) – HTML mit Zwischenüberschriften, plus Quellenhinweis |
| **Social-Media-Auszug** | KI, wird als WordPress-Auszug gespeichert |
| **Kategorien** | Vorschlag der KI aus deinen vorhandenen Kategorien; Mehrfachauswahl mit Strg/Cmd änderbar |

Gefällt das Ergebnis nicht, erzeugt **Neu generieren** einen neuen Entwurf.

## Beitragsbild (optional, über Pixabay)

Voraussetzung: ein Pixabay-Schlüssel unter **Einstellungen → Bilder**.

1. **Bild auswählen** öffnet die Bildsuche. Die KI schlägt passende Suchbegriffe vor.
2. Suchbegriff anpassen, Ergebnisse durchblättern, Bild anklicken.
3. Beim Speichern lädt WordPress das Bild in die **Mediathek** und setzt es als Beitragsbild.

Die Bilder stehen unter der Pixabay Content License; die Quelle wird in der Bildsuche angezeigt.

## Speichern / Veröffentlichen

Die Beschriftung des Buttons zeigt, was passiert:

| Einstellung | Button |
|---|---|
| Post-Status *Entwurf* | **Als Entwurf speichern** |
| Post-Status *Genehmigung ausstehend* | **Zur Prüfung einreichen** |
| Post-Status *Veröffentlicht* oder *Automatisch veröffentlichen* an | **Post veröffentlichen** |

Nach dem Speichern erscheint ein Erfolgskasten mit **Post bearbeiten** – die Seite lädt bewusst
nicht neu, damit nicht versehentlich eine zweite Generierung startet.

## Was im Beitrag gespeichert wird

- Titel, Inhalt, Auszug, Kategorien, Beitragsbild
- Unsichtbare Meta-Daten zur Herkunft (Quell-URL, Artikel-ID, Artikeltitel, Feed-Name, Zeitpunkt)
  – sichtbar im Editor in der Box **„AutoQuill-Quelle“**
- Die Verknüpfung im Feed-Artikel, damit das Dashboard „bereits verwendet“ anzeigen kann

## Grenzen

- Die KI sieht nur die ersten **8 000 Zeichen** des Artikels (als Text, ohne HTML).
- Bei sehr langen Längenvorgaben im Prompt kann ein Modell mit kleinem Ausgabelimit ablehnen –
  siehe [Prompts → Beitragslänge](Prompts#beitragslänge-und-token-budget).
- Die Qualität hängt stark von der Quelle ab: Paywall-Seiten oder JavaScript-lastige Seiten liefern
  oft kaum verwertbaren Text.
