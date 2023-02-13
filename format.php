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
require_once('locallib.php');



global $PAGE;

// Call the js complete_section
// $PAGE->requires->js_call_amd('format_mooin/complete_section');

// require_once($CFG->dir.'./mod/lesson.php');

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

// mooin: TODO get section and set last visited in userpref
$sectionnumber = optional_param('section', 0, PARAM_INT);
// mooin: TODO get section and set last visited in userpref
if ($sectionnumber > 0) {
    set_user_preference('format_mooin_last_section_in_course_'.$course->id, $sectionnumber, $USER->id);
}
else if ($sectionnumber == 0) {
    $last_section = get_user_preferences('format_mooin_last_section_in_course_'.$course->id, 0, $USER->id);
}

$renderer = $PAGE->get_renderer('format_mooin');

// mooin: print tiles here
$sectionnumber = optional_param('section', 0, PARAM_INT);
$unsetchapter = optional_param('unsetchapter', 0, PARAM_INT); // sectionid
$setchapter = optional_param('setchapter', 0, PARAM_INT); // sectionid

// set or unset chapter
if ($setchapter > 0 && has_capability('moodle/course:update', $context)) {
    set_chapter($setchapter);
}

if ($unsetchapter > 0 && has_capability('moodle/course:update', $context)) {
    unset_chapter($unsetchapter);
}

$progress = null;

// $Out is the card-output in course page
$main_out = null;
$neben_out = null;
$out = null;
$out_first_part = null;

// $lesson = new lesson($lessonrecord);

if ($sectionnumber == 0 ) { // && !$PAGE->user_is_editing()
    // newsforum

    $check_news = get_last_news($course->id, 'news');
    if ($check_news != null) {
        $news = $check_news;
    } else {

        $news = false;
    }

    $grade_in_course = get_course_grades($course->id);

    $course_grade = round($grade_in_course);
    $progressbar  = null;
    if ($course_grade != -1) {
        $progressbar .= html_writer::start_span('') . get_progress_bar_course($course_grade, 100) . html_writer::end_span();
        $progress = $course_grade;
    } else {
        $progressbar .= get_progress_bar_course(0, 100);
        $progress = 0;
    }

    $sectionnumber = 1;
    $continue_url = new moodle_url('/course/view.php', array('id' => $course->id, 'section' => $sectionnumber));

    $start_continue = get_string('start', 'format_mooin');
    // get last visited section from userpref
    if (isset($last_section)) {
        if ($last_section == 0) {
            // start learning
            $last_section = 1;
        } else {
            // continue learning
            if ($continuesection = $DB->get_record('course_sections', array('course' => $course->id, 'section' => $last_section))) {
                if ($continuesection->name) {
                    $start_continue = get_string('continue', 'format_mooin') . ' ' . $continuesection->name;
                } else {
                    $start_continue = get_string('continue', 'format_mooin') . ' ' . get_string('sectionname', 'format_mooin') . ' ' . $last_section;
                }
            } else {
                $start_continue = get_string('continue', 'format_mooin');
            }
        }
    } else {
        // start learning
        $last_section = 1;
    }
    $continue_url = new moodle_url('/course/view.php', array('id' => $course->id, 'section' => $last_section));

    // $dis = $DB->get_record('forum', ['course'=> $course->id, 'type'=>'general']);
    $sql_first = 'SELECT * FROM mdl_forum WHERE course = :id_course AND type = :type_forum ORDER BY ID DESC LIMIT 1 ';
    $param_first = array('id_course' => $course->id, 'type_forum' => 'general');
    // $new_in_course = $DB->get_record('forum', ['course' =>$courseid, 'type' => $forum_type]);
    $dis = $DB->get_record_sql($sql_first, $param_first);

   //  $diskussions_url = new moodle_url('/mod/forum/view.php', array('f' => $dis->id, 'tab' => '1'));
   $diskussions_url = new moodle_url('/course/format/mooin/alle_forums.php', array('id' => $course->id));

    $participants_url = new moodle_url('/course/format/mooin/participants.php', array('id' => $course->id));

    // Add rendere here
    $badges = null;
    ob_start();
    $badges .= display_user_and_availbale_badges($USER->id, $course->id);
    $badges .= ob_get_contents();
    ob_end_clean();

    $certificates_url = new moodle_url('/course/format/mooin/certificates.php', array('id' => $course->id));

    //$out .= show_certificat($course->id); // get_certificate($course->id);



    if (get_last_forum_discussion($course->id, 'general') != null) { //Nötig? get_last_news
        $check_diskussion = get_last_forum_discussion($course->id, 'general');
        // $check_diskussion = new moodle_url('/course/format/mooin/alle_forums.php', array('id' => $course->id));
    }

    // Participants
if (get_user_in_course($course->id) != null) {
    $user_card_list = get_user_in_course($course->id);
}
    // $user_card_list = get_user_in_course($course->id);
    // if ($user_card_list != null) {
    //     $out .= $user_card_list;
    // } else {
    //     $out .= '';
    // }


    // get_number_badges($course->id,null,null,null) - 3 ;
    if(count(get_badges($course->id, null, null, null))  > 3) {
        $badges_count = count(get_badges($course->id, null, null, null)) - 3;
    } else {
        $badges_count = false;
    }
    $templatecontext = [
        'course_headerimage_mobil' => get_headerimage_url($course->id, true),
        'course_headerimage_desktop' => get_headerimage_url($course->id, false),
        'coursename' => $course->fullname,
        'continue_url' => new moodle_url('/course/view.php', array('id' => $course->id, 'section' => $last_section)),
        'continue_text' => $start_continue,
        'news' => $news,
        // 'progressbar' => $progressbar,
        'badges_url' => new moodle_url('/course/format/mooin/badges.php', array('id' => $course->id)),
        'certificate_url' => new moodle_url('/course/format/mooin/certificates.php', array('id' => $course->id)),
        'discussions_url' => $diskussions_url,
        'participants_url' => $participants_url,
        'badges' => $badges,
        'certificates' => show_certificat($course->id),
        'discussion' => $check_diskussion,
        'userlist' => $user_card_list,
        'progress' => $progress,
        'topics' => $renderer->print_multiple_section_page($course, null, null, null, null),
        'other_badges' => $badges_count

    ];

    $coursecontext = context_course::instance($course->id);

    //Show 'Edit Gear' Symbol only if user has capability to update
    if (has_capability('moodle/course:update', $coursecontext)) {
        $gear_icon = html_writer::span('', 'bi bi-gear-fill');

        $edit_header_url = new moodle_url('/course/format/mooin/edit_header.php', array('course' => $course->id));
        $edit_header_link = html_writer::link($edit_header_url, $gear_icon);

        $edit_newsforum = new moodle_url('');
        $edit_newsforum_link = html_writer::link($edit_newsforum, $gear_icon);

        $manage_badges_url = new moodle_url('/badges/view.php', array('type' => '2', 'id' => $course->id));
        $manage_badges_link =  html_writer::link($manage_badges_url, $gear_icon, array('class' => 'manage-badges-gear'));

        $templatecontext['edit_header_link'] = $edit_header_link;
        $templatecontext['edit_newsforum_link'] = $edit_newsforum_link;
        $templatecontext['manage_badges_link'] = $manage_badges_link;

        $templatecontext['has_capability'] = true;
    }

    echo $OUTPUT->render_from_template('format_mooin/mooin_mainpage', $templatecontext);
}

//*/
if (!empty($displaysection)) {
    $renderer->print_single_section_page($course, null, null, null, null, $displaysection);
} else {
    $PAGE->navbar;
    $renderer->print_multiple_section_page($course, null, null, null, null);
    // unenrol from course
    if ($unenrolurl = get_unenrol_url($course->id)) {
        echo html_writer::link($unenrolurl, get_string('unenrol', 'format_mooin'), array('class' => 'unenrol-btn'));
    }
}
//*/



// Include course format js module.
$PAGE->requires->js('/course/format/mooin/format.js');
