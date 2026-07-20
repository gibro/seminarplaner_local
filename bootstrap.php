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
 * Shared bootstrap and access checks.
 *
 * @package    local_seminarplaner
 * @copyright  2026 Guido Brombach <gibro@posteo.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Symlink-safe Moodle bootstrap for the Seminarplaner local plugin.
 *
 * __DIR__ points to the real plugin source when the plugin is installed via a
 * symlink, so the document root is tried first; walking up from __DIR__ is
 * only the fallback for regular (non-symlinked) installs, e.g. Moodle 5.x
 * with the plugin at <wwwroot>/public/local/seminarplaner.
 */
if (!isset($CFG)) {
    global $CFG;

    $candidates = [];
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $candidates[] = rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR) . '/config.php';
    }
    // Regular install: plugin lives at <wwwroot>/local/seminarplaner.
    $candidates[] = dirname(__DIR__, 2) . '/config.php';
    $scriptfile = $_SERVER['SCRIPT_FILENAME'] ?? '';
    if ($scriptfile) {
        $candidates[] = dirname($scriptfile, 3) . '/config.php';
    }

    $configfile = '';
    foreach ($candidates as $candidate) {
        if (is_readable($candidate)) {
            $configfile = $candidate;
            break;
        }
    }
    if ($configfile === '') {
        $configfile = $candidates[0];
    }

    require_once($configfile);
}
