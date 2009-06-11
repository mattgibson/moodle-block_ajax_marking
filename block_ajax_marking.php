<?php

/**
 * This class builds a marking block on the front page which loads assignments and submissions dynamically into a tree structure using AJAX .
 * all marking occurs in pop-up windows and each node removes itself from the tree after its pop up is graded.
 */

class block_ajax_marking extends block_base {
 
    function init() {
        $this->title = get_string('ajaxmarking', 'block_ajax_marking');
        $this->version = 2009061101;
    }
	
    function specialization() {
            $this->title = get_string('marking', 'block_ajax_marking');
    }
	
    function get_content() {

        if ($this->content !== NULL) {
            return $this->content;
        }
		
       global $CFG, $USER;
       

       // admins will have a problem as they will see all the courses on the entire site
       $teacher_role     =  get_field('role','id','shortname','editingteacher'); // retrieve the teacher role id (3)
       $ne_teacher_role  =  get_field('role','id','shortname','teacher'); // retrieve the non-editing teacher role id (4)

       // check to see if any roles allow grading of assessments
       $coursecheck = 0;
       $courses = get_my_courses($USER->id, $sort='fullname', $fields='id, visible', $doanything=false);

       foreach ($courses as $course) {

            // exclude the front page
            if ($course->id == 1) {
                continue;
            }

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
                // skip this course if no teacher or teacher_non_editing role
                if (!$correct_role) {
                    continue;
                }

                $coursecheck++;

        }
        if ($coursecheck>0) { 
            // Grading permissions exist in at least one course, so display the block

            //start building content output
            $this->content = new stdClass;

            // Add a style to hide the HTML list if javascript is enabled in the client and AJAX is to be used
            if($CFG->enableajax && $USER->ajax) {
                $this->content->text .= '
                    <script type="text/javascript" defer="defer">
                        var styleElement = document.createElement("style");
                        styleElement.type = "text/css";
                        if (styleElement.styleSheet) {
                            styleElement.styleSheet.cssText = "#AMB_html_list { display: none; }";
                        } else {
                            styleElement.appendChild(document.createTextNode("#AMB_html_list { display: none; }"));
                        }
                        document.getElementsByTagName("head")[0].appendChild(styleElement);
                    </script>';
            }

            // make the non-ajax list whatever happens. Then allow the AJAX tree to usurp it if necessary
            include('html_list.php');
            $AMB_html_list_object = new html_list;
            $this->content->text .= '<div id="AMB_html_list">';
            $this->content->text .= $AMB_html_list_object->make_html_list();
            $this->content->text .= '</div>';
            $this->content->footer = '';

            if($CFG->enableajax && $USER->ajax) {
                // Seeing as the site and user both want to use AJAX,
                $AMfullname = fullname($USER);
                $variables = array(

                    'wwwroot'             => $CFG->wwwroot,
                    'totalMessage'        => get_string('total',              'block_ajax_marking'),
                    'userid'              => $USER->id,
                    'assignmentString'    => get_string('modulename',         'assignment'),
                    'workshopString'      => get_string('modulename',         'workshop'),
                    'forumString'         => get_string('modulename',         'forum'),
                    'instructions'        => get_string('instructions',       'block_ajax_marking'),
                    'configNothingString' => get_string('config_nothing',     'block_ajax_marking'),
                    'nothingString'       => get_string('nothing',            'block_ajax_marking'),
                    'refreshString'       => get_string('refresh',            'block_ajax_marking'),
                    'configureString'     => get_string('configure',          'block_ajax_marking'),
                    'forumSaveString'     => get_string('sendinratings',      'forum'),
                    'quizString'          => get_string('modulename',         'quiz'),
                    'quizSaveString'      => get_string('savechanges'),
                    'journalString'       => get_string('modulename',         'journal'),
                    'journalSaveString'   => get_string('saveallfeedback',    'journal'),
                    'connectFail'         => get_string('connect_fail',       'block_ajax_marking'),
                    'nogroups'            => get_string('nogroups',           'block_ajax_marking'),
                    'headertext'          => get_string('headertext',         'block_ajax_marking'),
                    'fullname'            => $AMfullname,
                    'confAssessmentShow'  => get_string('confAssessmentShow', 'block_ajax_marking'),
                    'confCourseShow'      => get_string('confCourseShow',     'block_ajax_marking'),
                    'confGroups'          => get_string('confGroups',         'block_ajax_marking'),
                    'confAssessmentHide'  => get_string('confAssessmentHide', 'block_ajax_marking'),
                    'confCourseHide'      => get_string('confCourseHide',     'block_ajax_marking'),
                    'confDefault'         => get_string('confDefault',        'block_ajax_marking'),

                );

                
                // for integrating the block_marking stuff, this stuff (divs) should all be created by javascript.
                $this->content->text .= "

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
                   
                         var amVariables = {";

                   // loop through the variables above, printing them in the right format for javascript to pick up
                   $check = 0;
                   foreach ($variables as $variable => $value) {
                       if ($check > 0) {$this->content->text .= ", ";}
                       $this->content->text .= $variable.": '".$value."'";
                       $check ++;
                   }
                    // this line adds the debug versions
                    $this->content->text .= require_js(array('yui_yahoo', 'yui_event', 'yui_dom', 'yui_logger',  $CFG->wwwroot.'/lib/yui/treeview/treeview-debug.js', 'yui_connection', 'yui_dom-event', 'yui_container', 'yui_utilities', $CFG->wwwroot.'/lib/yui/container/container_core-min.js', $CFG->wwwroot.'/lib/yui/menu/menu-min.js', 'yui_json', 'yui_button'))."";

                $this->content->text .=    "};
                    </script>
                </div>
              
                <script type=\"text/javascript\" defer=\"defer\" src=\"".$CFG->wwwroot.'/blocks/ajax_marking/javascript.js'."\">
                </script>";

                // TODO- make this dynamically so it doesn't show up without AJAX.
                $this->content->footer = '
                    <div id="conf_left">
                        <!-- <a href="javascript:" onclick="AJAXmarking.refreshTree(AJAXmarking.main); return false">'.get_string("collapse", "block_ajax_marking").'</a> -->
                    </div>
                    <div id="conf_right">
                        <!-- <a href="#" onclick="AJAXmarking.greyBuild();return false">'.get_string('configure', 'block_ajax_marking').'</a> -->
                    </div>
                ';


            } 
 
        } // end of if has capability
        return $this->content;	
    }	
	
    function instance_allow_config() {
        return false;
    }

    /**
     * Runs the check for plugins after the first install.
     */
    function after_install() {

        global $CFG;

        $modules = array();
        echo "<br /><br />Scanning site for modules which have an AJAX Marking Block plugin... <br /><br />";

        // make a list of directories to check for module grading files
        $installed_modules = get_list_of_plugins('mod');
        $directories = array($CFG->dirroot.'/blocks/ajax_marking');
        foreach ($installed_modules as $module) {
            $directories[] = $CFG->dirroot.'/mod/'.$module;
        }


        // get installed module ids so that we can store these later
        $comma_modules = array();
        foreach($installed_modules as $key => $installed_module) {
            $comma_modules[$key] = "'".$installed_module."'";
        }
        $comma_modules = implode(', ', $comma_modules);
        $sql = "
            SELECT name, id
            FROM {$CFG->prefix}modules
            WHERE name IN (".$comma_modules.")
        ";
        $module_ids = get_records_sql($sql);


        // Get files in each directory and check if they fit the naming convention
        foreach ($directories as $directory) {
            $files = scandir($directory);

            // check to see if they end in _grading.php
            foreach ($files as $file) {
                // this should lead to 'modulename' and 'grading.php'
                $pieces = explode('_', $file);
                if ((isset($pieces[1])) && ($pieces[1] == 'grading.php')) {

                    // Only add modules that are installed and activated? Could causes problems when those modules are re-enabled
                    if(in_array($pieces[0], $installed_modules)) {

                        $modname = $pieces[0];

                        // add the modulename part of the filename to the array
                        $modules[$modname] = new stdClass;
                        $modules[$modname]->name = $modname;
                        $modules[$modname]->dir  = $directory;
                        $modules[$modname]->id  = $module_ids[$modname]->id;
                        

                        echo "Registered $modname module <br />";
                    }

                }
            }
        }

        echo '<br />For instructions on how to write extensions for this block, see the documentation on Moodle Docs<br /><br />';

        set_config('modules', serialize($modules), 'block_ajax_marking');
    }
}
?>
	 
