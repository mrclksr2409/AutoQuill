# Updates

AutoQuill aktualisiert sich über den [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker)
direkt aus dem GitHub-Repository – wie ein Plugin aus dem offiziellen Verzeichnis.

## Stabile Updates (Standard)

WordPress prüft regelmäßig auf neue **GitHub-Releases**. Gibt es eine neuere Version, erscheint sie
unter **Dashboard → Aktualisierungen** und in der Plugin-Liste und lässt sich mit einem Klick
installieren (oder automatisch, wenn Auto-Updates für das Plugin aktiviert sind).

## Beta-Modus

**Einstellungen → System → Beta-Modus**

Statt nur offizieller Releases folgt das Plugin dem **`main`-Branch**: Jeder neue Commit dort wird
als Update angeboten. Sinnvoll für Testseiten, die Neuerungen früh sehen wollen – **nicht** für
Produktivseiten.

Zurück zu stabil: Beta-Modus ausschalten. Das nächste Release mit höherer Versionsnummer wird dann
wieder normal angeboten.

## Vor einem Update

- AutoQuill legt täglich eine [Sicherung](Backup-und-Wiederherstellung) an – vor einem größeren
  Versionssprung zusätzlich **Jetzt sichern** klicken.
- Datenbank-Änderungen werden nach dem Update beim ersten Aufruf einer Admin-Seite automatisch
  durchgeführt.
- Den Browser-Cache muss man nicht leeren: Skripte und Styles tragen die Versionsnummer.

## Änderungen nachlesen

Der **Changelog** steht im [README](https://github.com/mrclksr2409/autoquill#changelog), die
Versionen unter [Releases](https://github.com/mrclksr2409/autoquill/releases).

## Update wird nicht angezeigt

1. **Dashboard → Aktualisierungen → Erneut prüfen** klicken.
2. Der Plugin-Ordner sollte `auto-quill` heißen.
3. Der Server muss `github.com` und `api.github.com` erreichen können.
4. GitHub begrenzt anonyme API-Anfragen; bei vielen Seiten hinter einer IP kann die Prüfung
   vorübergehend scheitern – später erneut versuchen.
