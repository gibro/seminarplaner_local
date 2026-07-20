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
 * Import existing set form.
 *
 * @package    local_seminarplaner
 * @copyright  2026 Guido Brombach <gibro@posteo.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_seminarplaner\form;

use moodleform;

defined('MOODLE_INTERNAL') || die();

/**
 * Form for importing methods into an existing draft method set.
 */
class import_existing_set_form extends moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;
        $draftoptions = (array)($this->_customdata['draftoptions'] ?? []);
        $maxbytes = (int)($this->_customdata['maxbytes'] ?? 0);

        $mform->addElement('hidden', 'action', 'importmoddata_existingset');
        $mform->setType('action', PARAM_ALPHANUMEXT);

        $mform->addElement('select', 'methodsetid', get_string('targetdraftset', 'local_seminarplaner'),
            $draftoptions + ['' => get_string('choose')]);
        $mform->setType('methodsetid', PARAM_INT);
        $mform->addRule('methodsetid', null, 'required', null, 'client');

        $mform->addElement('filemanager', 'importfileexisting', get_string('importfile', 'local_seminarplaner'), null, [
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['.csv', '.zip', '.json'],
            'maxbytes' => $maxbytes,
        ]);

        $this->add_action_buttons(false, get_string('importexistingsetsubmit', 'local_seminarplaner'));
    }
}
