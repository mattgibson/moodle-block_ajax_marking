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
 * Class file for the block_ajax_marking_query_factory class
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2011 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * This is to build a query based on the parameters passed into the constructor. Without parameters,
 * the query should return all unmarked items across all of the site.
 */
class block_ajax_marking_query_factory {

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
    public static function get_query($filters = array()) {

        global $DB;

        // if not a union query, we will want to remember which module we are narrowed down to so we
        // can apply the postprocessing hook later
        $singlemoduleclass = false;
        $filterfunctionname = false;

        $modulequeries = array();
        $moduleid = false;
        $moduleclasses = block_ajax_marking_get_module_classes();

        $filternames = array_keys($filters);

        // If one of the filters is coursemodule, then we want to avoid querying all of the module
        // tables and just stick to the one with that coursemodule. If not, we do a UNION of all
        // the modules
        if (in_array('coursemoduleid', $filternames)) {
            // Get the right module id
            $moduleid = $DB->get_field('course_modules', 'module',
                                       array('id' => $filters['coursemoduleid']));
        }

        foreach ($moduleclasses as $modname => $moduleclass) {
            // comment to try to get the IDE code completion to work properly
            /** @var $moduleclass block_ajax_marking_module_base */

            if ($moduleid) {
                if ($moduleclass->get_module_id() == $moduleid) {
                    // We only want this one as we're filtering by a single coursemodule
                    $modulequeries[$modname] = $moduleclass->query_factory();
                    // we will use this ref to get access to non-union module functions
                    $singlemoduleclass = $moduleclass;
                } else {
                    continue;
                }
            } else {
                // we want all of them for the union
                $modulequeries[$modname] = $moduleclass->query_factory();
            }

            // Apply all the standard filters
            self::apply_sql_enrolled_students($modulequeries[$modname]);
            self::apply_sql_visible($modulequeries[$modname]);
            self::apply_sql_display_settings($modulequeries[$modname]);
            self::apply_sql_owncourses($modulequeries[$modname]);

            // Apply any filters specific to this request. Next node type one should be a GROUP BY,
            // the rest need to be WHEREs i.e. starting from the requested nodes, and moving back up
            // the tree e.g. 'student', 'assessment', 'course'
            $groupby = false;
            foreach ($filters as $name => $value) {
                if ($name == 'nextnodefilter') {
                    //continue; // nextnodefilter is applied to the outermost query
                    $filterfunctionname = 'apply_'.$value.'_filter';
                    $groupby = $value;
                    // The new node filter is in the form 'nextnodefilter => 'functionname', rather
                    // than 'filtername' => <rowid> We want to pass the name of the filter in with
                    // an empty value, so we set the value here.
                    $value = false;
                } else {
                    $filterfunctionname = 'apply_'.$name.'_filter';
                }
                // Find the function. Core ones are part of this class, others will be methods of
                // the module object.
                // If we are filtering by a specific module, look there first
                if (method_exists($moduleclass, $filterfunctionname)) {
                    // All core filters are methods of query_base and module specific ones will be
                    // methods of the module-specific subclass. If we have one of these, it will
                    // always be accompanied by a coursemoduleid, so will only be called on the
                    // relevant module query and not the rest
                    // TODO does this still pass by reference even though it's part of an array?
                    $moduleclass->$filterfunctionname($modulequeries[$modname], $value);
                } else if (method_exists(__CLASS__, $filterfunctionname)) {
                    self::$filterfunctionname($modulequeries[$modname], $value);
                } else {
                    // Can't find the function. Assume it's non-essential data.
                    debugging('Can\'t find the '.$filterfunctionname.' function.');
                }
            }

            // Sometimes, the module will want to customise the query a bit after all the filters
            // are applied but before it's run. This is mostly to affect what data comes in the
            // SELECT part of the query
            $moduleclass->alter_query_hook($modulequeries[$modname], $groupby);

            if ($moduleid) {
                break; // No need to carry on once we've done the only one
            }
        }

        // Make an array of queries to join with UNION ALL. This will get us the counts for each
        // module Implode separate subqueries with UNION ALL. Must use ALL to cope with duplicate
        // rows with same counts and ids across the UNION. Doing it this way keeps the other items
        // needing to be placed into the SELECT  out of the way of the GROUP BY bit, which makes
        // Oracle bork up.
        $moduleunionqueries = array();
        $moduleunionparams = array();
        foreach ($modulequeries as $query) {
            /** @var $query block_ajax_marking_query_base */
            $moduleunionqueries[] = $query->to_string();
            $moduleunionparams = array_merge($moduleunionparams, $query->get_params());
        }
        $moduleunion = implode("\n\n UNION ALL \n\n", $moduleunionqueries);
        // We want the bare minimum here. The idea is to avoid problems with GROUP BY ambiguity,
        // so we just get the counts
        $select = "moduleunion.".$filters['nextnodefilter'].
                  " AS id, SUM(moduleunion.count) AS count ";
        $groupby = "moduleunion.".$filters['nextnodefilter'];
        $havecoursemodulefilter = in_array('coursemoduleid', $filternames);
        $makingcoursemodulenodes = $filters['nextnodefilter'] === 'coursemoduleid';
        if ($havecoursemodulefilter || $makingcoursemodulenodes) {
            // Needed to get correct javascript
            $select .=  ", moduleunion.modulename AS modulename ";
            $groupby .=  ", moduleunion.modulename ";
        }
        $combinedmodulesubquery = "
            SELECT {$select}
              FROM ({$moduleunion}) moduleunion
          GROUP BY {$groupby}
          "; // Newlines so the debug query reads better

        // The outermost query just joins the already counted nodes with their display data e.g. we
        // already have a count for each courseid, now we want course name and course description
        // but we don't do this in the counting bit so as to avoid weird issues with group by on
        // oracle
        $unionquery = new block_ajax_marking_query_base();
        $unionquery->add_select(array(
                'table'    => 'combinedmodulesubquery',
                'column'   => 'id',
                'alias'    => $filters['nextnodefilter']));
        $unionquery->add_select(array(
                'table'    => 'combinedmodulesubquery',
                'column'   => 'count'));
        if ($havecoursemodulefilter) { // Need to have this pass through in case we have a mixture
            $unionquery->add_select(array(
                'table'    => 'combinedmodulesubquery',
                'column'   => 'modulename'));
        }
        $unionquery->add_from(array(
                'table'    => $combinedmodulesubquery,
                'alias'    => 'combinedmodulesubquery',
                'subquery' => true));
        $unionquery->add_params($moduleunionparams, false);

        // Now we need to run the final query through the filter for the nextnodetype so that the
        // rest of the necessary SELECT columns can be added, along with the JOIN to get them
        $nextnodefilterfunction = 'apply_'.$filters['nextnodefilter'].'_filter';
        if ($moduleid && method_exists($singlemoduleclass, $filterfunctionname)) {
            $singlemoduleclass->$nextnodefilterfunction($unionquery, false, true); // allow override
        } else if (method_exists(__CLASS__, $filterfunctionname)) {
            self::$filterfunctionname($unionquery, false, true);
        } else {
            // Problem - we have nothing to provide node display data
            throw new coding_exception('No final filter applied for nextnodetype!');
        }

        $debugquery = $unionquery->debuggable_query();

        $nodes = $unionquery->execute();
        if ($singlemoduleclass) {
            $singlemoduleclass->postprocess_nodes_hook($nodes, $filters);
        }
        return $nodes;

    }

    /**
     * Applies the filter needed for course nodes or their descendants
     *
     * @param block_ajax_marking_query_base $query
     * @param int $courseid Optional. Will apply SELECT and GROUP BY for nodes if missing
     * @param bool $union If we are glueing many module queries together, we will need to
     *                    run a wrapper query that will select from the UNIONed subquery
     * @return void|string
     */
    private static function apply_courseid_filter($query, $courseid = 0, $union = false) {
        // Apply SELECT clauses for course nodes

        if ($courseid) { // We are getting courses
            // Apply WHERE clause
            $query->add_where(array(
                    'type' => 'AND',
                    'condition' => 'moduletable.course = :'.$query->prefix_param('courseid')));
            $query->add_param('courseid', $courseid);
            return;
        }

        if (!$union) { // What do we need for the individual module queries?

            $selects = array(
                array(
                    'table'    => 'moduletable',
                    'column'   => 'course',
                    'alias'    => 'courseid'),
                array(
                    'table'    => 'sub',
                    'column'   => 'id',
                    'alias'    => 'count',
                    'function' => 'COUNT',
                    'distinct' => true));
        } else {
            $query->add_from(array(
                    'join' => 'INNER JOIN',
                    'table' => 'course',
                    'on' => 'combinedmodulesubquery.id = course.id'
            ));
            $selects = array(
                array(
                    'table'    => 'course',
                    'column'   => 'shortname',
                    'alias'    => 'name'),
                array(
                    'table'    => 'course',
                    'column'   => 'fullname',
                    'alias'    => 'tooltip'));
        }

        foreach ($selects as $select) {
            $query->add_select($select);
        }
    }

    /**
     *
     * @param block_ajax_marking_query_base $query
     * @param bool|int $groupid
     * @return void
     */
    private static function apply_groupid_filter ($query, $groupid = false) {

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
                    'condition' => 'groups.id = :'.$query->prefix_param('groupid')));
            $query->add_param('groupid', $groupid);
        }
    }

    /**
     * Applies a filter so that only nodes from a certain cohort are returned
     *
     * @global moodle_database $DB
     * @param \block_ajax_marking_query_base|bool $query
     * @param bool|int $cohortid
     * @param bool $union
     * @return void
     */
    private static function apply_cohortid_filter($query = false,
                                                  $cohortid = false,
                                                  $union = false) {

        global $DB;

        // Note: Adding a cohort filter after any other filter will cause a problem as e.g. courseid
        // will not include the code below limiting users to just those who are in a cohort. This
        // means that the total count may well be higher for

        $showorphanedusers = false; // TODO maybe we want a 'no cohort' node?
        // We need to join the userid to the cohort, if there is one

        $useridcolumn = $query->get_userid_column();
        if ($useridcolumn) {
            // Add join to cohort_members
            $query->add_from(array(
                    'join' => 'INNER JOIN',
                    'table' => 'cohort_members',
                    'on' => 'cohort_members.userid = '.$useridcolumn
            ));
            $query->add_from(array(
                    'join' => 'INNER JOIN',
                    'table' => 'cohort',
                    'on' => 'cohort_members.cohortid = cohort.id'
            ));

            // Join cohort_members only to cohorts that are enrolled in the course.
            // We already have a check for enrolments, so we just need a where.
            $query->add_where(array(
                    'type' => 'AND',
                    'condition' => 'cohort_members.id = enrol.customint1'));
            $query->add_where(array(
                    'type' => 'AND',
                    'condition' => $DB->sql_compare_text('enrol.enrol')." = 'cohort'"));
        }

        if ($cohortid) {
            // Apply WHERE clause
            $query->add_where(array(
                    'type' => 'AND',
                    'condition' => 'cohort.id = :'.$query->prefix_param('cohortid')));
            $query->add_param('cohortid', $cohortid);
            return;
        }

        if (!$union) { // what we want if we are just dealing with a single module in the count bit

            $selects = array(array(
                    'table'    => 'cohort',
                    'column'   => 'id',
                    'alias'    => 'cohortid'),
                array(
                    'table'    => 'sub',
                    'column'   => 'id',
                    'alias'    => 'count',
                    'function' => 'COUNT',
                    'distinct' => true),
            );
        } else {
            // What do we need for the nodes?
            $query->add_from(array(
                    'join' => 'INNER JOIN',
                    'table' => 'cohort',
                    'on' => 'combinedmodulesubquery.id = cohort.id'
            ));
            $selects = array(
                array(
                    'table'    => 'cohort',
                    'column'   => 'name'),
                array(
                    'table'    => 'cohort',
                    'column'   => 'description'));

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
     * @param bool $union
     * @return void
     */
    private static function apply_coursemoduleid_filter($query,
                                                        $coursemoduleid = 0,
                                                        $union = false) {

        if (!$coursemoduleid) {

            // Same order as the next query will need them
            if (!$union) {
                $selects = array(
                    array(
                        'table' => 'cm',
                        'column' => 'id',
                        'alias' => 'coursemoduleid'),
                    array(
                        'table' => 'sub',
                        'column' => 'id',
                        'alias' => 'count',
                        'function' => 'COUNT',
                        'distinct' => true),
                    // This is only needed to add the right callback function.
                    array(
                        'column' => "'".$query->get_modulename()."'",
                        'alias' => 'modulename'
                        ));
            } else {

                // Need to get the module stuff from specific tables, not coursemodule
                $query->add_from(array(
                        'join' => 'INNER JOIN',
                        'table' => 'course_modules',
                        'on' => 'combinedmodulesubquery.id = course_modules.id'
                ));

                // Awkwardly, the course_module table doesn't hold the name and description of the
                // module instances, so we need to join to the module tables. This will cause a mess
                // unless we specify that only coursemodules with a specific module id should join
                // to a specific module table
                $moduleclasses = block_ajax_marking_get_module_classes();
                $coalesce = array();
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

                $selects = array(
                    array(
                            'table'    => 'course_modules',
                            'column'   => 'id',
                            'alias'    => 'coursemoduleid'),
                    array(
                            'table'    => 'combinedmodulesubquery',
                            'column'   => 'modulename'),
                    array(
                            'table'    => $namecoalesce,
                            'function' => 'COALESCE',
                            'column'   => 'name',
                            'alias'    => 'name'),
                    array(
                            'table'    => $introcoalesce,
                            'function' => 'COALESCE',
                            'column'   => 'intro',
                            'alias'    => 'tooltip')

                );
            }

            foreach ($selects as $select) {
                $query->add_select($select);
            }

        } else {
            // Apply WHERE clause
            $query->add_where(array(
                    'type' => 'AND',
                    'condition' => 'cm.id = :'.$query->prefix_param('coursemoduleid')));
            $query->add_param('coursemoduleid', $coursemoduleid);
        }
    }

    /**
     * We need to check whether the assessment can be displayed (the user may have hidden it).
     * This sql can be dropped into a query so that it will get the right students
     *
     * @param block_ajax_marking_query_base $query a query object to apply these changes to
     * @return void
     */
    protected function apply_sql_display_settings($query) {

        $query->add_from(array(
                'join' => 'LEFT JOIN',
                'table' => 'block_ajax_marking',
                'alias' => 'bama',
                'on' => 'cm.id = bama.coursemoduleid'
        ));
        $query->add_from(array(
                'join' => 'LEFT JOIN',
                'table' => 'block_ajax_marking',
                'alias' => 'bamc',
                'on' => 'moduletable.course = bamc.courseid'
        ));

        // either no settings, or definitely display
        // TODO doesn't work without proper join table for groups

        // student might be a member of several groups. As long as one group is in the settings
        // table, it's ok.
        // TODO is this more or less efficient than doing an inner join to a subquery?

        // WHERE starts with the course defaults in case we find no assessment preference
        // Hopefully short circuit evaluation will makes this efficient.

        // TODO can this be made more elegant with a recursive bit in get_where() ?
        $useridfield = $query->get_userid_column();
        $groupsubquerya = self::get_sql_groups_subquery('bama', $useridfield);
        // EXISTS ([user in relevant group]):
        $groupsubqueryc = self::get_sql_groups_subquery('bamc', $useridfield);
        $query->add_where(array(
                'type' => 'AND',

                // Logic: show if we have:
                // - no item settings records, or a setting set to 'default' (legacy need)
                // - a course settings record that allows display
                'condition' => "
                (   bama.display = ".BLOCK_AJAX_MARKING_CONF_SHOW."
                    OR
                    ( bama.display = ".BLOCK_AJAX_MARKING_CONF_GROUPS." AND {$groupsubquerya} )
                    OR
                    ( ( bama.display IS NULL OR bama.display = ".BLOCK_AJAX_MARKING_CONF_DEFAULT." )
                        AND
                        ( bamc.display IS NULL
                          OR bamc.display = ".BLOCK_AJAX_MARKING_CONF_SHOW."
                          OR (bamc.display = ".BLOCK_AJAX_MARKING_CONF_GROUPS. "
                              AND {$groupsubqueryc})
                        )
                    )
                    )"));

    }

    /**
     * All modules have a common need to hide work which has been submitted to items that are now
     * hidden. Not sure if this is relevant so much, but it's worth doing so that test data and test
     * courses don't appear. General approach is to use cached context info from user session to
     * find a small list of contexts that a teacher cannot grade in within the courses where they
     * normally can, then do a NOT IN thing with it. Also the obvious visible = 1 stuff.
     *
     * @param \block_ajax_marking_query_base $query
     * @return array The join string, where string and params array. Note, where starts with 'AND'
     */
    protected function apply_sql_visible(block_ajax_marking_query_base $query) {

        global $DB;

        $query->add_from(array(
                'join' => 'INNER JOIN',
                'table' => 'course_modules',
                'alias' => 'cm',
                'on' => 'cm.instance = moduletable.id'
        ));
        $query->add_from(array(
                'join' => 'INNER JOIN',
                'table' => 'course',
                'alias' => 'course',
                'on' => 'course.id = moduletable.course'
        ));

        // Get coursemoduleids for all items of this type in all courses as one query. Won't come
        // back empty or else we would not have gotten this far
        $courses = block_ajax_marking_get_my_teacher_courses();
        // TODO Note that change to login as... in another tab may break this

        list($coursesql, $params) = $DB->get_in_or_equal(array_keys($courses), SQL_PARAMS_NAMED);

        // Get all coursemodules the current user could potentially access.
        // TODO this may return literally millions for a whole site admin. Change it to the one
        // that's limited by explicit category and course permissions
        $sql = "SELECT id
                  FROM {course_modules}
                 WHERE course {$coursesql}
                   AND module = :moduleid";
        $params['moduleid'] = $query->get_module_id();
        // no point caching - only one request per module per page request:
        $coursemoduleids = $DB->get_records_sql($sql, $params);
        // Get all contexts (will cache them). This is expensive and hopefully has been cached in]
        // the session already, so we take advantage of it.
        $contexts = get_context_instance(CONTEXT_MODULE, array_keys($coursemoduleids));
        // Use has_capability to loop through them finding out which are blocked. Unset all that we
        // have parmission to grade, leaving just those we are not allowed (smaller list). Hopefully
        // this will never exceed 1000 (oracle hard limit on number of IN values).
        foreach ($contexts as $key => $context) {
            if (has_capability($query->get_capability(), $context)) { // this is fast because cached
                unset($contexts[$key]);
            }
        }
        // return a get_in_or_equals with NOT IN if there are any, or empty strings if there arent.
        if (!empty($contexts)) {
            list($contextssql, $contextsparams) = $DB->get_in_or_equal(array_keys($contexts),
                                                                       SQL_PARAMS_NAMED,
                                                                       'context0000',
                                                                       false);
            $query->add_where(array('type' => 'AND', 'condition' => "cm.id {$contextssql}"));
            $query->add_params($contextsparams);
        }

        $query->add_where(array(
                'type' => 'AND',
                'condition' => 'cm.module = :'.$query->prefix_param('visiblemoduleid')));
        $query->add_where(array('type' => 'AND', 'condition' => 'cm.visible = 1'));
        $query->add_where(array('type' => 'AND', 'condition' => 'course.visible = 1'));

        $query->add_param('visiblemoduleid', $query->get_module_id());

    }

    /**
     * Makes sure we only get stuff for the courses this user is a teacher in
     *
     * @param block_ajax_marking_query_base $query
     * @return void
     */
    private function apply_sql_owncourses(block_ajax_marking_query_base $query) {

        global $DB;

        $courses = block_ajax_marking_get_my_teacher_courses();

        $courseids = array_keys($courses);

        if ($courseids) {
            $startname = $query->prefix_param('courseid0000');
            list($sql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, $startname);

            $query->add_where(array(
                    'type' => 'AND',
                    'condition' => "course.id {$sql}"));
            $query->add_params($params, false);
        }
    }

    /**
     * Returns an SQL snippet that will tell us whether a student is directly enrolled in this
     * course
     * @TODO: Needs to also check parent contexts.
     *
     * @param \block_ajax_marking_query_base $query
     * @internal param string $useralias the thing that contains the userid e.g. s.userid
     * @internal param string $moduletable the thing that contains the courseid e.g. a.course
     * @return array The join and where strings, with params. (Where starts with 'AND)
     */
    private function apply_sql_enrolled_students(block_ajax_marking_query_base $query) {

        global $DB, $CFG, $USER;

        $usercolumn = $query->get_userid_column();

        // TODO Hopefully, this will be an empty string when none are enabled
        if ($CFG->enrol_plugins_enabled) {
            // returns list of english names of enrolment plugins
            $plugins = explode(',', $CFG->enrol_plugins_enabled);
            $startparam = $query->prefix_param('enrol001');
            list($enabledsql, $params) = $DB->get_in_or_equal($plugins,
                                                              SQL_PARAMS_NAMED,
                                                              $startparam);
            $query->add_params($params, false);
        } else {
            // no enabled enrolment plugins
            $enabledsql = ' = :'.$query->prefix_param('never');
            $query->add_param('never', -1);
        }

        $subquery = new block_ajax_marking_query_base();
        $subquery->add_select(array(
                'table' => 'enrol',
                'column' => 'courseid'
        ));
        $subquery->add_from(array(
                'table' => 'user_enrolments'
        ));
        $subquery->add_from(array(
                'join' => 'INNER JOIN',
                'table' => 'enrol',
                'on' => 'enrol.id = user_enrolments.enrolid'
        ));
        $subquery->add_where(array(
                'type' => 'AND',
                'condition' => "user_enrolments.userid = :".$query->prefix_param('currentuser')
        ));
        $query->add_from(array(
                'join' => 'INNER JOIN',
                'table' => $subquery,
                'alias' => 'ue',
                'on' => "ue.courseid = moduletable.course"
        ));
        $query->add_param('currentuser', $USER->id);

        // Also hide our own work. Only really applies in testing, but still.
        $query->add_where(array(
                'type' => 'AND',
                'condition' => $query->get_userid_column()." != :".$query->prefix_param('currentuser2')
        ));
        $query->add_param('currentuser2', $USER->id);

    }

    /**
     * Provides an EXISTS(xxx) subquery that tells us whether there is a group with user x in it
     *
     * @param string $configalias this is the alias of the config table in the SQL
     * @param string $useridfield
     * @return string SQL fragment
     */
    private function get_sql_groups_subquery($configalias, $useridfield) {

        $groupsql = " EXISTS (SELECT 1
                                FROM {groups_members} gm
                          INNER JOIN {groups} g
                                  ON gm.groupid = g.id
                          INNER JOIN {block_ajax_marking_groups} gs
                                  ON g.id = gs.groupid
                               WHERE gm.userid = {$useridfield}
                                 AND gs.configid = {$configalias}.id) ";

        return $groupsql;

    }
}