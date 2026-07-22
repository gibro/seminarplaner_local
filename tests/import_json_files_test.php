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
 * Unit tests for attachments embedded in a JSON import.
 *
 * @package    local_seminarplaner
 * @copyright  2026 Guido Brombach <gibro@posteo.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/seminarplaner/locallib.php');

use local_seminarplaner\local\repository\methodset_repository;

/**
 * Tests that a JSON export from the activity keeps its attachments on import.
 *
 * The activity export embeds attachments as base64. The importer only read the
 * filenames and hard-coded an empty file list for JSON, so every attachment was
 * dropped without a word - the unit arrived, the handout did not.
 */
final class import_json_files_test extends advanced_testcase {
    /** @var int Method set id. */
    private int $methodsetid = 0;

    /** @var int Version id. */
    private int $versionid = 0;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);

        $repo = new methodset_repository();
        $this->methodsetid = $repo->create_methodset_draft('jsonset', 'JSON Set', 'desc',
            (int)context_system::instance()->id, 2);
        $this->versionid = $repo->create_version($this->methodsetid, 1, 'draft', '{}', 2);
    }

    /**
     * Build a JSON payload shaped like the activity's export.
     *
     * @param string $title Unit title.
     * @param array $materialien Attachment entries.
     * @return string JSON text.
     */
    private function export_json(string $title, array $materialien): string {
        return json_encode([
            'format' => 'seminarplaner-component-export',
            'version' => 3,
            'methods' => [[
                'titel' => $title,
                'kurzbeschreibung' => 'Kurz zu ' . $title,
                'materialien' => $materialien,
            ]],
        ]);
    }

    /**
     * An attachment entry as the export writes it.
     *
     * @param string $filename File name.
     * @param string $content Raw content.
     * @return array
     */
    private function embedded_file(string $filename, string $content): array {
        return [
            'name' => $filename,
            'mimetype' => 'application/pdf',
            'contentbase64' => base64_encode($content),
        ];
    }

    /**
     * Run an upload payload through the importer.
     *
     * @param string $jsontext JSON source.
     * @param string $mode insert|upsert.
     * @return array Import counters.
     */
    private function import_json(string $jsontext, string $mode): array {
        $path = make_request_directory() . '/export.json';
        file_put_contents($path, $jsontext);
        $payload = local_seminarplaner_extract_rows_from_upload($path, 'export.json');

        $records = [];
        foreach ((array)($payload['rows'] ?? []) as $row) {
            $mapped = local_seminarplaner_map_legacy_row($row);
            if ($mapped !== null) {
                $records[] = $mapped;
            }
        }

        return local_seminarplaner_import_records_to_set($this->methodsetid, $this->versionid, 2,
            $records, (array)($payload['files'] ?? []), $mode);
    }

    /**
     * Attachment filenames stored for a unit of the set.
     *
     * @param string $title Unit title.
     * @return string[]
     */
    private function stored_filenames(string $title): array {
        global $DB;

        // title is a TEXT column, which Moodle refuses to compare in SQL - filter in PHP.
        $method = null;
        foreach ($DB->get_records('local_kgen_method', ['methodsetid' => $this->methodsetid], 'id ASC') as $row) {
            if ((string)$row->title === $title) {
                $method = $row;
                break;
            }
        }
        if (!$method) {
            return [];
        }

        $fs = get_file_storage();
        $names = [];
        $links = $DB->get_records('local_kgen_method_file', ['methodid' => (int)$method->id, 'kind' => 'material']);
        foreach ($links as $link) {
            foreach ($fs->get_area_files((int)context_system::instance()->id, 'local_seminarplaner',
                'method_material', (int)$link->fileitemid, 'filename ASC', false) as $file) {
                $names[] = (string)$file->get_filename();
            }
        }
        sort($names);
        return $names;
    }

    public function test_embedded_file_is_extracted_from_the_json_payload(): void {
        $json = $this->export_json('Fuenf Briefumschlaege', [$this->embedded_file('Handout.pdf', 'inhalt')]);

        $files = local_seminarplaner_parse_json_embedded_files($json);

        $this->assertArrayHasKey('Handout.pdf', $files);
        $this->assertSame('inhalt', $files['Handout.pdf']);
    }

    public function test_json_import_into_an_existing_unit_adds_the_attachment(): void {
        // The unit exists in the set without any attachment - the reported case.
        $this->import_json($this->export_json('Fuenf Briefumschlaege', []), 'insert');
        $this->assertSame([], $this->stored_filenames('Fuenf Briefumschlaege'));

        $result = $this->import_json(
            $this->export_json('Fuenf Briefumschlaege', [$this->embedded_file('Handout.pdf', 'inhalt')]),
            'upsert'
        );

        $this->assertSame(1, $result['updated']);
        $this->assertSame(1, $result['files']);
        $this->assertSame(['Handout.pdf'], $this->stored_filenames('Fuenf Briefumschlaege'));
    }

    public function test_json_import_of_a_new_unit_brings_its_attachment(): void {
        $result = $this->import_json(
            $this->export_json('Kartenabfrage', [$this->embedded_file('Karten.pdf', 'inhalt')]),
            'upsert'
        );

        $this->assertSame(1, $result['created']);
        $this->assertSame(['Karten.pdf'], $this->stored_filenames('Kartenabfrage'));
    }

    public function test_filename_with_spaces_survives(): void {
        $name = '2025_Moodle_Kurs_einschreiben_V3_muss angepasst werden.docx';
        $result = $this->import_json(
            $this->export_json('Fuenf Briefumschlaege', [$this->embedded_file($name, 'inhalt')]),
            'upsert'
        );

        $this->assertSame(1, $result['files']);
        $this->assertSame([$name], $this->stored_filenames('Fuenf Briefumschlaege'));
    }

    public function test_attachment_named_but_not_delivered_is_reported(): void {
        // A row naming a file the upload does not carry: the attachment silently
        // disappeared before, so the import must now say which one is missing.
        $json = $this->export_json('Fuenf Briefumschlaege', [
            ['name' => 'Fehlt.pdf'],
            $this->embedded_file('Vorhanden.pdf', 'inhalt'),
        ]);

        $result = $this->import_json($json, 'upsert');

        $this->assertSame(1, $result['files']);
        $this->assertSame(['Fehlt.pdf'], $result['missingfiles']);
    }

    public function test_no_missing_report_when_everything_arrived(): void {
        $result = $this->import_json(
            $this->export_json('Fuenf Briefumschlaege', [$this->embedded_file('Da.pdf', 'inhalt')]),
            'upsert'
        );

        $this->assertSame([], $result['missingfiles']);
    }

    public function test_entry_without_content_is_skipped_without_breaking_the_import(): void {
        $json = $this->export_json('Fuenf Briefumschlaege', [
            ['name' => 'NurName.pdf'],
            $this->embedded_file('MitInhalt.pdf', 'inhalt'),
        ]);

        $result = $this->import_json($json, 'upsert');

        // The unit still arrives, and the attachment that does carry content is stored.
        $this->assertSame(1, $result['created']);
        $this->assertSame(['MitInhalt.pdf'], $this->stored_filenames('Fuenf Briefumschlaege'));
    }
}
