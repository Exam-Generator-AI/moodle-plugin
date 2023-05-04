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
 * Code for checking questions generation state.
 *
 * @package     local_aiquestions
 * @category    admin
 * @copyright   2023 Ruthy Salomon <ruthy.salomon@gmail.com> , Yedidia Klein <yedidia@openapp.co.il>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/templates'], function ($, Ajax, Templates) {
    // Load the state of the questions generation every 20 seconds.
    var intervalId = setInterval(function () {
        checkState(intervalId);
    }, 20000);

    function checkState(intervalId) {
        var userid = $("#userid")[0].outerText;
        var uniqid = $("#uniqid")[0].outerText;
        data = { userid: userid, uniqid: uniqid };
        var promises = Ajax.call([{
            methodname: 'local_aiquestions_check_state',
            args: {
                userid: userid,
                uniqid: uniqid
            }
        }]);
        promises[0].then(function(showSuccess) {
            if (showSuccess[0].success != '') {
                Templates.render('local_aiquestions/success', { success: showSuccess[0].success }).then(function(html) {
                    $("#success").html(html);
                });
                // Stop checking the state while questions are ready.
                clearInterval(intervalId);
            }
        });
    };
});