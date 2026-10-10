# FAQ

### Schreibt AutoQuill Beiträge vollautomatisch?
Nein. Automatisch laufen Abruf, Themenauswahl, Sicherung und Bericht. **Beiträge entstehen nur auf
deinen Klick** und werden standardmäßig als Entwurf gespeichert. Das ist Absicht: Ein Mensch sollte
jeden KI-Text gegen die Quelle prüfen.

### Welchen KI-Provider soll ich nehmen?
Zur Wahl stehen OpenAI, Claude (Anthropic), IONOS AI Model Hub (Open-Source-Modelle in
Rechenzentren in Deutschland) und jeder eigene OpenAI-kompatible Endpunkt (z. B. Mistral, Groq,
OpenRouter oder lokal Ollama). Probiere mit denselben Prompts aus und vergleiche Stil und
Genauigkeit – die Umstellung ist ein Dropdown, und jeder Provider behält seinen eigenen Schlüssel
und sein Modell. Es ist immer nur **ein** Provider gleichzeitig aktiv.

### Funktionieren lokale Modelle (Ollama, LM Studio)?
Ja, über *Eigener OpenAI-kompatibler Endpunkt* mit der Basis-URL des lokalen Servers, z. B.
`http://localhost:11434/v1`; ein Schlüssel ist dann nicht nötig. Der WordPress-Server muss den
Endpunkt erreichen können. Kleine Modelle halten das geforderte JSON-Format nicht immer ein.

### Was kostet das?
Das Plugin ist kostenlos. Kosten entstehen beim KI-Anbieter pro Anfrage. Pro Tag gibt es eine
Anfrage für die Themenauswahl und je generiertem Beitrag eine (plus eine kurze für die
Bild-Suchbegriffe; bei einer unbrauchbaren oder abgeschnittenen Antwort ggf. eine Wiederholung).
Im Interview kostet jede Frage einen kleinen Aufruf. Mit günstigen Modellen wie `gpt-4o-mini` liegt ein Beitrag im Bereich von
Cent-Bruchteilen bis wenigen Cent, größere Modelle kosten ein Vielfaches – aktuelle Preise stehen
beim Anbieter.

### Sind die Texte „original“?
Die KI formuliert neu und soll laut Standard-Prompt nur Fakten der Quelle verwenden und nicht
wörtlich übernehmen. Ob das im Einzelfall eingehalten ist, musst du prüfen – dafür steht der
Originaltext direkt daneben. Der Quellenhinweis verlinkt das Original.

### Kann ich die Länge der Beiträge ändern?
Ja, im [Prompt Beitragstext](Prompts), z. B. `400-600 Wörter`. Das Antwort-Budget passt sich
automatisch an.

### Kann ich in einer anderen Sprache schreiben lassen?
Ja – alle vier Prompts entsprechend anpassen (Titel- und Auszug-Prompt sagen standardmäßig „auf
Deutsch“), ggf. auch den Schreibstil und den Redakteurs-Prompt. Die Oberfläche des Plugins bleibt
deutsch.

### Wie stelle ich Tonalität und Anrede ein?
Über das Feld **Schreibstil** unter *Einstellungen → Prompts* – es gilt für alle Beiträge, aus
Feeds wie aus Interviews. Siehe [Prompts](Prompts#schreibstil).

### Kann ich ohne RSS-Feeds Beiträge erstellen?
Ja, mit dem [Interview-Modus](Interview): Die KI fragt dich zu einem Thema aus, aus deinen
Antworten entsteht der Beitrag.

### Wie viele Themen werden ausgewählt?
Fünf pro Tag, aus höchstens 50 neuen Artikeln der letzten 24 Stunden. Über den Tab
*Alle Feed-Einträge* kannst du aber zu **jedem** gespeicherten Artikel einen Beitrag erzeugen.

### Kann ich zu einem Artikel mehrere Beiträge erzeugen?
Ja. Die Verknüpfung im Dashboard zeigt, dass es schon einen gibt, verhindert aber keinen weiteren.

### Werden Bilder automatisch gesetzt?
Nein, nur wenn du auf der Generierungs- oder Interview-Seite eines auswählst. Dafür braucht es einen (kostenlosen)
Pixabay-Schlüssel.

### Was passiert mit alten Artikeln?
Sie werden nach Ablauf des RSS-Rückblicks (Standard 7 Tage) gelöscht – außer sie sind mit einem
Beitrag verknüpft. Die Beiträge selbst werden nie angefasst.

### Läuft AutoQuill auf Multisite?
Es ist nicht speziell dafür gebaut; pro Unterseite aktiviert, arbeitet jede Seite mit eigenen
Tabellen und Einstellungen. Netzwerkweite Aktivierung ist nicht getestet.

### Wie ziehe ich mit AutoQuill auf eine andere Seite um?
Im Backup-Tab eine Sicherung **herunterladen**, auf der neuen Seite **importieren** und
**wiederherstellen**, API-Schlüssel neu eintragen. Siehe [Backup und Wiederherstellung](Backup-und-Wiederherstellung).

### Was bleibt beim Löschen des Plugins?
Nur die erzeugten Beiträge und die in die Mediathek geladenen Bilder. Tabellen, Einstellungen,
Sicherungen und die AutoQuill-Meta der Beiträge werden entfernt.

### Wo finde ich diese Dokumentation aus WordPress heraus?
In der Plugin-Liste steht bei AutoQuill der Link **Wiki** (seit Version 1.8.1).

### Wo melde ich Fehler oder Wünsche?
Unter [Issues](https://github.com/mrclksr2409/AutoQuill/issues).
