<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

use local_konzeptgenerator\local\repository\methodset_repository;
use local_konzeptgenerator\local\service\workflow_service;

/**
 * DB-backed tests for workflow service.
 */
final class local_konzeptgenerator_workflow_service_test extends advanced_testcase {
    public function test_transition_draft_to_review_to_published(): void {
        $this->resetAfterTest(true);

        $repo = new methodset_repository();
        $methodsetid = $repo->create_methodset_draft('seta', 'Set A', 'desc', context_system::instance()->id, 2);
        $versionid = $repo->create_version($methodsetid, 1, 'draft', '{}', 2);

        $service = new workflow_service();
        $service->transition($methodsetid, $versionid, 'review', 2, 'to review');

        $set = $repo->get_methodset($methodsetid);
        $this->assertSame('review', $set->status);

        $service->transition($methodsetid, $versionid, 'published', 1, 'publish');
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
