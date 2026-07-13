<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . "/bootstrap.php");
require_once($CFG->libdir . "/formslib.php");
require_once(__DIR__ . "/locallib.php");

$action = optional_param("action", "", PARAM_ALPHANUMEXT);

require_login();
$syscontext = context_system::instance();
require_capability('local/seminarplaner:viewglobalsets', $syscontext);

$PAGE->set_url('/local/seminarplaner/manage.php');
$PAGE->set_context($syscontext);
$PAGE->set_title(get_string('manageglobalsets', 'local_seminarplaner'));
$PAGE->set_heading(get_string('manageglobalsets', 'local_seminarplaner'));

$repo = new \local_seminarplaner\local\repository\methodset_repository();
$stringmanager = get_string_manager();
$nameexplainertext = $stringmanager->string_exists('nameexplainer', 'local_seminarplaner')
    ? get_string('nameexplainer', 'local_seminarplaner')
    : 'Name = sichtbarer Anzeigename.';
$shortnameexplainertext = $stringmanager->string_exists('shortnameexplainer', 'local_seminarplaner')
    ? get_string('shortnameexplainer', 'local_seminarplaner')
    : 'Kurzbezeichnung = technischer, eindeutiger Schlüssel ohne Leerzeichen';

/**
 * Create a new draft version from the current set version and copy all methods/files.
 *
 * @param stdClass $methodset Method set record.
 * @param int $actorid User id performing the fork.
 * @param \local_seminarplaner\local\repository\methodset_repository $repo Repository instance.
 * @return int New draft version id.
 */
function local_seminarplaner_fork_current_version_to_draft(stdClass $methodset, int $actorid,
    \local_seminarplaner\local\repository\methodset_repository $repo): int {
    global $DB;

    $sourceversionid = (int)($methodset->currentversion ?? 0);
    if ($sourceversionid <= 0) {
        throw new moodle_exception('invalidparameter');
    }
    $sourceversion = $repo->get_version($sourceversionid);
    if (!$sourceversion) {
        throw new moodle_exception('invalidparameter');
    }

    $transaction = $DB->start_delegated_transaction();
    $newversionnum = ((int)$sourceversion->versionnum) + 1;
    $snapshotjson = (string)($sourceversion->snapshotjson ?? '{}');
    $newversionid = $repo->create_version((int)$methodset->id, $newversionnum, 'draft', $snapshotjson, $actorid);
    $repo->update_methodset_status((int)$methodset->id, 'draft', $actorid);
    $now = time();
    $methods = $DB->get_records('local_kgen_method', [
        'methodsetid' => (int)$methodset->id,
        'methodsetversionid' => $sourceversionid,
    ], 'id ASC');
    $files = get_file_storage();
    $contextid = context_system::instance()->id;

    foreach ($methods as $method) {
        $oldmethodid = (int)$method->id;
        unset($method->id);
        $method->methodsetversionid = $newversionid;
        $method->timecreated = $now;
        $method->timemodified = $now;
        $method->createdby = $actorid;
        $method->modifiedby = $actorid;
        $newmethodid = (int)$DB->insert_record('local_kgen_method', $method);

        $methodfilelinks = $DB->get_records('local_kgen_method_file', ['methodid' => $oldmethodid], 'id ASC');
        foreach ($methodfilelinks as $link) {
            $kind = ((string)$link->kind === 'h5p') ? 'h5p' : 'material';
            $filearea = $kind === 'h5p' ? 'method_h5p' : 'method_material';
            $newitemid = local_seminarplaner_next_file_itemid($filearea);
            $areaentries = $files->get_area_files(
                $contextid,
                'local_seminarplaner',
                $filearea,
                (int)$link->fileitemid,
                'id ASC',
                false
            );
            foreach ($areaentries as $entry) {
                $filerecord = (object)[
                    'contextid' => $contextid,
                    'component' => 'local_seminarplaner',
                    'filearea' => $filearea,
                    'itemid' => $newitemid,
                    'filepath' => (string)$entry->get_filepath(),
                    'filename' => (string)$entry->get_filename(),
                    'userid' => $actorid,
                ];
                $files->create_file_from_string($filerecord, (string)$entry->get_content());
            }
            $DB->insert_record('local_kgen_method_file', (object)[
                'methodid' => $newmethodid,
                'kind' => $kind,
                'fileitemid' => $newitemid,
                'timecreated' => $now,
            ]);
        }
    }

    $transaction->allow_commit();
    return $newversionid;
}

$message = '';
$error = false;

if ($action === 'createdraft' && confirm_sesskey()) {
    require_capability('local/seminarplaner:createdraftset', $syscontext);

    $shortname = required_param('shortname', PARAM_ALPHANUMEXT);
    $displayname = required_param('displayname', PARAM_TEXT);
    $description = optional_param('description', '', PARAM_TEXT);

    try {
        $methodsetid = $repo->create_methodset_draft($shortname, $displayname, $description, (int)$syscontext->id, (int)$USER->id);
        $repo->create_version($methodsetid, 1, 'draft', '{}', (int)$USER->id);
        $message = get_string('draftcreated', 'local_seminarplaner', $methodsetid);
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $error = true;
    }
}


if ($action === 'exportmoddata' && confirm_sesskey()) {
    require_capability('local/seminarplaner:exportglobalset', $syscontext);
    $methodsetid = required_param('methodsetid', PARAM_INT);
    $versionid = optional_param('versionid', 0, PARAM_INT);

    try {
        $set = $repo->get_methodset($methodsetid);
        if (!$set) {
            throw new moodle_exception('invalidparameter');
        }
        if ($versionid <= 0) {
            $versionid = (int)($set->currentversion ?? 0);
        }
        local_seminarplaner_send_moddata_export((int)$set->id, (int)$versionid, (string)$set->displayname);
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $error = true;
    }
}


if ($action === 'delete' && confirm_sesskey()) {
    require_capability('local/seminarplaner:archiveglobalset', $syscontext);
    $methodsetid = required_param('methodsetid', PARAM_INT);

    try {
        $methodset = $repo->get_methodset($methodsetid);
        if (!$methodset) {
            throw new moodle_exception('invalidparameter');
        }
        $repo->delete_methodset_cascade($methodsetid);
        $message = get_string('deleteok', 'local_seminarplaner', $methodsetid);
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $error = true;
    }
}

if ($action === 'renamemethodset' && confirm_sesskey()) {
    if (!has_capability('local/seminarplaner:editdraftset', $syscontext) &&
            !has_capability('local/seminarplaner:archiveglobalset', $syscontext)) {
        throw new required_capability_exception($syscontext, 'local/seminarplaner:editdraftset', 'nopermissions', '');
    }
    $methodsetid = required_param('methodsetid', PARAM_INT);
    $newdisplayname = trim((string)optional_param('newdisplayname', '', PARAM_TEXT));

    try {
        if ($newdisplayname === '') {
            throw new moodle_exception('renameerrornoname', 'local_seminarplaner');
        }
        $methodset = $repo->get_methodset($methodsetid);
        if (!$methodset) {
            throw new moodle_exception('invalidparameter');
        }
        $DB->update_record('local_kgen_methodset', (object)[
            'id' => (int)$methodsetid,
            'displayname' => $newdisplayname,
            'timemodified' => time(),
            'modifiedby' => (int)$USER->id,
        ]);
        $message = get_string('renameok', 'local_seminarplaner', (object)[
            'oldname' => (string)$methodset->displayname,
            'newname' => $newdisplayname,
        ]);
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $error = true;
    }
}

// D57/D55: Objektart (Methoden-Sammlung vs. Seminarkonzept) je Set festlegen.
// Steuert die Bibliotheks-Behandlung im mod-Plugin (Sammlungen immer durchsuchbar,
// Konzepte nur nach explizitem Import).
if ($action === 'setconcepttype' && confirm_sesskey()) {
    if (!has_capability('local/seminarplaner:editdraftset', $syscontext) &&
            !has_capability('local/seminarplaner:archiveglobalset', $syscontext)) {
        throw new required_capability_exception($syscontext, 'local/seminarplaner:editdraftset', 'nopermissions', '');
    }
    $methodsetid = required_param('methodsetid', PARAM_INT);
    $concepttype = optional_param('concepttype', 'sammlung', PARAM_ALPHA);
    $concepttype = $concepttype === 'seminarkonzept' ? 'seminarkonzept' : 'sammlung';

    try {
        $methodset = $repo->get_methodset($methodsetid);
        if (!$methodset) {
            throw new moodle_exception('invalidparameter');
        }
        $repo->update_methodset_concepttype((int)$methodsetid, $concepttype, (int)$USER->id);
        $message = get_string('concepttypesaved', 'local_seminarplaner', (object)[
            'name' => (string)$methodset->displayname,
            'typ' => get_string('concepttype_' . $concepttype, 'local_seminarplaner'),
        ]);
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $error = true;
    }
}

if ($action === 'importmoddata_newset' && confirm_sesskey()) {
    require_capability('local/seminarplaner:importglobalset', $syscontext);
    require_capability('local/seminarplaner:createdraftset', $syscontext);
    require_capability('local/seminarplaner:editdraftset', $syscontext);

    $shortname = required_param('shortname', PARAM_ALPHANUMEXT);
    $displayname = required_param('displayname', PARAM_TEXT);
    $description = optional_param('description', '', PARAM_TEXT);
    $draftitemid = required_param('importfilenew', PARAM_INT);

    try {
        $draftfile = local_seminarplaner_get_draft_upload($draftitemid);
        if (!$draftfile) {
            throw new moodle_exception('importerrornofile', 'local_seminarplaner');
        }

        if ((int)$draftfile->get_filesize() > LOCAL_SEMINARPLANER_IMPORT_MAX_BYTES) {
            throw new moodle_exception('invalidparameter');
        }

        $filename = clean_param((string)$draftfile->get_filename(), PARAM_FILE);
        $tmpfilepath = make_request_directory() . '/kgen-import-' . time() . '-' . random_int(1000, 9999);
        file_put_contents($tmpfilepath, (string)$draftfile->get_content());
        try {
            $payload = local_seminarplaner_extract_rows_from_upload($tmpfilepath, $filename);
        } finally {
            @unlink($tmpfilepath);
        }
        $rows = (array)($payload['rows'] ?? []);
        $zipfiles = (array)($payload['files'] ?? []);
        $records = [];
        foreach ($rows as $row) {
            $mapped = local_seminarplaner_map_legacy_row($row);
            if ($mapped !== null) {
                $records[] = $mapped;
            }
        }
        if (!$records) {
            throw new moodle_exception('importerrornomethods', 'local_seminarplaner');
        }

        $methodsetid = $repo->create_methodset_draft($shortname, $displayname, $description, (int)$syscontext->id, (int)$USER->id);
        $versionid = $repo->create_version($methodsetid, 1, 'draft', '{}', (int)$USER->id);
        $count = local_seminarplaner_import_records_to_set(
            (int)$methodsetid,
            (int)$versionid,
            (int)$USER->id,
            $records,
            $zipfiles
        );
        $message = get_string('importnewsetok', 'local_seminarplaner', (object)[
            'count' => $count,
            'id' => $methodsetid,
        ]);
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $error = true;
    }
}

if ($action === 'importmoddata_existingset' && confirm_sesskey()) {
    require_capability('local/seminarplaner:importglobalset', $syscontext);
    require_capability('local/seminarplaner:editdraftset', $syscontext);
    $methodsetid = required_param('methodsetid', PARAM_INT);
    $draftitemid = required_param('importfileexisting', PARAM_INT);

    try {
        $methodset = $repo->get_methodset($methodsetid);
        if (!$methodset) {
            throw new moodle_exception('invalidparameter');
        }
        $targetversionid = (int)($methodset->currentversion ?? 0);
        $setstatus = (string)$methodset->status;
        if ($setstatus === 'published') {
            $targetversionid = local_seminarplaner_fork_current_version_to_draft($methodset, (int)$USER->id, $repo);
            $methodset = $repo->get_methodset($methodsetid);
            $setstatus = (string)($methodset->status ?? '');
        }
        if ($setstatus !== 'draft') {
            throw new moodle_exception('importerrordraftrequired', 'local_seminarplaner');
        }
        if ($targetversionid <= 0) {
            throw new moodle_exception('invalidparameter');
        }
        $draftfile = local_seminarplaner_get_draft_upload($draftitemid);
        if (!$draftfile) {
            throw new moodle_exception('importerrornofile', 'local_seminarplaner');
        }

        if ((int)$draftfile->get_filesize() > LOCAL_SEMINARPLANER_IMPORT_MAX_BYTES) {
            throw new moodle_exception('invalidparameter');
        }

        $filename = clean_param((string)$draftfile->get_filename(), PARAM_FILE);
        $tmpfilepath = make_request_directory() . '/kgen-import-' . time() . '-' . random_int(1000, 9999);
        file_put_contents($tmpfilepath, (string)$draftfile->get_content());
        try {
            $payload = local_seminarplaner_extract_rows_from_upload($tmpfilepath, $filename);
        } finally {
            @unlink($tmpfilepath);
        }
        $rows = (array)($payload['rows'] ?? []);
        $zipfiles = (array)($payload['files'] ?? []);

        $records = [];
        foreach ($rows as $row) {
            $mapped = local_seminarplaner_map_legacy_row($row);
            if ($mapped !== null) {
                $records[] = $mapped;
            }
        }
        if (!$records) {
            throw new moodle_exception('importerrornomethods', 'local_seminarplaner');
        }

        $count = local_seminarplaner_import_records_to_set(
            (int)$methodsetid,
            $targetversionid,
            (int)$USER->id,
            $records,
            $zipfiles
        );
        $message = get_string('importok', 'local_seminarplaner', $count);
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $error = true;
    }
}

if ($action === 'syncallactivities' && confirm_sesskey()) {
    require_capability('local/seminarplaner:publishset', $syscontext);
    try {
        if (!class_exists('\\mod_seminarplaner\\local\\service\\methodset_sync_service')) {
            throw new moodle_exception('invalidparameter');
        }
        $syncservice = new \mod_seminarplaner\local\service\methodset_sync_service();
        $publishedsets = $repo->list_methodsets((int)$syscontext->id, 'published');
        $setcount = 0;
        $updatedactivities = 0;
        foreach ($publishedsets as $set) {
            $versionid = (int)($set->currentversion ?? 0);
            if ($versionid <= 0) {
                continue;
            }
            $setcount++;
            $updatedactivities += (int)$syncservice->sync_published_methodset((int)$set->id, $versionid, (int)$USER->id);
        }
        $message = get_string('syncallactivitiesok', 'local_seminarplaner', (object)[
            'setcount' => $setcount,
            'activitycount' => $updatedactivities,
        ]);
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $error = true;
    }
}

$methodsets = $repo->list_methodsets((int)$syscontext->id);

$draftoptions = [];
foreach ($methodsets as $set) {
    $status = (string)$set->status;
    if ($status === 'draft' || $status === 'published') {
        $label = format_string($set->displayname) . ' (#' . (int)$set->id . ')';
        $draftoptions[(int)$set->id] = $label;
    }
}

$maxuploadbytes = get_user_max_upload_file_size($syscontext, $CFG->maxbytes);
$newsetdraftitemid = file_get_submitted_draft_itemid('importfilenew');
file_prepare_draft_area($newsetdraftitemid, $syscontext->id, 'local_seminarplaner', 'import_upload', 0, [
    'subdirs' => 0,
    'maxfiles' => 1,
    'maxbytes' => $maxuploadbytes,
    'accepted_types' => ['.csv', '.zip', '.json'],
]);
$existingdraftitemid = file_get_submitted_draft_itemid('importfileexisting');
file_prepare_draft_area($existingdraftitemid, $syscontext->id, 'local_seminarplaner', 'import_upload', 0, [
    'subdirs' => 0,
    'maxfiles' => 1,
    'maxbytes' => $maxuploadbytes,
    'accepted_types' => ['.csv', '.zip', '.json'],
]);

$newsetform = new \local_seminarplaner\form\import_new_set_form(
    new moodle_url('/local/seminarplaner/manage.php'),
    ['maxbytes' => $maxuploadbytes]
);
$newsetform->set_data((object)[
    'action' => 'importmoddata_newset',
    'importfilenew' => $newsetdraftitemid,
]);

$existingsetform = new \local_seminarplaner\form\import_existing_set_form(
    new moodle_url('/local/seminarplaner/manage.php'),
    [
        'draftoptions' => $draftoptions,
        'maxbytes' => $maxuploadbytes,
    ]
);
$existingsetform->set_data((object)[
    'action' => 'importmoddata_existingset',
    'importfileexisting' => $existingdraftitemid,
]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manageglobalsets', 'local_seminarplaner'));
echo html_writer::start_div('kg-row');
echo html_writer::link(
    new moodle_url('/local/seminarplaner/reviewrequests.php'),
    get_string('reviewrequestspage', 'local_seminarplaner'),
    ['class' => 'kg-btn kg-btn-primary']
);
echo html_writer::end_div();
echo html_writer::tag('style', '
.kg-admin-grid{display:grid;grid-template-columns:1fr;gap:16px;margin:12px 0 20px}
.kg-admin-card{padding:14px;border:1px solid #d1d5db;border-radius:10px;background:#fff}
.kg-admin-card h3{margin-top:0}
.kg-admin-row{display:flex;flex-direction:column;gap:6px;margin:10px 0}
.kg-admin-row input[type=\"text\"],.kg-admin-row textarea,.kg-admin-row select,.kg-admin-row input[type=\"file\"]{max-width:100%;min-height:36px;padding:8px;border:1px solid #d1d5db;border-radius:8px}
.kg-admin-actions{margin-top:12px}
.kg-action-link{white-space:nowrap}
.kg-reviewer-form{display:flex;flex-direction:column;gap:8px;min-width:280px}
.kg-reviewdiff-link{font-weight:600}
.kg-tag-dropdown{position:relative}
.kg-tag-dropdown-toggle{width:100%;min-height:36px;padding:8px;border:1px solid #d1d5db;border-radius:8px;background:#fff;text-align:left;cursor:pointer}
.kg-tag-dropdown-panel{position:absolute;z-index:20;left:0;right:0;max-height:220px;overflow:auto;background:#fff;border:1px solid #d1d5db;border-radius:8px;padding:8px;box-shadow:0 6px 20px rgba(0,0,0,.12)}
.kg-tag-option{display:flex;align-items:center;gap:8px;padding:4px 2px}
.kg-hidden{display:none}
.kg-review-diff{display:flex;flex-direction:column;gap:10px}
.kg-review-section-title{font-weight:600;margin:4px 0}
.kg-diff-method{border:1px solid #d1d5db;border-radius:8px;padding:8px;background:#fff}
.kg-diff-method-title{font-weight:600;margin-bottom:6px}
.kg-diff-table{width:100%;border-collapse:collapse;font-size:12px}
.kg-diff-table th,.kg-diff-table td{border:1px solid #e5e7eb;padding:6px;vertical-align:top}
.kg-diff-value{display:inline-block;white-space:pre-wrap}
.kg-diff-added{color:#166534}
.kg-diff-removed{color:#b91c1c}
.kg-diff-replaced{color:#b45309}
.kg-diff-before.kg-diff-removed,.kg-diff-before.kg-diff-replaced{text-decoration:line-through}
.kg-diff-badge{padding:2px 6px;border-radius:999px;font-size:11px;font-weight:600}
.kg-diff-badge-added{background:#dcfce7;color:#166534}
.kg-diff-badge-removed{background:#fee2e2;color:#b91c1c}
.kg-diff-badge-replaced{background:#fef3c7;color:#b45309}
.kg-diff-decision{min-width:130px}
.kg-modal{position:fixed;inset:0;z-index:2000;background:rgba(0,0,0,.45);display:flex;align-items:flex-start;justify-content:center;padding:36px 16px}
.kg-modal-content{background:#fff;border-radius:12px;box-shadow:0 20px 48px rgba(0,0,0,.25);width:min(1200px,96vw);max-height:88vh;overflow:auto;padding:16px}
.kg-modal-header{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px}
.kg-modal-title{font-size:18px;font-weight:700}
.kg-modal-close{border:1px solid #d1d5db;background:#fff;border-radius:8px;padding:6px 10px;cursor:pointer}
.kg-modal-actions{display:flex;gap:8px;justify-content:flex-end;margin-top:12px}
.kg-inline-rename{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.kg-inline-rename input[type=\"text\"]{min-width:180px;max-width:260px;padding:8px;border:1px solid #d1d5db;border-radius:8px}
.kg-shortname-cell{display:flex;flex-direction:column;gap:6px}
.kg-name-with-edit{display:inline-flex;align-items:center;gap:6px}
.kg-name-edit-btn{display:inline-flex;align-items:center;justify-content:center;padding:0;margin:0;border:0;background:transparent;color:#646464;cursor:pointer}
.kg-name-edit-btn:hover{border:0;background:transparent;color:#2F80AB}
.kg-name-edit-btn .icon{margin:0}
.kg-inline-rename .kg-btn{margin-top:0;margin-bottom:0}
.kg-btn,.kg-admin-card input[type=\"submit\"],.kg-admin-card button:not(.kg-name-edit-btn),.kg-row .kg-btn{
  display:inline-flex;align-items:center;justify-content:center;gap:6px;
  min-height:36px;padding:8px 12px;border:1px solid #E3051B;border-radius:8px;
  background:#E3051B;color:#fff;text-decoration:none;cursor:pointer;
  margin-top:8px;margin-bottom:8px
}
.kg-btn:hover,.kg-admin-card input[type=\"submit\"]:hover,.kg-admin-card button:not(.kg-name-edit-btn):hover,.kg-row .kg-btn:hover{
  background:#882A30;border-color:#882A30;color:#fff;text-decoration:none
}
.kg-admin-card input[type=\"submit\"]:disabled,.kg-admin-card button:disabled{
  background:#fff;border-color:#c5ccd3;color:#6b7280;cursor:not-allowed
}
.kg-admin-card .fitem_actionbuttons .btn-primary{
  border-color:#E3051B;background:#E3051B;color:#fff
}
.kg-admin-card .fitem_actionbuttons .btn-primary:hover,.kg-admin-card .fitem_actionbuttons .btn-primary:focus{
  border-color:#882A30;background:#882A30;color:#fff
}
');

if ($message !== '') {
    echo $OUTPUT->notification($message, $error ? 'notifyproblem' : 'notifysuccess');
}

echo html_writer::start_div('kg-admin-grid');

echo html_writer::start_div('kg-admin-card');
echo html_writer::tag('h3', get_string('globalmethodsets', 'local_seminarplaner'));
if (has_capability('local/seminarplaner:publishset', $syscontext)) {
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => (new moodle_url('/local/seminarplaner/manage.php'))->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'syncallactivities']);
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'class' => 'kg-btn',
        'value' => get_string('syncallactivities', 'local_seminarplaner'),
    ]);
    echo html_writer::end_tag('form');
}

$table = new html_table();
$table->head = [
    'ID',
    get_string('name'),
    get_string('concepttypecol', 'local_seminarplaner'),
    get_string('shortname'),
    'Anzahl Seminareinheiten',
    get_string('publishedbycol', 'local_seminarplaner'),
    get_string('status', 'moodle'),
    get_string('actions'),
];

$usercache = [];

foreach ($methodsets as $set) {
    $actions = [];
    if (!empty($set->currentversion)) {
        $methodcount = (int)$DB->count_records('local_kgen_method', [
            'methodsetid' => (int)$set->id,
            'methodsetversionid' => (int)$set->currentversion,
        ]);
    } else {
        $methodcount = (int)$DB->count_records('local_kgen_method', ['methodsetid' => (int)$set->id]);
    }

    $publishedbyname = '-';
    $publishedbyid = 0;
    if (!empty($set->currentversion)) {
        $currentversion = $repo->get_version((int)$set->currentversion);
        if ($currentversion && !empty($currentversion->publishedby)) {
            $publishedbyid = (int)$currentversion->publishedby;
        }
    }
    if (!$publishedbyid) {
        $pubversion = $DB->get_record_sql(
            "SELECT publishedby
               FROM {local_kgen_methodset_ver}
              WHERE methodsetid = :methodsetid
                AND status = :status
                AND publishedby IS NOT NULL
           ORDER BY timemodified DESC",
            ['methodsetid' => (int)$set->id, 'status' => 'published'],
            IGNORE_MULTIPLE
        );
        if ($pubversion && !empty($pubversion->publishedby)) {
            $publishedbyid = (int)$pubversion->publishedby;
        }
    }
    if ($publishedbyid) {
        if (!array_key_exists($publishedbyid, $usercache)) {
            $usercache[$publishedbyid] = $DB->get_record('user', ['id' => $publishedbyid],
                'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename');
        }
        if (!empty($usercache[$publishedbyid])) {
            $publishedbyname = fullname($usercache[$publishedbyid]);
        }
    }

    if (has_capability('local/seminarplaner:archiveglobalset', $syscontext)) {
        $actions[] = html_writer::link(new moodle_url('/local/seminarplaner/manage.php', [
            'action' => 'delete',
            'sesskey' => sesskey(),
            'methodsetid' => $set->id,
        ]), get_string('deletemethodset', 'local_seminarplaner'), [
            'class' => 'kg-action-link',
            'onclick' => "return confirm(" . json_encode(get_string('deleteconfirm', 'local_seminarplaner', $set->displayname)) . ");",
        ]);
    }

    $canrename = has_capability('local/seminarplaner:editdraftset', $syscontext) ||
        has_capability('local/seminarplaner:archiveglobalset', $syscontext);
    $renameform = '';
    $namecell = s($set->displayname);
    if ($canrename) {
        $renameformid = 'kg-inline-rename-' . (int)$set->id;
        $renameinputid = 'kg-inline-rename-input-' . (int)$set->id;
        $renameform = html_writer::start_tag('form', [
            'method' => 'post',
            'action' => (new moodle_url('/local/seminarplaner/manage.php'))->out(false),
            'class' => 'kg-inline-rename kg-hidden',
            'id' => $renameformid,
        ]);
        $renameform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $renameform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'renamemethodset']);
        $renameform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'methodsetid', 'value' => (int)$set->id]);
        $renameform .= html_writer::empty_tag('input', [
            'type' => 'text',
            'id' => $renameinputid,
            'name' => 'newdisplayname',
            'value' => (string)$set->displayname,
            'maxlength' => 255,
            'required' => 'required',
            'data-kg-rename-input' => '1',
            'aria-label' => get_string('renamemethodset', 'local_seminarplaner'),
        ]);
        $renameform .= html_writer::empty_tag('input', [
            'type' => 'submit',
            'value' => get_string('renamemethodset', 'local_seminarplaner'),
            'class' => 'kg-btn',
        ]);
        $renameform .= html_writer::end_tag('form');

        $editbutton = html_writer::tag('button',
            $OUTPUT->pix_icon('t/editstring', get_string('edit')),
            [
                'type' => 'button',
                'class' => 'kg-name-edit-btn',
                'data-kg-rename-toggle' => $renameformid,
                'aria-controls' => $renameformid,
                'aria-label' => get_string('renamemethodset', 'local_seminarplaner'),
            ]
        );
        $namecell = html_writer::tag('span', s($set->displayname) . $editbutton, ['class' => 'kg-name-with-edit']);
    }
    $shortnamecell = html_writer::start_div('kg-shortname-cell');
    $shortnamecell .= html_writer::tag('span', s($set->shortname));
    if ($renameform !== '') {
        $shortnamecell .= $renameform;
    }
    $shortnamecell .= html_writer::end_div();

    // D57/D55: Objektart-Auswahl (Methoden-Sammlung vs. Seminarkonzept).
    $currenttype = ((string)($set->concepttype ?? 'sammlung') === 'seminarkonzept') ? 'seminarkonzept' : 'sammlung';
    if ($canrename) {
        $typcell = html_writer::start_tag('form', [
            'method' => 'post',
            'action' => (new moodle_url('/local/seminarplaner/manage.php'))->out(false),
            'class' => 'kg-concepttype-form',
        ]);
        $typcell .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $typcell .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'setconcepttype']);
        $typcell .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'methodsetid', 'value' => (int)$set->id]);
        $typcell .= html_writer::select([
            'sammlung' => get_string('concepttype_sammlung', 'local_seminarplaner'),
            'seminarkonzept' => get_string('concepttype_seminarkonzept', 'local_seminarplaner'),
        ], 'concepttype', $currenttype, false, [
            'class' => 'kg-concepttype-select',
            'onchange' => 'this.form.submit();',
            'aria-label' => get_string('concepttypecol', 'local_seminarplaner'),
        ]);
        // Fallback ohne JavaScript.
        $typcell .= html_writer::empty_tag('input', [
            'type' => 'submit',
            'value' => get_string('save'),
            'class' => 'kg-btn kg-concepttype-save',
        ]);
        $typcell .= html_writer::end_tag('form');
    } else {
        $typcell = s(get_string('concepttype_' . $currenttype, 'local_seminarplaner'));
    }

    $table->data[] = [
        $set->id,
        $namecell,
        $typcell,
        $shortnamecell,
        $methodcount,
        s($publishedbyname),
        s($set->status),
        implode(' | ', $actions),
    ];
}

echo html_writer::table($table);
echo html_writer::end_div();
echo html_writer::script("\n(function() {\n    document.querySelectorAll('[data-kg-rename-toggle]').forEach(function(btn) {\n        btn.addEventListener('click', function() {\n            var targetid = btn.getAttribute('data-kg-rename-toggle');\n            if (!targetid) {\n                return;\n            }\n            var form = document.getElementById(targetid);\n            if (!form) {\n                return;\n            }\n            form.classList.toggle('kg-hidden');\n            if (!form.classList.contains('kg-hidden')) {\n                var input = form.querySelector('[data-kg-rename-input]');\n                if (input) {\n                    input.focus();\n                    if (input.select) {\n                        input.select();\n                    }\n                }\n            }\n        });\n    });\n})();\n");

if (has_capability('local/seminarplaner:importglobalset', $syscontext)) {
    echo html_writer::start_div('kg-admin-card');

    echo html_writer::tag('h4', get_string('importnewsettitle', 'local_seminarplaner'));
    echo html_writer::tag('p', get_string('importnewset_desc', 'local_seminarplaner'));
    echo html_writer::tag('p', get_string('importstep1newset', 'local_seminarplaner'));
    echo html_writer::tag('p', get_string('importstep2file', 'local_seminarplaner'));
    echo html_writer::tag('p', get_string('importstep3run', 'local_seminarplaner'));
    $newsetform->display();
    echo html_writer::end_div();

    echo html_writer::start_div('kg-admin-card');
    echo html_writer::tag('h4', get_string('importexistingsettitle', 'local_seminarplaner'));
    echo html_writer::tag('p', get_string('importexistingset_desc', 'local_seminarplaner'));
    echo html_writer::tag('p', get_string('importstep1existingset', 'local_seminarplaner'));
    echo html_writer::tag('p', get_string('importstep2file', 'local_seminarplaner'));
    echo html_writer::tag('p', get_string('importstep3run', 'local_seminarplaner'));
    $existingsetform->display();
    echo html_writer::end_div();
}

echo html_writer::end_div();

echo $OUTPUT->footer();
