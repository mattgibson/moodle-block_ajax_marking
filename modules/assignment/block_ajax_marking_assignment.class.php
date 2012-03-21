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

require_once($CFG->dirroot.'/blocks/ajax_marking/modules/assignment/'.
             'block_ajax_marking_assignment_form.class.php');
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
 * request and a module object is instantiated ready to be used. For efficiency, only installed
 * modules which have grading code available are included & instantiated, so there is a list kept in
 * the block's config data saying which modules have available module_grading.php files based on a
 * search conducted each time the block is upgraded by the {@link amb_update_modules()} function.
 *
 * @copyright 2008 Matt Gibson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_ajax_marking_assignment extends block_ajax_marking_module_base {

    /**
     * Constructor. Needs to be duplicated in all modules, so best put in parent. PHP4 issue though.
     *
     * The aim is to pass in the main ajax_marking_functions object by reference, so that its
     * properties are accessible
     *
     * @internal param object $reference the parent object to be referred to
     * @return \block_ajax_marking_assignment
     */
    public function __construct() {

        // call parent constructor with the same arguments (keep for 2.1 - PHP 5.3 needed
        parent::__construct();

        $this->modulename           = $this->moduletable = 'assignment';  // DB modulename
        $this->capability           = 'mod/assignment:grade';
        $this->icon                 = 'mod/assignment/icon.gif';
    }

    /**
     * Makes a link for the pop up window so the work can be marked
     *
     * @param object $item a submission object
     * @return string
     */
    public function make_html_link($item) {

        global $CFG;

        $address = $CFG->wwwroot.'/mod/assignment/submissions.php?id='.$item->coiursemoduleid;
        return $address;
    }

    /**
     * Makes the grading interface for the pop up
     *
     * @global type $PAGE
     * @global type $CFG
     * @global type $DB
     * @global type $OUTPUT
     * @global type $USER
     * @param array $params From $_GET
     * @param object $coursemodule The coursemodule object that the user has been authenticated
     * against
     * @return string
     */
    public function grading_popup($params, $coursemodule) {

        global $PAGE, $CFG, $DB, $OUTPUT, $USER;

        $output = '';

        // Get all DB stuff
        // use coursemodule->instance so that we have checked permissions properly
        $assignment = $DB->get_record('assignment', array('id' => $coursemodule->instance));
        $submission = $DB->get_record('assignment_submissions',
                                      array('assignment' => $coursemodule->instance,
                                            'userid' => $params['userid']));

        if (!$submission) {
            print_error('No submission for this user');
            return false;
        }

        $course         = $DB->get_record('course', array('id' => $assignment->course));
        $coursemodule   = $DB->get_record('course_modules', array('id' => $coursemodule->id));
        $context        = get_context_instance(CONTEXT_MODULE, $coursemodule->id);

        // TODO more sanity and security checks
        $user = $DB->get_record('user', array('id' => $submission->userid));

        if (!$user) {
            print_error('No user');
            return false;
        }

        // Load up the required assignment code
        require_once($CFG->dirroot.'/mod/assignment/type/'.$assignment->assignmenttype.
                     '/assignment.class.php');
        $assignmentclass = 'assignment_'.$assignment->assignmenttype;
        /**
         * @var assignment_base $assignmentinstance
         */
        $assignmentinstance = new $assignmentclass($coursemodule->id, $assignment,
                                                   $coursemodule, $course);

        require_once($CFG->libdir.'/gradelib.php');
        require_once("$CFG->dirroot/repository/lib.php");

        $grading_info = grade_get_grades($course->id, 'mod', 'assignment',
                                         $assignment->id, array($user->id));
        $locked = $grading_info->items[0]->grades[$user->id]->locked;
        $overridden = $grading_info->items[0]->grades[$user->id]->overridden;
        $gradingdisabled = $locked || $overridden;

        $assignmentinstance->preprocess_submission($submission);

        $mformdata = new stdClass();
        $mformdata->context                 = $context;
        $mformdata->maxbytes                = $course->maxbytes;
        $mformdata->courseid                = $course->id;
        $mformdata->teacher                 = $USER;
        $mformdata->assignment              = $assignment;
        $mformdata->submission              = $submission;
        $mformdata->lateness                = assignment_display_lateness($submission->timemodified,
                                                                          $assignment->timedue);
        $mformdata->user                    = $user;
        $mformdata->offset                  = false;
        $mformdata->userid                  = $user->id;
        $mformdata->cm                      = $coursemodule;
        $mformdata->grading_info            = $grading_info;
        $mformdata->enableoutcomes          = $CFG->enableoutcomes;
        $mformdata->grade                   = $assignment->grade;
        $mformdata->gradingdisabled         = $gradingdisabled;
        // TODO set nextid to the nextnode id
        $mformdata->nextid                  = false;
        $mformdata->submissioncomment       = $submission->submissioncomment;
        $mformdata->submissioncommentformat = FORMAT_HTML;
        $mformdata->submission_content      = $assignmentinstance->print_user_files($user->id,
                                                                                    true);

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

        $submitform = new mod_assignment_grading_form(block_ajax_marking_form_url($params),
                                                      $mformdata);
        $submitform->set_data($mformdata);

        $PAGE->set_title($course->fullname . ': ' .get_string('feedback', 'assignment').' - '.
                         fullname($user, true));
        $PAGE->set_heading($course->fullname);
        $heading = get_string('feedback', 'assignment').': '.fullname($user, true);
        $output .= $OUTPUT->heading($heading);

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
     * @param $params
     * @return string
     */
    public function process_data($data, $params) {

        // from $assignmentinstance->process_feedback():

        global $CFG, $USER, $DB, $PAGE;

        if (!$data || !$params) {      // No incoming data?
            return 'No incoming data';
        }

        // TODO validate data

        require_once($CFG->libdir.'/gradelib.php');
        require_once("$CFG->dirroot/repository/lib.php");

        // For save and next, we need to know the userid to save, and the userid to go
        // We use a new hidden field in the form, and set it to -1. If it's set, we use this
        // as the userid to store

        // This seems to be something that the pop up javascript will change in the normal run of
        // things. Normally it will be the -1 default.
        if ((int)$data->saveuserid !== -1) {
            $data->userid = $data->saveuserid;
        }

        if (!empty($data->cancel)) {          // User hit cancel button
            return 'cancelled';
        }

        // get DB records
        $coursemodule = $DB->get_record('course_modules', array('id' => $params['coursemoduleid']));
        $assignment   = $DB->get_record('assignment', array('id' => $coursemodule->instance));
        $grading_info = grade_get_grades($coursemodule->course, 'mod', 'assignment',
                                         $assignment->id, $data->userid);
        $userid = $data->userid;
        $submission   = $DB->get_record('assignment_submissions',
                                        array('assignment' => $assignment->id,
                                              'userid' => $userid));

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

                // TODO needs sorting out!
                if (!empty($grading_info->outcomes)) {

                    foreach ($grading_info->outcomes as $n => $old) {
                        $name = 'outcome_'.$n;
                        $newvalue = $old->grades[$userid]->grade != $formdata->{$name}[$userid];
                        if (isset($formdata->{$name}[$userid]) and $newvalue) {
                            $outcomedata[$n] = $formdata->{$name}[$userid];
                        }
                    }

                    if (count($outcomedata) > 0) {
                        grade_update_outcomes('mod/assignment', $assignment->course, 'mod',
                                              'assignment', $assignment->id, $userid,
                                              $outcomedata);
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

            // Save submission
            $saveresult = $DB->update_record('assignment_submissions', $submission);

            if (!$saveresult) {
                return 'Problem saving feedback';
            }

            // Trigger grade event to update gradebook
            $assignment->cmidnumber = $coursemodule->id;
            assignment_update_grades($assignment, $submission->userid);

            add_to_log($coursemodule->course, 'assignment', 'update grades',
                       'submissions.php?id='.$coursemodule->id.'&user='.$data->userid,
                       $data->userid, $coursemodule->id);

            // Save files if necessary
            if (!is_null($data)) {
                $isupload = $assignment->assignmenttype == 'upload';
                $isuploadsingle = $assignment->assignmenttype == 'uploadsingle';
                if ($isupload || $isuploadsingle) {
                    if ($isupload) {
                        $fileui_options = array(
                            'subdirs'=>1,
                            'maxbytes'=>$assignment->maxbytes,
                            'maxfiles'=>$assignment->var1,
                            'accepted_types'=>'*',
                            'return_types'=>FILE_INTERNAL);
                    } else if ($isuploadsingle) {
                        $fileui_options = array(
                            'subdirs'=>0,
                            'maxbytes'=>$CFG->userquota,
                            'maxfiles'=>1,
                            'accepted_types'=>'*',
                            'return_types'=>FILE_INTERNAL);
                    }

                    file_postupdate_standard_filemanager($data,
                                                         'files',
                                                         $fileui_options,
                                                         $PAGE->context,
                                                         'mod_assignment',
                                                         'response',
                                                         $submission->id);
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
    public function query_factory() {

        global $DB;

        $query = new block_ajax_marking_query_base($this);

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

        // Standard userid for joins
        $query->add_select(array('table' => 'sub',
                                 'column' => 'userid'));

        $query->add_where(array(
                               'type' => 'AND',
                               'condition' => 'sub.timemarked < sub.timemodified'));
        $query->add_where(array('type' => 'AND', 'condition' =>
                "NOT ( (moduletable.resubmit = 0 AND sub.timemarked > 0)
                       OR (".$DB->sql_compare_text('moduletable.assignmenttype')." = 'upload'
                           AND ".$DB->sql_compare_text('sub.data2')." != 'submitted') )"));

        return $query;
    }


    /**
     * Applies filtering when assignment submission nodes need displaying by userid. Currently,
     * this means that only the displayselect and countselect bit are used.
     *
     * @param block_ajax_marking_query_base $query
     * @param $operation
     * @param bool $userid
     * @return void
     */
    public function apply_userid_filter(block_ajax_marking_query_base $query, $operation,
                                        $userid = false) {

        $selects = array();
        $countwrapper = $query->get_subquery('countwrapperquery');

        switch ($operation) {

            case 'where':
                // Not sure we'll ever need this, but just in case...
                $countwrapper->add_where(array(
                        'type' => 'AND',
                        'condition' => 'sub.userid = :assignmentuseridfilteruserid'));
                $query->add_param('assignmentuseridfilteruserid', $userid);
                break;

            case 'countselect':

                // Make the count be grouped by userid
                $countwrapper->add_select(array(
                        'table'    => 'moduleunion',
                        'column'   => 'userid',
                        'alias'    => 'id'), true
                );

                $selects = array(
                    array(
                        'table' => 'usertable',
                        'column' => 'firstname'),
                    array(
                        'table' => 'usertable',
                        'column' => 'lastname')
                );

                $query->add_from(array(
                        'table' => 'user',
                        'alias' => 'usertable',
                        'on' => 'usertable.id = countwrapperquery.id'));

                break;
        }

        foreach ($selects as $select) {
            $query->add_select($select);
        }
    }



}
