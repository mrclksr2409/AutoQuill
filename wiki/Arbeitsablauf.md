# Arbeitsablauf

AutoQuill teilt die Arbeit in zwei Hälften: Was **automatisch** jede Nacht passiert, und was **du**
entscheidest. Beiträge werden nie ohne deinen Klick geschrieben.

## Der Tagesablauf (Standardzeiten)

| Uhrzeit | Was passiert | Wer |
|---|---|---|
| **00:00** | RSS-Abruf: alle aktiven Quellen werden gelesen, neue Artikel gespeichert, alte aufgeräumt | automatisch |
| **01:00** | Themenauswahl: die KI bewertet die neuen Artikel und wählt die 5 besten Themen | automatisch |
| **03:00** | Sicherung von Einstellungen und RSS-Quellen | automatisch |
| **08:00** | Tagesbericht per E-Mail (nur wenn eingeschaltet und es etwas zu berichten gibt) | automatisch |
| tagsüber | Themen sichten, Beitrag generieren, prüfen, veröffentlichen | **du** |

Alle Uhrzeiten sind einstellbar, siehe [Zeitplan und Cron](Zeitplan-und-Cron).

## 1. RSS-Abruf

Für jede **aktive** Quelle:

1. Der Feed wird mit SimplePie gelesen, die **neuesten 20 Einträge** werden betrachtet.
2. Einträge, die älter als der **RSS-Rückblick** sind (Standard 7 Tage), werden übersprungen.
3. Bereits bekannte Einträge werden erkannt (Prüfsumme aus Feed-URL + Artikel-Link) und übersprungen.
4. Für neue Einträge wird zusätzlich die **Artikelseite selbst** geladen (max. 50 000 Zeichen), damit
   die KI später mehr als nur den Anreißer aus dem Feed hat.
5. Am Ende werden Artikel gelöscht, die älter als der Rückblick sind – **außer** sie sind bereits
   mit einem Beitrag verknüpft.

## 2. Themenauswahl

- Grundlage sind die bis zu **50 Artikel, die in den letzten 24 Stunden neu abgerufen** wurden.
- Die KI bekommt Titel und Beschreibung und wählt **5 Themen**. Jedes Thema erhält eine
  Bewertung von **0–100** („Wie lohnend wäre ein eigener Beitrag?“ – Relevanz, Aktualität,
  Substanz) und eine kurze Begründung.
- Die Themen werden **absteigend nach Bewertung** gespeichert und im Dashboard angezeigt.
- Pro Tag gibt es genau eine Themenliste; ein erneuter Lauf am selben Tag überschreibt sie.

> Weil nur **neu abgerufene** Artikel zählen, sollte die Themenauswahl kurz **nach** dem Abruf
> laufen. AutoQuill warnt beim Speichern, wenn das nicht der Fall ist.

## 3. Beitrag generieren (du)

Im [Dashboard](Dashboard) wählst du ein Top-Thema **oder** einen beliebigen Feed-Eintrag und
klickst **Blog-Post generieren**. Auf der [Generierungs-Seite](Blog-Post-erstellen):

1. Die KI erhält den Artikeltext (bis 8 000 Zeichen) und deine vier [Prompts](Prompts) in **einer**
   Anfrage und antwortet mit Titel, Beitragstext, Auszug und passenden Kategorien.
2. Am Ende wird serverseitig der **Quellenhinweis** angehängt.
3. Du vergleichst den Beitrag mit dem Originaltext, wählst optional ein Beitragsbild.

## 4. Veröffentlichen (du)

Ein Klick legt den WordPress-Beitrag an – als Entwurf, zur Prüfung oder veröffentlicht, je nach
Einstellung. Beitrag und Feed-Artikel bleiben dauerhaft miteinander verknüpft: Im Dashboard siehst
du, zu welchem Eintrag es schon einen Beitrag gibt, und im Beitragseditor zeigt die Box
**„AutoQuill-Quelle“** den Weg zurück zum Original.

## Manuell eingreifen

Jeder automatische Schritt lässt sich im Dashboard sofort auslösen:

| Button | Wirkung |
|---|---|
| **Feeds neu holen + Topics neu wählen** | Abruf (alle aktiven Feeds oder ein ausgewählter) und direkt danach Themenauswahl |
| **Nur Topics neu wählen** | Nur die Themenauswahl, z. B. nach Änderung des Modells |
