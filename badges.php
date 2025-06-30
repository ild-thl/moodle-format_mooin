<?php
require_once('../../../config.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/completionlib.php');

use format_mooin4\local\utils as utils;
global $USER, $PAGE, $CFG, $DB;

$courseid = optional_param('id', 0, PARAM_INT);
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance(SITEID);

require_login($course);

$systemcontext = context_system::instance();

$PAGE->set_course($course);
$PAGE->set_pagelayout('course');
$PAGE->set_context(context_course::instance($course->id));
$PAGE->set_title("$course->shortname: " . get_string('my_badges', 'format_mooin4'));
$PAGE->set_heading($course->fullname);
$PAGE->set_other_editing_capability('moodle/course:manageactivities');
$PAGE->set_url('/course/format/mooin4/badges.php', ['id' => $course->id]);

echo $OUTPUT->header();

$breadcrumb = utils::subpage_navbar();

$badgeinfo = utils::get_user_badge_progress($USER->id, $courseid);
$badgelist = isset($badgeinfo['badges']) && is_array($badgeinfo['badges']) ? $badgeinfo['badges'] : [];
$badgerows = array_chunk($badgelist, 2);

$data = [
    'breadcrumb' => $breadcrumb,
    'badgerows' => $badgerows,
    'badges' => $badgeinfo['badges'],      
    'userBadges' => new moodle_url('/user/profile.php', ['id' => $USER->id]),
    'badgeOptions' => new moodle_url('/badges/mybackpack.php')
];

echo $OUTPUT->render_from_template('format_mooin4/local/content/subpages/badges', $data);

echo $OUTPUT->footer();
