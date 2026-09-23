# Prompts

Unter **Einstellungen → Prompts** steuerst du, *wie* die KI schreibt. Die vier Felder beschreiben nur
die **inhaltlichen Vorgaben** je Bestandteil – den Rest baut AutoQuill selbst.

## Wie die Anfrage aufgebaut ist

AutoQuill schickt **eine einzige** Anfrage an die KI:

```
[System]  Du bist ein professioneller Blog-Autor … Antworte immer im geforderten JSON-Format.

[Nutzer]  Du erstellst einen Blog-Beitrag aus folgendem Quelltext.
          Quelltext (Originalartikel): Titel, Beschreibung, Inhalt (max. 8 000 Zeichen)
          Verfügbare Kategorien: - ID 3: Technik  - ID 7: Wirtschaft …
          --- Vorgaben Titel ---          ← dein Feld „Prompt: Titel“
          --- Vorgaben Beitragstext ---   ← dein Feld „Prompt: Beitragstext“
          --- Vorgaben Auszug ---         ← dein Feld „Prompt: Social-Media-Auszug“
          --- Vorgaben Kategorien ---     ← dein Feld „Prompt: Kategorie“
          --- Antwortformat ---           ← JSON-Schema, fest eingebaut
```

Deshalb gilt: **Kein eigenes JSON-Schema und keine „Antworte mit JSON“-Hinweise** in die Felder
schreiben – das ist bereits fest eingebaut. Ist die Antwort kein gültiges JSON, fragt AutoQuill
einmal automatisch nach.

## Die vier Felder

| Feld | Steuert | Platzhalter |
|---|---|---|
| **Prompt: Titel** | Beitragstitel | `{topic_title}` – Titel des Themas / Feed-Eintrags |
| **Prompt: Beitragstext** | Länge, Aufbau, Stil, Tonalität des Beitrags | – |
| **Prompt: Social-Media-Auszug** | WordPress-Auszug (Teaser, Social Media) | – |
| **Prompt: Kategorie** | Welche und wie viele Kategorien | `{categories_list}` – deine WordPress-Kategorien mit IDs |

Ein **leeres Feld** wird beim Speichern durch den Standard ersetzt – so kommst du jederzeit zurück.

## Standard-Prompts

**Titel**
```
Vorgaben für das Feld "title":
- prägnant und klickstark, auf Deutsch
- maximal ~70 Zeichen
- keine Anführungszeichen, keine Emojis, kein Punkt am Ende
- spiegelt den Inhalt des Quelltexts wider, kein Clickbait ohne Substanz
- darf vom Ausgangsthema "{topic_title}" abweichen, wenn dadurch ein besserer Titel entsteht
```

**Beitragstext**
```
Vorgaben für das Feld "content":
- ausführlicher, professioneller Blog-Post, 800-1200 Wörter
- mit einer ansprechenden Einleitung beginnen
- 3-4 Hauptabschnitte mit Zwischenüberschriften (<h2>)
- mit einem Fazit enden
- HTML-Formatierung verwenden (aber ohne <html>, <body> etc.)
- ausschließlich Fakten aus dem obigen Quelltext verwenden und paraphrasieren (kein wörtliches Kopieren)
```

**Social-Media-Auszug**
```
Vorgaben für das Feld "excerpt":
- kurzer, für Social Media optimierter Auszug auf Deutsch
- 1-2 Sätze
- maximal ~250 Zeichen
- mit einem Hook, der zum Klicken animiert
```

**Kategorie**
```
Vorgaben für das Feld "category_ids":
- wähle 1 bis 3 IDs, die thematisch wirklich passen
- ausschließlich IDs aus der Liste der verfügbaren Kategorien ({categories_list})
- als Array von Integer-IDs
```

## Beitragslänge und Token-Budget

Es gibt **kein eigenes Längenfeld** – die Länge steht im Prompt *Beitragstext*. AutoQuill liest die
**größte Wortzahl** daraus (z. B. `800-1200 Wörter` → 1200, auch `1.500 Wörter`) und berechnet
daraus das Antwort-Budget:

```
Tokens = Wörter × 4 + 1000, mindestens 6 500, höchstens 16 000
```

Steht keine Wortzahl im Prompt, wird mit 1 200 Wörtern gerechnet. Wird die Antwort trotzdem
abgeschnitten, wiederholt AutoQuill einmal mit 1,6-fachem Budget. Modelle mit kleinem
Ausgabelimit brauchen ggf. eine Korrektur über den Filter
[`auto_quill_max_tokens`](Hooks-und-Filter).

## Beispiele für Anpassungen

**Lockerer Ton, Du-Ansprache**
```
- lockerer, persönlicher Ton, Leser mit „du“ ansprechen
- kurze Absätze, maximal 3 Sätze
```

**Kürzere Beiträge**
```
- kompakter Blog-Post, 400-600 Wörter
- 2 Abschnitte mit <h2>
```

**Zielgruppe festlegen**
```
- Zielgruppe: Inhaber kleiner Handwerksbetriebe ohne IT-Vorwissen
- Fachbegriffe beim ersten Auftreten in einem Halbsatz erklären
```

**Struktur-Elemente**
```
- nach der Einleitung eine Liste „Das Wichtigste in Kürze“ mit 3 Stichpunkten (<ul>)
- am Ende eine Frage an die Leser für die Kommentare
```

**Andere Sprache**
```
- Beitrag vollständig auf Englisch schreiben
```
(Bei einer anderen Sprache auch Titel- und Auszug-Prompt anpassen, dort steht „auf Deutsch“.)

## Tipps

- Die Zeile **„ausschließlich Fakten aus dem obigen Quelltext“** ist die wichtigste Schutzmaßnahme
  gegen erfundene Details – nicht entfernen.
- Änderungen lassen sich schnell testen: Prompt speichern, im Dashboard einen Feed-Eintrag wählen,
  generieren – ohne Veröffentlichen geht nichts live.
- Vor größeren Umbauten **Jetzt sichern** im Backup-Tab klicken.
