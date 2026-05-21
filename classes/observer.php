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
 * Event observer for the mooin4 course format.
 *
 * @package     format_mooin4
 * @category    event
 * @copyright   2023 ISy TH Lübeck <dev.ild@th-luebeck.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use format_mooin4\local\utils;

/**
 * Observer class for handling various Moodle events in the mooin4 course format.
 *
 * This class provides static methods to handle events such as badge awards,
 * certificate issuance, forum discussions, user updates, and course resets.
 */
class format_mooin4_observer {
    /**
     * Triggered when a badge is awarded.
     *
     * @param \core\event\badge_awarded $event
     */
    public static function badge_awarded(\core\event\badge_awarded $event) {
        // Event parameters:
        // int expiredate: Badge expire timestamp.
        // int badgeissuedid: Badge issued ID.
        $awardedtoid = $event->relateduserid;
        $badgeissuedid = $event->other['badgeissuedid'];
        utils::set_new_badge($awardedtoid, $badgeissuedid);
    }

    /**
     * Triggered when a badge is viewed.
     *
     * @param \core\event\badge_viewed $event
     */
    public static function badge_viewed(\core\event\badge_viewed $event) {
        // Event parameters:
        // int badgeid: The ID of the badge.
        // int badgehash: The UID of the awarded badge.
        $viewedbyuserid = $event->userid;
        $badgehash = $event->other['badgehash'];
        utils::unset_new_badge($viewedbyuserid, $badgehash);
    }

    /**
     * Triggered when an ILD digital certificate is issued.
     *
     * @param \mod_ilddigitalcert\event\certificate_issued $event
     */
    public static function ilddigital_certificate_issued(\mod_ilddigitalcert\event\certificate_issued $event) {
        $awardedtoid = $event->relateduserid;
        $issuedid = $event->objectid;
        utils::set_new_certificate($awardedtoid, $issuedid, 'ilddigitalcert');
    }

    /**
     * Triggered when an ILD digital certificate is viewed.
     *
     * @param \mod_ilddigitalcert\event\certificate_viewed $event
     */
    public static function ilddigital_certificate_viewed(\mod_ilddigitalcert\event\certificate_viewed $event) {
        $viewedbyuserid = $event->userid;
        $issuedid = $event->objectid;
        utils::unset_new_certificate($viewedbyuserid, $issuedid, 'ilddigitalcert');
    }

    /**
     * Triggered when a course certificate is issued.
     *
     * @param \tool_certificate\event\certificate_issued $event
     */
    public static function course_certificate_issued(\tool_certificate\event\certificate_issued $event) {
        $awardedtoid = $event->relateduserid;
        $issuedid = $event->objectid;
        utils::set_new_certificate($awardedtoid, $issuedid, 'coursecertificate');
    }

    /**
     * Triggered when a course certificate is viewed.
     *
     * @param \mod_coursecertificate\event\course_module_viewed $event
     */
    public static function course_certificate_viewed(\mod_coursecertificate\event\course_module_viewed $event) {
        global $DB;
        $viewedbyuserid = $event->userid;
        $coursecertificateid = $event->objectid;
        if ($coursecertificate = $DB->get_record('coursecertificate', ['id' => $coursecertificateid])) {
            if ($coursecertificateissue = $DB->get_record(
                'tool_certificate_issues',
                [
                    'userid' => $viewedbyuserid,
                    'templateid' => $coursecertificate->template,
                    'courseid' => $coursecertificate->course,
                ]
            )) {
                utils::unset_new_certificate($viewedbyuserid, $coursecertificateissue->id, 'coursecertificate');
            }
        }
    }

    /**
     * Triggered when a forum discussion is viewed.
     *
     * @param \mod_forum\event\discussion_viewed $event
     */
    public static function discussion_viewed(\mod_forum\event\discussion_viewed $event) {
        $forumid = $event->contextinstanceid;
        $userid = $event->userid;
        $discussionid = $event->objectid;
        utils::set_discussion_viewed($userid, $forumid, $discussionid);
    }

    /**
     * Triggered when a user is updated.
     *
     * @param \core\event\user_updated $event
     */
    public static function user_updated(\core\event\user_updated $event) {
        global $DB;
        $userid = $event->objectid;
        if ($user = $DB->get_record('user', ['id' => $userid])) {
            if ($coordinates = utils::get_user_coordinates($user)) {
                utils::set_user_coordinates($userid, $coordinates->lat, $coordinates->lng);
            }
        }
    }

    /**
     * Triggered when a user is created.
     *
     * @param \core\event\user_created $event
     */
    public static function user_created(\core\event\user_created $event) {
        global $DB;
        $userid = $event->objectid;
        if ($user = $DB->get_record('user', ['id' => $userid])) {
            if ($coordinates = utils::get_user_coordinates($user)) {
                utils::set_user_coordinates($userid, $coordinates->lat, $coordinates->lng);
            }
        }
    }

    /**
     * Triggered when a course section is created.
     *
     * @param \core\event\course_section_created $event
     */
    public static function course_section_created(\core\event\course_section_created $event) {
        global $DB;

        $courseid = $event->courseid;

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

        if ($course->format == 'mooin4') {
            $newsection = new stdClass();
            $newsection->id = $event->objectid;
            $newsection->name = get_string('new_lesson', 'format_mooin4');

            if ($createdsection = $DB->get_record('course_sections', ['id' => $event->objectid])) {
                if ($createdsection->section == 0) {
                    $newsection->name = get_string('lesson', 'format_mooin4') . ' 0';
                }
            }

            $DB->update_record('course_sections', $newsection);
        }
    }

    /**
     * Handle course reset ended event.
     * Deletes all mooin4-related user preferences for the reset course.
     *
     * @param \core\event\course_reset_ended $event
     */
    public static function course_reset_ended(\core\event\course_reset_ended $event) {
        global $DB;

        $courseid = $event->courseid;
        $course = $DB->get_record('course', ['id' => $courseid]);

        // Only process if the course uses mooin4 format.
        if (!$course || $course->format !== 'mooin4') {
            return;
        }

        // Get all section IDs for this course.
        $sections = $DB->get_records('course_sections', ['course' => $courseid], '', 'id');
        $sectionids = array_keys($sections);

        // Get all course module IDs for this course.
        $cms = $DB->get_records('course_modules', ['course' => $courseid], '', 'id, instance');

        // Delete user preferences related to this course.
        // 1. Last section visited in course.
        $DB->delete_records_select('user_preferences',
            "name = :name",
            ['name' => 'format_mooin4_last_section_in_course_' . $courseid]
        );

        // 2. Section completed status for each section.
        foreach ($sectionids as $sectionid) {
            $DB->delete_records_select('user_preferences',
                "name = :name",
                ['name' => 'format_mooin4_section_completed_' . $sectionid]
            );
            $DB->delete_records_select('user_preferences',
                "name = :name",
                ['name' => 'format_mooin4_hide_modal_for_section_' . $sectionid]
            );
        }

        // 3. H5P progress for each course module.
        foreach ($cms as $cm) {
            $DB->delete_records_select('user_preferences',
                "name = :name",
                ['name' => 'format_mooin4_hvp_progress_cmid_' . $cm->id]
            );
            $DB->delete_records_select('user_preferences',
                "name = :name",
                ['name' => 'format_mooin4_hvp_progress_' . $cm->instance]
            );
        }

        // 4. Delete all mooin4 preferences using LIKE patterns for this course.
        // This catches any remaining preferences that might be tied to course-specific data.
        $likepatterns = [
            'format_mooin4_last_section_in_course_' . $courseid,
        ];

        foreach ($likepatterns as $pattern) {
            $DB->delete_records_select('user_preferences',
                "name = :name",
                ['name' => $pattern]
            );
        }
    }

    /**
     * Safety-net handler for course_content_deleted event.
     *
     * Acts as a fallback to clean up any remaining format_mooin4 user preferences
     * after a course is deleted. The primary cleanup is done in
     * format_mooin4::delete_format_data(), which runs while sections and modules
     * still exist. This handler catches anything left over, such as:
     *   - Badge / certificate notification preferences (not section-scoped)
     *   - Any prefs from a course whose format was changed before deletion
     *
     * Uses a LIKE query, so it is intentionally broad and runs only once per
     * course deletion (not per-user).
     *
     * @param \core\event\course_content_deleted $event
     */
    public static function course_content_deleted(\core\event\course_content_deleted $event) {
        global $DB;

        $courseid = $event->objectid;

        // Remove any remaining format_mooin4_* preferences that embed the course ID directly.
        $DB->delete_records_select(
            'user_preferences',
            $DB->sql_like('name', ':pattern'),
            ['pattern' => 'format_mooin4_%_' . $courseid]
        );

        // Also catch the last-section pref (format: format_mooin4_last_section_in_course_<courseid>).
        $DB->delete_records('user_preferences', [
            'name' => 'format_mooin4_last_section_in_course_' . $courseid,
        ]);
    }

}
