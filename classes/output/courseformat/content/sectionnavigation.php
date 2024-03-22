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
 * Contains the default section navigation output class.
 *
 * @package   core_courseformat
 * @copyright 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_moointopics\output\courseformat\content;

use context_course;
use core_courseformat\output\local\content\sectionnavigation as sectionnavigation_base;
use format_moointopics;
use stdClass;
use renderer_base;


/**
 * Base class to render a course add section navigation.
 *
 * @package   core_courseformat
 * @copyright 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sectionnavigation extends sectionnavigation_base {

    private $data = null;

    public function get_template_name(\renderer_base $renderer): string {
        return 'format_moointopics/local/content/sectionnavigation';
    }

    public function export_for_template(\renderer_base $output): stdClass {
        global $USER, $DB, $PAGE;

        if ($this->data !== null) {
            return $this->data;
        }

        $format = $this->format;
        $course = $format->get_course();
        $context = context_course::instance($course->id);
        

        $modinfo = $this->format->get_modinfo();
        $sections = $modinfo->get_section_info_all();
        $section = $sections[$this->sectionno];

        // FIXME: This is really evil and should by using the navigation API.
        $canviewhidden = has_capability('moodle/course:viewhiddensections', $context, $USER);

        $data = (object)[
            'previousurl' => '',
            'nexturl' => '',
            //'larrow' => $output->larrow(),
            //'rarrow' => $output->rarrow(),
            'currentsection' => $this->sectionno,
            'title_without_link' => $output->section_title_without_link($section, $course),
            'coursebreadcrumb' => format_moointopics\local\chapterlib::course_navbar()
        ];

        $section_progress = format_moointopics\local\progresslib::get_section_progress($course->id, $section->id, $USER->id);
        $data->sectionprogress = $section_progress;



        $back = $this->sectionno - 1;
        //$isChapter_prev = false;



        while ($back > 0 and empty($data->previousurl)) {
            if ($DB->get_record('format_moointopics_chapter', array('sectionid' => $sections[$back]->id))) {
                $data->previousname = get_string('previous_chapter', 'format_moointopics');
            } else {
                if ($canviewhidden || $sections[$back]->uservisible) {
                    if (!$sections[$back]->visible) {
                        $data->previoushidden = true;
                    }
                    if (empty($data->previousname)) {
                        $data->previousname = get_string('previous_lesson', 'format_moointopics');
                    }
                    


                    $data->previousurl = course_get_url($course, $back);
                    $data->hasprevious = true;
                }
            }

            $back--;
        }

        

        $forward = $this->sectionno + 1;
        $numsections = course_get_format($course)->get_last_section_number();
        while ($forward <= $numsections and empty($data->nexturl)) {
            if ($DB->get_record('format_moointopics_chapter', array('sectionid' => $sections[$forward]->id))) {
                $data->nextname = get_string('next_chapter', 'format_moointopics'); 
            } else {
                if ($canviewhidden || $sections[$forward]->uservisible) {
                    if (!$sections[$forward]->visible) {
                        $data->nexthidden = true;
                    }
                    if (empty($data->nextname)) {
                        $data->nextname = get_string('next_lesson', 'format_moointopics');
                    }
                    
                    $data->nexturl = course_get_url($course, $forward);
                    $data->hasnext = true;
                }
            }
            $forward++;
        }

        if ($this->sectionno == 2) {
            $data->hasprevious = false;
        }

        $this->data = $data;
        return $data;
    }
}
