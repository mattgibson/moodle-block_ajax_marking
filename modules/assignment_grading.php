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
 * @package   blocks-ajax_marking
 * @copyright 2008-2011 Matt Gibson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die();
}

/**
 * Wrapper for the module_base class which adds the parts that deal with the assignment module.
 *
 * It adds these functions to the module_base class, so that the assignment_functions object can
 * then provide the required data through a standard interface (although there is scope for the
 * interface to be extended or scaled back for modules that need more or less than 3 levels of nodes
 * e.g. the quiz module has extra functions because it has an extra level for quiz questions within
 * each quiz and the journal module has only two levels because it doesn't show students work
 * individually, only aggregated). All module specific files are included at the start of each
 * request and a module object is instatiated ready to be used. For efficiency, only installed
 * modules which have grading code available are included & instatiated, so there is a list kept in
 * the block's config data saying which modules have available module_grading.php files based on a
 * search conducted each time the block is upgraded by the {@link amb_update_modules()} function.
 *
 * @copyright 2008 Matt Gibson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_ajax_marking_assignment extends block_ajax_marking_module_base {

    /**
     * Constuctor. Needs to be duplicated in all modules, so best put in parent. PHP4 issue though.
     *
     * The aim is to pass in the main ajax_marking_functions object by reference, so that its
     * properties are accessible
     *
     * @param object $reference the parent object to be referred to
     * @return void
     */
    function __construct() {

        // must be the same as the DB modulename
        $this->modulename = 'assignment';
        $this->moduleid   = $this->get_module_id();
        $this->capability = 'mod/assignment:grade';
        $this->icon       = 'mod/assignment/icon.gif';
        
        // what nodes, if any come after the course and assessment ones for this module?
        $this->callbackfunctions  = array(
            'submissions'
        );
    }

    /**
     * function called from courses() which returns all
     * unmarked assignments from all courses ready for sorting through and counting
     *
     * @return bool
     */
    function get_all_unmarked($courseids) {

        global $DB;

        list($coursesql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        
        list($displayjoin, $displaywhere) = $this->get_display_settings_sql('a', 's');

        $sql = "SELECT s.id as subid, s.userid, a.course, a.name, a.intro as description, a.id, c.id as cmid
                  FROM {assignment} a
            INNER JOIN {course_modules} c
                    ON a.id = c.instance
            INNER JOIN {assignment_submissions} s
                    ON s.assignment = a.id
                       {$displayjoin}
                 WHERE c.module = :coursemodule
                   AND c.visible = 1
                   AND a.course $coursesql
                   AND s.timemarked < s.timemodified
               AND NOT ((a.resubmit = 0 AND s.timemarked > 0)
                    OR (a.assignmenttype = 'upload' AND s.data2 != 'submitted'))
                       {$displaywhere}
              ORDER BY a.id";

        $params['coursemodule'] = $this->moduleid;
        
        return $DB->get_records_sql($sql, $params);
    }
    
    /**
     * See documentation for abstract function in superclass
     * 
     * @global type $DB
     * @return array of objects
     */
    function get_course_totals() {
        
        global $DB;

        // TODO - need to check for enrolment status. Don't want to include unenrolled students
        
        list($displayjoin, $displaywhere) = $this->get_display_settings_sql('a', 's.userid');
        
        $sql = "SELECT a.course AS courseid, COUNT(s.id) AS count
                  FROM {assignment} a
            INNER JOIN {course_modules} c
                    ON a.id = c.instance
            INNER JOIN {assignment_submissions} s
                    ON s.assignment = a.id
                       {$displayjoin}
                 WHERE c.module = :coursemodule
                   AND c.visible = 1
                   AND s.timemarked < s.timemodified
               AND NOT ((a.resubmit = 0 AND s.timemarked > 0)
                    OR (a.assignmenttype = 'upload' AND s.data2 != 'submitted'))
                       {$displaywhere}
              GROUP BY a.course";

        $params = array();
        $params['coursemodule'] = $this->moduleid;
        
        return $DB->get_records_sql($sql, $params);
        
    }

    /**
     *fetches all of the unmarked assignment submissions for a course
     *
     * @param int $courseid The courseid from the main database.
     * @return object The results straight from the DB
     */
    function get_all_course_unmarked($courseid) {

        global $DB;
        
        $unmarked = '';

        $context = get_context_instance(CONTEXT_COURSE, $courseid);
        list($studentsql, $params) = $this->get_role_users_sql($context);

        //list($usql, $params) = $DB->get_in_or_equal($this->mainobject->students->ids->$courseid, SQL_PARAMS_NAMED);

        $sql = "SELECT s.id as subid, s.userid, a.id, a.name,
                       a.course, a.intro as description, c.id as cmid
                  FROM {assignment} a
            INNER JOIN {course_modules} c
                    ON a.id = c.instance
            INNER JOIN {assignment_submissions} s
                    ON s.assignment = a.id
            INNER JOIN ({$studentsql}) stsql
                    ON s.userid = stsql.id
                 WHERE c.module = :coursemodule 
                   AND c.visible = 1
                   AND a.course = $courseid
                   AND s.timemarked < s.timemodified
               AND NOT ((a.resubmit = 0 AND s.timemarked > 0)
                        OR (a.assignmenttype = 'upload'  AND s.data2 != 'submitted'))
              ORDER BY a.id";
        $params['coursemodule'] = $this->moduleid;

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * procedure for assignment submissions. We have to deal with several situations -
     * just show all the submissions at once (default)
     * divert this request to the groups function if the config asks for that
     * show the selected group's students
     *
     * @param int $assignmentid 
     * @param int $groupid The id of the group with the ajax request may have passed through
     * @return void
     */
    function submissions($assignmentid, $groupid=null) {

        global $CFG, $USER, $DB;
        
        $data = new stdClass;
        $nodes = array();
        
        $data->nodetype = 'submission';

        // need to get course id in order to retrieve students
        $assignment = $DB->get_record('assignment', array('id' => $assignmentid));
        $courseid = $assignment->course;

        // so we have cached student details
        $course = $DB->get_record('course', array('id' => $courseid));

        //permission to grade?
        $params = array('module' => $this->moduleid, 'instance' => $assignment->id);
        $coursemodule = $DB->get_record('course_modules', $params);
        $modulecontext = get_context_instance(CONTEXT_MODULE, $coursemodule->id);

        if (!has_capability($this->capability, $modulecontext, $USER->id)) {
            return;
        }

        $context = get_context_instance(CONTEXT_COURSE, $courseid);
        list($studentsql, $params) = $this->get_role_users_sql($context);

        $sql = "SELECT s.id as subid, s.userid, s.timemodified, c.id as cmid, u.firstname, u.lastname
                  FROM {assignment_submissions} s
            INNER JOIN {user} u
                    ON s.userid = u.id
            INNER JOIN {course_modules} c
                    ON s.assignment = c.instance
            INNER JOIN {assignment} a
                    ON s.assignment = a.id
            INNER JOIN ({$studentsql}) stsql
                    ON s.userid = stsql.id 
                 WHERE s.assignment = :assignment
                   AND s.timemarked < s.timemodified
               AND NOT ((a.resubmit = 0 AND s.timemarked > 0)
                       OR (a.assignmenttype = 'upload' AND s.data2 != 'submitted'))
                   AND c.module = :coursemodule
              ORDER BY timemodified ASC";
        $params['assignment'] = $assignmentid;
        $params['coursemodule'] = $this->moduleid;
        $submissions = $DB->get_records_sql($sql, $params);

        if ($submissions) {

            // If we are not making the submissions for a specific group, run the group filtering
            // function to see if the config settings say display by groups and display them if they
            // are (returning false). If there are no groups, the function will return true and we
            // carry on, but if the config settings say 'don't display' then it will return false
            // and we skip this assignment
            if (!$groupid) {

                $group_filter = block_ajax_marking_assessment_groups_filter($submissions,
                                                                            $this->modulename,
                                                                            $assignmentid,
                                                                            $assignment->course);

                if (!$group_filter) {
                    return;
                }
            }

            foreach ($submissions as $submission) {
                
                // if we are displaying for just one group, skip this submission if it doesn't match
                if ($groupid && !block_ajax_marking_is_member_of_group($groupid, $submission->userid)) {
                    continue;
                }

                // sort out the time info
                $summary = block_ajax_marking_make_time_summary(time() - $submission->timemodified);

                $nodes[] = block_ajax_marking_make_submission_node(array(
                        'name'            => fullname($submission),
                        'userid'          => $submission->userid,
                        'coursemoduleid'  => $submission->cmid,
                        'uniqueid'        => 'assignment_final'.$submission->cmid.'-'.$submission->userid,
                        'summary'         => $summary,
                        'seconds'         => (time() - $submission->timemodified),
                        'modulename'      => $this->modulename,
                        'time'            => $submission->timemodified));
            }
            
            return array($data, $nodes);
        }
    }

     /**
     * gets all assignments that could potentially have
     * graded work, even if there is none there now. Used by the config tree.
     *
     * @return void
     */
    function get_all_gradable_items($courseids) {

        global $CFG, $DB;
        list($usql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);

        $sql = "SELECT a.id, a.name, a.intro as summary, a.course, c.id as cmid
                  FROM {assignment} a
            INNER JOIN {course_modules} c
                    ON a.id = c.instance
                 WHERE c.module = :moduleid
                   AND c.visible = 1
                   AND a.course $usql
              ORDER BY a.id";
        $params['moduleid'] = $this->moduleid;
        $assignments = $DB->get_records_sql($sql, $params);
        $this->assessments = $assignments;

    }

    /**
     * Makes a link for the pop up window so the work can be marked
     *
     * @param object $item a submission object
     * @return string
     */
    function make_html_link($item) {

        global $CFG;
        $address = $CFG->wwwroot.'/mod/assignment/submissions.php?id='.$item->cmid;
        return $address;
    }
    
    /**
     * Slightly neater than having a separate file for the js that we include is to have this as a 
     * static function here. 
     * 
     * @return string the javascript to append to module.js.php
     */
    static function extra_javascript() {
        // Get the IDE to do proper script highlighting
        if(0) { ?><script><?php } 
        
        ?>
        /**
         * @param object clickednode the node of the tree that was clicked to open the popup
         */
M.block_ajax_marking.assignment = (function() {

    return {

        pop_up_arguments : function() {
            return 'menubar=0,location=0,scrollbars,resizable,width=900,height=630';
        },

        // pop_up_post_data : function (clickednode) {
        //    return 'id='+node.data.aid+'&userid='+clickednode.data.submissionid+'&mode=single&offset=0';
        // };

        pop_up_closing_url : function() {
            return '/mod/assignment/submissions.php';
        },

        pop_up_opening_url : function (clickednode) {

            var url = '/mod/assignment/submissions.php?id='+clickednode.data.coursemoduleid
                      +'&userid='+clickednode.data.userid+'&mode=single&offset=0';
            return url;
            //return '';
        },

        extra_ajax_request_arguments : function () {
            return '';
        },

        /**
         * this function is called every 100 milliseconds once the assignment pop up is called
         * and tries to add the onclick handlers until it is successful. There are a few extra
         * checks in the following functions that appear to be redundant but which are
         * necessary to avoid errors. The part of /mod/assignment/lib.php at line 509 tries to
         * update the main window with $this->update_main_listing($submission). This fails because
         * there is no main window with the submissions table as there would have been if the pop
         * up had been generated from the submissions grading screen. To avoid the errors,
         *
         * NOTE: the offset system for saveandnext depends on the sort state having been stored in the
         * $SESSION variable when the grading screen was accessed (which may not have happened, as we
         * are not coming from the submissions.php grading screen or may have been a while ago). The
         * sort reflects the last sort mode the user asked for when ordering the list of pop-ups, e.g.
         * by clicking on the firstname column header. I have not yet found a way to alter this variable
         * using javascript - ideally, the sort would be the same as it is in the list presented in the
         * marking block. Until a work around is found, the save and next function is be a bit wonky,
         * sometimes showing next when there is only one submission, so I have hidden it.
         */
        alter_popup : function(clickednode) {

            var submitelements  = '';
            var saveelements    = '';
            var nextelements    = '';

            this.counter = 0;


            //do nothing if it's been shut
            if (M.block_ajax_marking.pop_up_holder.closed) {
                window.clearInterval(M.block_ajax_marking.timerVar);
                return true;
            }

            // is the pop up even open? Hard to check if the elements of the DOM are not present yet,
            // so we check for the existence of each one before trying to add the onclick.
            if (M.block_ajax_marking.popupholder.document) {

                var buttons = YAHOO.util.Dom.getElementsByClassName('buttons', 'div', M.block_ajax_marking.popupholder.document);

                if(buttons.length > 0) {

                    // hide the unecessary buttons
                    YAHOO.util.Dom.setStyle(buttons[0].childNodes[2], 'display', 'none');
                    YAHOO.util.Dom.setStyle(buttons[0].childNodes[3], 'display', 'none');

                    // TODO pass the value needed in obj?
                    // TODO can 'this' be used to get the data object from the node?

                    var functiontext  = "return M.block_ajax_marking.markingtree.remove_node_from_tree('', '"
                                      + clickednode.data.uniqueid+"'); ";
                    YAHOO.util.on(buttons[0].childNodes[0], 'click', functiontext);

                    return true;
                }
            }

            return false;

            // cancel the timer loop for this function
            window.clearInterval(M.block_ajax_marking.popuptimer);

        }
    };
})();

// included here because the assignment pop up expects to have it

var assignment = {};

function setNext(){
    document.getElementById('submitform').mode.value = 'next';
    document.getElementById('submitform').userid.value = assignment.nextid;
}

function saveNext(){
    document.getElementById('submitform').mode.value = 'saveandnext';
    document.getElementById('submitform').userid.value = assignment.nextid;
    document.getElementById('submitform').saveuserid.value = assignment.userid;
    document.getElementById('submitform').menuindex.value = document.getElementById('submitform').grade.selectedIndex;
}

function initNext(nextid, usserid) {
    assignment.nextid = nextid;
    assignment.userid = userid;
}
        
        <?php
        
        // Get the IDE to do proper script highlighting
        if(0) { ?></script><?php } 
        
    }


}