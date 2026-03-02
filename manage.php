<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . "/../../config.php");
require_once($CFG->libdir . "/formslib.php");
require_once(__DIR__ . "/locallib.php");

$action = optional_param("action", "", PARAM_ALPHANUMEXT);

require_login();
$syscontext = context_system::instance();
require_capability('local/konzeptgenerator:viewglobalsets', $syscontext);

$PAGE->set_url('/local/konzeptgenerator/manage.php');
$PAGE->set_context($syscontext);
$PAGE->set_title(get_string('manageglobalsets', 'local_konzeptgenerator'));
$PAGE->set_heading(get_string('manageglobalsets', 'local_konzeptgenerator'));

$repo = new \local_konzeptgenerator\local\repository\methodset_repository();
$stringmanager = get_string_manager();
$nameexplainertext = $stringmanager->string_exists('nameexplainer', 'local_konzeptgenerator')
    ? get_string('nameexplainer', 'local_konzeptgenerator')
    : 'Name = sichtbarer Anzeigename.';
$shortnameexplainertext = $stringmanager->string_exists('shortnameexplainer', 'local_konzeptgenerator')
    ? get_string('shortnameexplainer', 'local_konzeptgenerator')
    : 'Kurzbezeichnung = technischer, eindeutiger Schlüssel ohne Leerzeichen';

$message = '';
$error = false;

if ($action === 'createdraft' && confirm_sesskey()) {
    require_capability('local/konzeptgenerator:createdraftset', $syscontext);

    $shortname = required_param('shortname', PARAM_ALPHANUMEXT);
    $displayname = required_param('displayname', PARAM_TEXT);
    $description = optional_param('description', '', PARAM_TEXT);

    try {
        $methodsetid = $repo->create_methodset_draft($shortname, $displayname, $description, (int)$syscontext->id, (int)$USER->id);
        $repo->create_version($methodsetid, 1, 'draft', '{}', (int)$USER->id);
        $message = get_string('draftcreated', 'local_konzeptgenerator', $methodsetid);
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $error = true;
    }
}


if ($action === 'exportmoddata' && confirm_sesskey()) {
    require_capability('local/konzeptgenerator:exportglobalset', $syscontext);
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
        local_konzeptgenerator_send_moddata_export((int)$set->id, (int)$versionid, (string)$set->displayname);
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $error = true;
    }
}


if ($action === 'delete' && confirm_sesskey()) {
    require_capability('local/konzeptgenerator:archiveglobalset', $syscontext);
    $methodsetid = required_param('methodsetid', PARAM_INT);

    try {
        $methodset = $repo->get_methodset($methodsetid);
        if (!$methodset) {
            throw new moodle_exception('invalidparameter');
        }
        $repo->delete_methodset_cascade($methodsetid);
        $message = get_string('deleteok', 'local_konzeptgenerator', $methodsetid);
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $error = true;
    }
}

if ($action === 'renamemethodset' && confirm_sesskey()) {
    if (!has_capability('local/konzeptgenerator:editdraftset', $syscontext) &&
            !has_capability('local/konzeptgenerator:archiveglobalset', $syscontext)) {
        throw new required_capability_exception($syscontext, 'local/konzeptgenerator:editdraftset', 'nopermissions', '');
    }
    $methodsetid = required_param('methodsetid', PARAM_INT);
    $newdisplayname = trim((string)optional_param('newdisplayname', '', PARAM_TEXT));

    try {
        if ($newdisplayname === '') {
            throw new moodle_exception('renameerrornoname', 'local_konzeptgenerator');
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
        $message = get_string('renameok', 'local_konzeptgenerator', (object)[
            'oldname' => (string)$methodset->displayname,
            'newname' => $newdisplayname,
        ]);
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $error = true;
    }
}

if ($action === 'importmoddata_newset' && confirm_sesskey()) {
    require_capability('local/konzeptgenerator:importglobalset', $syscontext);
    require_capability('local/konzeptgenerator:createdraftset', $syscontext);
    require_capability('local/konzeptgenerator:editdraftset', $syscontext);

    $shortname = required_param('shortname', PARAM_ALPHANUMEXT);
    $displayname = required_param('displayname', PARAM_TEXT);
    $description = optional_param('description', '', PARAM_TEXT);
    $draftitemid = required_param('importfilenew', PARAM_INT);

    try {
        $draftfile = local_konzeptgenerator_get_draft_upload($draftitemid);
        if (!$draftfile) {
            throw new moodle_exception('importerrornofile', 'local_konzeptgenerator');
        }

        if ((int)$draftfile->get_filesize() > LOCAL_KONZEPTGENERATOR_IMPORT_MAX_BYTES) {
            throw new moodle_exception('invalidparameter');
        }

        $filename = clean_param((string)$draftfile->get_filename(), PARAM_FILE);
        $tmpfilepath = make_request_directory() . '/kgen-import-' . time() . '-' . random_int(1000, 9999);
        file_put_contents($tmpfilepath, (string)$draftfile->get_content());
        try {
            $payload = local_konzeptgenerator_extract_rows_from_upload($tmpfilepath, $filename);
        } finally {
            @unlink($tmpfilepath);
        }
        $rows = (array)($payload['rows'] ?? []);
        $zipfiles = (array)($payload['files'] ?? []);
        $records = [];
        foreach ($rows as $row) {
            $mapped = local_konzeptgenerator_map_legacy_row($row);
            if ($mapped !== null) {
                $records[] = $mapped;
            }
        }
        if (!$records) {
            throw new moodle_exception('importerrornomethods', 'local_konzeptgenerator');
        }

        $methodsetid = $repo->create_methodset_draft($shortname, $displayname, $description, (int)$syscontext->id, (int)$USER->id);
        $versionid = $repo->create_version($methodsetid, 1, 'draft', '{}', (int)$USER->id);
        $count = local_konzeptgenerator_import_records_to_set(
            (int)$methodsetid,
            (int)$versionid,
            (int)$USER->id,
            $records,
            $zipfiles
        );
        $message = get_string('importnewsetok', 'local_konzeptgenerator', (object)[
            'count' => $count,
            'id' => $methodsetid,
        ]);
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $error = true;
    }
}

if ($action === 'importmoddata_existingset' && confirm_sesskey()) {
    require_capability('local/konzeptgenerator:importglobalset', $syscontext);
    require_capability('local/konzeptgenerator:editdraftset', $syscontext);
    $methodsetid = required_param('methodsetid', PARAM_INT);
    $draftitemid = required_param('importfileexisting', PARAM_INT);

    try {
        $methodset = $repo->get_methodset($methodsetid);
        if (!$methodset) {
            throw new moodle_exception('invalidparameter');
        }
        if ((string)$methodset->status !== 'draft') {
            throw new moodle_exception('importerrordraftrequired', 'local_konzeptgenerator');
        }
        $draftfile = local_konzeptgenerator_get_draft_upload($draftitemid);
        if (!$draftfile) {
            throw new moodle_exception('importerrornofile', 'local_konzeptgenerator');
        }

        if ((int)$draftfile->get_filesize() > LOCAL_KONZEPTGENERATOR_IMPORT_MAX_BYTES) {
            throw new moodle_exception('invalidparameter');
        }

        $filename = clean_param((string)$draftfile->get_filename(), PARAM_FILE);
        $tmpfilepath = make_request_directory() . '/kgen-import-' . time() . '-' . random_int(1000, 9999);
        file_put_contents($tmpfilepath, (string)$draftfile->get_content());
        try {
            $payload = local_konzeptgenerator_extract_rows_from_upload($tmpfilepath, $filename);
        } finally {
            @unlink($tmpfilepath);
        }
        $rows = (array)($payload['rows'] ?? []);
        $zipfiles = (array)($payload['files'] ?? []);

        $records = [];
        foreach ($rows as $row) {
            $mapped = local_konzeptgenerator_map_legacy_row($row);
            if ($mapped !== null) {
                $records[] = $mapped;
            }
        }
        if (!$records) {
            throw new moodle_exception('importerrornomethods', 'local_konzeptgenerator');
        }

        $count = local_konzeptgenerator_import_records_to_set(
            (int)$methodsetid,
            (int)$methodset->currentversion,
            (int)$USER->id,
            $records,
            $zipfiles
        );
        $message = get_string('importok', 'local_konzeptgenerator', $count);
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $error = true;
    }
}

if ($action === 'syncallactivities' && confirm_sesskey()) {
    require_capability('local/konzeptgenerator:publishset', $syscontext);
    try {
        if (!class_exists('\\mod_konzeptgenerator\\local\\service\\methodset_sync_service')) {
            throw new moodle_exception('invalidparameter');
        }
        $syncservice = new \mod_konzeptgenerator\local\service\methodset_sync_service();
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
        $message = get_string('syncallactivitiesok', 'local_konzeptgenerator', (object)[
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
    if ((string)$set->status === 'draft') {
        $draftoptions[(int)$set->id] = format_string($set->displayname) . ' (#' . (int)$set->id . ')';
    }
}

$maxuploadbytes = get_user_max_upload_file_size($syscontext, $CFG->maxbytes);
$newsetdraftitemid = file_get_submitted_draft_itemid('importfilenew');
file_prepare_draft_area($newsetdraftitemid, $syscontext->id, 'local_konzeptgenerator', 'import_upload', 0, [
    'subdirs' => 0,
    'maxfiles' => 1,
    'maxbytes' => $maxuploadbytes,
    'accepted_types' => ['.csv', '.zip'],
]);
$existingdraftitemid = file_get_submitted_draft_itemid('importfileexisting');
file_prepare_draft_area($existingdraftitemid, $syscontext->id, 'local_konzeptgenerator', 'import_upload', 0, [
    'subdirs' => 0,
    'maxfiles' => 1,
    'maxbytes' => $maxuploadbytes,
    'accepted_types' => ['.csv', '.zip'],
]);

$newsetform = new \local_konzeptgenerator\form\import_new_set_form(
    new moodle_url('/local/konzeptgenerator/manage.php'),
    ['maxbytes' => $maxuploadbytes]
);
$newsetform->set_data((object)[
    'action' => 'importmoddata_newset',
    'importfilenew' => $newsetdraftitemid,
]);

$existingsetform = new \local_konzeptgenerator\form\import_existing_set_form(
    new moodle_url('/local/konzeptgenerator/manage.php'),
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
echo $OUTPUT->heading(get_string('manageglobalsets', 'local_konzeptgenerator'));
echo html_writer::start_div('kg-row');
echo html_writer::link(
    new moodle_url('/local/konzeptgenerator/reviewrequests.php'),
    get_string('reviewrequestspage', 'local_konzeptgenerator'),
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
.kg-btn,.kg-admin-card input[type=\"submit\"],.kg-admin-card button,.kg-row .kg-btn{
  display:inline-flex;align-items:center;justify-content:center;gap:6px;
  min-height:36px;padding:8px 12px;border:1px solid #005ca9;border-radius:8px;
  background:#005ca9;color:#fff;text-decoration:none;cursor:pointer;
  margin-top:8px;margin-bottom:8px
}
.kg-btn:hover,.kg-admin-card input[type=\"submit\"]:hover,.kg-admin-card button:hover,.kg-row .kg-btn:hover{
  background:#004a87;border-color:#004a87;color:#fff;text-decoration:none
}
.kg-admin-card input[type=\"submit\"]:disabled,.kg-admin-card button:disabled{
  background:#fff;border-color:#c5ccd3;color:#6b7280;cursor:not-allowed
}
');

if ($message !== '') {
    echo $OUTPUT->notification($message, $error ? 'notifyproblem' : 'notifysuccess');
}

echo html_writer::start_div('kg-admin-grid');

if (has_capability('local/konzeptgenerator:createdraftset', $syscontext)) {
    echo html_writer::start_div('kg-admin-card');
    echo html_writer::tag('h3', get_string('createdraftset', 'local_konzeptgenerator'));
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => (new moodle_url('/local/konzeptgenerator/manage.php'))->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'createdraft']);
    echo html_writer::start_div('kg-admin-row');
    echo html_writer::label(get_string('name'), 'kg-name');
    echo html_writer::empty_tag('input', ['id' => 'kg-name', 'type' => 'text', 'name' => 'displayname', 'required' => 'required']);
    echo html_writer::tag('small', $nameexplainertext);
    echo html_writer::end_div();
    echo html_writer::start_div('kg-admin-row');
    echo html_writer::label(get_string('shortname'), 'kg-short');
    echo html_writer::empty_tag('input', ['id' => 'kg-short', 'type' => 'text', 'name' => 'shortname', 'required' => 'required']);
    echo html_writer::tag('small', $shortnameexplainertext);
    echo html_writer::end_div();
    echo html_writer::start_div('kg-admin-row');
    echo html_writer::label(get_string('description'), 'kg-desc');
    echo html_writer::tag('textarea', '', ['id' => 'kg-desc', 'name' => 'description', 'rows' => 3]);
    echo html_writer::end_div();
    echo html_writer::start_div('kg-admin-actions');
    echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('savechanges')]);
    echo html_writer::end_div();
    echo html_writer::end_tag('form');
    echo html_writer::end_div();
}

if (has_capability('local/konzeptgenerator:importglobalset', $syscontext)) {
    echo html_writer::start_div('kg-admin-card');

    echo html_writer::tag('h4', get_string('importnewsettitle', 'local_konzeptgenerator'));
    echo html_writer::tag('p', get_string('importnewset_desc', 'local_konzeptgenerator'));
    echo html_writer::tag('p', get_string('importstep1newset', 'local_konzeptgenerator'));
    echo html_writer::tag('p', get_string('importstep2file', 'local_konzeptgenerator'));
    echo html_writer::tag('p', get_string('importstep3run', 'local_konzeptgenerator'));
    $newsetform->display();

    echo html_writer::empty_tag('hr');
    echo html_writer::tag('h4', get_string('importexistingsettitle', 'local_konzeptgenerator'));
    echo html_writer::tag('p', get_string('importexistingset_desc', 'local_konzeptgenerator'));
    echo html_writer::tag('p', get_string('importstep1existingset', 'local_konzeptgenerator'));
    echo html_writer::tag('p', get_string('importstep2file', 'local_konzeptgenerator'));
    echo html_writer::tag('p', get_string('importstep3run', 'local_konzeptgenerator'));
    $existingsetform->display();
    echo html_writer::end_div();
}

echo html_writer::start_div('kg-admin-card');
echo html_writer::tag('h3', get_string('globalmethodsets', 'local_konzeptgenerator'));
if (has_capability('local/konzeptgenerator:publishset', $syscontext)) {
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => (new moodle_url('/local/konzeptgenerator/manage.php'))->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'syncallactivities']);
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'class' => 'kg-btn',
        'value' => get_string('syncallactivities', 'local_konzeptgenerator'),
    ]);
    echo html_writer::end_tag('form');
}

$table = new html_table();
$table->head = [
    'ID',
    get_string('shortname'),
    get_string('name'),
    get_string('methodcountcol', 'local_konzeptgenerator'),
    get_string('publishedbycol', 'local_konzeptgenerator'),
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

    if (has_capability('local/konzeptgenerator:archiveglobalset', $syscontext)) {
        $actions[] = html_writer::link(new moodle_url('/local/konzeptgenerator/manage.php', [
            'action' => 'delete',
            'sesskey' => sesskey(),
            'methodsetid' => $set->id,
        ]), get_string('deletemethodset', 'local_konzeptgenerator'), [
            'class' => 'kg-action-link',
            'onclick' => "return confirm(" . json_encode(get_string('deleteconfirm', 'local_konzeptgenerator', $set->displayname)) . ");",
        ]);
    }

    if (has_capability('local/konzeptgenerator:editdraftset', $syscontext) ||
            has_capability('local/konzeptgenerator:archiveglobalset', $syscontext)) {
        $renameform = html_writer::start_tag('form', [
            'method' => 'post',
            'action' => (new moodle_url('/local/konzeptgenerator/manage.php'))->out(false),
            'class' => 'kg-inline-rename',
        ]);
        $renameform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $renameform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'renamemethodset']);
        $renameform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'methodsetid', 'value' => (int)$set->id]);
        $renameform .= html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'newdisplayname',
            'value' => (string)$set->displayname,
            'maxlength' => 255,
            'required' => 'required',
            'aria-label' => get_string('renamemethodset', 'local_konzeptgenerator'),
        ]);
        $renameform .= html_writer::empty_tag('input', [
            'type' => 'submit',
            'value' => get_string('renamemethodset', 'local_konzeptgenerator'),
            'class' => 'kg-btn',
        ]);
        $renameform .= html_writer::end_tag('form');
        $actions[] = $renameform;
    }

    $table->data[] = [
        $set->id,
        s($set->shortname),
        s($set->displayname),
        $methodcount,
        s($publishedbyname),
        s($set->status),
        implode(' | ', $actions),
    ];
}

echo html_writer::table($table);
echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();
