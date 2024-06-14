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

namespace format_moointopics\courseformat;

use core_courseformat\stateupdates;
use core\event\course_module_updated;
use cm_info;
use section_info;
use stdClass;
use course_modinfo;
use moodle_exception;
use context_module;
use context_course;
use core_courseformat\stateactions as Base;
use format_moointopics;

/**
 * Contains the core course state actions.
 *
 * The methods from this class should be executed via "core_courseformat_edit" web service.
 *
 * Each format plugin could extend this class to provide new actions to the editor.
 * Extended classes should be locate in "format_XXX\course" namespace and
 * extends core_courseformat\stateactions.
 *
 * @package    core_courseformat
 * @copyright  2021 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stateactions extends Base {

    public function complete_section(
        stateupdates $updates,
        stdClass $course,
        array $ids = [],
        ?int $targetsectionid = null,
        ?int $targetcmid = null
    ): void {
        format_moointopics\local\progresslib::complete_section($targetsectionid);
        $this->section_state($updates, $course, $ids);
    }

    public function update_sectionprogress(
        stateupdates $updates,
        stdClass $course,
        array $ids = [],
        ?int $targetsectionid = null,
        ?int $targetcmid = null
    ): void {
        //format_moointopics\local\progresslib::complete_section($targetsectionid);
        $this->section_state($updates, $course, $ids);
    }

    public function set_last_section_modal(
        stateupdates $updates,
        stdClass $course,
        array $ids = [],
        ?int $targetsectionid = null,
        ?int $targetcmid = null
    ): void {
        set_user_preference('format_moointopics_hide_modal_for_section_'.$targetsectionid, 'true');
        $this->section_state($updates, $course, $ids);
    }

    public function section_setChapter(
        stateupdates $updates,
        stdClass $course,
        array $ids = [],
        ?int $targetsectionid = null,
        ?int $targetcmid = null
    ): void {
        format_moointopics\local\chapterlib::set_chapter($targetsectionid);
        $this->section_state($updates, $course, $ids);

        course_modinfo::purge_course_modules_cache($course->id, $ids);
        rebuild_course_cache($course->id, false, true);
    }

    public function section_unsetChapter(
        stateupdates $updates,
        stdClass $course,
        array $ids = [],
        ?int $targetsectionid = null,
        ?int $targetcmid = null
    ): void {
        format_moointopics\local\chapterlib::unset_chapter($targetsectionid);
        $this->section_state($updates, $course, $ids);
        course_modinfo::purge_course_modules_cache($course->id, $ids);
        rebuild_course_cache($course->id, false, true);
    }

    public function getContinuesection(
        stateupdates $updates,
        stdClass $course,
    ): void {
        $this->course_state($updates, $course);
    }

    public function readAllForumDiscussions(
        stateupdates $updates,
        stdClass $course,
        array $ids = [],
    ): void {
        global $DB, $USER;
        $forumid = $ids[0];
        if ($discussions = $DB->get_records('forum_discussions', array('forum' => $forumid))) {
            foreach ($discussions as $discussion) {
                format_moointopics\local\forumlib::set_discussion_viewed($USER->id, $forumid, $discussion->id);
            }
        }
    }

    /**
     * Show course sections.
     *
     * @param stateupdates $updates the affected course elements track
     * @param stdClass $course the course object
     * @param int[] $ids section ids
     * @param int $targetsectionid not used
     * @param int $targetcmid not used
     */
    public function section_show(
        stateupdates $updates,
        stdClass $course,
        array $ids = [],
        ?int $targetsectionid = null,
        ?int $targetcmid = null
    ): void {
        $this->set_section_visibility($updates, $course, $ids, 1);
    }

    /**
     * Show course sections.
     *
     * @param stateupdates $updates the affected course elements track
     * @param stdClass $course the course object
     * @param int[] $ids section ids
     * @param int $visible the new visible value
     */
    protected function set_section_visibility(
        stateupdates $updates,
        stdClass $course,
        array $ids,
        int $visible
    ) {
        $this->validate_sections($course, $ids, __FUNCTION__);
        $coursecontext = context_course::instance($course->id);
        require_all_capabilities(['moodle/course:update', 'moodle/course:sectionvisibility'], $coursecontext);

        $modinfo = get_fast_modinfo($course);

        foreach ($ids as $sectionid) {
            $section = $modinfo->get_section_info_by_id($sectionid, MUST_EXIST);
            course_update_section($course, $section, ['visible' => $visible]);
        }
        $this->section_state($updates, $course, $ids);
    }

    /**
     * Move course sections to another location in the same course.
     *
     * @param stateupdates $updates the affected course elements track
     * @param stdClass $course the course object
     * @param int[] $ids the list of affected course module ids
     * @param int $targetsectionid optional target section id
     * @param int $targetcmid optional target cm id
     */
    public function section_move(
        stateupdates $updates,
        stdClass $course,
        array $ids,
        ?int $targetsectionid = null,
        ?int $targetcmid = null
    ): void {
        // Validate target elements.
        if (!$targetsectionid) {
            throw new moodle_exception("Action cm_move requires targetsectionid");
        }

        $this->validate_sections($course, $ids, __FUNCTION__);

        $coursecontext = context_course::instance($course->id);
        require_capability('moodle/course:movesections', $coursecontext);

        $modinfo = get_fast_modinfo($course);

        // Target section.
        $this->validate_sections($course, [$targetsectionid], __FUNCTION__);
        $targetsection = $modinfo->get_section_info_by_id($targetsectionid, MUST_EXIST);

        $affectedsections = [$targetsection->section => true];

        $sections = $this->get_section_info($modinfo, $ids);
        foreach ($sections as $section) {
            $affectedsections[$section->section] = true;
            move_section_to($course, $section->section, $targetsection->section);
        }
        format_moointopics\local\chapterlib::sort_course_chapters($course->id);
        // Use section_state to return the section and activities updated state.
        $this->section_state($updates, $course, $ids, $targetsectionid);

        // All course sections can be renamed because of the resort.
        $allsections = $modinfo->get_section_info_all();
        foreach ($allsections as $section) {
            // Ignore the affected sections because they are already in the updates.
            if (isset($affectedsections[$section->section])) {
                continue;
            }
            $updates->add_section_put($section->id);
        }
        // The section order is at a course level.
        $updates->add_course_put();
    }
}
