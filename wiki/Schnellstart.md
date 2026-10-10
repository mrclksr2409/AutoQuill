# Schnellstart

In fünf Schritten vom frisch installierten Plugin zum ersten Beitrag.

## 1. KI-Provider einrichten

**AutoQuill → Einstellungen → Tab „KI-Provider“**

1. **KI-Provider** wählen: *OpenAI*, *Claude (Anthropic)*, *IONOS AI Model Hub* oder
   *Eigener OpenAI-kompatibler Endpunkt*. Es werden nur die Felder des gewählten Providers angezeigt.
2. **API-Schlüssel** eintragen (beim eigenen Endpunkt zusätzlich die **Basis-URL**, z. B.
   `https://api.mistral.ai/v1` oder `http://localhost:11434/v1` für Ollama; der Schlüssel ist dort
   optional).
3. Beim **Modell** lädt AutoQuill die Liste direkt beim Anbieter, sobald die Seite geöffnet oder
   ein neuer Schlüssel eingetippt wird; **Modelle neu laden** erzwingt einen frischen Abruf.
   Ohne besonderen Grund kannst du den Standard lassen (`gpt-4o-mini`, `claude-sonnet-4-6` bzw.
   `meta-llama/Llama-3.3-70B-Instruct`). Beim eigenen Endpunkt trägst du die Modell-ID ein.
4. **Änderungen speichern**.

> Kommt eine Meldung wie „Der API-Schlüssel wurde von … abgelehnt“, passt der Schlüssel meist
> nicht zum gewählten Provider (z. B. OpenAI-Schlüssel bei ausgewähltem Claude).

## 2. RSS-Quellen hinzufügen

**AutoQuill → RSS Quellen**

Titel und Feed-URL eintragen, z. B.:

| Titel | RSS-Feed URL |
|---|---|
| Heise | `https://www.heise.de/rss/heise-atom.xml` |
| t3n | `https://t3n.de/rss.xml` |

Mehr dazu unter [RSS-Quellen](RSS-Quellen).

## 3. Themen sofort erzeugen

Du musst nicht bis zur nächsten Nacht warten: Im Menü auf **AutoQuill** klicken (die Übersicht),
im Tab **Top-Themen** auf **Feeds neu holen + Topics neu wählen**.

Nach ein bis zwei Minuten lädt die Seite neu und zeigt bis zu fünf Themen, jeweils mit
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

- [Prompts](Prompts) an deinen Stil anpassen – am einfachsten über das Feld **Schreibstil**
  (Tonalität, Anrede), die Länge über *Prompt: Beitragstext*
- [Zeitplan](Zeitplan-und-Cron) prüfen – und bei wenig Besucherverkehr einen echten Server-Cron einrichten
- [Tagesbericht](Tagesbericht) einschalten, um Fehler mitzubekommen
- Unter **Einstellungen → Bilder** einen kostenlosen Pixabay-Schlüssel hinterlegen
- Den [Interview-Modus](Interview) ausprobieren: Beiträge aus deinem eigenen Wissen statt aus Feeds
