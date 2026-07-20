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
 * Methodset repository.
 *
 * @package    local_seminarplaner
 * @copyright  2026 Guido Brombach <gibro@posteo.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_seminarplaner\local\repository;

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
     * @param string $concepttype Object kind (D32): 'sammlung' or 'seminarkonzept'.
     * @return int
     */
    public function create_methodset_draft(
        string $shortname,
        string $displayname,
        string $description,
        int $scopecontextid,
        int $actorid,
        string $concepttype = 'sammlung'
    ): int {
        global $DB;

        $now = time();
        $record = (object)[
            'shortname' => $shortname,
            'displayname' => $displayname,
            'description' => $description,
            'scopecontextid' => $scopecontextid,
            'status' => 'draft',
            // D32: Seminarkonzepte (kompletter Plan inkl. Sequenz im Snapshot)
            // laufen über denselben Mechanismus wie Methoden-Sammlungen.
            'concepttype' => $concepttype === 'seminarkonzept' ? 'seminarkonzept' : 'sammlung',
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
     * Id of the most recently published version of a set.
     *
     * currentversion always points at the newest version regardless of status, so it is
     * not a reliable "what is published" pointer once a further draft has been created.
     * Everything that feeds published content into activities must use this instead.
     *
     * @param int $methodsetid Method set id.
     * @return int Version id, or 0 if the set has no published version.
     */
    public function get_published_versionid(int $methodsetid): int {
        global $DB;

        $record = $DB->get_record_sql(
            "SELECT id
               FROM {local_kgen_methodset_ver}
              WHERE methodsetid = :methodsetid
                AND status = :status
           ORDER BY versionnum DESC",
            ['methodsetid' => $methodsetid, 'status' => 'published'],
            IGNORE_MULTIPLE
        );

        return $record ? (int)$record->id : 0;
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
     * List all method sets with optional status filter.
     *
     * @param string $status Status filter.
     * @return array
     */
    public function list_all_methodsets(string $status = ''): array {
        global $DB;

        $conditions = [];
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
     * Persist the object kind (D32/D57): 'sammlung' or 'seminarkonzept'.
     *
     * Steuert die Bibliotheks-Behandlung im mod-Plugin (Methoden-Sammlungen sind
     * immer durchsuchbar, Seminarkonzepte nur nach explizitem Import, D55).
     *
     * @param int $methodsetid Method set id.
     * @param string $concepttype 'sammlung' or 'seminarkonzept'.
     * @param int $actorid Actor user id.
     * @return bool
     */
    public function update_methodset_concepttype(int $methodsetid, string $concepttype, int $actorid): bool {
        global $DB;

        $record = (object)[
            'id' => $methodsetid,
            'concepttype' => $concepttype === 'seminarkonzept' ? 'seminarkonzept' : 'sammlung',
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
            [$insql, $params] = $DB->get_in_or_equal($methodids, SQL_PARAMS_NAMED);
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
