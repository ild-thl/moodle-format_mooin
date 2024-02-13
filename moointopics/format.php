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
 * Topics course format. Display the whole course as "topics" made of modules.
 *
 * @package format_moointopics
 * @copyright 2006 The Open University
 * @author N.D.Freear@open.ac.uk, and others.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/completionlib.php');

// Horrible backwards compatible parameter aliasing.
if ($topic = optional_param('topic', 0, PARAM_INT)) {
    $url = $PAGE->url;
    $url->param('section', $topic);
    debugging('Outdated topic param passed to course/view.php', DEBUG_DEVELOPER);
    redirect($url);
}
// End backwards-compatible aliasing.

// Retrieve course format option fields and add them to the $course object.
$format = course_get_format($course);
$course = $format->get_course();
$context = context_course::instance($course->id);

if (($marker >= 0) && has_capability('moodle/course:setcurrentsection', $context) && confirm_sesskey()) {
    $course->marker = $marker;
    course_set_marker($course->id, $marker);
}

// Make sure section 0 is created.
course_create_sections_if_missing($course, 0);



$renderer = $PAGE->get_renderer('format_moointopics');

$sectionnumber = optional_param('section', 0, PARAM_INT);
$unsetchapter = optional_param('unsetchapter', 0, PARAM_INT); // sectionid
$setchapter = optional_param('setchapter', 0, PARAM_INT); // sectionid

// set or unset chapter
if ($setchapter > 0 && has_capability('moodle/course:update', $context)) {
    \format_moointopics\local\chapterlib::set_chapter($setchapter);
}

if ($unsetchapter > 0 && has_capability('moodle/course:update', $context)) {
    if ($chaptersection = $DB->get_record('course_sections', array('id' => $unsetchapter))) {
        if ($chaptersection->section == 1) {
            \core\notification::warning(get_string('cannot_remove_chapter', 'format_mooin4'));
        } else {
            \format_moointopics\local\chapterlib::unset_chapter($unsetchapter);
        }
    }
}


if (!empty($displaysection)) {
    $format->set_section_number($displaysection);
}
$outputclass = $format->get_output_classname('content');
$widget = new $outputclass($format);
echo $renderer->render($widget);

// Include course format js module.
$PAGE->requires->js('/course/format/moointopics/format.js');
