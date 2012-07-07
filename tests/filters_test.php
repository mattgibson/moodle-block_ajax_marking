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

/**
 * Tests the filter system to see if it alters the query properly.
 */
class filters_test extends advanced_testcase {

    public function test_group_visibility_subquery() {

        global $DB, $PAGE;

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
        $prototypeforum = new stdClass();
        $prototypeforum->course = $course->id;
        $forum1 = $forumgenerator->create_instance($prototypeforum);
        $forum2 = $forumgenerator->create_instance($prototypeforum);

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
        $item = new stdClass();
        $item->groupid = $group1->id;
        $item->cmid = $forum1->cmid;
        $item->display = 1;
        $expectedlist['a'] = $item;
        $item2 = clone($item);
        $item2->groupid = $group2->id;
        $expectedlist['b'] = $item2;
        $item3 = clone($item2);
        $item3->groupid = $group1->id;
        $item3->cmid = $forum2->cmid;
        $expectedlist['c'] = $item3;
        $item4 = clone($item3);
        $item4->groupid = $group2->id;
        $expectedlist['d'] = $item4;

        $this->assertEquals($expectedlist, $vanillalist);

    }

}
