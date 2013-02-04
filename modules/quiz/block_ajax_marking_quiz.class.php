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
 * Class file for the quiz grading functions
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

// We only need this file for the constants. Doing this so that we don't have problems including
// the file from module.js.


if (isset($CFG) && !empty($CFG)) {
    require_once($CFG->dirroot.'/lib/questionlib.php');
    require_once($CFG->dirroot.'/mod/quiz/attemptlib.php');
    require_once($CFG->dirroot.'/mod/quiz/locallib.php');
    require_once($CFG->dirroot.'/question/engine/states.php');
    require_once($CFG->dirroot.'/blocks/ajax_marking/modules/quiz/'.
                 'block_ajax_marking_quiz_form.class.php');
}

/**
 * Provides all marking functionality for the quiz module
 *
 * @copyright 2008-2010 Matt Gibson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_ajax_marking_quiz extends block_ajax_marking_module_base {

    /**
     * Constructor
     *
     * @internal param object $reference the parent object passed in by reference so that it's data
     * can be used
     * @return \block_ajax_marking_quiz
     */
    public function __construct() {

        // Call parent constructor with the same arguments.
        parent::__construct();

        // Must be the same as the DB modulename.
        $this->modulename           = 'quiz';
        $this->capability           = 'mod/quiz:grade';
        $this->icon                 = 'mod/quiz/icon.gif';
    }

    /**
     * To make up for the fact that in 2.0 there is no screen with both quiz question and feedback
     * text-entry box next to each other (the feedback bit is a separate pop-up), we have to make
     * a custom form to allow grading to happen. It is based on code from
     * /mod/quiz/reviewquestion.php
     *
     * Use questionid, rather than slot so we can group the same question in future, even across
     * random questions.
     *
     * @param array $params all of the stuff sent with the node click e.g. questionid
     * @param object $coursemodule
     * @throws moodle_exception
     * @global stdClass $CFG
     * @global moodle_database $DB
     * @return string the HTML page
     */
    public function grading_popup(array $params, $coursemodule) {

        global $CFG, $PAGE, $DB, $OUTPUT;

        $output = '';

        // TODO what params do we get here?
        require_once($CFG->dirroot.'/mod/quiz/locallib.php');

         // TODO feed in all dynamic variables here.
        $PAGE->set_url(new moodle_url('/blocks/ajax_marking/actions/grading_popup.php', $params));

        $formattributes = array(
                    'method' => 'post',
                    'class'  => 'mform',
                    'id'     => 'manualgradingform',
                    'action' => block_ajax_marking_form_url($params));
        $output .= html_writer::start_tag('form', $formattributes);

        // We could be looking at multiple attempts and/or multiple questions
        // Assume we have a user/quiz combo to get us here. We may have attemptid or questionid too.

        // Get all attempts with unmarked questions. We may or may not have a questionid, but
        // this comes later so we can use the quiz's internal functions.
        $questionattempts = $this->get_question_attempts($params);
        if (!$questionattempts) {
            $message =
                'Could not retrieve question attempts. Maybe someone else marked them just now';
            throw new moodle_exception($message);
        }

        // Print infobox.
        $rows = array();
        // Print user picture and name.
        $quizattempts = array(); // Cache the attempt objects for reuse..
        // We want to get the first one ready, so we can use it to print the info box.
        $firstattempt = reset($questionattempts);
        $quizattempt = quiz_attempt::create($firstattempt->quizattemptid);
        $quizattempts[$firstattempt->quizattemptid] = $quizattempt;
        $student = $DB->get_record('user', array('id' => $quizattempt->get_userid()));
        $courseid = $quizattempt->get_courseid();
        $picture = $OUTPUT->user_picture($student, array('courseid' => $courseid));
        $url = $CFG->wwwroot.'/user/view.php?id='.$student->id.'&amp;course='.$courseid;
        $rows[] = '<tr>
                       <th scope="row" class="cell">' . $picture . '</th>
                       <td class="cell">
                           <a href="' .$url. '">' .
                               fullname($student, true) .
                          '</a>
                      </td>
                  </tr>';

        // Now output the summary table, if there are any rows to be shown.
        if (!empty($rows)) {
            $output .= '<table class="generaltable generalbox quizreviewsummary"><tbody>'."\n";
            $output .= implode("\n", $rows);
            $output .= "\n</tbody></table>\n";
        }

        foreach ($questionattempts as $questionattempt) {
            // Everything should already be in the right order as a nested array.
            // N.B. Using the proper quiz functions in an attempt to make this more robust
            // against future changes.
            if (!isset($quizattempts[$questionattempt->quizattemptid])) {
                $quizattempt = quiz_attempt::create($questionattempt->quizattemptid);
                $quizattempts[$questionattempt->quizattemptid] = $quizattempt;
            } else {
                $quizattempt = $quizattempts[$questionattempt->quizattemptid];
            }

            // Log this review.
            $attemptid = $quizattempt->get_attemptid();
            add_to_log($quizattempt->get_courseid(), 'quiz', 'review',
                       'reviewquestion.php?attempt=' .
                       $attemptid . '&question=' . $params['questionid'] ,
                       $quizattempt->get_quizid(), $quizattempt->get_cmid());
            // Now make the actual markup to show one question plus commenting/grading stuff.
            $output .= $quizattempt->render_question_for_commenting($questionattempt->slot);
        }

        $output .= html_writer::start_tag('div');
        $output .= html_writer::empty_tag('input', array('type' => 'submit', 'value' => 'Save'));
        foreach ($params as $name => $value) {
            $output .= html_writer::empty_tag('input', array('type' => 'hidden',
                                                             'name' => $name,
                                                             'value' => $value));
        }
        $output .= html_writer::empty_tag('input', array('type' => 'hidden',
                                                  'name' => 'sesskey',
                                                  'value' => sesskey()));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('form');

        return $output;
    }

    /**
     * Deals with data coming in from the grading pop up
     *
     * @param object $data the form data
     * @param $params
     * @return mixed true on success or an error.
     */
    public function process_data($data, $params) {

        global $DB;

        // Get all attempts on all questions that were unmarked.
        // Slight chance that someone else will have marked the questions since this user opened
        // the pop up, which could lead to these grades being ignored, or the other person's
        // being overwritten. Not much we can do about that.
        $questionattempts = $this->get_question_attempts($params);
        // We will have duplicates as there could be multiple questions per attempt.
        $processedattempts = array();

        // This will get all of the attempts to pull the relevant data from all the POST stuff
        // and process it. The quiz adds lots of prefix stuff, so we won't have collisions.
        foreach ($questionattempts as $attempt) {
            $id = $attempt->quizattemptid;
            if (isset($processedattempts[$id])) {
                continue;
            }

            $processedattempt = quiz_attempt::create($id);
            $processedattempt->process_submitted_actions(time());

            $processedattempts[$id] = $processedattempt;
        }

        return '';
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
                'table' => $this->modulename,
                'alias' => 'moduletable',
        ));
        $query->set_column('courseid', 'moduletable.course');

        $query->add_from(array(
                'table' => 'quiz_attempts',
                'on'    => 'moduletable.id = quiz_attempts.quiz'
        ));
        $query->add_from(array(
                'table' => 'question_attempts',
                'on'    => 'question_attempts.questionusageid = quiz_attempts.uniqueid'
        ));
        $query->add_from(array(
                'table' => 'question_attempt_steps',
                'alias' => 'sub',
                'on'    => 'question_attempts.id = sub.questionattemptid'
        ));
        $query->add_from(array(
                'table' => 'question',
                'on'    => 'question_attempts.questionid = question.id'
        ));

        // Standard userid for joins.
        $query->add_select(array('table' => 'sub',
                                 'column' => 'userid'));
        $query->set_column('userid', 'sub.userid');

        $query->add_select(array('table' => 'sub',
                                'column' => 'timecreated',
                                'alias'  => 'timestamp'));

        $query->add_where('quiz_attempts.timefinish > 0');
        $query->add_where('quiz_attempts.preview = 0');
        $comparesql = $DB->sql_compare_text('question_attempts.behaviour')." = 'manualgraded'";
        $query->add_where($comparesql);
        $query->add_where("sub.state = '".question_state::$needsgrading."' ");

        // We want to get a list of graded states so we can retrieve all questions that don't have
        // one.
        $gradedstates = array();
        $us = new ReflectionClass('question_state');
        foreach ($us->getStaticProperties() as $name => $class) {
            /* @var question_state $class */
            if ($class->is_graded()) {
                $gradedstates[] = $name;
            }
        }
        list($gradedsql, $gradedparams) = $DB->get_in_or_equal($gradedstates,
                                                               SQL_PARAMS_NAMED,
                                                               'quizq001');
        $subsql = "NOT EXISTS( SELECT 1
                                 FROM {question_attempt_steps} st
                                WHERE st.state {$gradedsql}
                                  AND st.questionattemptid = question_attempts.id)";
        $query->add_where($subsql, $gradedparams);
        return $query;
    }

    /**
     * Based on the supplied param from the node that was clicked, go and get all question attempts
     * that we need to grade. Both grading_pop_up() and process_data() need this in order to either
     * present or process the attempts.
     *
     * @param array $params
     * @return array
     */
    protected function get_question_attempts($params) {

        global $DB;

        $quizmoduleid = $this->get_module_id();
        $sqlparams = array('quizmoduleid' => $quizmoduleid);

        $sql = "SELECT question_attempts.id,
                       quiz_attempts.userid,
                       sub.timecreated   AS timestamp,
                       course_modules.id AS coursemoduleid,
                       moduletable.course,
                       sub.id            AS subid,
                       'quiz'            AS modulename,
                       quiz_attempts.id  AS quizattemptid,
                       question_attempts.questionid,
                       question_attempts.slot
                FROM   mdl_quiz moduletable
                       INNER JOIN mdl_quiz_attempts quiz_attempts
                         ON moduletable.id = quiz_attempts.quiz
                       INNER JOIN mdl_question_attempts question_attempts
                         ON question_attempts.questionusageid = quiz_attempts.uniqueid
                       INNER JOIN mdl_question_attempt_steps sub
                         ON question_attempts.id = sub.questionattemptid
                       INNER JOIN mdl_question question
                         ON question_attempts.questionid = question.id
                       INNER JOIN mdl_course_modules course_modules
                         ON course_modules.instance = moduletable.id
                            AND course_modules.module = :quizmoduleid
                WHERE  quiz_attempts.timefinish > 0
                       AND quiz_attempts.preview = 0
                       AND question_attempts.behaviour = 'manualgraded'
                       AND sub.state = 'needsgrading'
                       AND NOT EXISTS(SELECT 1
                                      FROM   mdl_question_attempt_steps st
                                      WHERE  st.state IN ( 'gradedwrong', 'gradedpartial',
                                                           'gradedright',
                                                           'mangrwrong',
                                                           'mangrpartial', 'mangrright' )
                                             AND st.questionattemptid = question_attempts.id)";

        if (isset($params['coursemoduleid'])) {
            $sql .= ' AND course_modules.id = :coursemoduleid ';
            $sqlparams['coursemoduleid'] = $params['coursemoduleid'];
        }
        if (isset($params['questionid'])) {
            $sql .= ' AND question_attempts.questionid = :questionid ';
            $sqlparams['questionid'] = $params['questionid'];
        }
        if (isset($params['userid'])) {
            $sql .= ' AND quiz_attempts.userid = :userid ';
            $sqlparams['userid'] = $params['userid'];
        }

        $sql .= " ORDER  BY question_attempts.slot,
                          quiz_attempts.id ASC  ";

        // We want the oldest at the top so that the tutor can see how the answer changes over time.
        $questionattempts = $DB->get_records_sql($sql, $sqlparams);
        return $questionattempts;
    }

}





