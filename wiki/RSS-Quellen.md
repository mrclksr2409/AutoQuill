# RSS-Quellen

**AutoQuill → RSS Quellen** verwaltet die Feeds, aus denen AutoQuill Artikel sammelt. Die Seite hat
zwei Karten: *Neue RSS-Quelle hinzufügen* und *Bestehende RSS-Quellen*.

## Quelle hinzufügen

| Feld | Beschreibung |
|---|---|
| **Titel** | Anzeigename, erscheint im Dashboard, im Quellenhinweis (`{feed_name}`), in der Box „AutoQuill-Quelle“ und im Filter |
| **RSS-Feed URL** | Vollständige URL des Feeds (nur `http://` oder `https://`), RSS oder Atom |

Beide Felder sind Pflicht; ungültige URLs werden mit „Ungültige Eingabe.“ abgelehnt. Neue Quellen
sind sofort **aktiv**. Die erste Abfrage erfolgt beim nächsten geplanten Abruf – oder sofort über
die Übersicht (**Feeds neu holen + Topics neu wählen**).

## Bestehende Quellen

Die Tabelle zeigt Titel, Feed-URL, **Status** (*Aktiv* / *Inaktiv*) und die Aktion **Löschen**.
Abgerufen werden nur aktive Quellen. Einen Schalter zum Deaktivieren gibt es in der Oberfläche
nicht; der Aktiv-Status kommt nur beim Wiederherstellen einer [Sicherung](Backup-und-Wiederherstellung)
aus der Sicherung.

**Löschen** (mit Sicherheitsabfrage) entfernt nur den Feed-Eintrag. Bereits abgerufene Artikel
bleiben bis zum nächsten Aufräumen erhalten, erzeugte Beiträge bleiben unberührt.

## Die richtige Feed-URL finden

- Oft unter `/feed`, `/rss`, `/rss.xml` oder `/atom.xml` der Website
- Im Seitenquelltext nach `application/rss+xml` suchen
- WordPress-Seiten haben fast immer `https://example.com/feed/`
- Die URL im Browser öffnen: Es sollte XML erscheinen, keine HTML-Seite

## Was pro Abruf verarbeitet wird

- Die **20 neuesten** Einträge je Feed
- Einträge älter als der **RSS-Rückblick** (Einstellungen → Feeds & Zeitplan, Standard 7 Tage) werden
  ignoriert und später aus der Datenbank entfernt; `0` = unbegrenzt
- Duplikate werden über Feed-URL + Artikel-Link erkannt, auch wenn sich der Titel ändert
- Die Beschreibung aus dem Feed wird ohne HTML gespeichert (max. 1 000 Zeichen)
- Pro neuem Eintrag wird die Artikelseite geladen (Timeout 10 s, max. 3 Weiterleitungen,
  User-Agent `AutoQuill/<Version>`, max. 50 000 Zeichen)

## Tipps zur Auswahl

- **Themenfokus** schlägt Menge: 5–10 thematisch passende Feeds liefern bessere Top-Themen als 50
  gemischte.
- Feeds mit **vollständigen Artikelseiten ohne Paywall** liefern der KI deutlich mehr Substanz.
- Feeds mit sehr vielen Einträgen pro Tag (Ticker) verdrängen andere Quellen, da die Themenauswahl
  höchstens 50 Artikel betrachtet.

## Sicherung

RSS-Quellen sind Teil jeder [Sicherung](Backup-und-Wiederherstellung). Beim Wiederherstellen werden
fehlende Quellen angelegt und vorhandene (erkannt an der URL) aktualisiert – gelöscht wird nichts.
