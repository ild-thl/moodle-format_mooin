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
 * @package   format_mooin
 * @copyright 2022 ISy TH Lübeck <dev.ild@th-luebeck.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once('../../../mod/forum/lib.php');
require_once($CFG->libdir . '/completionlib.php');

$id = optional_param('id', 0, PARAM_INT);       // Course ID
$cmid = optional_param('cmid', 0, PARAM_INT);       // Course module ID
$f = optional_param('f', 0, PARAM_INT);        // Forum ID
$mode = optional_param('mode', 0, PARAM_INT);     // Display mode (for single forum)
$showall = optional_param('showall', '', PARAM_INT); // show all discussions on one page
$changegroup = optional_param('group', -1, PARAM_INT);   // choose the current group
$page = optional_param('page', 0, PARAM_INT);     // which page to show
$search = optional_param('search', '', PARAM_CLEAN);// search string
// $PAGE->navbar->add(get_string('my_forum', 'format_mooin'));
// mooin
$page = -1;

$params = array();
if ($cmid > 0) {
    $params['cmid'] = $cmid;
}
else if ($id > 0) {
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
$PAGE->set_url('/mod/forum/view.php', $params); // /course/format/mooin/forums.php', $params

if ($cmid > 0) {
    if (!$cm = $DB->get_record('course_modules', array('id' => $cmid))) {
        print_error('invalidcoursemodule');
    }
    if (!$course = $DB->get_record("course", array("id" => $cm->course))) {
        print_error('coursemisconf');

    }
    $PAGE->set_course($course);
    if (!$forum = $DB->get_record('forum', array('id' => $cm->instance))){
        print_error('forumnotfounderror');
    }
}
else if ($id > 0) {
    if (!$forum = $DB->get_record('forum', array('course' => $id, 'type' => 'general'))){ // general
        print_error('forumnotfounderror');
    }
    if (!$course = $DB->get_record("course", array("id" => $id))) {
        print_error('coursemisconf');
    }
    $oc_m = $DB->get_record('modules', array('name' => 'forum'));
    $oc_cm = $DB->get_record('course_modules', array('instance' => $forum->id, 'course' => $course->id, 'module' => $oc_m->id));
    if (!$oc_cm)  {
        print_error('invalidcoursemodule');
    }
    $cm = $oc_cm;
    if ($forum->type == 'single') {
        $PAGE->set_pagetype('mod-forum-discuss');
    }
    // move require_course_login here to use forced language for course
    // fix for MDL-6926
    require_course_login($course, true, $cm);
    $strforums = get_string("modulenameplural", "forum");
    $strforum = get_string("modulename", "forum");
} else if ($f > 0) {

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
//print_object($PAGE);die();
echo $OUTPUT->header();

//echo $OUTPUT->navbar();
/// Some capability checks.
if (empty($cm->visible) and !has_capability('moodle/course:viewhiddenactivities', $context)) {
    notice(get_string("activityiscurrentlyhidden"));
}

if (!has_capability('mod/forum:viewdiscussion', $context)) {
    notice(get_string('noviewdiscussionspermission', 'forum'));
}


//////////// mooin ////////////////////////////////
// Wenn mehrere Foren (Newsforum zählt nicht) vohanden sind,
// wird hier nur eine Liste mit Links angezeigt

/* global $USER, $DB;

$oc_m = $DB->get_record('modules', array('name' => 'forum'));
$oc_foren = $DB->get_records('forum', array('course' => $course->id, 'type' => 'general'));
$oc_showall = optional_param('showall', '', PARAM_RAW);
$oc_counter = 0;
ob_start();
if (count($oc_foren) >= 0 and $oc_showall == '') {
    echo '<h2>' . get_string('all_forums', 'format_mooin') . '</h2>';
    foreach ($oc_foren as $oc_forum) {
        $oc_cm = $DB->get_record('course_modules', array('instance' => $oc_forum->id, 'course' => $course->id, 'module' => $oc_m->id));
        $oc_link = html_writer::link(new moodle_url('/course/format/mooin/forums.php?showall=false&cmid=' . $oc_cm->id), $oc_forum->name);
        if ($oc_cm->visible == 1) {
            echo html_writer::tag('div', $oc_link);
            $oc_counter++;
        }
    }
    if ($oc_counter > 1) {
        ob_end_flush();
        echo $OUTPUT->footer($course);
        exit;
    }
}
ob_end_clean(); */

///////////////////////////////////////////////////////

echo $OUTPUT->heading(format_string($forum->name), 2);
if (!empty($forum->intro) && $forum->type != 'single' && $forum->type != 'teacher') {
    echo $OUTPUT->box(format_module_intro('forum', $forum, $cm->id), 'generalbox', 'intro');
}

// mooin Link: Meine Beiträge und Suche//////////////////////////////////////////////////////////////////////////////////////////


$mythreads_url = new moodle_url('/mod/forum/user.php', array('id' => $USER->id, 'course' => $course->id));
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
$searchform .= html_writer::link($mythreads_url, get_string('my_threads', 'format_mooin'));
$searchform .= '</div>';
echo $searchform;
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

// Forum abonnieren Link
//$forum->forcesubscribe
// 0 - optional
// 1 - verpflichtend
// 2 - automatisch
// 3 - deaktiviert

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

// mooin ////////////////
require_once($CFG->dirroot . '/course/format/mooin/forum_lib.php');

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
