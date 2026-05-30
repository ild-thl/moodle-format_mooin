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
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $url = new moodle_url('/admin/course/resetindentation.php', ['format' => 'mooin4']);
    $link = html_writer::link($url, get_string('resetindentation', 'admin'));
    $settings->add(new admin_setting_configcheckbox(
        'format_mooin4/indentation',
        new lang_string('indentation', 'format_mooin4'),
        new lang_string('indentation_help', 'format_mooin4').'<br />'.$link,
        1
    ));

    // Add a headline "Include in Navigation".
    $settings->add(new admin_setting_heading(
        'format_mooin4/global_tile_visibility',
        get_string('global_tile_visibility', 'format_mooin4'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'format_mooin4/toggle_global_badge_visibility',
        new lang_string('toggle_global_badge_visibility', 'format_mooin4'), "",
        1
    ));
    $settings->add(new admin_setting_configcheckbox(
        'format_mooin4/toggle_global_certificate_visibility',
        new lang_string('toggle_global_certificate_visibility', 'format_mooin4'), "",
        1
    ));
    $settings->add(new admin_setting_configcheckbox(
        'format_mooin4/toggle_global_discussion_visibility',
        new lang_string('toggle_global_discussion_visibility', 'format_mooin4'), "",
        1
    ));
    $settings->add(new admin_setting_configcheckbox(
        'format_mooin4/toggle_global_userlist_visibility',
        new lang_string('toggle_global_userlist_visibility', 'format_mooin4'), "",
        1
    ));
    $settings->add(new admin_setting_configcheckbox(
        'format_mooin4/toggle_global_participant_list_for_students',
        new lang_string('toggle_global_participant_list_for_students', 'format_mooin4'), "",
        0
    ));

    // Add a headline "Include in Navigation".
    $settings->add(new admin_setting_heading(
        'format_mooin4/include_in_navigation',
        get_string('include_in_sidebar', 'format_mooin4'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'format_mooin4/news',
        new lang_string('news', 'format_mooin4'), "",
        1
    ));
    $settings->add(new admin_setting_configcheckbox(
        'format_mooin4/badges',
        new lang_string('badges', 'format_mooin4'), "",
        1
    ));
    $settings->add(new admin_setting_configcheckbox(
        'format_mooin4/certificates',
        new lang_string('certificates', 'format_mooin4'), "",
        1
    ));
    $settings->add(new admin_setting_configcheckbox(
        'format_mooin4/coursecompetencies',
        new lang_string('coursecompetencies', 'tool_lp'), "",
        0
    ));
    $settings->add(new admin_setting_configcheckbox(
        'format_mooin4/discussions',
        new lang_string('discussions', 'format_mooin4'), "",
        1
    ));
    $settings->add(new admin_setting_configcheckbox(
        'format_mooin4/participants',
        new lang_string('participants', 'format_mooin4'), "",
        1
    ));

    // Configure data for getting user coordinates.
    // Add a headline "Include in Navigation".
    $settings->add(new admin_setting_heading(
        'format_mooin4/geonamessettings',
        get_string('geonamessettings', 'format_mooin4'),
        ''
    ));

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

    // Add a headline "Placeholder Images".
    $settings->add(new admin_setting_heading(
        'format_mooin4/placeholder_images',
        get_string('placeholder_images_heading', 'format_mooin4'),
        get_string('placeholder_images_description', 'format_mooin4')
    ));

    // Placeholder image for badges.
    $settings->add(new admin_setting_configstoredfile(
        'format_mooin4/placeholder_badges',
        new lang_string('placeholder_badges', 'format_mooin4'),
        new lang_string('placeholder_badges_help', 'format_mooin4'),
        'placeholder_badges'
    ));

    // Placeholder image for certificates.
    $settings->add(new admin_setting_configstoredfile(
        'format_mooin4/placeholder_certificates',
        new lang_string('placeholder_certificates', 'format_mooin4'),
        new lang_string('placeholder_certificates_help', 'format_mooin4'),
        'placeholder_certificates'
    ));

    // Placeholder image for participants.
    $settings->add(new admin_setting_configstoredfile(
        'format_mooin4/placeholder_participants',
        new lang_string('placeholder_participants', 'format_mooin4'),
        new lang_string('placeholder_participants_help', 'format_mooin4'),
        'placeholder_participants'
    ));

}

if (isset($PAGE)) {
    $PAGE->requires->js(new moodle_url('/course/format/mooin4/format.js'));
}
