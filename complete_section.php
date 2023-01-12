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
 * Complete a Lektion inside a chapter
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package format_mooin
 * @author Nguefack
 */
require_once('../../../config.php');
require_once($CFG->libdir.'/tablelib.php');
require_once($CFG->libdir.'/filelib.php');
require_once('../mooin/lib.php');
require_once('../mooin/locallib.php');

global $DB, $PAGE, $USER;

$contextid    = optional_param('contextid', 0, PARAM_INT); // One of this or.
$courseid     = optional_param('id', 0, PARAM_INT); // This are required. required_param('id', PARAM_INT);

if ($contextid) {
    $context = context::instance_by_id($contextid, MUST_EXIST);
    if ($context->contextlevel != CONTEXT_COURSE) {
        print_error('invalidcontext');
    }
    $course = $DB->get_record('course', array('id' => $context->instanceid), '*', MUST_EXIST);
} else {
    $course = $DB->get_record('course', array('id' => $_POST['courseid']), '*', MUST_EXIST);
    $context = context_course::instance($course->id, MUST_EXIST);
}

$courseformat = course_get_format($course);
$course_new = $courseformat->get_course();

// $url = new moodle_url('/course/format/mooin/complete_section.php');
// Get all the section list from DB
$sections = ($DB->count_records('course_sections', ['course' =>$course->id])) - 1;

echo 'Perial';
// Get the POST Data from complete_section.js
$sec = intval($_POST['section']);
complete_section($USER->id, $course->id, $sec);
// LOOP throught the section list to get the right one and change the state from progress bar
/* for ($i=1; $i <= $sections; $i++) { 
    parse_str($i, $out);
    if ( $i == $sec) { // array_key_exists($out, $_POST) && 
        echo($sections);
        echo $i;
        
    }
} */