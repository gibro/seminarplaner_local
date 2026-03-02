<?php
// This file is part of Moodle - http://moodle.org/

namespace local_konzeptgenerator\local\repository;

defined('MOODLE_INTERNAL') || die();

/**
 * Repository for workflow event log.
 */
class workflow_event_repository {
    /**
     * Create workflow event.
     *
     * @param int $methodsetid Method set id.
     * @param int|null $versionid Version id.
     * @param string $fromstatus Old status.
     * @param string $tostatus New status.
     * @param string $comment Optional comment.
     * @param int $actorid Actor user id.
     * @return int New event id.
     */
    public function create(int $methodsetid, ?int $versionid, string $fromstatus, string $tostatus, string $comment,
        int $actorid): int {
        global $DB;

        $record = (object)[
            'methodsetid' => $methodsetid,
            'methodsetversionid' => $versionid,
            'fromstatus' => $fromstatus,
            'tostatus' => $tostatus,
            'commenttext' => $comment,
            'actorid' => $actorid,
            'timecreated' => time(),
        ];

        return (int)$DB->insert_record('local_kgen_workflow_event', $record);
    }
}
