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
 * This is the file that contains all the code specific to the assignment module.
 *
 * @package   blocks-ajax_marking-db
 * @copyright 2008-2010 Matt Gibson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Check here for new modules to add to the database. This happens every time the block is upgraded.
 * If you have added your own extension, increment the version number in block_ajax_marking.php
 * to trigger this process. Also called after install.
 *
 * @return void
 */
function amb_update_modules() {

    global $CFG, $DB;

    $modules = array();
    echo '<br /><br />Scanning site for modules which have an AJAX Marking Block plugin... <br />';

    // make a list of directories to check for module grading files
    $installed_modules = get_list_of_plugins('mod');
    $directories = array($CFG->dirroot.'/blocks/ajax_marking');

    foreach ($installed_modules as $module) {
        $directories[] = $CFG->dirroot.'/mod/'.$module;
    }

    list($usql, $params) = $DB->get_in_or_equal($installed_modules);
    $sql = "SELECT name, id
              FROM {modules}
             WHERE name $usql";
    $module_ids = $DB->get_records_sql($sql, $params);

    // Get files in each directory and check if they fit the naming convention
    foreach ($directories as $directory) {
        $files = scandir($directory);

        // check to see if they end in _grading.php
        foreach ($files as $file) {
            // this should lead to 'modulename' and 'grading.php'
            $pieces = explode('_', $file);

            if ((isset($pieces[1])) && ($pieces[1] == 'grading.php')) {

                if (in_array($pieces[0], $installed_modules)) {

                    $modulename = $pieces[0];

                    // add the modulename part of the filename to the array
                    $modules[$modulename] = new stdClass;
                    $modules[$modulename]->name = $modulename;

                    // do not store $CFG->dirroot so that any changes to it will not break the block
                    $modules[$modulename]->dir  = str_replace($CFG->dirroot, '', $directory);
                    //$modules[$modulename]->dir  = $directory;

                    $modules[$modulename]->id   = $module_ids[$modulename]->id;

                    echo "Registered $modulename module <br />";
                }
            }
        }
    }

    echo '<br />For instructions on how to write extensions for this block, '
         .'see the documentation on Moodle Docs<br /><br />';

    set_config('modules', serialize($modules), 'block_ajax_marking');
}

/**
 * Standard upgrade function run every time the block's version number changes
 *
 * @param int $oldversion the current version of the installed block
 * @return bool
 */
function xmldb_block_ajax_marking_upgrade($oldversion=0) {

    //echo "oldversion: ".$oldversion;
    global $CFG, $THEME, $DB;

    $dbman = $DB->get_manager();

    $result = true;

    if ($result && $oldversion < 2007052901) { //New version in version.php

        // Define table block_ajax_marking to be created
        $table = new xmldb_table('block_ajax_marking');

        // Adding fields to table block_ajax_marking
        $table->add_field('id',             XMLDB_TYPE_INTEGER, '10'    , null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->add_field('userid',         XMLDB_TYPE_INTEGER, '10'    , null, null, null, null, null, null);
        $table->add_field('assessmenttype', XMLDB_TYPE_CHAR,    '40'    , null, null, null, null, null, null);
        $table->add_field('assessmentid',   XMLDB_TYPE_INTEGER, '10'    , null, null, null, null, null, null);
        $table->add_field('showhide',       XMLDB_TYPE_INTEGER, '1'     , null, XMLDB_NOTNULL, null, null, null, '1');
        $table->add_field('groups',         XMLDB_TYPE_TEXT,    'small' , null, null, null, null, null, null);


        // Adding keys to table block_ajax_marking
        $table->add_key('primary',   XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('useridkey', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));

        // Launch create table for block_ajax_marking
        $result = $result && $dbman->create_table($table);
    }

    if ($result && $oldversion < 2010050101) {

    }

    // run this on every upgrade.
    amb_update_modules();

    return $result;
}