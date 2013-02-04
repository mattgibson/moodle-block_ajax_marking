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
 * The assign module doesn't have a data generator, so this will do instead till one is made.
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2012 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot.'/lib/phpunit/classes/module_generator.php');
require_once($CFG->dirroot.'/mod/assign/mod_form.php');
require_once($CFG->dirroot.'/mod/assign/tests/generator/lib.php');

/**
 * The assign module doesn't have a data generator, so this will do instead till one is made.
 */
class block_ajax_marking_mod_assign_generator extends mod_assign_generator {

    /**
     * Gets DB module name.
     *
     * @return string
     */
    public function get_modulename() {
        return 'assign';
    }


    /**
     * Makes a new submission for the assign module.
     *
     * @param stdClass $record
     * @throws coding_exception
     * @return void
     */
    public function create_assign_submission($record) {

        global $DB;

        if (!isset($record->assignment)) {
            throw new coding_exception('Must have assign id to make new assign submission');
        }
        if (!isset($record->userid)) {
            throw new coding_exception('Must have assign id to make new assign submission');
        }

        $submission = new stdClass();
        $submission->timemodified = time();
        $submission->timecreated = time();
        $submission->status = 'submitted';

        $submission = (object)array_merge((array)$submission, (array)$record);

        $DB->insert_record('assign_submission', $submission);
    }
}

