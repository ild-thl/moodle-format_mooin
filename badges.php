<?php
require_once('../../../config.php');
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir.'/completionlib.php');

use format_mooin4\local\utils as utils;



global $USER, $PAGE, $CFG, $DB;

$courseid = optional_param('id', 0, PARAM_INT);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance(SITEID); // $course->id, MUST_EXIST

require_login($course);

$systemcontext = context_system::instance();

$PAGE->set_course($course);
$PAGE->set_pagelayout('course');
$PAGE->set_context(\context_course::instance($course->id));
$PAGE->set_title("$course->shortname: " . get_string('my_badges', 'format_mooin4'));
$PAGE->set_heading($course->fullname);
$PAGE->set_other_editing_capability('moodle/course:manageactivities');
$PAGE->set_url('/course/format/mooin4/badges.php', array('id' => $course->id));

echo $OUTPUT->header();

$breadcrumb = utils::subpage_navbar();

ob_start();
utils::get_user_and_availbale_badges($USER->id, $courseid);
$badges = ob_get_contents();
ob_end_clean();

$data = [
    'breadcrumb' => $breadcrumb,
    'badges' => $badges,
    'userBadges' => new moodle_url('/user/profile.php', array('id' => $USER->id)),
    'badgeOptions' => new moodle_url('/badges/mybackpack.php')
];

echo $OUTPUT->render_from_template('format_mooin4/local/content/subpages/badges', $data);
echo $OUTPUT->footer();