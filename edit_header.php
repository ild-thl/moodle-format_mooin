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
 * Page that shows a form to manage and set additional metadata dor a course.
 *
 * @package     format_mooin4
 * @copyright   2022 ISy TH Lübeck <dev.ild@th-luebeck.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once('edit_header_form.php');

$courseid = required_param('course', PARAM_INT);

$coursecontext = context_course::instance($courseid);

// Check capabilities.
if (!has_capability('moodle/course:update', $coursecontext)) {
    redirect(new moodle_url('/course/view.php?id='.$courseid));
}
// User has to be logged in.
require_login($courseid, false);

//$context = context_system::instance();
$url = new moodle_url('/course/format/mooin4/edit_header.php', array('course' => $courseid));
$course = $DB->get_record('course', array('id' => $courseid));
$coursecontext = context_course::instance($courseid);

$PAGE->set_pagelayout('admin');
$PAGE->set_context($coursecontext);
$PAGE->set_url($url);
$PAGE->set_title(get_string('coursetitle', 'moodle', array('course' => $course->fullname)));
$PAGE->set_heading(get_string('edit_course_header', 'format_mooin4'));

$filemanageropts = array(
    'subdirs' => 0,
    'maxbytes' => 1048576,
    'areamaxbytes' => 1048576,
    'maxfiles' => 1,
    'accepted_types' => array('image'),
    'context' => $coursecontext
);

$customdata = array('filemanageropts' => $filemanageropts);

$mform = new edit_header_form($url . '?course=' . $courseid, $customdata);

$redirectto = new moodle_url('/course/view.php?id='.$courseid);

if ($mform->is_cancelled()) {
    redirect($redirectto);
} 
else if ($fromform = $mform->get_data()) {
    // save
    if (isset($fromform->headerimagedesktop)) {
        file_save_draft_area_files($fromform->headerimagedesktop,
            $coursecontext->id,
            'format_mooin4',
            'headerimagedesktop',
            $courseid,
            array('maxfiles' => 1));
    }
    else {
        throw new coding_exception(get_string('file_save_error', 'format_mooin4'));
    }
    if (isset($fromform->headerimagemobile)) {
        file_save_draft_area_files($fromform->headerimagemobile,
            $coursecontext->id,
            'format_mooin4',
            'headerimagemobile',
            $courseid,
            array('maxfiles' => 1));
    }
    else {
        throw new coding_exception(get_string('file_save_error', 'format_mooin4'));
    }
    redirect($redirectto);
}
else {
    // prefill
    $draftitemiddesktop = file_get_submitted_draft_itemid('headerimagedesktop');
    file_prepare_draft_area($draftitemiddesktop, $coursecontext->id, 'format_mooin4', 'headerimagedesktop', $courseid, array('maxfiles' => 1));
    $draftitemidmobile = file_get_submitted_draft_itemid('headerimagemobile');
    file_prepare_draft_area($draftitemidmobile, $coursecontext->id, 'format_mooin4', 'headerimagemobile', $courseid, array('maxfiles' => 1));
    
    $toform = new stdClass();
    $toform->headerimagedesktop = $draftitemiddesktop;
    $toform->headerimagemobile = $draftitemidmobile;

    $mform->set_data($toform);
}

echo $OUTPUT->header();

    //$mform->set_data($toform);
    $mform->display();

echo $OUTPUT->footer();