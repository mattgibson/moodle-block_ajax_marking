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
 * This is the file that is called by block_ajax_marking.php when the block is created during page-load.
 *
 * It first includes the main lib.php fie that contains the base class which has all of the functions
 * in it, then instantiates a new html_list object which will process the request and output the HTML
 * that the block needs.
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2008 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_login(0, false);
require_once($CFG->dirroot.'/blocks/ajax_marking/lib.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/classes/module_base.class.php');

global $USER, $CFG, $DB;

$moduleclasses = block_ajax_marking_get_module_classes();

// get each module to do the sorting out - perhaps do this once when the request goes out
// first.
$htmllist = '';

$courses = block_ajax_marking_get_my_teacher_courses();

// Foreach course, ask each module for all of the nodes to be returned as an array, with
// each item having all the node details.
foreach ($courses as $course) {

    $course_output = '';
    $course_count = 0;
    $courseid = $course->id;
    $first = true;

    if (!$course->visible) {
        continue;
    }

    $studentids = block_ajax_marking_get_course_students($course);

    if (empty($studentids)) {
        // no students in this course
        continue;
    }

    // see which modules are currently enabled
    $sql = 'SELECT name
              FROM {modules}
             WHERE visible = 1';
    $enabledmods =  $DB->get_records_sql($sql);
    $enabledmods = array_keys($enabledmods);

    // loop through each module, getting a count for this course id from each one.
    foreach ($moduleclasses as $moduleclass) {

        list($count, $data) = $moduleclass->course_assessment_nodes($course->id, true);

        if ($count > 0) {
            $course_count  += $count;
            $course_output .= $data;
        }

    }


    if ($course_count > 0) {

        $htmllist .= '<ul class="AMB_html">'
                        .'<li class="AMB_html_course">'
                            .block_ajax_marking_add_icon('course')
                            .'<strong>('.$course_count.')</strong> '
                            .$course->shortname
                        .'</li>'
                        .'<li><ul class="AMB_html_items">'
                            .$course_output
                        .'</ul></li>'
                    .'</ul>';
    }
}

$htmllist = $htmllist ? $htmllist : get_string('nothingtomark', 'block_ajax_marking');


