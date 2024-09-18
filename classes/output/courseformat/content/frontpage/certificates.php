<?php

namespace format_mooin4\output\courseformat\content\frontpage;

use renderable;
use core_courseformat\base as course_format;
use moodle_url;
use context_course;
use format_mooin4\local\utils as utils;

/**
 * Base class to render the course certificates section.
 *
 * @package   format_mooin4
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
        $certificates = utils::show_certificat($course->id);
        $course_certificates = utils::get_course_certificates($course->id, $USER->id);
        $cert_count = count($course_certificates);
        if ($cert_count > 3) {
            $other_certificates = $cert_count - 3;
        } else {
            $other_certificates = false;
        }



        $data = (object)[
            'coursecertificates' => $certificates,
            'certificatesUrl' => new moodle_url('/course/format/mooin4/certificates.php', array('id' => $course->id)),
            'othercertificates' => $other_certificates
        ];
        return $data;
    }
}
