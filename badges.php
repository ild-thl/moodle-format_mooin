<?php
require_once('../../../config.php');
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir.'/completionlib.php');
require_once('locallib.php');


global $USER, $PAGE, $CFG, $DB;

// $courseid = optional_param('courseid', 1, PARAM_INT);
$courseid     = optional_param('id', 0, PARAM_INT);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance(SITEID); // $course->id, MUST_EXIST

require_login($course);

$systemcontext = context_system::instance();

// $PAGE->set_pagelayout('incourse');
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_context(\context_course::instance($course->id));
$PAGE->set_title("$course->shortname: " . get_string('my_badges', 'format_mooin'));
$PAGE->set_heading($course->fullname);

// $PAGE->set_pagetype('course-view-' . $course->format);
// $PAGE->add_body_class('path-user');                     // So we can style it independently.
$PAGE->set_other_editing_capability('moodle/course:manageactivities');
$PAGE->navbar->add(get_string('my_badges', 'format_mooin'));

// require_once('./locallib.php');

$PAGE->set_url('/course/format/mooin/badges.php', array('id' => $course->id));

echo $OUTPUT->header();

echo $OUTPUT->navbar();

$blockrecord = $DB->get_record('block_instances', array('blockname' => 'badges', 'parentcontextid' => $context->instanceid), '*', MUST_EXIST); // oc_mooc_nav || $context->id

// $blockinstance = block_instance('badges', $blockrecord); // oc_mooc_nav
// $total = $blockinstance->config->capira_questions;//0;
// $min_prozent = $blockinstance->config->capira_min;
// var_dump($blockrecord);
$cert_m = $DB->get_record('modules', array('name' => 'simplecertificate'));

if ($cert_m) {
    if ($min_prozent > 0 and $cert_cm = $DB->get_record('course_modules', array('module' => $cert_m->id, 'course' => $courseid, 'visible' => 1))) {
        if (has_capability('mod/simplecertificate:addinstance', $context)) {
            $simple_cert = $DB->get_record('simplecertificate', array('course' => $courseid, 'id' => $cert_cm->instance));
            $cert_issues = $DB->get_records('simplecertificate_issues', array('certificateid' => $simple_cert->id));
            echo 'Anzahl ausgestellter Zertifikate (' . get_string('only_for_trainers', 'format_mooin') . '): ' . count($cert_issues);
        }
    }
}

// echo html_writer::tag('h2', html_writer::tag('div', get_string('certificate', 'format_mooin'), array('class' => 'oc_badges_text')));
// echo html_writer::tag('div', html_writer::tag('div', get_string('cert_addtext', 'format_mooin'), array('class' => 'oc_badges_text')));

if ($cert_m) {
    if ($min_prozent > 0 and $cert_cm = $DB->get_record('course_modules', array('module' => $cert_m->id, 'course' => $courseid, 'visible' => 1))) {
        $percentage = 0;
        $mod_count = 0;

        /* hvp start */
        require_once($CFG->libdir . '/gradelib.php');
        $hvp_percentage = 0;
        $hvp_module = $DB->get_record('modules', array('name' => 'hvp'));
        $cm = $DB->get_records('course_modules', array('course' => $courseid, 'module' => $hvp_module->id, 'completion' => 2, 'visible' => 1));
        $hvp_count = count($cm);

        if ($hvp_count != 0) {
            foreach ($cm as $module) {
                $grading_info = grade_get_grades($module->course, 'mod', 'hvp', $module->instance, $USER->id);
                $val = (object)$grading_info;
                $user_grade = $val->items[0]->grades[$USER->id]->grade;

                $hvp_percentage += (int)$user_grade / $hvp_count;
            }

            $percentage = $hvp_percentage;
            $mod_count++;
        }
        /* hvp end */

        $percentage = $percentage / $mod_count;

        if ($percentage >= $min_prozent) {
            // zertifikat anzeigen
            $module_context = context_module::instance($cert_cm->id);
            require_capability('mod/simplecertificate:view', $module_context);

            $url = new moodle_url('/mod/simplecertificate/view.php', array(
                'id' => $cert_cm->id,
                'tab' => 0,
                'page' => 0,
                'perpage' => 30,
            ));
            $canmanage = 0;//has_capability('mod/simplecertificate:manage', $module_context);

            $link = new moodle_url('/mod/simplecertificate/view.php', array('id' => $cert_cm->id, 'action' => 'get'));
            $button = new single_button($link, get_string('certificate', 'format_mooin'));
            $button->add_action(
                new popup_action('click', $link, 'view' . $cert_cm->id,
                    array('height' => 600, 'width' => 800)));
            #echo html_writer::tag('h2', html_writer::tag('div', get_string('certificate', 'format_mooin'), array('class' => 'oc_badges_text')));
            echo html_writer::tag('div', get_string('cert_descr', 'format_mooin', $min_prozent));
            echo html_writer::tag('div', $OUTPUT->render($button), array('style' => 'text-align:left'));
        }
    }
}
echo '<br />';
echo '<br />';
echo html_writer::start_div('mooin-md-container'); //open outer div
echo html_writer::tag('h2', html_writer::tag('div', get_string('course_badges', 'format_mooin'), array('class' => 'oc_badges_text')));

echo html_writer::tag('div', get_string('badge_overview_description', 'format_mooin'));
echo '<br />';
echo '<div>' . html_writer::link(new moodle_url('/user/profile.php', array('id' => $USER->id)), get_string('profile_badges', 'format_mooin')) . '<br />';
echo html_writer::link(new moodle_url('/badges/mybackpack.php'), get_string('badge_options', 'format_mooin')) . '</div><br />';

// Eigene, in diesem Kurs erworbene Badges

$out = html_writer::tag('div', get_string('overview', 'format_mooin'), array('class' => 'oc_badges_text'));
echo html_writer::tag('h2', $out);
//display_badges($USER->id, $courseid);
ob_start();
display_user_and_availbale_badges($USER->id, $courseid);
$out = ob_get_contents();
ob_end_clean();
if ($out != '<ul class="badges"></ul>') {
    echo $out;
} else {
    echo html_writer::tag('div', get_string('no_badges_available', 'format_mooin'), array('class' => 'oc-no-badges'));
}

// Badges, die man erreichen kann (in diesem Kurs und Plattformbadges)
// echo html_writer::tag('div', get_string('available_badges', 'format_mooin'), array('class' => 'oc_badges_text'));
// echo html_writer::tag('div', get_string('in_course', 'format_mooin'), array('class' => 'oc_badges_text'));
// display_badges(0, $courseid);
// echo html_writer::tag('div', get_string('in_format_mooin', 'format_mooin'), array('class' => 'oc_badges_text'));
// display_badges(0, 0);

// in den letzten 24h/7d an Teilnehmer diesen Kurses verliehene Badges
$out = html_writer::tag('div', get_string('awarded_badges', 'format_mooin'), array('class' => 'oc_badges_text'));
echo html_writer::tag('h2', $out);
// echo html_writer::tag('div', get_string('lastday', 'format_mooin'), array('class' => 'oc_badges_text'));
// display_badges(0, $courseid, 24 * 60 * 60);
//echo html_writer::tag('div', get_string('lastweek', 'format_mooin'), array('class' => 'oc_badges_text'));
ob_start();
display_badges(0, $courseid, 12 * 31 * 7 * 24 * 60 * 60);
$out = ob_get_contents();
ob_end_clean();
if ($out != '') {
    echo $out;
} else {
    echo html_writer::tag('div', get_string('no_badges_awarded', 'format_mooin'), array('class' => 'oc-no-badges'));
}

echo html_writer::end_div(); //close outer div
// TODO Zertifikate

// TODO Highscore
//echo html_writer::tag('div', get_string('highscore', 'format_mooin'), array('class' => 'oc_badges_text'));
//echo html_writer::tag('div', get_string('in_course', 'format_mooin'), array('class' => 'oc_badges_text'));
//display_highscore($courseid);
//echo html_writer::tag('div', get_string('in_format_mooin', 'format_mooin'), array('class' => 'oc_badges_text'));


echo $OUTPUT->footer();