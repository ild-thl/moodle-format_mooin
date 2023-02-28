<?php
// require_once('../../../mod/forum/lib.php');

/**
 * Count the number of course modules with completion tracking activated
 * in this section, and the number which the student has completed
 * Exclude labels if we are using sub tiles, as these are not checkable
 * Also exclude items the user cannot see e.g. restricted
 * @param array $sectioncmids the ids of course modules to count
 * @param array $coursecms the course module objects for this course
 * @return array with the completion data x items complete out of y
 */
function section_progress($sectioncmids, $coursecms) {
    $completed = 0;
    $outof = 0;

    global $DB, $USER, $COURSE;
    foreach ($sectioncmids as $cmid) {

        $thismod = $coursecms[$cmid];
        // var_dump($thismod);
        if ($thismod->uservisible && !$thismod->deletioninprogress) {
            // if ($thismod->modname == 'label') {
                $outof = 1;
                // echo gettype($thismod->section);
                $com_value = $USER->id . '-' . $COURSE->id . '-' . $thismod->section;  //$thismod->sectionnum
                $value_pref = $DB->record_exists('user_preferences', array('value' => $com_value));
                if ($value_pref) {
                    $completed = 1;
                    }else {
                    $completed = 0;
                }
            // }
        }
    }
    return array('completed' => $completed, 'outof' => $outof);
}
function section_empty($course_section) {
    $completed = 0;
    $outof = 0;

    global $DB, $USER, $COURSE;
    if($course_section->visible) {
        $outof = 1;
        $com_value = $USER->id . '-' . $COURSE->id . '-' . $course_section->id;
        $value_pref = $DB->record_exists('user_preferences', array('value' => $com_value));
        if ($value_pref) {
            $completed = 1;
        }else {
            $completed = 0;
        }
    }
    return array('completed' => $completed, 'outof' => $outof);
}
/**
 * Prepare the data required to render a progress indicator (.e. 2/3 items complete)
 * to be shown on the tile or as an overall course progress indicator
 * @param int $numcomplete how many items are complete
 * @param int $numoutof how many items are available for completion
 * @param boolean $aspercent should we show the indicator as a percentage or numeric
 * @param boolean $isoverall whether this is an overall course completion indicator
 * @return array data for output template
 */
function completion_indicator($numcomplete, $numoutof, $aspercent, $isoverall) {
    $percentcomplete = $numoutof == 0 ? 0 : round(($numcomplete / $numoutof) * 100, 0);
    $progressdata = array(
        'numComplete' => $numcomplete,
        'numOutOf' => $numoutof,
        'percent' => $percentcomplete,
        'isComplete' => $numcomplete > 0 && $numcomplete == $numoutof ? 1 : 0,
        'isOverall' => $isoverall,
    );
    if ($aspercent) {
        // Percent in circle.
        $progressdata['showAsPercent'] = true;
        $circumference = 106.8;
        $progressdata['percentCircumf'] = $circumference;
        $progressdata['percentOffset'] = round(((100 - $percentcomplete) / 100) * $circumference, 0);
    }
    $progressdata['isSingleDigit'] = $percentcomplete < 10 ? true : false; // Position single digit in centre of circle.
    return $progressdata;
}
/**
 * Set a section Label activity as done
 *
 * @param array $section (argument not used)
 * @param int $userid (argument not used)
 * @param int $cid (argument not used)
 */
function complete_section($section, $cid, $userid) {
    global $DB;


    $res = false;

        $label_complete = $userid . '-' . $cid . '-' . $section;

        $value_check = $DB->record_exists('user_preferences', array('value' => $label_complete));
        $id = $DB->count_records('user_preferences', array('userid'=> $userid));


        if (!$value_check ) { // isset($_POST["id_bottom_complete-".$section]) ||  array_key_exists('btnComplete-'.$section, $_POST)

            $res = true;
            $values = new stdClass();
            $values->id = $id + 1;
            $values->userid = $userid;
            $values->name = 'section_progress_label-' . $label_complete;
            $values->value = $label_complete;

            // if (!$value_check) {
                $DB->insert_record('user_preferences',$values, true, false );
            // }
        }
    // Check the DB in Table course_sections, to see how many label was inside the section and update the completion value for each lignes
    /* $sequences_in_sections = $DB->get_record('course_sections', ['course'=> $cid, 'section'=>$section], 'sequence', IGNORE_MISSING);
    var_dump($sequences_in_sections->sequence);
    // transform the result to and array, so that we can loop throught and make some resquest in table course_modules for updating the completion value for each ligne

    $result_to_array = explode(",", $sequences_in_sections->sequence);
    if (count($result_to_array) > 1) {
        $update_completion_in_module = new stdClass();
        foreach ($result_to_array as $key => $value) {
            $update_completion_in_module = $DB->get_record('course_modules', ['id'=>$value], '*', IGNORE_MISSING);
            if ($update_completion_in_module == '13') {
                // Update the DB course_modules_completion, by addind a new row
                $sql = 'SELECT *
                        FROM {course_modules_completion} cmc
                        ORDER BY cmc.id  LIMIT 1';

                $params_array = [];
                $last_value = $DB->get_record_sql($sql, $params_array);
                $res = true;
                // insert a new value under table course_modules_completion to save the new entry
                $insert_data = new stdClass();
                $insert_data->id = $last_value->id + 1;
                $insert_data->coursemoduleid = $update_completion_in_module->section;
                $insert_data->userid = $userid;
                $insert_data->completionstate = 1;
                $insert_data->viewed = 0;
                $insert_data->overrideby = NULL;
                $insert_data->timemodified = time();

                $DB->insert_record('course_modules_completion', $insert_data, true, false);

            }
        }
    } else {
        $update_completion_in_module = $DB->get_record('course_modules', ['id'=>$sequences_in_sections->sequence], '*', IGNORE_MISSING);
        if ($update_completion_in_module) {
            // Update the DB course_modules_completion, by addind a new row
            $sql = 'SELECT *
                    FROM {course_modules_completion} cmc
                    ORDER BY cmc.id  LIMIT 1';

            $params_array = [];
            $last_value = $DB->get_record_sql($sql, $params_array);
            $res = true;
            // insert a new value under table course_modules_completion to save the new entry
            $insert_data = new stdClass();
            $insert_data->id = $last_value->id + 1;
            $insert_data->coursemoduleid = $update_completion_in_module->section;
            $insert_data->userid = $userid;
            $insert_data->completionstate = 1;
            $insert_data->viewed = 0;
            $insert_data->overrideby = NULL;
            $insert_data->timemodified = time();

            $DB->insert_record('course_modules_completion', $insert_data, true, false);

        }
    } */


    return $res;
}
    /**
     * get the section grade function
    */
    function get_section_grades(&$section) {
        global $DB, $CFG, $USER, $COURSE, $SESSION;
        require_once($CFG->libdir . '/gradelib.php');

        if (isset($section)) {
            // $mods = get_course_section_mods($COURSE->id, $section);//print_object($mods);
            // Find a way to get the right section from the DB

            $sec = $DB->get_record_sql("SELECT cs.id
                            FROM {course_sections} cs
                            WHERE cs.course = ? AND cs.section = ?", array($COURSE->id, $section));

            $mods = $DB->get_records_sql("SELECT cm.*, m.name as modname
                            FROM {modules} m, {course_modules} cm
                        WHERE cm.course = ? AND cm.section= ? AND cm.completion !=0 AND cm.module = m.id AND m.visible = 1", array($COURSE->id, (int)$sec->id));


            $percentage = 0;
            $mods_counter = 0;

            foreach ($mods as $mod) {
                if ($mod->visible == 1) { // ($mod->modname == 'hvp') &&
                    $skip = false;

                    if (isset($mod->availability)) {
                        $availability = json_decode($mod->availability);
                        foreach ($availability->c as $criteria) {
                            if ($criteria->type == 'language' && ($criteria->id != $SESSION->lang)) {
                                $skip = true;
                            }
                        }
                    }
                    if (!$skip) {
                        $grading_info = grade_get_grades($mod->course, 'mod', $mod->modname, $mod->instance, $USER->id); // 'hvp'
                            $grading_info = (object)($grading_info);// new, convert an array to object
                        if ($mod->modname == 'forum') {
                            $user_grade = $grading_info->items[1]->grades[$USER->id]->grade;
                        } else {
                            $user_grade = $grading_info->items[0]->grades[$USER->id]->grade;
                        }

                        $percentage += $user_grade;
                        $mods_counter++;
                        // var_dump($grading_info->items[0]);
                    }
                }
            }

            if ($mods_counter != 0) {

                return ($percentage / $mods_counter);
            } else {
                return -1;
            }
        } else {
            return -1;
        }
    }

    // Test
    /**
     * Get the section grade or the modules completion from activity
     * @parameter int section
     * return int
    */
    function get_progress($courseId, $sectionId) {
        global $DB, $CFG, $USER, $SESSION, $PAGE;
        require_once($CFG->libdir . '/gradelib.php');

        $sec = $DB->get_record_sql("SELECT cs.id
                            FROM {course_sections} cs
                            WHERE cs.course = ? AND cs.section = ?", array($courseId, $sectionId));

        $cm = $DB->get_records_sql("SELECT cm.*, m.name as modname
                    FROM {modules} m, {course_modules} cm
                    WHERE cm.course = ? AND cm.section= ? AND cm.module = m.id AND cm.completion >=0 ", array($courseId, $sectionId)); // AND m.visible = 1

        if (count($cm) == 0) {
            return false;
        }

        if(isset($SESSION->lang)) {
            $user_lang = $SESSION->lang;
        } else {
            $user_lang = $USER->lang;
        }

        //var_dump($cm);
        if (isset($sectionId)) {
            $percentage = 0;
            $mods_counter = 0;

            foreach ($cm as $mod) {
                    if ($mod->visible == 1) {
                        $skip = false;

                        if (isset($mod->availability)) {
                            $availability = json_decode($mod->availability);
                            foreach ($availability->c as $criteria) {
                                if ($criteria->type == 'language' && ($criteria->id != $user_lang)) {
                                    $skip = true;
                                }
                            }
                        }

                        if ($mod->completion == 0) {
                            $skip = true;
                        }

                        if (!$skip) {
                            $grading_info = grade_get_grades($mod->course, 'mod', $mod->modname, $mod->instance, $USER->id); // hvp
                            $grading_info = (object)$grading_info;
                            $max_grade = 100;

                             if ($mod->modname != 'hvp') {

                                if (count($grading_info->items) >= 1 && isset($grading_info->items[0]->grades[$USER->id]->grade)) {
                                    $user_grade = $grading_info->items[0]->grades[$USER->id]->grade;
                                } else {

                                    $exist_check = $DB->record_exists('course_modules_completion', ['coursemoduleid'=>$mod->id, 'userid'=>$USER->id]);
                                    // mod.section == course_sections.id
                                    if ($exist_check) {
                                        $user_grade = $max_grade;
                                    }else {
                                        $user_grade = 0;
                                    }
                                }

                            } else {
                                $user_grade = $grading_info->items[0]->grades[$USER->id]->grade;

                            }

                            $percentage += $user_grade;
                            $mods_counter++;

                        }
                    }
                // }
            }


            if ($mods_counter != 0) {
                $progress = array('sectionid' => $sectionId, 'percentage' => $percentage / $mods_counter);
                return $progress;
            } else {
                return -1;
            }
        } else {
            return -1;
        }
        // return $progress;
    }

    /**
    * Get  Progress bar
    */
    function get_progress_bar($p, $width, $sectionid = 0) {
        //$p_width = $width / 100 * $p;
        $result = html_writer::tag('div',
                    html_writer::tag('div',
                        html_writer::tag('div',
                            '',
                            array('style' => 'width: ' . $p . '%; height: 15px; border: 0px; background: #7fb99f; text-align: center; float: left; border-radius: 12px; transition: width 2s ease-in-out', 'id' => 'mooin4ection' . $sectionid)
                        ),
                        array('style' => 'width: ' . $width . '%; height: 15px; border: 1px; background: #C4DDD2; solid #aaa; margin: 0 auto; padding: 0;  border-radius: 12px;') //
                    ) .
                    // html_writer::tag('div', $p .'% der Lektion bearbeitet', array('style' => 'float: right; padding: 0; position: relative; color: #555; width: 100%; font-size: 12px; transform: translate(-50%, -50%);margin-top: -8px;left: 50%;','id' => 'mooin4ection-text-' . $sectionid)) .
                    html_writer::tag('div', '', array('style' => 'clear: both;'))  .
                    // html_writer::start_span('',['style' => 'float: left;font-size: 12px; margin-left: 12px; margin-top: 5px;font-weight: bold']) . $p . '% ' . html_writer::end_span() . // .' % bearbeitet'
                    // html_writer::tag('div', $p .'% der Lektion bearbeitet', array('style' => 'float: right; padding: 0; position: relative; color: #555; width: 100%; font-size: 12px; transform: translate(-50%, -50%);left: 50%;','id' => 'mooin4ection-text-' . $sectionid)) . // margin-top: -8px;
                    html_writer::tag('div', '<b>'.$p.'% </b> der Lektion bearbeitet', array('style' => 'float: left; font-size: 12px; display: contents; margin-left: 12px; padding-left: 5px;color: #555; width: 100%', 'id' => 'mooin4ection-text-' . $sectionid)) , // text-align: center; position: absolute;
                    array('class' => 'mooin4ection-div')); // float: left; position: absolute;
        return $result;
    }

// Badges functions
/**
 *
 */
function print_badges($records, $details = false, $highlight = false, $badgename = false) {
    global $DB, $COURSE, $USER, $PAGE;


    $lis = '';
    // echo count(badges_get_user_badges($USER->id, $COURSE->id, null, null, null, null));
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

        $image = html_writer::empty_tag('img', array('src' => $imageurl, 'class' => 'bg-image-'.$key, 'style' => 'width: 100px; height: 100px;' . $opacity));

        if (isset($record->uniquehash)) {
            $url = new moodle_url('/badges/badge.php', array('hash' => $record->uniquehash));
            $badgeisnew = get_user_preferences('format_mooin_new_badge_'.$record->issuedid, 0, $USER->id);
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

/**
 * Function to remove the new badge
 * @parameter int position_in_the_list
 * @parameter int user_id
 * @paremater int course_id
 *
 * save the result in the user_preferences DB
*/
function badge_remove($user_id, $course_id, $badge_position) {
    global $DB;

    // TO-DO
    $result = false;
    $value = 'badge' . '-' .$user_id . '-' . $course_id . '-' . $badge_position;
    $name_value = 'user_have_badge-'.$value;
        // Check if the value already in the DB
    $value_check = $DB->record_exists('user_preferences', array('name' => $name_value,'value' => $value));
    // Make a DB request in user_preferences
    $preferences = $DB->get_records('user_preferences',[], 'id', '*');


    $data_preferences = new stdClass();

    $data_preferences->id = count($preferences) + 1;
    $data_preferences->userid = $user_id;
    $data_preferences->name = $name_value;
    $data_preferences->value = $value;
    if (!$value_check) {
        $result = true;
        $DB->insert_record('user_preferences',$data_preferences, true, false );
    }

    return $result;
}
/**
 *
 */
function display_user_and_availbale_badges($userid, $courseid) {
    global $CFG, $USER, $PAGE;
    $result = null;
    require_once($CFG->dirroot . '/badges/renderer.php');

    $coursebadges = get_badges($courseid, null, null, null);
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
        $result = print_badges($coursebadges, false, true, true);
    } else {
        //$result .= html_writer::start_span() . get_string('no_badges_available', 'format_mooin') . html_writer::end_span();
        $result = null;
    }
    return $result;
}

/**
 *
 */
function get_badges($courseid = 0, $page = 0, $perpage = 0, $search = '') {
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
 *
 */
function get_badges_since($courseid, $since, $global = false) {
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
/**
 *
 */
function display_badges($userid = 0, $courseid = 0, $since = 0, $print = true) {
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
            $records = get_badges($courseid, null, null, null);
        } else {
            $records = get_badges_since($courseid, $since, false);
            // globale Badges
            // if ($courseid != 0) {
            // $records = array_merge(get_badges_since($courseid, $since, true), $records);
            // }
        }
        $renderer = new core_badges_renderer($PAGE, '');

        // Print local badges.
        if ($records) {
            //$right = $renderer->print_badges_list($records, $userid, true);
            if ($since == 0) {
                print_badges($records);
            } else {
                print_badges($records, true);
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

// Participants functions
/**
 * Returns Bagdes for a specific user in a specific course
 *
 * @return string badges
 */
function get_badges_list($userid, $courseid = 0) {
    global $CFG, $USER, $DB;
    require_once($CFG->dirroot . '/badges/renderer.php');

    if ($courseid == 0) {
        $context = context_system::instance();
    } else {
        $context = context_course::instance($courseid);
    }

    if ($USER->id == $userid || has_capability('moodle/badges:viewotherbadges', $context)) {
        if ($courseid == 0) {
            $records = get_global_user_badges($userid);
        } else {
            $records = badges_get_user_badges($userid, $courseid, null, null, null, true);
            // $records = $DB->get_records('badge_issued', ['userid' =>$userid]);

            //var_dump($records);
        }
        // Print local badges.

        //var_dump($context);
        if ($records) {
            $out = '';

            foreach ($records as $record) {
                // $val = $DB->get_record('badge', ['id' =>$record->badgeid]);
                $imageurl = moodle_url::make_pluginfile_url($context->id, 'badges', 'badgeimage', $record->id, '/', 'f1', false); // $context->id == $record->type
                $image = html_writer::empty_tag('img', array('src' => $imageurl, 'class' => 'badge-image', 'style' => 'width: 30px; height: 30px;'));
                $url = new moodle_url('/badges/badge.php', array('hash' => $record->uniquehash));
                $link = html_writer::link($url, $image, ['title'=>$record->name]);
                $out .= $link;
            }
            return $out;
        }
    }
}

/**
 * Returns SQL that can be used to limit a query to a period where the user last accessed a course..
 *
 * @param string $accesssince
 * @return string
 */
function get_course_lastaccess_sql($accesssince='') {
    if (empty($accesssince)) {
        return '';
    }
    if ($accesssince == -1) { // Never.
        return 'ul.timeaccess = 0';
    } else {
        return 'ul.timeaccess != 0 AND ul.timeaccess < '.$accesssince;
    }
}

/**
 * Returns SQL that can be used to limit a query to a period where the user last accessed the system.
 *
 * @param string $accesssince
 * @return string
 */
function get_user_lastaccess_sql($accesssince='') {
    if (empty($accesssince)) {
        return '';
    }
    if ($accesssince == -1) { // Never.
        return 'u.lastaccess = 0';
    } else {
        return 'u.lastaccess != 0 AND u.lastaccess < '.$accesssince;
    }
}
function cmp_badges_asc($a, $b) {
	if($a->badgecount == $b->badgecount){
        return 0;
    }
    return ($a->badgecount < $b->badgecount) ? -1 : 1;
}

function cmp_badges_desc($a, $b) {
	if($a->badgecount == $b->badgecount){
        return 0;
    }
    return ($a->badgecount > $b->badgecount) ? -1 : 1;
}

// Certificate Functions
/**
 * Get the number of certificate in a course for a special user
 * @param int userid
 * @param int courseid
 *
 * @return array ( for not have and complited certificat)
*/
function count_certificate($userid, $courseid){
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
/**
 * Get certificat in a course
 * @param int courseid
 * @return array
 */
function get_certificates($courseid) {

    global $DB, $USER;
    $templatedata = array();
    $templatedata1 = array();
    $templatedata2 = array();
    $anothertemplatedata = [];
    // $tables = $DB->get_tables_from_schema();
    $table = new xmldb_table('ilddigitalcert_issued');
    $table_course_certificate = new xmldb_table('coursecertificate');
    $course = $DB->get_record('course', ['id' =>$courseid]);
    if($DB->get_manager()->table_exists($table)) {
        $pe = $DB->get_records('ilddigitalcert_issued', ['courseid'=>$courseid], 'id', '*');

        $he = $DB->get_record('modules', ['name' =>'ilddigitalcert']);


        if ($he == true) {
            $te = $DB->get_records('course_modules', ['module' =>$he->id]);
        } else {
            $te = [];
        }


        // $ze = $DB->get_records('course_sections', ['course' =>$courseid]);

        $a = 1;
        foreach ($pe as $key => $value) {
            foreach ($te as $k => $v) {
                if ($value->cmid == $v->id) {
                    // var_dump($v);
                    // $cm_id = $v->id;
                    array_push($templatedata1, (object)[
                        'id'=> $v->id,
                        'index' => $a++,
                        'module' => $value->module,
                        'section' => $v->section,
                        'enrolmentid' => $value->enrolmentid,
                        'courseid' => $value->courseid,
                        'certificat_id' => $value->id,
                        'user_id' => $value->userid,
                        'component'=>'mod_ilddigitalcert',
                        'name' => $value->name,
                        'preview_url' => '#'

                    ]) ;
                }
            }
        }
        // var_dump($templatedata);
        // Build two array of certificat the receive one and the not
        $user_cert = [];
        $template_cert_id = [];
        $u_cer = [];
        $user_dont_cert = [];
        $ot_temp_cert = [];

        for($i = 0; $i < count($templatedata1); $i++) {
            if($templatedata1[$i]->user_id == $USER->id) {
                array_push($u_cer, $templatedata1[$i]->section);
                array_push($user_cert, $templatedata1[$i]);
            } else if( ($i  < count($templatedata1) && $templatedata1[$i]->section != $templatedata1[$i + 1]->section)) {
                // 
                if(( $templatedata1[$i]->user_id != $USER->id)) {
                    array_push($ot_temp_cert,$templatedata1[$i]->section);
                    array_push($user_dont_cert, $templatedata1[$i]); 
                }
                                   
            }
        }
        

        if(count($user_cert) > 0) {
            $templatedata1 = $user_cert;
            foreach($templatedata1 as $td) {
                array_push($template_cert_id, $td->section); 
           }
        }
        
        if(count($user_dont_cert) > 0) {
            // what should we do if the current user doesn't have any certificate
            foreach($user_dont_cert as $other_user_c) {
                if(!in_array($other_user_c->section,array_values($template_cert_id))) {
                    array_push($templatedata1, $other_user_c);
                }
            }
        }
        
        if (count($templatedata1) > 0) {
            for ($i=0; $i < count($templatedata1); $i++) {
                for($j = count($templatedata1) - 1; $j >= 0 ;$j--){
                    // $templatedata[$i]->certificate_name = 'Certificate';

                    /* if( isset($templatedata1[$j]->user_id) && $templatedata1[$i]->user_id != $templatedata1[$j]->user_id ){
                        unset($templatedata1[$i]);                       
                    } */
                    /* if(isset($templatedata1[$i]->user_id) && $templatedata1[$i]->user_id != $templatedata1[$j]->user_id ){
                        unset($templatedata1[$j]);                       
                    } */

                    if($USER->id == $templatedata1[$i]->user_id) {
                        $templatedata1[$i]->preview_url = (
                            new moodle_url(
                                '/mod/ilddigitalcert/view.php',
                                array("id" => $templatedata1[$i]->id, 'issuedid' => $templatedata1[$i]->certificat_id, 'ueid'=>$templatedata1[$i]->enrolmentid)
                            )
                        )->out(false);
                        $templatedata1[$i]->course_name = $course->fullname;
                    } else {
                        /* if($templatedata1[$i]->preview_url == '#') {
                            $templatedata1[$i]->preview_url = (
                                new moodle_url(
                                    "#"
                                )
                            )->out(false);
                        }  */  
                    }                    
                }
            }
        } else {
            $templatedata1 = [];
        }
       

    }  else {
        //$templatedata =  $OUTPUT->heading(get_string('certificate_overview', 'format_mooin'));
        $templatedata1 = [];
    }
    if($DB->get_manager()->table_exists($table_course_certificate)){
        // coursecertificate == cc
        $pe = $DB->get_records('tool_certificate_issues', ['courseid'=>$courseid], 'id', '*');

        
        $he = $DB->get_record('modules', ['name' =>'course_secrtificate']);


        if ($he == true) {
            $te = $DB->get_records('course_modules', ['module' =>$he->id]);
        } else {
            $te = [];
        }
        $number_certificate_in_cc = $DB->get_records('coursecertificate', ['course'=>$courseid], 'id', '*');
        $number_certificate_in_tool_cert_issues = $DB->get_records('tool_certificate_issues', ['courseid'=>$courseid, 'userid'=>$USER->id], 'id', '*');
        $module_req = $DB->get_record('modules', ['name' =>'coursecertificate']);
        if ($module_req == true) {
            $te = $DB->get_records('course_modules', ['module' =>$module_req->id]);
        } else {
            $te = [];
        }
        if(!$number_certificate_in_tool_cert_issues && !$number_certificate_in_cc) {
            $templatedata2 = [];
        } elseif(!$number_certificate_in_tool_cert_issues && $number_certificate_in_cc) {
            $a = 1;
            foreach($number_certificate_in_cc as $val){
                array_push($templatedata2, (object)[
                    'id'=>$val->id,
                    'name'=>$val->name,
                    'template'=>$val->template,
                    'index' => $a++,
                    'course-name' =>$course->fullname,
                    'preview_url' => '#'
                ]);
            }
            
            if(count($templatedata2) > 0){
                for($i= 0; $i < count($templatedata2); $i++) {
                    // $templatedata[$i]->certificate_name = $templatedata[$i]->name;
                    // $templatedata[$i]->preview_url = '';
                    // $templatedata[$i]->course_name = $course->fullname;
                }
            }
        } elseif($number_certificate_in_tool_cert_issues && $number_certificate_in_cc) {
            foreach($pe as $val) {
                foreach($te as $v) {
                    // var_dump($v);
                   if( $v->course == $val->courseid) { // && $USER->id == $v->userid
                        array_push($templatedata2, (object) [
                            'id'=>$val->id,
                            // 'name'=> $val->name,
                            'template'=>$val->templateid,
                            'courseid'=>$val->courseid,
                            'code'=>$val->code,
                            'timecreated'=>$val->timecreated,
                            'user_id'=>$val->userid,
                            'emailed'=>$val->emailed,
                            'component'=>$val->component,
                        ]);
                    }
                }
            }

            $templatedata2 = array_unique(($templatedata2), SORT_REGULAR);
            $templatedata2 = array_values($templatedata2);
            foreach($number_certificate_in_cc as $value){
                for($i = 0; $i < count($templatedata2); $i++){
                    if($value->template == $templatedata2[$i]->template && $templatedata2[$i]->courseid == $value->course) {
                        $templatedata2[$i]->name = $value->name;
                    }
                }
            }

            $u_cer = [];
            $user_cert = [];
            $other_user_cert = [];
            $template_cert_id = [];
            $ot_temp_cert = [];
           
            for($i = 0; $i < count($templatedata2); $i++) {
                if($templatedata2[$i]->user_id == $USER->id) {
                    array_push($u_cer, $templatedata2[$i]->template);
                    array_push($user_cert, $templatedata2[$i]);
                } else if( ($i  < count($templatedata2) )) {
                    if(( $templatedata2[$i]->user_id != $USER->id && $templatedata2[$i]->template != $templatedata2[$i + 1]->template)) {
                        array_push($ot_temp_cert,$templatedata2[$i]->template);
                        array_push($other_user_cert, $templatedata2[$i]); 
                    }
                                       
                }
            }
            if(count($user_cert) > 0) {
                $templatedata2 = $user_cert;
                foreach($templatedata2 as $td) {
                    array_push($template_cert_id, $td->template); 
               }
            }
            if(count($other_user_cert) > 0) {
                // what should we do if the current user doesn't have any certificate
                foreach($other_user_cert as $other_user_c) {
                    if(!in_array($other_user_c->template,array_values($template_cert_id))) {
                        array_push($templatedata2, $other_user_c);
                    }
                }
            }
            
            if(count($templatedata2) > 0) {
                $pdf = '.pdf';
                for($i = 0; $i < count($templatedata2); $i++) {
                    
                    // $templatedata[$i]->certificate_name = $templatedata[$i]->name;
                    if($USER->id == $templatedata2[$i]->user_id){
                        $templatedata2[$i]->preview_url = (
                            new moodle_url(
                                "/pluginfile.php/{$templatedata2[$i]->emailed}/tool_certificate/issues/{$templatedata2[$i]->timecreated}/{$templatedata2[$i]->code}". $pdf
                            )
                        )->out(false);
                    }/*  else {
                        $templatedata[$i]->preview_url = (
                            new moodle_url(
                                "#"
                            )
                        )->out(false);
                    }

                    $templatedata[$i]->course_name = $course->fullname; */
                }
            }
        } else {
            $templatedata2 = [];
        }

    }else {
        $templatedata2 = [];
    }
    // merge the two differents arrays here
    $templatedata = array_merge($templatedata1, $templatedata2);
    
    return $templatedata;
}

/**
 * show the  certificat on the welcome page
 * @param int courseid
 * @return array
 */
function show_certificat($courseid) {
    global $USER;
    $out_certificat = null;
    // if ( get_certificate($courseid)) {
    // TO-DO
    $templ = get_certificates($courseid);
    //$out_certificat .= html_writer::start_tag('div', ['class'=>'certificat_card', 'style'=>'display:flex']); // certificat_card
        // var_dump($templ);
        $templ = array_values($templ);
        if (isset($templ) && !empty($templ)) {
            if (is_string($templ) == 1) {
                $out_certificat = $templ;
            }
            if (is_string($templ) != 1) {

                $out_certificat .= html_writer::start_tag('div',['class'=>'certificat_list', 'style'=>'display:flex;justify-content: center']); // certificat_body
                    for ($i= 0; $i < count($templ); $i++) {
                        if ($templ[$i]->user_id == $USER->id) {
                            $out_certificat .= html_writer::start_tag('div', ['class'=>'certificate-img', 'style'=>'cursor:pointer; margin:0 10px 0 10px']); // certificat_card
                            // var_dump($templ[$i]);
                            // $out_certificat .= html_writer::empty_tag('img', array('src' => $imageurl, 'class' => '', 'style' => 'width: 100px; height: 100px; margin: 0 auto')); // $opacity

                            // $out_certificat .= html_writer::start_tag('button', ['class'=>'btn btn-primary btn-lg certificat-image', 'style'=>'margin-right:2rem']);
                            if($templ[$i]->component == 'mod_coursecertificate') {
                                $certificat_url = $templ[$i]->preview_url;
                                $out_certificat .= html_writer::link($certificat_url, ' ' . $templ[$i]->name);
                            } else {

                                $certificat_url = $templ[$i]->preview_url;
                                if(isset($certificat_url)) {
                                    $out_certificat .= html_writer::link($certificat_url, ' ' . $templ[$i]->name); //  . ' ' . $templ[$i]->index
                                } else {

                                    $out_certificat .= html_writer::span('',$templ[$i]->name);
                                }
                            }

                            // $out_certificat .= html_writer::div($btn_certificat,'btn btn-secondary' ,['style'=>'cursor:unset, type:button;margin-top: 10px']);
                            // $out_certificat .= html_writer::end_tag('button'); // button
                            $out_certificat .= html_writer::end_tag('div'); // certificat_body
                        } else {
                                $out_certificat .= html_writer::start_tag('div', ['class'=>'certificate-img', 'style'=>'cursor:unset; opacity: 0.20']); // certificat_card

                                if($templ[$i]->component == 'mod_coursecertificate') {
                                $certificat_url = $templ[$i]->preview_url;
                                $out_certificat .= html_writer::link($certificat_url, ' ' . $templ[$i]->name, ['style'=>'cursor:unset !important']); // $templ[$i]->course_name . ' ' . $templ[$i]->index

                                } else {
                                    $certificat_url = $templ[$i]->preview_url;
                                    if(isset($certificat_url)) {
                                        $out_certificat .= html_writer::link($certificat_url, ' ' . $templ[$i]->name, ['style'=>'cursor:unset !important']); //  . ' ' . $templ[$i]->index
                                    } else {
                                        $out_certificat .= html_writer::link('', '' .$templ[$i]->name, ['style'=>'cursor:unset !important']);
                                    }


                            }
                            //$out_certificat .= html_writer::div($btn_certificat,'btn btn-secondary' ,['style'=>'cursor:unset, type:button; margin-top: 10px']);
                            // $out_certificat .= html_writer::end_tag('button'); // button
                            $out_certificat .= html_writer::end_tag('div'); // certificat_body
                        }


                }
                $out_certificat .= html_writer::end_tag('div'); // certificat_body

            }
        } else {
            $out_certificat = null;
        }

    return  $out_certificat;
}
// News functions
/**
 * Get the right link news or foren
 * @param int $courseid
 * @param string $forum_type
 * @return array
 */
function news_forum_url($courseid, $forum_type){
    global $DB, $OUTPUT;

    $sql_first = 'SELECT * FROM mdl_forum WHERE course = :id_course AND type = :type_forum ORDER BY ID DESC LIMIT 1 '; //ORDER BY ID DESC
    $param_first = array('id_course'=>$courseid, 'type_forum'=>$forum_type);
    // $new_in_course = $DB->get_record('forum', ['course' =>$courseid, 'type' => $forum_type]);
    $new_in_course = $DB->get_record_sql($sql_first, $param_first);
    // var_dump($new_in_course);
    // get the news annoucement & forum discussion for a specific news or forum
    $result = '';
    $news_forum_id = $new_in_course->id;
    $newsurl = new moodle_url('/course/format/mooin/forums.php', array('f' => $news_forum_id, 'tab' => 1)); // mod/forum/view.php
    $url_disc = new moodle_url('/course/format/mooin/alle_forums.php', array('id' => $courseid));
    // new moodle_url('/course/format/mooin/forum_view.php', array('f'=>$news_forum_id, 'tab'=>1));
    if ($new_in_course == true) {

        if ($forum_type == 'news') {
            $result = $newsurl;
        } else {
            $sql = 'SELECT * FROM mdl_forum WHERE course = :cid AND type != :tid ORDER BY ID ASC';
            $param = array('cid' =>$courseid, 'tid' => 'news');
            $oc_foren = $DB->get_records_sql($sql, $param);
                // $oc_f = $DB->get_records('forum_discussions', array('course' => $courseid));
            $cond_in_forum_posts = 'SELECT * FROM mdl_forum_discussions WHERE course = :id ORDER BY ID DESC LIMIT 1';
            $param =  array('id' => $courseid );
            $oc_f = $DB->get_record_sql($cond_in_forum_posts, $param);
                // var_dump($oc_foren);
            $ar_for = (array)$oc_foren;
            if (count($ar_for) > 1 || count($oc_f)) {
                $result = $url_disc;
            } else {
                $result = $newsurl;
            }
        }
    } else {
        // TO-DO Print an image to inform the user, for not availabble news or foren
        // var_dump($new_in_course);
        if ($forum_type != 'news') {
            $result = $url_disc;
        }
    }
    return $result;
}
/**
 * Get the last News in the course
 * @param int $courseid
 * @param string $forum_type
 * @return array
 */
function get_last_news($courseid, $forum_type) {
    global $DB, $OUTPUT, $USER;

    // Get all the forum (news)  in the course
    $sql_first = 'SELECT * FROM mdl_forum WHERE course = :id_course AND type = :type_forum ORDER BY ID DESC LIMIT 1'; //ORDER BY ID DESC LIMIT 1
    $param_first = array('id_course'=>$courseid, 'type_forum'=>$forum_type);
    $new_in_course = $DB->get_record_sql($sql_first, $param_first);

    // Some test to fetch the forum with discussion within it

    // get the news annoucement & forum discussion for a specific news or forum
    if ($new_in_course == true) {
        $out = null;
        $cond = 'SELECT * FROM mdl_forum_discussions WHERE forum = :id  ORDER BY ID DESC LIMIT 1';
        $param =  array('id' => $new_in_course->id);

        $discussions_in_new = $DB->get_record_sql($cond, $param, IGNORE_MISSING);
        if ($discussions_in_new != false) {

        // Get the data in forum_posts (userid, subject, message, created)
        $cond_in_forum_posts = 'SELECT * FROM mdl_forum_posts WHERE discussion = :id_for_disc ORDER BY CREATED DESC LIMIT 1';
        $param =  array('id_for_disc' => $discussions_in_new->id );
        $news_forum_post = $DB->get_record_sql($cond_in_forum_posts, $param);
        $user = $DB->get_record('user', ['id' => $news_forum_post->userid], '*');

        // Get the right date for new creation
        // Deutsches Datumsformat hier oder in der lang file?
        $created_news = date("d. m. Y,  G:i", date((int)$news_forum_post->created));

            $out .= html_writer::start_tag('div', ['class'=> 'news']); // card_news
            $out .= html_writer::start_tag('div', ['class'=> 'container']); //container
            $out .= html_writer::nonempty_tag('h2',get_string('news', 'format_mooin'),['class' => 'mb-1']);

            $out .= html_writer::start_tag('div', ['class' => 'd-none d-md-inline-block align-items-center mb-3']); //right_part_new

            $news_forum_id = $new_in_course->id;
            $newsurl = new moodle_url('/course/format/mooin/forums.php', array('f' => $news_forum_id, 'tab' => 1)); // mod/forum/view.php
            $url_disc = new moodle_url('/course/format/mooin/alle_forums.php', array('id' => $courseid));
            // new moodle_url('/course/format/mooin/forum_view.php', array('f'=>$news_forum_id, 'tab'=>1));
            if ($forum_type == 'news') {
                // Falls es neue Nachrichten gibt
                $unread_news_number = get_unread_news_forum($courseid, 'news');
                $new_news = false;

                if($unread_news_number == 1) {
                    $new_news = html_writer::start_span('count-container inline-badge fw-700 mr-1') . $unread_news_number . html_writer::end_span();
                    //$new_news .= get_string('unread_news_single', 'format_mooin');
                    $new_news .= html_writer::link($newsurl, get_string('unread_news_single', 'format_mooin') . get_string('all_news', 'format_mooin'), array('title' => get_string('all_news', 'format_mooin'), 'class' =>'primary-link'));
                }
                else if ($unread_news_number > 1) {
                    $new_news .= html_writer::start_span('count-container inline-batch fw-700 mr-1') . $unread_news_number . html_writer::end_span(); //Notification Counter
                    //$new_news .= get_string('unread_news', 'format_mooin');
                    $new_news .= html_writer::link($newsurl, get_string('unread_news', 'format_mooin') . get_string('all_news', 'format_mooin'), array('title' => get_string('all_news', 'format_mooin'), 'class' =>'primary-link'));


                } else {
                    $new_news = false;
                    $out .= html_writer::link($newsurl, get_string('all_news', 'format_mooin'), array('title' => get_string('all_news', 'format_mooin')));;
                }


            }
            $out .= html_writer::end_tag('div'); // right_part_new

            $out .= html_writer::start_tag('div', ['class' =>'news-card d-flex']); // top_card_news justify-content-between

            $out .= html_writer::start_tag('div'); // align-items-center

            if ($forum_type == 'news') {
                $out .= html_writer::start_span('news-icon') .  html_writer::end_span();
            } else {
                 $out .= html_writer::start_span('chat-icon') . html_writer::end_span();
            }
            $out .= html_writer::end_tag('div'); // align-items-center


            //$out .= html_writer::start_tag('div'); // align-items-center

            $forum_discussion_url = new moodle_url('/mod/forum/discuss.php', array('d' => $news_forum_post->discussion));
            $templatecontext = [
                'news_url' => $newsurl,
                'user_firstname' =>  $user->firstname,
                'created_news' => $created_news,
                'user_picture' => $OUTPUT->user_picture($user, array('courseid' => $courseid)),
                'news_title' => $news_forum_post->subject,
                'news_text' => $news_forum_post->message,
                'discussion_url' => $forum_discussion_url,
                'neue_news_number' => $unread_news_number,
                'new_news' => $new_news
                //'discussion_url' => $url_disc
            ];
        }
    } else {
        //$out = null;
        $templatecontext = [];
    }


    return $templatecontext;
}

/**
 * Get the last forum discussion in the course
 * @param int $courseid
 * @param string @forum_type
 * @return array
*/
function get_last_forum_discussion($courseid, $forum_type) {
    global $DB, $OUTPUT, $USER;

    $sql_second = 'SELECT * FROM mdl_forum WHERE course = :id_course AND type = :type_forum ORDER BY ID DESC LIMIT 1'; //ORDER BY ID DESC LIMIT 1
    $param_second = array('id_course'=>$courseid, 'type_forum'=>$forum_type);
    $news_course = $DB->get_record_sql($sql_second, $param_second);

    //  Get the last discussion in course from the DB
    //  If the last forum in DB has no discussion, we check in the previous
    $param_first = array('id_course'=>$courseid, 'type_forum'=>$forum_type);

    $sql = 'SELECT * FROM mdl_forum f
        JOIN mdl_forum_discussions fd ON fd.forum = f.id
        JOIN mdl_forum_posts fp ON fp.discussion = fd.id
        WHERE f.course = :id_course AND f.type = :type_forum
        ORDER BY fd.id DESC LIMIT 1';

    $new_in_course = $DB->get_records_sql($sql, $param_first, $limitfrom = 0, $limitnum = 0);

    // Some test to fetch the forum with discussion within it
    // get the news annoucement & forum discussion for a specific news or forum
    // var_dump($new_in_course);
    if (count($new_in_course) > 0) {
        $out = null;
        foreach ($new_in_course as $key => $value) {
            $user = $DB->get_record('user', ['id' => $value->userid], '*');

            // Get the right date for new creation
            // Deutsches Datumsformat hier oder in der lang file?
            $created_news = date("d. m. Y,  G:i", date((int)$value->created));

            $out .= html_writer::start_tag('div', ['class'=> 'news']); // card_news
            $out .= html_writer::start_tag('div', ['class'=> 'container']); //container
            $out .= html_writer::nonempty_tag('h2',get_string('news', 'format_mooin'),['class' => 'mb-1']);
            // $out .= html_writer::start_tag('div', ['class' => 'd-none d-md-inline-block align-items-center mb-3']); //right_part_new

            $out .= html_writer::start_tag('div', ['class' => 'd-none d-md-inline-block align-items-center mb-3']); //right_part_new

            $news_forum_id = $news_course->id;
            // $newsurl = new moodle_url('/course/format/mooin/forums.php', array('f' => $news_forum_id, 'tab' => 1)); // mod/forum/view.php
            $url_disc = new moodle_url('/course/format/mooin/alle_forums.php', array('id' => $courseid));
            // new moodle_url('/course/format/mooin/forum_view.php', array('f'=>$news_forum_id, 'tab'=>1));

                $sql = 'SELECT * FROM mdl_forum WHERE course = :cid AND type != :tid ORDER BY ID ASC';
                $param = array('cid' =>$courseid, 'tid' => 'news');
                $oc_foren = $DB->get_records_sql($sql, $param);
                // $oc_f = $DB->get_records('forum_discussions', array('course' => $courseid));
                $cond_in_forum_posts = 'SELECT * FROM mdl_forum_discussions WHERE course = :id ORDER BY ID DESC LIMIT 1';
                $param =  array('id' => $courseid );
                $oc_f = $DB->get_record_sql($cond_in_forum_posts, $param);
                // var_dump((array)$oc_f);
                $ar_for = (array)$oc_foren;
                $new_news = false;

                if (count($ar_for) > 1 || count((array)$oc_f) != 0) {
                    $unread_forum_number = get_unread_news_forum($courseid, 'genral');
                    //echo $unread_forum_number;

                    if ($unread_forum_number == 1) {
                        $new_news = html_writer::start_span('count-container inline-badge fw-700 mr-1') . $unread_forum_number . html_writer::end_span();
                        //$new_news .= get_string('unread_discussions_single', 'format_mooin');
                        $new_news .= html_writer::link($url_disc, get_string('unread_discussions_single', 'format_mooin') . get_string('discussion_forum', 'format_mooin'), array('title' => get_string('discussion_forum', 'format_mooin'), 'class' =>'primary-link'));
                    }
                    if ($unread_forum_number > 1) {
                        $new_news = html_writer::start_span('count-container inline-badge fw-700 mr-1') . $unread_forum_number . html_writer::end_span();
                        //$new_news .= get_string('unread_discussions', 'format_mooin');
                        $new_news .= html_writer::link($url_disc, get_string('unread_discussions', 'format_mooin') . get_string('discussion_forum', 'format_mooin'), array('title' => get_string('discussion_forum', 'format_mooin'), 'class' =>'primary-link'));
                    }
                    /* if ($unread_forum_number >= 1) {
                        $out .= html_writer::start_span('count-container inline-batch fw-700 mr-1') . $unread_forum_number . html_writer::end_span(); //Notification Counter
                        $out .= html_writer::link($url_disc, get_string('all_discussions', 'format_mooin'), array('title' => get_string('all_discussions', 'format_mooin'), 'class' =>'primary-link'));
                    }  else {
                        $out .= html_writer::link($url_disc, get_string('all_forums', 'format_mooin'), array('title' => get_string('all_forums', 'format_mooin')));
                    } */
                } else {
                    $new_news = false;
                    $out .= html_writer::link($url_disc, get_string('all_forums', 'format_mooin'), array('title' => get_string('all_forums', 'format_mooin'))); // newsurl
                }

            // }

            $out .= html_writer::end_tag('div'); // right_part_new

            $out .= html_writer::start_tag('div', ['class' =>'news-card d-flex']); // top_card_news justify-content-between

            $out .= html_writer::start_tag('div'); // align-items-center

            $out .= html_writer::start_span('chat-icon') . html_writer::end_span();
            $out .= html_writer::end_tag('div'); // align-items-center

            $out .= html_writer::start_tag('div', ['class' => 'position-relative pt-0 pt-md-1']);
            // Get the user id for the one who created the news or forum
            $user_news = user_print_forum($courseid);
            $out .= html_writer::nonempty_tag('span',$OUTPUT->user_picture($user_news, array('courseid'=>$courseid)),array('class' => 'new_user_picture d-none d-md-block')); // $user

            $forum_discussion_url = new moodle_url('/mod/forum/discuss.php', array('d' => $value->discussion));
            $templatecontext = [
                'disc_url' => $url_disc,
                'user_firstname' =>  $user->firstname,
                'created_news' => $created_news,
                'user_picture' => $OUTPUT->user_picture($user, array('courseid' => $courseid)),
                'news_title' => $value->subject,
                'news_text' => $value->message,
                'discussion_url' => $forum_discussion_url,
                'neue_forum_number' => $unread_forum_number,
                'new_news' => $new_news
                //'discussion_url' => $url_disc
            ];
        }
    } else {
        // $out = null;
        // $news_forum_id = $news_course->id;
        // $url_disc = new moodle_url('/course/format/mooin/forum_view.php', array('f'=>$news_forum_id, 'tab'=>1));
        $url_disc = new moodle_url('/course/format/mooin/alle_forums.php', array('id' => $courseid));
        $templatecontext = [
            'disc_url' => $url_disc,
            'neue_forum_number' => 0,
            'no_discussions_available' => true,
            'no_news' => false,
            'new_news' => false
        ];
    }
    return $templatecontext;
}


/**
 * Get course grade
 * @param int courseid
 */
function get_course_grades($courseid) {
    global $DB, $CFG, $USER;
    require_once($CFG->libdir . '/gradelib.php');

    if (isset($courseid)) {

        $sec = $DB->get_records_sql("SELECT cs.*
                    FROM {course_sections} cs
                    WHERE cs.course = ? AND cs.section != 0 AND cs.sequence != '' ", array($courseid));


        /* $mods = $DB->get_records_sql("SELECT cm.*, m.name as modname
                    FROM {modules} m, {course_modules} cm
                    WHERE cm.course = ? AND cm.completiongradeitemnumber >= 0 AND cm.module = m.id AND m.visible = 1", array($courseid)); */

        $other_mods = $DB->get_records_sql("SELECT cm.*, cs.name as sectionname, cs.section as sectionincourse
                            FROM {course_modules} cm
                            LEFT JOIN {course_sections} cs ON cm.section = cs.id
                            WHERE cm.course = ? AND cs.section != 0", array($courseid));

        $percentage = 0;
        // $mods_counter = 0;
        $max_grade = 100.0;
        $number_section = count($sec);
        $element_in_seq = 0;
        $sequence_point = 0;
        $section_point = $max_grade / $number_section;


        // $number_element = count($other_mods);
        $seq = [];
        foreach ($sec as $val) {
            $seq = explode(",",$val->sequence);

            $element_in_seq = count($seq);
            $sequence_point = ($section_point / $element_in_seq);

            $section_complete_id = $USER->id . '-' . $courseid . '-' . $val->id; // $section->section
            $section_complete = $DB->record_exists('user_preferences',
                array('name' => 'section_progress_label-'.$section_complete_id,
                    'value' => $section_complete_id));
            if ($section_complete) {
                $percentage += $section_point;
                continue;
            } else {
                // Go throught the course_modules table and find a way to know exactly which module containts the section
                for ($i=0; $i < count($seq); $i++) {
                    // Check if the section exist in user_preferences Table

                    // Get_record to know if one of the module ein label is
                    $label_req = $DB->get_record('course_modules', ['id'=>$seq[$i]], '*');
                    if (is_object($label_req)) {
                        if ($label_req->module == '26') {
                            $grading_info = grade_get_grades($label_req->course, 'mod', 'hvp', $label_req->instance, $USER->id);
                            $grading_info = (object)($grading_info);// new, convert an array to object

                            $user_grade = $grading_info->items[0]->grades[$USER->id]->grade;
                            if ($user_grade > 0) {
                                $percentage += $sequence_point;
                             }
                        } else {
                            // Check if the module completion has be push in DB mdl_modules_completion
                            $exist_check = $DB->record_exists('course_modules_completion', ['coursemoduleid'=>$seq[$i], 'userid'=>$USER->id]);
                            if ($exist_check) {

                                $percentage += $sequence_point;
                            }
                        }
                    }
                }
            }
        }

    }
    return $percentage;
}

/**
 * Get the course progress
 * @param int p the progress course variable
 * @param int width the with for the progress bar
 */
function get_progress_bar_course($p, $width) {
    //$p_width = $width / 100 * $p;
    $result =
        html_writer::tag('div',
            html_writer::tag('div',
                html_writer::tag('div',
                    '',
                    array('style' => 'width: ' . $p . '%;', 'id' => 'mooinprogressbar', 'class' => 'progressbar-inner')
                ),
                array('class' => 'progressbar')
            ) .
            html_writer::start_span('',['style' => 'font-weight: bold']) . $p . '%' . html_writer::end_span() .
            html_writer::start_span(' d-sm-inline d-md-none ') . ' bearbeitet' . html_writer::end_span() .
            html_writer::start_span(' d-none d-md-inline ') . ' des Kurses bearbeitet' . html_writer::end_span()); // 'class' => 'oc-progress-div',
    return $result;
}

/**
 * Get the user in the course
 * @param int courseid
 * @return array out
 */
function get_user_in_course($courseid) {
    global $DB, $OUTPUT;
    $out = null;
    // Get the enrol data in the course

    $sql = 'SELECT * FROM mdl_enrol WHERE courseid = :cid AND enrol = :enrol_data ORDER BY ID ASC';
    $param = array('cid' => $courseid, 'enrol_data' =>'manual');
    $enrol_data = $DB->get_records_sql($sql,$param);

    // Get user_enrolments data
    $user_enrol_data = [];
    $sql_query = 'SELECT * FROM mdl_user_enrolments WHERE enrolid = :value_id ORDER BY timecreated DESC ';

    foreach ($enrol_data as $key => $value) {
        $param_array = array('value_id' => $value->id);
        $count_val = $DB->get_records_sql($sql_query, $param_array);
        $val = $DB->get_records_sql($sql_query, $param_array, 0, 3);// ('user_enrolments', ['enrolid' =>$value->id], 'userid');
        array_push($user_enrol_data, $val);
    }

    if (isset($enrol_data)) {
        // $out .= html_writer::start_tag('div', ['class' =>'participant-card']); // participant-card
        // $out .= html_writer::start_tag('div', ['class' =>'participant-card-inner']); // participant-card-inner
        // $out .= html_writer::start_tag('div', ['class' =>'d-flex']); // d-flex align-items-center
        // $out .= html_writer::start_span('icon-wrapper participant-icon') . html_writer::end_span();

        // $out .= html_writer::start_tag('div');
        // $out .= html_writer::nonempty_tag('p', get_string('user_card_title', 'format_mooin'), ['class'=>'caption fw-700 text-primary']);


        $user_count = count($count_val);
        $user_in_course = $user_count . ' ' . get_string('user_in_course', 'format_mooin');
        // $out .= html_writer::start_tag('p');
        // $out .= html_writer::start_span('fw-700') . $user_in_course . html_writer::end_span();
        // $out .= html_writer::end_tag('p');
        // $out .= html_writer::nonempty_tag('p', get_string('new_user', 'format_mooin'), ['class' => 'caption fw-700']);

        // $out .= html_writer::start_tag('ul', ['class' => 'caption', 'style' => 'line-height: 3rem']); // user_card_list

        $user_list = '';
        foreach ($user_enrol_data as $key => $value) {

            $el = array_values($value);
            for ($i = 0; $i < count($el); $i++) {
                // var_dump($el);
                $user_list .= html_writer::start_tag('li');
                $user = $DB->get_record('user', ['id' => $el[$i]->userid], '*');
                $user_list .= html_writer::start_tag('span');
                $user_list .= html_writer::nonempty_tag('span', $OUTPUT->user_picture($user, array('courseid' => $courseid)));
                $user_list .= $user->firstname . ' ' . $user->lastname;
                $user_list .= html_writer::end_tag('span');

                // $out .= html_writer::div($user-> firstname . ' ' . $user->lastname, 'user_card_name');
                $user_list .= html_writer::end_tag('li'); // user_card_element
            }
        }
        // $out .= html_writer::end_tag('ul'); // user_card_list
        // $out .= html_writer::end_tag('div');


        // $out .= html_writer::end_tag('div'); // d-flex

        // $out .= html_writer::start_tag('div', ['class' => 'primary-link d-none d-md-block text-right']);
        $participants_url = new moodle_url('/course/format/mooin/participants.php', array('id' => $courseid));
        $participants_link = html_writer::link($participants_url, get_string('show_all_infos', 'format_mooin'), array('title' => get_string('participants', 'format_mooin')));
        // $out .= $participants_link;
        // $out .= html_writer::end_tag('div'); // d-none d-md-block text-right
        // $out .= html_writer::end_tag('div'); // participant-card-inner
        // $out .= html_writer::end_tag('div'); // participant-card
    } else {
        $out .= html_writer::div(get_string('no_user', 'format_mooin'), 'no_user_class'); // '
    }

    $templatecontext = [
        'user_count' => $user_count,
        'user_list' => $user_list
    ];

    return $templatecontext;
}

/**
 * Returns url for headerimage
 *
 * @param int courseid
 * @param bool true if mobile header image is required or false for desktop image
 * @return string|bool String with url or false if no image exists
 */
function get_headerimage_url($courseid, $mobile = true) {
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
        'component' => 'format_mooin',
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

    $url = new moodle_url('/pluginfile.php/'.$context->id.'/format_mooin/'.$filearea.'/'.$courseid.'/0/'.$filename);
    return $url;
}

/**
 * Return the unread news and forum in a course
 *
 * @param int courseid
 * @param string forum_type
 * @return int Number of all unread news and forums
 */
function get_unread_news_forum($courseid, $forum_type) {

    global $DB;

    $oc_m = $DB->get_record('modules', array('name' => 'forum'));
    // $sql = 'SELECT * FROM mdl_forum WHERE course = :courseid AND type = :';



    //Get the module Data
    /* if ($forum_type == 'news') {
        $oc_m = $DB->get_record('modules', array('id' => 2));
    }
    if ($forum_type == 'general') {
        $oc_m = $DB->get_record('modules', array('id' => 9));
    } */
    $result = 0;
    if ($forum_type == 'news') {
        $sql = 'SELECT * FROM mdl_forum WHERE course = :cid AND type = :tid ORDER BY ID DESC'; //
        $param = array('cid' =>$courseid, 'tid' => 'news'); // 'tid' => 'news'
        $oc_foren = $DB->get_records_sql($sql, $param);
    } else {
        $sql = 'SELECT * FROM mdl_forum WHERE course = :cid AND type != :tid ORDER BY ID DESC'; //
        $param = array('cid' =>$courseid, 'tid' => 'news'); //
        $oc_foren = $DB->get_records_sql($sql, $param);
    }
    if (count($oc_foren) >= 1) {

        foreach ($oc_foren as $oc_forum) {
            $cm = get_coursemodule_from_instance('forum', $oc_forum->id, $courseid);

            $course = $DB->get_record("course", array("id" => $courseid)); // $cm->course
            $oc_forum->istracked = forum_tp_is_tracked($oc_forum);
                if ($oc_forum->istracked) {
                    $oc_forum->unreadpostscount = forum_tp_count_forum_unread_posts($cm, $course);
                }

            $oc_cm = $DB->get_record('course_modules', array('instance' => $oc_forum->id, 'course' => $courseid,'module' => $oc_m->id)); //

            //if ($oc_cm->visible == 1) {
                $result += intval($oc_forum->unreadpostscount);
            //}
        }

    }
    return $result;
}

/**
 * Get the right user picture for creating forum
 * @param int courseid
 * @return object of user
 */
function user_print_forum($courseid) {
    global $DB, $USER;

    $sql = 'SELECT * FROM mdl_forum WHERE course = :cid ORDER BY ID DESC ' ; // LIMIT 1
    $param = ['cid' => $courseid];

    $forum_in_course = $DB->get_records_sql($sql, $param, IGNORE_MISSING);
    // var_dump($forum_in_course);
    // get the forum_discussion data
    $sql_in_forum = 'SELECT * FROM mdl_forum_discussions ORDER BY ID DESC LIMIT 1'; // WHERE forum = :id
    // $param_in_forum = ['id'=> $forum_in_course->id];
    $discuss_forum_in_course = $DB->get_record_sql($sql_in_forum,  [],IGNORE_MISSING);

    $result = new stdClass;
    if ($discuss_forum_in_course->userid == $discuss_forum_in_course->usermodified) {
        $result = $DB->get_record('user',['id'=>$discuss_forum_in_course->userid]);
    } else {
        $result = $DB->get_record('user',['id'=>$discuss_forum_in_course->usermodified]);
    }


    return $result;
}

 /**
    * Return the navbar content in specific section on Desktop so that it can be echo out by the layout
    *
    * @return string XHTML navbar
*/
function navbar($displaysection = 0) {
    global $COURSE, $PAGE, $OUTPUT, $DB;
    $items = $PAGE->navbar->get_items(); //$this->page
    $itemcount = count($items);
    if ($itemcount === 0) {
        return '';
    }

    // Make a DB request in course_sections to get all the sections data
    $sections_data = $DB->get_records_sql("SELECT cs.*
                                        FROM {course_sections} cs
                                        WHERE cs.course = ? AND cs.section != 0  AND cs.visible = 1", array($COURSE->id));

    // Make chapter request anfrage
    $chapters_data = $DB->get_records_sql("SELECT fmc.*
                                            FROM {format_mooin_chapter} fmc
                                            WHERE fmc.courseid = ?", array($COURSE->id));
    $htmlblocks = array();
    // Iterate the navarray and display each node
    $separator = get_separator();
    $before = '';
    $val = '';
    $chapter_val = '';
    $chapter_n = '';
    $b = '';
    // $c = '';
    $chapter_array = [];
    $chap = '';
    $array_chap = [];
    for ($i=0;$i < $itemcount;$i++) {

        if ($displaysection != 0 && !is_string($displaysection)) {

            $item = $items[$i];

            $item->hideicon = true;
            if ($i===0) {
                $content = html_writer::tag('li', '  ');
            } else
            if($i === $itemcount - 2) {

                $content = html_writer::tag('li', '  '. $OUTPUT->render($item));
            }else
            if ($i === $itemcount - 1) {
                    foreach ($sections_data as $value) {

                        array_push($chapter_array, get_chapter_title($value->id));

                    }

                    foreach($chapter_array as $chap_value) {
                        if($chap_value != ''){
                            $chap = '  : ' . $chap_value;
                        }
                        array_push($array_chap, $chap);
                        $c = get_chapter_number($OUTPUT->render($item));

                        $b = get_lektion_number($OUTPUT->render($item));

                        if($c == " ") {
                            $chapter_n = str_replace("-", "",$OUTPUT->render($item));
                        } else {
                            $chapter_n = 'Kapitel ' . ' ' . $c ;
                            // $chapiter leer change the output
                        }

                    }
                    for($i = 1; $i <= count($array_chap); $i++) {
                        if($i == $displaysection ) {
                            $content = html_writer::tag('li', $before . ' / '. $chapter_n . $array_chap[$i-1] , ['class'=>'chap']); // $separator.$this->render($item)
                            $content .= html_writer::tag('li', $before . ' / Lektion: ' . $b, ['class'=>'sec']); // $separator.$this->render($item)

                        }
                    }
            } else {
                $content = '';
            }
        } else if (gettype($displaysection) === 'string') {
            // var_dump($itemcount);
            if($itemcount > 4) {
                $item = $items[$i];
                $item->hideicon = true;
                if ($i === $itemcount - 5) {
                    $content = html_writer::tag('li', $before . '');
                } elseif ($i === $itemcount - 4) {
                    $content = html_writer::tag('li', $before . '' );
                }
                else if ($i === $itemcount - 3) {
                    $content = html_writer::tag('li', $before . ''.  $OUTPUT->render($item));
                } else if ($i === $itemcount - 2) {
                    // var_dump($item);
                    $content_link = html_writer::link(new moodle_url('/course/format/mooin/alle_forums.php', ['id'=> $COURSE->id]), get_string('all_forums', 'format_mooin'));
                    $content = html_writer::tag('li', $before . ' / '. $content_link); // , ['class'=>'breadcrumb-item']
                } else if($i === $itemcount - 1) {
                    // echo($OUTPUT->render($item));
                    // var_dump($item->text);
                    $content = html_writer::tag('li', $before . ' / '. $item->text); // $OUTPUT->render($item)
                } else {
                    $content = '';
                }
            }
            if ($itemcount == 4) {
                // $val = $displaysection;
                $item = $items[$i];
                $item->hideicon = true;
                if ($i === 0) {
                    $content = html_writer::tag('li', $before . ' ');
                } elseif ($i === $itemcount - 4) {
                    $content = html_writer::tag('li', $before . $OUTPUT->render($item));
                }
                else if ($i === $itemcount - 3) {
                    $content = html_writer::tag('li', $before . ' ');
                } else if ($i === $itemcount - 2) {
                    $content = html_writer::tag('li', $before . $OUTPUT->render($item)); // , ['class'=>'breadcrumb-item']
                } else if($i === $itemcount - 1) {
                    $content = html_writer::tag('li', $before . ' / '. $item->text); // $OUTPUT->render($item)
                } else {
                    $content = '';
                }
            }
            if ($itemcount <= 3) {
                $val = $displaysection;
                $item = $items[$i];
                $item->hideicon = true;

                if ($i == 0) {

                    $content = html_writer::tag('li', $before . ''); // $OUTPUT->render($item)
                }
                else if ($i === $itemcount - 3) {
                    $content = html_writer::tag('li', $before . ' / '. $OUTPUT->render($item));
                } else if ($i == $itemcount - 1) {

                    $content = html_writer::tag('li', $before . $OUTPUT->render($item) . ' / ' . get_string($val, 'format_mooin')); // $separator.$this->render($item)
                } else if($i == $itemcount - 2) {
                    $content = html_writer::tag('li', ''); // $OUTPUT->render($item)
                } else {
                    $content = '';
                }
            }
        }
        /*  {
            $content = html_writer::tag('li', $separator.$this->render($item));
        } */
        $htmlblocks[] = $content;
    }

    //accessibility: heading for navbar list  (MDL-20446)
    $navbarcontent = html_writer::tag('span', get_string('pagepath'),
            array('class' => 'accesshide', 'id' => 'navbar-label'));
    // $navbarcontent .= html_writer::start_tag('nav', array('aria-labelledby' => 'navbar-label'));

    $navbarcontent .= html_writer::tag('nav',
            html_writer::tag('ol', join('', $htmlblocks),array('class' => "breadcrumb navbar_desktop", 'id'=> 'menu'),array('aria-labelledby' => 'navbar-label')), //navmenu
            );
    // $navbarcontent .= html_writer::start_tag('ul', array('id' => "menu"));
    // XHTML
    return $navbarcontent;
}

 /**
    * Return the navbar content in specific section on Mobile so that it can be echo out by the layout
    *
    * @return string XHTML navbar
*/
function navbar_mobile($displaysection = 0) {
    global $COURSE, $PAGE, $OUTPUT, $DB;
    $items = $PAGE->navbar->get_items(); //$this->page
    $itemcount = count($items);
    if ($itemcount === 0) {
        return '';
    }

    // Make a DB request in course_sections to get all the sections data
    $sections_data = $DB->get_records_sql("SELECT cs.*
                                        FROM {course_sections} cs
                                        WHERE cs.course = ? AND cs.section != 0  AND cs.visible = 1", array($COURSE->id));

    // Make chapter request anfrage
    $chapters_data = $DB->get_records_sql("SELECT fmc.*
                                            FROM {format_mooin_chapter} fmc
                                            WHERE fmc.courseid = ?", array($COURSE->id));
    $htmlblocks = array();
    // Iterate the navarray and display each node
    $separator = get_separator();
    $before = '&nbsp';
    $val = '';
    $chapter_val = '';
    $chapter_n = '';
    $b = '';
    $chapter_array = [];
    $chap = '';
    $array_chap = [];
    for ($i=0;$i < $itemcount;$i++) {
        /* if( $displaysection == 0) {
            $val .= $COURSE->shortname;
            $item = $items[$i];
            $item->hideicon = true;
            if ($i===0) {
                $content = html_writer::tag('li', $OUTPUT->render($item)); // $this
            } else
            if($i === $itemcount - 2) {
                $content = html_writer::tag('li', '  ');
            }else
            if ($i === $itemcount - 1) {
                $content = html_writer::tag('li', '  '. ' > '.$val); // $separator.$this->render($item)
            } else {
                $content = '';
            }
        } else */
        if ($displaysection != 0 && !is_string($displaysection)) {

            $item = $items[$i];
            $item->hideicon = true;
            if ($i===0) {
                $content = html_writer::tag('li', '  ');
            } else
            if($i === $itemcount - 2) {
                $content = html_writer::tag('li', '  '. $OUTPUT->render($item));
            }else
            if ($i === $itemcount - 1) {
                    foreach ($sections_data as $value) {

                        array_push($chapter_array, get_chapter_title($value->id));

                    }

                    foreach($chapter_array as $chap_value) {
                        /* if($chap_value != ''){
                            $chap = '  : ' . $chap_value .' / Lektion  '. ' ' ;
                        } */
                        array_push($array_chap, $chap);
                        $c = get_chapter_number($OUTPUT->render($item));

                        $b = get_lektion_number($OUTPUT->render($item));
                        $chapter_n = 'Kap. ' .''.$c ;
                    }
                    for($i = 1; $i <= count($array_chap); $i++) {

                        if($i == $displaysection ) {
                            $content = html_writer::tag('li', $before . ' / '. $chapter_n  . ' /  Lekt. ' .$b, ['class'=>'breadcrumd_in_section ']); // $separator.$this->render($item)

                        }
                    }
            } else {
                $content = '';
            }
        } else if (gettype($displaysection) === 'string') {

            if($itemcount > 4) {
                $item = $items[$i];
                $item->hideicon = true;
                if ($i === $itemcount - 5) {
                    $content = html_writer::tag('li', $before . '');
                } elseif ($i === $itemcount - 4) {
                    $content = html_writer::tag('li', $before . '' );
                }
                else if ($i === $itemcount - 3) {
                    $content_link = html_writer::link(new moodle_url('/course/format/mooin/view.php', ['id'=> $COURSE->id]), reduce_string($COURSE->shortname, 0));
                    $content = html_writer::tag('li', $before . ''.  $content_link);
                } else if ($i === $itemcount - 2) {
                    $content_link = html_writer::link(new moodle_url('/course/format/mooin/alle_forums.php', ['id'=> $COURSE->id]), reduce_string(get_string('all_forums', 'format_mooin'), 6));
                    $content = html_writer::tag('li', $before . ' / '. $content_link); // , ['class'=>'breadcrumb-item']
                } else if($i === $itemcount - 1) {
                    // echo($OUTPUT->render($item));
                    // var_dump($item->text);
                    $content = html_writer::tag('li', $before . ' / '. $item->text); // $OUTPUT->render($item)
                } else {
                    $content = '';
                }
            }
            if ($itemcount == 4) {
                // $val = $displaysection;
                $item = $items[$i];
                $item->hideicon = true;
                if ($i === 0) {
                    $content = html_writer::tag('li', $before . ' ');
                } elseif ($i === $itemcount - 4) {
                    $content = html_writer::tag('li', $before . $OUTPUT->render($item));
                }
                else if ($i === $itemcount - 3) {
                    $content = html_writer::tag('li', $before . ' ');
                } else if ($i === $itemcount - 2) {
                    $content = html_writer::tag('li', $before . $OUTPUT->render($item)); // , ['class'=>'breadcrumb-item']
                } else if($i === $itemcount - 1) {
                    /* var_dump($item->component);
                    if($item->title === 'Forum') {
                        $content_link = html_writer::link(new moodle_url('/course/format/mooin/alle_forums.php', ['id'=> $COURSE->id]), get_string('all_forums', 'format_mooin'));
                        $content = html_writer::tag('li', $before . ' / '. $content_link .' / ' . $item->text); // $OUTPUT->render($item)
                    } else {
                        $content = html_writer::tag('li', $before . ' / '. $OUTPUT->render($item));
                    } */
                    $content = html_writer::tag('li', $before . ' / '. $item->text); // $OUTPUT->render($item)
                } else {
                    $content = '';
                }
            }
            if ($itemcount <= 3) {
                $val .= $displaysection;
                $item = $items[$i];
                $item->hideicon = true;
                if ($i == 0) {
                    $content = html_writer::tag('li', $before . ''); // $OUTPUT->render($item)
                }
                else if ($i === $itemcount - 3) {
                    $content = html_writer::tag('li', $before . ' / '. $OUTPUT->render($item));
                } else if ($i == $itemcount - 1) {
                    $content = html_writer::tag('li', $before . $OUTPUT->render($item) . ' / ' . get_string('all_forums', 'format_mooin')); // $separator.$this->render($item)
                } else if($i == $itemcount - 2) {
                    $content = html_writer::tag('li', ''); // $OUTPUT->render($item)
                } else {
                    $content = '';
                }
            }
        }
        /*  {
            $content = html_writer::tag('li', $separator.$this->render($item));
        } */
        $htmlblocks[] = $content;
    }

    //accessibility: heading for navbar list  (MDL-20446)
    $navbarcontent = html_writer::tag('span', get_string('pagepath'),
            array('class' => 'accesshide', 'id' => 'navbar-label'));
    // $navbarcontent .= html_writer::start_tag('nav', array('aria-labelledby' => 'navbar-label'));

    $navbarcontent .= html_writer::tag('nav',
            html_writer::tag('ol', join('', $htmlblocks),array('class' => "breadcrumb navbar_mobile", 'id'=> 'menu'),array('aria-labelledby' => 'navbar-label')), //navmenu
            );
    // $navbarcontent .= html_writer::start_tag('ul', array('id' => "menu"));
    // XHTML
    return $navbarcontent;
}

function reduce_string($string, $limit) {
    if (strlen($string) > $limit) {
      return substr($string, 0, $limit) . "...";
    }
    return $string;
}
function reduce_url($url, $limit) {
    if (strlen($url) > $limit) {
      return substr($url, 0, $limit) . "...";
    }
    return $url;
  }

function get_chapter_title($value) {
    global $DB;

    $val = '';
    $value = $DB->get_record('format_mooin_chapter', ['sectionid'=>$value]);
    if ($value) {
       $val = $value->title;
    }
    return $val;
}
function get_lektion_number($value) {
    // Return the lektion number
    $lektion = explode(">", $value);
    $lek = explode("-", $lektion[3]);

    return $lek[0];
}
function get_chapter_number($value) {
    $chapter = explode(">", $value);
    $chap = explode("-", $chapter[3]);
    $c = explode(".", $chap[0]);

    // var_dump($c);
    return $c[0];
}
function unset_chapter($sectionid) {
    global $DB;

    $DB->delete_records('format_mooin_chapter', array('sectionid' => $sectionid));
    if ($csection = $DB->get_record('course_sections', array('id' => $sectionid))) {
        sort_course_chapters($csection->course);
    }
}

function set_chapter($sectionid) {
    global $DB;

    if ($DB->get_record('format_mooin_chapter', array('sectionid' => $sectionid))) {
        return;
    }

    if ($csection = $DB->get_record('course_sections', array('id' => $sectionid))) {
        $csectiontitle = $csection->name;
    }
    else {
        return;
    }

    if (!$csectiontitle) {
        $csectiontitle = get_string('new_chapter', 'format_mooin');
    }

    $chapter = new stdClass();
    $chapter->courseid = $csection->course;
    $chapter->title = $csectiontitle;
    $chapter->sectionid = $sectionid;
    $chapter->chapter = 0;
    $DB->insert_record('format_mooin_chapter', $chapter);

    sort_course_chapters($csection->course);
}

function sort_course_chapters($courseid) {
    global $DB;
    $coursechapters = get_course_chapters($courseid);
    $number = 0;
    foreach ($coursechapters as $coursechapter) {
        $number++;
        if ($existingcoursechapter = $DB->get_record('format_mooin_chapter', array('id' => $coursechapter->id))) {
            $existingcoursechapter->chapter = $number;
            $DB->update_record('format_mooin_chapter', $existingcoursechapter);
        }
    }
}

function get_course_chapters($courseid) {
    global $DB;

    $sql = 'SELECT c.*, s.section
              FROM {format_mooin_chapter} as c, {course_sections} as s
             WHERE s.course = :courseid
               and s.id = c.sectionid
          order by s.section asc';

    $params = array('courseid' => $courseid);

    $coursechapters = $DB->get_records_sql($sql, $params);

    return $coursechapters;
}

function get_sections_for_chapter($chapterid) {
    global $DB;
    $result = '';

    $sids = get_sectionids_for_chapter($chapterid);

    foreach ($sids as $sid) {
        if ($section = $DB->get_record('course_sections', array('id' => $sid))) {
            if ($result == '') {
                $result .= 'section-'.$section->section;
            }
            else {
                $result .= ' section-'.$section->section;
            }
        }
    }

    return $result;
}

function get_chapter_for_section($sectionid) {
    global $DB;
    $chapter = null;
    if ($section = $DB->get_record('course_sections', array('id' => $sectionid))) {
        $chapters = get_course_chapters($section->course);

        foreach ($chapters as $c) {
            if ($section->section > $c->section && ($chapter = null||$c->section > $chapter)) {
                $chapter = $c->chapter;
            }
        }
    }
    return $chapter;
}

function is_first_section_of_chapter($sectionid) {
    global $DB;
    $chapter = null;
    if ($section = $DB->get_record('course_sections', array('id' => $sectionid))) {

        $chapters = get_course_chapters($section->course);

        foreach ($chapters as $c) {
            if ($section->section == $c->section +1) {
               return true;
            }
        }
    }
    return false;
}

function is_last_section_of_chapter($sectionid) {
    global $DB;
    $chapter = null;
    if ($section = $DB->get_record('course_sections', array('id' => $sectionid))) {

        $chapters = get_course_chapters($section->course);
        $chapter = get_chapter_for_section($sectionid);

        $start = 0;
        $end = 0;
        foreach ($chapters as $c) {
            if ($c->chapter == $chapter) {
                $start = $c->section;
                continue;
            }
            if ($start != 0) {
                $end = $c->section;
                break;
            }
        }
        if ($start != 0) {
            if ($end == 0) {
                $end = get_last_section($section->course) + 1;
            }
        }

        if ($section -> section == $end-1) {
            return true;
        }
    }
    return false;
}

function get_last_section($courseid) {
    global $DB;

    $lastsection = 0;
    $count = $DB->count_records('course_sections', array('course' => $courseid));

    if ($count > 0) {
        $lastsection = $count - 1;
    }

    return $lastsection;
}

function get_section_prefix($section) {
    global $DB;

    $sectionprefix = '';

    $parentchapter = get_parent_chapter($section);
    if (is_object($parentchapter)) {
        $sids = get_sectionids_for_chapter($parentchapter->id);
        $sectionprefix .= $parentchapter->chapter.'.'.(array_search($section->id, $sids) + 1);

        return $sectionprefix;
    }

}

function get_parent_chapter($section) {
    global $DB;

    $chapters = $DB->get_records('format_mooin_chapter', array('courseid' => $section->course));
    foreach ($chapters as $chapter) {
        $sids = get_sectionids_for_chapter($chapter->id);
        if (in_array($section->id, $sids)) {
            return $chapter;
        }
    }

    return false;
}

function get_sectionids_for_chapter($chapterid) {
    global $DB;
    $result = array();
    if ($chapter = $DB->get_record('format_mooin_chapter', array('id' => $chapterid))) {
        $chapters = get_course_chapters($chapter->courseid);
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
                    $end = get_last_section($chapter->courseid) + 1;
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

function get_expand_string($section) {
    global $DB, $USER;
    $expand = '';
    $last_section = get_user_preferences('format_mooin_last_section_in_course_'.$section->course, 0, $USER->id);
    $parentchapter = get_parent_chapter($section);
    $sectionids = get_sectionids_for_chapter($parentchapter->id);
    if ($record = $DB->get_record('course_sections', array('course' => $section->course, 'section' => $last_section))) {
        if (in_array($record->id, $sectionids)) {
            $expand = ' show';
        }
    }
    return $expand;
}

function get_chapter_info($chapter) {
    global $USER, $DB;
    $info = array();

    $chaptercompleted = false;
    $lastvisited = false;

    $sectionids = get_sectionids_for_chapter($chapter->id);
    $completedsections = 0;

    foreach ($sectionids as $sectionid) {
        $section = $DB->get_record('course_sections', array('id' => $sectionid));
        if ($section && is_section_completed($chapter->courseid, $section)) {
            $completedsections++;
        }

        $last_section = get_user_preferences('format_mooin_last_section_in_course_'.$chapter->courseid, 0, $USER->id);
        if ($record = $DB->get_record('course_sections', array('course' => $chapter->courseid, 'section' => $last_section))) {
            if ($record->id == $sectionid) {
                $lastvisited = true;
            }
        }
    }
    if ($completedsections == count($sectionids)) {
        $chaptercompleted = true;
    }

    $info['completed'] = $chaptercompleted;
    $info['lastvisited'] = $lastvisited;
    return $info;
}

function get_unenrol_url($courseid) {
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

function is_section_completed($courseid, $section) {
    global $USER, $DB;
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
    return false;
}

function set_new_badge($awardedtoid, $badgeissuedid) {
    set_user_preference('format_mooin_new_badge_'.$badgeissuedid, true, $awardedtoid);
}

function unset_new_badge($viewedbyuserid, $badgehash) {
    global $DB;
    $sql = "select * from {badge_issued} where " . $DB->sql_compare_text('uniquehash') . " = :badgehash";
    $params = array('badgehash' => $badgehash);
    if ($records = $DB->get_records_sql($sql, $params)) {
        if (count($records) == 1) {
            if ($records[array_key_first($records)]->userid == $viewedbyuserid) {
                unset_user_preference('format_mooin_new_badge_'.$records[array_key_first($records)]->id, $viewedbyuserid);
            }
        }
    }
}

function count_unviewed_badges($userid, $courseid) {
    global $DB;
    $unviewed_badges = 0;
    $sql = 'SELECT bi.id
              FROM {badge_issued} as bi, {badge} as b
             WHERE b.courseid = :courseid
               AND b.id = bi.badgeid
               AND bi.userid = :userid';
    $params = array('courseid' => $courseid, 'userid' => $userid);
    if ($records = $DB->get_records_sql($sql, $params)) {
        foreach ($records as $record) {
            $badgeisnew = get_user_preferences('format_mooin_new_badge_'.$record->id, 0, $userid);
            if ($badgeisnew) {
                $unviewed_badges++;
            }
        }
    }
    return $unviewed_badges;
}
