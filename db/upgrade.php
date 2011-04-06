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
 * This is the file that contains all the upgrade code
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2008 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Standard upgrade function run every time the block's version number changes
 *
 * @param int $oldversion the current version of the installed block
 * @return bool
 */
function xmldb_block_ajax_marking_upgrade($oldversion=0) {

    //echo "oldversion: ".$oldversion;
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2007052901) { //New version in version.php

        // Define table block_ajax_marking to be created
        $table = new xmldb_table('block_ajax_marking');

        // Adding fields to table block_ajax_marking
        $table->add_field('id',             XMLDB_TYPE_INTEGER, '10'    , XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);
        $table->add_field('userid',         XMLDB_TYPE_INTEGER, '10'    , XMLDB_UNSIGNED, null, null, null, null);
        $table->add_field('assessmenttype', XMLDB_TYPE_CHAR,    '40'    , null, null, null, null, null);
        $table->add_field('assessmentid',   XMLDB_TYPE_INTEGER, '10'    , XMLDB_UNSIGNED, null, null, null, null);
        $table->add_field('showhide',       XMLDB_TYPE_INTEGER, '1'     , XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, '1');
        $table->add_field('groups',         XMLDB_TYPE_TEXT,    'small' , null, null, null, null, null);


        // Adding keys to table block_ajax_marking
        $table->add_key('primary',   XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('useridkey', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));

        // Launch create table for block_ajax_marking
        $dbman->create_table($table);
        
        upgrade_mod_savepoint(true, 2007052901, 'block_ajax_marking');
    }

    if ($oldversion < 2010061801) {

        // Define key useridkey (foreign) to be dropped form block_ajax_marking
        $table = new XMLDBTable('block_ajax_marking');
        $key   = new XMLDBKey('useridkey');
        $key->setAttributes(XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));

        // Launch drop key useridkey
        $result = $result && drop_key($table, $key);

        // Define table block_ajax_marking_groups to be created
        $table = new XMLDBTable('block_ajax_marking_groups');
        
        //$table->add_field();

        // Adding fields to table block_ajax_marking_groups
        $table->add_field('id',       XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);
        $table->add_field('configid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null);
        $table->add_field('groupid',  XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null);
        $table->add_field('display',  XMLDB_TYPE_INTEGER, '1',  XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null);

        // Adding keys to table block_ajax_marking_groups
        $table->add_key('primary',     XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('configid-id', XMLDB_KEY_FOREIGN, array('configid'), 'block_ajax_marking', array('id'));

        // Launch create table for block_ajax_marking_groups
        $dbman->create_table($table);

        // Transfer all groups stuff to the new table

        $sql = "SELECT id, groups FROM {block_ajax_marking}";
        $oldrecords = $DB->get_records_sql($sql);

        foreach ($oldrecords as $record) {

            // get the csv groups from the groups column
            if(!empty($record->groups)) {
                $groups = explode(' ', trim($record->groups));

                foreach ($groups as $group) {

                    $data = new stdClass;
                    $data->groupid  = $group;
                    $data->configid = $record->id;
                    // when we switch from groups to something else, we wanty to keep the groups settings
                    // so that it's easier to switch back. Alternative is to have to re enter the groups
                    // after they are removed from this table
                    $data->display  = 1;

                    $DB->insert_record('block_ajax_marking_groups', $data);
                }
            }
        }

        //Drop the groups column on the old table
        
        // Define field groups to be dropped from block_ajax_marking
        $table = new XMLDBTable('block_ajax_marking');
        $field = new XMLDBField('groups');

        // Launch drop field groups
        $dbman->drop_field($table, $field);
        
        upgrade_mod_savepoint(true, 2010061801, 'block_ajax_marking');


    }

    return true;
}