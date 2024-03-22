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


class badgeslib {

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
        usort($records, function($first, $second){
            global $USER;
            if (!isset($first->issuedid)) {
                $first->issuedid = 0;
            }
            if (!isset($second->issuedid)) {
                $second->issuedid = 0;
            }
            $f = get_user_preferences('format_moointopics_new_badge_'.$first->issuedid, 0, $USER->id);
            $s = get_user_preferences('format_moointopics_new_badge_'.$second->issuedid, 0, $USER->id);
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
    
            $value =  'badge'.'-'. $USER->id .'-' . $COURSE->id . '-' . $key;
            $name_value = 'user_have_badge-'.$value;
            // echo $value;
            // $value_check = $DB->record_exists('user_preferences', array('name'=>$name_value,'value' => $value));
    
            $image = html_writer::empty_tag('img', array('src' => $imageurl, 'class' => 'bg-image-'.$key, 'style' => $opacity));
    
            if (isset($record->uniquehash)) {
                $url = new moodle_url('/badges/badge.php', array('hash' => $record->uniquehash));
                $badgeisnew = get_user_preferences('format_mooin4_new_badge_'.$record->issuedid, 0, $USER->id);
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
                $lis .= html_writer::tag('li', $link, array('class' => 'all-badge-layer cid-badge-'.$COURSE->id , 'id'=>'badge-' . $key));
            } else {
                $lis .= html_writer::tag('li', $link, array('class' => 'new-badge-layer cid-badge-'.$COURSE->id , 'id'=>'badge-' . $key));
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
            //$result .= html_writer::start_span() . get_string('no_badges_available', 'format_mooin4') . html_writer::end_span();
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

    
}
