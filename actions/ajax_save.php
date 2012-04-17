<?PHP
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
 * Saves data sent back via AJAX from the block config or grading interfaces
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2011 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


define('AJAX_SCRIPT', true);

require_once(dirname(__FILE__).'/../../../config.php');

require_once($CFG->dirroot.'/blocks/ajax_marking/lib.php');

block_ajax_marking_login_error();
require_login(0, false);

// Target = what function is going to be doing the save operation. Either a core thing for
// config stuff, or a module name
$target = required_param('target', PARAM_ALPHA);

// Work out where to send it for processing
switch ($target) {

    case 'config_save':
        break;

    default:
        $modules = block_ajax_marking_get_module_classes();
        $modules[$target]->ajax_save();
        break;
}

// send it
