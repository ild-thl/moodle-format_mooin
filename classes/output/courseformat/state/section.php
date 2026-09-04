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

use core_courseformat\output\local\state\section as section_base;
use core_availability\info_section;
use core_courseformat\base as course_format;
use section_info;
use renderable;
use stdClass;
use context_course;
use renderer_base;
use format_mooin4\local\utils as utils;
use moodle_url;

/**
 * Contains the ajax update section structure.
 *
 * @package   format_mooin4
 * @copyright 2021 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section extends section_base {

    /** @var int|null $continuesection The section ID to continue from. */
    protected $continuesection;

    /**
     * Export this data so it can be used as state object in the course editor.
     *
     * @param renderer_base $output typically, the renderer that's calling this function
     * @return array data context for a mustache template
     */
    public function export_for_template(\renderer_base $output): stdClass {
        global $DB, $USER, $PAGE;
        $ischapter = false;
        $parentchapter = null;
        $course = $this->format->get_course();

        if ($chapter = $DB->get_record('format_mooin4_chapter', ['sectionid' => $this->section->id])) {
            $ischapter = $chapter->chapter;
        } else {
            $parentchapter = utils::get_parent_chapter($this->section);
        }
        $data = (object)parent::export_for_template($output);
        $data->ischapter = $ischapter;
        // Keep both key styles for compatibility across templates/JS state consumers.
        $data->isChapter = $ischapter;
        // Always provide a prefix key to keep frontend rendering stable.
        $data->prefix = '';
        if ($parentchapter) {
            $data->parentChapter = $parentchapter->chapter;
            $data->chapter = $parentchapter->chapter;
            $data->sectionid = $parentchapter->sectionid;

            if ($parentchapterassection = $DB->get_record('course_sections', ['id' => $parentchapter->sectionid])) {
                $data->innerchapternumber = $this->section->section - $parentchapterassection->section;
                $data->parentchapterid = $parentchapterassection->id;
            }
            // Show course index section prefix numbers according settings.
            require_once(__DIR__ . '/../../../../lib.php');
            $courseid = $course->id;
            if (get_toggle_section_number_visibility($courseid) === 1) {
                $data->sec_numb_visibility = true;
                $data->prefix = utils::get_section_prefix($this->section);
            } else {
                $data->sec_numb_visibility = false;
            }
        }
        // Set sec_numb_visibility for course index.
        require_once(__DIR__ . '/../../../../lib.php');
        $courseid = $course->id;
        if (get_toggle_section_number_visibility($courseid) === 1) {
            $data->sec_numb_visibility = true;
        } else {
            $data->sec_numb_visibility = false;
        }

        $sectionprogress = utils::get_section_progress($course->id, $this->section->id, $USER->id);
        $data->sectionprogress = $sectionprogress;
        if ($sectionprogress == 100) {
            $data->isCompleted = true;
        }

        if ($chapter = $DB->get_record('format_mooin4_chapter', ['sectionid' => $this->section->id])) {
            $lastsection = get_user_preferences('format_mooin4_last_section_in_course_' . $course->id, 0, $USER->id);
            if ($continuesection = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $lastsection])) {
                $lastsectionsparentchapter = utils::get_parent_chapter($continuesection);
                if ($lastsectionsparentchapter == $chapter) {
                    $data->containsActiveSection = true;
                }
            }
        }

        if (!$ischapter) {
            if (utils::is_first_section_of_chapter($this->section->id)) {
                $data->isfirstsectionofchapter = true;
                $data->isFirstSectionOfChapter = true;
            }
            if (utils::is_last_section_of_chapter($this->section->id)) {
                $data->islastsectionofchapter = true;
                $data->isLastSectionOfChapter = true;
                if (!get_user_preferences('format_mooin4_hide_modal_for_section_' . $this->section->id)) {
                    $data->showlastsectionmodal = true;
                }
            }

            $parentchapter = utils::get_parent_chapter($this->section);
            $lastsection = get_user_preferences('format_mooin4_last_section_in_course_' . $course->id, 0, $USER->id);
            if ($continuesection = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $lastsection])) {
                $lastsectionsparentchapter = utils::get_parent_chapter($continuesection);
                if ($lastsectionsparentchapter == $parentchapter) {
                    $data->containsActiveSection = true;
                }
            }

            if ($lastsection == $this->section->section) {
                $data->isActiveSection = true;
            }
        }

        // Detect "not yet available" sections: visible=1 but availability conditions not met.
        // These should show a "Noch nicht verfügbar" badge in the course index so learners
        // understand why the course progress is not at 100%.
        if ($this->section->visible && !$this->section->uservisible) {
            $info = new info_section($this->section);
            $warnings = [];
            $isavailable = $info->is_available($warnings, false, $USER->id);
            if (!$isavailable) {
                $data->isnotyetavailable = true;
            }
        }

        return $data;
    }
}
