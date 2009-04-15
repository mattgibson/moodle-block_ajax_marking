<?php

require_login(1, false);

class journal_functions extends module_base {

    function journal_functions(&$reference) {
        $this->mainobject = $reference;
        // must be the same as the DB modulename
        $this->type = 'journal';
        // doesn't seem to be a journal capability :s
        $this->capability = 'mod/assignment:grade';
        // How many nodes in total when fully expanded?
        $this->levels = 2;
    }


     /**
      * gets all unmarked journal submissions from all courses ready for counting
      * called from get_main_level_data
      */
    function get_all_unmarked() {

        global $CFG;

        $sql = "
            SELECT je.id as entryid, je.userid, j.name, j.course, j.id, c.id as cmid
            FROM {$CFG->prefix}journal_entries je
            INNER JOIN {$CFG->prefix}journal j
                ON je.journal = j.id
            INNER JOIN {$CFG->prefix}course_modules c
                ON j.id = c.instance
            WHERE c.module = {$this->mainobject->module_ids['journal']->id}
            AND j.course IN ({$this->mainobject->course_ids})
            AND c.visible = 1
            AND j.assessed <> 0
            AND je.modified > je.timemarked
           ";

        $this->all_submissions = get_records_sql($sql);
        return true;
       // return $this->submissions;
    }

    function get_all_course_unmarked($courseid) {

        global $CFG;

        $sql = "
            SELECT je.id as entryid, je.userid, j.intro as description, j.name, j.timemodified, j.id, c.id as cmid
            FROM {$CFG->prefix}journal_entries je
            INNER JOIN {$CFG->prefix}journal j
               ON je.journal = j.id
            INNER JOIN {$CFG->prefix}course_modules c
               ON j.id = c.instance
            WHERE c.module = {$this->mainobject->module_ids['journal']->id}
            AND c.visible = 1
            AND j.assessed <> 0
            AND je.modified > je.timemarked
            AND je.userid IN({$this->mainobject->student_ids->$courseid})
            AND j.course = {$this->mainobject->id}
        ";

        $unmarked = get_records_sql($sql, 'journal');
        return $unmarked;

    }

    /**
     * gets all journals for all courses ready for the config tree
     */
    function get_all_gradable_items() {

        global $CFG;

        $sql = "
            SELECT j.id, j.intro as summary, j.name, j.course, c.id as cmid
            FROM  {$CFG->prefix}journal j
            INNER JOIN {$CFG->prefix}course_modules c
                     ON j.id = c.instance
            WHERE c.module = {$this->mainobject->module_ids['journal']->id}
            AND c.visible = 1
            AND j.assessed <> 0
            AND j.course IN ({$this->mainobject->course_ids})
        ";

        $journals = get_records_sql($sql);
        $this->assessments = $journals;
    }

}

?>