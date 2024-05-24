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


class certificateslib {

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

    public static function set_new_certificate($awardedtoid, $issuedid, $modulename) {
        set_user_preference('format_mooin4_new_certificate_' . $modulename . '_' . $issuedid, true, $awardedtoid);
    }

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
        $params = array(
            'tablename' => $tablename,
            'id' => $issuedid,
            'userid' => $viewedbyuserid
        );

        if ($record = $DB->get_record_sql($sql, $params)) {
            if ($record->userid == $viewedbyuserid) {
                unset_user_preference('format_mooin4_new_certificate_' . $modulename . '_' . $record->id, $viewedbyuserid);
            }
        }
    }

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
                $out_certificat .= html_writer::start_tag('div', ['class' => 'certificat_list']); // certificat_body
                for ($i = 0; $i < count($templ); $i++) {
                    
                    if ($templ[$i]->url != '#') { // if certificate is issued to user
                        // has user already viewed the certificate?
                        $new = '';
                        $certmod = $templ[$i]->certmod;
                        $issuedid = $templ[$i]->issuedid;
                        if (get_user_preferences('format_mooin4_new_certificate_' . $certmod . '_' . $issuedid, 0, $USER->id) == 1) {
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
}
