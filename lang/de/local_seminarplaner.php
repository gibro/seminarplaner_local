<?php

// D38: "Methoden-Sammlung" ersetzt "Konzept"/"Methodenset" fuer Sammlungen
// ohne Ablauf. Der Rollenname "Konzeptverantwortliche" bleibt bestehen.
$string['pluginname'] = 'Seminarplaner (Global)';
$string['manageglobalsets'] = 'Globale Methoden-Sammlungen und Seminarkonzepte verwalten';
$string['reviewrequestspage'] = 'Reviewanfragen für globale Sammlungen und Konzepte';
$string['globalmethodsets'] = 'Globale Methoden-Sammlungen und Seminarkonzepte';
$string['globalmethodsetsview'] = 'Globale Methoden-Sammlungen anzeigen';
$string['createdraftset'] = 'Leere Methoden-Sammlung erstellen';
$string['draftcreated'] = 'Entwurf der Methoden-Sammlung erstellt (ID: {$a})';
$string['transitionok'] = 'Statusübergang erfolgreich durchgeführt';
$string['submitforreview'] = 'Zur Review einreichen';
$string['publishset'] = 'Veröffentlichen';
$string['backtodraft'] = 'Zurück zu Entwurf';
$string['archiveglobalset'] = 'Archivieren';
$string['seminarplaner:viewglobalsets'] = 'Globale Methoden-Sammlungen anzeigen';
$string['seminarplaner:createdraftset'] = 'Globale Methoden-Sammlungen (Entwurf) erstellen';
$string['seminarplaner:editdraftset'] = 'Globale Methoden-Sammlungen (Entwurf) bearbeiten';
$string['seminarplaner:submitforreview'] = 'Methoden-Sammlungen zur Review einreichen';
$string['seminarplaner:reviewset'] = 'Methoden-Sammlungen reviewen';
$string['seminarplaner:publishset'] = 'Methoden-Sammlungen veröffentlichen';
$string['seminarplaner:archiveglobalset'] = 'Globale Methoden-Sammlungen archivieren';
$string['seminarplaner:manageareascopes'] = 'Bereichsgrenzen verwalten';
$string['seminarplaner:importglobalset'] = 'Globale Methoden-Sammlungen importieren';
$string['seminarplaner:exportglobalset'] = 'Globale Methoden-Sammlungen exportieren';
$string['importmoddata'] = 'mod_data CSV/ZIP importieren';
$string['importmoddata_desc'] = 'Importiert Daten der Seminareinheit aus einem mod_data-kompatiblen CSV- oder ZIP-Export in eine Methoden-Sammlung im Entwurfsstatus.';
$string['importnewsettitle'] = 'Neuen globalen Eintrag hochladen';
$string['importnewset_desc'] = 'Legt einen neuen globalen Eintrag im Entwurfsstatus an und importiert die Seminareinheiten aus der Datei. Ob Methoden-Sammlung oder Seminarkonzept, legst du danach über den Bearbeiten-Stift fest.';
$string['importexistingsettitle'] = 'Seminareinheiten zu bestehendem Eintrag hinzufügen';
$string['importexistingset_desc'] = 'Importiert Seminareinheiten in einen bestehenden globalen Eintrag im Entwurfsstatus.';
$string['nameexplainer'] = 'Name = sichtbarer Anzeigename.';
$string['shortnameexplainer'] = 'Kurzbezeichnung = technischer, eindeutiger Schlüssel ohne Leerzeichen; Name = sichtbarer Anzeigename.';
$string['importstep1newset'] = 'Schritt 1: Name, Kurzbezeichnung und Beschreibung festlegen';
$string['importstep1existingset'] = 'Schritt 1: Bestehenden Eintrag auswählen';
$string['importstep2file'] = 'Schritt 2: Importdatei angeben';
$string['importstep3run'] = 'Schritt 3: Import ausführen';
$string['importnewsetsubmit'] = 'Neuen Eintrag importieren';
$string['importexistingsetsubmit'] = 'In bestehenden Eintrag importieren';
$string['importnewsetok'] = '{$a->count} Seminareinheiten importiert. Neuer globaler Eintrag erstellt.';
$string['reactivateglobalset'] = 'Reaktivieren (zu Entwurf)';
$string['exportmoddata'] = 'Methoden-Sammlung exportieren (mod_data)';
$string['targetdraftset'] = 'Ziel-Eintrag (Entwurf)';
$string['importfile'] = 'Importdatei';
$string['importok'] = '{$a} Seminareinheiten erfolgreich importiert.';
$string['importerrorfiletype'] = 'Bitte eine CSV- oder ZIP-Datei hochladen.';
$string['importerrorzipsupport'] = 'ZIP-Import wird auf diesem Server nicht unterstützt.';
$string['importerrorzipopen'] = 'ZIP-Datei konnte nicht geöffnet werden.';
$string['importerrorcsvmissing'] = 'Im ZIP wurde keine CSV-Datei gefunden.';
$string['importerrordraftrequired'] = 'Import ist nur in Methoden-Sammlungen im Entwurfsstatus möglich.';
$string['importerrornofile'] = 'Keine Importdatei hochgeladen.';
$string['importerrornomethods'] = 'Keine importierbaren Seminareinheiten in der Datei gefunden.';
$string['deletemethodset'] = 'Methoden-Sammlung löschen';
$string['deleteseminarkonzept'] = 'Seminarkonzept löschen';
$string['deleteconfirm'] = '„{$a}" und alle enthaltenen Daten wirklich löschen?';
$string['deleteok'] = '„{$a}" wurde gelöscht.';
$string['renamemethodset'] = 'Umbenennen';
$string['renameerrornoname'] = 'Bitte einen Namen eingeben.';
$string['editok'] = '„{$a->name}" gespeichert (Typ: {$a->typ}).';
$string['renameok'] = 'Methoden-Sammlung umbenannt: "{$a->oldname}" -> "{$a->newname}".';
$string['methodcountcol'] = 'Anzahl Karten';
$string['reviewerscol'] = 'Konzeptverantwortliche';
$string['publishedbycol'] = 'Veröffentlicht von';
$string['concepttypecol'] = 'Typ';
$string['concepttype_sammlung'] = 'Methoden-Sammlung';
$string['concepttype_seminarkonzept'] = 'Seminarkonzept';
$string['assignreviewers'] = 'Konzeptverantwortliche zuordnen';
$string['savereviewers'] = 'Konzeptverantwortliche speichern';
$string['reviewersassigned'] = 'Konzeptverantwortliche gespeichert ({$a}).';
$string['reviewdiffcol'] = 'Review-Diff';
$string['reviewdiffnew'] = 'Neue Seminareinheiten';
$string['reviewdiffchanged'] = 'Geänderte Seminareinheiten';
$string['reviewdiffremoved'] = 'Entfernte Seminareinheiten';
$string['reviewdiffnone'] = 'Keine Unterschiede erkannt';
$string['reviewdiffopen'] = 'Review-Diff anzeigen';
$string['reviewdiffpopuptitle'] = 'Review-Diff: {$a}';
$string['reviewacceptcol'] = 'Annehmen';
$string['reviewdecisioncol'] = 'Entscheidung';
$string['reviewdecision_pending'] = 'Offen';
$string['reviewdecision_accepted'] = 'Annehmen';
$string['reviewdecision_rejected'] = 'Ablehnen';
$string['reviewacceptallchanges'] = 'Allen Änderungen zustimmen';
$string['savereviewdecisions'] = 'Entscheidungen speichern';
$string['reviewdecisionssaved'] = 'Review-Entscheidungen gespeichert.';
$string['myreviewsheading'] = 'Meine Reviews';
$string['myreviewsnone'] = 'Derzeit sind dir keine Methoden-Sammlungen zur Review zugewiesen.';
$string['managequeuesheading'] = 'Review-Verwaltung';
$string['reviewfeedback_subject'] = 'Review-Rückmeldung für Methoden-Sammlung: {$a->setname}';
$string['reviewfeedback_body'] = 'Hallo,' . "\n\n" .
    'für die Methoden-Sammlung "{$a->setname}" hat {$a->reviewer} Review-Entscheidungen gespeichert.' . "\n\n" .
    'Angenommen: {$a->acceptedcount}' . "\n" .
    '{$a->acceptedlist}' . "\n\n" .
    'Abgelehnt: {$a->rejectedcount}' . "\n" .
    '{$a->rejectedlist}' . "\n\n" .
    'Übersicht: {$a->manageurl}';
$string['reviewmail_subject'] = 'Methoden-Sammlung zur Review eingereicht: {$a->setname}';
$string['reviewmail_body'] = 'Hallo,' . "\n\n" .
    'die Methoden-Sammlung "{$a->setname}" wurde von {$a->submitter} zur Review eingereicht.' . "\n" .
    'Bitte prüfe die Sammlung hier: {$a->url}' . "\n\n" .
    '{$a->sitename}';
$string['reviewersrequired'] = 'Bitte zuerst mindestens eine*n Konzeptverantwortliche*n zuordnen.';
$string['syncallactivities'] = 'Alle Aktivitäten jetzt synchronisieren';
$string['syncallactivitiesok'] = 'Synchronisierung abgeschlossen: {$a->activitycount} Aktivitäten in {$a->setcount} veröffentlichten Methoden-Sammlungen aktualisiert.';

$string['privacy:metadata:local_kgen_methodset'] = 'Speichert globale Methoden-Sammlungs-Definitionen mit Autorenbezug.';
$string['privacy:metadata:local_kgen_methodset:displayname'] = 'Anzeigename der Methoden-Sammlung.';
$string['privacy:metadata:local_kgen_methodset:description'] = 'Beschreibung der Methoden-Sammlung.';
$string['privacy:metadata:local_kgen_methodset:status'] = 'Workflow-Status.';
$string['privacy:metadata:local_kgen_methodset:createdby'] = 'Nutzer-ID der erstellenden Person.';
$string['privacy:metadata:local_kgen_methodset:modifiedby'] = 'Nutzer-ID der zuletzt ändernden Person.';
$string['privacy:metadata:local_kgen_methodset_ver'] = 'Speichert Versionshistorie mit Review-/Publish-Bezug.';
$string['privacy:metadata:local_kgen_methodset_ver:methodsetid'] = 'Referenz auf die Methoden-Sammlung.';
$string['privacy:metadata:local_kgen_methodset_ver:status'] = 'Workflow-Status der Version.';
$string['privacy:metadata:local_kgen_methodset_ver:snapshotjson'] = 'Snapshot-Nutzlast der Version.';
$string['privacy:metadata:local_kgen_methodset_ver:reviewedby'] = 'Nutzer-ID der reviewenden Person.';
$string['privacy:metadata:local_kgen_methodset_ver:publishedby'] = 'Nutzer-ID der veröffentlichenden Person.';
$string['privacy:metadata:local_kgen_method'] = 'Speichert Seminareinheiten in globalen Methoden-Sammlungen mit Autorenbezug.';
$string['privacy:metadata:local_kgen_method:methodsetid'] = 'Referenz auf die Methoden-Sammlung.';
$string['privacy:metadata:local_kgen_method:title'] = 'Titel der Seminareinheit.';
$string['privacy:metadata:local_kgen_method:createdby'] = 'Nutzer-ID der erstellenden Person.';
$string['privacy:metadata:local_kgen_method:modifiedby'] = 'Nutzer-ID der zuletzt ändernden Person.';
$string['privacy:metadata:local_kgen_workflow_event'] = 'Speichert Audit-Einträge für Workflow-Übergänge.';
$string['privacy:metadata:local_kgen_workflow_event:methodsetid'] = 'Referenz auf die Methoden-Sammlung.';
$string['privacy:metadata:local_kgen_workflow_event:fromstatus'] = 'Vorheriger Status.';
$string['privacy:metadata:local_kgen_workflow_event:tostatus'] = 'Zielstatus.';
$string['privacy:metadata:local_kgen_workflow_event:commenttext'] = 'Kommentar zum Übergang.';
$string['privacy:metadata:local_kgen_workflow_event:actorid'] = 'Nutzer-ID der ausführenden Person.';
$string['privacy:metadata:local_kgen_set_reviewer'] = 'Speichert Reviewer-Zuordnungen.';
$string['privacy:metadata:local_kgen_set_reviewer:methodsetid'] = 'Referenz auf die Methoden-Sammlung.';
$string['privacy:metadata:local_kgen_set_reviewer:userid'] = 'Nutzer-ID der zugewiesenen reviewenden Person.';
$string['privacy:metadata:local_kgen_set_reviewer:assignedby'] = 'Nutzer-ID der zuweisenden Person.';
$string['privacy:metadata:local_kgen_review_decision'] = 'Speichert item-spezifische Review-Entscheidungen.';
$string['privacy:metadata:local_kgen_review_decision:methodsetid'] = 'Referenz auf die Methoden-Sammlung.';
$string['privacy:metadata:local_kgen_review_decision:methodsetversionid'] = 'Referenz auf die Version der Methoden-Sammlung.';
$string['privacy:metadata:local_kgen_review_decision:itemkey'] = 'Schlüssel des reviewten Items.';
$string['privacy:metadata:local_kgen_review_decision:reviewerid'] = 'Nutzer-ID der reviewenden Person.';
$string['privacy:metadata:local_kgen_review_decision:decision'] = 'Gespeicherte Entscheidung.';
