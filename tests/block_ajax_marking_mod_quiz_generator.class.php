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
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2012 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot.'/mod/quiz/editlib.php');
require_once($CFG->dirroot.'/lib/phpunit/classes/module_generator.php');

/**
 * Makes unit test data for the quiz module.
 */
class block_ajax_marking_mod_quiz_generator extends phpunit_module_generator {

    /**
     * Gets DB module name.
     *
     * @return string|void
     */
    public function get_modulename() {
        return 'quiz';
    }

    /**
     * Create a test quiz module.
     *
     * @param array|stdClass $record
     * @param array $options
     * @throws coding_exception
     * @return \stdClass activity record
     */
    public function create_instance($record = null, array $options = array()) {

        global $DB;

        // So we can give unique names, just in case.
        static $instancecount;

        if (!isset($record->course)) {
            throw new coding_exception('Need course to make new quiz instance via generator');
        }

        $prototypequiz = new stdClass();
        $prototypequiz->name = 'Quiz name '.$instancecount;
        $prototypequiz->intro = 'Standard fake intro';
        $prototypequiz->introformat = FORMAT_HTML;
        $prototypequiz->timeopen = 0;
        $prototypequiz->timeclose = 0;
        $prototypequiz->preferredbehaviour = 'deferredfeedback';
        $prototypequiz->attempts = 0;
        $prototypequiz->attemptonlast = 0;
        $prototypequiz->grademethod = 0;
        $prototypequiz->decimalpoints = 2;
        $prototypequiz->questiondecimalpoints = -1;
        $prototypequiz->reviewattempt = 69904;
        $prototypequiz->reviewcorrectness = 4368;
        $prototypequiz->reviewmarks = 4368;
        $prototypequiz->reviewspecificfeedback = 4368;
        $prototypequiz->reviewgeneralfeedback = 4368;
        $prototypequiz->reviewrightanswer = 4368;
        $prototypequiz->reviewoverallfeedback = 4368;
        $prototypequiz->questionsperpage = 1;
        $prototypequiz->navmethod = 'free';
        $prototypequiz->shufflequestion = 0;
        $prototypequiz->shuffleanswers = 1;
        $prototypequiz->questions = '';
        $prototypequiz->sumgrades = 0.00000;
        $prototypequiz->grade = 100.00000;
        $prototypequiz->timecreated = 0;
        $prototypequiz->timemodified = time();
        $prototypequiz->timelimit = 0;
        $prototypequiz->overduehandling = 'autoabandon';
        $prototypequiz->graceperiod = '';
        $prototypequiz->password = '';
        $prototypequiz->subnet = '';
        $prototypequiz->browsersecurity = '-';
        $prototypequiz->delay1 = 0;
        $prototypequiz->delay2 = 0;
        $prototypequiz->showuserpicture = 0;
        $prototypequiz->showblocks = 0;

        $extended = (object)array_merge((array)$prototypequiz, (array)$record);

        $extended->coursemodule = $this->precreate_course_module($extended->course, $options);
        $extended->id = $DB->insert_record('quiz', $extended);
        return $this->post_add_instance($extended->id, $extended->coursemodule);
    }

    /**
     * Make some questions and add them to the quiz.
     *
     * @param int $courseid
     * @return stdClass
     */
    public function make_question($courseid) {

        $context = context_course::instance($courseid);
        $defaultcategory = question_make_default_categories(array($context));
        $questioncategoryid = $defaultcategory->id;

        global $USER, $DB;

        // Robbed from question/question.php.
        $question = new stdClass();
        $question->category = $questioncategoryid;
        $question->qtype = 'essay';
        $question->createdby = $USER->id;

        $question->formoptions = new stdClass();
        $question->formoptions->canedit = question_has_capability_on($question, 'edit');
        $params = array('id' => $question->category);
        if (!$category = $DB->get_record('question_categories', $params)) {
            print_error('categorydoesnotexist', 'question');
        }
        $categorycontext = context::instance_by_id($category->contextid);
        $addpermission = has_capability('moodle/question:add', $categorycontext);
        $question->formoptions->canmove =
            (question_has_capability_on($question, 'move') && $addpermission);
        $question->formoptions->cansaveasnew = false;
        $question->formoptions->repeatelements = true;
        $question->formoptions->movecontext = false;
        $question->formoptions->mustbeusable = 0;
        $question->errors = array();

        $qtypeobj = question_bank::get_qtype('essay');

        $fromform = new stdClass();
        $fromform->category = $questioncategoryid; // Slash separated.
        $fromform->name = '';
        $fromform->parent = '';
        $fromform->penalty = '';
        $fromform->questiontext['text'] = '';
        $fromform->questiontext['format'] = 1;
        $fromform->responseformat = 'editor';
        $fromform->responsefieldlines = 5;
        $fromform->attachments = 0;
        $fromform->graderinfo['text'] = '';
        $fromform->graderinfo['format'] = '';

        return $qtypeobj->save_question($question, $fromform);
    }

    /**
     * Questions need to have a category. WE just make one.
     */
    public function make_default_question_category($courseid) {

        $context = context_course::instance_by_id($courseid);

        $defaultcategory = question_make_default_categories(array($context));

        return $defaultcategory->id;

    }

    /**
     * Makes a student answer to the supplied question.
     */
    public function make_answer($student, $question, $quiz) {

    }

    /**
     * Starts an attempt and returns the object representing it.
     *
     * @param int $quizid
     * @param int $userid
     * @throws moodle_exception
     * @return \stdClass
     */
    public function start_quiz_attempt($quizid, $userid) {

        global $DB;

        $quizobj = quiz::create($quizid, $userid);
        $timenow = time();
        $attempt = quiz_create_attempt($quizobj,
                                       1,
                                       false,
                                       $timenow,
                                       false);

        // Taken from /mod/quiz/startattempt.php.
        $quba = question_engine::make_questions_usage_by_activity('mod_quiz',
                                                                  $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);

        // Starting a normal, new, quiz attempt.

        // Fully load all the questions in this quiz.
        $quizobj->preload_questions();
        $quizobj->load_questions();

        // Add them all to the $quba.
        $idstoslots = array();
        $questionsinuse = array_keys($quizobj->get_questions());
        foreach ($quizobj->get_questions() as $i => $questiondata) {
            if ($questiondata->qtype != 'random') {
                if (!$quizobj->get_quiz()->shuffleanswers) {
                    $questiondata->options->shuffleanswers = false;
                }
                $question = question_bank::make_question($questiondata);
            } else {
                $question = question_bank::get_qtype('random')->choose_other_question(
                    $questiondata, $questionsinuse, $quizobj->get_quiz()->shuffleanswers);
                if (is_null($question)) {
                    throw new moodle_exception('notenoughrandomquestions', 'quiz',
                                               $quizobj->view_url(), $questiondata);
                }
            }

            $idstoslots[$i] = $quba->add_question($question, $questiondata->maxmark);
            $questionsinuse[] = $question->id;
        }

        // Start all the questions.
        if ($attempt->preview) {
            $variantoffset = rand(1, 100);
        } else {
            $variantoffset = 1;
        }
        $quba->start_all_questions(
            new question_variant_pseudorandom_no_repeats_strategy($variantoffset), $timenow);

        // Update attempt layout.
        $newlayout = array();
        foreach (explode(',', $attempt->layout) as $qid) {
            if ($qid != 0) {
                $newlayout[] = $idstoslots[$qid];
            } else {
                $newlayout[] = 0;
            }
        }
        $attempt->layout = implode(',', $newlayout);

        question_engine::save_questions_usage_by_activity($quba);
        $attempt->uniqueid = $quba->get_id();
        $attempt->id = $DB->insert_record('quiz_attempts', $attempt);

        return $attempt;
    }

    /**
     * Stops the attempt and saves the grade.
     */
    public function end_quiz_attmept($attempt) {

        $timenow = time();

        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_finish($timenow, true);
    }

    /**
     * This adds a particular question to the supplied quiz. Based on /mod/quiz/edit.php
     *
     * @param int $questionid
     * @param stdClass $quiz
     * @return void
     */
    public function add_question_to_quiz($questionid, $quiz) {
        quiz_require_question_use($questionid);
        quiz_add_quiz_question($questionid, $quiz, 0);
        quiz_delete_previews($quiz);
        quiz_update_sumgrades($quiz);
    }

    /**
     * Makes an attempt for one student in this quiz, answering all the questions.
     *
     * @param $student
     * @param $quiz
     * @return int How many questions answers were made.
     */
    public function make_student_quiz_atttempt($student, $quiz) {

        $submissioncount = 0;

        $attempt = $this->start_quiz_attempt($quiz->id, $student->id);
        $quba = question_engine::load_questions_usage_by_activity($attempt->uniqueid);

        // This bit strips out bits of quiz_attempt::process_submitted_actions()
        // Simulates data from the form.
        // TODO iterate over the questions.
        $formdata = array(
            'answer' => 'Sample essay answer text',
            'answerformat' => FORMAT_MOODLE
        );
        $slot = 1; // Only 1 question so far.
        $quba->process_action($slot, $formdata, time());
        $submissioncount++;

        question_engine::save_questions_usage_by_activity($quba);

        $this->end_quiz_attmept($attempt);

        return $submissioncount;
    }


    // Make some student submissions to those questions.
}
