<?php

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


    public function __construct(course_format $format) {
        $this->format = $format;
    }

    public function export_for_template(\renderer_base $output) {
        global $DB;

        $course = $this->format->get_course();

        if (utils::get_last_forum_discussion($course->id, 'news') != null) {
            $previewPost = utils::get_last_forum_discussion($course->id, 'news');
        }
        

        $data = (object)[
            'all_discussions_url' => new moodle_url('/course/format/mooin4/all_discussionforums.php', array('id' => $course->id)),
            'previewPost' => $previewPost,
            'unreadNewsNumber' => $previewPost['unread_news_number'],
        ];

        if ($previewPost['unread_news_number'] == 0) {
            $data->no_new_news = true;
        } else if ($previewPost['unread_news_number'] == 1) {
            $data->one_new_news = true;
        }
        

        return $data;
    }
}
