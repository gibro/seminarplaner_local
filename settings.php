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
 * Administration settings.
 *
 * @package    local_seminarplaner
 * @copyright  2026 Guido Brombach <gibro@posteo.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $stringmanager = get_string_manager();
    $reviewrequeststitle = $stringmanager->string_exists('reviewrequestspage', 'local_seminarplaner')
        ? get_string('reviewrequestspage', 'local_seminarplaner')
        : 'Review requests for global method sets';

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_seminarplaner_manage',
        get_string('manageglobalsets', 'local_seminarplaner'),
        new moodle_url('/local/seminarplaner/manage.php')
    ));
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_seminarplaner_reviewrequests',
        $reviewrequeststitle,
        new moodle_url('/local/seminarplaner/reviewrequests.php')
    ));
}
