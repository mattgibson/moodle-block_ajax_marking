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

if (!defined('AJAX_SCRIPT')) {
    define('AJAX_SCRIPT', true);
}

require_once(dirname(__FILE__).'/../../../config.php');

// For unit tests to work.
global $CFG, $PAGE;

require_once($CFG->dirroot.'/blocks/ajax_marking/lib.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/classes/module_base.class.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/classes/nodes_builder_base.class.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/classes/unmarked_nodes_builder.class.php');

block_ajax_marking_login_error();
require_login(0, false); // Still need this to set stuff up.

// TODO might be in a course.
$PAGE->set_context(context_system::instance());

// Each ajax request will have different stuff that we want to pass to the callback function. Using
// required_param() means hard-coding them.
$params = array();

// Need to get the filters in the right order so that the query receives them in the right order.
foreach ($_POST as $name => $value) {
    $params[$name] = clean_param($value, PARAM_ALPHANUMEXT);
}

if (isset($params['config'])) {
    $nodes = block_ajax_marking_nodes_builder_base::get_config_nodes($params);
} else {
    $nodes = block_ajax_marking_unmarked_nodes_builder::unmarked_nodes($params);
}

$nextnodefilter = block_ajax_marking_get_nextnodefilter_from_params($params);
foreach ($nodes as &$node) {
    block_ajax_marking_format_node($node, $nextnodefilter);
}

// Reindex array so we pick it up in js as an array and can find the length. Associative arrays
// with strings for keys are automatically sent as objects.
$nodes = array_values($nodes);
$data = array('nodes' => $nodes);
if (isset($params['nodeindex'])) {
    $data['nodeindex'] = (int)$params['nodeindex']; // Cast to int for javascript strict type comparisons.
}
echo json_encode($data);

