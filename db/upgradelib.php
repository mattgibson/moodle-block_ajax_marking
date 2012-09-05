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
 * Holds functions used in upgrade and install of the block.
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2012 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Removes the 'groups' field from the main settings table, so it can be made into a separate
 * join table.
 */
function block_ajax_marking_drop_groups_field() {

    global $DB;

    $dbman = $DB->get_manager();

    $table = new xmldb_table('block_ajax_marking');
    $field = new xmldb_field('groups');

    // Launch drop field groups.
    if ($dbman->field_exists($table, $field)) {
        $dbman->drop_field($table, $field);
    }
}

/**
 * Add a new field for showing whether each group should be displayed. Allows override of.
 */
function block_ajax_marking_add_display_field() {

    global $DB;

    $dbman = $DB->get_manager();

    // Show this group that may have been set at course level.
    $table = new xmldb_table('block_ajax_marking_groups');
    $field = new xmldb_field('display', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, null,
                             null, '0', 'groupid');
    // Conditionally launch add field.
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }
}

/**
 * Add a new field for showing whether groups should be displayed.
 */
function block_ajax_marking_add_groups_display_field() {

    global $DB;

    $dbman = $DB->get_manager();

    $table = new xmldb_table('block_ajax_marking');
    $field = new xmldb_field('groupsdisplay', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, null,
                             null, '0', 'display');
    // Conditionally launch add field.
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }
}

/**
 * Alters the table to use coursemodule id instead of module name and module id. Separating due
 * to an xml problem that meant that some sites didn't upgrade cleanly and needed redoing.
 *
 * @return array
 */
function block_ajax_marking_change_config_to_courseid() {

    global $DB;

    $dbman = $DB->get_manager();

    $existingrecords = $DB->get_records('block_ajax_marking');

    // Make the old columns go away.

    // Define field courseid to be dropped from block_ajax_marking.
    $table = new xmldb_table('block_ajax_marking');

    $fieldstodrop = array(
        'courseid',
        'coursemoduleid',
        'assessmenttype',
        'assessmentid'
    );

    foreach ($fieldstodrop as $fieldtodrop) {
        $field = new xmldb_field($fieldtodrop);
        // Conditionally launch drop field.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
    }

    // Add a new field for holding general ids from various tables.
    $field = new xmldb_field('instanceid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null,
                             null, '0', 'userid');
    // Conditionally launch add field.
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    // Add a new field for holding the table name.
    $field = new xmldb_field('tablename', XMLDB_TYPE_CHAR, '40', null, null, null,
                             null, 'instanceid');
    // Conditionally launch add field.
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    // Remove old data in the wrong format.
    $sql = "TRUNCATE TABLE {block_ajax_marking}";
    $DB->execute($sql);

    // Put the old data back.
    $modids = $DB->get_records('modules', array(), '', 'name, id');
    foreach ($existingrecords as $record) {
        $oldid = $record->id;
        unset($record->id);
        if (!empty($record->courseid)) {
            $record->instanceid = $record->courseid;
            $record->tablename = 'course';
        } else if (!empty($record->coursemoduleid)) {
            $record->instanceid = $record->coursemoduleid;
            $record->tablename = 'course_modules';
        } else {
            // Previous upgrade failed somehow.
            $cmid = $DB->get_field('course_modules',
                                   'id',
                                   array('module' => $modids[$record->assessmenttype]->id,
                                         'instance' => $record->assessmentid));
            $record->tablename = 'course_modules';
            $record->instanceid = $cmid;
        }

        $newid = $DB->insert_record('block_ajax_marking', $record);
        $sql = "UPDATE {block_ajax_marking_groups}
                       SET configid = :newid
                     WHERE configid = :oldid ";
        $DB->execute($sql,
                     array('oldid' => $oldid,
                           'newid' => $newid));
    }
}

/**
 * Adds an index that massively speeds up the query to get unmarked essay questions. This has a major perfomance
 * impact, taking the isolated quiz bit from 37 seconds to 1.2 seconds on a question_attempt_steps table of > 5
 * million rows.
 */
function block_ajax_marking_add_index_question_attempt_steps() {

    global $DB;

    $dbman = $DB->get_manager();

    // Define index amb_questattstep_combo to be added to question_attempt_steps.
    $table = new xmldb_table('question_attempt_steps');
    $index = new xmldb_index('amb_questattstep_combo', XMLDB_INDEX_NOTUNIQUE, array('state', 'questionattemptid',
                                                                                    'userid', 'timecreated'));

    // Conditionally launch add index amb_questattstep_combo.
    if (!$dbman->index_exists($table, $index)) {
        $dbman->add_index($table, $index);
    }

}

/**
 * The context table may be huge and we need to find out the maximum depth so we can get teacher courses that are not
 * at system level for admins. There are no indexes covering this, so we make one here. Not a massive gain, but it goes
 * from 200ms to 40ms, so still worth it.
 */
function block_ajax_marking_add_index_context() {

    global $DB;

    $dbman = $DB->get_manager();

    // Define index amb_questattstep_combo to be added to question_attempt_steps.
    $table = new xmldb_table('context');
    $index = new xmldb_index('amb_context_combo', XMLDB_INDEX_NOTUNIQUE, array('contextlevel', 'depth'));

    // Conditionally launch add index amb_questattstep_combo.
    if (!$dbman->index_exists($table, $index)) {
        $dbman->add_index($table, $index);
    }
}

/**
 * The enrol table needs an index that covers all the fields used in the join and where for the lookups
 * of whether students are enrolled. As this is a NOT EXISTS, which is run against every potential unmarked row,
 * it must be fast, so this coupled with one of the existing core indexes on user_enrolments allows the index to
 * be used exclusively.
 */
function block_ajax_marking_add_index_enrol() {

    global $DB;

    $dbman = $DB->get_manager();

    // Define index amb_enrol_combo to be added to enrol.
    $table = new xmldb_table('enrol');
    $index = new xmldb_index('amb_enrol_combo', XMLDB_INDEX_NOTUNIQUE, array('courseid', 'enrol', 'id'));

    // Conditionally launch add index amb_enrol_combo.
    if (!$dbman->index_exists($table, $index)) {
        $dbman->add_index($table, $index);
    }
}

/**
 * This allows fast lookups of groups settings using a covering index.
 */
function block_ajax_marking_add_index_groups_settings() {

    global $DB;

    $dbman = $DB->get_manager();

    // Define index amb_enrol_combo to be added to enrol.
    $table = new xmldb_table('block_ajax_marking_groups');
    $index = new xmldb_index('amb_groups_settings_combo', XMLDB_INDEX_UNIQUE, array('configid',
                                                                             'groupid',
                                                                             'display'));

    // Conditionally launch add index amb_enrol_combo.
    if (!$dbman->index_exists($table, $index)) {
        $dbman->add_index($table, $index);
    }
}

/**
 * This allows fast lookups of groups settings using a covering index.
 */
function block_ajax_marking_add_index_groups_members() {

    global $DB;

    $dbman = $DB->get_manager();

    // Define index amb_enrol_combo to be added to enrol.
    $table = new xmldb_table('groups_members');
    $index = new xmldb_index('amb_groups_members_combo', XMLDB_INDEX_UNIQUE, array('userid', 'groupid'));

    // Conditionally launch add index amb_enrol_combo.
    if (!$dbman->index_exists($table, $index)) {
        $dbman->add_index($table, $index);
    }
}

/**
 * This allows fast lookups of groups settings using a covering index.
 */
function block_ajax_marking_add_index_settings() {

    global $DB;

    $dbman = $DB->get_manager();

    // Define index amb_enrol_combo to be added to enrol.
    $table = new xmldb_table('block_ajax_marking');
    $index = new xmldb_index('amb_settings_combo', XMLDB_INDEX_UNIQUE, array('userid',
                                                                             'tablename',
                                                                             'instanceid'));

    // Conditionally launch add index amb_enrol_combo.
    if (!$dbman->index_exists($table, $index)) {
        $dbman->add_index($table, $index);
    }
}
