<?php

require_login(0, false);

class quiz_functions extends module_base {

    function quiz_functions(&$reference) {
        $this->mainobject = $reference;
        // must be the same as th DB modulename
        $this->type = 'quiz';
        $this->capability = 'mod/quiz:grade';
        $this->levels = 4;
        $this->level2_return_function = 'quiz_questions';
        $this->level3_return_function = 'quiz_submissions';
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
        global $CFG;

        $sql = "SELECT qst.id as qstid, qsess.questionid, qz.id, qz.name, qz.course,
                       qa.userid, c.id as cmid
                  FROM {$CFG->prefix}quiz qz
            INNER JOIN {$CFG->prefix}course_modules c
                    ON qz.id = c.instance
            INNER JOIN {$CFG->prefix}quiz_attempts qa
                    ON qz.id = qa.quiz
            INNER JOIN {$CFG->prefix}question_sessions qsess
                    ON qsess.attemptid = qa.uniqueid
            INNER JOIN {$CFG->prefix}question_states qst
                    ON qsess.newest = qst.id
            INNER JOIN {$CFG->prefix}question q
                    ON qsess.questionid = q.id
                 WHERE qa.timefinish > 0
                   AND qa.preview = 0
                   AND c.module = {$this->mainobject->modulesettings['quiz']->id}
                   AND c.visible = 1
                   AND q.qtype = 'essay'
                   AND qz.course IN ({$this->mainobject->course_ids})
                   AND qst.event NOT IN (3,6,9)
              ORDER BY q.id";

            $this->all_submissions = get_records_sql($sql);
            return true;
    }


    function get_all_course_unmarked($courseid) {

        global $CFG;

         $sql = "SELECT qsess.id as qsessid, qzatt.userid, qz.id, qz.course,
                        qz.intro as description, qz.name,  c.id as cmid
                   FROM {$CFG->prefix}quiz qz
             INNER JOIN {$CFG->prefix}course_modules c
                     ON qz.id = c.instance
             INNER JOIN {$CFG->prefix}quiz_attempts qzatt
                     ON qz.id = qzatt.quiz
             INNER JOIN {$CFG->prefix}question_sessions qsess
                     ON qsess.attemptid = qzatt.uniqueid
             INNER JOIN {$CFG->prefix}question_states qst
                     ON qsess.newest = qst.id
             INNER JOIN {$CFG->prefix}question q
                     ON qsess.questionid = q.id
                  WHERE qzatt.userid IN ({$this->mainobject->student_ids->$courseid})
                    AND qzatt.timefinish > 0
                    AND qzatt.preview = 0
                    AND c.module = {$this->mainobject->modulesettings['quiz']->id}
                    AND c.visible = 1
                    AND qz.course = {$courseid}
                    AND q.qtype = 'essay'
                    AND qst.event NOT IN (3,6,9)
                  ORDER BY q.id";

            $submissions = get_records_sql($sql);
            return $submissions;
    }

    /**
     * Gets all of the question attempts for the current quiz. Uses the group filtering function to display groups first if
     * that has been specified via config. Seemed like abetter idea than questions then groups as tutors will mostly have a class to mark
     * rather than a question to mark.
     *
     * Uses $this->id as the quiz id
     * @global <type> $CFG
     * @return <type>
     */
    function quiz_questions() {

        $quiz = get_record('quiz', 'id', $this->mainobject->id);
        $courseid = $quiz->course;

        $this->mainobject->get_course_students($quiz->course);

        global $CFG, $USER;

        //permission to grade?
        //$module = get_record('modules','name',$this->type);
        $coursemodule = get_record('course_modules', 'course', $quiz->course, 'module', $this->mainobject->modulesettings['quiz']->id, 'instance', $quiz->id) ;
        $modulecontext = get_context_instance(CONTEXT_MODULE, $coursemodule->id);
        if (!has_capability('mod/quiz:grade', $modulecontext, $USER->id)) {
            return;
        }

        $csv_sql = "SELECT questions
                      FROM {$CFG->prefix}quiz
                     WHERE id = {$this->mainobject->id}";
        $csv_questions = get_record_sql($csv_sql);

        $sql = "SELECT qst.id as qstid, qst.event, qs.questionid as id, q.name, qa.userid,
                       q.questiontext as description, q.qtype, qa.userid, qa.timemodified
                  FROM {$CFG->prefix}question_states qst
            INNER JOIN {$CFG->prefix}question_sessions qs
                    ON qs.newest = qst.id
            INNER JOIN {$CFG->prefix}question q
                    ON qs.questionid = q.id
            INNER JOIN {$CFG->prefix}quiz_attempts qa
                    ON qs.attemptid = qa.uniqueid
                 WHERE qa.quiz = $quiz->id
                   AND qa.userid
                    IN ({$this->mainobject->student_ids->$courseid})
                   AND qa.timefinish > 0
                   AND qa.preview = 0
                   AND qs.questionid IN ($csv_questions->questions)
                   AND q.qtype = 'essay'
                   AND qst.event NOT IN (3,6,9)";

        $question_attempts = get_records_sql($sql);

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

        global $CFG, $USER;
        
        $quiz = get_record('quiz', 'id', $this->mainobject->quizid);
        $courseid = $quiz->course;

        //permission to grade?
        $coursemodule = get_record('course_modules', 'course', $quiz->course, 'module', $this->mainobject->modulesettings['quiz']->id, 'instance', $quiz->id) ;
        $modulecontext = get_context_instance(CONTEXT_MODULE, $coursemodule->id);
        if (!has_capability('mod/quiz:grade', $modulecontext, $USER->id)) {
            return;
        }

        $this->mainobject->get_course_students($quiz->course);

        $sql = "SELECT qst.id, qst.event, qs.questionid, qa.userid, qst.timestamp
                  FROM {$CFG->prefix}question_states qst
            INNER JOIN {$CFG->prefix}question_sessions qs
                    ON qs.newest = qst.id
            INNER JOIN {$CFG->prefix}quiz_attempts qa
                    ON qs.attemptid = qa.uniqueid
                 WHERE qa.quiz = {$this->mainobject->quizid}
                   AND qa.userid IN ({$this->mainobject->student_ids->$courseid})
                   AND qa.timefinish > 0
                   AND qa.preview = 0
                   AND qs.questionid = {$this->mainobject->id}
                   AND qst.event NOT IN (3,6,9)";

        $question_attempts = get_records_sql($sql);

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

                $now = time();
                $seconds = ($now - $question_attempt->timestamp);
                $summary = $this->mainobject->make_time_summary($seconds);

                $this->output .= $this->mainobject->make_submission_node($name, $question_attempt->userid,
                                                                         $this->mainobject->id,
                                                                         $summary, 'quiz_final', $seconds,
                                                                         $question_attempt->timestamp);

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

         global $CFG;

         $sql = "SELECT qz.id, qz.course, qz.intro as summary, qz.name, c.id as cmid
                   FROM {$CFG->prefix}quiz qz
             INNER JOIN {$CFG->prefix}course_modules c
                     ON qz.id = c.instance
             INNER JOIN {$CFG->prefix}quiz_question_instances qqi
                     ON qz.id = qqi.quiz
             INNER JOIN {$CFG->prefix}question q
                     ON qqi.question = q.id
                  WHERE c.module = {$this->mainobject->modulesettings['quiz']->id}
                    AND c.visible = 1
                    AND q.qtype = 'essay'
                    AND qz.course IN ({$this->mainobject->course_ids})
               ORDER BY qz.id";

        $quizzes = get_records_sql($sql);
        $this->assessments = $quizzes;

    }


    function make_html_link($item) {

        global $CFG;
        $address = $CFG->wwwroot.'/mod/quiz/report.php?q='.$item->id.'&mode=grading';
        return $address;
    }

}