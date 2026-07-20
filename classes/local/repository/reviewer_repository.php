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
 * Reviewer repository.
 *
 * @package    local_seminarplaner
 * @copyright  2026 Guido Brombach <gibro@posteo.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_seminarplaner\local\repository;

defined('MOODLE_INTERNAL') || die();

/**
 * Repository for reviewer assignments per method set.
 */
class reviewer_repository {
    /**
     * Replace all reviewers for a method set.
     *
     * @param int $methodsetid Method set id.
     * @param int[] $userids Reviewer user ids.
     * @param int $assignedby Assigning user id.
     * @return int Number of assignments stored.
     */
    public function replace_reviewers(int $methodsetid, array $userids, int $assignedby): int {
        global $DB;

        $methodsetid = (int)$methodsetid;
        $assignedby = (int)$assignedby;
        $cleanids = [];
        foreach ($userids as $userid) {
            $userid = (int)$userid;
            if ($userid > 0) {
                $cleanids[$userid] = $userid;
            }
        }

        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('local_kgen_set_reviewer', ['methodsetid' => $methodsetid]);

        $now = time();
        foreach ($cleanids as $userid) {
            $DB->insert_record('local_kgen_set_reviewer', (object)[
                'methodsetid' => $methodsetid,
                'userid' => $userid,
                'assignedby' => $assignedby,
                'timecreated' => $now,
            ]);
        }

        $transaction->allow_commit();
        return count($cleanids);
    }

    /**
     * Get reviewer user ids for a method set.
     *
     * @param int $methodsetid Method set id.
     * @return int[]
     */
    public function get_reviewer_userids(int $methodsetid): array {
        global $DB;

        $ids = $DB->get_fieldset_select('local_kgen_set_reviewer', 'userid', 'methodsetid = :methodsetid', [
            'methodsetid' => (int)$methodsetid,
        ]);
        return array_values(array_map('intval', $ids));
    }
}
