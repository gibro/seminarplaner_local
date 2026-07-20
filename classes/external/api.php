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
 * Api.
 *
 * @package    local_seminarplaner
 * @copyright  2026 Guido Brombach <gibro@posteo.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_seminarplaner\external;

use context;
use context_coursecat;
use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;
use local_seminarplaner\local\repository\methodset_repository;
use local_seminarplaner\local\service\workflow_service;

/**
 * External API for global method set governance.
 */
class api extends external_api {
    /** @var int Max allowed description length for create_draft_methodset. */
    private const MAX_DESCRIPTION_CHARS = 12000;
    /** @var int Max allowed transition comment length. */
    private const MAX_COMMENT_CHARS = 4000;
    /**
     * Resolve scope context.
     *
     * @param int $scopecontextid Context id.
     * @return context
     */
    private static function resolve_scope_context(int $scopecontextid): context {
        $ctx = context::instance_by_id($scopecontextid, MUST_EXIST);
        if (!($ctx instanceof context_system) && !($ctx instanceof context_coursecat)) {
            throw new invalid_parameter_exception('scopecontextid must be system or category context');
        }
        self::validate_context($ctx);
        return $ctx;
    }

    /**
     * Lightweight in-session write throttling for expensive governance endpoints.
     *
     * @param string $action Action key.
     * @param int $maxrequests Max requests in window.
     * @param int $windowseconds Time window.
     * @return void
     */
    private static function enforce_write_rate_limit(string $action, int $maxrequests, int $windowseconds): void {
        global $SESSION;

        if ($maxrequests <= 0 || $windowseconds <= 0) {
            return;
        }
        if (!isset($SESSION->local_seminarplaner_ratelimit) || !is_array($SESSION->local_seminarplaner_ratelimit)) {
            $SESSION->local_seminarplaner_ratelimit = [];
        }

        $now = time();
        $windowstart = $now - $windowseconds;
        $entries = $SESSION->local_seminarplaner_ratelimit[$action] ?? [];
        if (!is_array($entries)) {
            $entries = [];
        }

        $entries = array_values(array_filter($entries, static function ($ts) use ($windowstart) {
            return is_int($ts) && $ts >= $windowstart;
        }));
        if (count($entries) >= $maxrequests) {
            throw new invalid_parameter_exception(
                'Zu viele Schreibanfragen in kurzer Zeit. Bitte kurz warten und erneut versuchen.'
            );
        }

        $entries[] = $now;
        $SESSION->local_seminarplaner_ratelimit[$action] = $entries;
    }

    /**
     * Beschreibt die Parameter für das Anlegen eines Methodenset-Entwurfs.
     *
     * @return external_function_parameters Parameterdefinition des Webservice.
     */
    public static function create_draft_methodset_parameters(): external_function_parameters {
        return new external_function_parameters([
            'shortname' => new external_value(PARAM_ALPHANUMEXT, 'Short unique name'),
            'displayname' => new external_value(PARAM_TEXT, 'Display name'),
            'description' => new external_value(PARAM_RAW, 'Description', VALUE_DEFAULT, ''),
            'scopecontextid' => new external_value(PARAM_INT, 'System/category context id'),
        ]);
    }

    /**
     * Legt ein neues Methodenset als Entwurf samt erster Version an.
     *
     * @param string $shortname Eindeutiger Kurzname des Methodensets.
     * @param string $displayname Anzeigename des Methodensets.
     * @param string $description Beschreibungstext des Methodensets.
     * @param int $scopecontextid Kontext-Id (System oder Kursbereich), in dem das Set gilt.
     * @return array Array mit den Schlüsseln methodsetid und versionid.
     */
    public static function create_draft_methodset(
        string $shortname,
        string $displayname,
        string $description,
        int $scopecontextid
    ): array {
        $params = self::validate_parameters(self::create_draft_methodset_parameters(), [
            'shortname' => $shortname,
            'displayname' => $displayname,
            'description' => $description,
            'scopecontextid' => $scopecontextid,
        ]);

        $ctx = self::resolve_scope_context((int)$params['scopecontextid']);
        require_capability('local/seminarplaner:createdraftset', $ctx);
        self::enforce_write_rate_limit('create_draft_methodset', 30, 60);
        if (\core_text::strlen((string)$params['description']) > self::MAX_DESCRIPTION_CHARS) {
            throw new invalid_parameter_exception('description exceeds allowed length');
        }

        $repo = new methodset_repository();
        $methodsetid = $repo->create_methodset_draft(
            (string)$params['shortname'],
            (string)$params['displayname'],
            (string)$params['description'],
            (int)$ctx->id,
            (int)$GLOBALS['USER']->id
        );
        $versionid = $repo->create_version((int)$methodsetid, 1, 'draft', '{}', (int)$GLOBALS['USER']->id);

        return ['methodsetid' => $methodsetid, 'versionid' => $versionid];
    }

    /**
     * Beschreibt den Rückgabewert für das Anlegen eines Methodenset-Entwurfs.
     *
     * @return external_single_structure Rückgabedefinition des Webservice.
     */
    public static function create_draft_methodset_returns(): external_single_structure {
        return new external_single_structure([
            'methodsetid' => new external_value(PARAM_INT, 'Method set id'),
            'versionid' => new external_value(PARAM_INT, 'Initial version id'),
        ]);
    }

    /**
     * Beschreibt die Parameter für den Statuswechsel eines Methodensets.
     *
     * @return external_function_parameters Parameterdefinition des Webservice.
     */
    public static function transition_methodset_parameters(): external_function_parameters {
        return new external_function_parameters([
            'methodsetid' => new external_value(PARAM_INT, 'Method set id'),
            'versionid' => new external_value(PARAM_INT, 'Version id', VALUE_DEFAULT, 0),
            'tostatus' => new external_value(PARAM_ALPHA, 'Target status'),
            'comment' => new external_value(PARAM_TEXT, 'Comment', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Führt einen Statuswechsel für ein Methodenset durch.
     *
     * @param int $methodsetid Id des Methodensets.
     * @param int $versionid Id der betroffenen Version, 0 für keine bestimmte Version.
     * @param string $tostatus Zielstatus (draft, review, published oder archived).
     * @param string $comment Optionaler Kommentar zum Statuswechsel.
     * @return array Array mit dem Schlüssel success.
     */
    public static function transition_methodset(int $methodsetid, int $versionid, string $tostatus, string $comment = ''): array {
        $params = self::validate_parameters(self::transition_methodset_parameters(), [
            'methodsetid' => $methodsetid,
            'versionid' => $versionid,
            'tostatus' => $tostatus,
            'comment' => $comment,
        ]);

        $repo = new methodset_repository();
        self::enforce_write_rate_limit('transition_methodset', 60, 60);
        if (\core_text::strlen((string)$params['comment']) > self::MAX_COMMENT_CHARS) {
            throw new invalid_parameter_exception('comment exceeds allowed length');
        }
        $methodset = $repo->get_methodset((int)$params['methodsetid']);
        if (!$methodset) {
            throw new invalid_parameter_exception('Unknown methodsetid');
        }

        $scopectx = context::instance_by_id((int)$methodset->scopecontextid, MUST_EXIST);
        self::validate_context($scopectx);

        $target = strtolower((string)$params['tostatus']);
        if ($target === 'review') {
            require_capability('local/seminarplaner:submitforreview', $scopectx);
        } else if ($target === 'published') {
            require_capability('local/seminarplaner:publishset', context_system::instance());
        } else if ($target === 'draft') {
            if ((string)$methodset->status === 'archived') {
                require_capability('local/seminarplaner:archiveglobalset', context_system::instance());
            } else {
                require_capability('local/seminarplaner:reviewset', $scopectx);
            }
        } else if ($target === 'archived') {
            require_capability('local/seminarplaner:archiveglobalset', context_system::instance());
        } else {
            throw new invalid_parameter_exception('Unsupported target status');
        }

        $service = new workflow_service();
        $service->transition(
            (int)$params['methodsetid'],
            $params['versionid'] ? (int)$params['versionid'] : null,
            $target,
            (int)$GLOBALS['USER']->id,
            (string)$params['comment']
        );

        return ['success' => true];
    }

    /**
     * Beschreibt den Rückgabewert für den Statuswechsel eines Methodensets.
     *
     * @return external_single_structure Rückgabedefinition des Webservice.
     */
    public static function transition_methodset_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Transition status'),
        ]);
    }

    /**
     * Beschreibt die Parameter für das Auflisten von Methodensets.
     *
     * @return external_function_parameters Parameterdefinition des Webservice.
     */
    public static function list_methodsets_parameters(): external_function_parameters {
        return new external_function_parameters([
            'scopecontextid' => new external_value(PARAM_INT, 'System/category context id'),
            'status' => new external_value(PARAM_ALPHA, 'Optional status filter', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Listet die Methodensets eines Kontexts auf, optional nach Status gefiltert.
     *
     * @param int $scopecontextid Kontext-Id (System oder Kursbereich).
     * @param string $status Optionaler Statusfilter, leer für alle Status.
     * @return array Array mit dem Schlüssel methodsets.
     */
    public static function list_methodsets(int $scopecontextid, string $status = ''): array {
        $params = self::validate_parameters(self::list_methodsets_parameters(), [
            'scopecontextid' => $scopecontextid,
            'status' => $status,
        ]);

        $ctx = self::resolve_scope_context((int)$params['scopecontextid']);
        require_capability('local/seminarplaner:viewglobalsets', $ctx);

        $repo = new methodset_repository();
        $sets = $repo->list_methodsets((int)$ctx->id, (string)$params['status']);
        $out = [];

        foreach ($sets as $set) {
            $out[] = [
                'id' => (int)$set->id,
                'shortname' => (string)$set->shortname,
                'displayname' => (string)$set->displayname,
                'status' => (string)$set->status,
                'currentversion' => (int)$set->currentversion,
                'timemodified' => (int)$set->timemodified,
            ];
        }

        return ['methodsets' => $out];
    }

    /**
     * Beschreibt den Rückgabewert für das Auflisten von Methodensets.
     *
     * @return external_single_structure Rückgabedefinition des Webservice.
     */
    public static function list_methodsets_returns(): external_single_structure {
        return new external_single_structure([
            'methodsets' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Method set id'),
                'shortname' => new external_value(PARAM_ALPHANUMEXT, 'Short name'),
                'displayname' => new external_value(PARAM_TEXT, 'Display name'),
                'status' => new external_value(PARAM_ALPHA, 'Status'),
                'currentversion' => new external_value(PARAM_INT, 'Current version id'),
                'timemodified' => new external_value(PARAM_INT, 'Last modified timestamp'),
            ])),
        ]);
    }
}
