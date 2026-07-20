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
 * Web service and external function definitions.
 *
 * @package    local_seminarplaner
 * @copyright  2026 Guido Brombach <gibro@posteo.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
