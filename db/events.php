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
 * @package     format_mooin
 * @category    event
 * @copyright   2023 ISy TH Lübeck <dev.ild@th-luebeck.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

defined('MOODLE_INTERNAL') || die();

$observers = array(
    array(
        'eventname' => '\core\event\badge_awarded',
        'callback' => 'format_mooin_observer::badge_awarded',
    ),
    array(
        'eventname' => '\core\event\badge_viewed',
        'callback' => 'format_mooin_observer::badge_viewed',
    ),
    array(
        'eventname' => '\mod_ilddigitalcert\event\certificate_issued',
        'callback' => 'format_mooin_observer::ilddigital_certificate_issued',
    ),
    array(
        'eventname' => '\mod_ilddigitalcert\event\certificate_viewed',
        'callback' => 'format_mooin_observer::ilddigital_certificate_viewed',
    ),
    array(
        'eventname' => '\mod_forum\event\discussion_viewed',
        'callback' => 'format_mooin_observer::discussion_viewed'
    )
);