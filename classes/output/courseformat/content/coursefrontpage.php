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

namespace format_mooin4\output\courseformat\content;


use renderable;
use core_courseformat\base as course_format;
use format_mooin4\output\courseformat\content\frontpage\header;
use format_mooin4\output\courseformat\content\frontpage\news_section;
use format_mooin4\output\courseformat\content\frontpage\courseprogress;
use format_mooin4\output\courseformat\content\frontpage\badges;
use format_mooin4\output\courseformat\content\frontpage\certificates;
use format_mooin4\output\courseformat\content\frontpage\discussions;
use format_mooin4\output\courseformat\content\frontpage\participants;
use context_course;
use format_mooin4\local\utils as utils;



/**
 * Base class to render the course frontpage.
 *
 * @package   format_mooin4
 * @copyright 2023 ISy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coursefrontpage implements renderable {

    /** @var course_format the course format class */
    private $format;

    /** @var header the course frontpage header class */
    private $header;

    /** @var news_section the course frontpage news section class */
    private $newssection;

    /** @var courseprogress the course frontpage progress class */
    private $courseprogress;

    /** @var badges the course frontpage badges class */
    private $badges;

    /** @var certificates the course frontpage certificates class */
    private $certificates;

    /** @var discussions the course frontpage discussions class */
    private $discussions;

    /** @var participants the course frontpage participants class */
    private $participants;


    /**
     * Constructor.
     *
     * @param course_format $format the course format
     */
    public function __construct(course_format $format) {
        $this->format = $format;
        $this->header = new header($format);
        $this->newssection = new news_section($format);
        $this->courseprogress = new courseprogress($format);
        $this->badges = new badges($format);
        $this->certificates = new certificates($format);
        $this->discussions = new discussions($format);
        $this->participants = new participants($format);
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output typically, the renderer that's calling this function
     * @return \stdClass data context for a mustache template
     */
    public function export_for_template(\renderer_base $output) {
        $format = $this->format;
        $header = $this->header;
        $newssection = $this->newssection;
        $courseprogress = $this->courseprogress;
        $badges = $this->badges;
        $certificates = $this->certificates;
        $discussions = $this->discussions;
        $participants = $this->participants;
        $course = $format->get_course();

        $data = (object)[
            'header' => $header->export_for_template($output),
            'coursename' => $course->fullname,
            'news_section' => $newssection->export_for_template($output),
            'courseprogress' => $courseprogress->export_for_template($output),
            'badges' => $badges->export_for_template($output),
            'certificates' => $certificates->export_for_template($output),
            'discussions' => $discussions->export_for_template($output),
            'participants' => $participants->export_for_template($output),
            'unenrolurl' => utils::get_unenrol_url($course->id),
        ];

        require_once(__DIR__ . '/../../../../lib.php');
        $courseid = $course->id;

        // Handle data for course element visibility.
        if (get_toggle_newssection_visibility($courseid) == 1) {
            $data->newssection_visibility = true;
        } else {
            $data->newssection_visibility = false;
        }

        if (get_toggle_progressbar_visibility($courseid) == 1) {
            $data->progressbar_visibility = true;
        } else {
            $data->progressbar_visibility = false;
        }

        if (
            get_toggle_discussion_visibility($courseid) == 1
            && get_config('format_mooin4', "toggle_global_discussion_visibility") == 1
        ) {
            $data->discussion_visibility = true;
        } else {
            $data->discussion_visibility = false;
        }

        if (
            get_toggle_userlist_visibility($courseid) == 1
            && get_config('format_mooin4', "toggle_global_userlist_visibility") == 1
        ) {
            $data->userlist_visibility = true;
        } else {
            $data->userlist_visibility = false;
        }

        if (
            (get_toggle_discussion_visibility($courseid) == 0
                || get_config('format_mooin4', "toggle_global_discussion_visibility") == 0)
            && (get_toggle_userlist_visibility($courseid) == 0
                || get_config('format_mooin4', "toggle_global_userlist_visibility") == 0)
        ) {
            $data->community_visibility = false;
        } else {
            $data->community_visibility = true;
        }

        if (
            get_toggle_badge_visibility($courseid) == 1
            && get_config('format_mooin4', "toggle_global_badge_visibility") == 1
        ) {
            $data->badge_visibility = true;
        } else {
            $data->badge_visibility = false;
        }

        if (
            get_toggle_certificate_visibility($courseid) == 1
            && get_config('format_mooin4', "toggle_global_certificate_visibility") == 1
        ) {
            $data->certificate_visibility = true;
        } else {
            $data->certificate_visibility = false;
        }

        if (
            (get_toggle_badge_visibility($courseid) == 1
                && get_config('format_mooin4', "toggle_global_badge_visibility") == 1)
            && (get_toggle_certificate_visibility($courseid) == 1
                && get_config('format_mooin4', "toggle_global_certificate_visibility") == 1)
            && (get_toggle_discussion_visibility($courseid) == 1
                && get_config('format_mooin4', "toggle_global_discussion_visibility") == 1)
        ) {
            $data->badge_cert_visibility = true;
        } else {
            $data->badge_cert_visibility = false;
        }

        if (
            (get_toggle_badge_visibility($courseid) == 0
                || get_config('format_mooin4', "toggle_global_badge_visibility") == 0)
            && (get_toggle_certificate_visibility($courseid) == 0
                || get_config('format_mooin4', "toggle_global_certificate_visibility") == 0)
        ) {
            $data->badge_cert_hide = true;
        }

        if (
            (get_toggle_badge_visibility($courseid) == 0
                || get_config('format_mooin4', "toggle_global_badge_visibility") == 0)
            && (get_toggle_certificate_visibility($courseid) == 0
                || get_config('format_mooin4', "toggle_global_certificate_visibility") == 0)
            && (get_toggle_discussion_visibility($courseid) == 0
                || get_config('format_mooin4', "toggle_global_discussion_visibility") == 0)
            && (get_toggle_userlist_visibility($courseid) == 0
                || get_config('format_mooin4', "toggle_global_userlist_visibility") == 0)
        ) {
            $data->hide_coursefrontpage_side = true;
        }

        $coursecontext = context_course::instance($course->id);
        if (has_capability('moodle/course:update', $coursecontext)) {
            $data->has_capability = true;
        }

        return $data;
    }
}
