# Tagesbericht

AutoQuill kann dir einmal täglich per E-Mail zusammenfassen, was seit der letzten Mail passiert ist.
Einrichtung unter **Einstellungen → Benachrichtigungen**.

## Einrichten

1. **Täglich eine Zusammenfassung per E-Mail senden** ankreuzen.
2. **Uhrzeit** wählen (Ortszeit der Seite, Standard 08:00).
3. **Inhalte** auswählen:
   - *Neue Top-Themen* – die Themen der Themenauswahl, mit Bewertung
   - *Fehler und Warnungen* – aus dem Log, gebündelt
   - *Erstellte Blog-Posts* – mit Status und Bearbeiten-Link
4. **Empfänger** festlegen: Administratoren ankreuzen und/oder unter **Weitere Adressen** eine
   Adresse pro Zeile eintragen (auch Verteiler ohne WordPress-Konto). Insgesamt gehen höchstens
   50 Mails pro Bericht raus; doppelte Adressen werden zusammengefasst.
5. Speichern – danach **Test-Mail an alle Empfänger senden**. Der Test verschickt sofort den
   Bericht der letzten 24 Stunden oder, wenn es nichts zu berichten gibt, eine kurze Bestätigung.
   Die Meldung danach nennt, an wie viele Empfänger die Mail rausging.

## Inhalt und Form

- Betreff: `[Seitenname] AutoQuill Tagesbericht – TT.MM.JJJJ`
- Reiner Text (keine HTML-Mail), damit keine fremden Plugin-Mails beeinflusst werden
- **Eine Mail pro Empfänger** – niemand sieht die Adressen der anderen
- Neue Top-Themen je Tag mit Bewertung in eckigen Klammern und Link zur Übersicht
- Erstellte Blog-Posts mit Status und Bearbeiten-Link (höchstens 50)
- Wiederholte Fehler werden gebündelt, z. B. „13x [ERROR] client: OpenAI API-Fehler“ mit erstem und
  letztem Auftreten; Fehler stehen vor Warnungen, jeweils nach Häufigkeit sortiert. Angezeigt
  werden höchstens 15 Arten, der Rest als „… und N weitere Arten von Meldungen“

**Gibt es nichts zu berichten, wird nichts verschickt.**

## Gut zu wissen

- **Benutzer werden über ihre ID gespeichert**, nicht über die Adresse. Ändert jemand seine
  Mailadresse, gilt die neue sofort. Gelöschte Benutzer oder solche ohne Zugriff auf AutoQuill
  bekommen keine Mail mehr (mit Warnung im Log).
- **Der Berichtszeitraum** reicht von der letzten Mail bis jetzt, höchstens 7 Tage.
- **Fehler kann der Bericht nur melden, solange sie im Log stehen.** Das Log hält maximal 7 Tage
  bzw. 500 Einträge und löscht die ältesten zuerst – bei aktivem Debug-Logging können viele
  Info-Einträge ältere Fehler verdrängen. Der Bericht schreibt dann „mindestens N“.
- **Erstellte Beiträge** werden erst ab Version 1.4.0 erfasst.
- Aktiv, aber ohne Empfänger? Beim Speichern erscheint eine Warnung.

## Die Mail kommt nicht an

WordPress verschickt Mails standardmäßig über die PHP-Funktion `mail()`, die bei vielen Hostern
unzuverlässig ist oder im Spam landet.

1. **Test-Mail** senden. Meldet AutoQuill „Versand fehlgeschlagen“, steht der Grund unter
   **AutoQuill → Logs** (Quelle `notifier`).
2. Ein SMTP-Plugin einrichten (z. B. *WP Mail SMTP*, *FluentSMTP*) und über den eigenen Mailserver
   versenden.
3. Spam-Ordner prüfen; Absender ist die Standard-Absenderadresse der Seite.
4. Kommt die Test-Mail an, der tägliche Bericht aber nicht: [WP-Cron prüfen](Zeitplan-und-Cron#prüfen-ob-cron-läuft).
   Der nächste Termin steht unter **Einstellungen → System → Status**.
