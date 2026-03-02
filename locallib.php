<?php
// This file is part of Moodle - http://moodle.org/

/** @var int Max bytes for one import upload payload (CSV or ZIP). */
const LOCAL_KONZEPTGENERATOR_IMPORT_MAX_BYTES = 20971520; // 20 MB.
/** @var int Max ZIP entries to process to reduce ZIP bomb impact. */
const LOCAL_KONZEPTGENERATOR_IMPORT_MAX_ZIP_ENTRIES = 2000;
/** @var int Max parsed CSV rows per upload. */
const LOCAL_KONZEPTGENERATOR_IMPORT_MAX_ROWS = 5000;

/**
 * Split legacy multi-value text into normalized lines.
 *
 * @param string $value Raw field value.
 * @return array
 */
function local_konzeptgenerator_split_multi(string $value): array {
    if ($value === '') {
        return [];
    }
    $parts = preg_split('/##|[\r\n,;]+/u', $value) ?: [];
    $out = [];
    foreach ($parts as $part) {
        $part = trim(strip_tags((string)$part));
        if ($part !== '') {
            $out[] = $part;
        }
    }
    return $out;
}

/**
 * Get first non-empty value from legacy row key candidates.
 *
 * @param array $row Input row.
 * @param array $keys Key candidates.
 * @return string
 */
function local_konzeptgenerator_row_first(array $row, array $keys): string {
    foreach ($keys as $key) {
        if (!array_key_exists($key, $row)) {
            continue;
        }
        $value = trim((string)$row[$key]);
        if ($value !== '') {
            return $value;
        }
    }
    return '';
}

/**
 * Parse H5P placeholder or text field into filenames.
 *
 * @param string $value Field value.
 * @return array
 */
function local_konzeptgenerator_parse_h5p_filenames(string $value): array {
    $value = trim($value);
    if ($value === '') {
        return [];
    }

    $matches = [];
    preg_match_all('/@@PLUGINFILE@@\/([^?"\'&<>\s]+)/u', $value, $matches);
    if (!empty($matches[1])) {
        $out = [];
        foreach ($matches[1] as $match) {
            $decoded = trim(rawurldecode((string)$match));
            if ($decoded !== '') {
                $out[] = $decoded;
            }
        }
        return array_values(array_unique($out));
    }

    return local_konzeptgenerator_split_multi($value);
}

/**
 * Map a mod_data-like row to local method record fields.
 *
 * @param array $row CSV row.
 * @return array|null
 */
function local_konzeptgenerator_map_legacy_row(array $row): ?array {
    $title = local_konzeptgenerator_row_first($row, ['Titel', 'title', 'Name']);
    if ($title === '') {
        return null;
    }

    return [
        'title' => $title,
        'seminarphase' => implode('##', local_konzeptgenerator_split_multi(local_konzeptgenerator_row_first($row, ['Seminarphase', 'seminarphase']))),
        'zeitbedarf' => local_konzeptgenerator_row_first($row, ['Zeitbedarf', 'zeitbedarf']),
        'gruppengroesse' => local_konzeptgenerator_row_first($row, ['Gruppengröße', 'Gruppengroesse', 'gruppengroesse']),
        'kurzbeschreibung' => local_konzeptgenerator_row_first($row, ['Kurzbeschreibung', 'kurzbeschreibung']),
        'ablauf' => local_konzeptgenerator_row_first($row, ['Ablauf', 'ablauf']),
        'lernziele' => local_konzeptgenerator_row_first($row, ['Lernziele (Ich-kann ...)', 'lernziele']),
        'komplexitaetsgrad' => local_konzeptgenerator_row_first($row, ['Komplexitätsgrad', 'Komplexitaetsgrad', 'komplexitaet']),
        'vorbereitung' => local_konzeptgenerator_row_first($row, ['Vorbereitung nötig', 'Vorbereitung noetig', 'vorbereitung']),
        'raumanforderungen' => implode('##', local_konzeptgenerator_split_multi(local_konzeptgenerator_row_first($row, ['Raumanforderungen', 'raumanforderungen']))),
        'sozialform' => implode('##', local_konzeptgenerator_split_multi(local_konzeptgenerator_row_first($row, ['Sozialform', 'sozialform']))),
        'risiken_tipps' => local_konzeptgenerator_row_first($row, ['Risiken/Tipps', 'risiken_tipps', 'risiken']),
        'debrief' => local_konzeptgenerator_row_first($row, ['Debrief/Reflexionsfragen', 'debrief']),
        'material_technik' => local_konzeptgenerator_row_first($row, ['Material/Technik', 'material_technik', 'materialtechnik']),
        'tags' => local_konzeptgenerator_row_first($row, ['Tags / Schlüsselworte', 'Tags / Schluesselworte', 'tags', 'Tags']),
        'kognitive_dimension' => implode('##', local_konzeptgenerator_split_multi(local_konzeptgenerator_row_first($row, ['Kognitive Dimension', 'kognitive_dimension', 'kognitive']))),
        'autor_kontakt' => local_konzeptgenerator_row_first($row, ['Autor*in / Kontakt', 'Autor/in / Kontakt', 'autor_kontakt', 'autor']),
        '__materialfiles' => local_konzeptgenerator_split_multi(local_konzeptgenerator_row_first($row, ['Materialien', 'materialien'])),
        '__h5pfiles' => local_konzeptgenerator_parse_h5p_filenames(local_konzeptgenerator_row_first($row, ['H5P-Inhalt', 'h5p'])),
    ];
}

/**
 * Parse CSV text into associative rows.
 *
 * @param string $csvtext CSV source.
 * @return array
 */
function local_konzeptgenerator_parse_csv(string $csvtext): array {
    $csvtext = preg_replace('/^\xEF\xBB\xBF/', '', $csvtext);
    $lines = preg_split('/\r\n|\n|\r/', $csvtext);
    $firstline = (string)($lines[0] ?? '');
    $delimiters = [',', ';', "\t"];
    $delimiter = ',';
    $best = -1;
    foreach ($delimiters as $cand) {
        $count = substr_count($firstline, $cand);
        if ($count > $best) {
            $best = $count;
            $delimiter = $cand;
        }
    }

    $fp = fopen('php://temp', 'r+');
    fwrite($fp, $csvtext);
    rewind($fp);

    $headers = fgetcsv($fp, 0, $delimiter);
    if (!$headers || !is_array($headers)) {
        fclose($fp);
        return [];
    }
    $headers = array_map(static function($h) {
        return trim((string)$h);
    }, $headers);

    $rows = [];
    while (($values = fgetcsv($fp, 0, $delimiter)) !== false) {
        if (!is_array($values)) {
            continue;
        }
        $row = [];
        foreach ($headers as $idx => $header) {
            $row[$header] = isset($values[$idx]) ? (string)$values[$idx] : '';
        }
        if (trim(implode('', $row)) !== '') {
            $rows[] = $row;
            if (count($rows) > LOCAL_KONZEPTGENERATOR_IMPORT_MAX_ROWS) {
                fclose($fp);
                throw new moodle_exception('invalidparameter');
            }
        }
    }
    fclose($fp);
    return $rows;
}

/**
 * Extract rows from uploaded CSV/ZIP file.
 *
 * @param string $filepath Temporary uploaded file path.
 * @param string $filename Uploaded filename.
 * @return array
 */
function local_konzeptgenerator_extract_rows_from_upload(string $filepath, string $filename): array {
    if (!is_readable($filepath)) {
        throw new moodle_exception('importerrornofile', 'local_konzeptgenerator');
    }
    $filesize = @filesize($filepath);
    if ($filesize !== false && (int)$filesize > LOCAL_KONZEPTGENERATOR_IMPORT_MAX_BYTES) {
        throw new moodle_exception('invalidparameter');
    }

    $name = core_text::strtolower($filename);
    if (substr($name, -4) === '.csv') {
        $csvcontent = (string)file_get_contents($filepath);
        return [
            'rows' => local_konzeptgenerator_parse_csv($csvcontent),
            'files' => [],
        ];
    }
    if (substr($name, -4) !== '.zip') {
        throw new moodle_exception('importerrorfiletype', 'local_konzeptgenerator');
    }
    if (!class_exists('ZipArchive')) {
        throw new moodle_exception('importerrorzipsupport', 'local_konzeptgenerator');
    }

    $zip = new ZipArchive();
    if ($zip->open($filepath) !== true) {
        throw new moodle_exception('importerrorzipopen', 'local_konzeptgenerator');
    }

    $csvindex = -1;
    if ((int)$zip->numFiles > LOCAL_KONZEPTGENERATOR_IMPORT_MAX_ZIP_ENTRIES) {
        $zip->close();
        throw new moodle_exception('invalidparameter');
    }

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entryname = core_text::strtolower((string)$zip->getNameIndex($i));
        if (substr($entryname, -4) === '.csv' && strpos($entryname, 'records') !== false) {
            $csvindex = $i;
            break;
        }
    }
    if ($csvindex === -1) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryname = core_text::strtolower((string)$zip->getNameIndex($i));
            if (substr($entryname, -4) === '.csv') {
                $csvindex = $i;
                break;
            }
        }
    }
    if ($csvindex === -1) {
        $zip->close();
        throw new moodle_exception('importerrorcsvmissing', 'local_konzeptgenerator');
    }

    $csvcontent = (string)$zip->getFromIndex($csvindex);
    $files = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entryname = (string)$zip->getNameIndex($i);
        $entrynamelower = core_text::strtolower($entryname);
        if (substr($entrynamelower, -1) === '/') {
            continue;
        }
        if (strpos($entrynamelower, 'files/') !== 0) {
            continue;
        }
        $basename = trim((string)basename($entryname));
        $basename = clean_param($basename, PARAM_FILE);
        if ($basename === '' || $basename === '.') {
            continue;
        }
        $content = $zip->getFromIndex($i);
        if ($content === false) {
            continue;
        }
        if (strlen((string)$content) > LOCAL_KONZEPTGENERATOR_IMPORT_MAX_BYTES) {
            continue;
        }
        $files[$basename] = (string)$content;
        $files[rawurldecode($basename)] = (string)$content;
    }
    $zip->close();
    return [
        'rows' => local_konzeptgenerator_parse_csv($csvcontent),
        'files' => $files,
    ];
}

/**
 * Resolve first uploaded draft file for a filemanager field.
 *
 * @param int $draftitemid Draft area item id.
 * @return stored_file|null
 */
function local_konzeptgenerator_get_draft_upload(int $draftitemid): ?stored_file {
    global $USER;

    if ($draftitemid <= 0) {
        return null;
    }
    $userctx = context_user::instance((int)$USER->id);
    $fs = get_file_storage();
    $files = $fs->get_area_files($userctx->id, 'user', 'draft', $draftitemid, 'id ASC', false);
    foreach ($files as $file) {
        if (!$file->is_directory()) {
            return $file;
        }
    }
    return null;
}

/**
 * Get next file item id for local import file area.
 *
 * @param string $filearea File area.
 * @return int
 */
function local_konzeptgenerator_next_file_itemid(string $filearea): int {
    global $DB;

    static $cache = [];
    if (!array_key_exists($filearea, $cache)) {
        $max = (int)$DB->get_field_sql(
            "SELECT MAX(itemid)
               FROM {files}
              WHERE component = :component
                AND filearea = :filearea",
            ['component' => 'local_konzeptgenerator', 'filearea' => $filearea]
        );
        $cache[$filearea] = $max;
    }
    $cache[$filearea]++;
    return (int)$cache[$filearea];
}

/**
 * Store imported attachments from ZIP in file API and link table.
 *
 * @param int $methodid Method id.
 * @param string $kind material|h5p.
 * @param int $userid User id.
 * @param array $filenames Requested filenames from CSV.
 * @param array $zipfiles ZIP basename=>content map.
 * @return int Number of files stored.
 */
function local_konzeptgenerator_store_import_files(int $methodid, string $kind, int $userid, array $filenames, array $zipfiles): int {
    global $DB;

    $filenames = array_values(array_unique(array_filter(array_map('trim', $filenames))));
    if (!$filenames) {
        return 0;
    }
    $filearea = $kind === 'h5p' ? 'method_h5p' : 'method_material';
    $contextid = context_system::instance()->id;
    $fs = get_file_storage();
    $itemid = local_konzeptgenerator_next_file_itemid($filearea);
    $storedcount = 0;

    foreach ($filenames as $filename) {
        $filename = trim((string)$filename);
        $filename = clean_param($filename, PARAM_FILE);
        if ($filename === '') {
            continue;
        }
        $lookup = $filename;
        if (!array_key_exists($lookup, $zipfiles)) {
            $lookup = rawurldecode($filename);
        }
        if (!array_key_exists($lookup, $zipfiles)) {
            continue;
        }
        $content = (string)$zipfiles[$lookup];
        $filerecord = (object)[
            'contextid' => $contextid,
            'component' => 'local_konzeptgenerator',
            'filearea' => $filearea,
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => $filename,
            'userid' => $userid,
        ];
        $fs->create_file_from_string($filerecord, $content);
        $storedcount++;
    }

    if ($storedcount > 0) {
        $DB->insert_record('local_kgen_method_file', (object)[
            'methodid' => $methodid,
            'kind' => $kind === 'h5p' ? 'h5p' : 'material',
            'fileitemid' => $itemid,
            'timecreated' => time(),
        ]);
    }

    return $storedcount;
}

/**
 * Import mapped method records into a method set/version.
 *
 * @param int $methodsetid Method set id.
 * @param int $versionid Method set version id.
 * @param int $userid Importing user id.
 * @param array $records Mapped records.
 * @return int
 */
function local_konzeptgenerator_import_records_to_set(
    int $methodsetid,
    int $versionid,
    int $userid,
    array $records,
    array $zipfiles = []
): int {
    global $DB;

    $transaction = $DB->start_delegated_transaction();
    $now = time();
    $count = 0;
    foreach ($records as $rec) {
        $materialfiles = [];
        if (!empty($rec['__materialfiles']) && is_array($rec['__materialfiles'])) {
            $materialfiles = $rec['__materialfiles'];
        }
        unset($rec['__materialfiles'], $rec['__h5pfiles']);

        $record = (object)array_merge($rec, [
            'methodsetid' => $methodsetid,
            'methodsetversionid' => $versionid,
            'externalref' => null,
            'metadatakeyvaluesjson' => null,
            'h5pcontentid' => null,
            'timecreated' => $now,
            'timemodified' => $now,
            'createdby' => $userid,
            'modifiedby' => $userid,
        ]);
        $methodid = (int)$DB->insert_record('local_kgen_method', $record);
        if (!empty($zipfiles)) {
            local_konzeptgenerator_store_import_files($methodid, 'material', $userid, $materialfiles, $zipfiles);
        }
        $count++;
    }
    $transaction->allow_commit();
    return $count;
}

/**
 * Build one CSV cell for mod_data-compatible export.
 *
 * @param string $value Cell value.
 * @return string
 */
function local_konzeptgenerator_csv_cell(string $value): string {
    if (preg_match('/[",\r\n]/', $value)) {
        return '"' . str_replace('"', '""', $value) . '"';
    }
    return $value;
}

/**
 * Build CSV row payload for one method record.
 *
 * @param stdClass $row Method record.
 * @param array<int, array{kind:string,filename:string}> $filesbymethod Method file descriptors by method id.
 * @return array<int, string>
 */
function local_konzeptgenerator_export_row_from_method(stdClass $row, array $filesbymethod): array {
    $materialfiles = [];
    foreach (($filesbymethod[(int)$row->id] ?? []) as $file) {
        if ($file['kind'] !== 'h5p') {
            $materialfiles[] = $file['filename'];
        }
    }

    return [
        (string)($row->title ?? ''),
        (string)($row->seminarphase ?? ''),
        (string)($row->zeitbedarf ?? ''),
        (string)($row->gruppengroesse ?? ''),
        (string)($row->kurzbeschreibung ?? ''),
        (string)($row->autor_kontakt ?? ''),
        (string)($row->lernziele ?? ''),
        (string)($row->komplexitaetsgrad ?? ''),
        (string)($row->vorbereitung ?? ''),
        (string)($row->raumanforderungen ?? ''),
        (string)($row->sozialform ?? ''),
        (string)($row->risiken_tipps ?? ''),
        (string)($row->debrief ?? ''),
        implode('##', $materialfiles),
        (string)($row->material_technik ?? ''),
        (string)($row->ablauf ?? ''),
        (string)($row->tags ?? ''),
        (string)($row->kognitive_dimension ?? ''),
        (string)($row->tags ?? ''),
    ];
}

/**
 * Emit mod_data-compatible CSV or ZIP export response for one set version.
 *
 * @param int $methodsetid Method set id.
 * @param int $versionid Method set version id.
 * @param string $displayname Method set display name.
 * @return never
 */
function local_konzeptgenerator_send_moddata_export(int $methodsetid, int $versionid, string $displayname): void {
    global $DB;

    $rows = $DB->get_records('local_kgen_method', [
        'methodsetid' => $methodsetid,
        'methodsetversionid' => $versionid,
    ], 'id ASC');
    if (!$rows) {
        $rows = $DB->get_records('local_kgen_method', ['methodsetid' => $methodsetid], 'id ASC');
    }

    $headers = [
        'Titel', 'Seminarphase', 'Zeitbedarf', 'Gruppengröße', 'Kurzbeschreibung', 'Autor*in / Kontakt',
        'Lernziele (Ich-kann ...)', 'Komplexitätsgrad', 'Vorbereitung nötig', 'Raumanforderungen', 'Sozialform',
        'Risiken/Tipps', 'Debrief/Reflexionsfragen', 'Materialien', 'Material/Technik', 'Ablauf',
        'Tags / Schlüsselworte', 'Kognitive Dimension', 'Tags',
    ];

    $methodids = array_map(static function($row) {
        return (int)$row->id;
    }, array_values($rows));
    $filesbymethod = [];
    $filesforzip = [];
    if ($methodids) {
        list($insql, $params) = $DB->get_in_or_equal($methodids, SQL_PARAMS_NAMED);
        $links = $DB->get_records_select('local_kgen_method_file', "methodid {$insql}", $params, 'id ASC');
        if ($links) {
            $itemids = [];
            foreach ($links as $link) {
                $itemids[] = (int)$link->fileitemid;
            }
            $itemids = array_values(array_unique(array_filter($itemids)));
            $storedfiles = [];
            if ($itemids) {
                list($iteminsql, $itemparams) = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED);
                $storedfiles = $DB->get_records_select('files',
                    "itemid {$iteminsql}
                         AND component = :component
                         AND filearea = :materialarea
                         AND filename <> :dot
                         AND filesize > 0",
                    $itemparams + [
                        'component' => 'local_konzeptgenerator',
                        'materialarea' => 'method_material',
                        'dot' => '.',
                    ]);
            }
            $storedbyitem = [];
            foreach ($storedfiles as $stored) {
                $storedbyitem[(int)$stored->itemid][] = $stored;
            }
            foreach ($links as $link) {
                $kind = ((string)$link->kind === 'h5p') ? 'h5p' : 'material';
                if ($kind === 'h5p') {
                    continue;
                }
                $itemid = (int)$link->fileitemid;
                if (empty($storedbyitem[$itemid])) {
                    continue;
                }
                foreach ($storedbyitem[$itemid] as $stored) {
                    $filename = (string)$stored->filename;
                    if ($filename === '' || $filename === '.') {
                        continue;
                    }
                    $filesbymethod[(int)$link->methodid][] = [
                        'kind' => $kind,
                        'filename' => $filename,
                    ];
                    $filesforzip[] = (int)$stored->id;
                }
            }
        }
    }

    $lines = [];
    $lines[] = implode(',', array_map('local_konzeptgenerator_csv_cell', $headers));
    foreach ($rows as $row) {
        $csvrow = local_konzeptgenerator_export_row_from_method($row, $filesbymethod);
        $lines[] = implode(',', array_map(static function($value) {
            return local_konzeptgenerator_csv_cell((string)$value);
        }, $csvrow));
    }
    $csvcontent = implode("\n", $lines) . "\n";

    $slug = clean_filename(core_text::strtolower(str_replace(' ', '-', trim($displayname ?: ('set-' . $methodsetid)))));
    $csvname = $slug . '-records.csv';
    $hasfiles = !empty($filesforzip);

    if (!$hasfiles) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $csvname . '"');
        echo $csvcontent;
        exit;
    }

    if (!class_exists('ZipArchive')) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $csvname . '"');
        echo $csvcontent;
        exit;
    }

    $ziptmp = make_request_directory() . '/kgen-export-' . $methodsetid . '-' . time() . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($ziptmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $csvname . '"');
        echo $csvcontent;
        exit;
    }
    $zip->addFromString($csvname, $csvcontent);

    $fs = get_file_storage();
    $usednames = [];
    foreach (array_unique($filesforzip) as $fileid) {
        $stored = $fs->get_file_by_id((int)$fileid);
        if (!$stored || $stored->is_directory()) {
            continue;
        }
        $filename = $stored->get_filename();
        if ($filename === '' || $filename === '.') {
            continue;
        }
        if (!empty($usednames[$filename])) {
            $filename = time() . '-' . $fileid . '-' . $filename;
        }
        $usednames[$filename] = true;
        $zip->addFromString('files/' . $filename, $stored->get_content());
    }
    $zip->close();

    send_temp_file($ziptmp, $slug . '-moddata-export.zip');
}

/**
 * Build comparable method payload from local_kgen_method row.
 *
 * @param stdClass $row Method row.
 * @return array
 */
function local_konzeptgenerator_method_compare_payload(stdClass $row): array {
    return [
        'title' => trim((string)($row->title ?? '')),
        'seminarphase' => trim((string)($row->seminarphase ?? '')),
        'zeitbedarf' => trim((string)($row->zeitbedarf ?? '')),
        'gruppengroesse' => trim((string)($row->gruppengroesse ?? '')),
        'kurzbeschreibung' => trim((string)($row->kurzbeschreibung ?? '')),
        'ablauf' => trim((string)($row->ablauf ?? '')),
        'lernziele' => trim((string)($row->lernziele ?? '')),
        'komplexitaetsgrad' => trim((string)($row->komplexitaetsgrad ?? '')),
        'vorbereitung' => trim((string)($row->vorbereitung ?? '')),
        'raumanforderungen' => trim((string)($row->raumanforderungen ?? '')),
        'sozialform' => trim((string)($row->sozialform ?? '')),
        'risiken_tipps' => trim((string)($row->risiken_tipps ?? '')),
        'debrief' => trim((string)($row->debrief ?? '')),
        'material_technik' => trim((string)($row->material_technik ?? '')),
        'tags' => trim((string)($row->tags ?? '')),
        'kognitive_dimension' => trim((string)($row->kognitive_dimension ?? '')),
        'autor_kontakt' => trim((string)($row->autor_kontakt ?? '')),
    ];
}

/**
 * Build stable key for a single diff row.
 *
 * @param string $title Method title.
 * @param string $label Field label.
 * @param string $before Previous value.
 * @param string $after New value.
 * @param string $status Change status.
 * @return string
 */
function local_konzeptgenerator_diff_itemkey(string $title, string $label, string $before, string $after, string $status): string {
    return sha1($title . "\n" . $label . "\n" . $before . "\n" . $after . "\n" . $status);
}

/**
 * Compute review diff between two method version lists.
 *
 * @param stdClass[] $baserows Previous version methods.
 * @param stdClass[] $newrows Current review version methods.
 * @return array{added: array<int, array<string, mixed>>, removed: array<int, array<string, mixed>>,
 *     changed: array<int, array<string, mixed>>}
 */
function local_konzeptgenerator_compute_review_diff(array $baserows, array $newrows): array {
    $basebytitle = [];
    foreach ($baserows as $row) {
        $title = trim((string)($row->title ?? ''));
        if ($title === '') {
            continue;
        }
        $basebytitle[core_text::strtolower($title)] = local_konzeptgenerator_method_compare_payload($row);
    }

    $newbytitle = [];
    foreach ($newrows as $row) {
        $title = trim((string)($row->title ?? ''));
        if ($title === '') {
            continue;
        }
        $newbytitle[core_text::strtolower($title)] = local_konzeptgenerator_method_compare_payload($row);
    }

    $fieldlabels = [
        'title' => 'Titel',
        'seminarphase' => 'Seminarphase',
        'zeitbedarf' => 'Zeitbedarf',
        'gruppengroesse' => 'Gruppengröße',
        'kurzbeschreibung' => 'Kurzbeschreibung',
        'ablauf' => 'Ablauf',
        'lernziele' => 'Lernziele',
        'komplexitaetsgrad' => 'Komplexitätsgrad',
        'vorbereitung' => 'Vorbereitung',
        'raumanforderungen' => 'Raumanforderungen',
        'sozialform' => 'Sozialform',
        'risiken_tipps' => 'Risiken/Tipps',
        'debrief' => 'Debrief/Reflexionsfragen',
        'material_technik' => 'Material/Technik',
        'tags' => 'Tags',
        'kognitive_dimension' => 'Kognitive Dimension',
        'autor_kontakt' => 'Autor*in / Kontakt',
    ];

    $result = ['added' => [], 'removed' => [], 'changed' => []];

    foreach ($newbytitle as $key => $newpayload) {
        if (!isset($basebytitle[$key])) {
            $rows = [];
            foreach ($fieldlabels as $field => $label) {
                $after = (string)($newpayload[$field] ?? '');
                if ($after === '') {
                    continue;
                }
                $rows[] = [
                    'label' => $label,
                    'before' => '',
                    'after' => $after,
                    'status' => 'added',
                ];
            }
            $result['added'][] = [
                'title' => (string)($newpayload['title'] ?? ''),
                'rows' => $rows,
            ];
            continue;
        }

        $basepayload = $basebytitle[$key];
        $rows = [];
        foreach ($fieldlabels as $field => $label) {
            $before = (string)($basepayload[$field] ?? '');
            $after = (string)($newpayload[$field] ?? '');
            if ($before === $after) {
                continue;
            }
            $status = 'replaced';
            if ($before === '' && $after !== '') {
                $status = 'added';
            } else if ($before !== '' && $after === '') {
                $status = 'removed';
            }
            $rows[] = [
                'label' => $label,
                'before' => $before,
                'after' => $after,
                'status' => $status,
            ];
        }
        if ($rows) {
            $result['changed'][] = [
                'title' => (string)($newpayload['title'] ?? ''),
                'rows' => $rows,
            ];
        }
    }

    foreach ($basebytitle as $key => $basepayload) {
        if (isset($newbytitle[$key])) {
            continue;
        }
        $rows = [];
        foreach ($fieldlabels as $field => $label) {
            $before = (string)($basepayload[$field] ?? '');
            if ($before === '') {
                continue;
            }
            $rows[] = [
                'label' => $label,
                'before' => $before,
                'after' => '',
                'status' => 'removed',
            ];
        }
        $result['removed'][] = [
            'title' => (string)($basepayload['title'] ?? ''),
            'rows' => $rows,
        ];
    }

    usort($result['added'], static function($a, $b) {
        return strcmp((string)$a['title'], (string)$b['title']);
    });
    usort($result['changed'], static function($a, $b) {
        return strcmp((string)$a['title'], (string)$b['title']);
    });
    usort($result['removed'], static function($a, $b) {
        return strcmp((string)$a['title'], (string)$b['title']);
    });

    return $result;
}

/**
 * Render detailed before/after rows for a diff method entry.
 *
 * @param array<string, mixed> $item Diff item.
 * @return string
 */
function local_konzeptgenerator_render_diff_method(array $item, array $decisions = []): string {
    $out = html_writer::start_div('kg-diff-method');
    $out .= html_writer::tag('div', s((string)($item['title'] ?? '')), ['class' => 'kg-diff-method-title']);
    $out .= html_writer::start_tag('table', ['class' => 'kg-diff-table']);
    $out .= html_writer::start_tag('thead');
    $out .= html_writer::tag('tr',
        html_writer::tag('th', 'Feld') .
        html_writer::tag('th', 'Vorher') .
        html_writer::tag('th', 'Nachher') .
        html_writer::tag('th', 'Status') .
        html_writer::tag('th', get_string('reviewacceptcol', 'local_konzeptgenerator'))
    );
    $out .= html_writer::end_tag('thead');
    $out .= html_writer::start_tag('tbody');
    foreach ((array)($item['rows'] ?? []) as $row) {
        $status = (string)($row['status'] ?? 'replaced');
        $label = s((string)($row['label'] ?? ''));
        $before = trim((string)($row['before'] ?? ''));
        $after = trim((string)($row['after'] ?? ''));
        $rawlabel = (string)($row['label'] ?? '');
        $itemkey = local_konzeptgenerator_diff_itemkey((string)($item['title'] ?? ''), $rawlabel, $before, $after, $status);
        $selecteddecision = (string)($decisions[$itemkey] ?? 'pending');
        $beforetext = $before === '' ? '∅' : s($before);
        $aftertext = $after === '' ? '∅' : s($after);
        $decisionselect = html_writer::select([
            'pending' => get_string('reviewdecision_pending', 'local_konzeptgenerator'),
            'accepted' => get_string('reviewdecision_accepted', 'local_konzeptgenerator'),
            'rejected' => get_string('reviewdecision_rejected', 'local_konzeptgenerator'),
        ], 'decisions[' . $itemkey . ']', $selecteddecision, false, ['class' => 'kg-input kg-diff-decision']);
        $out .= html_writer::tag('tr',
            html_writer::tag('td', $label) .
            html_writer::tag('td', html_writer::tag('span', $beforetext, ['class' => 'kg-diff-value kg-diff-before kg-diff-' . $status])) .
            html_writer::tag('td', html_writer::tag('span', $aftertext, ['class' => 'kg-diff-value kg-diff-after kg-diff-' . $status])) .
            html_writer::tag('td', html_writer::tag('span', strtoupper($status), ['class' => 'kg-diff-badge kg-diff-badge-' . $status])) .
            html_writer::tag('td', $decisionselect)
        );
    }
    $out .= html_writer::end_tag('tbody');
    $out .= html_writer::end_tag('table');
    $out .= html_writer::end_div();
    return $out;
}

/**
 * Flatten diff structure to itemkey-indexed map.
 *
 * @param array<string, mixed> $diff Diff payload.
 * @return array<string, array<string, string>>
 */
function local_konzeptgenerator_diff_item_map(array $diff): array {
    $map = [];
    foreach (['added', 'changed', 'removed'] as $bucket) {
        foreach ((array)($diff[$bucket] ?? []) as $item) {
            $title = (string)($item['title'] ?? '');
            foreach ((array)($item['rows'] ?? []) as $row) {
                $label = (string)($row['label'] ?? '');
                $before = trim((string)($row['before'] ?? ''));
                $after = trim((string)($row['after'] ?? ''));
                $status = (string)($row['status'] ?? 'replaced');
                $itemkey = local_konzeptgenerator_diff_itemkey($title, $label, $before, $after, $status);
                $map[$itemkey] = [
                    'title' => $title,
                    'label' => $label,
                    'status' => $status,
                ];
            }
        }
    }
    return $map;
}

