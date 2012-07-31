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
 * Class file for the Coursework module grading functions
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

/**
 * Extension to the block_ajax_marking_module_base class which adds the parts that deal
 * with the assign module.
 */
class block_ajax_marking_coursework extends block_ajax_marking_module_base {

    /**
     * Constructor. Needs to be duplicated in all modules, so best put in parent. PHP4 issue though.
     *
     * The aim is to pass in the main ajax_marking_functions object by reference, so that its
     * properties are accessible
     *
     * @internal param object $reference the parent object to be referred to
     * @return \block_ajax_marking_coursework
     */
    public function __construct() {

        // Call parent constructor with the same arguments (keep for 2.1 - PHP 5.3 needed).
        parent::__construct();

        $this->modulename = 'coursework'; // DB modulename.
        $this->capability = 'mod/coursework:grade';
    }

    /**
     * Makes the grading interface for the pop up.
     *
     * @param array $params From $_GET
     * @param object $coursemodule The coursemodule object that the user has been authenticated
     * against
     * @param bool $data
     * @return string
     */
    public function grading_popup($params, $coursemodule, $data = false) {

    }

    /**
     * Process and save the data from the feedback form.
     *
     * @param object $data from the feedback form
     * @param array $params
     * @return string
     */
    public function process_data($data, $params) {

    }

    /**
     * Returns a query object with the basics all set up to get ungraded coursework stuff
     *
     * @global moodle_database $DB
     * @return block_ajax_marking_query_base
     */
    public function query_factory() {

        global $DB, $USER;

        $query = new block_ajax_marking_query_base($this);

        $table = array(
            'table' => 'coursework',
            'alias' => 'moduletable',
        );
        $query->add_from($table);
        $table = array(
            'join' => 'INNER JOIN',
            'table' => 'coursework_submissions',
            'alias' => 'sub',
            'on' => 'sub.courseworkid = moduletable.id'
        );
        $query->add_from($table);
        // LEFT JOIN, rather than NOT EXISTS because we may have an empty feedback saved, which
        // will create a grade record, but with a null grade. These should still count as ungraded.
        // What if it was reverted, then resubmitted? We still want these to show up for remarking.
        $table = array(
            'join' => 'LEFT JOIN',
            'table' => 'coursework_feedbacks',
            'on' => 'coursework_feedbacks.submissionid = sub.id
                     AND coursework_feedbacks.assessorid = :courseworkuserid
                     AND coursework_feedbacks.isfinalgrade = 0
                     AND coursework_feedbacks.ismoderation = 0
                     AND coursework_feedbacks.timemodified > sub.timemodified'
        );
        $query->add_from($table);
        $table = array(
            'join' => 'LEFT JOIN',
            'table' => 'coursework_allocation_pairs',
            'on' => 'coursework_allocation_pairs.courseworkid = moduletable.id
                     AND coursework_allocation_pairs.assessorid = :courseworkuserid2
                     AND coursework_allocation_pairs.studentid = sub.userid'
        );
        $query->add_from($table);
        $params = array(
            'courseworkuserid' => $USER->id,
            'courseworkuserid2' => $USER->id
        );
        $query->add_params($params);

        // Standard user id for joins.
        $column = array('table' => 'sub',
                        'column' => 'userid');
        $query->add_select($column);
        $column = array('table' => 'sub',
                        'column' => 'timemodified',
                        'alias' => 'timestamp');
        $query->add_select($column);

        // All work with no feedback record will show up.
        // TODO empty records from abandoned grading.
        // TODO formative with no grade.
        $where = array(
            'type' => 'AND',
            'condition' => 'coursework_feedbacks.id IS NULL');
        $query->add_where($where);
        // If allocations are in use, make sure we only return the ones for which there are relevant allocations.
        $where = array(
            'type' => 'AND',
            'condition' => '(moduletable.allocationenabled = 0
                             OR (moduletable.allocationenabled = 1 AND coursework_allocation_pairs.id IS NOT NULL))'
        );
        $query->add_where($where);
        $where = array(
            'type' => 'AND',
            'condition' => '(SELECT COUNT(countfeedbacks.id)
                               FROM {coursework_feedbacks} countfeedbacks
                              WHERE countfeedbacks.submissionid = sub.id
                                AND countfeedbacks.isfinalgrade = 0
                                AND countfeedbacks.ismoderation = 0) < moduletable.numberofmarkers
                                '
        );
        $query->add_where($where);

        return $query;

    }
}
