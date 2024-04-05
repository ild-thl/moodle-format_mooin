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
                $params = array('ilddigitalcertid' => $ilddigitalcert->id,
                                'userid' => $userid,
                                'ueid' => $ueid);
                if ($issued = $DB->get_record_sql($sql, $params)) {
                    $certificate->userid = $userid;
                    $certificate->url = $CFG->wwwroot.'/mod/ilddigitalcert/view.php?id='.$issued->cmid.'&issuedid='.$issued->id.'&ueid='.$ueid;
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
                if ($issued = $DB->get_record('tool_certificate_issues' ,array('userid' => $userid, 'courseid' => $courseid))) {
                    $url = '#';
                    $sql = 'SELECT *
                              FROM {modules} as m , {course_modules} as cm
                             WHERE m.name = :coursecertificate
                               AND cm.module = m.id
                               AND cm.instance = :coursecertificateid ';
                    $params = array('coursecertificate' => 'coursecertificate',
                                    'coursecertificateid' => $coursecertificate->id);
                    if ($cm = $DB->get_record_sql($sql, $params)) {
                        $url = $CFG->wwwroot.'/mod/coursecertificate/view.php?id='.$cm->id;
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
}
