<?php

/**
    * Get  Progress bar
*/
function get_progress_bar($p, $width, $sectionid = 0) {
        //$p_width = $width / 100 * $p;
    $result = html_writer::tag('div',
                html_writer::tag('div',
                    html_writer::tag('div',
                        '',
                        array('style' => 'width: ' . $p . '%; height: 15px; border: 0px; background: #9ADC00; text-align: center; float: left; border-radius: 12px', 'id' => 'mooin4ection' . $sectionid)
                    ),
                    array('style' => 'width: ' . $width . '%; height: 15px; border: 1px; background: #aaa; solid #aaa; margin: 0 auto; padding: 0;  border-radius: 12px')
                ) .
                // html_writer::tag('div', $p . '%', array('style' => 'float: right; padding: 0; position: relative; color: #555; width: 100%; font-size: 12px; transform: translate(-50%, -50%);margin-top: -8px;left: 50%;')) .
            html_writer::tag('div', '', array('style' => 'clear: both;'))  .
            html_writer::start_span('',['style' => 'float: left;font-size: 12px; margin-left: 12px']) . $p .' % bearbeitet' . html_writer::end_span(), //, 'id' => 'oc-progress-text-' . $sectionid
            array( 'style' => 'position: relative')); // 'class' => 'oc-progress-div',
    return $result;
}
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
            // var_dump($COURSE->id);
            if ($thismod->uservisible && !$thismod->deletioninprogress) {

                if ($thismod->modname == 'label') { // $this->completioninfo->is_enabled($thismod)
                    $outof = 1;

                    $com_value = $USER->id . ' ' . $COURSE->id . ' ' . $thismod->sectionnum;
                    $value_pref = $DB->record_exists('user_preferences', array('value' => $com_value));
                    if ($value_pref) {
                    	$completed = 1;
                    }else {
                         $completed = 0;
                    }
                }
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
     * Set a section without h5p element as done
     *
     * @param stdclass $course
     * @param array $sections (argument not used)
     * @param int $userid (argument not used)
     * @param int $courseid (argument not used)
 */
function complete_section($userid, $cid, $section) {
        global $DB;

        $res = false;
        $q = $userid .' ' . $cid. ' ' .$section;
        $value_check = $DB->record_exists('user_preferences', array('value' => $q));
        $id = $DB->count_records('user_preferences', array('userid'=> $userid));


        if ( array_key_exists('btnComplete-'.$section, $_POST)) { // isset($_POST["id_bottom_complete-".$section])
            $res = true;
            // echo ' Inside Complete Section '. $res;
            $values = new stdClass();
            $values->id = $id + 1;
            $values->userid = $userid;
            $values->name = 'section_progess_with_text'.$q;
            $values->value = $q;

            if (!$value_check) {
                $DB->insert_record('user_preferences',$values, true, false );
            }

        }
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

           /*  var_dump($sec);
            echo('SEC.'); */
            $mods = $DB->get_records_sql("SELECT cm.*, m.name as modname
                        FROM {modules} m, {course_modules} cm
                    WHERE cm.course = ? AND cm.section= ? AND cm.completion !=0 AND cm.module = m.id AND m.visible = 1", array($COURSE->id, (int)$sec->id));


            $percentage = 0;
            $mods_counter = 0;
            $max_grade = 10.0;

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

                return ($percentage / $mods_counter) * $max_grade; //$percentage * $mods_counter; // $percentage / $mods_counter
            } else {
                return -1;
            }
        } else {
            return -1;
        }
}


// Badges functions
/**
 *
 */
function print_badges($records, $details = false, $highlight = false, $badgename = false) {
    global $DB;
    $lis = '';
    foreach ($records as $record) {
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
        $image = html_writer::empty_tag('img', array('src' => $imageurl, 'class' => 'badge-image', 'style' => 'width: 100px; height: 100px;' . $opacity));
        if (isset($record->uniquehash)) {
            $url = new moodle_url('/badges/badge.php', array('hash' => $record->uniquehash));
        } else {
            $url = new moodle_url('/badges/overview.php', array('id' => $record->id));
        }
        $detail = '';
        if ($details) {
            $user = $DB->get_record('user', array('id' => $record->userid));
            $detail = '<br />' . $user->firstname . ' ' . $user->lastname . '<br />(' . date('d.m.y H:i', $record->dateissued) . ')';
        } else if ($badgename) {
            $detail = '<br />' . $record->name;
        }
        $link = html_writer::link($url, $image . $detail, array('title' => $record->name));
        $lis .= html_writer::tag('li', $link, array('class' => 'new-badge-layer')); // new-badge-layer class for the "new badge overlay"
    }
    echo html_writer::tag('ul', $lis, array('class' => 'badges-list badges'));
}

/**
 *
 */
function display_user_and_availbale_badges($userid, $courseid) {
    global $CFG, $USER;
    $result = null;
    require_once($CFG->dirroot . '/badges/renderer.php');

    $coursebadges = get_badges($courseid, null, null, null);
    $userbadges = badges_get_user_badges($userid, $courseid, null, null, null, true);

    foreach ($userbadges as $ub) {
        if ($ub->status != 4) {
            $coursebadges[$ub->id]->highlight = true;
            $coursebadges[$ub->id]->uniquehash = $ub->uniquehash;
        }
    }
    if ($coursebadges) {
        $result = print_badges($coursebadges, false, true, true);
    } else {
        //$result .= html_writer::start_span() . get_string('no_badges_available', 'format_mooin') . html_writer::end_span();
        $result .= html_writer::div('','no-badges-img');
    }
    return $result;
}

/**
 *
 */
function get_badges($courseid = 0, $page = 0, $perpage = 0, $search = '') {
    global $DB;
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
	if($a->badgecount == $b->badgecount)
	{
		return 0;
	}
	return ($a->badgecount < $b->badgecount) ? -1 : 1;
}

function cmp_badges_desc($a, $b) {
	if($a->badgecount == $b->badgecount)
	{
		return 0;
	}
	return ($a->badgecount > $b->badgecount) ? -1 : 1;
}

// Certificate Functions

/**
 * Get certificat in a course
 * @param int courseid
 * @return array
 */
function get_certificate($courseid) {
    global $DB, $OUTPUT;

    $he = $DB->get_record('modules', ['name' =>'ilddigitalcert']);

    $te = $DB->get_records('course_modules', ['module' =>$he->id]);

    $ze = $DB->get_records('course_sections', ['course' =>$courseid]);
    $course = $DB->get_record('course', ['id' =>$courseid]);

    // $cm_id = 0;
    $templatedata = array();

    $a = 1;
    foreach ($ze as $key => $value) {
        foreach ($te as $k => $v) {
            if ($value->id == $v->section) {
                // var_dump($v);
                $cm_id = $v->id;
                array_push($templatedata, (object)[
                    'id'=> $v->id,
                    'index' => $a++,
                    'module' => $value->module,
                    'section' => $v->section
                ]) ;
            }
        }
    }
    if (count($templatedata) > 0) {
        for ($i=0; $i < count($templatedata); $i++) {

                $templatedata[$i]->certificate_name = 'Certificate';
                $templatedata[$i]->preview_url = (
                new moodle_url(
                    '/mod/ilddigitalcert/view.php',
                    array("id" => $templatedata[$i]->id, 'issuedid' => $templatedata[$i]->section)
                )
                )->out(false);
                $templatedata[$i]->course_name = $course->fullname;
            }
    }else {
        $templatedata =  $OUTPUT->heading(get_string('certificate_overview', 'format_mooin'));
    }

    return $templatedata;
}
/**
 * show the  certificat on the welcome page
 * @param int courseid
 * @return array
 */
function show_certificat($courseid) {
    $out_certificat = null;
    // if ( get_certificate($courseid)) {
        // TO-DO
        $templ = get_certificate($courseid);
        $out_certificat .= html_writer::start_tag('div', ['class'=>'certificat_card', 'style'=>'display:flex']); // certificat_card

        if (is_string($templ) == 1) {
            $out_certificat = $templ;
    }
        if (is_string($templ) != 1) {

            $imageurl = 'images/certificat.png';
            for ($i=0; $i < count($templ); $i++) {

                $out_certificat .= html_writer::start_tag('div', ['class'=>'certificat_body', 'style'=>'display:grid; cursor:pointer']); // certificat_card

                $out_certificat .= html_writer::empty_tag('img', array('src' => $imageurl, 'class' => '', 'style' => 'width: 100px; height: 100px; margin: 0 auto')); // $opacity

                // $out_certificat .= html_writer::start_tag('button', ['class'=>'btn btn-primary btn-lg certificat-image', 'style'=>'margin-right:2rem']);
                $certificat_url = $templ[$i]->preview_url;
                $out_certificat .= html_writer::link($certificat_url, ' ' . $templ[$i]->course_name . ' ' . $templ[$i]->index);
                // $out_certificat .= html_writer::end_tag('button'); // button
                $out_certificat .= html_writer::end_tag('div'); // certificat_body

            }

        }
        // $out_certificat .= html_writer::end_tag('div'); // certificat_card
        // $out_certificat .= html_writer::end_tag('div'); // certificat_card
        // $out_certificat .= html_writer::end_tag('div'); // certificat_card

         // $out_certificat; //echo
    //  }
    return  $out_certificat;
}
// News functions

/**
 * Get the last News in a course
 * @param int $courseid
 * @param string $forum_type
 * @return array
 */
function get_last_news($courseid, $forum_type) {
    global $DB, $OUTPUT, $USER;


    // Get all the forum (news) oder general  in the course
    $sql_first = 'SELECT * FROM mdl_forum WHERE course = :id_course AND type = :type_forum LIMIT 1 '; //ORDER BY ID DESC
    $param_first = array('id_course'=>$courseid, 'type_forum'=>$forum_type);
    // $new_in_course = $DB->get_record('forum', ['course' =>$courseid, 'type' => $forum_type]);
    $new_in_course = $DB->get_record_sql($sql_first, $param_first);
    // var_dump($new_in_course);
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
        // var_dump($news_forum_post);
        $user = $DB->get_record('user', ['id' => $news_forum_post->userid], '*');

        // Get the right date for new creation
        // Deutsches Datumsformat hier oder in der lang file?
        $created_news = date("d. m. Y,  G:i", date((int)$news_forum_post->created));
        //$created_news = date("D M j G:i:s T Y", date((int)$news_forum_post->created));

            $out .= html_writer::start_tag('div', ['class'=> 'news']); // card_news

            if ($forum_type == 'news') {
                $out .= html_writer::start_tag('div', ['class'=> 'container']); //container
                $out .= html_writer::nonempty_tag('h2',get_string('news', 'format_mooin'),['class' => 'mb-1']);
                //$out .= get_string('news', 'format_mooin');
            } else {
                //$out .= get_string('discussion', 'format_mooin');
            }
            $out .= html_writer::start_tag('div', ['class' => 'd-none d-md-inline-block align-items-center mb-3']); //right_part_new

            $news_forum_id = $new_in_course->id;
            $newsurl = new moodle_url('/mod/forum/view.php', array('f' => $news_forum_id, 'tab' => 1));
            $url_disc = new moodle_url('/course/format/mooin/forum_view.php', array('f'=>$news_forum_id, 'tab'=>1));
            if ($forum_type == 'news') {
                // Falls es neue Nachrichten gibt
                $out .= html_writer::start_span('count-container inline-badge fw-700 mr-1') .'4'. html_writer::end_span(); //Notification Counter
                // sonst print 0
                $out .= '0 ';

                $out .= get_string('unread_news', 'format_mooin');
                $out .= html_writer::link($newsurl, get_string('all_news', 'format_mooin'), array('title' => get_string('all_news', 'format_mooin'), 'class' =>'primary-link'));
            } else {
                $sql = 'SELECT * FROM mdl_forum WHERE course = :cid AND type != :tid ORDER BY ID ASC';
                $param = array('cid' =>$courseid, 'tid' => 'news');
                $oc_foren = $DB->get_records_sql($sql, $param);
                // $oc_f = $DB->get_records('forum_discussions', array('course' => $courseid));
                $cond_in_forum_posts = 'SELECT * FROM mdl_forum_discussions WHERE course = :id ORDER BY ID DESC LIMIT 1';
                $param =  array('id' => $courseid );
                $oc_f = $DB->get_record_sql($cond_in_forum_posts, $param);
                if (count($oc_foren) > 1 || count($oc_f)) {
                    $out .= html_writer::start_span('count-container inline-badge fw-700 mr-1') .'4'. html_writer::end_span(); //Notification Counter
                    $out .= html_writer::link($url_disc, get_string('all_discussions', 'format_mooin'), array('title' => get_string('all_discussions', 'format_mooin'), 'class' =>'primary-link'));
                } /* else {
                    $out .= html_writer::link($newsurl, get_string('all_discussions', 'format_mooin'), array('title' => get_string('all_discussions', 'format_mooin')));
                } */

            }

            $out .= html_writer::end_tag('div'); // right_part_new
            // $out .= html_writer::start_tag('div', ['class' =>' primary-link d-none d-md-block']); //left_part_new
            // $news_forum_id = $new_in_course->id;
            // $newsurl = new moodle_url('/mod/forum/view.php', array('f' => $news_forum_id, 'tab' => 1));
            // if ($forum_type == 'news') {
            //     //$out .= html_writer::link($newsurl, get_string('old_news', 'format_mooin'), array('title' => get_string('old_news', 'format_mooin')));
            // } else {
            //     $out .= html_writer::link($newsurl, get_string('old_discussion', 'format_mooin'), array('title' => get_string('old_discussion', 'format_mooin')));
            // }

            // $out .= html_writer::end_tag('div'); // left_part_new

            $out .= html_writer::start_tag('div', ['class' =>'news-card d-flex']); // top_card_news justify-content-between


            //$out .= html_writer::start_tag('div'); // align-items-center



            if ($forum_type == 'news') {
                $out .= html_writer::start_span('news-icon') .  html_writer::end_span();
            } else {
                 $out .= html_writer::start_span('chat-icon') . html_writer::end_span();
            }
            //$out .= html_writer::end_tag('div'); // align-items-center



            $out .= html_writer::start_tag('div', ['class' => 'news-card-inner pt-1']);
            $out .= html_writer::start_span('caption d-none d-md-block');
            $out .= html_writer::start_span('fw-700 text-primary') . get_string('letze_beitrag','format_mooin') . '  ' . html_writer::end_span() . html_writer::start_span(''). ' von ' . $user->firstname . ' - ' . $created_news . html_writer::end_span();
            $out .= html_writer::end_span();

            $out .= html_writer::start_span('caption d-flex d-md-none justify-content-between');
            $out .= html_writer::start_span('fw-700 ') . get_string('latest_contribution_mobile','format_mooin') . '  ' . html_writer::end_span();
            $out .= html_writer::link($newsurl, get_string('all_news_mobile', 'format_mooin'), array('title' => get_string('all_news_mobile', 'format_mooin'), 'class' =>'primary-link text-primary'));

            $out .= html_writer::end_span();


            // $out .= html_writer::end_tag('p'); // caption text-primary pl-2


            $out .= html_writer::start_tag('div', ['class' => 'position-relative pt-0 pt-md-1']);
            $out .= html_writer::nonempty_tag('span',$OUTPUT->user_picture($USER, array('courseid'=>$courseid)),array('class' => 'new_user_picture d-none d-md-block')); // $user

            //$out .= html_writer::div($OUTPUT->user_picture($user, array('courseid'=>$courseid)), 'new_user_picture d-none d-md-block');

            $out .= html_writer::nonempty_tag('p', $news_forum_post->subject, ['class' => 'fw-500 text-truncate']); // fw-600 text-truncate
            //$out .= html_writer::start_span('in-community pb-2'). ' von ' . $user->firstname . ' - ' . $created_news.html_writer::end_span();

            // $out .= html_writer::nonempty_tag('p',$news_forum_post->message, ['class' => 'd-none d-md-block message']); // d-none d-md-block
            $out .= html_writer::start_tag('div', ['class' => 'd-none d-md-block message']); // d-none d-md-block
            $out .= $news_forum_post->message;
            $out .= html_writer::end_tag('div');

            $out .= html_writer::end_tag('div');

            $out .= html_writer::start_tag('div', ['class' =>'primary-link d-none d-md-block text-right']);
            $forum_discussion_url = new moodle_url('/mod/forum/discuss.php', array('d' => $news_forum_post->discussion));
            $discussion_url = html_writer::link($forum_discussion_url, get_string('discussion_news', 'format_mooin'), array('title' => get_string('discussion_news', 'format_mooin')));
            $out .= $discussion_url;
            $out .= html_writer::end_tag('div'); // div
            $out .= html_writer::end_tag('div'); // news-card-inner
            $out .= html_writer::end_tag('div'); // news-card

            $out .= html_writer::end_tag('div'); //container
            $out .= html_writer::div('','seperator');


            $out .= html_writer::end_tag('div'); // news
        }
        }
     else {
        $out = null;
    }
    return $out;
}

/**
 * Get course grade
 * @param int courseid
 */
function get_course_grades($courseid) {
    global $DB, $CFG, $USER;
    require_once($CFG->libdir . '/gradelib.php');

    if (isset($courseid)) {
        // $mods = get_course_section_mods($COURSE->id, $section);//print_object($mods);
        // Find a way to get the right section from the DB

        $sec = $DB->get_records_sql("SELECT cs.id
                    FROM {course_sections} cs
                    WHERE cs.course = ?", array($courseid));


        $mods = $DB->get_records_sql("SELECT cm.*, m.name as modname
                    FROM {modules} m, {course_modules} cm
                    WHERE cm.course = ? AND cm.completiongradeitemnumber >= 0 AND cm.module = m.id AND m.visible = 1", array($courseid)); // AND cm.completion !=0

        // echo count($mods);
        $percentage = 0;
        $mods_counter = 0;
        $max_grade = 100.0;
        $number_element = count($mods);
        foreach ($mods as $mod) {
            if ($mod->visible == 1) { // ($mod->modname == 'hvp')
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
                    $grading_info = grade_get_grades($mod->course, 'mod', $mod->modname, $mod->instance, $USER->id);
                    $grading_info = (object)($grading_info);// new, convert an array to object
                    if ($mod->modname == 'forum') {
                        $user_grade = $grading_info->items[1]->grades[$USER->id]->grade;
                    } else {
                    $user_grade = $grading_info->items[0]->grades[$USER->id]->grade;
                    }
                    //echo ('Grade : ' . $user_grade);
                    if ($user_grade > 0) {
                        $percentage += $user_grade ; // $user_grade
                    $mods_counter++;
                    }
                    // var_dump($mod->completion);

                }
            }
        }
        // echo ('Percentage : '. $mods_counter);
        if ($mods_counter != 0) {
            return ($max_grade * $mods_counter) /$number_element; //$percentage * $mods_counter; // $percentage / $mods_counter
        } else {
            return -1;
        }
    } else {
        return -1;
    }
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
                    array('style' => 'width: ' . $p . '%; height: 15px; border: 0px; background: #9ADC00; text-align: center; float: left; border-radius: 12px', 'id' => 'mooinprogressbar' )
                ),
                array('style' => 'width: ' . $width . '%; height: 15px; border: 1px; background: #aaa; solid #aaa; margin: 0 auto; padding: 0;  border-radius: 12px')
            ) .
            html_writer::start_span('',['style' => 'font-weight: bold']) . $p . '%' . html_writer::end_span() .
            html_writer::start_span(' d-sm-inline d-md-none ') . ' bearbeitet' . html_writer::end_span() .
            html_writer::start_span(' d-none d-md-inline ') . ' des Kurses bearbeitet' . html_writer::end_span() , // 'style' => 'float: left;font-size: 12px; margin-left: 12px',
            array( 'style' => 'position: relative')); // 'class' => 'oc-progress-div',
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
        $out .= html_writer::start_tag('div', ['class' =>'participant-card']); // participant-card
        $out .= html_writer::start_tag('div', ['class' =>'participant-card-inner']); // participant-card-inner
        $out .= html_writer::start_tag('div', ['class' =>'d-flex']); // d-flex align-items-center
        $out .= html_writer::start_span('icon-wrapper participant-icon') . html_writer::end_span();

        $out .= html_writer::start_tag('div');
        $out .= html_writer::nonempty_tag('p', get_string('user_card_title', 'format_mooin'), ['class'=>'caption fw-700 text-primary']);


        $user_count = count($count_val);
        $user_in_course = $user_count . ' ' . get_string('user_in_course', 'format_mooin');
        $out .= html_writer::start_tag('p');
        $out .= html_writer::start_span('fw-700') . $user_in_course . html_writer::end_span();
        $out .= html_writer::end_tag('p');
        $out .= html_writer::nonempty_tag('p', get_string('new_user', 'format_mooin'), ['class'=> 'caption fw-700']);

        $out .= html_writer::start_tag('ul', ['class' => 'caption', 'style'=> 'line-height: 3rem']); // user_card_list
        foreach ($user_enrol_data as $key => $value) {

            $el = array_values($value);
            for ($i=0; $i < count($el); $i++) {
                // var_dump($el);
                $out .= html_writer::start_tag('li');
                $user = $DB->get_record('user', ['id' => $el[$i]->userid], '*');
                $out .= html_writer::start_tag('span');
                $out .= html_writer::nonempty_tag('span',$OUTPUT->user_picture($user, array('courseid'=>$courseid)));
                $out .= $user-> firstname . ' ' . $user->lastname ;
                $out .= html_writer::end_tag('span');

                // $out .= html_writer::div($user-> firstname . ' ' . $user->lastname, 'user_card_name');
                $out .= html_writer::end_tag('li'); // user_card_element
            }
        }
        $out .= html_writer::end_tag('ul'); // user_card_list
        $out .= html_writer::end_tag('div');


        $out .= html_writer::end_tag('div'); // d-flex

        $out .= html_writer::start_tag('div', ['class' =>'primary-link d-none d-md-block text-right']);
        $participants_url = new moodle_url('/course/format/mooin/participants.php', array('id' => $courseid));
        $participants_link = html_writer::link($participants_url, get_string('show_all_infos', 'format_mooin'), array('title' => get_string('participants', 'format_mooin')));
        $out .= $participants_link;
        $out .= html_writer::end_tag('div'); // d-none d-md-block text-right
        $out .= html_writer::end_tag('div'); // participant-card-inner
        $out .= html_writer::end_tag('div'); // participant-card
    } else {
        $out .= html_writer::div(get_string('no_user', 'format_mooin'), 'no_user_class'); // '
    }

    return $out;
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