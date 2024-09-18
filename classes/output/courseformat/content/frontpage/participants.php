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
class participants implements renderable {

    /** @var course_format the course format class */
    private $format;


    public function __construct(course_format $format) {
        $this->format = $format;
    }

    public function export_for_template(\renderer_base $output) {
        global $DB;

        $course = $this->format->get_course();
        
        if (utils::get_user_in_course($course->id) != null) {
            $user_card_list = utils::get_user_in_course($course->id);
        }

        $data = (object)[
            'participantsUrl' => new moodle_url('/course/format/mooin4/participants.php', array('id' => $course->id)),
            'userCardList' => $user_card_list,
        ];

        

        return $data;
    }
}
