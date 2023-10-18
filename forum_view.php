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
 require_once('../mooin4/locallib.php');
 
 $id = optional_param('id', 0, PARAM_INT);       // Course Module ID
 $f = optional_param('f', 0, PARAM_INT);        // Forum ID
 $mode = optional_param('mode', 0, PARAM_INT);     // Display mode (for single forum)
 $showall = optional_param('showall', '', PARAM_INT); // show all discussions on one page
 $changegroup = optional_param('group', -1, PARAM_INT);   // choose the current group
 $page = optional_param('page', 0, PARAM_INT);     // which page to show
 $search = optional_param('search', '', PARAM_CLEAN);// search string
 $PAGE->set_pagelayout('incourse');
 
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
 $PAGE->set_url('/course/format/mooin4/forum_view.php', $params);
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
echo('Test Page Forum');
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

//echo navbar('Alle Forums');
/// Some capability checks.
if (empty($cm->visible) and !has_capability('moodle/course:viewhiddenactivities', $context)) {
    notice(get_string("activityiscurrentlyhidden"));
}

if (!has_capability('mod/forum:viewdiscussion', $context)) {
    //'mod/forum:viewdiscussion
    notice(get_string('noviewdiscussionspermission', 'forum'));
}


//////////// oncampus ////////////////////////////////
// Wenn mehrere Foren (Newsforum z�hlt nicht) vohanden sind,
// wird hier nur eine Liste mit Links angezeigt

global $USER, $DB;


echo $OUTPUT->heading(format_string($forum->name), 2);
if (!empty($forum->intro) && $forum->type != 'single' && $forum->type != 'teacher') {
    echo $OUTPUT->box(format_module_intro('forum', $forum, $cm->id), 'generalbox', 'intro');
}

// oncampus Link: Meine Beitr�ge und Suche//////////////////////////////////////////////////////////////////////////////////////////
// https://mooin.oncampus.de/mod/forum/user.php?id=4&course=2


/* $mythreads_url = new moodle_url('/mod/forum/user.php', array('id' => $USER->id, 'course' => $course->id));
$advancedsearch_url = new moodle_url('/mod/forum/search.php', array('id' => $course->id));

$strsearch = get_string('search');
$strgo = get_string('go');

$searchform = '<div class="searchform">' . $strsearch;
$searchform .= '<form action="' . $CFG->wwwroot . '/mod/forum/search.php" style="display:inline"><fieldset class="invisiblefieldset">';
$searchform .= '<legend class="accesshide">' . $strsearch . '</legend>';
$searchform .= '<input name="id" type="hidden" value="' . $course->id . '" />';  // course
$searchform .= '<label class="accesshide" for="searchform_search">' . $strsearch . '</label>' .
    '<input id="searchform_search" name="search" type="text" size="16" />';
$searchform .= '<button id="searchform_button" type="submit" title="' . $strsearch . '">' . $strgo . '</button>';
$searchform .= '</fieldset></form>';
$searchform .= html_writer::link($advancedsearch_url, get_string('advancedsearch', 'block_search_forums')) . $OUTPUT->help_icon('search') . '<br />';
$searchform .= html_writer::link($mythreads_url, get_string('my_threads', 'block_oc_mooc_nav'));
$searchform .= '</div>';
echo $searchform; */
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

// Forum abonnieren Link
//$forum->forcesubscribe
// 0 - optional
// 1 - verpflichtend
// 2 - automatisch
// 3 - deaktiviert

//if ($USER->username == 'riegerj') {
if ($forum->forcesubscribe == 0 OR $forum->forcesubscribe == 2) {
    sesskey();
    $subscription = $DB->get_record('forum_subscriptions', array('userid' => $USER->id, 'forum' => $forum->id));
    if ($subscription) {
        echo html_writer::link(new moodle_url('/mod/forum/subscribe.php?id=' . $forum->id . '&sesskey=' . $USER->sesskey), get_string('unsubscribe', 'forum'));
    } else {
        echo html_writer::link(new moodle_url('/mod/forum/subscribe.php?id=' . $forum->id . '&sesskey=' . $USER->sesskey), get_string('subscribe', 'forum'));
    }
    echo '<p></p>';
}
//}

/// find out current groups mode
groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/forum/view.php?id=' . $cm->id);

$params = array(
    'context' => $context,
    'objectid' => $forum->id
);
$event = \mod_forum\event\course_module_viewed::create($params);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('forum', $forum);
$event->trigger();

$SESSION->fromdiscussion = qualified_me();   // Return here if we post or set subscription etc


/// Print settings and things across the top

// If it's a simple single discussion forum, we need to print the display
// mode control.
if ($forum->type == 'single') {
    $discussion = NULL;
    $discussions = $DB->get_records('forum_discussions', array('forum' => $forum->id), 'timemodified ASC');
    if (!empty($discussions)) {
        $discussion = array_pop($discussions);
    }
    if ($discussion) {
        if ($mode) {
            set_user_preference("forum_displaymode", $mode);
        }
        $displaymode = get_user_preferences("forum_displaymode", $CFG->forum_displaymode);
        forum_print_mode_form($forum->id, $displaymode, $forum->type);
    }
}

if (!empty($forum->blockafter) && !empty($forum->blockperiod)) {
    $a = new stdClass();
    $a->blockafter = $forum->blockafter;
    $a->blockperiod = get_string('secondstotime' . $forum->blockperiod);
    echo $OUTPUT->notification(get_string('thisforumisthrottled', 'forum', $a));
}

if ($forum->type == 'qanda' && !has_capability('moodle/course:manageactivities', $context)) {
    echo $OUTPUT->notification(get_string('qandanotify', 'forum'));
}

// oncampus ////////////////
require_once($CFG->dirroot . '/course/format/mooin4/forum_lib.php');
//blocks/oc_mooc_nav
switch ($forum->type) {
    case 'single':
        if (!empty($discussions) && count($discussions) > 1) {
            echo $OUTPUT->notification(get_string('warnformorepost', 'forum'));
        }
        if (!$post = forum_get_post_full($discussion->firstpost)) {
            print_error('cannotfindfirstpost', 'forum');
        }
        if ($mode) {
            set_user_preference("forum_displaymode", $mode);
        }

        $canreply = forum_user_can_post($forum, $discussion, $USER, $cm, $course, $context);
        $canrate = has_capability('mod/forum:rate', $context);
        $displaymode = get_user_preferences("forum_displaymode", $CFG->forum_displaymode);

        
        echo("Get_user_preferences 2");
        var_dump($displaymode); 
        echo '&nbsp;'; // this should fix the floating in FF
        forum_print_discussion($course, $cm, $forum, $discussion, $post, $displaymode, $canreply, $canrate);
        break;

    case 'eachuser':
        echo '<p class="mdl-align">';
        if (forum_user_can_post_discussion($forum, null, -1, $cm)) {
            print_string("allowsdiscussions", "forum");
        } else {
            echo '&nbsp;';
        }
        echo '</p>';
        if (!empty($showall)) {
            forum_print_latest_discussions($course, $forum, 0, 'header', '', -1, -1, -1, 0, $cm);
        } else {
            forum_print_latest_discussions($course, $forum, -1, 'header', '', -1, -1, $page, $CFG->forum_manydiscussions, $cm);
        }
        break;

    case 'teacher':
        if (!empty($showall)) {
            forum_print_latest_discussions($course, $forum, 0, 'header', '', -1, -1, -1, 0, $cm);
        } else {
            forum_print_latest_discussions($course, $forum, -1, 'header', '', -1, -1, $page, $CFG->forum_manydiscussions, $cm);
        }
        break;

    case 'blog':
        echo '<br />';
        if (!empty($showall)) {
            forum_print_latest_discussions($course, $forum, 0, 'plain', '', -1, -1, -1, 0, $cm);
        } else {
            forum_print_latest_discussions($course, $forum, -1, 'plain', '', -1, -1, $page, $CFG->forum_manydiscussions, $cm);
        }
        break;

    default:
        echo '<br />';
        if (!empty($showall)) {
            oc_forum_print_latest_discussions($course, $forum, 0, 'header', '', -1, -1, -1, 0, $cm);
        } else {
            oc_forum_print_latest_discussions($course, $forum, -1, 'header', '', -1, -1, $page, $CFG->forum_manydiscussions, $cm);
        }


        break;
}

// Add the subscription toggle JS.
$PAGE->requires->yui_module('moodle-mod_forum-subscriptiontoggle', 'Y.M.mod_forum.subscriptiontoggle.init');

echo $OUTPUT->footer($course);
