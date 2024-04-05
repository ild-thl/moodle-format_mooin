<?php

namespace format_moointopics\output\courseformat\content\frontpage;

use renderable;
use core_courseformat\base as course_format;
use moodle_url;
use format_moointopics\local\forumlib;

/**
 * Base class to render the course news section.
 *
 * @package   format_moointopics
 * @copyright 2023 ISy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class discussions implements renderable {

    /** @var course_format the course format class */
    private $format;


    public function __construct(course_format $format) {
        $this->format = $format;
    }

    public function export_for_template(\renderer_base $output) {
        global $DB;

        $course = $this->format->get_course();

        if (forumlib::get_last_forum_discussion($course->id, 'news') != null) {
            $previewPost = forumlib::get_last_forum_discussion($course->id, 'news');
        }
        

        $data = (object)[
            'all_discussions_url' => new moodle_url('/course/format/moointopics/all_discussionforums.php', array('id' => $course->id)),
            'previewPost' => $previewPost,
        ];

        

        return $data;
    }
}
