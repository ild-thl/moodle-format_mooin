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
 * mooin course format. Display the whole course as "mooin" made of modules.
 *
 * @package format_mooin
 * @copyright 2022 ISy TH Lübeck <dev.ild@th-luebeck.de>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir.'/completionlib.php');

// Horrible backwards compatible parameter aliasing.
if ($topic = optional_param('topic', 0, PARAM_INT)) {
    $url = $PAGE->url;
    $url->param('section', $topic);
    debugging('Outdated topic param passed to course/view.php', DEBUG_DEVELOPER);
    redirect($url);
}
// End backwards-compatible aliasing.

$context = context_course::instance($course->id);
// Retrieve course format option fields and add them to the $course object.
$course = course_get_format($course)->get_course();

if (($marker >= 0) && has_capability('moodle/course:setcurrentsection', $context) && confirm_sesskey()) {
    $course->marker = $marker;
    course_set_marker($course->id, $marker);
}

// Make sure section 0 is created.
course_create_sections_if_missing($course, 0);
$sectionnumber = optional_param('section', 0, PARAM_INT);
// mooin: TODO get section and set last visited in userpref
if ($sectionnumber > 0) {
    set_user_preference('format_mooin_last_section_in_course_'.$courseid, $sectionnumber, $USER->id);
}
else if ($sectionnumber == 0) {
    $last_section = get_user_preferences('format_mooin_last_section_in_course_'.$courseid, 0, $USER->id);
}

$renderer = $PAGE->get_renderer('format_mooin');

// mooin: print tiles here

if ($sectionnumber == 0) {
    // newsforum
    if ($news_forum = $DB->get_record('forum', array('course' => $course->id, 'type' => 'news'))) {
        $news_forum_id = $news_forum->id;
        $newsurl = new moodle_url('/mod/forum/view.php', array('f' => $news_forum_id, 'tab' => 1));
        $forum_link = html_writer::link($newsurl, get_string('news', 'format_mooin'), array('title' => get_string('news', 'format_mooin')));
        echo $forum_link;
    }
    echo '<p></p>';

    // progress & start/continue learning button
    echo get_string('progress', 'format_mooin');
    $start_continue = get_string('start', 'format_mooin');
    // get last visited section from userpref
    if (isset($last_section)) {
        if ($last_section == 0) {
            // start learning
            $last_section = 1;
        }
        else {
            // continue learning
            if ($section = $DB->get_record('course_sections', array('course' => $course->id, 'section' => $last_section))) {
                if ($section->name) {
                    $start_continue = get_string('continue', 'format_mooin').' '.$section->name;
                }
                else {
                    $start_continue = get_string('continue', 'format_mooin').' '.get_string('sectionname', 'format_mooin').' '.$last_section;
                }
            }
            else {
                $start_continue = get_string('continue', 'format_mooin');
            }
        }
    }
    else {
        // start learning
        $last_section = 1;
    }
    $continue_url = new moodle_url('/course/view.php', array('id' => $course->id, 'section' => $last_section));
    $continue_link = html_writer::link($continue_url, $start_continue, array('title' => $start_continue));
    echo $continue_link;
    echo '<p></p>';

    // Badges and certificates
    echo '<h3>'.get_string('badges_certificates', 'format_mooin').'</h3>';
    $badges_url = new moodle_url('/course/format/mooin/badges.php', array('id' => $course->id));
    $badges_link = html_writer::link($badges_url, get_string('badges', 'format_mooin'), array('title' => get_string('badges', 'format_mooin')));
    echo $badges_link;
    $certificates_url = new moodle_url('/course/format/mooin/certificates.php', array('id' => $course->id));
    $certificates_link = html_writer::link($certificates_url, get_string('certificates', 'format_mooin'), array('title' => get_string('certificates', 'format_mooin')));
    echo $certificates_link;
    echo '<p></p>';

    // Community
    echo '<h3>'.get_string('community', 'format_mooin').'</h3>';
    $forums_url = new moodle_url('/course/format/mooin/forums.php', array('id' => $course->id));
    $forums_link = html_writer::link($forums_url, get_string('forums', 'format_mooin'), array('title' => get_string('forums', 'format_mooin')));
    echo $forums_link;
    $participants_url = new moodle_url('/course/format/mooin/participants.php', array('id' => $course->id));
    $participants_link = html_writer::link($participants_url, get_string('participants', 'format_mooin'), array('title' => get_string('participants', 'format_mooin')));
    echo $participants_link;
}

if (!empty($displaysection)) {
    $renderer->print_single_section_page($course, null, null, null, null, $displaysection);
} else {
    $renderer->print_multiple_section_page($course, null, null, null, null);
}

// Include course format js module.
$PAGE->requires->js('/course/format/mooin/format.js');
