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
define(['jquery'], function($) {
    Y.log('Test in complete_section');
    // Y.log($('.bottom_complete'));
    var section_number = [];
    var section_inside_course = [];
    $('.btn_comp').click(function(event) {
        // Y.log(event);
        section_number = event.currentTarget.id.substring(19); // .explode('-', event.currentTarget.id);
        section_inside_course = event.currentTarget.name.split('-');
        // Y.log(section_inside_course[1]);
       // Var percentage = 100;
        // Y.log(section_number);
        // Var value = $("#mooin4ection" + section_number);
        // Var another_value = $('#mooin4ection-text-' + section_number);
        // Var btn_bottom = $('.bottom_complete-' + section_number);
        var course_id = event.currentTarget.classList[3].split('-');
        // Var disable_btn_button = $('#id_bottom_complete-' + section_number);
        // Var show_percentage_text = String(percentage + '% der Lektion bearbeitet');

        var value = String('mooin4ection' + section_number);
        var another_value = String('mooin4ection-text-' + section_number);
        var disable_btn_button = String('id_bottom_complete-' + section_number);
        var percentage = '100%';
        var percentage_text = String(percentage + ' der Lektion bearbeitet');


        var dataSend = {};
        dataSend.section = section_number;
        dataSend.percentage = percentage;
        dataSend.section_inside_course = section_inside_course[1];
        dataSend.course_id = course_id[1];
        // Y.log(dataSend);
        $.ajax({
            type: 'POST',
            url: 'format/mooin/complete_section.php', // Format/mooin/
            data: dataSend,
            success: (dataSend) => {
                Y.log(dataSend);
                /* Notification.addNotification({
                    message: ' You have successfully complete this section',
                    type: 'success'
                }); */
                window.location.reload();
            },
            error: function(xhr, status, error) {
                Y.log(error);
            }
        }).done(function() {
            $('#' + value, window.parent.document).css('width', percentage);
            $('#' + another_value, window.parent.document).html(percentage_text);
            $('#' + disable_btn_button, window.parent.document).css('cursor', 'unset');
        });
    });
});