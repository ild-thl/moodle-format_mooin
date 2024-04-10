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

namespace format_moointopics\output;

use core_courseformat\output\section_renderer;
use moodle_page;
use core_courseformat\base as course_format;
use context_course;
use moodle_url;

/**
 * Basic renderer for topics format.
 *
 * @copyright 2012 Dan Poltawski
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends section_renderer {

    /**
     * Constructor method, calls the parent constructor.
     *
     * @param moodle_page $page
     * @param string $target one of rendering target constants
     */
    public function __construct(moodle_page $page, $target) {
        parent::__construct($page, $target);

        // Since format_moointopics_renderer::section_edit_control_items() only displays the 'Highlight' control
        // when editing mode is on we need to be sure that the link 'Turn editing mode on' is available for a user
        // who does not have any other managing capability.
        $page->set_other_editing_capability('moodle/course:setcurrentsection');
    }

    /**
     * Generate the section title, wraps it in a link to the section page if page is to be displayed on a separate page.
     *
     * @param section_info|stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section));
    }

    /**
     * Generate the section title to be displayed on the section page, without a link.
     *
     * @param section_info|stdClass $section The course_section entry from DB
     * @param int|stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title_without_link($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section, false));
    }

    /**
     * Get the course index drawer with placeholder.
     *
     * The default course index is loaded after the page is ready. Format plugins can override
     * this method to provide an alternative course index.
     *
     * If the format is not compatible with the course index, this method will return an empty string.
     *
     * @param course_format $format the course format
     * @return String the course index HTML.
     */
    public function course_index_drawer(course_format $format): ?String {
        global $DB;

        if ($format->uses_course_index()) {
            include_course_editor($format);
            $course = $format->get_course();

            $overview = new moodle_url('/course/view.php', array('id' => $course->id));
            $badgesUrl = new moodle_url('/course/format/moointopics/badges.php', array('id' => $course->id));
            $certificatesUrl = new moodle_url('/course/format/moointopics/certificates.php', array('id' => $course->id));
            $discussionsUrl = new moodle_url('/course/format/moointopics/all_discussionforums.php', array('id' => $course->id));
            $participantsUrl = new moodle_url('/course/format/moointopics/participants.php', array('id' => $course->id));

            if ($forum = $DB->get_record('forum', array('course' => $course->id, 'type' => 'news'))) {
                if ($module = $DB->get_record('modules', array('name' => 'forum'))) {
                    if($cm = $DB->get_record('course_modules', array('module' => $module->id, 'instance'=>$forum->id))){
                       $newsforumUrl = new moodle_url('/mod/forum/view.php', array('id' => $cm->id));
                    }
                }
            }
            $data = [
                'coursename' => $course->shortname,
                'overview' => ['url' => $overview, 'active' => $this->check_if_active($overview)],
                'newsforum' => ['url' => $newsforumUrl, 'active' => $this->check_if_active($newsforumUrl)],
                'badges' => ['url' => $badgesUrl, 'active' => $this->check_if_active($badgesUrl)],
                'certificates' => ['url' => $certificatesUrl, 'active' => $this->check_if_active($certificatesUrl)],
                'discussions' => ['url' => $discussionsUrl, 'active' => $this->check_if_active($discussionsUrl)],
                'participants' => ['url' => $participantsUrl, 'active' => $this->check_if_active($participantsUrl)],
            ];
            return $this->render_from_template('format_moointopics/local/courseindex/drawer', $data);
        }
        return '';
    }

    function check_if_active($url) {
        global $PAGE;
        if ($PAGE->url->compare($url, URL_MATCH_BASE)) {
            return true;
        } else {
            return false;
        }
    }

    function course_section_add_cm_control($course, $section, $sectionreturn = null, $displayoptions = array()) {
        $singlesection = course_get_format($course)->get_section_number();
        if ($singlesection) {
            if (
                !has_capability('moodle/course:manageactivities', context_course::instance($course->id))
                || !$this->page->user_is_editing()
            ) {
                return '';
            }

            $data = [
                'sectionid' => $section,
                'sectionreturn' => $sectionreturn
            ];
            $ajaxcontrol = $this->render_from_template('course/activitychooserbutton', $data);

            // Load the JS for the modal.
            $this->course_activitychooser($course->id);

            return $ajaxcontrol;
        }
    }
}
