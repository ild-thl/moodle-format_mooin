<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . "/externallib.php");
require_once($CFG->dirroot . '/course/lib.php');


use format_mooin4\local\utils as utils;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_format_value;
use core_external\external_value;
use core_external\external_warnings;

/**
 * External function for format_mooin4.
 *
 * @package     format_mooin4
 * @copyright   2023 ISy TH Lübeck <dev.ild@th-luebeck.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_mooin4_external extends external_api {


    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function check_completion_status_parameters() {
        return new external_function_parameters([
            'section_id' => new external_value(PARAM_INT, 'Current Section ID'),
            'isActivity' => new external_value(PARAM_BOOL, 'is it hvp?'),
            'course_already_completed' => new external_value(PARAM_BOOL, 'Check if the Course is completetd already'),
            'chapter_already_completed' => new external_value(PARAM_BOOL, 'Check if the current Chapter is completetd already'),
        ]);
    }



    /**
     * Returns status
     * @param int $sectionid
     * @param bool $isactivity
     * @param bool $coursealreadycompleted
     * @param bool $chapteralreadycompleted
     * @return array user data
     */
    public static function check_completion_status($sectionid, $isactivity, $coursealreadycompleted, $chapteralreadycompleted) {
        global $DB, $USER;

        $params = self::validate_parameters(
            self::check_completion_status_parameters(),
            [
                'section_id' => $sectionid,
                'isActivity' => $isactivity,
                'course_already_completed' => $coursealreadycompleted,
                'chapter_already_completed' => $chapteralreadycompleted,
            ]
        );

        $context = \context_system::instance();
        self::validate_context($context);

        if (!$isactivity) {
            utils::complete_section($sectionid, $USER->id);
        }

        $section = $DB->get_record('course_sections', ['id' => $params['section_id']]);
        $courseid = $section->course;
        $parentchapter = utils::get_parent_chapter($section);
        $info = utils::get_chapter_info($parentchapter);

        $iscoursecompleted = utils::is_course_completed($courseid);

        // Get the Section for the next Chapter.

        $chapterforward = null;
        if ($nextchapter = $DB->get_record(
            'format_mooin4_chapter',
            ['courseid' => $courseid, 'chapter' => ($parentchapter->chapter) + 1]
        )) {
            $nextchaptersection = $DB->get_record('course_sections', ['id' => $nextchapter->sectionid]);
            $chapterforward = $nextchaptersection->section + 1;
        } else {
            $chapterforward = -1;
        }
        $showchaptermodal = false;
        if (!$chapteralreadycompleted) {
            if ($info['completed']) {
                $showchaptermodal = true;
            }
        }

        $showcoursemodal = false;

        if (!$coursealreadycompleted) {
            if ($iscoursecompleted == true) {
                $showchaptermodal = false;
                $showcoursemodal = true;
            }
        }

        return [
            'show_chapter_modal' => $showchaptermodal,
            'show_course_modal' => $showcoursemodal,
            'chapter_id' => $parentchapter->sectionid,
            'course_id' => $courseid,
            'next_chapter' => $chapterforward,
        ];
    }

    /**
     * Returns description of method result value
     * @return external_single_structure
     */
    public static function check_completion_status_returns() {
        return new external_single_structure([
            'show_chapter_modal' => new external_value(PARAM_BOOL, 'if chapter is completed with section completion'),
            'show_course_modal' => new external_value(PARAM_BOOL, 'if course is completed'),
            'chapter_id' => new external_value(PARAM_INT, 'id of the sections chapter'),
            'course_id' => new external_value(PARAM_INT, 'Course id'),
            'next_chapter' => new external_value(PARAM_INT, 'Next Chapter'),
        ]);
    }



    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function setgrade_parameters() {
        return new external_function_parameters([
            'contentid' => new external_value(PARAM_INT, 'H5P content id'),
            'score' => new external_value(PARAM_FLOAT, 'H5P score'),
            'maxscore' => new external_value(PARAM_FLOAT, 'H5P max score'),
            'cmid' => new external_value(PARAM_INT, 'Course module id', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Returns status
     * @param int $contentid
     * @param float $score
     * @param float $maxscore
     * @param int $cmid
     * @return array user data
     */
    public static function setgrade($contentid, $score, $maxscore, $cmid = 0) {
        global $DB, $CFG, $USER;

        $params = self::validate_parameters(self::setgrade_parameters(), [
            'contentid' => $contentid,
            'score' => $score,
            'maxscore' => $maxscore,
            'cmid' => $cmid,
        ]);
        $contentid = $params['contentid'];
        $score = $params['score'];
        $maxscore = $params['maxscore'];
        $cmid = $params['cmid'];

        $context = \context_system::instance();
        self::validate_context($context);

        $cm = null;
        $module = null;

        if (!empty($cmid)) {
            $cm = get_coursemodule_from_id(null, $cmid, 0, false, IGNORE_MISSING);
            if ($cm) {
                $module = $cm->modname;
            }
        }

        if (!$cm) {
            $cm = get_coursemodule_from_instance('hvp', $contentid, 0, false, IGNORE_MISSING);
            if ($cm) {
                $module = 'hvp';
            }
        }

        if (!$cm) {
            // For h5pactivity, try to find by contentid (which might be the instance ID).
            $cm = get_coursemodule_from_instance('h5pactivity', $contentid, 0, false, IGNORE_MISSING);
            if ($cm) {
                $module = 'h5pactivity';
            } else {
                // If not found by instance, try to find by searching in h5pactivity table.
                // This is a fallback if contentid is not the instance ID.
                $h5pactivity = $DB->get_record('h5pactivity', ['id' => $contentid], '*', IGNORE_MISSING);
                if ($h5pactivity) {
                    $cm = get_coursemodule_from_instance('h5pactivity', $h5pactivity->id, 0, false, IGNORE_MISSING);
                    if ($cm) {
                        $module = 'h5pactivity';
                    }
                }
            }
        }

        if (!$cm) {
            return [
                'sectionid' => null,
                'percentage' => null,
                'course_already_completed' => false,
                'chapter_already_completed' => false,
                'courseid' => null,
            ];
        }

        if ($module === 'hvp') {
            require_once($CFG->dirroot . '/mod/hvp/lib.php');
        }

        $courseid = $cm->course;
        $sectionid = $cm->section;

        $coursealreadycompleted = utils::is_course_completed($courseid);
        $section = $DB->get_record('course_sections', ['id' => $sectionid]);
        $parentchapter = utils::get_parent_chapter($section);
        $info = utils::get_chapter_info($parentchapter);
        $chapteralreadycompleted = !empty($info['completed']);

        $progress = false;
        $immediatepercentage = null;
        if ($module === 'hvp') {
            $progress = utils::setgrade($contentid, $score, $maxscore);
        }

        if ($maxscore > 0) {
            $immediatepercentage = ($score / $maxscore) * 100;
            if (!empty($cm->id)) {
                set_user_preference('format_mooin4_hvp_progress_cmid_' . $cm->id, $immediatepercentage, $USER->id);
            }
            if ($module !== 'h5pactivity') {
                set_user_preference('format_mooin4_hvp_progress_' . $contentid, $immediatepercentage, $USER->id);
            } else {
                 // For h5pactivity, we MUST persist the grade to the gradebook.
                 // This ensures it survives backup/restore.
                 utils::setgrade_h5pactivity($cm, $score, $maxscore);
            }
        }

        // Always calculate the overall section progress after storing individual activity progress.
        // This ensures we return the correct percentage based on all activities in the section.
        $overallprogress = utils::get_section_progress($courseid, $sectionid, $USER->id);

        if ($progress === false) {
            $progress = [
                'sectionid' => $sectionid,
                'percentage' => $overallprogress,
            ];
        } else {
            // Update with overall progress, not just the single activity progress.
            $progress['percentage'] = $overallprogress;
        }

        return [
            'sectionid' => $progress['sectionid'],
            'percentage' => $progress['percentage'],
            'course_already_completed' => $coursealreadycompleted,
            'chapter_already_completed' => $chapteralreadycompleted,
            'courseid' => $courseid,
        ];
    }

    /**
     * Returns description of method result value
     * @return external_single_structure
     */
    public static function setgrade_returns() {
        return new \external_single_structure([
            'sectionid' => new external_value(PARAM_INT, 'Section ID'),
            'percentage' => new external_value(PARAM_FLOAT, 'Percentage of section progress'),
            'course_already_completed' => new external_value(PARAM_BOOL, 'Check if the Course is completetd already'),
            'chapter_already_completed' => new external_value(PARAM_BOOL, 'Check if the current Chapter is completetd already'),
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
        ], 'Section progress');
    }
}
