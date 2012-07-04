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

/**
 * The assign module doesn't have a data generator, so this will do instead till one is made.
 */
class block_ajax_marking_mod_assign_generator extends phpunit_module_generator {

    /**
     * Gets DB module name.
     *
     * @return string
     */
    public function get_modulename() {
        return 'assign';
    }

    /**
     * Create a test module
     *
     * @param array|stdClass $record
     * @param array $options
     * @throws coding_exception
     * @return \stdClass activity record
     */
    public function create_instance($record = null, array $options = array()) {

        static $instancecount = 0;
        $instancecount++;

        if (empty($record->course)) {
            throw new coding_exception('Can\'t make a new assign module instance without a course');
        }

        $assign = new stdClass();
        $assign->name = 'New assignment '.$instancecount;
        $assign->intro = 'New assignment description ';
        $assign->introformat = 1;
        $assign->alwaysshowdescription = 1;
        $assign->nosubmissions = 0;
        $assign->preventlatesubmissions = 0;
        $assign->submissiondrafts = 0;
        $assign->sendnotifications = 0;
        $assign->sendlatenotifications = 0;
        $assign->duedate = 0;
        $assign->allowsubmissionsfromdate = 0;
        $assign->grade = 100;
        $assign->timemodified = time();

        $assign->assignsubmission_onlinetext_enabled = 1;
        $assign->assignsubmission_file_enabled = 0;
        $assign->assignsubmission_comments_enabled = 0;
        $assign->assignsubmission_feedback_enabled = 1;
        $assign->assignfeedback_comments_enabled = 1;
        $assign->assignfeedback_file_enabled = 1;

        $assign = (object)array_merge((array)$assign, (array)$record);
        $assign->coursemodule = $this->precreate_course_module($assign->course, $options);
        $current = new stdClass();
        $current->instance = 1;
        $current->coursemodule = false;
        $current->course = false;
        $course = new stdClass();
        $course->id = $record->course;
        $fakeform = new mod_assign_mod_form($current, null, null, $course);
        $assign->id = assign_add_instance($assign, $fakeform);
        return $this->post_add_instance($assign->id, $assign->coursemodule);
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

