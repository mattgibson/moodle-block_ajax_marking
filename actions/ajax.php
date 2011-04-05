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
 * This is the file that is called by all the browser's ajax requests.
 *
 * It first includes the main lib.php fie that contains the base class
 * which has all of the functions in it, then instantiates a new ajax_marking_response
 * object which will process the request.
 *
 * @package   blocks-ajax_marking
 * @copyright 2008-2010 Matt Gibson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../../config.php');
require_login(0, false);
require_once($CFG->dirroot.'/blocks/ajax_marking/lib.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/classes/output.class.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/classes/module_base.class.php');

/**
 * Wrapper for the main functions library class which adds the parts that deal with the AJAX
 * request process.
 *
 * The block is used in two ways. Firstly when the PHP version is made, necessitating a HTML list
 * of courses + assessment names, and secondly when an AJAX request is made, which requires a JSON
 * response with just one set of nodes e.g. courses OR assessments OR student. The logic is that
 * shared functions go in the base class and this is extended by either the ajax_marking_response
 * class as here, or the HTML_list class in the html_list.php file.
 *
 * @package   blocks-ajax_marking
 * @copyright 2008-2010 Matt Gibson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

global $CFG, $USER, $DB;

// TODO - not necessary to load all things for all types. submissions level doesn't need
// the data for all the other types
$callbackfunction  = required_param('callbackfunction', PARAM_TEXT);
$callbackparamone  = optional_param('callbackparamone', null, PARAM_INT);
$callbackparamtwo  = optional_param('callbackparamtwo', null, PARAM_INT);
$groups            = optional_param('groups', null, PARAM_TEXT);
$assessmenttype    = optional_param('assessmenttype', null, PARAM_TEXT);
$assessmentid      = optional_param('assessmentid', null, PARAM_INT);
$showhide          = optional_param('showhide', null, PARAM_INT);
$groupid             = optional_param('group', '', PARAM_TEXT);
$courseid          = optional_param('courseid', null, PARAM_TEXT);
$modulename        = optional_param('modulename', null, PARAM_TEXT);

$moduleclasses = block_ajax_marking_get_module_classes();

//$outputobject = new block_ajax_marking_output();

$output = array();
$data = new stdClass;
$nodes = array();

// The type here refers to what was clicked, or sets up the tree in the case of 'main' and
// 'config_main_tree'. Type is also returned, where it refers to the node(s) that will be created,
// which then gets sent back to this function when that node is clicked.
switch ($callbackfunction) {

    // generate the list of courses when the tree is first prepared. Currently either makes
    // a config tree or a main tree
    case 'main':

        // admins will have a problem as they will see all the courses on the entire site.
        // However, they may want this (CONTRIB-1017)
        // TODO - this has big issues around language. role names will not be the same in
        // diffferent translations.
        
        
        $data->nodetype = 'course';
        $data->callbackfunction = 'course';
        
        

        // begin JSON object

        // iterate through each course, checking permisions, counting relevant assignment
        // submissions and adding the course to the JSON output if any appear
        $courses = block_ajax_marking_get_my_teacher_courses($USER->id);
        
        foreach ($courses as $course) {

            $courseid = '';
            $students = '';
            // set course assessments counter to 0
            $coursecount = 0;

            // show nothing if the course is hidden
            if (!$course->visible == 1) {
                continue;
            }



            // we must make sure we only get work from enrolled students
            $courseid = $course->id;
            $studentids = block_ajax_marking_get_course_students($course);

            // If there are no students, there's no point counting
            if (empty($studentids)) {
                continue;
            }

            

            // loop through each module, getting a count for this course id from each one.
            foreach ($moduleclasses as $moduleclass) {
                // Do not use modules which have been disabled by the admin
                
                $coursecount += $moduleclass->count_course_submissions($courseid, $studentids);
                
            }

            // TO DO: need to check in future for who has been assigned to mark them (new
            // groups stuff) in 1.9

            if ($coursecount > 0 || $config) {

                // there are some assessments, or its a config tree, so we include the
                // course always.
                
                $node = new stdClass;

                $node->callbackparamone   = $courseid;
                $node->callbackfunction   = 'course';

                $node->label              = block_ajax_marking_add_icon('course');
                $node->label              .= ($config) ? '' : "(<span class='AMB_count'>".$coursecount.'</span>) ';
                $node->label              .= block_ajax_marking_clean_name_text($course->shortname, 0);

                // name is there to allow labels to be reconstructed with a new count after
                // marked nodes are removed
                $node->name               = block_ajax_marking_clean_name_text($course->shortname, 0);
                $node->title              = block_ajax_marking_clean_name_text($course->shortname, -2);
                $node->summary            = block_ajax_marking_clean_name_text($course->shortname, -2);
                $node->icon               = block_ajax_marking_add_icon('course');
                $node->uniqueid           = 'course'.$courseid;
                $node->count              = $coursecount;
                $node->dynamic            = 'true';
                
                $nodes[] = $node;

            }
        }
        //end JSON object

        break;

    case 'config_main_tree':

        // Makes the course list for the configuration tree. No need to count anything, just
        // make the nodes. Might be possible to collapse it into the main one with some IF
        // statements.

        $config = true;
        
        $data->nodetype = 'course';
//        $data->callbackfunction = 'course';

        $output = '[{"callbackfunction":"config_main_tree"}';
        
        $courses = block_ajax_marking_get_my_teacher_courses($USER->id);

        foreach ($courses as $course) {
            // iterate through each course, checking permisions, counting assignments and
            // adding the course to the JSON output if anything is there that can be graded
            $coursecount = 0;

            if (!$course->visible) {
                continue;
            }

            foreach ($moduleclasses as $moduleclass) {
                $coursecount += $moduleclass->count_course_assessment_nodes($course->id);
            }

            if ($coursecount > 0) {

                $course_settings = block_ajax_marking_get_groups_settings('course', $course->id);

                $output .= ',';
                $output .= '{';

                $output .= '"id":"'.$course->id.'",';
                $output .= '"callbackfunction":"config_course",';
                $output .= '"title":"';
                $output .= get_string('currentsettings', 'block_ajax_marking').': ';

                // add the current settings to the tooltip
                if (isset($course_settings->showhide)) {

                    switch ($course_settings->showhide) {

                        case BLOCK_AJAX_MARKING_CONF_SHOW:
                            $output .= get_string('showthiscourse', 'block_ajax_marking');
                            break;

                        case BLOCK_AJAX_MARKING_CONF_GROUPS:
                            $output .= get_string('showwithgroups', 'block_ajax_marking');
                            break;

                        case BLOCK_AJAX_MARKING_CONF_HIDE:
                            $output .= get_string('hidethiscourse', 'block_ajax_marking');

                    }
                } else {
                    $output .= get_string('showthiscourse', 'block_ajax_marking');
                }

                $output .= '",';
                $output .= '"name":"'  .block_ajax_marking_clean_name_text($course->fullname).'",';
                // to be used for the title
                $output .= '"icon":"'  .block_ajax_marking_add_icon('course').'",';
                $output .= '"label":"' .block_ajax_marking_add_icon('course');
                $output .= block_ajax_marking_clean_name_text($course->fullname).'",';
                $output .= '"count":"' .$coursecount.'"';

                $output .= '}';

            }
        }
        $output .= ']';

        break;

    case 'course':
        
        $data->nodetype = 'assessment';

        $courseid = $callbackparamone;
        // we must make sure we only get work from enrolled students
        $course = $DB->get_record('course', array('id' => $courseid));

//        $output = '[{"callbackfunction":"course"}';

        foreach ($moduleclasses as $moduleclass) {
            $nodes = array_merge($nodes, $moduleclass->course_assessment_nodes($courseid));
//            $output .= $moduleclass->course_assessment_nodes($courseid);
        }

//        $output .= ']';
        break;

    case 'config_course':
        $course = $DB->get_record('course', array('id' => $courseid));

        $config = true;
        $output = '[{"callbackfunction":"config_course"}';

        foreach ($moduleclasses as $module) {
            $output .= $moduleclass->config_assessment_nodes($callbackparamone, $module);
        }

        $output .= ']';
        break;


    case 'config_groups':

        // writes to the db that we are to use config groups, then returns all the groups.
        // Called only when you click the option 2 of the config, so the next step is for the
        // javascript functions to build the groups checkboxes.

        $output = '[{"callbackfunction":"config_groups"}'; // begin JSON array

        // first set the config to 'display by group' as per the ajax request (this is the
        // option that was clicked)
        block_ajax_marking_make_config_data();

        if (block_ajax_marking_config_write()) {
            // next, we will now return all of the groups in a course as an array,
            $output .= block_ajax_marking_make_config_groups_radio_buttons($callbackparamone, $assessmenttype, $assessmentid);
        } else {
            $output .= ',{"result":"false"}';
        }
        $output .= ']';

        break;

    case 'config_set':

        /**
         * this is to save configuration choices from the radio buttons for 1 and 3 once
         * they have been clicked. Needed as a wrapper
         * so that the config_write bit can be used for the function above too
         */

        $output = '[{"callbackfunction":"config_set"}';

        // if the settings have been put back to default, destroy the existing record
        if ($showhide == AMB_CONF_DEFAULT) {
            //TODO need to check these variables are not empty
            $conditions = array(
                    'assessmenttype' => $assessmenttype,
                    'assessmentid'   => $assessmentid,
                    'userid'         => $USER->id
            );
            $deleterecord = $DB->delete_records('block_ajax_marking', $conditions);

            if ($deleterecord) {
                $output .= ',{"result":"true"}]';
            } else {
                $output .= ',{"result":"false"}]';
            }
        } else {
            block_ajax_marking_make_config_data();

            if (block_ajax_marking_config_write()) {
                $output .= ',{"result":"true"}]';
            } else {
                $output .= ',{"result":"false"}]';
            }
        }

        break;

    case 'config_check':

        /**
         * this is to check what the current status of an assessment is so that
         * the radio buttons can be made with that option selected.
         * if its currently 'show by groups', we need to send the group data too.
         *
         * This might be for an assessment node or a course node
         */

        // begin JSON array
        $output = '[{"callbackfunction":"config_check"}';

        $assessment_settings = block_ajax_marking_get_groups_settings($assessmenttype, $assessmentid);
        $course_settings     = block_ajax_marking_get_groups_settings('course', $courseid);

        // Procedure if it's an assessment
        if ($assessmentid) {

            if ($assessment_settings) {
                $output .= ',{"value":"'.$assessment_settings->showhide.'"}';

                if ($assessment_settings->showhide == 2) {
                    $output .= block_ajax_marking_make_config_groups_radio_buttons($courseid,
                                                            $assessmenttype,
                                                            $assessmentid);

                }
            } else {
                // no settings, so use course default.
                $output .= ',{"value":"0"}';
            }
        } else {
            // Procedure for courses
            if ($course_settings) {
                $output .= ',{"value":"'.$course_settings->showhide.'"}';

                if ($course_settings->showhide == 2) {
                    $output .= block_ajax_marking_make_config_groups_radio_buttons($courseid, 'course');
                }
            } else {
                // If there are no settings, default to 'show'
                $output .= ',{"value":"1"}';
            }
        }

        $output .= ']';

        break;

    case 'config_group_save':

        /**
         * sets the display of a single group from the config screen when its checkbox is
         * clicked. Then, it sends back a confirmation so that the checkbox can be un-greyed
         * and marked as done
         */

        $output = '[{"callbackfunction":"config_group_save"},{'; // begin JSON array

        block_ajax_marking_make_config_data();

        if (block_ajax_marking_config_write()) {
            $output .= '"value":"true"}]';
        } else {
            $output .= '"value":"false"}]';
        }

        break;

    default:
        
        // 2 options - it's a single word, in which case it's pre defined, or it's underscore separated, 
        // in which case the first bit is the classname

        // assume it's specific to one of the added modules. Run through each until
        // one of them has that function and it returns true.
        if (isset($modulename, $callbackfunction)) {
            if (method_exists($moduleclasses[$modulename], $callbackfunction)) {
                $params = array($callbackparamone, $groupid, $callbackparamtwo);
                list($data, $nodes) = call_user_func_array(array($moduleclasses[$modulename], $callbackfunction), $params);
            }
        }

        break;

}

// return the output to the client

$output = array('data' => $data, 'nodes' => $nodes);

echo json_encode($output);