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

namespace format_mooin4\local;

use html_writer;
use context_course;
use moodle_url;
use context_system;
use stdClass;
use context_user;
use core_badges_renderer;
use xmldb_table;

/**
 * Utility class for format_mooin4.
 *
 * @package     format_mooin4
 * @copyright   2025 onwards ILD
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class utils {

    /**
     * Mark a section as completed.
     *
     * @param int $section The section ID.
     */
    public static function complete_section($section) {
        global $USER;
        set_user_preference('format_mooin4_section_completed_' . $section, 1, $USER->id);
    }

    /**
     * Check if the course is completed.
     *
     * @param int $courseid The course ID.
     * @return bool True if completed, false otherwise.
     */
    public static function is_course_completed($courseid) {
        global $DB;
        $iscoursecompleted = false;
        if ($coursechapters = $DB->get_records('format_mooin4_chapter', ['courseid' => $courseid])) {
            $iscoursecompleted = true;
            foreach ($coursechapters as $chapter) {
                $chapterinfo = self::get_chapter_info($chapter);
                if ($chapterinfo['completed'] == false) {
                    $iscoursecompleted = false;
                    return false;
                }
            }
        }
        return $iscoursecompleted;
    }

    /**
     * Get the course progress for a user.
     *
     * @param int $courseid The course ID.
     * @param int $userid The user ID.
     * @return int The progress percentage.
     */
    public static function get_course_progress($courseid, $userid) {
        global $DB;

        $percentage = 0;
        $i = 0;
        if ($sections = $DB->get_records('course_sections', ['course' => $courseid])) {
            foreach ($sections as $section) {
                if (
                    !$DB->get_record('format_mooin4_chapter', ['sectionid' => $section->id]) &&
                    $section->section != 0
                ) {
                    $i++;
                    $percentage += self::get_section_progress($courseid, $section->id, $userid);
                }
            }
        }

        if ($percentage > 0) {
            $percentage = $percentage / $i;
        }

        return round($percentage);
    }


    /**
     * Set the grade for an activity.
     *
     * @param int $contextid The context ID.
     * @param float $score The score.
     * @param float $maxscore The maximum score.
     * @return mixed The new progress or false on failure.
     */
    public static function setgrade($contextid, $score, $maxscore) {
        global $DB, $USER, $CFG;
        require_once($CFG->dirroot . '/mod/hvp/lib.php');

        $cm = get_coursemodule_from_instance('hvp', $contextid);
        if (!$cm) {
            return false;
        }

        // Check permission.
        $context = \context_module::instance($cm->id);
        if (!has_capability('mod/hvp:saveresults', $context)) {
            return false;
        }

        // Get hvp data from content.
        $hvp = $DB->get_record('hvp', ['id' => $cm->instance]);
        if (!$hvp) {
            return false;
        }

        // Create grade object and set grades.
        $grade = (object)[
            'userid' => $USER->id,
        ];

        /* oncampus mod - start */
        require_once($CFG->libdir . '/gradelib.php');
        $gradinginfo = \grade_get_grades($cm->course, 'mod', 'hvp', $cm->instance, $USER->id);
        $gradinginfo = (object)$gradinginfo;
        if (!empty($gradinginfo->items)) {
            $usergrade = $gradinginfo->items[0]->grades[$USER->id]->grade;
        } else {
            $usergrade = 0;
        }

        if ($score >= $usergrade) {

            // Set grade using Gradebook API.
            $hvp->cmidnumber = $cm->idnumber;
            $hvp->name = $cm->name;
            $hvp->rawgrade = $score;
            $hvp->rawgrademax = $maxscore;
            hvp_grade_item_update($hvp, $grade);

            // Get content info for log.
            $content = $DB->get_record_sql(
                "SELECT c.name AS title, l.machine_name AS name, l.major_version, l.minor_version
                           FROM {hvp} c
                           JOIN {hvp_libraries} l ON l.id = c.main_library_id
                          WHERE c.id = ?",
                [$hvp->id]
            );

            // Log results set event.
            new \mod_hvp\event(
                'results',
                'set',
                $hvp->id,
                $content->title,
                $content->name,
                $content->major_version . '.' . $content->minor_version
            );

            $progress = self::get_hvp_section_progress($cm->course, $cm->section, $USER->id);
            return $progress;
        }

        return false;
    }

    /**
     * Set the grade for an h5pactivity.
     *
     * @param stdClass $coursemodule The course module object.
     * @param float $score The score.
     * @param float $maxscore The maximum score.
     * @return mixed The new progress or false on failure.
     */
    public static function setgrade_h5pactivity($coursemodule, $score, $maxscore) {
        global $DB, $USER, $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $cm = $coursemodule;
        
        // Get grading info for the user.
        $gradinginfo = \grade_get_grades($cm->course, 'mod', 'h5pactivity', $cm->instance, $USER->id);
        
        // Calculate raw grade from score/maxscore scaling to grade item's max.
        // Usually h5pactivity passes raw points. 
        // We will trust the passed score, but we should ensure we update the grade item correctly.
        
        if (empty($gradinginfo->items)) {
            return false;
        }
        
        $gradeitem = $gradinginfo->items[0];
        // If the grade item is locked or overridden, we shouldn't touch it.
        if ($gradeitem->locked || $gradeitem->overridden) {
             return false;
        }

        // Prepare grade record.
        $grade = new \stdClass();
        $grade->userid = $USER->id;
        $grade->rawgrade = $score;
        
        // Use grade_update to save.
        // Note: 'mod/h5pactivity' usually updates grades internally via its own events.
        // However, if we are forcing it from the frontend, we must use the gradebook API.
        
        $source = 'mooin4_manual'; 
        
        // Array structure for grade_update.
        $grades = array(
            $USER->id => array(
                'rawgrade' => $score,
                'userid'   => $USER->id,
                'usermodified' => time(),
            ) 
        );

        // We need to call h5pactivity_grade_item_update closely or just use grade_update directly.
        // Looking at mod/h5pactivity/lib.php would be ideal, but standard grade_update is safer for generic use.
        
        return \grade_update('mod/h5pactivity', $cm->course, 'mod', 'h5pactivity', $cm->instance, 0, $grades);
    }

    /**
     * Get HVP section progress.
     *
     * @param int $courseid The course ID.
     * @param int $sectionid The section ID.
     * @param int $userid The user ID.
     * @return mixed The progress array or values.
     */
    public static function get_hvp_section_progress($courseid, $sectionid, $userid) {
        global $DB, $CFG;

        require_once($CFG->libdir . '/gradelib.php');

        $percentage = 0;

        // No activities in this section?
        $coursemodules = $DB->get_records('course_modules', [
            'course' => $courseid,
            'deletioninprogress' => 0,
            'section' => $sectionid,
        ]);

        $activities = 0;

        foreach ($coursemodules as $coursemodule) {
            $storedprogress = null;
            $modulename = '';
            if ($module = $DB->get_record('modules', ['id' => $coursemodule->module])) {
                $modulename = $module->name;
            }

            if ($modulename === 'hvp') {
                $storedprogress = null;
                if (!empty($coursemodule->id)) {
                    $storedprogress = get_user_preferences('format_mooin4_hvp_progress_cmid_' . $coursemodule->id, null, $userid);
                }
                if ($storedprogress === null) {
                    $storedprogress = get_user_preferences('format_mooin4_hvp_progress_' . $coursemodule->instance, null, $userid);
                }
            }

            // Skip activities with no completion tracking at all.
            if ($coursemodule->completion == 0) {
                continue;
            }

            // Activities with manual completion (completion = 1, e.g. text pages, labels):
            // Always count as an activity; if manually marked as done → 100%, otherwise 0%.
            if ($coursemodule->completion == 1) {
                $activities++;
                $sql = 'SELECT *
                          FROM {course_modules_completion}
                         WHERE coursemoduleid = :coursemoduleid
                           AND userid = :userid
                           AND completionstate != 0';
                $params = [
                    'coursemoduleid' => $coursemodule->id,
                    'userid'         => $userid,
                ];
                if ($DB->get_record_sql($sql, $params)) {
                    $percentage += 100;
                }
                continue;
            }

            $activities++;

            // Activity is hvp, we use the grades to get the individual progress.
            if ($modulename == 'hvp') {
                $gradinginfo = grade_get_grades($courseid, 'mod', 'hvp', $coursemodule->instance, $userid);
                $grade = $gradinginfo->items[0]->grades[$userid]->grade;
                $grademax = $gradinginfo->items[0]->grademax;
                if (isset($grade) && $grade != 0) {
                    $percentage += 100 / ($grademax / $grade);
                } else if ($storedprogress !== null) {
                    $percentage += (float)$storedprogress;
                }
            } else if ($modulename == 'h5pactivity') {
                // Priority 1: Stored progress (cache).
                if ($storedprogress !== null) {
                    $percentage += (float)$storedprogress;
                } else {
                    // Priority 2: Gradebook.
                    $gradinginfo = grade_get_grades($courseid, 'mod', 'h5pactivity', $coursemodule->instance, $userid);
                    if (!empty($gradinginfo->items) && !empty($gradinginfo->items[0]->grades[$userid])) {
                        $grade = $gradinginfo->items[0]->grades[$userid]->grade;
                        $grademax = $gradinginfo->items[0]->grademax;
                        if (isset($grade) && $grade !== null && $grademax && $grademax > 0) {
                            $percentage += 100 / ($grademax / $grade);
                        } else {
                            $percentage += 0;
                        }
                    } else {
                        // Priority 3: Erledigt markiert (completionstate = 1) ODER Bestanden (completionstate = 2).
                        $sql = 'SELECT *
                                  FROM {course_modules_completion}
                                 WHERE coursemoduleid = :coursemoduleid
                                   AND userid = :userid
                                   AND completionstate != 0';
                        $params = [
                            'coursemoduleid' => $coursemodule->id,
                            'userid' => $userid,
                        ];
                        if ($DB->get_record_sql($sql, $params)) {
                            $percentage += 100;
                        } else {
                            $percentage += 0;
                        }
                    }
                }
            } else {
                // If completed, add to percentage.
                $sql = 'SELECT *
                              FROM {course_modules_completion}
                             WHERE coursemoduleid = :coursemoduleid
                               AND userid = :userid
                               AND completionstate != 0 ';
                $params = [
                    'coursemoduleid' => $coursemodule->id,
                    'userid' => $userid,
                ];
                if ($DB->get_record_sql($sql, $params)) {
                    $percentage += 100;
                }
            }
        }

        // No activities with completion activated?
        if ($activities == 0) {
            $percentage = 0;
            if (get_user_preferences('format_mooin4_section_completed_' . $sectionid, 0, $userid) == 1) {
                $percentage = 100;
            }
            return ['sectionid' => $sectionid, 'percentage' => $percentage];
        }
        $progress = ['sectionid' => $sectionid, 'percentage' => round($percentage / $activities)];
        return $progress; // Note: round($percentage / $activities).
    }

    /**
     * Set the user's coordinates.
     *
     * @param int $userid The user ID.
     * @param float $lat The latitude.
     * @param float $lng The longitude.
     */
    public static function set_user_coordinates($userid, $lat, $lng) {
        set_user_preference('format_mooin4_user_coordinates', $lat . '|' . $lng, $userid);
    }

    /**
     * Get the user's coordinates from preferences.
     *
     * @param int $userid The user ID.
     * @return stdClass|bool The coordinates or false if not set.
     */
    public static function get_user_coordinates_from_pref($userid) {
        $value = get_user_preferences('format_mooin4_user_coordinates', '', $userid);
        if ($value != '') {
            $valuearray = explode('|', $value);
            if (count($valuearray) == 2) {
                $coordinates = new stdClass();
                $coordinates->lat = $valuearray[0];
                $coordinates->lng = $valuearray[1];
                return $coordinates;
            }
        }
        return false;
    }

    /**
     * Get the URL for a placeholder image.
     *
     * @param string $type The type of placeholder (badges, certificates, participants)
     * @return string|null The URL of the placeholder image or null if not set
     */
    public static function get_placeholder_url($type) {
        $config = get_config('format_mooin4', 'placeholder_' . $type);

        // DEBUG: Log the config value
        error_log("DEBUG get_placeholder_url($type): config = " . var_export($config, true));

        if (empty($config)) {
            error_log("DEBUG get_placeholder_url($type): config is empty, returning null");
            return null;
        }

        $fs = get_file_storage();
        $context = context_system::instance();
        $files = $fs->get_area_files(
            $context->id,
            'format_mooin4',
            'placeholder_' . $type,
            0,
            'itemid, filepath, filename',
            false
        );

        error_log("DEBUG get_placeholder_url($type): found " . count($files) . " files");

        if (empty($files)) {
            error_log("DEBUG get_placeholder_url($type): no files found, returning null");
            return null;
        }

        $file = reset($files);
        $url = moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        )->out();
        
        error_log("DEBUG get_placeholder_url($type): returning URL = $url");
        return $url;
    }




    /**
     * Get the user in the course
     *
     * @param int $courseid
     * @return array out
     */
    public static function get_user_in_course($courseid) {
        global $DB, $OUTPUT;
        $out = null;
        // Get the enrol data in the course.

        $sql = 'SELECT * FROM mdl_enrol WHERE courseid = :cid AND enrol = :enrol_data ORDER BY ID ASC';
        $param = ['cid' => $courseid, 'enrol_data' => 'manual'];
        $enroldata = $DB->get_records_sql($sql, $param);

        // Get user_enrolments data.
        $userenroldata = [];
        $sqlquery = 'SELECT * FROM mdl_user_enrolments WHERE enrolid = :value_id ORDER BY timecreated DESC ';

        foreach ($enroldata as $key => $value) {
            $paramarray = ['value_id' => $value->id];
            $countval = $DB->get_records_sql($sqlquery, $paramarray);
            $val = $DB->get_records_sql($sqlquery, $paramarray, 0, 5);
            array_push($userenroldata, $val);
        }

        $sql2 = 'SELECT ue.*
               FROM mdl_enrol AS e, mdl_user_enrolments AS ue
              WHERE e.courseid = :cid
                AND ue.enrolid = e.id
           ORDER BY timecreated DESC';
        $userenroldata = [];
        $params2 = $param = ['cid' => $courseid];
        $userenrolments = $DB->get_records_sql($sql2, $params2);
        $usercount = count($userenrolments);
        $userenrolments = $DB->get_records_sql($sql2, $params2, 0, 5);
        array_push($userenroldata, $userenrolments);

        if (isset($enroldata)) {

            $userlist = '';
            foreach ($userenroldata as $key => $value) {

                $el = array_values($value);
                for ($i = 0; $i < count($el); $i++) {
                    $userlist .= html_writer::start_tag('li');
                    $user = $DB->get_record('user', ['id' => $el[$i]->userid], '*');
                    $userlist .= html_writer::start_tag('span');
                    $userlist .= html_writer::nonempty_tag('span', $OUTPUT->user_picture($user, ['courseid' => $courseid]));
                    $userlist .= $user->firstname . ' ' . $user->lastname;
                    $userlist .= html_writer::end_tag('span');
                    $userlist .= html_writer::end_tag('li'); // User_card_element.
                }
            }

            $participantsurl = new moodle_url('/course/format/mooin4/participants.php', ['id' => $courseid]);
            $participantslink = html_writer::link(
                $participantsurl,
                get_string('show_all_infos', 'format_mooin4'),
                ['title' => get_string('participants', 'format_mooin4')]
            );
        } else {
            $out .= html_writer::div(get_string('no_user', 'format_mooin4'), 'no_user_class');
        }

        $context = context_course::instance($courseid);
        $hascapability = has_capability('moodle/course:viewparticipants', $context);
        $singleuser = $usercount == 1 ? true : false;
        $templatecontext = [
            'user_count' => $usercount,
            'user_list' => $userlist,
            'has_capability' => $hascapability,
            'singleuser' => $singleuser,
        ];

        return $templatecontext;
    }

    /**
     * Get the last News in the course
     * @param int $courseid
     * @param string $forumtype
     * @return array
     */
    public static function get_last_news($courseid, $forumtype) {
        global $DB, $OUTPUT, $USER;

        $sql = 'SELECT fp.*, f.id as forumid
                FROM {forum_posts} fp,
                    {forum_discussions} fd,
                    {forum} f
                WHERE fp.discussion = fd.id
                AND fd.forum = f.id
                AND f.course = :courseid
                AND (fp.mailnow = 1 OR fp.created < :wait) ';
        if ($forumtype == 'news') {
            $sql .= 'AND f.type = :news ';
        } else {
            $sql .= 'AND f.type != :news ';
        }
        $sql .= 'ORDER BY fp.created DESC LIMIT 1 ';

        // Mod tinjohn - time() - 1800 - not sure why and does not work well with the rest.
        $params = [
            'courseid' => $courseid,
            'news' => 'news',
            'wait' => time() - 1800,
        ];
        $latestpost = $DB->get_record_sql($sql, $params);

        if (!empty($latestpost = $DB->get_record_sql($sql, $params))) {
            $newsforumpost = $latestpost;

            $user = $DB->get_record('user', ['id' => $newsforumpost->userid], '*');
            $creatednews = date("d.m.Y, G:i", date((int)$newsforumpost->created));

            if ($forum = $DB->get_record('forum', ['course' => $courseid, 'type' => 'news'])) {
                if ($module = $DB->get_record('modules', ['name' => 'forum'])) {
                    if ($cm = $DB->get_record('course_modules', ['module' => $module->id, 'instance' => $forum->id])) {
                        $newsurl = new moodle_url('/mod/forum/view.php', ['id' => $cm->id]);
                    }
                }
            }

            if ($forumtype == 'news') {
                $unreadnewsnumber = self::count_unread_posts($USER->id, $courseid, true);
                $newnews = false;

            }

            $forumdiscussionurl = new moodle_url('/mod/forum/discuss.php', ['d' => $newsforumpost->discussion]);
            $templatecontext = [
                'news_url' => $newsurl,
                'user_firstname' => $user->firstname,
                'created_news' => $creatednews,
                'user_picture' => $OUTPUT->user_picture($user, ['courseid' => $courseid]),
                'news_title' => $newsforumpost->subject,
                'news_text' => $newsforumpost->message,
                'discussion_url' => $forumdiscussionurl,
                'unread_news_number' => $unreadnewsnumber,
            ];
            return $templatecontext;
        }
    }

    /**
     * Count unread posts for a user.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @param bool $news Whether to count news posts only.
     * @param int $forumid Optional forum ID.
     * @return int The number of unread posts.
     */
    public static function count_unread_posts($userid, $courseid, $news = false, $forumid = 0) {
        global $DB, $USER;

        // SQL query to get all unread posts.
        $sql = 'SELECT DISTINCT fp.id AS uniqueid, fp.*, f.id as forumid, fd.groupid, fd.id as discussionid, cm.id as cmid
                FROM {forum_posts} fp
                JOIN {forum_discussions} fd ON fp.discussion = fd.id
                JOIN {forum} f ON fd.forum = f.id
                JOIN {course_modules} cm ON cm.instance = f.id
                WHERE f.course = :courseid
                AND cm.visible = 1
                AND (fp.mailnow = 1 OR fp.created < :wait) ';

        if ($forumid > 0) {
            $sql .= 'AND f.id = :forumid ';
        } else if ($news) {
            $sql .= 'AND f.type = :news ';
        } else {
            $sql .= 'AND f.type != :news ';
        }

        $sql .= 'AND fp.id NOT IN (SELECT postid FROM {forum_read} WHERE userid = :userid)';

        $params = [
            'courseid' => $courseid,
            'news' => 'news',
            'userid' => $userid,
            'forumid' => $forumid,
            'wait' => time() - 1800,
        ];

        $unreadposts = $DB->get_recordset_sql($sql, $params);
        $visibleunreadposts = 0;

        // Check visibility of each post.
        $ids = [];

        foreach ($unreadposts as $post) {

            $forum = $DB->get_record('forum', ['id' => $post->forumid]);
            $discussion = $DB->get_record('forum_discussions', ['id' => $post->discussionid]);
            $cm = get_coursemodule_from_instance('forum', $forum->id, $courseid);

            // Check if the user can see the post.
            if (forum_user_can_see_post($forum, $discussion, $post, $USER, $cm)) {
                // Fix: check if the post is already in the list.
                if (!in_array($post->id, $ids)) {
                    $visibleunreadposts++;
                }
                $ids[] = $post->id;
            }
        }

        return $visibleunreadposts;
    }


    /**
     * Get the last forum discussion in the course
     * @param int $courseid
     * @param string $forumtype
     * @return array
     */
    public static function get_last_forum_discussion($courseid, $forumtype) {
        global $DB, $OUTPUT, $USER;

        $sql = "SELECT fp.*, f.id as forumid, fd.groupid, fd.id as discussionid, cm.id as cmid
                FROM {forum_posts} fp
                JOIN {forum_discussions} fd ON fp.discussion = fd.id
                JOIN {forum} f ON fd.forum = f.id
                JOIN {course_modules} cm ON cm.instance = f.id
                WHERE f.course = :courseid
                AND (fp.mailnow = 1 OR fp.created < :wait)
                AND f.type != :news
                AND cm.module = (SELECT id FROM {modules} WHERE name = 'forum')
                ORDER BY fp.created DESC LIMIT 1";

        $params = [
            'courseid' => $courseid,
            'news' => 'news',
            'wait' => time() - 1800,
        ];

        $latestposts = $DB->get_records_sql($sql, $params);

        if (!empty($latestposts)) {
            foreach ($latestposts as $post) {
                $forum = $DB->get_record('forum', ['id' => $post->forumid]);
                $discussion = $DB->get_record('forum_discussions', ['id' => $post->discussionid]);
                $cm = get_coursemodule_from_instance('forum', $forum->id, $courseid);

                if (forum_user_can_see_post($forum, $discussion, $post, $USER, $cm)) {
                    $user = $DB->get_record('user', ['id' => $post->userid], '*');
                    $creatednews = date("d.m.Y, G:i", date((int)$post->created));
                    $unreadforumnumber = self::count_unread_posts($USER->id, $courseid, false);

                    $forumdiscussionurl = new moodle_url('/mod/forum/discuss.php', ['d' => $post->discussion]);
                    $templatecontext = [
                        'user_firstname' => $user->firstname,
                        'created_news' => $creatednews,
                        'user_picture' => $OUTPUT->user_picture($user, ['courseid' => $courseid]),
                        'news_title' => $post->subject,
                        'news_text' => $post->message,
                        'discussion_url' => $forumdiscussionurl,
                        'unread_news_number' => $unreadforumnumber,
                        'new_news' => false,
                        'small_countcontainer' => false,
                    ];
                    return $templatecontext;
                }
            }
        }

        // Default context if no posts are found or accessible.
        $templatecontext = [
            'unread_news_number' => 0,
            'no_discussions_available' => true,
            'no_news' => false,
            'new_news' => false,
        ];

        return $templatecontext;
    }




    /**
     * Get the right user picture for creating forum
     * @param int $courseid
     * @return object of user
     */
    public static function user_print_forum($courseid) {
        global $DB, $USER;

        $sql = 'SELECT * FROM mdl_forum WHERE course = :cid ORDER BY ID DESC '; // Note: LIMIT 1.
        $param = ['cid' => $courseid];

        $forumincourse = $DB->get_records_sql($sql, $param, IGNORE_MISSING);
        // Get the forum_discussion data.
        $sqlinforum = 'SELECT * FROM mdl_forum_discussions ORDER BY ID DESC LIMIT 1'; // Note: WHERE forum = :id.

        $discussforumincourse = $DB->get_record_sql($sqlinforum, [], IGNORE_MISSING);

        $result = new stdClass;
        if ($discussforumincourse->userid == $discussforumincourse->usermodified) {
            $result = $DB->get_record('user', ['id' => $discussforumincourse->userid]);
        } else {
            $result = $DB->get_record('user', ['id' => $discussforumincourse->usermodified]);
        }

        return $result;
    }

    /**
     * Set a discussion as viewed for a user.
     *
     * @param int $userid The user ID.
     * @param int $forumid The forum ID.
     * @param int $discussionid The discussion ID.
     * @return void
     */
    public static function set_discussion_viewed($userid, $forumid, $discussionid) {
        global $DB;

        $posts = $DB->get_records('forum_posts', ['discussion' => $discussionid]);
        foreach ($posts as $post) {
            if (!$read = $DB->get_record('forum_read', [
                'userid' => $userid,
                'forumid' => $forumid,
                'discussionid' => $discussionid,
                'postid' => $post->id,
            ])) {
                $read = new stdClass();
                $read->userid = $userid;
                $read->forumid = $forumid;
                $read->discussionid = $discussionid;
                $read->postid = $post->id;
                $read->firstread = time();
                $read->lastread = $read->firstread;
                $DB->insert_record('forum_read', $read);
            }
        }
    }

    /**
     * Set a section as a chapter.
     *
     * @param int $sectionid The section ID.
     */
    public static function set_chapter($sectionid) {
        global $DB;

        if ($DB->get_record('format_mooin4_chapter', ['sectionid' => $sectionid])) {
            return;
        }

        if ($csection = $DB->get_record('course_sections', ['id' => $sectionid])) {
            $csectiontitle = $csection->name;
        } else {
            return;
        }

        if (!$csectiontitle) {
            $csectiontitle = get_string('new_chapter', 'format_mooin4');
        }

        $chapter = new stdClass();
        $chapter->courseid = $csection->course;
        $chapter->title = $csectiontitle;
        $chapter->sectionid = $sectionid;
        $chapter->chapter = 0;
        $DB->insert_record('format_mooin4_chapter', $chapter);

        self::sort_course_chapters($csection->course);
    }

    /**
     * Unset a section as a chapter.
     *
     * @param int $sectionid The section ID.
     */
    public static function unset_chapter($sectionid) {
        global $DB;

        $DB->delete_records('format_mooin4_chapter', ['sectionid' => $sectionid]);
        if ($csection = $DB->get_record('course_sections', ['id' => $sectionid])) {
            self::sort_course_chapters($csection->course);
        }
    }

    /**
     * Sort course chapters.
     *
     * @param int $courseid The course ID.
     */
    public static function sort_course_chapters($courseid) {
        global $DB;
        $coursechapters = self::get_course_chapters($courseid);
        $number = 0;
        foreach ($coursechapters as $coursechapter) {
            $number++;
            if ($existingcoursechapter = $DB->get_record('format_mooin4_chapter', ['id' => $coursechapter->id])) {
                $existingcoursechapter->chapter = $number;
                $DB->update_record('format_mooin4_chapter', $existingcoursechapter);
            }
        }
    }

    /**
     * Get the last section number of a course.
     *
     * @param int $courseid The course ID.
     * @return int The last section number.
     */
    public static function get_last_section($courseid) {
        global $DB;

        $lastsection = 0;
        $count = $DB->count_records('course_sections', ['course' => $courseid]);

        if ($count > 0) {
            $lastsection = $count - 1;
        }

        return $lastsection;
    }



    /**
     * Get the prefix for a section.
     *
     * @param stdClass $section The section object.
     * @return string The prefix.
     */
    public static function get_section_prefix($section) {
        global $DB, $USER, $sections;

        if (isset($sections[$section->id]) && isset($sections[$section->id]->prefix)) {
            return $sections[$section->id]->prefix;
        }

        $sectionprefix = '';

        // Get parent chapter of the section.
        $parentchapter = self::get_parent_chapter($section);
        if (is_object($parentchapter)) {
            // Get section ids for the chapter.
            $sids = $parentchapter->sectionids;

            // Get the course and format.
            $course = get_course($section->course);
            $format = course_get_format($course);
            $modinfo = get_fast_modinfo($course, $USER->id);
            $context = context_course::instance($course->id);

            $visiblecount = 0;

            require_once(__DIR__ . '/../../lib.php');
            $courseid = $course->id;
            // Calc lesson numbers only if nessessary.
            if (get_toggle_section_number_visibility($courseid) === 1) {

                foreach ($sids as $sid) {
                    $sectioninfo = $modinfo->get_section_info_by_id($sid);
                    $isvisible = ($sectioninfo && $format->is_section_visible($sectioninfo));

                    if ($sid == $section->id) {
                        if (!$section->visible) {
                            $sectionprefix = 'ausgeblendet';
                        } else {
                            $visiblecount += 1;
                            $sectionprefix = $parentchapter->chapter . '.' . $visiblecount;
                        }
                        break;
                    }

                    if ($isvisible && $sectioninfo->visible) {
                        $visiblecount += 1;
                    }
                }
            } else {
                $sectionprefix = '';
            }
        }

        if (isset($sections[$section->id])) {
            $sections[$section->id]->prefix = $sectionprefix;
        }

        return $sectionprefix;
    }


    /**
     * Get the parent chapter for a section.
     *
     * @param stdClass $section The section object.
     * @return stdClass|bool The parent chapter object or false if not found.
     */
    public static function get_parent_chapter($section) {
        global $DB;
        global $chapters;
        global $sections;
        if (isset($sections[$section->id])) {
            if (isset($chapters[$sections[$section->id]->parentchapterid])) {
                return $chapters[$sections[$section->id]->parentchapterid];
            }
        }

        $chaptersrecords = $DB->get_records('format_mooin4_chapter', ['courseid' => $section->course]);
        foreach ($chaptersrecords as $chapter) {
            if (isset($chapters[$chapter->id]) && isset($chapters[$chapter->id]->sectionids)) {
                $sids = $chapters[$chapter->id]->sectionids;
            } else {
                $sids = self::get_sectionids_for_chapter($chapter->id);
            }
            if (in_array($section->id, $sids)) {
                $chapter->sectionids = $sids;
                $chapters[$chapter->id] = $chapter;
                $section->parentchapterid = $chapter->id;
                $sections[$section->id] = $section;
                return $chapter;
            }
        }

        return false;
    }

    /**
     * Get section IDs for a chapter.
     *
     * @param int $chapterid The chapter ID.
     * @return array Array of section IDs.
     */
    public static function get_sectionids_for_chapter($chapterid) {

        global $DB;
        $sectionids = [];
        if ($chapter = $DB->get_record('format_mooin4_chapter', ['id' => $chapterid])) {

            if ($chaptersection = $DB->get_record('course_sections', ['id' => $chapter->sectionid])) {
                if ($nextchapters = $DB->get_records(
                    'format_mooin4_chapter',
                    ['courseid' => $chapter->courseid, 'chapter' => $chapter->chapter + 1]
                )) {
                    // There is a bug somewhere - the mooin4_chapter table is not updated correctly.
                    // It contains chapters with sectionids that are not in the course or elsewhere.
                    foreach ($nextchapters as $nextchapter) {
                        if ($DB->get_record(
                            'course_sections',
                            ['id' => $nextchapter->sectionid, 'course' => $chapter->courseid]
                        )) {
                            break;
                        }
                    }

                    if ($nextchaptersection = $DB->get_record('course_sections', ['id' => $nextchapter->sectionid])) {
                        $sql = 'SELECT cs.id
                                FROM {course_sections} cs
                                WHERE cs.course = :courseid
                                AND cs.section > :chaptersection
                                AND cs.section < :nextchaptersection;';
                        $params = [
                            'courseid' => $chapter->courseid,
                            'chaptersection' => $chaptersection->section,
                            'nextchaptersection' => $nextchaptersection->section,
                        ];
                    }
                } else {
                    $sql = 'SELECT cs.id
                            FROM {course_sections} cs
                            WHERE cs.course = :courseid
                            AND cs.section > :chaptersection;';
                    $params = ['courseid' => $chapter->courseid, 'chaptersection' => $chaptersection->section];
                }
                $sectionids = $DB->get_fieldset_sql($sql, $params);
            }
        }
        return $sectionids;
    }

    /**
     * Get all chapters for a course.
     *
     * @param int $courseid The course ID.
     * @return array Array of chapter records.
     */
    public static function get_course_chapters($courseid) {
        global $DB;

        $sql = 'SELECT c.*, s.section
                  FROM {format_mooin4_chapter} c, {course_sections} s
                 WHERE s.course = :courseid
                   and s.id = c.sectionid
              order by s.section asc';

        $params = ['courseid' => $courseid];

        $coursechapters = $DB->get_records_sql($sql, $params);

        return $coursechapters;
    }

    /**
     * Check if a section is the first section of a chapter.
     *
     * @param int $sectionid The section ID.
     * @return bool True if first section, false otherwise.
     */
    public static function is_first_section_of_chapter($sectionid) {
        global $DB;

        if ($section = $DB->get_record('course_sections', ['id' => $sectionid])) {

            $chapters = self::get_course_chapters($section->course);

            $course = get_course($section->course);
            $format = course_get_format($course);

            foreach ($chapters as $c) {

                $nextsections = $DB->get_records_sql(
                    "SELECT * FROM {course_sections}
                     WHERE course = :courseid AND section > :chaptersection
                     ORDER BY section ASC",
                    ['courseid' => $section->course, 'chaptersection' => $c->section]
                );

                foreach ($nextsections as $nextsection) {
                    $sectioninfo = get_fast_modinfo($course)->get_section_info($nextsection->section);
                    if ($format->is_section_visible($sectioninfo)) {

                        if ($nextsection->id == $sectionid) {
                            return true;
                        }
                        break;
                    }
                }
            }
        }
        return false;
    }


    /**
     * Check if a section is the last visible section of a chapter.
     *
     * @param int $sectionid The section ID.
     * @return bool True if last section, false otherwise.
     */
    public static function is_last_section_of_chapter($sectionid) {
        global $DB;

        if ($section = $DB->get_record('course_sections', ['id' => $sectionid])) {
            $course = get_course($section->course);
            $format = course_get_format($course);
            // Tinjohn get_parent_chapter returns false if there was no.
            // But from lib.php function update_course_format_options sections without parents are not allowed.
            $parentchapter = self::get_parent_chapter($section);
            // Added  tinjohn.
            if (!$parentchapter) {
                return false;
            }
            $sectionids = self::get_sectionids_for_chapter($parentchapter->id);
            $highestvisiblesection = null;
            foreach ($sectionids as $sectionid) {
                if ($s = $DB->get_record('course_sections', ['id' => $sectionid])) {
                    $sectioninfo = get_fast_modinfo($course)->get_section_info($s->section);
                    if ($format->is_section_visible($sectioninfo) && $s->section > $highestvisiblesection) {
                        $highestvisiblesection = $s->section;
                    }
                }
            }
            return $section->section == $highestvisiblesection;
        }
        return false;
    }

    /**
     * Get the chapter for a section.
     *
     * @param int $sectionid The section ID.
     * @return int|null The chapter number or null if not found.
     */
    public static function get_chapter_for_section($sectionid) {
        global $DB;
        $chapter = null;
        if ($section = $DB->get_record('course_sections', ['id' => $sectionid])) {
            $chapters = self::get_course_chapters($section->course);

            foreach ($chapters as $c) {
                if ($section->section > $c->section && ($chapter = null || $c->section > $chapter)) {
                    $chapter = $c->chapter;
                }
            }
        }
        return $chapter;
    }

    /**
     * Get the course navigation bar.
     *
     * @return string Rendered navigation bar HTML.
     */
    public static function course_navbar() {
        global $PAGE, $OUTPUT, $COURSE;

        $items = $PAGE->navbar->get_items();

        if (!$items) {
            $message = "no breadcrumb for section 0 for testing ";
            \core\notification::warning($message);
            return;
        }
        $courseitems = [];

        // Split the navbar array at coursehome.
        foreach ($items as $item) {
            if ($item->key === $COURSE->id) {
                $courseitems = array_splice($items, intval(array_search($item, $items)));
            }
        }
        // Mod for check tinjohn.
        $courseitems[0]->add_class('course-title');
        $courseitems[0]->text = $COURSE->fullname;
        $sectionnode = $courseitems[array_key_last($courseitems)];
        $sectionnode->action = null;
        $text = $sectionnode->text;
        $parts = explode(':', $text, 2);
        $result = trim($parts[0]) . ':';
        $text = $sectionnode->text = $result;

        // Provide custom templatecontext for the new Navbar.
        $templatecontext = [
            'get_items' => $courseitems,
        ];

        return $OUTPUT->render_from_template('format_mooin4/custom_navbar', $templatecontext);
    }

    /**
     * Get the subpage navigation bar.
     *
     * @return string Rendered navigation bar HTML.
     */
    public static function subpage_navbar() {
        global $PAGE, $OUTPUT, $COURSE;
        $items = $PAGE->navbar->get_items();
        $courseitems = [];

        // Split the navbar array at coursehome.
        foreach ($items as $item) {
            if ($item->key === $COURSE->id) {
                $courseitems = array_splice($items, intval(array_search($item, $items)));
            }
        }
        $courseitems[0]->text = $COURSE->fullname;

        $lastnode = $courseitems[array_key_last($courseitems)];
        $lastnode->action = null;
        $lastnode->shorttext = $lastnode->text;

        // Provide custom templatecontext for the new Navbar.
        $templatecontext = [
            'get_items' => $courseitems,
        ];

        return $OUTPUT->render_from_template('format_mooin4/custom_navbar', $templatecontext);
    }

    /**
     * Get chapter information including completion status.
     *
     * @param stdClass $chapter The chapter object.
     * @return array Chapter info with 'completed' and 'lastvisited' keys.
     */
    public static function get_chapter_info($chapter) {
        global $USER, $DB, $chapters;
        $info = [];

        $chaptercompleted = false;
        $lastvisited = false;

        if (isset($chapters[$chapter->id]->sectionids)) {
            $sectionids = $chapters[$chapter->id]->sectionids;
        } else {
            $sectionids = self::get_sectionids_for_chapter($chapter->id);
        }
        $completedsections = 0;

        foreach ($sectionids as $sectionid) {
            $section = $DB->get_record('course_sections', ['id' => $sectionid]);
            if ($section && self::is_section_completed($chapter->courseid, $section)) {
                $completedsections++;
            }

            $lastsection = get_user_preferences('format_mooin4_last_section_in_course_' . $chapter->courseid, 0, $USER->id);
            if ($record = $DB->get_record('course_sections', ['course' => $chapter->courseid, 'section' => $lastsection])) {
                if ($record->id == $sectionid) {
                    $lastvisited = true;
                }
            }
        }
        if ($completedsections == count($sectionids)) {
            $chaptercompleted = true;
        } else {
            $chaptercompleted = false;
        }
        $info['completed'] = $chaptercompleted;
        $info['lastvisited'] = $lastvisited;
        return $info;
    }

    /**
     * Check if a section is completed.
     *
     * @param int $courseid The course ID.
     * @param stdClass $section The section object.
     * @return bool True if section is completed, false otherwise.
     */
    public static function is_section_completed($courseid, $section) {
        global $USER, $DB;
        $result = false;
        if (self::get_section_progress($courseid, $section->id, $USER->id) == 100) {
            $result = true;
        } else {
            $result = false;
        }

        return $result;
    }

    /**
     * Get the progress percentage for a section.
     *
     * @param int $courseid The course ID.
     * @param int $sectionid The section ID.
     * @param int $userid The user ID.
     * @return int The progress percentage (0-100).
     */
    public static function get_section_progress($courseid, $sectionid, $userid) {
        global $DB, $CFG, $SESSION;

        require_once($CFG->libdir . '/gradelib.php');

        $sessionlang = isset($SESSION->lang) ? $SESSION->lang : null;

        $percentage = 0;

        // No activities in this section?
        $coursemodules = $DB->get_records('course_modules', [
            'course' => $courseid,
            'deletioninprogress' => 0,
            'section' => $sectionid,
            'visible' => 1,
        ]);

        $activities = 0;

        foreach ($coursemodules as $coursemodule) {
            $modinfo = get_fast_modinfo($courseid, $userid);
            $cm = $modinfo->get_cm($coursemodule->id);
            $info = new \core_availability\info_module($cm);
            $warnings = [];
            $isavailable = $info->is_available($warnings, false, $userid);
            $skip = false;

            $modulename = '';
            if ($module = $DB->get_record('modules', ['id' => $coursemodule->module])) {
                $modulename = $module->name;
            }
            $storedprogress = null;
            if (!empty($coursemodule->id)) {
                $storedprogress = get_user_preferences('format_mooin4_hvp_progress_cmid_' . $coursemodule->id, null, $userid);
            }
            // Fallback for hvp: try to get progress by instance ID.
            if ($storedprogress === null && $modulename === 'hvp') {
                $storedprogress = get_user_preferences('format_mooin4_hvp_progress_' . $coursemodule->instance, null, $userid);
            }
            // For h5pactivity, we only use cmid-based storage (no fallback needed).

            $istrackedh5p = in_array($modulename, ['hvp', 'h5pactivity']);
            $completionrequired = ($coursemodule->completion == 2);
            $completionview = ($coursemodule->completion == 1); // z.B. Textseiten, Labels

            // Check whether a completion=1 activity has been manually marked as done.
            // Applies to ALL activity types (including H5P/HVP).
            $completionrecord = null;
            if ($completionview) {
                $completionrecord = $DB->get_record('course_modules_completion', [
                    'coursemoduleid' => $coursemodule->id,
                    'userid'         => $userid,
                ]);
            }
            $ismanuallycompletedview = ($completionview && $completionrecord && $completionrecord->completionstate != 0);

            // Skip only activities with no completion tracking at all (completion=0, no stored progress).
            // H5P activities with completion=1 (manual) are now always counted.
            if (!$completionrequired && !$completionview && $storedprogress === null) {
                continue;
            }

            // Check availability based on language user uses in session
            // For H5P activities, we still want to count them even if language doesn't match.
            if ($coursemodule->availability !== null && !$istrackedh5p) {
                $data = json_decode($coursemodule->availability, true);
                if (isset($data['c']) && is_array($data['c'])) {
                    foreach ($data['c'] as $item) {
                        if (isset($item['type']) && $item['type'] === 'language'
                            && isset($item['id']) && $item['id'] !== $sessionlang
                        ) {
                            $skip = true;
                            break;
                        }
                    }
                }
            }

            // For H5P activities, always count them even if not available (they will count as 0%)
            // For other activities, skip if not available.
            if (!$istrackedh5p && ($skip || !$isavailable)) {
                continue;
            }

            // Always increment activities counter - this ensures both H5P types are counted.
            $activities++;

            // Activity is hvp, we use the grades to get the individual progress.
            if ($modulename == 'hvp') {
                if ($storedprogress !== null) {
                    $percentage += (float)$storedprogress;
                } else {
                    $gradinginfo = grade_get_grades($courseid, 'mod', 'hvp', $coursemodule->instance, $userid);
                    if (!empty($gradinginfo->items)) {
                        $grade = $gradinginfo->items[0]->grades[$userid]->grade;
                        $grademax = $gradinginfo->items[0]->grademax;
                        if (isset($grade) && $grade != 0) {
                            $percentage += 100 / ($grademax / $grade);
                        } else if ($ismanuallycompletedview) {
                            // Fallback: no grade yet, but manually marked as done → 100%.
                            $percentage += 100;
                        } else {
                            // No grade yet, add 0% to ensure activity is counted in average.
                            $percentage += 0;
                        }
                    } else if ($ismanuallycompletedview) {
                        // No gradebook entry, but manually marked as done → 100%.
                        $percentage += 100;
                    } else {
                        // No gradebook entry yet, add 0% to ensure activity is counted in average.
                        $percentage += 0;
                    }
                }
            } else if ($modulename == 'h5pactivity') {
                // Priority 1: Stored progress (cache from user preferences).
                if ($storedprogress !== null) {
                    $percentage += (float)$storedprogress;
                } else {
                     // Priority 2: Gradebook.
                    $gradinginfo = grade_get_grades($courseid, 'mod', 'h5pactivity', $coursemodule->instance, $userid);
                    if (!empty($gradinginfo->items) && !empty($gradinginfo->items[0]->grades[$userid])) {
                        $grade = $gradinginfo->items[0]->grades[$userid]->grade;
                        $grademax = $gradinginfo->items[0]->grademax;
                        if (isset($grade) && $grade !== null && $grademax && $grademax > 0) {
                            // Calculate percentage from actual grade (even if 0).
                            $percentage += 100 / ($grademax / $grade);
                        } else {
                            $percentage += 0;
                        }
                    } else {
                        // Priority 3: Erledigt markiert (completionstate = 1) ODER Bestanden (completionstate = 2).
                        // Mit completionstate != 0 zählen wir beide Fälle: sowohl manuell als Erledigt
                        // markiert (ohne Aufgabe gelöst) als auch automatisch bestanden.
                        $sql = 'SELECT *
                                  FROM {course_modules_completion}
                                 WHERE coursemoduleid = :coursemoduleid
                                   AND userid = :userid
                                   AND completionstate != 0';
                        $params = [
                            'coursemoduleid' => $coursemodule->id,
                            'userid' => $userid,
                        ];
                        if ($DB->get_record_sql($sql, $params)) {
                            $percentage += 100;
                        } else {
                            $percentage += 0;
                        }
                    }
                }
            } else {
                if (!$completionrequired && $storedprogress !== null) {
                    $percentage += (float)$storedprogress;
                    continue;
                }
                // Für completion=1 (View-based / manuell): Als 100% zählen wenn als Erledigt markiert.
                if ($ismanuallycompletedview) {
                    $percentage += 100;
                    continue;
                }
                // If completed (completion=2), add to percentage.
                $sql = 'SELECT *
                              FROM {course_modules_completion}
                             WHERE coursemoduleid = :coursemoduleid
                               AND userid = :userid
                               AND completionstate != 0 ';
                $params = [
                    'coursemoduleid' => $coursemodule->id,
                    'userid' => $userid,
                ];
                if ($DB->get_record_sql($sql, $params)) {
                    $percentage += 100;
                }
            }
        }

        // No activities with completion activated?
        if ($activities == 0) {
            if (get_user_preferences('format_mooin4_section_completed_' . $sectionid, 0, $userid) == 1) {
                return 100;
            } else {
                return 0;
            }
        }

        return round($percentage / $activities);
    }

    /**
     * Get the unenrol URL for a course.
     *
     * @param int $courseid The course ID.
     * @return moodle_url|bool The unenrol URL or false if not available.
     */
    public static function get_unenrol_url($courseid) {
        global $DB, $USER, $CFG;

        if ($enrol = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'autoenrol', 'status' => 0])) {
            if ($userenrolment = $DB->get_record('user_enrolments', ['enrolid' => $enrol->id, 'userid' => $USER->id])) {
                $unenrolurl = new moodle_url($CFG->wwwroot . '/enrol/autoenrol/unenrolself.php?enrolid=' . $enrol->id);
                return $unenrolurl;
            }
        }

        if ($enrol = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'self', 'status' => 0])) {
            if ($userenrolment = $DB->get_record('user_enrolments', ['enrolid' => $enrol->id, 'userid' => $USER->id])) {
                $unenrolurl = new moodle_url($CFG->wwwroot . '/enrol/self/unenrolself.php?enrolid=' . $enrol->id);
                return $unenrolurl;
            }
        }

        return false;
    }

    /**
     * Check if a course has been started by the user.
     *
     * @param stdClass $course The course object.
     * @return bool True if course has been started, false otherwise.
     */
    public static function is_course_started($course) {
        global $DB;
        global $USER;
        $lastsection = get_user_preferences('format_mooin4_last_section_in_course_' . $course->id, 0, $USER->id);
        if ($lastsection) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get the continue section for a course.
     *
     * @param stdClass $course The course object.
     * @return int|bool The section prefix or 2 or false.
     */
    public static function get_continue_section($course) {
        global $DB;
        global $USER;

        $lastsection = get_user_preferences('format_mooin4_last_section_in_course_' . $course->id, 0, $USER->id);

        if ($lastsection) {
            if ($lastsection == 0 || $lastsection == 1) {
                $lastsection = 2;
            }

            if ($continuesection = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $lastsection])) {
                return self::get_section_prefix($continuesection);
            } else {
                return false;
            }
        } else {
            return 2;
        }
    }

    /**
     * Get the continue URL for a course.
     *
     * @param stdClass $course The course object.
     * @return moodle_url The URL to continue the course.
     */
    public static function get_continue_url($course) {
        global $DB;
        global $USER;

        $lastsection = get_user_preferences('format_mooin4_last_section_in_course_' . $course->id, 0, $USER->id);

        if ($lastsection) {
            if ($lastsection == 0 || $lastsection == 1) {
                $lastsection = 2;
            }
            if ($continuesection = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $lastsection])) {
                return new moodle_url('/course/view.php', ['id' => $course->id, 'section' => $continuesection->section]);
            } else {
                return new moodle_url('/course/view.php', ['id' => $course->id, 'section' => 2]);
            }
        } else {
            return new moodle_url('/course/view.php', ['id' => $course->id, 'section' => 2]);
        }
    }

    /**
     * Returns url for headerimage
     *
     * @param int $courseid
     * @param bool $mobile true if mobile header image is required or false for desktop image
     * @return string|bool String with url or false if no image exists
     */
    public static function get_headerimage_url($courseid, $mobile = true) {
        global $DB;
        $context = context_course::instance($courseid);
        $filearea = 'headerimagemobile';
        if (!$mobile) {
            $filearea = 'headerimagedesktop';
        }
        $filename = '';
        $sql = 'select 0, filename
                  from {files}
                 where contextid = :contextid
                   and component = :component
                   and filearea = :filearea
                   and itemid = :courseid
                   and mimetype like :mimetype';

        $params = [
            'contextid' => $context->id,
            'component' => 'format_mooin4',
            'filearea' => $filearea,
            'courseid' => $courseid,
            'mimetype' => 'image/%',
        ];

        $records = $DB->get_records_sql($sql, $params);

        if (count($records) == 1) {
            $filename = $records[0]->filename;
        } else {
            return false;
        }

        $url = new moodle_url(
            '/pluginfile.php/' . $context->id . '/format_mooin4/' . $filearea . '/' . $courseid . '/0/' . $filename
        );
        return $url;
    }

    /**
     * Get course certificates for a user.
     *
     * @param int $courseid The course ID.
     * @param int $userid The user ID.
     * @return array Array of certificate objects.
     */
    public static function get_course_certificates($courseid, $userid) {
        global $DB, $CFG;

        $certificates = [];
        $dbman = $DB->get_manager();

        // Ilddigitalcert.
        $table = new xmldb_table('ilddigitalcert');
        if ($dbman->table_exists($table) && $ilddigitalcerts = $DB->get_records('ilddigitalcert', ['course' => $courseid])) {
            // Get user enrolment id.
            $ueid = 0;
            $sql = 'SELECT ue.*
                  FROM {enrol} e,
                       {user_enrolments} ue
                 WHERE e.courseid = :courseid
                   AND e.id = ue.enrolid
                   AND ue.userid = :userid
                   AND ue.status = 0 ';
            $params = ['courseid' => $courseid, 'userid' => $userid];
            if ($ue = $DB->get_record_sql($sql, $params)) {
                $ueid = $ue->id;
            }

            // Get all certificates in course.
            foreach ($ilddigitalcerts as $ilddigitalcert) {
                $certificate = new stdClass();
                $certificate->userid = 0;
                $certificate->url = '#';
                $certificate->name = $ilddigitalcert->name;

                // Is certificate issued to user?
                $sql = 'SELECT di.id, di.cmid
                      FROM {ilddigitalcert_issued} di,
                           {course_modules} cm
                     WHERE cm.instance = :ilddigitalcertid
                       AND di.cmid = cm.id
                       AND di.userid = :userid
                       AND di.enrolmentid = :ueid
                     LIMIT 1 ';
                $params = [
                    'ilddigitalcertid' => $ilddigitalcert->id,
                    'userid' => $userid,
                    'ueid' => $ueid,
                ];
                if ($issued = $DB->get_record_sql($sql, $params)) {
                    $certificate->userid = $userid;
                    $certificate->url = $CFG->wwwroot . '/mod/ilddigitalcert/view.php?id=' . $issued->cmid
                        . '&issuedid=' . $issued->id . '&ueid=' . $ueid;
                    $certificate->issuedid = $issued->id;
                    $certificate->certmod = 'ilddigitalcert';
                }
                $certificates[] = $certificate;
            }
        }

        // Coursecertificate.
        $table = new xmldb_table('coursecertificate');
        if ($dbman->table_exists($table) && $coursecertificates = $DB->get_records('coursecertificate', ['course' => $courseid])) {
            // Get all certificates in course.
            foreach ($coursecertificates as $coursecertificate) {
                $certificate = new stdClass();
                $certificate->userid = 0;
                $certificate->url = '#';
                $certificate->name = $coursecertificate->name;

                // Is certificate issued to user?
                if ($issued = $DB->get_record('tool_certificate_issues', ['userid' => $userid, 'courseid' => $courseid])) {
                    $url = '#';
                    $sql = 'SELECT *
                          FROM {modules} m , {course_modules} cm
                         WHERE m.name = :coursecertificate
                           AND cm.module = m.id
                           AND cm.instance = :coursecertificateid ';
                    $params = [
                        'coursecertificate' => 'coursecertificate',
                        'coursecertificateid' => $coursecertificate->id,
                    ];
                    if ($cm = $DB->get_record_sql($sql, $params)) {
                        $url = $CFG->wwwroot . '/mod/coursecertificate/view.php?id=' . $cm->id;
                    }

                    $certificate->userid = $userid;
                    $certificate->url = $url;
                    $certificate->issuedid = $issued->id;
                    $certificate->certmod = 'coursecertificate';
                }
                $certificates[] = $certificate;
            }
        }
        return $certificates;
    }



    /**
     * Count certificates for a user in a course.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @return array Array with completed and not completed counts.
     */
    public static function count_certificate($userid, $courseid) {
        /* We have to found the certificate module in the DB
            One for ilddigitalcertificate and the other for coursecertificate
        */
        global $DB;
        $completed = 0;
        $notcompleted = 0;
        $result = [];
        // Make the request into the module & course_module.
        $moduleilddigitalcert = $DB->get_record('modules', ['name' => 'ilddigitalcert']);
        $modulecoursecertificate = $DB->get_record('modules', ['name' => 'coursecertificate']);

        if ($moduleilddigitalcert == true) {
            // Make request into course_module.
            $cmilddigitalcertificate = $DB->get_records('course_modules', [
                'module' => $moduleilddigitalcert->id,
                'course' => $courseid,
            ]);
        } else {
            $cmilddigitalcertificate  = [];
        }
        if ($modulecoursecertificate == true) {
            // Make request into course_module.
            $cmcoursecertificate = $DB->get_records('course_modules', [
                'module' => $modulecoursecertificate->id,
                'course' => $courseid,
            ]);
        } else {
            $cmcoursecertificate  = [];
        }

        // Check if the module has been completed and save into module_completion table.
        if (isset($cmilddigitalcertificate)) {
            foreach ($cmilddigitalcertificate as $value) {
                $existcompletedcertificate = $DB->record_exists(
                    'course_modules_completion',
                    ['coursemoduleid' => ($value->id) - 1, 'userid' => $userid]
                );
                if ($existcompletedcertificate) {
                    $completed++;
                } else {
                    $notcompleted++;
                }
            }
        }
        if (isset($cmcoursecertificate)) {
            foreach ($cmcoursecertificate as $value) {
                $existcompletedcertificate = $DB->record_exists(
                    'course_modules_completion',
                    ['coursemoduleid' => ($value->id) - 1, 'userid' => $userid]
                );
                if ($existcompletedcertificate) {
                    $completed++;
                } else {
                    $notcompleted++;
                }
            }
        }

        $result = ['completed' => $completed, 'not_completed' => $notcompleted];
        return $result;
    }

    /**
     * Get badges HTML for a user.
     *
     * @param int $userid The user ID (0 for current user).
     * @param int $courseid The course ID (0 for all courses).
     * @param int $since Timestamp to get badges since.
     * @param bool $print Whether to print or return the HTML.
     * @return string|void HTML content or void if $print is true.
     */
    public static function get_badges_html($userid = 0, $courseid = 0, $since = 0, $print = true) {
        global $CFG, $PAGE, $USER, $SITE;
        require_once($CFG->dirroot . '/badges/renderer.php');

        // Determine context.
        if (isloggedin()) {
            $context = context_user::instance($USER->id);
        } else {
            $context = context_system::instance();
        }

        if ($userid == 0) {
            if ($since == 0) {
                $records = self::get_badge_records($courseid, null, null, null);
            } else {
                $records = self::get_badge_records_since($courseid, $since, false);
            }
            $renderer = new core_badges_renderer($PAGE, '');

            // Print local badges.
            if ($records) {
                if ($since == 0) {
                    self::print_badges_html($records);
                } else {
                    self::print_badges_html($records, true);
                }
            }
        } else if ($USER->id == $userid || has_capability('moodle/badges:viewotherbadges', $context)) {
            $records = badges_get_user_badges($userid, $courseid, null, null, null, true);
            $renderer = new core_badges_renderer($PAGE, '');

            // Print local badges.
            if ($records) {
                $right = $renderer->print_badges_list($records, $userid, true);
                if ($print) {
                    echo html_writer::tag('dd', $right);
                } else {
                    return html_writer::tag('dd', $right);
                }
            }
        }
    }

    /**
     * Get badge records since a specific time.
     *
     * @param int $courseid The course ID.
     * @param int $since Timestamp to get badges since.
     * @param bool $global Whether to get global badges.
     * @return array Array of badge records.
     */
    public static function get_badge_records_since($courseid, $since, $global = false) {
        global $DB, $USER;
        if (!$global) {
            $params = [];
            $sql = 'SELECT
                        b.*,
                        bi.id,
                        bi.badgeid,
                        bi.userid,
                        bi.dateissued,
                        bi.uniquehash
                    FROM
                        {badge} b,
                        {badge_issued} bi
                    WHERE b.id = bi.badgeid ';

            $sql .= ' AND b.courseid = :courseid';
            $params['courseid'] = $courseid;

            if ($since > 0) {
                $sql .= ' AND bi.dateissued > :since ';
                $since = time() - $since;
                $params['since'] = $since;
            }
            $sql .= ' ORDER BY bi.dateissued DESC ';
            $sql .= ' LIMIT 20 OFFSET 0 ';
            $badges = $DB->get_records_sql($sql, $params);
        } else {
            $params = ['courseid' => $courseid];
            $sql = 'SELECT
                        b.*,
                        bi.id,
                        bi.badgeid,
                        bi.userid,
                        bi.dateissued,
                        bi.uniquehash
                    FROM
                        {badge} b,
                        {badge_issued} bi,
                        {user_enrolments} ue,
                        {enrol} e
                    WHERE b.id = bi.badgeid
                    AND	bi.userid = ue.userid
                    AND ue.enrolid = e.id
                    AND e.courseid = :courseid ';

            $sql .= ' AND b.type = :type';
            $params['type'] = 1;

            if ($since > 0) {
                $sql .= ' AND bi.dateissued > :since ';
                $since = time() - $since;
                $params['since'] = $since;
            }
            $sql .= ' ORDER BY bi.dateissued DESC ';
            $sql .= ' LIMIT 0, 20 ';
            $badges = $DB->get_records_sql($sql, $params);
        }

        $correctbadges = [];
        foreach ($badges as $badge) {
            $badge->id = $badge->badgeid;

            // Nur wenn der Inhaber kein Teacher ist anzeigen.
            $coursecontext = context_course::instance($courseid);
            $roles = get_user_roles($coursecontext, $badge->userid, false);
            $notateacher = true;
            foreach ($roles as $role) {
                if ($role->shortname == 'editingteacher') {
                    $notateacher = false;
                }
            }
            if ($notateacher) {
                $correctbadges[] = $badge;
            }
        }
        return $correctbadges;
    }

    /**
     * Print badges HTML.
     *
     * @param array $records Array of badge records.
     * @param bool $details Whether to show user details.
     * @param bool $highlight Whether to highlight badges.
     * @param bool $badgename Whether to show badge name.
     */
    public static function print_badges_html($records, $details = false, $highlight = false, $badgename = false) {
        global $DB, $COURSE, $USER;
        // Sort by new layer.
        usort($records, function ($first, $second) {
            global $USER;
            if (!isset($first->issuedid)) {
                $first->issuedid = 0;
            }
            if (!isset($second->issuedid)) {
                $second->issuedid = 0;
            }
            $f = get_user_preferences('format_mooin4_new_badge_' . $first->issuedid, 0, $USER->id);
            $s = get_user_preferences('format_mooin4_new_badge_' . $second->issuedid, 0, $USER->id);
            if ($f < $s) {
                return 1;
            }
            if ($f == $s) {
                return 0;
            }
            if ($f > $s) {
                return -1;
            }
        });

        $lis = '';
        foreach ($records as $key => $record) {
            if ($record->type == 2) {
                $context = context_course::instance($record->courseid);
            } else {
                $context = context_system::instance();
            }
            $opacity = '';
            if ($highlight) {
                $opacity = ' opacity: 0.15;';
                if (isset($record->highlight)) {
                    $opacity = ' opacity: 1.0;';
                }
            }
            $imageurl = moodle_url::make_pluginfile_url($context->id, 'badges', 'badgeimage', $record->id, '/', 'f1', false);
            // After the ajax call and save into the DB.

            $value = 'badge' . '-' . $USER->id . '-' . $COURSE->id . '-' . $key;
            $namevalue = 'user_have_badge-' . $value;

            $image = html_writer::empty_tag('img', ['src' => $imageurl, 'class' => 'bg-image-' . $key, 'style' => $opacity]);

            if (isset($record->uniquehash)) {
                $url = new moodle_url('/badges/badge.php', ['hash' => $record->uniquehash]);
                $badgeisnew = get_user_preferences('format_mooin4_new_badge_' . $record->issuedid, 0, $USER->id);
            } else {
                $url = new moodle_url('/badges/overview.php', ['id' => $record->id]);
                $badgeisnew = 0;
            }

            $detail = '';
            if ($details) {
                $user = $DB->get_record('user', ['id' => $record->userid]);
                $detail = '<br />' . $user->firstname . ' ' . $user->lastname . '<br />('
                    . date('d.m.y H:i', $record->dateissued) . ')';
            } else if ($badgename) {
                $detail = '<br />' . $record->name;
            }

            $link = html_writer::link($url, $image . $detail, ['title' => $record->name]);

            if (strcmp($opacity, " opacity: 0.15;") == 0 || $badgeisnew == 0) {
                $lis .= html_writer::tag(
                    'li',
                    $link,
                    ['class' => 'all-badge-layer cid-badge-' . $COURSE->id, 'id' => 'badge-' . $key]
                );
            } else {
                $lis .= html_writer::tag(
                    'li',
                    $link,
                    ['class' => 'new-badge-layer cid-badge-' . $COURSE->id, 'id' => 'badge-' . $key]
                );
            }
        }

        echo html_writer::tag('ul', $lis, ['class' => 'badges-list badges']);
    }

    /**
     * Get user and available badges for a course.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @return string|null HTML output or null.
     */
    public static function get_user_and_availbale_badges($userid, $courseid) {
        global $CFG, $USER, $PAGE;
        $result = null;
        require_once($CFG->dirroot . '/badges/renderer.php');

        $coursebadges = self::get_badge_records($courseid, null, null, null);
        $userbadges = badges_get_user_badges($userid, $courseid, null, null, null, true);

        foreach ($userbadges as $ub) {
            if ($ub->status == 1 || $ub->status == 3) {

                $coursebadges[$ub->id]->highlight = true;
                $coursebadges[$ub->id]->uniquehash = $ub->uniquehash;
                $coursebadges[$ub->id]->issuedid = $ub->issuedid;
                // Save the badge direct into user_preferences table, later it'll be remove when the user click on the badge.
            }
        }
        if ($coursebadges) {
            $result = self::print_badges_html($coursebadges, false, true, true);
        } else {
            $result = null;
        }
        return $result;
    }

    /**
     * Get badge records for a course.
     *
     * @param int $courseid The course ID (0 for all courses).
     * @param int $page Page number for pagination.
     * @param int $perpage Records per page.
     * @param string $search Search term.
     * @return array Array of badge records.
     */
    public static function get_badge_records($courseid = 0, $page = 0, $perpage = 0, $search = '') {
        global $DB, $PAGE;

        $params = [];
        $sql = 'SELECT
                    b.*
                FROM
                    {badge} b
                WHERE b.type > 0
                  AND (b.status = 1 OR b.status = 3)'; // Status for available badges.
        /*
        Statusvarianten:
        0 nicht verfügbar und nicht vergeben
        1 verfügbar und nicht vergeben
        2 zugriff verhindert und vergeben
        3 vergeben
        */

        if ($courseid == 0) {
            $sql .= ' AND b.type = :type';
            $params['type'] = 1;
        }

        if ($courseid != 0) {
            $sql .= ' AND b.courseid = :courseid';
            $params['courseid'] = $courseid;
        }

        if (!empty($search)) {
            $sql .= ' AND (' . $DB->sql_like('b.name', ':search', false) . ') ';
            $params['search'] = '%' . $DB->sql_like_escape($search) . '%';
        }

        $badges = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

        return $badges;
    }

    /**
     * show the  certificat on the welcome page
     * @param int $courseid
     * @return array|string|null
     */
    public static function show_certificat($courseid) {
        global $USER;
        $outcertificat = null;
        $templ = self::get_course_certificates($courseid, $USER->id);

        $templ = array_values($templ);
        if (isset($templ) && !empty($templ)) {
            if (is_string($templ) == 1) {
                $outcertificat = $templ;
            }
            if (is_string($templ) != 1) {
                $outcertificat .= html_writer::start_tag('div', ['class' => 'certificate_list']);
                for ($i = 0; $i < count($templ); $i++) {
                    if ($templ[$i]->url != '#') {
                        // Has user already viewed the certificate?
                        $new = '';
                        $certmod = $templ[$i]->certmod;
                        $issuedid = $templ[$i]->issuedid;
                        if (get_user_preferences('format_mooin4_new_certificate_' . $certmod . '_' . $issuedid, 0,
                            $USER->id) == 1) {
                            $new = ' new-certificate-layer';
                        }
                        $outcertificat .= html_writer::link(
                            $templ[$i]->url,
                            ' ' . $templ[$i]->name,
                            ['class' => 'certificate-img' . $new]
                        );
                    } else {

                        $outcertificat .= html_writer::span($templ[$i]->name, 'certificate-img');

                    }
                }
                $outcertificat .= html_writer::end_tag('div');
            }
        } else {
            $outcertificat = null;
        }
        return  $outcertificat;
    }

    /**
     * Set a new certificate preference.
     *
     * @param int $awardedtoid The user ID who was awarded the certificate.
     * @param int $issuedid The issued certificate ID.
     * @param string $modulename The module name.
     */
    public static function set_new_certificate($awardedtoid, $issuedid, $modulename) {
        set_user_preference('format_mooin4_new_certificate_' . $modulename . '_' . $issuedid, true, $awardedtoid);
    }

    /**
     * Unset a new certificate preference.
     *
     * @param int $viewedbyuserid The user ID who viewed the certificate.
     * @param int $issuedid The issued certificate ID.
     * @param string $modulename The module name.
     */
    public static function unset_new_certificate($viewedbyuserid, $issuedid, $modulename) {
        global $DB;
        $tablename = 'ilddigitalcert_issued';
        if ($modulename == 'coursecertificate') {
            $tablename = 'tool_certificate_issues';
        } else if ($modulename == 'ilddigitalcert') {
            $tablename = 'ilddigitalcert_issued';
        }
        $sql = 'SELECT * from {' . $tablename . '}
                 WHERE id = :id
                   AND userid = :userid ';
        $params = [
            'tablename' => $tablename,
            'id' => $issuedid,
            'userid' => $viewedbyuserid,
        ];

        if ($record = $DB->get_record_sql($sql, $params)) {
            if ($record->userid == $viewedbyuserid) {
                unset_user_preference('format_mooin4_new_certificate_' . $modulename . '_' . $record->id, $viewedbyuserid);
            }
        }
    }

    /**
     * Get user coordinates from city.
     *
     * @param stdClass $user The user object.
     * @return stdClass|bool Coordinates object or false.
     */
    public static function get_user_coordinates($user) {
        if ($user->city != '') {
            $coordinates = new stdClass();

            $url = get_config('format_mooin4', 'geonamesapi_url');
            $apiusername = get_config('format_mooin4', 'geonamesapi_username');

            $response = self::get_url_content(
                $url,
                "/search?username=" . $apiusername . "&maxRows=1&q=" . urlencode($user->city)
                    . "&country=" . urlencode($user->country)
            );

            if ($response != "" && $xml = simplexml_load_string($response)) {
                if (isset($xml->geoname->lat)) {
                    $coordinates->lat = floatval($xml->geoname->lat);
                    $coordinates->lng = floatval($xml->geoname->lng);
                }
            }

            return $coordinates;
        }
        return false;
    }

    /**
     * Gets the content of a url request
     * @param string $domain
     * @param string $path
     * @uses $CFG
     * @return String body of the returned request
     */
    public static function get_url_content($domain, $path) {

        global $CFG;

        $message = "GET $domain$path HTTP/1.0\r\n";
        $msgaddress = str_replace("http://", "", $domain);
        $message .= "Host: $msgaddress\r\n";
        $message .= "Connection: Close\r\n";
        $message .= "\r\n";

        if ($CFG->proxyhost != "" && $CFG->proxyport != 0) {
            $address = $CFG->proxyhost;
            $port = $CFG->proxyport;
        } else {
            $address = str_replace("http://", "", $domain);
            $port = 80;
        }

        /* Attempt to connect to the proxy server to retrieve the remote page */
        if (!$socket = fsockopen($address, $port, $errno, $errstring, 20)) {
            echo "Couldn't connect to host $address: $errno: $errstring\n";
            return "";
        }

        fwrite($socket, $message);
        $content = "";
        while (!feof($socket)) {
            $content .= fgets($socket, 1024);
        }

        fclose($socket);
        $retstr = self::extract_body($content);
        return $retstr;
    }

    /**
     * removes the headers from a url response
     * @param string $response
     * @return String body of the returned request
     */
    public static function extract_body($response) {

        $crlf = "\r\n";
        // Split header and body.
        $pos = strpos($response, $crlf . $crlf);
        if ($pos === false) {
            return ($response);
        }

        $header = substr($response, 0, $pos);
        $body = substr($response, $pos + 2 * strlen($crlf));
        // Parse headers.
        $headers = [];
        $lines = explode($crlf, $header);

        foreach ($lines as $line) {
            if (($pos = strpos($line, ':')) !== false) {
                $headers[strtolower(trim(substr($line, 0, $pos)))] = trim(substr($line, $pos + 1));
            }
        }

        return $body;
    }

    /**
     * Set a new badge preference.
     *
     * @param int $awardedtoid The user ID who was awarded the badge.
     * @param int $badgeissuedid The issued badge ID.
     */
    public static function set_new_badge($awardedtoid, $badgeissuedid) {
        set_user_preference('format_mooin4_new_badge_' . $badgeissuedid, true, $awardedtoid);
    }

    /**
     * Unset a new badge preference.
     *
     * @param int $viewedbyuserid The user ID who viewed the badge.
     * @param string $badgehash The unique hash of the badge.
     */
    public static function unset_new_badge($viewedbyuserid, $badgehash) {
        global $DB;
        $sql = "select * from {badge_issued} where " . $DB->sql_compare_text('uniquehash') . " = :badgehash";
        $params = ['badgehash' => $badgehash];
        if ($records = $DB->get_records_sql($sql, $params)) {
            if (count($records) == 1) {
                if ($records[array_key_first($records)]->userid == $viewedbyuserid) {
                    unset_user_preference('format_mooin4_new_badge_' . $records[array_key_first($records)]->id, $viewedbyuserid);
                }
            }
        }
    }

    /**
     * Count unviewed badges for a user in a course.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @return int Number of unviewed badges.
     */
    public static function count_unviewed_badges($userid, $courseid) {
        global $DB;
        $unviewedbadges = 0;
        $sql = 'SELECT bi.id
              FROM {badge_issued} bi, {badge} b
             WHERE b.courseid = :courseid
               AND b.id = bi.badgeid
               AND bi.userid = :userid';
        $params = ['courseid' => $courseid, 'userid' => $userid];
        if ($records = $DB->get_records_sql($sql, $params)) {
            foreach ($records as $record) {
                $badgeisnew = get_user_preferences('format_mooin4_new_badge_' . $record->id, 0, $userid);
                if ($badgeisnew) {
                    $unviewedbadges++;
                }
            }
        }
        return $unviewedbadges;
    }
}
