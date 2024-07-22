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


class participantslib {

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
}
