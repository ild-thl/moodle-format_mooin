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

        $certificates_number_mobile = 0;
        
        //for course_certificate get user_preference data
        $modulename = "coursecertificate";
        $awardedtoid = $USER->id;
        $issuedrecords = $DB->get_records('tool_certificate_issues', [
            'userid' => $USER->id,
            'courseid' => $course->id
        ], '', 'id');

        // Get Certificat number on moblie
        $issuedids = array_keys($issuedrecords);

        foreach ($issuedids as $issuedid) {
            $cert = get_user_preferences('format_mooin4_new_certificate_' . $modulename . '_' . $issuedid, 0, $awardedtoid);
            if ($cert == 1) {
                $certificates_number_mobile++;
            }
        }

        //for ilddigitalcert get user_preference data
        $modulename = "ilddigitalcert";
        $awardedtoid = $USER->id;
        $issuedrecords = $DB->get_records('ilddigitalcert_issued', [
            'userid' => $USER->id,
            'courseid' => $course->id
        ], '', 'id');

        // Get Certificat number on moblie
        $issuedids_ild = array_keys($issuedrecords);

        foreach ($issuedids_ild as $issuedid) {
            $cert = get_user_preferences('format_mooin4_new_certificate_' . $modulename . '_' . $issuedid, 0, $awardedtoid);
            if ($cert == 1) {
                $certificates_number_mobile++;
            }
        }

        //error_log('certificates_number_mobile: ' . $certificates_number_mobile);

        $new_cert = $certificates_number_mobile > 0;
        
        $data = (object)[
            'coursecertificates' => $certificates,
            'certificatesUrl' => new moodle_url('/course/format/mooin4/certificates.php', array('id' => $course->id)),
            'othercertificates' => $other_certificates,
            'new_cert' => $new_cert,
            'cert_number' => $certificates_number_mobile,
        ];
        return $data;
    }
}
