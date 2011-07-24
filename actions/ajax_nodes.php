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
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2007 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(dirname(__FILE__).'/../../../config.php');

// For unit tests to work
global $CFG, $PAGE;

require_login(0, false);
require_once($CFG->dirroot.'/blocks/ajax_marking/lib.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/classes/output.class.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/classes/module_base.class.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/classes/query_factory.class.php');

// TODO might be in a course
$PAGE->set_context(get_context_instance(CONTEXT_SYSTEM));

// Each ajax request will have different stuff that we want to pass to the callback function. Using 
// required_param() means hard-coding them. 
$params = array();

// Need to get the filters in the right order so that the query recieves them in the right order
foreach ($_POST as $name => $value) {
    $params[$name] = clean_param($value, PARAM_ALPHANUMEXT);
}

if (!isset($params['nextnodefilter'])) {
    print_error('No filter specified for next set of nodes');
    die();
}

$nodes = block_ajax_marking_query_factory::get_query($params);
foreach ($nodes as &$node) {
    block_ajax_marking_format_node($node, $params['nextnodefilter']);
}

// reindex array so we pick it up in js as an array and can find the length. Associative arrays
// with strings for keys are automatically sent as objects
$nodes = array_values($nodes);
echo json_encode(array('nodes' => $nodes));



// Old stuff:

// The type here refers to what was clicked, or sets up the tree in the case of 'main' and
// 'config_main_tree'. Type is also returned, where it refers to the node(s) that will be created,
// which then gets sent back to this function when that node is clicked.
//switch ($params['nextnodefilter']) {
//switch (reset(array_keys($params))) {

    // generate the list of courses when the tree is first prepared. Currently either makes
    // a config tree or a main tree
//    case 'courseid':
//
//        // admins will have a problem as they will see all the courses on the entire site.
//        // However, they may want this (CONTRIB-1017)
//        // TODO - this has big issues around language. role names will not be the same in
//        // different translations.
//
////        $data->payloadtype = 'nodes';
//        //$data->callbackfunction = 'course';
//        
//        //$filters = array('courseid' => '');
//        $nodes = block_ajax_marking_query_factory::get_query($params);
//        foreach ($nodes as &$node) {
//            //$node->callbackfunction   = 'course';
//            $node->style              = 'course';
//            block_ajax_marking_format_node($node);
//        }
//
//        break;

//    case 'coursemoduleid':
//
////        $data->payloadtype = 'nodes';
//        
////        $filters = array(
////                'coursemoduleid' => '',
////                'courseid' => $params['courseid']);
//        $nodes = block_ajax_marking_query_factory::get_query($params);
//
//        foreach ($nodes as &$node) {
//            // We need to say whether or not there are groups (JS can handle this if flagged?) and what comes next
////            if ($node->display == BLOCK_AJAX_MARKING_CONF_GROUPS) {
////               // $node->callbackfunction = 'groups';
////            } else {
////                // will be 'submission' in most cases. Make it non dynamic if there are no further callbacks listed
////                // by the module
////                
////                // Temporary fix before this is transferred to JS management
////                //$node->callbackfunction = $moduleclasses[$node->modulename]->get_next_callback();
////                
//////                $node->callbackfunction = isset($this->callbackfunctions[0]) ? $this->callbackfunctions[0] : false;
////            }
//            block_ajax_marking_format_node($node);
//        }
//        
        
        
//        $nodes = array();
//
//        foreach ($moduleclasses as $moduleclass) {
//
//            $assessments = $moduleclass->module_nodes($params['courseid']);
//
//            foreach ($assessments as $assessment) {
////                $nodes[] = block_ajax_marking_make_assessment_node($assessment);
//                block_ajax_marking_format_node($assessment);
//            }
//            
//            $nodes = array_merge($nodes, $assessments);
//        }

//        break;

//    case 'config_course':
//        $course = $DB->get_record('course', array('id' => $courseid));
//
//        $config = true;
//        $output = '[{"callbackfunction":"config_course"}';
//
//        foreach ($moduleclasses as $module) {
//            $output .= $moduleclass->config_assessment_nodes($callbackparamone, $module);
//        }
//
//        $output .= ']';
//        break;
//
//
//    case 'config_groups':
//
//        // writes to the db that we are to use config groups, then returns all the groups.
//        // Called only when you click the option 2 of the config, so the next step is for the
//        // javascript functions to build the groups checkboxes.
//
//        $output = '[{"callbackfunction":"config_groups"}'; // begin JSON array
//
//        // first set the config to 'display by group' as per the ajax request (this is the
//        // option that was clicked)
//        block_ajax_marking_make_config_data();
//
//        if (block_ajax_marking_config_write()) {
//            // next, we will now return all of the groups in a course as an array,
//            $output .= block_ajax_marking_make_config_groups_radio_buttons($callbackparamone, $assessmenttype, $assessmentid);
//        } else {
//            $output .= ',{"result":"false"}';
//        }
//        $output .= ']';
//
//        break;
//
//    case 'config_set':
//
//        /**
//         * this is to save configuration choices from the radio buttons for 1 and 3 once
//         * they have been clicked. Needed as a wrapper
//         * so that the config_write bit can be used for the function above too
//         */
//
//        $output = '[{"callbackfunction":"config_set"}';
//
//        // if the settings have been put back to default, destroy the existing record
//        if ($show == AMB_CONF_DEFAULT) {
//            //TODO need to check these variables are not empty
//            $conditions = array(
//                    'assessmenttype' => $assessmenttype,
//                    'assessmentid'   => $assessmentid,
//                    'userid'         => $USER->id
//            );
//            $deleterecord = $DB->delete_records('block_ajax_marking', $conditions);
//
//            if ($deleterecord) {
//                $output .= ',{"result":"true"}]';
//            } else {
//                $output .= ',{"result":"false"}]';
//            }
//        } else {
//            block_ajax_marking_make_config_data();
//
//            if (block_ajax_marking_config_write()) {
//                $output .= ',{"result":"true"}]';
//            } else {
//                $output .= ',{"result":"false"}]';
//            }
//        }
//
//        break;
//
//    case 'config_check':
//
//        /**
//         * this is to check what the current status of an assessment is so that
//         * the radio buttons can be made with that option selected.
//         * if its currently 'show by groups', we need to send the group data too.
//         *
//         * This might be for an assessment node or a course node
//         */
//
//        // begin JSON array
//        $output = '[{"callbackfunction":"config_check"}';
//
//        $assessment_settings = block_ajax_marking_get_groups_settings($assessmenttype, $assessmentid);
//        $course_settings     = block_ajax_marking_get_groups_settings('course', $courseid);
//
//        // Procedure if it's an assessment
//        if ($assessmentid) {
//
//            if ($assessment_settings) {
//                $output .= ',{"value":"'.$assessment_settings->show.'"}';
//
//                if ($assessment_settings->show == 2) {
//                    $output .= block_ajax_marking_make_config_groups_radio_buttons($courseid,
//                                                            $assessmenttype,
//                                                            $assessmentid);
//
//                }
//            } else {
//                // no settings, so use course default.
//                $output .= ',{"value":"0"}';
//            }
//        } else {
//            // Procedure for courses
//            if ($course_settings) {
//                $output .= ',{"value":"'.$course_settings->show.'"}';
//
//                if ($course_settings->show == 2) {
//                    $output .= block_ajax_marking_make_config_groups_radio_buttons($courseid, 'course');
//                }
//            } else {
//                // If there are no settings, default to 'show'
//                $output .= ',{"value":"1"}';
//            }
//        }
//
//        $output .= ']';
//
//        break;
//
//    case 'config_group_save':
//
//        /**
//         * sets the display of a single group from the config screen when its checkbox is
//         * clicked. Then, it sends back a confirmation so that the checkbox can be un-greyed
//         * and marked as done
//         */
//
//        $output = '[{"callbackfunction":"config_group_save"},{'; // begin JSON array
//
//        block_ajax_marking_make_config_data();
//
//        if (block_ajax_marking_config_write()) {
//            $output .= '"value":"true"}]';
//        } else {
//            $output .= '"value":"false"}]';
//        }
//
//        break;

//    default:
        
        // Need to make sure they are in the right order here
//        $params[] = '';
//        $callbackfunction = $params['callbackfunction'];
//        unset($params['callbackfunction']);
        
//        $data->payloadtype = $params['callbackfunction'];
        
//        $nodes = block_ajax_marking_query_factory::get_query($params);
//        
//        foreach ($nodes as &$node) {
//            block_ajax_marking_format_node($node);
//        }

        // If we're here, it's specific to one of the added modules.
//        if (isset($params['modulename'], $params['callbackfunction'])) {
//            
//            $modulename       = $params['modulename'];
//            $callbackfunction = $params['callbackfunction'];
//            
//            if (method_exists($moduleclasses[$modulename], $callbackfunction)) {
//                
//                // Only pass parameters used for filtering to the callbackfunction. They will need to be in the correct order!
//                unset($params['modulename']);
//                unset($params['callbackfunction']);
//                
//                list($data, $nodes) = call_user_func_array(array($moduleclasses[$modulename], $callbackfunction), array($params));
//                
//            } else {
//                // TODO catch error here
//            }
//        } else {
//            // TODO catch error here   
//        }

//        break;
//}



