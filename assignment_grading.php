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
 * This is the file that contains all the code specific to the assignment module.
 *
 * @package   block-ajax_marking
 * @copyright 2008 Matt Gibson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_login(0, false);

/**
 * Wrapper for the module_base class which adds the parts that deal with the assignment module.
 *
 * It adds these functions to the module_base class, so that the assignment_functions object can then provide the required data through
 * a standard interface (although there is scope for the interface to be extended or scaled back for modules
 * that need more or less than 3 levels of nodes e.g. the quiz module has extra functions because it
 * has an extra level for quiz questions within each quiz and the journal module has only two levels because
 * it doesn't show students work individually, only aggregated). All module specific files are included at the
 * start of each request and a module object is instatiated ready to be used. For efficiency, only installed
 * modules which have grading code available are included & instatiated, so there is a list kept in the block's
 * config data saying which modules have available module_grading.php files based on a search conducted each time
 * the block is upgraded by the {@link AMB_update_modules()} function.
 *
 * @copyright 2008 Matt Gibson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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

        $this->mainobject = $reference;
        // must be the same as th DB modulename
        $this->type       = 'assignment';
        $this->capability = 'mod/assignment:grade';
        $this->levels     = 3;
        $this->icon       = 'mod/assignment/icon.gif';

    }

    // procedures to fetch data and store it in the object

    /**
     * function called from courses() which returns all
     * unmarked assignments from all courses ready for sorting through and counting
     * @return Boolean
     */
    function get_all_unmarked() {

        global $CFG;
        $sql = "
            SELECT s.id as subid, s.userid, a.course, a.name, a.description, a.id, c.id as cmid
            FROM
                {$CFG->prefix}assignment a
            INNER JOIN {$CFG->prefix}course_modules c
                 ON a.id = c.instance
            LEFT JOIN {$CFG->prefix}assignment_submissions s
                 ON s.assignment = a.id
            WHERE c.module = {$this->mainobject->modulesettings['assignment']->id}
            AND c.visible = 1
            AND a.course IN ({$this->mainobject->course_ids})
            AND s.timemarked < s.timemodified
            AND NOT (a.resubmit = 0 AND s.timemarked > 0)
            ORDER BY a.id
         ";
         $this->all_submissions = get_records_sql($sql);
         return true;
    }


    /**
     *fetches all of the unmarked assignment submissions for a course
     * @global <type> $CFG
     * @param int $courseid The courseid from the main database.
     * @return object The results straight from the DB
     */
    function get_all_course_unmarked($courseid) {

        global $CFG;
        $unmarked = '';
        
             $sql = "SELECT s.id as subid, s.userid, a.id, a.name, a.course, a.description, c.id as cmid  FROM
                    {$CFG->prefix}assignment a
                INNER JOIN {$CFG->prefix}course_modules c
                     ON a.id = c.instance
                LEFT JOIN {$CFG->prefix}assignment_submissions s
                     ON s.assignment = a.id
                WHERE c.module = {$this->mainobject->modulesettings['assignment']->id}
                AND c.visible = 1
                AND a.course = $courseid
                AND s.timemarked < s.timemodified
                AND NOT (a.resubmit = 0 AND s.timemarked > 0)
                AND s.userid IN({$this->mainobject->student_ids->$courseid})
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
        $assignment = get_record('assignment', 'id', $this->mainobject->id);
        $courseid = $assignment->course;

        //permission to grade?
        $coursemodule = get_record('course_modules', 'module', '1', 'instance', $assignment->id) ;
        $modulecontext = get_context_instance(CONTEXT_MODULE, $coursemodule->id);
        if (!has_capability($this->capability, $modulecontext, $USER->id)) {
            return;
        }

        $this->mainobject->get_course_students($courseid);

        $sql = "SELECT s.id as subid, s.userid, s.timemodified, c.id as cmid
                FROM {$CFG->prefix}assignment_submissions s
                INNER JOIN {$CFG->prefix}course_modules c
                     ON s.assignment = c.instance
                INNER JOIN {$CFG->prefix}assignment a
                     ON s.assignment = a.id
                WHERE s.assignment = {$this->mainobject->id}
                    AND s.userid IN ({$this->mainobject->student_ids->$courseid})
                    AND s.timemarked < s.timemodified
                    AND NOT (a.resubmit = 0 AND s.timemarked > 0)
                    AND c.module = {$this->mainobject->modulesettings['assignment']->id}
                ORDER BY timemodified ASC";

        $submissions = get_records_sql($sql);

        if ($submissions) {

            // If we are not making the submissions for a specific group, run the group filtering function to
            // see if the config settings say display by groups and display them if they are (returning false). If there are no
            // groups, the function will return true and we carry on, but if the config settings say 'don't display'
            // then it will return false and we skip this assignment
            if(!$this->mainobject->group) {
               $group_filter = $this->mainobject->assessment_groups_filter($submissions, $this->type, $this->mainobject->id, $assignment->course);
               if (!$group_filter) {
                   return;
               }
            }

            // begin json object
            $this->mainobject->output = '[{"type":"submissions"}';

            foreach ($submissions as $submission) {
            // add submission to JSON array of objects
                if (!isset($submission->userid)) {
                    continue;
                }

                // if we are displaying for just one group, skip this submission if it doesn't match
                if ($this->mainobject->group && !$this->mainobject->check_group_membership($this->mainobject->group, $submission->userid)) {
                    continue;
                }
                
                $name = $this->mainobject->get_fullname($submission->userid);
                
                // sort out the time info
                $now     = time();
                $seconds = ($now - $submission->timemodified);
                $summary = $this->mainobject->make_time_summary($seconds);
                
                $this->mainobject->make_submission_node($name, $submission->userid, $submission->cmid, $summary, 'assignment_answer', $seconds, $submission->timemodified);
         
            }
            $this->mainobject->output .= "]"; // end JSON array
               
        }
    }


     /**
     * gets all assignments that could potentially have
     * graded work, even if there is none there now. Used by the config tree.
     * @return <type>
     */
    function get_all_gradable_items() {

        global $CFG;
   
        $sql = "
            SELECT  a.id, a.name, a.description as summary, a.course, c.id as cmid
            FROM
                {$CFG->prefix}assignment a
            INNER JOIN {$CFG->prefix}course_modules c
                 ON a.id = c.instance
            WHERE c.module = {$this->mainobject->modulesettings['assignment']->id}
            AND c.visible = 1
            AND a.course IN ({$this->mainobject->course_ids})
            ORDER BY a.id
         ";

         $assignments = get_records_sql($sql);
         $this->assessments = $assignments;
  
    }

    function make_html_link($item) {

        global $CFG;
        $address = $CFG->wwwroot.'/mod/assignment/submissions.php?id='.$item->cmid;
        return $address;
    }

    // count for all assignments in a course

    // nodes for all assignments in a course + counts and provide link data

    // student nodes for a single assignment

    //

}

?>