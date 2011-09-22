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
 * This provides a generic pop up to display the work to be marked and to provide a grading interface.
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2011 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

//define('AJAX_SCRIPT', true);

require_once(dirname(__FILE__).'/../../../config.php');


// Each popup request will have different stuff that we want to pass to $moduleobject->grading_popup()
$params = array();
// Use GET to discriminate between submitted form stuff and url stuff. optional_param() doesn't.
foreach ($_GET as $name => $value) {
    $params[$name] = clean_param($value, PARAM_ALPHANUMEXT);
}

$cmid = required_param('coursemoduleid', PARAM_INT);
$node = required_param('node', PARAM_INT);

$coursemodule = $DB->get_record('course_modules', array('id' => $cmid));
$modname = $DB->get_field('modules', 'name', array('id' => $coursemodule->module));

//permissions checks
if (!$coursemodule) {
    print_error('Bad coursemoduleid');
    die();
}
require_login($coursemodule->course, false, $coursemodule);  
$context = get_context_instance(CONTEXT_MODULE, $cmid);

require_once($CFG->dirroot.'/blocks/ajax_marking/lib.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/classes/module_base.class.php');
require_once($CFG->dirroot."/blocks/ajax_marking/modules/{$modname}/block_ajax_marking_{$modname}.class.php");

$classname = 'block_ajax_marking_'.$modname;
if (!class_exists($classname)) {
    print_error('AJAX marking block does not support the '.$modname.' module');
    die();
}
$moduleobject = new $classname;
if (!has_capability($moduleobject->capability, $context)) {
    print_error('You do not have permission to grade submissions for this course module');
    die();
}




// stuff from /mod/quiz/comment.php - catch data if this is a self-submit so that data can be processed
$data = data_submitted();

//if (0) {
if ($data && confirm_sesskey()) {

    // make sure this includes require_login() in order to set page context properly
    $error = $moduleobject->process_data($data, $params);

    // If success, notify and print a close button.
    if (!is_string($error)) {
        
        $url = new moodle_url('/blocks/ajax_marking/actions/grading_popup.php', array('module' => $modname));
        $PAGE->set_url($url);
        $PAGE->set_pagelayout('popup');
        
        echo $OUTPUT->notification(get_string('changessaved'), 'notifysuccess');
        
        $PAGE->requires->js_function_call('window.opener.M.block_ajax_marking.remove_node_from_current_tab',
                                          array($node));
        close_window(1);
    }

    // Otherwise, display the error and fall through to re-display the form.
    echo $OUTPUT->notification($error);
}

// Get the pop up header etc ready. This allows us the separate the interface (form) from whether it's a pop up 
// or an ajax operation.
$url = new moodle_url('/blocks/ajax_marking/actions/grading_popup.php', $params);
//add_to_log($coursemodule->course, $params['mod'], 'AJAX Marking block grading popup', '/blocks/ajax_marking/actions/grading_popup.php'.$url->get_query_string(FALSE));
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('popup');

// may involve a redirect if we don't want a form
$content = $moduleobject->grading_popup($params, $coursemodule);

echo $OUTPUT->header();
echo $content;
echo $OUTPUT->footer();


// Make the grading interface via AJAX
//$data = new stdClass();
//$data->payloadtype = 'gradinginterface';
//
//// Problem here: the mforms echo by default.
//$output = array('data' => $data, 'content' => $moduleobject->grading_popup($params, $coursemodule));
//
//echo json_encode($output);





?>
