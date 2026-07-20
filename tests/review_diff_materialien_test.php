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
 * Unit tests for review diff materialien.
 *
 * @package    local_seminarplaner
 * @copyright  2026 Guido Brombach <gibro@posteo.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/seminarplaner/locallib.php');

/**
 * Tests for attachments in the review diff (reviewrequests.php).
 *
 * A submission that only adds a handout used to be reported as "no differences",
 * because the diff read local_kgen_method only and attachments live in their own table.
 */
final class local_seminarplaner_review_diff_materialien_test extends advanced_testcase {
    /** @var int Method set id. */
    private int $methodsetid = 0;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->methodsetid = 4711;
    }

    /**
     * Insert a method row for a version of the set under test.
     *
     * @param int $versionid Version id.
     * @param string $title Title.
     * @param string $kurzbeschreibung Short description.
     * @return stdClass The inserted row.
     */
    private function create_method(int $versionid, string $title, string $kurzbeschreibung = 'Kurz'): stdClass {
        global $DB;

        $now = time();
        $id = (int)$DB->insert_record('local_kgen_method', (object)[
            'methodsetid' => $this->methodsetid,
            'methodsetversionid' => $versionid,
            'title' => $title,
            'kurzbeschreibung' => $kurzbeschreibung,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        return $DB->get_record('local_kgen_method', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Attach a material file to a method.
     *
     * @param stdClass $method Method row.
     * @param string $filename File name.
     * @return void
     */
    private function attach_file(stdClass $method, string $filename): void {
        global $DB;

        $itemid = (int)$method->id + 9000;
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
            'methodid' => (int)$method->id,
            'kind' => 'material',
            'fileitemid' => $itemid,
            'timecreated' => time(),
        ]);
    }

    /**
     * Find the diff row carrying a given label.
     *
     * @param array $item Diff item.
     * @param string $label Field label.
     * @return array|null
     */
    private function row_by_label(array $item, string $label): ?array {
        foreach ((array)($item['rows'] ?? []) as $row) {
            if ((string)$row['label'] === $label) {
                return $row;
            }
        }
        return null;
    }

    public function test_added_attachment_shows_up_as_change(): void {
        $base = $this->create_method(1, 'Hallo');
        $new = $this->create_method(2, 'Hallo');
        $this->attach_file($new, 'ZIM-Papier.pdf');

        $diff = local_seminarplaner_compute_review_diff([$base], [$new]);

        $this->assertCount(1, $diff['changed']);
        $row = $this->row_by_label($diff['changed'][0], 'Materialien');
        $this->assertNotNull($row);
        $this->assertSame('', $row['before']);
        $this->assertSame('ZIM-Papier.pdf', $row['after']);
        $this->assertSame('added', $row['status']);
    }

    public function test_identical_attachments_produce_no_difference(): void {
        $base = $this->create_method(1, 'Hallo');
        $this->attach_file($base, 'ZIM-Papier.pdf');
        $new = $this->create_method(2, 'Hallo');
        $this->attach_file($new, 'ZIM-Papier.pdf');

        $diff = local_seminarplaner_compute_review_diff([$base], [$new]);

        $this->assertSame([], $diff['added']);
        $this->assertSame([], $diff['changed']);
        $this->assertSame([], $diff['removed']);
    }

    public function test_removed_attachment_shows_up_as_change(): void {
        $base = $this->create_method(1, 'Hallo');
        $this->attach_file($base, 'ZIM-Papier.pdf');
        $new = $this->create_method(2, 'Hallo');

        $diff = local_seminarplaner_compute_review_diff([$base], [$new]);

        $this->assertCount(1, $diff['changed']);
        $row = $this->row_by_label($diff['changed'][0], 'Materialien');
        $this->assertNotNull($row);
        $this->assertSame('ZIM-Papier.pdf', $row['before']);
        $this->assertSame('removed', $row['status']);
    }

    public function test_replaced_attachment_lists_both_filenames(): void {
        $base = $this->create_method(1, 'Hallo');
        $this->attach_file($base, 'Handout.pdf');
        $new = $this->create_method(2, 'Hallo');
        $this->attach_file($new, 'Handout.pdf');
        $this->attach_file($new, 'Folie.pdf');

        $diff = local_seminarplaner_compute_review_diff([$base], [$new]);

        $row = $this->row_by_label($diff['changed'][0], 'Materialien');
        $this->assertNotNull($row);
        $this->assertSame('Handout.pdf', $row['before']);
        $this->assertSame('Folie.pdf, Handout.pdf', $row['after']);
        $this->assertSame('replaced', $row['status']);
    }

    public function test_new_unit_lists_its_attachment(): void {
        $new = $this->create_method(2, 'Kartenabfrage');
        $this->attach_file($new, 'Karten.pdf');

        $diff = local_seminarplaner_compute_review_diff([], [$new]);

        $this->assertCount(1, $diff['added']);
        $row = $this->row_by_label($diff['added'][0], 'Materialien');
        $this->assertNotNull($row);
        $this->assertSame('Karten.pdf', $row['after']);
    }

    public function test_text_change_without_attachments_is_unaffected(): void {
        $base = $this->create_method(1, 'Hallo', 'Alt');
        $new = $this->create_method(2, 'Hallo', 'Neu');

        $diff = local_seminarplaner_compute_review_diff([$base], [$new]);

        $this->assertCount(1, $diff['changed']);
        $this->assertNotNull($this->row_by_label($diff['changed'][0], 'Kurzbeschreibung'));
        $this->assertNull($this->row_by_label($diff['changed'][0], 'Materialien'));
    }
}
