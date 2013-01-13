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
 * This file contains all the javascript for the assign module extension
 * to the AJAX Marking block.
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2012 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Possibly getting to this point before the main block js file is included.
if (typeof M === 'undefined') {
    var M = {};
}
if (typeof(M.block_ajax_marking) === 'undefined') {
    M.block_ajax_marking = {};
}

/**
 * Specifies the size of the popup.
 */
M.block_ajax_marking.coursework = (function() {
    "use strict";

    return {
        pop_up_arguments : function() {
            return 'menubar=0,location=0,scrollbars,resizable,width=900,height=630';
        }
    };
}());
