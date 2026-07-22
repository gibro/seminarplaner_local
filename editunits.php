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

if ($action === 'saveunit' && confirm_sesskey()) {
    try {
        $target = $DB->get_record('local_kgen_method', ['id' => $methodid], '*', MUST_EXIST);
        if ((int)$target->methodsetid !== $methodsetid) {
            throw new moodle_exception('invalidparameter');
        }

        $values = [];
        foreach (array_keys(local_seminarplaner_review_field_labels()) as $field) {
            if ($field === 'materialien') {
                continue;
            }
            $values[$field] = optional_param($field, '', PARAM_TEXT);
        }

        $changed = local_seminarplaner_update_global_unit((int)$methodid, $values, (int)$USER->id);
        $message = $changed
            ? get_string('editunitsaved', 'local_seminarplaner', implode(', ', $changed))
            : get_string('editunitunchanged', 'local_seminarplaner');
        $methodid = 0;
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $error = true;
    }
}

$units = [];
if ($versionid > 0) {
    $units = $DB->get_records('local_kgen_method',
        ['methodsetid' => $methodsetid, 'methodsetversionid' => $versionid], 'id ASC');
}
if (!$units) {
    $units = $DB->get_records('local_kgen_method', ['methodsetid' => $methodsetid], 'id ASC');
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string((string)$set->displayname));
echo html_writer::tag('p', get_string('editunitsintro', 'local_seminarplaner'));
echo html_writer::div(
    html_writer::link(new moodle_url('/local/seminarplaner/reviewrequests.php'),
        get_string('backtoreviewrequests', 'local_seminarplaner'), ['class' => 'kg-btn']),
    'kg-row'
);

echo html_writer::tag('style', '
.kg-eu-list{border:1px solid #d1d5db;border-radius:10px;overflow:hidden;margin:14px 0}
.kg-eu-item{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:9px 12px;border-bottom:1px solid #e5e7eb;background:#fff}
.kg-eu-item:last-child{border-bottom:0}
.kg-eu-item strong{font-weight:600}
.kg-eu-form{border:1px solid #d1d5db;border-radius:10px;padding:14px;background:#f9fafb;margin:14px 0}
.kg-eu-field{display:flex;flex-direction:column;gap:4px;margin-bottom:10px}
.kg-eu-field label{font-size:12px;font-weight:600;color:#374151}
.kg-eu-field input[type="text"],.kg-eu-field textarea{width:100%;padding:8px;border:1px solid #d1d5db;border-radius:8px;font:inherit}
.kg-btn{display:inline-flex;align-items:center;gap:6px;min-height:36px;padding:8px 12px;border:1px solid #E3051B;border-radius:8px;background:#E3051B;color:#fff;text-decoration:none;cursor:pointer;margin:6px 6px 6px 0}
.kg-btn:hover{background:#882A30;border-color:#882A30;color:#fff;text-decoration:none}
.kg-btn-plain{background:#fff;color:#20242b;border-color:#c5ccd3}
.kg-btn-plain:hover{background:#eef1f4;color:#20242b;border-color:#98a0aa}
.kg-row{margin:10px 0}
');

if ($message !== '') {
    echo $OUTPUT->notification($message, $error ? 'notifyproblem' : 'notifysuccess');
}

$editing = null;
if ($methodid > 0) {
    foreach ($units as $unit) {
        if ((int)$unit->id === $methodid) {
            $editing = $unit;
            break;
        }
    }
}

if ($editing) {
    // Longer prose gets a textarea, short values a single line - a one-line Ablauf field
    // would be unusable.
    $multiline = ['kurzbeschreibung', 'ablauf', 'lernziele', 'vorbereitung', 'risiken_tipps', 'debrief'];

    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false), 'class' => 'kg-eu-form']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'saveunit']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'methodsetid', 'value' => $methodsetid]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'methodid', 'value' => (int)$editing->id]);

    foreach (local_seminarplaner_review_field_labels() as $field => $label) {
        if ($field === 'materialien') {
            continue;
        }
        $value = (string)($editing->$field ?? '');
        $id = 'kg-eu-' . $field;
        echo html_writer::start_div('kg-eu-field');
        echo html_writer::tag('label', s($label), ['for' => $id]);
        if (in_array($field, $multiline, true)) {
            echo html_writer::tag('textarea', s($value), ['id' => $id, 'name' => $field, 'rows' => 4]);
        } else {
            echo html_writer::empty_tag('input', [
                'type' => 'text',
                'id' => $id,
                'name' => $field,
                'value' => $value,
            ]);
        }
        echo html_writer::end_div();
    }

    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'class' => 'kg-btn',
        'value' => get_string('editunitsave', 'local_seminarplaner'),
    ]);
    echo html_writer::link($pageurl, get_string('cancel'), ['class' => 'kg-btn kg-btn-plain']);
    echo html_writer::end_tag('form');
} else {
    echo html_writer::tag('h4', get_string('editunitscount', 'local_seminarplaner', count($units)));
    echo html_writer::start_div('kg-eu-list');
    foreach ($units as $unit) {
        $title = trim((string)$unit->title) !== '' ? (string)$unit->title : '—';
        echo html_writer::start_div('kg-eu-item');
        echo html_writer::tag('strong', s($title));
        echo html_writer::link(
            new moodle_url('/local/seminarplaner/editunits.php',
                ['methodsetid' => $methodsetid, 'methodid' => (int)$unit->id]),
            get_string('edit'),
            ['class' => 'kg-btn kg-btn-plain']
        );
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
}

echo $OUTPUT->footer();
