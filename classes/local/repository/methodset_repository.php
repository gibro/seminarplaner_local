<?php
// This file is part of Moodle - http://moodle.org/

namespace local_konzeptgenerator\local\repository;

defined('MOODLE_INTERNAL') || die();

/**
 * Repository for global method sets and versions.
 */
class methodset_repository {
    /**
     * Create a draft method set.
     *
     * @param string $shortname Unique shortname.
     * @param string $displayname Display name.
     * @param string $description Description.
     * @param int $scopecontextid Scope context id.
     * @param int $actorid Actor user id.
     * @return int
     */
    public function create_methodset_draft(string $shortname, string $displayname, string $description, int $scopecontextid,
        int $actorid): int {
        global $DB;

        $now = time();
        $record = (object)[
            'shortname' => $shortname,
            'displayname' => $displayname,
            'description' => $description,
            'scopecontextid' => $scopecontextid,
            'status' => 'draft',
            'currentversion' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
            'createdby' => $actorid,
            'modifiedby' => $actorid,
        ];

        return (int)$DB->insert_record('local_kgen_methodset', $record);
    }

    /**
     * Create a method set version.
     *
     * @param int $methodsetid Method set id.
     * @param int $versionnum Version number.
     * @param string $status Status.
     * @param string $snapshotjson Snapshot payload.
     * @param int $actorid Actor user id.
     * @return int
     */
    public function create_version(int $methodsetid, int $versionnum, string $status, string $snapshotjson, int $actorid): int {
        global $DB;

        $now = time();
        $record = (object)[
            'methodsetid' => $methodsetid,
            'versionnum' => $versionnum,
            'status' => $status,
            'changelog' => '',
            'snapshotjson' => $snapshotjson,
            'reviewedby' => null,
            'publishedby' => null,
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        $versionid = (int)$DB->insert_record('local_kgen_methodset_ver', $record);

        $DB->update_record('local_kgen_methodset', (object)[
            'id' => $methodsetid,
            'currentversion' => $versionid,
            'timemodified' => $now,
            'modifiedby' => $actorid,
        ]);

        return $versionid;
    }

    /**
     * Get method set by id.
     *
     * @param int $methodsetid Method set id.
     * @return \stdClass|false
     */
    public function get_methodset(int $methodsetid) {
        global $DB;
        return $DB->get_record('local_kgen_methodset', ['id' => $methodsetid]);
    }

    /**
     * Get version by id.
     *
     * @param int $versionid Version id.
     * @return \stdClass|false
     */
    public function get_version(int $versionid) {
        global $DB;
        return $DB->get_record('local_kgen_methodset_ver', ['id' => $versionid]);
    }

    /**
     * List method sets by scope and optional status.
     *
     * @param int $scopecontextid Scope context id.
     * @param string $status Status filter.
     * @return array
     */
    public function list_methodsets(int $scopecontextid, string $status = ''): array {
        global $DB;

        $conditions = ['scopecontextid' => $scopecontextid];
        if ($status !== '') {
            $conditions['status'] = $status;
        }

        return $DB->get_records('local_kgen_methodset', $conditions, 'timemodified DESC');
    }

    /**
     * Persist method set status.
     *
     * @param int $methodsetid Method set id.
     * @param string $status New status.
     * @param int $actorid Modifier.
     * @return bool
     */
    public function update_methodset_status(int $methodsetid, string $status, int $actorid): bool {
        global $DB;

        $record = (object)[
            'id' => $methodsetid,
            'status' => $status,
            'timemodified' => time(),
            'modifiedby' => $actorid,
        ];

        return $DB->update_record('local_kgen_methodset', $record);
    }

    /**
     * Persist version status and reviewer/publisher marker.
     *
     * @param int $versionid Version id.
     * @param string $status New status.
     * @param int $actorid Actor user id.
     * @param string $role reviewer|publisher.
     * @return bool
     */
    public function update_version_status(int $versionid, string $status, int $actorid, string $role): bool {
        global $DB;

        $record = (object)[
            'id' => $versionid,
            'status' => $status,
            'timemodified' => time(),
        ];

        if ($role === 'reviewer') {
            $record->reviewedby = $actorid;
        }
        if ($role === 'publisher') {
            $record->publishedby = $actorid;
        }

        return $DB->update_record('local_kgen_methodset_ver', $record);
    }

    /**
     * Delete a method set and all related records.
     *
     * @param int $methodsetid Method set id.
     * @return bool
     */
    public function delete_methodset_cascade(int $methodsetid): bool {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        $methodids = $DB->get_fieldset_select('local_kgen_method', 'id', 'methodsetid = :methodsetid', [
            'methodsetid' => $methodsetid,
        ]);
        if (!empty($methodids)) {
            list($insql, $params) = $DB->get_in_or_equal($methodids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('local_kgen_method_file', "methodid {$insql}", $params);
        }

        $DB->delete_records('local_kgen_method', ['methodsetid' => $methodsetid]);
        $DB->delete_records('local_kgen_workflow_event', ['methodsetid' => $methodsetid]);
        $DB->delete_records('local_kgen_set_reviewer', ['methodsetid' => $methodsetid]);
        $DB->delete_records('local_kgen_review_decision', ['methodsetid' => $methodsetid]);
        $DB->delete_records('local_kgen_methodset_ver', ['methodsetid' => $methodsetid]);
        $ok = $DB->delete_records('local_kgen_methodset', ['id' => $methodsetid]);

        $transaction->allow_commit();
        return (bool)$ok;
    }
}
