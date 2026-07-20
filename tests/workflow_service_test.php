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
 * Unit tests for workflow service.
 *
 * @package    local_seminarplaner
 * @copyright  2026 Guido Brombach <gibro@posteo.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_seminarplaner\local\repository\methodset_repository;
use local_seminarplaner\local\repository\reviewer_repository;
use local_seminarplaner\local\service\workflow_service;

/**
 * DB-backed tests for workflow service.
 */
final class local_seminarplaner_workflow_service_test extends advanced_testcase {
    public function test_transition_draft_to_review_to_published(): void {
        $this->resetAfterTest(true);

        $author = $this->getDataGenerator()->create_user();
        $reviewer = $this->getDataGenerator()->create_user();

        $repo = new methodset_repository();
        $methodsetid = $repo->create_methodset_draft('seta', 'Set A', 'desc', context_system::instance()->id, $author->id);
        $versionid = $repo->create_version($methodsetid, 1, 'draft', '{}', $author->id);

        $reviewerrepo = new reviewer_repository();
        $reviewerrepo->replace_reviewers($methodsetid, [$reviewer->id], $author->id);

        $service = new workflow_service();
        $service->transition($methodsetid, $versionid, 'review', $author->id, 'to review');

        $set = $repo->get_methodset($methodsetid);
        $this->assertSame('review', $set->status);

        $service->transition($methodsetid, $versionid, 'published', $reviewer->id, 'publish');
        $set = $repo->get_methodset($methodsetid);
        $this->assertSame('published', $set->status);
    }

    public function test_invalid_transition_throws(): void {
        $this->resetAfterTest(true);

        $repo = new methodset_repository();
        $methodsetid = $repo->create_methodset_draft('setb', 'Set B', 'desc', context_system::instance()->id, 2);
        $versionid = $repo->create_version($methodsetid, 1, 'draft', '{}', 2);

        $service = new workflow_service();

        $this->expectException(coding_exception::class);
        $service->transition($methodsetid, $versionid, 'published', 1, 'invalid');
    }
}
