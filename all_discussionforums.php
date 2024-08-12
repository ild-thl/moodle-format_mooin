<?php
require_once('../../../config.php');
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir.'/completionlib.php');
require_once('../../../mod/forum/lib.php');

use format_moointopics\local\utils as utils;
use mod_forum\local\factories\url;

global $USER, $PAGE, $CFG, $DB;

$courseid = optional_param('id', 0, PARAM_INT);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($course->id);

require_login($course);

$PAGE->set_course($course);
$PAGE->set_pagelayout('course');
$PAGE->set_context(\context_course::instance($course->id));
$PAGE->set_title("$course->shortname: " . get_string('forums', 'format_moointopics'));
$PAGE->set_heading($course->fullname);
$PAGE->set_other_editing_capability('moodle/course:manageactivities');
$PAGE->set_url('/course/format/moointopics/all_discussionforums.php', array('id' => $course->id));

echo $OUTPUT->header();

$breadcrumb = utils::subpage_navbar();
$oc_m = $DB->get_record('modules', array('name' => 'forum'));

$readableforums = forum_get_readable_forums($USER->id, $course->id);
$forumslist = [];

$index = 1;  // Initialisieren des Index-Zählers

if (!empty($readableforums)) {
    foreach ($readableforums as $forum) {
        if ($forum->course == $course->id && $forum->type != 'news') {
            $forum->istracked = forum_tp_is_tracked($forum);
            if ($forum->istracked) {
                $forum->unreadpostscount = forum_tp_count_forum_unread_posts($forum->cm, $course);
            }
            $unreadposts = utils::count_unread_posts($USER->id, $course->id, false, $forum->id);

            $discussion_forum = new stdClass(); 
            $discussion_forum->name = $forum->name;
            $urlfactory = mod_forum\local\container::get_url_factory();
            $url = $urlfactory->get_forum_view_url_from_course_module_id($forum->cm->id);
            $discussion_forum->url = $url;
             // Setzen des Index und Erhöhen des Zählers
            $discussion_forum->id = $forum->id;
                
            if ($unreadposts >= 1) {
                $discussion_forum->unreadposts = $unreadposts;
            }

            if (intval($forum->cm->visible) === 1) {
                $discussion_forum->index = $index++; 
                array_push($forumslist, $discussion_forum);
            }
        }
    }
}

$data = [
    'breadcrumb' => $breadcrumb,
    'forums' => !empty($forumslist),
    'forumslist' => $forumslist
];

echo $OUTPUT->render_from_template('format_moointopics/local/content/subpages/all_discussionforums', $data);
echo $OUTPUT->footer();
