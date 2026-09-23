# Schnellstart

In fünf Schritten vom frisch installierten Plugin zum ersten Beitrag.

## 1. KI-Provider einrichten

**AutoQuill → Einstellungen → Tab „KI-Provider“**

1. **KI-Provider** wählen: *OpenAI* oder *Claude (Anthropic)*.
2. **API-Schlüssel** eintragen.
3. Beim **Modell** auf **Modelle neu laden** klicken – die Liste kommt direkt vom Anbieter.
   Ohne besonderen Grund kannst du den Standard lassen (`gpt-4o-mini` bzw. `claude-sonnet-4-6`).
4. **Änderungen speichern**.

> Kommt eine Meldung wie „Der API-Schlüssel wurde abgelehnt“, passt der Schlüssel meist nicht zum
> gewählten Provider (z. B. OpenAI-Schlüssel bei ausgewähltem Claude).

## 2. RSS-Quellen hinzufügen

**AutoQuill → RSS Quellen**

Name und Feed-URL eintragen, z. B.:

| Name | Feed-URL |
|---|---|
| Heise | `https://www.heise.de/rss/heise-atom.xml` |
| t3n | `https://t3n.de/rss.xml` |

Mehr dazu unter [RSS-Quellen](RSS-Quellen).

## 3. Themen sofort erzeugen

Du musst nicht bis zur nächsten Nacht warten:
**AutoQuill → Übersicht → „Feeds neu holen + Topics neu wählen“**.

Nach ein bis zwei Minuten erscheinen im Tab **Top-Themen** bis zu fünf Themen, jeweils mit
Bewertung (0–100) und kurzer Begründung.

## 4. Beitrag generieren

Bei einem Thema auf **Blog-Post generieren** klicken. Die Generierungs-Seite öffnet sich und die
KI beginnt sofort zu schreiben (je nach Modell 20–90 Sekunden).

Links steht der Originaltext, rechts der neue Beitrag – so kannst du prüfen, dass nichts
erfunden wurde. Optional wählst du ein Beitragsbild. Details: [Blog-Post erstellen](Blog-Post-erstellen).

## 5. Speichern oder veröffentlichen

Der Button heißt je nach Einstellung *Als Entwurf speichern*, *Zur Prüfung einreichen* oder
*Post veröffentlichen*. Standard ist **Entwurf** – du kannst den Beitrag danach ganz normal im
WordPress-Editor bearbeiten.

## Empfohlene nächste Schritte

- [Prompts](Prompts) an deinen Stil anpassen (Tonalität, Länge, Zielgruppe)
- [Zeitplan](Zeitplan-und-Cron) prüfen – und bei wenig Besucherverkehr einen echten Server-Cron einrichten
- [Tagesbericht](Tagesbericht) einschalten, um Fehler mitzubekommen
- Unter **Einstellungen → Bilder** einen kostenlosen Pixabay-Schlüssel hinterlegen
