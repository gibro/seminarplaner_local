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
 * Database upgrade steps.
 *
 * @package    local_seminarplaner
 * @copyright  2026 Guido Brombach <gibro@posteo.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade script for local_seminarplaner.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_seminarplaner_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026022303) {
        $table = new xmldb_table('local_kgen_set_reviewer');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('methodsetid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('assignedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('set_idx', XMLDB_INDEX_NOTUNIQUE, ['methodsetid']);
        $table->add_index('user_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('set_user_uix', XMLDB_INDEX_UNIQUE, ['methodsetid', 'userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026022303, 'local', 'seminarplaner');
    }

    if ($oldversion < 2026022304) {
        $table = new xmldb_table('local_kgen_review_decision');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('methodsetid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('methodsetversionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('itemkey', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
        $table->add_field('reviewerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('decision', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('setver_idx', XMLDB_INDEX_NOTUNIQUE, ['methodsetversionid']);
        $table->add_index('reviewer_idx', XMLDB_INDEX_NOTUNIQUE, ['reviewerid']);
        $table->add_index('setver_item_reviewer_uix', XMLDB_INDEX_UNIQUE, ['methodsetversionid', 'itemkey', 'reviewerid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026022304, 'local', 'seminarplaner');
    }

    if ($oldversion < 2026071001) {
        // D32: Seminarkonzepte nutzen denselben Review-Mechanismus wie
        // Methoden-Sammlungen; die Objektart wird am Set vermerkt.
        $table = new xmldb_table('local_kgen_methodset');
        $field = new xmldb_field('concepttype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'sammlung', 'status');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026071001, 'local', 'seminarplaner');
    }

    if ($oldversion < 2026071400) {
        // Gruppengröße der globalen Methoden von der alten 7-Werte-Skala auf
        // drei Cluster umrechnen (analog zum mod_seminarplaner-Upgrade).
        // Mapping (Nutzer-Entscheidung): 2–5 → Gruppenarbeit, 6+ → Plenum,
        // Einzelarbeit/unbekannt → beliebig. Inline gehalten.
        // Siehe mod_seminarplaner-Upgrade 2026071400: reine Zahlenwerte (auch
        // Streuwerte) nach Größe einordnen, 1–3-stellig optional mit '+', damit
        // lange Hash-Strings nicht als Zahl durchgehen, sondern auf "beliebig".
        $tocluster = static function (string $old): ?string {
            static $explicit = [
                '1' => 'beliebig',
                '2-3' => 'Gruppenarbeit (2-5)',
                '3–5' => 'Gruppenarbeit (2-5)',
                '6–12' => 'Plenum (10-20)',
                '13–24' => 'Plenum (10-20)',
                '25+' => 'Plenum (10-20)',
            ];
            static $isnew = ['Gruppenarbeit (2-5)' => true, 'Plenum (10-20)' => true, 'beliebig' => true];
            $old = trim($old);
            if ($old === '' || isset($isnew[$old])) {
                return null;
            }
            if (isset($explicit[$old])) {
                return $explicit[$old];
            }
            if (preg_match('/^(\d{1,3})\+?$/', $old, $m)) {
                $n = (int)$m[1];
                return $n <= 1 ? 'beliebig' : ($n <= 5 ? 'Gruppenarbeit (2-5)' : 'Plenum (10-20)');
            }
            return 'beliebig';
        };
        $rs = $DB->get_recordset('local_kgen_method', null, '', 'id, gruppengroesse');
        foreach ($rs as $row) {
            $new = $tocluster((string)$row->gruppengroesse);
            if ($new !== null && $new !== (string)$row->gruppengroesse) {
                $DB->set_field('local_kgen_method', 'gruppengroesse', $new, ['id' => $row->id]);
            }
        }
        $rs->close();

        upgrade_plugin_savepoint(true, 2026071400, 'local', 'seminarplaner');
    }

    if ($oldversion < 2026111002) {
        // Aus "Plenum (10-20)" wird "Plenum (13-20)". Der Konzept-Import liest die
        // Einheiten aus dem Snapshot, deshalb muss die Bezeichnung auch dort wechseln.
        $DB->set_field_select(
            'local_kgen_method',
            'gruppengroesse',
            'Plenum (13-20)',
            $DB->sql_compare_text('gruppengroesse') . ' = ' . $DB->sql_compare_text(':old'),
            ['old' => 'Plenum (10-20)']
        );
        $rs = $DB->get_recordset_select(
            'local_kgen_methodset_ver',
            $DB->sql_like('snapshotjson', ':old'),
            ['old' => '%Plenum (10-20)%'],
            '',
            'id, snapshotjson'
        );
        foreach ($rs as $row) {
            $json = str_replace('Plenum (10-20)', 'Plenum (13-20)', (string)$row->snapshotjson);
            $DB->set_field('local_kgen_methodset_ver', 'snapshotjson', $json, ['id' => $row->id]);
        }
        $rs->close();

        upgrade_plugin_savepoint(true, 2026111002, 'local', 'seminarplaner');
    }

    if ($oldversion < 2026111100) {
        // Gruppengrößen ohne Zahlen: Gruppenarbeit, Plenum, beliebige Gruppengröße
        // (neu dazu: Partnerarbeit). Auch in den Snapshots, aus denen der Konzept-Import liest.
        $renames = \local_seminarplaner\local\service\konzept_snapshot::GROUP_SIZE_RENAMES;
        foreach ($renames as $old => $new) {
            $DB->set_field_select(
                'local_kgen_method',
                'gruppengroesse',
                $new,
                $DB->sql_compare_text('gruppengroesse') . ' = ' . $DB->sql_compare_text(':old'),
                ['old' => $old]
            );
        }
        $rs = $DB->get_recordset_select(
            'local_kgen_methodset_ver',
            $DB->sql_like('snapshotjson', ':key'),
            ['key' => '%gruppengroesse%'],
            '',
            'id, snapshotjson'
        );
        foreach ($rs as $row) {
            $json = \local_seminarplaner\local\service\konzept_snapshot::rename_group_sizes_in_json((string)$row->snapshotjson);
            if ($json !== (string)$row->snapshotjson) {
                $DB->set_field('local_kgen_methodset_ver', 'snapshotjson', $json, ['id' => $row->id]);
            }
        }
        $rs->close();

        upgrade_plugin_savepoint(true, 2026111100, 'local', 'seminarplaner');
    }

    return true;
}
