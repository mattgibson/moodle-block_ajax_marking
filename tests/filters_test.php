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
require_once($CFG->dirroot.'/blocks/ajax_marking/tests/block_ajax_marking_mod_Assign_generator.class.php');

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

        $filter = new block_ajax_marking_groupid();
        list($query, $params) = $filter->group_visibility_subquery();

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

        $vanillars = $DB->get_recordset_sql($query, $params);
        $vanillalist = array();
        for ($i = 'a'; $i <= 'd'; $i++) {
            $vanillalist[$i] = $vanillars->current();
            $vanillars->next();
        }

        $this->assertEquals($expectedlist, $vanillalist);
    }

}
