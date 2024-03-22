<?php

namespace format_moointopics\local;

class progresslib {

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
}