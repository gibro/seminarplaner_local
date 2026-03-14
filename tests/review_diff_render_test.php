<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../locallib.php');

/**
 * Tests for review diff rendering helpers.
 */
final class local_seminarplaner_review_diff_render_test extends advanced_testcase {
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
