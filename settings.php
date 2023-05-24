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
 * Settings for format_mooin4
 *
 * @package    format_mooin4
 * @copyright  2023 TH Lübeck ISy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configcheckbox(
        'format_mooin4/forcetrackforums',
        get_string('configlabel_forcetrackforums', 'format_mooin4'),
        get_string('configdesc_forcetrackforums', 'format_mooin4', $CFG->wwwroot),
        1)
    );
    $settings->add(new admin_setting_configcheckbox(
        'format_mooin4/forcecompletiondefault',
        get_string('configlabel_forcecompletiondefault', 'format_mooin4'),
        get_string('configdesc_forcecompletiondefault', 'format_mooin4', $CFG->wwwroot),
        1)
    );
    $settings->add(new admin_setting_configtext(
        'format_mooin4/geonamesapi_url',
        get_string('configlabel_geonamesapi_url', 'format_mooin4'),
        get_string('configdesc_geonamesapi_url', 'format_mooin4'), 
        'http://api.geonames.org'
    ));
    $settings->add(new admin_setting_configtext(
        'format_mooin4/geonamesapi_username',
        get_string('configlabel_geonamesapi_username', 'format_mooin4'),
        get_string('configdesc_geonamesapi_username', 'format_mooin4'), 
        'mooin4'
    ));
}