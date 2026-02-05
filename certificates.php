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

/**
 * Display certificates for the course.
 *
 * @package     format_mooin4
 * @copyright   2025 onwards ILD
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir.'/completionlib.php');

use format_mooin4\local\utils as utils;



global $USER, $PAGE, $CFG, $DB;

$courseid = optional_param('id', 0, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance(SITEID); // Note: $course->id, MUST_EXIST.

require_login($course);

$systemcontext = context_system::instance();

$PAGE->set_course($course);
$PAGE->set_pagelayout('course');
$PAGE->set_context(\context_course::instance($course->id));
$PAGE->set_title("$course->shortname: " . get_string('my_certificate', 'format_mooin4'));
$PAGE->set_heading($course->fullname);
$PAGE->set_other_editing_capability('moodle/course:manageactivities');
$PAGE->set_url('/course/format/mooin4/certificates.php', ['id' => $course->id]);

echo $OUTPUT->header();

$breadcrumb = utils::subpage_navbar();
$certificates = utils::show_certificat($course->id);

$data = [
    'breadcrumb' => $breadcrumb,
    'certificates' => $certificates,
];

echo $OUTPUT->render_from_template('format_mooin4/local/content/subpages/certificates', $data);
echo $OUTPUT->footer();
