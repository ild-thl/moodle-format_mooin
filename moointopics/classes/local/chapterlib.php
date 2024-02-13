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
            if ($coursesections = $DB->get_records('course_sections', array('course' => $chapter->courseid), 'section', 'section, id')) {
                if ($start != 0) {
                    if ($end == 0) {
                        $end = self::get_last_section($chapter->courseid) + 1;
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
    
    public static function is_last_section_of_chapter($sectionid) {
        global $DB;
        $chapter = null;
        if ($section = $DB->get_record('course_sections', array('id' => $sectionid))) {
    
            $chapters = self::get_course_chapters($section->course);
            $chapter = self::get_chapter_for_section($sectionid);
    
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
                    $end = self::get_last_section($section->course) + 1;
                }
            }
    
            if ($section -> section == $end-1) {
                return true;
            }
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
}

