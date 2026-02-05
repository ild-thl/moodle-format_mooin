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
use core_courseformat\base as course_format;
use moodle_url;
use format_mooin4\local\utils as utils;

/**
 * Base class to render the course news section.
 *
 * @package   format_mooin4
 * @copyright 2023 ISy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class discussions implements renderable {

    /** @var course_format the course format class */
    private $format;


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
        global $DB;

        $course = $this->format->get_course();

        if (utils::get_last_forum_discussion($course->id, 'news') != null) {
            $previewpost = utils::get_last_forum_discussion($course->id, 'news');
        }

        $data = (object)[
            'all_discussions_url' => new moodle_url('/course/format/mooin4/all_discussionforums.php', ['id' => $course->id]),
            'previewPost' => $previewpost,
            'unreadNewsNumber' => $previewpost['unread_news_number'],
        ];

        if ($previewpost['unread_news_number'] == 0) {
            $data->no_new_news = true;
        } else if ($previewpost['unread_news_number'] == 1) {
            $data->one_new_news = true;
        }

        return $data;
    }
}
