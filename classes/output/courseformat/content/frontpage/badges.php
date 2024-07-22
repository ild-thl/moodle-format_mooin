<?php

namespace format_moointopics\output\courseformat\content\frontpage;

use renderable;
use core_courseformat\base as course_format;
use moodle_url;
use context_course;
use format_moointopics\local\utils as utils;

/**
 * Base class to render the course news section.
 *
 * @package   format_moointopics
 * @copyright 2023 ISy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class badges implements renderable {

    /** @var course_format the course format class */
    private $format;


    public function __construct(course_format $format) {
        $this->format = $format;
    }

    public function export_for_template(\renderer_base $output) {
        global $DB, $USER;

        $course = $this->format->get_course();

        $badges = null;
        ob_start();
        $badges .= utils::get_user_and_availbale_badges($USER->id, $course->id);
        $badges .= ob_get_contents();
        ob_end_clean();

        if(count(utils::get_badge_records($course->id, null, null, null))  > 3) {
            $other_badges = count(utils::get_badge_records($course->id, null, null, null)) - 3;
        } else {
            $other_badges = false;
        }

        $data = (object)[
            'badgesList' => $badges,
            'otherBadges' => $other_badges,
            'badgesUrl' => new moodle_url('/course/format/moointopics/badges.php', array('id' => $course->id)),
        ];

        $coursecontext = context_course::instance($course->id);
        if (has_capability('moodle/course:update', $coursecontext)) {
            $manage_badges_url = new moodle_url('/badges/view.php', array('type' => '2', 'id' => $course->id));
            $data->manage_badges_url = $manage_badges_url;
        }
        return $data;
    }
}
