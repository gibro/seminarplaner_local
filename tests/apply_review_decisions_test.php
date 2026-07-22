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
 * Unit tests for applying reviewer decisions.
 *
 * @package    local_seminarplaner
 * @copyright  2026 Guido Brombach <gibro@posteo.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/seminarplaner/locallib.php');

/**
 * Tests for local_seminarplaner_apply_review_decisions().
 *
 * The submitted version already carries every proposed change. Accepting a row means
 * leaving it alone, rejecting it means restoring the previous version's value - so
 * afterwards the version carries exactly what the reviewer let through.
 */
final class apply_review_decisions_test extends advanced_testcase {
    /** @var int Method set id. */
    private int $setid = 0;

    /** @var int Previous (published) version id. */
    private int $baseversionid = 0;

    /** @var int Version under review. */
    private int $newversionid = 0;

    /** @var int Reviewer user id. */
    private int $reviewerid = 2;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);

        global $DB;
        $now = time();
        $this->setid = (int)$DB->insert_record('local_kgen_methodset', (object)[
            'shortname' => 'APPLYSET',
            'displayname' => 'Apply Set',
            'scopecontextid' => (int)context_system::instance()->id,
            'status' => 'review',
            'concepttype' => 'sammlung',
            'currentversion' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $this->baseversionid = $this->add_version(1, 'published');
        $this->newversionid = $this->add_version(2, 'review');
        $DB->set_field('local_kgen_methodset', 'currentversion', $this->newversionid, ['id' => $this->setid]);
    }

    /**
     * Insert a version.
     *
     * @param int $versionnum Version number.
     * @param string $status Status.
     * @return int Version id.
     */
    private function add_version(int $versionnum, string $status): int {
        global $DB;

        $now = time();

        return (int)$DB->insert_record('local_kgen_methodset_ver', (object)[
            'methodsetid' => $this->setid,
            'versionnum' => $versionnum,
            'status' => $status,
            'snapshotjson' => '{}',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Insert a seminar unit into a version.
     *
     * @param int $versionid Version id.
     * @param string $title Title.
     * @param array $fields Extra column values.
     * @return int Method id.
     */
    private function add_unit(int $versionid, string $title, array $fields = []): int {
        global $DB;

        $now = time();

        return (int)$DB->insert_record('local_kgen_method', (object)array_merge([
            'methodsetid' => $this->setid,
            'methodsetversionid' => $versionid,
            'title' => $title,
            'timecreated' => $now,
            'timemodified' => $now,
        ], $fields));
    }

    /**
     * Attach a material file to a unit.
     *
     * @param int $methodid Method id.
     * @param string $filename File name.
     * @return void
     */
    private function attach(int $methodid, string $filename): void {
        global $DB;

        $itemid = $methodid + 400000;
        get_file_storage()->create_file_from_string((object)[
            'contextid' => (int)context_system::instance()->id,
            'component' => 'local_seminarplaner',
            'filearea' => 'method_material',
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => $filename,
            'userid' => 2,
        ], 'inhalt von ' . $filename);
        $DB->insert_record('local_kgen_method_file', (object)[
            'methodid' => $methodid,
            'kind' => 'material',
            'fileitemid' => $itemid,
            'timecreated' => time(),
        ]);
    }

    /**
     * Record a reviewer decision for one diff row.
     *
     * @param string $title Unit title.
     * @param string $label Field label as shown in the diff.
     * @param string $before Previous value.
     * @param string $after New value.
     * @param string $status added|removed|replaced.
     * @param string $decision accepted|rejected.
     * @return void
     */
    private function decide(string $title, string $label, string $before, string $after,
        string $status, string $decision): void {
        global $DB;

        $DB->insert_record('local_kgen_review_decision', (object)[
            'methodsetid' => $this->setid,
            'methodsetversionid' => $this->newversionid,
            'itemkey' => local_seminarplaner_diff_itemkey($title, $label, $before, $after, $status),
            'reviewerid' => $this->reviewerid,
            'decision' => $decision,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Run the apply step.
     *
     * @return array Counters.
     */
    private function apply(): array {
        return local_seminarplaner_apply_review_decisions($this->setid, $this->newversionid, $this->reviewerid);
    }

    /**
     * Read a unit of the version under review.
     *
     * @param string $title Title.
     * @return stdClass|null
     */
    private function unit(string $title): ?stdClass {
        global $DB;

        foreach ($DB->get_records('local_kgen_method', ['methodsetversionid' => $this->newversionid]) as $row) {
            if ((string)$row->title === $title) {
                return $row;
            }
        }
        return null;
    }

    /**
     * Attachment filenames of a unit in the version under review.
     *
     * @param string $title Title.
     * @return string[]
     */
    private function attachments(string $title): array {
        global $DB;

        $unit = $this->unit($title);
        if (!$unit) {
            return [];
        }
        $fs = get_file_storage();
        $names = [];
        foreach ($DB->get_records('local_kgen_method_file', ['methodid' => (int)$unit->id, 'kind' => 'material']) as $link) {
            foreach ($fs->get_area_files((int)context_system::instance()->id, 'local_seminarplaner',
                'method_material', (int)$link->fileitemid, 'filename ASC', false) as $file) {
                $names[] = (string)$file->get_filename();
            }
        }
        sort($names);
        return $names;
    }

    public function test_rejected_field_falls_back_to_the_previous_value(): void {
        $this->add_unit($this->baseversionid, 'Blitzlicht', ['kurzbeschreibung' => 'Alt']);
        $this->add_unit($this->newversionid, 'Blitzlicht', ['kurzbeschreibung' => 'Neu']);
        $this->decide('Blitzlicht', 'Kurzbeschreibung', 'Alt', 'Neu', 'replaced', 'rejected');

        $result = $this->apply();

        $this->assertSame(1, $result['fields']);
        $this->assertSame('Alt', $this->unit('Blitzlicht')->kurzbeschreibung);
    }

    public function test_accepted_field_stays_as_submitted(): void {
        $this->add_unit($this->baseversionid, 'Blitzlicht', ['kurzbeschreibung' => 'Alt']);
        $this->add_unit($this->newversionid, 'Blitzlicht', ['kurzbeschreibung' => 'Neu']);
        $this->decide('Blitzlicht', 'Kurzbeschreibung', 'Alt', 'Neu', 'replaced', 'accepted');

        $result = $this->apply();

        $this->assertSame(0, $result['fields']);
        $this->assertSame('Neu', $this->unit('Blitzlicht')->kurzbeschreibung);
    }

    public function test_mixed_decisions_on_one_unit_are_applied_per_field(): void {
        $this->add_unit($this->baseversionid, 'Blitzlicht', ['kurzbeschreibung' => 'Alt', 'ablauf' => 'AblaufAlt']);
        $this->add_unit($this->newversionid, 'Blitzlicht', ['kurzbeschreibung' => 'Neu', 'ablauf' => 'AblaufNeu']);
        $this->decide('Blitzlicht', 'Kurzbeschreibung', 'Alt', 'Neu', 'replaced', 'accepted');
        $this->decide('Blitzlicht', 'Ablauf', 'AblaufAlt', 'AblaufNeu', 'replaced', 'rejected');

        $this->apply();

        $unit = $this->unit('Blitzlicht');
        $this->assertSame('Neu', $unit->kurzbeschreibung);
        $this->assertSame('AblaufAlt', $unit->ablauf);
    }

    public function test_pending_rows_are_left_untouched_and_counted(): void {
        $this->add_unit($this->baseversionid, 'Blitzlicht', ['kurzbeschreibung' => 'Alt']);
        $this->add_unit($this->newversionid, 'Blitzlicht', ['kurzbeschreibung' => 'Neu', 'ablauf' => 'NeuerAblauf']);
        // Only one of the two rows gets a decision.
        $this->decide('Blitzlicht', 'Kurzbeschreibung', 'Alt', 'Neu', 'replaced', 'rejected');

        $result = $this->apply();

        $this->assertSame(1, $result['pending']);
        $unit = $this->unit('Blitzlicht');
        $this->assertSame('Alt', $unit->kurzbeschreibung);
        $this->assertSame('NeuerAblauf', $unit->ablauf);
    }

    public function test_fully_rejected_new_unit_is_discarded(): void {
        $this->add_unit($this->newversionid, 'Kartenabfrage', ['kurzbeschreibung' => 'Frisch']);
        $this->decide('Kartenabfrage', 'Titel', '', 'Kartenabfrage', 'added', 'rejected');
        $this->decide('Kartenabfrage', 'Kurzbeschreibung', '', 'Frisch', 'added', 'rejected');

        $result = $this->apply();

        $this->assertSame(1, $result['units_removed']);
        $this->assertNull($this->unit('Kartenabfrage'));
    }

    public function test_accepted_new_unit_survives(): void {
        $this->add_unit($this->newversionid, 'Kartenabfrage', ['kurzbeschreibung' => 'Frisch']);
        $this->decide('Kartenabfrage', 'Titel', '', 'Kartenabfrage', 'added', 'accepted');
        $this->decide('Kartenabfrage', 'Kurzbeschreibung', '', 'Frisch', 'added', 'accepted');

        $result = $this->apply();

        $this->assertSame(0, $result['units_removed']);
        $this->assertNotNull($this->unit('Kartenabfrage'));
    }

    public function test_rejected_removal_restores_the_unit(): void {
        $this->add_unit($this->baseversionid, 'Weggefallen', ['kurzbeschreibung' => 'Bestand']);
        // The submission dropped it - the version under review has no such unit.
        $this->decide('Weggefallen', 'Titel', 'Weggefallen', '', 'removed', 'rejected');
        $this->decide('Weggefallen', 'Kurzbeschreibung', 'Bestand', '', 'removed', 'rejected');

        $result = $this->apply();

        $this->assertSame(1, $result['units_restored']);
        $this->assertNotNull($this->unit('Weggefallen'));
        $this->assertSame('Bestand', $this->unit('Weggefallen')->kurzbeschreibung);
    }

    public function test_rejected_attachment_falls_back_to_the_previous_files(): void {
        $baseid = $this->add_unit($this->baseversionid, 'Blitzlicht');
        $this->attach($baseid, 'Alt.pdf');
        $newid = $this->add_unit($this->newversionid, 'Blitzlicht');
        $this->attach($newid, 'Neu.pdf');
        $this->decide('Blitzlicht', 'Materialien', 'Alt.pdf', 'Neu.pdf', 'replaced', 'rejected');

        $result = $this->apply();

        $this->assertSame(1, $result['materials']);
        $this->assertSame(['Alt.pdf'], $this->attachments('Blitzlicht'));
    }

    public function test_accepted_attachment_stays(): void {
        $baseid = $this->add_unit($this->baseversionid, 'Blitzlicht');
        $this->attach($baseid, 'Alt.pdf');
        $newid = $this->add_unit($this->newversionid, 'Blitzlicht');
        $this->attach($newid, 'Neu.pdf');
        $this->decide('Blitzlicht', 'Materialien', 'Alt.pdf', 'Neu.pdf', 'replaced', 'accepted');

        $result = $this->apply();

        $this->assertSame(0, $result['materials']);
        $this->assertSame(['Neu.pdf'], $this->attachments('Blitzlicht'));
    }

    public function test_rejected_new_attachment_leaves_the_unit_without_files(): void {
        $this->add_unit($this->baseversionid, 'Blitzlicht');
        $newid = $this->add_unit($this->newversionid, 'Blitzlicht');
        $this->attach($newid, 'Neu.pdf');
        $this->decide('Blitzlicht', 'Materialien', '', 'Neu.pdf', 'added', 'rejected');

        $this->apply();

        // The previous version had none, so rejecting means: none.
        $this->assertSame([], $this->attachments('Blitzlicht'));
    }

    public function test_without_any_decision_nothing_changes(): void {
        $this->add_unit($this->baseversionid, 'Blitzlicht', ['kurzbeschreibung' => 'Alt']);
        $this->add_unit($this->newversionid, 'Blitzlicht', ['kurzbeschreibung' => 'Neu']);

        $result = $this->apply();

        $this->assertSame(0, $result['fields']);
        $this->assertSame('Neu', $this->unit('Blitzlicht')->kurzbeschreibung);
    }
}
