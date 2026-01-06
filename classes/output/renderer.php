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

/**
 * Renderer for format_mooin4.
 *
 * @package   format_mooin4
 * @copyright 2012 Dan Poltawski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mooin4\output;



use core_courseformat\output\section_renderer;
use moodle_page;
use core_courseformat\base as course_format;
use context_course;
use moodle_url;
use format_mooin4\local\utils as utils;

/**
 * Basic renderer for topics format.
 *
 * @package   format_mooin4
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

        // Since format_mooin4_renderer::section_edit_control_items() only displays the 'Highlight' control
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

            $overview = new moodle_url('/course/view.php', ['id' => $course->id]);
            $badgesurl = new moodle_url('/course/format/mooin4/badges.php', ['id' => $course->id]);
            $certificatesurl = new moodle_url('/course/format/mooin4/certificates.php', ['id' => $course->id]);
            $discussionsurl = new moodle_url('/course/format/mooin4/all_discussionforums.php', ['id' => $course->id]);
            $participantsurl = new moodle_url('/course/format/mooin4/participants.php', ['id' => $course->id]);
            $coursecompetenciesurl = new moodle_url('/admin/tool/lp/coursecompetencies.php', [
                'courseid' => $course->id,
                'mod' => 0,
            ]);

            $newsforumurl = null;

            if ($forum = $DB->get_record('forum', ['course' => $course->id, 'type' => 'news'])) {
                if ($module = $DB->get_record('modules', ['name' => 'forum'])) {
                    if ($cm = $DB->get_record('course_modules', ['module' => $module->id, 'instance' => $forum->id])) {
                        $newsforumurl = new moodle_url('/mod/forum/view.php', ['id' => $cm->id]);
                    }
                }
            }

            $data = [
                'coursename' => $course->shortname,
                'overview' => ['url' => $overview, 'active' => $this->check_if_active($overview)],
                'unenrolurl' => utils::get_unenrol_url($course->id),
            ];

            // Check course settings.
            require_once(__DIR__ . '/../../../../lib.php');
            $courseid = $course->id;
            if (
                get_config('format_mooin4', "badges")
                && get_toggle_badge_visibility($courseid) === 1
                && get_config('format_mooin4', "toggle_global_badge_visibility")
            ) {
                $data['badges'] = [
                    'url' => $badgesurl,
                    'active' => $this->check_if_active($badgesurl),
                ];
            }

            if (
                get_config('format_mooin4', "certificates")
                && get_toggle_certificate_visibility($courseid) === 1
                && get_config('format_mooin4', "toggle_global_certificate_visibility")
            ) {
                $data['certificates'] = [
                    'url' => $certificatesurl,
                    'active' => $this->check_if_active($certificatesurl),
                ];
            }

            if (
                get_config('format_mooin4', "discussions")
                && get_toggle_discussion_visibility($courseid) === 1
                && get_config('format_mooin4', "toggle_global_discussion_visibility")
            ) {
                $data['discussions'] = [
                    'url' => $discussionsurl,
                    'active' => $this->check_if_active($discussionsurl),
                ];
            }

            $context = context_course::instance($course->id);
            $hascapability = has_capability('moodle/course:viewparticipants', $context);
            if (
                $hascapability
                && get_config('format_mooin4', 'participants')
                && get_toggle_userlist_visibility($courseid)
                && get_config('format_mooin4', 'toggle_global_userlist_visibility')
            ) {
                $data['participants'] = [
                    'url' => $participantsurl,
                    'active' => $this->check_if_active($participantsurl),
                ];
            }

            if (
                !is_null($newsforumurl)
                && get_config('format_mooin4', 'news')
                && get_toggle_newssection_visibility($courseid) === 1
            ) {
                $data['newsforum'] = [
                    'url' => $newsforumurl,
                    'active' => $this->check_if_active($newsforumurl),
                ];
            }
            return $this->render_from_template('format_mooin4/local/courseindex/drawer', $data);
        }
        return '';
    }

    /**
     * Checks if the current page URL matches the given URL.
     *
     * @param moodle_url|null $url The URL to check against.
     * @return bool True if active, false otherwise.
     */
    public function check_if_active(?moodle_url $url): bool {
        if ($url !== null) {
            if ($this->page->url->compare($url, URL_MATCH_EXACT)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generate the add control for a section.
     *
     * @param stdClass $course The course entry from DB
     * @param section_info|stdClass $section The course_section entry from DB
     * @param int|null $sectionreturn The section to return to regarding the section edit control
     * @param array $displayoptions Optional display options
     * @return string HTML to output.
     */
    public function course_section_add_cm_control($course, $section, $sectionreturn = null, $displayoptions = []) {
        if (
            !has_capability('moodle/course:manageactivities', context_course::instance($course->id))
            || !$this->page->user_is_editing()
        ) {
            return '';
        }

        $data = [
            'sectionid' => $section,
            'sectionreturn' => $sectionreturn,
        ];
        $ajaxcontrol = $this->render_from_template('course/activitychooserbutton', $data);

        // Load the JS for the modal.
        $this->course_activitychooser($course->id);

        return $ajaxcontrol;
    }
}
