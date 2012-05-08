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

/**
 * Provides common functions that are needed when applying filters to the parameterised query object
 * such as extracting a particular subquery so it can be altered.
 */
class block_ajax_marking_filter_base {

    /**
     * Fetches the subquery from within the main query. Assumes that we have the outer displayquery
     * wrapped around it already.
     *
     * @param $query
     * @return block_ajax_marking_query_base
     */
    protected function get_countwrapper_subquery($query) {
        return $query->get_subquery('countwrapperquery');
    }

    /**
     * Fetches the subquery from within the main query. Assumes that we have the outer displayquery
     * and middle-level countwrapper query wrapped around it already.
     *
     * @param $query
     * @return block_ajax_marking_query_base
     */
    protected function get_moduleunion_subquery($query) {
        $coutwrapper = self::get_countwrapper_subquery($query);
        return $coutwrapper->get_subquery('moduleunion');
    }
}

/**
 * Applies the filter needed for course nodes or their descendants
 */
class block_ajax_marking_courseid extends block_ajax_marking_filter_base {

    /**
     * This is for when a courseid node is an ancestor of the node that has been
     * selected, so we just do a where.
     *
     * @param block_ajax_marking_query_base $query
     * @param int $courseid
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod) Dynamic method names don't register
     */
    public static function where_filter($query, $courseid) {

        $countwrapper = self::get_countwrapper_subquery($query);

        $conditions = array(
            'type' => 'AND',
            'condition' => 'moduleunion.course = :courseidfiltercourseid');
        $countwrapper->add_where($conditions);
        $query->add_param('courseidfiltercourseid', $courseid);
    }

    /**
     * This is for when a courseid node is an ancestor of the node that has been
     * selected, so we just do a where.
     *
     * @param block_ajax_marking_query_base $query
     * @param int $courseid
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod) Dynamic method names don't register
     */
    public static function configwhere_filter($query, $courseid) {

        $conditions = array(
            'type' => 'AND',
            'condition' => 'course_modules.course = :courseidfiltercourseid');
        $query->add_where($conditions);
        $query->add_param('courseidfiltercourseid', $courseid);
    }

    /**
     * Applies the filter needed for course nodes or their descendants
     *
     * @param block_ajax_marking_query_base $query
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod) Dynamic method names don't register
     */
    public static function nextnodetype_filter($query) {

        $countwrapper = self::get_countwrapper_subquery($query);

        $countwrapper->add_select(array(
                                       'table' => 'moduleunion',
                                       'column' => 'course',
                                       'alias' => 'id'), true
        );

        // This is for the displayquery when we are making course nodes.
        $query->add_from(array(
                              'table' => 'course',
                              'alias' => 'course',
                              'on' => 'countwrapperquery.id = course.id'
                         ));
        $query->add_select(array(
                                'table' => 'course',
                                'column' => 'shortname',
                                'alias' => 'name'));
        $query->add_select(array(
                                'table' => 'course',
                                'column' => 'fullname',
                                'alias' => 'tooltip'));

        $query->add_orderby('course.shortname ASC');
    }

    /**
     * Applies the filter needed for course nodes or their descendants
     *
     * @param block_ajax_marking_query_base $query
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod) Dynamic method names don't register
     */
    public static function confignextnodetype_filter($query) {

        global $USER;

        // This is for the displayquery when we are making course nodes.
        $query->add_from(array(
                              'table' => 'course',
                              'alias' => 'course',
                              'on' => 'course_modules.course = course.id'
                         ));
        $query->add_select(array(
                                'table' => 'course',
                                'column' => 'id',
                                'alias' => 'courseid',
                                'distinct' => true));
        $query->add_select(array(
                                'table' => 'course',
                                'column' => 'shortname',
                                'alias' => 'name'));
        $query->add_select(array(
                                'table' => 'course',
                                'column' => 'fullname',
                                'alias' => 'tooltip'));

        // We need the config settings too, if there are any.
        // TODO this should be in the config filter.
        $query->add_from(array(
                              'join' => 'LEFT JOIN',
                              'table' => 'block_ajax_marking',
                              'alias' => 'settings',
                              'on' => "settings.instanceid = course.id
                                 AND settings.tablename = 'course'
                                 AND settings.userid = :settingsuserid"
                         ));
        $query->add_param('settingsuserid', $USER->id);
        $query->add_select(array(
                                'table' => 'settings',
                                'column' => 'display'));
        $query->add_select(array(
                                'table' => 'settings',
                                'column' => 'groupsdisplay'));
        $query->add_select(array(
                                'table' => 'settings',
                                'column' => 'id',
                                'alias' => 'settingsid'));

        $query->add_orderby('course.shortname ASC');
    }
}

/**
 * Holds the filters to deal with coursemoduleid nodes.
 */
class block_ajax_marking_coursemoduleid extends block_ajax_marking_filter_base {

    /**
     * Used when there is an ancestor node representing a coursemodule.
     *
     * @static
     * @param block_ajax_marking_query_base $query
     * @param $coursemoduleid
     */
    public static function where_filter($query, $coursemoduleid) {
        $countwrapper = self::get_countwrapper_subquery($query);
        $conditions = array(
            'type' => 'AND',
            'condition' => 'moduleunion.coursemoduleid = :coursemoduleidfiltercmid');
        $countwrapper->add_where($conditions);
        $query->add_param('coursemoduleidfiltercmid', $coursemoduleid);
    }

    /**
     * @static
     * @param block_ajax_marking_query_base $query
     */
    public static function nextnodetype_filter($query) {

        $countwrapper = self::get_countwrapper_subquery($query);
        // Same order as the super query will need them. Prefixed so we will have it as the
        // first column for the GROUP BY.
        $countwrapper->add_select(array(
                                       'table' => 'moduleunion',
                                       'column' => 'coursemoduleid',
                                       'alias' => 'id'), true);
        $query->add_from(array(
                              'join' => 'INNER JOIN',
                              'table' => 'course_modules',
                              'on' => 'course_modules.id = countwrapperquery.id'));
        $query->add_select(array(
                                'table' => 'course_modules',
                                'column' => 'id',
                                'alias' => 'coursemoduleid'));
        $query->add_select(array(
                                'table' => 'countwrapperquery',
                                'column' => 'modulename'));

        // This will add the stuff that will show us the name of the actual module instance.
        // TODO is this even needed any more?
        self::configdisplay_filter($query);
    }

    /**
     * Used when we are looking at the coursemodule nodes in the config tree
     * Awkwardly, the course_module table doesn't hold the name and description of the
     * module instances, so we need to join to the module tables. This will cause a mess
     * unless we specify that only coursemodules with a specific module id should join
     * to a specific module table.
     *
     * @static
     * @param block_ajax_marking_query_base $query
     */
    public static function confignextnodetype_filter($query) {

        global $USER;

        $moduleclasses = block_ajax_marking_get_module_classes();
        $introcoalesce = array();
        $namecoalesce = array();
        $orderbycoalesce = array();
        foreach ($moduleclasses as $moduleclass) {
            $moduletablename = $moduleclass->get_module_name();
            $query->add_from(array(
                                  'join' => 'LEFT JOIN',
                                  'table' => $moduletablename,
                                  // Ids of modules will be constant for one install, so we
                                  // don't need to worry about parameterising them for
                                  // query caching.
                                  'on' => "(course_modules.instance = ".$moduletablename.".id
                                  AND course_modules.module = '".$moduleclass->get_module_id()."')"
                             ));
            $namecoalesce[$moduletablename] = 'name';
            $introcoalesce[$moduletablename] = 'intro';
            $orderbycoalesce[$moduletablename] = $moduletablename.'.name';
        }
        $query->add_select(array(
                                'table' => 'course_modules',
                                'column' => 'id',
                                'alias' => 'coursemoduleid'));
        $query->add_select(array(
                                'table' => $namecoalesce,
                                'function' => 'COALESCE',
                                'column' => 'name',
                                'alias' => 'name'));
        $query->add_select(array(
                                'table' => $introcoalesce,
                                'function' => 'COALESCE',
                                'column' => 'intro',
                                'alias' => 'tooltip'));

        // We need the config settings too, if there are any.
        $query->add_from(array(
                              'join' => 'LEFT JOIN',
                              'table' => 'block_ajax_marking',
                              'alias' => 'settings',
                              'on' => "settings.instanceid = course_modules.id
                                 AND settings.tablename = 'course_modules'
                                 AND settings.userid = :settingsuserid"
                         ));
        $query->add_param('settingsuserid', $USER->id);
        $query->add_select(array(
                                'table' => 'settings',
                                'column' => 'display'));
        $query->add_select(array(
                                'table' => 'settings',
                                'column' => 'groupsdisplay'));
        $query->add_select(array(
                                'table' => 'settings',
                                'column' => 'id',
                                'alias' => 'settingsid'));

        // TODO get them in order of module type first?
        $query->add_orderby('COALESCE('.implode(', ', $orderbycoalesce).') ASC');
    }
}

/**
 * Holds the filters to deal with user nodes.
 */
class block_ajax_marking_groupid extends block_ajax_marking_filter_base {

    /**
     * @static
     * @param block_ajax_marking_query_base $query
     * @param int $groupid
     */
    public static function where_filter($query, $groupid) {
        self::add_highest_groupid_to_submissions($query);
        $countwrapper = self::get_countwrapper_subquery($query);
        $conditions = array(
            'type' => 'AND',
            'condition' => 'COALESCE(maxgroupidsubquery.groupid, 0) = :groupid');
        $countwrapper->add_where($conditions);
        $countwrapper->add_param('groupid', $groupid);
    }

    /**
     * @static
     * @param block_ajax_marking_query_base $query
     */
    public static function nextnodetype_filter($query) {

        self::add_highest_groupid_to_submissions($query);

        $countwrapper = self::get_countwrapper_subquery($query);

        $countwrapper->add_select(array(
                                       'table' => array('maxgroupidsubquery' => 'groupid',
                                                        '0'),
                                       'function' => 'COALESCE',
                                       'alias' => 'id'));

        // This is for the displayquery when we are making course nodes.
        $query->add_from(array(
                              'join' => 'LEFT JOIN',
                              // Group id 0 will not match anything.
                              'table' => 'groups',
                              'on' => 'countwrapperquery.id = groups.id'
                         ));
        // We may get a load of people in no group.
        $query->add_select(array(
                                'function' => 'COALESCE',
                                'table' => array('groups' => 'name',
                                                 get_string('notingroup', 'block_ajax_marking')),
                                'alias' => 'name'));
        $query->add_select(array(
                                'function' => 'COALESCE',
                                'table' => array('groups' => 'description',
                                                 get_string('notingroupdescription',
                                                            'block_ajax_marking')),
                                'alias' => 'tooltip'));

        $query->add_orderby("COALESCE(groups.name, '".get_string('notingroup',
                                                                 'block_ajax_marking')."') ASC");
    }

    /**
     * This adds the subquery that can tell us wht the display settings are for each group.
     * Once we have filtered out those submissions with no visible groups, we choose
     * the best match i.e. randomly assign the submissions to one of its visible groups
     * (there will usually only be one) so it's not counted twice in case the user is
     * in two groups in this context.
     *
     * @static
     * @param block_ajax_marking_query_base $query
     */
    private static function add_highest_groupid_to_submissions($query) {

        $countwrapper = self::get_countwrapper_subquery($query);
        list($maxgroupidsubquery, $maxgroupidparams) = self::sql_max_groupid_subquery();
        $countwrapper->add_params($maxgroupidparams);
        $table = array(
            'join' => 'LEFT JOIN',
            'table' => $maxgroupidsubquery,
            'on' => 'maxgroupidsubquery.cmid = moduleunion.coursemoduleid AND
                             maxgroupidsubquery.userid = moduleunion.userid',
            'alias' => 'maxgroupidsubquery',
            'subquery' => true);
        $countwrapper->add_from($table);
    }

    /**
     * Once we have filtered out the ones we don't want based on display settings, those that are
     * left may have memberships in more than one group. We want to choose one of these so that
     * the piece of work is not counted twice. This query returns the maximum (in terms of DB row
     * id) groupid for each student/coursemodule pair where course modules are in the courses that
     * the user teaches. This has the potential to be expensive, so hopefully the inner join will
     * be used by the optimiser to limit the rows that are actually calculated to the ones
     * that the external query needs.
     *
     * @return array sql and params
     */
    private static function sql_max_groupid_subquery() {

        list($visibilitysubquery, $params) = self::group_visibility_subquery();

        $maxgroupsql = <<<SQL
         SELECT members.userid,
                MAX(displaytable.groupid) AS groupid,
                displaytable.cmid
           FROM ({$visibilitysubquery}) AS displaytable
     INNER JOIN mdl_groups_members members
             ON members.groupid = displaytable.groupid
       GROUP BY members.userid,
                displaytable.cmid
SQL;
        return array($maxgroupsql,
                     $params);
    }

    /**
     * In order to display the right things, we need to work out the visibility of each group for
     * each course module. This subquery lists all submodules once for each coursemodule in the
     * user's courses, along with it's most relevant show/hide setting, i.e. a coursemodule level
     * override if it's there, otherwise a course level setting, or if neither, the site default.
     * This is potentially very expensive if there are hundreds of courses as it's effectively a
     * cartesian join between the groups and coursemodules tables, so we filter using the user's
     * courses. This may or may not impact on the query optimiser being able to cache the execution
     * plan between users.
     *
     * We need to reuse this subquery. Because it uses the user's teacher courses as a
     * filter (less calculation that way), we might have issues with the query optimiser
     * not reusing the execution plan. Hopefully not.
     *
     * // Query visualisation:
     *               ______________________________________________________________
     *               |                                                            |
     * Course - Groups - coursemodules                                            |
     *               |   course |  |id__ block_ajax_marking_settings - block_ajax_marking_groups
     *               |          |            (for coursemodules)           (per coursemodule)
     *               |          |
     *               |          |_____ block_ajax_marking_settings --- block_ajax_marking_groups
     *               |                       (for courses)                   (per course)
     *               |____________________________________________________________|
     *
     *
     * Table we get back:
     * ----------------------------------------
     * | course_module_id/ | groupid | display |
     * |    course_id      |         |
     * |---------------------------------------|
     * |        543        |    67   |    0    |
     * |        342        |    6    |    1    |
     *
     * @param string $type We may want to know the combined visibility (coalesce) or just the
     * visibility at either (course) or (coursemodule) level. The latter two are for getting
     * the groups in their current settings states so config stuff can be adjusted.
     *
     * @return array SQL and params
     * @todo Unit test this!
     * @SuppressWarnings(PHPMD.UnusedLocalVariable) Debuggable query
     */
    public static function group_visibility_subquery($type = 'coalesce') {

        global $DB, $CFG, $USER;

        // In case the subquery is used twice, this variable allows us to feed the same teacher
        // courses in more than once because Moodle requires variables with different suffixes.
        static $counter = 0;
        $counter++;

        $courses = block_ajax_marking_get_my_teacher_courses();
        list($coursessql, $coursesparams) = $DB->get_in_or_equal(array_keys($courses),
                                                                 SQL_PARAMS_NAMED,
                                                                 "groups{$counter}courses");
        $sitedefault = 1; // Configurable in future.
        $select = $join = $where = '';

        // These fragments are recombined as needed. Arguably less duplication is better than the 3
        // separate functions this would otherwise need.
        $coursemodulejoin = <<<SQL
     LEFT JOIN {block_ajax_marking} group_cmconfig
            ON group_course_modules.id = group_cmconfig.instanceid
                AND group_cmconfig.tablename = 'course_modules'
     LEFT JOIN {block_ajax_marking_groups} group_cmconfig_groups
            ON group_cmconfig_groups.configid = group_cmconfig.id
           AND group_cmconfig_groups.groupid = group_groups.id
SQL;
        $coursejoin = <<<SQL
     LEFT JOIN {block_ajax_marking} group_courseconfig
            ON group_courseconfig.instanceid = group_course_modules.course
                AND group_courseconfig.tablename = 'course'
     LEFT JOIN {block_ajax_marking_groups} group_courseconfig_groups
            ON group_courseconfig_groups.configid = group_courseconfig.id
               AND group_courseconfig_groups.groupid = group_groups.id
SQL;
        $coursewhere = <<<SQL
           AND (group_courseconfig.userid = :groupuserid1_{$counter}
                OR group_courseconfig.userid IS NULL)
SQL;
        $coursemodulewhere = <<<SQL
           AND (group_cmconfig.userid = :groupuserid2_{$counter}
                OR group_cmconfig.userid IS NULL)
SQL;

        // We have similar code in use for three cases, so we construct the SQL dynamically.
        switch ($type) {
            case 'coalesce':
                $select = <<<SQL
               group_course_modules.id AS cmid,
               COALESCE(group_cmconfig_groups.display,
                        group_courseconfig_groups.display,
                        {$sitedefault}) AS display
SQL;
                $join = $coursemodulejoin.$coursejoin;
                $where = $coursewhere.$coursemodulewhere;
                $coursesparams['groupuserid1_'.$counter] = $USER->id;
                $coursesparams['groupuserid2_'.$counter] = $USER->id;
                break;

            case 'course':
                $select = <<<SQL
               group_groups.courseid,
               group_courseconfig_groups.display AS display
SQL;
                $join = $coursejoin;
                $where = $coursewhere;
                $coursesparams['groupuserid1_'.$counter] = $USER->id;
                break;

            case 'coursemodule':
                $select = <<<SQL
               group_course_modules.id AS cmid,
               group_cmconfig_groups.display AS display
SQL;
                $join = $coursemodulejoin;
                $where = $coursemodulewhere;
                $coursesparams['groupuserid2_'.$counter] = $USER->id;
                break;
        }

        $separategroups = SEPARATEGROUPS;
        // The later part is making sure that we hide groups that a teacher is not a member of
        // when the group mode is set to 'separate groups'.
        // TODO does moving the group id stuff into a WHERE xx OR IS NULL make it faster?
        $groupdisplaysubquery = <<<SQL
        SELECT group_groups.id AS groupid,
               {$select}
          FROM {course_modules} group_course_modules
    INNER JOIN {course} group_course
            ON group_course.id = group_course_modules.course
    INNER JOIN {groups} group_groups
            ON group_groups.courseid = group_course_modules.course
               {$join}
         WHERE group_course_modules.course {$coursessql}
               {$where}
           AND (    (  (group_course.groupmodeforce = 1 AND
                        group_course.groupmode != {$separategroups})
                      OR
                       (group_course.groupmodeforce = 0 AND
                        group_course_modules.groupmode != {$separategroups})
                    )
                  OR
                    ( EXISTS ( SELECT 1
                                 FROM {groups_members} teachermemberships
                                WHERE teachermemberships.groupid = group_groups.id
                                  AND teachermemberships.userid = :teacheruserid{$counter}
               )     )         )
SQL;
        $coursesparams['teacheruserid'.$counter] = $USER->id;

        return array($groupdisplaysubquery, $coursesparams);
    }
}

/**
 * Holds the filters to deal with cohort nodes. Note: Adding a cohort filter after any other
 * filter will cause a problem as e.g. courseid on ancestors will not include the code below
 * which limits users to just those who are in a cohort. This means that the total count may well
 * be higher when the tree is loaded than when it is expanded.
 */
class block_ajax_marking_cohortid extends block_ajax_marking_filter_base {

    /**
     * Apply WHERE clause.
     * @static
     * @param block_ajax_marking_query_base $query
     * @param int $cohortid
     */
    public static function where_filter($query, $cohortid) {

        self::join_to_users($query);

        $countwrapper = self::get_countwrapper_subquery($query);

        $clause = array(
            'type' => 'AND',
            'condition' => 'cohort.id = :cohortidfiltercohortid');
        $countwrapper->add_where($clause);
        $countwrapper->add_param('cohortidfiltercohortid', $cohortid);
    }

    /**
     * @static
     * @param block_ajax_marking_query_base $query
     */
    public static function nextnodetype_filter($query) {

        self::join_to_users($query);

        $countwrapper = self::get_countwrapper_subquery($query);

        $conditions = array(
            'table' => 'cohort',
            'column' => 'id');
        $countwrapper->add_select($conditions);

        // What do we need for the nodes?
        $table = array(
            'join' => 'INNER JOIN',
            'table' => 'cohort',
            'on' => 'countwrapperquery.id = cohort.id'
        );
        $query->add_from($table);
        $conditions = array(
            'table' => 'cohort',
            'column' => 'name');
        $query->add_select($conditions);
        $conditions = array(
            'table' => 'cohort',
            'column' => 'description');
        $query->add_select($conditions);

        $query->add_orderby('cohort.name ASC');
    }

    /**
     * @static
     * @param block_ajax_marking_query_base $query
     */
    private static function join_to_users($query) {

        $countwrapper = self::get_countwrapper_subquery($query);
        // We need to join the userid to the cohort, if there is one.
        // TODO when is there not one?
        // Add join to cohort_members.
        $table = array(
            'join' => 'INNER JOIN',
            'table' => 'cohort_members',
            'on' => 'cohort_members.userid = moduleunion.userid'
        );
        $countwrapper->add_from($table);
        $table = array(
            'join' => 'INNER JOIN',
            'table' => 'cohort',
            'on' => 'cohort_members.cohortid = cohort.id'
        );
        $countwrapper->add_from($table);
    }
}
