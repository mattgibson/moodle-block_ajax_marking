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
 * The idea here is to make sure the grading popups render OK.
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2013 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/blocks/ajax_marking/modules/assign/block_ajax_marking_assign.class.php');

/**
 * Initially, this just has basic smoke tests looking for php errors in the rendering code that API
 * changes may have caused.
 */
class grading_popups_test extends advanced_testcase {

    /**
     * @var stdClass
     */
    protected $course;

    /**
     * @var array
     */
    public $students;
    public $teachers;

    /**
     * Prepare test environment with a course and a teacher.
     *
     * @todo DRY this up - it is copy/pasted from the nodes_builder_test class.
     */
    public function setUp() {
        global $PAGE, $DB;

        $this->resetAfterTest();

        // Make a course.
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();

        $this->setAdminUser();
        $PAGE->set_course($this->course);

        $manager = new course_enrolment_manager($PAGE, $this->course);
        $plugins = $manager->get_enrolment_plugins();
        $instances = $manager->get_enrolment_instances();
        /* @var enrol_manual_plugin $manualenrolplugin */
        $manualenrolplugin = reset($plugins);
        $manualenrolinstance = reset($instances);

        $studentroleid = $DB->get_field('role', 'id', array('shortname' => 'student'));
        $teacherroleid = $DB->get_field('role', 'id', array('shortname' => 'teacher'));

        // Make some students.
        for ($i = 0; $i < 10; $i++) {
            $student = $generator->create_user();
            $this->students[$student->id] = $student;
            $manualenrolplugin->enrol_user($manualenrolinstance, $student->id, $studentroleid);
        }

        // Make a couple of teachers.
        $teacher1 = $generator->create_user();
        $this->teachers[$teacher1->id] = $teacher1;
        $manualenrolplugin->enrol_user($manualenrolinstance, $teacher1->id, $teacherroleid);
        $teacher2 = $generator->create_user();
        $this->teachers[$teacher2->id] = $teacher2;
        $manualenrolplugin->enrol_user($manualenrolinstance, $teacher2->id, $teacherroleid);

    }

    /**
     * Should throw no errors.
     */
    public function test_new_assign_popup() {

        // Make an assign instance.
        $assigngenerator = new block_ajax_marking_mod_assign_generator($this->getDataGenerator());
        $assignrecord = new stdClass();
        $assignrecord->assessed = 1;
        $assignrecord->scale = 4;
        $assignrecord->course = $this->course->id;
        $assign = $assigngenerator->create_instance($assignrecord);

        // Make a submission.
        $submission = new stdClass();
        $submission->userid = reset($this->students)->id;
        $submission->assignment = $assign->id;
        $assigngenerator->create_assign_submission($submission);


        // Simulate the params from the get request:
        // http: //24moodle.dev:8888/blocks/ajax_marking/actions/grading_popup.php?userid=297&groupid=7&coursemoduleid=1257&courseid=16&node=30
        $params = array();
        $params['userid'] = $submission->userid;
        $params['coursemoduleid'] = $assign->cmid;
        $params['courseid'] = $assign->course;
        $params['node'] = 20; // Dummy node - doesn't matter as it's just passed through.

        $cm = get_coursemodule_from_id('assign', $assign->cmid, $assign->course, true, MUST_EXIST);

        $moduleobject = new block_ajax_marking_assign();
        $htmlstuff = $moduleobject->grading_popup($params, $cm);

    }

}

