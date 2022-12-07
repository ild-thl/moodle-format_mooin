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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Page that shows a form to manage and set additional metadata dor a course.
 *
 * @package     format_mooin
 * @copyright   2022 ISy TH Lübeck <dev.ild@th-luebeck.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

class edit_header_form extends \moodleform {
    public function definition() {
        $mform = $this->_form;
        $filemanageropts = $this->_customdata['filemanageropts'];        

        $mform->addElement('filemanager', 'headerimagedesktop', get_string('headerimagedesktop', 'format_mooin'), null, $filemanageropts);
        $mform->addElement('filemanager', 'headerimagemobile', get_string('headerimagemobile', 'format_mooin'), null, $filemanageropts);

        $this->add_action_buttons();
    }
}