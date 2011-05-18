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

// We only need this file for the constants. Doing this so that we don't have problems including the file from
// module.js.php
global $CFG;

if (isset($CFG) && !empty($CFG)) {
    require_once($CFG->dirroot.'/lib/questionlib.php');
    require_once($CFG->dirroot.'/blocks/ajax_marking/modules/quiz/block_ajax_marking_quiz_form.class.php');
}



// constants from /lib/questionlib.php
// Moodle has graded the responses. A SUBMIT event can be changed to a GRADE event by Moodle.
// define('QUESTION_EVENTGRADE', '3');
// Moodle has graded the responses. A CLOSE event can be changed to a CLOSEANDGRADE event by Moodle.
// define('QUESTION_EVENTCLOSEANDGRADE', '6');
// Grade was entered by teacher
// define('QUESTION_EVENTMANUALGRADE', '9');

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
     * @param object $reference the parent object passed in by reference so that it's data can be used
     * @return void
     */
    public function __construct() {
        
        // call parent constructor with the same arguments
        //call_user_func_array(array($this, 'parent::__construct'), func_get_args());
        parent::__construct();
        
        // must be the same as the DB modulename
        $this->modulename           = 'quiz';
        $this->capability           = 'mod/quiz:grade';
        $this->icon                 = 'mod/quiz/icon.gif';
        $this->callbackfunctions    = array(
                'quiz_questions',
                'submissions'
        );
        
    }
   
    /**
     * Gets all of the question attempts for the current quiz. Uses the group
     * filtering function to display groups first if that has been specified via
     * config. Seemed like a better idea than questions then groups as tutors
     * will mostly have a class to mark rather than a question to mark.
     * Uses $this->id as the quiz id
     *
     * @param int $quizid The id of the quiz to get questions for
     * @param int $groupid The id of the group that may have been supplied with the ajax request
     * @return void
     */
    function quiz_questions($params) {

        global $CFG, $USER, $DB;
        
        $data = new stdClass;
        $nodes = array();
        $data->nodetype = 'assessment';

        $query = $this->get_sql_query_base();
        
        array_push($query['select'],
                'q.id', 
                'COUNT(sub.id) AS count',
                'q.name',
                'q.questiontext AS tooltip', 
                'qa.timemodified AS time' 
        );

        // Filter to include only questions from this quiz
        $query['where'] .= "AND moduletable.id = :assessmentid ";
        $query['params']['assessmentid'] = $params['assessmentid'];
        
        $query['groupby'] = ' q.id';

        $questions = $this->execute_sql_query($query);

        foreach ($questions as $question) {
                
                if (strlen($question->tooltip) < 100) {
                    $question->tooltip = substr($question->tooltip, 0, 100).'...';
                }
                
                $question->name        = block_ajax_marking_clean_name_text($question->name, 30);
                $question->tooltip     = block_ajax_marking_clean_tooltip_text($question->tooltip);
//                $question->uniqueid    = 'quiz'.$params['assessmentid'].'quiz_question'.$question->id;
                
                $question->questionid           = $question->id;
                $question->assessmentid         = $params['assessmentid'];
                // TODO make this dynamic
                $question->callbackfunction     = 'submissions';
                
//                $question->uniqueid             = 'quiz'.$params['assessmentid'].'quiz_question'.$question->id;
                $question->modulename           = $this->modulename;
                
                block_ajax_marking_format_node($question);
        }
        
        return array($data, array_values($questions));
    }
    
    /**
     * Need attemptid and questionid for the pop up
     * 
     * @param type $moduleid
     * @return type 
     */
    protected function get_sql_submissions_select($moduleid) {
        
        return array(
                'sub.id AS subid',
                'moduletable.intro AS description',
                'qa.timefinish AS time',
                'COUNT(DISTINCT sub.id) as count',
                'qa.id as attemptid',
                'qsess.questionid',
                'u.firstname', 
                'u.lastname'
        );
    }
    
    /**
     * See parent class for docs
     * 
     * @return string
     */
    protected function get_sql_submissions_groupby() {
        
        $useridcolumn = $this->get_sql_userid_column();
        
        return "{$useridcolumn}, qsess.questionid";
    }
    
    /**
     * See parent class for docs
     * 
     * @return string
     */
    protected function get_sql_submission_where() {
        return 'AND qsess.questionid = :questionid ';
    }
    
    protected function popup_variables() {
        return array(
                'attemptid',
                'questionid'
        );
    }
    

    /**
     * gets all the quizzes for the config screen. still need the check in there for essay questions.
     *
     * @return void
     */
    function get_all_gradable_items($courseids) {

        global $CFG, $DB;

        list($usql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);

        $sql = "SELECT qz.id, qz.course, qz.intro as summary, qz.name, c.id as cmid
                  FROM {quiz} qz
            INNER JOIN {course_modules} c
                    ON qz.id = c.instance
            INNER JOIN {quiz_question_instances} qqi
                    ON qz.id = qqi.quiz
            INNER JOIN {question} q
                    ON qqi.question = q.id
                 WHERE c.module = :moduleid
                   AND c.visible = 1
                   AND q.qtype = 'essay'
                   AND qz.course $usql
              ORDER BY qz.id";
        $params['moduleid'] = $this->get_module_id();
        $quizzes = $DB->get_records_sql($sql, $params);
        $this->assessments = $quizzes;

    }

    /**
     * Makes an HTML link for the pop up to allow grading of a question
     *
     * @param object $item containing the quiz id as ->id
     * @return string
     */
    function make_html_link($item) {

        global $CFG;
        $address = $CFG->wwwroot.'/mod/quiz/report.php?q='.$item->assessmentid.'&mode=grading';
        return $address;
    }
    
    /**
     * See superclass for details
     * 
     * @return array the select, join and where clauses, with the aliases for module and submission tables
     */
    function get_sql_count() {
        
        global $DB;
        
        $moduletable = $this->get_sql_module_table();
        $submissiontable = $this->get_sql_submission_table();
        
        $from = "    FROM {{$moduletable}} moduletable
               INNER JOIN {quiz_attempts} qa
                       ON moduletable.id = qa.quiz
               INNER JOIN {question_sessions} qsess
                       ON qsess.attemptid = qa.uniqueid
               INNER JOIN {{$submissiontable}} sub
                       ON qsess.newest = sub.id
               INNER JOIN {question} q
                       ON qsess.questionid = q.id ";
                       
        $where = "  WHERE qa.timefinish > 0
                      AND qa.preview = 0
                      AND ".$DB->sql_compare_text('q.qtype')." = 'essay'
                      AND sub.event NOT IN (".QUESTION_EVENTGRADE.", ".
                                              QUESTION_EVENTCLOSEANDGRADE.", ".
                                              QUESTION_EVENTMANUALGRADE.") ";
        
        $params = array();
                        
        return array($from, $where, $params);
    }
    
    protected function get_sql_submission_table() {
        return 'question_states';
    }
    
    protected function get_sql_userid_column() {
        return 'qa.userid';
    }

    /**
     * To make up for the fact that in 2.0 there is no screen with both quiz question and feedback
     * text-entry box next to each other (the feedback bit is a separate pop-up), we have to make
     * a custom form to allow grading to happen. It is based on code from /mod/quiz/reviewquestion.php
     *
     * @param array $params all of the stuff sent with the node click e.g. questionid
     * @return string the HTML page
     */
    public function grading_popup($params) {

        global $CFG, $PAGE, $OUTPUT, $COURSE, $USER, $DB;

        require_once($CFG->dirroot.'/mod/quiz/locallib.php');

        if (!isset($params['attemptid'], $params['questionid'])) {
            die('Missing required params');
        }
        
        $url = new moodle_url('/blocks/ajax_marking/actions/grading_popup.php',
                              array('mod'=>'quiz',
                                    'attemptid'=>$params['attemptid'],
                                    'questionid'=>$params['questionid']));
        $PAGE->set_url($url);

        $attemptobj = quiz_attempt::create($params['attemptid']);

        // Create an object to manage all the other (non-roles) access rules.
        $accessmanager = $attemptobj->get_access_manager(time());
        $options = $attemptobj->get_review_options();

        // Load the questions and states.
        $questionids = array($params['questionid']);
        $attemptobj->load_questions($questionids);
        $attemptobj->load_question_states($questionids);

        // Work out the base URL of this page.
        $baseurl = $CFG->wwwroot . '/mod/quiz/reviewquestion.php?attempt=' .
                $attemptobj->get_attemptid() . '&amp;question=' . $params['questionid'];

        // Log this review.
        add_to_log($attemptobj->get_courseid(), 'quiz', 'review', 'reviewquestion.php?attempt=' .
                $attemptobj->get_attemptid() . '&question=' . $params['questionid'] ,
                $attemptobj->get_quizid(), $attemptobj->get_cmid());

        // Print infobox
        $rows = array();

        // User picture and name.
        if ($attemptobj->get_userid() <> $USER->id) {
            // Print user picture and name
            $student = $DB->get_record('user', array('id' => $attemptobj->get_userid()));
            $picture = $OUTPUT->user_picture($student, array('courseid'=>$attemptobj->get_courseid()));
            $rows[] = '<tr><th scope="row" class="cell">' . $picture . '</th><td class="cell"><a href="' .
                    $CFG->wwwroot . '/user/view.php?id=' . $student->id . '&amp;course=' . $attemptobj->get_courseid() . '">' .
                    fullname($student, true) . '</a></td></tr>';
        }

        // Quiz name.
        $rows[] = '<tr><th scope="row" class="cell">' . get_string('modulename', 'quiz') .
                '</th><td class="cell">' . format_string($attemptobj->get_quiz_name()) . '</td></tr>';

        // Question name.
        $rows[] = '<tr><th scope="row" class="cell">' . get_string('question', 'quiz') .
                '</th><td class="cell">' . format_string(
                $attemptobj->get_question($params['questionid'])->name) . '</td></tr>';

        // Other attempts at the quiz.
        // TODO does this work?
        if ($attemptobj->has_capability('mod/quiz:viewreports')) {
            $attemptlist = $attemptobj->links_to_other_attempts($baseurl);
            if ($attemptlist) {
                $rows[] = '<tr><th scope="row" class="cell">' . get_string('attempts', 'quiz') .
                        '</th><td class="cell">' . $attemptlist . '</td></tr>';
            }
        }

        // Timestamp of this action.
        $timestamp = $attemptobj->get_question_state($params['questionid'])->timestamp;
        if ($timestamp) {
            $rows[] = '<tr><th scope="row" class="cell">' . get_string('completedon', 'quiz') .
                    '</th><td class="cell">' . userdate($timestamp) . '</td></tr>';
        }

        // Now output the summary table, if there are any rows to be shown.
        if (!empty($rows)) {
            echo '<table class="generaltable generalbox quizreviewsummary"><tbody>', "\n";
            echo implode("\n", $rows);
            echo "\n</tbody></table>\n";
        }

        $attemptobj->print_question($params['questionid'], false, $baseurl);

        $formattributes = array(
                'method' => 'post',
                'class'  => 'mform',
                'id'     => 'manualgradingform',
                'action' => block_ajax_marking_form_url());
        echo html_writer::start_tag('form', $formattributes);
        echo html_writer::start_tag('div');
        $attemptobj->question_print_comment_fields($params['questionid'], 'response');
        echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => 'Save'));
        
        foreach ($params as $name => $value) {
            echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $name, 'value' => $value));
        }
        
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
        echo html_writer::end_tag('div');
        echo html_writer::end_tag('form');

//        $feedbackform = new block_ajax_marking_quiz_form(block_ajax_marking_form_url($params), $attemptobj);
//        
//        $feedbackform->display();

    }

    /**
     * Deals with data coming in from the grading pop up
     * 
     * @param object $data the form data
     * @return mixed true on success or an error.
     */
    public function process_data($data) {

        global $CFG;

        require_once($CFG->dirroot.'/mod/quiz/locallib.php');

        // TODO get these into form stuff
//        $attemptid = required_param('attempt', PARAM_INT); // attempt id
//        $questionid = required_param('question', PARAM_INT);
        $attemptobj = quiz_attempt::create($data->attemptid);

        // permissions check
//        require_login($attemptobj->get_courseid(), false, $attemptobj->get_cm());
//        $attemptobj->require_capability('mod/quiz:grade');

        // load question details
        $questionids = array($data->questionid);
        $attemptobj->load_questions($questionids);
        $attemptobj->load_question_states($questionids);

        return $attemptobj->process_comment($data->questionid, $data->response['comment'],
                                            FORMAT_HTML, $data->response['grade']);

    }
    

}

