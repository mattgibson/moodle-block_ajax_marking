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
require_once($CFG->dirroot.'/blocks/ajax_marking/classes/query_union.class.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/classes/module_base.class.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/modules/assignment/block_ajax_marking_assignment_form.class.php');
require_once($CFG->dirroot . '/grade/grading/lib.php');

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

        $this->modulename           = 'assignment';  // DB modulename.
        $this->capability           = 'mod/assignment:grade';
        $this->icon                 = 'mod/assignment/icon.gif';
    }

    /**
     * Makes the grading interface for the pop up
     *
     * @param array $params From $_GET
     * @param object $coursemodule The coursemodule object that the user has been authenticated
     * against
     * @param bool $data
     * @global $PAGE
     * @global stdClass $CFG
     * @global moodle_database $DB
     * @global $OUTPUT
     * @global stdClass $USER
     * @return string
     */
    public function grading_popup(array $params, $coursemodule, $data = false) {

        global $PAGE, $CFG, $DB, $OUTPUT;

        require_once($CFG->dirroot.'/grade/grading/lib.php');
        require_once($CFG->libdir.'/gradelib.php');
        require_once("$CFG->dirroot/repository/lib.php");

        $PAGE->requires->js('/mod/assignment/assignment.js');

        // Get all DB stuff.
        // Use coursemodule->instance so that we have checked permissions properly.
        $assignment = $DB->get_record('assignment', array('id' => $coursemodule->instance));
        $submission = $DB->get_record('assignment_submissions',
                                      array('assignment' => $coursemodule->instance,
                                            'userid' => $params['userid']), '*', MUST_EXIST);
        $course         = $DB->get_record('course', array('id' => $assignment->course));
        $coursemodule   = $DB->get_record('course_modules', array('id' => $coursemodule->id));
        $user           = $DB->get_record('user', array('id' => $submission->userid),
                                          '*', MUST_EXIST);

        $assignmentinstance = $this->get_assignment_instance($assignment, $coursemodule, $course);

        $assignmentinstance->preprocess_submission($submission);

        // Sort out the form ready to tell it to display.
        list($mformdata, $advancedgradingwarning) =
            $this->get_mform_data_object($course, $assignment, $submission, $user,
                                         $coursemodule, $assignmentinstance);
        $submitform = new block_ajax_marking_assignment_form(block_ajax_marking_form_url($params),
                                                      $mformdata);

        $submitform->set_data($mformdata);

        // Make the actual page output.
        $PAGE->set_title($course->fullname . ': ' .get_string('feedback', 'assignment').' - '.
                         fullname($user, true));
        $heading = get_string('feedback', 'assignment').': '.fullname($user, true);
        $output = '';
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
     * Load up the required assignment code.
     *
     * @param $assignment
     * @param $coursemodule
     * @param $course
     * @return assignment_base
     */
    private function get_assignment_instance($assignment, $coursemodule, $course) {
        global $CFG;

        require_once($CFG->dirroot.'/mod/assignment/type/'.$assignment->assignmenttype.
            '/assignment.class.php');
        $assignmentclass = 'assignment_'.$assignment->assignmenttype;
        $assignmentinstance = new $assignmentclass($coursemodule->id, $assignment,
                                                   $coursemodule, $course);
        return $assignmentinstance;
    }

    /**
     * Prepares the data for the grading form.
     *
     * @param $course
     * @param $assignment
     * @param $submission
     * @param $user
     * @param $coursemodule
     * @param assignment_base $assignmentinstance
     * @global $USER
     * @global $CFG
     * @return array
     */
    private function get_mform_data_object($course, $assignment, $submission, $user,
                                          $coursemodule, $assignmentinstance, $skiprender = false) {

        global $USER, $CFG, $PAGE;

        $context = context_module::instance($coursemodule->id);
        // Get grading information to see whether we should be allowed to make changed at all.
        $grading_info = grade_get_grades($course->id, 'mod', 'assignment',
                                         $assignment->id, array($user->id));
        $locked = $grading_info->items[0]->grades[$user->id]->locked;
        $overridden = $grading_info->items[0]->grades[$user->id]->overridden;
        $gradingdisabled = $locked || $overridden;

        $mformdata = new stdClass();
        $mformdata->context = $context;
        $mformdata->maxbytes = $course->maxbytes;
        $mformdata->courseid = $course->id;
        $mformdata->teacher = $USER;
        $mformdata->assignment = $assignment;
        $mformdata->submission = $submission;
        $mformdata->lateness = assignment_display_lateness($submission->timemodified,
                                                           $assignment->timedue);
        $mformdata->user = $user;
        $mformdata->offset = false;
        $mformdata->userid = $user->id;
        $mformdata->cm = $coursemodule;
        $mformdata->grading_info = $grading_info;
        $mformdata->enableoutcomes = $CFG->enableoutcomes;
        $mformdata->grade = $assignment->grade;
        $mformdata->gradingdisabled = $gradingdisabled;
        // TODO set nextid to the nextnode id.
        $mformdata->nextid = false;
        $mformdata->submissioncomment = $submission->submissioncomment;
        $mformdata->submissioncommentformat = FORMAT_HTML;

        // JS error due to the renderer not including the js module. Caused by render_assignment_files in the assignment renderer.
        $PAGE->requires->js('/mod/assignment/assignment.js');
        $mformdata->submission_content = $assignmentinstance->print_user_files($user->id,
                                                                                   true);

        if ($assignment->assignmenttype == 'upload') {
            $mformdata->fileui_options = array(
                'subdirs' => 1,
                'maxbytes' => $assignment->maxbytes,
                'maxfiles' => $assignment->var1,
                'accepted_types' => '*',
                'return_types' => FILE_INTERNAL);
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
        $gradingmethod = $gradingmanager->get_active_method();
        if ($gradingmethod) {
            // This returns a gradingform_controller instance, not grading_controller as docs
            // say.
            /* @var gradingform_controller $controller */
            $controller = $gradingmanager->get_controller($gradingmethod);
            if ($controller->is_form_available()) {
                $itemid = null;
                if (!empty($submission->id)) {
                    $itemid = $submission->id;
                }
                if ($gradingdisabled && $itemid) {
                    $mformdata->advancedgradinginstance =
                        $controller->get_current_instance($USER->id, $itemid);
                    return array($mformdata,
                                 $advancedgradingwarning);
                } else if (!$gradingdisabled) {
                    $instanceid = optional_param('advancedgradinginstanceid', 0, PARAM_INT);
                    $mformdata->advancedgradinginstance =
                        $controller->get_or_create_instance($instanceid, $USER->id, $itemid);
                    return array($mformdata,
                                 $advancedgradingwarning);
                }
                return array($mformdata,
                              $advancedgradingwarning);
            } else {
                $advancedgradingwarning = $controller->form_unavailable_notification();
                return array($mformdata,
                             $advancedgradingwarning);
            }
        }
        return array($mformdata,
                     $advancedgradingwarning);
    }

    /**
     * Process and save the data from the feedback form. Mostly lifted from
     * $assignmentinstance->process_feedback().
     *
     * @param object $data from the feedback form
     * @param $params
     * @return string
     */
    public function process_data($data, $params) {

        global $CFG, $DB;

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
        $coursemodule = $DB->get_record('course_modules',
                                        array('id' => $params['coursemoduleid']),
                                        '*',
                                        MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $coursemodule->course), '*', MUST_EXIST);
        $assignment   = $DB->get_record('assignment', array('id' => $coursemodule->instance),
                                        '*', MUST_EXIST);
        /* @var stdClass[] $grading_info */
        $grading_info = grade_get_grades($coursemodule->course, 'mod', 'assignment',
                                         $assignment->id, $data->userid);
        $submission = $DB->get_record('assignment_submissions',
                                      array('assignment' => $assignment->id,
                                            'userid' => $data->userid), '*', MUST_EXIST);
        $user = $DB->get_record('user', array('id' => $data->userid),
                                '*', MUST_EXIST);
        $assignmentinstance = $this->get_assignment_instance($assignment, $coursemodule, $course);

        // If 'revert to draft' has been clicked, we want a confirm button only.
        // We don't want to return yet because the main use case is to comment/grade and then
        // ask the student to improve.
        if (!empty($data->unfinalize) || !empty($data->revertbutton)) {
            $this->unfinalise_submission($submission, $assignment, $coursemodule, $course);
        }

        if (!$grading_info) {
            return 'Could not retrieve grading info.';
        }
        // Check to see if grade has been locked or overridden. If so, we can't save anything.
        if (($grading_info->items[0]->grades[$data->userid]->locked ||
            $grading_info->items[0]->grades[$data->userid]->overridden) ) {
            return 'Grade is locked or overridden';
        }

        // Advanced grading if enabled. From assignment_base->validate_and_process_feedback().
        // Sort out the form ready to tell it to display.
        list($mformdata, $advancedgradingwarning) =
            $this->get_mform_data_object($course, $assignment, $submission, $user,
                                         $coursemodule, $assignmentinstance, true);
        $submitform = new block_ajax_marking_assignment_form(block_ajax_marking_form_url($params),
                                                             $mformdata);
        $submitform->set_data($mformdata);

        if ($submitform->is_submitted() || !empty($data->revertbutton)) { // Possibly redundant.
            // Form was submitted (= a submit button other than 'cancel' or 'next' has been
            // clicked).
            if (!$submitform->is_validated()) {
                return 'form not validated';
            }
            /* @var gradingform_instance $gradinginstance */
            $gradinginstance = $submitform->use_advanced_grading();
            // Preprocess advanced grading here.
            if ($gradinginstance) {
                $formdata = $submitform->get_data();
                // Create submission if it did not exist yet because we need submission->id for
                // storing the grading instance.
                $advancedgrading = $formdata->advancedgrading;
                // Calculates the gradebook grade based on the rubrics.
                $data->xgrade = $gradinginstance->submit_and_get_grade($advancedgrading,
                                                                       $submission->id);
            }
        }

        // Save outcomes if necessary.
        if (!empty($CFG->enableoutcomes)) {
            $assignmentinstance->process_outcomes($data->userid);
        }

        $submission = $this->save_submission($submission, $data);
        if (!$submission) {
            return 'Problem saving feedback';
        }

        // Trigger grade event to update gradebook.
        $assignment->cmidnumber = $coursemodule->id;
        assignment_update_grades($assignment, $data->userid);

        add_to_log($coursemodule->course, 'assignment', 'update grades',
               'submissions.php?id='.$coursemodule->id.'&user='.$data->userid,
               $data->userid, $coursemodule->id);

        // Save files if necessary.
        $this->save_files($assignment, $submission, $data);

        return '';
    }

    /**
     * Puts the submission back in a state where the student can edit the files.
     *
     * @param $submission
     * @param $assignment
     * @param $coursemodule
     * @param $course
     */
    private function unfinalise_submission($submission, $assignment, $coursemodule, $course) {

        global $DB, $CFG;

        $updated = new stdClass();
        $updated->id = $submission->id;
        $updated->data2 = '';
        $DB->update_record('assignment_submissions', $updated);

        $submission->data2 = '';

        // Load up the required assignment code.
        require_once($CFG->dirroot.'/mod/assignment/type/'.$assignment->assignmenttype.
            '/assignment.class.php');
        $assignmentclass = 'assignment_'.$assignment->assignmenttype;

        /* @var assignment_base $assignmentinstance */
        $assignmentinstance = new $assignmentclass($coursemodule->id, $assignment,
                                                   $coursemodule, $course);
        $assignmentinstance->update_grade($submission);
    }

    /**
     * Adds extra info to the submission record and returns the modified record.
     *
     * @param $submission
     * @param $data
     * @global $DB
     * @global $USER
     * @return bool|stdClass
     */
    private function save_submission($submission, $data) {

        global $DB, $USER;

        $submission->grade = $data->xgrade;
        $submission->submissioncomment = $data->submissioncomment_editor['text'];
        $submission->teacher = $USER->id;
        $submission->timemarked = time();
        unset($submission->data1); // Don't need to update this.
        unset($submission->data2); // Don't need to update this.

        $mailinfo = get_user_preferences('assignment_mailinfo', 0);
        if (!$mailinfo) {
            $submission->mailed = 1; // Treat as already mailed.
        } else {
            $submission->mailed = 0; // Make sure mail goes out (again, even).
        }

        // Save submission.
        $saveresult = $DB->update_record('assignment_submissions', $submission);

        if ($saveresult) {
            return $submission;
        }
        return false;
    }

    /**
     * Saves any files that the teacher may have attached or embedded.
     *
     * @param $assignment
     * @param $submission
     * @param $data
     * @global $PAGE
     * @global $CFG
     * @return string
     */
    private function save_files($assignment, $submission, $data) {
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
        return '';
    }

    /**
     * Saves outcome data from the form
     *
     * @param $assignment
     * @param $grading_info
     * @param $data
     * @return void
     */
    private function save_outcomes($assignment, $grading_info, $data) {

        global $CFG;

        if (empty($CFG->enableoutcomes)) {
            return;
        }

        $outcomedata = array();
        $userid = $data->userid;

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
     * Returns a query object with the basics all set up to get assignment stuff. This is not very efficient due to
     * the large number of possible combinations of WHERE conditions. UNION is better in this case because
     * the individual queries make better use of the indexes. This therefore does a UNION in order to make the WHERE
     * simpler for each one.
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
        $query->set_column('courseid', 'moduletable.course');

        $query->add_from(array(
                              'join' => 'INNER JOIN',
                              'table' => 'assignment_submissions',
                              'alias' => 'sub',
                              'on' => 'sub.assignment = moduletable.id'
                         ));
        $query->set_column('userid', 'sub.userid');

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
        $commentstring = $DB->sql_compare_text('sub.submissioncomment');
        $assignmenttypestring = $DB->sql_compare_text('moduletable.assignmenttype');
        $datastring = $DB->sql_compare_text('sub.data2');
        // Resubmit seems not to be used for upload types.
        $query->add_where("( (sub.grade = -1 AND {$commentstring} = '') /* Never marked */
                                    OR
                                    ( (  moduletable.resubmit = 1
                                         OR ({$assignmenttypestring} = 'upload' AND moduletable.var4 = 1)
                                       ) /* Resubmit allowed */
                                       AND (sub.timemodified > sub.timemarked) /* Resubmit happened */
                                    )
                                )
                                /* Not in draft state */
                                AND ( {$assignmenttypestring} != 'upload'
                                      OR ( {$assignmenttypestring} = 'upload' AND {$datastring} = 'submitted'))
                                AND {$assignmenttypestring} != 'offline'
                                  ");

        // Advanced upload: data2 will be 'submitted' and grade will be -1, but if 'save changes'
        // is clicked, timemarked will be set to time(), but actually, grade and comment may
        // still be empty.

        return $query;
    }


}

