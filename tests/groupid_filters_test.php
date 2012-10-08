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

require_once($CFG->dirroot.'/blocks/ajax_marking/classes/nodes_builder_base.class.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/lib.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/tests/block_ajax_marking_mod_assign_generator.class.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/filters/groupid/attach_countwrapper.class.php');

/**
 * Tests the filter system to see if it alters the query properly.
 *
 * We have a number of varibles to test:
 *
 * - Number of groups a user is in: 0, 1, many
 * - Groups all hidden, all not hidden, or a mixture
 * - Group settings at course level, at coursemodule level, or both (different) for an override.
 *
 */
class groupid_filters_test extends advanced_testcase {

    /**
     * @var stdClass User is in no groups
     */
    public $user1;

    /**
     * @var stdClass User is in one group: group 2
     */
    public $user2;

    /**
     * @var stdClass User is in two groups: two and three.
     */
    public $user3;

    /**
     * @var stdClass
     */
    public $group2;

    /**
     * @var stdClass
     */
    public $group1;

    /**
     * @var stdClass
     */
    public $course;

    /**
     * @var stdClass
     */
    public $assign1;

    /**
     * @var stdClass
     */
    public $forum1;

    /**
     * @var stdClass
     */
    public $assign2;

    /**
     * Standard setUp function. Just resets after each test.
     */
    public function setUp() {

        global $DB;

        global $PAGE;

        $this->resetAfterTest();

        // Assignment module is disabled in the PHPUnit DB, so we need to re-enable it.
        $DB->set_field('modules', 'visible', 1, array('name' => 'assignment'));

        $generator = $this->getDataGenerator();

        // Make a course.
        $this->course = $generator->create_course();

        // Enrol as teacher so teacher_courses() works.
        $this->setAdminUser();
        $PAGE->set_course($this->course);

        $this->enrol_user_into_course(2); // Admin user id.

        // Make two groups, both in this course.
        $this->make_two_groups($generator);

        // Make two users.
        $this->make_three_users($generator);

        // User1 is in no groups, user2 is in one group, user3 is in two groups.
        $this->add_user_to_group($this->user2, $this->group1);
        $this->add_user_to_group($this->user3, $this->group1);
        $this->add_user_to_group($this->user3, $this->group2);

        // Enrol all users in the course.
        $this->enrol_user_into_course($this->user1->id);
        $this->enrol_user_into_course($this->user2->id);
        $this->enrol_user_into_course($this->user3->id);

        // Make three coursemodules.
        $this->make_three_coursemodules($generator);

        $this->make_single_submission_in_each_coursemodule_for_each_user($generator);
    }

    /**
     * @param $user
     * @param $group
     */
    protected function add_user_to_group($user, $group) {

        global $DB;

        $membership = new stdClass();
        $membership->groupid = $group->id;
        $membership->userid = $user->id;

        $DB->insert_record('groups_members', $membership);
    }

    /**
     * Must be called after $this->course is created.
     */
    protected function enrol_user_into_course($userid) {

        global $PAGE, $DB;

        $manager = new course_enrolment_manager($PAGE, $this->course);
        $plugins = $manager->get_enrolment_plugins();
        $instances = $manager->get_enrolment_instances();
        /* @var enrol_manual_plugin $manualenrolplugin */
        $manualenrolplugin = reset($plugins);
        $manualenrolinstance = reset($instances);
        $teacherroleid = $DB->get_field('role', 'id', array('shortname' => 'teacher'));
        $manualenrolplugin->enrol_user($manualenrolinstance, $userid, $teacherroleid);
    }

    /**
     * @param phpunit_data_generator $generator
     */
    protected function make_two_groups($generator) {
        $protptypegroup = new stdClass();
        $protptypegroup->courseid = $this->course->id;
        $this->group1 = $generator->create_group($protptypegroup);
        $protptypegroup = new stdClass();
        $protptypegroup->courseid = $this->course->id;
        $this->group2 = $generator->create_group($protptypegroup);
    }

    /**
     * Makes user1 and user2
     *
     * @param phpunit_data_generator $generator
     */
    protected function make_three_users($generator) {
        $this->user1 = $generator->create_user();
        $this->user2 = $generator->create_user();
        $this->user3 = $generator->create_user();
    }

    /**
     * Sorts out forum1 and asign1
     *
     * @param phpunit_data_generator $generator
     */
    protected function make_three_coursemodules($generator) {
        /* @var mod_forum_generator $forumgenerator */
        $forumgenerator = $generator->get_plugin_generator('mod_forum');
        $prototypemodule = new stdClass();
        $prototypemodule->course = $this->course->id;
        $this->forum1 = $forumgenerator->create_instance($prototypemodule);
        $assigngenerator = new block_ajax_marking_mod_assign_generator($generator);
        $this->assign1 = $assigngenerator->create_instance($prototypemodule);
        $this->assign2 = $assigngenerator->create_instance($prototypemodule);
    }

    /**
     * @param phpunit_data_generator $generator
     */
    protected function make_single_submission_in_each_coursemodule_for_each_user($generator) {

        $assigngenerator = new block_ajax_marking_mod_assign_generator($generator);

        $users = array(
            $this->user1,
            $this->user2,
            $this->user3
        );

        $assigns = array(
            $this->assign1,
            $this->assign2
        );

        foreach ($users as $user) {
            reset($assigns);
            foreach ($assigns as $assign) {
                $prototypesubmission = new stdClass();
                $prototypesubmission->userid = $user->id;
                $prototypesubmission->assignment = $assign->id;

                $assigngenerator->create_assign_submission($prototypesubmission);
            }
        }
    }

    /**
     * Makes sure that the query we use to hide groups that the user has set to be hidden in the per
     * user block settings works.
     */
    public function test_group_visibility_subquery() {

        global $DB, $USER;

        // Check we got everything.
        $this->assertEquals(3, $DB->count_records('course_modules'),
                            'Wrong number of course modules');
        $this->assertEquals(2, $DB->count_records('groups'), 'Wrong number of groups');
        $this->assertEquals(3, $DB->count_records('groups_members'),
                            'Wrong number of group members');

        list($query, $params) = block_ajax_marking_group_visibility_subquery();

        // We should get everything turning up as OK to display.
        // Don't use normal $DB functions as we will get duplicate array keys and it'll overwrite the rows.
        $vanillalist = $this->get_query_results_as_sequentially_keyed_array($query, $params);

        // No idea what order the stuff's going to come in, so we need to list whether we expect things to be there
        // or not. This uses the groupid and coursemodule id concatenated. We start off with everything
        // false i.e. not expected to be present in the list of hidden things..
        $expectedarray = array(
            $this->group1->id.'-'.$this->assign1->id => false,
            $this->group1->id.'-'.$this->forum1->id => false,
            $this->group1->id.'-'.$this->forum1->id => false,
            $this->group2->id.'-'.$this->forum1->id => false
        );

        $this->check_visibility_results($expectedarray, $vanillalist);

        // Now see if altering the settings work. Hide group1 for one course module (forum1).
        $cmsetting = new stdClass();
        $cmsetting->userid = $USER->id;
        $cmsetting->tablename = 'course_modules';
        $cmsetting->instanceid = $this->forum1->cmid;
        $cmsetting->display = 1;
        $cmsetting->id = $DB->insert_record('block_ajax_marking', $cmsetting);

        $groupsetting = new stdClass();
        $groupsetting->configid = $cmsetting->id;
        $groupsetting->groupid = $this->group1->id;
        $groupsetting->display = 0;
        $groupsetting->id = $DB->insert_record('block_ajax_marking_groups', $groupsetting);

        $expectedarray[$this->group1->id.'-'.$this->forum1->id] = true;

        $vanillalist = $this->get_query_results_as_sequentially_keyed_array($query, $params);

        $this->check_visibility_results($expectedarray, $vanillalist);

        // Now unhide, then hide it at course level instead, so we should see it hidden for both course modules.
        $DB->delete_records('block_ajax_marking_groups', array('id' => $groupsetting->id));
        $setting = new stdClass();
        $setting->userid = $USER->id;
        $setting->tablename = 'course';
        $setting->instanceid = $this->course->id;
        $setting->display = 1;
        $setting->id = $DB->insert_record('block_ajax_marking', $setting);

        $groupsetting = new stdClass();
        $groupsetting->configid = $setting->id;
        $groupsetting->groupid = $this->group1->id;
        $groupsetting->display = 0;
        $DB->insert_record('block_ajax_marking_groups', $groupsetting);

        $expectedarray[$this->group1->id.'-'.$this->forum1->id] = true;
        $expectedarray[$this->group1->id.'-'.$this->assign1->id] = true;

        $vanillalist = $this->get_query_results_as_sequentially_keyed_array($query, $params);

        $this->check_visibility_results($expectedarray, $vanillalist);

        // Now reveal it for forum 1 and make sure we don't have it any more, i.e. forum 1 should override for
        // this group, but assign 1 should keep the course setting.
        $groupsetting = new stdClass();
        $groupsetting->configid = $cmsetting->id;
        $groupsetting->groupid = $this->group1->id;
        $groupsetting->display = 1;
        $groupsetting->id = $DB->insert_record('block_ajax_marking_groups', $groupsetting);

        $expectedarray[$this->group1->id.'-'.$this->forum1->id] = false;

        $vanillalist = $this->get_query_results_as_sequentially_keyed_array($query, $params);

        $this->check_visibility_results($expectedarray, $vanillalist);
    }

    /**
     * Helper function to get the visibility subquery results as an array. Needed because the Moodle DB stuff expects
     * unique first row values, but we won't get that here.
     *
     * @param $query
     * @param $params
     * @return array of objects
     */
    protected function get_query_results_as_sequentially_keyed_array($query, $params) {

        global $DB;

        $vanillars = $DB->get_recordset_sql($query, $params);
        $vanillalist = array();
        while ($vanillars->valid()) {
            $vanillalist[] = $vanillars->current();
            $vanillars->next();
        }

        return $vanillalist;
    }

    /**
     * Helper function that loops over the results and checks that we got the right things. Used because we can't
     * access the array items by their keys because there would be duplicate keys.
     *
     * @param array $expectedarray
     * @param array $vanillalist
     */
    protected function check_visibility_results($expectedarray, $vanillalist) {
        foreach ($expectedarray as $identifier => $expectation) {
            $found =
                false; // We don't know what order the results will come in, just what the identifier is.
            foreach ($vanillalist as $settingsrow) {
                if ($settingsrow->groupid.'-'.$settingsrow->coursemoduleid == $identifier) {
                    $found = true;
                }
            }
            if ($expectation) {
                $this->assertTrue($found,
                                  'Combination '.$identifier.' should have been there, but wasn\'t');
            } else {
                $this->assertFalse($found,
                                   'Combination '.$identifier.' should not have been there, but was');
            }
        }
    }

    /**
     * Makes a single coursemodule level default setting, then a group setting for group 1 that
     * hides it.
     *
     * @return stdClass
     */
    public function hide_group_1_at_coursemodule_level() {

        global $USER, $DB;

        $cmsetting = new stdClass();
        $cmsetting->userid = $USER->id;
        $cmsetting->tablename = 'course_modules';
        $cmsetting->instanceid = $this->assign1->cmid;
        $cmsetting->display = 1;
        $cmsetting->id = $DB->insert_record('block_ajax_marking', $cmsetting);
        $cmgroupsetting = new stdClass();
        $cmgroupsetting->configid = $cmsetting->id;
        $cmgroupsetting->groupid = $this->group1->id;
        $cmgroupsetting->display = 0;
        $cmgroupsetting->id = $DB->insert_record('block_ajax_marking_groups', $cmgroupsetting);
        return $cmgroupsetting;
    }

    /**
     * Makes a single default setting for a course, then a group setting for group 1 with
     * display = 0.
     */
    public function hide_group_1_at_course_level() {

        global $USER, $DB;

        $coursesetting = new stdClass();
        $coursesetting->userid = $USER->id;
        $coursesetting->tablename = 'course';
        $coursesetting->instanceid = $this->course->id;
        $coursesetting->display = 1;
        $coursesetting->id = $DB->insert_record('block_ajax_marking', $coursesetting);
        $coursegroupsetting = new stdClass();
        $coursegroupsetting->configid = $coursesetting->id;
        $coursegroupsetting->groupid = $this->group1->id;
        $coursegroupsetting->display = 0;
        $coursegroupsetting->id =
            $DB->insert_record('block_ajax_marking_groups', $coursegroupsetting);
    }

    // These are the tests to cover all possible groups settings via the full query.
    // In all cases, we have three coursemodules in the same course, three users, two groups, with
    // one user in no groups, one user in one group and one user in two groups.


    /**
     * User 1 is in no groups, so ought to come up with either groupid 0, or missing if the
     * settings say so.
     */
    public function test_no_group_memberships_group_node_appears_groupid_filter() {

        // User 1 is in no groups. Ought to show up with groupid of 0. This ought to give us
        // a single node for groupid 0 with a count of 1.
        $params = array(
            'coursemoduleid' => $this->assign1->cmid,
            'groupid' => 'nextnodefilter',
        );

        $nodes = block_ajax_marking_nodes_builder_base::unmarked_nodes($params);

        $this->assertNotEmpty($nodes, 'No nodes returned');

        $foundnode = new stdClass();
        $count = 0;
        foreach ($nodes as $node) {
            if ($node->groupid == 0) {
                $count++;
                $foundnode = $node;
                break;
            }
        }

        $this->assertEquals(1, $count, 'Too many nodes found');
        $this->assertObjectHasAttribute('groupid', $foundnode);
        $this->assertEquals(0, $foundnode->groupid, 'Nothing returned for groupid 0');
        $this->assertEquals(1, $foundnode->itemcount);
    }

    /**
     * User 1 is in no groups, so ought to come up with either groupid 0, or missing if the
     * settings say so.
     */
    public function test_no_group_memberships_group_node_appears_userid_filter() {

        // User 1 is in no groups. Ought to show up with groupid of 0. This ought to give us
        // a single node for groupid 0 with a count of 1.
        $params = array(
            'coursemoduleid' => $this->assign1->cmid,
            'groupid' => 0,
            'userid' => 'nextnodefilter',
        );

        $nodes = block_ajax_marking_nodes_builder_base::unmarked_nodes($params);
        $this->assertCount(1, $nodes, 'Too many nodes!');
        $node = reset($nodes);

        $this->assertObjectHasAttribute('userid', $node);
        $this->assertEquals($this->user1->id, $node->userid, 'Wrong userid returned for groupid 0');
        $this->assertEquals(1, $node->itemcount);
    }

    /**
     * User 2 is in group1, as is user 3, but user 3 is also in group2, so ought to show up there instead.
     */
    public function test_group_one_group_node_appears_groupid_filter() {

        $params = array(
            'coursemoduleid' => $this->assign1->cmid,
            'groupid' => 'nextnodefilter',
        );

        $nodes = block_ajax_marking_nodes_builder_base::unmarked_nodes($params);

        $this->assertNotEmpty($nodes, 'No nodes returned');

        $foundnode = new stdClass();
        $count = 0;
        foreach ($nodes as $node) {
            if ($node->groupid == $this->group1->id) {
                $count++;
                $foundnode = $node;
                break;
            }
        }

        $this->assertNotEquals(0, $count, 'No nodes returned for groupid '.$this->group1->id);
        $this->assertEquals(1, $count, 'Too many nodes found');
        $this->assertEquals(1, $foundnode->itemcount, 'User should be hidden here due to other goup membership');
    }

    /**
     * User 2 is in group1, as is user 3, but user 3 is also in group2, so ought to show up there instead.
     */
    public function test_group_two_group_node_appears_groupid_filter() {

        $params = array(
            'coursemoduleid' => $this->assign1->cmid,
            'groupid' => 'nextnodefilter',
        );

        $nodes = block_ajax_marking_nodes_builder_base::unmarked_nodes($params);

        $this->assertNotEmpty($nodes, 'No nodes returned');

        $foundnode = new stdClass();
        $count = 0;
        foreach ($nodes as $node) {
            if ($node->groupid == $this->group2->id) {
                $count++;
                $foundnode = $node;
                break;
            }
        }

        $this->assertNotEquals(0, $count, 'No nodes returned for groupid '.$this->group2->id);
        $this->assertEquals(1, $count, 'Too many nodes found');
        $this->assertEquals(1, $foundnode->itemcount,
                            'User should be hidden here due to other goup membership');
    }
}
