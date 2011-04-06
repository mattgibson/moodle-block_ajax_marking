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
 * @copyright  2011 onwards Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../../config.php');

$module = required_param('module', PARAM_ALPHA); // attempt id
$uniqueid = required_param('uniqueid', PARAM_ALPHANUMEXT);

$modulesettings = unserialize(get_config('block_ajax_marking', 'modules'));

if (empty($modulesettings)) {
    block_ajax_marking_update_modules();
    $modulesettings = unserialize(get_config('block_ajax_marking', 'modules'));
}

// include all module files
require_once($CFG->dirroot.'/blocks/ajax_marking/lib.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/classes/module_base.class.php');
require_once("{$CFG->dirroot}{$modulesettings[$module]->dir}/{$module}_grading.php");
//include("{$module->dir}/{$modulename}_grading.php");
$classname = 'block_ajax_marking_'.$module;
//pass this object in so that a reference to it can be stored, allowing library functions
// to be called
//$moduleobject = new $classname();

// stuff from /mod/quiz/comment.php - catch data if this is a self-submit
if ($data = data_submitted() and confirm_sesskey()) {

    // make sure this includes require_login() in order to set page context properly
    $error = $classname::process_data($data);

    // If success, notify and print a close button.
    if (!is_string($error)) {

        $url = new moodle_url('/blocks/ajax_marking/actions/grading_popup.php', array('module' => $module));
        $PAGE->set_url($url);
        $PAGE->set_pagelayout('popup');
        
        echo $OUTPUT->notification(get_string('changessaved'), 'notifysuccess');
        // YAHOO.ajax_marking_block.markingtree.remove_node_from_tree('/mod/quiz/report.php', '"
        //                                 + clickednode.data.uniqueid+"');
        $PAGE->requires->js_function_call('window.opener.YAHOO.ajax_marking_block.markingtree.remove_node_from_tree',
                                          array($uniqueid));
        close_window(2, false);
    }

    // Otherwise, display the error and fall throug to re-display the form.
    echo $OUTPUT->notification($error);
}

$classname::grading_popup($uniqueid);


?>
