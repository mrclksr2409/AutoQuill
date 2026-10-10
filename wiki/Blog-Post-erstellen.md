# Blog-Post erstellen

Die Generierungs-Seite (`admin.php?page=auto-quill&aq_view=generate`) hat keinen eigenen
Menüeintrag – du erreichst sie über **Blog-Post generieren** (bzw. **Erneut generieren**) im
[Dashboard](Dashboard). Im Seitenkopf führt **Zurück zur Übersicht** wieder zurück – zum Tab
*Alle Feed-Einträge*, wenn du von dort gekommen bist.

## Start der Generierung

Kommst du über den Button aus dem Dashboard, startet die Generierung **automatisch**. Der Link
trägt dafür ein Sicherheits-Token (Nonce), weil jede Generierung einen kostenpflichtigen KI-Aufruf
auslöst. AutoQuill entfernt das Token sofort aus der Adresszeile – ein Neuladen der Seite, ein
Zurück-Navigieren oder ein Lesezeichen startet also **keine** zweite Generierung.

Ohne gültiges Token (z. B. bei einem kopierten Link) wartet die Seite und zeigt den Button
**Jetzt generieren**.

## Aufbau der Seite

```
┌───────────────────────────┬──────────────────────────────────┐
│ Originaltext              │ Generierter Beitrag              │
│  Titel · Feed · Datum     │  [Neu generieren]                │
│  Originalartikel öffnen ↗ │  Titel                           │
│  [Quelltext für die KI]   │  Text (Vorschau)                 │
│  [Roh-HTML]               │  Social-Media-Auszug             │
│                           │  Kategorien                      │
│                           │  Beitragsbild (optional)         │
│                           │  [Speichern / Veröffentlichen]   │
└───────────────────────────┴──────────────────────────────────┘
```

### Linke Seite: Originaltext

Oben stehen Titel, Name der RSS-Quelle, Veröffentlichungsdatum und der Link
**Originalartikel öffnen**. Darunter zwei Ansichten zum Umschalten:

- **Quelltext für die KI** – exakt der Text, den das Modell bekommen hat (Titel, Beschreibung,
  Inhalt und Quell-URL). *Was hier nicht steht, darf im Beitrag nicht auftauchen.* Wurde der
  Artikel gekürzt (Limit 8 000 Zeichen), steht ein Hinweis darüber.
- **Roh-HTML** – der gespeicherte Seitenquelltext der Artikelseite, als Text dargestellt (es wird
  nichts davon im Backend ausgeführt oder nachgeladen).

Konnte beim Abruf keine Artikelseite geladen werden, bekommt die KI nur Titel und RSS-Beschreibung.
Die Seite weist dann ausdrücklich darauf hin – ein dünner Beitrag ist in dem Fall erklärbar.
Lässt sich einem Top-Thema gar kein Feed-Eintrag zuordnen, erscheint ebenfalls eine Warnung: Die
KI arbeitet dann nur mit Titel und Zusammenfassung des Themas.

### Rechte Seite: Generierter Beitrag

Während die KI schreibt, liegt ein Ladeindikator mit Sekundenzähler über dem Bereich. Danach
erscheinen:

| Feld | Herkunft |
|---|---|
| **Titel** | KI, nach [Prompt: Titel](Prompts) |
| **Text** | KI, nach [Prompt: Beitragstext](Prompts) – HTML mit Zwischenüberschriften, plus Quellenhinweis |
| **Social-Media-Auszug** | KI, wird als WordPress-Auszug gespeichert |
| **Kategorien** | Vorschlag der KI aus deinen vorhandenen Kategorien |

Alles wird als reine Vorschau angezeigt, nicht als Formularfeld. Änderungen an Titel, Text, Auszug
oder Kategorien machst du nach dem Speichern wie gewohnt im WordPress-Editor.

Gefällt das Ergebnis nicht, erzeugt **Neu generieren** einen neuen Entwurf. Schlägt die
Generierung fehl, erscheint die Fehlermeldung mit einem Button **Erneut versuchen**. Nach
120 Sekunden ohne Antwort bricht die Seite mit „Die Anfrage hat zu lange gedauert“ ab.

Solange ein generierter Beitrag nicht gespeichert ist, warnt der Browser beim Verlassen der Seite.

## Beitragsbild (optional, über Pixabay)

Voraussetzung: ein Pixabay-Schlüssel unter **Einstellungen → Bilder**.

1. **Bild auswählen** öffnet die Bildsuche in einem Dialog (schließen mit *Esc*, einem Klick
   daneben oder dem ×). Die KI schlägt aus Titel und Auszug zwei bis drei Suchbegriffe vor, die
   Suche startet damit sofort.
2. Suchbegriff anpassen, Ergebnisse durchblättern (20 pro Seite), Bild anklicken.
3. **Bild entfernen** macht die Auswahl rückgängig.
4. Beim Speichern lädt WordPress das Bild in die **Mediathek** und setzt es als Beitragsbild. Als
   Alternativtext dienen die Pixabay-Schlagwörter und der Name des Fotografen.

Gesucht werden nur Fotos, mit SafeSearch und deutscher Sprache; Suchergebnisse werden eine Stunde
zwischengespeichert. Die Bilder stehen unter der Pixabay Content License, der Hinweis steht im
Dialog.

## Speichern / Veröffentlichen

Die Beschriftung des Buttons zeigt, was passiert:

| Einstellung | Button |
|---|---|
| Post-Status *Entwurf* | **Als Entwurf speichern** |
| Post-Status *Genehmigung ausstehend* | **Zur Prüfung einreichen** |
| Post-Status *Veröffentlicht* oder *Automatisch veröffentlichen* an | **Post veröffentlichen** |

Nach dem Speichern erscheint ein Erfolgskasten mit **Post bearbeiten** und **Zurück zur Übersicht**
– die Seite lädt bewusst nicht neu, damit nicht versehentlich eine zweite Generierung startet.
Autor des Beitrags ist der angemeldete Benutzer.

## Was im Beitrag gespeichert wird

- Titel, Inhalt, Auszug, Kategorien, Beitragsbild
- Unsichtbare Meta-Daten zur Herkunft (Quell-URL, Artikel-ID, Artikeltitel, Feed-Name, Zeitpunkt)
  – die Quelle ist im Editor in der Seitenbox **„AutoQuill-Quelle“** sichtbar, mit Link zum
  Originalartikel und zum AutoQuill-Dashboard
- Die Verknüpfung im Feed-Artikel, damit das Dashboard „bereits verwendet“ anzeigen kann

Details: [Datenbank → Post-Meta](Datenbank#post-meta-der-erzeugten-beiträge).

## Grenzen

- Die KI sieht nur die ersten **8 000 Zeichen** des Artikels (als Text, ohne HTML).
- Bei sehr langen Längenvorgaben im Prompt kann ein Modell mit kleinem Ausgabelimit ablehnen –
  siehe [Prompts → Beitragslänge](Prompts#beitragslänge-und-token-budget).
- Die Qualität hängt stark von der Quelle ab: Paywall-Seiten oder JavaScript-lastige Seiten liefern
  oft kaum verwertbaren Text.
- Der Quellenhinweis wird nicht doppelt gesetzt: Steht die URL des Originalartikels schon im
  generierten Text, hängt AutoQuill keinen zweiten an.
