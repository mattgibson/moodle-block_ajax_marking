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

require_once($CFG->dirroot.'/blocks/ajax_marking/classes/query_base.class.php');

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
    public function __construct() {
        
        // call parent constructor with the same arguments
        //call_user_func_array(array($this, 'parent::__construct'), func_get_args());
        parent::__construct();
        
        $this->modulename           = 'workshop';
        $this->capability           = 'mod/workshop:editdimensions';
        $this->icon                 = 'mod/workshop/icon.gif';
        
    }
  
    /**
     * Outputs the submission nodes. Note: deprecated in 2.0 as showing individual submissions to mark
     * seems less useful with the new model
     *
     * @param int $workshopid
     * @param int $groupid
     * @return void
     */
//    function submissions($workshopid, $groupid) {
//
//        global $CFG, $USER, $DB;
//
//        $workshop = $DB->get_record('workshop', array('id' => $workshopid));
//        $courseid = $workshop->course;
//
//        $context = get_context_instance(CONTEXT_COURSE, $courseid);
//        list($studentsql, $params) = $this->get_sql_role_users($context);
//
//        $sql = "SELECT s.id, s.authorid as userid, s.title, s.timecreated, s.workshopid, u.firstname, u.lastname
//                  FROM {workshop_submissions} s
//            INNER JOIN {user} u
//                    ON s.authorid = u.id
//             LEFT JOIN {workshop_assessments} a
//                    ON (s.id = a.submissionid)
//            INNER JOIN {workshop} w
//                    ON s.workshopid = w.id
//            INNER JOIN ({$studentsql}) stsql
//                    ON s.authorid = stsql.id
//                 WHERE (a.reviewerid != :".$this->prefix_param_name('userid')."
//                    OR (a.reviewerid = :".$this->prefix_param_name('userid2')."
//                   AND a.grade = -1))
//                   AND s.workshopid = :".$this->prefix_param_name('workshopid')."
//                   AND w.assessmentstart < :".$this->prefix_param_name('now')."
//              ORDER BY s.timecreated ASC";
//
//        $params['userid']     = $USER->id;
//        $params['userid2']    = $USER->id;
//        $params['workshopid'] = $workshopid;
//        $params['now'] = time();
//
//        $submissions = $DB->get_records_sql($sql, $params);
//
//        if ($submissions) {
//
//            // if this is set to display by group, we divert the data to the groups() function
//            if (!$groupid) {
//                $group_filter = block_ajax_marking_assessment_groups_filter($submissions, 'workshop', $workshop->id, $workshop->course);
//
//                if (!$group_filter) {
//                    return;
//                }
//            }
//            // otherwise, submissionids have come back, so it must be set to display all.
//
//            // begin json object
//            $output = '[{"callbackfunction":"submissions"}';
//
//            foreach ($submissions as $submission) {
//
//                // if we are displaying for a single group node, ignore those students in other groups
//                if ($groupid && !block_ajax_marking_is_member_of_group($groupid, $submission->userid)) {
//                    continue;
//                }
//
//                // sort out the time stuff
//                $seconds = (time() - $submission->timecreated);
//                $summary = block_ajax_marking_make_time_tooltip($seconds);
//
//                // make the node
//                $output .= block_ajax_marking_make_submission_node(array(
//                        'name'           => fullname($submission),
//                        'submissionid'   => $submission->id,
//                        'workshopid'     => $workshopid,
//                        'summary'        => $summary,
//                        'seconds'        => $seconds,
//                        'modulename'     => $this->modulename,
//                        'timecreated'    => $submission->timecreated));
//            }
//            $output .= ']';
//            echo $output;
//        }
//    }

//    /**
//     * gets all workshops for the config tree
//     *
//     * @return void
//     */
//    function get_all_gradable_items($courseids) {
//
//        global $CFG, $DB;
//
//        list($usql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
//
//        $sql = "SELECT w.id, w.course, w.name, w.intro as summary, cm.id as cmid
//                  FROM {workshop} w
//            INNER JOIN {course_modules} cm
//                    ON w.id = c.instance
//                 WHERE cm.module = :".$this->prefix_param_name('moduleid')."
//                   AND cm.visible = 1
//                   AND w.course $usql
//              ORDER BY w.id";
//        $params['moduleid'] = $this->get_module_id();
//        $workshops = $DB->get_records_sql($sql, $params);
//        $this->assessments = $workshops;
//    }

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
     * See superclass for details
     * 
     * @return array the submission atble alias, join and where clauses, with the aliases for module table
     */
//    function get_sql_count() {
//        
//        global $USER;
//        
//        $moduletable = $this->get_sql_module_table();
//        $submissiontable = $this->get_sql_submission_table();
//        
//        $from =     "FROM {{$moduletable}} moduletable
//                LEFT JOIN {{$submissiontable}} sub
//                       ON sub.workshopid = moduletable.id
//                LEFT JOIN {workshop_assessments} a
//                       ON (sub.id = a.submissionid) ";
//        
//        $where =   "WHERE (a.reviewerid != :workshopuserid1
//                           OR (a.reviewerid = :workshopuserid2
//                               AND a.grade = -1)) ";
//        
//        $params = array(
//                'workshopuserid1' => $USER->id, 
//                'workshopuserid2' => $USER->id);
//                       
//        return array($from, $where, $params);
//    }
    
    /**
     * Returns the column from the workshop_submissions table that has the userid in it
     * 
     * @return string
     */
    protected function get_sql_userid_column() {
        return 'sub.authorid';
    }
    
//    protected function get_sql_submission_table() {
//        return 'workshop_submissions';
//    }
    
    
    /**
     * Returns a query object with the basics all set up to get assignment stuff
     * 
     * @global type $DB
     * @return block_ajax_marking_query_base 
     */
    public function query_factory($callback = false) {
        
        global $USER;
        
        $query = new block_ajax_marking_query_base($this);
        $query->set_userid_column('sub.authorid');
        
        $query->add_from(array(
                'table' => $this->modulename,
                'alias' => 'moduletable',
        ));
        $query->add_from(array(
                'join' => 'INNER JOIN',
                'table' => 'workshop_submissions',
                'alias' => 'sub',
                'on' => 'sub.workshopid = moduletable.id'
        ));
        $query->add_from(array(
                'join' => 'LEFT JOIN',
                'table' => 'workshop_assessments',
                'alias' => 'a',
                'on' => 'sub.id = a.submissionid'
        ));
        
        $query->add_where(array(
                'type' => 'AND', 
                'condition' => '(a.reviewerid != :'.$query->prefix_param_name('userid').'
                                   OR (a.reviewerid = :'.$query->prefix_param_name('userid2').'
                                       AND a.grade = -1))'));
        
        $query->add_param('userid', $USER->id); 
        $query->add_param('userid2', $USER->id);
                       
        return $query;        
        
    }
    
    
//    /**
//     * Sometimes there will need to be extra processing of the nodes that is specific to this module
//     * e.g. the title to be displayed for submissions needs to be formatted with firstname and lastname
//     * in the way that makes sense for the user's chosen language.
//     * 
//     * This function provides a default that can be overidden by the subclasses.
//     * 
//     * @param array $nodes Array of objects
//     * @param string $nodetype the name of the filter that provides the SELECT statements for the query
//     * @param array $filters as sent via $_POST
//     * @return array of objects - the altered nodes
//     */
//    public function postprocess_nodes_hook($nodes, $filters) {
//        
//        foreach ($nodes as &$node) {
//        
//            switch ($filters['callbackfunction']) {
//                
//                case 'course':
//                    $node->mod = $this->get_module_name();
//                    break;
//
//                default:
//                    break;
//            }
//        }
//        
//        return $nodes;
//        
//    }

    
    /**
     * Makes the grading interface for the pop up
     * 
     * @global type $PAGE
     * @global type $CFG
     * @global type $DB
     * @global type $OUTPUT
     * @global type $USER
     * @param array $params From $_GET
     */
    public function grading_popup($params, $coursemodule) {
        
        // Get all DB stuff
        //$coursemodule = $DB->get_record('course_modules', array('id' => $params['cmid']));
        // use coursemodule->instance so that we have checked permissions properly
//        $workshop = $DB->get_record('workshop', array('id' => $coursemodule->instance));
        
//        if (!$workshop) {
//            print_error('Bad coursemodule id');
//            return;
//        }
        
        $workshopurl = new moodle_url('/mod/workshop/view.php?id='.$coursemodule->id);
        redirect($workshopurl);
        
        
        //http://moodle20dev.localhost:8888/mod/workshop/view.php?id=573
        
    }

}