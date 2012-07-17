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
 * Class file for the Assign module grading functions
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2012 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot.'/blocks/ajax_marking/classes/filters.class.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/classes/nodes_builder.class.php');
require_once($CFG->dirroot.'/enrol/locallib.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/lib.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/tests/block_ajax_marking_mod_assign_generator.class.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/filters/groupid/attach_countwrapper.class.php');

/**
 * Tests the filter system to see if it alters the query properly.
 */
class filters_test extends advanced_testcase {

    public function test_group_visibility_subquery() {

        global $DB, $PAGE, $USER;

        $this->resetAfterTest();

        // Make a course.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();

        // Enrol as teacher so teacher_courses() works.
        $this->setAdminUser();
        $PAGE->set_course($course);

        $manager = new course_enrolment_manager($PAGE, $course);
        $plugins = $manager->get_enrolment_plugins();
        $instances = $manager->get_enrolment_instances();
        /* @var enrol_manual_plugin $manualenrolplugin */
        $manualenrolplugin = reset($plugins);
        $manualenrolinstance = reset($instances);
        $teacherroleid = $DB->get_field('role', 'id', array('shortname' => 'teacher'));
        $manualenrolplugin->enrol_user($manualenrolinstance, 2, $teacherroleid);

        // Make two groups.
        $protptypegroup = new stdClass();
        $protptypegroup->courseid = $course->id;
        $group1 = $generator->create_group($protptypegroup);
        $group2 = $generator->create_group($protptypegroup);

        // Make two users.
        $user1 = $generator->create_user();
        $user2 = $generator->create_user();

        // Make user1 into group member in group1.
        $membership = new stdClass();
        $membership->groupid = $group1->id;
        $membership->userid = $user1->id;
        $DB->insert_record('groups_members', $membership);

        // Make two coursemodules.
        /* @var mod_forum_generator $forumgenerator */
        $forumgenerator = $generator->get_plugin_generator('mod_forum');
        $prototypemodule = new stdClass();
        $prototypemodule->course = $course->id;
        $forum1 = $forumgenerator->create_instance($prototypemodule);
        $assigngenerator = new block_ajax_marking_mod_assign_generator($generator);
        $assign1 = $assigngenerator->create_instance($prototypemodule);

        // Check we got everything.
        $this->assertEquals(2, $DB->count_records('course_modules'), 'Wrong number of course modules');
        $this->assertEquals(2, $DB->count_records('groups'), 'Wrong number of groups');
        $this->assertEquals(1, $DB->count_records('groups_members'), 'Wrong number of group members');

        list($query, $params) = block_ajax_marking_group_visibility_subquery();

        // We should get everything turning up as OK to display.
        // Don't use $DB as we will get duplicate array keys and it'll overwrite the rows.
        $vanillars = $DB->get_recordset_sql($query, $params);
        $vanillalist = array();
        for ($i = 'a'; $i <= 'd'; $i++) {
            $vanillalist[$i] = $vanillars->current();
            $vanillars->next();
        }

        $expectedlist = array();

        $item1 = new stdClass();
        $item1->groupid = $group1->id;
        $item1->cmid = $forum1->cmid;
        $item1->display = 1;
        $expectedlist['a'] = $item1;

        $item2 = new stdClass();
        $item2->groupid = $group2->id;
        $item2->cmid = $forum1->cmid;
        $item2->display = 1;
        $expectedlist['b'] = $item2;

        $item3 = new stdClass();
        $item3->groupid = $group1->id;
        $item3->cmid = $assign1->cmid;
        $item3->display = 1;
        $expectedlist['c'] = $item3;

        $item4 = new stdClass();
        $item4->groupid = $group2->id;
        $item4->cmid = $assign1->cmid;
        $item4->display = 1;
        $expectedlist['d'] = $item4;

        $this->assertEquals($expectedlist, $vanillalist);

        // Now see if altering the settings work. Hide one group for one course module.
        $setting = new stdClass();
        $setting->userid = $USER->id;
        $setting->tablename = 'course_modules';
        $setting->instanceid = $forum1->cmid;
        $setting->display = 1;
        $setting->id = $DB->insert_record('block_ajax_marking', $setting);

        $groupsetting = new stdClass();
        $groupsetting->configid = $setting->id;
        $groupsetting->groupid = $group1->id;
        $groupsetting->display = 0;
        $DB->insert_record('block_ajax_marking_groups', $groupsetting);

        $expectedlist['a']->display = 0;

        // This complicated looking setup makes the expected stuff into an array in the same form as the
        // results we should get. We than make n array out of the result set (Moodle DB array cannot have
        // duplicate keys).
        $vanillars = $DB->get_recordset_sql($query, $params);
        $vanillalist = array();
        for ($i = 'a'; $i <= 'd'; $i++) {
            $vanillalist[$i] = $vanillars->current();
            $vanillars->next();
        }

        $this->assertEquals($expectedlist, $vanillalist);
    }

    /**
     * This test the most complex decorator. The aim is to hide users who are in groups that are all hidden
     * and include those who are either in no group at all, or who have a group which is set to display.
     */
    public function test_groupid_attach_countrwapper() {

        global $PAGE, $DB, $USER;

        // Make basic course submissions. Keep track of which students have what.
        $this->resetAfterTest();

        // Make courses.
        $generator = $this->getDataGenerator();
        $coursea = $generator->create_course();
        $courseb = $generator->create_course();

        // Make four students and a teacher.
        $student1a = $generator->create_user();
        $student2a = $generator->create_user();
        $student3b = $generator->create_user();
        $student4b = $generator->create_user();
        $teacher = $generator->create_user();

        // Enrol the users into the courses.
        $this->setAdminUser();
        $studentroleid = $DB->get_field('role', 'id', array('shortname' => 'student'));
        $teacherroleid = $DB->get_field('role', 'id', array('shortname' => 'teacher'));

        $PAGE->set_course($coursea);
        $manager = new course_enrolment_manager($PAGE, $coursea);
        $plugins = $manager->get_enrolment_plugins();
        $instances = $manager->get_enrolment_instances();
        /* @var enrol_manual_plugin $manualenrolplugin */
        $manualenrolplugin = reset($plugins);
        $manualenrolinstance = reset($instances);
        $manualenrolplugin->enrol_user($manualenrolinstance, $student1a->id, $studentroleid);
        $manualenrolplugin->enrol_user($manualenrolinstance, $student2a->id, $studentroleid);
        $manualenrolplugin->enrol_user($manualenrolinstance, $teacher->id, $teacherroleid);

        $PAGE->set_course($courseb);
        $manager = new course_enrolment_manager($PAGE, $courseb);
        $plugins = $manager->get_enrolment_plugins();
        $instances = $manager->get_enrolment_instances();
        /* @var enrol_manual_plugin $manualenrolplugin */
        $manualenrolplugin = reset($plugins);
        $manualenrolinstance = reset($instances);
        $manualenrolplugin->enrol_user($manualenrolinstance, $student3b->id, $studentroleid);
        $manualenrolplugin->enrol_user($manualenrolinstance, $student4b->id, $studentroleid);
        $manualenrolplugin->enrol_user($manualenrolinstance, $teacher->id, $teacherroleid);

        // Make two groups in each course.
        $group1a = new stdClass();
        $group1a->courseid = $coursea->id;
        $group1a = $generator->create_group($group1a);
        $group2a = new stdClass();
        $group2a->courseid = $coursea->id;
        $group2a = $generator->create_group($group2a);
        $group3b = new stdClass();
        $group3b->courseid = $courseb->id;
        $group3b = $generator->create_group($group3b);
        $group4b = new stdClass();
        $group4b->courseid = $courseb->id;
        $group4b = $generator->create_group($group4b);

        // Make the students into group members.
        // To test all angles:
        // Student 1a is in two groups: 1a and 2a.
        // Student 2a is in 1 group: group 1a.
        // Student 3b is in one group: 3b.
        // Student 4a is in no groups.
        $groupmembership = new stdclass();
        $groupmembership->groupid = $group1a->id;
        $groupmembership->userid = $student1a->id;
        $groupmembership->timeadded = time();
        $DB->insert_record('groups_members', $groupmembership);
        $groupmembership = new stdclass();
        $groupmembership->groupid = $group1a->id;
        $groupmembership->userid = $student2a->id;
        $groupmembership->timeadded = time();
        $DB->insert_record('groups_members', $groupmembership);
        $groupmembership = new stdclass();
        $groupmembership->groupid = $group3b->id;
        $groupmembership->userid = $student3b->id;
        $groupmembership->timeadded = time();
        $DB->insert_record('groups_members', $groupmembership);
        $groupmembership = new stdclass();
        $groupmembership->groupid = $group2a->id;
        $groupmembership->userid = $student1a->id;
        $groupmembership->timeadded = time();
        $DB->insert_record('groups_members', $groupmembership);

        // Make an assignment for each course.
        $assigngenerator = new block_ajax_marking_mod_assign_generator($generator);
        $assignrecord = new stdClass();
        $assignrecord->assessed = 1;
        $assignrecord->scale = 4;
        $assignrecord->course = $coursea->id;
        $assigna = $assigngenerator->create_instance($assignrecord);
        $assignrecord = new stdClass();
        $assignrecord->assessed = 1;
        $assignrecord->scale = 4;
        $assignrecord->course = $courseb->id;
        $assignb = $assigngenerator->create_instance($assignrecord);

        // Make a student submission for each one.
        $submission = new stdClass();
        $submission->userid = $student1a->id;
        $submission->assignment = $assigna->id;
        $assigngenerator->create_assign_submission($submission);
        $submission = new stdClass();
        $submission->userid = $student2a->id;
        $submission->assignment = $assigna->id;
        $assigngenerator->create_assign_submission($submission);
        $submission = new stdClass();
        $submission->userid = $student3b->id;
        $submission->assignment = $assignb->id;
        $assigngenerator->create_assign_submission($submission);
        $submission = new stdClass();
        $submission->userid = $student4b->id;
        $submission->assignment = $assignb->id;
        $assigngenerator->create_assign_submission($submission);

        // A basic query should now return all four submissions.

        // Get coutwrapper without any decorators.
        $nodesbuilder = new ReflectionClass('block_ajax_marking_nodes_builder');

        $moduleunionmethod = $nodesbuilder->getMethod('get_module_queries_array');
        $moduleunionmethod->setAccessible(true);
        $modulequeries = $moduleunionmethod->invoke($nodesbuilder, array());
        $moduleunionmethod = $nodesbuilder->getMethod('get_count_wrapper_query');
        $moduleunionmethod->setAccessible(true);
        /* @var block_ajax_marking_query $countwrapper */
        $countwrapper = $moduleunionmethod->invoke($nodesbuilder, $modulequeries, array());
        $this->assertInstanceOf('block_ajax_marking_query', $countwrapper);

        $this->setUser($teacher->id);

        $totalnumberofsubmissions = 4;

        // Check that the raw countwrapper gets what we need.
        $sql = $countwrapper->debuggable_query();
        $recordset = $DB->get_recordset_sql($countwrapper->get_sql(), $countwrapper->get_params());

        // Count: should be 4. No other 'group by' filters, so just one record with an itemcount for all submissions.
        $pregroupscount = 0;
        $row = $recordset->current();
        $pregroupscount += $row->itemcount;

        $this->assertEquals($totalnumberofsubmissions,
                            $pregroupscount,
                            'Query not working before we even get to the groups decorator');

        // Wrap in groups decorator.
        $countwrapper = new block_ajax_marking_filter_groupid_attach_countwrapper($countwrapper);
        // Wrap
        // Add a select so we can see if it's there.
//        $countwrapper->add_select(array(
//                                       'table' => 'maxgroupidsubquery',
//                                       'column' => 'groupid'
//                                  ));
        $countwrapper->add_select(array(
                                       'table' => 'moduleunion',
                                       'column' => 'userid'
                                  ), true);
        $countwrapper->add_select(array(
                                       'table' => 'moduleunion',
                                       'column' => 'coursemoduleid'
                                  ));
        $wrappedquery = $countwrapper->debuggable_query();
        $results = $countwrapper->execute();

        $this->assertEquals($totalnumberofsubmissions,
                            count($results),
                            'Groups decorator has broken the query before any settings changed');

        // Make sure we have the right groups - should be the highest groupid available if they are in more than one
        // group, or the one group id if they are in one group.

        // Student 1a is in two groups. Should be the max id.
        $message = 'Student has the wrong groupid - '.$results[$student1a->id]->groupid.
            ' but ought to be the highest out of group1a and group2a ids: '.$group2a->id;
        $this->assertEquals($group2a->id, $results[$student1a->id]->groupid, $message);

        // Student 2a should be there with the id from group1a. Single group membership.
        $message = 'Student 2a should be in group 1a (id: '.$group1a->id.
            ' but is instead in a group with id '.$results[$student2a->id]->groupid;
        $this->assertEquals($group1a->id, $results[$student2a->id]->groupid, $message);

        // Student 4b has no group, so ought to be without any groupid.
        $message = 'Student 4b should have zero for a groupid due to being in no group, but doesn\'t';
        $this->assertEquals(0, $results[$student4b->id]->groupid, $message);

        // Hide a group at coursemoduleid level.
        $newsetting = new stdClass();
        $newsetting->userid = $teacher->id;
        $newsetting->tablename = 'course_modules';
        $newsetting->instanceid = $assigna->cmid;
        $newsetting->display = 1;
        $newsetting->id = $DB->insert_record('block_ajax_marking', $newsetting);
        $newgroupsetting = new stdClass();
        $newgroupsetting->configid = $newsetting->id;
        $newgroupsetting->groupid = $group2a->id;
        $newgroupsetting->display = 0;
        $newgroupsetting->id = $DB->insert_record('block_ajax_marking_groups', $newgroupsetting);

        $results = $countwrapper->execute();
        $message = 'Hiding a group at coursemodule level didn\'t work as it ought to have made the student '.
                'in multiple groups who previously had a high id have a low id.';
        $this->assertEquals($group1a->id,
                            $results[$student1a->id]->groupid,
                            $message);

        // Now hide the only group a student is in and verify that we get a null instead of zero.
        $newgroupsetting = new stdClass();
        $newgroupsetting->configid = $newsetting->id;
        $newgroupsetting->groupid = $group1a->id;
        $newgroupsetting->display = 0;
        $newgroupsetting->id = $DB->insert_record('block_ajax_marking_groups', $newgroupsetting);
        $results = $countwrapper->execute();
        $message = 'Hiding group 1a failed to leave student 2a with a null groupid';
        $this->assertNull($results[$student2a->id]->groupid, $message);
        $message = 'Hiding group 1a failed to leave student 1a with a null groupid';
        $this->assertNull($results[$student1a->id]->groupid, $message);

        // Hide both groups group at course level.
        // Clean out the settings first.
        $DB->delete_records('block_ajax_marking_groups');
        $DB->delete_records('block_ajax_marking');

        // Test for non-group users coming up as 0.
    }

}
