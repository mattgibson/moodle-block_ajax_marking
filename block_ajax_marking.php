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
 * Main block file
 *
 * @package   blocks-ajax_marking
 * @copyright 2008-2010 Matt Gibson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * This class builds a marking block on the front page which loads assignments and submissions
 * dynamically into a tree structure using AJAX. All marking occurs in pop-up windows and each node
 * removes itself from the tree after its pop up is graded.
 *
 * @copyright 2008-2010 Matt Gibson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_ajax_marking extends block_base {

    /**
     * Standard init function, sets block title and version number
     *
     * @return void
     */
    function init() {
        $this->title = get_string('ajaxmarking', 'block_ajax_marking');
        $this->version = 2010050601;
    }

    /**
     * Standard specialization function
     *
     * @return void
     */
    function specialization() {
            $this->title = get_string('marking', 'block_ajax_marking');
    }

    /**
     * Standard get content function returns $this->content containing the block HTML etc
     *
     * @return object
     */
    function get_content() {

        if ($this->content !== null) {
            return $this->content;
        }

        global $CFG, $USER, $DB, $PAGE;

        // admins will have a problem as they will see all the courses on the entire site
        // retrieve the teacher role id (3)
        $teacher_role     =  $DB->get_field('role', 'id', array('shortname' => 'editingteacher'));
        // retrieve the non-editing teacher role id (4)
        $ne_teacher_role  =  $DB->get_field('role', 'id', array('shortname' => 'teacher'));

        // check to see if any roles allow grading of assessments
        $coursecheck = 0;
        $courses = get_my_courses($USER->id, 'fullname', 'id, visible');
        $isteacherinanycourse = false;

        foreach ($courses as $course) {

            // exclude the front page
            if ($course->id == 1) {
                continue;
            }

            // role check bit borrowed from block_narking, thanks to Mark J Tyers [ZANNET]
            $context = get_context_instance(CONTEXT_COURSE, $course->id);

            // check for editing teachers
            $teachers = get_role_users($teacher_role, $context, true);
            $correct_role = false;

            if ($teachers) {

                foreach ($teachers as $teacher) {

                    if ($teacher->id == $USER->id) {
                            $correct_role = true;
                            $isteacherinanycourse = true;
                    }
                }
            }
            // check for non-editing teachers
            $teachers_ne = get_role_users($ne_teacher_role, $context, true);

            if ($teachers_ne) {

                foreach ($teachers_ne as $teacher) {

                    if ($teacher->id == $USER->id) {
                        $correct_role = true;
                        $isteacherinanycourse = true;
                    }
                }
            }
            // skip this course if no teacher or teacher_non_editing role
            if (!$correct_role) {
                continue;
            }

            $coursecheck++;

        }

        if ($coursecheck > 0) {
            // Grading permissions exist in at least one course, so display the block

            //start building content output
            $this->content = new stdClass;

            // make the non-ajax list whatever happens. Then allow the AJAX tree to usurp it if
            // necessary
            include('html_list.php');
            $amb_html_list_object = new AMB_html_list;
            $this->content->text .= '<div id="AMB_html_list">';
            $this->content->text .= $amb_html_list_object->make_html_list();
            $this->content->text .= '</div>';
            $this->content->footer = '';

            // Build the AJAX stuff on top of the plain HTML list
            if ($CFG->enableajax && $USER->ajax && !$USER->screenreader) {

                // Add a style to hide the HTML list and prevent flicker
                $s  = '<script type="text/javascript" defer="defer">';
                $s .= '/* <![CDATA[ */ var styleElement = document.createElement("style");';
                $s .= 'styleElement.type = "text/css";';
                $s .= 'if (styleElement.styleSheet) {';
                $s .=     'styleElement.styleSheet.cssText = "#AMB_html_list { display: none; }";';
                $s .= '} else {';
                $s .=     'styleElement.appendChild(document.createTextNode("#AMB_html_list {display: none;}"));';
                $s .= '}';
                $s .= 'document.getElementsByTagName("head")[0].appendChild(styleElement);';
                $s .= '/* ]]> */</script>';
                $this->content->text .=  $s;

                $variables  = array(
                        'wwwroot'             => $CFG->wwwroot,
                        'totalMessage'        => get_string('total',              'block_ajax_marking'),
                        'userid'              => $USER->id,
                        'instructions'        => get_string('instructions',       'block_ajax_marking'),
                        'configNothingString' => get_string('config_nothing',     'block_ajax_marking'),
                        'nothingString'       => get_string('nothing',            'block_ajax_marking'),
                        'refreshString'       => get_string('refresh',            'block_ajax_marking'),
                        'configureString'     => get_string('configure',          'block_ajax_marking'),
                        'connectFail'         => get_string('connect_fail',       'block_ajax_marking'),
                        'nogroups'            => get_string('nogroups',           'block_ajax_marking'),
                        'headertext'          => get_string('headertext',         'block_ajax_marking'),
                        'fullname'            => fullname($USER),
                        'confAssessmentShow'  => get_string('confAssessmentShow', 'block_ajax_marking'),
                        'confCourseShow'      => get_string('confCourseShow',     'block_ajax_marking'),
                        'confGroups'          => get_string('confGroups',         'block_ajax_marking'),
                        'confAssessmentHide'  => get_string('confAssessmentHide', 'block_ajax_marking'),
                        'confCourseHide'      => get_string('confCourseHide',     'block_ajax_marking'),
                        'confDefault'         => get_string('confDefault',        'block_ajax_marking'),
                        'debuglevel'          => $CFG->debug
                );

                $jsvariables  = "YAHOO.ajax_marking_block.variables = [];
                        var YAV = YAHOO.ajax_marking_block.variables;
                        YAV['wwwroot']             = '".$CFG->wwwroot."';
                        YAV['totalMessage']        = '".get_string('total',              'block_ajax_marking')."';
                        YAV['userid']              = '".$USER->id."';
                        YAV['instructions']        = '".get_string('instructions',       'block_ajax_marking')."';
                        YAV['configNothingString'] = '".get_string('config_nothing',     'block_ajax_marking')."';
                        YAV['nothingString']       = '".get_string('nothing',            'block_ajax_marking')."';
                        YAV['refreshString']       = '".get_string('refresh',            'block_ajax_marking')."';
                        YAV['configureString']     = '".get_string('configure',          'block_ajax_marking')."';
                        YAV['connectFail']         = '".get_string('connect_fail',       'block_ajax_marking')."';
                        YAV['nogroups']            = '".get_string('nogroups',           'block_ajax_marking')."';
                        YAV['headertext']          = '".get_string('headertext',         'block_ajax_marking')."';
                        YAV['fullname']            = '".fullname($USER)."';
                        YAV['confAssessmentShow']  = '".get_string('confAssessmentShow', 'block_ajax_marking')."';
                        YAV['confCourseShow']      = '".get_string('confCourseShow',     'block_ajax_marking')."';
                        YAV['confGroups']          = '".get_string('confGroups',         'block_ajax_marking')."';
                        YAV['confAssessmentHide']  = '".get_string('confAssessmentHide', 'block_ajax_marking')."';
                        YAV['confCourseHide']      = '".get_string('confCourseHide',     'block_ajax_marking')."';
                        YAV['confDefault']         = '".get_string('confDefault',        'block_ajax_marking')."';
                        YAV['debuglevel']          = '".$CFG->debug."';
                ";

                // for integrating the block_marking stuff, this stuff (divs) should all be created
                // by javascript.
                $this->content->text .= "
                    <div id='total'>
                        <div id='totalmessage'></div>
                        <div id='count'></div>
                        <div id='mainIcon'></div>
                    </div>
                    <div id='status'> </div>
                    <div id='treediv' class='yui-skin-sam'></div>";

                // Don't warn about javascript if the sreenreader option is set - it was deliberate
                if (!$USER->screenreader) {
                    $this->content->text .= '<noscript><p>AJAX marking block requires javascript, ';
                    $this->content->text .= 'but you have it turned off.</p></noscript>';
                }

                // Add all of the javascript libraries that the above script depends on
//                $scripts = array(
//                        'yahoo',
//                        'event',
//                        'dom',
//
//                        'dom-event',
//                        'container',
//                        'utilities',
//                        'menu.js',
//                        'json',
//                        'button',
//                    );

                // also need to add any js from individual modules
                foreach ($amb_html_list_object->modulesettings as $modname => $module) {

                    $file_in_mod_directory  = file_exists("{$CFG->dirroot}{$module->dir}/{$modname}_grading.js");
                    $file_in_block_directory = file_exists("{$CFG->dirroot}/blocks/ajax_marking/{$modname}_grading.js");

                    if ($file_in_mod_directory) {
                        $PAGE->requires->js('/'.$module->dir.'/'.$modname.'_grading.js');

                    } else if ($file_in_block_directory) {
                        $PAGE->requires->js("/blocks/ajax_marking/{$modname}_grading.js");
                    }
                }

                $PAGE->requires->yui2_lib('treeview');
                $PAGE->requires->yui2_lib('button');
                $PAGE->requires->yui2_lib('connection');
                $PAGE->requires->yui2_lib('json');

                if ($CFG->debug == 38911) {
                    $PAGE->requires->yui2_lib('logger');
                }

                $PAGE->requires->js('/blocks/ajax_marking/javascript.js');
                $PAGE->requires->js_init_code($jsvariables);
                $PAGE->requires->js_init_call('YAHOO.ajax_marking_block.initialise', null, true);

                $this->content->footer .= '<div id="conf_left"></div><div id="conf_right"></div>';

            }

        } else {
            // no grading permissions in any courses - don't display the block. Exception for
            // when the block is just installed and editing is on. Might look broken otherwise.
            if ($PAGE->user_is_editing()) {
                $this->content->text .= get_string('config_nothing', 'block_ajax_marking');
                $this->content->footer = '';
            }

        }
        return $this->content;
    }

    /**
     * Standard function - does the block allow configuration for specific instances of itself
     * rather than sitewide?
     *
     * @return bool false
     */
    function instance_allow_config() {
        return false;
    }

    /**
     * Runs the check for plugins after the first install.
     *
     * @return void
     */
    function after_install() {

        echo 'after install';

        global $CFG;

        include_once($CFG->dirroot.'/blocks/ajax_marking/db/upgrade.php');
        amb_update_modules();

    }
}