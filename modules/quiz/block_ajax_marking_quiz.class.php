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

/** @define "$blockdir" "../.." */
$blockdir = $CFG->dirroot.'/blocks/ajax_marking';
require_once($blockdir.'/classes/query_base.class.php');

// We only need this file for the constants. Doing this so that we don't have problems including
// the file from module.js


if (isset($CFG) && !empty($CFG)) {
    require_once($CFG->dirroot.'/lib/questionlib.php');
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

        // call parent constructor with the same arguments
        parent::__construct();

        // must be the same as the DB modulename
        $this->modulename = $this->moduletable = 'quiz';
        $this->capability           = 'mod/quiz:grade';
        $this->icon                 = 'mod/quiz/icon.gif';
    }

    /**
     * This will alter a query to send back the stuff needed for quiz questions
     *
     * @param \block_ajax_marking_query_base|\type $query
     * @param int $questionid the id to filter by
     * @param bool $outerquery
     * @return
     *
     */
    public function apply_questionid_filter(block_ajax_marking_query_base $query, $questionid = 0,
                                            $outerquery = false) {

        if ($questionid) {
            // Apply WHERE clause
            $query->add_where(array(
                    'type' => 'AND',
                    'condition' => 'question.id = :'.$query->prefix_param('questionid')));
            $query->add_param('questionid', $questionid);
            return;
        }

        if ($outerquery) {

            $query->add_from(array(
                    'join' => 'INNER JOIN',
                    'table' => 'question',
                    'on' => 'question.id = combinedmodulesubquery.id'));
            $selects = array(
                    array(
                        'table' => 'question',
                        'column' => 'name'),
                    array(
                        'table' => 'question',
                        'column' => 'questiontext',
                        'alias' => 'tooltip'),

            );

            foreach ($selects as $select) {
                $query->add_select($select);
            }
        } else {
            $selects = array(
                array(
                    'table' => 'question',
                    'column' => 'id',
                    'alias' => 'questionid'),
                array(
                    'table' => 'sub',
                    'column' => 'id',
                    'alias' => 'count',
                    'function' => 'COUNT',
                    'distinct' => true),
                 // This is only needed to add the right callback function.
                array(
                    'column' => "'".$query->get_modulename()."'",
                    'alias' => 'modulename'
                    )
                );

            foreach ($selects as $select) {
                $query->add_select($select);
            }
        }

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
     * Allows us to take account of potentially non-standard manually graded question types.
     * Need to make sure that nowhere uses hard coded reference to essay type and that the grading
     * interface works for them.
     *
     * This includes random ones. Not sure if that's bad.
     *
     * @global type $QTYPES
     * @return array of names
     */
    protected function get_manually_graded_qtypes() {

        global $QTYPES;

        $manualqtypes = array();

        foreach ($QTYPES as $qtype) {
            if ($qtype->is_manual_graded()) {
                $manualqtypes[] = $qtype->name();
            }
        }

        return $manualqtypes;
    }

    /**
     * To make up for the fact that in 2.0 there is no screen with both quiz question and feedback
     * text-entry box next to each other (the feedback bit is a separate pop-up), we have to make
     * a custom form to allow grading to happen. It is based on code from
     * /mod/quiz/reviewquestion.php
     *
     * @global stdClass $CFG
     * @global moodle_database $DB
     * @param array $params all of the stuff sent with the node click e.g. questionid
     * @param object $coursemodule
     * @return string the HTML page
     */
    public function grading_popup($params, $coursemodule) {

        global $CFG, $PAGE, $OUTPUT, $COURSE, $USER, $DB;

        require_once($CFG->dirroot.'/mod/quiz/locallib.php');

        // TODO what params do we get here?

        // We could be looking at multiple attempts and/or multiple questions
        // Assume we have a user/quiz combo to get us here. We may have attemptid or questionid too

        // Get all attempts with unmarked questions. We may or may not have a questionid, but
        // this comes later so we can use the quiz's internal functions
        list($usql, $sqlparams) = $DB->get_in_or_equal($this->get_manually_graded_qtypes(),
                                                       SQL_PARAMS_NAMED);
        $sqlparams['quizid'] = $coursemodule->instance;
        $sqlparams['userid'] = $params['userid'];
        $sql = "
        SELECT DISTINCT(quiz_atttempts.id) AS id
          FROM {quiz_attempts} quiz_atttempts
    INNER JOIN {question_sessions} question_sessions
            ON question_sessions.attemptid = quiz_atttempts.id
    INNER JOIN {question_states} question_states
            ON question_sessions.newest = question_states.id
    INNER JOIN {question} question
            ON question.id = question_sessions.questionid
         WHERE quiz_atttempts.userid = :userid
           AND quiz_atttempts.quiz = :quizid
           AND question.qtype {$usql}
           AND question_states.event NOT IN (".QUESTION_EVENTS_GRADED.")
      ORDER BY question_states.timestamp DESC ";

        $quizattempts = $DB->get_records_sql($sql, $sqlparams);
        if (!$quizattempts) {
            die('Could not retrieve quiz attempt');
        }

        //TODO feed in all dynamic variables here
        $url = new moodle_url('/blocks/ajax_marking/actions/grading_popup.php', $params);
        $PAGE->set_url($url);

        $formattributes = array(
                    'method' => 'post',
                    'class'  => 'mform',
                    'id'     => 'manualgradingform',
                    'action' => block_ajax_marking_form_url());
        echo html_writer::start_tag('form', $formattributes);

        // Each attempt may have multiple question attempts.
        foreach ($quizattempts as $quizattempt) {

            // N.B. Using the proper quiz functions in an attempt to make this more robust
            // against future changes
            $quizattemptobj = quiz_attempt::create($quizattempt->id);

            // Load the questions and states.
            // If we have a questionid, use it to limit what we display. If not, we want all
            // questions that need grading. Not all questions overall, as that would include
            // automatically marked ones.
            if (isset($params['questionid'])) {
                // This can't be random as the tree explicitly looks for essay question states
                $questionids = array($params['questionid']);
                $quizattemptobj->load_questions($questionids);
                $quizattemptobj->load_question_states($questionids);
            } else {
                // Output whatever there is (no questionid from the tree so we show the whole
                // attempt) complication: could be random questions. We only want it if it's a
                // random essay
                // TODO will be used for multiple loops, so move it higher up, dependent on
                // questionid existing
                $sqlparams[] = $coursemodule->instance;
                $sql = "SELECT q.id
                          FROM {question} q,
                    INNER JOIN {quiz_question_instances} qqi
                         WHERE qqi.question = q.id
                           AND qqi.quiz = ?";
                $questionids = $DB->get_records_sql($sql, $sqlparams);
                $gradeableqs = quiz_report_load_questions($quiz);
                $questionsinuse = implode(',', array_keys($gradeableqs));
                foreach ($gradeableqs as $qid => $question) {
                    // unfortunatley, random questions say 'yes' on the next line.
                    // TODO make random non-essay questions go away.
                    if (!$QTYPES[$question->qtype]->is_question_manual_graded($question,
                                                                              $questionsinuse)) {
                        unset($gradeableqs[$qid]);
                    }
                }
            }

            // Log this review.
            $attemptid = $quizattemptobj->get_attemptid();
            add_to_log($quizattemptobj->get_courseid(), 'quiz', 'review',
                       'reviewquestion.php?attempt=' .
                       $attemptid . '&question=' . $params['questionid'] ,
                       $quizattemptobj->get_quizid(), $quizattemptobj->get_cmid());

            // Print infobox
            $rows = array();

            // User picture and name.
            if ($quizattemptobj->get_userid() <> $USER->id) {
                // Print user picture and name
                $student = $DB->get_record('user', array('id' => $quizattemptobj->get_userid()));
                $courseid = $quizattemptobj->get_courseid();
                $picture = $OUTPUT->user_picture($student, array('courseid' => $courseid));
                $url = $CFG->wwwroot . '/user/view.php?id=' . $student->id . '&amp;course=' .
                       $quizattemptobj->get_courseid();
                $rows[] = '<tr>
                               <th scope="row" class="cell">' . $picture . '</th>
                               <td class="cell">
                                   <a href="' .$url. '">' .
                                       fullname($student, true) .
                                  '</a>
                              </td>
                          </tr>';
            }

            // Quiz name.
            $quizname = format_string($quizattemptobj->get_quiz_name());
            $rows[] = '<tr>
                           <th scope="row" class="cell">' . get_string('modulename', 'quiz').'</th>
                           <td class="cell">'.$quizname.'</td>
                       </tr>';

            // Question name.
            $qname = format_string($quizattemptobj->get_question($params['questionid'])->name);
            $rows[] = '<tr>
                           <th scope="row" class="cell">' . get_string('question', 'quiz') .'</th>
                           <td class="cell">' . $qname. '</td>
                       </tr>';

            // Timestamp of this action.
            $timestamp = $quizattemptobj->get_question_state($params['questionid'])->timestamp;
            if ($timestamp) {
                $label = get_string('completedon', 'quiz');
                $rows[] = '<tr>
                               <th scope="row" class="cell">'.$label.'</th>
                               <td class="cell">' . userdate($timestamp) . '</td>
                           </tr>';
            }
            // Now output the summary table, if there are any rows to be shown.
            if (!empty($rows)) {
                echo '<table class="generaltable generalbox quizreviewsummary"><tbody>', "\n";
                echo implode("\n", $rows);
                echo "\n</tbody></table>\n";
            }
            // Work out the base URL of this page. Should probably be different
            $baseurl = $CFG->wwwroot . '/mod/quiz/reviewquestion.php?attempt=' .
                    $quizattemptobj->get_attemptid() . '&amp;question=' . $params['questionid'];

            // Now make the actual markup to show one question plus commenting/grading stuff
            $quizattemptobj->print_question($params['questionid'], false, $baseurl);
            echo html_writer::start_tag('div');
            // The prefix with attemptid in it allows us to save the details for separate attempts
            // properly
            $quizattemptobj->question_print_comment_fields($params['questionid'],
                                                           'response-'.$attemptid);
            echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => 'Save'));
            echo html_writer::empty_tag('input', array('type' => 'hidden',
                                                       'name' => 'attemptid',
                                                       'value' => $quizattempt->id));
            foreach ($params as $name => $value) {
                echo html_writer::empty_tag('input', array('type' => 'hidden',
                                                           'name' => $name,
                                                           'value' => $value));
            }
            echo html_writer::empty_tag('input', array('type' => 'hidden',
                                                      'name' => 'sesskey',
                                                      'value' => sesskey()));
            echo html_writer::end_tag('div');

        }

        echo html_writer::end_tag('form');
    }

    /**
     * Deals with data coming in from the grading pop up
     *
     * @param object $data the form data
     * @return mixed true on success or an error.
     */
    public function process_data($data) {

        global $CFG;

        // Need to separate the different responses from each other so we can loop through them for
        // each question

        // Put responses into an array of arrays
        // attemptid1
        // -- comment
        // -- grade
        // attemptid2
        // -comment
        // -- grade
        // etc

        // then loop through them.

        // $data->prefix-1
        // [comment]
        // [grade]
        // $data->prefix-2

        // Loop over the data object's properties looking for ones that start with the correct
        // prefix. A bit awkward, but this is how the quiz does it
        $responses = array();
        foreach ((array)$data as $key => $item) {
            preg_match('/^(response)-(\d+)/', $key, $matches);
            if (isset($matches[1]) && $matches[1] == 'response') {
                if (isset($matches[2])) {
                    $responses[$matches[2]] = $item; // uses attemptid as the key
                }
            }
        }

        require_once($CFG->dirroot.'/mod/quiz/locallib.php');

        foreach ($responses as $attemptid => $response) {

            // We can have a grade with no comment, but a comment with no grade is probably an
            // accident
            // TODO return the incomplete form with an error so it can be fixed
            if (empty($response['grade'])) {
                if (empty($response['comment'])) {
                    continue;
                }
                $response['grade'] = 0;
            }

            $attemptobj = quiz_attempt::create($attemptid);

            // load question details
            $questionids = array($data->questionid);
            $attemptobj->load_questions($questionids);
            $attemptobj->load_question_states($questionids);

            $result = $attemptobj->process_comment($data->questionid, $response['comment'],
                                                   FORMAT_HTML, $response['grade']);

            // Need to update the question state

            // TODO notify any errors
        }

    }

    /**
     * Returns a query object with the basics all set up to get assignment stuff
     *
     * @param bool $callback
     * @global moodle_database $DB
     * @return block_ajax_marking_query_base
     */
    public function query_factory($callback = false) {

        global $DB;

        $query = new block_ajax_marking_query_base($this);
        $query->set_userid_column('quiz_attempts.userid');

        global $DB;

        $query->add_from(array(
                'table' => $this->modulename,
                'alias' => 'moduletable',
        ));
        $query->add_from(array(
                'join'  => 'INNER JOIN',
                'table' => 'quiz_attempts',
                'on'    => 'moduletable.id = quiz_attempts.quiz'
        ));
        $query->add_from(array(
                'join'  => 'INNER JOIN',
                'table' => 'question_attempts',
                'alias' => 'qatt',
                'on'    => 'qatt.questionusageid = quiz_attempts.uniqueid'
        ));
        $query->add_from(array(
                'join'  => 'INNER JOIN',
                'table' => 'question_attempt_steps',
                'alias' => 'sub',
                'on'    => 'qatt.id = sub.questionattemptid'
        ));
        $query->add_from(array(
                'join'  => 'INNER JOIN',
                'table' => 'question',
                'on'    => 'qatt.questionid = question.id'
        ));

        $query->add_where(array('type' => 'AND',
                                'condition' => 'quiz_attempts.timefinish > 0'));
        $query->add_where(array('type' => 'AND',
                                'condition' => 'quiz_attempts.preview = 0'));
        $comparesql = $DB->sql_compare_text('qatt.behaviour')." = 'manualgraded'";
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
        list($gradedsql, $gradedparams) = $DB->get_in_or_equal($gradedstates, SQL_PARAMS_NAMED, 'quizq001');
        $subsql = "NOT EXISTS( SELECT 1
                                 FROM {question_attempt_steps} st
                                WHERE st.state {$gradedsql}
                                  AND st.questionattemptid = qatt.id)";
        $query->add_where(array('type' => 'AND',
                                'condition' => $subsql));
        $query->add_params($gradedparams, false);
        return $query;
    }

    /**
     * Applies the module-specific stuff for the user nodes
     *
     * @param block_ajax_marking_query_base $query
     * @param int|\type $userid
     * @param bool $outerquery
     * @return void
     */
    public function apply_userid_filter(block_ajax_marking_query_base $query, $userid = 0,
                                        $outerquery = false) {

        if ($userid) {
            // Applies if users are not the final nodes
            $query->add_where(array(
                    'type' => 'AND',
                    'condition' => 'quiz_attempts.userid = :'.$query->prefix_param('submissionid'))
            );
            $query->add_param('submissionid', $userid);
            return;
        }

        if ($outerquery) {

            $selects = array(
                    array(
                        'table'    => 'usertable',
                        'column'   => 'firstname'),
                    array(
                        'table'    => 'usertable',
                        'column'   => 'lastname'));

            foreach ($selects as $select) {
                $query->add_select($select);
            }

            $query->add_from(array(
                    'join'  => 'INNER JOIN',
                    'table' => 'user',
                    'alias' => 'usertable',
                    'on'    => 'usertable.id = combinedmodulesubquery.id'
            ));

        } else {
            $selects = array(
                array(
                    'table'    => 'quiz_attempts',
                    'column'   => 'userid'),
                array( // Count in case we have user as something other than the last node
                    'function' => 'COUNT',
                    'table'    => 'sub',
                    'column'   => 'id',
                    'alias'    => 'count'),
                // This is only needed to add the right callback function.
                array(
                    'column' => "'".$query->get_modulename()."'",
                    'alias' => 'modulename'
                    ));
            foreach ($selects as $select) {
                $query->add_select($select);
            }
        }
    }

}

