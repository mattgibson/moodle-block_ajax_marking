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
    function __construct() {
        
        // must be the same as the DB modulename
        $this->modulename  = 'quiz';

        $this->moduleid    = $this->get_module_id();

        $this->capability  = 'mod/quiz:grade';
        $this->levels      = 4;
        $this->icon        = 'mod/quiz/icon.gif';
        $this->callbackfunctions   = array(
            'quiz_questions',
            'submissions'
        );
    }

    /**
     * gets all unmarked quiz question from all courses. used for the courses count
     *
     * @return bool true
     */
    function get_all_unmarked($courseids) {

        global $DB;

        list($coursessql, $coursesparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);


        $sql = "SELECT qst.id as qstid, qa.userid, qsess.questionid, qz.id,
                       qz.name, qz.course, c.id as cmid
                  FROM {quiz} qz
            INNER JOIN {course_modules} c
                    ON qz.id = c.instance
            INNER JOIN {quiz_attempts} qa
                    ON qz.id = qa.quiz
            INNER JOIN {question_sessions} qsess
                    ON qsess.attemptid = qa.uniqueid
            INNER JOIN {question_states} qst
                    ON qsess.newest = qst.id
            INNER JOIN {question} q
                    ON qsess.questionid = q.id
                 WHERE qa.timefinish > 0
                   AND qa.preview = 0
                   AND c.module = :moduleid
                   AND c.visible = 1
                   AND q.qtype = 'essay'
                   AND qz.course $coursessql
                   AND qst.event NOT IN (".QUESTION_EVENTGRADE.", ".
                                           QUESTION_EVENTCLOSEANDGRADE.", ".
                                           QUESTION_EVENTMANUALGRADE.")
              ORDER BY qa.timemodified";
        $coursesparams['moduleid'] = $this->moduleid;
        return $DB->get_records_sql($sql, $coursesparams);
    }
    
    /**
     * See documentation for abstract function in superclass
     * 
     * @global type $DB
     * @return array of objects
     */
    function get_course_totals() {
        
        global $DB;

        list($displayjoin, $displaywhere) = $this->get_display_settings_sql('qz', 'qa.userid');
        
        $sql = "SELECT qz.course AS courseid, COUNT(qst.id) AS count
                  FROM {quiz} qz
            INNER JOIN {course_modules} c
                    ON qz.id = c.instance
            INNER JOIN {quiz_attempts} qa
                    ON qz.id = qa.quiz
            INNER JOIN {question_sessions} qsess
                    ON qsess.attemptid = qa.uniqueid
            INNER JOIN {question_states} qst
                    ON qsess.newest = qst.id
            INNER JOIN {question} q
                    ON qsess.questionid = q.id
                       {$displayjoin}
                 WHERE qa.timefinish > 0
                   AND qa.preview = 0
                   AND c.module = :moduleid
                   AND c.visible = 1
                   AND q.qtype = 'essay'
                   AND qst.event NOT IN (".QUESTION_EVENTGRADE.", ".
                                           QUESTION_EVENTCLOSEANDGRADE.", ".
                                           QUESTION_EVENTMANUALGRADE.")
                       {$displaywhere}
              GROUP BY qz.course";
        
        $params = array();
        $params['moduleid'] = $this->moduleid;
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Gets all the unmarked quiz submissions for a course
     *
     * @param int $courseid the id number of the course
     * @return array results objects
     */
    function get_all_course_unmarked($courseid) {

        global $CFG, $DB;

        $context = get_context_instance(CONTEXT_COURSE, $courseid);
        list($studentsql, $params) = $this->get_role_users_sql($context);

        $sql = "SELECT qsess.id as qsessid, qa.userid, qz.id, qz.course,
                       qz.intro as description, qz.name, c.id as cmid
                  FROM {quiz} qz
            INNER JOIN {course_modules} c
                    ON qz.id = c.instance
            INNER JOIN {quiz_attempts} qa
                    ON qz.id = qa.quiz
            INNER JOIN {question_sessions} qsess
                    ON qsess.attemptid = qa.uniqueid
            INNER JOIN {question_states} qst
                    ON qsess.newest = qst.id
            INNER JOIN {question} q
                    ON qsess.questionid = q.id
            INNER JOIN ({$studentsql}) stsql
                    ON qa.userid = stsql.id
                 WHERE qa.timefinish > 0
                   AND qa.preview = 0
                   AND c.module = :moduleid
                   AND c.visible = 1
                   AND qz.course = :courseid
                   AND q.qtype = 'essay'
                   AND qst.event NOT IN (".QUESTION_EVENTGRADE.", ".
                                           QUESTION_EVENTCLOSEANDGRADE.", ".
                                           QUESTION_EVENTMANUALGRADE.")
                 ORDER BY qa.timemodified";
        $params['moduleid'] = $this->moduleid;
        $params['courseid'] = $courseid;
        $submissions = $DB->get_records_sql($sql, $params);
        return $submissions;
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
    function quiz_questions($quizid, $groupid=null) {

        global $CFG, $USER, $DB;
        
        $data = new stdClass;
        $nodes = array();
        $data->nodetype = 'assessment';

        $quiz = $DB->get_record('quiz', array('id' => $quizid));
        $courseid = $quiz->course;

        $course = $DB->get_record('course', array('id' => $courseid));
        //block_ajax_marking_get_course_students($course);

        //permission to grade?
        $moduleconditions = array(
                'course'   => $quiz->course,
                'module'   => $this->moduleid,
                'instance' => $quiz->id
        );
        $coursemodule = $DB->get_record('course_modules', $moduleconditions);
        $modulecontext = get_context_instance(CONTEXT_MODULE, $coursemodule->id);

        if (!has_capability($this->capability, $modulecontext, $USER->id)) {
            return;
        }

        $context = get_context_instance(CONTEXT_COURSE, $courseid);
        list($studentsql, $params) = $this->get_role_users_sql($context);

        $csv_questions = $DB->get_field('quiz', 'questions', array('id' => $quizid));
        $csv_questions = explode(',', $csv_questions);
        list($questionsql, $questionparams) = $DB->get_in_or_equal($csv_questions, SQL_PARAMS_NAMED, 'param9999');

        $sql = "SELECT qst.id as qstid, qa.userid, qst.event, qs.questionid as id, q.name,
                       q.questiontext as description, q.qtype, qa.timemodified
                  FROM {question_states} qst
            INNER JOIN {question_sessions} qs
                    ON qs.newest = qst.id
            INNER JOIN {question} q
                    ON qs.questionid = q.id
            INNER JOIN {quiz_attempts} qa
                    ON qs.attemptid = qa.uniqueid
            INNER JOIN ({$studentsql}) stsql
                    ON qa.userid = stsql.id
                 WHERE qa.quiz = :quizid
                   AND qa.timefinish > 0
                   AND qa.preview = 0
                   AND qs.questionid $questionsql
                   AND q.qtype = 'essay'
                   AND qst.event NOT IN (".QUESTION_EVENTGRADE.", ".
                                           QUESTION_EVENTCLOSEANDGRADE.", ".
                                           QUESTION_EVENTMANUALGRADE.")
              ORDER BY qa.timemodified";
        $params = array_merge($params, $questionparams);
        $params['quizid'] = $quiz->id;

        $question_attempts = $DB->get_records_sql($sql, $params);

        // not the same as $csv_questions as some of those questions will have no attempts
        // needing attention
        $questions = block_ajax_marking_list_assessment_ids($question_attempts);

        if (!$groupid) {

            if (!block_ajax_marking_assessment_groups_filter($question_attempts, 'quiz', $quizid, $quiz->course)) {
                return;
            }
        }

        // begin json object.

        foreach ($questions as $question) {

            $count = 0;

            foreach ($question_attempts as $question_attempt) {

                // if we have come from a group node, ignore attempts where the user is not in the
                // right group. Also ignore attempts not relevant to this question
                $rightquestion = ($question_attempt->id == $question->id);

                if (($groupid && !block_ajax_marking_is_member_of_group($groupid, $question_attempt->userid)) 
                     || !$rightquestion) {
                    continue;
                }
                $count = $count + 1;
            }

            if ($count > 0) {
                $name = $question->name;
                $questionid = $question->id;
                $sum = $question->description;
                $sumlength = strlen($sum);
                $shortsum = substr($sum, 0, 100);

                if (strlen($shortsum) < strlen($sum)) {
                    $shortsum .= '...';
                }
                $length = 30;
                
                $node = new stdClass();

                $node->label                = block_ajax_marking_add_icon('question').
                                              '(<span class="AMB_count">'.$count.'</span>) '.
                                              block_ajax_marking_clean_name_text($name, $length);
                $node->name                 = block_ajax_marking_clean_name_text($name, $length);
                $node->callbackparamtwo     = $questionid;
                $node->callbackparamone     = $quizid;
                $node->icon                 = block_ajax_marking_add_icon('question');
                
                if ($groupid) {
                $node->group                = $groupid;
                }
                $node->assid                = 'qq'.$questionid;
                $node->callbackfunction     = 'submissions';
                $node->summary              = block_ajax_marking_clean_summary_text($shortsum);
                $node->count                = $count;
                $node->uniqueid             = 'quiz'.$quizid.'quiz_question'.$questionid;
                $node->modulename           = $this->modulename;
                
                $nodes[] = $node;
            }
        }
        return array($data, $nodes);
    }

    /**
     * Makes the nodes with the student names for each question. works either with or without a group having been set.
     *
     * @param int $quizid
     * @param int $questionid
     * @param int $groupid
     * @return void
     */
    function submissions($quizid, $groupid, $questionid) {

        global $CFG, $USER, $DB;
        
        $data = new stdClass;
        $nodes = array();
        $data->nodetype = 'submission';

        $quiz = $DB->get_record('quiz', array('id' => $quizid));
        $courseid = $quiz->course;

        // so we have cached student details
        //$course = $DB->get_record('course', array('id' => $courseid));
        //get_course_students($course);

        //permission to grade?
        $moduleconditions = array(
                'course'   => $quiz->course,
                'module'   => $this->moduleid,
                'instance' => $quiz->id
        );
        $coursemodule = $DB->get_record('course_modules', $moduleconditions);
        $modulecontext = get_context_instance(CONTEXT_MODULE, $coursemodule->id);
        $coursecontext = get_context_instance(CONTEXT_COURSE, $courseid);

        if (!has_capability($this->capability, $modulecontext, $USER->id)) {
            return;
        }

        list($studentsql, $params) = $this->get_role_users_sql($coursecontext);

        $sql = "SELECT qst.id, COUNT(DISTINCT qst.id) as count, qa.userid, qst.event, 
                       qs.questionid, qst.timestamp, qs.attemptid, u.firstname, u.lastname
                  FROM {question_states} qst
            INNER JOIN {question_sessions} qs
                    ON qs.newest = qst.id
            INNER JOIN {quiz_attempts} qa
                    ON qs.attemptid = qa.uniqueid
            INNER JOIN {user} u
                    ON qa.userid = u.id
            INNER JOIN ({$studentsql}) stsql
                    ON qa.userid = stsql.id
                 WHERE qa.quiz = :quizid
                   AND qa.timefinish > 0
                   AND qa.preview = 0
                   AND qs.questionid = :questionid
                   AND qst.event NOT IN (".QUESTION_EVENTGRADE.", ".
                                           QUESTION_EVENTCLOSEANDGRADE.", ".
                                           QUESTION_EVENTMANUALGRADE.")
              GROUP BY qa.userid, qs.questionid
              ORDER BY qa.timemodified";
        $params['quizid'] = $quizid;
        $params['questionid'] = $questionid;
        $question_attempts = $DB->get_records_sql($sql, $params);

        if ($question_attempts) {

            foreach ($question_attempts as $question_attempt) {

                if (!isset($question_attempt->userid)) {
                    continue;
                }
                
                // If this is a group node, ignore those where the student is not in the right group
                if ($groupid && !block_ajax_marking_is_member_of_group($grtoupid, $question_attempt->userid)) {
                     continue;
                }

                // Sometimes, a person will have more than 1 attempt for the question.
                // No need to list them twice, so we add a count after their name.
                if ($question_attempt->count > 1) {
                    $name .=' ('.$question_attempt->count.')';
                }

                $now = time();
                $seconds = ($now - $question_attempt->timestamp);
                $summary = block_ajax_marking_make_time_summary($seconds);

                $nodes[] = block_ajax_marking_make_submission_node(array(
                        'name'          => fullname($question_attempt),
                        'attemptid'     => $question_attempt->attemptid,
                        'questionid'    => $questionid,
                        'uniqueid'      => 'quiz_final'.$question_attempt->attemptid.'-'.$quizid,
                        'title'         => $summary,
                        'seconds'       => $seconds,
                        'time'          => $question_attempt->timestamp,
                        'modulename'    => $this->modulename,
                        'count'         => $question_attempt->count));


            }
            return array($data, $nodes);
        }
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
        $params['moduleid'] = $this->moduleid;
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
        $address = $CFG->wwwroot.'/mod/quiz/report.php?q='.$item->id.'&mode=grading';
        return $address;
    }

    /**
     * To make up for the fact that in 2.0 there is no screen with both quiz question and feedback
     * text-entry box next to each other (the feedback bit is a separate pop-up), we have to make
     * a custom form to allow grading to happen. It is based on code from /mod/quiz/reviewquestion.php
     *
     * @param string uniqueid the identifier of the node that needs to be deleted from the tree
     */
    static function grading_popup($uniqueid) {

        global $CFG, $PAGE, $OUTPUT, $COURSE, $USER, $DB;

        require_once($CFG->dirroot.'/mod/quiz/locallib.php');

        $attemptid = required_param('attempt', PARAM_INT); // attempt id
        $questionid = required_param('question', PARAM_INT); // question id
        $stateid = optional_param('state', 0, PARAM_INT); // state id

        $url = new moodle_url('/blocks/ajax_marking/actions/grading_popup.php',
                              array('module'=>'quiz',
                                    'attempt'=>$attemptid,
                                    'question'=>$questionid));
        if ($stateid !== 0) {
            $url->param('state', $stateid);
        }
        $PAGE->set_url($url);

        $attemptobj = quiz_attempt::create($attemptid);

    /// Check login.
        require_login($attemptobj->get_courseid(), false, $attemptobj->get_cm());
        $attemptobj->check_review_capability();

    /// Create an object to manage all the other (non-roles) access rules.
        $accessmanager = $attemptobj->get_access_manager(time());
        $options = $attemptobj->get_review_options();

    /// Permissions checks for normal users who do not have quiz:viewreports capability.
        if (!$attemptobj->has_capability('mod/quiz:viewreports')) {
        /// Can't review during the attempt - send them back to the attempt page.
            if (!$attemptobj->is_finished()) {
                echo $OUTPUT->header();
                echo $OUTPUT->notification(get_string('cannotreviewopen', 'quiz'));
                echo $OUTPUT->close_window_button();
                echo $OUTPUT->footer();
                die;
            }
        /// Can't review other users' attempts.
            if (!$attemptobj->is_own_attempt()) {
                echo $OUTPUT->header();
                echo $OUTPUT->notification(get_string('notyourattempt', 'quiz'));
                echo $OUTPUT->close_window_button();
                echo $OUTPUT->footer();
                die;
            }

        /// Can't review unless Students may review -> Responses option is turned on.
            if (!$options->responses) {
                $accessmanager = $attemptobj->get_access_manager(time());
                echo $OUTPUT->header();
                echo $OUTPUT->notification($accessmanager->cannot_review_message($attemptobj->get_review_options()));
                echo $OUTPUT->close_window_button();
                echo $OUTPUT->footer();
                die;
            }
        }

    /// Load the questions and states.
        $questionids = array($questionid);
        $attemptobj->load_questions($questionids);
        $attemptobj->load_question_states($questionids);

    /// If it was asked for, load another state, instead of the latest.
        if ($stateid) {
            $attemptobj->load_specific_question_state($questionid, $stateid);
        }

    /// Work out the base URL of this page.
        $baseurl = $CFG->wwwroot . '/mod/quiz/reviewquestion.php?attempt=' .
                $attemptobj->get_attemptid() . '&amp;question=' . $questionid;

    /// Log this review.
        add_to_log($attemptobj->get_courseid(), 'quiz', 'review', 'reviewquestion.php?attempt=' .
                $attemptobj->get_attemptid() . '&question=' . $questionid .
                ($stateid ? '&state=' . $stateid : ''),
                $attemptobj->get_quizid(), $attemptobj->get_cmid());

    /// Print the page header
        //$attemptobj->get_question_html_head_contributions($questionid);
        //$PAGE->set_title($attemptobj->get_course()->shortname . ': '.format_string($attemptobj->get_quiz_name()));
        //$PAGE->set_heading($COURSE->fullname);
        $PAGE->set_pagelayout('popup');
        echo $OUTPUT->header();

    /// Print infobox
        $rows = array();

    /// User picture and name.
        if ($attemptobj->get_userid() <> $USER->id) {
            // Print user picture and name
            $student = $DB->get_record('user', array('id' => $attemptobj->get_userid()));
            $picture = $OUTPUT->user_picture($student, array('courseid'=>$attemptobj->get_courseid()));
            $rows[] = '<tr><th scope="row" class="cell">' . $picture . '</th><td class="cell"><a href="' .
                    $CFG->wwwroot . '/user/view.php?id=' . $student->id . '&amp;course=' . $attemptobj->get_courseid() . '">' .
                    fullname($student, true) . '</a></td></tr>';
        }

    /// Quiz name.
        $rows[] = '<tr><th scope="row" class="cell">' . get_string('modulename', 'quiz') .
                '</th><td class="cell">' . format_string($attemptobj->get_quiz_name()) . '</td></tr>';

    /// Question name.
        $rows[] = '<tr><th scope="row" class="cell">' . get_string('question', 'quiz') .
                '</th><td class="cell">' . format_string(
                $attemptobj->get_question($questionid)->name) . '</td></tr>';

    /// Other attempts at the quiz.
        if ($attemptobj->has_capability('mod/quiz:viewreports')) {
            $attemptlist = $attemptobj->links_to_other_attempts($baseurl);
            if ($attemptlist) {
                $rows[] = '<tr><th scope="row" class="cell">' . get_string('attempts', 'quiz') .
                        '</th><td class="cell">' . $attemptlist . '</td></tr>';
            }
        }

    /// Timestamp of this action.
        $timestamp = $attemptobj->get_question_state($questionid)->timestamp;
        if ($timestamp) {
            $rows[] = '<tr><th scope="row" class="cell">' . get_string('completedon', 'quiz') .
                    '</th><td class="cell">' . userdate($timestamp) . '</td></tr>';
        }

    /// Now output the summary table, if there are any rows to be shown.
        if (!empty($rows)) {
            echo '<table class="generaltable generalbox quizreviewsummary"><tbody>', "\n";
            echo implode("\n", $rows);
            echo "\n</tbody></table>\n";
        }

    /// Print the question in the requested state.
        if ($stateid) {
            $baseurl .= '&amp;state=' . $stateid;
        }
        $attemptobj->print_question($questionid, false, $baseurl);


        // Adding the bit from /mod/quiz/comment.php to allow us to add comments and grades

        echo '<form method="post" class="mform" id="manualgradingform" action="' . $CFG->wwwroot .
             '/blocks/ajax_marking/actions/grading_popup.php?module=quiz&uniqueid='.$uniqueid.'">';
        $attemptobj->question_print_comment_fields($questionid, 'response');
?>
<div>
    <input type="hidden" name="attempt" value="<?php echo $attemptobj->get_attemptid(); ?>" />
    <input type="hidden" name="question" value="<?php echo $questionid; ?>" />
    <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>" />
</div>
<fieldset class="hidden">
    <div>
        <div class="fitem">
            <div class="fitemtitle">
                <div class="fgrouplabel"><label> </label></div>
            </div>
            <fieldset class="felement fgroup">
                <input id="id_submitbutton" type="submit" name="submit" value="<?php print_string('save', 'quiz'); ?>"/>
                <input id="id_cancel" type="button" value="<?php print_string('cancel'); ?>" onclick="close_window"/>
            </fieldset>
        </div>
    </div>
</fieldset>
<?php
    echo '</form>';


    /// Finish the page
        echo $OUTPUT->footer();
        

    }

    /**
     * 
     */
    static function process_data($data) {

        global $CFG;

        require_once($CFG->dirroot.'/mod/quiz/locallib.php');

        $attemptid = required_param('attempt', PARAM_INT); // attempt id
        $questionid = required_param('question', PARAM_INT);
        $attemptobj = quiz_attempt::create($attemptid);

        // permissions check
        require_login($attemptobj->get_courseid(), false, $attemptobj->get_cm());
        $attemptobj->require_capability('mod/quiz:grade');

        // load question details
        $questionids = array($questionid);
        $attemptobj->load_questions($questionids);
        $attemptobj->load_question_states($questionids);

        return $attemptobj->process_comment($questionid, $data->response['comment'],
                                            FORMAT_HTML, $data->response['grade']);

    }
    
    /**
     * Slightly neater than having a separate file for the js that we include is to have this as a 
     * static function here. 
     * 
     * @return string the javascript to append to module.js.php
     */
    static function extra_javascript() {
        // Get the IDE to do proper script highlighting
        if(0) { ?><script><?php } 
        
        ?>
M.block_ajax_marking.quiz = (function() {

    return {
        
//        pop_up_post_data : function(clickednode) {
//            return 'mode=grading&action=grade&q='+clickednode.parent.parent.data.id+'&questionid='+
//                   clickednode.data.aid+'&userid='+clickednode.data.sid;
//        },
        
        pop_up_closing_url : function() {
            return '/mod/quiz/report.php';
        },
        
        pop_up_opening_url : function(clickednode) {
            return '/blocks/ajax_marking/actions/grading_popup.php?module=quiz&attempt='+clickednode.data.attemptid+
                   '&question='+clickednode.data.questionid+'&uniqueid='+clickednode.data.uniqueid;
        },
        
        pop_up_arguments : function() {
            return 'menubar=0,location=0,scrollbars,resizable,width=780,height=670';
        },
        
        extra_ajax_request_arguments : function(clickednode) {
        
            if (clickednode.data.type == 'quiz_question') {
                return '&secondary_id='+clickednode.parent.data.id;
            } else {
                return '';
            }
        },
        
        /**
         * adds onclick stuff to the quiz popup
         */
        alter_popup : function(clickednode) {
        
            var inputelements = '';
            var lastbutone = '';
        
            if (typeof(M.block_ajax_marking.popupholder.document.getElementsByTagName('input')) != 'undefined') {
                // window is open with some input. could be loading lots though.
                inputelements = M.block_ajax_marking.popupholder.document.getElementsByTagName('input');
        
                if (inputelements.length > 14) {
                
                    // there is at least the DOM present for a single attempt, but if the student has
                    // made a couple of attempts, there will be a larger window.
                    lastbutone = inputelements.length - 1;
        
                    if (inputelements[lastbutone].value == amVariables.quizSaveString) {
        
                        // the onclick carries out the functions that are already specified in lib.php,
                        // followed by the function to update the tree
                        // TODO - did this change work?
                        var functiontext = "return M.block_ajax_marking.markingtree.remove_node_from_tree('/mod/quiz/report.php', '"
                                         + clickednode.data.uniqueid+"'); ";
                        inputelements[lastbutone]['onclick'] = new Function(functiontext);
                        //inputelements[lastButOne]["onclick"] = new Function("return M.block_ajax_marking.remove_node_from_tree('/mod/quiz/report.php', M.block_ajax_marking.main, '"+me+"'); ");
                        // cancel the loop for this function
        
                        window.clearInterval(M.block_ajax_marking.popuptimer);
                    }
                }
            }
        }
    };
})();
            
        <?php
        
        // Get the IDE to do proper script highlighting
        if(0) { ?></script><?php } 
        
    }

}