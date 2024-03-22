<?php

namespace format_moointopics\output\courseformat\content\frontpage;

use renderable;
use core_courseformat\base as course_format;
use moodle_url;
use context_course;

/**
 * Base class to render the course news section.
 *
 * @package   format_moointopics
 * @copyright 2023 ISy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certificates implements renderable {

    /** @var course_format the course format class */
    private $format;


    public function __construct(course_format $format) {
        $this->format = $format;
    }

    public function export_for_template(\renderer_base $output) {
        global $DB, $USER;

        $course = $this->format->get_course();

       

        $data = (object)[
            
        ];
        return $data;
    }
}
