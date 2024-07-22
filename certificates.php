<?php
require_once('../../../config.php');
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir.'/completionlib.php');

use format_moointopics\local\utils as utils;



global $USER, $PAGE, $CFG, $DB;

$courseid = optional_param('id', 0, PARAM_INT);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance(SITEID); // $course->id, MUST_EXIST

require_login($course);

$systemcontext = context_system::instance();

$PAGE->set_course($course);
$PAGE->set_pagelayout('course');
$PAGE->set_context(\context_course::instance($course->id));
$PAGE->set_title("$course->shortname: " . get_string('my_certificate', 'format_moointopics'));
$PAGE->set_heading($course->fullname);
$PAGE->set_other_editing_capability('moodle/course:manageactivities');
$PAGE->set_url('/course/format/moointopics/certificates.php', array('id' => $course->id));

echo $OUTPUT->header();

$breadcrumb = utils::subpage_navbar();
$certificates = utils::show_certificat($course->id);

$data = [
    'breadcrumb' => $breadcrumb,
    'certificates' => $certificates
];

echo $OUTPUT->render_from_template('format_moointopics/local/content/subpages/certificates', $data);
echo $OUTPUT->footer();