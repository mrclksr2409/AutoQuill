# Updates

AutoQuill aktualisiert sich über den mitgelieferten
[Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) (in `lib/`) direkt
aus dem [GitHub-Repository](https://github.com/mrclksr2409/AutoQuill) – wie ein Plugin aus dem
offiziellen Verzeichnis.

## Stabile Updates (Standard)

WordPress prüft regelmäßig den **`main`-Branch**: Steht dort eine höhere Versionsnummer im
Plugin-Header, erscheint sie unter **Dashboard → Aktualisierungen** und in der Plugin-Liste und
lässt sich mit einem Klick installieren (oder automatisch, wenn Auto-Updates für das Plugin
aktiviert sind).

GitHub-Releases und Tags werden dabei **ignoriert** – maßgeblich ist allein der aktuelle Stand des
Branches (seit Version 1.7.2).

## Beta-Modus

**Einstellungen → System → Beta-Modus**

Statt `main` folgt das Plugin dem **`beta`-Branch**, auf dem entwickelt wird: Jede höhere
Versionsnummer dort wird als Update angeboten. Sinnvoll für Testseiten, die Neuerungen früh sehen
wollen – **nicht** für Produktivseiten.

Beim Umschalten verwirft AutoQuill die zwischengespeicherte Update-Information, sodass die nächste
Prüfung sofort den neu gewählten Branch abfragt.

Zurück zu stabil: Beta-Modus ausschalten. Ein Downgrade findet nicht statt – die Seite bleibt auf
ihrer Beta-Version, bis auf `main` eine höhere Versionsnummer erscheint.

## Vor einem Update

- AutoQuill legt täglich eine [Sicherung](Backup-und-Wiederherstellung) an – vor einem größeren
  Versionssprung zusätzlich **Jetzt sichern** klicken.
- Datenbank-Änderungen werden nach dem Update beim ersten Aufruf einer Admin-Seite automatisch
  durchgeführt.
- Den Browser-Cache muss man nicht leeren: Skripte und Styles tragen die Versionsnummer.

## Welche Version ist installiert?

Die Versionsnummer steht in der Plugin-Liste und im Seitenkopf jeder AutoQuill-Seite.

## Änderungen nachlesen

Eine Zusammenfassung steht im [Changelog](Changelog), die vollständige Fassung im
[README](https://github.com/mrclksr2409/AutoQuill#changelog).

## Update wird nicht angezeigt

1. **Dashboard → Aktualisierungen → Erneut prüfen** klicken.
2. Der Plugin-Ordner sollte `auto-quill` heißen.
3. Der Server muss `github.com` und `api.github.com` erreichen können.
4. GitHub begrenzt anonyme API-Anfragen; bei vielen Seiten hinter einer IP kann die Prüfung
   vorübergehend scheitern – später erneut versuchen.
5. Im Beta-Modus? Dann kommen Updates nur vom Branch `beta`.
