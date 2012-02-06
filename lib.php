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
 * The main library file for the AJAX Marking block
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2008 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// show/hide constants for config settings
define('BLOCK_AJAX_MARKING_CONF_DEFAULT', 0);
define('BLOCK_AJAX_MARKING_CONF_SHOW',    1);
define('BLOCK_AJAX_MARKING_CONF_GROUPS',  2);
define('BLOCK_AJAX_MARKING_CONF_HIDE',    3);

// include the upgrade file so we have access to amb_update_modules() in case of no settings
require_once($CFG->dirroot.'/blocks/ajax_marking/db/upgrade.php');
require_once("$CFG->dirroot/enrol/locallib.php");


/**
 * Returns the sql and params array for 'IN (x, y, z)' where xyz are the ids of teacher or
 * non-editing teacher roles
 *
 * @return array $sql and $param
 */
function block_ajax_marking_teacherrole_sql() {

    global $DB;

    // TODO should be a site wide or block level setting
    $teacherroles = $DB->get_records('role', array('archetype' => 'teacher'));
    $editingteacherroles = $DB->get_records('role', array('archetype' => 'editingteacher'));
    $teacherroleids = array_keys($teacherroles + $editingteacherroles);

    return $DB->get_in_or_equal($teacherroleids);
}

/**
 * Finds out how many levels there are in the largest hierarchy of categories across the site.
 * This is so that left joins can be done that will search up the entire category hierarchy for
 * roles that were assigned at category level that would give someone grading permission in a course
 *
 * @global type $DB
 * @param bool $reset clear the cache?
 * @return int
 */
function block_ajax_marking_get_number_of_category_levels($reset=false) {

    global $DB;

    /**
     * @var stdClass $categorylevels cache this in case this is called twice during one request
     */
    static $categorylevels;

    if (isset($categorylevels) && !$reset) {
        return $categorylevels;
    }

    $sql = 'SELECT MAX(cx.depth) as depth
              FROM {context} cx
             WHERE cx.contextlevel <= ? ';
    $params = array(CONTEXT_COURSECAT);

    $categorylevels = $DB->get_record_sql($sql, $params);

    $categorylevels = $categorylevels->depth;
    $categorylevels--; // ignore site level category to get actual number of categories

    return $categorylevels;
}

/**
 * This is to find out what courses a person has a teacher role. This is instead of
 * enrol_get_my_courses(), which would prevent teachers from being assigned at category level
 *
 * @param bool $returnsql flag to determine whether we want to get the sql and params to use as a
 * subquery for something else
 * @param bool $reset
 * @return array of courses keyed by courseid
 */
function block_ajax_marking_get_my_teacher_courses($returnsql=false, $reset=false) {

    // NOTE could also use subquery without union
    /**
     * @var stdClass $USER
     */
    global $DB, $USER;

    // cache to save DB queries.
    static $courses = '';
    static $query = '';
    static $params = '';

    if ($returnsql && !$reset) {

        if (!empty($query)) {
            return array($query, $params);
        }

    } else {

        if (!empty($courses) && !$reset) {
            return $courses;
        }
    }

    list($rolesql, $roleparams) = block_ajax_marking_teacherrole_sql();

    $fieldssql = 'DISTINCT(course.id)';
    // Only get extra columns back if we are returning the actual results. Subqueries won't need it.
    $fieldssql .= $returnsql ? '' : ', fullname, shortname';

    // Main bit

    // all directly assigned roles
    $select = "SELECT {$fieldssql}
                 FROM {course} course
           INNER JOIN {context} cx
                   ON cx.instanceid = course.id
           INNER JOIN {role_assignments} ra
                   ON ra.contextid = cx.id
                WHERE course.visible = 1
                  AND cx.contextlevel = ?
                  AND ra.userid = ?
                  AND ra.roleid {$rolesql} ";

    // roles assigned in category 1 or 2 etc
    //
    // what if roles are assigned in two categories that are parent/child?
    $select .= " UNION

               SELECT {$fieldssql}
                 FROM {course} course

            LEFT JOIN {course_categories} cat1
                   ON course.category = cat1.id ";

    $where =   "WHERE course.visible = 1
                  AND EXISTS (SELECT 1
                                  FROM {context} cx
                            INNER JOIN {role_assignments} ra
                                    ON ra.contextid = cx.id
                                 WHERE cx.contextlevel = ?
                                   AND ra.userid = ?
                                   AND ra.roleid {$rolesql}
                                   AND (cx.instanceid = cat1.id ";

    // loop adding extra join tables. $categorylevels = 2 means we only need one level of categories
    // (which we already have with the first left join above) so we start from 2 and only add
    // anything if there are 3 levels or more
    // TODO does this cope with no hierarchy at all? This would mean $categoryleveles = 1
    $categorylevels = block_ajax_marking_get_number_of_category_levels();

    for ($i = 2; $i <= $categorylevels; $i++) {

        $previouscat = $i-1;
        $select .= "LEFT JOIN {course_categories} cat{$i}
                           ON cat{$previouscat}.parent = cat{$i}.id ";

        $where .= "OR cx.instanceid = cat{$i}.id ";
    }

    $query = $select.$where.'))';

    $params = array_merge(array(CONTEXT_COURSE, $USER->id),
                          $roleparams,
                          array(CONTEXT_COURSECAT, $USER->id), $roleparams);

    if ($returnsql) {
        return array($query, $params);
    } else {
        $courses = $DB->get_records_sql($query, $params);
        return $courses;
    }

}

/**
 * Instantiates all plugin classes and returns them as an array
 *
 * @param bool $reset
 * @global type $DB
 * @global type $CFG
 * @return block_ajax_marking_module_base[] array of objects keyed by modulename, each one being
 * the module plugin for that name.
 * Returns a reference.
 */
function &block_ajax_marking_get_module_classes($reset=false) {

    global $DB, $CFG;

    // cache them so we don't waste them
    static $moduleclasses = array();

    if ($moduleclasses && !$reset) {
        return $moduleclasses;
    }

    // see which modules are currently enabled
    $sql = 'SELECT name
              FROM {modules}
             WHERE visible = 1';
    $enabledmods = $DB->get_records_sql($sql);
    $enabledmods = array_keys($enabledmods);

    foreach ($enabledmods as $enabledmod) {

        if ($enabledmod === 'journal') { // just until it's fixed
            continue;
        }

        $file = "{$CFG->dirroot}/blocks/ajax_marking/modules/{$enabledmod}/".
                "block_ajax_marking_{$enabledmod}.class.php";

        if (file_exists($file)) {
            require_once($file);
            $classname = 'block_ajax_marking_'.$enabledmod;
            $moduleclasses[$enabledmod] = new $classname();
        }
    }

    return $moduleclasses;

}

/**
 * Splits the node into display and returndata bits. Display will only involve certain things, so we
 * can hardcode them to be shunted into where they belong. Anything else should be in returndata,
 * which will vary a lot, so we use that as the default.
 *
 * @param object $node
 * @param string $nextnodefilter name of the current filter
 * @return void
 */
function block_ajax_marking_format_node(&$node, $nextnodefilter) {

    $node->displaydata = new stdClass;
    $node->returndata  = new stdClass;
    $node->popupstuff  = new stdClass;
    $node->configdata  = new stdClass;

    // The things to go into display are fixed. Stuff for return data varies
    $displayitems = array(
            'count',
            'description',
            'firstname',
            'lastname',
            'modulename',
            'name',
            'seconds',
            'style',
            'summary',
            'tooltip',
            'time'
    );

    $configitems = array(
            'display',
            'groupsdisplay',
            'groups'
    );

    // loop through the rest of the object's properties moving them to the returndata bit
    foreach ($node as $varname => $value) {

        if ($varname !== 'displaydata' &&
            $varname !== 'returndata' &&
            $varname !== 'popupstuff' &&
            $varname !== 'configdata') {

            if (in_array($varname, $displayitems)) {
                $node->displaydata->$varname = $value;
            } else if (in_array($varname, $configitems)) {
                $node->configdata->$varname = $value;
            } else if ($varname == $nextnodefilter) {
                $node->returndata->$varname = $value;
            } else {
                $node->popupstuff->$varname = $value;
            }

            unset($node->$varname);
        }
    }
}

/**
 * Makes the url for the grading pop up, collapsing all the supplied parameters into GET
 *
 * @param array $params
 * @return string
 */
function block_ajax_marking_form_url($params=array()) {

    global $CFG;

    $urlbits = array();

    $url = $CFG->wwwroot.'/blocks/ajax_marking/actions/grading_popup.php?';

    foreach ($params as $name => $value) {
        $urlbits[] = $name.'='.$value;
    }

    $url .= implode('&', $urlbits);

    return $url;

}

/**
 * This is not used for output, but just converts the parametrised query to one that can be
 * copy/pasted into an SQL GUI in order to debug SQL errors
 *
 * @param block_ajax_marking_query_base|string $query
 * @param array $params
 * @global stdClass $CFG
 * @return string
 */
function block_ajax_marking_debuggable_query($query,
                                             $params = array()) {

    global $CFG;

    if (!is_string($query)) {
        $params = $query->get_params();
        $query = $query->to_string();
    }

    // We may have a problem with params being missing. Check here (assuming the params ar in SQL_PARAMS_NAMED format
    // And tell us the names of the offending params via an exception
    $pattern = '/:([A-Za-z0-9]+)/';
    $expectedparams = preg_match_all($pattern, $query, $paramnames);
    if ($expectedparams) {
        if ($expectedparams > count($params)) {
            // params are indexed by the name we gave, whereas the $paramnames are indexed by numeric position in $query
            $missingparams = array_diff($paramnames[1], array_keys($params)); // first array has colons at start of keys
            throw new coding_exception('Missing parameters: '.implode(', ', $missingparams));
        } else if ($expectedparams < count($params)) {
            $extraparams = array_diff_key(array_keys($params), $paramnames[1]);
            throw new coding_exception('Too many parameters: '.implode(', ', $extraparams));
        }
    }

    // Substitute all the {tablename} bits
    $query = preg_replace('/\{/', $CFG->prefix, $query);
    $query = preg_replace('/}/', '', $query);

    // Now put all the params in place
    foreach ($params as $name => $value) {
        $pattern = '/:'.$name.'/';
        $replacevalue = (is_numeric($value) ? $value : "'".$value."'");
        $query = preg_replace($pattern, $replacevalue, $query);
    }

    return $query;
}



