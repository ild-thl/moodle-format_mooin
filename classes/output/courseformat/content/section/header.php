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
 * Contains the default section header format output class.
 *
 * @package   core_courseformat
 * @copyright 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mooin4\output\courseformat\content\section;

use core_courseformat\output\local\content\section\header as header_base;
use stdClass;
use core_courseformat\base as course_format;
use format_mooin4;
use section_info;
use format_mooin4\local\utils as utils;

/**
 * Base class to render a section header.
 *
 * @package   core_courseformat
 * @copyright 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class header extends header_base {

    protected $chapter;

    public function __construct(course_format $format, section_info $section, $chapter) {
        parent::__construct($format, $section);
        $this->chapter = $chapter;
    }

    public function get_template_name(\renderer_base $renderer): string {
        return 'format_mooin4/local/content/section/header';
    }

    public function export_for_template(\renderer_base $output): stdClass {
        global $USER;

        $format = $this->format;
        $chapter = $this->chapter;
        $section = $this->section;
        $course = $format->get_course();

        $data = (object)[
            'num' => $section->section,
            'id' => $section->id,
        ];

        //TODO: 
        require_once(__DIR__ . '/../../../../../lib.php');
        $courseid = $course->id;
        if (get_toggle_section_number_visibility($courseid) === 1) {
            $data->sec_numb_visibility = true; 
        }
        else {
            $data->sec_numb_visibility = false; 
        }
        $data->title = $output->section_title_without_link($section, $course);

        $coursedisplay = $format->get_course_display();
        $data->headerdisplaymultipage = false;
        if ($coursedisplay == COURSE_DISPLAY_MULTIPAGE) {
            $data->headerdisplaymultipage = true;

                if ($chapter) {
                    $data->chapter = true;
                    $data->prefix = $chapter->chapter;
                    $data->title = $output->section_title_without_link($section, $course);
                } else {
                    $data->chapter = false;
                    $data->prefix = utils::get_section_prefix($section);
                    $data->title_with_link = $output->section_title($section, $course);
                    $data->title_without_link = $output->section_title_without_link($section, $course);
                    // if (format_mooin4\local\progresslib::get_section_progress($course->id, $this->section->id, $USER->id) == 100) {
                    //     $data->isCompleted = true;
                    // }
                }
                

            
                 //$data->prefix = format_mooin4\local\chapterlib::get_section_prefix($section);
                //$url = course_get_url($course, $section->section, array('navigation' => true));
                //$data->title = $output->section_title_without_link($section, $course);
                 
                // $data->url = course_get_url($course, $section->section, array('navigation' => true));

            


        }

        if ($section->section > $format->get_last_section_number()) {
            // Stealth sections (orphaned) has special title.
            $data->title = get_string('orphanedactivitiesinsectionno', '', $section->section);
        }

        if (!$section->visible) {
            $data->ishidden = true;
        }

        if ($course->id == SITEID) {
            $data->sitehome = true;
        }

        $data->editing = $format->show_editor();

        if (!$format->show_editor() && $coursedisplay == COURSE_DISPLAY_MULTIPAGE && empty($data->issinglesection)) {
            if ($section->uservisible) {
                $data->url = course_get_url($course, $section->section);
            }
        }
        $data->name = get_section_name($course, $section);
        return $data;
    }
}
