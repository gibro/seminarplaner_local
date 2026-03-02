<?php
// This file is part of Moodle - http://moodle.org/

namespace local_konzeptgenerator\form;

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

        $mform->addElement('select', 'methodsetid', get_string('targetdraftset', 'local_konzeptgenerator'),
            $draftoptions + ['' => get_string('choose')]);
        $mform->setType('methodsetid', PARAM_INT);
        $mform->addRule('methodsetid', null, 'required', null, 'client');

        $mform->addElement('filemanager', 'importfileexisting', get_string('importfile', 'local_konzeptgenerator'), null, [
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['.csv', '.zip'],
            'maxbytes' => $maxbytes,
        ]);

        $this->add_action_buttons(false, get_string('importexistingsetsubmit', 'local_konzeptgenerator'));
    }
}
