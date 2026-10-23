# local_seminarplaner – globale Bibliothek und Prüf-Workflow

Site-weites Gegenstück zur Kursaktivität [`mod_seminarplaner`](https://github.com/gibro/seminarplaner_mod). Es verwaltet die **globale Bibliothek**, aus der sich Referent*innen in allen Kursen bedienen können, und den **Prüf-Workflow**, über den neue oder geänderte Inhalte in diese Bibliothek gelangen.

Die Bibliothek kennt zwei Arten von Einträgen:

- **Methoden-Sammlung**: eine Sammlung einzelner Seminareinheiten
- **Seminarkonzept**: ein vollständiges Seminar inklusive Ablaufplan

`mod_seminarplaner` setzt dieses Plugin voraus; es muss daher zuerst installiert werden.

| | |
| --- | --- |
| Version | `1.0.0` (`2026100600`), Reife `STABLE` |
| Moodle | ab 4.5 (`$plugin->requires = 2024042200`) |
| Datenbanken | MySQL/MariaDB und PostgreSQL |
| Lizenz | GNU GPL v3 oder später (siehe `LICENSE`) |

## Funktionen

- **Verwaltung globaler Einträge** (`manage.php`, erreichbar unter `Website-Administration -> Plugins -> Lokale Plugins`): anlegen, bearbeiten, archivieren; jeder Eintrag gilt site-weit oder für einen Kursbereich
- **Seminareinheiten direkt bearbeiten** (`editunits.php`), inklusive Dateianhängen
- **Status-Workflow** `draft` → `review` → `published`, optional `archived`; zurück zu `draft` zur Überarbeitung
- **Konzeptverantwortliche** (Prüfer*innen) pro Eintrag zuordnen
- **Review-Seite** (`reviewrequests.php`) mit Änderungsansicht zwischen Versionen, Annehmen/Ablehnen je Änderung und Veröffentlichen
- **Benachrichtigungen** bei Einreichung; dauerhafter Zugang zur Review-Seite über `Profil -> Berichte -> Reviewanfragen für globale Konzepte`
- **Import** aus CSV, ZIP oder Seminarplaner-JSON in einen neuen oder einen bestehenden Entwurf. Beim Import in einen bestehenden Eintrag werden Einheiten mit gleichem Titel aktualisiert statt doppelt angelegt; leere Spalten lassen vorhandene Werte unangetastet. Dateianhänge gehen nur per ZIP (Ordner `files/`, Dateinamen in der Spalte „Materialien“).
- **Export** globaler Einträge als mod_data-kompatible CSV/ZIP
- Datenschutz-Provider (Privacy API) für Export und Löschung personenbezogener Daten

Eingereicht wird aus der Kursaktivität heraus (Tab **Einreichen** in `mod_seminarplaner`). Anhänge der eingereichten Seminareinheiten werden dabei in den globalen Dateibereich übernommen und bleiben nach der Veröffentlichung erhalten.

## Installation

1. Plugin nach `moodle/local/seminarplaner` legen (bei Moodle mit `public/`-Verzeichnis: `public/local/seminarplaner`), danach `mod_seminarplaner` nach `mod/seminarplaner`.
2. Upgrade ausführen: `Website-Administration -> Mitteilungen` oder per CLI:

   ```bash
   php admin/cli/upgrade.php
   ```

3. Caches leeren (`Website-Administration -> Entwicklung -> Caches leeren`).
4. Rollen für Konzeptverantwortliche einrichten (siehe unten).

Für den ZIP-Import muss PHP `ZipArchive` bereitstellen.

## Rechte

| Berechtigung | Einfach erklärt | Kontext | Standardrollen |
| --- | --- | --- | --- |
| `local/seminarplaner:viewglobalsets` | Globale Einträge ansehen. Grundlage für fast alles Weitere, auch für das Übernehmen in eine Kursaktivität. | System oder Kursbereich | Trainer/in (beide), Manager |
| `local/seminarplaner:createdraftset` | Neuen globalen Eintrag als Entwurf anlegen | System oder Kursbereich | Manager |
| `local/seminarplaner:editdraftset` | Entwürfe bearbeiten und Konzeptverantwortliche zuordnen | System oder Kursbereich | Manager |
| `local/seminarplaner:submitforreview` | Entwurf zur Prüfung einreichen (`draft` → `review`). Einreich-Recht, kein Prüf-Recht. | System oder Kursbereich | Manager |
| `local/seminarplaner:reviewset` | Das eigentliche Prüf-Recht: zugewiesene Prüfungen unter „Meine Reviews“ sehen, Änderungen annehmen/ablehnen, zurück auf `draft` setzen. Nur wer es hat, ist als Konzeptverantwortliche*r auswählbar. | System oder Kursbereich | Manager |
| `local/seminarplaner:publishset` | Nach der Prüfung veröffentlichen (`review` → `published`). **Wird immer auf Systemebene geprüft.** | Nur System | – |
| `local/seminarplaner:archiveglobalset` | Veröffentlichte Einträge archivieren bzw. zurückholen | Nur System | – |
| `local/seminarplaner:manageareascopes` | Geltungsbereiche verwalten | Nur System | Manager |
| `local/seminarplaner:importglobalset` | Globale Einträge aus Dateien importieren | System oder Kursbereich | Manager |
| `local/seminarplaner:exportglobalset` | Globale Einträge exportieren | System oder Kursbereich | Manager |

`publishset` und `archiveglobalset` hat **keine** Standardrolle. Ohne manuelle Vergabe können nur Admins veröffentlichen oder archivieren.

## Konzeptverantwortliche (Prüfer*innen) einrichten

1. `Website-Administration -> Nutzer/innen -> Rechte ändern -> Rollen verwalten`
2. Neue Rolle anlegen (oder eine bestehende duplizieren), z. B. „Konzeptverantwortliche“
3. Als Kontexttypen für die Zuweisung `System` und `Kursbereich` erlauben
4. Auf `Erlauben` setzen:
   - `local/seminarplaner:reviewset` (Pflicht)
   - `local/seminarplaner:viewglobalsets` (empfohlen)
   - `local/seminarplaner:publishset` (nur wenn die Person nach der Prüfung selbst veröffentlichen soll)
5. Rolle zuweisen – global (`Globale Rollen zuweisen`) oder im Kursbereich (`Kursbereich -> Rollen zuweisen`).
   **Achtung:** Wer veröffentlichen soll, braucht die Rolle **global**, weil `publishset` im Systemkontext geprüft wird.
6. Person am Eintrag zuordnen: auf `reviewrequests.php` beim Eintrag unter „Konzeptverantwortliche“ auswählen.
7. Prüfen: Bei einem Eintrag im Status `review` sieht die Person „Review-Diff anzeigen“ und „Zurück zu Entwurf“, mit `publishset` zusätzlich „Veröffentlichen“.

### Empfohlene Rollentrennung

- **Reine Prüfer*innen:** `reviewset`, aber **kein** `submitforreview`. Auf der Review-Seite erscheint dann nur „Meine Reviews“ mit den eigenen Zuweisungen, ohne Möglichkeit, andere zuzuordnen.
- **Prüfer*innen mit Veröffentlichungsrecht:** zusätzlich `publishset`, Rolle global zugewiesen.
- **Erweiterte Workflow-Rolle:** zusätzlich `submitforreview` – nur, wenn die Person Entwürfe selbst einreichen soll. Sie verhält sich dann teilweise wie eine verwaltende Person.

### Typische Symptome bei fehlenden Rechten

- Person ist nicht als Konzeptverantwortliche*r auswählbar → `reviewset` fehlt im Geltungsbereich (Kursbereich/System) des Eintrags.
- „Meine Reviews“ ist leer, obwohl eingereicht wurde → Person ist dem Eintrag nicht zugeordnet.
- Nach angenommener Prüfung fehlt „Veröffentlichen“ → `publishset` fehlt, oder die Rolle ist nur im Kursbereich statt global zugewiesen.
- Kein Eintrag unter `Profil -> Berichte` → keine Prüf-/Verwaltungsrechte in einem Bereich mit vorhandenen Einträgen, oder nach dem Update wurden die Caches nicht geleert.
- Person sieht trotz reiner Prüf-Rolle fremde Einträge oder Zuordnungs-Steuerelemente → im selben Bereich ist noch ein zusätzliches Workflow- oder Verwaltungsrecht vergeben.

## Entwicklung

- Tests: `vendor/bin/phpunit --testsuite local_seminarplaner_testsuite`
- Webservice-Funktionen (AJAX): `local_seminarplaner_create_draft_methodset`, `local_seminarplaner_transition_methodset`, `local_seminarplaner_list_methodsets`
