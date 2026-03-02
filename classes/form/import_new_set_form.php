<?php
// This file is part of Moodle - http://moodle.org/

namespace local_konzeptgenerator\form;

use moodleform;

defined('MOODLE_INTERNAL') || die();

/**
 * Form for importing a new global method set via Moodle file manager.
 */
class import_new_set_form extends moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;
        $maxbytes = (int)($this->_customdata['maxbytes'] ?? 0);

        $mform->addElement('hidden', 'action', 'importmoddata_newset');
        $mform->setType('action', PARAM_ALPHANUMEXT);

        $mform->addElement('text', 'displayname', get_string('name'));
        $mform->setType('displayname', PARAM_TEXT);
        $mform->addRule('displayname', null, 'required', null, 'client');

        $mform->addElement('text', 'shortname', get_string('shortname'));
        $mform->setType('shortname', PARAM_ALPHANUMEXT);
        $mform->addRule('shortname', null, 'required', null, 'client');

        $mform->addElement('textarea', 'description', get_string('description'), ['rows' => 2]);
        $mform->setType('description', PARAM_TEXT);

        $mform->addElement('filemanager', 'importfilenew', get_string('importfile', 'local_konzeptgenerator'), null, [
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['.csv', '.zip'],
            'maxbytes' => $maxbytes,
        ]);

        $this->add_action_buttons(false, get_string('importnewsetsubmit', 'local_konzeptgenerator'));
    }
}
