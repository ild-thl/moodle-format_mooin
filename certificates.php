<?php
require_once('../../../config.php');
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir.'/completionlib.php');
require_once('locallib.php');
// require_once('../../../mod/ilddigitalcert/overview.php');

global $USER, $PAGE, $CFG, $DB;

// $courseid = optional_param('courseid', 1, PARAM_INT);
$courseid     = optional_param('id', 0, PARAM_INT);
$id = optional_param('id', null, PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance(SITEID); // $course->id, MUST_EXIST



require_login($course);

$systemcontext = context_system::instance();

// $PAGE->set_pagelayout('incourse');
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_context(\context_course::instance($course->id));
$PAGE->set_title("$course->shortname: " . get_string('my_certificate', 'format_mooin'));
$PAGE->set_heading($course->fullname);

// $PAGE->set_pagetype('course-view-' . $course->format);
// $PAGE->add_body_class('path-user');                     // So we can style it independently.
$PAGE->set_other_editing_capability('moodle/course:manageactivities');
$PAGE->navbar->add(get_string('my_certificate', 'format_mooin'));

// require_once('./locallib.php');

$PAGE->set_url('/course/format/mooin/certificates.php', array('id' => $course->id));

echo $OUTPUT->header();

echo $OUTPUT->navbar();
$val = false;
echo '<br />';
echo '<br />';
echo html_writer::tag('h2', html_writer::tag('div', get_string('my_certificate', 'format_mooin'), array('class' => 'oc_badges_text')));

echo html_writer::tag('div', get_string('certificate_overview_description', 'format_mooin'));
echo '<br />';
$templatedata = get_certificate($course->id);
// echo gettype($templatedata);
/* if ($cm_id != 0) {

    $templatedata['certificate_name'] = 'Certificate'; // $moduleinstance->name;
    $templatedata['preview_url'] = (
        new moodle_url(
            '/mod/ilddigitalcert/view.php',
            array("id" => $cm_id, 'view' => "preview")
        )
    )->out(false);
    $templatedata['course_name'] = $course->fullname;
} else {
    if ($hascapviewall) {
        echo $OUTPUT->heading(get_string('overview_certifier', 'mod_ilddigitalcert'));
    } else {
        echo $OUTPUT->heading(get_string('overview', 'mod_ilddigitalcert'));
    }
} */
if (gettype($templatedata) == 'string') {
    $val = false;
} else {
    $val = true;
}
$templatedatas = (object)[
    'data' => $templatedata,
    'value' => $val
];

echo $OUTPUT->render_from_template('format_mooin/certificat_overview', $templatedatas);

echo $OUTPUT->footer();