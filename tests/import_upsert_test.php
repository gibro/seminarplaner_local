<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/seminarplaner/locallib.php');

use local_seminarplaner\local\repository\methodset_repository;

/**
 * Tests for updating an existing method set via import (upsert mode).
 */
final class local_seminarplaner_import_upsert_test extends advanced_testcase {
    /** @var int Method set id. */
    private $methodsetid;

    /** @var int Method set version id. */
    private $versionid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);

        $repo = new methodset_repository();
        $this->methodsetid = $repo->create_methodset_draft('upsertset', 'Upsert Set', 'desc',
            (int)context_system::instance()->id, 2);
        $this->versionid = $repo->create_version($this->methodsetid, 1, 'draft', '{}', 2);
    }

    /**
     * Build a mapped record as local_seminarplaner_map_legacy_row() would return it.
     *
     * @param string $title Method title.
     * @param array $overrides Column values to set.
     * @return array
     */
    private function record(string $title, array $overrides = []): array {
        $row = ['Titel' => $title] + $overrides;
        return local_seminarplaner_map_legacy_row($row);
    }

    /**
     * Run an import against the set under test.
     *
     * @param array $records Mapped records.
     * @param array $zipfiles ZIP basename=>content map.
     * @param string $mode insert|upsert.
     * @return array
     */
    private function import(array $records, array $zipfiles = [], string $mode = 'upsert'): array {
        return local_seminarplaner_import_records_to_set(
            $this->methodsetid, $this->versionid, 2, $records, $zipfiles, $mode);
    }

    /**
     * Fetch the single method of the set under test.
     *
     * @return stdClass
     */
    private function only_method(): stdClass {
        global $DB;
        $rows = $DB->get_records('local_kgen_method', ['methodsetid' => $this->methodsetid], 'id ASC');
        $this->assertCount(1, $rows);
        return reset($rows);
    }

    /**
     * List material filenames attached to a method.
     *
     * @param int $methodid Method id.
     * @return array Filenames, sorted.
     */
    private function material_filenames(int $methodid): array {
        global $DB;
        $fs = get_file_storage();
        $contextid = (int)context_system::instance()->id;
        $names = [];
        $links = $DB->get_records('local_kgen_method_file', ['methodid' => $methodid, 'kind' => 'material']);
        foreach ($links as $link) {
            $files = $fs->get_area_files($contextid, 'local_seminarplaner', 'method_material',
                (int)$link->fileitemid, 'filename ASC', false);
            foreach ($files as $file) {
                $names[] = (string)$file->get_filename();
            }
        }
        sort($names);
        return $names;
    }

    public function test_matching_title_updates_instead_of_duplicating(): void {
        $this->import([$this->record('Blitzlicht', ['Zeitbedarf' => '10 min'])], [], 'insert');

        $result = $this->import([$this->record('Blitzlicht', ['Zeitbedarf' => '15 min'])]);

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame('15 min', $this->only_method()->zeitbedarf);
    }

    public function test_title_match_ignores_case_and_extra_whitespace(): void {
        $this->import([$this->record('Blitzlicht  am   Ende')], [], 'insert');

        $result = $this->import([$this->record('  blitzlicht am ende ', ['Zeitbedarf' => '5 min'])]);

        $this->assertSame(1, $result['updated']);
        $this->assertSame('5 min', $this->only_method()->zeitbedarf);
    }

    public function test_empty_columns_leave_existing_values_untouched(): void {
        $this->import([
            $this->record('Blitzlicht', ['Zeitbedarf' => '10 min', 'Kurzbeschreibung' => 'Alte Beschreibung']),
        ], [], 'insert');

        $this->import([$this->record('Blitzlicht', ['Zeitbedarf' => '', 'Kurzbeschreibung' => 'Neue Beschreibung'])]);

        $method = $this->only_method();
        $this->assertSame('10 min', $method->zeitbedarf);
        $this->assertSame('Neue Beschreibung', $method->kurzbeschreibung);
    }

    public function test_unmatched_title_is_added_as_new_method(): void {
        $this->import([$this->record('Blitzlicht')], [], 'insert');

        $result = $this->import([$this->record('Blitzlicht'), $this->record('Kartenabfrage')]);

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(2, $this->count_methods());
    }

    public function test_attachment_is_added_to_existing_method_without_files(): void {
        $this->import([$this->record('Blitzlicht')], [], 'insert');
        $methodid = (int)$this->only_method()->id;
        $this->assertSame([], $this->material_filenames($methodid));

        $result = $this->import(
            [$this->record('Blitzlicht', ['Materialien' => 'Handout.pdf'])],
            ['Handout.pdf' => 'inhalt']
        );

        $this->assertSame(1, $result['updated']);
        $this->assertSame(1, $result['files']);
        $this->assertSame($methodid, (int)$this->only_method()->id);
        $this->assertSame(['Handout.pdf'], $this->material_filenames($methodid));
    }

    public function test_existing_attachments_are_kept_and_same_name_is_replaced(): void {
        $this->import(
            [$this->record('Blitzlicht', ['Materialien' => 'Handout.pdf##Folie.pdf'])],
            ['Handout.pdf' => 'alt', 'Folie.pdf' => 'folie'],
            'insert'
        );
        $methodid = (int)$this->only_method()->id;

        $result = $this->import(
            [$this->record('Blitzlicht', ['Materialien' => 'Handout.pdf'])],
            ['Handout.pdf' => 'neu']
        );

        $this->assertSame(1, $result['files']);
        // Folie.pdf was not in the second ZIP and must survive; Handout.pdf is replaced, not duplicated.
        $this->assertSame(['Folie.pdf', 'Handout.pdf'], $this->material_filenames($methodid));
        $this->assertSame('neu', $this->material_content($methodid, 'Handout.pdf'));
    }

    public function test_insert_mode_still_adds_every_record(): void {
        $this->import([$this->record('Blitzlicht')], [], 'insert');
        $result = $this->import([$this->record('Blitzlicht')], [], 'insert');

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(2, $this->count_methods());
    }

    public function test_duplicate_titles_update_the_oldest_method(): void {
        $this->import([$this->record('Blitzlicht')], [], 'insert');
        $this->import([$this->record('Blitzlicht')], [], 'insert');
        global $DB;
        $ids = array_keys($DB->get_records('local_kgen_method', ['methodsetid' => $this->methodsetid], 'id ASC'));
        $oldest = (int)$ids[0];

        $result = $this->import([$this->record('Blitzlicht', ['Zeitbedarf' => '15 min'])]);

        $this->assertSame(1, $result['updated']);
        $this->assertSame(2, $this->count_methods());
        $this->assertSame('15 min', $DB->get_field('local_kgen_method', 'zeitbedarf', ['id' => $oldest]));
        $this->assertSame('', $DB->get_field('local_kgen_method', 'zeitbedarf', ['id' => (int)$ids[1]]));
    }

    /**
     * Count methods in the set under test.
     *
     * @return int
     */
    private function count_methods(): int {
        global $DB;
        return (int)$DB->count_records('local_kgen_method', ['methodsetid' => $this->methodsetid]);
    }

    /**
     * Read the content of one attached material file.
     *
     * @param int $methodid Method id.
     * @param string $filename File name.
     * @return string
     */
    private function material_content(int $methodid, string $filename): string {
        global $DB;
        $fs = get_file_storage();
        $contextid = (int)context_system::instance()->id;
        $links = $DB->get_records('local_kgen_method_file', ['methodid' => $methodid, 'kind' => 'material']);
        foreach ($links as $link) {
            $file = $fs->get_file($contextid, 'local_seminarplaner', 'method_material',
                (int)$link->fileitemid, '/', $filename);
            if ($file) {
                return (string)$file->get_content();
            }
        }
        return '';
    }
}
