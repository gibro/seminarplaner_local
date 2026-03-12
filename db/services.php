<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_seminarplaner_create_draft_methodset' => [
        'classname' => 'local_seminarplaner\\external\\api',
        'methodname' => 'create_draft_methodset',
        'classpath' => '',
        'description' => 'Create a draft global method set.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_seminarplaner_transition_methodset' => [
        'classname' => 'local_seminarplaner\\external\\api',
        'methodname' => 'transition_methodset',
        'classpath' => '',
        'description' => 'Transition method set workflow state.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_seminarplaner_list_methodsets' => [
        'classname' => 'local_seminarplaner\\external\\api',
        'methodname' => 'list_methodsets',
        'classpath' => '',
        'description' => 'List global method sets by scope.',
        'type' => 'read',
        'ajax' => true,
    ],
];
