<?php


/**
 * All of the main functions for the AJAX marking block are contained within this class. Its is instantiated as
 * an object
 * each time a request is made, then automatically runs and outputs based on the post data provided.
 * Output is in JSON format, ready to be parsed into object form by eval() in the javascript callback object.
 *
 * Each module provides grading code in the form of a file called modname_grading.php which is checked for
 * and included if present. For each installed module that is gradable, an object is created from the class
 * definition in the above file, which is stored within the main object as $this->modname. The main code
 * then runs through the process of generating the nodes for output, getting the data from these module
 * objects as it goes.
 *
 * The module objects make use of some shared functions and shared data, so the main object is passed in
 * by reference to each one.
 *
 *
 */
class ajax_marking_functions {

    /**
     * get the variables that were sent as part of the ajax call. Not needed for the UL list
     */
    function get_variables() {
        $this->type              = required_param('type',                   PARAM_TEXT); // refers to the part being built
        $this->id                = optional_param('id',               NULL, PARAM_INT);
        $this->userid            = optional_param('userid',           NULL, PARAM_INT);
        $this->quizid            = optional_param('quizid',           NULL, PARAM_INT);
        $this->groups            = optional_param('groups',           NULL, PARAM_TEXT);
        $this->assessmenttype    = optional_param('assessmenttype',   NULL, PARAM_TEXT);
        $this->assessmentid      = optional_param('assessmentid',     NULL, PARAM_INT);
        $this->showhide          = optional_param('showhide',         NULL, PARAM_INT);
        $this->group             = optional_param('group',            NULL, PARAM_TEXT);
        $this->courseid          = optional_param('courseid',         NULL, PARAM_TEXT);


    }
    /**
     * Setup of inital variables
     */
    function initial_setup($html=NULL) {

        $this->output            = '';
        $this->config            = NULL;
        $this->student_ids       = '';
        $this->student_array     = '';
        $this->student_details   = array();
        global $USER, $CFG;


        // call expensive queries only when needed
        $get_my_courses_types = array('quiz_diagnostic', 'main', 'config_course', 'config_main', 'course', 'assignment', 'workshop', 'forum', 'quiz_question', 'quiz', 'journal_submissions');
        if (in_array($this->type, $get_my_courses_types)) {
            $this->courses = get_my_courses($USER->id, $sort='fullname', $fields='id', $doanything=false, $limit=0) or die('get my courses error');

            if ($this->courses) {
                $this->make_course_ids_list();
                $this->get_teachers();
            }
        }
        $get_my_groups_types = array('main', 'course', 'assignment', 'workshop', 'forum', 'quiz_question', 'quiz', 'journal_submissions');
        if (in_array($this->type, $get_my_groups_types)) {
            $this->group_members = $this->get_my_groups();
        }

        // Ready for dynamic retrieval of modules in future, rather than a hard coded list
        // TODO - use installed modules, if file exists and foreach to get a list of those that have implemented marking functions.
        
        $this->modules = array('assignment', 'forum', 'workshop', 'quiz', 'journal');
        $this->get_coursemodule_ids();

        // instantiate function classes for each of the available modules and store them in the modules object
        foreach ($this->modules as $modname) {
            
            include("{$CFG->dirroot}/blocks/ajax_marking/{$modname}_grading.php");
            $classname = $modname.'_functions';
            $this->$modname = new $classname($this);

        }

        // get all configuration options set by this user
        $sql                     = "SELECT * FROM {$CFG->prefix}block_ajax_marking WHERE userid = $USER->id";
        $this->groupconfig       = get_records_sql($sql);
    }

    /**
     * Function to generate the list of courses when the tree is first prepared. Currently either makes a config tree or a main tree
     * depending on $this->type
     * @global <type> $CFG
     */
    function courses() {
     
        $course_ids = NULL;
        global $CFG;

        // admins will have a problem as they will see all the courses on the entire site
        // TODO - this has big issues around language. role names will not be the same in diffferent translations.

        // begin JSON array
        $this->output = '[{"type":"main"}';

        // get all unmarked submissions for all courses, so they can be sorted out later
        //$this->get_main_level_data();
       
        // iterate through each course, checking permisions, counting relevant assignment submissions and
        // adding the course to the JSON output if any appear
        foreach ($this->courses as $course) {

            // show nothing if the course is hidden
            if (!$course->visible == 1)  {
                continue;
            }

            // set course assessments counter to 0
            $count = 0;

            $courseid = $course->id;
            // we must make sure we only get work from enrolled students
            $this->get_course_students($courseid);

            // If there are no students, there's no point counting
            if (!$this->student_ids->$courseid) {
                continue;
            }
            // loop through each module, getting a count for this course id from each one.
            foreach ($this->modules as $module) {
                $count += $this->$module->count_course_submissions($courseid);
            }

            // TO DO: need to check in future for who has been assigned to mark them (new groups stuff) in 1.9
            //$coursecontext = get_context_instance(CONTEXT_COURSE, $course->id);
/*
            if ($this->assignment->submissions) {
                $count = $count + $this->assignment->count_course_submissions($course->id);
                //$count = $count + $this->modules->assignment->functions->count_course_submissions($course->id);
               // $count = $count + $this->count_course_submissions($this->modules->assignment->submissions, 'assignment', $course->id);
            }

            if ($this->workshop->submissions) {
                $count = $count + $this->count_course_submissions($this->workshop->submissions, 'workshop', $course->id);
            }

            if ($this->forum->submissions) {
                $count = $count + $this->forum->count_course_submissions($course->id);
                //$count = $count + $this->count_course_submissions($this->modules->forum->submissions, 'forum', $course->id);
            }

            if ($this->quiz->submissions) {
                $count = $count + $this->count_course_submissions($this->quiz->submissions, 'quiz', $course->id);
            }

           // if ($this->journal->submissions) {
           //     $count = $count + $this->count_course_submissions($this->journal->submissions, 'journal', $course->id);
           // }
            if (array_key_exists("journal", $this->module_ids)) {
                $this->journal->course_count($course->id);
                //submissions = $this->get_all_unmarked_journals() ;
            }
*/
            if ($count > 0 || $this->config) { // there are some assessments	, or its a config tree, so we include the course always.

                // now add course to JSON array of objects
                $cid  = $course->id;

                $this->output .= ','; // add a comma if there was a preceding course
                $this->output .= '{';

                $this->output .= '"id":"'.$cid.'",';
                $this->output .= '"type":"course",';
                $this->output .= '"label":"'.$this->add_icon('course').$this->clean_name_text($course->shortname, 0);
                $this->output .= ($this->config) ? '",' : ' ('.$count.')",';
                // name is there to allow labels to be reconstructed with a new count after marked nodes are removed
                $this->output .= '"name":"'.$this->add_icon('course').$this->clean_name_text($course->shortname, 0).'",';
                $this->output .= '"title":"'.$this->clean_name_text($course->shortname, -2).'",';
                $this->output .= '"summary":"'.$this->clean_name_text($course->shortname, -2).'",';
                $this->output .= '"count":"'.$count.'",';
                $this->output .= '"dynamic":"true",';
                $this->output .= '"cid":"c'.$cid.'"';
                $this->output .= '}';
                
            } // end if there are some assessments
        } // end for each courses
        $this->output .= "]"; //end JSON array
    } // end function


    /**
     * This function will check through all of the assessments of a particular type for a particular course
     * and return the nodes for a course of the main tree
     * @global <type> $CFG
     * @global <type> $SESSION
     * @param <type> $sql
     * @param <string> $type The type of assessment we are dealing with e.g. 'assignment'. Never plurals
     */
    function course_assessment_nodes($sql, $type) {

            global $CFG, $SESSION;

            // the assignment pop up thinks it was called from the table of assignment submissions, so to avoid javascript errors,
            // we need to set the $SESSION variable to think that all the columns in the table are collapsed so no javascript is generated
            // to try to update them.

            $assessment_submissions = get_records_sql($sql, $type);

            if ($assessment_submissions) {

                // we need all the assignment ids for the loop, so we make an array of them
                $assessments = $this->list_assessment_ids($assessment_submissions);

                // each type has a different capability to check for. Might be better to feed this in as a parameter
                // as it will make for a more felxible approach in future
                switch ($type) {

                    case 'assignment':
                        $cap = 'mod/assignment:grade';
                        break;

                    case 'forum':
                        $cap = 'mod/forum:viewhiddentimedposts';
                        break;

                    case 'workshop':
                        $cap = 'mod/workshop:manage';
                        break;

                    case 'quiz':
                        $cap = 'mod/quiz:grade';
                        break;

                    case 'journal':
                        $cap = 'mod/assignment:grade';
                        break;
                }

                foreach ($assessments as $assessment) {

                    // counter for number of unmarked submissions
                    $count = 0;

                    // permission to grade?
                    $modulecontext = get_context_instance(CONTEXT_MODULE, $assessment->cmid);
                    if (!has_capability($cap, $modulecontext, $this->userid)) {continue;}

                    if(!$this->config) { //we are making the main block tree, not the configuration tree

                        // has this assignment been set to invisible in the config settings?
                        $check = $this->get_groups_settings($type, $assessment->id);
                        if ($check) {
                            if ($check->showhide == 3) {
                                continue;
                            }

                        }

                        // If the submission is for this assignment and group settings are 'display all', or 'display by groups' and
                        // the user is a group member of one of them, count it.
                        foreach($assessment_submissions as $assessment_submission) {
                            if ($assessment_submission->id == $assessment->id) {
                                if (!isset($assessment_submission->userid)) {
                                    continue;
                                }
                                if ((!$check) || ($check && ($check->showhide == 2) && $this->check_group_membership($check->groups, $assessment_submission->userid)) || ($check->showhide == 1)) {
                                   // if ($type == 'forum') {print_r($assessment_submission);}
                                    $count++;
                                }
                            }
                        }

                        // if there are no unmarked assignments, just skip this one. Important not to skip
                        // it in the SQL as config tree needs all assignments
                        if ($count == 0) {
                            continue;
                        }
                    }

                    // journals are a special case as they do not have submissions nodes normally.
                    // They may have group nodes however.
                    if ($this->type == 'journal') {

                        // set the type to reflect whether or not there are group nodes to come.
                        $journal_type = '';
                        if ($check && $check->showhide == 2) {
                            $journal_type = "journal_groups";
                        } else {
                            $journal_type = "journal";
                        }
                        $this->make_submission_node($assessment->name, $assessment->id, $assessment->cmid, $assessment->description, $journal_type, $assessment->timemodified, $count);
                    } else {
                        // all other assessments
                        $this->make_assessment_node($assessment->name, $assessment->id, $assessment->cmid, $assessment->description, $type, $count);
                    }

                }// end foreach assignment
            } // end if assignment_submissions
	} // end course assessments
	////////////////////////////////////////////////////
	//  procedure for assignments
	////////////////////////////////////////////////////


	#need to make a dummy fletable to prevent generation of javascript designed to update the parent page from the assignment pop up.
	# $dummytable = new flexible_table('mod-assignment-submissions');
	# $dummytable->collapse['submissioncomment'] = 1;

	/**
         * This function will produce a node for every assignment with unmarked submissions. The logic is complicated by the groups requiremnt,
         * so a list of all unmarked submissions is retrieved, the for each unique assignment id in the list, each submission
         * is checked for whether it relates to this assignment and whether the groups settings allow it to be displayed for
         * this user.
         *
         * If any match, the node is built, along with the count.
         * @global <type> $CFG
         * @global <type> $SESSION
         */
	/*
	function assignments() {

            global $CFG, $SESSION;

            // the assignment pop up thinks it was called from the table of assignment submissions, so to avoid javascript errors,
            // we need to set the $SESSION variable to think that all the columns in the table are collapsed so no javascript is generated
            // to try to update them.

            $sql = "SELECT s.id as subid, s.userid, a.id, a.name, a.description, c.id as cmid  FROM
                        {$CFG->prefix}assignment a
                    INNER JOIN {$CFG->prefix}course_modules c
                         ON a.id = c.instance
                    LEFT JOIN {$CFG->prefix}assignment_submissions s
                         ON s.assignment = a.id
                    WHERE c.module = {$this->module_ids['assignment']->id}
                    AND c.visible = 1
                    AND a.course = $this->id
                    AND s.timemarked < s.timemodified
                    AND NOT (a.resubmit = 0 AND s.timemarked > 0)
                    AND s.userid IN($this->student_ids)
                    ORDER BY a.id
                  ";

            $assignment_submissions = get_records_sql($sql);

            if ($assignment_submissions) {

                // we need all the assignment ids for the loop, so we make an array of them
                $assignments = $this->list_assessment_ids($assignment_submissions);

                foreach ($assignments as $assignment) {

                    // counter for number of unmarked submissions
                    $count = 0;

                    // permission to grade?
                    $modulecontext = get_context_instance(CONTEXT_MODULE, $assignment->cmid);
                    if (!has_capability('mod/assignment:grade', $modulecontext, $this->userid)) {continue;}

                    if(!$this->config) { //we are making the main block tree, not the configuration tree

                        // has this assignment been set to invisible in the config settings?
                        $check = $this->get_groups_settings('assignment', $assignment->id);
                        if ($check) {

                            if ($check->showhide == 3) {
                                continue;
                            }

                        }

                        // If the submission is for this assignment and group settings are 'display all', or 'display by groups' and
                        // the user is a group member of one of them, count it.
                        foreach($assignment_submissions as $assignment_submission) {
                            if ($assignment_submission->id == $assignment->id) {
                                if (!isset($assignment_submission->userid)) {
                                    continue;
                                }
                                if ((!$check) || ($check && ($check->showhide == 2) && $this->check_group_membership($check->groups, $assignment_submission->userid)) || ($check->showhide == 1)) {
                                    $count++;
                                }
                            }
                        }

                        // if there are no unmarked assignments, just skip this one. Important not to skip
                        // it in the SQL as config tree needs all assignments
                        if ($count == 0) {
                            continue;
                        }
                    }


                    $this->make_assessment_node($assignment->name, $assignment->id, $assignment->cmid, $assignment->description, 'assignment', $count);

                    /*
                    //  add the asssignment to JSON array of objects, ready for display
                    $aid = $assignment->id;
                    $sum = $assignment->description;                                 // make summary
                    $sumlength = strlen($sum);                                       // how long it it?
                    $shortsum = substr($sum, 0, 100);                                // cut it at 100 characters
                    if (strlen($shortsum) < strlen($sum)) {$shortsum .= "...";}      // if that cut the end off, add an ellipsis

                    $this->output .= ','; // add a comma before section only if there was a preceding assignment

                    $this->output .= '{';
                    $this->output .= '"name":"'.$this->clean_name_text($assignment->name, 1).'",';
                    $this->output .= '"id":"'.$aid.'",';
                    $this->output .= '"assid":"a'.$aid.'",';
                    $this->output .= '"cmid":"'.$assignment->cmid.'",';
                    $this->output .= '"type":"assignment",';
                    $this->output .= '"summary":"'.$this->clean_summary_text($shortsum).'",';
                    $this->output .= '"count":"'.$count.'"';
                    $this->output .= '}';

                }// end foreach assignment
            } // end if assignment_submissions
	} // end asssignments function


*/
	


	//////////////////////////////////////////////////////
	// Procedure for workshops
	//////////////////////////////////////////////////////

	function workshops() {
	    global $CFG;
            $courseid = $this->id;
            $sql = "SELECT s.id as submissionid, s.userid, w.id, w.name, w.course, w.description, c.id as cmid
                    FROM
                       ( {$CFG->prefix}workshop w
                    INNER JOIN {$CFG->prefix}course_modules c
                         ON w.id = c.instance)
                    LEFT JOIN {$CFG->prefix}workshop_submissions s
                         ON s.workshopid = w.id
                    LEFT JOIN {$CFG->prefix}workshop_assessments a
                    ON (s.id = a.submissionid)
                    WHERE (a.userid != {$this->userid}
                      OR (a.userid = {$this->userid}
                            AND a.grade = -1))
                    AND c.module = {$this->module_ids['workshop']->id}
                    AND c.visible = 1
                    AND w.course = $this->id
                    AND s.userid IN ({$this->student_ids->$courseid})
                    ORDER BY w.id
                  ";

            $workshop_submissions = get_records_sql($sql);


            if ($workshop_submissions) {

                $workshops = $this->list_assessment_ids($workshop_submissions);

                foreach ($workshops as $workshop) {

                    $count = 0;

                    $modulecontext = get_context_instance(CONTEXT_MODULE, $workshop->cmid);
                    if (!has_capability('mod/workshop:manage', $modulecontext, $this->userid)) {continue;}

                    if(!$this->config) { //we are making the main block tree, not the configuration tree

                        // has this workshop been set to invisible in the config settings?
                        $check = $this->get_groups_settings('workshop', $workshop->id);
                        if ($check) {
                            if ($check->showhide == 3) {continue;}
                        }

                        foreach($workshop_submissions as $workshop_submission) {
                            if (!isset($workshop_submission->userid)) {continue;}
                            if ($workshop_submission->id == $workshop->id) {
                                if (!$check || ($check->showhide == 2 && $this->check_group_membership($check->groups, $workshop_submission->userid)) || $check->showhide == 1) {
                                    $count++;
                                }
                            }
                        }
                        if ($count == 0) {continue;}
                    }

                    $this->make_assessment_node($workshop->name, $workshop->id, $workshop->cmid, $workshop->description, 'workshop', $count);

                    /*
                    $wid = $workshop->id;
                    $sum = $workshop->description;
                    $sumlength = strlen($sum);
                    $shortsum = substr($sum, 0, 100);
                    if (strlen($shortsum) < strlen($sum)) {$shortsum .= "...";}
                    $this->output .= ','; // add a comma before section only if there was a preceding assignment

                    $this->output .= '{';
                    $this->output .= '"name":"'.$this->clean_name_text($workshop->name, 1).'",';
                    $this->output .= '"id":"'.$wid.'",';
                    $this->output .= '"assid":"w'.$wid.'",';
                    $this->output .= '"cmid":"'.$workshop->cmid.'",';
                    $this->output .= '"type":"workshop",';
                    $this->output .= '"summary":"'.$this->clean_summary_text($shortsum).'",';
                    $this->output .= '"count":"'.$count.'"';
                    $this->output .= '}';
                   */
                } // end foreach workshop
            } //end if workshop_submissions
        }// end function workshops






	function workshop_submissions() {

	    $workshop = get_record('workshop', 'id', $this->id);
            $courseid = $workshop->course;
            //$rec2 = get_record('course_modules', 'module', '17', 'instance', $this->id) or die ("get record module error");

            $this->get_course_students($workshop->course);
            global $CFG;

            $now = time();
            // fetch workshop submissions for this workshop where there is no corresponding record of a teacher assessment
            $sql = "
                    SELECT s.id, s.userid, s.title, s.timecreated, s.workshopid
                    FROM {$CFG->prefix}workshop_submissions s
                    LEFT JOIN {$CFG->prefix}workshop_assessments a
                            ON (s.id = a.submissionid)
                    INNER JOIN {$CFG->prefix}workshop w
                            ON s.workshopid = w.id
                    WHERE (a.userid != {$this->userid}
                           OR (a.userid = {$this->userid}
                               AND a.grade = -1))
                    AND s.workshopid = {$this->id}
                    AND s.userid IN ({$this->student_ids->$courseid})
                    AND w.assessmentstart < {$now}
                    ORDER BY s.timecreated ASC";

            $submissions = get_records_sql($sql);

            if ($submissions) {

                    // if this is set to display by group, we divert the data to the groups() function
                    // $sql = "SELECT id, groups FROM {$CFG->prefix}block_ajax_marking WHERE combinedref = 'workshop$workshop->id' AND userid = $this->userid AND showhide = 2";
               // $combinedref = "workshop".$workshop->id;
               if(!$this->group) {
                   $group_filter = $this->assessment_groups_filter($submissions, "workshop", $workshop->id);
                   if (!$group_filter) {return;}
               }
                // otherwise, submissionids have come back as its display all.

                // begin json object
                $this->output = '[{"type":"submissions"}';

                foreach ($submissions as $submission) {

                    if (!isset($submission->userid)) {continue;}
                        if ($this->group && !$this->check_group_membership($this->group, $submission->userid)) {continue;}

                        $name = $this->get_fullname($submission->userid);

                        $sid = $submission->id;

                        // sort out the time stuff
                        $now = time();
                        $seconds = ($now - $submission->timecreated);
                        $summary = $this->make_time_summary($seconds);
                        $this->output .= $this->make_submission_node($name, $sid, $this->id, $summary, 'workshop_answer', $seconds, $submission->timecreated);

                       /*
                        $this->output .= ','; // add a comma if there was a preceding submission

                        $this->output .= '{';
                        $this->output .= '"name":"'.$this->clean_name_text($name, 2).'",';
                        $this->output .= '"sid":"'.$sid.'",'; // id of submission for hyperlink - in this case it is the submission id, not the user id
                        $this->output .= '"aid":"'.$this->id.'",'; // id of workshop for hyperlink
                        $this->output .= '"seconds":"'.$seconds.'",'; // seconds sent to allow style to change according to how long it has been
                        $this->output .= '"summary":"'.$this->clean_summary_text($summary).'",';
                        $this->output .= '"type":"workshop_answer",';
                        $this->output .= '"time":"'.$submission->timecreated.'",'; // send the time of submission for tooltip
                        $this->output .= '"count":"1"';
                        $this->output .= '}';
                        * */

                }
                $this->output .= "]"; // end JSON array
            }
	}

	/**
	 * function for adding forums with unrated posts
	 */

	/*
	function forums() {

	global $CFG;
	//$this->get_course_students($this->id); // get a list of students in this course


            $sql = "SELECT p.id as post_id, p.userid, d.firstpost, f.type, f.id, f.name, f.intro as description, c.id as cmid
                        FROM
                            {$CFG->prefix}forum f
                        INNER JOIN {$CFG->prefix}course_modules c
                             ON f.id = c.instance
                        INNER JOIN {$CFG->prefix}forum_discussions d
                             ON d.forum = f.id
                        INNER JOIN {$CFG->prefix}forum_posts p
                             ON p.discussion = d.id
                        LEFT JOIN {$CFG->prefix}forum_ratings r
                             ON  p.id = r.post
                        WHERE p.userid <> $this->userid
                            AND p.userid IN ($this->student_ids)
                            AND (r.userid <> $this->userid OR r.userid IS NULL)
                            AND ((f.type <> 'eachuser') OR (f.type = 'eachuser' AND p.id = d.firstpost))
                            AND c.module = {$this->module_ids['forum']->id}
                            AND c.visible = 1
                            AND f.course = $this->id
                            AND f.assessed > 0
                        ORDER BY f.id
                  ";

		$forum_posts = get_records_sql($sql);

		if ($forum_posts) {

                    $forums = $this->list_assessment_ids($forum_posts);

			foreach ($forums as $forum) {

                            // counter for number of unmarked submissions
                            $count = 0;

                            // permission to grade?
                            $modulecontext = get_context_instance(CONTEXT_MODULE, $forum->cmid);
                            if (!has_capability('mod/forum:viewhiddentimedposts', $modulecontext, $this->userid)) {continue;}

                            if(!$this->config) { //we are making the main block tree, not the configuration tree

                                // has this assignment been set to invisible in the config settings?

                                $check = $this->get_groups_settings('forum', $forum->id);
                                if ($check) {
                                    if ($check->showhide == 3) {
                                        continue;
                                    }
                                }

                                foreach($forum_posts as $forum_post) {
                                    if (!isset($forum_post->userid)) {
                                        continue;
                                    }

                                    if ($forum_post->id == $forum->id) {
                                        if (!$check || ($check && $check->showhide == 2 && $this->check_group_membership($check->groups, $forum_post->userid)) || ($check && $check->showhide == 1)) {
                                            $count++;
                                        }
                                    }
                                }
                                if ($count == 0) {
                                    continue;
                                }
                            }

                            $this->make_assessment_node($forum->name, $forum->id, $forum->cmid, $forum->description, 'forum', $count);

                        /*
                            // add the node if there were any posts or if there was a config call
                            //if ($count > 0 || $this->config) {
                            $fid = $forum->id;
                            $sum = $forum->description;
                            $sumlength = strlen($sum);
                            $shortsum = substr($sum, 0, 100);
                            if (strlen($shortsum) < strlen($sum)) {$shortsum .= "...";}
                            $this->output .= ','; // add a comma before section only if there was a preceding assignment

                            $this->output .= '{';
                            $this->output .= '"name":"'.$this->clean_name_text($forum->name, 1).'",';
                            $this->output .= '"id":"'.$fid.'",';
                            $this->output .= '"assid":"f'.$fid.'",';
                            $this->output .= '"cmid":"'.$forum->cmid.'",';
                            $this->output .= '"type":"forum",';
                            $this->output .= '"summary":"'.$this->clean_summary_text($shortsum).'",';
                            $this->output .= '"count":"'.$count.'"';
                            $this->output .= '}';

			} // foreach forum
		} // if forums
	} // end function
	*/
	/**
	 * function to make nodes for forum submissions
	 */
/*
	function forum_submissions() {
	    global $CFG;
            $discussions = '';
            $forum = get_record('forum', 'id', $this->id);
            $courseid = $forum->course;
            $this->get_course_students($forum->course);

            $discussions = get_records('forum_discussions', 'forum', $this->id);
            if (!$discussions) {
                return;
            }
            $sql = "SELECT p.id, p.userid, p.created, p.message, d.id as discussionid
                    FROM {$CFG->prefix}forum_discussions d ";

            if ($forum->type == 'eachuser') {
                // add a bit to link to forum so we can check the type is correct
                $sql .= "INNER JOIN {$CFG->prefix}forum f ON d.forum = f.id "  ;
            }

            $sql .= "INNER JOIN {$CFG->prefix}forum_posts p
                         ON p.discussion = d.id
                     LEFT JOIN {$CFG->prefix}forum_ratings r
                         ON  p.id = r.post
                     WHERE d.forum = $this->id
                     AND p.userid <> $this->userid
                     AND p.userid IN ({$this->student_ids->$courseid})
                     AND (((r.userid <> $this->userid)  AND (r.userid NOT IN ($this->teachers))) OR r.userid IS NULL)
                     ";

            if ($forum->type == 'eachuser') {
                // make sure that it is just the first posts that we get
                $sql .= " AND (f.type = 'eachuser' AND p.id = d.firstpost)";
            }

            $posts = get_records_sql($sql);

            if ($posts) {
                foreach ($posts as $key=>$post) {

                  // sort for obvious exclusions
                  if (!isset($post->userid)) {
                       unset($posts[$key]);
                       continue;
                   }
                   // Maybe this forum doesn't rate posts earlier than X time, so we check.
                   if ($forum->assesstimestart != 0) {

                        if (!($post->created > $forum->assesstimestart))  {
                            unset($posts[$key]);
                            continue;
                        }
                    }
                    // Same for later cut-off time
                    if ($forum->assesstimefinish != 0) {
                        if (!($post->created < $forum->assesstimefinish)) { // it also has a later limit, so check that too.
                            unset($posts[$key]);
                            continue;
                         }
                    }
                }

                // Check to see if group nodes need to be made instead of submissions
                //print_r($posts);
                if(!$this->group) {
                       $group_filter = $this->assessment_groups_filter($posts, "forum", $forum->id);
                       if (!$group_filter) {return;}
                }

                // Submissions nodes are needed, so make one per discussion
                $this->output = '[{"type":"submissions"}';      // begin json object.

                foreach ($discussions as $discussion) {

                    $firstpost = '';
                    if ($this->group && !groups_is_member($this->group, $discussion->userid)) {
                        continue;
                    }

                    $count = 0;
                    $sid = 0; // this variable will hold the id of the first post which is unrated, so it can be used
                                              // in the link to load the pop up with the discussion page at that position.
                    $time = time(); // start seconds at current time so we can compare with time created to find the oldest as we cycle through

                    // if this forum is set to 'each student posts one discussion', we want to only grade the first one, which is the only one returned.
                    if ($forum->type == 'eachuser') {
                         $count = 1;
                    } else {

                        // any other type of graded forum, we can grade any posts that are not yet graded
                        // this means counting them first.

                        $time = time(); // start seconds at current time so we can compare with time created to find the oldest as we cycle through

                        $firsttime = '';
                        foreach ($posts as $key=>$post) {

                            if ($discussion->id == $post->discussionid) {
                                //post is relevant
                                $count++;

                                // link needs the id of the earliest post, so store time if this is the first post; check and modify for subsequent ones
                                if ($firstpost) {
                                    if ($post->created > $firstpost) {
                                        $firstpost = $post;
                                    }
                                } else {
                                    $firstpost = $post;
                                }
                                // store the time created for the tooltip if its the oldest post yet for this discussion
                                if ($firsttime) {
                                    if ($post->created < $time) {
                                        $time = $post->created;
                                    }
                                } else {
                                    $firsttime = $post->created;
                                }
                            }
                        } // end foreach posts
                    } // end any other graded forum

                    // add the node if there were any posts -  the node is the discussion with a count of the number of unrated posts
                    if ($count > 0) {

                        // make all the variables ready to put them together into the array
                        $seconds = time() - $discussion->timemodified;

                        if ($forum->type == 'eachuser') { // we will show the student name as the node name as there is only one post that matters
                            $name = $this->get_fullname($firstpost->userid);

                        } else { // the name will be the name of the discussion
                                $name = $discussion->name;

                        }

                        $sum = strip_tags($firstpost->message);

                        $shortsum = substr($sum, 0, 100);
                        if (strlen($shortsum) < strlen($sum)) {$shortsum .= "...";}
                        $timesum = $this->make_time_summary($seconds, true);
                        if (!isset($discuss)) {
                            $discuss = get_string('discussion', 'block_ajax_marking');
                        }
                        $summary = $discuss.": ".$shortsum."<br />".$timesum;

                        $this->output .= $this->make_submission_node($name, $firstpost->id, $discussion->id, $summary, 'discussion', $seconds, $time, $count);

                    }
                }// end foreach discussion
                $this->output .= "]"; // end JSON array
            }// if discussions
	} // end function
*/
	function quizzes() {

            $courseid = $this->id;
            global $CFG;

            // this is only needed for the constants. might just avoid it and redefine them here...
            //require_once ("{$CFG->dirroot}/mod/quiz/locallib.php");


            $sql2 = "
                  SELECT
                       qst.id as questionstateid, qst.question as questionstatequestionid, qsess.questionid as questsessquestionid, qsess.id as questionsessionid, qsess.newest as questsessnewest, qsess.attemptid as questsessattemptid, q.id as questionid, q.type as questiontype qa.uniqueid as quizattemptid, qz.*, c.id as cmid
                  FROM


                     {$CFG->prefix}quiz qz
                  INNER JOIN {$CFG->prefix}course_modules c
                     ON qz.id = c.instance
                  INNER JOIN
                    {$CFG->prefix}quiz_attempts qa
                      ON
                        qz.id = qa.quiz
                  INNER JOIN
                    {$CFG->prefix}question_sessions qsess
                      ON
                        qsess.attemptid = qa.uniqueid
                  INNER JOIN {$CFG->prefix}question q
                     ON
                        qsess.questionid = q.id
                  INNER JOIN
                    {$CFG->prefix}question_states qst
                     ON
                        qsess.newest = qst.id



                  WHERE
                    qa.userid
                      IN ({$this->student_ids->$courseid})
                  AND qa.timefinish > 0
                  AND qa.preview = 0
                  AND c.module = {$this->module_ids['quiz']->id}
                  AND c.visible = 1
                  AND qz.course = {$this->id}
                  AND q.qtype = 'essay'
                  AND qst.event NOT IN (3,6,9)";
            /*
                        ".QUESTION_EVENTGRADE.",
                        ".QUESTION_EVENTCLOSEANDGRADE.",
                        ".QUESTION_EVENTMANUALGRADE."
                      )
                  ORDER BY q.id
                ";
              */
            $sql = "
                  SELECT
                      qsess.id as qsessid, qzatt.userid, qz.id, qz.intro as description, qz.name,  c.id as cmid
                  FROM
                    {$CFG->prefix}quiz qz
                  INNER JOIN {$CFG->prefix}course_modules c
                     ON qz.id = c.instance
                  INNER JOIN
                    {$CFG->prefix}quiz_attempts qzatt
                      ON
                        qz.id = qzatt.quiz
                  INNER JOIN
                    {$CFG->prefix}question_sessions qsess
                      ON
                        qsess.attemptid = qzatt.uniqueid
                  INNER JOIN
                    {$CFG->prefix}question_states qst
                     ON
                        qsess.newest = qst.id
                  INNER JOIN {$CFG->prefix}question q
                     ON
                        qsess.questionid = q.id
                  WHERE
                      qzatt.userid IN ({$this->student_ids->$courseid})
                  AND qzatt.timefinish > 0
                  AND qzatt.preview = 0
                  AND c.module = {$this->module_ids['quiz']->id}
                  AND c.visible = 1
                  AND qz.course = {$this->id}
                  AND q.qtype = 'essay'
                  AND qst.event NOT IN (3,6,9)
                  ORDER BY q.id";
            /*
                        ".QUESTION_EVENTGRADE.",
                        ".QUESTION_EVENTCLOSEANDGRADE.",
                        ".QUESTION_EVENTMANUALGRADE."
                      )
                  ORDER BY q.id
            ";
           */
            //echo $sql;
            $quiz_submissions = get_records_sql($sql);

            //echo count($quiz_submissions)." ";

          /*  if ($this->type == 'quiz_diagnostic') {
                echo $sql;
                echo '<br /><br />';
                print_r($quiz_submissions);
                $quiz_submissions2 = get_records_sql($sql2);
                echo '<br /><br />';
                echo $sql2;
                echo '<br /><br />';
                print_r($quiz_submissions2);
                return;
            }

           */
            if ($quiz_submissions) {

                 // we need all the assignment ids for the loop, so we make an array of them
                 $quizzes = $this->list_assessment_ids($quiz_submissions);

                 foreach ($quizzes as $quiz) {

                    // counter for number of unmarked submissions
                    $count = 0;

                    // permission to grade?
                    $modulecontext = get_context_instance(CONTEXT_MODULE, $quiz->cmid);
                    if (!has_capability('mod/quiz:grade', $modulecontext, $this->userid)) {continue;}

                    if(!$this->config) { //we are making the main block tree, not the configuration tree

                        // has this assignment been set to invisible in the config settings?

                        $check = $this->get_groups_settings('quiz', $quiz->id);
                        if ($check) {
                            if ($check->showhide == 3) {
                                continue;
                            }
                        }
                        foreach ($quiz_submissions as $quiz_submission) {
                            if (!isset($quiz_submission->userid)) {
                                continue;
                            }
                            if ($quiz_submission->id == $quiz->id) {
                                if (!$check) {
                                    $count ++;
                                } elseif ($check->showhide == 2) {
                                    $groupmatch = $this->check_group_membership($check->groups, $quiz_submission->userid);

                                    if ($groupmatch) {
                                     $count++;
                                    }
                                } elseif ( $check->showhide == 1) {
                                    $count++;
                                } else {

                                }
                            }
                        }

                        // if there are no unmarked assignments, just skip this one. Important not to skip
                        // it in the SQL as config tree needs all assignments
                        if ($count == 0) {

                            continue;
                        }
                    }

                    $fid = $quiz->id;
                    $sum = $quiz->description;
                    $sumlength = strlen($sum);
                    $shortsum = substr($sum, 0, 100);
                    if (strlen($shortsum) < strlen($sum)) {
                        $shortsum .= "...";
                    }
                    $this->output .= ','; // add a comma before section only if there was a preceding assignment

                    $this->output .= '{';
                    $this->output .= '"name":"'.$this->clean_name_text($quiz->name, 1).'",';
                    $this->output .= '"id":"'.$fid.'",';
                    $this->output .= '"assid":"q'.$fid.'",';
                    $this->output .= '"cmid":"'.$quiz->cmid.'",';
                    $this->output .= '"type":"quiz",';
                    $this->output .= '"summary":"'.$this->clean_summary_text($shortsum).'",';
                    $this->output .= '"count":"'.$count.'"';
                    $this->output .= '}';

                } // end foreach quizzes
            }// end if quiz submissions
        }// end quizzes function


	//////////////////////////////////////////////////////////////
	// Function to get all the quiz attempts
	////////////////////////////////////////////////////////////////
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

	    $quiz = get_record('quiz', 'id', $this->id); 
            $courseid = $quiz->course;

            $this->get_course_students($quiz->course);

            global $CFG;
            // needed for the constants, but a big include - maybe not necessary.
            // constants have been replaced with their numerical values. Will need changing if they alter.
            // require_once ("{$CFG->dirroot}/mod/quiz/locallib.php");

            //permission to grade?
            $coursemodule = get_record('course_modules', 'course', $quiz->course, 'module', 13, 'instance', $quiz->id) ;
            $modulecontext = get_context_instance(CONTEXT_MODULE, $coursemodule->id);
            if (!has_capability('mod/quiz:grade', $modulecontext, $this->userid)) {
                return;
            }

            $csv_questions = get_record_sql("SELECT questions FROM {$CFG->prefix}quiz WHERE id = $this->id");

            $sql = "
                  SELECT
                    qst.id as qstid, qst.event, qs.questionid as id, q.name, qa.userid, q.questiontext as description, q.qtype, qa.userid, qa.timemodified
                  FROM
                    {$CFG->prefix}question_states qst
                  INNER JOIN
                    {$CFG->prefix}question_sessions qs
                     ON
                        qs.newest = qst.id
                  INNER JOIN {$CFG->prefix}question q
                     ON
                        qs.questionid = q.id
                  INNER JOIN
                    {$CFG->prefix}quiz_attempts qa
                      ON
                        qs.attemptid = qa.uniqueid
                  WHERE
                    qa.quiz = $quiz->id
                  AND
                    qa.userid
                      IN ({$this->student_ids->$courseid})
                  AND qa.timefinish > 0
                  AND qa.preview = 0
                  AND qs.questionid IN ($csv_questions->questions)
                  AND q.qtype = 'essay'
                  AND qst.event NOT IN (3,6,9)";
            /*
                        ".QUESTION_EVENTGRADE.",
                        ".QUESTION_EVENTCLOSEANDGRADE.",
                        ".QUESTION_EVENTMANUALGRADE.")
            ";
            */
            $question_attempts = get_records_sql($sql);

            // not the same as $csv_questions as some of those questions will have no attempts needing attention
            $questions = $this->list_assessment_ids($question_attempts);

            if (!$this->group) {
                $group_check = $this->assessment_groups_filter($question_attempts, 'quiz', $this->id);
                if (!$group_check) {
                    return;
                }
            }


            $this->output = '[{"type":"quiz_question"}';      // begin json object.   Why course?? Children treatment?

            foreach ($questions as $question) {

                $count = 0;

                foreach ($question_attempts as $question_attempt) {
                    if (!isset($question_attempt->userid)) {continue;}
                    // if we have come from a group node, ignore attempts where the user is not in the right group
                    // also ignore attempts not relevant to this question
                    // if () { //if a group has been specified, ignore any in ohter groups
                    if (($this->group && !$this->check_group_membership($this->group, $question_attempt->userid)) || (!($question_attempt->id == $question->id))) {

                        continue;
                    }
                    $count = $count + 1;
                }

                if ($count > 0) {
                    $name = $question->name;
                    $qid = $question->id;
                    $sum = $question->description;
                    $sumlength = strlen($sum);
                    $shortsum = substr($sum, 0, 100);
                    if (strlen($shortsum) < strlen($sum)) {
                        $shortsum .= "...";
                    }
                    $this->output .= ',';

                    $this->output .= '{';
                    $this->output .= '"name":"'.$this->clean_name_text($name, 1).'",';
                    $this->output .= '"id":"'.$qid.'",';
                    if ($this->group) {
                        $this->output .= '"group":"'.$this->group.'",';
                    }
                    $this->output .= '"assid":"qq'.$qid.'",';
                    $this->output .= '"type":"quiz_question",';
                    $this->output .= '"summary":"'.$this->clean_summary_text($shortsum).'",';
                    $this->output .= '"count":"'.$count.'"';
                    $this->output .= '}';
                }
            }
        $this->output .= "]"; // end JSON array
	}

	/**
         * Makes the nodes with the student names for each question. works either with or without a group having been set.
         * @global <type> $CFG
         * @return <type>
         */

	function quiz_submissions() {

            $quiz = get_record('quiz', 'id', $this->quizid);
            $courseid = $quiz->course;

             //permission to grade?
            $coursemodule = get_record('course_modules', 'course', $quiz->course, 'module', 13, 'instance', $quiz->id) ;
            $modulecontext = get_context_instance(CONTEXT_MODULE, $coursemodule->id);
            if (!has_capability('mod/quiz:grade', $modulecontext, $this->userid)) {
                return;
            }

            $this->get_course_students($quiz->course);
            global $CFG;
            //require_once ("{$CFG->dirroot}/mod/quiz/locallib.php");

            $question_attempts = get_records_sql("

                  SELECT
                    qst.id, qst.event, qs.questionid, qa.userid, qa.timemodified
                  FROM
                    {$CFG->prefix}question_states qst
                  INNER JOIN
                    {$CFG->prefix}question_sessions qs
                     ON
                        qs.newest = qst.id
                  INNER JOIN
                    {$CFG->prefix}quiz_attempts qa
                      ON
                        qs.attemptid = qa.uniqueid
                  WHERE
                    qa.quiz = $this->quizid
                  AND
                    qa.userid
                      IN ({$this->student_ids->$courseid})
                  AND qa.timefinish > 0
                  AND qa.preview = 0
                  AND qs.questionid = $this->id
                  AND qst.event NOT IN (3,6,9)
             ");
            /*
                        ".QUESTION_EVENTGRADE.",
                        ".QUESTION_EVENTCLOSEANDGRADE.",
                        ".QUESTION_EVENTMANUALGRADE.")
            ");
            */
            $this->output = '[{"type":"submissions"}';      // begin json object.

            foreach ($question_attempts as $question_attempt) {
                if (!isset($question_attempt->userid)) {
                    continue;
                }
                // ignore those where the group is not set
                if ($this->group && ($this->group != 'undefined')) {
                     $check = $this->check_group_membership($this->group, $question_attempt->userid);
                     if(!$check)  {
                         continue;
                     }
                }

                $name = $this->get_fullname($question_attempt->userid);

                $now = time();
                $seconds = ($now - $question_attempt->timemodified);
                $summary = $this->make_time_summary($seconds);

                $this->output .= $this->make_submission_node($name, $question_attempt->userid, $this->id, $summary, 'quiz_answer', $seconds, $question_attempt->timemodified);

                /*
                $this->output .= ','; // add a comma before section only if there was a preceding assignment

                $this->output .= '{';
                $this->output .= '"name":"'.$this->clean_name_text($name, -2).'",';
                $this->output .= '"sid":"'.$question_attempt->userid.'",'; //  user id for hyperlink
                $this->output .= '"aid":"'.$this->id.'",'; // id of question for hyperlink
                $this->output .= '"seconds":"'.$seconds.'",'; // seconds sent to allow style to change according to how long it has been
                $this->output .= '"summary":"'.$this->clean_summary_text($summary).'",';
                $this->output .= '"type":"quiz_answer",';
                $this->output .= '"count":"1",';
                $this->output .= '"time":"'.$question_attempt->timemodified.'"'; // send the time of submission for tooltip
                $this->output .= '}';
                */
            }
            $this->output .= "]"; // end JSON array
	}


	/**
         * Gets all of the journals ready for node display. If groups are set,
         * the node will be an expanding one for groups. Otherwise, it will be just clickable
         */

        function journals() {

            global $CFG;
            $this->get_course_students($this->id);
            $courseid = $this->id;
            $sql = "
                    SELECT je.id as entryid, je.userid, j.intro as description, j.name, j.id, c.id as cmid
                    FROM {$CFG->prefix}journal_entries je
                    INNER JOIN {$CFG->prefix}journal j
                       ON je.journal = j.id
                    INNER JOIN {$CFG->prefix}course_modules c
                             ON j.id = c.instance
                    WHERE c.module = {$this->module_ids['journal']->id}
                    AND c.visible = 1
                    AND j.assessed <> 0
                    AND je.modified > je.timemarked
                    AND je.userid IN({$this->student_ids->$courseid})
                    AND j.course = $this->id
                   ";

            $journal_entries = get_records_sql($sql);

            if ($journal_entries) {

                $journals = $this->list_assessment_ids($journal_entries);

                foreach($journals as $journal) {

                    $count = 0;

                    //permission to grade?
                    $context = get_context_instance(CONTEXT_COURSE, $this->id);
                    if (!has_capability('mod/assignment:grade', $context)) {continue;} // could not find journal capabilities - will this work?

                    if (!$this->config) { // its the main tree

                        // has this assignment been set to invisible in the config settings?
                        $check = $this->get_groups_settings('journal', $journal->id);
                        if ($check) {
                            if ($check->showhide == 3) {continue;}
                        }

                        foreach($journal_entries as $journal_entry) {
                            if (!isset($journal_entry->userid)) {continue;}
                            if ($journal_entry->id == $journal->id) {
                                if (!$check || ($check && $check->showhide == 2 && $this->check_group_membership($check->groups, $journal_entry->userid)) || $check->showhide == 1) {
                                    $count++;
                                }
                            }
                        }
                        if ($count == 0) {continue;}
                    }//end if !$this->config

                    // there are some entries so we add the journal node
                    $this->make_assessment_node($journal->name, $journal->id, $journal->cmid, $journal->description, 'journal', $count);
                    /*

                    $aid = $journal->id;
                    $sum = $journal->intro;                                          // make summary
                    $sumlength = strlen($sum);                                       // how long it it?
                    $shortsum = substr($sum, 0, 100);                                // cut it at 100 characters
                    if (strlen($shortsum) < strlen($sum)) {$shortsum .= "...";}      // if that cut the end off, add an ellipsis
                    $this->output .= ','; // add a comma before section only if there was a preceding assignment

                    $this->output .= '{';
                    $this->output .= '"name":"'.$this->clean_name_text($journal->name, 1).'",';
                    $this->output .= '"id":"'.$coursemodule->id.'",';
                    $this->output .= '"assid":"j'.$aid.'",';
                    $this->output .= '"cmid":"'.$coursemodule->id.'",';
                    if ($check && $check->showhide == 2) {
                        $this->output .= '"type":"journal_submissions",';
                    } else {
                        $this->output .= '"type":"journal",';
                    }
                    $this->output .= '"summary":"'.$this->clean_summary_text($shortsum).'",';
                    $this->output .= '"count":"'.$count.'"';
                    $this->output .= '}';

                     */
                } // end foreach journal
            }
	}




        /**
         * Formats the summary text so that it works in the tooltips without odd characters
         *
         * @param <type> $text the summary text to formatted
         * @param <type> $stripbr optional flag which removes <strong> tags
         * @return <type>
         */

	function clean_summary_text($text, $stripbr=true) {
            if ($stripbr == true) {
                    $text = strip_tags($text, '<strong>');
            }
            $text = str_replace(array("\n","\r",'"'),array("","","&quot;"),$text);

            return $text;
	}

        /**
         * this function controls how long the names will be in the block. different levels need different lengths as the tree indenting varies.
         * the aim is for all names to reach as far to the right as possible without causing a line break. Forum discussions will be clipped
	 * if you don't alter that setting in forum_submissions()
         * @param <type> $text
         * @param <type> $level - how many characters to stip, corresponding roughly with how far into the tree we are.
         * @param <type> $stripbr
         * @return <type>
         */

	function clean_name_text($text,  $level=0, $stripbr=true) {
		if ($stripbr == true) {
			$text = strip_tags($text, '<strong>');
		}
                $text = htmlentities($text, ENT_QUOTES);
		switch($level) {

                case -2:
                        break;
                case -1:
                        $text = substr($text, 0, 30);
                        break;
		case 0:
			$text = substr($text, 0, 12);

		case 1:
			$text = substr($text, 0, 24);
			break;
		case 2:
			$text = substr($text, 0, 10);
			break;
		case 3:
			$text = substr($text, 0, 10);
			break;
		}
		$text = str_replace(array("\n","\r",'"'),array("","","&quot;"),$text);
		return $text;
	}

	/**
	 * This function returns a comma separated list of all student ids in a course. It uses the config variable for gradebookroles
	 * to get ones other than 'student' and to make it language neutral. Point is that when students leave the course, often
	 * their work remains, so we need to check that we are only using work from currently enrolled students.
	 *
         * @param <type> $courseid
         * @return <type>
         */
	function get_course_students($courseid) {

            if (!isset($this->student_ids->$courseid)) {
                $course_context = $this->courses[$courseid]->context;

                $student_array = array();
                $student_details = array();

                // get the roles that are specified as graded in config
                $student_roles = get_field('config','value', 'name', 'gradebookroles');
                $student_roles = explode(",", $student_roles); //make the list into an array

                foreach ($student_roles as $student_role) {
                    $course_students = get_role_users($student_role, $course_context); // get students in this course with this role
                    if ($course_students) {
                        // we have an array of objects, which we need to get the student ids out of and into a comma separated list
                        foreach($course_students as $course_student) {
                           // print_r($course_student);
                           // break;
                                array_push($student_array, $course_student->id);
                               // if (!isset($course_student->id)) {
                                //    print_r($course_student);
                               // }
                               // $id = ;
                                $student_details[$course_student->id] = $course_student;
                        }
                    }
                }
                if (count($student_array) > 0) {
                    // some students were returned

                    // convert to comma separated list
                    $student_ids = implode(",", $student_array);

                    // save the list so it can be reused
                    $this->student_ids->$courseid     = $student_ids;
                    $this->student_array->$courseid   = $student_array;
                    // keep all student details so they can be used for making nodes.
                    // TODO - make a check so this only happens when needed.
                    $this->student_details = $this->student_details +  $student_details;
                } else {
                    return false;
                }
            }
	}


        /**
         * This is to get the teachers so that forum posts that have been graded by another teacher
         * can be hidden automatically. No courseid = get all teachers in all courses.
         *
         * TODO Can this be cached?
         *
         * @param <type> $courseid
         * @return <type>
         */
        function get_teachers() {

            $teacher_array = array();
            $teacher_details = array();

            // TODO get the roles that are specified as being able to grade forums
            $teacher_roles = array(3, 4);

            foreach ($this->courses as $course) {

                // this mulidimensional array isn't currently used.
                //$teacher_array[$coure->id] = array();

                $course_teachers = get_role_users($teacher_roles, $course->context); // get teachers in this course with this role
                if ($course_teachers) {

                    foreach($course_teachers as $course_teacher) {

                        if (!in_array($course_teacher->id, $teacher_array)) {

                            $teacher_array[] = $course_teacher->id;

                         }
                    }
                }
            }
            if (count($teacher_array, 1) > 0) { // some teachers were returned
                 $this->teacher_array = $teacher_array;
                 $this->teachers = implode(',', $teacher_array);
            } else {
                 $this->teacher_array = false;
            }
	}

	/**
	 * function to make the summary for submission nodes, showing how long ago it was
         * submitted
	 */

	function make_time_summary($seconds, $discussion=false) {
            $weeksstr = get_string('weeks', 'block_ajax_marking');
            $weekstr = get_string('week', 'block_ajax_marking');
            $daysstr = get_string('days', 'block_ajax_marking');
            $daystr = get_string('day', 'block_ajax_marking');
            $hoursstr = get_string('hours', 'block_ajax_marking');
            $hourstr = get_string('hour', 'block_ajax_marking');
            $submitted = ""; // make the time bold unless its a discussion where there is already a lot of bolding
            $ago = get_string('ago', 'block_ajax_marking');

            if ($seconds<3600) {
               $name = $submitted."<1 ".$hourstr;
            }
            if ($seconds<7200) {
               $name = $submitted."1 ".$hourstr;
            }
            elseif ($seconds<86400) {
               $hours = floor($seconds/3600);
               $name = $submitted.$hours." ".$hoursstr;
            }
            elseif ($seconds<172800) {
               $name = $submitted."1 ".$daystr;
            }
            else {
               $days = floor($seconds/86400);
               $name = $submitted.$days." ".$daysstr;
            }
            $name .= " ".$ago;
            return $name;
	}


    /**
     * Makes the course list for the configuration tree. No need to count anything, just make the nodes
     * Might be possible to collapse it into the main one with some IF statements.
     *
     */
    function config_courses() {

            $this->output = '[{"type":"config_main"}';

            if ($this->courses) { // might not be any available

                $assignments = $this->get_all_assignments();
                $workshops   = $this->get_all_workshops();
                $quizzes     = $this->get_all_quizzes();
                $forums      = $this->get_all_forums();
                $journals    = $this->get_all_journals();

                foreach ($this->courses as $course) {	
                    // iterate through each course, checking permisions, counting assignments and
                    // adding the course to the JSON output if anything is there that can be graded
                    $count = 0;
                    $count = $count + $this->count_course_assessment_nodes($assignments, $course->id, 'assignment');
                    $count = $count + $this->count_course_assessment_nodes($workshops, $course->id, 'workshop');
                    $count = $count + $this->count_course_assessment_nodes($quizzes, $course->id, 'quiz');
                    $count = $count + $this->count_course_assessment_nodes($forums, $course->id, 'forum');
                    $count = $count + $this->count_course_assessment_nodes($journals, $course->id, 'journal');

                    if ($count > 0) {

                        $this->output .= ','; // add a comma if there was a preceding course
                        $this->output .= '{';

                        $this->output .= '"id":"'       .$course->id.'",';
                        $this->output .= '"type":"config_course",';
                        $this->output .= '"title":"'    .$this->clean_name_text($course->shortname, -2).'",';
                        $this->output .= '"label":"'    .$this->add_icon('course').$this->clean_name_text($course->shortname, -2).'",';
                        // to be used for the title
                        $this->output .= '"label":"'    .$this->add_icon('course').$this->clean_name_text($course->shortname, -2).'",';
                        $this->output .= '"summary":"'  .$this->clean_name_text($course->shortname, -2).'",';
                        $this->output .= '"count":"'    .$count.'"';

                        $this->output .= '}';

                    }
                }
            }
            $this->output .= ']';
	}

	/**
         * writes to the db that we are to use config groups, then returns all the groups.
         * Called only when you click the option 2 of the config, so the next step is for the javascript
         * functions to build the groups checkboxes.
         *
         * Currently returns true regradless of
         */

	function config_groups() { //writes to the db that we are to use config groups, then returns all the groups.

            $this->output = '[{"type":"config_groups"}'; 	// begin JSON array

            //first set the config to 'display by group'
            $this->make_data();
            if ($this->config_write()) {
                // we will now return all of the groups in a course as an array,
                $this->return_groups($this->id, $this->assessmenttype, $this->assessmentid);
            } else {
                $this->output .= ',{"result":"false"}';
            }
            $this->output .= ']';
	}

        /**
         * this is to save configuration choices from the radio buttons for 1 and 3 once
         * they have been clicked. Needed as a wrapper
         * so that the config_write bit can be used for the function above too
         */

	function config_set() {

		$this->output = '[{"type":"config_set"}';
		$this->make_data();
		if($this->config_write()) {
			$this->output .= ',{"result":"true"}]';
		} else {
			$this->output .= ',{"result":"false"}]';
		}
	}

        /**
         * This is to build the data ready to be written to the db, using the parameters submitted so far.
         * Others might be added to this object later byt he functions that call it, to match different scenarios
         */

	function make_data() {
		$this->data = new stdClass;
		$this->data->userid = $this->userid;
                $this->data->assessmenttype = $this->assessmenttype;
                $this->data->assessmentid = $this->assessmentid;
		$this->data->showhide = $this->showhide;
	}

	/**
         * takes data as the $this->data object and writes it to the db as either a new record or an updated one.
         * might be to show or not show or show by groups.
         * Called from config_set, config_groups, return_groups ($this->data->groups)
         *
         * @return <type>
         */
	function config_write() {
            $check = NULL;
            $check2 = NULL;

            $check = get_record('block_ajax_marking', 'assessmenttype', $this->assessmenttype, 'assessmentid', $this->assessmentid, 'userid', $this->userid);
            if ($check) {

                // record exists, so we update
                $this->data->id = $check->id;
                $check2 = update_record('block_ajax_marking', $this->data);

                if($check2) {
                    return true;
                } else {
                    return false;
                }
            } else {
                // no record, so we create

                $check = insert_record('block_ajax_marking', $this->data);
                if ($check) {
                    return true;
                } else {
                    return false;
                }
            }
            echo $check;
	}

	/**
         * this is to check what the current status of an assessment is so that
         * the radio buttons can be made with that option selected.
	 * if its currently 'show by groups', we need to send the group data too.
         *
         *
         * @return nothing
	 */
	function config_check() {

            $this->output = '[{"type":"config_check"}'; 	// begin JSON array

            $config_settings = $this->get_groups_settings($this->assessmenttype, $this->assessmentid);

            if ($config_settings) {
                $this->output .= ',{"value":"'.$config_settings->showhide.'"}';
                if ($config_settings->showhide == 2) {
                    $this->return_groups($this->id, $this->assessmenttype, $this->assessmentid);
                }
            } else {
                    $this->output .= ',{"value":"1"}';
            }
            $this->output .= ']';
	}




	/**
         * finds the groups info for a given course for the config tree. It then needs to check if those groups
         * are to be displayed for this assessment and user. can probably be merged with the function above.
         * Outputs a json object straight to AJAX
         *
         * @param int $courseid
         * @param string $type type of assessment e.g. forum, workshop
         * @param int $assessmentid
         */

	function return_groups($courseid, $assessmenttype, $assessmentid) {
		$groups = NULL;
		$current_settings = NULL;
		$current_groups = NULL;
		$groupslist = '';

                // get currently saved groups settings, if there are any, so that check boxes can be marked correctly
		$config_settings = $this->get_groups_settings($assessmenttype, $assessmentid);
                if ($config_settings) {

                    //only make the array if there is not a null value
                    if ($config_settings->groups && ($config_settings->groups != 'none') && ($config_settings->groups != NULL)) {

                            $current_groups = explode(' ', $config_settings->groups); //turn space separated list of groups from possible config entry into an array
                    }
                }
                $groups = get_records('groups', 'courseid', $courseid);
                if ($groups) { //there are some groups


			foreach($groups as $group) {

                           // make a space separated list for saving if this is the first time
                            if (!$config_settings || !$config_settings->groups) {
                                    $groupslist .= $group->id." ";

                            }
                            $this->output .= ',{';
                            
                            // do they have a record for which groups to display? if no records yet made, default to display, i.e. box is checked
                            if ($current_groups) {
                                $this->output .= (in_array($group->id, $current_groups)) ? '"display":"true",' : '"display":"false",';
                                //if (in_array($group->id, $current_groups)) { // the group id is in the array of groups that were stored in the db
                                //        $this->output .= '"display":"true",';
                                //} else { // it was not set in the db
                                //        $this->output .= '"display":"false",';
                                //}
                            } elseif ($config_settings && ($config_settings->groups == 'none')) {// all groups should not be displayed.
                                $this->output .= '"display":"false",';

                            } else {
                                //default to display if there was no entry so far (first time)
                                $this->output .= '"display":"true",';
                            }
                            $this->output .= '"label":"'.$group->name.'",';
                            $this->output .= '"name":"'.$group->name.'",';
                            $this->output .= '"id":"'.$group->id.'"';
                            $this->output .= '}';
			}
			if (!$config_settings || !$config_settings->groups) {
				// save the groups if this is the first time
				$this->data->groups = $groupslist;

				$this->config_write();
			}

		}// end if  groups
                // TODO - what if there are no groups - does the return function in javascript deal with this?
	}// end function

	/** sets the display of a single group from the config screen when its checkbox is clicked. Then, it sends back a confirmation so
         * that the checkbox can be un-greyed and marked as done
         *
         */

	function config_group_save() {
		$this->output = '[{"type":"config_group_save"},{'; 	// begin JSON array

		$this->make_data();
                if($this->groups) {
                    $this->data->groups = $this->groups;
                }
		if($this->config_write()) {
			$this->output .= '"value":"true"}]';
		} else {
			$this->output .= '"value":"false"}]';
		}
	}

	/**
	 * This is the function that is called from the assessment_submissions functions to
         * take care of checking config settings and filtering the submissions if necessary. It behaves
         * differently depending on the users preferences, and is called from both the clicked assessment node
         * (forum, workshop) and also the clicked group nodes if there are any. It returns the nodes to be built.
         *
         * 
         *
	 * @param object with $submission->userid of the unmarked submissions for this assessment
         * @param string $type the type of assessment e.g. forum, assignment
         * @param
         * @return mixed false if set to hidden, or groups exist and nodes are built. True if set to
         *               display all, if no config settings exist
         *
	 */

	function assessment_groups_filter($submissions, $type, $assessmentid) {
	    unset($config_settings);
            global $CFG;

            //need to get the groups for this assignment from the config object
            //$combinedrefs = $type.$assessmentid;
            $config_settings = $this->get_groups_settings($type, $assessmentid);

            // maybe nothing was there, so we need a default, i.e. show all.
            if (!$config_settings) {
                return true;
            } else {
                // maybe its set to show all
                if ($config_settings->showhide == 1) {
                    return true;
                }
                // perhaps it is set to hidden
                if ($config_settings->showhide == 3) {
                    return false;
                }

                // it must otherwise be set to groups, so we make the groups output and then stop.
                $this->output = '[{"type":"groups"}';
                $trimmed_groups = trim($config_settings->groups);

                // assuming an array of ids are passed, along with a space separated list of groups, we need to make both into arrays
                $groupsarray = explode(" ", $trimmed_groups);
                $csv_groups = implode(',', $groupsarray);
                //TODO make this into a cached query for all groups in this course.
                $sql = "SELECT id, name, description FROM {$CFG->prefix}groups WHERE id IN ($csv_groups)";
                $groupdetails = get_records_sql($sql);

                //now cycle through each group, plucking out the correct members for each one.
                //some people may be in 2 groups, so will show up twice. not sure what to do about that. Maybe use groups mode from DB...

                foreach($groupsarray as $group) {

                    $count = 0;

                    foreach($submissions as $submission) {

                        // check against the group members to see if 1. this is the right group and 2. the id is a member
                        if ($this->check_group_membership($group, $submission->userid))  {
                            $count++;
                        }
                    }

                    
                    $summary = $groupdetails[$group]->description ? $groupdetails[$group]->description : "no summary";
                   

                    if ($count > 0) {
                        $this->output .= ',';
                        $this->output .= '{';
                        $this->output .= '"label":"'        .$this->add_icon('group').$groupdetails[$group]->name.' ('.$count.')",';
                        $this->output .= '"name":"'         .$this->add_icon('group').$groupdetails[$group]->name.'",';
                        $this->output .= '"group":"'        .$group.'",'; // id of submission for hyperlink
                        $this->output .= '"id":"'           .$assessmentid.'",'; // id of assignment for hyperlink
                        $this->output .= '"title":"'        .$summary.'",';
                        $this->output .= '"type":"'         .$type.'",';
                        //$this->output .= '"seconds":"'.$seconds.'",'; // seconds sent to allow style to change according to how long it has been
                        //$this->output .= '"time":"'.$submission->timemodified.'",'; // send the time of submission for tooltip
                        $this->output .= '"count":"'        .$count.'"';
                        $this->output .= '}';
                    }
                }
                $this->output .= ']';
                return false;
            }
	}

        /**
         * A peculiarity with assignments, due to the pop up system in place at the moment,
         * is that the pop-up javascript tries to update the underlying page when it's closed,
         * but because we are no on that page when it is called, we get a javascript error because those DOM
         * elements are missing. This function was to simulate the collapse of all of the table elements
         * so that they would not need updating.
         *
         * Never worked properly
         */
	function assignment_expand() {
			if (!isset($SESSION->flextable)) {
               $SESSION->flextable = array();
           }
		if (!isset($SESSION->flextable['mod-assignment-submissions']->collapse)) {
	        $SESSION->flextable['mod-assignment-submissions']->collapse = array();
		}

		$SESSION->flextable['mod-assignment-submissions']->collapse['submissioncomment'] = true;
		$SESSION->flextable['mod-assignment-submissions']->collapse['grade']             = true;
		$SESSION->flextable['mod-assignment-submissions']->collapse['timemodified']      = true;
		$SESSION->flextable['mod-assignment-submissions']->collapse['timemarked']        = true;
		$SESSION->flextable['mod-assignment-submissions']->collapse['status']            = true;

	}

	/**
         * See previous function
         */
	function assignment_contract() {
		if (isset($SESSION->flextable['mod-assignment-submissions']->collapse)) {
			  $SESSION->flextable['mod-assignment-submissions']->collapse['submissioncomment'] = false;
			  $SESSION->flextable['mod-assignment-submissions']->collapse['grade']             = false;
			  $SESSION->flextable['mod-assignment-submissions']->collapse['timemodified']      = false;
			  $SESSION->flextable['mod-assignment-submissions']->collapse['timemarked']        = false;
			  $SESSION->flextable['mod-assignment-submissions']->collapse['status']            = false;
		}
	}

        /**
         * Fetches all of the group members of all of the courses that this user is a part of. probably needs to be narrowed using roles so that
         * only those courses where the user has marking capabilities get fetched. Not perfect yet, as the check for role assignments could throw
         * up a student with a role in a different course to that which they are in a group for. This is not a problem, as
         * this list is used to filter student submissions returned from SQL including a check for being one of the course students.
         * The bit in ths function just serves to limit the size a little.
         * @global <type> $CFG
         * @return object $group_members results object, as provided by db.
         */
        function get_my_groups() {

            global $CFG;
            $course_ids = NULL;

            if (!$this->courses) {return false;}

            $sql = "SELECT gm.*
                    FROM {$CFG->prefix}groups_members gm
                    INNER JOIN {$CFG->prefix}groups g
                        ON gm.groupid = g.id
                    WHERE g.courseid IN ($this->course_ids)
            ";

            $group_members = get_records_sql($sql);

            return $group_members;
        }

        /**
         * Fetches the correct config settings row from the settings object, given the details
         * of an assessment item
         *
         * @param string $combinedref a concatenation of assessment type and assessment id e.g. forum3, workshop17
         * @return <type>
         */
        function get_groups_settings($assessmenttype, $assessmentid) {
            if ($this->groupconfig) {
                foreach($this->groupconfig as $key => $config_row) {
                    if (($config_row->assessmenttype == $assessmenttype) && ($config_row->assessmentid == $assessmentid)) {
                        return $config_row;
                    }
                }
            }
            return false;
        }

        /**
         * This is to find out whether the assessment item should be displayed or not, according to the user's
         * preferences
         * @param <type> $type course or assessment item?
         * @param <type> $id   id# of that item
         * @param <type> $config 
         * 
         */
        function check_assessment_display_settings($settings) {

             if ($settings) {
                 if ($settings->showhide == 3) {
                     return false;
                 } else {
                     return true;
                 }
             }
             return true;
        }

        /**
         * This takes the settings object for a particular assessment item and checks whether the student
         * id supplied should be added tothe count for it, depending on display and group info.
         *
         * @param <type> $check
         * @param <type> $userid
         * @return <type> Boolean
         */
        function check_submission_display_settings($check, $userid) {

            if ((!$check) || ($check && ($check->showhide == 2) && $this->check_group_membership($check->groups, $userid)) || ($check->showhide == 1)) {
                return true;
            } else {
                return false;
            }
        }

        /**
         * This runs through the previously retrieved group members list looking for a match between student id and group
         * id. If one is found, it returns true. False means that the student is not a member of said group.
         *
         * @para string $groups A comma separated list of groups.
         * @param array $data
         */
        function check_group_membership($groups, $memberid){

             //if (stripos( $groups, ',')) {


             //} else {
                 $groups_array = array();
                 $groups =  trim($groups);
                 $groups_array = explode(' ', $groups);
                 //$groupfix = (int) $groups;
                 //$groups_array[] = $groupfix;

            // }
       //echo "groups $groups ";
      // print_r($groups_array);
             foreach ($this->group_members as $group_member) {

                 $gid = $group_member->groupid;

                 foreach ($groups_array as $group) {
                    if ($gid == $group) {
                        $uid = $group_member->userid;
                        if ($uid == $memberid) {
                            return true;
                        }
                    }
                }
            }
            return false;
        }


        /**
         * Fetches the fullname for a given userid. All student details are retrieved in a single SQL query at the start and
         * the stored object is checked with this function. Included is a check for very long names
         * (>15 chars), which will need hyphenating
         *
         * @param int $userid
         * @return string
         */
        function get_fullname($userid) {

            $student_details = $this->student_details[$userid];
                
            if (strlen($student_details->firstname) > 15) {
                $name = substr_replace($student_details->firstname, '-', 15, 0);
            } else {
                $name = $student_details->firstname;
            }
            $name .= " ";
            if (strlen($student_details->lastname) > 15) {
                $name .= substr_replace($student_details->lastname, '-', 15, 0);
            } else {
                $name .= $student_details->lastname;
            }
            //$name = chunk_split($student_details->firstname, 15, '-')." ".$name = chunk_split($student_details->lastname, 15, '-');
            //$name = $this->clean_name_text($name, 2)

           // chunk_split($name, 10, '-');
                
            
            return $name;
        }

        /**
         * Makes the JSON data for output. Called only from the submissions functions.
         *
         * @param string $name The name of the student or the truncated title of the discussion for the link
         * @param int $sid  Submission id for the link
         * @param int $aid  Assessment id or coursemodule id for the link
         * @param string $summary Text for the tooltip
         * @param string $type Type of assessment
         * @param int $seconds Number of second ago that this was submitted - for the colour coding
         * @param int $timemodified - Time submitted in unix format, for the tooltip(?)
         */
        function make_submission_node($name, $sid, $aid, $summary, $type, $seconds, $timemodified, $count=1) {
            $this->output .= ',';
          
            $this->output .= '{';

                $this->output .= '"label":"';
                // some assessment types only have 2 levels as they cannot be displayed per student e.g. journals
                $this->output .= (isset($this->$type->levels)) ? $this->add_icon($type) : $this->add_icon('user');
                $this->output .= htmlentities($name, ENT_QUOTES).'",';
                // this bit gets the user icon anyway as it isn't used at level 2
                $this->output .= '"name":"'   .$this->add_icon('user').htmlentities($name, ENT_QUOTES).'",';

                $this->output .= (isset($this->$type->levels)) ? '"dynamic":"false",' : '';

                $this->output .= '"sid":"'    .$sid.'",'; // id of submission for hyperlink
                $this->output .= '"aid":"'    .$aid.'",'; // id of assignment for hyperlink
                $this->output .= '"id":"'     .$aid.'",'; // might need uniqueId to replace it
                $this->output .= '"title":"'  .$this->clean_summary_text($summary).'",';
                $this->output .= '"type":"'   .$type.'",';
                $this->output .= '"seconds":"'.$seconds.'",'; // 'seconds ago' sent to allow style to change according to how long it has been
                $this->output .= '"time":"'   .$timemodified.'",'; // send the time of submission for tooltip
                $this->output .= '"count":"'  .$count.'"';
            $this->output .= '}';
        }

        /**
         * Makes a list of unique ids from an sql object containing submissions for many different assessments.
         * Called from the assessment level functions e.g. quizzes().
         *
         * @param object $submissions Must have
         *               $submission->id as the assessment id and
         *               $submission->cmid as coursemodule id (optional for quiz question)
         *               $submission->description as the desription
         *               $submission->name as the name
         * @return array array of ids => cmids
         */
        function list_assessment_ids($submissions, $course=false) {

            $ids = array();

                foreach ($submissions as $submission) {
                    if ($course) {
                        if ($submission->course != $course) {
                            continue;
                        }
                    }
                    $check = in_array($submission->id, $ids);
                    if (!$check) {
                            $ids[$submission->id]->id = $submission->id;

                            $ids[$submission->id]->cmid         = (isset($submission->cmid))         ? $submission->cmid         : NULL;
                            $ids[$submission->id]->description  = (isset($submission->description))  ? $submission->description  : NULL;
                            $ids[$submission->id]->name         = (isset($submission->name))         ? $submission->name         : NULL;
                            $ids[$submission->id]->timemodified = (isset($submission->timemodified)) ? $submission->timemodified : NULL;
                    }
                }
                return $ids;
        }

    /**
     * This counts how many unmarked assessments of a particular type are waiting for a particular course
     * It is called from the courses() function when the top level nodes are built
     * @param object $submissions - object containing all of the unmarked submissions of a particular type
     * @param string $type        - type of submissions e.g. 'forums'
     * @param int $course         - id of the course we are counting submissions for
     * @return int                - the number of unmarked assessments
     */
    function count_course_submissions($submissions, $type, $course) {

        foreach ($this->modules as $modname => $mod) {

            $count = 0;
            $discussions = array();




            $assessments = $this->list_assessment_ids($submissions);

            foreach ($assessments as $key => $assessment) {

                if (!$this->assessment_grading_permission($type, $assessment->cmid)) {
                    unset($assessments[$key]);
                }
            }

            foreach ($submissions as $submission) {

                $check = NULL;

                // Is this assignment attached to this course?
                if(!($submission->course == $course))  {continue;}

                //the object may contain assessments with no submissions
                if(!isset($submission->userid))  {
                    continue;
                } else {
                    // is the submission from a current user of this course
                    if(!in_array($submission->userid, $this->student_array->$course)) {continue;}
                }

                // check against previously filtered list of assignments - permission to grade?
                if(!isset($assessments[$submission->id]))  {continue;}

                // get groups settings
                $check = $this->get_groups_settings($type, $submission->id);

                // ignore if the group is set to hidden
                if ($check && ($check->showhide == 3)) { continue; }

                // if there are no settings (default is show), 'display by group' is selected and the group matches, or 'display all' is selected, count it.
                if ((!$check) || ($check && $check->showhide == 2 && $this->check_group_membership($check->groups, $submission->userid)) || ($check && $check->showhide == 1)) {
                    $count++;
                }
            }
        }
        return $count;
    }

    /**
     * This gets module-related stuff ready for use later, when the coursemodule id is needed for filtering
     * in SQL statements
     * @global <type> $CFG
     * @return <type> object with names and ids of coursemodules
     */
    function get_coursemodule_ids() {
        global $CFG;
        $comma_modules = "'";

        // limit things to just the modules we want
           $counter = 0;
           foreach ($this->modules as $module) {
               if ($counter > 0) {
                   $comma_modules .= ",'";
               }
               $comma_modules .= $module."'";
               $counter++;

           }

        $sql = "
            SELECT name, id FROM {$CFG->prefix}modules
            WHERE visible = 1
            AND name IN (".$comma_modules.")
        ";

        $modules = get_records_sql($sql);

        $this->module_ids = $modules;
        return true;
    }
/*
    function get_assignment_names() {
        $courseids = '';
        foreach ($this->courses as $key => $course) {
            $courseids .= $course;
        }
        $sql = "
            SELECT s.id as subid, s.userid, a.course, a.id, c.id as cmid
            FROM
                {$CFG->prefix}assignment a
            INNER JOIN {$CFG->prefix}course_modules c
                 ON a.id = c.instance
            LEFT JOIN {$CFG->prefix}assignment_submissions s
                 ON s.assignment = a.id
            WHERE c.module = {$this->module_ids['assignment']->id}
            AND c.visible = 1
            AND a.course IN ($course_ids)
            AND s.timemarked < s.timemodified
            ORDER BY a.id
         ";
         $assignments = get_records_sql($sql);
         return $assignments;
    }
*/
    /**
     * For SQL statements, a comma separated list of course ids is needed. It is vital that only courses where
     * the user is a teacher are used and also that the front page is excluded.
     */
    function make_course_ids_list() {

        global $USER;
        if($this->courses) {

            $teacher_role=get_field('role','id','shortname','editingteacher'); // retrieve the teacher role id (3)

            foreach ($this->courses as $key=>$course) {

                $allowed_role = false;

                // exclude the front page.
                if ($course->id == 1) {
                    unset($this->courses[$key]);
                    continue;
                }

                // role check bit borrowed from block_marking, thanks to Mark J Tyers [ZANNET]
                $teachers = 0;
                $teachers_ne = 0;

                $teachers = get_role_users($teacher_role, $course->context, true); // check for editing teachers

                if ($teachers) {
                    foreach($teachers as $teacher) {
                        if ($teacher->id == $USER->id) {
                            $allowed_role = true;
                        }
                    }
                }
                if (!$allowed_role) {
                    $ne_teacher_role=get_field('role','id','shortname','teacher'); // retrieve the non-editing teacher role id (4)
                    $teachers_ne = get_role_users($ne_teacher_role, $course->context, true); // check for non-editing teachers
                }
                if ($teachers_ne) {
                    foreach($teachers_ne as $key2=>$val2) {
                        if ($val2->id == $USER->id) {
                            $allowed_role = true;
                        }
                    }
                }
                if (!$allowed_role) {
                    unset($this->courses[$key]);
                    continue;
                }
                $this->course_ids[] = $course->id;
            }
        }
        $this->course_ids = implode($this->course_ids, ',');
    }

   

    /**
     * Gets all unmarked forum posts, but defines unmarked as not marked by the current account. If
     * another teacher has marked it, that is a problem.
     * @return <type> gets all unmarked forum discussions for all courses
     */
    function get_all_unmarked_forums() {
        global $CFG, $USER;
        $sql = "
            SELECT p.id as postid, p.userid, d.id, f.id, f.name, f.course, c.id as cmid
            FROM
                {$CFG->prefix}forum f
            INNER JOIN {$CFG->prefix}course_modules c
                 ON f.id = c.instance
            INNER JOIN {$CFG->prefix}forum_discussions d
                 ON d.forum = f.id
            INNER JOIN {$CFG->prefix}forum_posts p
                 ON p.discussion = d.id
            LEFT JOIN {$CFG->prefix}forum_ratings r
                 ON  p.id = r.post
            WHERE p.userid <> $USER->id
                AND (((r.userid <> $USER->id) AND (r.userid NOT IN ($this->teachers))) OR r.userid IS NULL)
                AND c.module = {$this->module_ids['forum']->id}
                AND c.visible = 1
                AND f.course IN ($this->course_ids)
                AND ((f.type <> 'eachuser') OR (f.type = 'eachuser' AND p.id = d.firstpost))
                AND f.assessed > 0
            ORDER BY f.id
        ";

        $forum_submissions = get_records_sql($sql);
        return $forum_submissions;
    }

    /**
     * gets all of the forums for all courses, ready for the config tree.
     * @global <type> $CFG
     * @return <type>
     */
    function get_all_forums() {
        global $CFG;
        $sql = '
            SELECT f.id, f.course, f.intro as summary, f.name, f.type, c.id as cmid
            FROM
                '.$CFG->prefix.'forum f
            INNER JOIN '.$CFG->prefix.'course_modules c
                 ON f.id = c.instance

            WHERE

                c.module = '.$this->module_ids['forum']->id.'
                AND c.visible = 1
                AND f.course IN ('.$this->course_ids.')
                AND f.assessed > 0

            ORDER BY f.id
        ';
        $forums = get_records_sql($sql);
        return $forums;
    }

    /**
     * gets all unmarked quiz question from all courses. used for the courses count
     *
     */
     function get_all_unmarked_quizzes() {
        global $CFG;

        //require_once ($CFG->dirroot.'/mod/quiz/locallib.php');
        $sql = '
              SELECT
                  qst.id as qstid, qsess.questionid, qz.id, qz.name, qz.course, qa.userid, c.id as cmid
              FROM
                '.$CFG->prefix.'quiz qz
              INNER JOIN '.$CFG->prefix.'course_modules c
                         ON qz.id = c.instance
              INNER JOIN
                '.$CFG->prefix.'quiz_attempts qa
                  ON
                    qz.id = qa.quiz
              INNER JOIN
                '.$CFG->prefix.'question_sessions qsess
                  ON
                    qsess.attemptid = qa.uniqueid
              INNER JOIN
                '.$CFG->prefix.'question_states qst
                 ON
                    qsess.newest = qst.id
              INNER JOIN '.$CFG->prefix.'question q
                 ON
                    qsess.questionid = q.id
              WHERE
                   qa.timefinish > 0
              AND qa.preview = 0
              AND c.module = '.$this->module_ids['quiz']->id.'
              AND c.visible = 1
              AND q.qtype = \'essay\'
              AND qz.course IN ('.$this->course_ids.')
              AND qst.event NOT IN (3,6,9)
              ORDER BY q.id
              ';
            /*
                    '.QUESTION_EVENTGRADE.',
                    '.QUESTION_EVENTCLOSEANDGRADE.',
                    '.QUESTION_EVENTMANUALGRADE.')
              ORDER BY q.id
            ';
         */

            $quiz_submissions = get_records_sql($sql);
            return $quiz_submissions;
    }
    /**
     * gets all the quizzes for the config screen. still need the check in there for essay questions.
     * @global <type> $CFG
     * @return <type>
     */
     function get_all_quizzes() {

         global $CFG;

         $sql = "
              SELECT
                   qz.id, qz.course, qz.intro as summary, qz.name, c.id as cmid
              FROM
                {$CFG->prefix}quiz qz
              INNER JOIN {$CFG->prefix}course_modules c
                         ON qz.id = c.instance
              INNER JOIN
                {$CFG->prefix}quiz_question_instances qqi
                  ON
                    qz.id = qqi.quiz
              INNER JOIN {$CFG->prefix}question q
                 ON
                    qqi.question = q.id
              WHERE
              c.module = {$this->module_ids['quiz']->id}
              AND c.visible = 1
              AND q.qtype = 'essay'
              AND qz.course IN ($this->course_ids)
              ORDER BY qz.id
            ";

            $quizzes = get_records_sql($sql);

            return $quizzes;
    }

    
    /**
     * gets all assignments that could potentially have
     * graded work, even if there is none there now. Used by the config tree.
     * @return <type>
     */
    function get_all_assignments() {

        global $CFG;

        $sql = "
            SELECT  a.id, a.name, a.description as summary, a.course, c.id as cmid
            FROM
                {$CFG->prefix}assignment a
            INNER JOIN {$CFG->prefix}course_modules c
                 ON a.id = c.instance
            WHERE c.module = {$this->module_ids['assignment']->id}
            AND c.visible = 1
            AND a.course IN ($this->course_ids)
            ORDER BY a.id
         ";

         $assignments = get_records_sql($sql);
         return $assignments;
    }

    /**
     * gets all workshops for the config tree
     */
    function get_all_workshops() {

        global $CFG;

        $sql = "
                SELECT w.id, w.course, w.name, w.description as summary, c.id as cmid
                FROM
                    {$CFG->prefix}workshop w
                INNER JOIN {$CFG->prefix}course_modules c
                     ON w.id = c.instance

                WHERE
                    c.module = {$this->module_ids['workshop']->id}
                AND c.visible = 1
                AND w.course IN ($this->course_ids)
                ORDER BY w.id
              ";

            $workshops = get_records_sql($sql);
            return $workshops;
    }

    /**
     * gets all journals for all courses ready for the config tree
     */
    function get_all_journals() {

        global $CFG;

        $sql = "
                SELECT j.id, j.intro as summary, j.name, j.course, c.id as cmid
                FROM  {$CFG->prefix}journal j
                INNER JOIN {$CFG->prefix}course_modules c
                         ON j.id = c.instance
                WHERE c.module = {$this->module_ids['journal']->id}
                AND c.visible = 1
                AND j.assessed <> 0
                AND j.course IN ($this->course_ids)
               ";

            $journals = get_records_sql($sql);
            return $journals;
    }

    /**
     * creates assignment nodes for the config tree
     */
    function config_assessments($course, $type){
        switch($type) {
            case 'assignment':
                $assessments = $this->get_all_assignments();
                break;
            case 'quiz':
                $assessments = $this->get_all_quizzes();
                break;
            case 'forum':
                $assessments = $this->get_all_forums();
                break;
            case 'workshop':
                $assessments = $this->get_all_workshops();
                break;
            case 'journal':
                $assessments = $this->get_all_journals();
                break;
        }
        if ($assessments) {
            foreach ($assessments as $assessment) {
                if (!$this->assessment_grading_permission($type, $assessment->cmid)) {
                    continue;
                }
                if ($assessment->course == $course) {
                    $this->make_assessment_node($assessment->name, $assessment->id, $assessment->cmid, $assessment->summary, $type, NULL);
                }
            }
        }

    }


    /**
     * This counts the assessments that a course has available. Called when the config tree is built.
     * @assessments object All available assessments from the users courses
     * @course int  course id of the course we are counting for
     * @type string type of assessments e.g. 'forum'
     * @return int count of items
     */
    function count_course_assessment_nodes($assessments, $course, $type) {

        $count = 0;
        if ($assessments) {
            foreach ($assessments as $assessment) {
                // permissions check
                if (!$this->assessment_grading_permission($type, $assessment->cmid)) {
                    continue;
                }
                //is it for this course?
                if ($assessment->course == $course) {
                    $count++;
                }
            }
        }
        return $count;
    }

    /**
     * Checks whether the user has grading permission for this assessment
     * @type string the type of assessment e.g. 'assignment, 'workshop'
     * @cmid int the coursemodule id of the assessment being checked.
     */
    function assessment_grading_permission($type, $cmid) {

        $modulecontext = get_context_instance(CONTEXT_MODULE, $cmid);

        switch ($type) {
            case "assignment":
            $cap = 'mod/assignment:grade';
            break;

            case "workshop":
            $cap = 'mod/workshop:manage';
            break;

            case "forum":
            $cap = 'mod/forum:viewhiddentimedposts';
            break;

            case "quiz":
            $cap = 'mod/quiz:grade';
            break;

            case "journal":
            $cap = 'mod/assignment:grade';
            break;
        }

        if (has_capability($cap, $modulecontext, $this->userid, false)) {
            return true;
        } else {
            return false;
        }

    }

    /**
     * Makes an assessment node for either the main tree or the config tree
     * @param <type> $name
     * @param <type> $aid
     * @param <type> $cmid
     * @param <type> $summary
     * @param <string> $type the db name of the module, or the type of node e.g. 'user', 'group'
     * @param <type> $count
     */
    function make_assessment_node($name, $aid, $cmid, $summary, $type, $count) {

        // cut it at 200 characters
        $shortsum = substr($summary, 0, 200);
        if (strlen($shortsum) < strlen($summary)) {$shortsum .= "...";}

        $this->output .= ','; 
        $this->output .= '{';

        $length = ($this->type == 'config_course') ? -2 : 1;
       
        $this->output .= '"label":"'.$this->add_icon($type).$this->clean_name_text($name, $length);
        $this->output .= ($this->config) ? '",' : ' ('.$count.')",';
        $this->output .= '"name":"' .$this->add_icon($type).$this->clean_name_text($name, $length).'",';
       
        $this->output .= '"dynamic":"true",';
        $this->output .= '"id":"'.$aid.'",';
        $this->output .= '"assessmentid":"a'.$aid.'",';
        $this->output .= '"cmid":"'.$cmid.'",';
        $this->output .= '"type":"'.$type.'",';
        $this->output .= '"title":"'.$this->clean_summary_text($shortsum).'"';

        if ($count) {
            $this->output .= ',"count":"'.$count.'"';
        }

        $this->output .= '}';
    }

    

    /**
     * Function to hold the bits that get data ready for main level stuff. Allows code ruse between main AJAX
     * and non-AJAX <ul> list.
     */
    function get_main_level_data() {

        // new bit
        //foreach ($this->modules as $module) {
        //    $this->$module->get_all_unmarked();
        //}

        $temp = '';
        if (array_key_exists("assignment", $this->module_ids)) {
          
             $this->assignment->submissions = $this->assignment->get_all_unmarked();
             
        }

        if (array_key_exists("workshop", $this->module_ids)) {
            $func = 'get_all_unmarked_workshops';
            
            $temp = $this->$func();
         
                $this->workshop->submissions = $temp;
            
        }

        if (array_key_exists("forum", $this->module_ids)) {
            $this->forum->submissions = $this->forum->get_all_unmarked();
            //$this->modules->forum->submissions = $this->get_all_unmarked_forums();
        }

        if (array_key_exists("quiz", $this->module_ids)) {
            $this->quiz->submissions = $this->quiz->get_all_unmarked();
            //$this->modules->quiz->submissions = $this->get_all_unmarked_quizzes();
        }

        if (array_key_exists("journal", $this->module_ids)) {
            $this->journal->submissions = $this->get_all_unmarked_journals() ;
        }

    }


    /**
     * It turned out to be impossible to add icons reliably
     * with CSS, so this function generates the right img tag
     * @param <string> $type This is the name of the type of icon. For assessments it is the db name of the
     * module
     */
    function add_icon($type) {

        global $CFG, $THEME;

        $result = "<img class='amb-icon' src='{$CFG->wwwroot}/";

        // If custompix is not enabled, assume that the theme does not have any icons worth using
        // and use the main pix folder
        if ($THEME->custompix) {
            $result .= "theme/".current_theme()."/";
        }

        $result .= "pix/";

        switch ($type) {

            case 'course':
                $result .= "i/course.gif";
                break;

            case 'assignment':
                $result .= "mod/assignment/icon.gif";
                break;

            case 'workshop':
                $result .= "mod/workshop/icon.gif";
                break;

            case 'forum':
                $result .= "mod/forum/icon.gif";
                break;

            case 'quiz':
                $result .= "mod/quiz/icon.gif";
                break;

            case 'question':
                $result .= "i/questions.gif";
                break;

            case 'journal':
                $result .= "mod/journal/icon.gif";
                break;

            case 'group':
                $result .= "i/users.gif";
                break;

            case 'user':
                $result .= "i/user.gif";
                break;

        }
        $result .= "' />";
        return $result;
    }

}// end class

/*
 * This class forms the basis of the objects that hold and process the module data. The aim is for node data to be
 * returned ready for output in JSON or HTML format. Each module that's active will provide a class definition
 * in it's modname_grading.php file, which will extend this base class and add methods specific to that module
 * which can return the right nodes.
 */
class module_base {
    
     /**
     * This counts how many unmarked assessments of a particular type are waiting for a particular course
     * It is called from the courses() function when the top level nodes are built
     * @param object $submissions - object containing all of the unmarked submissions of a particular type
     * @param string $type        - type of submissions e.g. 'forums'
     * @param int $course         - id of the course we are counting submissions for
     * @return int                - the number of unmarked assessments
     */
    function count_course_submissions($course) {

        if (!isset($this->all_submissions)) {
            $this->get_all_unmarked();
        }

        // maybe there is nothing to mark
        if (isset($this->all_submissions) && !$this->all_submissions) {
            return 0;
        }

        $count = 0;
        $type = $this->type;

        // Run through all of the unmarked work, extracting the ids of the assessment items they belong to.
        $this->assessment_ids = $this->mainobject->list_assessment_ids($this->all_submissions, $course);

        // Now check all of these asseeement ids to see if the user has grading capabilities
        foreach ($this->assessment_ids as $key => $assessment) {
            
            if (!$this->mainobject->assessment_grading_permission($this->type, $assessment->cmid)) {
                unset($this->assessment_ids[$key]);
            }
        }

        // get groups settings

        //if ($type == 'workshop') {
        //    echo "count = ".count($this->all_submissions);
        //}
       
        foreach ($this->all_submissions as $submission) {

            $check = NULL;

            // Is this assignment attached to this course?
            if($submission->course != $course)  {
                continue;
            }
            
            //the object may contain assessments with no submissions
            if(!isset($submission->userid))  {
                continue;
            } else {
                // is the submission from a current user of this course
                if(!in_array($submission->userid, $this->mainobject->student_array->$course)) {
                    continue;
                }
            }

            // check against previously filtered list of assignments - permission to grade?
            if(!isset($this->assessment_ids[$submission->id]))  {
                continue;
            }

            $settings = $this->mainobject->get_groups_settings($this->type, $submission->id);
            // check if this should be displayed and if so, is the student in the right group
            if(!$this->mainobject->check_assessment_display_settings($settings)) {
                continue;
            }
           

            if(!$this->mainobject->check_submission_display_settings($settings, $submission->userid) ) {
                continue;
            }

            //if (!$this->mainobject->check_submission_display_settings($settings, $submission->userid)) {
            //    continue;
            //}
            // if there are no settings (default is show), 'display by group' is selected and the group matches, or 'display all' is selected, count it.
            $count++;
           
        }
        
        return $count;
    }

    /**
     * This function will check through all of the assessments of a particular type (depends on instantiation)
     * for a particular course, then return the nodes for a course ready for the main tree
     * @global <type> $CFG
     * @global <type> $SESSION
     * @param <type> $sql
     * @param <string> $type The type of assessment we are dealing with e.g. 'assignment'. Never plurals
     */
    function course_assessment_nodes($courseid) {

        global $CFG, $SESSION, $USER;

        // if the unmarked stuff is not yet retrieved, get it.
        if (!isset($this->course_unmarked->$courseid)) {

            // if the unmarked stuff for all courses has already been requested, filter it to save a DB query.
            // this will be the case only if making the non-ajax <ul> list
            if (isset($this->all_submissions) && !empty($this->submissions)) {

                $unmarked = new stdClass();

                foreach ($this->all_submissions as $key => $submission) {
                     if ($submission->course == $courseid) {
                         $unmarked->$key = $submission;
                     }
                 }
                 $this->course_unmarked->$courseid = $unmarked;
             } else {
                 // We have no data, so get it from the DB
                 $this->course_unmarked->$courseid = $this->get_all_course_unmarked($courseid);
                
             }
        }

        // now loop through the returned items, checking for permission to grade etc.

        // check that there is stuff to loop through
        if (isset($this->course_unmarked->$courseid) && !empty($this->course_unmarked->$courseid)) {

            // we need all the assessment ids for the loop, so we make an array of them
            $assessments = $this->mainobject->list_assessment_ids($this->course_unmarked->$courseid);

            foreach ($assessments as $assessment) {

                // counter for number of unmarked submissions
                $count = 0;

                // permission to grade?
                $modulecontext = get_context_instance(CONTEXT_MODULE, $assessment->cmid);
                if (!has_capability($this->capability, $modulecontext, $USER->id)) {
                    continue;
                }

                if(!$this->mainobject->config) { //we are making the main block tree, not the configuration tree

                    // retrieve the user-defined display settings for this assessment item
                    $settings = $this->mainobject->get_groups_settings($this->type, $assessment->id);

                    // check if this item should be displayed at all
                    if(!$this->mainobject->check_assessment_display_settings($settings)) {
                        continue;
                    }

                    // If the submission is for this assignment and group settings are 'display all', or 'display by groups' and
                    // the user is a group member of one of them, count it.
                    foreach($this->course_unmarked->$courseid as $assessment_submission) {
                        if ($assessment_submission->id == $assessment->id) {
                            if (!isset($assessment_submission->userid)) {
                                continue;
                            }
                            // if the item is set to group display, it may not be right to add the student's submission
                            // if they are in the wrong group
                            if (!$this->mainobject->check_submission_display_settings($settings, $assessment_submission->userid)) {
                                continue;
                            }
                            $count++;
                        }
                    }

                    // if there are no unmarked assignments, just skip this one. Important not to skip
                    // it in the SQL as config tree needs all assignments
                    if ($count == 0) {
                        continue;
                    }
                }

                // journals are a special case as they do not have submissions nodes normally.
                // They may have group nodes however.
                // TODO - make this into a general check for levels.
                if ($this->type == 'journal') {

                    // set the type to reflect whether or not there are group nodes to come.
                    $journal_type = '';
                    if ($settings && $settings->showhide == 2) {
                        $journal_type = "journal_groups";
                    } else {
                        $journal_type = "journal";
                    }
                    $seconds = (time() - $assessment->timemodified);
                    $this->mainobject->make_submission_node($assessment->name.' ('.$count.')', $assessment->id, $assessment->cmid, $assessment->description, $journal_type, $seconds, $assessment->timemodified, $count);
                } else {
                    // all other assessments
                    $this->mainobject->make_assessment_node($assessment->name, $assessment->id, $assessment->cmid, $assessment->description, $this->type, $count);
                }

            }// end foreach assignment
        } // end if assignment_submissions
    } // end course assessments





}



?>