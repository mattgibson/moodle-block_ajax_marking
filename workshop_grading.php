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
class workshop_functions extends module_base {

    /**
     * Constructor
     *
     * @param object $mainobject the parent object passed in by reference
     * @return void
     */
    function workshop_functions(&$mainobject) {
        $this->mainobject = $mainobject;
        // must be the same as th DB modulename
        $this->type = 'workshop';
        $this->capability = 'mod/workshop:editdimensions';
        $this->levels = 2;
        $this->icon = 'mod/workshop/icon.gif';
        $this->functions  = array(
            'workshop' => 'submissions'
        );
    }

    /**
     * Function to return all unmarked workshop submissions for all courses.
     * Called by courses()
     *
     * @return bool true
     */
    function get_all_unmarked() {

        global $CFG, $USER, $DB;

        list($usql, $params) = $DB->get_in_or_equal($this->mainobject->courseids, SQL_PARAMS_NAMED);

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
        $params['userid'] = $USER->id;
        $params['userid2'] = $USER->id;
        $params['moduleid'] = $this->mainobject->modulesettings[$this->type]->id;
        $this->all_submissions = $DB->get_records_sql($sql, $params);
        return true;
    }

    /**
     * Gets all the unmarked stuff for a course
     *
     * @param int $courseid the id number of the course
     * @return array of results objects
     */
    function get_all_course_unmarked($courseid) {

        global $CFG, $USER, $DB;

        list($usql, $params) = $DB->get_in_or_equal($this->mainobject->students->ids->$courseid, SQL_PARAMS_NAMED);

        $context = get_context_instance(CONTEXT_COURSE, $courseid);
        $student_sql = $this->get_role_users_sql($context);
        $params = $student_sql->params;

        $sql = "SELECT s.id as submissionid, s.authorid as userid, w.id, w.name, w.course,
                       w.intro as description, c.id as cmid
                  FROM ({workshop} w
            INNER JOIN {course_modules} c
                    ON w.id = c.instance)
             LEFT JOIN {workshop_submissions} s
                    ON s.workshopid = w.id
             LEFT JOIN {workshop_assessments} a
                    ON (s.id = a.submissionid)
            INNER JOIN ({$student_sql->sql}) stsql
                    ON s.authorid = stsql.id
                 WHERE (a.reviewerid != :userid
                    OR (a.reviewerid = :userid2
                   AND a.grade = -1))
                   AND c.module = :moduleid
                   AND c.visible = 1
                   AND w.course = :courseid
              ORDER BY w.id";
        $params['userid'] = $USER->id;
        $params['userid2'] = $USER->id;
        $params['moduleid'] = $this->mainobject->modulesettings[$this->type]->id;
        $params['courseid'] = $courseid;
        $unmarked = $DB->get_records_sql($sql, $params);
        return $unmarked;
    }

    /**
     * Outputs the submission nodes
     *
     * @return void
     */
    function submissions() {

        global $CFG, $USER, $DB;

        $workshop = $DB->get_record('workshop', array('id' => $this->mainobject->id));
        $courseid = $workshop->course;

        $this->mainobject->get_course_students($workshop->course);

        // fetch workshop submissions for this workshop where there is no corresponding record of
        // a teacher assessment
        list($usql, $params) = $DB->get_in_or_equal($this->mainobject->students->ids->$courseid, SQL_PARAMS_NAMED);

        $context = get_context_instance(CONTEXT_COURSE, $courseid);
        $studentsql = $this->get_role_users_sql($context);
        $params = $studentsql->params;

        $sql = "SELECT s.id, s.authorid as userid, s.title, s.timecreated, s.workshopid
                  FROM {workshop_submissions} s
             LEFT JOIN {workshop_assessments} a
                    ON (s.id = a.submissionid)
            INNER JOIN {workshop} w
                    ON s.workshopid = w.id
            INNER JOIN ({$studentsql->sql}) stsql
                    ON s.authorid = stsql.id
                 WHERE (a.reviewerid != :userid
                    OR (a.reviewerid = :userid2
                   AND a.grade = -1))
                   AND s.workshopid = :workshopid
                   AND w.assessmentstart < :now
              ORDER BY s.timecreated ASC";

        $params['userid'] = $USER->id;
        $params['userid2'] = $USER->id;
        $params['workshopid'] = $this->mainobject->id;
        $params['now'] = time();

        $submissions = $DB->get_records_sql($sql, $params);

        if ($submissions) {

            // if this is set to display by group, we divert the data to the groups() function
            if (!$this->mainobject->group) {
                $group_filter = $this->mainobject->assessment_groups_filter($submissions,
                                                                            'workshop',
                                                                            $workshop->id,
                                                                            $workshop->course);

                if (!$group_filter) {
                    return;
                }
            }
            // otherwise, submissionids have come back, so it must be set to display all.

            // begin json object
            $this->mainobject->output = '[{"type":"submissions"}';

            foreach ($submissions as $submission) {

                if (!isset($submission->userid)) {
                    continue;
                }
                // if we are displaying for a single group node, ignore those students in other groups
                $thisisagroupnode    = $this->mainobject->group;
                $inrightgroup = $this->mainobject->check_group_membership($this->mainobject->group,
                                                                          $submission->userid);

                if ($thisisagroupnode && !$inrightgroup) {
                    continue;
                }

                $name = $this->mainobject->get_fullname($submission->userid);

                // sort out the time stuff
                $seconds = (time() - $submission->timecreated);
                $summary = $this->mainobject->make_time_summary($seconds);

                // make the node
                $this->mainobject->output .= $this->mainobject->make_submission_node($name,
                                                                                     $submission->id,
                                                                                     $this->mainobject->id,
                                                                                     $summary,
                                                                                     'workshop_final',
                                                                                     $seconds,
                                                                                     $submission->timecreated);

            }
            $this->mainobject->output .= ']';
        }
    }

    /**
     * gets all workshops for the config tree
     *
     * @return void
     */
    function get_all_gradable_items() {

        global $CFG, $DB;

        list($usql, $params) = $DB->get_in_or_equal($this->mainobject->courseids, SQL_PARAMS_NAMED);

        $sql = "SELECT w.id, w.course, w.name, w.intro as summary, c.id as cmid
                  FROM {workshop} w
            INNER JOIN {course_modules} c
                    ON w.id = c.instance
                 WHERE c.module = :moduleid
                   AND c.visible = 1
                   AND w.course $usql
              ORDER BY w.id";
        $params['moduleid'] = $this->mainobject->modulesettings[$this->type]->id;
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