<?php

include("../../config.php");
require_login(1, false);
include("lib.php");

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
    function html_list() {

        global $CFG;
        $this->initial_setup(true);

        // get unmarked work
        $this->get_main_level_data();
        $module_ids_array = array();
        // get an array of module ids
        // foreach ($this->modules as $module) {
        //    $module_ids_array[] = $this->moduleids[module];
        // }

        // make temp array to sort data into.
        // Its structure is main array->course id array->name of assessment type array->assessment id array->submissions array ->indivdual submisisons
        $temp_array = array();
        // sort it out into courses and assessments with counts

        // foreach type of assessments, process all of them by sorting them into course arrays
     
        foreach ($this->modules as $module) {
            

            if ($module->submissions) {

                foreach ($module->submissions as $submission) {

                    $course  = $submission->course;
                    $id      = $submission->id;
                    $modname = $module->name;
                    $name    = $submission->name;
                    $cmid    = $submission->cmid;


                    // make course array if not present
                    if (!array_key_exists($course, $temp_array)) {
                        $temp_array[$course] = array();
                    }

                    // add this type of assessment if not present
                    if (!array_key_exists($modname, $temp_array[$course])) {
                        $temp_array[$course][$modname] = array();
                    }

                    if (!array_key_exists($id, $temp_array[$course][$modname])) {

                        // add the assessment id of the item that the submisison relates to

                       // $temp_array[$course][$modname][$id] = array('count'=>1, 'type'=>$modname, 'name'=>$name, 'cmid'=>$cmid, 'id'=>$submission->id);
                        $temp_array[$course][$modname][$id] = array('count'=>1, 'type'=>$modname, 'name'=>$name, 'cmid'=>$cmid, 'id'=>$submission->id, 'submissions'=>array($id=>$submission));

                    } else {
                        // the item is already there, so we add one to it's count
                        $temp_array[$course][$modname][$id]['submissions'][] = $submission;
                        //$temp_array[$course][$modname][$id]['count']++;
                    }

                }
           
            }

        } // should have a nested set of arrays with all data now.


        // Now, filter out any that don't belong.

        // remove items that do not belong because students have unenrolled
        // remove assessments where the person does not have permission to grade
        foreach ($temp_array as $courseid => $course) {

            $this->get_course_students($courseid);

            foreach ($this->modules as $modname => $module) {

                foreach ($course[$modname] as $type) {

                    foreach ($type as $ass_id => $assessment_item) {


                        //HERE
                        if (!$this->assessment_grading_permission($type, $assessment->cmid)) {
                            unset($type[$ass_id]);
                        }

                        foreach ($assessment_item['submissions'] as $sub_id => $submission) {
                            if(!in_array($submission->userid, $this->student_array)) {
                                unset($assessment_item['submissions'][$sub_id]);
                            }
                        }
                    }
                }
            }
        }

        

        // get groups settings
            $check = $this->get_groups_settings($type, $submission->id);

            // ignore if the group is set to hidden
            if ($check && ($check->showhide == 3)) { continue; }

            // if there are no settings (default is show), 'display by group' is selected and the group matches, or 'display all' is selected, count it.
            if ((!$check) || ($check && $check->showhide == 2 && $this->check_group_membership($check->groups, $submission->userid)) || ($check && $check->showhide == 1)) {
                $count++;
            }



// count for courses

        // Make the actual output, if there is any
  
        if (count($temp_array) > 0) {

            $output = '<ul>';

            foreach ($temp_array as $courseid => $coursedata) {
                $output .= '<li>'.$this->courses[$courseid]->shortname.'</li><ul>';
                foreach ($this->modules as $module) {
                    $modname = $module->name;
                    
                        if (array_key_exists($modname, $coursedata)) {
                            foreach ($coursedata[$modname] as $item) {

                                // get the link data
                                // add CSS classes
                                $output .= '<li><a href="'.$CFG->wwwroot;
                                switch ($module->name) {

                                case 'assignment':
                                    
                                    $output .= '/mod/assignment/submissions.php?id='.$item['cmid'];
                                    break;

                                case 'forum':
                                    $output .= '/mod/forum/view.php?id='.$item['cmid'];
                                    break;

                                case 'workshop':
                                    $output .= '/mod/workshop/view.php?id='.$item['cmid'];
                                    break;

                                case 'quiz':
                                    $output .= '/mod/quiz/report.php?q='.$item['id'];
                                    break;

                                case 'journal':
                                    $output .= '/mod/journal/report.php?id='.$item['cmid'];


                                }
                                $output .= '" >'.$item['name'].' ('.$item['count'].'</a>';
                                

                            switch ($modname) {

                                case 'assignment':



                            }


                            //.$item['name'].' ('.$item['count'].')</li>';
                            }
                        }
                
                }
                $output .= '</ul>';
            }
            $output .= '</ul>';
        }

        $this->output = $output;
      

    }// end function

}












?>