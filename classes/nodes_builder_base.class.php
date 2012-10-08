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
 * Class file for the block_ajax_marking_nodes_builder_base class
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
require_once($CFG->dirroot.'/blocks/ajax_marking/classes/bulk_context_module.class.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/lib.php');

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
class block_ajax_marking_nodes_builder_base {

    /**
     * This function applies standard joins to the module query so it can filter as much as possible before we
     * UNION the results. The more we do here, the better, as this stuff can use JOINs and therefore indexes.
     * Stuff that's done in the outer countwrapper query will be join to the temporary table created by the UNION
     * and therefore may not perform so well.
     *
     * @param array $filters
     * @param block_ajax_marking_module_base $moduleclass e.g. quiz, assignment to supply the module specific query.
     * @return block_ajax_marking_query
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
            $query = $moduleclass->query_factory();
        }

        $query->add_select(array('table'  => 'course_modules',
                                 'column' => 'id',
                                 'alias'  =>'coursemoduleid'));
        $query->set_column('coursemoduleid', 'course_modules.id');

        // Need the course to join to other filters.
        $query->add_select(array('table'  => 'moduletable',
                                 'column' => 'course'));
        // Some filters need a coursemodule id to join to, so we need to make it part of every query.
        $query->add_from(array('table' => 'course_modules',
                               'on'    => 'course_modules.instance = moduletable.id AND
                                           course_modules.module = '.$moduleclass->get_module_id()));
        // Some modules need to add stuff by joining the moduleunion back to the sub table. This
        // gets round the way we can't add stuff from just one module's sub table into the UNION
        // bit.
        $query->add_select(array('table'  => 'sub',
                                 'column' => 'id',
                                 'alias'  =>'subid'));
        // Need to pass this through sometimes for the javascript to know what sort of node it is.
        $query->add_select(array('column' => "'".$moduleclass->get_module_name()."'",
                                 'alias'  =>'modulename'));

        self::apply_sql_enrolled_students($query, $filters);
        self::apply_sql_visible($query);

        if (!block_ajax_marking_admin_see_all($filters)) {
            self::apply_sql_owncourses($query);
        }

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
    public static function unmarked_nodes(array $filters = array()) {

        global $CFG;

        $modulequeries = self::get_module_queries_array($filters);
        $nextnodefilter = block_ajax_marking_get_nextnodefilter_from_params($filters);

        $havecoursemodulefilter = array_key_exists('coursemoduleid', $filters) &&
                                  $filters['coursemoduleid'] !== 'nextnodefilter';
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
            $debugquery = $countwrapperquery->debuggable_query();
        }

        // Join to the config tables so we have settings available for the nodes context menus and for filtering
        // hidden ones.
        self::add_query_filter($countwrapperquery, 'core', 'attach_config_tables_countwrapper');
        // Apply all the standard filters. These only make sense when there's unmarked work.
        self::apply_sql_display_settings($countwrapperquery);
        // TODO is it more efficient to have this in the moduleunions to limit the rows?

        // This is just for copying and pasting from the paused debugger into a DB GUI.
        if ($CFG->debug == DEBUG_DEVELOPER) {
            $debugquery = $countwrapperquery->debuggable_query();
        }

        $displayquery = self::get_display_query($countwrapperquery, $filters);

        // This is just for copying and pasting from the paused debugger into a DB GUI.
        if ($CFG->debug == DEBUG_DEVELOPER) {
            $debugquery = $displayquery->debuggable_query();
        }

        $nodes = $displayquery->execute();

        if ($nextnodefilter == 'courseid') {
            $nodes = self::attach_groups_to_course_nodes($nodes);
        } else if ($nextnodefilter == 'coursemoduleid') {
            $nodes = self::attach_groups_to_coursemodule_nodes($nodes);
        }
        if ($havecoursemodulefilter) {
            // This e.g. allows the forum module to tweak the name depending on forum type.
            $moduleclass->postprocess_nodes_hook($nodes, $filters);
        }
        return $nodes;
    }

    /**
     * Uses the list of filters supplied by AJAX to find functions within this class and the
     * module classes which will modify the query
     *
     * @static
     * @param array $filters
     * @param block_ajax_marking_query $query which will have varying levels of nesting
     * @param bool $config flag to tell us if this is the config tree, which has a differently
     *                     structured query
     * @param block_ajax_marking_module_base|bool $moduleclass if we have a coursemoduleid filter,
     *                                                         this is the corresponding module
     *                                                         object
     */
    private static function apply_filters_to_query(array $filters,
                                                   block_ajax_marking_query $query,
                                                   $config = false,
                                                   $moduleclass = false) {

        // Now that the query object has been constructed, we want to add all the filters that will
        // limit the nodes to just the stuff we want (ignore hidden things, ignore things in other
        // parts of the tree) and groups the unmarked work by whatever it needs to be grouped by.
        foreach ($filters as $name => $value) {

            // Classes that hold the filters are named 'block_ajax_marking_filtername', where
            // filtername may be courseid, groupid, etc. For module overrides, they are
            // 'block_ajax_marking_quiz_filtername'.
            // We want a single node count.
            $classnamesuffix = $name;
            $filterfunctionname = ($value == 'nextnodefilter') ? 'nextnodetype_filter' : 'where_filter';
            $filterfunctionname = $config ? 'config'.$filterfunctionname : $filterfunctionname;
            $coreclassname = 'block_ajax_marking_'.$classnamesuffix;

            if ($moduleclass) {
                // Special case for nextnodefilter. Usually, we will have ancestors.
                $moduleclassname = self::module_override_available($moduleclass,
                                                                   $classnamesuffix,
                                                                   $filterfunctionname);
                if ($moduleclassname) {

                    // Modules provide a separate class for each type of node (userid, groupid, etc)
                    // which provide static methods for these operations.
                    $moduleclassname::$filterfunctionname($query, $value);
                }

            } else if (class_exists($coreclassname) &&
                       method_exists($coreclassname, $filterfunctionname)) {

                // Config tree needs to have select stuff that doesn't mention sub. Like for the
                // outer wrappers of the normal query for the unmarked work nodes.
                $coreclassname::$filterfunctionname($query, $value);

            }
        }
    }

    /**
     * Finds out whether there is a method provided by the modules that overrides the core ones.
     *
     * @static
     * @param $moduleclass
     * @param $classnamesuffix
     * @param $filterfunctionname
     * @return bool|string
     */
    private static function module_override_available(block_ajax_marking_module_base $moduleclass,
                                                      $classnamesuffix,
                                                      $filterfunctionname) {

        // If we are filtering by a specific module, look there first.
        $moduleclassname = 'block_ajax_marking_'.$moduleclass->get_module_name().'_'.$classnamesuffix;

        $moduleoverrideavailable = class_exists($moduleclassname) &&
                                   method_exists($moduleclassname, $filterfunctionname);

        if ($moduleoverrideavailable) {
            return $moduleclassname;
        }
        return false;
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

        // We allow course settings to override the site default and activity settings to override
        // the course ones.
        $sitedefaultactivitydisplay = 1;
        $query->add_where("COALESCE(cmconfig.display,
                                         courseconfig.display,
                                         {$sitedefaultactivitydisplay}) = 1");
    }

    /**
     * All modules have a common need to hide work which has been submitted to items that are now
     * hidden. Not sure if this is relevant so much, but it's worth doing so that test data and test
     * courses don't appear. General approach is to use cached context info from user session to
     * find a small list of contexts that a teacher cannot grade in within the courses where they
     * normally can, then do a NOT IN thing with it. Also the obvious visible = 1 stuff.
     *
     * @param block_ajax_marking_query $query
     * @param bool $includehidden Do we want to have hidden coursemodules included? Config = yes
     * @return array The join string, where string and params array. Note, where starts with 'AND'
     */
    private static function apply_sql_visible(block_ajax_marking_query $query, $includehidden = false) {
        global $DB;

        $query->add_from(array(
                'join' => 'INNER JOIN',
                'table' => 'course',
                'on' => 'course.id = '.$query->get_column('courseid')
        ));

        // If the user can potentially be blocked from accessing some things using permissions overrides, then we
        // need to take this into account.

        $mods = block_ajax_marking_get_module_classes();
        $modids = array();
        foreach ($mods as $mod) {
            $modids[] = $mod->get_module_id(); // Save these for later.
        }

        // Get coursemoduleids for all items of this type in all courses as one query. Won't come
        // back empty or else we would not have gotten this far.
        if (!is_siteadmin()) {

            $courses = block_ajax_marking_get_my_teacher_courses();
            // TODO Note that change to login as... in another tab may break this. Needs testing.
            list($coursesql, $courseparams) = $DB->get_in_or_equal(array_keys($courses), SQL_PARAMS_NAMED);
            list($modsql, $modparams) = $DB->get_in_or_equal(array_keys($modids), SQL_PARAMS_NAMED);
            $params = array_merge($courseparams, $modparams);
            // Get all course modules the current user could potentially access. Limit to the enabled
            // mods.
            $sql = "SELECT context.*
                      FROM {context} context
                INNER JOIN {course_modules} course_modules
                        ON context.instanceid = course_modules.id
                       AND context.contextlevel = ".CONTEXT_MODULE."
                     WHERE course_modules.course {$coursesql}
                       AND course_modules.module {$modsql}";
            // No point caching - only one request per module per page request...
            $contexts = $DB->get_records_sql($sql, $params);

            // Use has_capability to loop through them finding out which are blocked. Unset all that we
            // have permission to grade, leaving just those we are not allowed (smaller list). Hopefully
            // this will never exceed 1000 (oracle hard limit on number of IN values).
            // TODO - very inefficient as we are checking every context even though many will be for a different mod.
            foreach ($mods as $mod) {
                foreach ($contexts as $key => $context) {
                    // If we don't find any capabilities for a context, it will remain and be excluded
                    // from the SQL. Hopefully this will be a small list. n.b. the list is of all
                    // course modules.
                    if (has_capability($mod->get_capability(), new bulk_context_module($context))) {
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
                $query->add_where("course_modules.id {$contextssql}", $contextsparams);
            }
        }

        // We want the coursmeodules that are hidden to be gone form the main trees. For config,
        // We may want to show them greyed out so that settings can be sorted before they are shown
        // to students.
        if (!$includehidden) {
            $query->add_where('course_modules.visible = 1');
        }
        $query->add_where('course.visible = 1');

    }

    /**
     * Makes sure we only get stuff for the courses this user is a teacher in
     *
     * @param block_ajax_marking_query $query
     * @return void
     */
    private static function apply_sql_owncourses(block_ajax_marking_query $query) {

        global $DB;

        $coursecolumn = $query->get_column('courseid');
        $courses = block_ajax_marking_get_my_teacher_courses();
        $courseids = array_keys($courses);

        if ($courseids) {
            list($sql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED,
                                                       $query->get_module_name().'courseid0000');

            $query->add_where($coursecolumn.' '.$sql, $params);
        }
    }

    /**
     * Returns an SQL snippet that will tell us whether a student is directly enrolled in this
     * course. Params need to be made specific to this module as they will be duplicated. This is required
     * because sometimes, students leave and their work is left over.
     *
     * @param block_ajax_marking_query $query
     * @param array $filters So we can filter by cohort id if we need to
     * @return array The join and where strings, with params. (Where starts with 'AND')
     */
    private static function apply_sql_enrolled_students(block_ajax_marking_query $query,
                                                        array $filters) {

        global $DB, $CFG, $USER;

        $nextnodefilter = block_ajax_marking_get_nextnodefilter_from_params($filters);

        // Hide users added by plugins which are now disabled.
        if (isset($filters['cohortid']) || $nextnodefilter == 'cohortid') {
            // We need to specify only people enrolled via a cohort. No other enrollment methods matter as
            // people who are part of a cohort will have been added to the course via a cohort enrolment or
            // else they wouldn't be there.
            $enabledsql = " = 'cohort'";
            $params = array();
        } else if ($CFG->enrol_plugins_enabled) {
            // Returns list of english names of enrolment plugins.
            $plugins = explode(',', $CFG->enrol_plugins_enabled);
            list($enabledsql, $params) = $DB->get_in_or_equal($plugins,
                                                              SQL_PARAMS_NAMED,
                                                              'enrol'.$query->get_module_name().'001');
        } else {
            // No enabled enrolment plugins.
            $enabledsql = ' = :sqlenrollednever'.$query->get_module_name();
            $params = array('sqlenrollednever'.$query->get_module_name() => -1);
        }

        $sql = <<<SQL
                SELECT NULL
                  FROM {enrol} enrol
            INNER JOIN {user_enrolments} user_enrolments
                    ON user_enrolments.enrolid = enrol.id
                 WHERE enrol.enrol {$enabledsql}
                   AND enrol.courseid = {$query->get_column('courseid')}
                   AND user_enrolments.userid != :enrol{$query->get_module_name()}currentuser
                   AND user_enrolments.userid = {$query->get_column('userid')}
SQL;

        $params['enrol'.$query->get_module_name().'currentuser'] = $USER->id;
        $query->add_where("EXISTS ({$sql})", $params);
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

        $modulename = self::get_module_name_from_filters($filters);

        // The logic is that we need to filter the course modules because some of them will be
        // hidden or the user will not have access to them. Then we may or may not group them by
        // course.
        $configbasequery = new block_ajax_marking_query_base();
        $configbasequery->add_from(array('table' => 'course_modules'));
        $configbasequery->set_column('coursemoduleid', 'course_modules.id');
        $configbasequery->set_column('courseid', 'course_modules.course');

        // Now apply the filters.
        self::apply_sql_owncourses($configbasequery);
        self::apply_sql_visible($configbasequery, true);

        // TODO put this into its own function.
        reset($filters);
        foreach ($filters as $filtername => $filtervalue) {

            if ($filtervalue == 'nextnodefilter') {
                // This will attach an id to the query, to either be retrieved directly from the moduleunion,
                // or added via a join of some sort.
                self::add_query_filter($configbasequery, $filtername, 'current_config', null, $modulename);
                // If this one needs it, we add the decorator that gets the config settings.
                // TODO this is not a very elegant way of determining this.
                // Currently, we use the same wrapper for the display query, no matter what the mechanism
                // for getting the settings into the countwrapper query is, because they will just have standard
                // aliases. We don't always need it though.
            } else {
                self::add_query_filter($configbasequery, $filtername, 'ancestor', $filtervalue, $modulename);
            }
        }

        // This is just for copying and pasting from the paused debugger into a DB GUI.
        if ($CFG->debug == DEBUG_DEVELOPER) {
            $debugquery = $configbasequery->debuggable_query();
        }

        $nodes = $configbasequery->execute();

        $nextnodefilter = block_ajax_marking_get_nextnodefilter_from_params($filters);
        if ($nextnodefilter === 'courseid') {
            $nodes = self::attach_groups_to_course_nodes($nodes);
        } else if ($nextnodefilter === 'coursemoduleid') {
            $nodes = self::attach_groups_to_coursemodule_nodes($nodes);
        }

        return $nodes;

    }

    /**
     * In order to adjust the groups display settings properly, we need to know what groups are
     * available. This takes the nodes we have and attaches the groups to them if there are any.
     * We only need this for the main tree if we intend to have the ability to adjust settings
     * via right-click menus.
     *
     * @param array $nodes
     * @return array
     */
    private static function attach_groups_to_course_nodes($nodes) {

        global $DB, $USER;

        if (!$nodes) {
            return array();
        }

        // Need to get all groups for each node. Can't do this in the main query as there are
        // possibly multiple groups settings for each node.

        // Get the ids of the nodes.
        $courseids = array();
        foreach ($nodes as $node) {
            if (isset($node->courseid)) {
                $courseids[] = $node->courseid;
            }
        }

        if ($courseids) {
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

            $groups = $DB->get_records_sql($sql, $params);

            foreach ($groups as $group) {

                // Cast to integers so we can do strict type checking in javascript.
                $group->id = (int)$group->id;
                $group->courseid = (int)$group->courseid;
                $group->display = (int)$group->display;

                if (!isset($nodes[$group->courseid]->groups)) {
                    $nodes[$group->courseid]->groups = array();
                }

                $nodes[$group->courseid]->groups[$group->id] = $group;
            }
        }

        return $nodes;
    }

    /**
     * Adds an array of groups to each node for coursemodules.
     *
     * @param array $nodes
     * @throws coding_exception
     * @return mixed
     */
    private static function attach_groups_to_coursemodule_nodes($nodes) {

        global $DB, $USER;

        $coursemoduleids = array();
        foreach ($nodes as $node) {
            if (isset($node->coursemoduleid)) {
                if (in_array($node->coursemoduleid, $coursemoduleids)) {
                    throw new coding_exception('Duplicate coursemoduleids: '.$node->coursemoduleid);
                }
                $coursemoduleids[] = $node->coursemoduleid;
            }
        }

        if ($coursemoduleids) {

            // This will include groups that have no settings as we may want to make settings
            // for them.
            list($cmsql, $params) = $DB->get_in_or_equal($coursemoduleids, SQL_PARAMS_NAMED);
            // Can't have repeating groupids in column 1 or it throws an error.
            $concat = $DB->sql_concat('groups.id', "'-'", 'course_modules.id');
            $sql = <<<SQL
                 SELECT {$concat} as uniqueid,
                        groups.id,
                        course_modules.id AS coursemoduleid,
                        groups.name,
                        COALESCE(coursemodulesettingsgroups.display, coursesettingsgroups.display, 1) AS display
                   FROM {groups} groups
             INNER JOIN {course_modules} course_modules
                     ON course_modules.course = groups.courseid

               /* Get course default if it's there */
               /* There can only be one config setting at course level per group, so this is unique */
              LEFT JOIN {block_ajax_marking} coursesettings
                     ON coursesettings.tablename = 'course'
                        AND coursesettings.instanceid = groups.courseid
                        AND coursesettings.userid = :courseuserid
              LEFT JOIN {block_ajax_marking_groups} coursesettingsgroups
                     ON coursesettingsgroups.groupid = groups.id
                    AND coursesettings.id = coursesettingsgroups.configid


               /* Get coursemodule setting if it's there */
              LEFT JOIN {block_ajax_marking} coursemodulesettings
                     ON coursemodulesettings.tablename = 'course_modules'
                        AND coursemodulesettings.instanceid = course_modules.id
                        AND coursemodulesettings.userid = :coursemoduleuserid
              LEFT JOIN {block_ajax_marking_groups} coursemodulesettingsgroups
                     ON coursemodulesettingsgroups.groupid = groups.id
                    AND coursemodulesettings.id = coursemodulesettingsgroups.configid
                    WHERE course_modules.id {$cmsql}

SQL;
            $params['coursemoduleuserid'] = $USER->id;
            $params['courseuserid'] = $USER->id;

            $groups = $DB->get_records_sql($sql, $params);

            foreach ($groups as $group) {

                unset($group->uniqueid);
                // Cast to integers so we can do strict type checking in javascript.
                $group->id = (int)$group->id;
                $group->coursemoduleid = (int)$group->coursemoduleid;
                $group->display = (int)$group->display;

                if (!isset($nodes[$group->coursemoduleid]->groups)) {
                    $nodes[$group->coursemoduleid]->groups = array();
                }

                $nodes[$group->coursemoduleid]->groups[$group->id] = $group;
            }
        }

        return $nodes;
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

        $havecoursemodulefilter = array_key_exists('coursemoduleid', $filters) &&
                                  $filters['coursemoduleid'] !== 'nextnodefilter';

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

            $query = self::get_unmarked_module_query($filters, $moduleclass);

            // A very small (currently only quiz question id) number of filters need to be applied
            // at moduleunion level.
            if ($havecoursemodulefilter) {
                foreach (array_keys($filters) as $filter) {
                    if (!in_array($filter, array('courseid', 'coursemoduleid'))) { // Save a filesystem lookup.
                        self::add_query_filter($query, $filter, 'attach_moduleunion', null, $modname);
                    }
                }
            }

            $modulequeries[$modname] = $query;

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
        $modulename = null;
        if (!empty($filters['coursemoduleid']) && is_numeric($filters['coursemoduleid'])) {
            $moduleobject = self::get_module_object_from_cmid($filters['coursemoduleid']);
            if ($moduleobject) {
                $modulename = $moduleobject->get_module_name();
            }
        }
        $nextnodefilter = block_ajax_marking_get_nextnodefilter_from_params($filters);
        $makingcoursemodulenodes = ($nextnodefilter === 'coursemoduleid');

        $countwrapperquery = new block_ajax_marking_query_base();
        $countwrapperquery->set_column('courseid', 'moduleunion.course');
        $countwrapperquery->set_column('coursemoduleid', 'moduleunion.coursemoduleid');
        $countwrapperquery->set_column('userid', 'moduleunion.userid');

        // We find out how many submissions we have here. Not DISTINCT as we are grouping by
        // nextnodefilter in the superquery.

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

        // Add the counts - total, recent, medium and overdue.
        self::add_query_filter($countwrapperquery, 'core', 'counts_countwrapper');
        // Add the groupid so we can check the settings.

        if (self::filters_need_cohort_id($filters)) {
            self::add_query_filter($countwrapperquery, 'cohortid', 'attach_highest');
        }

        // Add the groupid stuff. This is expensive, so we don't add this to each module query. It also
        // uses a lot of tables, so if we duplicated it a lot of times, there's a risk of hitting the table
        // join limit.
        self::add_query_filter($countwrapperquery, 'groupid', 'attach_highest');

        // Apply the node decorators to the query, depending on what nodes are being asked for.
        reset($filters);
        foreach ($filters as $filtername => $filtervalue) {

            // Current nodes need to be grouped by this filter.
            if ($filtervalue === 'nextnodefilter') {
                // This will attach an id to the query, to either be retrieved directly from the moduleunion,
                // or added via a join of some sort.
                self::add_query_filter($countwrapperquery, $filtername, 'attach_countwrapper', null, $modulename);
                self::add_query_filter($countwrapperquery, $filtername, 'select_config_display_countwrapper');

            } else { // The rest of the filters are all of the same sort.
                self::add_query_filter($countwrapperquery, $filtername, 'ancestor', $filtervalue, $modulename);
            }
        }

        return $countwrapperquery;
    }

    /**
     * If we need the cohort id, then it has to ba attached. This will eventually be non-optional because
     * the user may have configured some cohorts to be hidden, so we will always need to cross reference
     * with the settings.
     *
     * @static
     * @param array $filters
     * @return bool
     */
    private static function filters_need_cohort_id(array $filters) {

        if (array_key_exists('cohortid', $filters)) {
            return true;
        }

        return false;
    }

    /**
     * Finds the file with the right filter (decorator) class in it and wraps the query object in it if possible.
     * Might be a filter provided by a module, so we need to check in the right place first if so in order
     * that the modules can always override any core filters. Occasionally, we use the return value to know
     * whether related filters need to be attached.
     *
     * @param block_ajax_marking_query $query
     * @param string $filterid e.g. courseid, groupid
     * @param string $type Name of the class within the folder for that id
     * @param int|string|null $parameter
     * @param string|null $modulename
     * @return bool
     */
    private static function add_query_filter(block_ajax_marking_query &$query,
                                             $filterid,
                                             $type,
                                             $parameter = null,
                                             $modulename = null) {

        global $CFG;

        $placestotry = array();

        // Module specific location. Try this first. We can't be sure that a module will
        // actually provide an override.
        if (!empty($modulename) && $modulename !== 'nextnodefilter') {
            $filename = $CFG->dirroot.'/blocks/ajax_marking/modules/'.$modulename.'/filters/'.
                        $filterid.'/'.$type.'.class.php';
            $classname = 'block_ajax_marking_'.$modulename.'_filter_'.$filterid.'_'.$type;
            $placestotry[$filename] = $classname;
        }

        // Core filter location.
        $filename = $CFG->dirroot.'/blocks/ajax_marking/filters/'.$filterid.'/'.$type.'.class.php';
        $classname = 'block_ajax_marking_filter_'.$filterid.'_'.$type;
        $placestotry[$filename] = $classname;

        foreach ($placestotry as $filename => $classname) {
            if (file_exists($filename)) {
                require_once($filename);
                if (class_exists($classname)) {
                    $query = new $classname($query, $parameter);
                    return true;
                }
            }
        }

        return false;
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
        $modulename = self::get_module_name_from_filters($filters);
        $nextnodefilter = block_ajax_marking_get_nextnodefilter_from_params($filters);
        $displayquery->add_select(array(
                                       'table' => 'countwrapperquery',
                                       'column' => 'id',
                                       'alias' => $nextnodefilter));
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

        reset($filters);
        foreach ($filters as $filtername => $filtervalue) {

            if ($filtervalue === 'nextnodefilter') {
                // This will attach an id to the query, to either be retrieved directly from the moduleunion,
                // or added via a join of some sort.
                self::add_query_filter($displayquery, $filtername, 'current', null, $modulename);
                // If this one needs it, we add the decorator that gets the config settings.
                // TODO this is not a very elegant way of determining this.
                // Currently, we use the same wrapper for the display query, no matter what the mechanism
                // for getting the settings into the countwrapper query is, because they will just have standard
                // aliases. We don't always need it though.
                if (in_array($filtername, array('courseid', 'coursemoduleid'))) {
                    self::add_query_filter($displayquery, 'core', 'select_config_display_displayquery');
                }
            }
        }

        return $displayquery;
    }

    /**
     * If there is a coursemodule in the filters, get the name of the associated module so we know what class to use.
     *
     * @static
     * @param $filters
     * @return array|null|string
     */
    private static function get_module_name_from_filters($filters) {

        $modulename = null;

        if (!empty($filters['coursemoduleid']) && is_numeric($filters['coursemoduleid'])) {
            $moduleobject = self::get_module_object_from_cmid($filters['coursemoduleid']);
            if ($moduleobject) {
                $modulename = $moduleobject->get_module_name();
            }
        }
        return $modulename;
    }

    /**
     * If we have a coursemoduleid, we want to be able to get the module object that corresponds
     * to it
     *
     * @static
     * @param int $coursemoduleid
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
     * We don't need a group by, just a where that's specific to the node, and a count.
     *
     * @param array $filters
     * @return array
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public static function get_count_for_single_node($filters) {

        // New approach...
        // Re-do the filters so that whatever the current filter is, it gets used to group the nodes again.
        // e.g. click on a course and nextnodefilter will normally be the next level down, but we are going to ask
        // for a course grouping whilst also having courseid = 2 so course is used for both current and ancestor
        // decorators.
        // TODO this will wipe out the existing value via the same array key being used. Needs a unit test.
        $filters[$filters['currentfilter']] = 'nextnodefilter';

        // Get nodes using unmarked nodes function.
        $nodes = self::unmarked_nodes($filters);

        // Pop the only one off the end.
        $node = array_pop($nodes); // Single node object.
        return array('recentcount'  => $node->recentcount,
                     'mediumcount'  => $node->mediumcount,
                     'overduecount' => $node->overduecount,
                     'itemcount'    => $node->itemcount);
    }

}

