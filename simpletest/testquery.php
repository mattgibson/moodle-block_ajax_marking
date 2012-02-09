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
 * Class file for the block_ajax_marking_nodes_factory class
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2011 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); //  It must be included from a Moodle page
}

global $CFG;
/**
 *
 */
require_once($CFG->dirroot.'/blocks/ajax_marking/classes/nodes_factory.class.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/classes/query_base.class.php');


class block_ajax_marking_query_test extends UnitTestCaseUsingDatabase {

    /**
     * This will create a shared test environment with a course, some users, some enrolments, etc
     *
     * @return void
     */
    public function setUp() {

        global $DB;

        $this->switch_to_test_db();

        // Make all the tables we will need
        // courses, users, enrolments, contexts,
        $tablestomake = array('course_categories' => 'lib',
                              'course' => 'lib',
                              'log' => 'lib',
                              'course_sections' => 'lib',
                              'cache_flags' => 'lib',
                              'config_plugins' => 'lib',
                              'role_assignments' => 'lib',
                              'role_capabilities' => 'lib',
                              'events_handlers' => 'lib',
                              'block' => 'lib',
                              'block_instances' => 'lib',
                              'context' => 'lib',
        );
        foreach($tablestomake as $table => $file) {
            $this->create_test_table($table, $file);
        }

        // Make a copy of the basic site stuff from the main DB
        $blocktoget = array('site_main_menu',
                            'course_summary',
                            'news_items',
                            'calendar_upcoming',
                            'recent_activity',
                            'calendar_month',
                            'search_forums');
        $this->revert_to_real_db();
        $retrievedblocks = array();
        foreach ($blocktoget as $block) {
            $retrievedblocks[] = $DB->get_record('block', array('name' => $block));
        }
        $misccategory = $DB->get_record('course_categories', array('id' => 1));
        $sitecontext = $DB->get_record('context', array('id' => 1));
        $frontcourse = $DB->get_record('course', array('id' => 1));

        // Put the stuff into the unit test DB
        $this->switch_to_test_db();
        foreach ($retrievedblocks as $block) {
            $DB->insert_record('block', $block);
        }
        $misccategory->id = $DB->insert_record('course_categories', $misccategory);
        $sitecontext->id = $DB->insert_record('context', $sitecontext);
        $misccontext = create_context(CONTEXT_COURSECAT, $misccategory->id);
        $frontcourse->id = $DB->insert_record('course', $frontcourse);

        // Make a new course
        $count = 0;
        $data = new stdClass();
        $data->category = $misccategory->id;
        $data->shortname = 'Test course '.$count.' '.date("j F, Y, g:i a");
        $data->fullname = 'Test course '.$count.' '.date("j F, Y, g:i a");
        $options = array();
        create_course($data, $options);


        // Make a new assignment

        // Make some new users

        // Make the current user into the teacher

        // Enrol the others

        // Make submissions

    }

    public function test_assignment_enrol() {

    }




}
