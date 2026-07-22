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
 * Reviewrequests.
 *
 * @package    local_seminarplaner
 * @copyright  2026 Guido Brombach <gibro@posteo.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/bootstrap.php');
require_once(__DIR__ . '/locallib.php');

$action = optional_param('action', '', PARAM_ALPHANUMEXT);

require_login();
$syscontext = context_system::instance();

$PAGE->set_url('/local/seminarplaner/reviewrequests.php');
$PAGE->set_context($syscontext);
$PAGE->set_title(get_string('reviewrequestspage', 'local_seminarplaner'));
$PAGE->set_heading(get_string('manageglobalsets', 'local_seminarplaner'));

$repo = new \local_seminarplaner\local\repository\methodset_repository();
$reviewerrepo = new \local_seminarplaner\local\repository\reviewer_repository();
$workflow = new \local_seminarplaner\local\service\workflow_service();

/**
 * Resolve scope context for a method set.
 *
 * @param stdClass $methodset
 * @param context_system $fallback
 * @return context
 */
function local_seminarplaner_get_set_scope_context(stdClass $methodset, context_system $fallback): context {
    $scopeid = (int)($methodset->scopecontextid ?? 0);
    if ($scopeid <= 0) {
        return $fallback;
    }
    try {
        return context::instance_by_id($scopeid, MUST_EXIST);
    } catch (Throwable $e) {
        return $fallback;
    }
}

/**
 * Return assignable reviewer users for a method set scope.
 *
 * @param context $scopecontext
 * @return array<int, stdClass>
 */
function local_seminarplaner_get_reviewer_candidates(context $scopecontext): array {
    global $DB;

    $fields = 'u.id,u.firstname,u.lastname,u.firstnamephonetic,u.lastnamephonetic,u.middlename,u.alternatename,u.email';
    $candidates = get_users_by_capability(
        $scopecontext,
        'local/seminarplaner:reviewset',
        $fields,
        'u.lastname ASC, u.firstname ASC'
    );
    $byid = [];
    foreach ($candidates as $candidate) {
        $byid[(int)$candidate->id] = $candidate;
    }

    // Fallback for explicit global role assignments on system context.
    if ((int)$scopecontext->contextlevel === CONTEXT_SYSTEM) {
        $sysctxid = (int)$scopecontext->id;
        $fallback = $DB->get_records_sql(
            "SELECT DISTINCT {$fields}
               FROM {user} u
               JOIN {role_assignments} ra ON ra.userid = u.id
               JOIN {role_capabilities} rc ON rc.roleid = ra.roleid
              WHERE ra.contextid = :sysctxid
                AND rc.contextid = :sysctxid2
                AND rc.capability = :capability
                AND rc.permission = :permallow
                AND u.deleted = 0
                AND u.suspended = 0",
            [
                'sysctxid' => $sysctxid,
                'sysctxid2' => $sysctxid,
                'capability' => 'local/seminarplaner:reviewset',
                'permallow' => CAP_ALLOW,
            ]
        );
        foreach ($fallback as $candidate) {
            $byid[(int)$candidate->id] = $candidate;
        }
    }

    uasort($byid, static function (stdClass $a, stdClass $b): int {
        $alast = core_text::strtolower((string)($a->lastname ?? ''));
        $blast = core_text::strtolower((string)($b->lastname ?? ''));
        if ($alast !== $blast) {
            return $alast <=> $blast;
        }
        $afirst = core_text::strtolower((string)($a->firstname ?? ''));
        $bfirst = core_text::strtolower((string)($b->firstname ?? ''));
        if ($afirst !== $bfirst) {
            return $afirst <=> $bfirst;
        }
        return (int)$a->id <=> (int)$b->id;
    });

    return $byid;
}

/**
 * Check whether the current user should see management controls for a scope.
 *
 * @param context $scopecontext
 * @param context_system $syscontext
 * @return bool
 */
function local_seminarplaner_user_can_manage_scope(context $scopecontext, context_system $syscontext): bool {
    return has_capability('local/seminarplaner:createdraftset', $scopecontext)
        || has_capability('local/seminarplaner:editdraftset', $scopecontext)
        || has_capability('local/seminarplaner:importglobalset', $scopecontext)
        || has_capability('local/seminarplaner:exportglobalset', $scopecontext)
        || has_capability('local/seminarplaner:publishset', $syscontext)
        || has_capability('local/seminarplaner:archiveglobalset', $syscontext);
}

/**
 * Check whether a user is assigned as reviewer for a method set.
 *
 * @param local_seminarplaner\local\repository\reviewer_repository $reviewerrepo
 * @param int $methodsetid
 * @param int $userid
 * @return bool
 */
function local_seminarplaner_user_is_assigned_reviewer(
    \local_seminarplaner\local\repository\reviewer_repository $reviewerrepo,
    int $methodsetid,
    int $userid
): bool {
    return in_array($userid, $reviewerrepo->get_reviewer_userids($methodsetid), true);
}

/**
 * Render diff bucket sections.
 *
 * @param array<string, mixed> $diff
 * @param array<string, string> $decisions
 * @param bool $allowdecisions
 * @return string
 */
function local_seminarplaner_render_review_diff_sections(array $diff, array $decisions, bool $allowdecisions): string {
    $content = html_writer::start_div('kg-review-diff');
    if (!empty($diff['added'])) {
        $content .= html_writer::tag(
            'div',
            get_string('reviewdiffnew', 'local_seminarplaner'),
            ['class' => 'kg-review-section-title']
        );
        foreach ($diff['added'] as $item) {
            $content .= local_seminarplaner_render_diff_method($item, $decisions, $allowdecisions);
        }
    }
    if (!empty($diff['changed'])) {
        $content .= html_writer::tag(
            'div',
            get_string('reviewdiffchanged', 'local_seminarplaner'),
            ['class' => 'kg-review-section-title']
        );
        foreach ($diff['changed'] as $item) {
            $content .= local_seminarplaner_render_diff_method($item, $decisions, $allowdecisions);
        }
    }
    if (!empty($diff['removed'])) {
        $content .= html_writer::tag(
            'div',
            get_string('reviewdiffremoved', 'local_seminarplaner'),
            ['class' => 'kg-review-section-title']
        );
        foreach ($diff['removed'] as $item) {
            $content .= local_seminarplaner_render_diff_method($item, $decisions, $allowdecisions);
        }
    }
    $content .= html_writer::end_div();

    return $content;
}

/**
 * Build the review diff link and modal for a set.
 *
 * @param stdClass $set
 * @param local_seminarplaner\local\repository\methodset_repository $repo
 * @param bool $allowdecisions
 * @param string $modalprefix
 * @return array{cell:string,modal:string}
 */
function local_seminarplaner_build_reviewdiff_payload(
    stdClass $set,
    \local_seminarplaner\local\repository\methodset_repository $repo,
    bool $allowdecisions,
    string $modalprefix
): array {
    global $DB, $USER;

    if (empty($set->currentversion)) {
        return ['cell' => get_string('reviewdiffnone', 'local_seminarplaner'), 'modal' => ''];
    }

    $currentversion = $repo->get_version((int)$set->currentversion);
    if (!$currentversion) {
        return ['cell' => get_string('reviewdiffnone', 'local_seminarplaner'), 'modal' => ''];
    }

    $baserows = [];
    $previousversion = $DB->get_record_sql(
        "SELECT id
           FROM {local_kgen_methodset_ver}
          WHERE methodsetid = :methodsetid
            AND versionnum < :versionnum
       ORDER BY versionnum DESC",
        ['methodsetid' => (int)$set->id, 'versionnum' => (int)$currentversion->versionnum],
        IGNORE_MULTIPLE
    );
    if ($previousversion) {
        $baserows = $DB->get_records('local_kgen_method', [
            'methodsetid' => (int)$set->id,
            'methodsetversionid' => (int)$previousversion->id,
        ]);
    }

    $newrows = $DB->get_records('local_kgen_method', [
        'methodsetid' => (int)$set->id,
        'methodsetversionid' => (int)$set->currentversion,
    ]);
    $diff = local_seminarplaner_compute_review_diff($baserows, $newrows);
    if (empty($diff['added']) && empty($diff['changed']) && empty($diff['removed'])) {
        return ['cell' => get_string('reviewdiffnone', 'local_seminarplaner'), 'modal' => ''];
    }

    $modalid = $modalprefix . '-' . (int)$set->id;
    $cell = html_writer::link('#', get_string('reviewdiffopen', 'local_seminarplaner'), [
        'class' => 'kg-reviewdiff-link',
        'data-kg-open-modal' => $modalid,
    ]);

    $decisions = [];
    $records = $DB->get_records('local_kgen_review_decision', [
        'methodsetversionid' => (int)$set->currentversion,
        'reviewerid' => (int)$USER->id,
    ]);
    foreach ($records as $record) {
        $decisions[(string)$record->itemkey] = (string)$record->decision;
    }

    if ($allowdecisions) {
        $modalcontent = html_writer::start_tag('form', [
            'method' => 'post',
            'action' => (new moodle_url('/local/seminarplaner/reviewrequests.php'))->out(false),
            'class' => 'kg-modal-form',
        ]);
        $modalcontent .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $modalcontent .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'action',
            'value' => 'savereviewdecisions',
        ]);
        $modalcontent .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'methodsetid', 'value' => (int)$set->id]);
        $modalcontent .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'versionid',
            'value' => (int)$set->currentversion,
        ]);
        $modalcontent .= html_writer::start_div('kg-review-diff-tools');
        $modalcontent .= html_writer::tag('button', get_string('reviewacceptallchanges', 'local_seminarplaner'), [
            'type' => 'button',
            'class' => 'kg-btn kg-btn-primary',
            'data-kg-accept-all-decisions' => '1',
        ]);
        $modalcontent .= html_writer::end_div();
        $modalcontent .= html_writer::div(
            local_seminarplaner_render_review_diff_sections($diff, $decisions, true),
            'kg-modal-body'
        );
        $modalcontent .= html_writer::start_div('kg-modal-actions');
        $modalcontent .= html_writer::tag('button', get_string('savereviewdecisions', 'local_seminarplaner'), [
            'type' => 'submit',
            'class' => 'kg-btn kg-btn-primary',
        ]);
        // Die Entscheidungen wirklich einpflegen: abgelehnte Zeilen fallen auf den
        // vorherigen Stand zurueck, angenommene bleiben. Danach traegt die Version
        // genau das, was freigegeben wurde - vorher war das ein Alles-oder-nichts.
        // Eigener Feldname statt eines zweiten "action": das Formular traegt bereits
        // ein verstecktes action-Feld, zwei gleichnamige Felder waeren Gluecksache.
        $modalcontent .= html_writer::tag('button', get_string('applyreviewdecisions', 'local_seminarplaner'), [
            'type' => 'submit',
            'class' => 'kg-btn',
            'name' => 'applynow',
            'value' => '1',
            'onclick' => 'return confirm(' . json_encode(
                get_string('applyreviewdecisionsconfirm', 'local_seminarplaner')
            ) . ');',
        ]);
        $modalcontent .= html_writer::tag('button', get_string('closebuttontitle', 'moodle'), [
            'type' => 'button',
            'class' => 'kg-modal-close',
            'data-kg-close-modal' => $modalid,
        ]);
        $modalcontent .= html_writer::end_div();
        $modalcontent .= html_writer::end_tag('form');
    } else {
        $modalcontent = html_writer::div(
            local_seminarplaner_render_review_diff_sections($diff, $decisions, false),
            'kg-modal-body'
        );
        $modalcontent .= html_writer::start_div('kg-modal-actions');
        $modalcontent .= html_writer::tag('button', get_string('closebuttontitle', 'moodle'), [
            'type' => 'button',
            'class' => 'kg-modal-close',
            'data-kg-close-modal' => $modalid,
        ]);
        $modalcontent .= html_writer::end_div();
    }

    $modal = html_writer::start_div('kg-modal kg-hidden', ['id' => $modalid, 'data-kg-modal' => '1']);
    $modal .= html_writer::start_div('kg-modal-content');
    $modal .= html_writer::start_div('kg-modal-header');
    $modal .= html_writer::tag(
        'div',
        get_string('reviewdiffpopuptitle', 'local_seminarplaner', format_string($set->displayname)),
        ['class' => 'kg-modal-title']
    );
    $modal .= html_writer::tag('button', '×', [
        'type' => 'button',
        'class' => 'kg-modal-close',
        'data-kg-close-modal' => $modalid,
    ]);
    $modal .= html_writer::end_div();
    $modal .= $modalcontent;
    $modal .= html_writer::end_div();
    $modal .= html_writer::end_div();

    return ['cell' => $cell, 'modal' => $modal];
}

/**
 * Render reviewer assignment controls for a set.
 *
 * @param int $setid
 * @param array<int, stdClass> $candidates
 * @param array<int, int> $selectedreviewers
 * @return string
 */
function local_seminarplaner_render_reviewer_cell(int $setid, array $candidates, array $selectedreviewers): string {
    $options = [];
    foreach ($candidates as $candidate) {
        $label = fullname($candidate);
        if (!empty($candidate->email)) {
            $label .= ' <' . $candidate->email . '>';
        }
        $options[(int)$candidate->id] = $label;
    }

    $reviewerselectid = 'kg-reviewers-' . $setid;
    $reviewercell = html_writer::start_tag('form', [
        'method' => 'post',
        'action' => (new moodle_url('/local/seminarplaner/reviewrequests.php'))->out(false),
        'class' => 'kg-reviewer-form',
    ]);
    $reviewercell .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $reviewercell .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'assignreviewers']);
    $reviewercell .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'methodsetid', 'value' => $setid]);
    $reviewercell .= html_writer::label(get_string('assignreviewers', 'local_seminarplaner'), $reviewerselectid);

    $reviewercell .= html_writer::start_div('kg-tag-dropdown', [
        'id' => 'kg-reviewer-dropdown-' . $setid,
        'data-kg-reviewer-dropdown' => '1',
    ]);
    $reviewercell .= html_writer::tag('button', get_string('assignreviewers', 'local_seminarplaner'), [
        'type' => 'button',
        'id' => 'kg-reviewer-toggle-' . $setid,
        'class' => 'kg-tag-dropdown-toggle',
        'data-kg-reviewer-toggle' => '1',
    ]);
    $reviewercell .= html_writer::start_div('kg-tag-dropdown-panel kg-hidden', [
        'id' => 'kg-reviewer-panel-' . $setid,
        'data-kg-reviewer-panel' => '1',
    ]);

    foreach ($options as $reviewerid => $label) {
        $checkboxid = $reviewerselectid . '-' . (int)$reviewerid;
        $attrs = [
            'type' => 'checkbox',
            'id' => $checkboxid,
            'name' => 'reviewerids[]',
            'value' => (int)$reviewerid,
            'data-kg-reviewer-checkbox' => '1',
        ];
        if (in_array((int)$reviewerid, $selectedreviewers, true)) {
            $attrs['checked'] = 'checked';
        }
        $reviewercell .= html_writer::start_tag('label', ['class' => 'kg-tag-option', 'for' => $checkboxid]);
        $reviewercell .= html_writer::empty_tag('input', $attrs);
        $reviewercell .= html_writer::tag('span', s($label));
        $reviewercell .= html_writer::end_tag('label');
    }

    $reviewercell .= html_writer::end_div();
    $reviewercell .= html_writer::end_div();
    $reviewercell .= html_writer::empty_tag('input', [
        'type' => 'submit',
        'value' => get_string('savereviewers', 'local_seminarplaner'),
        'class' => 'kg-btn',
    ]);
    $reviewercell .= html_writer::end_tag('form');

    return $reviewercell;
}

/**
 * Render a table for review/manage sets.
 *
 * @param array<int, stdClass> $sets
 * @param local_seminarplaner\local\repository\methodset_repository $repo
 * @param array<int, array<int, int>> $assignedreviewers
 * @param array<int, array<int, stdClass>> $reviewercandidatesbyset
 * @param array<int, bool> $setmanagerrights
 * @param array<int, bool> $setsubmitrights
 * @param array<int, bool> $setreviewrights
 * @param array<int, bool> $isassignedreviewer
 * @param context_system $syscontext
 * @param bool $showreviewers
 * @param string $modalprefix
 * @return array{table:string,modals:string}
 */
function local_seminarplaner_render_set_table(
    array $sets,
    \local_seminarplaner\local\repository\methodset_repository $repo,
    array $assignedreviewers,
    array $reviewercandidatesbyset,
    array $setmanagerrights,
    array $setsubmitrights,
    array $setreviewrights,
    array $isassignedreviewer,
    context_system $syscontext,
    bool $showreviewers,
    string $modalprefix
): array {
    $table = new html_table();
    $table->head = [
        'ID',
        get_string('shortname'),
        get_string('name'),
        get_string('status', 'moodle'),
    ];
    if ($showreviewers) {
        $table->head[] = get_string('reviewerscol', 'local_seminarplaner');
    }
    $table->head[] = get_string('reviewdiffcol', 'local_seminarplaner');
    $table->head[] = get_string('actions');

    $diffmodals = '';
    foreach ($sets as $set) {
        $setid = (int)$set->id;
        $actions = [];
        $reviewercountforaction = count($assignedreviewers[$setid] ?? []);
        $setcanassignreviewers = !empty($setmanagerrights[$setid]);
        if ((string)$set->status === 'draft' && $setcanassignreviewers && $reviewercountforaction > 0) {
            $actions[] = html_writer::link(new moodle_url('/local/seminarplaner/reviewrequests.php', [
                'action' => 'transition',
                'sesskey' => sesskey(),
                'methodsetid' => $setid,
                'versionid' => $set->currentversion,
                'tostatus' => 'review',
            ]), get_string('submitforreview', 'local_seminarplaner'), ['class' => 'kg-action-link']);
        } else if ((string)$set->status === 'draft' && $setcanassignreviewers) {
            $actions[] = html_writer::tag('span', get_string('reviewersrequired', 'local_seminarplaner'), [
                'class' => 'kg-action-link',
                'style' => 'color:#b91c1c;font-weight:600',
            ]);
        }
        if ((string)$set->status === 'review' && !empty($setreviewrights[$setid]) && !empty($isassignedreviewer[$setid])) {
            $actions[] = html_writer::link(new moodle_url('/local/seminarplaner/reviewrequests.php', [
                'action' => 'transition',
                'sesskey' => sesskey(),
                'methodsetid' => $setid,
                'versionid' => $set->currentversion,
                'tostatus' => 'draft',
            ]), get_string('backtodraft', 'local_seminarplaner'), ['class' => 'kg-action-link']);
        }
        if ((string)$set->status === 'review' && has_capability('local/seminarplaner:publishset', $syscontext)) {
            $actions[] = html_writer::link(new moodle_url('/local/seminarplaner/reviewrequests.php', [
                'action' => 'transition',
                'sesskey' => sesskey(),
                'methodsetid' => $setid,
                'versionid' => $set->currentversion,
                'tostatus' => 'published',
            ]), get_string('publishset', 'local_seminarplaner'), ['class' => 'kg-action-link']);
        }

        $row = [
            (int)$set->id,
            s((string)$set->shortname),
            s((string)$set->displayname),
            s((string)$set->status),
        ];

        if ($showreviewers) {
            $reviewercell = '-';
            if ($setcanassignreviewers) {
                $reviewercell = local_seminarplaner_render_reviewer_cell(
                    $setid,
                    $reviewercandidatesbyset[$setid] ?? [],
                    $assignedreviewers[$setid] ?? []
                );
            }
            $row[] = $reviewercell;
        }

        $allowdecisions = ((string)$set->status === 'review')
            && !empty($setreviewrights[$setid])
            && !empty($isassignedreviewer[$setid]);
        $reviewdiffpayload = local_seminarplaner_build_reviewdiff_payload($set, $repo, $allowdecisions, $modalprefix);
        $row[] = $reviewdiffpayload['cell'];
        $row[] = implode(' | ', $actions);
        $table->data[] = $row;
        $diffmodals .= $reviewdiffpayload['modal'];
    }

    return [
        'table' => html_writer::table($table),
        'modals' => $diffmodals,
    ];
}

$message = '';
$error = false;

if ($action === 'transition' && confirm_sesskey()) {
    $methodsetid = required_param('methodsetid', PARAM_INT);
    $versionid = optional_param('versionid', 0, PARAM_INT);
    $tostatus = required_param('tostatus', PARAM_ALPHA);

    try {
        $methodset = $repo->get_methodset((int)$methodsetid);
        if (!$methodset) {
            throw new moodle_exception('invalidparameter');
        }
        // The action links are baked into the page with the status it had when it was
        // rendered. If another action (or another tab) has moved the set on since, the
        // link is stale - that is normal use, not a coding error, so show a friendly
        // "reload the page" hint instead of letting the workflow throw a coding_exception.
        $rules = new \local_seminarplaner\local\workflow\workflow_rules();
        if (!$rules->can_transition((string)$methodset->status, $tostatus)) {
            throw new moodle_exception('transitionstale', 'local_seminarplaner');
        }
        $scopecontext = local_seminarplaner_get_set_scope_context($methodset, $syscontext);
        if ($tostatus === 'review') {
            require_capability('local/seminarplaner:submitforreview', $scopecontext);
            $reviewers = $reviewerrepo->get_reviewer_userids((int)$methodsetid);
            if (!$reviewers) {
                throw new moodle_exception('reviewersrequired', 'local_seminarplaner');
            }
        } else if ($tostatus === 'published') {
            require_capability('local/seminarplaner:publishset', $syscontext);
        } else if ($tostatus === 'draft') {
            require_capability('local/seminarplaner:reviewset', $scopecontext);
            if (!local_seminarplaner_user_is_assigned_reviewer($reviewerrepo, (int)$methodsetid, (int)$USER->id)) {
                throw new required_capability_exception($scopecontext, 'local/seminarplaner:reviewset', 'nopermissions', '');
            }
        } else {
            throw new moodle_exception('invalidparameter');
        }

        $workflow->transition($methodsetid, $versionid ?: null, $tostatus, (int)$USER->id, 'Manual transition');
        $message = get_string('transitionok', 'local_seminarplaner');
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $error = true;
    }
}

if ($action === 'assignreviewers' && confirm_sesskey()) {
    $methodsetid = required_param('methodsetid', PARAM_INT);
    $rawreviewerids = $_POST['reviewerids'] ?? [];
    if (!is_array($rawreviewerids)) {
        $rawreviewerids = [$rawreviewerids];
    }

    try {
        $methodset = $repo->get_methodset((int)$methodsetid);
        if (!$methodset) {
            throw new moodle_exception('invalidparameter');
        }
        $scopecontext = local_seminarplaner_get_set_scope_context($methodset, $syscontext);
        if (!local_seminarplaner_user_can_manage_scope($scopecontext, $syscontext)) {
            throw new required_capability_exception($scopecontext, 'local/seminarplaner:editdraftset', 'nopermissions', '');
        }

        $candidates = local_seminarplaner_get_reviewer_candidates($scopecontext);
        $allowed = [];
        foreach ($candidates as $candidate) {
            $allowed[(int)$candidate->id] = true;
        }

        $clean = [];
        foreach ($rawreviewerids as $rawreviewerid) {
            $reviewerid = (int)clean_param($rawreviewerid, PARAM_INT);
            if ($reviewerid > 0 && !empty($allowed[$reviewerid])) {
                $clean[] = $reviewerid;
            }
        }

        $count = $reviewerrepo->replace_reviewers((int)$methodsetid, $clean, (int)$USER->id);
        $message = get_string('reviewersassigned', 'local_seminarplaner', $count);
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $error = true;
    }
}

if ($action === 'savereviewdecisions' && confirm_sesskey()) {
    $methodsetid = required_param('methodsetid', PARAM_INT);
    $versionid = required_param('versionid', PARAM_INT);
    $rawdecisions = $_POST['decisions'] ?? [];
    if (!is_array($rawdecisions)) {
        $rawdecisions = [];
    }

    try {
        $set = $repo->get_methodset($methodsetid);
        if (!$set) {
            throw new moodle_exception('invalidparameter');
        }
        $scopecontext = local_seminarplaner_get_set_scope_context($set, $syscontext);
        require_capability('local/seminarplaner:reviewset', $scopecontext);
        if (!local_seminarplaner_user_is_assigned_reviewer($reviewerrepo, (int)$methodsetid, (int)$USER->id)) {
            throw new required_capability_exception($scopecontext, 'local/seminarplaner:reviewset', 'nopermissions', '');
        }

        $version = $repo->get_version($versionid);
        if (!$version || (int)$version->methodsetid !== (int)$methodsetid) {
            throw new moodle_exception('invalidparameter');
        }

        $validdecisions = ['pending' => true, 'accepted' => true, 'rejected' => true];
        $now = time();
        foreach ($rawdecisions as $itemkey => $decision) {
            $itemkey = clean_param($itemkey, PARAM_ALPHANUMEXT);
            $decision = clean_param($decision, PARAM_ALPHA);
            if ($itemkey === '' || empty($validdecisions[$decision])) {
                continue;
            }
            $existing = $DB->get_record('local_kgen_review_decision', [
                'methodsetversionid' => (int)$versionid,
                'itemkey' => $itemkey,
                'reviewerid' => (int)$USER->id,
            ]);
            if ($existing) {
                $existing->decision = $decision;
                $existing->timemodified = $now;
                $DB->update_record('local_kgen_review_decision', $existing);
            } else {
                $DB->insert_record('local_kgen_review_decision', (object)[
                    'methodsetid' => (int)$methodsetid,
                    'methodsetversionid' => (int)$versionid,
                    'itemkey' => $itemkey,
                    'reviewerid' => (int)$USER->id,
                    'decision' => $decision,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
            }
        }

        $baserows = [];
        $previousversion = $DB->get_record_sql(
            "SELECT id
               FROM {local_kgen_methodset_ver}
              WHERE methodsetid = :methodsetid
                AND versionnum < :versionnum
           ORDER BY versionnum DESC",
            ['methodsetid' => (int)$methodsetid, 'versionnum' => (int)$version->versionnum],
            IGNORE_MULTIPLE
        );
        if ($previousversion) {
            $baserows = $DB->get_records('local_kgen_method', [
                'methodsetid' => (int)$methodsetid,
                'methodsetversionid' => (int)$previousversion->id,
            ]);
        }
        $newrows = $DB->get_records('local_kgen_method', [
            'methodsetid' => (int)$methodsetid,
            'methodsetversionid' => (int)$versionid,
        ]);
        $diff = local_seminarplaner_compute_review_diff($baserows, $newrows);
        $itemmap = local_seminarplaner_diff_item_map($diff);

        $allreviewerdecisions = $DB->get_records('local_kgen_review_decision', [
            'methodsetversionid' => (int)$versionid,
            'reviewerid' => (int)$USER->id,
        ]);

        $accepted = [];
        $rejected = [];
        foreach ($allreviewerdecisions as $record) {
            $key = (string)$record->itemkey;
            $info = $itemmap[$key] ?? ['title' => $key, 'label' => '', 'status' => 'unknown'];
            $line = trim((string)$info['title']) . ' - ' . trim((string)$info['label'])
                . ' [' . trim((string)$info['status']) . ']';
            if ((string)$record->decision === 'accepted') {
                $accepted[] = $line;
            } else if ((string)$record->decision === 'rejected') {
                $rejected[] = $line;
            }
        }

        $submitevent = $DB->get_record_sql(
            "SELECT actorid
               FROM {local_kgen_workflow_event}
              WHERE methodsetid = :methodsetid
                AND methodsetversionid = :versionid
                AND tostatus = :tostatus
           ORDER BY timecreated DESC",
            ['methodsetid' => (int)$methodsetid, 'versionid' => (int)$versionid, 'tostatus' => 'review'],
            IGNORE_MULTIPLE
        );
        $submitterid = (int)($submitevent->actorid ?? 0);
        if ($submitterid > 0) {
            $submitter = $DB->get_record(
                'user',
                ['id' => $submitterid, 'deleted' => 0, 'suspended' => 0],
                'id,username,email,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename,mailformat'
            );
            if ($submitter) {
                $setname = format_string((string)$set->displayname);
                $a = (object)[
                    'setname' => $setname,
                    'reviewer' => fullname($USER),
                    'acceptedcount' => count($accepted),
                    'rejectedcount' => count($rejected),
                    'acceptedlist' => $accepted ? "- " . implode("\n- ", $accepted) : '-',
                    'rejectedlist' => $rejected ? "- " . implode("\n- ", $rejected) : '-',
                    'manageurl' => (new moodle_url('/local/seminarplaner/reviewrequests.php'))->out(false),
                ];
                $subject = get_string('reviewfeedback_subject', 'local_seminarplaner', $a);
                $text = get_string('reviewfeedback_body', 'local_seminarplaner', $a);
                $html = text_to_html($text, false, false, true);
                email_to_user($submitter, $USER, $subject, $text, $html);
            }
        }

        $message = get_string('reviewdecisionssaved', 'local_seminarplaner');

        // Zweiter Knopf: die eben gespeicherten Entscheidungen auch einpflegen.
        if (optional_param('applynow', 0, PARAM_BOOL)) {
            $applied = local_seminarplaner_apply_review_decisions(
                (int)$methodsetid,
                (int)$versionid,
                (int)$USER->id
            );
            $message .= ' ' . get_string('applyreviewdecisionsok', 'local_seminarplaner', (object)[
                'fields' => (int)$applied['fields'],
                'materials' => (int)$applied['materials'],
                'removed' => (int)$applied['units_removed'],
                'restored' => (int)$applied['units_restored'],
            ]);
            if ((int)$applied['pending'] > 0) {
                $message .= ' ' . get_string('applyreviewdecisionspending', 'local_seminarplaner',
                    (int)$applied['pending']);
            }
        }
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $error = true;
    }
}

$allsets = $repo->list_all_methodsets();
$assignedreviewers = [];
$reviewercandidatesbyset = [];
$reviewercandidatescache = [];
$setmanagerrights = [];
$setsubmitrights = [];
$setreviewrights = [];
$isassignedreviewer = [];
$myreviewsets = [];
$hasreviewscope = false;

foreach ($allsets as $set) {
    $setid = (int)$set->id;
    $assignedreviewers[$setid] = $reviewerrepo->get_reviewer_userids($setid);
    $isassignedreviewer[$setid] = in_array((int)$USER->id, $assignedreviewers[$setid], true);

    $scopecontext = local_seminarplaner_get_set_scope_context($set, $syscontext);
    $scopecontextid = (int)$scopecontext->id;

    $setmanagerrights[$setid] = local_seminarplaner_user_can_manage_scope($scopecontext, $syscontext);
    $setsubmitrights[$setid] = has_capability('local/seminarplaner:submitforreview', $scopecontext);
    $setreviewrights[$setid] = has_capability('local/seminarplaner:reviewset', $scopecontext);
    $hasreviewscope = $hasreviewscope || !empty($setreviewrights[$setid]);

    if (!empty($setsubmitrights[$setid])) {
        if (!array_key_exists($scopecontextid, $reviewercandidatescache)) {
            $reviewercandidatescache[$scopecontextid] = local_seminarplaner_get_reviewer_candidates($scopecontext);
        }
        $reviewercandidatesbyset[$setid] = $reviewercandidatescache[$scopecontextid];
    }

    if ((string)$set->status === 'review' && !empty($setreviewrights[$setid]) && !empty($isassignedreviewer[$setid])) {
        $myreviewsets[$setid] = $set;
    }
}

$managementsets = [];
foreach ($allsets as $set) {
    $setid = (int)$set->id;
    if (!empty($setmanagerrights[$setid])) {
        $managementsets[$setid] = $set;
    }
}

$canaccesspage = $hasreviewscope
    || !empty($myreviewsets)
    || !empty($managementsets);
if (!$canaccesspage) {
    require_capability('local/seminarplaner:reviewset', $syscontext);
}

$managerview = !empty($managementsets);
if (!$managerview) {
    $PAGE->set_title(get_string('myreviewsheading', 'local_seminarplaner'));
    $PAGE->set_heading(get_string('myreviewsheading', 'local_seminarplaner'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading($managerview
    ? get_string('reviewrequestspage', 'local_seminarplaner')
    : get_string('myreviewsheading', 'local_seminarplaner'));

echo html_writer::tag('style', '
.kg-section{margin-top:24px}
.kg-action-link{white-space:nowrap}
.kg-reviewer-form{display:flex;flex-direction:column;gap:8px;min-width:280px}
.kg-reviewdiff-link{font-weight:600}
.table-responsive{position:relative;z-index:1;overflow-x:auto;overflow-y:visible}
.kg-tag-dropdown{position:relative;z-index:3000}
.kg-tag-dropdown-toggle{width:100%;min-height:36px;padding:8px;border:1px solid #d1d5db;border-radius:8px;
background:#fff;text-align:left;cursor:pointer}
.kg-tag-dropdown-panel{position:absolute;z-index:3100;left:0;right:0;max-height:220px;overflow:auto;background:#fff;
border:1px solid #d1d5db;border-radius:8px;padding:8px;box-shadow:0 6px 20px rgba(0,0,0,.12)}
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
.kg-review-diff-tools{display:flex;justify-content:flex-end;margin-bottom:8px;flex-shrink:0}
.kg-modal{position:fixed;inset:0;z-index:2000;background:rgba(0,0,0,.45);display:flex;align-items:flex-start;
justify-content:center;padding:36px 16px}
.kg-modal-content{background:#fff;border-radius:12px;box-shadow:0 20px 48px rgba(0,0,0,.25);width:min(1200px,96vw);
max-height:88vh;overflow:hidden;padding:16px;display:flex;flex-direction:column}
.kg-modal-form{display:flex;flex-direction:column;flex:1 1 auto;min-height:0}
.kg-modal-body{flex:1 1 auto;min-height:0;overflow:auto;border:1px solid #d1d5db;border-radius:8px;padding:10px;background:#f9fafb}
.kg-modal-header{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px;flex-shrink:0}
.kg-modal-title{font-size:18px;font-weight:700}
.kg-modal-close{border:1px solid #d1d5db;background:#fff;border-radius:8px;padding:6px 10px;cursor:pointer}
.kg-modal-actions{display:flex;gap:8px;justify-content:flex-end;margin-top:12px;flex-shrink:0}
.kg-btn,.singlebutton .btn,.kg-row .kg-btn{
  display:inline-flex;align-items:center;justify-content:center;gap:6px;
  min-height:36px;padding:8px 12px;border:1px solid #E3051B;border-radius:8px;
  background:#E3051B;color:#fff;text-decoration:none;cursor:pointer;
  margin-top:8px;margin-bottom:8px
}
.kg-btn:hover,.singlebutton .btn:hover,.kg-row .kg-btn:hover{
  background:#882A30;border-color:#882A30;color:#fff;text-decoration:none
}
.kg-btn:disabled{
  background:#fff;border-color:#c5ccd3;color:#6b7280;cursor:not-allowed
}
');

if ($message !== '') {
    echo $OUTPUT->notification($message, $error ? 'notifyproblem' : 'notifysuccess');
}

$allmodals = '';

echo html_writer::start_div('kg-section');
if ($managerview) {
    echo $OUTPUT->heading(get_string('myreviewsheading', 'local_seminarplaner'), 3);
}
if (!empty($myreviewsets)) {
    $myreviewtable = local_seminarplaner_render_set_table(
        $myreviewsets,
        $repo,
        $assignedreviewers,
        $reviewercandidatesbyset,
        $setmanagerrights,
        $setsubmitrights,
        $setreviewrights,
        $isassignedreviewer,
        $syscontext,
        false,
        'kg-review-diff-mine'
    );
    echo $myreviewtable['table'];
    $allmodals .= $myreviewtable['modals'];
} else if ($hasreviewscope) {
    echo $OUTPUT->notification(get_string('myreviewsnone', 'local_seminarplaner'), 'info');
}
echo html_writer::end_div();

if ($managerview) {
    echo html_writer::start_div('kg-section');
    echo $OUTPUT->heading(get_string('managequeuesheading', 'local_seminarplaner'), 3);
    echo $OUTPUT->single_button(
        new moodle_url('/local/seminarplaner/manage.php'),
        get_string('manageglobalsets', 'local_seminarplaner'),
        'get'
    );
    $managementtable = local_seminarplaner_render_set_table(
        $managementsets,
        $repo,
        $assignedreviewers,
        $reviewercandidatesbyset,
        $setmanagerrights,
        $setsubmitrights,
        $setreviewrights,
        $isassignedreviewer,
        $syscontext,
        true,
        'kg-review-diff-manage'
    );
    echo $managementtable['table'];
    $allmodals .= $managementtable['modals'];
    echo html_writer::end_div();
}

echo $allmodals;

echo html_writer::script(
    "\n"
    . "(function() {\n"
    . "    var roots = document.querySelectorAll('[data-kg-reviewer-dropdown]');\n"
    . "    var closeAll = function(except) {\n"
    . "        roots.forEach(function(root) {\n"
    . "            var panel = root.querySelector('[data-kg-reviewer-panel]');\n"
    . "            if (!panel) {\n"
    . "                return;\n"
    . "            }\n"
    . "            if (except && root === except) {\n"
    . "                return;\n"
    . "            }\n"
    . "            panel.classList.add('kg-hidden');\n"
    . "        });\n"
    . "    };\n"
    . "    var updateLabel = function(root) {\n"
    . "        var toggle = root.querySelector('[data-kg-reviewer-toggle]');\n"
    . "        var checks = root.querySelectorAll('[data-kg-reviewer-checkbox]');\n"
    . "        if (!toggle || !checks) {\n"
    . "            return;\n"
    . "        }\n"
    . "        var count = 0;\n"
    . "        checks.forEach(function(chk) {\n"
    . "            if (chk.checked) {\n"
    . "                count++;\n"
    . "            }\n"
    . "        });\n"
    . "        toggle.textContent = count ? 'Konzeptverantwortliche (' + count + ')' : 'Konzeptverantwortliche wählen';\n"
    . "    };\n"
    . "\n"
    . "    roots.forEach(function(root) {\n"
    . "        var toggle = root.querySelector('[data-kg-reviewer-toggle]');\n"
    . "        var panel = root.querySelector('[data-kg-reviewer-panel]');\n"
    . "        if (!toggle || !panel) {\n"
    . "            return;\n"
    . "        }\n"
    . "        updateLabel(root);\n"
    . "        toggle.addEventListener('click', function() {\n"
    . "            var ishidden = panel.classList.contains('kg-hidden');\n"
    . "            closeAll(root);\n"
    . "            panel.classList.toggle('kg-hidden', !ishidden);\n"
    . "        });\n"
    . "        root.addEventListener('change', function(event) {\n"
    . "            var target = event.target;\n"
    . "            if (!target || target.getAttribute('data-kg-reviewer-checkbox') !== '1') {\n"
    . "                return;\n"
    . "            }\n"
    . "            updateLabel(root);\n"
    . "        });\n"
    . "    });\n"
    . "    document.addEventListener('click', function(event) {\n"
    . "        var target = event.target;\n"
    . "        var inside = false;\n"
    . "        roots.forEach(function(root) {\n"
    . "            if (root.contains(target)) {\n"
    . "                inside = true;\n"
    . "            }\n"
    . "        });\n"
    . "        if (!inside) {\n"
    . "            closeAll(null);\n"
    . "        }\n"
    . "    });\n"
    . "\n"
    . "    var openers = document.querySelectorAll('[data-kg-open-modal]');\n"
    . "    var closeModalById = function(id) {\n"
    . "        if (!id) {\n"
    . "            return;\n"
    . "        }\n"
    . "        var modal = document.getElementById(id);\n"
    . "        if (!modal) {\n"
    . "            return;\n"
    . "        }\n"
    . "        modal.classList.add('kg-hidden');\n"
    . "        document.body.style.overflow = '';\n"
    . "    };\n"
    . "    openers.forEach(function(opener) {\n"
    . "        opener.addEventListener('click', function(event) {\n"
    . "            event.preventDefault();\n"
    . "            var id = opener.getAttribute('data-kg-open-modal');\n"
    . "            if (!id) {\n"
    . "                return;\n"
    . "            }\n"
    . "            var modal = document.getElementById(id);\n"
    . "            if (!modal) {\n"
    . "                return;\n"
    . "            }\n"
    . "            modal.classList.remove('kg-hidden');\n"
    . "            document.body.style.overflow = 'hidden';\n"
    . "        });\n"
    . "    });\n"
    . "    document.querySelectorAll('[data-kg-close-modal]').forEach(function(btn) {\n"
    . "        btn.addEventListener('click', function() {\n"
    . "            closeModalById(btn.getAttribute('data-kg-close-modal'));\n"
    . "        });\n"
    . "    });\n"
    . "    document.querySelectorAll('[data-kg-modal]').forEach(function(modal) {\n"
    . "        modal.addEventListener('click', function(event) {\n"
    . "            if (event.target === modal) {\n"
    . "                closeModalById(modal.id);\n"
    . "            }\n"
    . "        });\n"
    . "    });\n"
    . "})();\n"
);
echo html_writer::script(
    "\n"
    . "(function() {\n"
    . "    document.querySelectorAll('[data-kg-accept-all-decisions]').forEach(function(btn) {\n"
    . "        btn.addEventListener('click', function() {\n"
    . "            var form = btn.closest('form');\n"
    . "            if (!form) {\n"
    . "                return;\n"
    . "            }\n"
    . "            form.querySelectorAll('select.kg-diff-decision').forEach(function(select) {\n"
    . "                select.value = 'accepted';\n"
    . "            });\n"
    . "        });\n"
    . "    });\n"
    . "})();\n"
);

echo $OUTPUT->footer();
