<?php

require_login(1, false);

class workshop_functions extends module_base {

    function workshop_functions(&$mainobject) {
        $this->mainobject = $mainobject;
        // must be the same as th DB modulename
        $this->type = 'workshop';
        $this->capability = 'mod/workshop:manage';
        $this->levels = 3;
    }


    /**
     * Function to return all unmarked workshop submissions for all courses.
     * Called by courses()
     */
    function get_all_unmarked() {
        global $CFG, $USER;
        $sql = "
             SELECT s.id as subid, s.userid, w.id, w.name, w.course, w.description, c.id as cmid
             FROM ({$CFG->prefix}workshop w
             INNER JOIN {$CFG->prefix}course_modules c
                 ON w.id = c.instance)
             LEFT JOIN {$CFG->prefix}workshop_submissions s
                 ON s.workshopid = w.id
             LEFT JOIN {$CFG->prefix}workshop_assessments a
             ON (s.id = a.submissionid)
             WHERE (a.userid != {$USER->id}
              OR (a.userid = {$USER->id}
                    AND a.grade = -1))
             AND c.module = {$this->mainobject->module_ids['workshop']->id}
             AND w.course IN ({$this->mainobject->course_ids})
             AND c.visible = 1
             ORDER BY w.id
        ";

        $this->all_submissions = get_records_sql($sql);
        return true;
    }

    function get_all_course_unmarked($courseid) {

        global $CFG, $USER;
        $sql = "SELECT s.id as submissionid, s.userid, w.id, w.name, w.course, w.description, c.id as cmid
            FROM
               ( {$CFG->prefix}workshop w
            INNER JOIN {$CFG->prefix}course_modules c
                 ON w.id = c.instance)
            LEFT JOIN {$CFG->prefix}workshop_submissions s
                 ON s.workshopid = w.id
            LEFT JOIN {$CFG->prefix}workshop_assessments a
            ON (s.id = a.submissionid)
            WHERE (a.userid != {$USER->id}
              OR (a.userid = {$USER->id}
                    AND a.grade = -1))
            AND c.module = {$this->mainobject->module_ids['workshop']->id}
            AND c.visible = 1
            AND w.course = $courseid
            AND s.userid IN ({$this->mainobject->student_ids->$courseid})
            ORDER BY w.id
        ";

        $unmarked = get_records_sql($sql);
        return $unmarked;
    }
    
    function submissions() {

        global $CFG, $USER;

        $workshop = get_record('workshop', 'id', $this->mainobject->id);
        $courseid = $workshop->course;
       
        $this->mainobject->get_course_students($workshop->course);
        
        $now = time();
        // fetch workshop submissions for this workshop where there is no corresponding record of a teacher assessment
        $sql = "
            SELECT s.id, s.userid, s.title, s.timecreated, s.workshopid
            FROM {$CFG->prefix}workshop_submissions s
            LEFT JOIN {$CFG->prefix}workshop_assessments a
            ON (s.id = a.submissionid)
            INNER JOIN {$CFG->prefix}workshop w
            ON s.workshopid = w.id
            WHERE (a.userid != {$USER->id}
            OR (a.userid = {$USER->id}
            AND a.grade = -1))
            AND s.workshopid = {$this->mainobject->id}
            AND s.userid IN ({$this->mainobject->student_ids->$courseid})
            AND w.assessmentstart < {$now}
            ORDER BY s.timecreated ASC
        ";

        $submissions = get_records_sql($sql);

        if ($submissions) {

            // if this is set to display by group, we divert the data to the groups() function
            if(!$this->mainobject->group) {
                $group_filter = $this->mainobject->assessment_groups_filter($submissions, "workshop", $workshop->id);
                if (!$group_filter) {
                    return;
                }
            }
            // otherwise, submissionids have come back as its display all.

            // begin json object
            $this->mainobject->output = '[{"type":"submissions"}';

            foreach ($submissions as $submission) {

                if (!isset($submission->userid)) {
                    continue;
                }
                if ($this->mainobject->group && !$this->check_group_membership($this->mainobject->group, $submission->userid)) {
                    continue;
                }

                $name = $this->mainobject->get_fullname($submission->userid);

                $sid = $submission->id;

                // sort out the time stuff
                $now = time();
                $seconds = ($now - $submission->timecreated);
                $summary = $this->mainobject->make_time_summary($seconds);
                $this->mainobject->output .= $this->mainobject->make_submission_node($name, $sid, $this->mainobject->id, $summary, 'workshop_answer', $seconds, $submission->timecreated);

            }
            $this->mainobject->output .= "]"; // end JSON array
        }
    }

    /**
     * gets all workshops for the config tree
     */
    function get_all_gradable_items() {

        global $CFG;

        $sql = "
            SELECT w.id, w.course, w.name, w.description as summary, c.id as cmid
            FROM {$CFG->prefix}workshop w
            INNER JOIN {$CFG->prefix}course_modules c
            ON w.id = c.instance
            WHERE c.module = {$this->mainobject->module_ids['workshop']->id}
            AND c.visible = 1
            AND w.course IN ({$this->mainobject->course_ids})
            ORDER BY w.id
        ";

        $workshops = get_records_sql($sql);
        $this->assessments = $workshops;

    }



}

?>