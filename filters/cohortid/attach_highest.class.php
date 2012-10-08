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
 * Makes the query retrieve the highest visible cohort id for each submission. This takes account of the fact that
 * students can be in more than one cohort and those cohorts may or may not be hidden by the block settings. We
 * always want this to be used so we can filter based on settings.
 *
 * @todo This doesn't work. Use greatest-n-per-group to fix it.
 */
class block_ajax_marking_filter_cohortid_attach_highest extends block_ajax_marking_query_decorator_base {

    /**
     * This will join the cohorts tables so tht the id can be added to the query in some way.
     *
     * @todo doesn't deal with a user being in more than one cohort yet.
     * @return void
     */
    protected function alter_query() {

        // We need to join the userid to the cohort, if there is one.
        // TODO when is there not one?
        // Add join to cohort_members.
        $table = array(
            'join' => 'INNER JOIN',
            'table' => 'cohort_members',
            'on' => 'cohort_members.userid = moduleunion.userid'
        );
        $this->wrappedquery->add_from($table);
        $table = array(
            'join' => 'INNER JOIN',
            'table' => 'cohort',
            'on' => 'cohort_members.cohortid = cohort.id'
        );
        $this->wrappedquery->add_from($table);

    }
}
