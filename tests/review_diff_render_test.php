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
 * Unit tests for review diff render.
 *
 * @package    local_seminarplaner
 * @copyright  2026 Guido Brombach <gibro@posteo.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../locallib.php');

/**
 * Tests for review diff rendering helpers.
 */
final class review_diff_render_test extends advanced_testcase {
    public function test_render_diff_method_is_editable_when_requested(): void {
        $this->resetAfterTest(true);

        $item = [
            'title' => 'Method A',
            'rows' => [[
                'label' => 'Kurzbeschreibung',
                'before' => 'Alt',
                'after' => 'Neu',
                'status' => 'replaced',
            ]],
        ];

        $html = local_seminarplaner_render_diff_method($item, [], true);

        $this->assertStringContainsString('<select', $html);
        $this->assertStringContainsString('decisions[', $html);
    }

    public function test_render_diff_method_can_render_read_only_decision_state(): void {
        $this->resetAfterTest(true);

        $item = [
            'title' => 'Method A',
            'rows' => [[
                'label' => 'Kurzbeschreibung',
                'before' => 'Alt',
                'after' => 'Neu',
                'status' => 'replaced',
            ]],
        ];
        $itemkey = local_seminarplaner_diff_itemkey('Method A', 'Kurzbeschreibung', 'Alt', 'Neu', 'replaced');

        $html = local_seminarplaner_render_diff_method($item, [$itemkey => 'accepted'], false);

        $this->assertStringNotContainsString('<select', $html);
        $this->assertStringContainsString(get_string('reviewdecision_accepted', 'local_seminarplaner'), $html);
    }
}
