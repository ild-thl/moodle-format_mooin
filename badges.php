<?php
require_once('../../../config.php');
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir.'/completionlib.php');

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
$PAGE->set_title("$course->shortname: " . get_string('participants'));
$PAGE->set_heading($course->fullname);

// $PAGE->set_pagetype('course-view-' . $course->format);
// $PAGE->add_body_class('path-user');                     // So we can style it independently.
$PAGE->set_other_editing_capability('moodle/course:manageactivities');
$PAGE->navbar->add(get_string('my_badges', 'format_mooin'));

// require_once('./locallib.php');

$PAGE->set_url('/course/format/mooin/badges.php', array('id' => $course->id));

echo $OUTPUT->header();
// echo $OUTPUT->heading(get_string('badges', 'format_mooin'));

$blockrecord = $DB->get_record('block_instances', array('blockname' => 'badges', 'parentcontextid' => $context->instanceid), '*', MUST_EXIST); // oc_mooc_nav || $context->id

// $blockinstance = block_instance('badges', $blockrecord); // oc_mooc_nav
// $total = $blockinstance->config->capira_questions;//0;
// $min_prozent = $blockinstance->config->capira_min;
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
                $user_grade = $grading_info->items[0]->grades[$USER->id]->grade;

                $hvp_percentage += $user_grade / $hvp_count;
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

// TODO Zertifikate

// TODO Highscore
//echo html_writer::tag('div', get_string('highscore', 'format_mooin'), array('class' => 'oc_badges_text'));
//echo html_writer::tag('div', get_string('in_course', 'format_mooin'), array('class' => 'oc_badges_text'));
//display_highscore($courseid);
//echo html_writer::tag('div', get_string('in_format_mooin', 'format_mooin'), array('class' => 'oc_badges_text'));


echo $OUTPUT->footer();

function print_badges($records, $details = false, $highlight = false, $badgename = false) {
    global $DB;
    $lis = '';
    foreach ($records as $record) {
        if ($record->type == 2) {
            $context = context_course::instance($record->courseid);
        } else {
            $context = context_system::instance();
        }
        $opacity = '';
        if ($highlight) {
            $opacity = ' opacity: 0.15;';
            if (isset($record->highlight)) {
                $opacity = ' opacity: 1.0;';
            }
        }
        $imageurl = moodle_url::make_pluginfile_url($context->id, 'badges', 'badgeimage', $record->id, '/', 'f1', false);
        $image = html_writer::empty_tag('img', array('src' => $imageurl, 'class' => 'badge-image', 'style' => 'width: 100px; height: 100px;' . $opacity));
        if (isset($record->uniquehash)) {
            $url = new moodle_url('/badges/badge.php', array('hash' => $record->uniquehash));
        } else {
            $url = new moodle_url('/badges/overview.php', array('id' => $record->id));
        }
        $detail = '';
        if ($details) {
            $user = $DB->get_record('user', array('id' => $record->userid));
            $detail = '<br />' . $user->firstname . ' ' . $user->lastname . '<br />(' . date('d.m.y H:i', $record->dateissued) . ')';
        } else if ($badgename) {
            $detail = '<br />' . $record->name;
        }
        $link = html_writer::link($url, $image . $detail, array('title' => $record->name));
        $lis .= html_writer::tag('li', $link);
    }
    echo html_writer::tag('ul', $lis, array('class' => 'badges'));
}
function get_badges($courseid = 0, $page = 0, $perpage = 0, $search = '') {
    global $DB;
    $params = array();
    $sql = 'SELECT
                b.*
            FROM
                {badge} b
            WHERE b.type > 0 
			  AND b.status != 4 ';

    if ($courseid == 0) {
        $sql .= ' AND b.type = :type';
        $params['type'] = 1;
    }

    if ($courseid != 0) {
        $sql .= ' AND b.courseid = :courseid';
        $params['courseid'] = $courseid;
    }

    if (!empty($search)) {
        $sql .= ' AND (' . $DB->sql_like('b.name', ':search', false) . ') ';
        $params['search'] = '%' . $DB->sql_like_escape($search) . '%';
    }

    $badges = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

    return $badges;
}

function get_badges_since($courseid, $since, $global = false) {
    global $DB, $USER;
    if (!$global) {
        $params = array();
        $sql = 'SELECT
					b.*,
					bi.id,
					bi.badgeid,
					bi.userid,
					bi.dateissued,
					bi.uniquehash
				FROM
					{badge} b,
					{badge_issued} bi
				WHERE b.id = bi.badgeid ';


        $sql .= ' AND b.courseid = :courseid';
        $params['courseid'] = $courseid;

        if ($since > 0) {
            $sql .= ' AND bi.dateissued > :since ';
            $since = time() - $since;
            $params['since'] = $since;
        }
        $sql .= ' ORDER BY bi.dateissued DESC ';
        $sql .= ' LIMIT 0, 20 ';
        $badges = $DB->get_records_sql($sql, $params);
    } else {
        $params = array('courseid' => $courseid);
        $sql = 'SELECT
					b.*,
					bi.id,
					bi.badgeid,
					bi.userid,
					bi.dateissued,
					bi.uniquehash
				FROM
					{badge} b,
					{badge_issued} bi,
					{user_enrolments} ue,
					{enrol} e
				WHERE b.id = bi.badgeid 
				AND	bi.userid = ue.userid 
				AND ue.enrolid = e.id 
				AND e.courseid = :courseid ';


        $sql .= ' AND b.type = :type';
        $params['type'] = 1;

        if ($since > 0) {
            $sql .= ' AND bi.dateissued > :since ';
            $since = time() - $since;
            $params['since'] = $since;
        }
        $sql .= ' ORDER BY bi.dateissued DESC ';
        $sql .= ' LIMIT 0, 20 ';
        $badges = $DB->get_records_sql($sql, $params);
    }

    $correct_badges = array();
    foreach ($badges as $badge) {
        $badge->id = $badge->badgeid;

        // nur wenn der Inhaber kein Teacher ist anzeigen
        $coursecontext = context_course::instance($courseid);
        $roles = get_user_roles($coursecontext, $badge->userid, false);
        $not_a_teacher = true;
        foreach ($roles as $role) {
            if ($role->shortname == 'editingteacher') {
                $not_a_teacher = false;
            }
        }
        if ($not_a_teacher) {
            $correct_badges[] = $badge;
        }
    }
    return $correct_badges;
}
function display_badges($userid = 0, $courseid = 0, $since = 0, $print = true) {
    global $CFG, $PAGE, $USER, $SITE;
    require_once($CFG->dirroot . '/badges/renderer.php');

    // Determine context.
    if (isloggedin()) {
        $context = context_user::instance($USER->id);
    } else {
        $context = context_system::instance();
    }

    if ($userid == 0) {
        if ($since == 0) {
            $records = get_badges($courseid, null, null, null);
        } else {
            $records = get_badges_since($courseid, $since, false);
            // globale Badges
            // if ($courseid != 0) {
            // $records = array_merge(get_badges_since($courseid, $since, true), $records);
            // }
        }
        $renderer = new core_badges_renderer($PAGE, '');

        // Print local badges.
        if ($records) {
            //$right = $renderer->print_badges_list($records, $userid, true);
            if ($since == 0) {
                print_badges($records);
            } else {
                print_badges($records, true);
            }
        }
    } elseif ($USER->id == $userid || has_capability('moodle/badges:viewotherbadges', $context)) {
        $records = badges_get_user_badges($userid, $courseid, null, null, null, true);
        $renderer = new core_badges_renderer($PAGE, '');

        // Print local badges.
        if ($records) {
            $right = $renderer->print_badges_list($records, $userid, true);
            if ($print) {
                echo html_writer::tag('dd', $right);
                //print_badges($records);
            } else {
                return html_writer::tag('dd', $right);
            }
        }
    }
}
function display_user_and_availbale_badges($userid, $courseid) {
    global $CFG, $USER;
    require_once($CFG->dirroot . '/badges/renderer.php');

    $coursebadges = get_badges($courseid, null, null, null);
    $userbadges = badges_get_user_badges($userid, $courseid, null, null, null, true);

    foreach ($userbadges as $ub) {
        if ($ub->status != 4) {
            $coursebadges[$ub->id]->highlight = true;
            $coursebadges[$ub->id]->uniquehash = $ub->uniquehash;
        }
    }

    print_badges($coursebadges, false, true, true);
}
