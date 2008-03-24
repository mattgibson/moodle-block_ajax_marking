<?php
///////////////////////////////////////////////////////////////////////////
// Block AJAX Marking -  Matt Gibson 2007
//
// Some code adapted ot borrowed from block_marking by Mark J tyers and Bruno Vernier
//
// This file contains the procedures for getting stuff from the database
// in order to create the nodes of the marking block YUI tree. 
// currently it focuses on assignments, but will expand to include quizzes, 
// workshops and scorm 
/////////////////////////////////////////////////////////////////////////// 


  include('../../config.php');
  require_once('../../lib/accesslib.php');
  //include('../../lib/datalib.php');
  require_once('../../lib/dmllib.php'); 
  require_once('../../lib/moodlelib.php'); 

    require_login(1, false);
 //  $testvar = $USER->id;
  

////////////////////////////////////////////////////////
// info from GET is
// id: (user id, course id, assignment id)
// type of request: (courses, assignments, submissions)
// parent = node that this is to be appended to
////////////////////////////////////////////////////////

class ajax_marking_functions {

	function ajax_marking_functions() {
	// constructor retrieves GET data and works out what type of AJAX call has been made before running the correct function
	// TODO: should check capability with $USER here to improve security. currently, this is only checked when making course nodes.
	// TODO: optional_param here rather than isset

	    $this->output = '';
		if (isset($_GET['id'])) {$this->id = $_GET['id'];}
		if (isset($_GET['userid'])) {$this->userid = $_GET['userid'];}
		if (isset($_GET['quizid'])) {$this->quizid = $_GET['quizid'];}
		
		if (isset($_GET['type'])) {
		    $this->type= $_GET['type'];
			if ($this->type == "courses") {
			   $this->courses();
			   print_r($this->output);
			} 
			if ($this->type == "assessments") {	
				
				$this->output = '[{"type":"assessments"}'; 	// begin JSON array
				$this->assignments();
				$this->journals();
				$this->workshops();
				$this->forums();
				$this->quizzes();
				$this->output .= "]"; // end JSON array
				print_r($this->output);
			}
			if ($this->type == "assignment_submissions") {	
				$this->assignment_submissions();
				print_r($this->output);
			}
			if ($this->type == "workshop_submissions") {	
				$this->workshop_submissions();
				print_r($this->output);
			}
			if ($this->type == "forum_submissions") {
			    $this->forum_submissions();
				print_r($this->output);
			}
			if ($this->type == "quiz_submissions") {
			    $this->quiz_submissions();
				print_r($this->output);
			}
			if ($this->type == "quiz_questions") {
			    $this->quiz_questions();
				print_r($this->output);
			}
			if ($this->type == "journal_submissions") {
			    $this->journal_submissions();
				print_r($this->output);
			}
		}
	}	
	
	//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// --in progress--
	// This function makes sure that the person has grading capability and needs to be run every time the block is accessed
	//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	
	function check_permissions($user_id) {
	
		$courses = get_my_courses($user_id, $sort='fullname', $fields='*', $doanything=false, $limit=21) or die('get my courses error');
		$output_array = array();
		$checkvar = 0;
		
		foreach ($courses as $course) {	// iterate through each course, checking permisions, counting assignment submissions and 
								 	// adding the course to the JSON output if any appear				   
			if ($course->visible == 1)  { // show nothing if the course is hidden
				$checkvar = 0;
				// am I allowed to grade stuff in this course? Question is too broad - some assignments are hidden and some I may not 
				// have capabilities for. Will have to check each thing one at time.
	
				// TO DO: need to check in future for who has been assigned to mark them (new groups stuff) in 1.9
				//$coursecontext = get_context_instance(CONTEXT_COURSE, $course->id);
	
				if ($course->id == 1) {continue;} // exclude the front page
			
				
				// role check bit borrowed from block_narking, thanks to Mark J Tyers [ZANNET]
				$context = get_context_instance(CONTEXT_COURSE, $course->id);
				
				$teachers = get_role_users($teacher_role, $context, true); // check for editing teachers
				$j = false;
				if ($teachers) {
					foreach($teachers as $key2=>$val2) {
						if ($val2->id == $this->userid) {
							$j = true;
							$checkvar = 1;
						}
					}
				}
				$teachers_ne = get_role_users($ne_teacher_role, $context, true); // check for non-editing teachers
				if ($teachers_ne) {
					foreach($teachers_ne as $key2=>$val2) {
						if ($val2->id == $this->userid) {
							$j = true;
							$checkvar = 1;
						}
					}
				}
				
				if ($j) {return $output_array;} // only display if in a teacher or teacher_non_editing role
				else {return false;}
			}	
		}
	}
	
	
	
	//////////////////////////////////////
	// procedure for courses
	//////////////////////////////////////
	
	function courses() {
		$courses = '';
		global $CFG;
		// probably don't need all the fields here.
		$courses = get_my_courses($this->id, $sort='fullname', $fields='*', $doanything=false) or die('get my courses error');
		
		$i = 0; // course counter to keep commas in the right places later on
		
		// admins will have a problem as they will see all the courses on the entire site
		// TO DO - this has big issues around language. role names will not be the same in diffferent translations.
		
	    $teacher_role=get_field('role','id','shortname','editingteacher'); // retrieve the teacher role id (3)
        $ne_teacher_role=get_field('role','id','shortname','teacher'); // retrieve the non-editing teacher role id (4)
		
	   
		
		$this->output = '[{"type":"courses"}'; 	// begin JSON array
		
		foreach ($courses as $course) {	// iterate through each course, checking permisions, counting assignment submissions and 
									 	// adding the course to the JSON output if any appear
										
			// TO DO get an array of all students so they can be checked. deleted students still appear in the DB.
			// $students = 
			$count = 0;             // set assessments counter to 0						   
			if ($course->visible == 1)  { // show nothing if the course is hidden
				// am I allowed to grade stuff in this course? Question is too broad - some assignments are hidden and some I may not 
				// have capabilities for. Will have to check each thing one at time.

				// TO DO: need to check in future for who has been assigned to mark them (new groups stuff) in 1.9
				//$coursecontext = get_context_instance(CONTEXT_COURSE, $course->id);

				if ($course->id == 1) {continue;} // exclude the front page
				$students = $this->get_course_students($course->id);
				if ($students) {
				// role check bit borrowed from block_narking, thanks to Mark J Tyers [ZANNET]
				
				$course_context = get_context_instance(CONTEXT_COURSE, $course->id);
			//print_r($student_array);
			//echo "($student_array)";
					
					$teachers = get_role_users($teacher_role, $course_context, true); // check for editing teachers
					$j = false;
					if ($teachers) {
						foreach($teachers as $key2=>$val2) {
							if ($val2->id == $this->userid) {
								$j = true;
							}
						}
					}
					$teachers_ne = get_role_users($ne_teacher_role, $course_context, true); // check for non-editing teachers
					if ($teachers_ne) {
						foreach($teachers_ne as $key2=>$val2) {
							if ($val2->id == $this->userid) {
								$j = true;
							}
						}
					}
					if (!$j) {continue;} // only display if in a teacher or teacher_non_editing role
					
					// 1. get all assignments
					$assignments = get_records('assignment', 'course', $course->id);
					if ($assignments) {
						foreach ($assignments as $assignment) {
						
					// 2. check each for visibility		
							$module = get_record('modules', 'name', 'assignment');   // what is the module id for assignments?
							//get coursemodule as assignment does not have visibility
							$coursemodule = get_record('course_modules', 'course', $course->id, 'module', $module->id, 'instance', $assignment->id);
						
							if ($coursemodule->visible == 1) {
							//if ($course->id == 3) {echo "assignment id: $assignment->id ";}
					// 3. check for permission to grade	  
								$modulecontext = get_context_instance(CONTEXT_MODULE, $coursemodule->id);  
								if (has_capability('mod/assignment:grade', $modulecontext, $this->userid)) {
							
					// 4. check for submissions
									$sql = "SELECT count(id) FROM ".$CFG->prefix."assignment_submissions 
									WHERE (assignment = '".$assignment->id."' AND timemarked < timemodified) AND userid IN ($students)";
								//echo $sql;	
					// 5. add to total count
									if ($total = count_records_sql($sql)) {
										 if ($total > 0) {$count = $count + $total;} 
									}
								}
							}
						}
					}
					//echo "assignment: ".$count;
					
					$workshops = get_records('workshop', 'course', $course->id);
					if ($workshops) {
						foreach ($workshops as $workshop) {
						//echo "workshop iteration";
						$total = 0;
						/// is this workshop visible?
						$module = get_record('modules', 'name', 'workshop');   // what is the module id for assignments?
						//get coursemodule as workshop does not have visibility
						$coursemodule = get_record('course_modules', 'course', $course->id, 'module', $module->id, 'instance', $workshop->id); 
							if ($coursemodule->visible == 1) {
							
							// 3. check for permission to grade	  
								$modulecontext = get_context_instance(CONTEXT_MODULE, $coursemodule->id);  
								if (has_capability('mod/workshop:manage', $modulecontext, $this->userid)) {
						
									// count workshop submissions for this workshop where there is no corresponding record of a teacher assessment
									$sql = "SELECT COUNT(DISTINCT s.id) FROM ".$CFG->prefix."workshop_submissions s LEFT JOIN ".$CFG->prefix;
									$sql .= "workshop_assessments a ON s.id = a.submissionid WHERE s.workshopid = '".$workshop->id;
									$sql .= "' AND s.userid IN ($students) 
                                                                 AND (
                                                                                                                                            NOT EXISTS (
                                                                                                                                                                 SELECT 1 FROM ".$CFG->prefix."workshop_assessments wa
                                                                                 LEFT JOIN ".$CFG->prefix."workshop_submissions ws
                                                                                 ON ws.id = wa.submissionid  
                                                                                   WHERE wa.userid = '".$this->userid."'
                                                                                      
                                                                                  )

                                                                       OR (a.userid = '".$this->userid."' AND a.grade = -1) 
                                                                      )";
									
									if ($total = count_records_sql($sql)) {
										if ($total > 0) {$count = $count + $total;} 
										//echo "workshop total: ".$total;
									}
								}							
							}
						}
					}
			
					// now check for forums with ratings enabled and unrated posts
					
					// some code borrowed from block_marking - cheers to Mark and Bruno.
					$forums = get_records('forum', 'course', $course->id);
					if ($forums) {
						foreach ($forums as $forum) {
						
						/// is this forum visible?
						$module = get_record('modules', 'name', 'forum');   // what is the module id for assignments?
						//get coursemodule as workshop does not have visibility
						$coursemodule = get_record('course_modules', 'course', $course->id, 'module', $module->id, 'instance', $forum->id); 
							if ($coursemodule->visible == 1) {
							
							// are ratings enabled?
								if ($forum->assessed != 0) {
								
									// 3. check for permission to grade	  
									$modulecontext = get_context_instance(CONTEXT_MODULE, $coursemodule->id); 
									 
									if (has_capability('mod/forum:rate', $modulecontext, $this->userid)) {
								
										$select = "assessed !=0  and forum = $forum->id and course = $course->id" ;
										$discussions = get_records_select('forum_discussions', $select );
										
										if ($discussions) {
										
											 foreach ($discussions as $discussion) {
											     // if this forum is set to 'each student posts one discussion', we want to only grade the first one
												if ($forum->type == 'eachuser') {
													$first_post = get_record('forum_posts', 'id', $discussion->firstpost);
													$select = " post = $first_post->id ";
													$student = get_record_select('forum_ratings', $select, 'count(id) as marked' ); // count the ratings so far for this post
													if ($student->marked==0) {
														$count += 1;
													}
												} else {
													// any other type of graded forum, we can grade any posts that are not yet graded
													 $select = " discussion = $discussion->id AND userid != $this->id AND userid IN ($students)"; 
													 $posts = get_records_select('forum_posts', $select, 'id');
													 
													 if ($posts) {
										
														 foreach ($posts as $post) {
														 
															 $select = " post = $post->id ";
															 $student = get_record_select('forum_ratings', $select, 'count(id) as marked' ); // count the ratings so far
															 if ($student->marked==0) {
															 
																 $count = $count + 1;													 }
														 }
													 }
												 }
											 }
										 }
									 }
								 }
							 }
						 }
					 }
					 //echo "forum: ".$count;
	
					 
					// Quiz essays code - borrowed and adapted from Mark and Bruno 
					
					
					require_once ("{$CFG->dirroot}/mod/quiz/locallib.php");
					$select = " course='$course->id'";
					$quizzes = get_records_select('quiz', $select);
					
					if ($quizzes) {
					
						foreach($quizzes as $quiz) {
						
							// check each for visibility		
							$module = get_record('modules', 'name', 'quiz');   
							// get coursemodule as assignment does not have visibility
							$coursemodule = get_record('course_modules', 'course', $course->id, 'module', $module->id, 'instance', $quiz->id);
							if ($coursemodule->visible == 1) {
							
								// check for permission to grade
								$modulecontext = get_context_instance(CONTEXT_MODULE, $coursemodule->id);  
								
								if (has_capability('mod/quiz:grade', $modulecontext, $this->userid)) {
								
									$all_question = array();  // array that will hold all the questions in all the quizzes
									$quiz_questions = quiz_questions_in_quiz($quiz->questions);
									
									if ($quiz_questions) {
										$questions = get_records_select('question', "id IN ($quiz_questions)", 'id', 'id, qtype');
										foreach ($questions as $key=>$q) {
											if ($q->qtype != 'essay') { unset($questions[$key]); }
										}
									}
									
									$all_question[$quiz->id] = $questions;
									
									foreach($all_question as $quiz_id=>$questions) {
									
										if ($questions) {      // if any questions at all came back earlier
											foreach ($questions as $q) {
												$attempts = get_records_sql("SELECT qa.* FROM {$CFG->prefix}quiz_attempts qa, {$CFG->prefix}question_sessions qs ".
												"WHERE	quiz = $quiz_id AND qa.timefinish > 0 AND qa.userid IN ($students) AND qa.preview = 0 AND qs.questionid = '$q->id'");
												if ($attempts) {
													foreach ($attempts as $attempt) {
														$sql = "SELECT state.id, state.event, sess.manualcomment
														FROM {$CFG->prefix}question_states state, {$CFG->prefix}question_sessions sess WHERE sess.newest = state.id 
														AND sess.attemptid = $attempt->uniqueid	AND sess.questionid = '$q->id'";
														$state = get_record_sql($sql);
														if (!question_state_is_graded($state)) {
															$count = $count + 1;
														}
													}
												}
											}
										}
									}	
								}
							}					
						}
					}
					
					
				// JOURNAL ====================================================================================
					$select = "course='$course->id'";
					$journals = get_records_select('journal', $select);
					$context = get_context_instance(CONTEXT_COURSE, $course->id);
					if ($journals) {
						foreach($journals as $journal) {
							
							if (has_capability('mod/assignment:grade', $context)) { // could not find journal capabilities
							
								$module = get_record('modules', 'name', 'journal');   // what is the module id for journals?
								$coursemodule = get_record('course_modules', 'course', $course->id, 'module', $module->id, 'instance', $journal->id) ;
								if ($coursemodule->visible == 1) {
								
									// counts how many students still need their assignment marking
									$select = "modified > timemarked AND userid IN ($students)  AND journal = " . $journal->id;
									$entries = count_records_select('journal_entries', $select, 'count(id)');
									// if there is some work needing marking we display the assignment name together with the unmarked number in parenthesis.
									if ($entries > 0) {
									
										// there are some entries so we add the journal count
										$count = $count + $entries;
											
											
										
									}
								}
								
							}
						} // end foreach journal
					}
					
					
					// echo "quiz: ".$count;
	
					
					if ($count > 0) { // there are some assessments		
		
						// now add course to JSON array of objects
						$cid  = $course->id;
						//$sum  = $course->summary;
						//$sumlength = strlen($sum);
						//$shortsum = substr($sum, 0, 100);
						//if (strlen($shortsum) < strlen($sum)) {$shortsum .= "...";}
							
						$this->output .= ','; // add a comma if there was a preceding course
						$this->output .= '{';
						$this->output .= '"name":"'.$this->clean_name_text($course->shortname, 0).'",';
						$this->output .= '"id":"'.$cid.'",';
						$this->output .= '"type":"assessments",';
						// commented as it only relates to course tooltips, which are disabled
						//$this->output .= '"summary":"'.$this->clean_summary_text($shortsum).'",';
						$this->output .= '"count":"'.$count.'",';
						$this->output .= '"cid":"c'.$cid.'"';
						$this->output .= '}';
						
						 // increment the course counter so commas can be inserted ok
						
					} // emd if there are some assignments
				}	// end if course students
			}// end if course visible
		} // end for each courses
		$this->output .= "]"; //end JSON array
	} // end function
	
	////////////////////////////////////////////////////
	//  procedure for assignments
	////////////////////////////////////////////////////
	
	/*
	need to make a dummy fletable to prevent generation of javascript designed to update the parent page from the assignment pop up.
	 $dummytable = new flexible_table('mod-assignment-submissions');
	 $dummytable->collapse['submissioncomment'] = 1;
	
	
	*/
	
	function assignments() {
		global $CFG, $SESSION;
		//$id = $this->id;
		$students = $this->get_course_students($this->id); // we must make sure we only get work from enrolled students
		
		// the assignment pop up thinks it was called from the table of assignment submissions, so to avoid javascript errors, 
		// we need to set the $SESSION variable to think that all the columns in the table are collapsed so no javascript is generated
		// to try to update them. 
		
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
		
		$assignments = get_records('assignment', 'course', $this->id);
		if ($assignments) {
			foreach ($assignments as $assignment) {
			
				/// is this assignment visible?
				$module = get_record('modules', 'name', 'assignment');   // what is the module id for assignments?
				//get coursemodule as assignment does not have visibility

				$coursemodule = get_record('course_modules', 'course', $this->id, 'module', $module->id, 'instance', $assignment->id) ;
				if ($coursemodule->visible == 1) {
								
					$modulecontext = get_context_instance(CONTEXT_MODULE, $coursemodule->id);
				
			/// check if there are there unmarked submissions for this assignment
					$sql = "SELECT COUNT(id) FROM ".$CFG->prefix."assignment_submissions 
							WHERE (assignment = '".$assignment->id."' AND timemarked < timemodified) AND userid IN ($students)";
					$count = count_records_sql($sql);
					if ($count > 0) { 
		
			/// if so, add the asssignment to JSON array of objects
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
						$this->output .= '"type":"assignment_submissions",';
						$this->output .= '"summary":"'.$this->clean_summary_text($shortsum).'",';
						$this->output .= '"count":"'.$count.'"';
						$this->output .= '}';
						
					}
				}
			}
		} // end if assignments
		// put the session collapse back so the grading screen isn't in a mess.
		if (isset($SESSION->flextable['mod-assignment-submissions']->collapse)) {
			$SESSION->flextable['mod-assignment-submissions']->collapse['submissioncomment'] = false;
			$SESSION->flextable['mod-assignment-submissions']->collapse['grade']             = false;
			$SESSION->flextable['mod-assignment-submissions']->collapse['timemodified']      = false;
			$SESSION->flextable['mod-assignment-submissions']->collapse['timemarked']        = false;
			$SESSION->flextable['mod-assignment-submissions']->collapse['status']            = false;
		}
		
	} // end asssignments function
	
	
	
	
	//////////////////////////////////////////////////////
	// Procedure for workshops
	//////////////////////////////////////////////////////
	
	function workshops() {
	    global $CFG;
		//$id = $this->id;
		$students = $this->get_course_students($this->id);
		//echo "course id: ".$id." ";
		$workshops = get_records('workshop', 'course', $this->id); //  or die("get workshop records failed");
		if ($workshops) {
			foreach ($workshops as $workshop) {
			$count = 0;
			/// is this workshop visible?
			$module = get_record('modules', 'name', 'workshop');   // what is the module id for assignments?
			//get coursemodule as workshop does not have visibility
			$coursemodule = get_record('course_modules', 'course', $this->id, 'module', $module->id, 'instance', $workshop->id) or die('workshops coursemodule error'); 
				if ($coursemodule->visible == 1) {
					
					$modulecontext = get_context_instance(CONTEXT_MODULE, $coursemodule->id);
					
			    	
					// count workshop submissions for this workshop where there is no corresponding record of a teacher assessment
					$sql = "SELECT COUNT(DISTINCT s.id) FROM ".$CFG->prefix."workshop_submissions s LEFT JOIN ".$CFG->prefix;
					$sql .= "workshop_assessments a ON s.id = a.submissionid WHERE s.workshopid = '".$workshop->id;
					$sql .= "' AND s.userid IN ($students)  
                                        AND (
                                                                                                                                            NOT EXISTS (
                                                                                                                                                                 SELECT 1 FROM ".$CFG->prefix."workshop_assessments wa
                                                                                 LEFT JOIN ".$CFG->prefix."workshop_submissions ws
                                                                                 ON ws.id = wa.submissionid  
                                                                                   WHERE wa.userid = '".$this->userid."'
                                                                                      
                                                                                  )


                                             OR (a.userid = '".$this->userid."' AND a.grade = -1) 
                                            ) 


                                         ORDER BY 's.timecreated DESC'";
				
				  
					$count = count_records_sql($sql);
					//echo "<br />".$sql."<br />";
					//echo "--count: ".$count."--";
					if ($count > 0) {
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
						$this->output .= '"type":"workshop_submissions",';
						$this->output .= '"summary":"'.$this->clean_summary_text($shortsum).'",';
						$this->output .= '"count":"'.$count.'"';
						$this->output .= '}';
						
					}
				}
			}
		}
	}
	
	//////////////////////////////////////////////////////////
	// function for adding forums with unrated posts
	//////////////////////////////////////////////////////////
	
	
	function forums() {
	$students = $this->get_course_students($this->id); // get a list of students in this course
		$forums = get_records('forum', 'course', $this->id);
		if ($forums) {
			foreach ($forums as $forum) {
				$count = 0;
				/// is this forum visible?
				$module = get_record('modules', 'name', 'forum');   // what is the module id for assignments?
				//get coursemodule as workshop does not have visibility
				$coursemodule = get_record('course_modules', 'course', $this->id, 'module', $module->id, 'instance', $forum->id); 
				if ($coursemodule->visible == 1) {
					
					// are ratings enabled?
					if ($forum->assessed != 0) {
					
					    // check for permission to grade	  
						$modulecontext = get_context_instance(CONTEXT_MODULE, $coursemodule->id);
						
						if (has_capability('mod/forum:rate', $modulecontext, $this->userid)) {
						
						
							$select = "assessed !=0  and forum = $forum->id and course = $this->id" ;
							$discussions = get_records_select('forum_discussions', $select );
							if ($discussions) {
								foreach ($discussions as $discussion) {
							   						// if this forum is set to 'each student posts one discussion', we want to only grade the first one
									if ($forum->type == 'eachuser') {
										$first_post = get_record('forum_posts', 'id', $discussion->firstpost);
										$select = " post = $first_post->id ";
										$student = get_record_select('forum_ratings', $select, 'count(id) as marked' ); // count the ratings so far for this post
										if ($student->marked==0) {
											$count += 1;
										}
									} else {
										// any other type of graded forum, we can grade any posts that are not yet graded
										$select = " discussion = $discussion->id and userid != $this->userid AND userid IN ($students)"; // added this bit to make sure own posts are not included
										$posts = get_records_select('forum_posts', $select, 'id');
										if ($posts) {
											foreach ($posts as $post) {
												
												$select = " post = $post->id ";
												$student = get_record_select('forum_ratings', $select, 'count(id) as marked' ); // count the ratings so far
												if ($student->marked==0) {
													
													$count = $count + 1;
												}
											}
										}
									}
								}
							}
							// add the node if there were any posts
							if ($count > 0) {
								$fid = $forum->id;
								$sum = $forum->intro;
								$sumlength = strlen($sum);
								$shortsum = substr($sum, 0, 100);
								if (strlen($shortsum) < strlen($sum)) {$shortsum .= "...";}
								$this->output .= ','; // add a comma before section only if there was a preceding assignment
									
								$this->output .= '{';
								$this->output .= '"name":"'.$this->clean_name_text($forum->name, 1).'",';
								$this->output .= '"id":"'.$fid.'",';
								$this->output .= '"assid":"f'.$fid.'",';
								$this->output .= '"type":"forum_submissions",';
								$this->output .= '"summary":"'.$this->clean_summary_text($shortsum).'",';
								$this->output .= '"count":"'.$count.'"';
								$this->output .= '}';
								
								
							}
						}
					} // if assessed
				} // if visible
			} // foreach forum
		} // if forums
	} // end function
	
	
	
	function quizzes() {
		$students = $this->get_course_students($this->id);
		global $CFG;
		require_once ("{$CFG->dirroot}/mod/quiz/locallib.php");
		$select = " course='$this->id'";
		$quizzes = get_records_select('quiz', $select);
		if ($quizzes) {
			foreach($quizzes as $quiz) {
			    $count = 0;
				// check each for visibility		
				$module = get_record('modules', 'name', 'quiz');   
				// get coursemodule as assignment does not have visibility
				$coursemodule = get_record('course_modules', 'course', $this->id, 'module', $module->id, 'instance', $quiz->id);
				if ($coursemodule->visible == 1) {
					// check for permission to grade
					$modulecontext = get_context_instance(CONTEXT_MODULE, $coursemodule->id);  
					if (has_capability('mod/quiz:grade', $modulecontext, $this->userid)) {
			
						$all_question = array();                                 // array that will hold all the questions in all the quizzes
						$quiz_questions = quiz_questions_in_quiz($quiz->questions);
						if ($quiz_questions) {
							$questions = get_records_select('question', "id IN ($quiz_questions)", 'id', 'id, qtype');
							foreach ($questions as $key=>$q) {
								if ($q->qtype != 'essay') { unset($questions[$key]); }
							}
						}
						$all_question[$quiz->id] = $questions;
						foreach($all_question as $quiz_id=>$questions) {
							if ($questions) {                                         // if any questions at all came back earlier
								foreach ($questions as $q) {
									$attempts = get_records_sql("SELECT qa.* FROM {$CFG->prefix}quiz_attempts qa, {$CFG->prefix}question_sessions qs ".
									"WHERE	quiz = $quiz_id AND qa.userid IN ($students) AND qa.timefinish > 0 ".
									" AND qa.preview = 0 AND qs.questionid = '$q->id'");
									if ($attempts) {
										$data=array(); 
										$str = '';
										$num = 0;
										foreach ($attempts as $attempt) {
											$sql = "SELECT state.id, state.event, sess.manualcomment
											FROM {$CFG->prefix}question_states state, {$CFG->prefix}question_sessions sess WHERE sess.newest = state.id 
											AND sess.attemptid = $attempt->uniqueid	AND sess.questionid = '$q->id'";
											$state = get_record_sql($sql);
											if (!question_state_is_graded($state)) {
												$count = $count + 1;
											}
										}
									}
								}
							}
						}
					}
				}
				// add the node for this quiz
				if ($count > 0) {
					
					//$name = $quiz->name;
					$fid = $quiz->id;
					$sum = $quiz->intro;
					$sumlength = strlen($sum);
					$shortsum = substr($sum, 0, 100);
					if (strlen($shortsum) < strlen($sum)) {$shortsum .= "...";}
					$this->output .= ','; // add a comma before section only if there was a preceding assignment
						
					$this->output .= '{';
					$this->output .= '"name":"'.$this->clean_name_text($quiz->name, 1).'",';
					$this->output .= '"id":"'.$fid.'",';
					$this->output .= '"assid":"q'.$fid.'",';
					$this->output .= '"type":"quiz_questions",';
					$this->output .= '"summary":"'.$this->clean_summary_text($shortsum).'",';
					$this->output .= '"count":"'.$count.'"';
					$this->output .= '}';
					
				}							
			}
		}
	}
	
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Get all the journal assignments
	// ready to test
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	
	function journals() {
		global $CFG;
		$students = $this->get_course_students($this->id);
	// JOURNAL ====================================================================================
		$select = "course='$this->id'";
		$journals = get_records_select('journal', $select);
		if ($journals) {
			foreach($journals as $journal) {
				
				$entries = 0;
				$module = get_record('modules', 'name', 'journal');   // what is the module id for journals?
				$coursemodule = get_record('course_modules', 'course', $this->id, 'module', $module->id, 'instance', $journal->id) ;
				if ($coursemodule->visible == 1) {
				
					$context = get_context_instance(CONTEXT_COURSE, $this->id);
					if (has_capability('mod/assignment:grade', $context)) { // could not find journal capabilities - will this work?
						// counts how many students still need their assignment marking
						$select = "modified > timemarked AND (userid IN ($students))  AND journal = " . $journal->id;
						$entries = count_records_select('journal_entries', $select, 'count(id)');
						// if there is some work needing marking we display the assignment name together with the unmarked number in parenthesis.
						if ($entries >0) {
						
							// there are some entries so we add the journal node
							
							$aid = $journal->id;
							$sum = $journal->intro;                                 // make summary
							$sumlength = strlen($sum);                                       // how long it it?
							$shortsum = substr($sum, 0, 100);                                // cut it at 100 characters
							if (strlen($shortsum) < strlen($sum)) {$shortsum .= "...";}      // if that cut the end off, add an ellipsis
							$this->output .= ','; // add a comma before section only if there was a preceding assignment
							
							$this->output .= '{';
							$this->output .= '"name":"'.$this->clean_name_text($journal->name, 1).'",';
							$this->output .= '"id":"'.$coursemodule->id.'",';
							$this->output .= '"assid":"j'.$aid.'",';
							$this->output .= '"type":"journal_submissions",';
							$this->output .= '"summary":"'.$this->clean_summary_text($shortsum).'",';
							$this->output .= '"count":"'.$entries.'"';
							$this->output .= '}';
							
								
							
						}
					}
					
				}
			} // end foreach journal
		}
	}
	
	
	//////////////////////////////////////////////////////////////////////////////////////
	// function to get groups if needed
	//////////////////////////////////////////////////////////////////////////////////////
	
	function groups() {
	    // this function should check if there are groups enabled for the activity. If no groups setting is specified, 
		// it needs to go to the course default. If no groups, this needs to skip straight to the calling function. 
		// Otherwise, it should make the groups nodes.
		
		if($x) { //check if the activity has groups enabled (case?)
		} else { //get it from the course
		}
		if($x) { //groups type is none
			return false;
		} else {
			 if($x) { //count the groups - more than 1?
			 	//loop through them
					// get group details
					// count how many assignments there are in that group
					// make the group node
			}
		}
	}
	
	
	
	
	
	
	
	//////////////////////////////////////////////////////////////
	//  procedure for assignment submissions
	//////////////////////////////////////////////////////////////
	
	function assignment_submissions() {
		global $CFG;
		//$id = $this->id;
		
		// need to get course id in order to retrieve students
		$assignment = get_record('assignment', 'id', $this->id);
		$students = $this->get_course_students($assignment->course);
		
		$sql = "SELECT * FROM ".$CFG->prefix."assignment_submissions WHERE assignment";
		$sql .= " = $this->id AND userid IN ($students) ORDER BY timemodified ASC";
		
		$submissions = get_records_sql($sql);
		
		if ($submissions) {
		
			// begin json object
			$this->output = '[{"type":"submissions"}';
			
			foreach ($submissions as $submission) {
			// add submission to JSON array of objects
				if ($submission->timemarked < $submission->timemodified) { // needs marking
					// get users full name
					$rec = get_record('user', 'id', $submission->userid);
					$name = $rec->firstname." ".$rec->lastname;
					
					// get coursemodule id
					$rec2 = get_record('course_modules', 'module', '1', 'instance', $submission->assignment) or die ("get record module error");
					$aid = $rec2->id;
					
					// sort out the time info
					$now = time();
					$seconds = ($now - $submission->timemodified);
						$summary = $this->make_time_summary($seconds);
					$sid = $submission->userid;
					
					// put it all together into the array
					$this->output .= ','; 
					
					$this->output .= '{';
						$this->output .= '"name":"'.$this->clean_name_text($name, 2).'",';
						$this->output .= '"sid":"'.$sid.'",'; // id of submission for hyperlink
						$this->output .= '"aid":"'.$aid.'",'; // id of assignment for hyperlink
						$this->output .= '"summary":"'.$this->clean_summary_text($summary).'",';
						$this->output .= '"type":"assignment_answer",';
						$this->output .= '"seconds":"'.$seconds.'",'; // seconds sent to allow style to change according to how long it has been
						$this->output .= '"time":"'.$submission->timemodified.'",'; // send the time of submission for tooltip
						$this->output .= '"count":"1"';
						$this->output .= '}';
						
				}
			}
			$this->output .= "]"; // end JSON array
		}
	}
	
	function workshop_submissions() {
	
	    $workshop = get_record('workshop', 'id', $this->id);
		$students = $this->get_course_students($workshop->course);
		global $CFG;
		//$id = $this->id;
		//http://study-space.com/mod/workshop/assess.php?id=40&sid=2
	
		// fetch workshop submissions for this workshop where there is no corresponding record of a teacher assessment
		$sql = "
                     SELECT s.id, s.userid, s.title, s.timecreated, s.workshopid 
                     FROM ".$CFG->prefix."workshop_submissions s LEFT JOIN ".$CFG->prefix."workshop_assessments a 
                     ON s.id = a.submissionid 
                           WHERE s.workshopid = '".$this->id."' 
                                 AND s.userid IN ($students) 
                                 AND (
                                                                           AND (
                                                                                                                                            NOT EXISTS (
                                                                                                                                                                 SELECT 1 FROM ".$CFG->prefix."workshop_assessments wa
                                                                                 LEFT JOIN ".$CFG->prefix."workshop_submissions ws
                                                                                 ON ws.id = wa.submissionid  
                                                                                   WHERE wa.userid = '".$this->userid."'
                                                                                      
                                                                                  )


                                      OR (a.userid = '".$this->userid."' AND a.grade = -1) 
                                     ) 
                     ORDER BY 's.timecreated DESC'
                      ";
		
		$submissions = get_records_sql($sql);		
		
		// begin json object
		$this->output = '[{"type":"submissions"}';
		
		foreach ($submissions as $submission) {
		    // get the user's name from the user table
			$rec = get_record('user', 'id', $submission->userid);
			//echo "rec id: ".$rec->id."<br />";
			$name = $rec->firstname." ".$rec->lastname;
			
			// get coursemoduleid
			$rec2 = get_record('course_modules', 'module', '17', 'instance', $submission->workshopid) or die ("get record module error");
			$wid = $rec2->id;
			$sid = $submission->id;
			
			// sort out the time stuff
			$now = time();
			$seconds = ($now - $submission->timecreated);
            		$summary = $this->make_time_summary($seconds);
			
			$this->output .= ','; // add a comma if there was a preceding submission
			
			$this->output .= '{';
			$this->output .= '"name":"'.$this->clean_name_text($name, 2).'",';
			$this->output .= '"sid":"'.$sid.'",'; // id of submission for hyperlink - in this case it is the submission id, not the user id
			$this->output .= '"aid":"'.$wid.'",'; // id of workshop for hyperlink
			$this->output .= '"seconds":"'.$seconds.'",'; // seconds sent to allow style to change according to how long it has been
			$this->output .= '"summary":"'.$this->clean_summary_text($summary).'",';
			$this->output .= '"type":"workshop_answer",';
			$this->output .= '"time":"'.$submission->timecreated.'",'; // send the time of submission for tooltip
			$this->output .= '"count":"1"';
			$this->output .= '}';
		   
		
		}
		$this->output .= "]"; // end JSON array
	}
	
	//////////////////////////////////////////////////////
	// function to make nodes for forum submissions
	//////////////////////////////////////////////////////
	
	function forum_submissions() {
	
		$forum = get_record('forum', 'id', $this->id);
		$students = $this->get_course_students($forum->course);
		$discussions = '';
		$discussions = get_records('forum_discussions', 'forum', $this->id);
		if ($discussions) {
		//	echo " id = $this->id, "; 
		   // echo count($discussions);
		
		$this->output = '[{"type":"submissions"}';      // begin json object.
		$i = 0;                   // counter to keep track of where commas go in in the loop.
		                 
			foreach ($discussions as $discussion) {
			    $count = 0;
				$sid = 0; // this variable will hold the id of the first post which is unrated, so it can be used 
							  // in the link to load the pop up with the discussion page at that position.
				$time = time(); // start seconds at current time so we can compare with time created to find the oldest as we cycle through
				
				// if this forum is set to 'each student posts one discussion', we want to only grade the first one
				if ($forum->type == 'eachuser') {
					$first_post = get_record('forum_posts', 'id', $discussion->firstpost);
				    $select = " post = $first_post->id ";
					$student = get_record_select('forum_ratings', $select, 'count(id) as marked' ); // count the ratings so far for this post
					if ($student->marked==0) {
						$count += 1;
					}
				} else {
					// any other type of graded forum, we can grade any posts that are not yet graded
					
					$select = " discussion = $discussion->id and userid != $this->userid AND userid IN ($students)"; // added this bit to make sure own posts are not included
					$posts = get_records_select('forum_posts', $select, 'id');
					$time = time(); // start seconds at current time so we can compare with time created to find the oldest as we cycle through
					if ($posts) {
						foreach ($posts as $post) {
							$select = " post = $post->id ";
							$student = get_record_select('forum_ratings', $select, 'count(id) as marked' ); // count the ratings so far for this post
							if ($student->marked==0) {
								if ($count == 0) {
									$sid = $post->id; // store id of first unmarked post for the link
								}
								if ($post->created < $time) {
									$time = $post->created; // store the time created for the tooltip if its the oldest post yet for this discussion
								}
								$count += 1;
							}
						}
					}
				}
			
				// add the node if there were any posts -  the node is the discussion with a count of the number of unrated posts
				if ($count > 0) {
				
				    // make all the variables ready to put them together into the array
				    $seconds = time() - $discussion->timemodified;
					$first_post = get_record('forum_posts', 'id', $discussion->firstpost);
					if ($forum->type == 'eachuser') { // we will show the student name as the node name as there is only one post that matters
						$rec = get_record('user', 'id', $first_post->userid);
						$name = $rec->firstname." ".$rec->lastname;
					} else { // the name will be the name of the discussion
						$name = substr($discussion->name, 0, 14);
						$name = $name." (".$count.")";
					}
					$sum = $first_post->message;
					$sum = strip_tags($sum);
					$sumlength = strlen($sum);
					$shortsum = substr($sum, 0, 100);
					if (strlen($shortsum) < strlen($sum)) {$shortsum .= "...";}
					$timesum = $this->make_time_summary($seconds, true);
					$discuss = get_string('discussion', 'block_ajax_marking');
					$summary = "<strong>".$discuss.":</strong> ".$shortsum."<br />".$timesum;
					
					$this->output .= ','; // add a comma before section only if there was a preceding assignment
						
					$this->output .= '{';
					$this->output .= '"name":"'.$this->clean_name_text($name, 1).'",';
					$this->output .= '"sid":"'.$sid.'",';
					$this->output .= '"type":"discussion",';
					$this->output .= '"aid":"'.$discussion->id.'",';
					$this->output .= '"summary":"'.$this->clean_summary_text($summary, false).'",';
					$this->output .= '"time":"'.$time.'",';
					$this->output .= '"count":"'.$count.'",';
					$this->output .= '"seconds":"'.$seconds.'"';
					$this->output .= '}';
				
				}
			}
			$this->output .= "]"; // end JSON array
		}// if discussions
	} // end function
	
	//////////////////////////////////////////////////////////////
	// Function to get all the quiz attempts
	////////////////////////////////////////////////////////////////
	
	function quiz_questions() {
	
	    $quiz = get_record('quiz', 'id', $this->id);
		$students = $this->get_course_students($quiz->course);
		
		global $CFG;
		require_once ("{$CFG->dirroot}/mod/quiz/locallib.php");
		
		
		$i = 0;                   // counter to keep track of where commas go in in the loop.
		
		$select = " id='$this->id'";
		$quiz = get_record_select('quiz', $select);
		//$all_question = array();                                 // array that will hold all the questions in all the quizzes?
		if ($quiz) {
			//print_r($quiz);
			$quiz_questions = quiz_questions_in_quiz($quiz->questions);
		}
		if ($quiz_questions) {
			$questions = get_records_select('question', "id IN ($quiz_questions)", 'id', 'id, qtype, name, questiontext');
			foreach ($questions as $key=>$q) {
				if ($q->qtype != 'essay') { unset($questions[$key]); }
			}
		}
		if ($questions) {     
			$this->output = '[{"type":"assessments"}';      // begin json object.                                    // if any questions at all came back earlier
			foreach ($questions as $q) {
				$count = 0;
				$attempts = get_records_sql("SELECT qa.* FROM {$CFG->prefix}quiz_attempts qa, {$CFG->prefix}question_sessions qs ".
				"WHERE	quiz = $quiz->id AND qa.userid IN ($students) AND qa.timefinish > 0 ".
				" AND qa.preview = 0 AND qs.questionid = '$q->id'");
				if ($attempts) {
					foreach ($attempts as $attempt) {
						$sql = "SELECT state.id, state.event
						FROM {$CFG->prefix}question_states state, {$CFG->prefix}question_sessions sess WHERE sess.newest = state.id 
						AND sess.attemptid = $attempt->uniqueid	AND sess.questionid = '$q->id'";
						$state = get_record_sql($sql);
						if (!question_state_is_graded($state)) {								
							$count = $count + 1;								
						}
					}
				}
			
				if ($count > 0) {
					$name = $q->name;
					$qid = $q->id;
					$sum = $q->questiontext;
					$sumlength = strlen($sum);
					$shortsum = substr($sum, 0, 100);
					if (strlen($shortsum) < strlen($sum)) {$shortsum .= "...";}
					$this->output .= ','; // add a comma before section only if there was a preceding assignment
						
					$this->output .= '{';
					$this->output .= '"name":"'.$this->clean_name_text($name, 2).'",';
					$this->output .= '"id":"'.$qid.'",';
					$this->output .= '"assid":"qq'.$qid.'",';
					$this->output .= '"type":"quiz_submissions",';
					$this->output .= '"summary":"'.$this->clean_summary_text($shortsum).'",';
					$this->output .= '"count":"'.$count.'"';
					$this->output .= '}';
						
				}
			}
		}
		$this->output .= "]"; // end JSON array
	}
	
	//////////////////////////////////////////
	// user submissions for the quiz question
	//////////////////////////////////////////
	
	
	function quiz_submissions() {
		$quiz = get_record('quiz', 'id', $this->quizid);
		$students = $this->get_course_students($quiz->course);
		global $CFG;
		require_once ("{$CFG->dirroot}/mod/quiz/locallib.php");
		
		
		$i = 0;                   // counter to keep track of where commas go in in the loop.
		
		$question = get_record_select('question', "id = $this->id", 'id', 'id, qtype, name, questiontext');
		if ($question) { 
			$attempts = get_records_sql("SELECT qa.* FROM {$CFG->prefix}quiz_attempts qa, {$CFG->prefix}question_sessions qs ".
			"WHERE	quiz = $this->quizid AND qa.userid IN ($students) AND qa.timefinish > 0 ".
			" AND qa.preview = 0 AND qs.questionid = '$question->id'");
			if ($attempts) {
				$this->output = '[{"type":"submissions"}';      // begin json object.
			//	$data=array();
			//	$str = '';
			//	$num = 0;
				foreach ($attempts as $attempt) {
					$sql = "SELECT state.id, state.event
					FROM {$CFG->prefix}question_states state, {$CFG->prefix}question_sessions sess WHERE sess.newest = state.id 
					AND sess.attemptid = $attempt->uniqueid	AND sess.questionid = '$question->id'";
					$state = get_record_sql($sql);
					if (!question_state_is_graded($state)) {
						$rec = get_record('user', 'id', $attempt->userid);
						$name = $rec->firstname." ".$rec->lastname;
						$now = time();
						$seconds = ($now - $attempt->timemodified);
						$summary = $this->make_time_summary($seconds);
						$this->output .= ','; // add a comma before section only if there was a preceding assignment
							
						$this->output .= '{';
						$this->output .= '"name":"'.$this->clean_name_text($name, 3).'",';
						$this->output .= '"sid":"'.$attempt->userid.'",'; //  user id for hyperlink
						$this->output .= '"aid":"'.$question->id.'",'; // id of question for hyperlink
						$this->output .= '"seconds":"'.$seconds.'",'; // seconds sent to allow style to change according to how long it has been
						$this->output .= '"summary":"'.$this->clean_summary_text($summary).'",';
						$this->output .= '"type":"quiz_answer",';
						$this->output .= '"count":"1",';
						$this->output .= '"time":"'.$attempt->timemodified.'"'; // send the time of submission for tooltip
						$this->output .= '}';
															
					}
				}
				$this->output .= "]"; // end JSON array
			}
		}
		
	}
	
	
	function clean_summary_text($text, $stripbr=true) {
		if ($stripbr == true) {
			$text = strip_tags($text, '<strong>');
		}
		$text = str_replace(array("\n","\r",'"'),array("","","&quot;"),$text);
		return $text;
	}
	
	
	function clean_name_text($text,  $level=0, $stripbr=true) {
		if ($stripbr == true) {
			$text = strip_tags($text, '<strong>');
		}
		switch($level) {
		// this switch controls how long the names will be in the block. different levels need different lengths as the tree indenting varies.
		// the aim is for all names to reach as far to the right as possible without causing a line break. Forum discussions will be clipped 
		//if you don't alter that setting in foru_submissions()
		case 0:
			$text = substr($text, 0, 22);
			break;
		case 1:
			$text = substr($text, 0, 18);
			break;
		case 2:
			$text = substr($text, 0, 16);
			break;
		case 3:
			$text = substr($text, 0, 16);
			break;
		}
		$text = str_replace(array("\n","\r",'"'),array("","","&quot;"),$text);
		return $text;
	}
	
	//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// This function returns a comma separated list of all student ids in a course. It uses the config variable for gradebookroles
	// to get ones other than 'student' and to make it language neutral. Point is that when students leave the course, often 
	// their work remains, so we need to check that we are only using work from currently enrolled students.
	///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	
	function get_course_students($courseid) {
	
		$course_context = get_context_instance(CONTEXT_COURSE, $courseid);
		$student_array = array();
		
		// get the roles that are specified as graded in config
		$student_roles = get_field('config','value', 'name', 'gradebookroles');
		$student_roles = explode(",", $student_roles); //make the list into an array
		
		foreach ($student_roles as $student_role) {
			$course_students = get_role_users($student_role, $course_context); // get students in this course with this role
			if ($course_students) {
				// we have an array of objects, which we need to get the student ids out of and into a comma separated list
				foreach($course_students as $course_student) {
					array_push($student_array, $course_student->id);
				}
			}
		}
		if (count($student_array > 0)) { // some students were returned
			$student_array = implode(",", $student_array); //convert to comma separated
			return $student_array;
		} else {
			return false;
		}
	}

	////////////////////////////////////////////////////
	// function to make the summary for submission nodes
	////////////////////////////////////////////////////
	
	function make_time_summary($seconds, $discussion=false) {
		$weeksstr = get_string('weeks', 'block_ajax_marking');
		$weekstr = get_string('week', 'block_ajax_marking');
		$daysstr = get_string('days', 'block_ajax_marking');
		$daystr = get_string('day', 'block_ajax_marking');
		$hoursstr = get_string('hours', 'block_ajax_marking');
		$hourstr = get_string('hour', 'block_ajax_marking');
		$submitted = "<strong>"; // make the time bold unless its a discussion where there is already a lot of bolding
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
		$name .= "</strong> ".$ago;
		return $name;
	}
	
}
/// initialise the object
$ajax = new ajax_marking_functions;


?>