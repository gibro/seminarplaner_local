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
 * Unit tests for methodset repository.
 *
 * @package    local_seminarplaner
 * @copyright  2026 Guido Brombach <gibro@posteo.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_seminarplaner\local\repository\methodset_repository;

/**
 * Tests for the method set repository.
 */
final class local_seminarplaner_methodset_repository_test extends advanced_testcase {
    public function test_list_all_methodsets_can_filter_by_status(): void {
        $this->resetAfterTest(true);

        $repo = new methodset_repository();
        $scopecontextid = context_system::instance()->id;

        $draftsetid = $repo->create_methodset_draft('draftset', 'Draft Set', 'desc', $scopecontextid, 2);
        $reviewsetid = $repo->create_methodset_draft('reviewset', 'Review Set', 'desc', $scopecontextid, 2);
        $repo->update_methodset_status($reviewsetid, 'review', 2);

        $allsets = $repo->list_all_methodsets();
        $this->assertArrayHasKey($draftsetid, $allsets);
        $this->assertArrayHasKey($reviewsetid, $allsets);

        $reviewsets = $repo->list_all_methodsets('review');
        $this->assertArrayNotHasKey($draftsetid, $reviewsets);
        $this->assertArrayHasKey($reviewsetid, $reviewsets);
    }
}
