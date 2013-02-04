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
 * Class file for the Coursework module grading functions
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2012 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot.'/blocks/ajax_marking/classes/query_base.class.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/classes/module_base.class.php');
require_once($CFG->dirroot.'/mod/coursework/classes/tables/coursework_submission.class.php');
require_once($CFG->dirroot.'/lib/adminlib.php');
require_once($CFG->dirroot.'/lib/formslib.php');
require_once($CFG->dirroot.'/local/ulcc_form_library/ulcc_form.class.php');
require_once($CFG->dirroot.'/mod/coursework/renderer.php');
require_once($CFG->dirroot.'/mod/coursework/classes/tables/coursework.class.php');
require_once($CFG->dirroot.'/mod/coursework/classes/tables/coursework_submission.class.php');

/**
 * Extension to the block_ajax_marking_module_base class which adds the parts that deal
 * with the assign module.
 */
class block_ajax_marking_coursework extends block_ajax_marking_module_base {

    /**
     * @var ulcc_form
     */
    private $ulccform;

    /**
     * Constructor. Needs to be duplicated in all modules, so best put in parent. PHP4 issue though.
     *
     * The aim is to pass in the main ajax_marking_functions object by reference, so that its
     * properties are accessible
     *
     * @internal param object $reference the parent object to be referred to
     * @return \block_ajax_marking_coursework
     */
    public function __construct() {

        // Call parent constructor with the same arguments (keep for 2.1 - PHP 5.3 needed).
        parent::__construct();

        $this->modulename = 'coursework'; // DB modulename.
        $this->capability = 'mod/coursework:grade';
    }

    /**
     * Makes the grading interface for the pop up.
     *
     * @param array $params From $_GET
     * @param object $coursemodule The coursemodule object that the user has been authenticated
     * against
     * @param bool $data
     * @throws moodle_exception
     * @return string
     */
    public function grading_popup(array $params, $coursemodule, $data = false) {

        global $CFG, $DB, $USER, $OUTPUT, $PAGE;

        $cmid = $params['coursemoduleid'];
        $assessorid = $USER->id;
        // This will come from the form when it's submitted.
        $currentpage = optional_param('current_page', 1, PARAM_INT);

        $course_module = get_coursemodule_from_id('coursework', $cmid, 0, false, MUST_EXIST);
        $params = array(
            'courseworkid' => $course_module->instance,
            'userid' => $params['userid']
        );
        $submissionid = $DB->get_field('coursework_submissions', 'id', $params);

        $coursework = new coursework($course_module->instance);
        $submission = new coursework_submission($coursework, $submissionid);

        // Need to get any pre-existing feedback in case this is a resubmit.
        // TODO is this going to work for an admin wanting to mark on behalf of someone else?

        $teacherfeedback = $this->get_submission_assessor_feedback($submissionid, $assessorid);

        // TODO shift into custom data and set via somewhere else.
        $coursework->submissionid = $submissionid;
        $coursework->cmid = $cmid;

        // This is the module view page this will be the page that the user is returned to if they press
        // cancel or they make a submission.
        $viewurl = $CFG->wwwroot.'/mod/coursework/view.php?id='.$cmid;

        $editentry_id = null;
        if ($teacherfeedback) {
            $editentry_id = $teacherfeedback->entry_id;
        }

        if (!isset($this->ulccform)) { // Allows us to reuse the form after pages have been saved and altered.

            $this->ulccform = new ulcc_form('mod',
                                            'coursework',
                                            $coursework->formid,
                                            $editentry_id,
                                            $PAGE->url->out(false),
                                            $viewurl,
                                            $currentpage);
        }

        $gradingstring = get_string('gradingfor', 'coursework',
                                    $submission->get_username());

        if (!$coursework->formid) { // No form, so we have to get them to make or choose one.

            $urlattributes = array('moodlepluginname' => 'coursework',
                                   'moodleplugintype' => 'mod',
                                   'context_id' => $PAGE->context->id,
                                   'cm_id' => $course_module->id);
            $needformurl =
                new moodle_url('/local/ulcc_form_library/actions/view_forms.php', $urlattributes);
            redirect($needformurl, get_string('needsfeedbackform', 'coursework'));
        }

        $html = '';

        // We only want this section to display if the form has not been submitted.

        $html .= $OUTPUT->heading($gradingstring);
        $assessor = $DB->get_record('user', array('id' => $assessorid));
        $html .= html_writer::tag('p',
                                  get_string('assessor', 'coursework').' '.fullname($assessor));
        $html .= html_writer::tag('p',
                                  get_string('gradingoutof', 'coursework',
                                             round($coursework->grade)));

        // In case we have an editor come along, we want to show that this has happened.
        if (!empty($teacherfeedback)) { // May not have been marked yet.
            if ($submissionid && !empty($teacherfeedback->lasteditedbyuser)) {
                $editor =
                    $DB->get_record('user', array('id' => $teacherfeedback->lasteditedbyuser));
            } else {
                $editor = $assessor;
            }
            $details = new stdClass();
            $details->name = fullname($editor);
            $details->time = userdate($teacherfeedback->timemodified);
            $html .= html_writer::tag('p', get_string('lastedited', 'coursework', $details));
        }

        $files = $submission->get_submission_files();
        $files_string = count($files) > 1 ? 'submissionfiles' : 'submissionfile';

        $html .= html_writer::start_tag('h1');
        $html .= get_string($files_string, 'coursework');
        $html .= html_writer::end_tag('h1');

        $output = $PAGE->get_renderer('mod_coursework');
        $html .= $output->render($files);

        ob_start();
        // Will already have happened if page has changed, but not on first display.
        $this->ulccform->try_to_save_whole_form_and_get_entry_id();

        $this->ulccform->display_form();
        $html .= ob_get_contents();
        ob_end_clean();

        return $html;
    }

    /**
     * Process and save the data from the feedback form.
     *
     * @param object $data from the feedback form
     * @param array $params
     * @throws moodle_exception
     * @return string
     */
    public function process_data($data, $params) {

        global $USER, $PAGE, $DB;

        $assessorid = $USER->id;
        $formid = $data->form_id;
        $currentpage = $data->current_page;
        $course_module =
            get_coursemodule_from_id('coursework', $params['coursemoduleid'], 0, false, MUST_EXIST);
        $coursework = new coursework($course_module->instance);

        $params = array(
            'courseworkid' => $course_module->instance,
            'userid' => $params['userid']
        );
        $submissionid = $DB->get_field('coursework_submissions', 'id', $params);
        $submission = new coursework_submission($coursework, $submissionid);

        $this->ulccform = new ulcc_form('mod',
                                        'coursework',
                                        $formid,
                                        0,
                                        $PAGE->url->out(false),
                                        '',
                                        $currentpage);

        // The entry id will be provided if the form has been submitted. False if not.
        $entry_id = $this->ulccform->try_to_save_whole_form_and_get_entry_id();

        // This is an indication that the whole form has been submitted, not just one page of it.
        if (!empty($entry_id)) {

            $teacherfeedback = $this->get_submission_assessor_feedback($submissionid, $assessorid);

            // If we are editing a feedback, it should already be present so we will only be updating the timestamps.
            if (!$teacherfeedback) {
                $teacherfeedback = new coursework_feedback(false, $submission);
                $teacherfeedback->submissionid = $submissionid;
                $teacherfeedback->assessorid = $assessorid;
                $teacherfeedback->isfinalgrade = 0;

                // Slim possibility that this page has been accessed by someone going back after making a new entry,
                // in which case they will trigger a new insert and bork the system. Sanity check here...
                $params = array(
                    'isfinalgrade' => 0,
                    'submissionid' => $submissionid,
                    'assessorid' => $assessorid
                );
                if ($DB->record_exists('coursework_feedbacks', $params)) {
                    // Problem. Assume we had someone go back when they shouldn't.
                    throw new moodle_exception('Trying to create a new feedback where one already exists!');
                }
            }

            // Possible that we have a converted moderator feedback that previously had no entry id.
            $teacherfeedback->entry_id = $entry_id;

            // If we are editing a feedback then check if the entry has a grade in it using the feedback entry id.
            $grades = $this->ulccform->get_form_element_value($teacherfeedback->entry_id,
                                                              'form_element_plugin_modgrade',
                                                              false);
            if ($grades) {
                $teacherfeedback->grade = array_pop($grades);
            }
            $gradecomments =
                $this->ulccform->get_form_element_value($teacherfeedback->entry_id,
                                                        'form_element_plugin_comment_editor',
                                                        false);
            if ($gradecomments) {
                $teacherfeedback->feedbackcomment = array_pop($gradecomments);
            }

            $teacherfeedback->lasteditedbyuser = $USER->id;

            $teacherfeedback->save();

            // If this is a single grader coursework, then this is going to be the final and only feedback.
            // Recalculate moderation set now that we have a new grade, which may determine who gets moderated.
            if (!$coursework->has_multiple_markers()) {
                $coursework->grade_changed_event();
            }

            return '';
        }

        // Multi page forms.
        return 'Display another page';
    }

    /**
     * Returns a query object with the basics all set up to get ungraded coursework stuff
     *
     * @global moodle_database $DB
     * @return block_ajax_marking_query_base
     */
    public function query_factory() {

        global $USER;

        $query = new block_ajax_marking_query_base($this);

        $table = array(
            'table' => 'coursework',
            'alias' => 'moduletable',
        );
        $query->add_from($table);
        $query->set_column('courseid', 'moduletable.course');

        $table = array(
            'join' => 'INNER JOIN',
            'table' => 'coursework_submissions',
            'alias' => 'sub',
            'on' => 'sub.courseworkid = moduletable.id'
        );
        $query->add_from($table);
        $query->set_column('userid', 'sub.userid');

        // LEFT JOIN, rather than NOT EXISTS because we may have an empty feedback saved, which
        // will create a grade record, but with a null grade. These should still count as ungraded.
        // What if it was reverted, then resubmitted? We still want these to show up for remarking.
        $table = array(
            'join' => 'LEFT JOIN',
            'table' => 'coursework_feedbacks',
            'on' => 'coursework_feedbacks.submissionid = sub.id
                     AND coursework_feedbacks.assessorid = :courseworkuserid
                     AND coursework_feedbacks.isfinalgrade = 0
                     AND coursework_feedbacks.ismoderation = 0
                     AND coursework_feedbacks.timemodified >= sub.timemodified'
        );
        $query->add_from($table);
        $table = array(
            'join' => 'LEFT JOIN',
            'table' => 'coursework_allocation_pairs',
            'on' => 'coursework_allocation_pairs.courseworkid = moduletable.id
                     AND coursework_allocation_pairs.assessorid = :courseworkuserid2
                     AND coursework_allocation_pairs.studentid = sub.userid'
        );
        $query->add_from($table);
        $params = array(
            'courseworkuserid' => $USER->id,
            'courseworkuserid2' => $USER->id
        );
        $query->add_params($params);

        // Standard user id for joins.
        $column = array('table' => 'sub',
                        'column' => 'userid');
        $query->add_select($column);
        $column = array('table' => 'sub',
                        'column' => 'timemodified',
                        'alias' => 'timestamp');
        $query->add_select($column);

        // All work with no feedback record will show up.
        // TODO formative with no grade.
        $where = "(coursework_feedbacks.id IS NULL
                             OR (coursework_feedbacks.grade IS NULL
                                 AND (coursework_feedbacks.feedbackcomment = ''
                                      OR coursework_feedbacks.feedbackcomment IS NULL)))";
        $query->add_where($where);
        // If allocations are in use, make sure we only return the ones for which there are relevant allocations.
        $where = '(moduletable.allocationenabled = 0
                             OR (moduletable.allocationenabled = 1 AND coursework_allocation_pairs.id IS NOT NULL))';
        $query->add_where($where);
        $where = '(SELECT COUNT(countfeedbacks.id)
                               FROM {coursework_feedbacks} countfeedbacks
                              WHERE countfeedbacks.submissionid = sub.id
                                AND countfeedbacks.isfinalgrade = 0
                                AND countfeedbacks.ismoderation = 0) < moduletable.numberofmarkers
                                ';
        $query->add_where($where);

        return $query;
    }

    /**
     * @param int $submissionid
     * @param int $assessorid
     * @return mixed
     */
    private function get_submission_assessor_feedback($submissionid, $assessorid) {

        global $DB;

        $params = array(
            'submissionid' => $submissionid,
            'assessorid' => $assessorid
        );

        return $DB->get_record('coursework_feedbacks', $params);
    }
}
