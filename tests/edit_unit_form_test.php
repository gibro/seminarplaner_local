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
 * Unit tests for the edit form of global seminar units.
 *
 * @package    local_seminarplaner
 * @copyright  2026 Guido Brombach <gibro@posteo.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_seminarplaner\form\edit_unit_form;

/**
 * Eigene Sozialformen neben den vorgegebenen ueberstehen das Absenden des Formulars.
 */
final class edit_unit_form_test extends advanced_testcase {
    public function test_custom_sozialform_survives_submit(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        edit_unit_form::mock_submit([
            'methodsetid' => 1,
            'methodid' => 1,
            'action' => 'saveunit',
            'title' => 'Blitzlicht',
            'sozialform' => ['Kleingruppen', 'Plenum', 'Murmelgruppe'],
        ]);
        $form = new edit_unit_form(null, ['maxbytes' => 0, 'context' => context_system::instance()]);
        $data = $form->get_data();

        $this->assertNotNull($data);
        $this->assertSame(['Kleingruppen', 'Plenum', 'Murmelgruppe'], array_values((array)$data->sozialform));
    }
}
