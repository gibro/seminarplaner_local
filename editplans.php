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
 * Lists the plans of a global Seminarkonzept and lets the people responsible delete them.
 *
 * @package    local_seminarplaner
 * @copyright  2026 Guido Brombach <gibro@posteo.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/bootstrap.php');
require_once(__DIR__ . '/locallib.php');

use local_seminarplaner\local\service\konzept_snapshot;

$methodsetid = required_param('methodsetid', PARAM_INT);
$plankey = optional_param('plan', '', PARAM_ALPHANUM);
$action = optional_param('action', '', PARAM_ALPHANUMEXT);

require_login();
$syscontext = context_system::instance();

$repo = new \local_seminarplaner\local\repository\methodset_repository();
$reviewerrepo = new \local_seminarplaner\local\repository\reviewer_repository();

$set = $repo->get_methodset($methodsetid);
if (!$set) {
    throw new moodle_exception('invalidparameter');
}

// Same gate as editunits.php: the capability alone is not enough, the user has to be one
// of the people actually responsible for this set.
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

$pageurl = new moodle_url('/local/seminarplaner/editplans.php', ['methodsetid' => $methodsetid]);
$PAGE->set_url($pageurl);
$PAGE->set_context($syscontext);
$PAGE->set_title(get_string('editplanstitle', 'local_seminarplaner'));
$PAGE->set_heading(get_string('editplanstitle', 'local_seminarplaner'));

$versionid = (int)($set->currentversion ?? 0);
$version = $versionid > 0 ? $repo->get_version($versionid) : null;
$plans = [];
if ($version) {
    foreach (konzept_snapshot::decode((string)$version->snapshotjson)['plans'] as $plan) {
        $plans[konzept_snapshot::plan_key((string)$plan['name'])] = $plan;
    }
}

if ($action === 'deleteplan' && isset($plans[$plankey])) {
    if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
        $deleted = local_seminarplaner_delete_global_plan($methodsetid, $versionid, $plankey, (int)$USER->id);
        redirect($pageurl, get_string('editplandeleted', 'local_seminarplaner', s($deleted)));
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string((string)$set->displayname));
    echo $OUTPUT->confirm(
        get_string('editplandeleteconfirm', 'local_seminarplaner', s((string)$plans[$plankey]['name'])),
        new moodle_url($pageurl, [
            'action' => 'deleteplan',
            'plan' => $plankey,
            'confirm' => 1,
            'sesskey' => sesskey(),
        ]),
        $pageurl
    );
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string((string)$set->displayname));
echo html_writer::tag('p', get_string('editplansintro', 'local_seminarplaner'));
echo html_writer::div(
    html_writer::link(new moodle_url('/local/seminarplaner/reviewrequests.php'),
        get_string('backtoreviewrequests', 'local_seminarplaner'), ['class' => 'btn btn-secondary']),
    'mb-3'
);

if (!$plans) {
    echo $OUTPUT->notification(get_string('editplansnone', 'local_seminarplaner'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('name'),
        get_string('editplancol_tage', 'local_seminarplaner'),
        get_string('editplancol_einheiten', 'local_seminarplaner'),
        get_string('editplancol_bausteine', 'local_seminarplaner'),
        get_string('actions'),
    ];
    foreach ($plans as $key => $plan) {
        $counts = konzept_snapshot::plan_counts($plan);
        $table->data[] = [
            s((string)$plan['name']),
            (int)$counts['tage'],
            (int)$counts['einheiten'],
            (int)$counts['bausteine'],
            html_writer::link(new moodle_url($pageurl, ['action' => 'deleteplan', 'plan' => $key]),
                get_string('delete'), ['class' => 'text-danger']),
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
