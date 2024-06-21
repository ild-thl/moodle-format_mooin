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


class chapterlib {

    public static function set_chapter($sectionid) {
        global $DB;
    
        if ($DB->get_record('format_moointopics_chapter', array('sectionid' => $sectionid))) {
            return;
        }
    
        if ($csection = $DB->get_record('course_sections', array('id' => $sectionid))) {
            $csectiontitle = $csection->name;
        }
        else {
            return;
        }
    
        if (!$csectiontitle) {
            $csectiontitle = get_string('new_chapter', 'format_moointopics');
        }
    
        $chapter = new stdClass();
        $chapter->courseid = $csection->course;
        $chapter->title = $csectiontitle;
        $chapter->sectionid = $sectionid;
        $chapter->chapter = 0;
        $DB->insert_record('format_moointopics_chapter', $chapter);
    
        self::sort_course_chapters($csection->course);
    }

    public static function unset_chapter($sectionid) {
        global $DB;
    
        $DB->delete_records('format_moointopics_chapter', array('sectionid' => $sectionid));
        if ($csection = $DB->get_record('course_sections', array('id' => $sectionid))) {
            self::sort_course_chapters($csection->course);
        }
    }

    public static function sort_course_chapters($courseid) {
        global $DB;
        $coursechapters = self::get_course_chapters($courseid);
        $number = 0;
        foreach ($coursechapters as $coursechapter) {
            $number++;
            if ($existingcoursechapter = $DB->get_record('format_moointopics_chapter', array('id' => $coursechapter->id))) {
                $existingcoursechapter->chapter = $number;
                $DB->update_record('format_moointopics_chapter', $existingcoursechapter);
            }
        }
    }

    public static function get_last_section($courseid) {
        global $DB;
    
        $lastsection = 0;
        $count = $DB->count_records('course_sections', array('course' => $courseid));
    
        if ($count > 0) {
            $lastsection = $count - 1;
        }
    
        return $lastsection;
    }
    
    public static function get_section_prefix($section) {
        global $DB;
    
        $sectionprefix = '';
    
        $parentchapter = self::get_parent_chapter($section);
        if (is_object($parentchapter)) {
            $sids = self::get_sectionids_for_chapter($parentchapter->id);
            $sectionprefix .= $parentchapter->chapter.'.'.(array_search($section->id, $sids) + 1);
    
            return $sectionprefix;
        }
    
    }

    public static function get_parent_chapter($section) {
        global $DB;
    
        $chapters = $DB->get_records('format_moointopics_chapter', array('courseid' => $section->course));
        foreach ($chapters as $chapter) {
            $sids = self::get_sectionids_for_chapter($chapter->id);
            if (in_array($section->id, $sids)) {
                return $chapter;
            }
        }
    
        return false;
    }

    public static function get_sectionids_for_chapter($chapterid) {
        global $DB;
        $result = array();
        if ($chapter = $DB->get_record('format_moointopics_chapter', array('id' => $chapterid))) {
            $chapters = self::get_course_chapters($chapter->courseid);
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
            if ($coursesections = $DB->get_records('course_sections', array('course' => $chapter->courseid), 'section', 'section, id, visible')) {
                if ($start != 0) {
                    if ($end == 0) {
                        $end = self::get_last_section($chapter->courseid) + 1;
                    }
                    $i = $start + 1;
                    while ($i < $end) {
                        if ($coursesections[$i]->visible == true) {
                            $result[] = $coursesections[$i]->id; 
                        }
                        $i++;
                    }
                }
            }
        }
        return $result;
    }

    public static function get_course_chapters($courseid) {
        global $DB;
    
        $sql = 'SELECT c.*, s.section
                  FROM {format_moointopics_chapter} as c, {course_sections} as s
                 WHERE s.course = :courseid
                   and s.id = c.sectionid
              order by s.section asc';
    
        $params = array('courseid' => $courseid);
    
        $coursechapters = $DB->get_records_sql($sql, $params);
    
        return $coursechapters;
    }

    public static function is_first_section_of_chapter($sectionid) {
        global $DB;
        $chapter = null;
        if ($section = $DB->get_record('course_sections', array('id' => $sectionid))) {
    
            $chapters = self::get_course_chapters($section->course);
    
            foreach ($chapters as $c) {
                if ($section->section == $c->section +1) {
                   return true;
                }
            }
        }
        return false;
    }
    
    // public static function is_last_section_of_chapter($sectionid) {
    //     global $DB;
    //     $chapter = null;
    //     if ($section = $DB->get_record('course_sections', array('id' => $sectionid))) {
    
    //         $chapters = self::get_course_chapters($section->course);
    //         $chapter = self::get_chapter_for_section($sectionid);
    
    //         $start = 0;
    //         $end = 0;
    //         foreach ($chapters as $c) {
    //             if ($c->chapter == $chapter) {
    //                 $start = $c->section;
    //                 continue;
    //             }
    //             if ($start != 0) {
    //                 $end = $c->section;
    //                 break;
    //             }
    //         }
    //         if ($start != 0) {
    //             if ($end == 0) {
    //                 $end = self::get_last_section($section->course) + 1;
    //             }
    //         }
    
    //         if ($section -> section == $end-1) {
    //             return true;
    //         }
    //     }
    //     return false;
    // }

    //TODO: Funktioniert -> evtl gut falls sections doch unsichtbar gemacht werden sollen, aber erstmal sections stattdessen sperren
    public static function is_last_section_of_chapter($sectionid) {
        global $DB;
        
        if ($section = $DB->get_record('course_sections', array('id' => $sectionid))) {
            $course = get_course($section->course);
            $format = course_get_format($course);
            $parentchapter = self::get_parent_chapter($section);
            $sectionids = self::get_sectionids_for_chapter($parentchapter->id);
            $highestVisibleSection = null;
            foreach ($sectionids as $sectionid) {
                if ($s = $DB->get_record('course_sections', array('id' => $sectionid))) {
                    $section_info = get_fast_modinfo($course)->get_section_info($s->section);
                    if ($format->is_section_visible($section_info) && $s->section > $highestVisibleSection) {
                        $highestVisibleSection = $s->section;
                    }
                }
            }
            return $section->section == $highestVisibleSection;
        }
        return false;
    }

    public static function get_chapter_for_section($sectionid) {
        global $DB;
        $chapter = null;
        if ($section = $DB->get_record('course_sections', array('id' => $sectionid))) {
            $chapters = self::get_course_chapters($section->course);
    
            foreach ($chapters as $c) {
                if ($section->section > $c->section && ($chapter = null||$c->section > $chapter)) {
                    $chapter = $c->chapter;
                }
            }
        }
        return $chapter;
    }

    public static function course_navbar() {
        global $PAGE, $OUTPUT, $COURSE;
         $items = $PAGE->navbar->get_items();
         $course_items = [];
    
        //Split the navbar array at coursehome
         foreach($items as $item) {
            if ($item->key === $COURSE->id) {
                $course_items = array_splice($items, intval(array_search($item, $items)));
            }
         }
    
         $course_items[0]->add_class('course-title');
         $section_node = $course_items[array_key_last($course_items)];
         $section_node->action = null;
         $text = $section_node->text;
         $parts = explode(':', $text, 2);
         $result = trim($parts[0]) . ':';
         $text = $section_node->text = $result;
    
         //Provide custom templatecontext for the new Navbar
        $templatecontext = array(
            'get_items'=> $course_items
        );
    
        return $OUTPUT->render_from_template('format_moointopics/custom_navbar', $templatecontext);
    }

    public static function subpage_navbar() {
        global $PAGE, $OUTPUT, $COURSE;
         $items = $PAGE->navbar->get_items();
         $course_items = [];
    
        //Split the navbar array at coursehome
         foreach($items as $item) {
            if ($item->key === $COURSE->id) {
                $course_items = array_splice($items, intval(array_search($item, $items)));
            }
         }
    
         //$course_items[0]->add_class('course-title');
         $last_node = $course_items[array_key_last($course_items)];
         $last_node->action = null;
         $last_node->shorttext = $last_node->text;
    
    
         //Provide custom templatecontext for the new Navbar
        $templatecontext = array(
            'get_items'=> $course_items
        );
    
        return $OUTPUT->render_from_template('format_moointopics/custom_navbar', $templatecontext);
    }

    public static function get_chapter_info($chapter) {
        global $USER, $DB;
        $info = array();
    
        $chaptercompleted = false;
        $lastvisited = false;
    
        $sectionids = self::get_sectionids_for_chapter($chapter->id);
        $completedsections = 0;
    
        foreach ($sectionids as $sectionid) {
            $section = $DB->get_record('course_sections', array('id' => $sectionid));
            if ($section && self::is_section_completed($chapter->courseid, $section)) {
                $completedsections++;
            }
    
            $last_section = get_user_preferences('format_moointopics_last_section_in_course_'.$chapter->courseid, 0, $USER->id);
            if ($record = $DB->get_record('course_sections', array('course' => $chapter->courseid, 'section' => $last_section))) {
                if ($record->id == $sectionid) {
                    $lastvisited = true;
                }
            }
        }
        if ($completedsections == count($sectionids)) {
            $chaptercompleted = true;
        }else {
            $chaptercompleted = false;
        }
        $info['completed'] = $chaptercompleted;
        $info['lastvisited'] = $lastvisited;
        return $info;
    }

    public static function is_section_completed($courseid, $section) {
        global $USER, $DB;
        /*
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
        */
        $result = false;
        if (self::get_section_progress($courseid, $section->id, $USER->id) == 100) {
            $result = true;
        }else {
            $result = false;
        }
    
        return $result;
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

    public static function get_unenrol_url($courseid) {
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

    public static function is_course_started($course) {
        global $DB;
        global $USER;
        //$chapterlib = $this->chapterlib;
        //$course = $this->format->get_course();
        $last_section = get_user_preferences('format_moointopics_last_section_in_course_' . $course->id, 0, $USER->id);
        if ($last_section) {
            return true;
        } else {
            return false;
        }
    }

    public static function get_continue_section($course) {
        global $DB;
        global $USER;
        //$chapterlib = $this->chapterlib;
        //$course = $this->format->get_course();

        $last_section = get_user_preferences('format_moointopics_last_section_in_course_' . $course->id, 0, $USER->id);


        if ($last_section) {
            if ($last_section == 0 || $last_section == 1) {
                $last_section = 2;
            }

            if ($continuesection = $DB->get_record('course_sections', array('course' => $course->id, 'section' => $last_section))) {
                return self::get_section_prefix($continuesection);
            } else {
                return false;
            }
        } else {
            return 2;
        }
    }

    public static function get_continue_url($course) {
        global $DB;
        global $USER;
        //$chapterlib = $this->chapterlib;
        //$course = $this->format->get_course();

        $last_section = get_user_preferences('format_moointopics_last_section_in_course_' . $course->id, 0, $USER->id);


        if ($last_section) {
            if ($last_section == 0 || $last_section == 1) {
                //return new moodle_url('/course/view.php', array('id' => $course->id, 'section' => 1));
                $last_section = 2;
            }
            if ($continuesection = $DB->get_record('course_sections', array('course' => $course->id, 'section' => $last_section))) {
                return new moodle_url('/course/view.php', array('id' => $course->id, 'section' => $continuesection->section));
                //return $continuesection->section;
            } else {
                return new moodle_url('/course/view.php', array('id' => $course->id, 'section' => 2));
            }
        } else {
            //return new moodle_url('/course/view.php', array('id' => $course->id, 'section' => 1));
            return new moodle_url('/course/view.php', array('id' => $course->id, 'section' => 2));
        }
    }

    /**
     * Returns url for headerimage
     *
     * @param int courseid
     * @param bool true if mobile header image is required or false for desktop image
     * @return string|bool String with url or false if no image exists
     */
    static function get_headerimage_url($courseid, $mobile = true) {
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
            'component' => 'format_moointopics',
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
    
        $url = new moodle_url('/pluginfile.php/'.$context->id.'/format_moointopics/'.$filearea.'/'.$courseid.'/0/'.$filename);
        return $url;
    }
}

