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

require_once($CFG->dirroot.'/blocks/ajax_marking/classes/query_base.class.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/classes/module_base.class.php');

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

        // Call parent constructor with the same arguments (keep for 2.1 - PHP 5.3 needed.
        parent::__construct();

        $this->modulename           = $this->moduletable = 'assignment';  // DB modulename.
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
     * @global stdClass $CFG
     * @global moodle_database $DB
     * @global type $OUTPUT
     * @global stdClass $USER
     * @param array $params From $_GET
     * @param object $coursemodule The coursemodule object that the user has been authenticated
     * against
     * @return string
     */
    public function grading_popup($params, $coursemodule) {

        global $PAGE, $CFG, $DB, $OUTPUT, $USER;

        require_once($CFG->dirroot.'/grade/grading/lib.php');

        $PAGE->requires->js('/mod/assignment/assignment.js');

        $output = '';

        // Get all DB stuff.
        // Use coursemodule->instance so that we have checked permissions properly.
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
        $context        = context_module::instance($coursemodule->id);

        // TODO more sanity and security checks.
        $user = $DB->get_record('user', array('id' => $submission->userid));

        if (!$user) {
            print_error('No user');
            return false;
        }

        // Load up the required assignment code.
        require_once($CFG->dirroot.'/mod/assignment/type/'.$assignment->assignmenttype.
                     '/assignment.class.php');
        $assignmentclass = 'assignment_'.$assignment->assignmenttype;
        /*
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
        // TODO set nextid to the nextnode id.
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

        $advancedgradingwarning = false;
        $gradingmanager = get_grading_manager($context, 'mod_assignment', 'submission');
        if ($gradingmethod = $gradingmanager->get_active_method()) {
            $controller = $gradingmanager->get_controller($gradingmethod);
            if ($controller->is_form_available()) {
                $itemid = null;
                if (!empty($submission->id)) {
                    $itemid = $submission->id;
                }
                if ($gradingdisabled && $itemid) {
                    $mformdata->advancedgradinginstance =
                        $controller->get_current_instance($USER->id, $itemid);
                } else if (!$gradingdisabled) {
                    $instanceid = optional_param('advancedgradinginstanceid', 0, PARAM_INT);
                    $mformdata->advancedgradinginstance =
                        $controller->get_or_create_instance($instanceid, $USER->id, $itemid);
                }
            } else {
                $advancedgradingwarning = $controller->form_unavailable_notification();
            }
        }

        // Here, we start to make a specific HTML display, rather than just getting data.

        $submitform = new mod_assignment_grading_form(block_ajax_marking_form_url($params),
                                                      $mformdata);
        $submitform->set_data($mformdata);

        $PAGE->set_title($course->fullname . ': ' .get_string('feedback', 'assignment').' - '.
                         fullname($user, true));
        $heading = get_string('feedback', 'assignment').': '.fullname($user, true);
        $output .= $OUTPUT->heading($heading);

        // Display mform here...
        ob_start();
        if ($advancedgradingwarning) {
            echo $OUTPUT->notification($advancedgradingwarning, 'error');
        }
        $submitform->display();
        $output .= ob_get_contents();
        ob_end_clean();

        // No variation across subclasses.
        $customfeedback = $assignmentinstance->custom_feedbackform($submission, true);
        if (!empty($customfeedback)) {
            $output .= $customfeedback;
        }

        return $output;
    }

    /**
     * Process and save the data from the feedback form. Mostly lifted from
     * $assignmentinstance->process_feedback().
     *
     * @param object $data from the feedback form
     * @param $params
     * @return string|void
     */
    public function process_data($data, $params) {

        global $CFG, $USER, $DB, $PAGE;

        if (!$data || !$params) {      // No incoming data?
            return 'No incoming data';
        }

        // TODO validate data.

        require_once($CFG->libdir.'/gradelib.php');
        require_once("$CFG->dirroot/repository/lib.php");

        // For save and next, we need to know the userid to save, and the userid to go
        // We use a new hidden field in the form, and set it to -1. If it's set, we use this
        // as the userid to store.

        // This seems to be something that the pop up javascript will change in the normal run of
        // things. Normally it will be the -1 default.
        if ((int)$data->saveuserid !== -1) {
            $data->userid = $data->saveuserid;
        }

        if (!empty($data->cancel)) { // User hit cancel button.
            return 'cancelled';
        }

        // Get DB records.
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
        // Check to see if grade has been locked or overridden.
        if (($grading_info->items[0]->grades[$data->userid]->locked ||
            $grading_info->items[0]->grades[$data->userid]->overridden) ) {
            return 'Grade is locked or overridden';
        }

        // Save outcomes if necessary.
        if (!empty($CFG->enableoutcomes)) {
            $this->save_outcomes($assignment, $userid, $grading_info, $data);
        }

        // Prepare the submission object.
        $submission->grade      = $data->xgrade;
        $submission->submissioncomment    = $data->submissioncomment_editor['text'];
        $submission->teacher    = $USER->id;
        $submission->timemarked = time();
        unset($submission->data1);  // Don't need to update this.
        unset($submission->data2);  // Don't need to update this.

        $mailinfo = get_user_preferences('assignment_mailinfo', 0);
        if (!$mailinfo) {
            $submission->mailed = 1; // Treat as already mailed.
        } else {
            $submission->mailed = 0; // Make sure mail goes out (again, even).
        }

        // Save submission.
        $saveresult = $DB->update_record('assignment_submissions', $submission);
        if (!$saveresult) {
            return 'Problem saving feedback';
        }

        // Trigger grade event to update gradebook.
        $assignment->cmidnumber = $coursemodule->id;
        assignment_update_grades($assignment, $submission->userid);

        add_to_log($coursemodule->course, 'assignment', 'update grades',
                   'submissions.php?id='.$coursemodule->id.'&user='.$data->userid,
                   $data->userid, $coursemodule->id);

        // Save files if necessary.
        if (!is_null($data)) {
            $this->save_files($assignment, $submission, $data, $PAGE, $CFG);
        }

        return true;
    }

    /**
     * Saves any files that the teacher may have attached or embedded.
     *
     * @param $assignment
     * @param $submission
     * @param $data
     * @param $PAGE
     * @param $CFG
     */
    private function save_files($assignment, $submission, $data, $PAGE, $CFG) {
        global $PAGE, $CFG;

        $isupload = $assignment->assignmenttype == 'upload';
        $isuploadsingle = $assignment->assignmenttype == 'uploadsingle';
        if ($isupload || $isuploadsingle) {
            $fileui_options = array();
            if ($isupload) {
                $fileui_options = array(
                    'subdirs' => 1,
                    'maxbytes' => $assignment->maxbytes,
                    'maxfiles' => $assignment->var1,
                    'accepted_types' => '*',
                    'return_types' => FILE_INTERNAL);
            } else if ($isuploadsingle) {
                $fileui_options = array(
                    'subdirs' => 0,
                    'maxbytes' => $CFG->userquota,
                    'maxfiles' => 1,
                    'accepted_types' => '*',
                    'return_types' => FILE_INTERNAL);
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

    /**
     * Saves outcome data from the form
     *
     * @param $assignment
     * @param $userid
     * @param $grading_info
     * @param $data
     */
    private function save_outcomes($assignment, $userid, $grading_info, $data) {
        $outcomedata = array();

        // TODO needs sorting out!
        if (!empty($grading_info->outcomes)) {

            foreach ($grading_info->outcomes as $n => $old) {
                $name = 'outcome_'.$n;
                $newvalue = $old->grades[$userid]->grade != $data->{$name}[$userid];
                if (isset($data->{$name}[$userid]) and $newvalue) {
                    $outcomedata[$n] = $data->{$name}[$userid];
                }
            }

            if (count($outcomedata) > 0) {
                grade_update_outcomes('mod/assignment', $assignment->course, 'mod',
                                      'assignment', $assignment->id, $userid,
                                      $outcomedata);
            }
        }
    }

    /**
     * Returns a query object with the basics all set up to get assignment stuff
     *
     * @global moodle_database $DB
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

        // Standard userid for joins.
        $query->add_select(array('table' => 'sub',
                                 'column' => 'userid'));
        $query->add_select(array('table' => 'sub',
                                'column' => 'timemodified',
                                'alias' => 'timestamp'));

        // First bit: not graded
        // Second bit of first bit: has been resubmitted
        // Third bit: if it's advanced upload, only care about the first bit if 'send for marking'
        // was clicked.
        $query->add_where(array('type' => 'AND',
                                'condition' =>
                                "( (sub.grade = -1 AND sub.submissioncomment = '') OR
                                   (moduletable.resubmit = 1 AND (sub.timemodified > sub.timemarked)) )
                                 AND ( moduletable.assignmenttype != 'upload'
                                       OR (moduletable.assignmenttype = 'upload' AND sub.data2 = 'submitted')) "));

        // TODO only sent for marking.

        // Advanced upload: data2 will be 'submitted' and grade will be -1, but if 'save changes'
        // is clicked, timemarked will be set to time(), but actually, grade and comment may
        // still be empty.

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

                // Make the count be grouped by userid.
                $countwrapper->add_select(array(
                        'table'    => 'moduleunion',
                        'column'   => 'userid',
                        'alias'    => 'id'), true
                );
                $query->add_select(array(
                        'table'  => 'countwrapperquery',
                        'column' => 'timestamp',
                        'alias'  => 'tooltip')
                );
                // Need this to make the popup show properly because some assignment code shows or
                // not depending on this flag to tell if it's in a pop-up e.g. the revert to draft
                // button for advanced upload.
                $query->add_select(array('column' => "'single'",
                                         'alias' => 'mode')
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
