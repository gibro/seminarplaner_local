<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/konzeptgenerator:viewglobalsets' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
    'local/konzeptgenerator:createdraftset' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
    'local/konzeptgenerator:editdraftset' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
    'local/konzeptgenerator:submitforreview' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
    'local/konzeptgenerator:reviewset' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
    'local/konzeptgenerator:publishset' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [],
    ],
    'local/konzeptgenerator:archiveglobalset' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [],
    ],
    'local/konzeptgenerator:manageareascopes' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
    'local/konzeptgenerator:importglobalset' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
    'local/konzeptgenerator:exportglobalset' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
