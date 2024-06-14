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


class forumlib {

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
        global $DB;

        $sql = 'SELECT fp.*
                  FROM {forum_posts} as fp,
                       {forum_discussions} as fd,
                       {forum} as f,
                       {course_modules} as cm
                 WHERE fp.discussion = fd.id
                   AND fd.forum = f.id
                   AND f.course = :courseid
                   AND cm.instance = f.id
                   AND cm.visible = 1
                   AND (fp.mailnow = 1 OR fp.created < :wait) ';
        if ($forumid > 0) {
            $sql .= 'AND f.id = :forumid ';
        } else if ($news) {
            $sql .= 'AND f.type = :news ';
        } else {
            $sql .= 'AND f.type != :news ';
        }

        $sql .= '  AND fp.id not in (SELECT postid
                                      FROM {forum_read}
                                     WHERE userid = :userid) ';

        $params = array(
            'courseid' => $courseid,
            'news' => 'news',
            'userid' => $userid,
            'forumid' => $forumid,
            'wait' => time() - 1800
        );

        $unreadposts = $DB->get_records_sql($sql, $params);
        return count($unreadposts);
    }

    /**
     * Get the last forum discussion in the course
     * @param int $courseid
     * @param string @forum_type
     * @return array
     */
    public static function get_last_forum_discussion($courseid, $forum_type) {
        global $DB, $OUTPUT, $USER;

        $sql = 'SELECT fp.*, f.id as forumid
                FROM {forum_posts} as fp,
                    {forum_discussions} as fd,
                    {forum} as f
                WHERE fp.discussion = fd.id
                AND fd.forum = f.id
                AND f.course = :courseid
                AND (fp.mailnow = 1 OR fp.created < :wait)
                AND f.type != :news ';
        $sql .= 'ORDER BY fp.created DESC LIMIT 1 ';

        $params = array(
            'courseid' => $courseid,
            'news' => 'news',
            'wait' => time() - 1800
        );

        if ($latestpost = $DB->get_records_sql($sql, $params)) {
            $new_in_course = $latestpost;
        }
        //*/
        // Some test to fetch the forum with discussion within it
        // get the news annoucement & forum discussion for a specific news or forum
        // var_dump($new_in_course);
        if (!empty($new_in_course) && count($new_in_course) > 0) {
            $out = null;
            foreach ($new_in_course as $key => $value) {
                if (!empty($value->userid)) {

                    $user = $DB->get_record('user', ['id' => $value->userid], '*');

                    // Get the right date for new creation
                    $created_news = date("d.m.Y, G:i", date((int)$value->created));

                    $sql = 'SELECT * FROM mdl_forum WHERE course = :cid AND type != :tid ORDER BY ID ASC';
                    $param = array('cid' => $courseid, 'tid' => 'news');
                    $oc_foren = $DB->get_records_sql($sql, $param);
                    $cond_in_forum_posts = 'SELECT * FROM mdl_forum_discussions WHERE course = :id ORDER BY ID DESC LIMIT 1';
                    $param =  array('id' => $courseid);
                    $oc_f = $DB->get_record_sql($cond_in_forum_posts, $param);
                    $ar_for = (array)$oc_foren;
                    $new_news = false;
                    $small_countcontainer = false;

                    if (count($ar_for) > 1 || count((array)$oc_f) != 0) {
                        $unread_forum_number = self::count_unread_posts($USER->id, $courseid, false);

                        // if ($unread_forum_number == 1) {
                        //     $new_news = html_writer::start_span('count-container d-inline-flex inline-badge fw-700 mr-1') . $unread_forum_number . html_writer::end_span();
                        //     $new_news .= html_writer::link($url_disc, get_string('unread_discussions_single', 'format_mooin4') . get_string('discussion_forum', 'format_mooin4'), array('title' => get_string('discussion_forum', 'format_mooin4'), 'class' => 'primary-link'));
                        // }
                        // if ($unread_forum_number > 1) {
                        //     $new_news = html_writer::start_span('count-container d-inline-flex inline-badge fw-700 mr-1') . $unread_forum_number . html_writer::end_span();
                        //     $new_news .= html_writer::link($url_disc, get_string('unread_discussions', 'format_mooin4') . get_string('discussion_forum', 'format_mooin4'), array('title' => get_string('discussion_forum', 'format_mooin4'), 'class' => 'primary-link'));
                        // }
                        // if ($unread_forum_number >= 99) {
                        //     $small_countcontainer = true;
                        //     $new_news = html_writer::start_span('count-container count-container-small d-inline-flex inline-badge fw-700 mr-1') . "99+" . html_writer::end_span();
                        //     $new_news .= html_writer::link($url_disc, get_string('unread_discussions', 'format_mooin4') . get_string('discussion_forum', 'format_mooin4'), array('title' => get_string('discussion_forum', 'format_mooin4'), 'class' => 'primary-link'));
                        // }

                    } else {
                        $new_news = false;
                        //$out .= html_writer::link($url_disc, get_string('all_forums', 'format_mooin4'), array('title' => get_string('all_forums', 'format_mooin4'))); // newsurl
                    }




                    // Get the user id for the one who created the news or forum
                    $user_news = self::user_print_forum($courseid);

                    $forum_discussion_url = new moodle_url('/mod/forum/discuss.php', array('d' => $value->discussion));
                    $templatecontext = [
                        'user_firstname' =>  $user->firstname,
                        'created_news' => $created_news,
                        'user_picture' => $OUTPUT->user_picture($user, array('courseid' => $courseid)),
                        'news_title' => $value->subject,
                        'news_text' => $value->message,
                        'discussion_url' => $forum_discussion_url,
                        'unread_news_number' => $unread_forum_number,
                        'new_news' => $new_news,
                        'small_countcontainer' => $small_countcontainer
                    ];
                }
            }
        } else {
            $templatecontext = [
                'unread_news_number' => 0,
                'no_discussions_available' => true,
                'no_news' => false,
                'new_news' => false
            ];
        }
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
}
