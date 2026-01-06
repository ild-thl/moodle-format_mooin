<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

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


    /**
     * Constructor.
     *
     * @param course_format $format the course format
     */
    public function __construct(course_format $format) {
        $this->format = $format;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output typically, the renderer that's calling this function
     * @return \stdClass data context for a mustache template
     */
    public function export_for_template(\renderer_base $output) {
        global $DB, $USER;

        $course = $this->format->get_course();
        $certificates = utils::show_certificat($course->id);
        $coursecertificates = utils::get_course_certificates($course->id, $USER->id);
        $certcount = count($coursecertificates);
        if ($certcount > 3) {
            $othercertificates = $certcount - 3;
        } else {
            $othercertificates = false;
        }

        $certificatesnumbermobile = 0;

        // For course_certificate get user_preference data.
        $modulename = "coursecertificate";
        $awardedtoid = $USER->id;

        $dbman = $DB->get_manager();

        if ($dbman->table_exists('tool_certificate_issues')) {

            $issuedrecords = $DB->get_records('tool_certificate_issues', [
                'userid' => $USER->id,
                'courseid' => $course->id,
            ], '', 'id');
        } else {
            // Tabelle existiert nicht, ggf. Fehlerbehandlung.
            $issuedrecords = [];
        }

        // Get Certificat number on moblie.
        $issuedids = array_keys($issuedrecords);

        foreach ($issuedids as $issuedid) {
            $cert = get_user_preferences('format_mooin4_new_certificate_' . $modulename . '_' . $issuedid, 0, $awardedtoid);
            if ($cert == 1) {
                $certificatesnumbermobile++;
            }
        }

        // For ilddigitalcert get user_preference data.
        $modulename = "ilddigitalcert";
        $awardedtoid = $USER->id;

        if ($dbman->table_exists('ilddigitalcert_issued')) {

            $issuedrecords = $DB->get_records('ilddigitalcert_issued', [
                'userid' => $USER->id,
                'courseid' => $course->id,
            ], '', 'id');
        } else {
            // Tabelle existiert nicht, ggf. Fehlerbehandlung.
            $issuedrecords = [];
        }
        // Get Certificat number on moblie.
        $issuedidsild = array_keys($issuedrecords);

        foreach ($issuedidsild as $issuedid) {
            $cert = get_user_preferences('format_mooin4_new_certificate_' . $modulename . '_' . $issuedid, 0, $awardedtoid);
            if ($cert == 1) {
                $certificatesnumbermobile++;
            }
        }

        $newcert = $certificatesnumbermobile > 0;

        $data = (object)[
            'coursecertificates' => $certificates,
            'certificatesUrl' => new moodle_url('/course/format/mooin4/certificates.php', ['id' => $course->id]),
            'othercertificates' => $othercertificates,
            'new_cert' => $newcert,
            'cert_number' => $certificatesnumbermobile,
        ];
        return $data;
    }
}
