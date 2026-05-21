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
 * Trigger the specified events
 *
 * @package     format_mooin4
 * @category    event
 * @copyright   2023 ISy TH Lübeck <dev.ild@th-luebeck.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    // Badges.
    [
        'eventname' => '\core\event\badge_awarded',
        'callback' => 'format_mooin4_observer::badge_awarded',
    ],
    [
        'eventname' => '\core\event\badge_viewed',
        'callback' => 'format_mooin4_observer::badge_viewed',
    ],
    // Ilddigitalcert.
    [
        'eventname' => '\mod_ilddigitalcert\event\certificate_issued',
        'callback' => 'format_mooin4_observer::ilddigital_certificate_issued',
    ],
    [
        'eventname' => '\mod_ilddigitalcert\event\certificate_viewed',
        'callback' => 'format_mooin4_observer::ilddigital_certificate_viewed',
    ],
    // Coursecertificate.
    [
        'eventname' => '\mod_coursecertificate\event\course_module_viewed',
        'callback' => 'format_mooin4_observer::course_certificate_viewed',
    ],
    [
        'eventname' => '\tool_certificate\event\certificate_issued',
        'callback' => 'format_mooin4_observer::course_certificate_issued',
    ],
    // Forum.
    [
        'eventname' => '\mod_forum\event\discussion_viewed',
        'callback' => 'format_mooin4_observer::discussion_viewed',
    ],
    // User.
    [
        'eventname' => '\core\event\user_updated',
        'callback' => 'format_mooin4_observer::user_updated',
    ],
    [
        'eventname' => '\core\event\user_created',
        'callback' => 'format_mooin4_observer::user_created',
    ],
    // Sections.
    [
        'eventname' => '\core\event\course_section_created',
        'callback' => 'format_mooin4_observer::course_section_created',
    ],
    // Course reset.
    [
        'eventname' => '\core\event\course_reset_ended',
        'callback' => 'format_mooin4_observer::course_reset_ended',
    ],
    // Course deletion: safety-net cleanup for any remaining format_mooin4 user preferences.
    // The primary cleanup is done in format_mooin4::delete_format_data(), but this observer
    // acts as a fallback for preferences that reference course-independent keys (e.g. badge/
    // certificate notifications) and for courses whose format may have been switched away
    // from mooin4 before deletion.
    [
        'eventname' => '\core\event\course_content_deleted',
        'callback' => 'format_mooin4_observer::course_content_deleted',
    ],
];

