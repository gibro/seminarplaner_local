<?php
// This file is part of Moodle - http://moodle.org/

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
