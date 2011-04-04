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
 * Class file for the workshop marking class
 *
 * @package   blocks-ajax_marking
 * @copyright 2008-2010 Matt Gibson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_login(0, false);

/**
 * Provides marking funcionality for the workshop module
 *
 * @copyright 2008-2010 Matt Gibson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_ajax_marking_workshop extends block_ajax_marking_module_base {

    /**
     * Constructor
     *
     * @param object $mainobject the parent object passed in by reference
     * @return void
     */
    function __construct() {
        // must be the same as th DB modulename
        $this->modulename   = 'workshop';
        $this->moduleid     = $this->get_module_id();
        $this->capability   = 'mod/workshop:editdimensions';
        //$this->levels       = 2;
        $this->icon         = 'mod/workshop/icon.gif';
        $this->callbackfunctions    = array();
    }

    /**
     * Function to return all unmarked workshop submissions for all courses.
     * Called by courses()
     *
     * @return bool true
     */
    function get_all_unmarked($courseids) {

        global $CFG, $USER, $DB;

        list($usql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);

        $sql = "SELECT s.id as subid, s.authorid as userid, w.id, w.name, w.course, w.intro as description, c.id as cmid
                  FROM ({workshop} w
            INNER JOIN {course_modules} c
                    ON w.id = c.instance)
             LEFT JOIN {workshop_submissions} s
                    ON s.workshopid = w.id
             LEFT JOIN {workshop_assessments} a
                    ON (s.id = a.submissionid)
                 WHERE (a.reviewerid != :userid
                    OR (a.reviewerid = :userid2
                   AND a.grade = -1))
                   AND c.module = :moduleid
                   AND w.course $usql
                   AND c.visible = 1
              ORDER BY w.id";
        $params['userid']   = $USER->id;
        $params['userid2']  = $USER->id;
        $params['moduleid'] = $this->moduleid;
        return $DB->get_records_sql($sql, $params);
    }
    
    /**
     * See documentation for abstract function in superclass
     * 
     * @global type $DB
     * @return array of objects
     */
    function get_course_totals() {
        
        global $USER, $DB;
        
        list($displayjoin, $displaywhere) = $this->get_display_settings_sql('w', 's.authorid');

        $sql = "SELECT w.course AS courseid, COUNT(s.id) as count
                  FROM ({workshop} w
            INNER JOIN {course_modules} c
                    ON w.id = c.instance)
             LEFT JOIN {workshop_submissions} s
                    ON s.workshopid = w.id
             LEFT JOIN {workshop_assessments} a
                    ON (s.id = a.submissionid)
                       {$displayjoin}
                 WHERE (a.reviewerid != :userid
                        OR (a.reviewerid = :userid2
                            AND a.grade = -1))
                   AND c.module = :moduleid
                   AND c.visible = 1
                       {$displaywhere}
              GROUP BY w.course";
        
        $params = array();
        $params['userid']   = $USER->id;
        $params['userid2']  = $USER->id;
        $params['moduleid'] = $this->moduleid;
        
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Gets all the unmarked stuff for a course
     *
     * @param int $courseid the id number of the course
     * @return array of results objects
     */
    function get_all_course_unmarked($courseid) {

        global $CFG, $USER, $DB;

        //list($usql, $params) = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED);

        $context = get_context_instance(CONTEXT_COURSE, $courseid);
        list($studentsql, $params) = $this->get_role_users_sql($context);

        $sql = "SELECT s.id as submissionid, s.authorid as userid, w.id, w.name, w.course,
                       w.intro as description, c.id as cmid
                  FROM ({workshop} w
            INNER JOIN {course_modules} c
                    ON w.id = c.instance)
             LEFT JOIN {workshop_submissions} s
                    ON s.workshopid = w.id
             LEFT JOIN {workshop_assessments} a
                    ON (s.id = a.submissionid)
            INNER JOIN ({$studentsql}) stsql
                    ON s.authorid = stsql.id
                 WHERE (a.reviewerid != :userid
                    OR (a.reviewerid = :userid2
                   AND a.grade = -1))
                   AND c.module = :moduleid
                   AND c.visible = 1
                   AND w.course = :courseid
              ORDER BY w.id";
        $params['userid']   = $USER->id;
        $params['userid2']  = $USER->id;
        $params['moduleid'] = $this->moduleid;
        $params['courseid'] = $courseid;
        $unmarked = $DB->get_records_sql($sql, $params);
        return $unmarked;
    }

    /**
     * Outputs the submission nodes. Note: deprecated in 2.0 as showing individual submissions to mark
     * seems less useful with the new model
     *
     * @param int $workshopid
     * @param int $groupid
     * @return void
     */
    function submissions($workshopid, $groupid) {

        global $CFG, $USER, $DB;

        $workshop = $DB->get_record('workshop', array('id' => $workshopid));
        $courseid = $workshop->course;

        $context = get_context_instance(CONTEXT_COURSE, $courseid);
        list($studentsql, $params) = $this->get_role_users_sql($context);

        $sql = "SELECT s.id, s.authorid as userid, s.title, s.timecreated, s.workshopid, u.firstname, u.lastname
                  FROM {workshop_submissions} s
            INNER JOIN {user} u
                    ON s.authorid = u.id
             LEFT JOIN {workshop_assessments} a
                    ON (s.id = a.submissionid)
            INNER JOIN {workshop} w
                    ON s.workshopid = w.id
            INNER JOIN ({$studentsql}) stsql
                    ON s.authorid = stsql.id
                 WHERE (a.reviewerid != :userid
                    OR (a.reviewerid = :userid2
                   AND a.grade = -1))
                   AND s.workshopid = :workshopid
                   AND w.assessmentstart < :now
              ORDER BY s.timecreated ASC";

        $params['userid']     = $USER->id;
        $params['userid2']    = $USER->id;
        $params['workshopid'] = $workshopid;
        $params['now'] = time();

        $submissions = $DB->get_records_sql($sql, $params);

        if ($submissions) {

            // if this is set to display by group, we divert the data to the groups() function
            if (!$groupid) {
                $group_filter = block_ajax_marking_assessment_groups_filter($submissions, 'workshop', $workshop->id, $workshop->course);

                if (!$group_filter) {
                    return;
                }
            }
            // otherwise, submissionids have come back, so it must be set to display all.

            // begin json object
            $output = '[{"callbackfunction":"submissions"}';

            foreach ($submissions as $submission) {

                // if we are displaying for a single group node, ignore those students in other groups
                if ($groupid && !block_ajax_marking_is_member_of_group($groupid, $submission->userid)) {
                    continue;
                }

                // sort out the time stuff
                $seconds = (time() - $submission->timecreated);
                $summary = block_ajax_marking_make_time_summary($seconds);

                // make the node
                $output .= block_ajax_marking_make_submission_node(array(
                        'name'           => fullname($submission),
                        'submissionid'   => $submission->id,
                        'workshopid'     => $workshopid,
                        'summary'        => $summary,
                        'seconds'        => $seconds,
                        'modulename'     => $this->modulename,
                        'timecreated'    => $submission->timecreated));
            }
            $output .= ']';
            echo $output;
        }
    }

    /**
     * gets all workshops for the config tree
     *
     * @return void
     */
    function get_all_gradable_items($courseids) {

        global $CFG, $DB;

        list($usql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);

        $sql = "SELECT w.id, w.course, w.name, w.intro as summary, c.id as cmid
                  FROM {workshop} w
            INNER JOIN {course_modules} c
                    ON w.id = c.instance
                 WHERE c.module = :moduleid
                   AND c.visible = 1
                   AND w.course $usql
              ORDER BY w.id";
        $params['moduleid'] = $this->moduleid;
        $workshops = $DB->get_records_sql($sql, $params);
        $this->assessments = $workshops;
    }

    /**
     * Makes the HTML link for the popup
     *
     * @param object $item that has the workshop's courseid as cmid property
     * @return string
     */
    function make_html_link($item) {

        global $CFG;
        $address = $CFG->wwwroot.'/mod/workshop/view.php?id='.$item->cmid;
        return $address;
    }

}