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
 * Provider.
 *
 * @package    local_seminarplaner
 * @copyright  2026 Guido Brombach <gibro@posteo.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_seminarplaner\privacy;

defined('MOODLE_INTERNAL') || die();

use context;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\plugin\provider as request_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider implementation for local_seminarplaner.
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    request_provider,
    core_userlist_provider {

    /**
     * @inheritDoc
     */
    public static function get_metadata(collection $items): collection {
        $items->add_database_table('local_kgen_methodset', [
            'displayname' => 'privacy:metadata:local_kgen_methodset:displayname',
            'description' => 'privacy:metadata:local_kgen_methodset:description',
            'status' => 'privacy:metadata:local_kgen_methodset:status',
            'createdby' => 'privacy:metadata:local_kgen_methodset:createdby',
            'modifiedby' => 'privacy:metadata:local_kgen_methodset:modifiedby',
        ], 'privacy:metadata:local_kgen_methodset');

        $items->add_database_table('local_kgen_methodset_ver', [
            'methodsetid' => 'privacy:metadata:local_kgen_methodset_ver:methodsetid',
            'status' => 'privacy:metadata:local_kgen_methodset_ver:status',
            'snapshotjson' => 'privacy:metadata:local_kgen_methodset_ver:snapshotjson',
            'reviewedby' => 'privacy:metadata:local_kgen_methodset_ver:reviewedby',
            'publishedby' => 'privacy:metadata:local_kgen_methodset_ver:publishedby',
        ], 'privacy:metadata:local_kgen_methodset_ver');

        $items->add_database_table('local_kgen_method', [
            'methodsetid' => 'privacy:metadata:local_kgen_method:methodsetid',
            'title' => 'privacy:metadata:local_kgen_method:title',
            'createdby' => 'privacy:metadata:local_kgen_method:createdby',
            'modifiedby' => 'privacy:metadata:local_kgen_method:modifiedby',
        ], 'privacy:metadata:local_kgen_method');

        $items->add_database_table('local_kgen_workflow_event', [
            'methodsetid' => 'privacy:metadata:local_kgen_workflow_event:methodsetid',
            'fromstatus' => 'privacy:metadata:local_kgen_workflow_event:fromstatus',
            'tostatus' => 'privacy:metadata:local_kgen_workflow_event:tostatus',
            'commenttext' => 'privacy:metadata:local_kgen_workflow_event:commenttext',
            'actorid' => 'privacy:metadata:local_kgen_workflow_event:actorid',
        ], 'privacy:metadata:local_kgen_workflow_event');

        $items->add_database_table('local_kgen_set_reviewer', [
            'methodsetid' => 'privacy:metadata:local_kgen_set_reviewer:methodsetid',
            'userid' => 'privacy:metadata:local_kgen_set_reviewer:userid',
            'assignedby' => 'privacy:metadata:local_kgen_set_reviewer:assignedby',
        ], 'privacy:metadata:local_kgen_set_reviewer');

        $items->add_database_table('local_kgen_review_decision', [
            'methodsetid' => 'privacy:metadata:local_kgen_review_decision:methodsetid',
            'methodsetversionid' => 'privacy:metadata:local_kgen_review_decision:methodsetversionid',
            'itemkey' => 'privacy:metadata:local_kgen_review_decision:itemkey',
            'reviewerid' => 'privacy:metadata:local_kgen_review_decision:reviewerid',
            'decision' => 'privacy:metadata:local_kgen_review_decision:decision',
        ], 'privacy:metadata:local_kgen_review_decision');

        return $items;
    }

    /**
     * @inheritDoc
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();
        $hassystemdata = false;
        $tablesandfields = [
            'local_kgen_methodset' => ['createdby', 'modifiedby'],
            'local_kgen_methodset_ver' => ['reviewedby', 'publishedby'],
            'local_kgen_method' => ['createdby', 'modifiedby'],
            'local_kgen_workflow_event' => ['actorid'],
            'local_kgen_set_reviewer' => ['userid', 'assignedby'],
            'local_kgen_review_decision' => ['reviewerid'],
        ];

        foreach ($tablesandfields as $table => $fields) {
            foreach ($fields as $field) {
                if ($DB->record_exists_select($table, "{$field} = ?", [$userid])) {
                    $hassystemdata = true;
                    break 2;
                }
            }
        }

        if ($hassystemdata) {
            $contextlist->add_context(context_system::instance());
        }
        return $contextlist;
    }

    /**
     * @inheritDoc
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_system) {
            return;
        }

        $userlist->add_from_sql('createdby', 'SELECT createdby FROM {local_kgen_methodset}', []);
        $userlist->add_from_sql('modifiedby', 'SELECT modifiedby FROM {local_kgen_methodset}', []);
        $userlist->add_from_sql('reviewedby', 'SELECT reviewedby FROM {local_kgen_methodset_ver} WHERE reviewedby IS NOT NULL', []);
        $userlist->add_from_sql('publishedby', 'SELECT publishedby FROM {local_kgen_methodset_ver} WHERE publishedby IS NOT NULL', []);
        $userlist->add_from_sql('createdby', 'SELECT createdby FROM {local_kgen_method}', []);
        $userlist->add_from_sql('modifiedby', 'SELECT modifiedby FROM {local_kgen_method}', []);
        $userlist->add_from_sql('actorid', 'SELECT actorid FROM {local_kgen_workflow_event}', []);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_kgen_set_reviewer}', []);
        $userlist->add_from_sql('assignedby', 'SELECT assignedby FROM {local_kgen_set_reviewer}', []);
        $userlist->add_from_sql('reviewerid', 'SELECT reviewerid FROM {local_kgen_review_decision}', []);
    }

    /**
     * @inheritDoc
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_system) {
                continue;
            }
            $data = [
                'methodsets_created' => array_values($DB->get_records('local_kgen_methodset', ['createdby' => $userid])),
                'methodsets_modified' => array_values($DB->get_records('local_kgen_methodset', ['modifiedby' => $userid])),
                'versions_reviewed' => array_values($DB->get_records('local_kgen_methodset_ver', ['reviewedby' => $userid])),
                'versions_published' => array_values($DB->get_records('local_kgen_methodset_ver', ['publishedby' => $userid])),
                'methods_created' => array_values($DB->get_records('local_kgen_method', ['createdby' => $userid])),
                'methods_modified' => array_values($DB->get_records('local_kgen_method', ['modifiedby' => $userid])),
                'workflow_events' => array_values($DB->get_records('local_kgen_workflow_event', ['actorid' => $userid])),
                'reviewer_assignments' => array_values($DB->get_records_select('local_kgen_set_reviewer',
                    'userid = ? OR assignedby = ?', [$userid, $userid])),
                'review_decisions' => array_values($DB->get_records('local_kgen_review_decision', ['reviewerid' => $userid])),
            ];
            writer::with_context($context)->export_data(['global_workflow'], $data);
        }
    }

    /**
     * @inheritDoc
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if (!$context instanceof context_system) {
            return;
        }
        $DB->delete_records('local_kgen_workflow_event');
        $DB->delete_records('local_kgen_review_decision');
        $DB->delete_records('local_kgen_set_reviewer');
        $DB->execute('UPDATE {local_kgen_methodset_ver} SET reviewedby = NULL, publishedby = NULL');
        $DB->execute('UPDATE {local_kgen_method} SET createdby = 0, modifiedby = 0');
        $DB->execute('UPDATE {local_kgen_methodset} SET createdby = 0, modifiedby = 0');
    }

    /**
     * @inheritDoc
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            self::delete_user_from_context($context, [$userid]);
        }
    }

    /**
     * @inheritDoc
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        self::delete_user_from_context($userlist->get_context(), $userlist->get_userids());
    }

    /**
     * Delete/anonymise data for users in the system context.
     *
     * @param context $context Target context.
     * @param array $userids User ids.
     * @return void
     */
    private static function delete_user_from_context(context $context, array $userids): void {
        global $DB;

        if (!$context instanceof context_system || empty($userids)) {
            return;
        }
        $userids = array_values(array_unique(array_map('intval', $userids)));
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_QM);

        $DB->delete_records_select('local_kgen_workflow_event', "actorid {$insql}", $params);
        $DB->delete_records_select('local_kgen_review_decision', "reviewerid {$insql}", $params);
        $DB->delete_records_select('local_kgen_set_reviewer', "userid {$insql} OR assignedby {$insql}", array_merge($params, $params));

        $DB->execute("UPDATE {local_kgen_methodset_ver} SET reviewedby = NULL WHERE reviewedby {$insql}", $params);
        $DB->execute("UPDATE {local_kgen_methodset_ver} SET publishedby = NULL WHERE publishedby {$insql}", $params);
        $DB->execute("UPDATE {local_kgen_methodset} SET createdby = 0 WHERE createdby {$insql}", $params);
        $DB->execute("UPDATE {local_kgen_methodset} SET modifiedby = 0 WHERE modifiedby {$insql}", $params);
        $DB->execute("UPDATE {local_kgen_method} SET createdby = 0 WHERE createdby {$insql}", $params);
        $DB->execute("UPDATE {local_kgen_method} SET modifiedby = 0 WHERE modifiedby {$insql}", $params);
    }
}
