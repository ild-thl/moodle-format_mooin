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

namespace format_mooin4\output\courseformat\content\frontpage;

use renderable;
use moodle_url;
use format_mooin4\local\utils as utils;
use core_courseformat\base as course_format;



/**
 * Base class to render the course frontpage courseprogress.
 *
 * @package   format_mooin4
 * @copyright 2023 ISy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class courseprogress implements renderable {

    /** @var course_format the course format class */
    private $format;

    /** @var chapterlib the chapter library */
    private $chapterlib;

    /**
     * Constructor.
     *
     * @param course_format $format the course format
     */
    public function __construct(course_format $format) {
        $this->format = $format;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output typically, the renderer that's calling this function
     * @return \stdClass data context for a mustache template
     */
    public function export_for_template(\renderer_base $output) {
        global $USER;
        $course = $this->format->get_course();
        $courseprogress = utils::get_course_progress($course->id, $USER->id);

        $data = (object)[
            'is_course_started' => utils::is_course_started($course),
            'continue_section' => utils::get_continue_section($course),
            'continue_url' => utils::get_continue_url($course),
            'courseprogress' => $courseprogress,
        ];
        return $data;
    }


}
