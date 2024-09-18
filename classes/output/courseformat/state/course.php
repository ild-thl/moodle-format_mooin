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

namespace format_mooin4\output\courseformat\state;

use core_courseformat\base as course_format;
use core_courseformat\output\local\state\course as course_base;
use course_modinfo;
use moodle_url;
use renderable;
use stdClass;
use renderer_base;

/**
 * Contains the ajax update course structure.
 *
 * @package   core_course
 * @copyright 2021 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course extends course_base {
    

    /**
     * Export this data so it can be used as state object in the course editor.
     *
     * @param renderer_base $output typically, the renderer that's calling this function
     * @return stdClass data context for a mustache template
     */
    // public function export_for_template(\renderer_base $output): stdClass {
    //     global $USER, $DB;
    //     $data = parent::export_for_template($output);
    //     $course = $this->format->get_course();
    //     $last_section = get_user_preferences('format_mooin4_last_section_in_course_' . $course->id, 0, $USER->id);
    //     if ($continuesection = $DB->get_record('course_sections', array('course' => $course->id, 'section' => $last_section))) {
    //         $data->continueSection = $continuesection->id;
    //     }
        


    //     return $data;
    // }
}
