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

if (!defined('MOODLE_INTERNAL')) {
    die();
}

/**
 * This class forms the basis of the objects that hold and process the module data. The aim is for
 * node data to be returned ready for output in JSON or HTML format. Each module that's active will
 * provide a class definition in it's modname_grading.php file, which will extend this base class
 * and add methods specific to that module which can return the right nodes.
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2008 Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class block_ajax_marking_module_base {
    
    /**
     * The name of the module as it appears in the DB modules table
     * 
     * @var string
     */
    public $modulename;
    
    /** 
     * The id of the module in the database
     * 
     * @var int
     */
    public $moduleid;
    
    /**
     * The capability that determines whether a user can grade items for this module
     * 
     * @var string
     */
    public $capability;
    
    /**
     * The url of the icon for this module
     * 
     * @var string
     */
    public $icon;
    
    /**
     * An array showing what callback functions to use for each ajax request
     * 
     * @var array
     */
    public $functions;
    
    /**
     * The items that could potentially have graded work
     * 
     * @var array of objects
     */
    public $assessments;
    
    /**
     * This will hold an array of totals keyed by courseid. Each total corresponds to the number of
     * unmarked submissions that course has for modules of this type.
     * 
     * @var array of objects 
     */
    protected $coursetotals = null;
    
    /**
     * Name of the table that this module stores it's instances in. Needed so that we can join to it 
     * from apply_coursemoduleid_filter(). At the moment it will always be the same as $modulename, but
     * could conceivably vary.
     * 
     * @var string 
     */
    protected $moduletable;
    
    /**
     * Constructor. Overridden by all subclasses.
     */
    public function __construct() {
        
    }
    
    /**
     * Returns the name of the table holding the submissions we are counting for this module. We need
     * this to make the SQL queries
     * 
     * @return string
     */
//    abstract protected function get_sql_submission_table();
    
    /**
     * This will provide the sql to count the number of student submissions. It is added to to make 
     * the queries for each set of nodes
     */
//    abstract protected function get_sql_count();
  
    
    /**
     * So far, all the module tables ar enamed after their modulenames
     * 
     * @return string
     */
//    protected function get_sql_module_table() {
//        return $this->modulename;
//    }
    
    /**
     * This returns the column name in the submissions table that holds the userid. It varies according 
     * to module, but the default is here. Other modules can override if they need to
     * 
     * @return string
     */
    protected function get_sql_userid_column() {
        return 'sub.userid';
    }
    
    /**
     * Once the parts of the query have been constructed, this function put them together as a string
     * and runs it.
     *  
     * @global object $DB
     * @param array $query
     * @return array of objects
     */
//    protected function execute_sql_query($query) {
//        
//        global $DB;
//        
//        $query['select'] = 'SELECT '.implode(', ', $query['select']).' ';
//        $query['groupby'] = $query['groupby'] ? 'GROUP BY '.$query['groupby'] : '';
//        
//        $querystring = $query['select'].$query['from'].$query['where'].$query['groupby'];
//        
//        return $DB->get_records_sql($querystring, $query['params']);
//    }
    
    /**
     * Returns the name of this module as used in the DB
     * 
     * @return string
     */
    protected function get_module_name() {
        return $this->modulename;
    }
    
    /**
     * Getter function for the capability allowing this module's submissions to be graded
     * 
     * @return string
     */
    public function get_capability() {
        return $this->capability;
    }


    /**
     * This counts how many unmarked assessments of a particular type are waiting for a particular
     * course. It is called from the courses() function when the top level nodes are built
     *
     * @param int $courseid id of the course we are counting submissions for
     * @param array $studentids array of students who are enrolled in the course we are counting submissions for
     * @return int the number of unmarked assessments
     */
//    public function course_count($courseid) {
//        
//        if (!isset($this->coursetotals)) {
//            
//            $query = $this->get_sql_query_base();
//            
//            $query['select'][] = 'moduletable.course AS courseid';
//            $query['select'][] = 'COUNT(sub.id) AS count';
//            $query['groupby'] = "moduletable.course";
//            
//            $this->coursetotals = $this->execute_sql_query($query);
//        }
//        
//        if (!isset($this->coursetotals[$courseid])) {
//            //error_log($courseid.' missing from '.$this->modulename);
//        } else {
//            return $this->coursetotals[$courseid]->count;
//        }
//    }
    
    //dead
    /**
     * This will return a fragment of SQL that will check whether a user has permission to grade a particular
     * assessment item. It is of the get_in_or_equals() type and needs to be compared to coursemoduleids.
     * Blank string and empty array if no blocked items.
     * 
     * @return array The sql and params
     */
//    protected function get_sql_permission_denied() {
//        
//        global $DB;
//        
//        // Get coursemoduleids for all items of this type in all courses as one query
//        // TODO what if there are none at all?
//        
//        // There will be courses, or we would not be here
//        $courses = block_ajax_marking_get_my_teacher_courses();
//        
//        list($coursesql, $params) = $DB->get_in_or_equal(array_keys($courses), SQL_PARAMS_NAMED);
//        
//        // Get all coursemodules the current user could potentially access. 
//        // TODO this may return literally millions for a whole site admin. Change it to the one that's 
//        // limited by explicit category and course permissions
//        $sql = "SELECT id 
//                  FROM {course_modules}
//                 WHERE course {$coursesql}
//                   AND module = :moduleid ";
//        $params['moduleid'] = $this->get_module_id();
//                   
//        $coursemoduleids = $DB->get_records_sql($sql, $params);
//        
//        // Get all contexts (will cache them)
//        $contexts = get_context_instance(CONTEXT_MODULE, array_keys($coursemoduleids));
//        
//        // Use has_capability to loop through them finding out which are blocked. Unset all that we have
//        // parmission to grade, leaving just those we are not allowed (smaller list)
//        foreach ($contexts as $key => $context) {
//            
//            if (has_capability($this->capability, $context)) {
//                unset($contexts[$key]);
//            }
//        }
//        
//        $returnsql = '';
//        $returnparams = array();
//        
//        // return a get_in_or_equals with NOT IN if there are any, or empty strings if there arent.
//        if (!empty($contexts)) {
//            list($returnsql, $returnparams) = $DB->get_in_or_equal(array_keys($contexts), SQL_PARAMS_NAMED, 'context0000', false);
//        }
//        
//        return array($returnsql, $returnparams);
//        
//    }
    
    //dead
    /**
     * Provides an EXISTS(xxx) subquery that tells us whether there is a group with user x in it
     * 
     * @param string $configalias this is the alias of the config table in the SQL
     * @return string SQL fragment 
     */
//    private function get_sql_groups_subquery($configalias) {
//        
//        $submissiontablealias = $this->get_sql_submission_table();
//        $useridfield = $this->get_sql_userid_column();
//        
//        $groupsql = " EXISTS (SELECT 1 
//                                FROM {groups_members} gm
//                          INNER JOIN {groups} g
//                                  ON gm.groupid = g.id 
//                          INNER JOIN {block_ajax_marking_groups} gs
//                                  ON g.id = gs.groupid
//                               WHERE gm.userid = {$useridfield}
//                                 AND gs.configid = {$configalias}.id) ";
//                                 
//        return $groupsql;                         
//        
//    }
    
    /**
     * We need to check whether the assessment can be displayed (the user may have hidden it).
     * This sql can be dropped into a query so that it will get the right students
     * 
     * @param string $assessmenttablealias the SQL alias for the assessment table e.g. 'f' for forum
     * @param string $submissiontablealias the SQL alias for the submission table e.g. 's' 
     * @return array of 2 strings with the join and where parts in that order
     */
//    protected function get_sql_display_settings() {
//        
//        // TODO this should use coursemoduleid
//        // TODO needs testing
//        
//        // New plan. 
//        // Check for coursemodule level stuff
//        // EXISTS (SELECT 1 
//        //           FROM {block_ajax_marking} bama
//        //          WHERE {$assessmenttablealias}.id = bama.assessmentid 
//        //            AND bama.assessmenttype = '{$this->modulename})
//        
//        // OR EXISTS(SELECT 1 
//        //           FROM )
//        
//        
//        // bama = block ajax marking assessment 
//        // bamc = block ajax marking course
//        // gmc  = groups members courses
//        
//        $join = "LEFT JOIN {block_ajax_marking} bama
//                        ON cm.id = bama.coursemoduleid
//                 
//                 LEFT JOIN {block_ajax_marking} bamc
//                        ON (moduletable.course = bamc.courseid) 
//                 ";
//                        
//        // either no settings, or definitely display
//        // TODO doesn't work without proper join table for groups
//                            
//        // student might be a member of several groups. As long as one group is in the settings table, it's ok.
//        // TODO is this more or less efficient than doing an inner join to a subquery?
//                        
//        // where starts with the course defaults in case we find no assessment preference
//        // Hopefully short circuit evaluation will makes this efficient.
//        $groupsubquery = $this->get_sql_groups_subquery('bamc'); // EXISTS ([user in relevant group])
//        
//        $where = " AND (( ( bama.display IS NULL 
//                            OR bama.display = ".BLOCK_AJAX_MARKING_CONF_DEFAULT."
//                          ) AND ( 
//                            bamc.display IS NULL 
//                            OR bamc.display = ".BLOCK_AJAX_MARKING_CONF_SHOW."
//                            OR (bamc.display = ".BLOCK_AJAX_MARKING_CONF_GROUPS. " AND {$groupsubquery})
//                          )
//                        ) ";
//
//        
//        $groupsubquery = $this->get_sql_groups_subquery('bama');
//        
//        $where .= " OR bama.display = ".BLOCK_AJAX_MARKING_CONF_SHOW.
//                  " OR (bama.display = ".BLOCK_AJAX_MARKING_CONF_GROUPS. " AND {$groupsubquery})) ";
//        
//        return array($join, $where);
//        
//    }
    


    /**
     * This function will check through all of the assessments of a particular type (depends on
     * instantiation - there is one of these objects made for each type of assessment) for a
     * particular course, then return the nodes for a course ready for the main tree
     *
     * @param int $courseid the id of the course
     * @param bool $html Are we making a HTML list?
     * @return mixed array or void depending on the html type
     */
//    public function module_nodes($courseid) {
//        
//        $query = $this->get_sql_query_base();
//        
//        array_push($query['select'],
//                'moduletable.id',
//                'COUNT(sub.id) AS count',
//                'COALESCE(bama.display, bamc.display, 1) AS display',
//                'cm.id AS cmid',
//                'moduletable.name',
//                'moduletable.intro AS tooltip'
//        );
//        
//        // The group by moduletable.id clause means that we have to do another self join to get the other
//        // columns from the module table, otherwise we get duplicates
//        $query['where'] .= "AND moduletable.course = :courseid ";
//        $query['groupby'] .= "moduletable.id ";
//        $query['params']['courseid'] = $courseid;
//        
//        $modulecounts = $this->execute_sql_query($query);
//  
//        foreach ($modulecounts as &$assessment) {
//            
//
//            $assessment->modulename    = $this->modulename;
//            $assessment->name          = block_ajax_marking_clean_name_text($assessment->name, 30);
//            $assessment->assessmentid  = $assessment->id;
//            unset($assessment->id);
//            
////            $assessment->tooltip = (strlen($assessment->tooltip) > 100) ? substr($assessment->tooltip, 0, 100).'...' : $assessment->tooltip;
//            $assessment->tooltip = get_string('modulename', $assessment->modulename).': '.
//                                   block_ajax_marking_clean_tooltip_text($assessment->tooltip);
//            $assessment->style = 'course';
//            
//        }
//        
//        return $modulecounts;
//    }

    /**
     * This counts the assessments that a course has available. Called when the config tree is built.
     *
     * @param int $course course id of the course we are counting for
     * @return int count of items
     */
    public function count_course_assessment_nodes($course) {

        if (!isset($this->assessments)) {
            $this->get_all_gradable_items();
        }

        $count = 0;

        if ($this->assessments) {

            foreach ($this->assessments as $assessment) {
                // permissions check
                if (!$this->permission_to_grade($assessment)) {
                    continue;
                }
                //is it for this course?
                if ($assessment->course == $course) {
                    $count++;
                }
            }
        }
        return $count;
    }

    /**
     * creates assessment nodes of a particular type and course for the config tree
     *
     * @param int $course the id number of the course
     * @param string $modulename e.g. forum
     * @return void
     */
//    public function config_assessment_nodes($course, $modulename) {
//
//        $this->get_all_gradable_items();
//
//        if ($this->assessments) {
//
//            foreach ($this->assessments as $assessment) {
//
//                $context = get_context_instance(CONTEXT_MODULE, $assessment->cmid);
//
//                if (!$this->permission_to_grade($assessment)) {
//                    continue;
//                }
//
//                if ($assessment->course == $course) {
//                    $assessment->type = $this->modulename;
//                    // TODO - alter SQL so that this line is not needed.
//                    $assessment->description = $assessment->summary;
//                    $assessment->dynamic = false;
//                    $assessment->count = false;
//                    return block_ajax_marking_make_assessment_node($assessment, true);
//                }
//            }
//        }
//    }

    /**
     * This is to allow the ajax call to be sent to the correct function. When the
     * type of one of the pluggable modules is sent back via the ajax call, the ajax_marking_response constructor
     * will refer to this function in each of the module objects in turn from the default in the switch statement
     *
     * @param string $type the type name variable from the ajax call
     * @return bool
     */
    public function return_function($type, $args) {

        if (array_key_exists($type, $this->functions)) {
            $function = $this->functions[$type];
            call_user_func_array(array($this, $function), $args);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Rather than waste resources getting loads of students we don't need via get_role_users() then
     * cross referencing, we use this to drop the right SQL into a sub query. Without it, some large
     * installations hit a barrier using IN($course_students) e.g. oracle can't cope with more than
     * 1000 expressions in an IN() clause.
     * 
     * This works for a specific context, so it's not going to be so great for all course submissions
     *
     * @param object $context the context object we want to get users for
     * @param bool $parent should we look in higher contexts too?
     */
//    protected function get_sql_role_users($context, $parent=true, $paramtype=SQL_PARAMS_NAMED) {
//
//        global $CFG, $DB;
//
//        $parentcontexts = '';
//        $parentcontextssql = '';
//        // need an empty one for array_merge() later
//        $parentcontextsparams = array();
//
//        $parentcontexts = substr($context->path, 1); // kill leading slash
//        $parentcontexts = explode('/', $parentcontexts);
//
//        if ($parentcontexts !== '') {
//            list($parentcontextssql, $parentcontextsparams) = $DB->get_in_or_equal($parentcontexts, $paramtype, 'parentcontext9000');
//        }
//
//        // get the roles that are specified as graded in site config settings. Will sometimes be here,
//        // sometimes not depending on ajax call
//        
//        // TODO does this work?
//        $student_roles = $CFG->gradebookroles;
//
//        // start this set of params at a later point to avoid collisions
//        list($studentrolesql, $studentroleparams) = $DB->get_in_or_equal($student_roles, $paramtype, 'param0900');
//
//        $sql = " SELECT DISTINCT(u.id)
//                   FROM {role_assignments} ra
//                   JOIN {user} u
//                     ON u.id = ra.userid
//                   JOIN {role} r
//                     ON ra.roleid = r.id
//                  WHERE ra.contextid {$parentcontextssql}
//                    AND ra.roleid {$studentrolesql}";
//
//        $data = array($sql, $parentcontextsparams + $studentroleparams);
//        return $data;
//
//    }

    /**
     * Returns an SQL snippet that will tell us whether a student is enrolled in this course
     * Needs to also check parent contexts.
     * 
     * @param string $useralias the thing that contains the userid e.g. s.userid
     * @param string $moduletable the thing that contains the courseid e.g. a.course
     * @return array The join and where strings, with params. (Where starts with 'AND)
     */
//    protected function get_sql_enrolled_students() {
//        
//        global $DB, $CFG, $USER;
//        
//        $usercolumn = $this->get_sql_userid_column();
//
//        // TODO Hopefully, this will be an empty string when none are enabled
//        if ($CFG->enrol_plugins_enabled) {
//            // returns list of english names of enrolment plugins
//            list($enabledsql, $params) = $DB->get_in_or_equal(explode(',', $CFG->enrol_plugins_enabled), SQL_PARAMS_NAMED);
//        } else {
//            // no enabled enrolment plugins
//            $enabledsql = "= :never";
//            $params = array('never'=> -1);
//        }
//
//        $join = " INNER JOIN {user_enrolments} ue 
//                          ON ue.userid = {$usercolumn} 
//                  INNER JOIN {enrol} e 
//                          ON (e.id = ue.enrolid) ";
//        $where = "       AND e.courseid = moduletable.course
//                         AND {$usercolumn} != :currentuser
//                         AND e.enrol {$enabledsql} ";
//                         
//        $params['currentuser'] = $USER->id;
//        
//        // uncomment to disable (for testing)
//        // $join = '';
//        // $where = '';
//        // $params = array();
//                        
//        return array($join, $where, $params);
//    }
    
    
    
    /**
     * All modules have a common need to hide work which has been submitted to items that are now hidden.
     * Not sure if this is relevant so much, but it's worth doing so that test data and test courses don't appear.
     * 
     * @return array The join string, where string and params array. Note, where starts with 'AND'
     */
//    protected function get_sql_visible() {
//        
//        list($permissionsql, $params) = $this->get_sql_permission_denied();
//        
//        $join = "INNER JOIN {course_modules} cm
//                         ON cm.instance = moduletable.id 
//                 INNER JOIN {course} course
//                         ON course.id = moduletable.course ";
//
//        $where = "AND cm.id {$permissionsql}
//                  AND cm.module = :moduleid 
//                  AND cm.visible = 1
//                  AND course.visible = 1 ";
//        
//        $params['moduleid'] = $this->get_module_id();
//        
//        // uncomment to disable (for testing)
//        // $join = '';
//        // $where = '';
//        // $params = array();
//        
//        return array($join, $where, $params);
//        
//    }
    

    /**
     * Find the id of this module in the DB. It may vary from site to site
     *
     * @return int the id of the module in the DB
     */
    public function get_module_id($reset=false) {
        
        global $DB;

        if (isset($this->moduleid) && !empty($this->moduleid) && !$reset) {
            return $this->moduleid;
        }

        $this->moduleid = $DB->get_field('modules', 'id', array('name' => $this->modulename));
        
        if (empty($this->moduleid)) {
            error_log('No module id for '.$this->modulename);
        }

        return $this->moduleid;
    }

    /**
     * Checks whether the user has grading permission for this assessment
     *
     * @param object $assessment a row from the db
     * @return bool
     */
    protected function permission_to_grade($assessment) {

        global $USER;

        $context = get_context_instance(CONTEXT_MODULE, $assessment->cmid);

        if (has_capability($this->capability, $context, $USER->id, false)) {
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * Checks the functions array to see if this module has a function that corresponds to it
     * 
     * @param string $callback
     * @return bool 
     */
    public function contains_callback($callback) {
        return method_exists($this, $callback);
    }
    
    /**
     * This will find the module's javascript file and add it to the page. Used by the main block.
     * 
     * @return void
     */
    public function include_javascript() {
        
        global $CFG, $PAGE;
        
        $blockdirectoryfile = "/blocks/ajax_marking/{$this->modulename}_grading.js";
        $moddirectoryfile   = "/mod/{$this->modulename}/{$this->modulename}_grading.js";
        
        if (file_exists($CFG->dirroot.$blockdirectoryfile)) {
            
            $PAGE->requires->js($blockdirectoryfile);
            
        } else {

            if (file_exists($CFG->dirroot.$moddirectoryfile)) {
                $PAGE->requires->js($moddirectoryfile);
            }
        }
    }
    
    /**
     * Gets submissions for this module type aggregated and filtered according to the supplied parameters
     * 
     * @param string $mode what sort of submission aggregation we want. 'course', module', 'group', 'submissions' or 'other'
     * @param array $extraparams need to be in named format to avoid collisions
     * @param array $extraselect
     * @param string $extrajoin 
     * @param string $extrawhere
     * @param string $extragroup
     * @return array of objects
     */
//    public function get_sql_query_base() {
//        
//        global $DB;
//        
//        $query = array(
//                'select' => array(),
//                'from' => '',
//                'where' => '',
//                'groupby' => '',
//                'params' => array()
//        );
//        
//        // These fragments define common filters, which apply to all queries.
//        list($countfrom, $countwhere, $countparams)       = $this->get_sql_count();
//        list($displayjoin, $displaywhere)                 = $this->get_sql_display_settings();
//        list($enroljoin, $enrolwhere, $enrolparams)       = $this->get_sql_enrolled_students();
//        list($visiblejoin, $visiblewhere, $visibleparams) = $this->get_sql_visible();
//        
//        $query['from'] = $countfrom.
//                         $enroljoin.
//                         $visiblejoin.
//                         $displayjoin;
//                       
//        $query['where'] = $countwhere.
//                          $displaywhere.
//                          $enrolwhere.
//                          $visiblewhere;
//                
//        $query['params'] = array_merge($countparams, $enrolparams, $visibleparams);
//        
//        return $query;
//        
//    }
    
    /**
     * Which column needs to be first in the SQL query for submissions? The default is here, but 
     * modules can override it if they need to e.g. if they need to aggregate submissions like for
     * the forum module
     */
//    protected function get_sql_submissions_unique_column() {
//        return 'sub.id AS subid';
//    }
    
    /**
     * Makes the submissions nodes.
     * 
     * @global object $DB
     * @param array $params Whatever came back via Ajax
     * @return array - data and nodes
     */
//    public function submissions($params) {
//        
//        global $DB;
//        
//        $data = new stdClass;
//        $data->nodetype = 'submission';
//        $nodes = array();
//        
//        $query = $this->get_sql_query_base();
//        
//        $usercolumnalias   = $this->get_sql_userid_column();
//        $uniquecolumn      = $this->get_sql_submissions_unique_column();
//        $extramoduleselect = $this->get_sql_submissions_select($params['assessmentid']);
//
//        array_push($query['select'],
//                //$uniquecolumn, // -forum differs. normally sub.id
//                //"{$usercolumnalias} AS userid",
//                //'moduletable.id AS assessmentid',
//                'cm.id AS cmid'
//                //'u.firstname', 
//                //'u.lastname'
//        );
//        
//        // Some modules will need extra stuff depending on how their links work.
//        $query['select'] = array_merge($extramoduleselect, $query['select']);
//                
//        $query['from'] .= $this->get_sql_submissions_from().' ';       
//        $query['from'] .= "INNER JOIN {user} u
//                                   ON u.id = {$usercolumnalias} ";
//        
//        $query['where'] .= 'AND moduletable.id = :assessmentid ';
//        $query['where'] .= $this->get_sql_submission_where();
//        
//        foreach ($params as $paramname => $paramvalue) {
//            $query['params'][$paramname] = $paramvalue;
//        }
//        
//        // If we are following from a group node, filter by groupid too. Other params will be dealt with
//        // by the submissions code.
//        if (isset($params['groupid'])) {
//            
//            $query['from'] .= "INNER JOIN {groups_members} gm 
//                                       ON gm.userid = {$usercolumnalias} ";
//                                       
//            $query['where'] .= "AND gm.groupid = :groupid ";
//            
//            $query['params']['groupid'] = $params['groupid'];
//        }
//        
//        $query['groupby'] = $this->get_sql_submissions_groupby();
//        
//        $submissions = $this->execute_sql_query($query);
//
//        foreach ($submissions as $uniquecolumnid => &$submission) {
//            
//            $submission->seconds  = (time() - $submission->time);
//            unset($submission->time);
//            $submission->tooltip  = block_ajax_marking_make_time_tooltip($submission->seconds);
//            
////            $submission->uniqueid = $this->get_module_name().'-submission-'.$uniquecolumnid;
////            // uniqueid needs to include something unique for each level of filters. Could be duplicates otherwise
////            foreach ($params as $paramname => $paramvalue) {
////                $submission->uniqueid .= '-'.$paramname.'-'.$paramvalue;
////            }
//            
//            $submission->name           = $this->submission_title($submission, $params['assessmentid']);
//            $submission->mod            = $this->modulename; // needed to get mod js in client
//            
//            block_ajax_marking_format_node($submission);
//            
//        }
//        
//        // use array_values to re-key the array so that json_encode() makes an array, not an object
//        return array($data, array_values($submissions));
//    }
    
    /**
     * Add any extra stuff that we need in order to construct the link for the submission pop up. 
     * Default is here, modules override if they need to
     * 
     * @return array must include description and timemodified
     */
//    protected function get_sql_submissions_select($moduleid=null) {
//        
//        return array(
//                '1 AS count',
//                'moduletable.intro AS description',
//                'sub.timemodified AS time',
//                'sub.userid',
//                'cm.id AS coursemoduleid'
//        );
//    }
    
    /**
     * Any extra table joins needed for the submissions query. This is the default which can be overridden.
     * 
     * @return string
     */
//    protected function get_sql_submissions_from() {
//        return '';
//    }
    
    /**
     * Any extra where clauses that are needed to filter the submissions stuff.  This is the default 
     * which can be overridden.
     * 
     * @return string
     */
//    protected function get_sql_submission_where() {
//        return '';
//    }
    
    /**
     * Any group by clauses which the submissions stuff needs. This is the default which can be overridden.
     * 
     * @return string
     */
//    protected function get_sql_submissions_groupby() {
//        return '';
//    }
    
    /**
     * The submissions nodes may be aggregating actual work so that it is easier to view/grade e.g.
     * seeing a whole forum discussion at once because posts are meaninless without context. This allows 
     * modules to override the default label text of the node, which is the user's name. 
     * 
     * @param object $submission
     * @param int $moduleid
     * @return string 
     */
    protected function submission_title(&$submission, $moduleid=null) {
        $title = fullname($submission);
        unset($submission->firstname, $submission->lastname);
        return $title;
    }
    
    /**
     * Returns a query object that has been set up to retrieve all unmarked submissions for this teacher
     * and this (subclassed) module
     * 
     * @return block_ajax_marking_query_base
     */
    abstract public function query_factory($callback = false);
    
    /**
     * Hook to allow subclasses to add specific selects and joins to the query. This is important as
     * getting the grading interface to pop up needs certain data
     * 
     * @param block_ajax_marking_query_base $query 
     * @return void
     */
    public function alter_query_hook(block_ajax_marking_query_base $query, $groupby = false) {
        
    }
    
    /**
     * Sometimes there will need to be extra processing of the nodes that is specific to this module
     * e.g. the title to be displayed for submissions needs to be formatted with firstname and lastname
     * in the way that makes sense for the user's chosen language.
     * 
     * This function provides a default that can be overidden by the subclasses.
     * 
     * @param array $nodes Array of objects
     * @param string $nodetype the name of the filter that provides the SELECT statements for the query
     * @param array $filters as sent via $_POST
     * @return array of objects - the altered nodes
     */
    public function postprocess_nodes_hook($nodes, $filters) {
        
        foreach ($nodes as &$node) {
            
            // just so we know (for styling and accessing js in the client)
            $node->modulename = $this->modulename;
        
            switch ($filters['nextnodefilter']) {
                
                case 'userid':
//                    $node->mod = $this->get_module_name();
                    // Sort out the firstname/lastname thing
                    $node->name = fullname($node);
                    unset($node->firstname, $node->lastname);

                    break;

                default:
                    break;
            }
        }
        
        return $nodes;
        
    }
    
//    /**
//     * Applies the filter needed for course nodes or their descendants
//     * 
//     * @param block_ajax_marking_query_base $query 
//     * @param int $courseid Optional. Will apply SELECT and GROUP BY for nodes if missing
//     * @param bool $union If we are glueing many module queries together, we will need to 
//     *                    run a wrapper query that will select from the UNIONed subquery
//     * @return void|string
//     */
//    private static function apply_courseid_filter($query, $courseid = 0, $union = false) {
//        
//        $selects = array(
//                array(
//                    'table' => 'moduletable', 
//                    'column' => 'course',
//                    'alias' => 'courseid'),
//                array(
//                    'table' => 'sub', 
//                    'column' => 'id',
//                    'alias' => 'count',
//                    'function' => 'COUNT'),
//                array(
//                    'table' => 'course', 
//                    'column' => 'shortname',
//                    'alias' => 'name'),
//                array(
//                    'table' => 'course', 
//                    'column' => 'fullname',
//                    'alias' => 'tooltip')
//        );
//        
//        if (!$courseid) {
//            // Apply SELECT clauses for course nodes
//            if (!$union) {
//                foreach ($selects as $select) {
//                    $query->add_select($select);
//                }
//            } else { // we need to select just the aliases
//                $selectstring = '';
//                foreach ($selects as $select) {
//                    $selectstring .= isset($select['function']) ? $select['function'].'(' : '';
//                    $selectstring .= 'unionquery.'.$select['alias'];
//                    $selectstring .= isset($select['function']) ? ')' : '';
//                }
//            }
//
//        } else {
//            // Apply WHERE clause
//            $query->add_where(array('type' => 'AND', 'condition' => 'moduletable.course = :'.$query->prefix_param_name('courseid')));
//            $query->add_param('courseid', $courseid);
//            
//        }
//        
//    }
//    
//    
//    /**
//     * Applies the filter needed for assessment nodes or their descendants
//     * 
//     * @param block_ajax_marking_query_base $query 
//     * @param int $coursemoduleid optional. Will apply SELECT and GROUP BY for nodes if missing
//     * @return void
//     */
//    private static function apply_coursemoduleid_filter($query, $coursemoduleid = 0) {
//        
//        if (!$coursemoduleid) {
//            
//            // Same order as the next query will need them 
//            $selects = array(
//                array(
//                    'table' => 'cm', 
//                    'column' => 'id',
//                    'alias' => 'coursemoduleid'),
//                array(
//                    'table' => 'sub', 
//                    'column' => 'id',
//                    'alias' => 'count',
//                    'function' => 'COUNT'),
//                array(
//                    'column' => 'COALESCE(bama.display, bamc.display, 1)',
//                    'alias' => 'display'),
//                array(
//                    'table' => 'moduletable', 
//                    'column' => 'id',
//                    'alias' => 'assessmentid'),
//                array(
//                    'table' => 'moduletable', 
//                    'column' => 'name'),
//                array(
//                    'table' => 'moduletable', 
//                    'column' => 'intro',
//                    'alias' => 'tooltip'),
//                // This is only needed to add the right callback function. 
//                array(
//                    'column' => "'".$query->get_modulename()."'",
//                    'alias' => 'modulename'
//                    )
//            );
//            
//            foreach ($selects as $select) {
//                $query->add_select($select);
//            }
//            
//        } else {
//            // Apply WHERE clause
//            $query->add_where(array(
//                    'type' => 'AND', 
//                    'condition' => 'cm.id = :'.$query->prefix_param_name('coursemoduleid')));
//            $query->add_param('coursemoduleid', $coursemoduleid);
//            
//        }
//    }
    
    /**
     * Getter for the module 
     * @return string
     */
    public function get_module_table() {
        return $this->moduletable;
    }

}

?>
