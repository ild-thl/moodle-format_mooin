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
 * Base class to render the course news section.
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
 * Renderable class for the course news section.
 */
class news_section implements renderable {

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
        global $DB;

        $course = $this->format->get_course();
        $newsforumurl = null;

        if ($forum = $DB->get_record('forum', ['course' => $course->id, 'type' => 'news'])) {
            if ($module = $DB->get_record('modules', ['name' => 'forum'])) {
                if ($cm = $DB->get_record('course_modules', ['module' => $module->id, 'instance' => $forum->id])) {
                    $newsforumurl = new moodle_url('/mod/forum/view.php', ['id' => $cm->id]);
                }
            }
        }

        $lastpost = utils::get_last_news($course->id, 'news');

        $data = (object) [
            'newsforumUrl' => $newsforumurl,
            'previewPost' => $lastpost,
            'unreadNewsNumber' => $lastpost['unread_news_number'] ?? 0,
        ];

        if (($lastpost['unread_news_number'] ?? 0) == 0) {
            $data->no_new_news = true;
        } else if (($lastpost['unread_news_number'] ?? 0) == 1) {
            $data->one_new_news = true;
        }

        $coursecontext = context_course::instance($course->id);
        if (has_capability('moodle/course:update', $coursecontext)) {
            $data->showGear = true;
        }

        return $data;
    }
}
