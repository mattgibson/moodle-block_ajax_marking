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
 * Class file for the Assignment grading functions
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2008 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot.'/mod/assignment/lib.php');

/**
 * Allows us to alter the form to have no 'revert to draft' button, and add an extra 'save and revert to draft'
 * button for advanced upload assignment types.
 */
class block_ajax_marking_assignment_form extends assignment_grading_form {

    /**
     * Adds the 'save and revert to draft' button.
     */
    public function add_action_buttons($cancel = true, $submitlabel = null) {

        $mform =& $this->_form;

        $buttonarray = array();
        $buttonarray[] = &
        $mform->createElement('submit', 'submitbutton', get_string('savechanges'));
        if ($this->_customdata->assignment->assignmenttype == 'upload') {
            // Extra button to save and revert.
            $buttonarray[] = &$mform->createElement('submit',
                                                  'revertbutton',
                                                  get_string('saveandrevert',
                                                             'block_ajax_marking'));
        }
        $buttonarray[] = &$mform->createElement('cancel');

        $mform->addGroup($buttonarray, 'grading_buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('grading_buttonar');
        $mform->setType('grading_buttonar', PARAM_RAW);
    }

}
