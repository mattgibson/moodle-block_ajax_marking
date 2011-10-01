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
 * This will get the dynamic CSS rules from each of the module plugins
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2011 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// make the class files happy
define('MOODLE_INTERNAL', true);

// include all the files in the modules directory
$directory = opendir(dirname(__FILE__).'/modules');

if ($directory) {

    require_once(dirname(__FILE__).'/classes/module_base.class.php');

    while (($modulefile = readdir($directory)) !== false) {

        if ($modulefile == '.' || $modulefile == '..') {
            continue;
        }

        $filepath = dirname(__FILE__).'/modules/'.$modulefile.'/block_ajax_marking_'.$modulefile.
                    '.class.php';

        require_once($filepath);

        $modulename = substr($modulefile, 0, -4);  //remove '.php'
        $classname = 'block_ajax_marking_'.$modulename;
        call_user_func($classname.'::print_css');
    }

    closedir($directory);
}