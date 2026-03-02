<?php

$string['pluginname'] = 'Seminar Planner (Global)';
$string['manageglobalsets'] = 'Manage global method sets';
$string['reviewrequestspage'] = 'Review requests for global method sets';
$string['globalmethodsets'] = 'Global method sets';
$string['globalmethodsetsview'] = 'Show global method sets';
$string['createdraftset'] = 'Create empty method set';
$string['draftcreated'] = 'Draft set created (ID: {$a})';
$string['transitionok'] = 'Status transition completed successfully';
$string['submitforreview'] = 'Submit for review';
$string['publishset'] = 'Publish';
$string['backtodraft'] = 'Back to draft';
$string['archiveglobalset'] = 'Archive';
$string['konzeptgenerator:viewglobalsets'] = 'View global method sets';
$string['konzeptgenerator:createdraftset'] = 'Create global draft sets';
$string['konzeptgenerator:editdraftset'] = 'Edit global draft sets';
$string['konzeptgenerator:submitforreview'] = 'Submit method sets for review';
$string['konzeptgenerator:reviewset'] = 'Review method sets';
$string['konzeptgenerator:publishset'] = 'Publish method sets';
$string['konzeptgenerator:archiveglobalset'] = 'Archive global method sets';
$string['konzeptgenerator:manageareascopes'] = 'Manage area scopes';
$string['konzeptgenerator:importglobalset'] = 'Import global method sets';
$string['konzeptgenerator:exportglobalset'] = 'Export global method sets';
$string['importmoddata'] = 'Import mod_data CSV/ZIP';
$string['importmoddata_desc'] = 'Imports method data from a mod_data-compatible CSV or ZIP export into a draft set.';
$string['importnewsettitle'] = 'Upload a new global method set';
$string['importnewset_desc'] = 'Creates a new draft method set and imports methods from the file.';
$string['importexistingsettitle'] = 'Add methods to an existing method set';
$string['importexistingset_desc'] = 'Imports methods into an existing draft method set.';
$string['nameexplainer'] = 'Name = visible display name.';
$string['shortnameexplainer'] = 'Shortname = technical unique key without spaces; Name = visible display name.';
$string['importstep1newset'] = 'Step 1: Define name, shortname and description';
$string['importstep1existingset'] = 'Step 1: Select an existing method set';
$string['importstep2file'] = 'Step 2: Select import file';
$string['importstep3run'] = 'Step 3: Run import';
$string['importnewsetsubmit'] = 'Import new set';
$string['importexistingsetsubmit'] = 'Import into existing set';
$string['importnewsetok'] = '{$a->count} methods imported. New method set created.';
$string['reactivateglobalset'] = 'Reactivate (to draft)';
$string['exportmoddata'] = 'Export method set (mod_data)';
$string['targetdraftset'] = 'Target draft set';
$string['importfile'] = 'Import file';
$string['importok'] = '{$a} methods imported successfully.';
$string['importerrorfiletype'] = 'Please upload a CSV or ZIP file.';
$string['importerrorzipsupport'] = 'ZIP import is not supported on this server.';
$string['importerrorzipopen'] = 'Could not open ZIP file.';
$string['importerrorcsvmissing'] = 'No CSV file was found inside the ZIP.';
$string['importerrordraftrequired'] = 'Import is only allowed for draft sets.';
$string['importerrornofile'] = 'No import file uploaded.';
$string['importerrornomethods'] = 'No importable methods found in the file.';
$string['deletemethodset'] = 'Delete method set';
$string['deleteconfirm'] = 'Really delete method set "{$a}" and all related data?';
$string['deleteok'] = 'Method set {$a} was deleted.';
$string['renamemethodset'] = 'Rename method set';
$string['renameerrornoname'] = 'Please provide a new method set name.';
$string['renameok'] = 'Method set renamed: "{$a->oldname}" -> "{$a->newname}".';
$string['methodcountcol'] = 'Cards';
$string['reviewerscol'] = 'Responsible planners';
$string['publishedbycol'] = 'Published by';
$string['assignreviewers'] = 'Assign responsible planners';
$string['savereviewers'] = 'Save responsible planners';
$string['reviewersassigned'] = 'Responsible planners saved ({$a}).';
$string['reviewdiffcol'] = 'Review diff';
$string['reviewdiffnew'] = 'New methods';
$string['reviewdiffchanged'] = 'Changed methods';
$string['reviewdiffremoved'] = 'Removed methods';
$string['reviewdiffnone'] = 'No differences detected';
$string['reviewdiffopen'] = 'Show review diff';
$string['reviewdiffpopuptitle'] = 'Review diff: {$a}';
$string['reviewacceptcol'] = 'Accept';
$string['reviewdecision_pending'] = 'Open';
$string['reviewdecision_accepted'] = 'Accept';
$string['reviewdecision_rejected'] = 'Reject';
$string['savereviewdecisions'] = 'Save decisions';
$string['reviewdecisionssaved'] = 'Review decisions saved.';
$string['reviewfeedback_subject'] = 'Review feedback for method set: {$a->setname}';
$string['reviewfeedback_body'] = 'Hello,' . "\n\n" .
    '{$a->reviewer} has saved review decisions for method set "{$a->setname}".' . "\n\n" .
    'Accepted: {$a->acceptedcount}' . "\n" .
    '{$a->acceptedlist}' . "\n\n" .
    'Rejected: {$a->rejectedcount}' . "\n" .
    '{$a->rejectedlist}' . "\n\n" .
    'Overview: {$a->manageurl}';
$string['reviewmail_subject'] = 'Method set submitted for review: {$a->setname}';
$string['reviewmail_body'] = 'Hello,' . "\n\n" .
    'the method set "{$a->setname}" was submitted for review by {$a->submitter}.' . "\n" .
    'Please review it here: {$a->url}' . "\n\n" .
    '{$a->sitename}';
$string['reviewersrequired'] = 'Please assign at least one responsible planner first.';
$string['syncallactivities'] = 'Synchronize all activities now';
$string['syncallactivitiesok'] = 'Synchronization completed: {$a->activitycount} activities updated across {$a->setcount} published method sets.';
