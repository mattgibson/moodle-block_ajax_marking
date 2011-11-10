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

// show/hide constants for config settings
define('BLOCK_AJAX_MARKING_CONF_DEFAULT', 0);
define('BLOCK_AJAX_MARKING_CONF_SHOW',    1);
define('BLOCK_AJAX_MARKING_CONF_GROUPS',  2);
define('BLOCK_AJAX_MARKING_CONF_HIDE',    3);

// include the upgrade file so we have access to amb_update_modules() in case of no settings
require_once($CFG->dirroot.'/blocks/ajax_marking/db/upgrade.php');

require_once("$CFG->dirroot/enrol/locallib.php");

$output = '';
$config = false;
$students = '';
$courseids = '';
$courses = '';
$teachers = '';


/**
 * Formats the summary text so that it works in the tooltips without odd characters
 *
 * @param string $text the summary text to formatted
 * @param bool $stripbr optional flag which removes <strong> tags
 *
 * @return string the cleaned text
 */
function block_ajax_marking_clean_tooltip_text($text, $stripbr=true) {

    if ($stripbr == true) {
            $text = strip_tags($text, '<strong>');
    }
    $text = str_replace(array('\n', '\r', '"'), array('', '', '&quot;'), $text);

    return $text;
}

/**
 * this function controls how long the names will be in the block. different levels need
 * different lengths as the tree indenting varies. The aim is for all names to reach as far to
 * the right as possible without causing a line break. Forum discussions will be clipped if you
 * don't alter that setting in forum_submissions(). It also removes any HTML tags.
 *
 * @param string $text the string to clean up
 * @param int $length - how many characters to cut down to. Defaults to unlimited
 *
 * @return string
 */
function block_ajax_marking_clean_name_text($text, $length=0) {

    $text = strip_tags($text, '');

    $text = htmlentities($text, ENT_QUOTES);

    if ($length) {
        $text = substr($text, 0, $length);
    }

    $text = str_replace(array('\n', '\r', '"'), array('', '', '&quot;'), $text);
    return $text;
}


/**
 * function to make the summary for submission nodes, showing how long ago it was
 * submitted
 *
 * @param int $seconds the number of seconds since submission
 * @internal param bool $discussion flag - is this a discussion in which case we need to say
 * something different
 * @return string
 */
function block_ajax_marking_make_time_tooltip($seconds) {

    $daysstr    = get_string('days',  'block_ajax_marking');
    $daystr     = get_string('day',   'block_ajax_marking');
    $hoursstr   = get_string('hours', 'block_ajax_marking');
    $hourstr    = get_string('hour',  'block_ajax_marking');
    // make the time bold unless its a discussion where there is already a lot of bolding
    $submitted = '';
    $ago = get_string('ago', 'block_ajax_marking');

    if ($seconds<3600) {
        $name = $submitted.'<1 '.$hourstr;

    } else if ($seconds<7200) {
        $name = $submitted.'1 '.$hourstr;

    } else if ($seconds<86400) {
        $hours = floor($seconds/3600);
        $name = $submitted.$hours.' '.$hoursstr;

    } else if ($seconds<172800) {
        $name = $submitted.'1 '.$daystr;

    } else {
        $days = floor($seconds/86400);
        $name = $submitted.$days.' '.$daysstr;
    }
    $name .= ' '.$ago;
    return $name;
}

/**
 * Fetches the correct config settings row from the settings object, given the details
 * of an assessment item
 *
 * @param string $assessmenttype e.g. forum, workshop
 * @param int $assessmentid the id number of the assessment
 * @param bool $reset
 * @return object a row from the config table of the DB
 */
function block_ajax_marking_get_groups_settings($assessmenttype, $assessmentid, $reset=false) {

    global $USER, $DB;

    static $groupconfig;

    if (!$groupconfig || $reset) {
        // get all configuration options set by this user
        $sql = 'SELECT * FROM {block_ajax_marking} WHERE userid = :userid';
        $params = array('userid' => $USER->id);
        $groupconfig = $DB->get_records_sql($sql, $params);
    }

    if ($groupconfig) {
        foreach ($groupconfig as $config_row) {
            $righttype = ($config_row->assessmenttype == $assessmenttype);
            $rightid = ($config_row->assessmentid == $assessmentid);

            if ($righttype && $rightid) {
                return $config_row;
            }

        }

    }
    // no settings have been stored yet - all to be left as default
    return false;
}

/**
 * This is to find out whether the assessment item should be displayed or not, according to the
 * user's preferences
 *
 * @param string $assessmenttype e.g. form, workshop
 * @param int $assessmentid   id# of that assessment
 * @param int $courseid the id number of the course
 * @return bool
 */
function block_ajax_marking_check_assessment_display_settings($assessmenttype, $assessmentid,
                                                              $courseid) {

    // find the relevant row of the config object
    $assessmentsettings = block_ajax_marking_get_groups_settings($assessmenttype, $assessmentid);
    $coursesettings = block_ajax_marking_get_groups_settings('course', $courseid);

    if ($assessmentsettings) {

        if ($assessmentsettings->display == BLOCK_AJAX_MARKING_CONF_HIDE) {
            return false;
        } else {
            return true;
        }

    } else if ($coursesettings) {
        // if there was no settings object for the item, check for a course level default
        if ($coursesettings->display == BLOCK_AJAX_MARKING_CONF_HIDE) {
            return false;
        } else {
            return true;
        }
    }
    // default to show
    return true;
}

/**
 * This runs through the previously retrieved group members list looking for a match between
 * student id and group id. If one is found, it returns true. False means that the student is
 * not a member of said group, or there were no groups supplied. Takes a space separated list so
 * that it can be used with groups list taken straight from the user settings in the DB
 *
 * @param string $groups A space separated list of groups.
 * @param int $memberid the student id to be searched for
 * @param bool $reset
 * @return bool
 */
function block_ajax_marking_is_member_of_group($groups, $memberid, $reset=false) {

    global $DB;

    static $groupmembers;

    if (!$groupmembers || $reset) {

        // TODO can we cache the course ids?
        list($coursesql, $courseparams) = block_ajax_marking_get_my_teacher_courses(true);

        $sql = "SELECT gm.*
                  FROM {groups_members} gm
            INNER JOIN {groups} g
                    ON gm.groupid = g.id
                 WHERE g.courseid IN($coursesql)";

        $groupmembers = $DB->get_records_sql($sql, $courseparams);
    }

    $groups = trim($groups);
    $groupsarray = explode(' ', $groups);

    if (!empty($groupmembers)) {

        foreach ($groupmembers as $groupmember) {

            if ($groupmember->id == $memberid) {

                if (in_array($groupmember->groupid, $groupsarray)) {
                    return true;
                }
            }
        }
    }
    return false;
}

/**
 * Makes a list of unique ids from an sql object containing submissions for many different
 * assessments. Called from the assessment level functions e.g. quizzes() and
 * count_course_submissions() Must be per course due to the cmid
 *
 * @param object $submissions Must have
 *               $submission->id as the assessment id and
 *               $submission->cmid as coursemodule id (optional for quiz question)
 *               $submission->description as the description
 *               $submission->name as the name
 * @param bool $course are we listing them for a course level node?
 * @return array array of ids => cmids
 */
function block_ajax_marking_list_assessment_ids($submissions, $course=false) {

    $ids = array();

    foreach ($submissions as $submission) {

        if ($course) {

            if ($submission->course != $course) {
                continue;
            }
        }
        $check = in_array($submission->id, $ids);

        if (!$check) {

            $ids[$submission->id]->id = $submission->id;

            if (isset($submission->cmid)) {
                $ids[$submission->id]->cmid = $submission->cmid;
            } else {
                $ids[$submission->id]->cmid = null;
            }

            if (isset($submission->description)) {
                $ids[$submission->id]->description = $submission->description;
            } else {
                $ids[$submission->id]->description = null;
            }

            if (isset($submission->name)) {
                $ids[$submission->id]->name = $submission->name;
            } else {
                $ids[$submission->id]->name = null;
            }

            if (isset($submission->timemodified)) {
                $ids[$submission->id]->timemodified = $submission->timemodified;
            } else {
                $ids[$submission->id]->timemodified = null;
            }
        }
    }
    return $ids;
}

/**
 * It turned out to be impossible to add icons reliably
 * with CSS, so this function generates the right img tag
 *
 * @param string $type This is the name of the type of icon. For assessments it is the db name
 * of the module
 * @return string the HTML for the icon
 */
function block_ajax_marking_add_icon($type) {

    // TODO make this work properly - load all icons in HTML, then apply them using css as needed
    return true;

    $result = "<img class='amb-icon' src='";

    // TODO - make question into a function held within the quiz file
    switch ($type) {

        case 'course':
            $result .= $OUTPUT->pix_url('i/course')."' alt='course icon'";
            break;

        // TODO - how to deal with 4 level modules dynamically?
        case 'question':
            $result .= $OUTPUT->pix_url('i/questions')."'";
            break;

        case 'journal':
            $result .= $OUTPUT->pix_url('icon', 'journal')."'";
            break;

        case 'group':
            $result .= $OUTPUT->pix_url('i/users')."'";
            break;

        case 'user':
            $result .= $OUTPUT->pix_url('i/user')."' alt='user icon'";
            break;

        default:

            $result .= $OUTPUT->pix_url('icon', $type)."' alt='".$type." icon'";
    }
    $result .= ' />';
    return $result;
}

/**
 * This is to make the nodes for the ul/li list that is used if AJAX is disabled.
 *
 * @param object $item contains data about the course or assessment
 * @return string
 */
function block_ajax_marking_make_html_node($item) {

    $icon = block_ajax_marking_add_icon($item->modulename);
    $title = block_ajax_marking_clean_name_text($item->tooltip);
    $node = "<li class=\"AMB_html\">
                <a href=\"{$item->link}\" title=\"{$title}\" >
                        {$icon}<strong>({$item->count})</strong>{$item->name}
            </a></li>";
    return $node;
}

/**
 * Records the display settings for one group in the database
 *
 * @global moodle_database $DB
 * @param int $groupid The id of the group
 * @param int $display 1 to show it, 0 to hide it
 * @param int $configid The id of the row int he config table that this corresponds to
 * @return bool
 */
function block_ajax_marking_set_group_display($groupid, $display, $configid) {

    global $DB;

    $data = new stdClass;
    $data->groupid = $groupid;
    $data->configid = $configid;
    $data->display = $display;

    $current = $DB->get_record('block_ajax_marking_groups', array('groupid' => $groupid));

    if ($current) {
        $data->id = $current->id;
        $DB->update_record('block_ajax_marking_groups', $data);
    } else {
        $DB->insert_record('block_ajax_marking_groups', $data);
    }
}

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
 * @staticvar int $categorylevels
 * @return int
 */
function block_ajax_marking_get_number_of_category_levels($reset=false) {

    global $DB;

    // cache this in case this is called twice during one request
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
 * @param $query
 * @param array $params
 * @global type $CFG
 * @return string
 */
function block_ajax_marking_debuggable_query($query, $params = array()) {

    global $CFG;

    if (!is_string($query)) {
        $params = $query->get_params();
        $query = $query->to_string();
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



