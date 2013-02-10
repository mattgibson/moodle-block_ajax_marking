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
 * This provides a generic pop up to display the work to be marked and to provide a grading
 * interface.
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2011 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../../config.php');

global $DB, $OUTPUT, $CFG, $PAGE;

// Each popup request will have different stuff that we want to pass to
// $moduleobject->grading_popup().
$params = array();
// Use GET to discriminate between submitted form stuff and url stuff. optional_param() doesn't.
$thingwithstuff = $_GET;
foreach ($thingwithstuff as $name => $value) {
    if (is_array($value)) {
        $params[$name] = clean_param_array($value, PARAM_ALPHANUMEXT);
    } else {
        $params[$name] = clean_param($value, PARAM_ALPHANUMEXT);
    }
}
if (empty($params)) {
    die('No parmeters supplied');
}


$cmid = required_param('coursemoduleid', PARAM_INT);
$nodeid = required_param('node', PARAM_INT);

$coursemodule = $DB->get_record('course_modules', array('id' => $cmid));
/* @var string $modname  */
$modname = $DB->get_field('modules', 'name', array('id' => $coursemodule->module));

// Permissions checks.
if (!$coursemodule) {
    print_error('Bad coursemoduleid');
    die();
}
require_login($coursemodule->course, false, $coursemodule);
$context = context_module::instance($cmid);

/* @define $blockdir "../" */
$blockdir = $CFG->dirroot.'/blocks/ajax_marking/';
require_once($blockdir.'lib.php');
require_once($blockdir.'classes/module_base.class.php');
require_once($blockdir."modules/{$modname}/block_ajax_marking_{$modname}.class.php");

$classname = 'block_ajax_marking_'.$modname;
if (!class_exists($classname)) {
    print_error('AJAX marking block does not support the '.$modname.' module');
    die();
}
/* @var $moduleobject block_ajax_marking_module_base   */
$moduleobject = new $classname;
if (!has_capability($moduleobject->capability, $context)) {
    print_error('You do not have permission to grade submissions for this course module');
    die();
}

// Get the pop up header etc ready. This allows us the separate the interface (form) from
// whether it's a pop up or an ajax operation.
$url = new moodle_url('/blocks/ajax_marking/actions/grading_popup.php', $params);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('popup');


// Stuff from /mod/quiz/comment.php - catch data if this is a self-submit so that data can be
// processed. Also catches all other submitted form data.
$data = data_submitted();
$htmlstuff = '';

if ($data && confirm_sesskey()) {

    // Make sure this includes require_login() in order to set page context properly.
    $error = $moduleobject->process_data($data, $params);

    // If success (empty string), notify and print a close button.
    if (empty($error)) {

        $url = new moodle_url('/blocks/ajax_marking/actions/grading_popup.php',
                              array('module' => $modname));
        $PAGE->set_url($url);
        $PAGE->set_pagelayout('popup');

        echo $OUTPUT->header();
        echo $OUTPUT->notification(get_string('changessaved'), 'notifysuccess');
        $callfunction = "
            window.opener.M.block_ajax_marking.block.remove_node_from_current_tab({$nodeid});
        ";
        $PAGE->requires->js_init_code($callfunction, false);

        close_window(1);

    } else if ($error != 'displayagain') {

        // May have a specific second step e.g. confirm revert to draft, so we allow fall-through.
        // Otherwise, display the error and fall through to re-display the form.
        $htmlstuff .= $OUTPUT->notification($error);
    }

}

// Make sure that whatever happens, we lose the tree highlight when the pop up shuts.
$code = "

    function close_window_and_remove_node_highlight(nodeid) {
        // Get tree
        var tab = window.opener.M.block_ajax_marking.block.get_current_tab();
        var tree = tab.displaywidget;

        // get node
        var node = tree.getNodeByIndex(nodeid);

        if (node !== null) {
            // un-highlight node
            node.unhighlight();
        }
    }

    window.onbeforeunload = function() {
        // YAHOO.util.Event.addListener(window, 'beforeunload', function(args) {

        // Apparently no standard way to do this in YUI: http://yuilibrary.com/projects/yui3/ticket/2528059
        // e.returnValue = msg; // most browsers
        // return msg; // safari

        close_window_and_remove_node_highlight({$nodeid});

        // Don't remove the node here because the window may just have been closed with no marking
        // done. We want to keep the tree node in this case.

    };

    // Makes sure that any cancel button on screen will close the window after removing the tree
    // highlight
    YUI().use('event', function (Y) {
        var buttons = Y.all('#id_cancel, #id_cancelbutton').on('click', function(e) {

            e.preventDefault();

            close_window_and_remove_node_highlight({$nodeid});

            window.close();
            return false;

        });
    });

";
$PAGE->requires->js_init_code($code);

// Might cause redirect.
$htmlstuff .= $moduleobject->grading_popup($params, $coursemodule);

echo $OUTPUT->header();

echo $htmlstuff;

echo $OUTPUT->footer();
