<?php
/**
 *  Block AJAX Marking -  Copyright Matt Gibson 2008
 *  
 *  This file contains the procedures for getting stuff from the database
 *  in order to create the nodes of the marking block YUI tree. 
 * 
 * Released under terms of the GPL v 3.0
 */
include("../../config.php");
require_login(1, false);


// TODO needed if possible but doesn't yet work as no session created yet?
// if (!confirm_sesskey()) {
//       echo 'session error';
// }

include("lib.php");


class ajax_marking_response extends ajax_marking_functions {

   /**
     * This function takes the POST data, makes variables out of it, then chooses the correct function to
     * deal with the request, before printing the output.
     * @global <type> $CFG
     */

    function ajax_marking_response() {
    // constructor retrieves GET data and works out what type of AJAX call has been made before running the correct function
    // TODO: should check capability with $USER here to improve security. currently, this is only checked when making course nodes.

        global $CFG, $USER;

        // TODO - not necessary to load all things for all types. submissions level doesn't need the data for all the other types
        $this->get_variables();
        $this->initial_setup();

        switch ($this->type) {
        
        
            //  generate the list of courses when the tree is first prepared. Currently either makes a config tree or a main tree
            case "main":
                
                $course_ids = NULL;

                // admins will have a problem as they will see all the courses on the entire site
                // TODO - this has big issues around language. role names will not be the same in diffferent translations.

                // begin JSON array
                $this->output = '[{"type":"main"}';

                // get all unmarked submissions for all courses, so they can be sorted out later
                //$this->get_main_level_data();

                // iterate through each course, checking permisions, counting relevant assignment submissions and
                // adding the course to the JSON output if any appear
                foreach ($this->courses as $course) {

                    // show nothing if the course is hidden
                    if (!$course->visible == 1)  {
                        continue;
                    }

                    // set course assessments counter to 0
                    $count = 0;

                    $courseid = $course->id;
                    // we must make sure we only get work from enrolled students
                    $this->get_course_students($courseid);

                    // If there are no students, there's no point counting
                    if (!$this->student_ids->$courseid) {
                        continue;
                    }
                    // loop through each module, getting a count for this course id from each one.
                    foreach ($this->modules as $module) {
                        $count += $this->$module->count_course_submissions($courseid);
                    }

                    // TO DO: need to check in future for who has been assigned to mark them (new groups stuff) in 1.9
                    //$coursecontext = get_context_instance(CONTEXT_COURSE, $course->id);

                    if ($count > 0 || $this->config) { // there are some assessments	, or its a config tree, so we include the course always.

                        // now add course to JSON array of objects
                        $cid  = $course->id;

                        $this->output .= ','; // add a comma if there was a preceding course
                        $this->output .= '{';

                        $this->output .= '"id":"'.$cid.'",';
                        $this->output .= '"type":"course",';
                        $this->output .= '"label":"'.$this->add_icon('course').$this->clean_name_text($course->shortname, 0);
                        $this->output .= ($this->config) ? '",' : ' ('.$count.')",';
                        // name is there to allow labels to be reconstructed with a new count after marked nodes are removed
                        $this->output .= '"name":"'.$this->add_icon('course').$this->clean_name_text($course->shortname, 0).'",';
                        $this->output .= '"title":"'.$this->clean_name_text($course->shortname, -2).'",';
                        $this->output .= '"summary":"'.$this->clean_name_text($course->shortname, -2).'",';
                        $this->output .= '"count":"'.$count.'",';
                        $this->output .= '"dynamic":"true",';
                        $this->output .= '"cid":"c'.$cid.'"';
                        $this->output .= '}';

                    } 
                } 
                $this->output .= "]"; //end JSON array

               break;

            case "config_main":

                // Makes the course list for the configuration tree. No need to count anything, just make the nodes
                // Might be possible to collapse it into the main one with some IF statements.

                $this->config = true;

                $this->output = '[{"type":"config_main"}';

                if ($this->courses) { // might not be any available

                    // tell each module to fetch all of the items that are gradable, even if they have no unmarked stuff waiting
                    foreach ($this->modules as $module) {
                        $this->$module->get_all_gradable_items();
                    }

                    foreach ($this->courses as $course) {
                        // iterate through each course, checking permisions, counting assignments and
                        // adding the course to the JSON output if anything is there that can be graded
                        $count = 0;

                        foreach ($this->modules as $module) {
                            if ($this->$module->assessments) {
                                $count = $count + $this->count_course_assessment_nodes($this->$module->assessments, $course->id, $module);
                            }
                        }

                        if ($count > 0) {

                            $this->output .= ','; // add a comma if there was a preceding course
                            $this->output .= '{';

                            $this->output .= '"id":"'       .$course->id.'",';
                            $this->output .= '"type":"config_course",';
                            $this->output .= '"title":"'    .$this->clean_name_text($course->shortname, -2).'",';
                            $this->output .= '"label":"'    .$this->add_icon('course').$this->clean_name_text($course->shortname, -2).'",';
                            // to be used for the title
                            $this->output .= '"label":"'    .$this->add_icon('course').$this->clean_name_text($course->shortname, -2).'",';
                            $this->output .= '"summary":"'  .$this->clean_name_text($course->shortname, -2).'",';
                            $this->output .= '"count":"'    .$count.'"';

                            $this->output .= '}';

                        }
                    }
                }
                $this->output .= ']';

                break;

            case "course":

                $courseid = $this->id;
                // we must make sure we only get work from enrolled students
                $this->get_course_students($courseid);

                $this->output = '[{"type":"course"}';

                foreach ($this->modules as $module) {
                    $this->$module->course_assessment_nodes($courseid);
                }

                $this->output .= "]";
                break;

            case "config_course":
                $this->get_course_students($this->id);

                $this->config = true;
                $this->output = '[{"type":"config_course"}';

                foreach ($this->modules as $module) {
                    $this->config_assessments($this->id, $module);
                }
                //$this->config_assessments($this->id, 'assignment');
                //$this->config_assessments($this->id, 'journal');
                //$this->config_assessments($this->id, 'workshop');
                //$this->config_assessments($this->id, 'forum');
                //$this->config_assessments($this->id, 'quiz');

                $this->output .= "]";
                break;

            case "assignment":
                $this->assignment->submissions();
                break;

            case "workshop":
                $this->workshop->submissions();
                break;

            case "forum":
                $this->forum->submissions();
                break;

            case "quiz_question":
                $this->quiz->submissions();
                break;

            case "quiz":
                $this->quiz->quiz_questions();
                break;

            case "config_groups":

               // writes to the db that we are to use config groups, then returns all the groups.
               // Called only when you click the option 2 of the config, so the next step is for the javascript
               // functions to build the groups checkboxes.

                $this->output = '[{"type":"config_groups"}'; 	// begin JSON array

                //first set the config to 'display by group' as per the ajax request (this is the option that was clicked)
                $this->make_config_data();
                if ($this->config_write()) {
                    // next, we will now return all of the groups in a course as an array,
                    $this->make_config_groups_radio_buttons($this->id, $this->assessmenttype, $this->assessmentid);
                } else {
                    $this->output .= ',{"result":"false"}';
                }
                $this->output .= ']';

                break;

            case "config_set":

                /**
                 * this is to save configuration choices from the radio buttons for 1 and 3 once
                 * they have been clicked. Needed as a wrapper
                 * so that the config_write bit can be used for the function above too
                 */

                $this->output = '[{"type":"config_set"}';
                $this->make_config_data();
                if($this->config_write()) {
                    $this->output .= ',{"result":"true"}]';
                } else {
                    $this->output .= ',{"result":"false"}]';
                }

                break;

            case "config_check":

               /**
                * this is to check what the current status of an assessment is so that
                * the radio buttons can be made with that option selected.
                * if its currently 'show by groups', we need to send the group data too.
                */

                $this->output = '[{"type":"config_check"}'; 	// begin JSON array

                $config_settings = $this->get_groups_settings($this->assessmenttype, $this->assessmentid);

                if ($config_settings) {
                    $this->output .= ',{"value":"'.$config_settings->showhide.'"}';
                    if ($config_settings->showhide == 2) {
                        $this->make_config_groups_radio_buttons($this->id, $this->assessmenttype, $this->assessmentid);
                    }
                } else {
                    // default to 'show'
                    $this->output .= ',{"value":"1"}';
                }
                $this->output .= ']';

                break;

            case "config_group_save":

                /**
                 * sets the display of a single group from the config screen when its checkbox is clicked. Then, it sends back a confirmation so
                 * that the checkbox can be un-greyed and marked as done
                 */

                $this->output = '[{"type":"config_group_save"},{'; 	// begin JSON array

                $this->make_config_data();
                        if($this->groups) {
                            $this->data->groups = $this->groups;
                        }
                if($this->config_write()) {
                    $this->output .= '"value":"true"}]';
                } else {
                    $this->output .= '"value":"false"}]';
                }

                break;

            case 'quiz_diagnostic':
                $this->get_course_students($courseid);
                $this->quizzes();
                break;

        }

        print_r($this->output);
    }
}


// initialise the object, beginning the response process
//if (isset($response)) {
 //   unset($response);
//}
$AMB_AJAX_response = new ajax_marking_response;


?>