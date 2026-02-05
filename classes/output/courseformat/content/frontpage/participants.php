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
 * Renderable class for the course participants section.
 *
 * @package   format_mooin4
 * @copyright 2023 ISy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mooin4\output\courseformat\content\frontpage;

use renderable;
use core_courseformat\base as course_format;
use moodle_url;
use format_mooin4\local\utils as utils;
use context_course;

/**
 * Renderable class for the course participants section.
 *
 * @package   format_mooin4
 * @copyright 2023 ISy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class participants implements renderable {

    /** @var course_format the course format class */
    private $format;

    /**
     * Constructor.
     *
     * @param course_format $format the course format class
     */
    public function __construct(course_format $format) {
        $this->format = $format;
    }

    /**
     * Export the data for the template.
     *
     * @param \renderer_base $output the renderer
     * @return \stdClass the data
     */
    public function export_for_template(\renderer_base $output) {
        $course = $this->format->get_course();
        $usercardlist = utils::get_user_in_course($course->id);

        $data = (object) [
            'participantsUrl' => new moodle_url('/course/format/mooin4/participants.php', ['id' => $course->id]),
            'userCardList' => $usercardlist,
        ];

        $coursecontext = context_course::instance($course->id);
        $data->has_capability_viewuser = has_capability('moodle/course:viewparticipants', $coursecontext);

        return $data;
    }
}
