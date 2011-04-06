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
 * Unit tests for blocks/ajax_marking/output.class.php.
 * 
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2010 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); //  It must be included from a Moodle page
}

// Make sure the code being tested is accessible.
require_once($CFG->dirroot.'/blocks/ajax_marking/output.class.php'); // Include the code to test

/** This class contains the test cases for the functions in ouput.class.php.php. */
class block_ajax_marking_outputclass_test extends UnitTestCase {

    private $testoutputclass;

    function setUp() {
        $this->testoutputclass = new block_ajax_marking_output();
    }

    public function tearDown() {
        $this->testoutputclass = null;
    }

    function test_next_pointer() {

        $oldpointer = '[5][7][94]';
        $expectedpointer = '[5][7][95]';
        $this->testoutputclass->set_pointer($oldpointer);
        $this->testoutputclass->next_array();
        $newpointer = $this->testoutputclass->get_pointer();

        $this->assertEqual($newpointer, $expectedpointer);
    }

    function test_up_one_level() {
        $oldpointer = '[5][7][94]';
        $expectedpointer = '[5][8]';
        $this->testoutputclass->set_pointer($oldpointer);
        $this->testoutputclass->up_one_level();
        $newpointer = $this->testoutputclass->get_pointer();

        $this->assertEqual($newpointer, $expectedpointer);
    }

//    function test_json_render() {
//
//        $data = array(
//                        1 => array(
//                                'subnodes' => array(
//                                        1 => array(
//                                                'subnodes' => array(
//
//                                                'name' => 'quiz 1',
//                                                'id' => 5),
//
//                                'name' => 'course 1',
//                                'id' => 8),
//                        2 => array(
//                                'subnodes' => array(
//                                'name' => 'course 2',
//                                'id' => 9)
//                        );

}