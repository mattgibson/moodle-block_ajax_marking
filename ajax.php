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

        global $CFG;

        $this->get_variables();
        $this->initial_setup();



       // echo $this->teachers;

        switch ($this->type) {
        case "main":
           $this->courses();
           break;

       case "config_main":
            $this->config = true;
            $this->config_courses();
            break;

        case "course":
            $this->get_course_students($this->id); // we must make sure we only get work from enrolled students
            $courseid = $this->id;
            //echo $courseid;
             
            //echo $this->student_ids->$courseid;

            $this->output = '[{"type":"course"}'; // begin JSON array
            /*
            $sql = "SELECT s.id as subid, s.userid, a.id, a.name, a.description, c.id as cmid  FROM
                        {$CFG->prefix}assignment a
                    INNER JOIN {$CFG->prefix}course_modules c
                         ON a.id = c.instance
                    LEFT JOIN {$CFG->prefix}assignment_submissions s
                         ON s.assignment = a.id
                    WHERE c.module = {$this->module_ids['assignment']->id}
                    AND c.visible = 1
                    AND a.course = $this->id
                    AND s.timemarked < s.timemodified
                    AND NOT (a.resubmit = 0 AND s.timemarked > 0)
                    AND s.userid IN({$this->student_ids->$courseid})
                    ORDER BY a.id
                  ";
             
             */
            $this->modules->assignment->functions->course_assessments($courseid);
            //$this->course_assessments($sql, 'assignment');
            $sql = "
                    SELECT je.id as entryid, je.userid, j.intro as description, j.name, j.timemodified, j.id, c.id as cmid
                    FROM {$CFG->prefix}journal_entries je
                    INNER JOIN {$CFG->prefix}journal j
                       ON je.journal = j.id
                    INNER JOIN {$CFG->prefix}course_modules c
                       ON j.id = c.instance
                    WHERE c.module = {$this->module_ids['journal']->id}
                    AND c.visible = 1
                    AND j.assessed <> 0
                    AND je.modified > je.timemarked
                    AND je.userid IN({$this->student_ids->$courseid})
                    AND j.course = $this->id
                   ";
            $this->course_assessments($sql, 'journal');
            $sql = "SELECT s.id as submissionid, s.userid, w.id, w.name, w.course, w.description, c.id as cmid
                    FROM ( {$CFG->prefix}workshop w
                    INNER JOIN {$CFG->prefix}course_modules c
                        ON w.id = c.instance)
                    LEFT JOIN {$CFG->prefix}workshop_submissions s
                        ON s.workshopid = w.id
                    LEFT JOIN {$CFG->prefix}workshop_assessments a
                        ON (s.id = a.submissionid)
                    WHERE (a.userid != {$this->userid}
                        OR (a.userid = {$this->userid}
                            AND a.grade = -1))
                    AND c.module = {$this->module_ids['workshop']->id}
                    AND c.visible = 1
                    AND w.course = $this->id
                    AND s.userid IN ({$this->student_ids->$courseid})
                    ORDER BY w.id
                  ";
            $this->course_assessments($sql, 'workshop');
            /*
            $sql = "SELECT p.id as post_id, p.userid, d.firstpost, f.type, f.id, f.name, f.intro as description, c.id as cmid
                        FROM
                            {$CFG->prefix}forum f
                        INNER JOIN {$CFG->prefix}course_modules c
                             ON f.id = c.instance
                        INNER JOIN {$CFG->prefix}forum_discussions d
                             ON d.forum = f.id
                        INNER JOIN {$CFG->prefix}forum_posts p
                             ON p.discussion = d.id
                        LEFT JOIN {$CFG->prefix}forum_ratings r
                             ON  p.id = r.post
                        WHERE p.userid <> $this->userid
                            AND p.userid IN ({$this->student_ids->$courseid})
                            AND (((r.userid <> $this->userid) AND (r.userid NOT IN ($this->teachers))) OR r.userid IS NULL)

                            AND ((f.type <> 'eachuser') OR (f.type = 'eachuser' AND p.id = d.firstpost))
                            AND c.module = {$this->module_ids['forum']->id}
                            AND c.visible = 1
                            AND f.course = $this->id
                            AND f.assessed > 0
                        ORDER BY f.id
                  ";
            */
            $this->modules->forum->functions->course_assessments($courseid);
            //$this->course_assessments($sql, 'forum');
            $this->quizzes();

            $this->output .= "]"; // end JSON array
            break;

        case "config_course":
            $this->get_course_students($this->id); // we must make sure we only get work from enrolled students

            $this->config = true;
            $this->output = '[{"type":"config_course"}'; // begin JSON array

            $this->config_assessments($this->id, 'assignment');
            $this->config_assessments($this->id, 'journal');
            $this->config_assessments($this->id, 'workshop');
            $this->config_assessments($this->id, 'forum');
            $this->config_assessments($this->id, 'quiz');

            $this->output .= "]"; // end JSON array
            break;

        case "assignment":
            $this->modules->assignment->functions->submissions();
            //$this->assignment_submissions();
            break;

        case "workshop":
            $this->workshop_submissions();
            break;

        case "forum":
            $this->modules->forum->functions->submissions();
           // $this->forum_submissions();
            break;

        case "quiz_question":
            $this->quiz_submissions();
            break;

        case "quiz":
            $this->quiz_questions();
            break;

        case "journal_submissions":
            $this->journal_submissions();
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
            $this->get_course_students($this->id);
            $this->quizzes();
            break;

        } // end switch

        print_r($this->output);
    }


}





// initialise the object, beginning the response process
if (isset($response)) {
    unset($response);
}
$response = new ajax_response;


?>