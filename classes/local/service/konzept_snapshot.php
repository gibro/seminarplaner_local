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
 * Reads and changes the plans and units a global concept carries in its version snapshot.
 *
 * @package    local_seminarplaner
 * @copyright  2026 Guido Brombach <gibro@posteo.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_seminarplaner\local\service;

use moodle_exception;

/**
 * Snapshot of a global concept: units with their card ids plus any number of plans.
 *
 * A plan keeps its whole state, so its building blocks (`sequenz.bausteine`) travel with it.
 * Snapshots written before plans became a list carry a single `plan`, collections a plain
 * method list; {@see self::decode()} reads all of them.
 */
class konzept_snapshot {
    /** @var string Key of the sequence section inside a plan state. */
    private const KEY_SEQUENZ = 'sequenz';

    /** @var string Key of the course-bound assignment on a placement (D84). */
    private const KEY_REFERENTEN = 'referenten';

    /** @var int Upper limit of plans taken from one upload. */
    private const MAX_UPLOAD_PLANS = 500;

    /**
     * Normalize a unit title or plan name so equally named entries find each other.
     *
     * @param string $title Title.
     * @return string
     */
    public static function normalize_key(string $title): string {
        $title = trim(strip_tags($title));
        if ($title === '') {
            return '';
        }
        $title = (string)preg_replace('/\s+/u', ' ', $title);
        return \core_text::strtolower($title);
    }

    /**
     * URL-safe identifier of a plan within its snapshot.
     *
     * @param string $name Plan name.
     * @return string
     */
    public static function plan_key(string $name): string {
        return md5(self::normalize_key($name));
    }

    /**
     * Decode a stored snapshot of any generation.
     *
     * @param string $json Stored snapshot.
     * @return array{methods: array, plans: array}
     */
    public static function decode(string $json): array {
        $decoded = json_decode($json, true);
        $snapshot = ['methods' => [], 'plans' => []];
        if (!is_array($decoded)) {
            return $snapshot;
        }
        if (isset($decoded[0])) {
            $snapshot['methods'] = array_values(array_filter($decoded, 'is_array'));
            return $snapshot;
        }
        if (is_array($decoded['methods'] ?? null)) {
            $snapshot['methods'] = array_values(array_filter($decoded['methods'], 'is_array'));
        }
        $rawplans = is_array($decoded['plans'] ?? null) ? $decoded['plans'] : [];
        if (!$rawplans && is_array($decoded['plan'] ?? null)) {
            $rawplans = [$decoded['plan']];
        }
        foreach ($rawplans as $plan) {
            if (is_array($plan)) {
                $snapshot['plans'][] = self::normalize_plan($plan);
            }
        }
        return $snapshot;
    }

    /**
     * Encode a snapshot for storage.
     *
     * @param array $snapshot Snapshot as returned by {@see self::decode()}.
     * @return string
     */
    public static function encode(array $snapshot): string {
        $json = json_encode([
            'typ' => 'seminarkonzept',
            'methods' => array_values($snapshot['methods']),
            'plans' => array_values($snapshot['plans']),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new moodle_exception('importerrorfiletype', 'local_seminarplaner');
        }
        return $json;
    }

    /**
     * Read units and plans from an uploaded component export.
     *
     * @param string $json Uploaded JSON.
     * @return array{methods: array, plans: array, skipped: string[]}
     */
    public static function from_upload(string $json): array {
        $decoded = json_decode($json, true);
        $result = ['methods' => [], 'plans' => [], 'skipped' => []];
        if (!is_array($decoded)) {
            return $result;
        }
        $rawmethods = array_key_exists('methods', $decoded) ? $decoded['methods'] : (isset($decoded[0]) ? $decoded : []);
        foreach ((is_array($rawmethods) ? $rawmethods : []) as $method) {
            if (is_array($method)) {
                $result['methods'][] = self::clean_method($method);
            }
        }

        $rawplans = is_array($decoded['seminarplaene'] ?? null) ? $decoded['seminarplaene'] : [];
        if (count($rawplans) > self::MAX_UPLOAD_PLANS) {
            throw new moodle_exception('invalidparameter');
        }
        $usednames = [];
        $position = 0;
        foreach ($rawplans as $rawplan) {
            if (!is_array($rawplan)) {
                continue;
            }
            $position++;
            $plan = self::normalize_plan($rawplan);
            if ($plan['name'] === '') {
                $plan['name'] = 'Seminarplan ' . $position;
            }
            // An export whose plan could not be read carries an empty state. It must not
            // overwrite a real plan of the same name.
            if (!$plan['state']) {
                $result['skipped'][] = $plan['name'];
                continue;
            }
            // Two plans of one file must not collapse into one when they share a name.
            $name = $plan['name'];
            $suffix = 2;
            while (isset($usednames[self::normalize_key($name)])) {
                $name = $plan['name'] . ' (' . $suffix . ')';
                $suffix++;
            }
            $usednames[self::normalize_key($name)] = true;
            $plan['name'] = $name;
            $plan['state'] = self::strip_referenten($plan['state']);
            $result['plans'][] = $plan;
        }
        return $result;
    }

    /**
     * Merge uploaded units and plans into a snapshot.
     *
     * A plan of the same name is replaced as a whole, so its building blocks end up exactly
     * as the upload has them - added, changed or gone. Plans the upload does not mention
     * stay. Units are matched by title; empty uploaded fields leave the stored value alone,
     * as they do for the method rows.
     *
     * @param array $snapshot Snapshot as returned by {@see self::decode()}.
     * @param array $methods Uploaded units.
     * @param array $plans Uploaded plans.
     * @return array{snapshot: array, created: int, updated: int, missingrefs: int, missingplans: string[]}
     */
    public static function merge(array $snapshot, array $methods, array $plans): array {
        $units = array_values($snapshot['methods']);
        $indexbytitle = [];
        $knownids = [];
        foreach ($units as $index => $unit) {
            $key = self::normalize_key(self::unit_title($unit));
            if ($key !== '' && !isset($indexbytitle[$key])) {
                $indexbytitle[$key] = $index;
            }
            $id = trim((string)($unit['id'] ?? ''));
            if ($id !== '') {
                $knownids[$id] = true;
            }
        }

        // The upload may come from another activity: its plans reference its own card ids.
        $idmap = [];
        foreach ($methods as $method) {
            $key = self::normalize_key(self::unit_title($method));
            if ($key === '') {
                continue;
            }
            $uploadid = trim((string)($method['id'] ?? ''));
            if (isset($indexbytitle[$key])) {
                $index = $indexbytitle[$key];
                foreach ($method as $field => $value) {
                    if ($field !== 'id' && $value !== '' && $value !== null && $value !== []) {
                        $units[$index][$field] = $value;
                    }
                }
                $finalid = trim((string)($units[$index]['id'] ?? ''));
                if ($finalid === '') {
                    $finalid = 'global-' . substr(sha1($key), 0, 16);
                    $units[$index]['id'] = $finalid;
                    $knownids[$finalid] = true;
                }
            } else {
                $finalid = ($uploadid !== '' && !isset($knownids[$uploadid]))
                    ? $uploadid
                    : 'global-' . substr(sha1($key), 0, 16);
                $method['id'] = $finalid;
                $units[] = $method;
                $indexbytitle[$key] = count($units) - 1;
                $knownids[$finalid] = true;
            }
            if ($uploadid !== '') {
                $idmap[$uploadid] = $finalid;
            }
        }

        $stored = array_values($snapshot['plans']);
        $indexbyname = [];
        foreach ($stored as $index => $plan) {
            $indexbyname[self::normalize_key((string)$plan['name'])] = $index;
        }
        $result = ['created' => 0, 'updated' => 0, 'missingrefs' => 0, 'missingplans' => []];
        foreach ($plans as $plan) {
            $plan['state'] = self::rewrite_card_refs($plan['state'], $idmap);
            $missing = 0;
            foreach (self::collect_card_refs($plan['state']) as $ref) {
                if (!isset($knownids[$ref])) {
                    $missing++;
                }
            }
            if ($missing > 0) {
                $result['missingrefs'] += $missing;
                $result['missingplans'][] = (string)$plan['name'];
            }
            $key = self::normalize_key((string)$plan['name']);
            if (isset($indexbyname[$key])) {
                $stored[$indexbyname[$key]] = $plan;
                $result['updated']++;
            } else {
                $stored[] = $plan;
                $indexbyname[$key] = count($stored) - 1;
                $result['created']++;
            }
        }

        $result['snapshot'] = ['methods' => $units, 'plans' => $stored];
        return $result;
    }

    /**
     * Point the card references of a plan state at other ids.
     *
     * `legacy:<uid>` references point into the plan's own day entries and stay as they are.
     *
     * @param array $state Plan state.
     * @param array<string, string> $idmap Old id => new id.
     * @return array
     */
    public static function rewrite_card_refs(array $state, array $idmap): array {
        if (!is_array($state[self::KEY_SEQUENZ]['einheitenauswahlen'] ?? null)) {
            return $state;
        }
        foreach ($state[self::KEY_SEQUENZ]['einheitenauswahlen'] as $id => $auswahl) {
            if (!is_array($auswahl)) {
                continue;
            }
            $kandidaten = [];
            foreach ((array)($auswahl['kandidaten'] ?? []) as $ref) {
                $ref = (string)$ref;
                $kandidaten[] = $idmap[$ref] ?? $ref;
            }
            $auswahl['kandidaten'] = $kandidaten;
            if (isset($auswahl['aktiv']) && $auswahl['aktiv'] !== '') {
                $aktiv = (string)$auswahl['aktiv'];
                $auswahl['aktiv'] = $idmap[$aktiv] ?? $aktiv;
            }
            $state[self::KEY_SEQUENZ]['einheitenauswahlen'][$id] = $auswahl;
        }
        return $state;
    }

    /**
     * Remove one plan from a snapshot.
     *
     * The units stay: other plans may use them and they remain part of the concept.
     *
     * @param array $snapshot Snapshot.
     * @param string $plankey Key from {@see self::plan_key()}.
     * @return array{snapshot: array, name: ?string} Name is null if there is no such plan.
     */
    public static function delete_plan(array $snapshot, string $plankey): array {
        $name = null;
        $kept = [];
        foreach ($snapshot['plans'] as $plan) {
            if ($name === null && self::plan_key((string)$plan['name']) === $plankey) {
                $name = (string)$plan['name'];
                continue;
            }
            $kept[] = $plan;
        }
        $snapshot['plans'] = $kept;
        return ['snapshot' => $snapshot, 'name' => $name];
    }

    /**
     * Counts that tell plans apart in a list.
     *
     * @param array $plan Plan.
     * @return array{tage: int, einheiten: int, bausteine: int}
     */
    public static function plan_counts(array $plan): array {
        $sequenz = is_array($plan['state'][self::KEY_SEQUENZ] ?? null) ? $plan['state'][self::KEY_SEQUENZ] : [];
        $einheiten = 0;
        foreach ((is_array($sequenz['platzierungen'] ?? null) ? $sequenz['platzierungen'] : []) as $platzierung) {
            if (is_array($platzierung) && (string)($platzierung['typ'] ?? '') === 'einheit') {
                $einheiten++;
            }
        }
        return [
            'tage' => is_array($sequenz['tage'] ?? null) ? count($sequenz['tage']) : 0,
            'einheiten' => $einheiten,
            'bausteine' => is_array($sequenz['bausteine'] ?? null) ? count($sequenz['bausteine']) : 0,
        ];
    }

    /**
     * Bring a stored or uploaded plan into its canonical shape.
     *
     * @param array $plan Plan.
     * @return array{name: string, description: string, state: array}
     */
    private static function normalize_plan(array $plan): array {
        return [
            'name' => trim(strip_tags((string)($plan['name'] ?? ''))),
            'description' => (string)($plan['description'] ?? ''),
            'state' => is_array($plan['state'] ?? null) ? $plan['state'] : [],
        ];
    }

    /**
     * Title of a unit in either spelling of the export.
     *
     * @param array $unit Unit.
     * @return string
     */
    private static function unit_title(array $unit): string {
        foreach (['titel', 'title', 'Titel'] as $key) {
            if (is_scalar($unit[$key] ?? null) && trim((string)$unit[$key]) !== '') {
                return (string)$unit[$key];
            }
        }
        return '';
    }

    /**
     * Drop what must not end up in a snapshot: file contents and pointers into one activity.
     *
     * @param array $method Uploaded unit.
     * @return array
     */
    private static function clean_method(array $method): array {
        unset($method['_kgsync'], $method['_kgkonzept'], $method['materialiendraftitemid'], $method['h5pdraftitemid']);
        if (is_array($method['materialien'] ?? null)) {
            foreach ($method['materialien'] as $index => $entry) {
                if (is_array($entry)) {
                    unset($method['materialien'][$index]['contentbase64']);
                }
            }
        }
        return $method;
    }

    /**
     * Drop the Referent*innen assignments: they name people of the exporting course (D84).
     *
     * @param array $state Plan state.
     * @return array
     */
    private static function strip_referenten(array $state): array {
        if (!is_array($state[self::KEY_SEQUENZ]['platzierungen'] ?? null)) {
            return $state;
        }
        foreach ($state[self::KEY_SEQUENZ]['platzierungen'] as $id => $platzierung) {
            if (is_array($platzierung)) {
                unset($state[self::KEY_SEQUENZ]['platzierungen'][$id][self::KEY_REFERENTEN]);
            }
        }
        return $state;
    }

    /**
     * Card ids a plan state references.
     *
     * @param array $state Plan state.
     * @return string[]
     */
    private static function collect_card_refs(array $state): array {
        $refs = [];
        $auswahlen = $state[self::KEY_SEQUENZ]['einheitenauswahlen'] ?? [];
        foreach ((is_array($auswahlen) ? $auswahlen : []) as $auswahl) {
            if (!is_array($auswahl)) {
                continue;
            }
            $candidates = (array)($auswahl['kandidaten'] ?? []);
            $candidates[] = $auswahl['aktiv'] ?? '';
            foreach ($candidates as $ref) {
                $ref = trim((string)$ref);
                if ($ref !== '' && strpos($ref, 'legacy:') !== 0) {
                    $refs[$ref] = $ref;
                }
            }
        }
        return array_values($refs);
    }
}
