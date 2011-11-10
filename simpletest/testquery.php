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
 * Class file for the block_ajax_marking_query_factory class
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

require_once($CFG->wwwroot.'/blocks/ajax_marking/classes/query_factory.class.php');
require_once($CFG->wwwroot.'/blocks/ajax_marking/classes/query_base.class.php');

global $DB;
Mock::generate(get_class($DB), 'mockDB');

class block_ajax_marking_query_test extends UnitTestCase {

    /**
     * This will create a shared test environment with a known number of bits of work to mark
     * @return void
     */
    private function setUp() {
        global $DB;
        $this->realDB = $DB;
        $DB           = new mockDB();

        // Make a new course

        // Make a new assignment

        // Make some new users

        // Make the current user into the teacher

        // Enrol the others

        // Make submissions



    }

    /**
     * This will take all the test fixtures down and put the DB back to normal
     * @return void
     */
    private function tearDown() {
        global $DB;
        $DB = $this->realDB;
    }



}