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
 * Class file for the Assign module grading functions
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2012 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot.'/blocks/ajax_marking/classes/query_base.class.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/classes/module_base.class.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/classes/filters.class.php');
require_once($CFG->dirroot.'/mod/assign/locallib.php');

/**
 * Extension to the block_ajax_marking_module_base class which adds the parts that deal
 * with the assign module.
 */
class block_ajax_marking_assign extends block_ajax_marking_module_base {

    /**
     * Constructor. Needs to be duplicated in all modules, so best put in parent. PHP4 issue though.
     *
     * The aim is to pass in the main ajax_marking_functions object by reference, so that its
     * properties are accessible
     *
     * @internal param object $reference the parent object to be referred to
     * @return \block_ajax_marking_assign
     */
    public function __construct() {

        // Call parent constructor with the same arguments (keep for 2.1 - PHP 5.3 needed.
        parent::__construct();

        $this->modulename = 'assign'; // DB modulename.
        $this->capability = 'mod/assign:grade';
        $this->icon = 'mod/assign/icon.gif';
    }

    /**
     * Makes the grading interface for the pop up
     *
     * @param array $params From $_GET
     * @param object $coursemodule The coursemodule object that the user has been authenticated
     * against
     * @param bool $data
     * @global $PAGE
     * @global stdClass $CFG
     * @global moodle_database $DB
     * @global $OUTPUT
     * @global stdClass $USER
     * @return string
     */
    public function grading_popup($params, $coursemodule, $data = false) {

        global $PAGE, $CFG, $DB, $OUTPUT;

        $output = '';

        return $output;
    }

    /**
     * Process and save the data from the feedback form.
     *
     * @param object $data from the feedback form
     * @param $params
     * @return string
     */
    public function process_data($data, $params) {

        return '';
    }

    /**
     * Returns a query object with the basics all set up to get assignment stuff
     *
     * @global moodle_database $DB
     * @return block_ajax_marking_query_base
     */
    public function query_factory() {

        global $DB;

        $query = new block_ajax_marking_query_base($this);

        $query->add_from(array(
                              'table' => 'assign',
                              'alias' => 'moduletable',
                         ));

        $query->add_from(array(
                              'join' => 'INNER JOIN',
                              'table' => 'assign_submission',
                              'alias' => 'sub',
                              'on' => 'sub.assignment = moduletable.id'
                         ));

        // Standard user id for joins.
        $query->add_select(array('table' => 'sub',
                                 'column' => 'userid'));
        $query->add_select(array('table' => 'sub',
                                 'column' => 'timemodified',
                                 'alias' => 'timestamp'));

        $statustext = $DB->sql_compare_text('sub.status');
        $query->add_where(array(
                               'type' => 'AND',
                               'condition' => $statustext." = '".ASSIGN_SUBMISSION_STATUS_SUBMITTED."'"));

        // First bit: not graded
        // Second bit of first bit: has been resubmitted
        // Third bit: if it's advanced upload, only care about the first bit if 'send for marking'
        // was clicked.

        return $query;
    }
}

/**
 * Holds any custom filters for userid nodes that this module offers
 */
class block_ajax_marking_assign_userid extends block_ajax_marking_filter_base {

    /**
     * Not sure we'll ever need this, but just in case...
     *
     * @static
     * @param block_ajax_marking_query_base $query
     * @param $userid
     */
    public static function where_filter($query, $userid) {
        $countwrapper = self::get_countwrapper_subquery($query);
        $clause = array(
            'type' => 'AND',
            'condition' => 'sub.userid = :assignuseridfilteruserid');
        $countwrapper->add_where($clause);
        $query->add_param('assignuseridfilteruserid', $userid);
    }

    /**
     * Makes user nodes for the assign modules by grouping them and then adding in the right
     * text to describe them.
     *
     * @static
     * @param block_ajax_marking_query_base $query
     */
    public static function nextnodetype_filter($query) {

        $countwrapper = self::get_countwrapper_subquery($query);

        // Make the count be grouped by userid.
        $conditions = array(
            'table' => 'moduleunion',
            'column' => 'userid',
            'alias' => 'id');
        $countwrapper->add_select($conditions, true);
        $conditions = array(
            'table' => 'countwrapperquery',
            'column' => 'timestamp',
            'alias' => 'tooltip');
        $query->add_select($conditions);
        // Need this to make the popup show properly because some assign code shows or
        // not depending on this flag to tell if it's in a pop-up e.g. the revert to draft
        // button for advanced upload.
        $conditions = array('column' => "'single'",
                            'alias' => 'mode');
        $query->add_select($conditions);

        $conditions = array(
            'table' => 'usertable',
            'column' => 'firstname');
        $query->add_select($conditions);
        $conditions = array(
            'table' => 'usertable',
            'column' => 'lastname');
        $query->add_select($conditions);

        $table = array(
            'table' => 'user',
            'alias' => 'usertable',
            'on' => 'usertable.id = countwrapperquery.id');
        $query->add_from($table);
    }
}
