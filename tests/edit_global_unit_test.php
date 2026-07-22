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
 * Unit tests for editing seminar units of a global set.
 *
 * @package    local_seminarplaner
 * @copyright  2026 Guido Brombach <gibro@posteo.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/seminarplaner/locallib.php');

/**
 * Tests for local_seminarplaner_update_global_unit().
 *
 * Concept owners maintain their collection directly, without a submission being under
 * review. The edit lands in the current version and is written to the workflow log.
 */
final class edit_global_unit_test extends advanced_testcase {
    /** @var int Method set id. */
    private int $setid = 0;

    /** @var int Version id. */
    private int $versionid = 0;

    /** @var int Method id under test. */
    private int $methodid = 0;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);

        global $DB;
        $now = time();
        $this->setid = (int)$DB->insert_record('local_kgen_methodset', (object)[
            'shortname' => 'EDITSET',
            'displayname' => 'Edit Set',
            'scopecontextid' => (int)context_system::instance()->id,
            'status' => 'published',
            'concepttype' => 'sammlung',
            'currentversion' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $this->versionid = (int)$DB->insert_record('local_kgen_methodset_ver', (object)[
            'methodsetid' => $this->setid,
            'versionnum' => 1,
            'status' => 'published',
            'snapshotjson' => '{}',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $DB->set_field('local_kgen_methodset', 'currentversion', $this->versionid, ['id' => $this->setid]);
        $this->methodid = (int)$DB->insert_record('local_kgen_method', (object)[
            'methodsetid' => $this->setid,
            'methodsetversionid' => $this->versionid,
            'title' => 'Blitzlicht',
            'kurzbeschreibung' => 'Alte Beschreibung',
            'ablauf' => 'Alter Ablauf',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Read the unit under test.
     *
     * @return stdClass
     */
    private function unit(): stdClass {
        global $DB;

        return $DB->get_record('local_kgen_method', ['id' => $this->methodid], '*', MUST_EXIST);
    }

    /**
     * Workflow log entries for the set.
     *
     * @return array
     */
    private function log_entries(): array {
        global $DB;

        return $DB->get_records('local_kgen_workflow_event', ['methodsetid' => $this->setid], 'id ASC');
    }

    public function test_changed_field_is_written_and_reported(): void {
        $changed = local_seminarplaner_update_global_unit($this->methodid, ['kurzbeschreibung' => 'Neue Beschreibung'], 2);

        $this->assertSame(['Kurzbeschreibung'], $changed);
        $this->assertSame('Neue Beschreibung', $this->unit()->kurzbeschreibung);
    }

    public function test_unmentioned_fields_stay_untouched(): void {
        local_seminarplaner_update_global_unit($this->methodid, ['kurzbeschreibung' => 'Neu'], 2);

        // 'ablauf' was not part of the payload and must survive unchanged.
        $this->assertSame('Alter Ablauf', $this->unit()->ablauf);
    }

    public function test_identical_value_counts_as_no_change(): void {
        $changed = local_seminarplaner_update_global_unit($this->methodid, ['kurzbeschreibung' => 'Alte Beschreibung'], 2);

        $this->assertSame([], $changed);
        $this->assertSame([], $this->log_entries());
    }

    public function test_edit_is_written_to_the_workflow_log(): void {
        local_seminarplaner_update_global_unit($this->methodid, ['kurzbeschreibung' => 'Neu', 'ablauf' => 'Anders'], 3);

        $entries = $this->log_entries();
        $this->assertCount(1, $entries);
        $entry = reset($entries);
        $this->assertSame(3, (int)$entry->actorid);
        // The status does not change - the entry documents the edit itself.
        $this->assertSame('published', (string)$entry->fromstatus);
        $this->assertSame('published', (string)$entry->tostatus);
        $this->assertStringContainsString('Blitzlicht', (string)$entry->commenttext);
        $this->assertStringContainsString('Kurzbeschreibung', (string)$entry->commenttext);
    }

    public function test_empty_title_is_rejected(): void {
        $this->expectException(moodle_exception::class);

        local_seminarplaner_update_global_unit($this->methodid, ['title' => '   '], 2);
    }

    public function test_title_can_be_corrected(): void {
        $changed = local_seminarplaner_update_global_unit($this->methodid, ['title' => 'Blitzlicht (kurz)'], 2);

        $this->assertSame(['Titel'], $changed);
        $this->assertSame('Blitzlicht (kurz)', $this->unit()->title);
    }

    public function test_attachments_are_not_touched_by_the_text_editor(): void {
        global $DB;

        $DB->insert_record('local_kgen_method_file', (object)[
            'methodid' => $this->methodid,
            'kind' => 'material',
            'fileitemid' => 991,
            'timecreated' => time(),
        ]);

        local_seminarplaner_update_global_unit($this->methodid, ['materialien' => 'ignoriert', 'ablauf' => 'Neu'], 2);

        // 'materialien' is not a column; the attachment link must remain untouched.
        $this->assertTrue($DB->record_exists('local_kgen_method_file', ['methodid' => $this->methodid]));
    }

    public function test_several_fields_at_once(): void {
        $changed = local_seminarplaner_update_global_unit($this->methodid, [
            'kurzbeschreibung' => 'Neu',
            'ablauf' => 'Anders',
            'zeitbedarf' => '45 min',
        ], 2);

        sort($changed);
        $this->assertSame(['Ablauf', 'Kurzbeschreibung', 'Zeitbedarf'], $changed);
        $unit = $this->unit();
        $this->assertSame('45 min', $unit->zeitbedarf);
    }
}
