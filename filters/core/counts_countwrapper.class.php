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
 * These classes provide filters that modify the dynamic query that fetches the nodes. Depending on
 * what node is being requested and what that node's ancestor nodes are, a different combination
 * of filters will be applied. There is one class per type of node, and one method with the class
 * for the type of operation. If there is a courseid node as an ancestor, we want to use the
 * courseid::where_filter, but if we are asking for courseid nodes, we want the
 * courseid::count_select filter.
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2012 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot.'/blocks/ajax_marking/filters/base.class.php');

/**
 * This decorator attaches the config tables for course and coursemodule so display decisions can be made
 * based ont heir settings.
 */
class block_ajax_marking_filter_core_counts_countwrapper extends block_ajax_marking_query_decorator_base {

    /**
     * @param block_ajax_marking_query $query
     */
    public function __construct(block_ajax_marking_query $query) {
        parent::__construct($query);
        $this->alter_query();
    }

    /**
     * Sticks the stuff onto the query. In this case, it's the stuff that every query needs
     * to pull out of the moduleunion.
     */
    protected function alter_query() {

        // TODO this needs to be sub.id so we can count two things for one student.
        $this->wrappedquery->add_select(array('table' => 'moduleunion',
                                             'column' => 'userid',
                                             'alias' => 'itemcount',
                                             // COUNT is a reserved word.
                                             'function' => 'COUNT'));

        // To get the three times for recent, medium and overdue pieces of work, we do three
        // count operations here.
        $fourdaysago = time() - BLOCK_AJAX_MARKING_FOUR_DAYS;
        $tendaysago = time() - BLOCK_AJAX_MARKING_TEN_DAYS;
        $recentcolumn = "CASE WHEN (moduleunion.timestamp > {$fourdaysago}) THEN 1 ELSE 0 END";
        $this->wrappedquery->add_select(array('column' => $recentcolumn,
                                             'alias' => 'recentcount',
                                             // COUNT is a reserved word.
                                             'function' => 'SUM'));
        $mediumcolumn = "CASE WHEN (moduleunion.timestamp < {$fourdaysago} AND ".
            "moduleunion.timestamp > {$tendaysago}) THEN 1 ELSE 0 END";
        $this->wrappedquery->add_select(array('column' => $mediumcolumn,
                                             'alias' => 'mediumcount',
                                             // COUNT is a reserved word.
                                             'function' => 'SUM'));
        $overduecolumn = "CASE WHEN moduleunion.timestamp < $tendaysago THEN 1 ELSE 0 END";
        $this->wrappedquery->add_select(array('column' => $overduecolumn,
                                             'alias' => 'overduecount',
                                             // COUNT is a reserved word.
                                             'function' => 'SUM'));
    }

}
