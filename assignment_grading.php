<?php

require_login(1, false);
/**
 * preparing for the modularisation in future, all assignment relateed code should live in here
 */
class assignment_functions extends module_base {

    /**
     * Constuctor. Needs to be duplicated in all modules, so best put in parent. PHP4 issue though.
     *
     * The aim is to pass in the main ajax_marking_functions object by reference, so that its properties
     * are accessible
     *
     */
    function assignment_functions(&$reference) {
        $this->reference = $reference;
        // must be the same as th DB modulename
        $this->type = 'assignment';
        $this->capability = 'mod/assignment:grade';
    }

    // procedure to fetch data and store it in the object

     /**
     * function called from courses() which returns all
     * unmarked assignments from all courses ready for sorting through
     * @return Boolean
     */
    function get_all_unmarked() {

       
        global $CFG;
        $sql = "
            SELECT s.id as subid, s.userid, a.course, a.name,  a.id, c.id as cmid
            FROM
                {$CFG->prefix}assignment a
            INNER JOIN {$CFG->prefix}course_modules c
                 ON a.id = c.instance
            LEFT JOIN {$CFG->prefix}assignment_submissions s
                 ON s.assignment = a.id
            WHERE c.module = {$this->reference->module_ids['assignment']->id}
            AND c.visible = 1
            AND a.course IN ({$this->reference->course_ids})
            AND s.timemarked < s.timemodified
            AND NOT (a.resubmit = 0 AND s.timemarked > 0)
            ORDER BY a.id
         ";
         $submissions = get_records_sql($sql);
         return $submissions;
           
         
    }


     /**
     * gets all assignments that could potentially have
     * graded work, even if there is none there now. Used by the config tree.
     * @return <type>
     */
    function get_all_items() {

        global $CFG;
        if (!$this->assignments) {
            $sql = "
                SELECT  a.id, a.name, a.description as summary, a.course, c.id as cmid
                FROM
                    {$CFG->prefix}assignment a
                INNER JOIN {$CFG->prefix}course_modules c
                     ON a.id = c.instance
                WHERE c.module = {$this->reference->module_ids['assignment']->id}
                AND c.visible = 1
                AND a.course IN ($this->reference->course_ids)
                ORDER BY a.id
             ";

             $assignments = get_records_sql($sql);
             $this->items = $assignments;
             return $assignments;
         } else {
             return $this->assignments;
         }
    }

    // fetches all of the unmarked assignment submissions for a course
    function get_all_course_unmarked($courseid) {

        global $CFG;
        $unmarked = '';
        
             $sql = "SELECT s.id as subid, s.userid, a.id, a.name, a.description, c.id as cmid  FROM
                    {$CFG->prefix}assignment a
                INNER JOIN {$CFG->prefix}course_modules c
                     ON a.id = c.instance
                LEFT JOIN {$CFG->prefix}assignment_submissions s
                     ON s.assignment = a.id
                WHERE c.module = {$this->reference->module_ids['assignment']->id}
                AND c.visible = 1
                AND a.course = $courseid
                AND s.timemarked < s.timemodified
                AND NOT (a.resubmit = 0 AND s.timemarked > 0)
                AND s.userid IN({$this->reference->student_ids->$courseid})
                ORDER BY a.id
              ";

            $unmarked = get_records_sql($sql, 'assignment');
            return $unmarked;
        


    }


    /**
	 * procedure for assignment submissions. We have to deal with several situations -
	 * just show all the submissions at once (default)
	 * divert this request to the groups function if the config asks for that
	 * show the selected group's students
	 */

    function submissions() {
        
        global $CFG, $USER;

        // need to get course id in order to retrieve students
        $assignment = get_record('assignment', 'id', $this->reference->id);
        $courseid = $assignment->course;

        //permission to grade?
        $coursemodule = get_record('course_modules', 'module', '1', 'instance', $assignment->id) ;
        $modulecontext = get_context_instance(CONTEXT_MODULE, $coursemodule->id);
        if (!has_capability($this->capability, $modulecontext, $USER->id)) {
            return;
        }

        $this->reference->get_course_students($courseid);

        $sql = "SELECT s.id as subid, s.userid, s.timemodified, c.id as cmid
                FROM {$CFG->prefix}assignment_submissions s
                INNER JOIN {$CFG->prefix}course_modules c
                     ON s.assignment = c.instance
                INNER JOIN {$CFG->prefix}assignment a
                     ON s.assignment = a.id
                WHERE s.assignment = {$this->reference->id}
                    AND s.userid IN ({$this->reference->student_ids->$courseid})
                    AND s.timemarked < s.timemodified
                    AND NOT (a.resubmit = 0 AND s.timemarked > 0)
                    AND c.module = {$this->reference->module_ids['assignment']->id}
                ORDER BY timemodified ASC";

        $submissions = get_records_sql($sql);

        if ($submissions) {

            // If we are not making the submissions for a specific group, run the group filtering function to
            // see if the config settings say display by groups and display them if they are (returning false). If there are no
            // groups, the function will return true and we carry on, but if the config settings say 'don't display'
            // then it will return false and we skip this assignment
            if(!$this->reference->group) {
               $group_filter = $this->reference->assessment_groups_filter($submissions, $this->type, $this->reference->id);
               if (!$group_filter) {
                   return;
               }
            }

            // begin json object
            $this->reference->output = '[{"type":"submissions"}';

            foreach ($submissions as $submission) {
            // add submission to JSON array of objects
                if (!isset($submission->userid)) {
                    continue;
                }

                // if we are displaying for just one group, skip this submission if it doesn't match
                if ($this->reference->group && !$this->reference->check_group_membership($this->reference->group, $submission->userid)) {
                    continue;
                }
                
                $name = $this->reference->get_fullname($submission->userid);
                
                // sort out the time info
                $now     = time();
                $seconds = ($now - $submission->timemodified);
                $summary = $this->reference->make_time_summary($seconds);
                
                $this->reference->make_submission_node($name, $submission->userid, $submission->cmid, $summary, 'assignment_answer', $seconds, $submission->timemodified);
         
            }
            $this->reference->output .= "]"; // end JSON array
               
        }
    }


    // count for all assignments in a course

    // nodes for all assignments in a course + counts and provide link data

    // student nodes for a single assignment

    //

}

?>