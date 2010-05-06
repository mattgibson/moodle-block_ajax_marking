<?php

require_login(0, false);

class workshop_functions extends module_base {

    function workshop_functions(&$mainobject) {
        $this->mainobject = $mainobject;
        // must be the same as th DB modulename
        $this->type = 'workshop';
        $this->capability = 'mod/workshop:manage';
        $this->levels = 3;
        $this->icon = 'mod/workshop/icon.gif';
        $this->functions  = array(
            'workshop' => 'submissions'
        );
    }


    /**
     * Function to return all unmarked workshop submissions for all courses.
     * Called by courses()
     */
    function get_all_unmarked() {

        global $CFG, $USER, $DB;

        list($usql, $params) = $DB->get_in_or_equal($this->mainobject->courseids, SQL_PARAMS_NAMED);

        $sql = "SELECT s.id as subid, s.userid, w.id, w.name, w.course, w.description, c.id as cmid
                  FROM ({workshop} w
            INNER JOIN {course_modules} c
                    ON w.id = c.instance)
             LEFT JOIN {workshop_submissions} s
                    ON s.workshopid = w.id
             LEFT JOIN {workshop_assessments} a
                    ON (s.id = a.submissionid)
                 WHERE (a.userid != :userid
                    OR (a.userid = :userid
                   AND a.grade = -1))
                   AND c.module = :moduleid
                   AND w.course $usql
                   AND c.visible = 1
              ORDER BY w.id";
        $params['userid'] = $USER->id;
        $params['moduleid'] = $this->mainobject->modulesettings['workshop']->id;
        $this->all_submissions = $DB->get_records_sql($sql, $params);
        return true;
    }

    function get_all_course_unmarked($courseid) {

        global $CFG, $USER, $DB;

        list($usql, $params) = $DB->get_in_or_equal($this->mainobject->students->ids->$courseid, SQL_PARAMS_NAMED);

        $sql = "SELECT s.id as submissionid, s.userid, w.id, w.name, w.course,
                       w.description, c.id as cmid
                  FROM ({workshop} w
            INNER JOIN {course_modules} c
                    ON w.id = c.instance)
             LEFT JOIN {workshop_submissions} s
                    ON s.workshopid = w.id
             LEFT JOIN {workshop_assessments} a
                    ON (s.id = a.submissionid)
                 WHERE (a.userid != :userid
                    OR (a.userid = :userid
                   AND a.grade = -1))
                   AND c.module = :moduleid
                   AND c.visible = 1
                   AND w.course = :courseid
                   AND s.userid $usql
              ORDER BY w.id";
        $params['userid'] = $USER->id;
        $params['moduleid'] = $this->mainobject->modulesettings['workshop']->id;
        $params['courseid'] = $courseid;
        $unmarked = $DB->get_records_sql($sql, $params);
        return $unmarked;
    }

    function submissions() {

        global $CFG, $USER, $DB;

        $workshop = $DB->get_record('workshop', array('id' => $this->mainobject->id));
        $courseid = $workshop->course;

        $this->mainobject->get_course_students($workshop->course);

        // fetch workshop submissions for this workshop where there is no corresponding record of
        // a teacher assessment
        list($usql, $params) = $DB->get_in_or_equal($this->mainobject->students->ids->$courseid, SQL_PARAMS_NAMED);

        $sql = "SELECT s.id, s.userid, s.title, s.timecreated, s.workshopid
                  FROM {workshop_submissions} s
             LEFT JOIN {workshop_assessments} a
                    ON (s.id = a.submissionid)
            INNER JOIN {workshop} w
                    ON s.workshopid = w.id
                 WHERE (a.userid != :userid
                    OR (a.userid = :userid
                   AND a.grade = -1))
                   AND s.workshopid = :workshopid
                   AND s.userid $usql
                   AND w.assessmentstart < :now
              ORDER BY s.timecreated ASC";

        $params['userid'] = $USER->id;
        $params['workshopid'] = $this->mainobject->id;
        $params['now'] = time();

        $submissions = $DB->get_records_sql($sql, params);

        if ($submissions) {

            // if this is set to display by group, we divert the data to the groups() function
            if(!$this->mainobject->group) {
                $group_filter = $this->mainobject->assessment_groups_filter($submissions, "workshop", $workshop->id, $workshop->course);
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
                $groupnode    = $this->mainobject->group;
                $inrightgroup = $this->mainobject->check_group_membership($this->mainobject->group, $submission->userid);
                if ($groupnode && !$inrightgroup) {
                    continue;
                }

                $name = $this->mainobject->get_fullname($submission->userid);

                $sid = $submission->id;

                // sort out the time stuff
                $seconds = ($now - $submission->timecreated);
                $summary = $this->mainobject->make_time_summary($seconds);
                $this->mainobject->output .= $this->mainobject->make_submission_node($name, $sid, $this->mainobject->id,
                                                                                     $summary, 'workshop_final', $seconds,
                                                                                     $submission->timecreated);

            }
            $this->mainobject->output .= "]";
        }
    }

    /**
     * gets all workshops for the config tree
     */
    function get_all_gradable_items() {

        global $CFG, $DB;

        list($usql, $params) = $DB->get_in_or_equal($this->mainobject->courseids, SQL_PARAMS_NAMED);

        $sql = "SELECT w.id, w.course, w.name, w.description as summary, c.id as cmid
                  FROM {workshop} w
            INNER JOIN {course_modules} c
                    ON w.id = c.instance
                 WHERE c.module = :moduleid
                   AND c.visible = 1
                   AND w.course $usql
              ORDER BY w.id";
        $params['moduleid'] = $this->mainobject->modulesettings['workshop']->id;
        $workshops = $DB->get_records_sql($sql, $params);
        $this->assessments = $workshops;
    }

    function make_html_link($item) {

        global $CFG;
        $address = $CFG->wwwroot.'/mod/workshop/view.php?id='.$item->cmid;
        return $address;
    }

}