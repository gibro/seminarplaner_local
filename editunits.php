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
 * Lets assigned concept owners edit the seminar units of a global set.
 *
 * The review flow only helps while something is under review. This page is the way in
 * when nothing was submitted: the unit is edited straight in the version the set points
 * at, and every edit is written to the workflow log.
 *
 * @package    local_seminarplaner
 * @copyright  2026 Guido Brombach <gibro@posteo.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/bootstrap.php');
require_once(__DIR__ . '/locallib.php');

$methodsetid = required_param('methodsetid', PARAM_INT);
$methodid = optional_param('methodid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHANUMEXT);

require_login();
$syscontext = context_system::instance();

$repo = new \local_seminarplaner\local\repository\methodset_repository();
$reviewerrepo = new \local_seminarplaner\local\repository\reviewer_repository();

$set = $repo->get_methodset($methodsetid);
if (!$set) {
    throw new moodle_exception('invalidparameter');
}

// Same gate as the review tools: the capability alone is not enough, the user has to be
// one of the people actually responsible for this set.
$scopecontext = $syscontext;
if (!empty($set->scopecontextid)) {
    $candidate = context::instance_by_id((int)$set->scopecontextid, IGNORE_MISSING);
    if ($candidate) {
        $scopecontext = $candidate;
    }
}
require_capability('local/seminarplaner:reviewset', $scopecontext);
if (!in_array((int)$USER->id, $reviewerrepo->get_reviewer_userids($methodsetid), true)) {
    throw new required_capability_exception($scopecontext, 'local/seminarplaner:reviewset', 'nopermissions', '');
}

$pageurl = new moodle_url('/local/seminarplaner/editunits.php', ['methodsetid' => $methodsetid]);
$PAGE->set_url($pageurl);
$PAGE->set_context($syscontext);
$PAGE->set_title(get_string('editunitstitle', 'local_seminarplaner'));
$PAGE->set_heading(get_string('editunitstitle', 'local_seminarplaner'));

$message = '';
$error = false;

$versionid = (int)($set->currentversion ?? 0);
$maxbytes = get_user_max_upload_file_size($syscontext, $CFG->maxbytes);

$units = [];
if ($versionid > 0) {
    $units = $DB->get_records('local_kgen_method',
        ['methodsetid' => $methodsetid, 'methodsetversionid' => $versionid], 'id ASC');
}
if (!$units) {
    $units = $DB->get_records('local_kgen_method', ['methodsetid' => $methodsetid], 'id ASC');
}

$editing = ($methodid > 0 && isset($units[$methodid])) ? $units[$methodid] : null;

// Löschen einer Seminareinheit - eigener, bestätigter Pfad, unabhängig vom Bearbeiten-Formular.
if ($action === 'deleteunit' && $methodid > 0 && isset($units[$methodid])) {
    $unit = $units[$methodid];
    if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
        $deleted = local_seminarplaner_delete_global_unit((int)$unit->id, (int)$USER->id);
        redirect($pageurl, get_string('editunitdeleted', 'local_seminarplaner', s($deleted)));
    }

    $unittitle = trim((string)$unit->title) !== '' ? (string)$unit->title : '—';
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string((string)$set->displayname));
    echo $OUTPUT->confirm(
        get_string('editunitdeleteconfirm', 'local_seminarplaner', s($unittitle)),
        new moodle_url($pageurl, [
            'action' => 'deleteunit',
            'methodid' => (int)$unit->id,
            'confirm' => 1,
            'sesskey' => sesskey(),
        ]),
        $pageurl
    );
    echo $OUTPUT->footer();
    exit;
}

$mform = null;
if ($editing) {
    $mform = new \local_seminarplaner\form\edit_unit_form($pageurl->out(false), [
        'maxbytes' => $maxbytes,
        'context' => $syscontext,
    ]);

    // Vorhandene Anhaenge in den Entwurfsbereich des Dateimanagers spiegeln.
    $draftitemid = file_get_submitted_draft_itemid('materialien');
    if (!$mform->is_submitted()) {
        $link = $DB->get_record('local_kgen_method_file',
            ['methodid' => (int)$editing->id, 'kind' => 'material'], '*', IGNORE_MULTIPLE);
        $fs = get_file_storage();
        $usercontext = context_user::instance((int)$USER->id);
        file_prepare_draft_area($draftitemid, null, null, null, null);
        if ($link) {
            foreach ($fs->get_area_files((int)$syscontext->id, 'local_seminarplaner', 'method_material',
                (int)$link->fileitemid, 'filename', false) as $file) {
                $fs->create_file_from_storedfile([
                    'contextid' => $usercontext->id,
                    'component' => 'user',
                    'filearea' => 'draft',
                    'itemid' => $draftitemid,
                    'filepath' => '/',
                    'filename' => (string)$file->get_filename(),
                ], $file);
            }
        }
    }

    $richfields = \local_seminarplaner\form\edit_unit_form::rich_text_fields();
    $multifields = \local_seminarplaner\form\edit_unit_form::multi_fields();

    $data = (object)[
        'methodsetid' => $methodsetid,
        'methodid' => (int)$editing->id,
        'action' => 'saveunit',
        'title' => (string)$editing->title,
        'tags' => (string)$editing->tags,
        'autor_kontakt' => (string)$editing->autor_kontakt,
        'zeitbedarf' => (string)$editing->zeitbedarf,
        'gruppengroesse' => (string)$editing->gruppengroesse,
        'vorbereitung' => (string)$editing->vorbereitung,
        'materialien' => $draftitemid,
    ];
    foreach ($multifields as $field) {
        $data->$field = array_values(array_filter(explode('##', (string)($editing->$field ?? ''))));
    }
    foreach ($richfields as $field) {
        $data->{$field . '_editor'} = ['text' => (string)($editing->$field ?? ''), 'format' => FORMAT_HTML];
    }
    $mform->set_data($data);

    if ($mform->is_cancelled()) {
        redirect($pageurl);
    } else if ($submitted = $mform->get_data()) {
        try {
            $values = [
                'title' => (string)$submitted->title,
                'tags' => (string)$submitted->tags,
                'autor_kontakt' => (string)$submitted->autor_kontakt,
                'zeitbedarf' => (string)$submitted->zeitbedarf,
                'gruppengroesse' => (string)$submitted->gruppengroesse,
                'vorbereitung' => (string)$submitted->vorbereitung,
            ];
            foreach ($multifields as $field) {
                $values[$field] = implode('##', (array)($submitted->$field ?? []));
            }
            foreach ($richfields as $field) {
                $editor = (array)($submitted->{$field . '_editor'} ?? []);
                $values[$field] = (string)($editor['text'] ?? '');
            }

            $changed = local_seminarplaner_update_global_unit((int)$editing->id, $values, (int)$USER->id);
            $files = local_seminarplaner_sync_unit_materials_from_draft(
                (int)$editing->id,
                (int)$submitted->materialien,
                (int)$USER->id
            );

            $parts = [];
            if ($changed) {
                $parts[] = get_string('editunitsaved', 'local_seminarplaner', implode(', ', $changed));
            }
            if ($files['added'] || $files['removed']) {
                $parts[] = get_string('editunitfileschanged', 'local_seminarplaner', (object)[
                    'added' => count($files['added']),
                    'removed' => count($files['removed']),
                ]);
            }
            redirect($pageurl, $parts
                ? implode(' ', $parts)
                : get_string('editunitunchanged', 'local_seminarplaner'));
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $error = true;
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string((string)$set->displayname));

// Wie auf den anderen Seiten des Plugins bringt die Seite ihr CSS selbst mit -
// local_seminarplaner hat keine styles.css.
echo html_writer::tag('style', '
.kg-row{margin:10px 0}
.kg-btn{
  display:inline-flex;align-items:center;justify-content:center;gap:6px;
  min-height:36px;padding:8px 12px;border:1px solid #E3051B;border-radius:8px;
  background:#E3051B;color:#fff;text-decoration:none;cursor:pointer;
  margin-top:8px;margin-bottom:8px
}
.kg-btn:hover,.kg-btn:focus{
  background:#882A30;border-color:#882A30;color:#fff;text-decoration:none
}
.kg-btn-plain{background:#fff;color:#20242b;border-color:#c5ccd3}
.kg-btn-plain:hover,.kg-btn-plain:focus{background:#eef1f4;color:#20242b;border-color:#98a0aa}
');

if ($message !== '') {
    echo $OUTPUT->notification($message, $error ? 'notifyproblem' : 'notifysuccess');
}

if ($mform) {
    echo html_writer::div(
        html_writer::link($pageurl, get_string('editunitsbacktolist', 'local_seminarplaner'),
            ['class' => 'kg-btn kg-btn-plain']),
        'kg-row'
    );
    $mform->display();
} else {
    echo html_writer::tag('p', get_string('editunitsintro', 'local_seminarplaner'));
    echo html_writer::div(
        html_writer::link(new moodle_url('/local/seminarplaner/reviewrequests.php'),
            get_string('backtoreviewrequests', 'local_seminarplaner'), ['class' => 'kg-btn']),
        'kg-row'
    );
    echo html_writer::tag('h4', get_string('editunitscount', 'local_seminarplaner', count($units)));

    $table = new html_table();
    $table->head = [get_string('editunitfield_title', 'local_seminarplaner'), get_string('actions')];
    foreach ($units as $unit) {
        $title = trim((string)$unit->title) !== '' ? (string)$unit->title : '—';
        $editlink = html_writer::link(new moodle_url('/local/seminarplaner/editunits.php',
            ['methodsetid' => $methodsetid, 'methodid' => (int)$unit->id]), get_string('edit'));
        $deletelink = html_writer::link(new moodle_url('/local/seminarplaner/editunits.php',
            ['methodsetid' => $methodsetid, 'methodid' => (int)$unit->id, 'action' => 'deleteunit']),
            get_string('delete'), ['class' => 'text-danger']);
        $table->data[] = [
            s($title),
            $editlink . ' &middot; ' . $deletelink,
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
