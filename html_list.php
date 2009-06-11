<?php

//include("../../config.php");
require_login(1, false);
include($CFG->dirroot.'/blocks/ajax_marking/lib.php');

/**
 * This class alows the building of the <ul> list of clickable links for non-javascript enabled browsers
 */

class html_list extends ajax_marking_functions {


    /**
     * This is to build the initial non-ajax set of html nodes for accessibility and non-javascript browsers.
     * It will eventually (hopefully) be used in a progressive enhancement way so that the block
     * exhibits gracful degradation, but this may prove awkward to implement
     *
     * The output is a ul indented list of courses and assessment items with counts, with each assessment item
     * as a link to the grading page
     *
     * The ul list can be recycled to make an accessible config tree in time.
     */
    function make_html_list() {

        global $CFG;

        $this->initial_setup(true);

        // get each module to do the sorting out - perhaps do this once when the request goes out first.
        $html_list = '';
        // Foreach course, ask each module for all of the nodes to be returned as an array, with each item having all the node details.
        foreach ($this->courses as $course) {
            $course_output = '';
            $course_count = 0;
            $courseid = $course->id;
            $this->get_course_students($courseid);
            if ((!isset($this->student_ids->$courseid)) || (!$this->student_ids->$courseid)) {
                // no students in this course
                continue;
            }
            
            // see which modules are currently enabled
            $sql = "
                SELECT name 
                FROM {$CFG->prefix}modules
                WHERE visible = 1
            ";
            $enabledmods =  get_records_sql($sql);
            $enabledmods = array_keys($enabledmods);
           
            // loop through each module, getting a count for this course id from each one.
            foreach ($this->modulesettings as $modname => $module) {
                if(in_array($modname, $enabledmods)) {

                    $mod_output = $this->$modname->course_assessment_nodes($course->id, true);
                    if ($mod_output['count'] > 0) {
                        $course_count  += $mod_output['count'];
                        $course_output .= $mod_output['data'];
                    }
                }
                
            }
            if ($course_count > 0) {
                
                $html_list .= '<ul class="AMB_html">';
                $html_list .=     '<li class="AMB_html_course">'.$this->add_icon('course').'<strong>('.$course_count.')</strong> '.$course->shortname.'</li>';
                $html_list .=     '<ul class="AMB_html_items">';
                $html_list .=         $course_output;
                $html_list .=     '</ul>';
                $html_list .= '</ul>';
            }
        }
        if ($html_list) {
            return $html_list;
        } else {
            return get_string('nothing', 'block_ajax_marking');
        }

    }// end function

}












?>