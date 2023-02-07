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
 * @package   mod_forum 
 * @copyright 2023 ISY
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once('../../../mod/forum/lib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once('../mooin/locallib.php');

$id = optional_param('id', 0, PARAM_INT);       // Course Module ID
$f = optional_param('f', 0, PARAM_INT);        // Forum ID
$mode = optional_param('mode', 0, PARAM_INT);     // Display mode (for single forum)
$showall = optional_param('showall', '', PARAM_INT); // show all discussions on one page
$changegroup = optional_param('group', -1, PARAM_INT);   // choose the current group
$page = optional_param('page', 0, PARAM_INT);     // which page to show
$search = optional_param('search', '', PARAM_CLEAN);// search string

global $USER, $DB, $COURSE;

/* if ($id) {
    if (!$cm = get_coursemodule_from_id('forum', 9)) {
        echo 'Cm';
        print_error('invalidcoursemodule');
    }
    if (!$course = $DB->get_record("course", array("id" => $id))) { // $cm->course
        echo 'Course';
        print_error('coursemisconf');
    }
    if (!$forum = $DB->get_record("forum", array("id" => $cm->instance))) {
        echo 'Forum';
        print_error('invalidforumid', 'forum');
    }
    if ($forum->type == 'single') {
        $PAGE->set_pagetype('mod-forum-discuss');
    }

    // move require_course_login here to use forced language for course
    // fix for MDL-6926
    require_course_login($course, true, $cm);
    $strforums = get_string("modulenameplural", "forum");
    $strforum = get_string("modulename", "forum");
} */

$courseid = optional_param('id', 0, PARAM_INT);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance(SITEID); // $course->id, MUST_EXIST

require_login($course);

$systemcontext = context_system::instance();

// $PAGE->set_pagelayout('incourse');
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_context(\context_course::instance($course->id));
// $PAGE->set_title("$course->shortname: " . get_string('my_badges', 'format_mooin'));
// $PAGE->set_heading($course->fullname);

// $PAGE->set_pagetype('course-view-' . $course->format);
// $PAGE->add_body_class('path-user');                     // So we can style it independently.
$PAGE->set_other_editing_capability('moodle/course:manageactivities');
$PAGE->set_url('/course/format/mooin/alle_forums.php', array('id' => $course->id));

echo $OUTPUT->header();



/* if (empty($cm->visible) and !has_capability('moodle/course:viewhiddenactivities', $context)) {
    notice(get_string("activityiscurrentlyhidden"));
}

if (!has_capability('mod/forum:viewdiscussion', $context)) {
    notice(get_string('noviewdiscussionspermission', 'forum'));
} */

$oc_m = $DB->get_record('modules', array('name' => 'forum'));

// $sql = 'SELECT * FROM mdl_forum WHERE course = :courseid AND type = :';
$sql = 'SELECT * FROM mdl_forum WHERE course = :cid AND type != :tid ORDER BY ID DESC';
$param = array('cid' =>$COURSE->id, 'tid' => 'news');
$oc_foren = $DB->get_records_sql($sql, $param);
$s = 'SELECT * FROM mdl_forum_discussions WHERE course = :cid ORDER BY ID DESC';
$p = array('cid' =>$course->id);
$oc_f= $DB->get_records_sql($s,$p);
// var_dump($oc_f);

$oc_showall = optional_param('showall', '', PARAM_RAW);
$oc_counter = 0;

    echo html_writer::start_div('mooin-md-container'); //open outer div

    echo navbar('All Forums');
    
    echo '<h2>' . get_string('all_forums', 'format_mooin') . '</h2>';
    echo '<br>';
    echo html_writer::start_div('border-card'); //open outer div

    
    $value = '1';
    if (count($oc_foren) >= 1) {
        
        foreach ($oc_foren as $key => $oc_forum) {
            $cm = get_coursemodule_from_instance('forum', $oc_forum->id, $course->id);
            
            if(is_object($cm)) {
                $forum = $DB->get_record("forum", array("id" => $cm->instance));

                // var_dump($forum);
                $forum->istracked = forum_tp_is_tracked($oc_forum);
                if ($forum->istracked) {
                    $forum->unreadpostscount = forum_tp_count_forum_unread_posts($cm, $course);
                }

                $oc_cm = $DB->get_record('course_modules', array('instance' => $oc_forum->id, 'course' => $course->id, 'module' => $oc_m->id), '*', $strictness=IGNORE_MISSING);
                
                $oc_link = html_writer::link(new moodle_url('/course/format/mooin/forums.php?f=' . $oc_forum->id .'&tab='.'1'), $oc_forum->name);
                if (intval($oc_cm->visible) === 1) {
                    $forum_element =  html_writer::div($value++  . ' ' .$oc_link, 'forum_title');
                    if ($forum->unreadpostscount >= 1) {
                        $forum_index = html_writer::div($key, 'forum_index');
                        $forum_unread = html_writer::div($forum->unreadpostscount, 'count-container inline-batch fw-700 mr-1');
                        echo html_writer::start_span('forum_elemts_in_list') . $forum_element . ' ' . $forum_unread. html_writer::end_span();
                    } else {
                        echo html_writer::start_span('forum_elemts_in_list') . $forum_element . html_writer::end_span();
                    }
                }
            }
            
        }
        if ($oc_counter > 1) {
            ob_end_flush();
            echo $OUTPUT->footer($course);
            exit;
        }
    }
    echo html_writer::end_div(); //close border-card div
    echo html_writer::end_div(); //close outer div
  
echo $OUTPUT->footer($course);
