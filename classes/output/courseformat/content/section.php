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
 * Contains the default section controls output class.
 *
 * @package   format_moointopics
 * @copyright 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_moointopics\output\courseformat\content;

use core_courseformat\base as course_format;
use core_courseformat\output\local\content\section as section_base;
use format_moointopics;
use stdClass;
use section_info;
use renderer_base;
use format_moointopics\local\chapterlib;

/**
 * Base class to render a course section.
 *
 * @package   format_moointopics
 * @copyright 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section extends section_base {

    /** @var course_format the course format */
    protected $format;

    /** @var isChapter if this section is actually a chapter */
    protected $chapter;

    protected $containsActiveSection = false;

    /** @var is_first_section_of_chapter if this section is the first section of a chapter */
    protected $is_first_section_of_chapter = false;

    /** @var is_last_section_of_chapter if this section is the last section of a chapter */
    protected $is_last_section_of_chapter = false;

    /** @var parent_chapter if this section is the last section of a chapter */
    protected $parent_chapter;



    public function __construct(course_format $format, section_info $section) {
        global $USER;
        parent::__construct($format, $section);
        $course = $format->get_course();
        $sectionnumber = optional_param('section', 0, PARAM_INT);
        if ($sectionnumber > 0) {
            set_user_preference('format_moointopics_last_section_in_course_' . $course->id, $sectionnumber, $USER->id);
        }
        $this->addChapterData();
    }

    public function get_template_name(\renderer_base $renderer): string {
        return 'format_moointopics/local/content/section';
    }

    public function export_for_template(\renderer_base $output): stdClass {
        global $USER, $DB;

        $format = $this->format;

        $data = parent::export_for_template($output);

        $course = $this->format->get_course();
        //$sectionnumber = optional_param('section', 0, PARAM_INT);
        // if ($sectionnumber > 0) {
        //     set_user_preference('format_moointopics_last_section_in_course_' . $course->id, $sectionnumber, $USER->id);
        // }



        if (!$this->format->get_section_number()) {
            $addsectionclass = $format->get_output_classname('content\\addsection');
            $addsection = new $addsectionclass($format);
            $data->numsections = $addsection->export_for_template($output);
            $data->insertafter = true;
            $data->isChapter = $this->chapter;
            $data->chapter_num = $this->chapter ? $this->chapter->chapter : null;
            $data->is_first_section_of_chapter = $this->is_first_section_of_chapter;
            $data->is_last_section_of_chapter = $this->is_last_section_of_chapter;
            $data->parent_chapter = $this->parent_chapter ? $this->parent_chapter->chapter : null;
            $data->isActiveSection = $this->is_active_section();
            $data->containsActiveSection = $this->containsActiveSection;
        }

        // $section_progress = format_moointopics\local\progresslib::get_section_progress($course->id, $this->section, $USER->id);
        // $data->sectionprogress = $section_progress;

        if (!$DB->get_records('course_modules', array(
            'course' => $course->id,
            'deletioninprogress' => 0,
            'section' => $this->section->id,
            'completion' => 2
        ))) {

            $data->showCompletionButton = true;
            if (format_moointopics\local\progresslib::get_section_progress($course->id, $this->section->id, $USER->id) == 100) {
                $data->isCompleted = true;
            }
        }

        if ($chapter = $this->chapter) {
            $info = chapterlib::get_chapter_info($chapter);
            if ($info['completed']) {
                $data->isCompleted = true;
            }
        }


        return $data;
    }

    protected function is_active_section() {
        global $USER;
        $course = $this->format->get_course();
        $last_section = get_user_preferences('format_moointopics_last_section_in_course_' . $course->id, 0, $USER->id);
        if ($last_section == $this->section->section) {
            return true;
        } else {
            return false;
        }
    }

    protected function add_header_data(stdClass &$data, renderer_base $output): bool {
        if (!empty($this->hidetitle)) {
            return false;
        }

        $section = $this->section;
        $format = $this->format;

        $header = new $this->headerclass($format, $section, $this->chapter);
        $headerdata = $header->export_for_template($output);

        // When a section is displayed alone the title goes over the section, not inside it.
        if ($section->section != 0 && $section->section == $format->get_section_number()) {
            $data->singleheader = $headerdata;
        } else {
            $data->header = $headerdata;
        }
        return true;
    }

    protected function addChapterData() {
        global $DB;
        global $USER;
        $course = $this->format->get_course();
        
        if ($chapter = $DB->get_record('format_moointopics_chapter', array('sectionid' => $this->section->id))) {
            $this->chapter = $chapter;
            $last_section = get_user_preferences('format_moointopics_last_section_in_course_' . $course->id, 0, $USER->id);
            if ($continuesection = $DB->get_record('course_sections', array('course' => $course->id, 'section' => $last_section))) {
                $last_sections_parent_chapter = chapterlib::get_parent_chapter($continuesection);
                if ($last_sections_parent_chapter == $this->chapter) {
                    $this->containsActiveSection = true;
                }
            }
        }
        if (empty($this->chapter)) {
            if (chapterlib::is_first_section_of_chapter($this->section->id)) {
                $this->is_first_section_of_chapter = true;
            }
            if (chapterlib::is_last_section_of_chapter($this->section->id)) {
                $this->is_last_section_of_chapter = true;
            }

            $this->parent_chapter = chapterlib::get_parent_chapter($this->section);
            $last_section = get_user_preferences('format_moointopics_last_section_in_course_' . $course->id, 0, $USER->id);
            if ($continuesection = $DB->get_record('course_sections', array('course' => $course->id, 'section' => $last_section))) {
                $last_sections_parent_chapter = chapterlib::get_parent_chapter($continuesection);
                if ($last_sections_parent_chapter == $this->parent_chapter) {
                    $this->containsActiveSection = true;
                }
            }
        }
    }
}
