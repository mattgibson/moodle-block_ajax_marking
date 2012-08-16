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
 * Makes the query retrieve the highest visible groupid for each submission. This takes account of the fact that
 * students can be in more than one group and those groups may or may not be hidden by the block settings. We
 * always want this to be used so we can filter based on settings.
 */
class block_ajax_marking_filter_groupid_attach_highest extends block_ajax_marking_query_decorator_base {

    /**
     * This will add a groupid to each of the submissions coming out of the moduleunion. This means getting
     * all the group memberships for this course and choosing the maximum groupid. We can't count more than
     * once when students are in two groups, so we need to do it like this. It's a greatest-n-per-group
     * problem, solved with the classic left-join approach.
     *
     * @param block_ajax_marking_module_query $query
     * @return void
     */
    protected function alter_query(block_ajax_marking_module_query $query) {

        list($maxquery, $maxparams) = block_ajax_marking_group_max_subquery();
        list($memberquery, $memberparams) = block_ajax_marking_group_members_subquery();
        list($visibilitysubquery, $visibilityparams) = block_ajax_marking_group_visibility_subquery();

        // We need to join to groups members to see if there are any memberships at all (in which case
        // we use the highest visible id if there is one), or 0 if there are no memberships at all.
        $table = array(
            'join' => 'LEFT JOIN',
            'table' => $memberquery,
            'on' => 'membergroupquery.userid = '.$query->get_userid_column().
                     ' AND membergroupquery.coursemoduleid = '.$query->get_coursemoduleid_column(),
            'alias' => 'membergroupquery',
            'subquery' => true);
        $query->add_from($table);
        $query->add_params($memberparams);

        // To make sure it's the highest visible one, we use this subquery as a greatest-n-per-group thing.
        $table = array(
            'join' => 'LEFT JOIN',
            'table' => $maxquery,
            'on' => 'membergroupquery.userid = maxgroupquery.userid
                     AND membergroupquery.coursemoduleid = maxgroupquery.coursemoduleid
                     AND maxgroupquery.groupid > membergroupquery.groupid',
            'alias' => 'maxgroupquery',
            'subquery' => true);
        $query->add_from($table);
        $query->add_params($maxparams);

        // We join only if the group id is larger, then specify that it must be null. This means that
        // the membergroupquery group id will be the largest available.
        $query->add_where(array(
                           'type' => 'AND',
                           'condition' => 'maxgroupquery.groupid IS NULL'
                      ));

        // Make sure it's not hidden. We want to know if there are people with no group, compared to a group that
        // is hidden, so the aim is to get a null group id if there are no memberships by left joining, then
        // hide that null row if the settings for group id 0 say so.
        $table = array(
            'join' => 'LEFT JOIN',
            'table' => $visibilitysubquery,
            'on' => '(membervisibilityquery.groupid = membergroupquery.groupid OR
                      (membervisibilityquery.groupid = 0 AND membergroupquery.groupid IS NULL))
                     AND membervisibilityquery.coursemoduleid = membergroupquery.coursemoduleid',
            'alias' => 'membervisibilityquery',
            'subquery' => true);
        $query->add_from($table);
        $query->add_params($visibilityparams);
        $query->add_where(array(
            'type' => 'AND',
            'condition' => 'membervisibilityquery.coursemoduleid IS NULL'
        ));

    }
}
