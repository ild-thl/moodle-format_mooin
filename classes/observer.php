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
 *
 * @package     format_mooin
 * @category    event
 * @copyright   2023 ISy TH Lübeck <dev.ild@th-luebeck.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

defined('MOODLE_INTERNAL') || die();

class format_mooin_observer
{
    public static function badge_awarded(\core\event\badge_awarded $event) {
        // event parameters:
        // int expiredate: Badge expire timestamp.
        // int badgeissuedid: Badge issued ID.
        global $CFG;
        require_once($CFG->dirroot. '/course/format/mooin/locallib.php');
        $awardedtoid = $event->relateduserid;
        $badgeissuedid = $event->other['badgeissuedid'];
        set_new_badge($awardedtoid, $badgeissuedid);
    }

    public static function badge_viewed(\core\event\badge_viewed $event) {
        // event parameters:
        // int badgeid: the ID of the badge.
        // int badgehash: The UID of the awarded badge.
        global $CFG;
        require_once($CFG->dirroot. '/course/format/mooin/locallib.php');
        $viewedbyuserid = $event->userid;
        $badgehash = $event->other['badgehash'];
        unset_new_badge($viewedbyuserid, $badgehash);
    }

    public static function discussion_viewed(\mod_forum\event\discussion_viewed $event) {
        global $CFG;
        require_once($CFG->dirroot. '/course/format/mooin/locallib.php');
        $forumid = $event->contextinstanceid;
        $userid = $event->userid;
        $discussionid = $event->objectid;
        set_discussion_viewed($userid, $forumid, $discussionid);
    }
}