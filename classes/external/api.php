<?php
// This file is part of Moodle - http://moodle.org/

namespace local_konzeptgenerator\external;

use context;
use context_coursecat;
use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;
use local_konzeptgenerator\local\repository\methodset_repository;
use local_konzeptgenerator\local\service\workflow_service;

defined('MOODLE_INTERNAL') || die();

/**
 * External API for global method set governance.
 */
class api extends external_api {
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

    public static function create_draft_methodset_parameters(): external_function_parameters {
        return new external_function_parameters([
            'shortname' => new external_value(PARAM_ALPHANUMEXT, 'Short unique name'),
            'displayname' => new external_value(PARAM_TEXT, 'Display name'),
            'description' => new external_value(PARAM_RAW, 'Description', VALUE_DEFAULT, ''),
            'scopecontextid' => new external_value(PARAM_INT, 'System/category context id'),
        ]);
    }

    public static function create_draft_methodset(string $shortname, string $displayname, string $description,
        int $scopecontextid): array {
        $params = self::validate_parameters(self::create_draft_methodset_parameters(), [
            'shortname' => $shortname,
            'displayname' => $displayname,
            'description' => $description,
            'scopecontextid' => $scopecontextid,
        ]);

        $ctx = self::resolve_scope_context((int)$params['scopecontextid']);
        require_capability('local/konzeptgenerator:createdraftset', $ctx);

        $repo = new methodset_repository();
        $methodsetid = $repo->create_methodset_draft((string)$params['shortname'], (string)$params['displayname'],
            (string)$params['description'], (int)$ctx->id, (int)$GLOBALS['USER']->id);
        $versionid = $repo->create_version((int)$methodsetid, 1, 'draft', '{}', (int)$GLOBALS['USER']->id);

        return ['methodsetid' => $methodsetid, 'versionid' => $versionid];
    }

    public static function create_draft_methodset_returns(): external_single_structure {
        return new external_single_structure([
            'methodsetid' => new external_value(PARAM_INT, 'Method set id'),
            'versionid' => new external_value(PARAM_INT, 'Initial version id'),
        ]);
    }

    public static function transition_methodset_parameters(): external_function_parameters {
        return new external_function_parameters([
            'methodsetid' => new external_value(PARAM_INT, 'Method set id'),
            'versionid' => new external_value(PARAM_INT, 'Version id', VALUE_DEFAULT, 0),
            'tostatus' => new external_value(PARAM_ALPHA, 'Target status'),
            'comment' => new external_value(PARAM_TEXT, 'Comment', VALUE_DEFAULT, ''),
        ]);
    }

    public static function transition_methodset(int $methodsetid, int $versionid, string $tostatus, string $comment = ''): array {
        $params = self::validate_parameters(self::transition_methodset_parameters(), [
            'methodsetid' => $methodsetid,
            'versionid' => $versionid,
            'tostatus' => $tostatus,
            'comment' => $comment,
        ]);

        $repo = new methodset_repository();
        $methodset = $repo->get_methodset((int)$params['methodsetid']);
        if (!$methodset) {
            throw new invalid_parameter_exception('Unknown methodsetid');
        }

        $scopectx = context::instance_by_id((int)$methodset->scopecontextid, MUST_EXIST);
        self::validate_context($scopectx);

        $target = strtolower((string)$params['tostatus']);
        if ($target === 'review') {
            require_capability('local/konzeptgenerator:submitforreview', $scopectx);
        } else if ($target === 'published') {
            require_capability('local/konzeptgenerator:publishset', context_system::instance());
        } else if ($target === 'draft') {
            if ((string)$methodset->status === 'archived') {
                require_capability('local/konzeptgenerator:archiveglobalset', context_system::instance());
            } else {
                require_capability('local/konzeptgenerator:reviewset', $scopectx);
            }
        } else if ($target === 'archived') {
            require_capability('local/konzeptgenerator:archiveglobalset', context_system::instance());
        } else {
            throw new invalid_parameter_exception('Unsupported target status');
        }

        $service = new workflow_service();
        $service->transition((int)$params['methodsetid'], $params['versionid'] ? (int)$params['versionid'] : null,
            $target, (int)$GLOBALS['USER']->id, (string)$params['comment']);

        return ['success' => true];
    }

    public static function transition_methodset_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Transition status'),
        ]);
    }

    public static function list_methodsets_parameters(): external_function_parameters {
        return new external_function_parameters([
            'scopecontextid' => new external_value(PARAM_INT, 'System/category context id'),
            'status' => new external_value(PARAM_ALPHA, 'Optional status filter', VALUE_DEFAULT, ''),
        ]);
    }

    public static function list_methodsets(int $scopecontextid, string $status = ''): array {
        $params = self::validate_parameters(self::list_methodsets_parameters(), [
            'scopecontextid' => $scopecontextid,
            'status' => $status,
        ]);

        $ctx = self::resolve_scope_context((int)$params['scopecontextid']);
        require_capability('local/konzeptgenerator:viewglobalsets', $ctx);

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
