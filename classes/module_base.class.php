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
 * @package   blocks-ajax_marking
 * @copyright 2008-2011 Matt Gibson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
     * An array of all the courseids where this user is a teacher
     * 
     * @var array
     */
    private $courseids;
    
    /**
     * The capability that determines whether a user can grade items for this module
     * 
     * @var string
     */
    public $capability;
    
    /**
     * The number of levels of tree nodes that this module has without groups
     * 
     * @var int
     */
    public $levels;
    
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
     * An array of all of the submissions for this module type across all courses
     * 
     * @var type 
     */
    private $allsubmissions;
    
    /**
     * An array of the ids of all of the assessments in a course
     * 
     * @var type 
     */
    public $course_assessment_ids;
    
    /**
     * An object with properties which are arrays, named by courseid, containing all the unmarked 
     * submissions for a particular course
     * 
     * @var array
     */
    private $coursesubmissions;
    
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
    protected $coursetotals;
    
    /**
     * Constructor. Overridden by all subclasses.
     */
    public function __construct() {
        
    }
    
    /**
     * This is to get all of the unmarked submissions for the module across all courses so they can 
     * be sorted through.
     * 
     * @param array $courseids
     * @return array of objects
     */
    abstract protected function get_all_unmarked($courseids);
    
    /**
     * This will retrieve the counts for each course in the site so they can be aggregated into 
     * counts for each course node in the main level of the tree
     */
    abstract protected function get_course_totals();

    /**
     * This counts how many unmarked assessments of a particular type are waiting for a particular
     * course. It is called from the courses() function when the top level nodes are built
     *
     * @param int $courseid id of the course we are counting submissions for
     * @param array $studentids array of students who are enrolled in the course we are counting submissions for
     * @return int the number of unmarked assessments
     */
    function count_course_submissions($courseid) {
        
        
        // Need to switch to using sql queries that return counts for each course.
        
        // cache the object with the totals
        if (!isset($this->coursetotals)) {
            $this->coursetotals = $this->get_course_totals();
        }
        
        return $this->coursetotals[$courseid]->count;
        
        // TODO grading permission SQL as subquery
        // i.e. denied permissions at assessment level or denied permission to this type of 
        // assessment at course level?
        // - complex due to overrides. Could use cached permissions to supply a list of coursemodule ids
        //   that this user is allowed to mark as an IN() clause. Make sure to limit this to courses 
        //   that are current
        //
        // get all cmids for all courses
        // filter using has_capability()
        
        // TODO check display settings SQL
        // - is it display = yes
        // - is it display = groups and the student is in the group
        
        // TODO enrolment SQL
        // - does the student have an enrolment
        
        
        
        
        // old stuff below

        // html_list.php will be doing this many times, so we reuse the data.
        if (!isset($this->allsubmissions)) {
            $this->allsubmissions = $this->get_all_unmarked($studentids);
        }

        // maybe there is nothing to mark?
        if (isset($this->allsubmissions) && !$this->allsubmissions) {
            return 0;
        }

        // Run through all of the unmarked work, extracting the ids of the assessment items they
        // belong to.

        // get a list of all the assessments in this course, ready to loop through
        $this->course_assessment_ids = block_ajax_marking_list_assessment_ids($this->allsubmissions, $courseid);
        
        // echo count($this->assessment_ids).' ';
        // Now check all of these assessment ids to see if the user has grading capabilities
        foreach ($this->course_assessment_ids as $key => $assessment) {

            if (!$this->permission_to_grade($assessment)) {
                unset($this->course_assessment_ids[$key]);
            }
            // TODO get the group settings here and attach them to the array, avoiding a
            // get_groups_settings function call later for each submission

            // This assessment might be set to 'hidden'
            if (!block_ajax_marking_check_assessment_display_settings($this->modulename, $assessment->id, $courseid)) {
                unset($this->course_assessment_ids[$key]);
            }

        }

        $count = 0;

        // loop through all of the submissions, ignoring any that should not be counted
        foreach ($this->allsubmissions as $submission) {

            $check = null;

            // Is this assignment attached to this course?
            if ($submission->course != $courseid) {
                continue;
            }

            // the object may contain assessments with no submissions
            if (!isset($submission->userid)) {
                continue;
            } else {
                // is the submission from a current user of this course
                if (!in_array($submission->userid, $studentids)) {
                    continue;
                }
            }

            

            if (!block_ajax_marking_can_show_submission($this->modulename, $submission)) {
                continue;
            }
            $count++;
        }
        return $count;
    }
    
    /**
     * We need to check whether the assessment can be displayed (the user may have hidden it).
     * This sql can be dropped into a query so that it will get the right students
     * 
     * @param string $assessmenttablealias the SQL alias for the assessment table e.g. 'f' for forum
     * @param string $submissiontablealias the SQL alias for the submission table e.g. 's' 
     * @return array of 2 strings with the join and where parts in that order
     */
    protected function get_display_settings_sql($assessmenttablealias, $submissiontablealias) {
        
        // TODO this should use coursemoduleid
        // TODO needs testing
        
        // bama = block ajax marking assessment
        // bamc = block ajax marking course
        // gmc  = groups members courses
        
        $join = "LEFT JOIN {block_ajax_marking} bama
                        ON ({$assessmenttablealias}.id = bama.assessmentid AND bama.assessmenttype = '{$this->modulename}')
                 LEFT JOIN {block_ajax_marking} bamc
                        ON ({$assessmenttablealias}.course = bamc.assessmentid AND bamc.assessmenttype = 'course') ";
                        
        // either no settings, or definitely display
        // TODO doesn't work without proper join table for groups
                            
        // student might be a member of several groups. As long as one group is in the settings table, it's ok.
        // TODO is this more or less efficient than doing an inner join to a subquery?
        $groupsql = " EXISTS (SELECT 1 
                                FROM {groups_members} gm
                          INNER JOIN {groups} g
                                  ON gm.groupid = g.id 
                          INNER JOIN {block_ajax_marking_groups} gs
                                  ON g.id = gs.groupid
                               WHERE gm.userid = {$submissiontablealias}
                                 AND g.courseid = {$assessmenttablealias}.course
                                 AND gs.display = ".BLOCK_AJAX_MARKING_CONF_SHOW.") ";
                            
                            
        // where starts with the course defaults in case we find no assessment preference
        // Hopefully short circuit evaluation will makes this efficient.
        $where = " AND (( ( bama.showhide IS NULL 
                            OR bama.showhide = ".BLOCK_AJAX_MARKING_CONF_DEFAULT."
                          ) AND ( 
                            bamc.showhide IS NULL 
                            OR bamc.showhide = ".BLOCK_AJAX_MARKING_CONF_SHOW."
                            OR (bama.showhide = ".BLOCK_AJAX_MARKING_CONF_GROUPS. " AND {$groupsql})
                          )
                        ) ";
                    

        // now we look at the assessment options
        $where .= " OR bama.showhide = ".BLOCK_AJAX_MARKING_CONF_SHOW.
                  " OR (bama.showhide = ".BLOCK_AJAX_MARKING_CONF_GROUPS. " AND {$groupsql})) ";
        
        return array($join, $where);
        
    }

    /**
     * This function will check through all of the assessments of a particular type (depends on
     * instantiation - there is one of these objects made for each type of assessment) for a
     * particular course, then return the nodes for a course ready for the main tree
     *
     * @param int $courseid the id of the course
     * @param bool $html Are we making a HTML list?
     * @return mixed array or void depending on the html type
     */
    function course_assessment_nodes($courseid, $html=false, $config=false) {

        global $CFG, $SESSION, $USER;
        $dynamic = true;

        // the HTML list needs to know the count for the course
        $html_output = '';
        $html_count = 0;
        $nodes = array();

        // if the unmarked stuff for all courses has already been requested (html_list.php), filter
        // it to save a DB query.
        // this will be the case only if making the non-ajax <ul> list
        if (isset($this->allsubmissions) && !empty($this->submissions)) {

            $unmarked = new stdClass();

            foreach ($this->allsubmissions as $key => $submission) {

                if ($submission->course == $courseid) {
                     $unmarked->$key = $submission;
                }
            }
            $this->coursesubmissions[$courseid] = $unmarked;
        } else {
            // We have no data, so get it from the DB (normal ajax.php call)
            $this->coursesubmissions[$courseid] = $this->get_all_course_unmarked($courseid);

        }

        // now loop through the returned items, checking for permission to grade etc.

        // check that there is stuff to loop through
        if (isset($this->coursesubmissions[$courseid]) && !empty($this->coursesubmissions[$courseid])) {

            // we need all the assessment ids for the loop, so we make an array of them
            $assessments = block_ajax_marking_list_assessment_ids($this->coursesubmissions[$courseid]);

            foreach ($assessments as $assessment) {

                // counter for number of unmarked submissions
                $count = 0;

                // permission to grade?
                $modulecontext = get_context_instance(CONTEXT_MODULE, $assessment->cmid);

                if (!has_capability($this->capability, $modulecontext, $USER->id)) {
                    continue;
                }

                if (!$config) {

                    //we are making the main block tree, not the configuration tree

                    // retrieve the user-defined display settings for this assessment item
                    //$settings = $this->mainobject->get_groups_settings($this->type, $assessment->id);

                    // check if this item should be displayed at all
                    $oktodisplayassessment = block_ajax_marking_check_assessment_display_settings($this->modulename,
                                                                               $assessment->id,
                                                                               $courseid);

                    if (!$oktodisplayassessment) {
                        continue;
                    }

                    // If the submission is for this assignment and group settings are 'display all',
                    // or 'display by groups' and the user is a group member of one of them, count it.
                    foreach ($this->coursesubmissions[$courseid] as $assessment_submission) {

                        if ($assessment_submission->id == $assessment->id) {

                            if (!isset($assessment_submission->userid)) {
                                continue;
                            }

                            // if the item is set to group display, it may not be right to add the
                            // student's submission if they are in the wrong group
                            $oktodisplaysubmission = block_ajax_marking_can_show_submission($this->modulename, $assessment_submission);

                            if (!$oktodisplaysubmission) {
                                continue;
                            }
                            $count++;
                        }
                    }

                    // if there are no unmarked assignments, just skip this one. Important not to skip
                    // it in the SQL as config tree needs all assignments
                    if ($count == 0) {
                        continue;
                    }
                }

                // if there are only two levels, there will only need to be dynamic load if there are groups to display
                if (count($this->callbackfunctions === 0)) {

                    $assessment_settings = block_ajax_marking_get_groups_settings($this->modulename, $assessment->id);
                    $course_settings     = block_ajax_marking_get_groups_settings('course', $courseid);

                    if (($assessment_settings && $assessment_settings->showhide == BLOCK_AJAX_MARKING_CONF_GROUPS) ||
                        ($course_settings && $course_settings->showhide == BLOCK_AJAX_MARKING_CONF_GROUPS)) {
                            $assessment->callbackfunction = 'groups';
                    } else {
                        // will be 'submission' in most cases
                        $assessment->callbackfunction = isset($this->callbackfunctions[0]) ? $this->callbackfunctions[0] : false;
                    }
                }

                $assessment->count       = $count;
                $assessment->modulename  = $this->modulename;
                $assessment->icon        = block_ajax_marking_add_icon($this->modulename);

                if ($html) {
                    // make a node for returning as part of an array
                    $assessment->link    = $this->make_html_link($assessment);
                    $html_output .= block_ajax_marking_make_html_node($assessment);
                    $html_count += $assessment->count;
                } else {
                    // add this node to the JSON output object
                    $nodes[] = block_ajax_marking_make_assessment_node($assessment);
                }
            }

            if ($html) {
                // text string of <li> nodes
                $html_array = array($html_count, $html_output);
                return $html_array;
            } else {
                return $nodes;
            }
        }
    }

    /**
     * This counts the assessments that a course has available. Called when the config tree is built.
     *
     * @param int $course course id of the course we are counting for
     * @return int count of items
     */
    function count_course_assessment_nodes($course) {

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
    function config_assessment_nodes($course, $modulename) {

        $this->get_all_gradable_items();

        if ($this->assessments) {

            foreach ($this->assessments as $assessment) {

                $context = get_context_instance(CONTEXT_MODULE, $assessment->cmid);

                if (!$this->permission_to_grade($assessment)) {
                    continue;
                }

                if ($assessment->course == $course) {
                    $assessment->type = $this->modulename;
                    // TODO - alter SQL so that this line is not needed.
                    $assessment->description = $assessment->summary;
                    $assessment->dynamic = false;
                    $assessment->count = false;
                    return block_ajax_marking_make_assessment_node($assessment, true);
                }
            }
        }
    }

    /**
     * This is to allow the ajax call to be sent to the correct function. When the
     * type of one of the pluggable modules is sent back via the ajax call, the ajax_marking_response constructor
     * will refer to this function in each of the module objects in turn from the default in the switch statement
     *
     * @param string $type the type name variable from the ajax call
     * @return bool
     */
    function return_function($type, $args) {

        if (array_key_exists($type, $this->functions)) {
            $function = $this->functions[$type];
            call_user_func_array(array($this, $function), $args);
//            $this->$function();
            return true;
        } else {
            return false;
        }
    }

    /**
     * Getter function for the levels stored as part of the
     * pluggable modules object
     *
     * @return int the number of levels
     */
    function levels() {

        //$levels = count($this->functions) + 2;
        return $this->levels;
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
    protected function get_role_users_sql($context, $parent=true, $paramtype=SQL_PARAMS_NAMED) {

        global $CFG, $DB;

        $parentcontexts = '';
        $parentcontextssql = '';
        // need an empty one for array_merge() later
        $parentcontextsparams = array();

        $parentcontexts = substr($context->path, 1); // kill leading slash
        $parentcontexts = explode('/', $parentcontexts);

        if ($parentcontexts !== '') {
            list($parentcontextssql, $parentcontextsparams) = $DB->get_in_or_equal($parentcontexts, $paramtype, 'param9000');
        }

        // get the roles that are specified as graded in site config settings. Will sometimes be here,
        // sometimes not depending on ajax call
        
        // TODO does this work?
        $student_roles = $CFG->gradebookroles;

        // start this set of params at a later point to avoid collisions
        list($studentrolesql, $studentroleparams) = $DB->get_in_or_equal($student_roles, $paramtype, 'param0900');

        $sql = " SELECT DISTINCT(u.id)
                   FROM {role_assignments} ra
                   JOIN {user} u
                     ON u.id = ra.userid
                   JOIN {role} r
                     ON ra.roleid = r.id
                  WHERE ra.contextid {$parentcontextssql}
                    AND ra.roleid {$studentrolesql}";

        $data = array($sql, $parentcontextsparams + $studentroleparams);
        return $data;

    }

    /**
     * Returns an SQL snippet that will tell us whether a student is enrolled in this course
     * Needs to also check parent contexts.
     */
    private function get_enrolled_student_sql() {

        // TODO how does the normal enrolment check do this?
        
        // TODO what is an enrolment filter and how does it work?
        
        // begin stuff lifted from enrolment manager
        
        global $DB;
//        if ($direction !== 'ASC') {
//            $direction = 'DESC';
//        }
//        $key = md5("$sort-$direction-$page-$perpage");
//        if (!array_key_exists($key, $this->users)) {
            list($instancessql, $params, $filter) = $this->get_instance_sql();
            
            // begin stuff from get_instance_sql()
            
            global $DB;
            if ($this->_instancessql === null) {
                $instances = $this->get_enrolment_instances();
                $filter = $this->get_enrolment_filter();
                if ($filter && array_key_exists($filter, $instances)) {
                    $sql = " = :ifilter";
                    $params = array('ifilter'=>$filter);
                } else {
                    $filter = 0;
                    if ($instances) {
                        list($sql, $params) = $DB->get_in_or_equal(array_keys($this->get_enrolment_instances()), SQL_PARAMS_NAMED);
                    } else {
                        // no enabled instances, oops, we should probably say something
                        $sql = "= :never";
                        $params = array('never'=>-1);
                    }
                }
                $this->instancefilter = $filter;
                $this->_instancessql = array($sql, $params, $filter);
            }
            return $this->_instancessql;
            
            // end get_instance_sql() stuff
            
            
            
            
            
            $ufields = user_picture::fields('u', array('lastaccess', 'email'));
            $sql = "SELECT DISTINCT $ufields, ul.timeaccess AS lastseen
                      FROM {user} u
                      JOIN {user_enrolments} ue ON (ue.userid = u.id  AND ue.enrolid $instancessql)
                      JOIN {enrol} e ON (e.id = ue.enrolid)
                 LEFT JOIN {user_lastaccess} ul ON (ul.courseid = e.courseid AND ul.userid = u.id)";
            if ($sort === 'firstname') {
                $sql .= " ORDER BY u.firstname $direction, u.lastname $direction";
            } else if ($sort === 'lastname') {
                $sql .= " ORDER BY u.lastname $direction, u.firstname $direction";
            } else if ($sort === 'email') {
                $sql .= " ORDER BY u.email $direction, u.lastname $direction, u.firstname $direction";
            } else if ($sort === 'lastseen') {
                $sql .= " ORDER BY ul.timeaccess $direction, u.lastname $direction, u.firstname $direction";
            }
            $this->users[$key] = $DB->get_records_sql($sql, $params, $page*$perpage, $perpage);
//        }
//        return $this->users[$key];
        
        // end stuff lifted from enrolment manager


    }

    /**
     * Find the id of this module in the DB. It may vary from site to site
     *
     * @staticvar int $moduleid cache the thing to save DB queries
     * @return int the id of the module in the DB
     */
    protected function get_module_id() {
        
        global $DB;

        static $moduleid;

        if (isset($moduleid)) {
            return $moduleid;
        }

        $moduleid = $DB->get_field('modules', 'id', array('name' => $this->modulename));

        return $moduleid;
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
            
            //require_once($blockdirectoryfile);
            
            $PAGE->requires->js($blockdirectoryfile);
            
        } else {

            if (file_exists($CFG->dirroot.$moddirectoryfile)) {
                $PAGE->requires->js($moddirectoryfile);
            }
        }
        
    }


}

?>
