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
 * @module     format_mooin/show_popup
 * @copyright  2022 Perial Dupont Nguefack Kuaguim
 */
define(['jquery'], function($) {
  // $(document).ready(function() {
    // Get the modal
    Y.log('Show Popup');
    var modal = $('.modal_style');
    // Get the <span> element that closes the modal
    var span = $('.close_style');

    // Get the button schliessen
    var btn = $('.modal_button_close');
    // When the page loads, display the modal
    // Modal.modal('show');
    // When the user clicks on <span> (x), close the modal
    function closeModal() {
      $("#myModal").hide();
    };
    span.click(closeModal);
    btn.click(closeModal);
    // When the user clicks anywhere outside of the modal, close it
    $(window).click(function(event) {
      if (event.target == modal[0]) {
        $("#myModal").hide();
      }
    });
  // });
  });
