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
 * Library functions.
 *
 * @package    local_seminarplaner
 * @copyright  2026 Guido Brombach <gibro@posteo.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Check whether a user may open the review requests page.
 *
 * Mirrors the access rules of reviewrequests.php: review capability at system level,
 * or review/manage capabilities in the scope context of at least one existing method set.
 *
 * @param int $userid User id.
 * @return bool
 */
function local_seminarplaner_can_access_reviewrequests(int $userid): bool {
    global $DB;

    $syscontext = context_system::instance();
    if (has_capability('local/seminarplaner:reviewset', $syscontext, $userid)) {
        return true;
    }

    $scopeids = $DB->get_fieldset_sql(
        'SELECT DISTINCT scopecontextid FROM {local_kgen_methodset} WHERE scopecontextid > 0'
    );
    if (!$scopeids) {
        return false;
    }

    if (has_capability('local/seminarplaner:publishset', $syscontext, $userid)
            || has_capability('local/seminarplaner:archiveglobalset', $syscontext, $userid)) {
        return true;
    }

    $scopecaps = [
        'local/seminarplaner:reviewset',
        'local/seminarplaner:createdraftset',
        'local/seminarplaner:editdraftset',
        'local/seminarplaner:importglobalset',
        'local/seminarplaner:exportglobalset',
    ];
    foreach ($scopeids as $scopeid) {
        $scopecontext = context::instance_by_id((int)$scopeid, IGNORE_MISSING);
        if (!$scopecontext) {
            continue;
        }
        foreach ($scopecaps as $capability) {
            if (has_capability($capability, $scopecontext, $userid)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Add a "Review requests" link to the reports section of the user's own profile page.
 *
 * @param \core_user\output\myprofile\tree $tree Profile tree.
 * @param stdClass $user Viewed user.
 * @param bool $iscurrentuser Whether the viewer looks at their own profile.
 * @param stdClass|null $course Course context of the profile page, if any.
 * @return bool
 */
function local_seminarplaner_myprofile_navigation(\core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    if (!$iscurrentuser || isguestuser($user)) {
        return true;
    }
    if (!local_seminarplaner_can_access_reviewrequests((int)$user->id)) {
        return true;
    }

    $node = new \core_user\output\myprofile\node(
        'reports',
        'local_seminarplaner_reviewrequests',
        get_string('reviewrequestspage', 'local_seminarplaner'),
        null,
        new moodle_url('/local/seminarplaner/reviewrequests.php')
    );
    $tree->add_node($node);
    return true;
}
