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
 * @package   format_mooin4
 * @copyright 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mooin4\output\courseformat\content;

use core_courseformat\base as course_format;
use core_courseformat\output\local\content\section as section_base;
use format_mooin4;
use stdClass;
use section_info;
use renderer_base;
use format_mooin4\local\utils as utils;

/**
 * Base class to render a course section.
 *
 * @package   format_mooin4
 * @copyright 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section extends section_base {

    /** @var course_format the course format */
    protected $format;

    /** @var stdClass|null if this section is actually a chapter */
    protected $chapter;

    /** @var bool whether this section contains the active section */
    protected $containsactivesection = false;

    /** @var bool if this section is the first section of a chapter */
    protected $isfirstsectionofchapter = false;

    /** @var bool if this section is the last section of a chapter */
    protected $islastsectionofchapter = false;

    /** @var stdClass|null the parent chapter if this section is part of one */
    protected $parentchapter;

    /**
     * Constructor.
     *
     * @param course_format $format the course format
     * @param section_info $section the section info
     */
    public function __construct(course_format $format, section_info $section) {
        global $USER;
        parent::__construct($format, $section);
        $course = $format->get_course();
        $sectionnumber = $format->get_sectionnum();
        if (!empty($sectionnumber)) {
            set_user_preference('format_mooin4_last_section_in_course_' . $course->id, $sectionnumber, $USER->id);
        }
        $this->add_chapter_data();
    }

    /**
     * Get the name of the template to use.
     *
     * @param renderer_base $renderer the renderer
     * @return string the template name
     */
    public function get_template_name(\renderer_base $renderer): string {
        return 'format_mooin4/local/content/section';
    }

    /**
     * Export the data for the template.
     *
     * @param renderer_base $output the renderer
     * @return stdClass the data
     */
    public function export_for_template(\renderer_base $output): stdClass {
        global $USER, $DB;

        $format = $this->format;

        $data = parent::export_for_template($output);

        $course = $this->format->get_course();

        // Update course table prefix according course settings.
        require_once(__DIR__ . '/../../../../lib.php');
        $courseid = $course->id;
        if (get_toggle_section_number_visibility($courseid) === 1) {
            $data->sec_numb_visibility = true;
        } else {
            $data->sec_numb_visibility = false;
        }

        if (!$this->format->get_sectionnum()) {
            $addsectionclass = $format->get_output_classname('content\\addsection');
            $addsection = new $addsectionclass($format);
            $data->numsections = $addsection->export_for_template($output);
            $data->insertafter = true;
            $data->isChapter = $this->chapter;
            $data->chapter_num = $this->chapter ? $this->chapter->chapter : null;
            $data->is_first_section_of_chapter = $this->isfirstsectionofchapter;
            $data->is_last_section_of_chapter = $this->islastsectionofchapter;
            $data->parent_chapter = $this->parentchapter ? $this->parentchapter->chapter : null;
            $data->isActiveSection = $this->is_active_section();
            $data->containsActiveSection = $this->containsactivesection;
        }

        $sectionprogress = utils::get_section_progress($course->id, $this->section->id, $USER->id);
        $data->sectionprogress = $sectionprogress;

        // Show the section completion button only when no activity in the section
        // has completion tracking enabled (manual or automatic).
        if (!$DB->record_exists_select(
            'course_modules',
            'course = :course AND deletioninprogress = 0 AND section = :section AND completion <> 0',
            [
                'course' => $course->id,
                'section' => $this->section->id,
            ]
        )) {
            $data->showCompletionButton = true;
        }
        if ($sectionprogress == 100) {
            $data->isCompleted = true;
        }

        if ($chapter = $this->chapter) {
            $info = utils::get_chapter_info($chapter);
            if ($info['completed']) {
                $data->isCompleted = true;
            }
        }

        return $data;
    }

    /**
     * Check if this is the active section.
     *
     * @return bool true if it is the active section
     */
    protected function is_active_section() {
        global $USER;
        $course = $this->format->get_course();
        $lastsection = get_user_preferences('format_mooin4_last_section_in_course_' . $course->id, 0, $USER->id);
        if ($lastsection == $this->section->section) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Add header data to the template data.
     *
     * @param stdClass $data the template data
     * @param renderer_base $output the renderer
     * @return bool true if header data was added
     */
    protected function add_header_data(stdClass &$data, renderer_base $output): bool {
        if (!empty($this->hidetitle)) {
            return false;
        }

        $section = $this->section;
        $format = $this->format;

        $header = new $this->headerclass($format, $section, $this->chapter);
        $headerdata = $header->export_for_template($output);

        // When a section is displayed alone the title goes over the section, not inside it.
        if ($section->section != 0 && $section->section == $format->get_sectionnum()) {
            $data->singleheader = $headerdata;
        } else {
            $data->header = $headerdata;
        }
        return true;
    }

    /**
     * Add chapter data to the section.
     */
    protected function add_chapter_data() {
        global $DB;
        global $USER;
        $course = $this->format->get_course();

        if ($chapter = $DB->get_record('format_mooin4_chapter', ['sectionid' => $this->section->id])) {
            $this->chapter = $chapter;
            $lastsection = get_user_preferences('format_mooin4_last_section_in_course_' . $course->id, 0, $USER->id);
            if ($continuesection = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $lastsection])) {
                $lastsectionsparentchapter = utils::get_parent_chapter($continuesection);
                if ($lastsectionsparentchapter == $this->chapter) {
                    $this->containsactivesection = true;
                }
            }
        }
        if (empty($this->chapter)) {
            if (utils::is_first_section_of_chapter($this->section->id)) {
                $this->isfirstsectionofchapter = true;
            }
            if (utils::is_last_section_of_chapter($this->section->id)) {
                $this->islastsectionofchapter = true;
            }

            $this->parentchapter = utils::get_parent_chapter($this->section);
            $lastsection = get_user_preferences('format_mooin4_last_section_in_course_' . $course->id, 0, $USER->id);
            if ($continuesection = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $lastsection])) {
                $lastsectionsparentchapter = utils::get_parent_chapter($continuesection);
                if ($lastsectionsparentchapter == $this->parentchapter) {
                    $this->containsactivesection = true;
                }
            }
        }
    }
}
