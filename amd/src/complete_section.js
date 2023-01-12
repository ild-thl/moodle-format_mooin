/* eslint-disable camelcase */
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
 * Mark a Section (Lektion) in Chapter Card as complete.
 *
 * @module     format_mooin/complete_section
 * @copyright  2022 Perial Dupont Nguefack Kuaguim
 */
define(['jquery', 'core/notification'], function($, Notification) {
    Y.log('Test in complete_section');
    // Y.log($('.bottom_complete'));
    var section_number = [];
    $('.bottom_complete').click(function(event) {
        section_number = event.currentTarget.id.substring(19); // .explode('-', event.currentTarget.id);
        Y.log(section_number);
        var value = $("#mooin4ection" + section_number);
        value.css('width', '100%');
        var data = {};
        data.section = section_number;
        $.ajax({
            type: 'POST',
            url: 'complete_section.php',
            data: data,
            success: (data) => {
                Y.log(data);
                Notification.addNotification({
                    message: ' You have successfully complete this section',
                    type: 'success'
                });
                // Window.location.reload();
            },
        });
    });
});