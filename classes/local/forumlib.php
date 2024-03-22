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

                if ($unread_news_number == 1) {
                    $new_news = html_writer::start_span('count-container d-inline-flex inline-badge fw-700 mr-1') . $unread_news_number . html_writer::end_span();
                    $new_news .= html_writer::link($newsurl, get_string('unread_news_single', 'format_moointopics') . get_string('all_news', 'format_moointopics'), array('title' => get_string('all_news', 'format_mooin4'), 'class' => 'primary-link'));
                } else if ($unread_news_number > 1) {
                    $new_news .= html_writer::start_span('count-container d-inline-flex inline-badge fw-700 mr-1') . $unread_news_number . html_writer::end_span(); //Notification Counter
                    $new_news .= html_writer::link($newsurl, get_string('unread_news', 'format_moointopics') . get_string('all_news', 'format_moointopics'), array('title' => get_string('all_news', 'format_mooin4'), 'class' => 'primary-link'));
                } else {
                    $new_news = false;
                }
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
                'new_news' => $new_news
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
}
