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

require_once($CFG->dirroot.'/blocks/ajax_marking/classes/query_base.class.php');

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
        $this->modulename        = $this->moduletable = 'journal';
        // doesn't seem to be a journal capability :s
        $this->capability  = 'mod/assignment:grade';
        // How many nodes in total when fully expanded (no groups)?
        // function to trigger for the third level nodes (might be different if there are four
        $this->icon        = 'mod/journal/icon.gif';
        
        // call parent constructor with the same arguments
        call_user_func_array(array($this, 'parent::__construct'), func_get_args());
    }
    

    /**
     * gets all journals for all courses ready for the config tree
     *
     * @return void
     */
//    function get_all_gradable_items($courseids) {
//
//        global $CFG, $DB;
//
//        list($usql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
//
//        $sql = "SELECT j.id, j.intro as summary, j.name, j.course, cm.id as cmid
//                  FROM {journal} j
//            INNER JOIN {course_modules} cm
//                    ON j.id = cm.instance
//                 WHERE cm.module = :".$this->prefix_param_name('moduleid')."
//                   AND cm.visible = 1
//                   AND j.assessed <> 0
//                   AND j.course {$usql}";
//        $params['moduleid'] = $this->get_module_id();
//
//        $journals = $DB->get_records_sql($sql, $params);
//        $this->assessments = $journals;
//    }

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

        list($studentsql, $params) = $this->get_sql_role_users($context);

        $sql = "SELECT je.id as entryid, je.userid, j.intro as description, j.name, j.timemodified,
                       u.firstname, u.lastname, j.id, c.id as cmid
                  FROM {journal_entries} je
            INNER JOIN {user} u
                    ON s.userid = u.id
            INNER JOIN {journal} j
                    ON je.journal = j.id
            INNER JOIN {course_modules} cm
                    ON j.id = cm.instance
            INNER JOIN ({$studentsql}) stsql
                    ON je.userid = stsql.id
                 WHERE cm.module = :".$this->prefix_param_name('moduleid')."
                   AND cm.visible = 1
                   AND j.assessed <> 0
                   AND je.modified > je.timemarked
                   AND je.userid $usql
                   AND j.id = :".$this->prefix_param_name('journalid');
        $params['moduleid'] = $this->get_module_id();
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
     * See superclass for details
     * 
     * @return array the select, join and where clauses, with the aliases for module and submission tables
     */
//    function get_sql_count() {
//        
//        $moduletable = $this->get_sql_module_table();
//        $submissionstable = $this->get_sql_submission_table();
//        
//        $from =     "FROM {{$submissionstable}} moduletable
//               INNER JOIN {{$moduletable}} sub
//                       ON sub.journal = moduletable.id ";
//                       
//        $where =   "WHERE moduletable.assessed <> 0
//                      AND sub.modified > sub.timemarked ";
//        
//        $params = array();
//                       
//        return array($from, $where, $params);
//    }
    
//    protected function get_sql_submission_table() {
//        return 'journal_entries';
//    }
    
 
    /**
     * Returns a query object with the basics all set up to get assignment stuff
     * 
     * @global type $DB
     * @return block_ajax_marking_query_base 
     */
    public function query_factory($callback = false) {
        
        global $DB;
        
        $query = new block_ajax_marking_query_base($this);
        $query->set_userid_column('sub.userid');
        
        $query->add_from(array(
                'table' => $this->modulename,
                'alias' => 'moduletable',
        ));
        
        $query->add_from(array(
                'join' => 'INNER JOIN',
                'table' => 'journal_entries',
                'alias' => 'sub',
                'on' => 'sub.journal = moduletable.id'
        ));
        
        $query->add_where(array('type' => 'AND', 'condition' => 'moduletable.assessed <> 0'));
        $query->add_where(array('type' => 'AND', 'condition' => 'sub.modified > sub.timemarked'));
        
        return $query;
    }

}