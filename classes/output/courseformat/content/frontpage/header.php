<?php

namespace format_moointopics\output\courseformat\content\frontpage;

use renderable;
use format_moointopics\local\chapterlib;
use core_courseformat\base as course_format;
use format_moointopics;
use moodle_url;


/**
 * Base class to render the course frontpage header.
 *
 * @package   format_moointopics
 * @copyright 2023 ISy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class header implements renderable {

    /** @var course_format the course format class */
    private $format;

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
        $course = $this->format->get_course();

        $editheaderlink = new moodle_url('/course/format/moointopics/edit_header.php', array('course' => $course->id));

        $headerimageurl = chapterlib::get_headerimage_url($course->id, false);
        $headerimageURLMobile =  chapterlib::get_headerimage_url($course->id, true);

        $data = (object)[
            'headerimageURL' => $headerimageurl,
            'headerimageURLMobile' => $headerimageURLMobile,
            'editheaderlink' => $editheaderlink,
            'is_course_started' => chapterlib::is_course_started($course),
            'continue_section' => chapterlib::get_continue_section($course),
            'continue_url' => chapterlib::get_continue_url($course),
        ];

        return $data;
    }
}
