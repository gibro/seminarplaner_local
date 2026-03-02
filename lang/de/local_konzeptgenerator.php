<?php

$string['pluginname'] = 'Seminarplaner (Global)';
$string['manageglobalsets'] = 'Globale Methodensets verwalten';
$string['reviewrequestspage'] = 'Reviewanfragen globale Methodensets';
$string['globalmethodsets'] = 'Globale Methodensets';
$string['globalmethodsetsview'] = 'Globale Methodensets anzeigen';
$string['createdraftset'] = 'Leeres Methodenset erstellen';
$string['draftcreated'] = 'Entwurfs-Set erstellt (ID: {$a})';
$string['transitionok'] = 'Statusübergang erfolgreich durchgeführt';
$string['submitforreview'] = 'Zur Review einreichen';
$string['publishset'] = 'Veröffentlichen';
$string['backtodraft'] = 'Zurück zu Entwurf';
$string['archiveglobalset'] = 'Archivieren';
$string['konzeptgenerator:viewglobalsets'] = 'Globale Methodensets anzeigen';
$string['konzeptgenerator:createdraftset'] = 'Globale Entwurfs-Sets erstellen';
$string['konzeptgenerator:editdraftset'] = 'Globale Entwurfs-Sets bearbeiten';
$string['konzeptgenerator:submitforreview'] = 'Methodensets zur Review einreichen';
$string['konzeptgenerator:reviewset'] = 'Methodensets reviewen';
$string['konzeptgenerator:publishset'] = 'Methodensets veröffentlichen';
$string['konzeptgenerator:archiveglobalset'] = 'Globale Methodensets archivieren';
$string['konzeptgenerator:manageareascopes'] = 'Bereichsgrenzen verwalten';
$string['konzeptgenerator:importglobalset'] = 'Globale Methodensets importieren';
$string['konzeptgenerator:exportglobalset'] = 'Globale Methodensets exportieren';
$string['importmoddata'] = 'mod_data CSV/ZIP importieren';
$string['importmoddata_desc'] = 'Importiert Methodendaten aus einem mod_data-kompatiblen CSV- oder ZIP-Export in ein Entwurfs-Set.';
$string['importnewsettitle'] = 'Neues globales Methodenset hochladen';
$string['importnewset_desc'] = 'Legt ein neues Entwurfs-Methodenset an und importiert die Methoden aus der Datei.';
$string['importexistingsettitle'] = 'Methoden zu bestehendem Methodenset hinzufügen';
$string['importexistingset_desc'] = 'Importiert Methoden in ein bestehendes Entwurfs-Methodenset.';
$string['nameexplainer'] = 'Name = sichtbarer Anzeigename.';
$string['shortnameexplainer'] = 'Kurzbezeichnung = technischer, eindeutiger Schlüssel ohne Leerzeichen; Name = sichtbarer Anzeigename.';
$string['importstep1newset'] = 'Schritt 1: Name, Kurzbezeichnung und Beschreibung festlegen';
$string['importstep1existingset'] = 'Schritt 1: Bestehendes Methodenset auswählen';
$string['importstep2file'] = 'Schritt 2: Importdatei angeben';
$string['importstep3run'] = 'Schritt 3: Import ausführen';
$string['importnewsetsubmit'] = 'Neues Set importieren';
$string['importexistingsetsubmit'] = 'In bestehendes Set importieren';
$string['importnewsetok'] = '{$a->count} Methoden importiert. Neues Methodenset erstellt.';
$string['reactivateglobalset'] = 'Reaktivieren (zu Entwurf)';
$string['exportmoddata'] = 'Methodenset exportieren (mod_data)';
$string['targetdraftset'] = 'Ziel-Entwurfs-Set';
$string['importfile'] = 'Importdatei';
$string['importok'] = '{$a} Methoden erfolgreich importiert.';
$string['importerrorfiletype'] = 'Bitte eine CSV- oder ZIP-Datei hochladen.';
$string['importerrorzipsupport'] = 'ZIP-Import wird auf diesem Server nicht unterstützt.';
$string['importerrorzipopen'] = 'ZIP-Datei konnte nicht geöffnet werden.';
$string['importerrorcsvmissing'] = 'Im ZIP wurde keine CSV-Datei gefunden.';
$string['importerrordraftrequired'] = 'Import ist nur in Entwurfs-Sets möglich.';
$string['importerrornofile'] = 'Keine Importdatei hochgeladen.';
$string['importerrornomethods'] = 'Keine importierbaren Methoden in der Datei gefunden.';
$string['deletemethodset'] = 'Methodenset löschen';
$string['deleteconfirm'] = 'Methodenset "{$a}" und alle enthaltenen Daten wirklich löschen?';
$string['deleteok'] = 'Methodenset {$a} wurde gelöscht.';
$string['renamemethodset'] = 'Methodenset umbenennen';
$string['renameerrornoname'] = 'Bitte einen neuen Namen für das Methodenset eingeben.';
$string['renameok'] = 'Methodenset umbenannt: "{$a->oldname}" -> "{$a->newname}".';
$string['methodcountcol'] = 'Anzahl Karten';
$string['reviewerscol'] = 'Konzeptverantwortliche';
$string['publishedbycol'] = 'Veröffentlicht von';
$string['assignreviewers'] = 'Konzeptverantwortliche zuordnen';
$string['savereviewers'] = 'Konzeptverantwortliche speichern';
$string['reviewersassigned'] = 'Konzeptverantwortliche gespeichert ({$a}).';
$string['reviewdiffcol'] = 'Review-Diff';
$string['reviewdiffnew'] = 'Neue Methoden';
$string['reviewdiffchanged'] = 'Geänderte Methoden';
$string['reviewdiffremoved'] = 'Entfernte Methoden';
$string['reviewdiffnone'] = 'Keine Unterschiede erkannt';
$string['reviewdiffopen'] = 'Review-Diff anzeigen';
$string['reviewdiffpopuptitle'] = 'Review-Diff: {$a}';
$string['reviewacceptcol'] = 'Annehmen';
$string['reviewdecision_pending'] = 'Offen';
$string['reviewdecision_accepted'] = 'Annehmen';
$string['reviewdecision_rejected'] = 'Ablehnen';
$string['savereviewdecisions'] = 'Entscheidungen speichern';
$string['reviewdecisionssaved'] = 'Review-Entscheidungen gespeichert.';
$string['reviewfeedback_subject'] = 'Review-Rückmeldung für Methodenset: {$a->setname}';
$string['reviewfeedback_body'] = 'Hallo,' . "\n\n" .
    'für das Methodenset "{$a->setname}" hat {$a->reviewer} Review-Entscheidungen gespeichert.' . "\n\n" .
    'Angenommen: {$a->acceptedcount}' . "\n" .
    '{$a->acceptedlist}' . "\n\n" .
    'Abgelehnt: {$a->rejectedcount}' . "\n" .
    '{$a->rejectedlist}' . "\n\n" .
    'Übersicht: {$a->manageurl}';
$string['reviewmail_subject'] = 'Methodenset zur Review eingereicht: {$a->setname}';
$string['reviewmail_body'] = 'Hallo,' . "\n\n" .
    'das Methodenset "{$a->setname}" wurde von {$a->submitter} zur Review eingereicht.' . "\n" .
    'Bitte prüfe das Set hier: {$a->url}' . "\n\n" .
    '{$a->sitename}';
$string['reviewersrequired'] = 'Bitte zuerst mindestens eine*n Konzeptverantwortliche*n zuordnen.';
$string['syncallactivities'] = 'Alle Aktivitäten jetzt synchronisieren';
$string['syncallactivitiesok'] = 'Synchronisierung abgeschlossen: {$a->activitycount} Aktivitäten in {$a->setcount} veröffentlichten Methodensets aktualisiert.';
