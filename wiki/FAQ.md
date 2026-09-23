# FAQ

### Schreibt AutoQuill Beiträge vollautomatisch?
Nein. Automatisch laufen Abruf, Themenauswahl, Sicherung und Bericht. **Beiträge entstehen nur auf
deinen Klick** und werden standardmäßig als Entwurf gespeichert. Das ist Absicht: Ein Mensch sollte
jeden KI-Text gegen die Quelle prüfen.

### Welcher Provider ist besser, OpenAI oder Claude?
Beide funktionieren gleichwertig. Probiere mit denselben Prompts beide aus und vergleiche Stil und
Genauigkeit – die Umstellung ist ein Dropdown. Es ist immer nur **ein** Provider pro Installation aktiv.

### Was kostet das?
Das Plugin ist kostenlos. Kosten entstehen beim KI-Anbieter pro Anfrage. Pro Tag gibt es eine
Anfrage für die Themenauswahl und je generiertem Beitrag eine (plus eine kurze für die
Bild-Suchbegriffe). Mit günstigen Modellen wie `gpt-4o-mini` liegt ein Beitrag im Bereich von
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
Deutsch“). Die Oberfläche des Plugins bleibt deutsch.

### Wie viele Themen werden ausgewählt?
Fünf pro Tag, aus höchstens 50 neuen Artikeln der letzten 24 Stunden. Über den Tab
*Alle Feed-Einträge* kannst du aber zu **jedem** gespeicherten Artikel einen Beitrag erzeugen.

### Kann ich zu einem Artikel mehrere Beiträge erzeugen?
Ja. Die Verknüpfung im Dashboard zeigt, dass es schon einen gibt, verhindert aber keinen weiteren.

### Werden Bilder automatisch gesetzt?
Nein, nur wenn du auf der Generierungs-Seite eines auswählst. Dafür braucht es einen (kostenlosen)
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

### Wo melde ich Fehler oder Wünsche?
Unter [Issues](https://github.com/mrclksr2409/autoquill/issues).
