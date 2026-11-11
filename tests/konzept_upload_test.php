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
 * Unit tests for uploading complete concepts with plans and building blocks.
 *
 * @package    local_seminarplaner
 * @copyright  2026 Guido Brombach <gibro@posteo.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/seminarplaner/locallib.php');

use local_seminarplaner\local\repository\methodset_repository;
use local_seminarplaner\local\service\konzept_snapshot;

/**
 * Tests for plans in global concepts: upload, update by name, deletion.
 */
final class konzept_upload_test extends advanced_testcase {
    /** @var methodset_repository Repository. */
    private $repo;

    /** @var int Method set id. */
    private $setid;

    /** @var int Version id. */
    private $versionid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->repo = new methodset_repository();
        $this->setid = $this->repo->create_methodset_draft('konz', 'Konzept', '', (int)context_system::instance()->id, 2);
        $this->versionid = $this->repo->create_version($this->setid, 1, 'draft', '{}', 2);
    }

    /**
     * Build a plan as the component export writes it.
     *
     * @param string $name Plan name.
     * @param array $bausteine Building blocks by id.
     * @param array $refs Card ids of the only unit selection.
     * @return array
     */
    private function plan(string $name, array $bausteine, array $refs = ['karte-1']): array {
        return [
            'name' => $name,
            'description' => 'Beschreibung ' . $name,
            'state' => ['sequenz' => [
                'version' => 1,
                'tage' => [['tag' => 1]],
                'platzierungen' => ['p1' => ['typ' => 'einheit', 'referenten' => [7, 8]]],
                'bausteine' => $bausteine,
                'einheitenauswahlen' => ['ea1' => ['kandidaten' => $refs, 'aktiv' => $refs[0]]],
            ]],
        ];
    }

    /**
     * Run an upload against the test set and return the stored snapshot.
     *
     * @param array $file Export payload.
     * @return array{0: array, 1: array} Snapshot and outcome.
     */
    private function upload(array $file): array {
        $konzept = konzept_snapshot::from_upload((string)json_encode($file));
        $outcome = local_seminarplaner_apply_konzept_upload($this->setid, $this->versionid, $konzept, 2);
        return [konzept_snapshot::decode((string)$this->repo->get_version($this->versionid)->snapshotjson), $outcome];
    }

    /**
     * Plans of a snapshot by name.
     *
     * @param array $snapshot Snapshot.
     * @return array
     */
    private function by_name(array $snapshot): array {
        return array_column($snapshot['plans'], null, 'name');
    }

    public function test_upload_takes_plans_and_their_building_blocks(): void {
        [$snapshot, $outcome] = $this->upload([
            'methods' => [['id' => 'karte-1', 'titel' => 'Blitzlicht', 'materialien' => [
                ['name' => 'a.pdf', 'contentbase64' => 'QUJD'],
            ]]],
            'seminarplaene' => [
                $this->plan('Plan A', ['b1' => ['titel' => 'Baustein 1']]),
                $this->plan('Plan B', []),
            ],
        ]);

        $this->assertSame(2, $outcome['created']);
        $this->assertSame(0, $outcome['missingrefs']);
        $plans = $this->by_name($snapshot);
        $this->assertCount(2, $plans);
        $this->assertSame('Baustein 1', $plans['Plan A']['state']['sequenz']['bausteine']['b1']['titel']);
        $this->assertSame('seminarkonzept', (string)$this->repo->get_methodset($this->setid)->concepttype);

        // Personen des exportierenden Kurses und Dateiinhalte gehoeren nicht in den Snapshot.
        $this->assertArrayNotHasKey('referenten', $plans['Plan A']['state']['sequenz']['platzierungen']['p1']);
        $this->assertArrayNotHasKey('contentbase64', $snapshot['methods'][0]['materialien'][0]);
    }

    public function test_upload_of_same_plan_name_replaces_it_and_keeps_the_others(): void {
        $this->upload([
            'methods' => [['id' => 'karte-1', 'titel' => 'Blitzlicht']],
            'seminarplaene' => [
                $this->plan('Plan A', ['alt' => ['titel' => 'Entfaellt'], 'b1' => ['titel' => 'Vorher']]),
                $this->plan('Plan B', ['b9' => ['titel' => 'Bleibt']]),
            ],
        ]);

        [$snapshot, $outcome] = $this->upload([
            'seminarplaene' => [
                $this->plan('  plan   a ', ['b1' => ['titel' => 'Nachher'], 'neu' => ['titel' => 'Dazu']]),
                $this->plan('Plan C', []),
            ],
        ]);

        $this->assertSame(1, $outcome['updated']);
        $this->assertSame(1, $outcome['created']);
        $plans = array_column($snapshot['plans'], null, 'description');
        $this->assertCount(3, $snapshot['plans']);
        $bausteine = $plans['Beschreibung   plan   a ']['state']['sequenz']['bausteine'];
        $this->assertSame(['b1', 'neu'], array_keys($bausteine));
        $this->assertSame('Nachher', $bausteine['b1']['titel']);
        $this->assertSame('Bleibt', $plans['Beschreibung Plan B']['state']['sequenz']['bausteine']['b9']['titel']);
    }

    public function test_upload_from_other_activity_points_plan_at_stored_unit(): void {
        $this->upload([
            'methods' => [['id' => 'karte-1', 'titel' => 'Blitzlicht', 'ablauf' => 'Alt', 'kurz' => 'Bleibt']],
            'seminarplaene' => [$this->plan('Plan A', [])],
        ]);

        [$snapshot, $outcome] = $this->upload([
            'methods' => [['id' => 'fremd-77', 'titel' => 'BLITZLICHT', 'ablauf' => 'Neu', 'kurz' => '']],
            'seminarplaene' => [$this->plan('Plan B', [], ['fremd-77'])],
        ]);

        $this->assertSame(0, $outcome['missingrefs']);
        $this->assertCount(1, $snapshot['methods']);
        $this->assertSame('karte-1', $snapshot['methods'][0]['id']);
        $this->assertSame('Neu', $snapshot['methods'][0]['ablauf']);
        $this->assertSame('Bleibt', $snapshot['methods'][0]['kurz']);
        $auswahl = $this->by_name($snapshot)['Plan B']['state']['sequenz']['einheitenauswahlen']['ea1'];
        $this->assertSame(['karte-1'], $auswahl['kandidaten']);
        $this->assertSame('karte-1', $auswahl['aktiv']);
    }

    public function test_plan_referencing_unknown_unit_is_kept_and_reported(): void {
        [$snapshot, $outcome] = $this->upload(['seminarplaene' => [$this->plan('Plan A', [], ['gibtsnicht'])]]);

        $this->assertCount(1, $snapshot['plans']);
        $this->assertSame(1, $outcome['missingrefs']);
        $this->assertSame(['Plan A'], $outcome['missingplans']);
        $this->assertTrue(local_seminarplaner_konzept_upload_notice($outcome)['warn']);
    }

    public function test_plan_without_content_does_not_overwrite_a_stored_one(): void {
        $this->upload([
            'methods' => [['id' => 'karte-1', 'titel' => 'Blitzlicht']],
            'seminarplaene' => [$this->plan('Plan A', ['b1' => ['titel' => 'Bleibt']])],
        ]);

        [$snapshot, $outcome] = $this->upload(['seminarplaene' => [['name' => 'Plan A', 'state' => []]]]);

        $this->assertSame(['Plan A'], $outcome['skipped']);
        $this->assertSame('Bleibt', $this->by_name($snapshot)['Plan A']['state']['sequenz']['bausteine']['b1']['titel']);
    }

    public function test_upload_keeps_plan_of_earlier_single_plan_snapshot(): void {
        $single = $this->plan('Eingereicht', []);
        $this->repo->update_version_snapshot($this->versionid, (string)json_encode([
            'typ' => 'seminarkonzept',
            'methods' => [['id' => 'karte-1', 'titel' => 'Blitzlicht']],
            'plan' => $single,
        ]));

        [$snapshot] = $this->upload(['seminarplaene' => [$this->plan('Dazu', [])]]);

        $this->assertSame(['Eingereicht', 'Dazu'], array_column($snapshot['plans'], 'name'));
    }

    public function test_file_without_plans_leaves_a_collection_untouched(): void {
        [$snapshot, $outcome] = $this->upload(['methods' => [['id' => 'karte-1', 'titel' => 'Blitzlicht']]]);

        $this->assertSame([], $snapshot['methods']);
        $this->assertSame(0, $outcome['created']);
        $this->assertSame('sammlung', (string)$this->repo->get_methodset($this->setid)->concepttype);
    }

    public function test_plan_only_file_has_no_rows_but_a_plan(): void {
        $path = make_request_directory() . '/konzept.json';
        file_put_contents($path, json_encode([
            'format' => 'seminarplaner-component-export',
            'components' => ['methods' => false, 'seminarplaene' => true],
            'seminarplaene' => [$this->plan('Plan A', [])],
        ]));

        $payload = local_seminarplaner_extract_rows_from_upload($path, 'konzept.json');

        $this->assertSame([], $payload['rows']);
        $this->assertCount(1, $payload['konzept']['plans']);
    }

    public function test_delete_plan_removes_only_that_plan_and_logs_it(): void {
        global $DB;

        $this->upload([
            'methods' => [['id' => 'karte-1', 'titel' => 'Blitzlicht']],
            'seminarplaene' => [$this->plan('Plan A', []), $this->plan('Plan B', [])],
        ]);

        $name = local_seminarplaner_delete_global_plan(
            $this->setid,
            $this->versionid,
            konzept_snapshot::plan_key('Plan A'),
            2
        );

        $this->assertSame('Plan A', $name);
        $snapshot = konzept_snapshot::decode((string)$this->repo->get_version($this->versionid)->snapshotjson);
        $this->assertSame(['Plan B'], array_column($snapshot['plans'], 'name'));
        $this->assertCount(1, $snapshot['methods']);
        $this->assertSame(1, $DB->count_records('local_kgen_workflow_event', ['methodsetid' => $this->setid]));
    }

    public function test_resubmitted_plan_keeps_other_plans_and_their_units(): void {
        $snapshot = [
            'methods' => [
                ['id' => 'k1', 'titel' => 'Gemeinsam', 'ablauf' => 'Alt', 'kurz' => 'Wird geleert'],
                ['id' => 'k2', 'titel' => 'Nur alter Plan A'],
                ['id' => 'k3', 'titel' => 'Nur Plan B'],
                ['id' => 'k4', 'titel' => 'Ohne Plan'],
            ],
            'plans' => [
                $this->plan('Plan A', ['alt' => ['titel' => 'Entfaellt']], ['k1', 'k2']),
                $this->plan('Plan B', ['b9' => ['titel' => 'Bleibt']], ['k1', 'k3']),
            ],
        ];

        $outcome = konzept_snapshot::merge_resubmitted_plan(
            $snapshot,
            [['id' => 'kurs-1', 'titel' => 'gemeinsam', 'ablauf' => 'Neu', 'kurz' => '', '_kgsync' => ['x' => 1]]],
            $this->plan('Plan A', ['neu' => ['titel' => 'Dazu']], ['kurs-1'])
        );

        $merged = $outcome['snapshot'];
        $units = array_column($merged['methods'], null, 'id');
        $this->assertSame(['k1', 'k3', 'k4'], array_keys($units));
        $this->assertSame([konzept_snapshot::normalize_key('Nur alter Plan A')], $outcome['droppedtitles']);
        $this->assertSame('Neu', $units['k1']['ablauf']);
        $this->assertArrayNotHasKey('kurz', $units['k1']);
        $this->assertArrayNotHasKey('_kgsync', $units['k1']);

        $plans = $this->by_name($merged);
        $this->assertSame(['Plan A', 'Plan B'], array_keys($plans));
        $this->assertSame(['neu'], array_keys($plans['Plan A']['state']['sequenz']['bausteine']));
        $this->assertSame(['k1'], $plans['Plan A']['state']['sequenz']['einheitenauswahlen']['ea1']['kandidaten']);
        $this->assertSame('Bleibt', $plans['Plan B']['state']['sequenz']['bausteine']['b9']['titel']);
    }

    public function test_copy_to_version_gives_the_unit_its_own_files(): void {
        global $DB;

        $now = time();
        $methodid = (int)$DB->insert_record('local_kgen_method', (object)[
            'methodsetid' => $this->setid,
            'methodsetversionid' => $this->versionid,
            'title' => 'Mit Datei',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $itemid = local_seminarplaner_next_file_itemid('method_material');
        $contextid = context_system::instance()->id;
        $files = get_file_storage();
        $files->create_file_from_string((object)[
            'contextid' => $contextid,
            'component' => 'local_seminarplaner',
            'filearea' => 'method_material',
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => 'a.txt',
        ], 'Inhalt');
        $DB->insert_record('local_kgen_method_file', (object)[
            'methodid' => $methodid,
            'kind' => 'material',
            'fileitemid' => $itemid,
            'timecreated' => $now,
        ]);
        $target = $this->repo->create_version($this->setid, 2, 'draft', '{}', 2);

        $copyid = local_seminarplaner_copy_method_to_version($DB->get_record('local_kgen_method', ['id' => $methodid]), $target, 2);

        $this->assertSame($target, (int)$DB->get_field('local_kgen_method', 'methodsetversionid', ['id' => $copyid]));
        $newitemid = (int)$DB->get_field('local_kgen_method_file', 'fileitemid', ['methodid' => $copyid]);
        $this->assertNotSame($itemid, $newitemid);
        $copied = $files->get_file($contextid, 'local_seminarplaner', 'method_material', $newitemid, '/', 'a.txt');
        $this->assertSame('Inhalt', $copied->get_content());
    }

    public function test_old_group_size_of_an_export_gets_its_current_name(): void {
        $konzept = konzept_snapshot::from_upload((string)json_encode([
            'methods' => [['id' => 'k1', 'titel' => 'A', 'gruppengroesse' => 'Plenum (10-20)']],
        ]));

        $this->assertSame('Plenum', $konzept['methods'][0]['gruppengroesse']);
        $this->assertSame('Gruppenarbeit', konzept_snapshot::rename_group_size('Gruppenarbeit (2-5)'));
        $this->assertSame('beliebige Gruppengröße', konzept_snapshot::rename_group_size('beliebig'));
        $this->assertSame('Partnerarbeit', konzept_snapshot::rename_group_size('Partnerarbeit'));
        $row = local_seminarplaner_map_legacy_row(['Titel' => 'A', 'Gruppengröße' => 'Plenum (6-12)']);
        $this->assertSame('Plenum', $row['gruppengroesse']);
    }

    public function test_group_sizes_in_a_stored_snapshot_get_their_current_name(): void {
        $json = (string)json_encode([
            'methods' => [
                ['titel' => 'beliebig', 'gruppengroesse' => 'beliebig'],
                ['titel' => 'B', 'gruppengroesse' => 'Plenum (13-20)'],
            ],
        ]);
        $renamed = json_decode(konzept_snapshot::rename_group_sizes_in_json($json), true);

        $this->assertSame('beliebige Gruppengröße', $renamed['methods'][0]['gruppengroesse']);
        $this->assertSame('beliebig', $renamed['methods'][0]['titel']);
        $this->assertSame('Plenum', $renamed['methods'][1]['gruppengroesse']);
    }

    public function test_delete_of_unknown_plan_is_refused(): void {
        $this->expectException(moodle_exception::class);
        local_seminarplaner_delete_global_plan($this->setid, $this->versionid, md5('nix'), 2);
    }
}
