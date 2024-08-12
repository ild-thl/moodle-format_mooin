<?php

namespace format_moointopics\local;

use html_writer;
use context_course;
use moodle_url;
use context_system;
use stdClass;
use context_user;
use core_badges_renderer;
use xmldb_table;

defined('MOODLE_INTERNAL') || die();

class utils {
   
    public static function complete_section($section) {
        global $USER;
        set_user_preference('format_moointopics_section_completed_'.$section, 1, $USER->id);
    }

    public static function is_course_completed($course_id) {
        global $DB;
        $is_course_completed = false;
        if ($course_chapters = $DB->get_records('format_moointopics_chapter', array('courseid' => $course_id))) {
          $is_course_completed = true;
          foreach ($course_chapters as $chapter) {
            $chapter_info = \format_moointopics\local\chapterlib::get_chapter_info($chapter);
            if ($chapter_info['completed'] == false) {
              $is_course_completed = false;
              return false;
            }
          }
        }
        return $is_course_completed;
    }

    public static function get_course_progress($courseid, $userid) {
        global $DB;
    
        $percentage = 0;
        $i = 0;
        if ($sections = $DB->get_records('course_sections', array('course' => $courseid))) {
            foreach ($sections as $section) {
                if (!$DB->get_record('format_moointopics_chapter', array('sectionid' => $section->id)) &&
                        $section->section != 0) {
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
        $hvp = $DB->get_record('hvp', array('id' => $cm->instance));
        if (!$hvp) {
            return false;
        }
    
        // Create grade object and set grades.
        $grade = (object)array(
            'userid' => $USER->id
        );
    
        /* oncampus mod - start */
        require_once($CFG->libdir . '/gradelib.php');
        $grading_info = \grade_get_grades($cm->course, 'mod', 'hvp', $cm->instance, $USER->id);
        $grading_info = (object)$grading_info;
        if (!empty($grading_info->items)) {
            $user_grade = $grading_info->items[0]->grades[$USER->id]->grade;
        } else {
            $user_grade = 0;
        }
    
        if ($score >= $user_grade) {
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
                array($hvp->id)
            );
    
            // Log results set event.
            new \mod_hvp\event(
                'results', 'set',
                $hvp->id, $content->title,
                $content->name, $content->major_version . '.' . $content->minor_version
            );
    
            // $progress = get_progress($cm->course, $cm->section);
            $progress = self::get_hvp_section_progress($cm->course, $cm->section, $USER->id);
            /* <script>;
                var divId = String('mooin4ection' + $_POST['sectionid']); // Mooin4ection-progress
                var textDivId = String('mooin4ection-text-' + $_POST['sectionid']); // Mooin4ection-progress-text-
    
                var percentageInt = String($_POST['percentage'] + '%');
                var percentageText = String($_POST['percentage'] + '% der Lektion bearbeitet');
    
                $('#' + divId, window.parent.document).css('width', percentageInt);
                $('#' + textDivId, window.parent.document).html(percentageText);
            </script>; */
    
            return $progress;
        }
        return false;
    }

    public static function get_hvp_section_progress($courseid, $sectionid, $userid) {
        global $DB, $CFG;
    
        require_once($CFG->libdir . '/gradelib.php');
    
        $percentage = 0;
    
        // no activities in this section?
        $coursemodules = $DB->get_records('course_modules', array('course' => $courseid,
                                                                       'deletioninprogress' => 0,
                                                                       'section' => $sectionid));
    
        $activities = 0;
    
        foreach ($coursemodules as $coursemodule) {
            // cm has completion activated?
            if ($coursemodule->completion == 2) {
                $activities++;
    
                $modulename = '';
                if ($module = $DB->get_record('modules', array('id' => $coursemodule->module))) {
                    $modulename = $module->name;
                }
    
                // activity is hvp, we use the grades to get the individual progress
                if ($modulename == 'hvp') {
                    $grading_info = grade_get_grades($courseid, 'mod', 'hvp', $coursemodule->instance, $userid);
                    $grade = $grading_info->items[0]->grades[$userid]->grade;
                    $grademax = $grading_info->items[0]->grademax;
                    if (isset($grade) && $grade != 0) {
                        $percentage += 100 / ($grademax / $grade);
                    }
                }
                else {
                    // if completed, add to percentage
                    $sql = 'SELECT *
                              FROM {course_modules_completion}
                             WHERE coursemoduleid = :coursemoduleid
                               AND userid = :userid
                               AND completionstate != 0 ';
                    $params = array('coursemoduleid' => $coursemodule->id,
                                    'userid' => $userid);
                    if ($DB->get_record_sql($sql, $params)) {
                        $percentage += 100;
                    }
                }
            }
        }
    
        // no activities with completion activated?
        if ($activities == 0) {
            if (get_user_preferences('format_moointopics_section_completed_'.$sectionid, 0, $userid) == 1) {
                return 100;
            }
            else {
                return 0;
            }
        }
        $progress = array('sectionid' => $sectionid, 'percentage' => round($percentage / $activities));
        return $progress;// round($percentage / $activities);
    }

    public static function set_user_coordinates($userid, $lat, $lng) {
        set_user_preference('format_mooin4_user_coordinates', $lat . '|' . $lng, $userid);
    }

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
     * Get the user in the course
     * @param int courseid
     * @return array out
     */
    public static function get_user_in_course($courseid) {
        global $DB, $OUTPUT;
        $out = null;
        // Get the enrol data in the course

        $sql = 'SELECT * FROM mdl_enrol WHERE courseid = :cid AND enrol = :enrol_data ORDER BY ID ASC';
        $param = array('cid' => $courseid, 'enrol_data' => 'manual');
        $enrol_data = $DB->get_records_sql($sql, $param);

        // Get user_enrolments data
        $user_enrol_data = [];
        $sql_query = 'SELECT * FROM mdl_user_enrolments WHERE enrolid = :value_id ORDER BY timecreated DESC ';

        foreach ($enrol_data as $key => $value) {
            $param_array = array('value_id' => $value->id);
            $count_val = $DB->get_records_sql($sql_query, $param_array);
            $val = $DB->get_records_sql($sql_query, $param_array, 0, 5); // ('user_enrolments', ['enrolid' =>$value->id], 'userid');
            array_push($user_enrol_data, $val);
        }

        $sql2 = 'SELECT ue.*
               FROM mdl_enrol AS e, mdl_user_enrolments AS ue
              WHERE e.courseid = :cid
                AND ue.enrolid = e.id
           ORDER BY timecreated DESC';
        $user_enrol_data = [];
        $params2 = $param = array('cid' => $courseid);
        $user_enrolments = $DB->get_records_sql($sql2, $params2);
        $user_count = count($user_enrolments);
        $user_enrolments = $DB->get_records_sql($sql2, $params2, 0, 5);
        array_push($user_enrol_data, $user_enrolments);

        if (isset($enrol_data)) {

            $user_list = '';
            foreach ($user_enrol_data as $key => $value) {

                $el = array_values($value);
                for ($i = 0; $i < count($el); $i++) {
                    $user_list .= html_writer::start_tag('li');
                    $user = $DB->get_record('user', ['id' => $el[$i]->userid], '*');
                    $user_list .= html_writer::start_tag('span');
                    $user_list .= html_writer::nonempty_tag('span', $OUTPUT->user_picture($user, array('courseid' => $courseid)));
                    $user_list .= $user->firstname . ' ' . $user->lastname;
                    $user_list .= html_writer::end_tag('span');
                    $user_list .= html_writer::end_tag('li'); // user_card_element
                }
            }

            $participants_url = new moodle_url('/course/format/mooin4/participants.php', array('id' => $courseid));
            $participants_link = html_writer::link($participants_url, get_string('show_all_infos', 'format_mooin4'), array('title' => get_string('participants', 'format_mooin4')));
        } else {
            $out .= html_writer::div(get_string('no_user', 'format_mooin4'), 'no_user_class');
        }

        $templatecontext = [
            'user_count' => $user_count,
            'user_list' => $user_list
        ];

        return $templatecontext;
    }

     /**
     * Get the last News in the course
     * @param int $courseid
     * @param string $forum_type
     * @return array
     */
    public static function get_last_news($courseid, $forum_type) {
        global $DB, $OUTPUT, $USER;

        $sql = 'SELECT fp.*, f.id as forumid
                FROM {forum_posts} as fp,
                    {forum_discussions} as fd,
                    {forum} as f
                WHERE fp.discussion = fd.id
                AND fd.forum = f.id
                AND f.course = :courseid
                AND (fp.mailnow = 1 OR fp.created < :wait) ';
        if ($forum_type == 'news') {
            $sql .= 'AND f.type = :news ';
        } else {
            $sql .= 'AND f.type != :news ';
        }
        $sql .= 'ORDER BY fp.created DESC LIMIT 1 ';

        $params = array(
            'courseid' => $courseid,
            'news' => 'news',
            'wait' => time() - 1800
        );

        if ($latestpost = $DB->get_record_sql($sql, $params)) {
            $news_forum_post = $latestpost;

            $user = $DB->get_record('user', ['id' => $news_forum_post->userid], '*');
            $created_news = date("d.m.Y, G:i", date((int)$news_forum_post->created));

            if ($forum = $DB->get_record('forum', array('course' => $courseid, 'type' => 'news'))) {
                if ($module = $DB->get_record('modules', array('name' => 'forum'))) {
                    if ($cm = $DB->get_record('course_modules', array('module' => $module->id, 'instance' => $forum->id))) {
                        $newsurl =  new moodle_url('/mod/forum/view.php', array('id' => $cm->id));
                    }
                }
            }

            if ($forum_type == 'news') {
                $unread_news_number = self::count_unread_posts($USER->id, $courseid, true);
                $new_news = false;

                // if ($unread_news_number == 1) {
                //     $new_news = html_writer::start_span('count-container d-inline-flex inline-badge fw-700 mr-1') . $unread_news_number . html_writer::end_span();
                //     $new_news .= html_writer::link($newsurl, get_string('unread_news_single', 'format_moointopics') . get_string('all_news', 'format_moointopics'), array('title' => get_string('all_news', 'format_mooin4'), 'class' => 'primary-link'));
                // } else if ($unread_news_number > 1) {
                //     $new_news .= html_writer::start_span('count-container d-inline-flex inline-badge fw-700 mr-1') . $unread_news_number . html_writer::end_span(); //Notification Counter
                //     $new_news .= html_writer::link($newsurl, get_string('unread_news', 'format_moointopics') . get_string('all_news', 'format_moointopics'), array('title' => get_string('all_news', 'format_mooin4'), 'class' => 'primary-link'));
                // } else {
                //     $new_news = false;
                // }
            }

            $forum_discussion_url = new moodle_url('/mod/forum/discuss.php', array('d' => $news_forum_post->discussion));
            $templatecontext = [
                'news_url' => $newsurl,
                'user_firstname' =>  $user->firstname,
                'created_news' => $created_news,
                'user_picture' => $OUTPUT->user_picture($user, array('courseid' => $courseid)),
                'news_title' => $news_forum_post->subject,
                'news_text' => $news_forum_post->message,
                'discussion_url' => $forum_discussion_url,
                'unread_news_number' => $unread_news_number,
                //'new_news' => $new_news
            ];
        } else {
            $templatecontext = [];
        }
        return $templatecontext;
    }

    public static function count_unread_posts($userid, $courseid, $news = false, $forumid = 0) {
        global $DB, $USER;
    
        // SQL query to get all unread posts
        $sql = 'SELECT fp.*, f.id as forumid, fd.groupid, fd.id as discussionid, cm.id as cmid
                FROM {forum_posts} as fp
                JOIN {forum_discussions} as fd ON fp.discussion = fd.id
                JOIN {forum} as f ON fd.forum = f.id
                JOIN {course_modules} as cm ON cm.instance = f.id
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
    
        $params = array(
            'courseid' => $courseid,
            'news' => 'news',
            'userid' => $userid,
            'forumid' => $forumid,
            'wait' => time() - 1800
        );
    
        $unreadposts = $DB->get_records_sql($sql, $params);
        $visible_unread_posts = 0;
    
        // Check visibility of each post
        foreach ($unreadposts as $post) {
            $forum = $DB->get_record('forum', array('id' => $post->forumid));
            $discussion = $DB->get_record('forum_discussions', array('id' => $post->discussionid));
            $cm = get_coursemodule_from_instance('forum', $forum->id, $courseid);
    
            if (forum_user_can_see_post($forum, $discussion, $post, $USER, $cm)) {
                $visible_unread_posts++;
            }
        }
    
        return $visible_unread_posts;
    }
    

    /**
     * Get the last forum discussion in the course
     * @param int $courseid
     * @param string @forum_type
     * @return array
     */
    public static function get_last_forum_discussion($courseid, $forum_type) {
        global $DB, $OUTPUT, $USER;
    
        $sql = 'SELECT fp.*, f.id as forumid, fd.groupid, fd.id as discussionid, cm.id as cmid
                FROM {forum_posts} as fp
                JOIN {forum_discussions} as fd ON fp.discussion = fd.id
                JOIN {forum} as f ON fd.forum = f.id
                JOIN {course_modules} as cm ON cm.instance = f.id
                WHERE f.course = :courseid
                AND (fp.mailnow = 1 OR fp.created < :wait)
                AND f.type != :news
                AND cm.module = (SELECT id FROM {modules} WHERE name = "forum")
                ORDER BY fp.created DESC';
    
        $params = array(
            'courseid' => $courseid,
            'news' => 'news',
            'wait' => time() - 1800
        );
    
        $latestposts = $DB->get_records_sql($sql, $params);
    
        if (!empty($latestposts)) {
            foreach ($latestposts as $post) {
                $forum = $DB->get_record('forum', array('id' => $post->forumid));
                $discussion = $DB->get_record('forum_discussions', array('id' => $post->discussionid));
                $cm = get_coursemodule_from_instance('forum', $forum->id, $courseid);
    
                if (forum_user_can_see_post($forum, $discussion, $post, $USER, $cm)) {
                    $user = $DB->get_record('user', ['id' => $post->userid], '*');
                    $created_news = date("d.m.Y, G:i", date((int)$post->created));
                    $unread_forum_number = self::count_unread_posts($USER->id, $courseid, false);
    
                    $forum_discussion_url = new moodle_url('/mod/forum/discuss.php', array('d' => $post->discussion));
                    $templatecontext = [
                        'user_firstname' =>  $user->firstname,
                        'created_news' => $created_news,
                        'user_picture' => $OUTPUT->user_picture($user, array('courseid' => $courseid)),
                        'news_title' => $post->subject,
                        'news_text' => $post->message,
                        'discussion_url' => $forum_discussion_url,
                        'unread_news_number' => $unread_forum_number,
                        'new_news' => false,
                        'small_countcontainer' => false
                    ];
                    return $templatecontext;
                }
            }
        }
    
        // Default context if no posts are found or accessible
        $templatecontext = [
            'unread_news_number' => 0,
            'no_discussions_available' => true,
            'no_news' => false,
            'new_news' => false
        ];
    
        return $templatecontext;
    }
    
    
    

    /**
     * Get the right user picture for creating forum
     * @param int courseid
     * @return object of user
     */
    public static function user_print_forum($courseid) {
        global $DB, $USER;

        $sql = 'SELECT * FROM mdl_forum WHERE course = :cid ORDER BY ID DESC '; // LIMIT 1
        $param = ['cid' => $courseid];

        $forum_in_course = $DB->get_records_sql($sql, $param, IGNORE_MISSING);
        // var_dump($forum_in_course);
        // get the forum_discussion data
        $sql_in_forum = 'SELECT * FROM mdl_forum_discussions ORDER BY ID DESC LIMIT 1'; // WHERE forum = :id
        // $param_in_forum = ['id'=> $forum_in_course->id];
        $discuss_forum_in_course = $DB->get_record_sql($sql_in_forum,  [], IGNORE_MISSING);

        $result = new stdClass;
        if ($discuss_forum_in_course->userid == $discuss_forum_in_course->usermodified) {
            $result = $DB->get_record('user', ['id' => $discuss_forum_in_course->userid]);
        } else {
            $result = $DB->get_record('user', ['id' => $discuss_forum_in_course->usermodified]);
        }


        return $result;
    }

    public static function set_discussion_viewed($userid, $forumid, $discussionid) {
        global $DB;
    
        $posts = $DB->get_records('forum_posts', array('discussion' => $discussionid));
        foreach ($posts as $post) {
            if (!$read = $DB->get_record('forum_read', array('userid' => $userid,
                                                             'forumid' => $forumid,
                                                             'discussionid' => $discussionid,
                                                             'postid' => $post->id))) {
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

    public static function set_chapter($sectionid) {
        global $DB;
    
        if ($DB->get_record('format_moointopics_chapter', array('sectionid' => $sectionid))) {
            return;
        }
    
        if ($csection = $DB->get_record('course_sections', array('id' => $sectionid))) {
            $csectiontitle = $csection->name;
        }
        else {
            return;
        }
    
        if (!$csectiontitle) {
            $csectiontitle = get_string('new_chapter', 'format_moointopics');
        }
    
        $chapter = new stdClass();
        $chapter->courseid = $csection->course;
        $chapter->title = $csectiontitle;
        $chapter->sectionid = $sectionid;
        $chapter->chapter = 0;
        $DB->insert_record('format_moointopics_chapter', $chapter);
    
        self::sort_course_chapters($csection->course);
    }

    public static function unset_chapter($sectionid) {
        global $DB;
    
        $DB->delete_records('format_moointopics_chapter', array('sectionid' => $sectionid));
        if ($csection = $DB->get_record('course_sections', array('id' => $sectionid))) {
            self::sort_course_chapters($csection->course);
        }
    }

    public static function sort_course_chapters($courseid) {
        global $DB;
        $coursechapters = self::get_course_chapters($courseid);
        $number = 0;
        foreach ($coursechapters as $coursechapter) {
            $number++;
            if ($existingcoursechapter = $DB->get_record('format_moointopics_chapter', array('id' => $coursechapter->id))) {
                $existingcoursechapter->chapter = $number;
                $DB->update_record('format_moointopics_chapter', $existingcoursechapter);
            }
        }
    }

    public static function get_last_section($courseid) {
        global $DB;
    
        $lastsection = 0;
        $count = $DB->count_records('course_sections', array('course' => $courseid));
    
        if ($count > 0) {
            $lastsection = $count - 1;
        }
    
        return $lastsection;
    }
    
    
    
    public static function get_section_prefix($section) {
        global $DB, $USER;
    
        $sectionprefix = '';
    
        // Get parent chapter of the section
        $parentchapter = self::get_parent_chapter($section);
        if (is_object($parentchapter)) {
            // Get section ids for the chapter
            $sids = self::get_sectionids_for_chapter($parentchapter->id);
    
            // Get the course and format
            $course = get_course($section->course);
            $format = course_get_format($course);
            $modinfo = get_fast_modinfo($course, $USER->id);
            $context = context_course::instance($course->id);
    
            $visible_count = 0;
    
            foreach ($sids as $sid) {
                $section_info = $modinfo->get_section_info_by_id($sid);
                $is_visible = ($section_info && $format->is_section_visible($section_info));
    
                if ($sid == $section->id) {
                    if (!$section->visible) {
                        $sectionprefix = 'ausgeblendet';
                    } else {
                        $visible_count += 1;
                        $sectionprefix = $parentchapter->chapter . '.' . $visible_count;
                    }
                    break;
                }
    
                
                if ($is_visible && $section_info->visible) {
                    $visible_count += 1;
                    error_log($section->name.$visible_count);
                }
            }
        }
    
        return $sectionprefix;
    }
    
    
    
    
    // public static function get_section_prefix($section) {
    //     global $DB, $USER;
    
    //     $sectionprefix = '';
    
    //     // Get parent chapter of the section
    //     $parentchapter = self::get_parent_chapter($section);
    //     if (is_object($parentchapter)) {
    //         // Get section ids for the chapter
    //         $sids = self::get_sectionids_for_chapter($parentchapter->id);
    
    //         // Filter sids array to include only visible sections
    //         $visible_sids = array_filter($sids, function($sid) use ($DB, $USER) {
    //             $course_section = $DB->get_record('course_sections', array('id' => $sid));
    //             if ($course_section) {
    //                 $course = get_course($course_section->course);
    //                 $format = course_get_format($course);
    //                 $modinfo = get_fast_modinfo($course, $USER->id);
    //                 $section_info = $modinfo->get_section_info_by_id($sid);
    //                 return ($section_info && $format->is_section_visible($section_info));
    //             }
    //             return false;
    //         });
    
    //         // Re-index the array to ensure keys are sequential
    //         $visible_sids = array_values($visible_sids);
    
    //         // Find the index of the current section in the filtered array
    //         $index = array_search($section->id, $visible_sids);
    //         if ($index !== false) {
    //             $index += 1; // Convert to 1-based index
    //             $sectionprefix = $parentchapter->chapter . '.' . $index;
    //         }
    //     }
    //     return $sectionprefix;
    // }
    
    

    // public static function get_section_prefix($section) {
    //     global $DB;
    
    //     $sectionprefix = '';
    
    //     $parentchapter = self::get_parent_chapter($section);
    //     if (is_object($parentchapter)) {
    //         $sids = self::get_sectionids_for_chapter($parentchapter->id);
    //         $sectionprefix .= $parentchapter->chapter.'.'.(array_search($section->id, $sids) + 1);
    
    //         return $sectionprefix;
    //     }
    
    // }

    
    
    
    
    

    public static function get_parent_chapter($section) {
        global $DB;
    
        $chapters = $DB->get_records('format_moointopics_chapter', array('courseid' => $section->course));
        foreach ($chapters as $chapter) {
            $sids = self::get_sectionids_for_chapter($chapter->id);
            if (in_array($section->id, $sids)) {
                return $chapter;
            }
        }
    
        return false;
    }

    public static function get_sectionids_for_chapter($chapterid) {
        global $DB;
        $result = array();
        if ($chapter = $DB->get_record('format_moointopics_chapter', array('id' => $chapterid))) {
            $chapters = self::get_course_chapters($chapter->courseid);
            $start = 0;
            $end = 0;
            foreach ($chapters as $c) {
                if ($c->id == $chapterid) {
                    $start = $c->section;
                    continue;
                }
                if ($start != 0) {
                    $end = $c->section;
                    break;
                }
            }
            if ($coursesections = $DB->get_records('course_sections', array('course' => $chapter->courseid), 'section', 'section, id')) {
                if ($start != 0) {
                    if ($end == 0) {
                        $end = self::get_last_section($chapter->courseid) + 1;
                    }
                    $i = $start + 1;
                    while ($i < $end) {
                        
                            $result[] = $coursesections[$i]->id; 
                        
                        $i++;
                    }
                }
            }
        }
        return $result;
    }

    public static function get_course_chapters($courseid) {
        global $DB;
    
        $sql = 'SELECT c.*, s.section
                  FROM {format_moointopics_chapter} as c, {course_sections} as s
                 WHERE s.course = :courseid
                   and s.id = c.sectionid
              order by s.section asc';
    
        $params = array('courseid' => $courseid);
    
        $coursechapters = $DB->get_records_sql($sql, $params);
    
        return $coursechapters;
    }

    public static function is_first_section_of_chapter($sectionid) {
        global $DB;
    
        
        if ($section = $DB->get_record('course_sections', array('id' => $sectionid))) {
            
            $chapters = self::get_course_chapters($section->course);

            $course = get_course($section->course);
            $format = course_get_format($course);
    
           
            foreach ($chapters as $c) {
                
                $next_sections = $DB->get_records_sql(
                    "SELECT * FROM {course_sections}
                     WHERE course = :courseid AND section > :chaptersection
                     ORDER BY section ASC",
                    array('courseid' => $section->course, 'chaptersection' => $c->section)
                );
    
                
                foreach ($next_sections as $next_section) {
                    $section_info = get_fast_modinfo($course)->get_section_info($next_section->section);
                    if ($format->is_section_visible($section_info)) {
                        
                        if ($next_section->id == $sectionid) {
                            return true;
                        }
                        break; 
                    }
                }
            }
        }
        return false;
    }
    
    
    // public static function is_last_section_of_chapter($sectionid) {
    //     global $DB;
    //     $chapter = null;
    //     if ($section = $DB->get_record('course_sections', array('id' => $sectionid))) {
    
    //         $chapters = self::get_course_chapters($section->course);
    //         $chapter = self::get_chapter_for_section($sectionid);
    
    //         $start = 0;
    //         $end = 0;
    //         foreach ($chapters as $c) {
    //             if ($c->chapter == $chapter) {
    //                 $start = $c->section;
    //                 continue;
    //             }
    //             if ($start != 0) {
    //                 $end = $c->section;
    //                 break;
    //             }
    //         }
    //         if ($start != 0) {
    //             if ($end == 0) {
    //                 $end = self::get_last_section($section->course) + 1;
    //             }
    //         }
    
    //         if ($section -> section == $end-1) {
    //             return true;
    //         }
    //     }
    //     return false;
    // }

    //TODO: Funktioniert -> evtl gut falls sections doch unsichtbar gemacht werden sollen, aber erstmal sections stattdessen sperren
    public static function is_last_section_of_chapter($sectionid) {
        global $DB;
        
        if ($section = $DB->get_record('course_sections', array('id' => $sectionid))) {
            $course = get_course($section->course);
            $format = course_get_format($course);
            $parentchapter = self::get_parent_chapter($section);
            $sectionids = self::get_sectionids_for_chapter($parentchapter->id);
            $highestVisibleSection = null;
            foreach ($sectionids as $sectionid) {
                if ($s = $DB->get_record('course_sections', array('id' => $sectionid))) {
                    $section_info = get_fast_modinfo($course)->get_section_info($s->section);
                    if ($format->is_section_visible($section_info) && $s->section > $highestVisibleSection) {
                        $highestVisibleSection = $s->section;
                    }
                }
            }
            return $section->section == $highestVisibleSection;
        }
        return false;
    }

    public static function get_chapter_for_section($sectionid) {
        global $DB;
        $chapter = null;
        if ($section = $DB->get_record('course_sections', array('id' => $sectionid))) {
            $chapters = self::get_course_chapters($section->course);
    
            foreach ($chapters as $c) {
                if ($section->section > $c->section && ($chapter = null||$c->section > $chapter)) {
                    $chapter = $c->chapter;
                }
            }
        }
        return $chapter;
    }

    public static function course_navbar() {
        global $PAGE, $OUTPUT, $COURSE;
         $items = $PAGE->navbar->get_items();
         $course_items = [];
    
        //Split the navbar array at coursehome
         foreach($items as $item) {
            if ($item->key === $COURSE->id) {
                $course_items = array_splice($items, intval(array_search($item, $items)));
            }
         }
    
         $course_items[0]->add_class('course-title');
         $section_node = $course_items[array_key_last($course_items)];
         $section_node->action = null;
         $text = $section_node->text;
         $parts = explode(':', $text, 2);
         $result = trim($parts[0]) . ':';
         $text = $section_node->text = $result;
    
         //Provide custom templatecontext for the new Navbar
        $templatecontext = array(
            'get_items'=> $course_items
        );
    
        return $OUTPUT->render_from_template('format_moointopics/custom_navbar', $templatecontext);
    }

    public static function subpage_navbar() {
        global $PAGE, $OUTPUT, $COURSE;
         $items = $PAGE->navbar->get_items();
         $course_items = [];
    
        //Split the navbar array at coursehome
         foreach($items as $item) {
            if ($item->key === $COURSE->id) {
                $course_items = array_splice($items, intval(array_search($item, $items)));
            }
         }
    
         //$course_items[0]->add_class('course-title');
         $last_node = $course_items[array_key_last($course_items)];
         $last_node->action = null;
         $last_node->shorttext = $last_node->text;
    
    
         //Provide custom templatecontext for the new Navbar
        $templatecontext = array(
            'get_items'=> $course_items
        );
    
        return $OUTPUT->render_from_template('format_moointopics/custom_navbar', $templatecontext);
    }

    public static function get_chapter_info($chapter) {
        global $USER, $DB;
        $info = array();
    
        $chaptercompleted = false;
        $lastvisited = false;
    
        $sectionids = self::get_sectionids_for_chapter($chapter->id);
        $completedsections = 0;
    
        foreach ($sectionids as $sectionid) {
            $section = $DB->get_record('course_sections', array('id' => $sectionid));
            if ($section && self::is_section_completed($chapter->courseid, $section)) {
                $completedsections++;
            }
    
            $last_section = get_user_preferences('format_moointopics_last_section_in_course_'.$chapter->courseid, 0, $USER->id);
            if ($record = $DB->get_record('course_sections', array('course' => $chapter->courseid, 'section' => $last_section))) {
                if ($record->id == $sectionid) {
                    $lastvisited = true;
                }
            }
        }
        if ($completedsections == count($sectionids)) {
            $chaptercompleted = true;
        }else {
            $chaptercompleted = false;
        }
        $info['completed'] = $chaptercompleted;
        $info['lastvisited'] = $lastvisited;
        return $info;
    }

    public static function is_section_completed($courseid, $section) {
        global $USER, $DB;
        /*
        $user_complete_label = $USER->id . '-' . $courseid . '-' . $section->id;
        $label_complete = $DB->record_exists('user_preferences',
            array('name' => 'section_progress_label-'.$user_complete_label,
                  'value' => $user_complete_label));
        if (is_array(get_progress($courseid, $section->id))) {
            $progress_result = intval(get_progress($courseid, $section->id)['percentage']);
            if ($progress_result == 100) {
                return true;
            }
        }
        else if($label_complete) {
            return true;
        }
        */
        $result = false;
        if (self::get_section_progress($courseid, $section->id, $USER->id) == 100) {
            $result = true;
        }else {
            $result = false;
        }
    
        return $result;
    }

    public static function get_section_progress($courseid, $sectionid, $userid) {
        global $DB, $CFG;
    
        require_once($CFG->libdir . '/gradelib.php');
    
        $percentage = 0;
    
        // no activities in this section?
        $coursemodules = $DB->get_records('course_modules', array('course' => $courseid,
                                                                       'deletioninprogress' => 0,
                                                                       'section' => $sectionid));
    
        $activities = 0;
    
        foreach ($coursemodules as $coursemodule) {
            // cm has completion activated?
            if ($coursemodule->completion == 2) {
                $activities++;
    
                $modulename = '';
                if ($module = $DB->get_record('modules', array('id' => $coursemodule->module))) {
                    $modulename = $module->name;
                }
    
                // activity is hvp, we use the grades to get the individual progress
                if ($modulename == 'hvp') {
                    $grading_info = grade_get_grades($courseid, 'mod', 'hvp', $coursemodule->instance, $userid);
                    $grade = $grading_info->items[0]->grades[$userid]->grade;
                    $grademax = $grading_info->items[0]->grademax;
                    if (isset($grade) && $grade != 0) {
                        $percentage += 100 / ($grademax / $grade);
                    }
                }
                else {
                    // if completed, add to percentage
                    $sql = 'SELECT *
                              FROM {course_modules_completion}
                             WHERE coursemoduleid = :coursemoduleid
                               AND userid = :userid
                               AND completionstate != 0 ';
                    $params = array('coursemoduleid' => $coursemodule->id,
                                    'userid' => $userid);
                    if ($DB->get_record_sql($sql, $params)) {
                        $percentage += 100;
                    }
                }
            }
        }
    
        // no activities with completion activated?
        if ($activities == 0) {
            if (get_user_preferences('format_moointopics_section_completed_'.$sectionid, 0, $userid) == 1) {
                return 100;
            }
            else {
                return 0;
            }
        }
    
        return round($percentage / $activities);
    }

    public static function get_unenrol_url($courseid) {
        global $DB, $USER, $CFG;
    
        if ($enrol = $DB->get_record('enrol', array('courseid' => $courseid, 'enrol' => 'autoenrol', 'status' => 0))) {
            if ($user_enrolment = $DB->get_record('user_enrolments', array('enrolid' => $enrol->id, 'userid' => $USER->id))) {
                $unenrolurl = new moodle_url($CFG->wwwroot.'/enrol/autoenrol/unenrolself.php?enrolid='.$enrol->id);
                return $unenrolurl;
            }
        }
    
        if ($enrol = $DB->get_record('enrol', array('courseid' => $courseid, 'enrol' => 'self', 'status' => 0))) {
            if ($user_enrolment = $DB->get_record('user_enrolments', array('enrolid' => $enrol->id, 'userid' => $USER->id))) {
                $unenrolurl = new moodle_url($CFG->wwwroot.'/enrol/self/unenrolself.php?enrolid='.$enrol->id);
                return $unenrolurl;
            }
        }
    
        return false;
    }

    public static function is_course_started($course) {
        global $DB;
        global $USER;
        //$chapterlib = $this->chapterlib;
        //$course = $this->format->get_course();
        $last_section = get_user_preferences('format_moointopics_last_section_in_course_' . $course->id, 0, $USER->id);
        if ($last_section) {
            return true;
        } else {
            return false;
        }
    }

    public static function get_continue_section($course) {
        global $DB;
        global $USER;
        //$chapterlib = $this->chapterlib;
        //$course = $this->format->get_course();

        $last_section = get_user_preferences('format_moointopics_last_section_in_course_' . $course->id, 0, $USER->id);


        if ($last_section) {
            if ($last_section == 0 || $last_section == 1) {
                $last_section = 2;
            }

            if ($continuesection = $DB->get_record('course_sections', array('course' => $course->id, 'section' => $last_section))) {
                return self::get_section_prefix($continuesection);
            } else {
                return false;
            }
        } else {
            return 2;
        }
    }

    public static function get_continue_url($course) {
        global $DB;
        global $USER;
        //$chapterlib = $this->chapterlib;
        //$course = $this->format->get_course();

        $last_section = get_user_preferences('format_moointopics_last_section_in_course_' . $course->id, 0, $USER->id);


        if ($last_section) {
            if ($last_section == 0 || $last_section == 1) {
                //return new moodle_url('/course/view.php', array('id' => $course->id, 'section' => 1));
                $last_section = 2;
            }
            if ($continuesection = $DB->get_record('course_sections', array('course' => $course->id, 'section' => $last_section))) {
                return new moodle_url('/course/view.php', array('id' => $course->id, 'section' => $continuesection->section));
                //return $continuesection->section;
            } else {
                return new moodle_url('/course/view.php', array('id' => $course->id, 'section' => 2));
            }
        } else {
            //return new moodle_url('/course/view.php', array('id' => $course->id, 'section' => 1));
            return new moodle_url('/course/view.php', array('id' => $course->id, 'section' => 2));
        }
    }

    /**
     * Returns url for headerimage
     *
     * @param int courseid
     * @param bool true if mobile header image is required or false for desktop image
     * @return string|bool String with url or false if no image exists
     */
    static function get_headerimage_url($courseid, $mobile = true) {
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
    
        $params = array('contextid' => $context->id,
            'component' => 'format_moointopics',
            'filearea' => $filearea,
            'courseid' => $courseid,
                        'mimetype' => 'image/%');
    
        $records = $DB->get_records_sql($sql, $params);
    
        if (count($records) == 1) {
            $filename = $records[0]->filename;
        }
        else {
            return false;
        }
    
        $url = new moodle_url('/pluginfile.php/'.$context->id.'/format_moointopics/'.$filearea.'/'.$courseid.'/0/'.$filename);
        return $url;
    }

    public static function get_course_certificates($courseid, $userid) {
        global $DB, $CFG;

        $certificates = array();
        $dbman = $DB->get_manager();

        // ilddigitalcert
        $table = new xmldb_table('ilddigitalcert');
        if ($dbman->table_exists($table) && $ilddigitalcerts = $DB->get_records('ilddigitalcert', array('course' => $courseid))) {
            // get user enrolment id
            $ueid = 0;
            $sql = 'SELECT ue.*
                  FROM {enrol} as e,
                       {user_enrolments} as ue
                 WHERE e.courseid = :courseid
                   AND e.id = ue.enrolid
                   AND ue.userid = :userid
                   AND ue.status = 0 ';
            $params = array('courseid' => $courseid, 'userid' => $userid);
            if ($ue = $DB->get_record_sql($sql, $params)) {
                $ueid = $ue->id;
            }

            // get all certificates in course
            foreach ($ilddigitalcerts as $ilddigitalcert) {
                $certificate = new stdClass();
                $certificate->userid = 0;
                $certificate->url = '#';
                $certificate->name = $ilddigitalcert->name;

                // is certificate issued to user?
                $sql = 'SELECT di.id, di.cmid
                      FROM {ilddigitalcert_issued} as di,
                           {course_modules} as cm
                     WHERE cm.instance = :ilddigitalcertid
                       AND di.cmid = cm.id
                       AND di.userid = :userid
                       AND di.enrolmentid = :ueid
                     LIMIT 1 ';
                $params = array(
                    'ilddigitalcertid' => $ilddigitalcert->id,
                    'userid' => $userid,
                    'ueid' => $ueid
                );
                if ($issued = $DB->get_record_sql($sql, $params)) {
                    $certificate->userid = $userid;
                    $certificate->url = $CFG->wwwroot . '/mod/ilddigitalcert/view.php?id=' . $issued->cmid . '&issuedid=' . $issued->id . '&ueid=' . $ueid;
                    $certificate->issuedid = $issued->id;
                    $certificate->certmod = 'ilddigitalcert';
                }
                $certificates[] = $certificate;
            }
        }

        // coursecertificate
        $table = new xmldb_table('coursecertificate');
        if ($dbman->table_exists($table) && $coursecertificates = $DB->get_records('coursecertificate', array('course' => $courseid))) {
            // get all certificates in course
            foreach ($coursecertificates as $coursecertificate) {
                $certificate = new stdClass();
                $certificate->userid = 0;
                $certificate->url = '#';
                $certificate->name = $coursecertificate->name;

                // is certificate issued to user?
                if ($issued = $DB->get_record('tool_certificate_issues', array('userid' => $userid, 'courseid' => $courseid))) {
                    $url = '#';
                    $sql = 'SELECT *
                          FROM {modules} as m , {course_modules} as cm
                         WHERE m.name = :coursecertificate
                           AND cm.module = m.id
                           AND cm.instance = :coursecertificateid ';
                    $params = array(
                        'coursecertificate' => 'coursecertificate',
                        'coursecertificateid' => $coursecertificate->id
                    );
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

    

    public static function count_certificate($userid, $courseid){
        /* We have to found the certificate module in the DB
            One for ilddigitalcertificate and the other for coursecertificate
        */
        global $DB;
        $completed = 0;
        $not_completed = 0;
        $result = [];
        // Make the request into the module & course_module
        $module_ilddigitalcert = $DB->get_record('modules', ['name' =>'ilddigitalcert']);
        $module_coursecertificate = $DB->get_record('modules', ['name' =>'coursecertificate']);
    
        if($module_ilddigitalcert == true) {
            // Make request into course_module
            $cm_ilddigitalcertificate = $DB->get_records('course_modules', ['module' =>$module_ilddigitalcert->id]);
        } else {
            $cm_ilddigitalcertificate  = [];
        }
        if($module_coursecertificate == true) {
            // Make request into course_module
            $cm_coursecertificate = $DB->get_records('course_modules', ['module' =>$module_coursecertificate->id]);
        } else {
            $cm_coursecertificate  = [];
        }
    
        // Check if the module has been completed and save into module_completion table
        if(isset($cm_ilddigitalcertificate)) {
            foreach($cm_ilddigitalcertificate as $value) {
                $exist_completed_certificate = $DB->record_exists('course_modules_completion', ['coursemoduleid'=>$value->id, 'userid'=>$userid]);
                if($exist_completed_certificate) {
                    $completed++;
                }else {
                    $not_completed++;
                }
            }
        }
        if(isset($cm_coursecertificate)) {
            foreach($cm_coursecertificate as $value) {
                $exist_completed_certificate = $DB->record_exists('course_modules_completion', ['coursemoduleid'=>$value->id, 'userid'=>$userid]);
                if($exist_completed_certificate) {
                    $completed++;
                }else {
                    $not_completed++;
                }
            }
        }
    
        $result = ['completed'=>$completed, 'not_completed'=>$not_completed] ;
    
        return $result;
    }

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
                //$right = $renderer->print_badges_html_list($records, $userid, true);
                if ($since == 0) {
                    self::print_badges_html($records);
                } else {
                    self::print_badges_html($records, true);
                }
            }
        } elseif ($USER->id == $userid || has_capability('moodle/badges:viewotherbadges', $context)) {
            $records = badges_get_user_badges($userid, $courseid, null, null, null, true);
            $renderer = new core_badges_renderer($PAGE, '');

            // Print local badges.
            if ($records) {
                $right = $renderer->print_badges_list($records, $userid, true);
                if ($print) {
                    echo html_writer::tag('dd', $right);
                    //print_badges($records);
                } else {
                    return html_writer::tag('dd', $right);
                }
            }
        }
    }

    public static function get_badge_records_since($courseid, $since, $global = false) {
        global $DB, $USER;
        if (!$global) {
            $params = array();
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
            $sql .= ' LIMIT 0, 20 ';
            $badges = $DB->get_records_sql($sql, $params);
        } else {
            $params = array('courseid' => $courseid);
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

        $correct_badges = array();
        foreach ($badges as $badge) {
            $badge->id = $badge->badgeid;

            // nur wenn der Inhaber kein Teacher ist anzeigen
            $coursecontext = context_course::instance($courseid);
            $roles = get_user_roles($coursecontext, $badge->userid, false);
            $not_a_teacher = true;
            foreach ($roles as $role) {
                if ($role->shortname == 'editingteacher') {
                    $not_a_teacher = false;
                }
            }
            if ($not_a_teacher) {
                $correct_badges[] = $badge;
            }
        }
        return $correct_badges;
    }

    public static function print_badges_html($records, $details = false, $highlight = false, $badgename = false) {
        global $DB, $COURSE, $USER;
        // sort by new layer
        usort($records, function ($first, $second) {
            global $USER;
            if (!isset($first->issuedid)) {
                $first->issuedid = 0;
            }
            if (!isset($second->issuedid)) {
                $second->issuedid = 0;
            }
            $f = get_user_preferences('format_moointopics_new_badge_' . $first->issuedid, 0, $USER->id);
            $s = get_user_preferences('format_moointopics_new_badge_' . $second->issuedid, 0, $USER->id);
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
            // After the ajax call and save into the DB

            $value =  'badge' . '-' . $USER->id . '-' . $COURSE->id . '-' . $key;
            $name_value = 'user_have_badge-' . $value;
            // echo $value;
            // $value_check = $DB->record_exists('user_preferences', array('name'=>$name_value,'value' => $value));

            $image = html_writer::empty_tag('img', array('src' => $imageurl, 'class' => 'bg-image-' . $key, 'style' => $opacity));

            if (isset($record->uniquehash)) {
                $url = new moodle_url('/badges/badge.php', array('hash' => $record->uniquehash));
                $badgeisnew = get_user_preferences('format_moointopics_new_badge_' . $record->issuedid, 0, $USER->id);
            } else {
                $url = new moodle_url('/badges/overview.php', array('id' => $record->id));
                $badgeisnew = 0;
            }

            $detail = '';
            if ($details) {
                $user = $DB->get_record('user', array('id' => $record->userid));
                $detail = '<br />' . $user->firstname . ' ' . $user->lastname . '<br />(' . date('d.m.y H:i', $record->dateissued) . ')';
            } else if ($badgename) {
                $detail = '<br />' . $record->name;
            }

            $link = html_writer::link($url, $image . $detail, array('title' => $record->name));

            if (strcmp($opacity, " opacity: 0.15;") == 0 || $badgeisnew == 0) { // $value_check ||
                $lis .= html_writer::tag('li', $link, array('class' => 'all-badge-layer cid-badge-' . $COURSE->id, 'id' => 'badge-' . $key));
            } else {
                $lis .= html_writer::tag('li', $link, array('class' => 'new-badge-layer cid-badge-' . $COURSE->id, 'id' => 'badge-' . $key));
            }
        }

        echo html_writer::tag('ul', $lis, array('class' => 'badges-list badges'));
    }

    public static function get_user_and_availbale_badges($userid, $courseid) {
        global $CFG, $USER, $PAGE;
        $result = null;
        require_once($CFG->dirroot . '/badges/renderer.php');

        $coursebadges = self::get_badge_records($courseid, null, null, null);
        $userbadges = badges_get_user_badges($userid, $courseid, null, null, null, true);

        foreach ($userbadges as $ub) {
            if ($ub->status != 4) {

                $coursebadges[$ub->id]->highlight = true;
                $coursebadges[$ub->id]->uniquehash = $ub->uniquehash;
                $coursebadges[$ub->id]->issuedid = $ub->issuedid;
                // Save the badge direct into user_preferences table, later it'll be remove when the user click on the badge
            }
        }
        if ($coursebadges) {
            $result = self::print_badges_html($coursebadges, false, true, true);
        } else {
            $result = null;
        }
        return $result;
    }

    public static function get_badge_records($courseid = 0, $page = 0, $perpage = 0, $search = '') {
        global $DB, $PAGE;

        $params = array();
        $sql = 'SELECT
                    b.*
                FROM
                    {badge} b
                WHERE b.type > 0
                  AND b.status != 4 ';

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
     * @param int courseid
     * @return array
     */
    public static function show_certificat($courseid) {
        global $USER;
        $out_certificat = null;
        $templ = self::get_course_certificates($courseid, $USER->id);

        $templ = array_values($templ);
        if (isset($templ) && !empty($templ)) {
            if (is_string($templ) == 1) {
                $out_certificat = $templ;
            }
            if (is_string($templ) != 1) {
                $out_certificat .= html_writer::start_tag('div', ['class' => 'certificate_list']); // certificat_body
                for ($i = 0; $i < count($templ); $i++) {
                    if ($templ[$i]->url != '#') { // if certificate is issued to user
                        // has user already viewed the certificate?
                        $new = '';
                        $certmod = $templ[$i]->certmod;
                        $issuedid = $templ[$i]->issuedid;
                        if (get_user_preferences('format_moointopics_new_certificate_' . $certmod . '_' . $issuedid, 0, $USER->id) == 1) {
                            $new = ' new-certificate-layer';
                        }
                        $out_certificat .= html_writer::link($templ[$i]->url, ' ' . $templ[$i]->name, array('class' => 'certificate-img' . $new));
                    } else {

                        $out_certificat .= html_writer::span($templ[$i]->name, 'certificate-img'); // $templ[$i]->course_name . ' ' . $templ[$i]->index

                    }
                }
                $out_certificat .= html_writer::end_tag('div'); // certificat_body
            }
        } else {
            $out_certificat = null;
        }
        return  $out_certificat;
    }

    public static function set_new_certificate($awardedtoid, $issuedid, $modulename) {
        set_user_preference('format_moointopics_new_certificate_'.$modulename.'_'.$issuedid, true, $awardedtoid);
    }

    public static function unset_new_certificate($viewedbyuserid, $issuedid, $modulename) {
        global $DB;
        $tablename = 'ilddigitalcert_issued';
        if ($modulename == 'coursecertificate') {
            $tablename = 'tool_certificate_issues';
        }
        else if ($modulename == 'ilddigitalcert') {
            $tablename = 'ilddigitalcert_issued';
        }
        $sql = 'SELECT * from {'.$tablename.'}
                 WHERE id = :id
                   AND userid = :userid ';
        $params = array('tablename' => $tablename,
                        'id' => $issuedid,
                        'userid' => $viewedbyuserid);
    
        if ($record = $DB->get_record_sql($sql, $params)) {
            if ($record->userid == $viewedbyuserid) {
                unset_user_preference('format_moointopics_new_certificate_'.$modulename.'_'.$record->id, $viewedbyuserid);
            }
        }
    }

    static function get_user_coordinates($user) {
        if ($user->city != '') {
            $coordinates = new stdClass();
    
            $url = get_config('format_moointopics', 'geonamesapi_url');
            $apiusername = get_config('format_moointopics', 'geonamesapi_username');
    
            $response = self::get_url_content($url, "/search?username=".$apiusername."&maxRows=1&q=".urlencode($user->city)."&country=".urlencode($user->country));
    
            if($response != "" && $xml = simplexml_load_string($response)) {
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
 * @uses $CFG
 * @return String body of the returned request
 */
static function get_url_content($domain, $path){

	global $CFG;

	$message = "GET $domain$path HTTP/1.0\r\n";
	$msgaddress = str_replace("http://","",$domain);
	$message .= "Host: $msgaddress\r\n";
    $message .= "Connection: Close\r\n";
    $message .= "\r\n";

	if($CFG->proxyhost != "" && $CFG->proxyport != 0){
    	$address = $CFG->proxyhost;
    	$port = $CFG->proxyport;
	} else {
		$address = str_replace("http://","",$domain);
    	$port = 80;
	}

    /* Attempt to connect to the proxy server to retrieve the remote page */
    if(!$socket = fsockopen($address, $port, $errno, $errstring, 20)){
        echo "Couldn't connect to host $address: $errno: $errstring\n";
        return "";
    }

    fwrite($socket, $message);
    $content = "";
    while (!feof($socket)){
            $content .= fgets($socket, 1024);
    }

    fclose($socket);
    $retStr = extract_body($content);
    return $retStr;
}

    public static function set_new_badge($awardedtoid, $badgeissuedid) {
        set_user_preference('format_mooin4_new_badge_'.$badgeissuedid, true, $awardedtoid);
    }
    
    public static function unset_new_badge($viewedbyuserid, $badgehash) {
        global $DB;
        $sql = "select * from {badge_issued} where " . $DB->sql_compare_text('uniquehash') . " = :badgehash";
        $params = array('badgehash' => $badgehash);
        if ($records = $DB->get_records_sql($sql, $params)) {
            if (count($records) == 1) {
                if ($records[array_key_first($records)]->userid == $viewedbyuserid) {
                    unset_user_preference('format_mooin4_new_badge_'.$records[array_key_first($records)]->id, $viewedbyuserid);
                }
            }
        }
    }

}