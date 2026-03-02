<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

use local_konzeptgenerator\local\workflow\workflow_rules;

/**
 * Tests for workflow rules.
 */
final class local_konzeptgenerator_workflow_rules_test extends advanced_testcase {
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
