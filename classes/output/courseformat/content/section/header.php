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
 * @package   format_mooin4
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
 * @package   format_mooin4
 * @copyright 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class header extends header_base {

    /** @var object The chapter object. */
    protected $chapter;

    /**
     * Constructor.
     *
     * @param course_format $format The course format object.
     * @param section_info $section The section info object.
     * @param mixed $chapter The chapter information.
     */
    public function __construct(course_format $format, section_info $section, $chapter) {
        parent::__construct($format, $section);
        $this->chapter = $chapter;
    }

    /**
     * Get the template name to use for rendering.
     *
     * @param \renderer_base $renderer The renderer base.
     * @return string The template name.
     */
    public function get_template_name(\renderer_base $renderer): string {
        return 'format_mooin4/local/content/section/header';
    }

    /**
     * Export the data for the template.
     *
     * @param \renderer_base $output The renderer base.
     * @return stdClass The exported data.
     */
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

        $data->title = $output->section_title_without_link($section, $course);

        require_once(__DIR__ . '/../../../../../lib.php');
        $courseid = $course->id;
        if (get_toggle_section_number_visibility($courseid) === 1) {
            $data->sec_numb_visibility = true;
        } else {
            $data->sec_numb_visibility = false;
        }
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
            }
        }

        if ($section->section > $format->get_last_section_number()) {
            // Stealth sections (orphaned) has special title.
            $data->title = get_string('orphanedactivitiesinsectionno', '', $section->section);
        }

        if (!$section->visible) {
            $data->ishidden = true;
        }

        // Detect "not yet available" sections: visible=1 but availability conditions not met.
        if ($section->visible && !$section->uservisible) {
            $info = new \core_availability\info_section($section);
            $warnings = [];
            $isavailable = $info->is_available($warnings, false, $USER->id);
            if (!$isavailable) {
                $data->isnotyetavailable = true;
            }
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
