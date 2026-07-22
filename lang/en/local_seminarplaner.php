<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Language strings (en).
 *
 * @package    local_seminarplaner
 * @copyright  2026 Guido Brombach <gibro@posteo.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Seminar Planner (Global)';
$string['manageglobalsets'] = 'Manage global method collections and seminar concepts';
$string['reviewrequestspage'] = 'Review requests for global collections and concepts';
$string['globalmethodsets'] = 'Global method collections and seminar concepts';
$string['globalmethodsetsview'] = 'Show global method sets';
$string['createdraftset'] = 'Create empty method set';
$string['draftcreated'] = 'Draft set created (ID: {$a})';
$string['transitionok'] = 'Status transition completed successfully';
$string['transitionstale'] = 'The status of this set has changed in the meantime - it was probably edited in another tab or by someone else. Please reload the page and try again.';
$string['submitforreview'] = 'Submit for review';
$string['publishset'] = 'Publish';
$string['backtodraft'] = 'Back to draft';
$string['archiveglobalset'] = 'Archive';
$string['seminarplaner:viewglobalsets'] = 'View global method sets';
$string['seminarplaner:createdraftset'] = 'Create global draft sets';
$string['seminarplaner:editdraftset'] = 'Edit global draft sets';
$string['seminarplaner:submitforreview'] = 'Submit method sets for review';
$string['seminarplaner:reviewset'] = 'Review method sets';
$string['seminarplaner:publishset'] = 'Publish method sets';
$string['seminarplaner:archiveglobalset'] = 'Archive global method sets';
$string['seminarplaner:manageareascopes'] = 'Manage area scopes';
$string['seminarplaner:importglobalset'] = 'Import global method sets';
$string['seminarplaner:exportglobalset'] = 'Export global method sets';
$string['importmoddata'] = 'Import mod_data CSV/ZIP';
$string['importmoddata_desc'] = 'Imports method data from a mod_data-compatible CSV or ZIP export into a draft set.';
$string['importnewsettitle'] = 'Upload a new global entry';
$string['importnewset_desc'] = 'Creates a new global entry in draft status and imports the seminar units from the file. Whether it becomes a method collection or a seminar concept is set afterwards via the edit pencil.';
$string['importexistingsettitle'] = 'Add to or update an existing entry';
$string['importexistingset_desc'] = 'Imports seminar units into an existing global entry in draft status. Units with a matching title are updated instead of duplicated: non-empty columns overwrite the previous value, empty columns leave it untouched. Titles without a match are added. Attachments require a ZIP file (folder "files/", filenames listed in the "Materialien" column); existing attachments are kept, a file of the same name is replaced by the new version.';
$string['nameexplainer'] = 'Name = visible display name.';
$string['shortnameexplainer'] = 'Shortname = technical unique key without spaces; Name = visible display name.';
$string['importstep1newset'] = 'Step 1: Define name, shortname and description';
$string['importstep1existingset'] = 'Step 1: Select an existing entry';
$string['importstep2file'] = 'Step 2: Select import file';
$string['importstep3run'] = 'Step 3: Run import';
$string['importnewsetsubmit'] = 'Import new entry';
$string['importexistingsetsubmit'] = 'Import into existing entry';
$string['importnewsetok'] = '{$a->count} seminar units imported. New global entry created.';
$string['reactivateglobalset'] = 'Reactivate (to draft)';
$string['exportmoddata'] = 'Export method set (mod_data)';
$string['targetdraftset'] = 'Target entry (draft)';
$string['importfile'] = 'Import file';
$string['importok'] = '{$a} methods imported successfully.';
$string['importupsertok'] = 'Import finished: {$a->created} seminar units added, {$a->updated} updated, {$a->files} files stored.';
$string['importmissingfiles'] = 'NOTE: {$a->count} attachments named in the file were not included and are therefore missing: {$a->names}. In a ZIP they must sit in the "files/" folder; a CSV cannot carry attachments.';
$string['importerrorfiletype'] = 'Please upload a CSV or ZIP file.';
$string['importerrorzipsupport'] = 'ZIP import is not supported on this server.';
$string['importerrorzipopen'] = 'Could not open ZIP file.';
$string['importerrorcsvmissing'] = 'No CSV file was found inside the ZIP.';
$string['importerrordraftrequired'] = 'Import is only allowed for draft sets.';
$string['importerrornofile'] = 'No import file uploaded.';
$string['importerrornomethods'] = 'No importable methods found in the file.';
$string['deletemethodset'] = 'Delete method collection';
$string['deleteseminarkonzept'] = 'Delete seminar concept';
$string['deleteconfirm'] = 'Really delete "{$a}" and all related data?';
$string['deleteok'] = '"{$a}" was deleted.';
$string['renamemethodset'] = 'Rename';
$string['renameerrornoname'] = 'Please provide a name.';
$string['editok'] = '"{$a->name}" saved (type: {$a->typ}).';
$string['renameok'] = 'Method set renamed: "{$a->oldname}" -> "{$a->newname}".';
$string['methodcountcol'] = 'Cards';
$string['reviewerscol'] = 'Responsible planners';
$string['publishedbycol'] = 'Published by';
$string['concepttypecol'] = 'Type';
$string['concepttype_sammlung'] = 'Method collection';
$string['concepttype_seminarkonzept'] = 'Seminar concept';
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
$string['reviewdecisioncol'] = 'Decision';
$string['reviewdecision_pending'] = 'Open';
$string['reviewdecision_accepted'] = 'Accept';
$string['reviewdecision_rejected'] = 'Reject';
$string['reviewacceptallchanges'] = 'Accept all changes';
$string['savereviewdecisions'] = 'Save decisions';
$string['applyreviewdecisions'] = 'Apply accepted changes';
$string['editunitslink'] = 'Edit seminar units';
$string['editunitsection1'] = '1) Quick version';
$string['editunitsection2'] = '2) Flow and setting';
$string['editunitsection3'] = '3) Materials and equipment';
$string['editunitfield_title'] = 'Title';
$string['editunitfield_lernziele'] = 'Learning objectives (I can ...)';
$string['editunitfield_seminarphase'] = 'Seminar phase';
$string['editunitfield_tags'] = 'Tags / keywords';
$string['editunitfield_zeitbedarf'] = 'Time needed';
$string['editunitfield_gruppengroesse'] = 'Group size';
$string['editunitfield_kurzbeschreibung'] = 'Short description';
$string['editunitfield_ablauf'] = 'Procedure';
$string['editunitfield_autor'] = 'Author / contact';
$string['editunitfield_raum'] = 'Room requirements';
$string['editunitfield_sozialform'] = 'Social form';
$string['editunitfield_vorbereitung'] = 'Preparation needed';
$string['editunitfield_risiken'] = 'Risks / tips';
$string['editunitfield_debrief'] = 'Debriefing questions';
$string['editunitfield_materialien'] = 'Materials';
$string['editunitfield_materialtechnik'] = 'Material / equipment';
$string['editunitsbacktolist'] = '← Back to the list of seminar units';
$string['editunitfileschanged'] = 'Files: {$a->added} added, {$a->removed} removed.';
$string['editunitstitle'] = 'Edit seminar units';
$string['editunitsintro'] = 'Maintain the seminar units of this set directly - even when nothing is currently up for review. Changes take effect in the current version straight away and are recorded in the history. Activities pick them up on their next "apply pending updates".';
$string['editunitscount'] = '{$a} seminar units';
$string['editunitsave'] = 'Save changes';
$string['editunitsaved'] = 'Saved. Changed: {$a}.';
$string['editunitunchanged'] = 'No changes - nothing was saved.';
$string['editunittitlerequired'] = 'The title must not be empty.';
$string['editunitlogged'] = 'Seminar unit "{$a->title}" edited ({$a->fields}).';
$string['backtoreviewrequests'] = 'Back to the overview';
$string['applyreviewdecisionsconfirm'] = 'The decisions are saved and then applied: accepted changes stay, rejected ones fall back to the previous state. Continue?';
$string['applyreviewdecisionsok'] = 'Applied: {$a->fields} fields reverted, {$a->materials} attachments reverted, {$a->removed} new seminar units discarded, {$a->restored} removed seminar units restored. The version now carries exactly the accepted changes and can be published.';
$string['applyreviewdecisionspending'] = 'Note: {$a} rows are still set to "open" and were left untouched.';
$string['reviewdecisionssaved'] = 'Review decisions saved.';
$string['myreviewsheading'] = 'My reviews';
$string['myreviewsnone'] = 'There are currently no method sets assigned to you for review.';
$string['managequeuesheading'] = 'Review administration';
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

$string['privacy:metadata:local_kgen_methodset'] = 'Stores global method-set definitions and ownership references.';
$string['privacy:metadata:local_kgen_methodset:displayname'] = 'Display name of method set.';
$string['privacy:metadata:local_kgen_methodset:description'] = 'Description of method set.';
$string['privacy:metadata:local_kgen_methodset:status'] = 'Workflow status.';
$string['privacy:metadata:local_kgen_methodset:createdby'] = 'Creating user id.';
$string['privacy:metadata:local_kgen_methodset:modifiedby'] = 'Last modifying user id.';
$string['privacy:metadata:local_kgen_methodset_ver'] = 'Stores method-set version history and reviewer/publisher references.';
$string['privacy:metadata:local_kgen_methodset_ver:methodsetid'] = 'Method-set reference.';
$string['privacy:metadata:local_kgen_methodset_ver:status'] = 'Version workflow status.';
$string['privacy:metadata:local_kgen_methodset_ver:snapshotjson'] = 'Version snapshot payload.';
$string['privacy:metadata:local_kgen_methodset_ver:reviewedby'] = 'Reviewing user id.';
$string['privacy:metadata:local_kgen_methodset_ver:publishedby'] = 'Publishing user id.';
$string['privacy:metadata:local_kgen_method'] = 'Stores methods and author references inside global sets.';
$string['privacy:metadata:local_kgen_method:methodsetid'] = 'Method-set reference.';
$string['privacy:metadata:local_kgen_method:title'] = 'Method title.';
$string['privacy:metadata:local_kgen_method:createdby'] = 'Creating user id.';
$string['privacy:metadata:local_kgen_method:modifiedby'] = 'Last modifying user id.';
$string['privacy:metadata:local_kgen_workflow_event'] = 'Stores workflow transition audit entries.';
$string['privacy:metadata:local_kgen_workflow_event:methodsetid'] = 'Method-set reference.';
$string['privacy:metadata:local_kgen_workflow_event:fromstatus'] = 'Previous status.';
$string['privacy:metadata:local_kgen_workflow_event:tostatus'] = 'Target status.';
$string['privacy:metadata:local_kgen_workflow_event:commenttext'] = 'Transition comment.';
$string['privacy:metadata:local_kgen_workflow_event:actorid'] = 'Acting user id.';
$string['privacy:metadata:local_kgen_set_reviewer'] = 'Stores reviewer assignments.';
$string['privacy:metadata:local_kgen_set_reviewer:methodsetid'] = 'Method-set reference.';
$string['privacy:metadata:local_kgen_set_reviewer:userid'] = 'Assigned reviewer user id.';
$string['privacy:metadata:local_kgen_set_reviewer:assignedby'] = 'Assigning user id.';
$string['privacy:metadata:local_kgen_review_decision'] = 'Stores item-level review decisions.';
$string['privacy:metadata:local_kgen_review_decision:methodsetid'] = 'Method-set reference.';
$string['privacy:metadata:local_kgen_review_decision:methodsetversionid'] = 'Method-set version reference.';
$string['privacy:metadata:local_kgen_review_decision:itemkey'] = 'Reviewed item key.';
$string['privacy:metadata:local_kgen_review_decision:reviewerid'] = 'Reviewer user id.';
$string['privacy:metadata:local_kgen_review_decision:decision'] = 'Decision value.';
