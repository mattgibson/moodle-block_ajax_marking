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
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2012 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot.'/enrol/locallib.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/lib.php');


class test_nodes_builder extends advanced_testcase {

    /**
     * @var stdClass
     */
    protected $course;

    /**
     * @var array of stdClass objects
     */
    protected $students;

    /**
     * @var array of stdClass objects
     */
    protected $teachers;

    /**
     * Gets a blank course with users ready for each test
     */
    protected function setUp() {

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
     * For each module, we need to see if we can actually get any data back using the query from
     * the module's query factory. Possible problem with third (fourth?) party module access code,
     * so check first to see if the generator can handle making one to test with.
     */
    public function test_basic_module_retrieval() {

        global $DB;

        // Assignment module is disabled in the PHPUnit DB, so we need to reenable it.
        $DB->set_field('modules', 'visible', 1, array('name' => 'assignment'));

        $classes = block_ajax_marking_get_module_classes();

        foreach ($classes as $modclass) {

            $modname = $modclass->get_module_name();

            // We need some submissions, but these are different for every module.
            // Without a standardised way of doing this, we will use methods in this class to do
            // the job until a better way emerges.
            $createdatamethod = 'create_'.$modname.'_submission_data';
            $expectedcount = 0;
            if (method_exists($this, $createdatamethod)) {
                // Let the modules decide what number of things should be expected. Some are more
                // complex than others.
                $expectedcount = $this->$createdatamethod();
            } else {
                // No point carrying on without some data to check.
                continue;
            }

            // Make query.
            $filters = array('nextnodetype' => 'userid');
            $query = $modclass->query_factory();
            // We will get an error if we leave it like this as the userids in the first
            // column are not unique.
            $wrapper = new block_ajax_marking_query_base();
            $wrapper->add_select(array('function' => 'COUNT',
                                       'column' => 'userid',
                                       'alias' => 'count'));
            $wrapper->add_from(array('table' => $query,
                                     'alias' => 'modulequery'));

            // Run query. Get one stdClass with a count property.
            $unmarkedstuff = $wrapper->execute();

            // Make sure we get the right number of things back.
            $this->assertEquals($expectedcount, reset($unmarkedstuff)->count);

        }
    }

    /**
     * Makes 10 submissions that ought to be picked up by test_basic_module_retrieval() as well as
     * a few others that shouldn't be. This is for the 2.2 and earlier assignment module.
     */
    private function create_assignment_submission_data() {

        $submissioncount = 0;
        // Provide defaults to prevent IDE griping.
        $student = new stdClass();
        $student->id = 3;

        /* @var phpunit_module_generator $assignmentgenerator */
        $assignmentgenerator = $this->getDataGenerator()->get_plugin_generator('mod_assignment');
        $assignments = array();
        $assignmentrecord = new stdClass();
        $assignmentrecord->course = $this->course->id;
        $assignmentrecord->timedue = strtotime('1 hour ago');
        // Make offline assignment to confuse things.
        $assignmentrecord->assignmenttype = 'offline';
        $assignments[] = $assignmentgenerator->create_instance($assignmentrecord);
        // Make online text assignment.
        $assignmentrecord->assignmenttype = 'online';
        $assignments[] = $assignmentgenerator->create_instance($assignmentrecord);
        // Make single file submission assignment.
        $assignmentrecord->assignmenttype = 'upload';
        $assignments[] = $assignmentgenerator->create_instance($assignmentrecord);
        // Make advanced upload assignment.
        $assignmentrecord->assignmenttype = 'uploadsingle';
        $assignments[] = $assignmentgenerator->create_instance($assignmentrecord);

        // Currently, we only have create_instance() in the generator, so we need to do this
        // from scratch.
        foreach ($assignments as $assignment) {

            foreach ($this->students as $student) {

                // Make new submission.
                // Stuff common to all types first.
                $submission = new stdClass();

                $submission->assignment = $assignment->id;
                $submission->userid = $student->id;

                // Now stuff that varies across types to alter the defaults.
                switch ($assignment->assignmenttype) {

                    case 'offline':
                        // Theoretically impossible, but good to check.
                        break;

                    case 'online':
                        $submission->data1 = 'Text of online essay here';
                        $submission->data2 = 1;
                        break;

                    case 'upload':
                        $submission->numfiles = 1;
                        $submission->data2 = 'submitted';
                        break;

                    case 'uploadsingle':
                        $submission->numfiles = 0;
                        $submission->data1 = '';
                        $submission->data2 = '';
                        break;

                }

                $this->make_assignment_submission($submission);

                // Add one to the count.
                if ($assignment->assignmenttype != 'offline') {
                    $submissioncount++;
                }
            }
        }

        // Make some data to test edge cases.

        // Deadline not passed. Does not matter. Ought to be picked up.
        $assignmentrecord = new stdClass();
        $assignmentrecord->assignmenttype = 'online';
        $assignmentrecord->course = $this->course->id;
        $assignmentrecord->timedue = strtotime('1 hour');
        $assignment = $assignmentgenerator->create_instance($assignmentrecord);
        $submission = new stdClass();
        $submission->assignment = $assignment->id;
        $submission->userid = $student->id; // One will be left over from the loop.
        $submission->data1 = 'Text of online essay here';
        $submission->data2 = 1;
        $this->make_assignment_submission($submission);
        $submissioncount++;

        // Not finalised.
        $assignmentrecord = new stdClass();
        $assignmentrecord->assignmenttype = 'upload';
        $assignmentrecord->course = $this->course->id;
        $assignment = $assignmentgenerator->create_instance($assignmentrecord);
        $submission = new stdClass();
        $submission->assignment = $assignment->id;
        $submission->userid = $student->id; // One will be left over from the loop.
        $submission->numfiles = 1;
        $submission->data2 = ''; // Should be 'submitted' to be picked up.
        $this->make_assignment_submission($submission);

        // Empty feedback?
        $assignmentrecord = new stdClass();
        $assignmentrecord->assignmenttype = 'upload';
        $assignmentrecord->course = $this->course->id;
        $assignment = $assignmentgenerator->create_instance($assignmentrecord);
        $submission = new stdClass();
        $submission->assignment = $assignment->id;
        $submission->userid = $student->id; // One will be left over from the loop.
        $submission->numfiles = 1;
        $submission->data2 = 'submitted';
        $submission->submissioncomment = '';
        $submission->format = 1;
        $submission->grade = -1;
        $this->make_assignment_submission($submission);
        $submissioncount++;

        return $submissioncount;

    }

    /**
     * @param stdClass $submissionrecord
     *
     * @throws coding_exception
     * @return bool|\stdClass
     */
    private function make_assignment_submission($submissionrecord) {

        global $DB;

        if (!isset($submissionrecord->assignment)) {
            throw new coding_exception('Make submission needs an assignment id.');
        }
        if (!isset($submissionrecord->userid)) {
            throw new coding_exception('Make submission needs a user id.');
        }

        // Make new submission.
        // Stuff common to all types first.
        $submission = new stdClass();
        $submission->timecreated = time();
        $submission->timemodified = time();

        // Now defaults.
        $submission->numfiles = 0;
        $submission->data1 = null;
        $submission->data2 = null;
        $submission->grade = -1;
        $submission->submissioncomment = '';
        $submission->format = 0;
        $submission->teacher = 0;
        $submission->timemarked = 0;
        $submission->mailed = 0;

        $extended = (object)array_merge((array)$submission, (array)$submissionrecord);

        return $DB->insert_record('assignment_submissions', $extended);
    }



}
