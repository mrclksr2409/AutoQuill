# Einstellungen

**AutoQuill → Einstellungen** ist in neun Tabs gegliedert – in der Reihenfolge, in der man das Plugin
einrichtet. Ein Klick auf **Änderungen speichern** speichert **alle** Tabs auf einmal; danach bleibt
der zuletzt geöffnete Tab aktiv.

| Tab | Inhalt |
|---|---|
| [KI-Provider](#tab-ki-provider) | Anbieter, API-Schlüssel, Modell |
| [Feeds & Zeitplan](#tab-feeds--zeitplan) | Uhrzeiten für Abruf und Themenauswahl, RSS-Rückblick |
| [Prompts](#tab-prompts) | Vorgaben für Titel, Text, Auszug, Kategorien |
| [Interview](#tab-interview) | Perspektive, Richtwert Fragen, Redakteurs-Prompt |
| [Veröffentlichung](#tab-veröffentlichung) | Post-Status, Quellenhinweis |
| [Bilder](#tab-bilder) | Pixabay-Schlüssel |
| [Benachrichtigungen](#tab-benachrichtigungen) | Tagesbericht per E-Mail |
| [Backup](#tab-backup) | Automatische Sicherung, Liste, Import |
| [System](#tab-system) | Beta-Updates, Debug-Logging, Status |

---

## Tab: KI-Provider

| Einstellung | Beschreibung | Standard |
|---|---|---|
| **KI-Provider** | *OpenAI*, *Claude (Anthropic)*, *IONOS AI Model Hub* oder *Eigener OpenAI-kompatibler Endpunkt*. Gilt für Themenauswahl, Beitrag, Interview und Bild-Suchbegriffe. Es werden nur die Felder des gewählten Providers angezeigt. | OpenAI |
| **API-Schlüssel (…)** | Je Provider ein eigener Schlüssel – ein Wechsel des Providers verliert also nichts. Nach dem Speichern bleibt das Feld leer – leer lassen heißt *gespeicherten Schlüssel behalten*. Ist `AUTO_QUILL_AI_KEY` in der `wp-config.php` gesetzt, gilt er für jeden Provider und die Felder sind gesperrt. Beim eigenen Endpunkt ist der Schlüssel optional. | – |
| **Basis-URL** (nur eigener Endpunkt) | Adresse bis einschließlich Versionspfad, ohne `/chat/completions`, z. B. `https://api.mistral.ai/v1`, `https://api.groq.com/openai/v1`, `https://openrouter.ai/api/v1` oder `http://localhost:11434/v1` (Ollama). | – |
| **Modell** | Dropdown (OpenAI, Claude, IONOS) bzw. Textfeld mit Vorschlägen (eigener Endpunkt). | `gpt-4o-mini` / `claude-sonnet-4-6` / `meta-llama/Llama-3.3-70B-Instruct` / – |

### IONOS AI Model Hub

Open-Source-Modelle (Llama, Mistral …) in Rechenzentren in Deutschland, über eine
OpenAI-kompatible Schnittstelle (`https://openai.inference.de-txl.ionos.com/v1`). Den Token
erzeugst du im IONOS *Data Center Designer* unter **Token Manager**.

### Eigener OpenAI-kompatibler Endpunkt

Für jeden Dienst, der `/chat/completions` im OpenAI-Format anbietet – Cloud-Anbieter wie Mistral,
Groq, OpenRouter, Together oder lokale Server wie Ollama und LM Studio. Unterstützt der Endpunkt
`/models`, erscheinen die Modelle als Vorschläge; sonst trägst du die Modell-ID von Hand ein.
Kleine lokale Modelle halten das geforderte JSON-Format nicht immer zuverlässig ein – bei
Parse-Fehlern ein größeres Modell wählen.

> Upgrade von 1.6: Der bisherige gemeinsame Schlüssel wird automatisch dem damals gewählten
> Provider zugeordnet.

### Wie die Modellliste entsteht

- Beim Öffnen der Seite lädt AutoQuill die Liste mit deinem Schlüssel **direkt beim Anbieter**
  und speichert sie **12 Stunden** zwischen. **Modelle neu laden** holt sie sofort frisch.
- Tippst du einen neuen Schlüssel ein, wird die Liste damit neu geladen – schon vor dem Speichern.
- Bei OpenAI werden Modelle ausgefiltert, die keinen Text erzeugen (Embeddings, Audio, Bild,
  Moderation …); bei IONOS und eigenen Endpunkten offensichtliche Embedding- und Bildmodelle.
- Das **gespeicherte Modell bleibt immer auswählbar**, auch wenn der Anbieter es nicht mehr listet
  (Zusatz „aktuell gespeichert, nicht in der Liste“). Ein fehlgeschlagener Abruf ändert also nie
  deine Einstellung.

### Welches Modell?

| Ziel | Empfehlung |
|---|---|
| Günstig, schnell, gute Qualität | `gpt-4o-mini` bzw. ein aktuelles Claude-Sonnet- oder Haiku-Modell |
| Bestmögliche Texte | die größeren Modelle des Anbieters (höhere Kosten, längere Laufzeit) |

> **Reasoning-Modelle** (OpenAI o-Serie, `gpt-5*`): Diese „denken“ vor der Antwort, und dieses
> Denken zählt ins selbe Token-Budget. Bei langen Beiträgen kann die Antwort dann abgeschnitten
> werden. AutoQuill wiederholt in dem Fall einmal mit größerem Budget; bleibt es dabei, ein
> klassisches Chat-Modell wählen oder das Budget per [Filter](Hooks-und-Filter) erhöhen.

---

## Tab: Feeds & Zeitplan

| Einstellung | Beschreibung | Standard |
|---|---|---|
| **RSS-Abruf** | Uhrzeit des täglichen Feed-Abrufs (Ortszeit der Seite) | 00:00 |
| **Themenauswahl** | Uhrzeit der täglichen KI-Themenauswahl | 01:00 |
| **RSS-Rückblick (Tage)** | Ältere Feed-Einträge werden nicht übernommen und aus der Datenbank entfernt (verknüpfte ausgenommen). `0` = unbegrenzt. | 7 |

Die Themenauswahl betrachtet die Artikel der letzten 24 Stunden und sollte daher **bis zu 12 Stunden
nach** dem Abruf liegen. Andernfalls erscheint beim Speichern ein Hinweis. Hintergründe:
[Zeitplan und Cron](Zeitplan-und-Cron).

---

## Tab: Prompts

Vier Textfelder, die zu **einer** KI-Anfrage zusammengesetzt werden: *Titel*, *Beitragstext*,
*Social-Media-Auszug*, *Kategorie*. Ein leeres Feld stellt den Standard wieder her.
Ausführlich mit Beispielen: **[Prompts](Prompts)**.

---

## Tab: Interview

Einstellungen für **AutoQuill → Interview** (siehe [Interview](Interview)).

| Feld | Bedeutung | Standard |
|---|---|---|
| **Perspektive des Beitrags** | *Ich-Perspektive* (dein eigener Blog-Post), *Redaktioneller Artikel mit Zitaten* (dritte Person) oder *Frage-Antwort-Interview* | Ich-Perspektive |
| **Richtwert Fragen** | Nach etwa so vielen Antworten meldet der Redakteur „genug Material“ (3–15). Schreiben lassen geht ab 3 Antworten. | 6 |
| **Prompt: Redakteur** | Rolle und Fragestil der KI. Thema, Hinweise, Verlauf und Antwortformat ergänzt AutoQuill selbst. Leer speichern = Standard. | siehe Tab |

Titel, Länge, Auszug und Kategorien des Interview-Beitrags folgen weiter dem Tab *Prompts*.

---

## Tab: Veröffentlichung

| Einstellung | Beschreibung | Standard |
|---|---|---|
| **Standard Post-Status** | *Entwurf*, *Veröffentlicht* oder *Genehmigung ausstehend* | Entwurf |
| **Automatisch veröffentlichen** | Jeder Beitrag wird sofort veröffentlicht – **überschreibt** den Post-Status | aus |
| **Link zum Originalartikel** | Hängt jedem Beitrag einen Quellenhinweis an | an |
| **Text des Quellenhinweises** | Reiner Text mit Platzhaltern | `Quelle: {source_link}` |

### Platzhalter im Quellenhinweis

HTML ist nicht erlaubt und wird beim Speichern entfernt – das Link-Markup liefert der Platzhalter.

| Platzhalter | Ergebnis |
|---|---|
| `{source_link}` | Fertiger Link mit dem Artikeltitel als Text (`target="_blank"`, `rel="nofollow noopener"`) |
| `{article_title}` | Titel des Originalartikels |
| `{source_url}` | URL des Originalartikels (als Text) |
| `{feed_name}` | Name der RSS-Quelle |

Beispiele:

```
Quelle: {source_link}
Mehr dazu bei {feed_name}: {source_link}
Dieser Beitrag basiert auf „{article_title}“ ({feed_name}).
```

Der Hinweis wird **serverseitig** angehängt, steht also garantiert im Beitrag – unabhängig davon,
ob die KI selbst einen Link ausgibt – und ist bereits in der Vorschau sichtbar.

---

## Tab: Bilder

| Einstellung | Beschreibung |
|---|---|
| **Pixabay-API-Key** | Optional. Aktiviert die Beitragsbild-Suche auf der Generierungs-Seite. Kostenloser Schlüssel unter [pixabay.com/api/docs](https://pixabay.com/api/docs/). Alternativ als `AUTO_QUILL_PIXABAY_KEY` in der `wp-config.php`. |

---

## Tab: Benachrichtigungen

| Einstellung | Beschreibung | Standard |
|---|---|---|
| **Tagesbericht** | Einmal täglich eine Zusammenfassung per E-Mail | aus |
| **Uhrzeit** | Versandzeit (Ortszeit) | 08:00 |
| **Inhalte** | *Neue Top-Themen*, *Fehler und Warnungen*, *Erstellte Blog-Posts* | alle |
| **Empfänger: Benutzer** | Auswahl aus den Administratoren | – |
| **Weitere Adressen** | Freie Adressen, eine pro Zeile | – |

Darunter: **Test-Mail an alle Empfänger senden**. Details: [Tagesbericht](Tagesbericht).

---

## Tab: Backup

| Einstellung | Beschreibung | Standard |
|---|---|---|
| **Automatische Sicherung** | Einstellungen und RSS-Quellen täglich sichern | an |
| **Uhrzeit** | Zeitpunkt der Sicherung (Ortszeit) | 03:00 |
| **Aufbewahren** | Anzahl der aufgehobenen Sicherungen (1–100) | 7 |

Darunter: Liste der Sicherungen mit *Wiederherstellen*, *Herunterladen*, *Löschen*, der Button
*Jetzt sichern* und der Import. Details: [Backup und Wiederherstellung](Backup-und-Wiederherstellung).

---

## Tab: System

| Einstellung | Beschreibung | Standard |
|---|---|---|
| **Beta-Modus** | Updates vom `beta`-Branch statt vom stabilen `main`-Branch – siehe [Updates](Updates) | aus |
| **Debug-Logging** | Zeichnet zusätzlich Info- und Debug-Einträge inkl. gekürzter API-Payloads auf | aus |

Darunter das **Status-Panel**: DB-Version, Tabellen und Zeilenzahlen, die gespeicherten
Einstellungen (Schlüssel maskiert) sowie die nächsten geplanten Läufe von Abruf, Themenauswahl,
Tagesbericht und Sicherung. Details: [Logs und Debugging](Logs-und-Debugging).
