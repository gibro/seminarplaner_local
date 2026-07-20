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
 * Workflow rules.
 *
 * @package    local_seminarplaner
 * @copyright  2026 Guido Brombach <gibro@posteo.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_seminarplaner\local\workflow;

defined('MOODLE_INTERNAL') || die();

/**
 * Workflow transition policy for global method sets.
 */
class workflow_rules {
    /** @var array<string, string[]> */
    private const TRANSITIONS = [
        'draft' => ['review'],
        'review' => ['draft', 'published'],
        'published' => ['archived'],
        'archived' => ['draft'],
    ];

    /**
     * Validate transition.
     *
     * @param string $fromstatus Source status.
     * @param string $tostatus Target status.
     * @return bool
     */
    public function can_transition(string $fromstatus, string $tostatus): bool {
        if (!array_key_exists($fromstatus, self::TRANSITIONS)) {
            return false;
        }

        return in_array($tostatus, self::TRANSITIONS[$fromstatus], true);
    }

    /**
     * Validate status token.
     *
     * @param string $status Status name.
     * @return bool
     */
    public function is_valid_status(string $status): bool {
        return array_key_exists($status, self::TRANSITIONS);
    }
}
