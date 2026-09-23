# RSS-Quellen

**AutoQuill → RSS Quellen** verwaltet die Feeds, aus denen AutoQuill Artikel sammelt.

## Quelle hinzufügen

| Feld | Beschreibung |
|---|---|
| **Titel** | Anzeigename, erscheint im Dashboard, im Quellenhinweis (`{feed_name}`) und im Filter |
| **RSS-Feed URL** | Vollständige URL des Feeds (`http://` oder `https://`), RSS oder Atom |

Neue Quellen sind sofort **aktiv**. Die erste Abfrage erfolgt beim nächsten geplanten Abruf – oder
sofort über **Übersicht → Feeds neu holen**.

## Quelle löschen

**Löschen** entfernt nur den Feed-Eintrag. Bereits abgerufene Artikel bleiben bis zum nächsten
Aufräumen erhalten, erzeugte Beiträge bleiben unberührt.

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
- Pro neuem Eintrag wird die Artikelseite geladen (Timeout 10 s, max. 3 Weiterleitungen)

## Tipps zur Auswahl

- **Themenfokus** schlägt Menge: 5–10 thematisch passende Feeds liefern bessere Top-Themen als 50
  gemischte.
- Feeds mit **vollständigen Artikelseiten ohne Paywall** liefern der KI deutlich mehr Substanz.
- Feeds mit sehr vielen Einträgen pro Tag (Ticker) verdrängen andere Quellen, da die Themenauswahl
  höchstens 50 Artikel betrachtet.

## Sicherung

RSS-Quellen sind Teil jeder [Sicherung](Backup-und-Wiederherstellung). Beim Wiederherstellen werden
fehlende Quellen angelegt und vorhandene (erkannt an der URL) aktualisiert – gelöscht wird nichts.
