<?php

namespace format_moointopics\output\courseformat\content\frontpage;

use renderable;
use format_moointopics\local\chapterlib;
use core_courseformat\base as course_format;
use format_moointopics;


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

    public function export_for_template(\renderer_base $output)
    {
        $course = $this->format->get_course();

        $headerimageurl = "http://localhost:8888/moodle401/theme/image.php?theme=mooin4&component=theme&image=.%2Fheader_placeholder_desktop";

        $data = (object)[
            //'headerimageURL' => $headerimageurl,
            'is_course_started' => chapterlib::is_course_started($course),
            'continue_section' => chapterlib::get_continue_section($course),
            'continue_url' => chapterlib::get_continue_url($course),
        ];

        return $data;
    }
}
