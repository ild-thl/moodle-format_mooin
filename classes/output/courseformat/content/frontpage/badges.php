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

namespace format_mooin4\output\courseformat\content\frontpage;

use renderable;
use core_courseformat\base as course_format;
use moodle_url;
use context_course;
use format_mooin4\local\utils as utils;

/**
 * Base class to render the course news section.
 *
 * @package   format_mooin4
 * @copyright 2023 ISy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class badges implements renderable {

    /** @var course_format the course format class */
    private $format;


    /**
     * Constructor.
     *
     * @param course_format $format the course format
     */
    public function __construct(course_format $format) {
        $this->format = $format;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output typically, the renderer that's calling this function
     * @return \stdClass data context for a mustache template
     */
    public function export_for_template(\renderer_base $output) {
        global $DB, $USER;

        $course = $this->format->get_course();

        $badges = null;
        ob_start();
        $badges .= utils::get_user_and_availbale_badges($USER->id, $course->id);
        $badges .= ob_get_contents();
        ob_end_clean();

        if (count(utils::get_badge_records($course->id, null, null, null)) > 3) {
            $otherbadges = count(utils::get_badge_records($course->id, null, null, null)) - 3;
        } else {
            $otherbadges = false;
        }
        $badgescountmobile = utils::count_unviewed_badges($USER->id, $course->id);
        $newbadge = $badgescountmobile > 0;

        $data = (object)[
            'badgesList' => $badges,
            'otherBadges' => $otherbadges,
            'badgesUrl' => new moodle_url('/course/format/mooin4/badges.php', ['id' => $course->id]),
            'new_badge' => $newbadge,
            'badges_number' => $badgescountmobile,
        ];

        $coursecontext = context_course::instance($course->id);
        if (has_capability('moodle/course:update', $coursecontext)) {
            $managebadgesurl = new moodle_url('/badges/view.php', ['type' => '2', 'id' => $course->id]);
            $data->manage_badges_url = $managebadgesurl;
        }
        return $data;
    }
}
