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

$observers = array(
    // Badges
    array(
        'eventname' => '\core\event\badge_awarded',
        'callback' => 'format_mooin4_observer::badge_awarded',
    ),
    array(
        'eventname' => '\core\event\badge_viewed',
        'callback' => 'format_mooin4_observer::badge_viewed',
    ),
    // ilddigitalcert
    array(
        'eventname' => '\mod_ilddigitalcert\event\certificate_issued',
        'callback' => 'format_mooin4_observer::ilddigital_certificate_issued',
    ),
    array(
        'eventname' => '\mod_ilddigitalcert\event\certificate_viewed',
        'callback' => 'format_mooin4_observer::ilddigital_certificate_viewed',
    ),
    // coursecertificate
    array(
        'eventname' => '\mod_coursecertificate\event\course_module_viewed',
        'callback' => 'format_mooin4_observer::course_certificate_viewed',
    ),
    array(
        'eventname' => '\tool_certificate\event\certificate_issued',
        'callback' => 'format_mooin4_observer::course_certificate_issued',
    ),
    // Forum
    array(
        'eventname' => '\mod_forum\event\discussion_viewed',
        'callback' => 'format_mooin4_observer::discussion_viewed'
    ),
    // User
    array(
        'eventname' => '\core\event\user_updated',
        'callback' => 'format_mooin4_observer::user_updated'
    ),
    array(
        'eventname' => '\core\event\user_created',
        'callback' => 'format_mooin4_observer::user_created'
    ),
    // Sections
    array(
        'eventname' => '\core\event\course_section_created',
        'callback' => 'format_mooin4_observer::section_created'
    )
);