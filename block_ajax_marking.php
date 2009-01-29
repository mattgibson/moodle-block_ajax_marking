<?php

/**
 * This class builds a marking block on the front page which loads assignments and submissions dynamically into a tree structure using AJAX .
 * all marking occurs in pop-up windows and each node removes itself from the tree after its pop up is graded.
 */

class block_ajax_marking extends block_base {
 
    function init() {
        $this->title = get_string('ajaxmarking', 'block_ajax_marking');
        $this->version = 2008110401;
    }
	
    function specialization() {
            $this->title = get_string('marking', 'block_ajax_marking');
    }
	
    function get_content() {
        if ($this->content !== NULL) {
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
                $correct_role = false;
                if ($teachers) {
                        foreach($teachers as $teacher) {
                                if ($teacher->id == $USER->id) {
                                        $correct_role = true;
                                }
                        }
                }
                $teachers_ne = get_role_users($ne_teacher_role, $context, true); // check for non-editing teachers
                if ($teachers_ne) {
                        foreach($teachers_ne as $teacher) {
                                if ($teacher->id == $USER->id) {
                                        $correct_role = true;
                                }
                        }
                }
                if (!$correct_role) {continue;} // skip this course if no teacher or teacher_non_editing role

                $coursecheck++;

        }
        if ($coursecheck>0) { // display the block

            if($CFG->enableajax && $USER->ajax) {

                $AMfullname = fullname($USER);
                $variables = array(

                    'wwwroot'             => $CFG->wwwroot,
                    'totalMessage'        => get_string('total', 'block_ajax_marking'),
                    'userid'              => $USER->id,
                    'assignmentString'    => get_string('modulename', 'assignment'),
                    'workshopString'      => get_string('modulename', 'workshop'),
                    'forumString'         => get_string('modulename', 'forum'),
                    'instructions'        => get_string('instructions', 'block_ajax_marking'),
                    'configNothingString' => get_string('config_nothing', 'block_ajax_marking'),
                    'nothingString'       => get_string('nothing', 'block_ajax_marking'),
                    'collapseString'      => get_string('collapse', 'block_ajax_marking'),
                    'forumSaveString'     => get_string('sendinratings', 'forum'),
                    'quizString'          => get_string('modulename', 'quiz'),
                    'quizSaveString'      => get_string('savechanges'),
                    'journalString'       => get_string('modulename', 'journal'),
                    'journalSaveString'   => get_string('saveallfeedback', 'journal'),
                    'connectFail'         => get_string('connect_fail', 'block_ajax_marking'),
                    'nogroups'            => get_string('nogroups', 'block_ajax_marking'),
                    'headertext'          => get_string('headertext', 'block_ajax_marking'),
                    'fullname'            => $AMfullname

                );

                //start building content output
                $this->content = new stdClass;
                // for integrating the block_marking stuff, this stuff (divs) should all be created by javascript.
                $this->content->text = "

                <div id='total'>
                    <div id='totalmessage'></div>
                    <div id='count'></div>
                    <div id='mainIcon'></div>
                </div>
                <div id='status'> </div>
                <div id='treediv' class='yui-skin-sam'>
                    <noscript><p>AJAX marking block requires javascript, but you have it turned off.</p></noscript>
                </div>
                <div id='javaValues'>
                <script type=\"text/javascript\" defer=\"defer\">
                    // function am_go() {
                         var amVariables = {";
                                           $check = 0;
                                           foreach ($variables as $variable => $value) {
                                               if ($check > 0) {$this->content->text .= ", ";}
                                               $this->content->text .= $variable.": '".$value."'";
                                               $check ++;
                                           }
                $this->content->text .=    "};
                    </script>
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
                </div>
                <div id='cover'></div>
                <script type=\"text/javascript\" defer=\"defer\" src=\"".$CFG->wwwroot.'/blocks/ajax_marking/javascript.js'."\">
                </script>";
                $this->content->text .= require_js(array('yui_yahoo', 'yui_event', 'yui_dom', 'yui_treeview', 'yui_connection', 'yui_dom-event', 'yui_container', 'yui_utilities'))."";

                $this->content->footer = '
                    <div id="conf_left">
                        <a href="javascript:" onclick="AJAXmarking.refreshTree(AJAXmarking.main); return false">'.get_string("collapse", "block_ajax_marking").'</a>
                    </div>
                    <div id="conf_right">
                        <a href="#" onclick="AJAXmarking.greyBuild();return false">'.get_string('configure', 'block_ajax_marking').'</a>
                    </div>
                ';


            } else {// end if ajax is enabled

                $this->content->text .= 'This block requires you to enable \'AJAX and javascript\' in your <a href="'.$CFG->wwwroot.'/user/edit.php?id='.$USER->id.'&course=1">profile settings</a> (click \'show advanced\')';
                $this->content->footer = '';
                // if ajax is not enabled, we want to see the non-ajax list
                 /*
                include("html_list.php");
                if (isset($response)) {
                    unset($response);
                }
                $response = new html_list;
                $this->content->text .= $initial_object->output;

                  */

            }
                
           

        } // end of if has capability
        return $this->content;	
    }	
	
    function instance_allow_config() {
        return true;
    }
}
?>
	 
