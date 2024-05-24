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

namespace format_moointopics\output\courseformat\state;

use core_courseformat\output\local\state\section as section_base;
use core_availability\info_section;
use core_courseformat\base as course_format;
use section_info;
use renderable;
use stdClass;
use context_course;
use renderer_base;
use format_moointopics\local\chapterlib;
use format_moointopics\local\progresslib;
use moodle_url;

/**
 * Contains the ajax update section structure.
 *
 * @package   core_course
 * @copyright 2021 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section extends section_base {

    protected $continuesection;

    /**
     * Constructor.
     *
     * @param course_format $format the course format
     * @param section_info $section the section info
     */
    // public function __construct(course_format $format, section_info $section) {
    //     global $USER;
    //     parent::__construct($format, $section);
    //     $course = $format->get_course();
    //     $sectionnumber = optional_param('section', 0, PARAM_INT);
    //     if ($sectionnumber > 0) {
    //         set_user_preference('format_moointopics_last_section_in_course_' . $course->id, $sectionnumber, $USER->id);
    //     }
    // }

    //protected $containsActiveSection = false;

    /**
     * Export this data so it can be used as state object in the course editor.
     *
     * @param renderer_base $output typically, the renderer that's calling this function
     * @return array data context for a mustache template
     */
    public function export_for_template(\renderer_base $output): stdClass {
        global $DB, $USER, $PAGE;
        $isChapter = false;
        $course = $this->format->get_course();


        if ($chapter = $DB->get_record('format_moointopics_chapter', array('sectionid' => $this->section->id))) {
            $isChapter = $chapter->chapter;
        } else {
            $parentchapter = chapterlib::get_parent_chapter($this->section);
        }
        $data = (object)parent::export_for_template($output);
        $data->isChapter = $isChapter;
        if ($parentchapter) {
            $data->parentChapter = $parentchapter->chapter;

            if ($parentchapterAsSection = $DB->get_record('course_sections', array('id' => $parentchapter->sectionid))) {
                $data->innerChapterNumber = $this->section->section - $parentchapterAsSection->section;
            }   
        }
        //$data->containsActiveSection = $this->containsActiveSection;

        $section_progress = progresslib::get_section_progress($course->id, $this->section->id, $USER->id);
        $data->sectionprogress = $section_progress;
        if ($section_progress == 100) {
            $data->isCompleted = true;
        }

        if ($chapter = $DB->get_record('format_moointopics_chapter', array('sectionid' => $this->section->id))) {
            $last_section = get_user_preferences('format_moointopics_last_section_in_course_' . $course->id, 0, $USER->id);
            if ($continuesection = $DB->get_record('course_sections', array('course' => $course->id, 'section' => $last_section))) {
                $last_sections_parent_chapter = chapterlib::get_parent_chapter($continuesection);
                if ($last_sections_parent_chapter == $chapter) {
                    $data->containsActiveSection = true;
                }
            }
        }

        if (!$isChapter) {
            if (chapterlib::is_first_section_of_chapter($this->section->id)) {
                $data->isFirstSectionOfChapter = true;
            }
            if (chapterlib::is_last_section_of_chapter($this->section->id)) {
                $data->isLastSectionOfChapter = true;
                if (!get_user_preferences('format_moointopics_hide_modal_for_section_'.$this->section->id)) {
                    $data->showLastSectionModal = true;
                    //set_user_preference('format_moointopics_hide_modal_for_section_'.$this->section->id, 'true');
                }
            }

            $parent_chapter = chapterlib::get_parent_chapter($this->section);
            $last_section = get_user_preferences('format_moointopics_last_section_in_course_' . $course->id, 0, $USER->id);
            if ($continuesection = $DB->get_record('course_sections', array('course' => $course->id, 'section' => $last_section))) {
                $last_sections_parent_chapter = chapterlib::get_parent_chapter($continuesection);
                if ($last_sections_parent_chapter == $parent_chapter) {
                    $data->containsActiveSection = true;
                }
            }

            if ($last_section == $this->section->section) {
                $data->isActiveSection = true;
            }
            
        }

        return $data;
    }



    
}
