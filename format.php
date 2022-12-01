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
$progress = null;

// $Out is the card-output in course page
$main_out = null;
$neben_out = null;
$out = null;
$out_first_part = null;

$main_out .= html_writer::start_tag('div', ['class'=>'wrapper']);
$main_out .= html_writer::start_tag('div', ['class' => 'main-container bg-white']); // 'main-container
$main_out .= html_writer::start_tag('div', ['class' => 'course-title-header']); // course-title-header
$main_out .= html_writer::start_tag('div', ['class' => 'container']);
$main_out .= html_writer::start_tag('p');
$main_out .= get_string('welcome', 'format_mooin');
$main_out .= html_writer::end_tag('p');
$main_out .= html_writer::start_tag('h2'); //h2
$main_out .= $course->fullname;
$main_out .= html_writer::end_tag('h2'); //h2
$main_out .= html_writer::end_tag('div'); // container
$main_out .= html_writer::end_tag('div'); // course-title-header
// $lesson = new lesson($lessonrecord);

if ($sectionnumber == 0 ) { // && !$PAGE->user_is_editing()
    // newsforum
    $check_news = get_last_news($course->id, 'news');
    if ($check_news != null) {
        $main_out .= $check_news;  
    } else {
        
        $main_out .= '';
    }
    
    $out_first_part .= html_writer::start_tag('div', array('class' => 'course-progress')); // first_frontpage_mooin
    // progress & start/continue learning button
    // progress card
    $out_first_part .= html_writer::start_tag('div', array('class' => 'container')); // container
    $out_first_part .= html_writer::nonempty_tag('h2',get_string('progress', 'format_mooin'));
    $out_first_part .= html_writer::start_tag('div', array('class' => 'even-columns')); // even-columns
    
    $out_first_part .= html_writer::start_tag('div'); // array('class' =>'progress_card col-10')
    // To Remove
    /* $mods = $DB->get_records_sql("SELECT cm.*, m.name as modname
                    FROM {modules} m, {course_modules} cm
                WHERE cm.course = ? AND cm.completiongradeitemnumber >= ? AND cm.module = m.id AND m.visible = 1", array($course->id, 0));
    echo count($mods);
    var_dump($mods); */
    $grade_in_course = get_course_grades($course->id);
    
    $course_grade = round($grade_in_course);
    if ($course_grade != -1) {
        $out_first_part .= html_writer::start_span('') . get_progress_bar_course($course_grade, 100) . html_writer::end_span();
    }else {
        $out_first_part .= get_progress_bar_course(0, 100);
    }
    
    $out_first_part .= html_writer::end_tag('div');
    // TODO get last visited section from userpref
    $out_first_part .= html_writer::start_tag('div', ['class' => 'mooin-btn mooin-btn-primary mooin-btn-icon']); // array('class' =>'start_contnue_card')
    $sectionnumber = 1;
    $continue_url = new moodle_url('/course/view.php', array('id' => $course->id, 'section' => $sectionnumber));
    $continue_link = html_writer::link($continue_url, get_string('continue', 'format_mooin'), array('title' => get_string('continue', 'format_mooin')));
    // echo '&nbsp;';
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
    $out_first_part.= html_writer::link($continue_url, $start_continue, array('title' => $start_continue));
    // echo $continue_link;
    
    $out_first_part .= html_writer::start_span('icon-wrapper start-icon') . html_writer::end_span();
    $out_first_part .= html_writer::end_tag('div'); // start_contnue_card
    $out_first_part .= html_writer::end_tag('div'); // even-columns
    $out_first_part .= html_writer::end_tag('div'); // container
    $out_first_part .= html_writer::end_tag('div'); // course-progress
    
    // $main_out .= $out_first_part;
    // Badges mobile buttons
    $out_first_part .= html_writer::start_tag('div',['class' => 'badges-mobile-mooin-btn d-sm-block d-md-none']);
    $out_first_part .= html_writer::start_tag('div', ['class' => 'container']);
    $out_first_part .= html_writer::nonempty_tag('h2',get_string('badges_certificates', 'format_mooin'));
    $out_first_part .= html_writer::start_tag('div', ['class' => 'even-columns']);
    $out_first_part .= html_writer::start_div();

    $badges_url = new moodle_url('/course/format/mooin/badges.php', array('id' => $course->id));
    $out_first_part .= html_writer::link($badges_url, get_string('course_badges', 'format_mooin'), array('title' => get_string('course_badges', 'format_mooin'), 'class'=> 'mooin-btn mooin-btn-special mooin-btn-icon icon-wrapper badges-icon'));
   
    $out_first_part .= html_writer::end_div();
    $out_first_part .= html_writer::start_div();
    $certificate_url = new moodle_url('/course/format/mooin/certificates.php', array('id' => $course->id));
    $out_first_part .= html_writer::link($certificate_url, get_string('my_certificate', 'format_mooin'), array('title' => get_string('my_certificate', 'format_mooin'), 'class'=> 'mooin-btn mooin-btn-special mooin-btn-icon icon-wrapper award-icon'));
   
    $out_first_part .= html_writer::end_div();
    $out_first_part .= html_writer::end_tag('div'); // even-columns
    $out_first_part .= html_writer::end_tag('div'); // container
    $out_first_part .= html_writer::end_tag('div'); //badges-mobile-mooin-btn

    // Community mobile buttons (später ändern)
    // $dis = $DB->get_record('forum', ['course'=> $course->id, 'type'=>'general']);
    $sql_first = 'SELECT * FROM mdl_forum WHERE course = :id_course AND type = :type_forum ORDER BY ID DESC LIMIT 1 ';
    $param_first = array('id_course'=>$course->id, 'type_forum'=>'general');
    // $new_in_course = $DB->get_record('forum', ['course' =>$courseid, 'type' => $forum_type]);
    $dis = $DB->get_record_sql($sql_first, $param_first);
    $out_first_part .= html_writer::start_tag('div',['class' => 'community-mobile-mooin-btn d-sm-block d-md-none']);
    $out_first_part .= html_writer::start_tag('div', ['class' => 'container']);
    $out_first_part .= html_writer::nonempty_tag('h2',get_string('community', 'format_mooin'));
    $out_first_part .= html_writer::start_tag('div', ['class' => 'even-columns']);
    $out_first_part .= html_writer::start_div();
    
    $diskussions_url = new moodle_url('/mod/forum/view.php', array('f' => $dis->id, 'tab'=>'1'));
    $out_first_part .= html_writer::link($diskussions_url, get_string('forums', 'format_mooin'), array('title' => get_string('forums', 'format_mooin'), 'class'=> 'mooin-btn mooin-btn-special mooin-btn-icon icon-wrapper chat-icon'));
   
    /* $out_first_part .= html_writer::start_tag('a', ['class'=> 'mooin-btn mooin-btn-special mooin-btn-icon']);
    $out_first_part .= html_writer::start_span('icon-wrapper chat-icon') . get_string('forums', 'format_mooin') . html_writer::end_span();
    $out_first_part .= html_writer::end_tag('a'); */
    $out_first_part .= html_writer::end_div();
    $out_first_part .= html_writer::start_div();
    $participants_url = new moodle_url('/course/format/mooin/participants.php', array('id' => $course->id));
    $out_first_part .= html_writer::link($participants_url, get_string('users', 'format_mooin'), array('title' => get_string('users', 'format_mooin'), 'class'=> 'mooin-btn mooin-btn-special mooin-btn-icon icon-wrapper participant-icon'));
   
    $out_first_part .= html_writer::end_div();
    $out_first_part .= html_writer::end_tag('div'); // even-columns
    $out_first_part .= html_writer::end_tag('div'); // container
    $out_first_part .= html_writer::end_tag('div'); //badges-mobile-mooin-btn

    $main_out .= $out_first_part;
    // Add rendere here
    
    $main_out .= html_writer::end_tag('div'); // main-container

    
    // Sidebar Card
    $out .= html_writer::start_tag('div', array('class' => 'side-right d-none d-md-inline-block')); // side-right d-none d-md-inline-block
    // Badges and certificates
    
    $out .= html_writer::start_tag('div', ['class' => 'badges']); // badges
    $out .= html_writer::start_tag('div', ['class' => 'container']); // container
    $out .= html_writer::nonempty_tag('h2',get_string('badges_certificates', 'format_mooin'));
    $out .= html_writer::start_tag('div', array('class' => 'd-flex align-items-center')); // d-flex align-items-center
    $out .= html_writer::start_span('icon-wrapper badges-icon') . html_writer::end_span();
    $badges_url = new moodle_url('/course/format/mooin/badges.php', array('id' => $course->id));
    $out .= html_writer::start_tag('p', array('class' => 'caption fw-700 text-primary pl-2'));
    $out .= html_writer::link($badges_url, get_string('badges', 'format_mooin'), array('title' => get_string('badges', 'format_mooin')));
    $out .= html_writer::end_tag('p'); // p
    $out .= html_writer::end_tag('div'); // align-items-center
    $out .= html_writer::start_tag('div', array('class' => 'badges-card')); // badges-card
    

    $out .= html_writer::start_tag('div', array('class' => 'badges-card-inner'));
    ob_start();
    $out .= display_user_and_availbale_badges($USER->id, $course->id);
    $out .= ob_get_contents();
    ob_end_clean();
    $out .= html_writer::end_tag('div'); // badges-card-inner
    $bottom_badge_link = html_writer::link($badges_url, get_string('see_badges', 'format_mooin'), array('title' => get_string('see_badges', 'format_mooin')));
    $out .= html_writer::div($bottom_badge_link, 'primary-link d-block text-right');
    $out .= html_writer::end_tag('div'); // badges-card
    // $out .= html_writer::end_tag('div');
    

    $out .= html_writer::start_tag('div', array('class' => 'certificate-card')); // certificate-card
    $out .= html_writer::start_tag('div', array('class' => 'd-flex align-items-center')); // d-flex align-items-center
    $out .= html_writer::start_span('icon-wrapper award-icon') . html_writer::end_span();
    $certificates_url = new moodle_url('/course/format/mooin/certificates.php', array('id' => $course->id));
    // $out .= html_writer::tag('p', get_string('certificates', 'format_mooin'));
    $out .= html_writer::start_tag('p', array('div' => 'caption fw-700 text-primary pl-2'));
    $out .= html_writer::link($certificates_url, get_string('certificates', 'format_mooin'), array('title' => get_string('certificates', 'format_mooin')));
    $out .= html_writer::end_tag('p'); // p
    $out .= html_writer::end_tag('div'); // align-items-center
    
    $out .= html_writer::start_tag('div', array('class' => 'certificate-card-inner')); // certificate-card-inner
    $out .= show_certificat($course->id);// get_certificate($course->id);
    // $out .= ob_get_contents();
    $out .= html_writer::end_tag('div'); // certificate-card-inner
    
    $out .= html_writer::end_tag('div');// certificate-card
    $out .= html_writer::end_tag('div'); //container
    $out .= html_writer::end_tag('div'); // badges

    // Community
    $out .= html_writer::start_tag('div', ['class' =>'community']); // community 
    $out .= html_writer::start_tag('div', ['class' =>'container']); // container
    $out .= html_writer::nonempty_tag('h2',get_string('community', 'format_mooin'));
    
    $out .= html_writer::start_tag('div', array('class' => 'forum-card')); // forum-card
    
    //$out .= html_writer::start_tag('div', array('class' => 'forum-card-inner')); // forum-card-inner
    $out .= html_writer::start_tag('div', array('class' => 'd-flex align-items-center')); // d-flex align-items-center
    
    // $out .= html_writer::start_tag('p', array('div' => 'caption fw-700 text-primary pl-2'));
    
    $check_diskussion = get_last_news($course->id, 'general');
    if ($check_diskussion!= null) {
        $out .= $check_diskussion; 
    } else {
        $out .= '';
    }
    $out .= html_writer::end_tag('div'); // d-flex align-items-center
    
    
    // $out .= html_writer::end_tag('div'); // diskussion_card

    
    $out .= html_writer::end_tag('div');// forum-card

    // Participants
   
    $user_card_list = get_user_in_course($course->id);
    if ($user_card_list != null) {
        $out .= $user_card_list;
    } else {
        $out .= '';
    }
    $out .= html_writer::end_tag('div'); // container
    $out .= html_writer::end_tag('div'); // community
    $out .= html_writer::end_tag('div');
    $main_out .= $out;
    
    $main_out .= html_writer::end_tag('div'); // wrapper
    echo $main_out; 
    
      
    
}if (!empty($displaysection)) {
    $renderer->print_single_section_page($course, null, null, null, null, $displaysection);
} else {
    $renderer->print_multiple_section_page($course, null, null, null, null);
    
}

// Include course format js module.
$PAGE->requires->js('/course/format/mooin/format.js');
