<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_konzeptgenerator_create_draft_methodset' => [
        'classname' => 'local_konzeptgenerator\\external\\api',
        'methodname' => 'create_draft_methodset',
        'classpath' => '',
        'description' => 'Create a draft global method set.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_konzeptgenerator_transition_methodset' => [
        'classname' => 'local_konzeptgenerator\\external\\api',
        'methodname' => 'transition_methodset',
        'classpath' => '',
        'description' => 'Transition method set workflow state.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_konzeptgenerator_list_methodsets' => [
        'classname' => 'local_konzeptgenerator\\external\\api',
        'methodname' => 'list_methodsets',
        'classpath' => '',
        'description' => 'List global method sets by scope.',
        'type' => 'read',
        'ajax' => true,
    ],
];
