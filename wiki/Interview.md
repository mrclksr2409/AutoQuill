# Interview

Unter **AutoQuill → Interview** (oder über **Interview starten** auf der Übersicht) entsteht ein
Beitrag nicht aus einem RSS-Artikel, sondern aus **deinem Wissen**: Die KI übernimmt die Rolle
eines Redakteurs, stellt dir nacheinander Fragen zu einem Thema, du antwortest im Chat – und aus
deinen Antworten schreibt die KI anschließend einen Blog-Beitrag.

## Ablauf

1. **Thema eingeben** (max. 200 Zeichen) – z. B. „Meine Erfahrungen mit Homeoffice im Handwerk“.
   Optional kannst du unter **Hinweise für den Redakteur** (max. 2 000 Zeichen) Zielgruppe,
   Stichpunkte oder Pflichtinhalte mitgeben.
2. **Interview starten** – die KI stellt die erste Frage.
3. **Antworten** – ins Textfeld schreiben (max. 4 000 Zeichen, ein Zähler zeigt den Stand) und auf
   *Antworten* klicken (oder **Strg+Enter** bzw. **⌘+Enter**). Der Redakteur hakt nach, wenn eine
   Antwort vage bleibt, und arbeitet sich durch verschiedene Seiten des Themas. Eine Fortschritts-
   anzeige zeigt „x von ca. y Fragen beantwortet“.
   - **Frage überspringen** – die Frage bleibt im Verlauf, zählt aber nicht als Antwort.
   - **Andere Frage** – die offene Frage wird durch eine neue ersetzt.
4. **Genug Material** – spätestens beim eingestellten Richtwert (Standard: 6 Antworten) meldet der
   Redakteur, dass er genug für einen Beitrag hat. Ab 3 Antworten darf die KI das auch früher
   melden, wenn sie das Material für ausreichend hält. Du kannst trotzdem weiter antworten.
5. **Beitrag schreiben** – ab **3 Antworten** möglich. Rechts erscheinen wie auf der
   [Generierungs-Seite](Blog-Post-erstellen) Titel, Text, Social-Media-Auszug, Kategorien und die
   Bildauswahl.
6. **Speichern** – wie gewohnt als Entwurf oder direkt veröffentlicht (je nach
   [Einstellungen → Veröffentlichung](Einstellungen#tab-veröffentlichung)).

Gefällt der Beitrag nicht, einfach weitere Fragen beantworten und **Beitrag neu schreiben** (ein
noch nicht gespeicherter Entwurf wird nach Rückfrage ersetzt).

Ein Interview-Beitrag bekommt **keinen Quellenhinweis** – es gibt keinen Originalartikel, auf den
verlinkt werden könnte.

## Interviews fortsetzen

Jedes Interview wird mit dem kompletten Gesprächsverlauf gespeichert. Die Liste
**Bisherige Interviews** zeigt die 50 zuletzt bearbeiteten:

| Spalte | Inhalt |
|---|---|
| **Thema** | Thema des Interviews |
| **Antworten** | Anzahl beantworteter (nicht übersprungener) Fragen |
| **Status** | siehe unten; bei gespeichertem Beitrag mit Link **Post bearbeiten** |
| **Zuletzt bearbeitet** | Zeitpunkt der letzten Änderung |
| **Aktionen** | **Fortsetzen** (laufend) bzw. **Öffnen**, **Löschen** |

Mit **Fortsetzen** geht es genau dort weiter, wo du aufgehört hast; ein Neuladen der Seite verliert
nichts. **Zurück zu den Interviews** im Seitenkopf führt zur Liste.

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
| **Frage-Antwort-Interview** | Kurze Einleitung, dann die Fragen als Zwischenüberschriften mit deinen (behutsam gekürzten) Antworten; übersprungene Fragen entfallen. |

Länge, Titel, Auszug und Kategorien steuern weiterhin die [Prompts](Prompts), Tonalität und Anrede
der **Schreibstil** – bei Widersprüchen hat die Perspektive Vorrang. Die KI ist angewiesen,
**nichts hinzuzuerfinden**: Reicht das Material nicht für die vorgegebene Länge, wird der Beitrag
kürzer. Mehr und konkretere Antworten (Beispiele, Zahlen, Erlebnisse) ergeben einen besseren
Beitrag.

Wie der Redakteur fragt (Rolle, Tonfall, Du/Sie, Schwerpunkte), legt das Feld
**Prompt: Redakteur** im selben Tab fest.

## Fehler

- Scheitert die nächste Frage (z. B. API-Fehler), ist deine Antwort **trotzdem gespeichert**.
  Ein Klick auf *Frage erneut anfordern* holt die Frage nach.
- Scheitert das Senden selbst, bleibt dein Text im Eingabefeld stehen.
- Details stehen unter *AutoQuill → Logs* (Quelle `interview`).

## Kosten

Jede Frage ist ein kleiner KI-Aufruf (bis 800 Token Antwort, der Verlauf wächst mit), das
Schreiben des Beitrags ein großer – vergleichbar mit einem Beitrag aus einem Feed-Eintrag.
