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
     * @return void
     */
    protected function alter_query() {

        global $DB;

        $query = $this->wrappedquery;

        list($visibilitysubquery, $visibilityparams) = block_ajax_marking_group_visibility_subquery();

        // These three tables get us a group id that's not hidden based on the user's group memberships.

        // WE can't just use this as there may be more than one membership per user per course, so we
        // need a LEFT JOIN with greatest-=n-per-group. Trouble is, some of these groups may be set to 'hidden'.
        $query->add_from(
            array(
                 'join' => 'LEFT JOIN',
                 'table' => 'groups_members',
                 'alias' => 'gmember_members',
                 'on' => 'gmember_members.userid = '.$query->get_column('userid')
            ));
        // Limit to user's courses for performance.
        $extrajoin = '';
        $courses = block_ajax_marking_get_my_teacher_courses();
        if (!block_ajax_marking_admin_see_all() && !empty($courses)) {
            list($gmembercoursesql, $gmembercourseparams) =
                $DB->get_in_or_equal(array_keys($courses), SQL_PARAMS_NAMED);

            $query->add_params($gmembercourseparams);
            $extrajoin =  ' AND gmember_groups.courseid '.$gmembercoursesql;
        }
        $query->add_from(
            array(
                 'join' => 'LEFT JOIN',
                 'table' => 'groups',
                 'alias' => 'gmember_groups',
                 'on' => 'gmember_groups.id = gmember_members.groupid'.
                       ' AND gmember_groups.courseid = '.$query->get_column('courseid').$extrajoin

            )
        );
        // This subquery has all the group-coursemoudule combinations that the user has configured as hidden via
        // the block settings. We want this to be null as any value shows that the user has chosen it to be hidden.
        // If any are not null, then those groups must be excluded from the list we can choose from to choose the
        // highest id.
        $query->add_from(
            array(
                 'join' => 'LEFT JOIN',
                 'table' => $visibilitysubquery,
                 'subquery' => true,
                 'alias' => 'gvis',
                 'on' => '(gvis.groupid = gmember_groups.id OR (gvis.groupid = 0 AND gmember_groups.id IS NULL))
                    AND gvis.coursemoduleid = '.$query->get_column('coursemoduleid')
            ));
        $query->add_params($visibilityparams);

        // Due to using two tables for the left joins, we need to make sure they either match or are both null. Because
        // we may have two groups and because we may wish to ignore one of them, we specify that we cannot
        // have mixed nulls and groupids for the first set of tables that we are reading from, but the second, we
        // either want nothing, or we allow.
        $query->add_where('((gmember_groups.id = gmember_members.groupid)
                                  OR (gmember_groups.id IS NULL AND gmember_members.groupid IS NULL))');
        $query->add_where('gvis.groupid IS NULL');

        // LEFT JOIN with all three tables failed because the join needs to happen if the groupid is greater but only
        // if the group visibility table says the group is visible. It's not possible to reference a where condition
        // like that in a join, so NOT EXISTS is here instead. The optimiser ought to be able to short circuit it.
        $gmaxcoursesql = '';
        $gmaxcourseparams = array();
        if (!block_ajax_marking_admin_see_all() && !empty($courses)) {
            list($gmaxcoursesql, $gmaxcourseparams) = $DB->get_in_or_equal(array_keys($courses), SQL_PARAMS_NAMED);
            $gmaxcoursesql = ' AND gmax_groups.courseid '.$gmaxcoursesql;
        }
        list($gmaxvisibilitysubquery, $gmaxvisibilityparams) = block_ajax_marking_group_visibility_subquery();
        $query->add_where("NOT EXISTS (
                                    SELECT 1
                                      FROM {groups_members} gmax_members
                                INNER JOIN {groups} gmax_groups
                                        ON gmax_groups.id = gmax_members.groupid
                                     WHERE NOT EXISTS (SELECT 1
                                                         FROM ($gmaxvisibilitysubquery) gmax_vis
                                                        WHERE gmax_vis.groupid = gmax_groups.id
                                                          AND gmax_vis.coursemoduleid = {$query->get_column('coursemoduleid')})
                                       AND gmax_members.userid = {$query->get_column('userid')}
                                       AND gmax_groups.courseid = {$query->get_column('courseid')}
                                       AND gmax_members.groupid > gmember_members.groupid
                                       {$gmaxcoursesql}
                 )", array_merge($gmaxcourseparams, $gmaxvisibilityparams)
            );

        $sqltogettothegroupid = block_ajax_marking_get_countwrapper_groupid_sql($query);
        $query->set_column('groupid', $sqltogettothegroupid);

    }
}
