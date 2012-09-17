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
 * This receives AJAX requests for config settings to be saved and processes them, sending a true
 * or false response, depending on whether all the data checked out and saved OK.
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2011 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('AJAX_SCRIPT')) {
    define('AJAX_SCRIPT', true);
}

require_once(dirname(__FILE__).'/../../../config.php');

global $DB, $USER, $CFG;
require_once($CFG->dirroot.'/blocks/ajax_marking/lib.php');

block_ajax_marking_login_error();
require_login();

// Get POST data.
$tablename       = required_param('tablename',        PARAM_ALPHAEXT);
$instanceid      = required_param('instanceid',       PARAM_INT);
$settingtype     = required_param('settingtype',      PARAM_ALPHAEXT);
$settingvalue    = optional_param('settingvalue',     null, PARAM_INT); // Here, null = inherit.
$groupid         = optional_param('groupid', 0,       PARAM_INT);
// These are passed through so that the right bit of the tree/menu gets fixed on the way back.
$menuitemindex   = optional_param('menuitemindex', false, PARAM_INT);
$nodeindex       = optional_param('nodeindex', false,  PARAM_INT);
$menutype        = optional_param('menutype', false,  PARAM_ALPHA);
$menugroupindex  = optional_param('menugroupindex', 0,  PARAM_INT);

if ($nodeindex === false && $menuitemindex === false) {
    die ('Either menuitem index or node index must be provided');
}

// Check for validity.
$allowedtables = array('course', 'course_modules');
if (!in_array($tablename, $allowedtables)) {
    die('Invalid Table');
}
$instance = $DB->get_record($tablename, array('id' => $instanceid));
if (!$instance) {
    die('Invlaid table row');
}
$allowedsettings = array('display', 'groupsdisplay', 'group');
if (!in_array($settingtype, $allowedsettings)) {
    die('Invalid setting type');
}
if ($settingvalue > 1) {
    die('Invlaid setting value');
}
if ($groupid) {
    $group = $DB->get_record('groups', array('id' => $groupid));
    if (!$group) {
        die('Invalid group id');
    }
}

/*
 * @var stdClass $USER // Prevent IDE complaining about undefined fields
 */
$userid = $USER->id;
$existingsetting = $DB->get_record('block_ajax_marking', array('tablename' => $tablename,
                                                               'instanceid' => $instanceid,
                                                               'userid' => $userid));
if (!$existingsetting) {
    $existingsetting = new stdClass();
    $existingsetting->tablename  = $tablename;
    $existingsetting->instanceid = $instanceid;
    $existingsetting->userid     = $userid;
    $existingsetting->id = $DB->insert_record('block_ajax_marking', $existingsetting);
    if (!$existingsetting->id) {
        die('Could not create new setting');
    }
}

$existinggroupsettings = $DB->get_records('block_ajax_marking_groups',
                                   array('configid' => $existingsetting->id));
$success = false;

switch ($settingtype) {

    case 'display':

    case 'groupsdisplay':

        // First, update the main setting for the thing that was clicked.
        $existingsetting->$settingtype = $settingvalue;
        $success = $DB->update_record('block_ajax_marking', $existingsetting);

        // For a course level node, we also want to set all child nodes to default, otherwise
        // it could get complex/confusing for the users.
        if ($tablename === 'course') {

            // Because MSSQL doesn't work with normal SQL for updates involving INNER JOIN
            // and aliases, we need to split this into two operations.
            // Start by getting the ids of all coursemodule settings records in this course.
            $params = array('settingid' => $existingsetting->id);
            $sql = "SELECT course_module_config.id
                      FROM {block_ajax_marking} course_module_config
                INNER JOIN {course_modules} course_modules
                        ON (course_modules.id = course_module_config.instanceid
                            AND course_module_config.tablename = 'course_modules')
                INNER JOIN {block_ajax_marking} course_config
                        ON (course_config.instanceid = course_modules.course
                            AND course_config.tablename = 'course')
                     WHERE course_config.id = :settingid";
            $settingids = $DB->get_records_sql($sql, $params);

            if ($settingids) {
                // Now update each record. Hopefully we won't hit the 1000 limit for IN on Oracle.
                list($idsql, $idparams) = $DB->get_in_or_equal(array_keys($settingids));
                $sql = "UPDATE {block_ajax_marking}
                           SET {$settingtype} = NULL
                         WHERE id {$idsql}
                           ";
                $DB->execute($sql, $idparams);
            }
        }
        break;

    case 'group':

        if (!$groupid) {
            die('Need a group ID for a showgroup operation');
        }

        // Do we have an existing setting?
        $havegroupsettting = false;
        $groupsetting = false;
        foreach ($existinggroupsettings as $groupsetting) {
            if ($groupsetting->groupid == $groupid) {
                $havegroupsettting = true;
                break; // Leaving $groupsetting as the one we want.
            }
        }
        if ($havegroupsettting) {
            if (is_null($settingvalue)) {
                $params = array('id' => $groupsetting->id);
                $success = $DB->delete_records('block_ajax_marking_groups', $params);
            } else {
                $groupsetting->display = $settingvalue;
                $success = $DB->update_record('block_ajax_marking_groups', $groupsetting);
            }
        } else {
            if (is_null($settingvalue)) { // Nothing to change.
                $success = true;
            } else {
                $groupsetting = new stdClass();
                $groupsetting->configid = $existingsetting->id;
                $groupsetting->groupid = $groupid;
                $groupsetting->display = $settingvalue;
                $success = $DB->insert_record('block_ajax_marking_groups', $groupsetting);
            }
        }

        if ($tablename === 'course') {
            // Delete all group settings for associated coursemodules, making them NULL.
            $sql = "     DELETE config_groups
                           FROM {block_ajax_marking_groups} config_groups
                     INNER JOIN {block_ajax_marking} course_module_config
                             ON (config_groups.configid = course_module_config.id
                                AND course_module_config.tablename = 'course_modules')
                     INNER JOIN {course_modules} course_modules
                             ON course_modules.id = course_module_config.instanceid
                     INNER JOIN {block_ajax_marking} course_config
                             ON (course_config.instanceid = course_modules.course
                                AND course_config.tablename = 'course')
                          WHERE course_config.id = :settingid
                            AND config_groups.groupid = :groupid
                    ";
            $params = array('settingid' => $existingsetting->id,
                            'groupid'   => $groupid);
            $DB->execute($sql, $params);
        }

        break;
}

$response = new stdClass();
$response->configsave = array('menuitemindex'  => $menuitemindex,
                              'settingtype'    => $settingtype,
                              'success'        => (bool)$success,
                              'newsetting'     => $settingvalue,
                              'groupid'        => $groupid,
                              'menutype'       => $menutype,
                              'menugroupindex' => $menugroupindex,
                              'nodeindex'      => $nodeindex);

// Cast to int for javascript strict type comparisons.
foreach ($response->configsave as &$value) {
    if (is_numeric($value)) {
        $value = (int)$value;
    }
}

echo json_encode($response);
