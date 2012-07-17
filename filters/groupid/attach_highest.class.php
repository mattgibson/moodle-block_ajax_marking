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

require_once($CFG->dirroot.'/blocks/ajax_marking/filters/attach_base.class.php');

/**
 * Makes the query retrieve the highest visible groupid for each submission. This takes account of the fact that
 * students can be in more than one group and those groups may or may not be hidden by the block settings. We
 * always want this to be used so we can filter based on settings.
 */
class block_ajax_marking_filter_groupid_attach_highest extends block_ajax_marking_filter_attach_base {

    /**
     * This will add a groupid to each of the submissions coming out of the moduleunion. This means getting
     * all the group memberships for this course and choosing the maximum groupid. We can't count more than
     * once when students are in two groups, so we need to do it like this. It's a greatest-n-per-group
     * problem, solved with the classic left-join approach.
     *
     * @param block_ajax_marking_query $query
     * @return void
     */
    protected function alter_query(block_ajax_marking_query $query) {

        list($visibilitysubquery, $maxgroupidparams) = block_ajax_marking_group_visibility_subquery();

        $maxgroupidsubquery = <<<SQL
        /* This query shows the maximum visible group id for every group member-coursemodule combination */
         SELECT members.userid,
                MAX(displaytable.groupid) AS groupid,
                displaytable.cmid
           FROM ({$visibilitysubquery}) AS displaytable
     INNER JOIN {groups_members} members
             ON members.groupid = displaytable.groupid
          WHERE displaytable.display = 1
       GROUP BY members.userid,
                displaytable.cmid
        /* End query to show maximum visible group id */
SQL;

        $query->add_params($maxgroupidparams);
        $table = array(
            'join' => 'LEFT JOIN',
            'table' => $maxgroupidsubquery,
            'on' => 'maxgroupidsubquery.cmid = moduleunion.coursemoduleid AND
                             maxgroupidsubquery.userid = moduleunion.userid',
            'alias' => 'maxgroupidsubquery',
            'subquery' => true);
        $query->add_from($table);

        // Make sure we don't retrieve items where there are group memberships and they are all set to hidden.
        $query->add_where(array(
                               'type' => 'AND',
                               'condition' => block_ajax_marking_get_countwrapper_groupid_sql().' IS NOT NULL'
                          ));

    }
}
