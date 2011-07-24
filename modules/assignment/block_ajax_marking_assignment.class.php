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
 * Class file for the Assignment grading functions
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

global $CFG;

require_once($CFG->dirroot.'/blocks/ajax_marking/modules/assignment/block_ajax_marking_assignment_form.class.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/classes/query_base.class.php');

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
    public function __construct() {
        
        // call parent constructor with the same arguments (keep for 2.1 - PHP 5.3 needed
        //call_user_func_array(array($this, 'parent::__construct'), func_get_args());
        parent::__construct();
        
        $this->modulename           = 'assignment';  // must be the same as the DB modulename
        $this->capability           = 'mod/assignment:grade';
        $this->icon                 = 'mod/assignment/icon.gif';
    }

     /**
     * gets all assignments that could potentially have
     * graded work, even if there is none there now. Used by the config tree.
     *
     * @return void
     */
//    function get_all_gradable_items($courseids) {
//
//        global $CFG, $DB;
//        list($usql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
//
//        $sql = "SELECT a.id, a.name, a.intro as summary, a.course, cm.id as cmid
//                  FROM {assignment} a
//            INNER JOIN {course_modules} cm
//                    ON a.id = cm.instance
//                 WHERE cm.module = :moduleid
//                   AND cm.visible = 1
//                   AND a.course $usql
//              ORDER BY a.id";
//        $params['moduleid'] = $this->get_module_id();
//        $assignments = $DB->get_records_sql($sql, $params);
//        $this->assessments = $assignments;
//
//    }

    /**
     * Makes a link for the pop up window so the work can be marked
     *
     * @param object $item a submission object
     * @return string
     */
    function make_html_link($item) {

        global $CFG;
        
        $address = $CFG->wwwroot.'/mod/assignment/submissions.php?id='.$item->coiursemoduleid;
        return $address;
    }
    
//    protected function get_sql_submission_table() {
//        return 'assignment_submissions';
//    }
    
    
    /**
     * See superclass for details
     * 
     * @return array the select, join and where clauses, with the aliases for module and submission tables
     */
//    protected function get_sql_count() {
//        
//        global $DB;
//        
//        $moduletable = $this->get_sql_module_table();
//        $submissiontable = $this->get_sql_submission_table();
//        
//        $from =     "FROM {{$moduletable}} moduletable
//               INNER JOIN {{$submissiontable}} sub
//                       ON sub.assignment = moduletable.id ";
//               
////        $subdatacompare = $DB->sql_compare_text($fieldname, $numchars=32);
//                       
//        $where =   "WHERE sub.timemarked < sub.timemodified
//                  AND NOT ((moduletable.resubmit = 0 
//                           AND sub.timemarked > 0)
//                       OR (".$DB->sql_compare_text('moduletable.assignmenttype')." = 'upload' 
//                           AND ".$DB->sql_compare_text('sub.data2')." != 'submitted')) ";
//        
//        $params = array();
//                       
//        return array($from, $where, $params);
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
     * @param object $coursemodule The coursemodule object that the user has been authenticated against
     */
    public function grading_popup($params, $coursemodule) {
        
        global $PAGE, $CFG, $DB, $OUTPUT, $USER;
        
        $output = '';
        
        // Get all DB stuff
        //$coursemodule = $DB->get_record('course_modules', array('id' => $params['cmid']));
        // use coursemodule->instance so that we have checked permissions properly
        $assignment = $DB->get_record('assignment', array('id' => $coursemodule->instance));
        $submission = $DB->get_record('assignment_submissions', array('assignment' => $coursemodule->instance, 
                                                                      'userid' => $params['userid']));
        
        if (!$submission) {
            print_error('No submission for this user');
            return;
        }
        
        $course         = $DB->get_record('course', array('id' => $assignment->course));
        $coursemodule   = $DB->get_record('course_modules', array('id' => $coursemodule->id));
        $context        = get_context_instance(CONTEXT_MODULE, $coursemodule->id);
        
        // TODO more sanity and security checks
        $user = $DB->get_record('user', array('id' => $submission->userid));
        
        if (!$user) {
            print_error('No user');
            return;
        }
        
        // Load up the required assignment code
        require_once($CFG->dirroot.'/mod/assignment/type/'.$assignment->assignmenttype.'/assignment.class.php');
        $assignmentclass = 'assignment_'.$assignment->assignmenttype;
        $assignmentinstance = new $assignmentclass($coursemodule->id, $assignment, $coursemodule, $course);

        require_once($CFG->libdir.'/gradelib.php');
        require_once("$CFG->dirroot/repository/lib.php");
        
        $grading_info = grade_get_grades($course->id, 'mod', 'assignment', $assignment->id, array($user->id));
        $gradingdisabled = $grading_info->items[0]->grades[$user->id]->locked || $grading_info->items[0]->grades[$user->id]->overridden;

        $assignmentinstance->preprocess_submission($submission);

        $mformdata = new stdClass();
        $mformdata->context                   = $context;
        $mformdata->maxbytes                  = $course->maxbytes;
        $mformdata->courseid                  = $course->id;
        $mformdata->teacher                   = $USER;
        $mformdata->assignment                = $assignment;
        $mformdata->submission                = $submission;
        $mformdata->lateness                  = assignment_display_lateness($submission->timemodified, $assignment->timedue);
        //$mformdata->auser = $auser;
        $mformdata->user                      = $user;
        $mformdata->offset                    = false;
        $mformdata->userid                    = $user->id;
        $mformdata->cm                        = $coursemodule;
        $mformdata->grading_info              = $grading_info;
        $mformdata->enableoutcomes            = $CFG->enableoutcomes;
        $mformdata->grade                     = $assignment->grade;
        $mformdata->gradingdisabled           = $gradingdisabled;
        // TODO set nextid to the nextnode id
        $mformdata->nextid                    = false;
        $mformdata->submissioncomment         = $submission->submissioncomment;
        $mformdata->submissioncommentformat   = FORMAT_HTML;
        $mformdata->submission_content        = $assignmentinstance->print_user_files($user->id,true);
//        $mformdata->filter = $filter;
        
        if ($assignment->assignmenttype == 'upload') {
            $mformdata->fileui_options = array(
                    'subdirs' => 1, 
                    'maxbytes' => $assignment->maxbytes, 
                    'maxfiles' => $assignment->var1, 
                    'accepted_types' => '*', 
                    'return_types'=>FILE_INTERNAL);
            
        } else if ($assignment->assignmenttype == 'uploadsingle') {
            $mformdata->fileui_options = array(
                    'subdirs' => 0, 
                    'maxbytes' => $CFG->userquota, 
                    'maxfiles' => 1, 
                    'accepted_types' => '*', 
                    'return_types' => FILE_INTERNAL);
        }
        
        // Here, we start to make a specific HTML display, rather than just getting data

        $submitform = new mod_assignment_grading_form(block_ajax_marking_form_url($params), $mformdata);
//        $submitform = new block_ajax_marking_assignment_form(block_ajax_marking_form_url($params), $mformdata);

//         if (!$display) {
//            $ret_data = new stdClass();
//            $ret_data->mform = $submitform;
//            $ret_data->fileui_options = $mformdata->fileui_options;
//            return $ret_data;
//        }

//        if ($submitform->is_cancelled()) {
//            redirect('submissions.php?id='.$this->cm->id);
//        }

        $submitform->set_data($mformdata);

        $PAGE->set_title($course->fullname . ': ' .get_string('feedback', 'assignment').' - '.fullname($user, true));
        $PAGE->set_heading($course->fullname);
//        $PAGE->navbar->add(get_string('submissions', 'assignment'), new moodle_url('/mod/assignment/submissions.php', array('id' => $details['coursemoduleid'])));
//        $PAGE->navbar->add(fullname($user, true));

//        echo $OUTPUT->header();
        $output .= $OUTPUT->heading(get_string('feedback', 'assignment').': '.fullname($user, true));

        // display mform here...
        $output .= $submitform->display();

        // no variation across subclasses
        $customfeedback = $assignmentinstance->custom_feedbackform($submission, true);
        
        if (!empty($customfeedback)) {
            $output .= $customfeedback;
        }

        return $output;
        
    }
    
    /**
     * Process and save the data from the feedback form
     * 
     * @param object $data from the feedback form
     * @return void
     */
    public function process_data($data, $params) {
        
        // from $assignmentinstance->process_feedback():
        //
        global $CFG, $USER, $DB, $PAGE;
        
//        $submitform = new mod_assignment_grading_form();
//        $data = $submitform->get_data();
        
        if (!$data || !$params) {      // No incoming data?
            return 'No incoming data';
        }
        
        // TODO validate data
        
        require_once($CFG->libdir.'/gradelib.php');
        require_once("$CFG->dirroot/repository/lib.php");

        // For save and next, we need to know the userid to save, and the userid to go
        // We use a new hidden field in the form, and set it to -1. If it's set, we use this
        // as the userid to store
        
        // This seems to be something that the pop up javascript will change in the normal run of things.
        // Normally it will be the -1 default.
        if ((int)$data->saveuserid !== -1){
            $data->userid = $data->saveuserid;
        }
        
        if (!empty($data->cancel)) {          // User hit cancel button
            return 'cancelled';
        }
        
        // get DB records
        $coursemodule = $DB->get_record('course_modules', array('id' => $params['coursemoduleid']));
        $assignment   = $DB->get_record('assignment', array('id' => $coursemodule->instance));
        $grading_info = grade_get_grades($coursemodule->course, 'mod', 'assignment', $assignment->id, $data->userid);
        $submission   = $DB->get_record('assignment_submissions', array('assignment' => $assignment->id, 
                                                                      'userid' => $data->userid)); 
        
        if (!$submission) {
            return 'Wrong submission id';
        }
        
        if (!$coursemodule) {
            return 'Wrong coursemodule id';
        }
        
        if (!$assignment) {
            return 'Wrong assignment id';
        }
        
        if (!$grading_info) {
            return 'Could not retrieve grading info.';
        }
        
        // Check to see if grade has been locked or overridden
        if (!($grading_info->items[0]->grades[$data->userid]->locked ||
            $grading_info->items[0]->grades[$data->userid]->overridden) ) {
            
            // Save outcomes if necessary
            if (!empty($CFG->enableoutcomes)) {

                $outcomedata = array();

                if (!empty($grading_info->outcomes)) {

                    foreach($grading_info->outcomes as $n=>$old) {
                        $name = 'outcome_'.$n;

                        if (isset($formdata->{$name}[$userid]) and $old->grades[$userid]->grade != $formdata->{$name}[$userid]) {
                            $outcomedata[$n] = $formdata->{$name}[$userid];
                        }
                    }

                    if (count($outcomedata) > 0) {
                        grade_update_outcomes('mod/assignment', $this->course->id, 'mod', 'assignment', 
                                              $this->assignment->id, $userid, $outcomedata);
                    }
                }
            }

            // Prepare the submission object
            $submission->grade      = $data->xgrade;
            $submission->submissioncomment    = $data->submissioncomment_editor['text'];
            $submission->teacher    = $USER->id;
            $submission->timemarked = time();
            
            $mailinfo = get_user_preferences('assignment_mailinfo', 0);
            
            if (!$mailinfo) {
                $submission->mailed = 1; // treat as already mailed
            } else {
                $submission->mailed = 0; // Make sure mail goes out (again, even)
            }

            unset($submission->data1);  // Don't need to update this.
            unset($submission->data2);  // Don't need to update this.

//            if (empty($submission->timemodified)) {   // eg for offline assignments
//                // $submission->timemodified = time();
//            }

            // Save submission
            $saveresult = $DB->update_record('assignment_submissions', $submission);
            
            if (!$saveresult) {
                return 'Problem saving feedback';
            }

            // Trigger grade event to update gradebook
            $assignment->cmidnumber = $coursemodule->id;
            assignment_update_grades($assignment, $submission->userid);

            add_to_log($coursemodule->course, 'assignment', 'update grades',
                       'submissions.php?id='.$coursemodule->id.'&user='.$data->userid, $data->userid, $coursemodule->id);
            
            // Save files if necessary
            if (!is_null($data)) {

                if ($assignment->assignmenttype == 'upload' || $assignment->assignmenttype == 'uploadsingle') {
                    //$mformdata = $formdata->mform->get_data();
                    if ($assignment->assignmenttype == 'upload') {
                        $fileui_options = array('subdirs'=>1, 'maxbytes'=>$assignment->maxbytes, 'maxfiles'=>$assignment->var1, 'accepted_types'=>'*', 'return_types'=>FILE_INTERNAL);
                    } else if ($assignment->assignmenttype == 'uploadsingle') {
                        $fileui_options = array('subdirs'=>0, 'maxbytes'=>$CFG->userquota, 'maxfiles'=>1, 'accepted_types'=>'*', 'return_types'=>FILE_INTERNAL);
                    }

                    $mformdata = file_postupdate_standard_filemanager($data, 'files', $fileui_options, $PAGE->context, 'mod_assignment', 'response', $submission->id);
                }
            }
        }

        return true;
    }
    
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
                'table' => 'assignment',
                'alias' => 'moduletable',
        ));

        $query->add_from(array(
                'join' => 'INNER JOIN',
                'table' => 'assignment_submissions',
                'alias' => 'sub',
                'on' => 'sub.assignment = moduletable.id'
        ));

        $query->add_where(array('type' => 'AND', 'condition' => 'sub.timemarked < sub.timemodified'));
        $query->add_where(array('type' => 'AND', 'condition' => 
                "NOT ( (moduletable.resubmit = 0 AND sub.timemarked > 0)
                       OR (".$DB->sql_compare_text('moduletable.assignmenttype')." = 'upload' 
                           AND ".$DB->sql_compare_text('sub.data2')." != 'submitted') )"));

        return $query;
    }
    
    
    
    public function apply_userid_filter(block_ajax_marking_query_base $query, $userid) {
        
        if (!$userid) { // display submissions - final nodes
        
            $data = new stdClass;
            $data->nodetype = 'submission';

//            $usercolumnalias   = $query->get_userid_column();

            $selects = array(
                array(
                    'table' => 'sub', 
                    'column' => 'id',
                    'alias' => 'subid'),
                array(
                    'table' => 'user',
                    'column' => 'firstname'),
                array(
                    'table' => 'user',
                    'column' => 'lastname'),
                array( // Count in case we have user as something other than the last node
                    'function' => 'COUNT',
                    'table'    => 'sub',
                    'column'   => 'id',
                    'alias'    => 'count'),
                array(
                    'table' => 'sub', 
                    'column' => 'timemodified',
                    'alias' => 'time'),
                array(
                    'table' => 'sub', 
                    'column' => 'userid'),
                    // This is only needed to add the right callback function. 
                array(
                    'column' => "'".$this->modulename."'",
                    'alias' => 'modulename'
                    )
            );
            
            foreach ($selects as $select) {
                $query->add_select($select);
            }
            
            $query->add_from(array(
                    'join' => 'INNER JOIN',
                    'table' => 'user',
                    'on' => 'user.id = sub.userid'));
            
        } else {
            // Not sure we'll ever need this, but just in case...
            $query->add_where(array(
                    'type' => 'AND', 
                    'condition' => 'sub.id = :'.$query->prefix_param_name('submissionid')));
            $query->add_param('submissionid', $userid);
        }
    }
    
    
    
}