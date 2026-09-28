# Interview

Unter **AutoQuill → Interview** entsteht ein Beitrag nicht aus einem RSS-Artikel, sondern aus
**deinem Wissen**: Die KI übernimmt die Rolle eines Redakteurs, stellt dir nacheinander Fragen zu
einem Thema, du antwortest im Chat – und aus deinen Antworten schreibt die KI anschließend einen
Blog-Beitrag.

## Ablauf

1. **Thema eingeben** – z. B. „Meine Erfahrungen mit Homeoffice im Handwerk“. Optional kannst du
   dem Redakteur Hinweise mitgeben: Zielgruppe, Stichpunkte, was unbedingt vorkommen soll.
2. **Interview starten** – die KI stellt die erste Frage.
3. **Antworten** – ins Textfeld schreiben und auf *Antworten* klicken (oder **Strg+Enter**).
   Der Redakteur hakt nach, wenn eine Antwort vage bleibt, und arbeitet sich durch verschiedene
   Seiten des Themas.
   - **Frage überspringen** – die Frage bleibt im Verlauf, zählt aber nicht als Antwort.
   - **Andere Frage** – die offene Frage wird durch eine neue ersetzt.
4. **Genug Material** – nach dem eingestellten Richtwert (Standard: 6 Antworten) meldet der
   Redakteur, dass er genug für einen Beitrag hat. Du kannst trotzdem weiter antworten.
5. **Beitrag schreiben** – ab **3 Antworten** möglich. Rechts erscheinen wie auf der
   [Generierungs-Seite](Blog-Post-erstellen) Text, Titel, Social-Media-Auszug, Kategorien und die
   Bildauswahl.
6. **Speichern** – wie gewohnt als Entwurf oder direkt veröffentlicht (je nach
   [Einstellungen → Veröffentlichung](Einstellungen#tab-veröffentlichung)).

Gefällt der Beitrag nicht, einfach weitere Fragen beantworten und **Beitrag neu schreiben**.

## Interviews fortsetzen

Jedes Interview wird mit dem kompletten Gesprächsverlauf gespeichert. Die Liste unter
*AutoQuill → Interview* zeigt Thema, Anzahl Antworten, Status und – falls schon gespeichert – den
Link zum Beitrag. Mit **Fortsetzen** geht es genau dort weiter, wo du aufgehört hast; ein
Neuladen der Seite verliert nichts.

| Status | Bedeutung |
|---|---|
| Läuft | Noch kein Beitrag geschrieben |
| Beitrag entworfen | Die KI hat mindestens einmal einen Beitrag geschrieben, gespeichert wurde er noch nicht |
| Beitrag gespeichert | Der Beitrag liegt in WordPress; Post-Meta `_auto_quill_interview_id` verweist zurück |

Löschen entfernt nur das Interview, ein bereits gespeicherter Beitrag bleibt erhalten.

## Perspektive des Beitrags

Eingestellt unter [Einstellungen → Interview](Einstellungen#tab-interview):

| Perspektive | Ergebnis |
|---|---|
| **Ich-Perspektive** (Standard) | Der Beitrag klingt wie dein eigener Blog-Post; die Fragen verschwinden. |
| **Redaktioneller Artikel mit Zitaten** | Dritte Person, einige deiner Aussagen als wörtliche Zitate. |
| **Frage-Antwort-Interview** | Kurze Einleitung, dann die Fragen als Zwischenüberschriften mit deinen Antworten. |

Länge, Titel, Auszug und Kategorien steuern weiterhin die [Prompts](Prompts). Die KI ist
angewiesen, **nichts hinzuzuerfinden**: Reicht das Material nicht für die vorgegebene Länge, wird
der Beitrag kürzer. Mehr und konkretere Antworten (Beispiele, Zahlen, Erlebnisse) ergeben einen
besseren Beitrag.

## Fehler

- Scheitert die nächste Frage (z. B. API-Fehler), ist deine Antwort **trotzdem gespeichert**.
  Ein Klick auf *Frage erneut anfordern* holt die Frage nach.
- Scheitert das Senden selbst, bleibt dein Text im Eingabefeld stehen.
- Details stehen unter *AutoQuill → Logs* (Quelle `interview`).

## Kosten

Jede Frage ist ein kleiner KI-Aufruf (einige hundert Token Antwort, der Verlauf wächst mit), das
Schreiben des Beitrags ein großer – vergleichbar mit einem Beitrag aus einem Feed-Eintrag.
