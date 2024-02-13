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

namespace format_moointopics\output\courseformat\content\section;

use core_courseformat\output\local\content\section\header as header_base;
use stdClass;
use core_courseformat\base as course_format;
use format_moointopics;
use section_info;

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
        return 'format_moointopics/local/content/section/header';
    }

    public function export_for_template(\renderer_base $output): stdClass {

        $format = $this->format;
        $chapter = $this->chapter;
        $section = $this->section;
        $course = $format->get_course();

        $data = (object)[
            'num' => $section->section,
            'id' => $section->id,
        ];

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
                    $data->prefix = format_moointopics\local\chapterlib::get_section_prefix($section);
                    $data->title_with_link = $output->section_title($section, $course);
                    $data->title_without_link = $output->section_title_without_link($section, $course);
                }
                

            
                 //$data->prefix = format_moointopics\local\chapterlib::get_section_prefix($section);
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
