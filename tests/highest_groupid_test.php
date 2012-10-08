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
require_once($CFG->dirroot.'/blocks/ajax_marking/tests/block_ajax_marking_mod_assign_generator.class.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/classes/nodes_builder_base.class.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/lib.php');

/**
 * Tests that the filter that gets the highest non-hidden group id, which is the most complex bit of the SQL.
 */
class highest_groupid_test extends advanced_testcase {

    /**
     * @var stdClass
     */
    protected $student1a;

    /**
     * @var stdClass
     */
    protected $student2a;

    /**
     * @var stdClass
     */
    protected $student3b;

    /**
     * @var stdClass
     */
    protected $student4b;

    /**
     * @var stdClass
     */
    protected $group1a;

    /**
     * @var stdClass
     */
    protected $group2a;

    /**
     * @var stdClass
     */
    protected $group3b;

    /**
     * @var stdClass
     */
    protected $group4b;

    /**
     * @var stdClass
     */
    protected $group5a;

    /**
     * @var stdClass
     */
    protected $assigna;

    /**
     * @var stdClass
     */
    protected $assignb;

    /**
     * @var int
     */
    protected $totalnumberofsubmissions = 0;

    /**
     * @var block_ajax_marking_query
     */
    protected $query;

    /**
     * @var stdClass
     */
    protected $teacher;

    /**
     * Makes shared test fixtures.
     */
    public function setUp() {

        global $PAGE, $DB;

        $this->resetAfterTest();

        parent::setUp();

        $this->setAdminUser();

        // Make basic course submissions. Keep track of which students have what.
        // Make courses.
        $generator = $this->getDataGenerator();
        $coursea = $generator->create_course();
        $courseb = $generator->create_course();

        // Make four students and a teacher.
        $this->student1a = $generator->create_user();
        $this->student2a = $generator->create_user();
        $this->student3b = $generator->create_user();
        $this->student4b = $generator->create_user();
        $this->teacher = $generator->create_user();

        // Enrol the users into the courses.
        $studentroleid = $DB->get_field('role', 'id', array('shortname' => 'student'));
        $teacherroleid = $DB->get_field('role', 'id', array('shortname' => 'teacher'));
        $PAGE->set_course($coursea);
        $manager = new course_enrolment_manager($PAGE, $coursea);
        $plugins = $manager->get_enrolment_plugins();
        $instances = $manager->get_enrolment_instances();
        /* @var enrol_manual_plugin $manualenrolplugin */
        $manualenrolplugin = reset($plugins);
        $manualenrolinstance = reset($instances);
        $manualenrolplugin->enrol_user($manualenrolinstance, $this->student1a->id, $studentroleid);
        $manualenrolplugin->enrol_user($manualenrolinstance, $this->student2a->id, $studentroleid);
        $manualenrolplugin->enrol_user($manualenrolinstance, $this->teacher->id, $teacherroleid);
        $PAGE->set_course($courseb);
        $manager = new course_enrolment_manager($PAGE, $courseb);
        $plugins = $manager->get_enrolment_plugins();
        $instances = $manager->get_enrolment_instances();
        $manualenrolplugin = reset($plugins);
        $manualenrolinstance = reset($instances);
        $manualenrolplugin->enrol_user($manualenrolinstance, $this->student3b->id, $studentroleid);
        $manualenrolplugin->enrol_user($manualenrolinstance, $this->student4b->id, $studentroleid);
        $manualenrolplugin->enrol_user($manualenrolinstance, $this->teacher->id, $teacherroleid);

        // Make two groups in each course.
        $group1a = new stdClass();
        $group1a->courseid = $coursea->id;
        $this->group1a = $generator->create_group($group1a);
        $group2a = new stdClass();
        $group2a->courseid = $coursea->id;
        $this->group2a = $generator->create_group($group2a);
        $group3b = new stdClass();
        $group3b->courseid = $courseb->id;
        $this->group3b = $generator->create_group($group3b);
        $group4b = new stdClass();
        $group4b->courseid = $courseb->id;
        $this->group4b = $generator->create_group($group4b);
        // Extra one.
        $group5a = new stdClass();
        $group5a->courseid = $coursea->id;
        $this->group5a = $generator->create_group($group5a);

        // Make the students into group members.
        // To test all angles:
        // Student 1a is in two groups: 1a and 2a.
        // Student 2a is in 1 group: group 1a.
        // Student 3b is in one group: 3b.
        // Student 4a is in no groups.
        $groupmembership = new stdclass();
        $groupmembership->groupid = $this->group1a->id;
        $groupmembership->userid = $this->student1a->id;
        $groupmembership->timeadded = time();
        $DB->insert_record('groups_members', $groupmembership);
        $groupmembership = new stdclass();
        $groupmembership->groupid = $this->group1a->id;
        $groupmembership->userid = $this->student2a->id;
        $groupmembership->timeadded = time();
        $DB->insert_record('groups_members', $groupmembership);
        $groupmembership = new stdclass();
        $groupmembership->groupid = $this->group3b->id;
        $groupmembership->userid = $this->student3b->id;
        $groupmembership->timeadded = time();
        $DB->insert_record('groups_members', $groupmembership);
        $groupmembership = new stdclass();
        $groupmembership->groupid = $this->group2a->id;
        $groupmembership->userid = $this->student1a->id;
        $groupmembership->timeadded = time();
        $DB->insert_record('groups_members', $groupmembership);

        // Make an assignment for each course.
        $assigngenerator = new block_ajax_marking_mod_assign_generator($generator);
        $assignrecord = new stdClass();
        $assignrecord->assessed = 1;
        $assignrecord->scale = 4;
        $assignrecord->course = $coursea->id;
        $this->assigna = $assigngenerator->create_instance($assignrecord);
        $assignrecord = new stdClass();
        $assignrecord->assessed = 1;
        $assignrecord->scale = 4;
        $assignrecord->course = $courseb->id;
        $this->assignb = $assigngenerator->create_instance($assignrecord);

        // Make a student submission for each one.
        $submission = new stdClass();
        $submission->userid = $this->student1a->id;
        $submission->assignment = $this->assigna->id;
        $assigngenerator->create_assign_submission($submission);
        $this->totalnumberofsubmissions++;
        $submission = new stdClass();
        $submission->userid = $this->student2a->id;
        $submission->assignment = $this->assigna->id;
        $assigngenerator->create_assign_submission($submission);
        $this->totalnumberofsubmissions++;
        $submission = new stdClass();
        $submission->userid = $this->student3b->id;
        $submission->assignment = $this->assignb->id;
        $assigngenerator->create_assign_submission($submission);
        $this->totalnumberofsubmissions++;
        $submission = new stdClass();
        $submission->userid = $this->student4b->id;
        $submission->assignment = $this->assignb->id;
        $assigngenerator->create_assign_submission($submission);
        $this->totalnumberofsubmissions++;

        // A basic query should now return all four submissions.
        $this->setUser($this->teacher->id);

        // Get countwrapper without any special decorators.
        $nodesbuilder = new ReflectionClass('block_ajax_marking_nodes_builder_base');

        $moduleunionmethod = $nodesbuilder->getMethod('get_module_queries_array');
        $moduleunionmethod->setAccessible(true);
        $modulequeries = $moduleunionmethod->invoke($nodesbuilder, array());
        $moduleunionmethod = $nodesbuilder->getMethod('get_count_wrapper_query');
        $moduleunionmethod->setAccessible(true);
        /* @var block_ajax_marking_query $countwrapper */
        $this->query = $moduleunionmethod->invoke($nodesbuilder, $modulequeries, array());
        $this->assertInstanceOf('block_ajax_marking_query', $this->query);

        // Add a select or two so we can see if the groupid is there.
        $this->query->add_select(
            array(
                 'column' => block_ajax_marking_get_countwrapper_groupid_sql($this->query),
                 'alias' => 'groupid'
            ));
        $this->query->add_select(
            array(
                 'table' => 'moduleunion',
                 'column' => 'userid'
            ), true);
        $this->query->add_select(
            array(
                 'table' => 'moduleunion',
                 'column' => 'coursemoduleid'
            ));

        // This will help with diagnosing issues with the complex left joins.
        $this->query->add_select(
            array(
                 'table' => 'gmember_members',
                 'column' => 'groupid',
                 'alias' => 'gmember_members_groupid'
            ));
        $this->query->add_select(
            array(
                 'table' => 'gmember_groups',
                 'column' => 'id',
                 'alias' => 'gmember_groupss_id'
            ));
        $this->query->add_select(
            array(
                 'table' => 'gvis',
                 'column' => 'groupid',
                 'alias' => 'gvis_groupid'
            ));

    }

    /**
     * This test the most complex decorator. The aim is to hide users who are in groups that are all hidden
     * and include those who are either in no group at all, or who have a group which is set to display.
     */
    public function test_groupid_attach_countrwapper() {

        global $DB;

        // For debugging. Stop here and copy this to see what's in the query.
        $wrappedquery = $this->query->debuggable_query();

        $results = $this->query->execute();

        // Sanity check: we should still get the whole lot before messing with anything.
        $this->assertEquals($this->totalnumberofsubmissions,
                            count($results),
                            'Total count is wrong before any settings changed');

        // Make sure we have the right groups - should be the highest groupid available if they are in more than one
        // group, or the one group id if they are in one group.

        // Student 1a is in two groups. Should be the max id.
        $message = 'Student has the wrong groupid - '.$results[$this->student1a->id]->groupid.
            ' but ought to be the highest out of group1a and group2a ids: '.$this->group2a->id;
        $this->assertEquals($this->group2a->id, $results[$this->student1a->id]->groupid, $message);

        // Student 2a should be there with the id from group1a. Single group membership.
        $message = 'Student 2a should be in group 1a (id: '.$this->group1a->id.
            ' but is instead in a group with id '.$results[$this->student2a->id]->groupid;
        $this->assertEquals($this->group1a->id, $results[$this->student2a->id]->groupid, $message);

        // Student 4b has no group, so ought to be without any groupid.
        $message = 'Student 4b should have zero for a groupid due to being in no group, but doesn\'t';
        $this->assertEquals(0, $results[$this->student4b->id]->groupid, $message);

        // Hide a group2a at coursemoduleid level, expecting it to make the other group membership get used.
        $newsetting = new stdClass();
        $newsetting->userid = $this->teacher->id;
        $newsetting->tablename = 'course_modules';
        $newsetting->instanceid = $this->assigna->cmid;
        $newsetting->display = 1;
        $newsetting->id = $DB->insert_record('block_ajax_marking', $newsetting);
        $newgroupsetting = new stdClass();
        $newgroupsetting->configid = $newsetting->id;
        $newgroupsetting->groupid = $this->group2a->id;
        $newgroupsetting->display = 0;
        $newgroupsetting->id = $DB->insert_record('block_ajax_marking_groups', $newgroupsetting);

        $results = $this->query->execute();
        $message = "Hiding at coursemodule level has hidden user {$this->student1a->id} instead of used the ".
            "other available groupid ({$this->group1a->id})";
        $this->assertArrayHasKey($this->student1a->id, $results, $message);
        $message = 'Hiding a group at coursemodule level didn\'t work as it ought to have made the student '.
            'in multiple groups who previously had a high id have a low id.';
        $this->assertEquals($this->group1a->id, $results[$this->student1a->id]->groupid, $message);

        // Now, add another group to make sure we still get just one entry for that student.
        $groupmembership = new stdclass();
        $groupmembership->groupid = $this->group5a->id;
        $groupmembership->userid = $this->student1a->id;
        $groupmembership->timeadded = time();
        $DB->insert_record('groups_members', $groupmembership);
        $results = $this->query->execute();
        $message = "Ading user {$this->student1a->id} to a third group ({$this->group5a->id}) that ought to have made that".
            "group's id appear didn't work";
        $this->assertEquals($this->group5a->id, $results[$this->student1a->id]->groupid, $message);
        $this->assertEquals($this->totalnumberofsubmissions,
                            count($results),
                            'Adding a new group membership caused too many items to turn up');

        // No hide that new group and make sure things go back to how they were.
        $hidegroup5 = new stdClass();
        $hidegroup5->configid = $newsetting->id;
        $hidegroup5->groupid = $this->group5a->id;
        $hidegroup5->display = 0;
        $hidegroup5->id = $DB->insert_record('block_ajax_marking_groups', $hidegroup5);
        $results = $this->query->execute();
        $message = "Hiding group {$this->group5a->id} at coursemodule level has led to the wrong item count";
        $this->assertEquals($this->totalnumberofsubmissions, count($results), $message);
        $message = "Two hidden groups should leave student {$this->student1a->id} with group {$this->group1a->id} but it ".
            "has {$results[$this->student1a->id]->groupid} instead";
        $this->assertEquals($this->group1a->id, $results[$this->student1a->id]->groupid, $message);

        // Now hide the only group a student is in and verify that we have that student hidden.
        $newgroupsetting = new stdClass();
        $newgroupsetting->configid = $newsetting->id;
        $newgroupsetting->groupid = $this->group1a->id;
        $newgroupsetting->display = 0;
        $newgroupsetting->id = $DB->insert_record('block_ajax_marking_groups', $newgroupsetting);
        $results = $this->query->execute();
        $message = 'Hiding group 1a failed to leave student 2a with a null groupid';
        $this->assertArrayNotHasKey($this->student2a->id, $results, $message);
        $message = 'Hiding group 1a failed to leave student 1a with a null groupid';
        $this->assertArrayNotHasKey($this->student1a->id, $results, $message);

        // Hide both groups group at course level.
        // Clean out the settings first.
        $DB->delete_records('block_ajax_marking_groups');
        $DB->delete_records('block_ajax_marking');
        // Test for non-group users coming up as 0.
    }
}
