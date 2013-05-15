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

/* @define "$blockdir" "../.." */
$blockdir = $CFG->dirroot.'/blocks/ajax_marking';
require_once($blockdir.'/classes/query_base.class.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/classes/filters.class.php');
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
     * Makes an HTML link for the pop up to allow grading of a question
     *
     * @param object $item containing the quiz id as ->id
     * @return string
     */
    public function make_html_link($item) {

        global $CFG;
        $address = $CFG->wwwroot.'/mod/quiz/report.php?q='.$item->assessmentid.'&mode=grading';
        return $address;
    }

    /**
     * Returns the name of the column in the submissions table which holds the userid of the
     * submitter
     *
     * @return string
     */
    protected function get_sql_userid_column() {
        return 'qa.userid';
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
    public function grading_popup($params, $coursemodule) {

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
            /* @var quiz_attempt $quiz_attempt */
            $quiz_attempt = quiz_attempt::create($id);
            $transaction = $DB->start_delegated_transaction();
            $quiz_attempt->process_all_actions(time());
            $transaction->allow_commit();

            $processedattempts[$id] = $quiz_attempt;
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
        $query->set_userid_column('quiz_attempts.userid');

        $query->add_from(array(
                'table' => $this->modulename,
                'alias' => 'moduletable',
        ));
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
        $query->add_select(array('table' => 'quiz_attempts',
                                 'column' => 'userid'));
        $query->add_select(array('table' => 'sub',
                                'column' => 'timecreated',
                                'alias'  => 'timestamp'));

        $query->add_where(array('type' => 'AND',
                                'condition' => 'quiz_attempts.timefinish > 0'));
        $query->add_where(array('type' => 'AND',
                                'condition' => 'quiz_attempts.preview = 0'));
        $comparesql = $DB->sql_compare_text('question_attempts.behaviour')." = 'manualgraded'";
        $query->add_where(array('type' => 'AND',
                                'condition' => $comparesql));
        $query->add_where(array('type' => 'AND',
                                'condition' => "sub.state = '".question_state::$needsgrading."' "));

        // We want to get a list of graded states so we can retrieve all questions that don't have
        // one.
        $gradedstates = array();
        $us = new ReflectionClass('question_state');
        foreach ($us->getStaticProperties() as $name => $class) {
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
        $query->add_where(array('type' => 'AND',
                                'condition' => $subsql));
        $query->add_params($gradedparams);
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
                FROM   {quiz} moduletable
                       INNER JOIN {quiz_attempts} quiz_attempts
                         ON moduletable.id = quiz_attempts.quiz
                       INNER JOIN {question_attempts} question_attempts
                         ON question_attempts.questionusageid = quiz_attempts.uniqueid
                       INNER JOIN {question_attempt_steps} sub
                         ON question_attempts.id = sub.questionattemptid
                       INNER JOIN {question} question
                         ON question_attempts.questionid = question.id
                       INNER JOIN {course_modules} course_modules
                         ON course_modules.instance = moduletable.id
                            AND course_modules.module = :quizmoduleid
                WHERE  quiz_attempts.timefinish > 0
                       AND quiz_attempts.preview = 0
                       AND question_attempts.behaviour = 'manualgraded'
                       AND sub.state = 'needsgrading'
                       AND NOT EXISTS(SELECT 1
                                      FROM   {question_attempt_steps} st
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

/**
 * Questionid filters for the quiz module.
 */
class block_ajax_marking_quiz_questionid extends block_ajax_marking_filter_base {

    /**
     * Adds SQL to a dynamic query for when there is a question node as an ancestor of the current
     * nodes.
     *
     * @static
     * @param block_ajax_marking_query_base $query
     * @param int $questionid
     */
    public static function where_filter($query, $questionid) {

        $moduleunion = self::get_moduleunion_subquery($query);
        $countwrapper = self::get_countwrapper_subquery($query);
        // Apply WHERE clause.
        // TODO can we just add the questionid in there all the time and not have to make
        // moduleunion dynamic?
        $conditions = array(
            'table' => 'question',
            'column' => 'id',
            'alias' => 'questionid'
        );
        $moduleunion['quiz']->add_select($conditions);
        $clause = array(
            'type' => 'AND',
            'condition' => 'moduleunion.questionid = :questionidfilterquestionid');
        $countwrapper->add_where($clause);
        $countwrapper->add_param('questionidfilterquestionid', $questionid);
    }

    /**
     * Makes a set of question nodes by grouping submissions by questionid.
     *
     * @static
     * @param block_ajax_marking_query_base $query
     */
    public static function nextnodetype_filter($query) {

        $moduleunion = self::get_moduleunion_subquery($query);
        $countwrapper = self::get_countwrapper_subquery($query);

        $moduleunion['quiz']->add_select(array(
                                              'table' => 'question',
                                              'column' => 'id',
                                              'alias' => 'questionid'
                                         ));
        // We can add this as we can be sure that we are only looking at quiz nodes, so there
        // will be no other modules being added with UNION, so they won't all need the same
        // columns for the UNION to work.
        $countwrapper->add_select(array(
                                       'table' => 'moduleunion',
                                       'column' => 'questionid',
                                       'alias' => 'id'));

        // Outer bit to get display name.
        $query->add_from(array(
                              'join' => 'INNER JOIN',
                              'table' => 'question',
                              'on' => 'question.id = countwrapperquery.id'));
        $query->add_select(array(
                                'table' => 'question',
                                'column' => 'name'));
        $query->add_select(array(
                                'table' => 'question',
                                'column' => 'questiontext',
                                'alias' => 'tooltip'));

        // This is only needed to add the right callback function.
        $query->add_select(array(
                                'column' => "'quiz'",
                                'alias' => 'modulename'));

        $query->add_orderby("question.name ASC");
    }
}

/**
 * Userid filters for the quiz module
 */
class block_ajax_marking_quiz_userid extends block_ajax_marking_filter_base {

    /**
     * Adds SQL for when there is a userid node as an ancestor of the current nodes. Unlikely to
     * be used.
     *
     * @static
     * @param block_ajax_marking_query_base $query
     * @param int $userid
     */
    public static function where_filter($query, $userid) {
        // Applies if users are not the final nodes.
        $clause = array(
            'type' => 'AND',
            'condition' => 'quiz_attempts.userid = :useridfiltersubmissionid');
        $query->add_where($clause
        );
        $query->add_param('useridfiltersubmissionid', $userid);
    }

    /**
     * Makes a bunch of user nodes by grouping quiz submissions by the user id. The grouping is
     * automatic, but the text labels for the nodes are specified here.
     *
     * @static
     * @param block_ajax_marking_query_base $query
     */
    public static function nextnodetype_filter($query) {
        $countwrapper = self::get_countwrapper_subquery($query);

        $query->add_select(array(
                                'table' => 'countwrapperquery',
                                'column' => 'timestamp',
                                'alias' => 'tooltip')
        );

        $query->add_select(array(
                                'table' => 'usertable',
                                'column' => 'firstname'));
        $query->add_select(array(
                                'table' => 'usertable',
                                'column' => 'lastname'));

        $query->add_from(array(
                              'join' => 'INNER JOIN',
                              'table' => 'user',
                              'alias' => 'usertable',
                              'on' => 'usertable.id = countwrapperquery.id'
                         ));

        $countwrapper->add_select(array(
                                       'table' => 'moduleunion',
                                       'column' => 'userid',
                                       'alias' => 'id'));
        // This is only needed to add the right callback function.
        $query->add_select(array(
                                'column' => "'quiz'",
                                'alias' => 'modulename'
                           ));
    }


}

