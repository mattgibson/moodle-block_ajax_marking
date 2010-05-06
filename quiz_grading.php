<?php

require_login(0, false);

class quiz_functions extends module_base {

    function quiz_functions(&$reference) {
        $this->mainobject = $reference;
        // must be the same as the DB modulename
        $this->type = 'quiz';
        $this->capability = 'mod/quiz:grade';
        $this->levels = 4;
        $this->icon = 'mod/quiz/icon.gif';
        $this->functions  = array(
            'quiz' => 'quiz_questions',
            'quiz_question' => 'submissions'
        );
    }

     /**
      * gets all unmarked quiz question from all courses. used for the courses count
      *
      */
     function get_all_unmarked() {

        global $CFG, $DB;

        list($usql, $params) = $DB->get_in_or_equal($this->mainobject->courseids, SQL_PARAMS_NAMED);

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
                   AND qz.course $usql
                   AND qst.event NOT IN (3,6,9)
              ORDER BY qa.timemodified";
        $params['moduleid'] = $this->mainobject->modulesettings['quiz']->id;
        $this->all_submissions = $DB->get_records_sql($sql, $params);
        return true;
    }

    function get_all_course_unmarked($courseid) {

        global $CFG, $DB;

        list($usql, $params) = $DB->get_in_or_equal($this->mainobject->students->ids->$courseid, SQL_PARAMS_NAMED);

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
                 WHERE qa.userid $usql
                   AND qa.timefinish > 0
                   AND qa.preview = 0
                   AND c.module = :moduleid
                   AND c.visible = 1
                   AND qz.course = :courseid
                   AND q.qtype = 'essay'
                   AND qst.event NOT IN (3,6,9)
                 ORDER BY qa.timemodified";
        $params['moduleid'] = $this->mainobject->modulesettings['quiz']->id;
        $params['courseid'] = $courseid;
        $submissions = $DB->get_records_sql($sql, $params);
        return $submissions;
    }

    /**
     * Gets all of the question attempts for the current quiz. Uses the group
     * filtering function to display groups first if that has been specified via
     * config. Seemed like a better idea than questions then groups as tutors
     * will mostly have a class to mark rather than a question to mark.
     *
     * Uses $this->id as the quiz id
     * @global <type> $CFG
     * @return <type>
     */
    function quiz_questions() {

        $quiz = $DB->get_record('quiz', array('id' => $this->mainobject->id));
        $courseid = $quiz->course;

        $this->mainobject->get_course_students($quiz->course);

        global $CFG, $USER, $DB;

        //permission to grade?
        $moduleconditions = array(
                'course' => $quiz->course,
                'module' => $this->mainobject->modulesettings['quiz']->id,
                'instance' => $quiz->id
        );
        $coursemodule = $DB->get_record('course_modules', $moduleconditions);
        $modulecontext = get_context_instance(CONTEXT_MODULE, $coursemodule->id);

        if (!has_capability($this->capability, $modulecontext, $USER->id)) {
            return;
        }

        list($usql, $params) = $DB->get_in_or_equal($this->mainobject->students->ids->$courseid, SQL_PARAMS_NAMED);

        $csv_sql = "SELECT questions
                      FROM {quiz}
                     WHERE id = {$this->mainobject->id}";
        $csv_questions = explode(', ', $DB->get_record_sql($csv_sql));
        list($usql2, $params2) = $DB->get_in_or_equal($csv_questions, SQL_PARAMS_NAMED);

        $sql = "SELECT qst.id as qstid, qa.userid, qst.event, qs.questionid as id, q.name,
                       q.questiontext as description, q.qtype, qa.timemodified
                  FROM {question_states} qst
            INNER JOIN {question_sessions} qs
                    ON qs.newest = qst.id
            INNER JOIN {question} q
                    ON qs.questionid = q.id
            INNER JOIN {quiz_attempts} qa
                    ON qs.attemptid = qa.uniqueid
                 WHERE qa.quiz = :quizid
                   AND qa.userid $usql
                   AND qa.timefinish > 0
                   AND qa.preview = 0
                   AND qs.questionid $usql2
                   AND q.qtype = 'essay'
                   AND qst.event NOT IN (3,6,9)
              ORDER BY qa.timemodified";
        $params = array_merge($params, $params2);
        $params['quizid'] = $quiz->id;

        $question_attempts = $DB->get_records_sql($sql, $params);

        // not the same as $csv_questions as some of those questions will have no attempts
        // needing attention
        $questions = $this->mainobject->list_assessment_ids($question_attempts);

        if (!$this->mainobject->group) {
            $group_check = $this->mainobject->assessment_groups_filter($question_attempts, 'quiz', $this->mainobject->id, $quiz->course);
            if (!$group_check) {
                return;
            }
        }

        // begin json object.   Why course?? Children treatment?
        $this->mainobject->output = '[{"type":"quiz_question"}';

        foreach ($questions as $question) {

            $count = 0;

            foreach ($question_attempts as $question_attempt) {
                if (!isset($question_attempt->userid)) {continue;}
                // if we have come from a group node, ignore attempts where the user is not in the
                // right group. Also ignore attempts not relevant to this question
                $groupnode     = $this->mainobject->group;
                $inrightgroup  = $this->mainobject->check_group_membership($this->mainobject->group, $question_attempt->userid);
                $rightquestion = ($question_attempt->id == $question->id);
                if (($groupnode && !$inrightgroup) || ! $rightquestion) {
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
                    $shortsum .= "...";
                }
                $length = 30;
                $this->mainobject->output .= ',';

                $this->mainobject->output .= '{';
                $this->mainobject->output .= '"label":"'.$this->mainobject->add_icon('question');
                $this->mainobject->output .=     '(<span class=\"AMB_count\">'.$count.'</span>) ';
                $this->mainobject->output .=     $this->mainobject->clean_name_text($name, $length).'",';
                $this->mainobject->output .= '"name":"'.$this->mainobject->clean_name_text($name, $length).'",';
                $this->mainobject->output .= '"id":"'.$questionid.'",';
                $this->mainobject->output .= '"icon":"'.$this->mainobject->add_icon('question').'",';

                $this->mainobject->output .= $this->mainobject->group ? '"group":"'.$this->mainobject->group.'",' : '';

                $this->mainobject->output .= '"assid":"qq'.$questionid.'",';
                $this->mainobject->output .= '"type":"quiz_question",';
                $this->mainobject->output .= '"summary":"'.$this->mainobject->clean_summary_text($shortsum).'",';
                $this->mainobject->output .= '"count":"'.$count.'",';
                $this->mainobject->output .= '"uniqueid":"quiz_question'.$questionid.'",';
                $this->mainobject->output .= '"dynamic":"true"';
                $this->mainobject->output .= '}';
            }
        }
        // end JSON array
        $this->mainobject->output .= "]";
    }

    /**
     * Makes the nodes with the student names for each question. works either with or without a group having been set.
     * @global <type> $CFG
     * @return <type>
     */
    function submissions() {

        global $CFG, $USER, $DB;

        $quiz = $DB->get_record('quiz', array('id' => $this->mainobject->secondary_id));
        $courseid = $quiz->course;

        //permission to grade?
        $moduleconditions = array(
                'course' => $quiz->course,
                'module' => $this->mainobject->modulesettings['quiz']->id,
                'instance' => $quiz->id
        );
        $coursemodule = $DB->get_record('course_modules', $moduleconditions);
        $modulecontext = get_context_instance(CONTEXT_MODULE, $coursemodule->id);

        if (!has_capability($this->capability, $modulecontext, $USER->id)) {
            return;
        }

        $this->mainobject->get_course_students($quiz->course);
        list($usql, $params) = $DB->get_in_or_equal($this->mainobject->students->ids->$courseid, SQL_PARAMS_NAMED);

        $sql = "SELECT qst.id, COUNT(DISTINCT qst.id) as count, qa.userid, qst.event, qs.questionid, qst.timestamp
                  FROM {question_states} qst
            INNER JOIN {question_sessions} qs
                    ON qs.newest = qst.id
            INNER JOIN {quiz_attempts} qa
                    ON qs.attemptid = qa.uniqueid
                 WHERE qa.quiz = :quizid
                   AND qa.userid $usql
                   AND qa.timefinish > 0
                   AND qa.preview = 0
                   AND qs.questionid = :questionid
                   AND qst.event NOT IN (3,6,9)
              GROUP BY qa.userid, qs.questionid
              ORDER BY qa.timemodified";
        $params['quizid'] = $this->mainobject->secondary_id;
        $params['questionid'] = $this->mainobject->id;
        $question_attempts = $DB->get_records_sql($sql, $params);

        if($question_attempts) {

            $this->mainobject->output = '[{"type":"submissions"}';

            foreach ($question_attempts as $question_attempt) {
                if (!isset($question_attempt->userid)) {
                    continue;
                }
                // If this is a group node, ignore those where the student is not in the right group
                $groupnode = $this->mainobject->group &&
                $inrightgroup = $this->mainobject->check_group_membership($this->mainobject->group, $question_attempt->userid);
                if ($groupnode && !$inrightgroup) {
                     continue;
                }

                $name = $this->mainobject->get_fullname($question_attempt->userid);
                // Sometimes, a person will have more than 1 attempt for the question.
                // No need to list them twice, so we add a count after their name.
                if ($question_attempt->count > 1) {
                    $name .=' ('.$question_attempt->count.')';
                }

                $now = time();
                $seconds = ($now - $question_attempt->timestamp);
                $summary = $this->mainobject->make_time_summary($seconds);

                $this->output .= $this->mainobject->make_submission_node($name,
                                                                         $question_attempt->userid,
                                                                         $this->mainobject->id,
                                                                         $summary,
                                                                         'quiz_final',
                                                                         $seconds,
                                                                         $question_attempt->timestamp,
                                                                         $question_attempt->count);

            }
            $this->mainobject->output .= "]";
        }
    }

     /**
     * gets all the quizzes for the config screen. still need the check in there for essay questions.
     * @global <type> $CFG
     * @return <type>
     */
     function get_all_gradable_items() {

        global $CFG, $DB;

        list($usql, $params) = $DB->get_in_or_equal($this->mainobject->courseids, SQL_PARAMS_NAMED);

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
        $params['moduleid'] = $this->mainobject->modulesettings['quiz']->id;
        $quizzes = $DB->get_records_sql($sql, $params);
        $this->assessments = $quizzes;

    }

    /**
     * Makes a HTML link for the pop up to allow grading of a question
     *
     * @param object $item containing the quiz id as ->id
     */
    function make_html_link($item) {

        global $CFG;
        $address = $CFG->wwwroot.'/mod/quiz/report.php?q='.$item->id.'&mode=grading';
        return $address;
    }

}