<?php

/**
 * This class builds a marking block on the front page which loads assignments and submissions dynamically into a tree structure using AJAX .
 * all marking occurs in pop-up windows and each node removes itself from the tree after its pop up is graded.
 */

class block_ajax_marking extends block_base {
 
    function init() {
        $this->title = get_string('ajaxmarking', 'block_ajax_marking');
        $this->version = 2007092300;
    }
	
	function specialization() {
		$this->title = get_string('marking', 'block_ajax_marking');
	}
	
	function get_content() 
	{
       if ($this->content !== NULL) 
	   {
        	return $this->content;
		}
		
	   global $CFG, $USER;
	  // $id = $USER->id;
	   
	   // admins will have a problem as they will see all the courses on the entire site
	   $teacher_role=get_field('role','id','shortname','editingteacher'); // retrieve the teacher role id (3)
       $ne_teacher_role=get_field('role','id','shortname','teacher'); // retrieve the non-editing teacher role id (4)
	   
	   // check to see if any roles allow grading of assessments
	   $coursecheck = 0;
	   $courses = get_my_courses($USER->id, $sort='fullname', $fields='id, visible', $doanything=false) ;
		foreach ($courses as $course) { 
		    if ($course->id == 1) {continue;} // exclude the front page
			
		    // role check bit borrowed from block_narking, thanks to Mark J Tyers [ZANNET]
		    $context = get_context_instance(CONTEXT_COURSE, $course->id);
			
			$teachers = get_role_users($teacher_role, $context, true); // check for editing teachers
			$j = false;
			if ($teachers) {
				foreach($teachers as $key2=>$val2) {
					if ($val2->id == $USER->id) {
						$j = true;
					}
				}
			}
			$teachers_ne = get_role_users($ne_teacher_role, $context, true); // check for non-editing teachers
			if ($teachers_ne) {
				foreach($teachers_ne as $key2=>$val2) {
					if ($val2->id == $USER->id) {
						$j = true;
					}
				}
			}
			if (!$j) {continue;} // skip this course if no teacher or teacher_non_editing role           
			 
			$coursecheck = $coursecheck + 1; 
			
		}
		if ($coursecheck>0) { // display the block

			//start building content output
	        $this->content = new stdClass;
	       // for integrating the block_marking stuff, this stuff (divs) should all be created by javascript.
			$this->content->text = "
			
			<script type='text/javascript' src='".$CFG->wwwroot."/lib/yui/yahoo/yahoo-min.js'></script>
			<script type='text/javascript' src='".$CFG->wwwroot."/lib/yui/event/event-min.js'></script>
			<script type='text/javascript' src='".$CFG->wwwroot."/lib/yui/dom/dom-min.js'></script>
			<script type='text/javascript' src='".$CFG->wwwroot."/lib/yui/treeview/treeview.js'></script>
			<script type='text/javascript' src='".$CFG->wwwroot."/lib/yui/connection/connection-min.js'></script>
			
			
            <script type='text/javascript' src='".$CFG->wwwroot."/lib/yui/yahoo-dom-event/yahoo-dom-event.js'></script> 
			<script type='text/javascript' src='".$CFG->wwwroot."/lib/yui/container/container-min.js'></script>
			
		
			<div id='total'>
				<div id='totalmessage'></div>
				<div id='count'></div>
				<div id='icon'></div>
			</div>
			<div id='status'> </div>
			<div id='treediv'> 
				<noscript>AJAX marking block requires javascript, but you have it turned off.</noscript>
			</div>
			<div id='javaValues'>
			    <form id='valuediv' action='post'>
				    <p>
						<input type='hidden' id='wwwrootvalue' value='".$CFG->wwwroot."' />
						<input type='hidden' id='total_string' value='".get_string('total', 'block_ajax_marking')."' />
						<input type='hidden' id='useridvalue' value='".$USER->id."' />
						<input type='hidden' id='assignment_string' value='".get_string('modulename', 'assignment')."' />
						<input type='hidden' id='workshop_string' value='".get_string('modulename', 'workshop')."' />
						<input type='hidden' id='forum_string' value='".get_string('modulename', 'forum')."' />
						<input type='hidden' id='assignment_work_string' value='".get_string('assignment_work', 'block_ajax_marking')."' />
						<input type='hidden' id='workshop_work_string' value='".get_string('workshop_work', 'block_ajax_marking')."' />
						<input type='hidden' id='themevalue' value='".$CFG->theme."' />
						<input type='hidden' id='nothing_string' value='".get_string('nothing', 'block_ajax_marking')."' />
						<input type='hidden' id='collapse_string' value='".get_string('collapse', 'block_ajax_marking')."' />
						<input type='hidden' id='forumsavestring' value='".get_string('sendinratings', 'forum')."' />
						<input type='hidden' id='quizstring' value='".get_string('modulename', 'quiz')."' />
						<input type='hidden' id='quizsavestring' value='".get_string('savechanges')."' />
						<input type='hidden' id='ajaxmarking' value='".get_string('ajaxmarking', 'block_ajax_marking')."' />
						<input type='hidden' id='journalstring' value='".get_string('modulename', 'journal')."' />
						<input type='hidden' id='journalsavestring' value='".get_string('saveallfeedback', 'journal')."' />
				    </p>
				</form>  
			</div>
			<div id='hidden-icons'>
				<div id ='img_1' class='icon-course'></div>
				<div id ='img_2' class='icon-assign'></div>
				<div id ='img_3' class='icon-user'></div>
				<div id ='img_4' class='icon-workshop'></div>
				<div id ='img_5' class='icon-forum'></div>
				<div id ='img_6' class='icon-quiz'></div>
				<div id ='img_7' class='icon-question'></div>
				<div id ='img_8' class='icon-journal'></div>
				<div id ='img_9' class='icon-course'></div>
			</div>
			    <script type='text/javascript' src='".$CFG->wwwroot."/blocks/ajax_marking/javascript.js'></script>
				<script type='text/javascript' defer='defer'> 
	 
					function init() { 
						getVars();
						treeBuild(); 
					} 
					
				// wanted to use this, but its only available in YUI 2.2.1
	            // YAHOO.util.Event.onDOMReady(init());
				
				init();
				 </script>
			";
			//$this->content->text .= "<input type='button' value='Ajax request function' onclick='ajaxRequest();'/>;";

			$this->content->footer = '<div id="tree_control"></div><div id="control"> </div>';
		} // end of if has capability
		return $this->content;	
    }	
	
    function instance_allow_config() {
        return false;
    }
}
?>
	 
