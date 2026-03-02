<?php
// This file is part of Moodle - http://moodle.org/

namespace local_konzeptgenerator\local\workflow;

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
