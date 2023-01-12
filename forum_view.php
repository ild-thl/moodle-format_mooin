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
 * @package   mod_occapira
 * @category  grade
 * @copyright 2015 oncampus
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

// oncampus
$page = -1;

$params = array();
if ($id) {
    $params['id'] = $id;
} else {
    $params['f'] = $f;
}
if ($page) {
    $params['page'] = $page;
}
if ($search) {
    $params['search'] = $search;
}
$PAGE->set_url('/course/format/mooin/forum_view.php', $params);
// removed by oncampus $PAGE->set_url('/mod/forum/view.php', $params);

if ($id) {
    if (!$cm = get_coursemodule_from_id('forum', $id)) {
        print_error('invalidcoursemodule');
    }
    if (!$course = $DB->get_record("course", array("id" => $cm->course))) {
        print_error('coursemisconf');
    }
    if (!$forum = $DB->get_record("forum", array("id" => $cm->instance))) {
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
} else if ($f) {

    if (!$forum = $DB->get_record("forum", array("id" => $f))) {
        print_error('invalidforumid', 'forum');
    }
    if (!$course = $DB->get_record("course", array("id" => $forum->course))) {
        print_error('coursemisconf');
    }

    if (!$cm = get_coursemodule_from_instance("forum", $forum->id, $course->id)) {
        print_error('missingparameter');
    }
    // move require_course_login here to use forced language for course
    // fix for MDL-6926
    require_course_login($course, true, $cm);
    $strforums = get_string("modulenameplural", "forum");
    $strforum = get_string("modulename", "forum");
} else {
    print_error('missingparameter');
}

if (!$PAGE->button) {
    $PAGE->set_button(forum_search_form($course, $search));
}

$context = context_module::instance($cm->id);
$PAGE->set_context($context);

if (!empty($CFG->enablerssfeeds) && !empty($CFG->forum_enablerssfeeds) && $forum->rsstype && $forum->rssarticles) {
    require_once("$CFG->libdir/rsslib.php");

    $rsstitle = format_string($course->shortname, true, array('context' => context_course::instance($course->id))) . ': ' . format_string($forum->name);
    rss_add_http_header($context, 'mod_forum', $forum, $rsstitle);
}

// Mark viewed if required
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

/// Print header.

$PAGE->set_title($forum->name);
$PAGE->add_body_class('forumtype-' . $forum->type);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

// Print Navbar in layout
echo navbar(get_string('discussions', 'format_mooin'));
/// Some capability checks.
if (empty($cm->visible) and !has_capability('moodle/course:viewhiddenactivities', $context)) {
    notice(get_string("activityiscurrentlyhidden"));
}

if (!has_capability('mod/forum:viewdiscussion', $context)) {
    notice(get_string('noviewdiscussionspermission', 'forum'));
}


//////////// oncampus ////////////////////////////////
// Wenn mehrere Foren (Newsforum z�hlt nicht) vohanden sind,
// wird hier nur eine Liste mit Links angezeigt

global $USER, $DB;

//if ($USER->username == 'riegerj' or $USER->username == 'rieger') {
//echo '*';
$oc_m = $DB->get_record('modules', array('name' => 'forum'));
// $sql = 'SELECT * FROM mdl_forum WHERE course = :courseid AND type = :';
$sql = 'SELECT * FROM mdl_forum WHERE course = :cid AND type != :tid ORDER BY ID DESC';
$param = array('cid' =>$course->id, 'tid' => 'news');
$oc_foren = $DB->get_records_sql($sql, $param);
$s = 'SELECT * FROM mdl_forum_discussions WHERE course = :cid ORDER BY ID DESC';
$p = array('cid' =>$course->id);
$oc_f= $DB->get_records_sql($s,$p);
$oc_showall = optional_param('showall', '', PARAM_RAW);
$oc_counter = 0;

// ob_start();
//if (count($oc_foren) > 1 || count($oc_f) >1 ) { and $oc_showall == ''
    echo html_writer::start_div('mooin-md-container'); //open outer div
    echo '<h2>' . get_string('all_forums', 'format_mooin') . '</h2>';
    echo '<br>';
    echo html_writer::start_div('border-card'); //open outer div

    
    $value = '1';
    if (count($oc_foren) >= 1) {
        
        foreach ($oc_foren as $oc_forum) {
            $cm = get_coursemodule_from_instance('forum', $oc_forum->id, $course->id);
            
            $forum->istracked = forum_tp_is_tracked($oc_forum);
            if ($forum->istracked) {
                $forum->unreadpostscount = forum_tp_count_forum_unread_posts($cm, $course);
            }

            $oc_cm = $DB->get_record('course_modules', array('instance' => $oc_forum->id, 'course' => $course->id, 'module' => $oc_m->id));

            // blocks/oc_mooc_nav/forum_view.php?showall=false&
            $oc_link = html_writer::link(new moodle_url('/course/format/mooin/forums.php?f=' . $oc_forum->id .'&tab='.'1'), $oc_forum->name); // /mod/forum/view.php
            if ($oc_cm->visible == 1) {
                $forum_element =  html_writer::div($oc_link, 'forum_title');
                if ($forum->unreadpostscount >= 1) {
                    $forum_unread = html_writer::div($forum->unreadpostscount, 'count-container inline-batch fw-700 mr-1');
                    echo html_writer::start_span('forum_elemts_in_list') . $forum_element . ' ' . $forum_unread. html_writer::end_span();
                } else {
                    echo html_writer::start_span('forum_elemts_in_list') . $forum_element . html_writer::end_span();
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
   /*  if (count($oc_f) > 1 ) {
       foreach ($oc_f as $value) {
        foreach ($oc_foren as $oc_forum) {
            if ($value->forum == $oc_forum->id) {
                $oc_cm = $DB->get_record('course_modules', array('instance' => $value->id, 'course' => $course->id, 'module' => $oc_m->id));
                //var_dump($oc_m->visible);
                $oc_link = html_writer::link(new moodle_url('/mod/forum/discuss.php?d=' . $value->id), $value->name);
                //if ($oc_cm->visible == '1') {
                    echo html_writer::div($oc_link, 'all_forum_list');
                    //$oc_counter++;
                //}
            }
        }
       }
       if ($oc_counter > 1) {
            ob_end_flush();
            echo $OUTPUT->footer($course);
            exit;
        }
    } */

// }
// ob_end_clean();
//}

///////////////////////////////////////////////////////

// echo $OUTPUT->heading(format_string($forum->name), 2);


// Add the subscription toggle JS.
// $PAGE->requires->yui_module('moodle-mod_forum-subscriptiontoggle', 'Y.M.mod_forum.subscriptiontoggle.init');

echo $OUTPUT->footer($course);
