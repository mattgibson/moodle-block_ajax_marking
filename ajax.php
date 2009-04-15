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

// TODO needed if possible but doesn't yet work as no session created yet
// if (!confirm_sesskey()) {
//       echo 'session error';
// }

include("lib.php");


class ajax_response extends ajax_marking_functions {

   /**
     * This function takes the POST data, makes variables out of it, then chooses the correct function to
     * deal with the request, before printing the output.
     * @global <type> $CFG
     */

    function ajax_response() {
    // constructor retrieves GET data and works out what type of AJAX call has been made before running the correct function
    // TODO: should check capability with $USER here to improve security. currently, this is only checked when making course nodes.

        global $CFG, $USER;

        // TODO - not necessary to load all things for all types. submissions level doesn't need the data for all the other types
        $this->get_variables();
        $this->initial_setup();

        switch ($this->type) {
        
            case "main":
               $this->courses();
               break;

            case "config_main":
                $this->config = true;
                $this->config_courses();
                break;

            case "course":
                $courseid = $this->id;
                // we must make sure we only get work from enrolled students
                $this->get_course_students($courseid);

                $this->output = '[{"type":"course"}';

                $this->assignment->course_assessment_nodes($courseid);
                $this->forum->course_assessment_nodes($courseid);
                $this->quiz->course_assessment_nodes($courseid);
                $this->journal->course_assessment_nodes($courseid);
                $this->workshop->course_assessment_nodes($courseid);

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
                $this->config_groups();
                break;

            case "config_set":
                $this->config_set();
                break;

            case "config_check":
                $this->config_check();
                break;

            case "config_group_save":
                $this->config_group_save();
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
if (isset($response)) {
    unset($response);
}
$AMB_AJAX_response = new ajax_response;


?>