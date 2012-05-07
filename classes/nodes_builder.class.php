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
 * Class file for the block_ajax_marking_nodes_builder class
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2011 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/*
 * Four days, before which, work is considered 'recent', after which things are considered 'medium'
 */
define('BLOCK_AJAX_MARKING_FOUR_DAYS', 345600);

/*
 * Ten days. Later than this is overdue.
 */
define('BLOCK_AJAX_MARKING_TEN_DAYS', 864000);

global $CFG;
require_once($CFG->dirroot.'/blocks/ajax_marking/classes/query_base.class.php');

/**
 * This is to build a query based on the parameters passed in from the client. Without parameters,
 * the query should return all unmarked items across all of the site.
 *
 * The query has 3 layers: the innermost is a UNION of several queries that go and fetch the
 * unmarked submissions from each module (1 for each module as they all store unmarked work
 * differently). The middle layer attaches standard filters  via apply_sql_xxxx_settings() functions
 * e.g. 'only show submissions from currently enrolled students' and 'only show submissions that
 * I have not configured to be hidden'. It also applies filters so that drilling down through the
 * nodes tree, the lower levels filter by the upper levels e.g. expanding a course node leads to a
 * 'WHERE courseid = xxx' clause being added. Finally, a GROUP BY statement is added for the
 * current node level e.g. for coursemodule nodes, we want to use coursemoduleid for this, then
 * count the submissions. The outermost layer then joins to the GROUP BY ids and counts (the only
 * two columns that the middle query provides) to supply the display details e.g. the name
 * of the coursemodule. This arrangement is needed because Oracle doesn't allow text
 * fields and GROUP BY to be mixed.
 */
class block_ajax_marking_nodes_builder {

    /**
     * This will take the parameters which were supplied by the clicked node and its ancestors and
     * construct an SQL query to get the relevant work from the database. It can be used by the
     * grading popups in cases where there are multiple items e.g. multiple attempts at a quiz, but
     * it is mostly for making the queries used to get the next set of nodes.
     *
     * @param array $filters
     * @param block_ajax_marking_module_base $moduleclass e.g. quiz, assignment
     * @return block_ajax_marking_query_base
     */
    private static function get_unmarked_module_query(array $filters,
                                                     block_ajax_marking_module_base $moduleclass) {

        // Might be a config nodes query, in which case, we want to leave off the unmarked work
        // stuff and make sure we add the display select stuff to this query instead of leaving
        // it for the outer displayquery that the unmarked work needs.
        $confignodes = isset($filters['config']) ? true : false;
        if ($confignodes) {
            $query = new block_ajax_marking_query_base($moduleclass);
            $query->add_from(array(
                    'table' => $moduleclass->get_module_name(),
                    'alias' => 'moduletable',
            ));
        } else {
            $query = $moduleclass->query_factory($confignodes);
        }

        $query->add_select(array('table'  => 'course_modules',
                                 'column' => 'id',
                                 'alias'  =>'coursemoduleid'));
        // Need the course to join to other filters.
        $query->add_select(array('table'  => 'moduletable',
                                 'column' => 'course'));
        // Some filters need a coursemoduleid to join to, so we need to make it part of every query.
        $query->add_from(array('table' => 'course_modules',
                               'on'    => 'course_modules.instance = moduletable.id AND
                                           course_modules.module = '.$query->get_module_id()));
        // Some modules need to add stuff by joining the moduleunion back to the sub table. This
        // gets round the way we can't add stuff from just one module's sub table into the UNION
        // bit.
        $query->add_select(array('table'  => 'sub',
                                 'column' => 'id',
                                 'alias'  =>'subid'));
        // Need to pass this through sometimes for the javascript to know what sort of node it is.
        $query->add_select(array('column' => "'".$query->get_modulename()."'",
                                 'alias'  =>'modulename'));

        return $query;
    }

    /**
     * This is to build whatever query is needed in order to return the requested nodes. It may be
     * necessary to compose this query from quite a few different pieces. Without filters, this
     * should return all unmarked work across the whole site for this teacher.
     *
     * The main union query structure involves two levels of nesting: First, all modules provide a
     * query that counts the unmarked work and leaves us with
     *
     * In:
     * - filters as an array. course, coursemodule, student, others (as defined by module base
     *   classes
     *
     * Issues:
     * - maintainability: easy to add and subtract query filters
     * - readability: this is very complex
     *
     * @global moodle_database $DB
     * @param array $filters list of functions to run on the query. Methods of this or the module
     * class
     * @return array
     */
    public static function unmarked_nodes($filters = array()) {

        global $CFG;

        $modulequeries = self::get_module_queries_array($filters);

        $havecoursemodulefilter = array_key_exists('coursemoduleid', $filters);
        $moduleclass = false;
        if ($havecoursemodulefilter) {
            $moduleclass = self::get_module_object_from_cmid($filters['coursemoduleid']);
        }

        if (empty($modulequeries)) {
            return array();
        }

        $countwrapperquery = self::get_count_wrapper_query($modulequeries, $filters);
        // This is just for copying and pasting from the paused debugger into a DB GUI.
        if ($CFG->debug == DEBUG_DEVELOPER) {
            $debugquery = block_ajax_marking_debuggable_query($countwrapperquery);
        }

        $displayquery = self::get_display_query($countwrapperquery, $filters);

        self::apply_filters_to_query($filters, $displayquery, false, $moduleclass);

        // Adds the config settings if there are any, so that we
        // know what the current settings are for the context menu.
        self::apply_config_filter($displayquery, $filters['nextnodefilter']);

        // This is just for copying and pasting from the paused debugger into a DB GUI.
        if ($CFG->debug == DEBUG_DEVELOPER) {
            $debugquery = block_ajax_marking_debuggable_query($displayquery);
        }

        $nodes = $displayquery->execute();

        $nodes = self::attach_groups_to_nodes($nodes, $filters);

        if ($havecoursemodulefilter) {
            // This e.g. allows the forum module to tweak the name depending on forum type.
            $moduleclass->postprocess_nodes_hook($nodes, $filters);
        }
        return $nodes;
    }

    /**
     * @static
     * USes the list of filters supplied by AJAX to find functions within this class and the
     * module classes which will modify the query
     *
     * @param array $filters
     * @param block_ajax_marking_query_base $query which will have varying levels of nesting
     * @param bool $config flag to tell us if this is the config tree, which has a differently
     *                     structured query
     * @param block_ajax_marking_module_base|bool $moduleclass if we have a coursemoduleid filter,
     *                                                         this is the corresponding module
     *                                                         object
     */
    private static function apply_filters_to_query($filters,
                                                   $query,
                                                   $config = false,
                                                   $moduleclass = false) {

        // Now that the query object has been constructed, we want to add all the filters that will
        // limit the nodes to just the stuff we want (ignore hidden things, ignore things in other
        // parts of the tree) and groups the unmarked work by whatever it needs to be grouped by.
        foreach ($filters as $name => $value) {

            if ($name == 'nextnodefilter') {
                $filterfunctionname = 'apply_'.$value.'_filter';
                // The new node filter is in the form 'nextnodefilter => 'functionname', rather
                // than 'filtername' => <rowid> We want to pass the name of the filter in with
                // an empty value, so we set the value here.
                $value = false;
                // TODO not a very elegant way of telling the filters that the query is different.
                $operation = $config ? 'configdisplay' : 'countselect';
            } else {
                // We want a single node count.
                $filterfunctionname = 'apply_'.$name.'_filter';
                $operation = $config ? 'configwhere' : 'where';
            }

            // Find the function. Core ones are part of this class, others will be methods of
            // the module object.
            // If we are filtering by a specific module, look there first.
            if ($moduleclass instanceof block_ajax_marking_module_base &&
                method_exists($moduleclass, $filterfunctionname) ) {

                // All core filters are methods of query_base and module specific ones will be
                // methods of the module-specific subclass. If we have one of these, it will
                // always be accompanied by a coursemoduleid, so will only be called on the
                // relevant module query and not the rest.
                $moduleclass->$filterfunctionname($query, $operation, $value);
            } else if (method_exists(__CLASS__, $filterfunctionname)) {
                // Config tree needs to have select stuff that doesn't mention sub. Like for the
                // outer wrappers of the normal query for the unmarked work nodes.
                self::$filterfunctionname($query, $operation, $value);
            }
        }
    }

    /**
     * Applies the filter needed for course nodes or their descendants
     *
     * @param block_ajax_marking_query_base $query
     * @param bool|string $operation If we are gluing many module queries together, we will need to
     *                    run a wrapper query that will select from the UNIONed subquery
     * @param int $courseid Optional. Will apply SELECT and GROUP BY for nodes if missing
     * @return void|string
     * @SuppressWarnings(PHPMD.UnusedLocalVariable) Skip the debuggable query
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod) Dynamic method names don't register
     */
    private static function apply_courseid_filter($query, $operation, $courseid = 0) {
        global $USER;

        $selects = array();
        $countwrapper = '';
        if ($operation != 'configdisplay' && $operation != 'configwhere') {
            $countwrapper = $query->get_subquery('countwrapperquery');
        }

        switch ($operation) {

            case 'where':

                // This is for when a courseid node is an ancestor of the node that has been
                // selected, so we just do a where.
                $countwrapper->add_where(array(
                        'type' => 'AND',
                        'condition' => 'moduleunion.course = :courseidfiltercourseid'));
                $query->add_param('courseidfiltercourseid', $courseid);
                break;

            case 'configwhere':

                // This is for when a courseid node is an ancestor of the node that has been
                // selected, so we just do a where.
                $query->add_where(array(
                        'type' => 'AND',
                        'condition' => 'course_modules.course = :courseidfiltercourseid'));
                $query->add_param('courseidfiltercourseid', $courseid);
                break;

            case 'countselect':

                $countwrapper->add_select(array(
                        'table'    => 'moduleunion',
                        'column'   => 'course',
                        'alias'    => 'id'), true
                );

                // This is for the displayquery when we are making course nodes.
                $query->add_from(array(
                        'table' =>'course',
                        'alias' => 'course',
                        'on' => 'countwrapperquery.id = course.id'
                ));
                $query->add_select(array(
                    'table'    => 'course',
                    'column'   => 'shortname',
                    'alias'    => 'name'));
                $query->add_select(array(
                    'table'    => 'course',
                    'column'   => 'fullname',
                    'alias'    => 'tooltip'));

                $query->add_orderby('course.shortname ASC');

                break;

            case 'configdisplay':

                // This is for the displayquery when we are making course nodes.
                $query->add_from(array(
                        'table' =>'course',
                        'alias' => 'course',
                        'on' => 'course_modules.course = course.id'
                ));
                $query->add_select(array(
                    'table'    => 'course',
                    'column'   => 'id',
                    'alias' => 'courseid',
                    'distinct' => true));
                $query->add_select(array(
                    'table'    => 'course',
                    'column'   => 'shortname',
                    'alias'    => 'name'));
                $query->add_select(array(
                    'table'    => 'course',
                    'column'   => 'fullname',
                    'alias'    => 'tooltip'));

                // We need the config settings too, if there are any.
                // TODO this should be in the config filter.
                $query->add_from(array(
                        'join' => 'LEFT JOIN',
                        'table' =>'block_ajax_marking',
                        'alias' => 'settings',
                        'on' => "settings.instanceid = course.id
                                 AND settings.tablename = 'course'
                                 AND settings.userid = :settingsuserid"
                ));
                $query->add_param('settingsuserid', $USER->id);
                $query->add_select(array(
                    'table'    => 'settings',
                    'column'   => 'display'));
                $query->add_select(array(
                    'table'    => 'settings',
                    'column'   => 'groupsdisplay'));
                $query->add_select(array(
                    'table'    => 'settings',
                    'column'   => 'id',
                    'alias'    => 'settingsid'));

                $query->add_orderby('course.shortname ASC');

                break;

        }

        foreach ($selects as $select) {
            $query->add_select($select);
        }
    }



    /**
     *
     * @param block_ajax_marking_query_base $query
     * @param $operation
     * @param bool|int $groupid
     * @return void
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod) Dynamic method names don't register
     */
    private static function apply_groupid_filter($query, $operation, $groupid = 0) {

        $countwrapper = '';
        if ($operation != 'configdisplay' && $operation != 'configwhere') {
            $countwrapper = $query->get_subquery('countwrapperquery');
        }

        // We only want to add the bit that appends a groupid to each submission if we are
        // going to use it as the calculations are expensive.
        if ($operation == 'where' || $operation == 'countselect') {
            // This adds the subquery that can tell us wht the display settings are for each group.
            // Once we have filtered out those submissions with no visible groups, we choose
            // the best match i.e. randomly assign the submissions to one of its visible groups
            // (there will usually only be one) so it's not counted twice in case the user is
            // in two groups in this context.
            list($maxgroupidsubquery, $maxgroupidparams) = self::sql_max_groupid_subquery();
            $countwrapper->add_params($maxgroupidparams);
            $countwrapper->add_from(array(
                    'join' => 'LEFT JOIN',
                    'table' => $maxgroupidsubquery,
                    'on' => 'maxgroupidsubquery.cmid = moduleunion.coursemoduleid AND
                             maxgroupidsubquery.userid = moduleunion.userid',
                    'alias' => 'maxgroupidsubquery',
                    'subquery' => true));
        }

        switch ($operation) {

            case 'where':

                $countwrapper->add_where(array(
                        'type' => 'AND',
                        'condition' => 'COALESCE(maxgroupidsubquery.groupid, 0) = :groupid'));
                $countwrapper->add_param('groupid', $groupid);
                break;

            // This is when we make group nodes and need group name etc.
            case 'countselect':

                $countwrapper->add_select(array(
                        'table' => array('maxgroupidsubquery' => 'groupid',
                                         '0'),
                        'function' => 'COALESCE',
                        'alias' => 'id'));

                // This is for the displayquery when we are making course nodes.
                $query->add_from(array(
                        'join' => 'LEFT JOIN', // Group id 0 will not match anything.
                        'table' => 'groups',
                        'on' => 'countwrapperquery.id = groups.id'
                ));
                // We may get a load of people in no group.
                $query->add_select(array(
                        'function' => 'COALESCE',
                        'table'    => array('groups' => 'name',
                                            get_string('notingroup', 'block_ajax_marking')),
                        'alias' => 'name'));
                $query->add_select(array(
                        'function' => 'COALESCE',
                        'table'    => array('groups' => 'description',
                                            get_string('notingroupdescription',
                                                       'block_ajax_marking')),
                        'alias'    => 'tooltip'));

                $query->add_orderby("COALESCE(groups.name, '".get_string('notingroup', 'block_ajax_marking')."') ASC");

                break;

        }

    }

    /**
     * Applies a filter so that only nodes from a certain cohort are returned
     *
     * @param block_ajax_marking_query_base|bool $query
     * @param $operation
     * @param bool|int $cohortid
     * @global moodle_database $DB
     * @return void
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod) Dynamic method names don't register
     */
    private static function apply_cohortid_filter(block_ajax_marking_query_base $query,
                                                  $operation, $cohortid = false) {

        $selects = array();
        $countwrapper = $query->get_subquery('countwrapperquery');

        // Note: Adding a cohort filter after any other filter will cause a problem as e.g.
        // courseid on ancestors will not include the code below which limits users to just
        // those who are in a cohort. This means that the total count may well be higher when
        // the tree is loaded than when it is expanded.

        // We need to join the userid to the cohort, if there is one.
        // TODO when is there not one?
        // Add join to cohort_members.
        $countwrapper->add_from(array(
                'join' => 'INNER JOIN',
                'table' => 'cohort_members',
                'on' => 'cohort_members.userid = moduleunion.userid'
        ));
        $countwrapper->add_from(array(
                'join' => 'INNER JOIN',
                'table' => 'cohort',
                'on' => 'cohort_members.cohortid = cohort.id'
        ));

        switch ($operation) {

            case 'where':

                // Apply WHERE clause.
                $countwrapper->add_where(array(
                        'type' => 'AND',
                        'condition' => 'cohort.id = :cohortidfiltercohortid'));
                $countwrapper->add_param('cohortidfiltercohortid', $cohortid);
                break;

            case 'countselect':

                $countwrapper->add_select(array(
                        'table'    => 'cohort',
                        'column'   => 'id'));

                // What do we need for the nodes?
                $query->add_from(array(
                        'join' => 'INNER JOIN',
                        'table' => 'cohort',
                        'on' => 'countwrapperquery.id = cohort.id'
                ));
                $selects = array(
                    array(
                        'table'    => 'cohort',
                        'column'   => 'name'),
                    array(
                        'table'    => 'cohort',
                        'column'   => 'description'));

                $query->add_orderby('cohort.name ASC');

                break;
        }

        foreach ($selects as $select) {
            $query->add_select($select);
        }
    }

    /**
     * Applies the filter needed for assessment nodes or their descendants
     *
     * @param block_ajax_marking_query_base $query
     * @param int $coursemoduleid optional. Will apply SELECT and GROUP BY for nodes if missing
     * @param bool $operation
     * @return void
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod) Dynamic method names don't register
     */
    private static function apply_coursemoduleid_filter($query, $operation, $coursemoduleid = 0 ) {
        global $USER;

        $countwrapper = '';
        if ($operation != 'configdisplay') {
            $countwrapper = $query->get_subquery('countwrapperquery');
        }

        switch ($operation) {

            case 'where':
                $countwrapper->add_where(array(
                        'type' => 'AND',
                        'condition' => 'moduleunion.coursemoduleid = :coursemoduleidfiltercmid'));
                $query->add_param('coursemoduleidfiltercmid', $coursemoduleid);
                break;

            case 'countselect':

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
                        'table'    => 'course_modules',
                        'column'   => 'id',
                        'alias'    => 'coursemoduleid'));
                $query->add_select(array(
                        'table'    => 'countwrapperquery',
                        'column'   => 'modulename'));

                // Deliberate fall-through.

            case 'configdisplay':

                // Awkwardly, the course_module table doesn't hold the name and description of the
                // module instances, so we need to join to the module tables. This will cause a mess
                // unless we specify that only coursemodules with a specific module id should join
                // to a specific module table.
                $moduleclasses = block_ajax_marking_get_module_classes();
                $introcoalesce = array();
                $namecoalesce = array();
                $orderbycoalesce = array();
                foreach ($moduleclasses as $moduleclass) {
                    $moduletablename = $moduleclass->get_module_table();
                    $query->add_from(array(
                        'join' => 'LEFT JOIN',
                        'table' => $moduletablename,
                        // Ids of modules will be constant for one install, so we don't need to
                        // worry about parameterising them for query caching.
                        'on' => "(course_modules.instance = ".$moduletablename.".id
                                  AND course_modules.module = '".$moduleclass->get_module_id()."')"
                    ));
                    $namecoalesce[$moduletablename] = 'name';
                    $introcoalesce[$moduletablename] = 'intro';
                    $orderbycoalesce[$moduletablename] = $moduletablename.'.name';
                }
                $query->add_select(array(
                        'table'    => 'course_modules',
                        'column'   => 'id',
                        'alias'    => 'coursemoduleid'));
                $query->add_select(array(
                        'table'    => $namecoalesce,
                        'function' => 'COALESCE',
                        'column'   => 'name',
                        'alias'    => 'name'));
                $query->add_select(array(
                        'table'    => $introcoalesce,
                        'function' => 'COALESCE',
                        'column'   => 'intro',
                        'alias'    => 'tooltip'));

                // We need the config settings too, if there are any.
                $query->add_from(array(
                        'join' => 'LEFT JOIN',
                        'table' =>'block_ajax_marking',
                        'alias' => 'settings',
                        'on' => "settings.instanceid = course_modules.id
                                 AND settings.tablename = 'course_modules'
                                 AND settings.userid = :settingsuserid"
                ));
                $query->add_param('settingsuserid', $USER->id);
                $query->add_select(array(
                    'table'    => 'settings',
                    'column'   => 'display'));
                $query->add_select(array(
                    'table'    => 'settings',
                    'column'   => 'groupsdisplay'));
                $query->add_select(array(
                    'table'    => 'settings',
                    'column'   => 'id',
                    'alias'    => 'settingsid'));

                // TODO get them in order of module type first?
                $query->add_orderby('COALESCE('.implode(', ', $orderbycoalesce).') ASC');

                break;

        }

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
     * Table we get back:
     * ----------------------------------------
     * | course_module_id | groupid | display |
     * |--------------------------------------|
     * |        543       |    67   |    0    |
     * |        342       |    6    |    1    |
     *
     * @param string $type We may want to know the combined visibility (coalesce) or just the
     * visibility at either (course) or (coursemodule) level. The latter two are for getting
     * the groups in their current settings states so config stuff can be adjusted.
     *
     * @return array SQL and params
     * @SuppressWarnings(PHPMD.UnusedLocalVariable) Debuggable query
     */
    private function sql_group_visibility_subquery($type = 'coalesce') {

        // TODO unit test this!!

        global $DB, $CFG, $USER;

        /**
         * We may need to reuse this subquery. Because it uses the user's teacher courses as a
         * filter (less calculation that way), we might have issues with the query optimiser
         * not reusing the execution plan. Hopefully not. This variable allows us to feed the
         * same teacher courses in more than once because Moodle requires variables with different
         * names for different parts of the query - you cannot reuse one with the same name in more
         * than one place. The number of params must match the number of array items.
         */
        static $counter = 0;

        $courses = block_ajax_marking_get_my_teacher_courses();
        list($coursessql, $coursesparams) = $DB->get_in_or_equal(array_keys($courses),
                                                                 SQL_PARAMS_NAMED,
                                                                 "groups{$counter}courses");
        $counter++;

        // TODO use proper constant
        $sitedefault = 1; // Configurable in future.

        // Query visualisation:
        /*
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
         */

        $select = '';
        $join = '';
        $where = '';

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
                $join = $coursemodulejoin . $coursejoin;
                $where = $coursewhere . $coursemodulewhere;
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
        // TODO does moving the groupid stuff into a WHERE xx OR IS NULL make it faster?
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
           AND (
                    (  (group_course.groupmodeforce = 1 AND
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
                             )
                    )
               )
SQL;
        $coursesparams['teacheruserid'.$counter] = $USER->id;

        if ($CFG->debug == DEBUG_DEVELOPER) {
            // Only a tiny overhead, but still worth avoiding if not needed.
            $debugquery = block_ajax_marking_debuggable_query($groupdisplaysubquery,
                                                              $coursesparams);
        }

        return array($groupdisplaysubquery, $coursesparams);

    }

    /**
     * We need to check whether the activity can be displayed (the user may have hidden it
     * using the settings). This sql can be dropped into a query so that it will get the right
     * students. This will also make sure that if only some groups are being displayed, the
     * submission is by a user who is in one of the displayed groups.
     *
     * @param block_ajax_marking_query_base $query a query object to apply these changes to
     * @return void
     */
    private static function apply_sql_display_settings($query) {

        global $USER;

        $query->add_from(array('join' => 'LEFT JOIN',
                               'table' => 'block_ajax_marking',
                               'on' => "cmconfig.tablename = 'course_modules'
                                        AND cmconfig.instanceid = moduleunion.coursemoduleid
                                        AND cmconfig.userid = :confuserid1 ",
                               'alias' => 'cmconfig' ));

        $query->add_from(array('join' => 'LEFT JOIN',
                               'table' => 'block_ajax_marking',
                               'on' => "courseconfig.tablename = 'course'
                                       AND courseconfig.instanceid = moduleunion.course
                                       AND courseconfig.userid = :confuserid2 ",
                               'alias' => 'courseconfig' ));
        $query->add_param('confuserid1', $USER->id);
        $query->add_param('confuserid2', $USER->id);

        // Here, we filter out the users with no group memberships, where the users without group
        // memberships have been set to be hidden for this coursemodule.
        // Second bit (after OR) filters out those who have group memberships, but all of them are
        // set to be hidden. This is done by saying 'are there any visible at all'
        // The bit after that, talking about separate groups is to make sure users don't see any
        // of these groups unless they are members of them if separate groups is enabled.
        $sitedefaultnogroup = 1; // what to do with users who have no group membership?
        list($existsvisibilitysubquery, $existsparams) = self::sql_group_visibility_subquery();
        $query->add_params($existsparams);
        $hidden = <<<SQL
    (
        ( NOT EXISTS (SELECT NULL
                FROM {groups_members} groups_members
          INNER JOIN {groups} groups
                  ON groups_members.groupid = groups.id
               WHERE groups_members.userid = moduleunion.userid
                 AND groups.courseid = moduleunion.course)

          AND ( COALESCE(cmconfig.showorphans,
                         courseconfig.showorphans,
                         {$sitedefaultnogroup}) = 1 ) )

        OR

        ( EXISTS (SELECT NULL
                    FROM {groups_members} groups_members
              INNER JOIN {groups} groups
                      ON groups_members.groupid = groups.id
              INNER JOIN ({$existsvisibilitysubquery}) existsvisibilitysubquery
                      ON existsvisibilitysubquery.groupid = groups.id
                   WHERE groups_members.userid = moduleunion.userid
                     AND existsvisibilitysubquery.cmid = moduleunion.coursemoduleid
                     AND groups.courseid = moduleunion.course
                     AND existsvisibilitysubquery.display = 1)

        )
    )
SQL;
        $query->add_where(array('type' => 'AND',
                                'condition' => $hidden));

        // We allow course settings to override the site default and activity settings to override
        // the course ones.
        $sitedefaultactivitydisplay = 1;
        $query->add_where(array(
                'type' => 'AND',
                'condition' => "COALESCE(cmconfig.display,
                                         courseconfig.display,
                                         {$sitedefaultactivitydisplay}) = 1")
        );

    }

    /**
     * All modules have a common need to hide work which has been submitted to items that are now
     * hidden. Not sure if this is relevant so much, but it's worth doing so that test data and test
     * courses don't appear. General approach is to use cached context info from user session to
     * find a small list of contexts that a teacher cannot grade in within the courses where they
     * normally can, then do a NOT IN thing with it. Also the obvious visible = 1 stuff.
     *
     * @param block_ajax_marking_query_base $query
     * @param string $coursemodulejoin What table.column to join to course_modules.id
     * @param bool $includehidden Do we want to have hidden coursemodules included? Config = yes
     * @return array The join string, where string and params array. Note, where starts with 'AND'
     */
    private static function apply_sql_visible(block_ajax_marking_query_base $query,
                                              $coursemodulejoin = '', $includehidden = false) {
        global $DB;

        if ($coursemodulejoin) { // Only needed if the table is not already there.
            $query->add_from(array(
                    'join' => 'INNER JOIN',
                    'table' => 'course_modules',
                    'on' => 'course_modules.id = '.$coursemodulejoin
            ));
        }
        $query->add_from(array(
                'join' => 'INNER JOIN',
                'table' => 'course',
                'on' => 'course.id = course_modules.course'
        ));

        // Get coursemoduleids for all items of this type in all courses as one query. Won't come
        // back empty or else we would not have gotten this far.
        $courses = block_ajax_marking_get_my_teacher_courses();
        // TODO Note that change to login as... in another tab may break this. Needs testing.
        list($coursesql, $params) = $DB->get_in_or_equal(array_keys($courses), SQL_PARAMS_NAMED);
        // Get all coursemodules the current user could potentially access.
        $sql = "SELECT id
                  FROM {course_modules}
                 WHERE course {$coursesql}";
        // No point caching - only one request per module per page request...
        $coursemoduleids = $DB->get_records_sql($sql, $params);

        // Get all contexts (will cache them). This is expensive and hopefully has been cached in
        // the session already, so we take advantage of it.
        /*
         * @var array $contexts PHPDoc needs updating for get_context_instance()
         */
        $contexts = array();
        foreach (array_keys($coursemoduleids) as $cmid) {
            $contexts[] = context_module::instance($cmid);
        }
        // Use has_capability to loop through them finding out which are blocked. Unset all that we
        // have permission to grade, leaving just those we are not allowed (smaller list). Hopefully
        // this will never exceed 1000 (oracle hard limit on number of IN values).
        $mods = block_ajax_marking_get_module_classes();
        $modids = array();
        foreach ($mods as $mod) {
            $modids[] = $mod->get_module_id(); // Save these for later.
            foreach ($contexts as $key => $context) {
                // If we don't find any capabilities for a context, it will remain and be excluded
                // from the SQL. Hopefully this will be a small list.
                if (has_capability($mod->get_capability(), $context)) { // This is cached, so fast.
                    unset($contexts[$key]);
                }
            }
        }
        // Return a get_in_or_equals with NOT IN if there are any, or empty strings if there aren't.
        if (!empty($contexts)) {
            list($contextssql, $contextsparams) = $DB->get_in_or_equal(array_keys($contexts),
                                                                       SQL_PARAMS_NAMED,
                                                                       'context0000',
                                                                       false);
            $query->add_where(array('type' => 'AND',
                                    'condition' => "course_modules.id {$contextssql}"));
            $query->add_params($contextsparams);
        }

        // Only show enabled mods.
        list($visiblesql, $visibleparams) = $DB->get_in_or_equal($modids, SQL_PARAMS_NAMED,
                                                                 'visible000');
        $query->add_where(array(
                'type'      => 'AND',
                'condition' => "course_modules.module {$visiblesql}"));
        // We want the coursmeodules that are hidden to be gone form the main trees. For config,
        // We may want to show them greyed out so that settings can be sorted before they are shown
        // to students.
        if (!$includehidden) {
            $query->add_where(array('type' => 'AND', 'condition' => 'course_modules.visible = 1'));
        }
        $query->add_where(array('type' => 'AND', 'condition' => 'course.visible = 1'));

        $query->add_params($visibleparams);

    }

    /**
     * Makes sure we only get stuff for the courses this user is a teacher in
     *
     * @param block_ajax_marking_query_base $query
     * @param string $coursecolumn
     * @return void
     */
    private static function apply_sql_owncourses(block_ajax_marking_query_base $query,
                                                 $coursecolumn = '') {

        global $DB;

        $courses = block_ajax_marking_get_my_teacher_courses();

        $courseids = array_keys($courses);

        if ($courseids) {
            list($sql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED,
                                                       'courseid0000');

            $query->add_where(array(
                    'type' => 'AND',
                    'condition' => $coursecolumn.' '.$sql));
            $query->add_params($params);
        }
    }

    /**
     * Returns an SQL snippet that will tell us whether a student is directly enrolled in this
     * course
     *
     * @param block_ajax_marking_query_base $query
     * @param array $filters So we can filter by cohortid if we need to
     * @return array The join and where strings, with params. (Where starts with 'AND)
     */
    private static function apply_sql_enrolled_students(block_ajax_marking_query_base $query,
                                                        array $filters) {

        global $DB, $CFG, $USER;

        // Hide users added by plugins which are now disabled.
        if (isset($filters['cohortid']) || $filters['nextnodefilter'] == 'cohortid') {
            // We need to specify only people enrolled via a cohort.
            $enabledsql = " = 'cohort'";
        } else if ($CFG->enrol_plugins_enabled) {
            // Returns list of english names of enrolment plugins.
            $plugins = explode(',', $CFG->enrol_plugins_enabled);
            list($enabledsql, $params) = $DB->get_in_or_equal($plugins,
                                                              SQL_PARAMS_NAMED,
                                                              'enrol001');
            $query->add_params($params);
        } else {
            // No enabled enrolment plugins.
            $enabledsql = ' = :sqlenrollednever';
            $query->add_param('sqlenrollednever', -1);
        }

        $sql = <<<SQL
                SELECT NULL
                  FROM {enrol} enrol
            INNER JOIN {user_enrolments} user_enrolments
                    ON user_enrolments.enrolid = enrol.id
                 WHERE enrol.enrol {$enabledsql}
                   AND enrol.courseid = moduleunion.course
                   AND user_enrolments.userid != :enrolcurrentuser
                   AND user_enrolments.userid = moduleunion.userid
SQL;

        $query->add_where(array('type' => 'AND',
                                'condition' => "EXISTS ({$sql})"));
        $query->add_param('enrolcurrentuser', $USER->id, false);
    }

    /**
     * For the config nodes, we want all of the coursemodules. No need to worry about counting etc.
     * There is also no need for a dynamic rearrangement of the nodes, so we have two simple queries
     *
     * @param array $filters
     * @return array
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public static function get_config_nodes($filters) {

        global $CFG;

        // The logic is that we need to filter the course modules because some of them will be
        // hidden or the user will not have access to them. Then we may or may not group them by
        // course.
        $configbasequery = new block_ajax_marking_query_base();
        $configbasequery->add_from(array('table' => 'course_modules'));

        // Now apply the filters.
        self::apply_sql_owncourses($configbasequery, 'course_modules.course');
        self::apply_sql_visible($configbasequery, '', true);
        self::apply_filters_to_query($filters, $configbasequery, true);

        // This is just for copying and pasting from the paused debugger into a DB GUI.
        if ($CFG->debug == DEBUG_DEVELOPER) {
            $debugquery = block_ajax_marking_debuggable_query($configbasequery);
        }

        $nodes = $configbasequery->execute();

        $nodes = self::attach_groups_to_nodes($nodes, $filters);

        return $nodes;

    }

    /**
     * In order to adjust the groups display settings properly, we need to know what groups are
     * available. This takes the nodes we have and attaches the groups to them if there are any.
     * We only need this for the main tree if we intend to have the ability to adjust settings
     * via right-click menus
     *
     * @param array $nodes
     * @param array $filters
     * @return array
     */
    private function attach_groups_to_nodes($nodes, $filters) {

        global $DB, $CFG, $USER;

        if (!$nodes) {
            return array();
        }

        // Need to get all groups for each node. Can't do this in the main query as there are
        // possibly multiple groups settings for each node.

        // Get the ids of the nodes.
        $courseids = array();
        $coursemoduleids = array();
        foreach ($nodes as $node) {
            if (isset($node->courseid)) {
                $courseids[] = $node->courseid;
            }
            if (isset($node->coursemoduleid)) {
                $coursemoduleids[] = $node->coursemoduleid;
            }
        }

        if ($filters['nextnodefilter'] == 'courseid') {
            // Retrieve all groups that we may need. This includes those with no settings yet as
            // otherwise, we won't be able to offer to create settings for them.
            list($coursesql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
            $params['userid'] = $USER->id;

            $separategroups = SEPARATEGROUPS;

            $sql = <<<SQL

             SELECT groups.id,
                    groups.courseid AS courseid,
                    groups.name,
                    settings.display
               FROM {groups} groups
         INNER JOIN {course} course
                 ON course.id = groups.courseid

          LEFT JOIN (SELECT coursesettings.instanceid AS courseid,
                            groupsettings.groupid,
                            groupsettings.display
                       FROM {block_ajax_marking_groups} AS groupsettings
                 INNER JOIN {block_ajax_marking} coursesettings
                         ON (coursesettings.tablename = 'course'
                             AND coursesettings.id = groupsettings.configid)
                      WHERE coursesettings.userid = :userid ) settings

                 ON (groups.courseid = settings.courseid
                     AND groups.id = settings.groupid)

              WHERE groups.courseid {$coursesql}
                AND (  course.groupmode != {$separategroups} OR
                       EXISTS ( SELECT 1
                                   FROM {groups_members} teachermemberships
                                  WHERE teachermemberships.groupid = groups.id
                                    AND teachermemberships.userid = :teacheruserid
                               )
                    )
SQL;
            $params['teacheruserid'] = $USER->id;

            if ($CFG->debug == DEBUG_DEVELOPER) {
                $debugquery = block_ajax_marking_debuggable_query($sql, $params);
            }
            $groups = $DB->get_records_sql($sql, $params);

            foreach ($groups as $group) {
                if (!isset($nodes[$group->courseid]->groups)) {
                    $nodes[$group->courseid]->groups = array();
                }
                $nodes[$group->courseid]->groups[] = $group;
            }

        } else if ($filters['nextnodefilter'] == 'coursemoduleid'
            && $coursemoduleids) {

            // This will include groups that have no settings as we may want to make settings
            // for them.
            list($cmsql, $params) = $DB->get_in_or_equal($coursemoduleids, SQL_PARAMS_NAMED);
            list($subsql, $subparams) = self::sql_group_visibility_subquery('coursemodule');
            // Can't have repeating groupids in column 1 or it throws an error.
            $concat = $DB->sql_concat('groups.id', "'-'", 'visibilitysubquery.cmid');
            $sql = <<<SQL
                 SELECT {$concat} as uniqueid,
                        groups.id,
                        visibilitysubquery.cmid AS coursemoduleid,
                        groups.name,
                        visibilitysubquery.display
                   FROM {groups} groups
             INNER JOIN ({$subsql}) visibilitysubquery
                     ON visibilitysubquery.groupid = groups.id
                    AND visibilitysubquery.cmid {$cmsql}
SQL;
            $params = array_merge($params, $subparams);

            if ($CFG->debug == DEBUG_DEVELOPER) {
                $debugquery = block_ajax_marking_debuggable_query($sql, $params);
            }
            $groups = $DB->get_records_sql($sql, $params);

            foreach ($groups as $group) {
                if (!isset($nodes[$group->coursemoduleid]->groups)) {
                    $nodes[$group->coursemoduleid]->groups = array();
                }
                $nodes[$group->coursemoduleid]->groups[] = $group;
            }
        }

        return $nodes;
    }

    /**
     * Config nodes need some stuff to be returned from the config tables so we can have settings
     * adjusted based on existing values.
     *
     * @TODO this isn't really a filter
     *
     * @param block_ajax_marking_query_base $query
     * @param string $nextnodefilter
     * @return void
     */
    private static function apply_config_filter(block_ajax_marking_query_base $query,
                                                $nextnodefilter = '') {

        if (!$nextnodefilter) {
            return;
        }
        $nodesthatneedconfigsettings = array('courseid',
                                             'coursemoduleid');
        if (!in_array($nextnodefilter, $nodesthatneedconfigsettings)) {
            return;
        }

        // The inner query joins to the config tables already for the WHERE clauses, so we
        // make use of them to get the settings for those nodes that are not filtered out.
        $countwrapper = $query->get_subquery('countwrapperquery');

        switch ($nextnodefilter) {

            // This is for the ordinary nodes. We need to work out what to request for the next node
            // so groupsdisplay has to be sent through. Also for the config context menu to work.
            // COALESCE is no good here as we need the actual settings, so we work out that stuff
            // in JavaScript.
            case 'courseid':

                $countwrapper->add_select(array(
                                         'table' => 'courseconfig',
                                         'column' => 'display'));
                $countwrapper->add_select(array(
                                         'table' => 'courseconfig',
                                         'column' => 'groupsdisplay'));
                break;

            case 'coursemoduleid':

                // The inner query joins to the config tables already for the WHERE clauses, so we
                // make use of them to get the settings for those nodes that are not filtered out.
                $countwrapper = $query->get_subquery('countwrapperquery');

                $countwrapper->add_select(array(
                                         'table' => 'cmconfig',
                                         'column' => 'display'));
                $countwrapper->add_select(array(
                                         'table' => 'cmconfig',
                                         'column' => 'groupsdisplay'));
                break;
        }

        // The outer query (we need one because we have to do a join between the numeric
        // fields that can be fed into a GROUP BY and the text fields that we display) pulls
        // through the display fields, which were sent through from the middle query using the
        // stuff above.
        $query->add_select(array('table' => 'countwrapperquery',
                                 'column' => 'display'));
        $query->add_select(array('table' => 'countwrapperquery',
                                 'column' => 'groupsdisplay'));
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
    private function sql_max_groupid_subquery() {

        list($visibilitysubquery, $params) = self::sql_group_visibility_subquery();

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
        return array($maxgroupsql, $params);

    }

    /**
     * Gets an array of queries, one for each module, which when UNION ALLed will provide
     * all marking from across the site. This will get us the counts for each
     * module. Implode separate subqueries with UNION ALL. Must use ALL to cope with duplicate
     * rows with same counts and ids across the UNION. Doing it this way keeps the other items
     * needing to be placed into the SELECT  out of the way of the GROUP BY bit, which makes
     * Oracle bork up.
     *
     * @static
     * @param $filters
     * @return array
     */
    private static function get_module_queries_array($filters) {

        global $DB;

        // If not a union query, we will want to remember which module we are narrowed down to so we
        // can apply the postprocessing hook later.

        $modulequeries = array();
        $moduleid = false;
        $moduleclasses = block_ajax_marking_get_module_classes();
        if (!$moduleclasses) {
            return array(); // No nodes.
        }

        $havecoursemodulefilter = array_key_exists('coursemoduleid', $filters);

        // If one of the filters is coursemodule, then we want to avoid querying all of the module
        // tables and just stick to the one with that coursemodule. If not, we do a UNION of all
        // the modules.
        if ($havecoursemodulefilter) {
            // Get the right module id.
            $moduleid = $DB->get_field('course_modules', 'module',
                                       array('id' => $filters['coursemoduleid']));
        }

        foreach ($moduleclasses as $modname => $moduleclass) {
            /* @var $moduleclass block_ajax_marking_module_base */

            if ($moduleid && $moduleclass->get_module_id() !== $moduleid) {
                // We don't want this one as we're filtering by a single coursemodule.
                continue;
            }

            $modulequeries[$modname] = self::get_unmarked_module_query($filters, $moduleclass);

            if ($moduleid) {
                break; // No need to carry on once we've got the only one we need.
            }
        }

        return $modulequeries;
    }

    /**
     * Wraps the array of moduleunion queries in an outer one that will group the submissions
     * into nodes with counts. We want the bare minimum here. The idea is to avoid problems with
     * GROUP BY ambiguity, so we just get the counts as well as the node ids.
     *
     * @static
     * @param $modulequeries
     * @param $filters
     * @return \block_ajax_marking_query_base
     */
    private static function get_count_wrapper_query($modulequeries, $filters) {

        $havecoursemodulefilter = array_key_exists('coursemoduleid', $filters);
        $makingcoursemodulenodes = ($filters['nextnodefilter'] === 'coursemoduleid');

        $countwrapperquery = new block_ajax_marking_query_base();
        // We find out how many submissions we have here. Not DISTINCT as we are grouping by
        // nextnodefilter in the superquery.
        $countwrapperquery->add_select(array('table' => 'moduleunion',
                                             'column' => 'userid',
                                             'alias' => 'itemcount',
                                             // COUNT is a reserved word.
                                             'function' => 'COUNT'));

        // To get the three times for recent, medium and overdue pieces of work, we do three
        // count operations here.
        $fourdaysago = time() - BLOCK_AJAX_MARKING_FOUR_DAYS;
        $tendaysago = time() - BLOCK_AJAX_MARKING_TEN_DAYS;
        $recentcolumn = "CASE WHEN (moduleunion.timestamp > {$fourdaysago}) THEN 1 ELSE 0 END";
        $countwrapperquery->add_select(array('column' => $recentcolumn,
                                             'alias' => 'recentcount',
                                             // COUNT is a reserved word.
                                             'function' => 'SUM'));
        $mediumcolumn = "CASE WHEN (moduleunion.timestamp < {$fourdaysago} AND ".
            "moduleunion.timestamp > {$tendaysago}) THEN 1 ELSE 0 END";
        $countwrapperquery->add_select(array('column' => $mediumcolumn,
                                             'alias' => 'mediumcount',
                                             // COUNT is a reserved word.
                                             'function' => 'SUM'));
        $overduecolumn = "CASE WHEN moduleunion.timestamp < $tendaysago THEN 1 ELSE 0 END";
        $countwrapperquery->add_select(array('column' => $overduecolumn,
                                             'alias' => 'overduecount',
                                             // COUNT is a reserved word.
                                             'function' => 'SUM'));

        if ($havecoursemodulefilter || $makingcoursemodulenodes) {
            // Needed to access the correct javascript so we can open the correct popup, so
            // we include the name of the module.
            $countwrapperquery->add_select(array('table' => 'moduleunion',
                                                 'column' => 'modulename'));
        }

        // We want all nodes to have an oldest piece of work timestamp for background colours.
        $countwrapperquery->add_select(array('table' => 'moduleunion',
                                             'column' => 'timestamp',
                                             'function' => 'MAX',
                                             'alias' => 'timestamp'));

        $countwrapperquery->add_from(array('table' => $modulequeries,
                                           'alias' => 'moduleunion',
                                           'union' => true,
                                           'subquery' => true));

        // Apply all the standard filters. These only make sense when there's unmarked work.
        self::apply_sql_enrolled_students($countwrapperquery, $filters);
        self::apply_sql_visible($countwrapperquery, 'moduleunion.coursemoduleid',
                                'moduleunion.course');
        self::apply_sql_display_settings($countwrapperquery);
        self::apply_sql_owncourses($countwrapperquery, 'moduleunion.course');

        return $countwrapperquery;
    }

    /**
     * Wraps the countwrapper query so that a join can be done to the table(s) that hold the name
     * and other text fields which provide data for the node labels. We can't put the text
     * fields into the countwrapper because Oracle won't have any of it.
     *
     * @static
     * @param $countwrapperquery
     * @param $filters
     * @return block_ajax_marking_query_base
     */
    private static function get_display_query($countwrapperquery, $filters) {

        // The outermost query just joins the already counted nodes with their display data e.g. we
        // already have a count for each courseid, now we want course name and course description
        // but we don't do this in the counting bit so as to avoid weird issues with group by on
        // Oracle.
        $displayquery = new block_ajax_marking_query_base();
        $displayquery->add_select(array(
                                       'table' => 'countwrapperquery',
                                       'column' => 'id',
                                       'alias' => $filters['nextnodefilter']));
        $displayquery->add_select(array(
                                       'table' => 'countwrapperquery',
                                       'column' => 'itemcount'));
        $displayquery->add_select(array(
                                       'table' => 'countwrapperquery',
                                       'column' => 'timestamp'));
        $displayquery->add_select(array(
                                       'table' => 'countwrapperquery',
                                       'column' => 'recentcount'));
        $displayquery->add_select(array(
                                       'table' => 'countwrapperquery',
                                       'column' => 'mediumcount'));
        $displayquery->add_select(array(
                                       'table' => 'countwrapperquery',
                                       'column' => 'overduecount'));

        $havecoursemodulefilter = array_key_exists('coursemoduleid', $filters);
        if ($havecoursemodulefilter) { // Need to have this pass through in case we have a mixture.
            $displayquery->add_select(array(
                                           'table' => 'countwrapperquery',
                                           'column' => 'modulename'));
        }
        $displayquery->add_from(array(
                                     'table' => $countwrapperquery,
                                     'alias' => 'countwrapperquery',
                                     'subquery' => true));

        return $displayquery;
    }

    /**
     * If we have a coursemoduleid, we want to be able to get the module object that corresponds
     * to it
     *
     * @static
     * @param $coursemoduleid
     * @return block_ajax_marking_module_base|bool
     */
    private static function get_module_object_from_cmid($coursemoduleid) {

        global $DB;

        $moduleclasses = block_ajax_marking_get_module_classes();

        $moduleid = $DB->get_field('course_modules', 'module',
                                   array('id' => $coursemoduleid));

        foreach ($moduleclasses as $moduleclass) {
            /* @var $moduleclass block_ajax_marking_module_base */

            if ($moduleclass->get_module_id() == $moduleid) {
                // We don't want this one as we're filtering by a single coursemodule.
                return $moduleclass;
            }
        }

        return false;
    }

    /**
     * Called from ajax_node_count.php and returns the counts for a specific node so we can update
     * it in the tree when groups display stuff is changed and it is not expanded.
     *
     * @param array $filters
     * @return array
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public static function get_count_for_single_node($filters) {

        global $CFG;

        $modulequeries = self::get_module_queries_array($filters);

        $havecoursemodulefilter = array_key_exists('coursemoduleid', $filters);
        $moduleclass = false;
        if ($havecoursemodulefilter) {
            $moduleclass = self::get_module_object_from_cmid($filters['coursemoduleid']);
        }

        if (empty($modulequeries)) {
            return array();
        }

        $countwrapperquery = self::get_count_wrapper_query($modulequeries, $filters);
        $displayquery = self::get_display_query($countwrapperquery, $filters);

        // This will give us a query that will get the relavant node and all its siblings.
        self::apply_filters_to_query($filters, $displayquery, false, $moduleclass);

        // Now, add the current node as a WHERE clause, so we only get that one.
        $displayquery->add_where(array('type' => 'AND',
                                       'condition' => 'countwrapperquery.id = :filtervalue '));
        $displayquery->add_param('filtervalue', $filters['filtervalue']);

        // This is just for copying and pasting from the paused debugger into a DB GUI.
        if ($CFG->debug == DEBUG_DEVELOPER) {
            $debugquery = block_ajax_marking_debuggable_query($displayquery);
        }

        $nodes = $displayquery->execute();

        $node = array_pop($nodes); // Single node object.
        return array('recentcount'  => $node->recentcount,
                     'mediumcount'  => $node->mediumcount,
                     'overduecount' => $node->overduecount,
                     'itemcount'    => $node->itemcount);

    }
}
