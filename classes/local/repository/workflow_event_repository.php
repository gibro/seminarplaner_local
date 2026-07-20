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
 * Workflow event repository.
 *
 * @package    local_seminarplaner
 * @copyright  2026 Guido Brombach <gibro@posteo.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_seminarplaner\local\repository;

defined('MOODLE_INTERNAL') || die();

/**
 * Repository for workflow event log.
 */
class workflow_event_repository {
    /**
     * Create workflow event.
     *
     * @param int $methodsetid Method set id.
     * @param int|null $versionid Version id.
     * @param string $fromstatus Old status.
     * @param string $tostatus New status.
     * @param string $comment Optional comment.
     * @param int $actorid Actor user id.
     * @return int New event id.
     */
    public function create(
        int $methodsetid,
        ?int $versionid,
        string $fromstatus,
        string $tostatus,
        string $comment,
        int $actorid
    ): int {
        global $DB;

        $record = (object)[
            'methodsetid' => $methodsetid,
            'methodsetversionid' => $versionid,
            'fromstatus' => $fromstatus,
            'tostatus' => $tostatus,
            'commenttext' => $comment,
            'actorid' => $actorid,
            'timecreated' => time(),
        ];

        return (int)$DB->insert_record('local_kgen_workflow_event', $record);
    }
}
