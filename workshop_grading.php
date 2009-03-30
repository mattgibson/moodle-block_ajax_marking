<?php

require_login(1, false);

class workshop_functions extends module_base {

    function workshop_functions(&$reference) {
        $this->mainobject = $reference;
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





}

?>