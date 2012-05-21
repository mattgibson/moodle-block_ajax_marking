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
 * This is to use as a basic always-passes test to get the test harness working.
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2012 Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests to see if testing infrastructure is running OK.
 */
class smoke_testcase extends basic_testcase {

    /**
     * Basic test to see if we can get it all working.
     */
    public function test_equals() {
        $a = 1 + 2;
        $this->assertEquals(3, $a);
    }
}