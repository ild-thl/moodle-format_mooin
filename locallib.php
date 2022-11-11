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
            echo ' Inside Complete Section '. $res;
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
                if (($mod->modname == 'hvp') && $mod->visible == 1) {
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
                        $grading_info = grade_get_grades($mod->course, 'mod', 'hvp', $mod->instance, $USER->id);
                        $grading_info = (object)($grading_info);// new, convert an array to object
                        $user_grade = $grading_info->items[0]->grades[$USER->id]->grade;

                        $percentage += $user_grade;
                        $mods_counter++;
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