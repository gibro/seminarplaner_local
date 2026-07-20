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
 * Unit tests for workflow rules.
 *
 * @package    local_seminarplaner
 * @copyright  2026 Guido Brombach <gibro@posteo.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_seminarplaner\local\workflow\workflow_rules;

/**
 * Tests for workflow rules.
 */
final class local_seminarplaner_workflow_rules_test extends advanced_testcase {
    public function test_valid_transitions(): void {
        $rules = new workflow_rules();

        $this->assertTrue($rules->can_transition('draft', 'review'));
        $this->assertTrue($rules->can_transition('review', 'published'));
        $this->assertTrue($rules->can_transition('review', 'draft'));
        $this->assertTrue($rules->can_transition('published', 'archived'));
        $this->assertTrue($rules->can_transition('archived', 'draft'));
    }

    public function test_invalid_transitions(): void {
        $rules = new workflow_rules();

        $this->assertFalse($rules->can_transition('draft', 'published'));
        $this->assertFalse($rules->can_transition('published', 'review'));
    }

    public function test_status_validation(): void {
        $rules = new workflow_rules();

        $this->assertTrue($rules->is_valid_status('draft'));
        $this->assertTrue($rules->is_valid_status('review'));
        $this->assertFalse($rules->is_valid_status('unknown'));
    }
}
