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
 * Form for editing a seminar unit of a global set.
 *
 * @package    local_seminarplaner
 * @copyright  2026 Guido Brombach <gibro@posteo.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_seminarplaner\form;

use context;
use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * Mirrors the "Neue Seminareinheit anlegen" form from the activity.
 *
 * Same three sections, same fields, same option lists - so a concept owner meets the
 * layout they already know. Two differences on purpose: the long text fields use the
 * editor configured in Moodle instead of a plain textarea, and "Alternative
 * Seminareinheiten" is left out because a global set has no column for it.
 */
class edit_unit_form extends moodleform {
    /**
     * Long text fields that get the configured Moodle editor.
     *
     * @return string[]
     */
    public static function rich_text_fields(): array {
        return ['lernziele', 'kurzbeschreibung', 'ablauf', 'risiken_tipps', 'debrief', 'material_technik'];
    }

    /**
     * Fields holding several values, stored separated by '##'.
     *
     * @return string[]
     */
    public static function multi_fields(): array {
        return ['seminarphase', 'raumanforderungen', 'sozialform'];
    }

    /**
     * Option lists, mirrored from the activity form.
     *
     * @return array<string, array<string, string>>
     */
    public static function option_lists(): array {
        $plain = static function (array $values): array {
            return array_combine($values, $values);
        };

        return [
            'seminarphase' => $plain(['Orientierung', 'Erfahrungserhebung', 'Analyse', 'Handlungsteil', 'Transfer']),
            'zeitbedarf' => $plain(['5', '10', '20', '30', '45', '60', '90', '120', '150', '180',
                'mehr als 180 Minuten']),
            'gruppengroesse' => $plain(['Gruppenarbeit (2-5)', 'Plenum (10-20)', 'beliebig']),
            'vorbereitung' => $plain(['keine', '<10 Min', '10–30 Min', '>30 Min']),
            'raumanforderungen' => $plain(['Plenum', 'Stuhlkreis', 'Stehtische', 'viel Freifläche',
                'Gruppentische', 'Gruppenräume', 'akustisch ruhig']),
            'sozialform' => $plain(['Vortrag', 'Diskussion', 'Einzelarbeit', 'Partnerarbeit',
                'Kleingruppen', 'Galeriegang', 'Fishbowl']),
        ];
    }

    /**
     * Form definition.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;
        $maxbytes = (int)($this->_customdata['maxbytes'] ?? 0);
        $context = $this->_customdata['context'] ?? null;
        $options = self::option_lists();

        $editoroptions = [
            'trusttext' => false,
            'subdirs' => false,
            'maxfiles' => 0,
            'context' => $context instanceof context ? $context : null,
        ];

        $mform->addElement('hidden', 'methodsetid');
        $mform->setType('methodsetid', PARAM_INT);
        $mform->addElement('hidden', 'methodid');
        $mform->setType('methodid', PARAM_INT);
        $mform->addElement('hidden', 'action', 'saveunit');
        $mform->setType('action', PARAM_ALPHANUMEXT);

        // 1) Schnellfassung.
        $mform->addElement('header', 'quick', get_string('editunitsection1', 'local_seminarplaner'));
        $mform->setExpanded('quick', true);

        $mform->addElement('text', 'title', get_string('editunitfield_title', 'local_seminarplaner'),
            ['size' => 60]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');

        $mform->addElement('editor', 'lernziele_editor',
            get_string('editunitfield_lernziele', 'local_seminarplaner'), null, $editoroptions);
        $mform->setType('lernziele_editor', PARAM_RAW);

        $phase = $mform->addElement('select', 'seminarphase',
            get_string('editunitfield_seminarphase', 'local_seminarplaner'), $options['seminarphase']);
        $phase->setMultiple(true);

        $mform->addElement('text', 'tags', get_string('editunitfield_tags', 'local_seminarplaner'),
            ['size' => 60]);
        $mform->setType('tags', PARAM_TEXT);

        $mform->addElement('select', 'zeitbedarf', get_string('editunitfield_zeitbedarf', 'local_seminarplaner'),
            ['' => ''] + $options['zeitbedarf']);
        $mform->addElement('select', 'gruppengroesse',
            get_string('editunitfield_gruppengroesse', 'local_seminarplaner'),
            ['' => ''] + $options['gruppengroesse']);

        $mform->addElement('editor', 'kurzbeschreibung_editor',
            get_string('editunitfield_kurzbeschreibung', 'local_seminarplaner'), null, $editoroptions);
        $mform->setType('kurzbeschreibung_editor', PARAM_RAW);

        // 2) Ablauf und Rahmen.
        $mform->addElement('header', 'flow', get_string('editunitsection2', 'local_seminarplaner'));
        $mform->setExpanded('flow', true);

        $mform->addElement('editor', 'ablauf_editor',
            get_string('editunitfield_ablauf', 'local_seminarplaner'), null, $editoroptions);
        $mform->setType('ablauf_editor', PARAM_RAW);

        $mform->addElement('text', 'autor_kontakt', get_string('editunitfield_autor', 'local_seminarplaner'),
            ['size' => 60]);
        $mform->setType('autor_kontakt', PARAM_TEXT);

        $raum = $mform->addElement('select', 'raumanforderungen',
            get_string('editunitfield_raum', 'local_seminarplaner'), $options['raumanforderungen']);
        $raum->setMultiple(true);

        $sozial = $mform->addElement('select', 'sozialform',
            get_string('editunitfield_sozialform', 'local_seminarplaner'), $options['sozialform']);
        $sozial->setMultiple(true);

        $mform->addElement('select', 'vorbereitung',
            get_string('editunitfield_vorbereitung', 'local_seminarplaner'),
            ['' => ''] + $options['vorbereitung']);

        $mform->addElement('editor', 'risiken_tipps_editor',
            get_string('editunitfield_risiken', 'local_seminarplaner'), null, $editoroptions);
        $mform->setType('risiken_tipps_editor', PARAM_RAW);

        $mform->addElement('editor', 'debrief_editor',
            get_string('editunitfield_debrief', 'local_seminarplaner'), null, $editoroptions);
        $mform->setType('debrief_editor', PARAM_RAW);

        // 3) Materialien und Technik.
        $mform->addElement('header', 'material', get_string('editunitsection3', 'local_seminarplaner'));
        $mform->setExpanded('material', true);

        $mform->addElement('filemanager', 'materialien',
            get_string('editunitfield_materialien', 'local_seminarplaner'), null, [
                'subdirs' => 0,
                'maxfiles' => 25,
                'accepted_types' => '*',
                'maxbytes' => $maxbytes,
                'areamaxbytes' => $maxbytes,
                'context' => $context instanceof context ? $context : null,
            ]);

        // Im Aktivitaets-Formular ist das eine Textarea, und die Daten tragen Listen -
        // ein einzeiliges Textfeld haette die Auszeichnung beim Speichern zerrissen.
        $mform->addElement('editor', 'material_technik_editor',
            get_string('editunitfield_materialtechnik', 'local_seminarplaner'), null, $editoroptions);
        $mform->setType('material_technik_editor', PARAM_RAW);

        $this->add_action_buttons(true, get_string('editunitsave', 'local_seminarplaner'));
    }
}
