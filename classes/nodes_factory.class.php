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
 * Class file for the block_ajax_marking_nodes_factory class
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2011 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/blocks/ajax_marking/classes/query_base.class.php');

/**
 * This is to build a query based on the parameters passed into the constructor. Without parameters,
 * the query should return all unmarked items across all of the site.
 */
class block_ajax_marking_nodes_factory {

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
    public static function get_unmarked_module_query(array $filters,
                                                     block_ajax_marking_module_base $moduleclass) {

        // Might be a config nodes query, in which case, we want to leave off the unmarked work
        // stuff and make sure we add the display select stuff to this query instead of leaving
        // it for the outer displayquery that the unmarked work needs
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
        // Need the course to join to other filters
        $query->add_select(array('table'  => 'moduletable',
                                 'column' => 'course'));
        // Some filters need a coursemoduleid to join to, so we need to make it part of every query.
        $query->add_from(array('table' => 'course_modules',
                               'on'    => 'course_modules.instance = moduletable.id AND
                                           course_modules.module = '.$query->get_module_id()));
        // Some modules need to add stuff by joining the moduleunion back to the sub table. This
        // gets round the way we can't add stuff from just one module's sub table into the UNION bit
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
    public static function get_unmarked_nodes($filters = array()) {

        global $DB;

        // if not a union query, we will want to remember which module we are narrowed down to so we
        // can apply the postprocessing hook later

        $modulequeries = array();
        $moduleid = false;
        $moduleclass = '';
        $moduleclasses = block_ajax_marking_get_module_classes();
        if (!$moduleclasses) {
            return array(); // No nodes
        }

        $filternames = array_keys($filters);
        $havecoursemodulefilter = in_array('coursemoduleid', $filternames);
        $makingcoursemodulenodes = ($filters['nextnodefilter'] === 'coursemoduleid');

        // If one of the filters is coursemodule, then we want to avoid querying all of the module
        // tables and just stick to the one with that coursemodule. If not, we do a UNION of all
        // the modules
        if ($havecoursemodulefilter) {
            // Get the right module id
            $moduleid = $DB->get_field('course_modules', 'module',
                                       array('id' => $filters['coursemoduleid']));
        }

        foreach ($moduleclasses as $modname => $moduleclass) {
            /** @var $moduleclass block_ajax_marking_module_base */

            if ($moduleid && $moduleclass->get_module_id() !== $moduleid) {
                // We don't want this one as we're filtering by a single coursemodule
                continue;
            }

            $modulequeries[$modname] = self::get_unmarked_module_query($filters, $moduleclass);

            if ($moduleid) {
                break; // No need to carry on once we've got the only one we need
            }
        }

        // Make an array of queries to join with UNION ALL. This will get us the counts for each
        // module. Implode separate subqueries with UNION ALL. Must use ALL to cope with duplicate
        // rows with same counts and ids across the UNION. Doing it this way keeps the other items
        // needing to be placed into the SELECT  out of the way of the GROUP BY bit, which makes
        // Oracle bork up.

        // We want the bare minimum here. The idea is to avoid problems with GROUP BY ambiguity,
        // so we just get the counts as well as the node ids

        $countwrapperquery = new block_ajax_marking_query_base();
        // We find out how many submissions we have here. Not DISTINCT as we are grouping by
        // nextnodefilter in the superquery
        $countwrapperquery->add_select(array('table' => 'moduleunion',
                                             'column' => 'userid',
                                             'alias' => 'count',
                                             'function' => 'COUNT'));

        if ($havecoursemodulefilter || $makingcoursemodulenodes) {
            // Needed to access the correct javascript so we can open the correct popup, so
            // we include the name of the module
            $countwrapperquery->add_select(array('table' => 'moduleunion',
                                                 'column' => 'modulename'));
        }

        $countwrapperquery->add_from(array('table' => $modulequeries,
                                           'alias' => 'moduleunion',
                                           'union' => true,
                                           'subquery' => true));

        // Apply all the standard filters. These only make sense when there's unmarked work
        self::apply_sql_enrolled_students($countwrapperquery, $filters);
        self::apply_sql_visible($countwrapperquery, 'moduleunion.coursemoduleid',
                                'moduleunion.course');
        self::apply_sql_display_settings($countwrapperquery);
        self::apply_sql_owncourses($countwrapperquery, 'moduleunion.course');

        // The outermost query just joins the already counted nodes with their display data e.g. we
        // already have a count for each courseid, now we want course name and course description
        // but we don't do this in the counting bit so as to avoid weird issues with group by on
        // oracle
        $displayquery = new block_ajax_marking_query_base();
        $displayquery->add_select(array(
                'table'    => 'countwrapperquery',
                'column'   => 'id',
                'alias'    => $filters['nextnodefilter']));
        $displayquery->add_select(array(
                'table'    => 'countwrapperquery',
                'column'   => 'count'));
        if ($havecoursemodulefilter) { // Need to have this pass through in case we have a mixture
            $displayquery->add_select(array(
                'table'    => 'countwrapperquery',
                'column'   => 'modulename'));
        }
        $displayquery->add_from(array(
                'table'    => $countwrapperquery,
                'alias'    => 'countwrapperquery',
                'subquery' => true));

        foreach ($filters as $name => $value) {

            if ($name == 'nextnodefilter') {
                $filterfunctionname = 'apply_'.$value.'_filter';
                // The new node filter is in the form 'nextnodefilter => 'functionname', rather
                // than 'filtername' => <rowid> We want to pass the name of the filter in with
                // an empty value, so we set the value here.
                $value = false;
                $operation = 'countselect';
            } else {
                $filterfunctionname = 'apply_'.$name.'_filter';
                $operation = 'where';
            }

            // Find the function. Core ones are part of the factory class, others will be methods of
            // the module object.
            // If we are filtering by a specific module, look there first
            if (method_exists($moduleclass, $filterfunctionname)) {
                // All core filters are methods of query_base and module specific ones will be
                // methods of the module-specific subclass. If we have one of these, it will
                // always be accompanied by a coursemoduleid, so will only be called on the
                // relevant module query and not the rest
                $moduleclass->$filterfunctionname($displayquery, $operation, $value);
            } else if (method_exists(__CLASS__, $filterfunctionname)) {
                // config tree needs to have select stuff that doesn't mention sub. Like for the
                // outer wrappers of the normal query for the unmarked work nodes
                self::$filterfunctionname($displayquery, $operation, $value);
            }
        }

        // This is just for copying and pasting from the paused debugger into a DB GUI
        $debugquery = block_ajax_marking_debuggable_query($displayquery);

        $nodes = $displayquery->execute();
        if ($moduleid) {
            // this does e.g. allowing the forum module to tweak the name depending on forum type
            $moduleclass->postprocess_nodes_hook($nodes, $filters);
        }
        return $nodes;
    }

    /**
     * Applies the filter needed for course nodes or their descendants
     *
     * @param block_ajax_marking_query_base $query
     * @param bool|string $operation If we are gluing many module queries together, we will need to
     *                    run a wrapper query that will select from the UNIONed subquery
     * @param int $courseid Optional. Will apply SELECT and GROUP BY for nodes if missing
     * @return void|string
     */
    private static function apply_courseid_filter($query, $operation, $courseid = 0) {
        global $USER;

        $selects = array();
        $countwrapper = '';
        if ($operation != 'configdisplay' && $operation != 'configwhere') {
            $countwrapper = $query->get_subquery('countwrapperquery');
        }

        $tablename = '';

        switch ($operation) {

            case 'where':

                // This is for when a courseid node is an ancestor of the node that has been
                // selected, so we just do a where
                $countwrapper->add_where(array(
                        'type' => 'AND',
                        'condition' => 'moduleunion.course = :courseidfiltercourseid'));
                $query->add_param('courseidfiltercourseid', $courseid);
                break;

            case 'configwhere':

                // This is for when a courseid node is an ancestor of the node that has been
                // selected, so we just do a where
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

                // This is for the displayquery when we are making course nodes
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
                break;

            case 'configdisplay':

                // This is for the displayquery when we are making course nodes
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

                // We need the config settings too, if there are any
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
     */
    private static function apply_groupid_filter ($query, $operation, $groupid = false) {

        if (!$groupid) {
            $selects = array(array(
                    'table'    => 'groups',
                    'column'   => 'id',
                    'alias'    => 'groupid'),
                array(
                    'table'    => 'sub',
                    'column'   => 'id',
                    'alias'    => 'count',
                    'function' => 'COUNT',
                    'distinct' => true),
                array(
                    'table'    => 'groups',
                    'column'   => 'name',
                    'alias'    => 'name'),
                array(
                    'table'    => 'groups',
                    'column'   => 'description',
                    'alias'    => 'tooltip')
            );
            foreach ($selects as $select) {
                $query->add_select($select);
            }
        } else {
            // Apply WHERE clause
            $query->add_where(array(
                    'type' => 'AND',
                    'condition' => 'groups.id = :groupidfiltergroupid'));
            $query->add_param('groupidfiltergroupid', $groupid);
        }
    }

    /**
     * Applies a filter so that only nodes from a certain cohort are returned
     *
     * @param \block_ajax_marking_query_base|bool $query
     * @param $operation
     * @param bool|int $cohortid
     * @global moodle_database $DB
     * @return void
     */
    private static function apply_cohortid_filter(block_ajax_marking_query_base $query,
                                                  $operation, $cohortid = false) {

        $selects = array();
        /**
         * @var block_ajax_marking_query_base $countwrapper
         */
        $countwrapper = $query->get_subquery('countwrapperquery');

        // Note: Adding a cohort filter after any other filter will cause a problem as e.g. courseid
        // will not include the code below limiting users to just those who are in a cohort. This
        // means that the total count may well be higher for

        // We need to join the userid to the cohort, if there is one.
        // TODO when is there not one?
        // Add join to cohort_members
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

                // Apply WHERE clause
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
                // first column for the GROUP BY
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

            case 'configdisplay':

                // Awkwardly, the course_module table doesn't hold the name and description of the
                // module instances, so we need to join to the module tables. This will cause a mess
                // unless we specify that only coursemodules with a specific module id should join
                // to a specific module table
                $moduleclasses = block_ajax_marking_get_module_classes();
                $introcoalesce = array();
                $namecoalesce = array();
                foreach ($moduleclasses as $moduleclass) {
                    $query->add_from(array(
                        'join' => 'LEFT JOIN',
                        'table' => $moduleclass->get_module_table(),
                        'on' => "(course_modules.instance = ".$moduleclass->get_module_table().".id
                                  AND course_modules.module = '".$moduleclass->get_module_id()."')"
                    ));
                    $namecoalesce[$moduleclass->get_module_table()] = 'name';
                    $introcoalesce[$moduleclass->get_module_table()] = 'intro';
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

                // We need the config settings too, if there are any
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

                break;

        }

    }

    /**
     * We need to check whether the activity can be displayed (the user may have hidden it).
     * This sql can be dropped into a query so that it will get the right students. This will also
     * make sure that if only some groups are being displayed, the submission is by a user who
     * is in one of the displayed groups.
     *
     * @param block_ajax_marking_query_base $query a query object to apply these changes to
     * @return void
     */
    private static function apply_sql_display_settings($query) {

        global $DB;

        // User settings for individual activities
        $coursemodulescompare = $DB->sql_compare_text('settings_course_modules.tablename');
        $query->add_from(array(
                'join'  => 'LEFT JOIN',
                'table' => 'block_ajax_marking',
                'alias' => 'settings_course_modules',
                'on'    => "(course_modules.id = settings_course_modules.instanceid ".
                           "AND {$coursemodulescompare} = 'course_modules')"
        ));
        // User settings for courses (defaults in case of no activity settings)
        $coursecompare = $DB->sql_compare_text('settings_course.tablename');
        $query->add_from(array(
                'join'  => 'LEFT JOIN',
                'table' => 'block_ajax_marking',
                'alias' => 'settings_course',
                'on'    => "(course_modules.course = settings_course.instanceid ".
                           "AND {$coursecompare} = 'course')"
        ));
        // User settings for groups per course module. Will have a value if there is any groups
        // settings for this user and coursemodule
        list ($groupuserspersetting, $groupsparams) = self::get_sql_groups_subquery();
        $query->add_params($groupsparams);
        $query->add_from(array(
                'join'  => 'LEFT JOIN',
                'table' => $groupuserspersetting,
                'subquery' => true,
                'alias' => 'settings_course_modules_groups',
                'on'    => "settings_course_modules.id = settings_course_modules_groups.configid".
                           " AND settings_course_modules_groups.userid = moduleunion.userid"
        ));
        // Same thing for the courses. Provides a default.
        // Need to get the sql again to regenerate the params to a unique placeholder.
        list ($groupuserspersetting, $groupsparams) = self::get_sql_groups_subquery();
        $query->add_params($groupsparams);
        $query->add_from(array(
                'join'  => 'LEFT JOIN',
                'table' => $groupuserspersetting,
                'subquery' => true,
                'alias' => 'settings_course_groups',
                'on'    => "settings_course.id = settings_course_groups.configid".
                           " AND settings_course_groups.userid = moduleunion.userid"
        ));

        // Hierarchy of displaying things, simplest first. Hopefully lazy evaluation will make it
        // quick.
        // - No display settings (default to show without any groups)
        // - settings_course_modules display is 1, settings_course_modules.groupsdisplay is 0.
        //   Overrides any course settings
        // - settings_course_modules display is 1, groupsdisplay is 1 and user is in OK group
        // - settings_course_modules display is null, settings_course.display is 1,
        //   settings_course.groupsdisplay is 0
        // - settings_course_modules display is null, settings_course.display is 1,
        //   settings_course.groupsdisplay is 1 and user is in OK group.
        //   Only used if there is no setting at course_module level, so overrides that hide stuff
        //   which is shown at course level work.
        // - settings_course_modules display is null, settings_course.display is 1,
        //   settings_course.groupsdisplay is 1 and user is in OK group.
        //   Only used if there is no setting at course_module level, so overrides that hide stuff
        //   which is shown at course level work.
        $query->add_where(array(
                'type' => 'AND',
                'condition' => "
            ( (settings_course_modules.display IS NULL
               AND settings_course.display IS NULL)

              OR

              (settings_course_modules.display = 1
               AND settings_course_modules.groupsdisplay = 0)

              OR

               (settings_course_modules.display = 1
                AND settings_course_modules.groupsdisplay = 0
                AND settings_course_modules_groups.display = 1)

              OR

              (settings_course_modules.display IS NULL
               AND settings_course.display = 1
               AND settings_course.groupsdisplay = 0)

              OR

              (settings_course_modules.display IS NULL
               AND settings_course.display = 1
               AND settings_course.groupsdisplay = 1
               AND settings_course_groups.display = 1)
            )"));

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

        if ($coursemodulejoin) { // only needed if the table is not already there
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
        // back empty or else we would not have gotten this far
        $courses = block_ajax_marking_get_my_teacher_courses();
        // TODO Note that change to login as... in another tab may break this. Needs testing.
        list($coursesql, $params) = $DB->get_in_or_equal(array_keys($courses), SQL_PARAMS_NAMED);
        // Get all coursemodules the current user could potentially access.
        $sql = "SELECT id
                  FROM {course_modules}
                 WHERE course {$coursesql}";
        // no point caching - only one request per module per page request:
        $coursemoduleids = $DB->get_records_sql($sql, $params);

        // Get all contexts (will cache them). This is expensive and hopefully has been cached in
        // the session already, so we take advantage of it.
        /**
         * @var array $contexts PHPDoc needs updating for get_context_instance()
         */
        $contexts = get_context_instance(CONTEXT_MODULE, array_keys($coursemoduleids));
        // Use has_capability to loop through them finding out which are blocked. Unset all that we
        // have permission to grade, leaving just those we are not allowed (smaller list). Hopefully
        // this will never exceed 1000 (oracle hard limit on number of IN values).
        $mods = block_ajax_marking_get_module_classes();
        $modids = array();
        foreach ($mods as $mod) {
            $modids[] = $mod->get_module_id(); // Save these for later
            foreach ($contexts as $key => $context) {
                // If we don't find any capabilities for a context, it will remain and be excluded
                // from the SQL. Hopefully this will be a small list.
                if (has_capability($mod->get_capability(), $context)) { // this is cached, so fast
                    unset($contexts[$key]);
                }
            }
        }
        // return a get_in_or_equals with NOT IN if there are any, or empty strings if there aren't.
        if (!empty($contexts)) {
            list($contextssql, $contextsparams) = $DB->get_in_or_equal(array_keys($contexts),
                                                                       SQL_PARAMS_NAMED,
                                                                       'context0000',
                                                                       false);
            $query->add_where(array('type' => 'AND',
                                    'condition' => "course_modules.id {$contextssql}"));
            $query->add_params($contextsparams);
        }

        // Only show enabled mods
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
            // We need to specify only people enrolled via a cohort
            $enabledsql = " = 'cohort'";
        } else if ($CFG->enrol_plugins_enabled) {
            // returns list of english names of enrolment plugins
            $plugins = explode(',', $CFG->enrol_plugins_enabled);
            list($enabledsql, $params) = $DB->get_in_or_equal($plugins,
                                                              SQL_PARAMS_NAMED,
                                                              'enrol001');
            $query->add_params($params);
        } else {
            // no enabled enrolment plugins
            $enabledsql = ' = :sqlenrollednever';
            $query->add_param('sqlenrollednever', -1);
        }

        $sql = "SELECT 1 FROM {enrol} enrol
            INNER JOIN {user_enrolments} user_enrolments
                    ON user_enrolments.enrolid = enrol.id
                 WHERE enrol.enrol {$enabledsql}
                   AND enrol.courseid = moduleunion.course
                   AND user_enrolments.userid != :enrolcurrentuser
                   AND user_enrolments.userid = moduleunion.userid
        ";

        $query->add_where(array('type' => 'AND',
                                'condition' => "EXISTS ({$sql})"));
        $query->add_param('enrolcurrentuser', $USER->id, false);
    }

    /**
     * Provides a subquery with all users who are in groups that ought to be displayed, per config
     * setting e.g. which users are in displayed groups display for items where groups display is
     * enabled. We use a SELECT 1 to see if the user of the submission is there for the relevant
     * config thing.
     *
     * @return array SQL fragment and params
     */
    private function get_sql_groups_subquery() {
        global $USER;

        static $count = 1; // If we reuse this, we cannot have the same names for the params

        // If a user is in two groups, this will lead to duplicates. We use DISTINCT in the
        // SELECT to prevent this. Possibly one group will say 'display' and the other will say
        // 'hide'. We assume display if it's there, using MAX to get any 1 that there is.
        $groupsql = " SELECT DISTINCT gm.userid, groups_settings.configid,
                             MAX(groups_settings.display) AS display
                        FROM {groups_members} gm
                  INNER JOIN {groups} g
                          ON gm.groupid = g.id
                  INNER JOIN {block_ajax_marking_groups} groups_settings
                          ON g.id = groups_settings.groupid
                  INNER JOIN {block_ajax_marking} settings
                          ON groups_settings.configid = settings.id
                       WHERE settings.groupsdisplay = 1
                         AND settings.userid = :groupsettingsuserid{$count}
                    GROUP BY gm.userid, groups_settings.configid";
        // Adding userid to reduce the results set so that the SQL can be more efficient
        $params = array('groupsettingsuserid'.$count => $USER->id);
        $count++;

        return array($groupsql, $params);
    }

    /**
     * For the config nodes, we want all of the coursemodules. No need to worry about counting etc.
     * There is also no need for a dynamic rearrangement of the nodes, so we have two simple queries
     *
     * @param array $filters
     * @return array
     */
    public static function get_config_nodes($filters) {
        global $DB, $USER;

        // The logic is that we need to filter the course modules because some of them will be
        // hidden or the user will not have access to them. Then we m,ay or may not group them by
        // course
        $configbasequery = new block_ajax_marking_query_base();
        $configbasequery->add_from(array('table' => 'course_modules'));

        // Now apply the filters.
        self::apply_sql_owncourses($configbasequery, 'course_modules.course');
        self::apply_sql_visible($configbasequery, '', true);

        // Now we either want the courses, grouped via DISTINCT, or the whole lot
        foreach ($filters as $name => $value) {

            if ($name == 'nextnodefilter') {
                $filterfunctionname = 'apply_'.$value.'_filter';
                // The new node filter is in the form 'nextnodefilter => 'functionname', rather
                // than 'filtername' => <rowid> We want to pass the name of the filter in with
                // an empty value, so we set the value here.
                $value = false;
                $operation = 'configdisplay';
            } else {
                $filterfunctionname = 'apply_'.$name.'_filter';
                $operation = 'configwhere';
            }

            // Find the function. Core ones are part of the factory class, others will be methods of
            // the module object.
            // If we are filtering by a specific module, look there first
            if (method_exists(__CLASS__, $filterfunctionname)) {
                // config tree needs to have select stuff that doesn't mention sub. Like for the
                // outer wrappers of the normal query for the unmarked work nodes
                self::$filterfunctionname($configbasequery, $operation, $value);
            }
        }

        // This is just for copying and pasting from the paused debugger into a DB GUI
        $debugquery = block_ajax_marking_debuggable_query($configbasequery);

        $nodes = $configbasequery->execute();

        // Need to get all groups for each node. Can't do this in the main query as there are
        // possibly multiple groups settings for each node. There is a limit to how many things we
        // can have in an SQL IN statement
        // Join to the config table and

        // Get the ids of the nodes
        $courseids = array();
        $coursemoduleids = array();
        $groups = array();
        $sql = '';
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
            // otherwise, we won't be able to offer to create settings for them. Only for courses
            list($coursesql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
            $sql = "SELECT groups.id, groups.courseid AS courseid,
                           groups.name, combinedsettings.display
                      FROM {groups} groups
                 LEFT JOIN (SELECT groupssettings.display, settings.instanceid AS courseid,
                                   groupssettings.groupid
                              FROM {block_ajax_marking} settings
                        INNER JOIN {block_ajax_marking_groups} groupssettings
                                ON groupssettings.configid = settings.id
                             WHERE settings.tablename = 'course'
                               AND settings.userid = :settingsuserid) combinedsettings
                        ON (combinedsettings.courseid = groups.courseid
                            AND combinedsettings.groupid = groups.id)
                   WHERE groups.courseid {$coursesql}
                        ";
            $params['settingsuserid'] = $USER->id;

            $debugquery = block_ajax_marking_debuggable_query($sql, $params);
            $groups = $DB->get_records_sql($sql, $params);

            foreach ($groups as $group) {
                if (!isset($nodes[$group->courseid]->groups)) {
                    $nodes[$group->courseid]->groups = array();
                }
                $nodes[$group->courseid]->groups[] = $group;
            }

        } else if ($filters['nextnodefilter'] == 'coursemoduleid' && $coursemoduleids) {
            // Here, we just want data o override the course stuff if necessary
            list($cmsql, $params) = $DB->get_in_or_equal($coursemoduleids, SQL_PARAMS_NAMED);
            $sql = "SELECT groups.id, settings.instanceid AS coursemoduleid,
                           groups.name, groupssettings.display
                      FROM {groups} groups
                INNER JOIN {block_ajax_marking_groups} groupssettings
                        ON groupssettings.groupid = groups.id
                INNER JOIN {block_ajax_marking} settings
                        ON settings.id = groupssettings.configid
                     WHERE settings.tablename = 'course_modules'
                       AND settings.userid = :settingsuserid
                      AND groups.courseid = :settingscourseid
                      AND settings.instanceid {$cmsql}
                        ";
            $params['settingscourseid'] = $filters['courseid'];
            $params['settingsuserid'] = $USER->id;

            $debugquery = block_ajax_marking_debuggable_query($sql, $params);
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
     * @param block_ajax_marking_query_base $query
     * @param $operation
     * @return void
     */
    private static function apply_config_filter(block_ajax_marking_query_base $query, $operation) {

        switch ($operation) {

            case 'where':
                break;

            case 'countselect':
                break;

            case 'configselect':

                // Join to config tables. This will only be happening on the get_config_nodes query.
                // We need to join to the correct table: course or course_modules
                $table = $query->has_join_table('course_modules') ? 'course_modules' : 'course';

                $query->add_from(array(
                                     'join' => 'LEFT JOIN',
                                     'table' => 'block_ajax_marking',
                                     'alias' => 'config',
                                     'on' => "config.instanceid = {$table}.id AND
                                              config.tablename = '{$table}'"
                                 ));

                // Get display setting
                $query->add_select(array(
                                       'table' =>'config',
                                       'column' => 'display'
                                   ));
                $query->add_select(array(
                                       'table' => 'config',
                                       'column' => 'groupsdisplay'
                                   ));

                // Get groups display setting

                // Get JSON of current groups settings?
                // - what groups could have settings
                // - what groups actually have settings
                break;

            case 'displayselect':
                break;
        }

    }

}