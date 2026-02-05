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
 * Display all discussion forums for the course.
 *
 * @package     format_mooin4
 * @copyright   2025 onwards ILD
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir.'/completionlib.php');
require_once('../../../mod/forum/lib.php');

use format_mooin4\local\utils as utils;
use mod_forum\local\factories\url;

global $USER, $PAGE, $CFG, $DB;

$courseid = optional_param('id', 0, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($course->id);

require_login($course);

$PAGE->set_course($course);
$PAGE->set_pagelayout('course');
$PAGE->set_context(\context_course::instance($course->id));
$PAGE->set_title("$course->shortname: " . get_string('forums', 'format_mooin4'));
$PAGE->set_heading($course->fullname);
$PAGE->set_other_editing_capability('moodle/course:manageactivities');
$PAGE->set_url('/course/format/mooin4/all_discussionforums.php', ['id' => $course->id]);

echo $OUTPUT->header();

$breadcrumb = utils::subpage_navbar();
$forummodule = $DB->get_record('modules', ['name' => 'forum']);

$readableforums = forum_get_readable_forums($USER->id, $course->id);
$forumslist = [];

$index = 1;  // Initialize the index counter.

if (!empty($readableforums)) {
    foreach ($readableforums as $forum) {
        if ($forum->course == $course->id && $forum->type != 'news') {
            $forum->istracked = forum_tp_is_tracked($forum);
            if ($forum->istracked) {
                $forum->unreadpostscount = forum_tp_count_forum_unread_posts($forum->cm, $course);
            }
            $unreadposts = utils::count_unread_posts($USER->id, $course->id, false, $forum->id);

            $discussionforum = new stdClass();
            $discussionforum->name = $forum->name;
            $urlfactory = mod_forum\local\container::get_url_factory();
            $url = $urlfactory->get_forum_view_url_from_course_module_id($forum->cm->id);
            $discussionforum->url = $url;
             // Set the index and increment the counter.
            $discussionforum->id = $forum->id;

            if ($unreadposts >= 1) {
                $discussionforum->unreadposts = $unreadposts;
            }

            if (intval($forum->cm->visible) === 1) {
                $discussionforum->index = $index++;
                array_push($forumslist, $discussionforum);
            }
        }
    }
}

$data = [
    'breadcrumb' => $breadcrumb,
    'forums' => !empty($forumslist),
    'forumslist' => $forumslist,
];

echo $OUTPUT->render_from_template('format_mooin4/local/content/subpages/all_discussionforums', $data);
echo $OUTPUT->footer();
