<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade script for local_konzeptgenerator.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_konzeptgenerator_upgrade($oldversion) {
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

        upgrade_plugin_savepoint(true, 2026022303, 'local', 'konzeptgenerator');
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

        upgrade_plugin_savepoint(true, 2026022304, 'local', 'konzeptgenerator');
    }

    return true;
}
