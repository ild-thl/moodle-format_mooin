<?php

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

    private $chapterlib;

    //private $continue_section;


    /**
     * Constructor.
     *
     * @param course $course the course
     */
    public function __construct(course_format $format) {
        $this->format = $format;
        //$this->chapterlib = new chapterlib();
        //$this->continue_section = $this->get_continue_section();
    }

    public function export_for_template(\renderer_base $output) {
        global $USER;
        $course = $this->format->get_course();
        $courseprogress = utils::get_course_progress($course->id, $USER->id);

        $data = (object)[
            'is_course_started' => utils::is_course_started($course),
            'continue_section' => utils::get_continue_section($course),
            'continue_url' => utils::get_continue_url($course),
            'courseprogress' => $courseprogress
        ];
        return $data;
    }

    // public function is_course_started() {
    //     global $DB;
    //     global $USER;
    //     $chapterlib = $this->chapterlib;
    //     $course = $this->format->get_course();
    //     $last_section = get_user_preferences('format_mooin4_last_section_in_course_' . $course->id, 0, $USER->id);
    //     if ($last_section) {
    //         return true;
    //     } else {
    //         return false;
    //     }
    // }

    // public function get_continue_section() {
    //     global $DB;
    //     global $USER;
    //     $chapterlib = $this->chapterlib;
    //     $course = $this->format->get_course();

    //     $last_section = get_user_preferences('format_mooin4_last_section_in_course_' . $course->id, 0, $USER->id);


    //     if ($last_section) {
    //         if ($last_section == 0 || $last_section == 1) {
    //             $last_section = 2;
    //         }

    //         if ($continuesection = $DB->get_record('course_sections', array('course' => $course->id, 'section' => $last_section))) {
    //             return $chapterlib->get_section_prefix($continuesection);
    //         } else {
    //             return false;
    //         }
    //     } else {
    //         return 2;
    //     }
    // }

    // public function get_continue_url() {
    //     global $DB;
    //     global $USER;
    //     $chapterlib = $this->chapterlib;
    //     $course = $this->format->get_course();

    //     $last_section = get_user_preferences('format_mooin4_last_section_in_course_' . $course->id, 0, $USER->id);


    //     if ($last_section) {
    //         if ($last_section == 0 || $last_section == 1) {
    //             //return new moodle_url('/course/view.php', array('id' => $course->id, 'section' => 1));
    //             $last_section = 2;
    //         }
    //         if ($continuesection = $DB->get_record('course_sections', array('course' => $course->id, 'section' => $last_section))) {
    //             return new moodle_url('/course/view.php', array('id' => $course->id, 'section' => $continuesection->section));
    //             //return $continuesection->section;
    //         } else {
    //             return new moodle_url('/course/view.php', array('id' => $course->id, 'section' => 2));
    //         }
    //     } else {
    //         //return new moodle_url('/course/view.php', array('id' => $course->id, 'section' => 1));
    //         return new moodle_url('/course/view.php', array('id' => $course->id, 'section' => 2));
    //     }
    // }
}
