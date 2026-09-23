# Sicherheit und Datenschutz

## Zugriffsrechte

Alle AutoQuill-Seiten, Aktionen und REST-Endpunkte erfordern die Berechtigung `manage_options`
(Administratoren). Formulare und Aktionen sind mit WordPress-Nonces gegen fremd ausgelöste Anfragen
(CSRF) geschützt, die REST-Endpunkte über den Cookie-Nonce des angemeldeten Benutzers.

## API-Schlüssel

| Wo | Schutz |
|---|---|
| **`wp-config.php`** (empfohlen) | `AUTO_QUILL_AI_KEY`, `AUTO_QUILL_PIXABAY_KEY` – nie in der Datenbank, haben Vorrang vor dem Formular |
| **Einstellungsformular** | In der Datenbank (`wp_options`), im Formular nie wieder angezeigt (Passwortfeld bleibt leer) |
| **Status-Panel** | Maskiert, z. B. `sk-•••••••abc` |
| **Interne Sicherungen** | Enthalten, liegen in der Datenbank |
| **Heruntergeladene Sicherungen** | **Nicht** enthalten |
| **Zwischengespeicherte Modelllisten** | Nur ein Hash des Schlüssels im Namen, nie der Schlüssel selbst |

Die Schlüssel werden in der Datenbank **nicht verschlüsselt** gespeichert. Wer Lesezugriff auf die
Datenbank hat, kann sie lesen – daher die Empfehlung für `wp-config.php`.

## Welche Daten verlassen den Server?

| Empfänger | Was | Wann |
|---|---|---|
| **OpenAI** oder **Anthropic** | Titel und Beschreibungen der neuen Artikel (Themenauswahl); Artikeltext bis 8 000 Zeichen, deine Prompts und die Namen deiner Kategorien (Beitrag); Titel und Auszug (Bild-Suchbegriffe) | Themenauswahl, Generierung |
| **OpenAI** oder **Anthropic** | Nur der API-Schlüssel, zum Abruf der Modellliste | Einstellungsseite |
| **Pixabay** | Suchbegriffe | Bildsuche |
| **RSS-Quellen / Artikelseiten** | Normaler Seitenabruf mit User-Agent `AutoQuill/<Version>` | RSS-Abruf |
| **GitHub** | Versionsabfrage | Update-Prüfung |
| **Mailserver** | Tagesbericht | wenn eingeschaltet |

Es werden **keine Besucherdaten** deiner Website übertragen. AutoQuill setzt keine Cookies im Frontend
und bindet dort nichts ein.

Für die Datenschutzerklärung relevant sind in erster Linie **Beitragsbilder von Pixabay**: Sie werden
in die eigene Mediathek geladen und von dort ausgeliefert – es entsteht keine Verbindung zwischen
Besuchern und Pixabay.

## Urheberrecht und Inhalte

- Der Standard-Prompt verlangt, **nur Fakten aus der Quelle** zu verwenden und **zu paraphrasieren
  statt zu kopieren**. Das ist eine Anweisung an die KI, keine Garantie – prüfe jeden Beitrag auf der
  Generierungs-Seite gegen den Originaltext.
- Der **Quellenhinweis** verlinkt das Original mit `rel="nofollow noopener"`.
- Beiträge werden standardmäßig als **Entwurf** angelegt. Bewusst: Ein Mensch sollte vor der
  Veröffentlichung draufschauen.
- Pixabay-Bilder unterliegen der Pixabay Content License.

## Eingaben und Ausgaben

- Alle Einstellungen werden beim Speichern (und beim Wiederherstellen) validiert: Uhrzeiten,
  Zahlenbereiche, Modell-IDs, E-Mail-Adressen, Feed-URLs.
- Von der KI erzeugtes HTML wird mit `wp_kses_post()` gefiltert, bevor es angezeigt oder
  gespeichert wird.
- Artikelseiten werden mit `wp_safe_remote_get()` geladen, das Anfragen an interne Adressen blockiert.
