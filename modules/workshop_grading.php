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
 * Class file for the workshop grading functions
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2008 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die();
}

/**
 * Provides marking funcionality for the workshop module
 *
 * @copyright 2008-2010 Matt Gibson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_ajax_marking_workshop extends block_ajax_marking_module_base {

    /**
     * Constructor
     *
     * @param object $mainobject the parent object passed in by reference
     * @return void
     */
    function __construct() {
        // must be the same as th DB modulename
        $this->modulename   = 'workshop';
        $this->moduleid     = $this->get_module_id();
        $this->capability   = 'mod/workshop:editdimensions';
        //$this->levels       = 2;
        $this->icon         = 'mod/workshop/icon.gif';
        $this->callbackfunctions    = array();
    }
    
    /**
     * See documentation for abstract function in superclass
     * 
     * @global type $DB
     * @return array of objects
     */
    function get_course_totals() {
        
        global $USER, $DB;
        
        list($displayjoin, $displaywhere)      = $this->get_display_settings_sql('w', 's.authorid');
        list($enroljoin, $enrolwhere, $params) = $this->get_enrolled_student_sql('w.course', 's.authorid');
        list($visiblejoin, $visiblewhere, $visibleparams) = $this->get_visible_sql('w');

        $sql = "SELECT w.course AS courseid, COUNT(s.id) as count
                  FROM {workshop} w
             LEFT JOIN {workshop_submissions} s
                    ON s.workshopid = w.id
             LEFT JOIN {workshop_assessments} a
                    ON (s.id = a.submissionid)
                       {$displayjoin}
                       {$enroljoin}
                       {$visiblejoin}
                 WHERE (a.reviewerid != :userid
                        OR (a.reviewerid = :userid2
                            AND a.grade = -1))
                       {$displaywhere}
                       {$enrolwhere}
                       {$visiblewhere}
              GROUP BY w.course";
        
        $params = array_merge($params, $visibleparams);
        $params['userid']   = $USER->id;
        $params['userid2']  = $USER->id;
        
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Gets all the unmarked stuff for a course
     *
     * @param int $courseid the id number of the course
     * @return array of results objects
     */
    function get_all_course_unmarked($courseid) {

        global $CFG, $USER, $DB;

        //list($usql, $params) = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED);

        $context = get_context_instance(CONTEXT_COURSE, $courseid);
        list($studentsql, $params) = $this->get_role_users_sql($context);

        $sql = "SELECT s.id as submissionid, s.authorid as userid, w.id, w.name, w.course,
                       w.intro as description, c.id as cmid
                  FROM ({workshop} w
            INNER JOIN {course_modules} c
                    ON w.id = c.instance)
             LEFT JOIN {workshop_submissions} s
                    ON s.workshopid = w.id
             LEFT JOIN {workshop_assessments} a
                    ON (s.id = a.submissionid)
            INNER JOIN ({$studentsql}) stsql
                    ON s.authorid = stsql.id
                 WHERE (a.reviewerid != :userid
                    OR (a.reviewerid = :userid2
                   AND a.grade = -1))
                   AND c.module = :moduleid
                   AND c.visible = 1
                   AND w.course = :courseid
              ORDER BY w.id";
        $params['userid']   = $USER->id;
        $params['userid2']  = $USER->id;
        $params['moduleid'] = $this->moduleid;
        $params['courseid'] = $courseid;
        $unmarked = $DB->get_records_sql($sql, $params);
        return $unmarked;
    }

    /**
     * Outputs the submission nodes. Note: deprecated in 2.0 as showing individual submissions to mark
     * seems less useful with the new model
     *
     * @param int $workshopid
     * @param int $groupid
     * @return void
     */
    function submissions($workshopid, $groupid) {

        global $CFG, $USER, $DB;

        $workshop = $DB->get_record('workshop', array('id' => $workshopid));
        $courseid = $workshop->course;

        $context = get_context_instance(CONTEXT_COURSE, $courseid);
        list($studentsql, $params) = $this->get_role_users_sql($context);

        $sql = "SELECT s.id, s.authorid as userid, s.title, s.timecreated, s.workshopid, u.firstname, u.lastname
                  FROM {workshop_submissions} s
            INNER JOIN {user} u
                    ON s.authorid = u.id
             LEFT JOIN {workshop_assessments} a
                    ON (s.id = a.submissionid)
            INNER JOIN {workshop} w
                    ON s.workshopid = w.id
            INNER JOIN ({$studentsql}) stsql
                    ON s.authorid = stsql.id
                 WHERE (a.reviewerid != :userid
                    OR (a.reviewerid = :userid2
                   AND a.grade = -1))
                   AND s.workshopid = :workshopid
                   AND w.assessmentstart < :now
              ORDER BY s.timecreated ASC";

        $params['userid']     = $USER->id;
        $params['userid2']    = $USER->id;
        $params['workshopid'] = $workshopid;
        $params['now'] = time();

        $submissions = $DB->get_records_sql($sql, $params);

        if ($submissions) {

            // if this is set to display by group, we divert the data to the groups() function
            if (!$groupid) {
                $group_filter = block_ajax_marking_assessment_groups_filter($submissions, 'workshop', $workshop->id, $workshop->course);

                if (!$group_filter) {
                    return;
                }
            }
            // otherwise, submissionids have come back, so it must be set to display all.

            // begin json object
            $output = '[{"callbackfunction":"submissions"}';

            foreach ($submissions as $submission) {

                // if we are displaying for a single group node, ignore those students in other groups
                if ($groupid && !block_ajax_marking_is_member_of_group($groupid, $submission->userid)) {
                    continue;
                }

                // sort out the time stuff
                $seconds = (time() - $submission->timecreated);
                $summary = block_ajax_marking_make_time_summary($seconds);

                // make the node
                $output .= block_ajax_marking_make_submission_node(array(
                        'name'           => fullname($submission),
                        'submissionid'   => $submission->id,
                        'workshopid'     => $workshopid,
                        'summary'        => $summary,
                        'seconds'        => $seconds,
                        'modulename'     => $this->modulename,
                        'timecreated'    => $submission->timecreated));
            }
            $output .= ']';
            echo $output;
        }
    }

    /**
     * gets all workshops for the config tree
     *
     * @return void
     */
    function get_all_gradable_items($courseids) {

        global $CFG, $DB;

        list($usql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);

        $sql = "SELECT w.id, w.course, w.name, w.intro as summary, c.id as cmid
                  FROM {workshop} w
            INNER JOIN {course_modules} c
                    ON w.id = c.instance
                 WHERE c.module = :moduleid
                   AND c.visible = 1
                   AND w.course $usql
              ORDER BY w.id";
        $params['moduleid'] = $this->moduleid;
        $workshops = $DB->get_records_sql($sql, $params);
        $this->assessments = $workshops;
    }

    /**
     * Makes the HTML link for the popup
     *
     * @param object $item that has the workshop's courseid as cmid property
     * @return string
     */
    function make_html_link($item) {

        global $CFG;
        $address = $CFG->wwwroot.'/mod/workshop/view.php?id='.$item->cmid;
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
            
M.block_ajax_marking.workshop = (function() {

    // TODO - did this cahnge work?
    
    
    return {
        
        pop_up_arguments : function() {
            return 'menubar=0,location=0,scrollbars,resizable,width=980,height=630';
        },
        
        //M.block_ajax_marking.workshop_final.pop_up_post_data = function (node) {
        //    return 'id='+node.data.aid+'&sid='+node.data.sid+'&redirect='+amVariables.wwwroot;
        //}
        
        pop_up_closing_url : function () {
            return '/mod/workshop/assess.php';
        },
        
        pop_up_opening_url : function (clickednode) {
            return '/mod/workshop/view.php?id='+clickednode.data.cmid;
        },

        extra_ajax_request_arguments : function () {
            return '';
        },
        /**
         * workshop pop up stuff
         * function to add workshop onclick stuff and shut the pop up after its been graded.
         * the pop -up goes to a redirect to display the grade, so we have to wait until
         * then before closing it so that the grade is processed properly.
         *
         * note: this looks odd because there are 2 things that needs doing, one after the pop up loads
         * (add onclicks)and one after it goes to its redirect (close window).it is easier to check for
         * a fixed url (i.e. the redirect page) than to mess around with regex stuff to detect a dynamic
         * url, so the else will be met first, followed by the if. The loop will keep running whilst the
         * pop up is open, so this is not very elegant or efficient, but should not cause any problems
         * unless the client is horribly slow. A better implementation will follow sometime soon.
         */
        alter_popup : function (clickednode) {
        
            var els ='';
            // check that the frames are loaded - this can vary according to conditions
            
            if (typeof M.block_ajax_marking.popupholder.frames[0] != 'undefined') {
            
                //var currenturl = M.block_ajax_marking.popupholder.frames[0].location.href;
               // var targeturl = amVariables.wwwroot+'/mod/workshop/assessments.php';
                
                if (currenturl != targeturl) {
                    // this is the early stage, pop up has loaded and grading is occurring
                    // annoyingly, the workshop module has not named its submit button, so we have to
                    // get it using another method as the 11th input
                    els = M.block_ajax_marking.popupholder.frames[0].document.getElementsByTagName('input');
                    
                    if (els.length == 11) {
                        // TODO - did this change work?
                        var functiontext = "return M.block_ajax_marking.markingtree.remove_node_from_tree("
                                         + "'/mod/workshop/assessments.php', '"
                                         + clickednode.data.uniqueid+"');";
                        els[10]['onclick'] = new Function(functiontext);
                        // els[10]["onclick"] = new Function("return M.block_ajax_marking.remove_node_from_tree('/mod/workshop/assessments.php', M.block_ajax_marking.main, '"+me+"', true);"); // IE
                        
                        // cancel timer loop
                        window.clearInterval(M.block_ajax_marking.popuptimer);
                    }
                }
            }
        }
    }
})();            
            
        <?php
        
        // Get the IDE to do proper script highlighting
        if(0) { ?></script><?php } 
        
    }

}