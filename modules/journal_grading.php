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
 * Class file for the journal grading functions
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
 * Provides marking functions for the journal module
 *
 * @copyright 2008-2010 Matt Gibson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_ajax_marking_journal extends block_ajax_marking_module_base {

    /**
     * Constructor
     *
     * @param object $reference the parent object, passed in so it's functions can be referenced
     * @return void
     */
    function __construct() {
        
        // must be the same as the DB modulename
        $this->modulename        = 'journal';
        $this->moduleid    = $this->get_module_id();
        // doesn't seem to be a journal capability :s
        $this->capability  = 'mod/assignment:grade';
        // How many nodes in total when fully expanded (no groups)?
        $this->levels      = 2;
        // function to trigger for the third level nodes (might be different if there are four
        $this->icon        = 'mod/journal/icon.gif';
        $this->callbackfunctions   = array();
    }

     /**
      * gets all unmarked journal submissions from all courses ready for counting
      * called from get_main_level_data
      *
      * @return bool true
      */
    function get_all_unmarked($courseids) {

        global $DB;
        
        list($usql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $sql = "SELECT je.id as entryid, je.userid, j.name, j.course, j.id, c.id as cmid
                  FROM {journal_entries} je
            INNER JOIN {journal} j
                    ON je.journal = j.id
            INNER JOIN {course_modules} c
                    ON j.id = c.instance
                 WHERE c.module = :moduleid
                   AND j.course $usql
                   AND c.visible = 1
                   AND j.assessed <> 0
                   AND je.modified > je.timemarked";
        $params['moduleid'] = $this->moduleid;
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
        
        list($displayjoin, $displaywhere) = $this->get_display_settings_sql('j', 'je.userid');
        
        $sql = "SELECT j.course AS courseid, COUNT(je.id) AS count
                  FROM {journal_entries} je
            INNER JOIN {journal} j
                    ON je.journal = j.id
            INNER JOIN {course_modules} c
                    ON j.id = c.instance
                       {$displayjoin}
                 WHERE c.module = :moduleid
                   AND c.visible = 1
                   AND j.assessed <> 0
                   AND je.modified > je.timemarked
                       {$displaywhere}";
        
        $params = array();
        $params['moduleid'] = $this->moduleid;
        
        return $DB->get_records_sql($sql, $params);
        
    }
    

    /**
     * Gets all the unmarked journals for a course
     *
     * @param int $courseid the id of the course
     * @return array results objects
     */
    function get_all_course_unmarked($courseid) {

        global $CFG, $DB;
        //list($usql, $params) = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED);
        
        $context = get_context_instance(CONTEXT_COURSE, $courseid);

        list($studentsql, $params) = $this->get_role_users_sql($context);

        $sql = "SELECT je.id as entryid, je.userid, j.intro as description, j.course, j.name,
                       j.timemodified, j.id, c.id as cmid
                  FROM {journal_entries} je
            INNER JOIN {journal} j
                    ON je.journal = j.id
            INNER JOIN {course_modules} c
                    ON j.id = c.instance
            INNER JOIN ({$studentsql}) stsql
                    ON je.userid = stsql.id
                 WHERE c.module = :moduleid
                   AND c.visible = 1
                   AND j.assessed <> 0
                   AND je.modified > je.timemarked
                   AND j.course = :courseid";
        $params['moduleid'] = $this->moduleid;
        $params['courseid'] = $courseid;

        $unmarked = $DB->get_records_sql($sql, $params);
        return $unmarked;
    }

    /**
     * gets all journals for all courses ready for the config tree
     *
     * @return void
     */
    function get_all_gradable_items($courseids) {

        global $CFG, $DB;

        list($usql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);

        $sql = "SELECT j.id, j.intro as summary, j.name, j.course, c.id as cmid
                  FROM {journal} j
            INNER JOIN {course_modules} c
                    ON j.id = c.instance
                 WHERE c.module = :moduleid
                   AND c.visible = 1
                   AND j.assessed <> 0
                   AND j.course $usql";
        $params['moduleid'] = $this->moduleid;

        $journals = $DB->get_records_sql($sql, $params);
        $this->assessments = $journals;
    }

    /**
     * this will never actually lead to submissions, but will only be called if there are group
     * nodes to show.
     *
     * @return void
     */
    function submissions($journalid) {

        global $USER, $CFG, $DB;
        // need to get course id in order to retrieve students
        $journal = $DB->get_record('journal', array('id' => $journalid));
        $courseid = $journal->course;

        $coursemodule = $DB->get_record('course_modules', array('module' => '1', 'instance' => $journalid));
        $modulecontext = get_context_instance(CONTEXT_MODULE, $coursemodule->id);

        if (!has_capability($this->capability, $modulecontext, $USER->id)) {
            return;
        }
        
        $context = get_context_instance(CONTEXT_COURSE, $courseid);

        list($studentsql, $params) = $this->get_role_users_sql($context);

        $sql = "SELECT je.id as entryid, je.userid, j.intro as description, j.name, j.timemodified,
                       u.firstname, u.lastname, j.id, c.id as cmid
                  FROM {journal_entries} je
            INNER JOIN {user} u
                    ON s.userid = u.id
            INNER JOIN {journal} j
                    ON je.journal = j.id
            INNER JOIN {course_modules} c
                    ON j.id = c.instance
            INNER JOIN ({$studentsql}) stsql
                    ON je.userid = stsql.id
                 WHERE c.module = :moduleid
                   AND c.visible = 1
                   AND j.assessed <> 0
                   AND je.modified > je.timemarked
                   AND je.userid $usql
                   AND j.id = :journalid";
        $params['moduleid'] = $this->moduleid;
        $params['journalid'] = $journal->id;
        $submissions = $DB->get_records_sql($sql, $params);

        // TODO: does this work with 'journal' rather than 'journal_final'?

        // This function does not need any checks for group status as it will only be called if groups are set.
        $group_filter = block_ajax_marking_assessment_groups_filter($submissions,
                                                                    'journal',
                                                                    $journal->id,
                                                                    $journal->course);

        // group nodes have now been printed by the groups function
        return;
    }

    /**
     * Makes a HTML link for the popup
     *
     * @param object $item a journal object with cmid property
     * @return string
     */
    function make_html_link($item) {

        global $CFG;
        $address = $CFG->wwwroot.'/mod/journal/report.php?id='.$item->cmid;
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
// uses 'journal' as the node that will be clicked on will have this type.
M.block_ajax_marking.journal = (function(clickednode) {

    return {
        
        pop_up_post_data : function () {
            return 'id='+clickednode.data.cmid;
        },
        
        pop_up_closing_url : function () {
            return '/mod/journal/report.php';
        },
        
        pop_up_arguments : function () {
            return 'menubar=0,location=0,scrollbars,resizable,width=900,height=500';
        },
        
        pop_up_opening_url : function () {
            var url  = '/mod/journal/report.php?id='+clickednode.data.cmid+'&group=';
                url += ((typeof(clickednode.data.group)) != 'undefined') ? clickednode.data.group : '0' ;
            return url;
        },

        extra_ajax_request_arguments : function () {
            return '';
        },
        
        /**
         * adds onclick stuff to the journal pop up elements once they are ready.
         * me is the id number of the journal we want
         */
        alter_popup : function () {
        
            // get the form submit input, which is always last but one (length varies)
            var input_elements = M.block_ajax_marking.popupholder.document.getElementsByTagName('input');
        
            // TODO - might catch the pop up half loaded. Not ideal.
            if (typeof(input_elements) != 'undefined' && input_elements.length > 0) {
            
                var key = input_elements.length -1;
        
                YAHOO.util.Event.on(
                    input_elements[key],
                    'click',
                    function(){
                        return M.block_ajax_marking.markingtree.remove_node_from_tree(
                            '/mod/journal/report.php',
                            clickednode.data.uniqueid
                        );
                    }
                );
                // cancel the timer loop for this function
                window.clearInterval(M.block_ajax_marking.popuptimer);
            }
        }
    };
})();
            
        <?php
        
        // Get the IDE to do proper script highlighting
        if(0) { ?></script><?php } 
        
    }

}