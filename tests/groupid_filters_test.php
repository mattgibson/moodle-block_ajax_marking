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
 */
class groupid_filters_test extends advanced_testcase {

    /**
     * @var stdClass
     */
    public $user1;

    /**
     * @var stdClass
     */
    public $user2;

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
     * Standard setUp function. Just resets after each test.
     */
    public function setUp() {
        $this->resetAfterTest();
    }

    /**
     * The various subqueries that deal with groups and their visibility need the same stuff as a baseline, so it is
     * set up here.
     */
    protected function set_up_for_group_subqueries() {

        global $PAGE, $DB;

        $generator = $this->getDataGenerator();

        // Make a course.
        $this->course = $generator->create_course();

        // Enrol as teacher so teacher_courses() works.
        $this->setAdminUser();
        $PAGE->set_course($this->course);

        $this->enrol_admin_user_into_course();

        // Make two groups, both in this course.
        $this->make_two_groups($generator);

        // Make two users.
        $this->make_two_users($generator);

        // Make user1 into a group member in group1.
        $membership = new stdClass();
        $membership->groupid = $this->group1->id;
        $membership->userid = $this->user1->id;
        $DB->insert_record('groups_members', $membership);

        // Make two coursemodules.
        $this->make_two_coursemodules($generator);
    }

    /**
     * Must be called after $this->course is created.
     */
    protected function enrol_admin_user_into_course() {

        global $PAGE, $DB;

        $manager = new course_enrolment_manager($PAGE, $this->course);
        $plugins = $manager->get_enrolment_plugins();
        $instances = $manager->get_enrolment_instances();
        /* @var enrol_manual_plugin $manualenrolplugin */
        $manualenrolplugin = reset($plugins);
        $manualenrolinstance = reset($instances);
        $teacherroleid = $DB->get_field('role', 'id', array('shortname' => 'teacher'));
        $manualenrolplugin->enrol_user($manualenrolinstance, 2, $teacherroleid);
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
    protected function make_two_users($generator) {
        $this->user1 = $generator->create_user();
        $this->user2 = $generator->create_user();
    }

    /**
     * Sorts out forum1 and asign1
     *
     * @param phpunit_data_generator $generator
     */
    protected function make_two_coursemodules($generator) {
        /* @var mod_forum_generator $forumgenerator */
        $forumgenerator = $generator->get_plugin_generator('mod_forum');
        $prototypemodule = new stdClass();
        $prototypemodule->course = $this->course->id;
        $this->forum1 = $forumgenerator->create_instance($prototypemodule);
        $assigngenerator = new block_ajax_marking_mod_assign_generator($generator);
        $this->assign1 = $assigngenerator->create_instance($prototypemodule);
    }

    /**
     * Makes sure that the query we use to hide groups that the user has set to be hidden in the per user block settings
     * works.
     */
    public function test_group_visibility_subquery() {

        global $DB, $USER;

        $this->set_up_for_group_subqueries();

        // Check we got everything.
        $this->assertEquals(2, $DB->count_records('course_modules'), 'Wrong number of course modules');
        $this->assertEquals(2, $DB->count_records('groups'), 'Wrong number of groups');
        $this->assertEquals(1, $DB->count_records('groups_members'), 'Wrong number of group members');

        list($query, $params) = block_ajax_marking_group_visibility_subquery();

        // We should get everything turning up as OK to display.
        // Don't use normal $DB functions as we will get duplicate array keys and it'll overwrite the rows.
        $vanillalist = $this->get_visibility_array_results($query, $params);

        // No idea what order the stuff's going to come in, so we need to list whether we expect things to be there
        // or not. This uses the groupid and coursemodule id concatenated.
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

        $vanillalist = $this->get_visibility_array_results($query, $params);

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

        $vanillalist = $this->get_visibility_array_results($query, $params);

        $this->check_visibility_results($expectedarray, $vanillalist);

        // Now reveal it for forum 1 and make sure we don't have it any more, i.e. forum 1 should override for
        // this group, but assign 1 should keep the course setting.
        $groupsetting = new stdClass();
        $groupsetting->configid = $cmsetting->id;
        $groupsetting->groupid = $this->group1->id;
        $groupsetting->display = 1;
        $groupsetting->id = $DB->insert_record('block_ajax_marking_groups', $groupsetting);

        $expectedarray[$this->group1->id.'-'.$this->forum1->id] = false;

        $vanillalist = $this->get_visibility_array_results($query, $params);

        $this->check_visibility_results($expectedarray, $vanillalist);
    }

    /**
     * Helper function to get the visibility subquery results as an array. Needed because the Moodle DB stuff expects
     * unique first row values.
     *
     * @param $query
     * @param $params
     * @return array
     */
    protected function get_visibility_array_results($query, $params) {

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
            $found = false;
            foreach ($vanillalist as $settingsrow) {
                if ($settingsrow->groupid.'-'.$settingsrow->coursemoduleid == $identifier) {
                    $found = true;
                }
            }
            if ($expectation) {
                $this->assertTrue($found, 'Combination '.$identifier.' should have been there, but wasn\'t');
            } else {
                $this->assertFalse($found, 'Combination '.$identifier.' should not have been there, but was');
            }
        }
    }


    /**
     * Needs to make sure that we get only the groups that are visible.
     *
     * Needs groups, coursemodules, a course and some settings.
     */
    public function test_group_max_subquery() {

        global $DB;

        // Make a coursemodule, group, etc.
        $this->set_up_for_group_subqueries();

        // Get the query.
        list($query, $params) = block_ajax_marking_group_max_subquery();

        // Ought to be all groupid-userid-coursemoduleid triples for all group memberships.
        // User1 is in group1 and there are two coursemodules. Should be 2 entries.
        $results = $this->get_visibility_array_results($query, $params);

        $message = 'Wrong number of items visible before starting the group max subquery test';
        $this->assertEquals(2, count($results), $message);
        // TODO is it the right group?

        // Hide one coursemodule and we ought to get one.
        // Hide a group1 at coursemoduleid level.
        $cmgroupsetting = $this->hide_group_1_at_coursemodule_level();

        $results = $this->get_visibility_array_results($query, $params);
        $message = 'Hiding a group at coursemodule level didn\'t hide the item';
        $this->assertEquals(1, count($results), $message);

        // Now hide at course level and we ought to get nothing.
        $this->hide_group_1_at_course_level();

        $results = $this->get_visibility_array_results($query, $params);
        $message = 'Hiding a group at course level didn\'t hide the coursemodule items';
        $this->assertEquals(0, count($results), $message);

        // Now override at coursemodule level so we ought to have one only again.
        $cmgroupsetting->display = 1;
        $cmgroupsetting->id = $DB->insert_record('block_ajax_marking_groups', $cmgroupsetting);

        $results = $this->get_visibility_array_results($query, $params);
        $this->assertEquals(1, count($results));

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
     * Makes a single defaule setting for a course, then a group setting for group 1 with
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

    /**
     * @todo Make this.
     */
    public function test_group_members_subquery() {

        $this->set_up_for_group_subqueries();

    }

    /**
     * Previously, a bug emerged whereby expanding the group which contained orphans (no group memberships)
     * would make the whole lot disappear.
     *
     * Settings sent to the server:
     * groupid:0
     * coursemoduleid:188230
     * courseid:1929
     * userid:nextnodefilter
     * nodeindex:9
     */
    public function test_not_in_any_group() {

        global $PAGE;

        // Make some unmarked work.
        $generator = $this->getDataGenerator();
        // Make a course.
        $this->course = $generator->create_course();
        // Enrol as teacher so teacher_courses() works.
        $this->setAdminUser();
        $PAGE->set_course($this->course);
        $this->enrol_admin_user_into_course();
        $this->make_two_users($generator);
        $this->make_two_coursemodules($generator);
        /* @var phpunit_module_generator $assigngenerator */
        $assigngenerator = new block_ajax_marking_mod_assign_generator($this->getDataGenerator());
        $submission = new stdClass();
        $submission->userid = $this->user1->id;
        $submission->assignment = $this->assign1->id;
        $assigngenerator->create_assign_submission($submission);

        // Make a query for users, with groupid = 0 to see what happens.
        $filters = array(
            'courseid' => $this->course->id,
            'coursemoduleid' => $this->assign1->cmid,
            'groupid' => 0,
            'userid' => 'nextnodefilter'
        );
        $nodes = block_ajax_marking_nodes_builder_base::unmarked_nodes($filters);

        // Should come back with all the unmarked work.
        $this->assertEquals(1, count($nodes), 'Wrong number of nodes when checking for not in any group');

    }


}
