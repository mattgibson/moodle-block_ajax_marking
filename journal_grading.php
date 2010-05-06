<?php

require_login(0, false);

class journal_functions extends module_base {

    function journal_functions(&$reference) {
        $this->mainobject = $reference;
        // must be the same as the DB modulename
        $this->type = 'journal';
        // doesn't seem to be a journal capability :s
        $this->capability = 'mod/assignment:grade';
        // How many nodes in total when fully expanded (no groups)?
        $this->levels = 2;
        // function to trigger for the third level nodes (might be different if there are four
        //$this->level2_return_function = 'journal_submissions';
        $this->icon = 'mod/journal/icon.gif';
        $this->functions  = array(
            'journal' => 'submissions'
        );

    }

     /**
      * gets all unmarked journal submissions from all courses ready for counting
      * called from get_main_level_data
      */
    function get_all_unmarked() {

        global $CFG, $DB;
        list($usql, $params) = $DB->get_in_or_equal($this->mainobject->courseids, SQL_PARAMS_NAMED);
        $sql = "SELECT je.id as entryid, je.userid, j.name, j.course, j.id, c.id as cmid
                  FROM {journal_entries} je
            INNER JOIN {journal} j
                    ON je.journal = j.id
            INNER JOIN {course_modules} c
                    ON j.id = c.instance
                 WHERE c.module = :moduleid
                   AND j.course $usql
                   AND c.visible = 1
                   AND j.assessed <> 0
                   AND je.modified > je.timemarked";
        $params['moduleid'] = $this->mainobject->modulesettings['journal']->id;
        $this->all_submissions = $DB->get_records_sql($sql, $params);
        return true;
    }

    function get_all_course_unmarked($courseid) {

        global $CFG, $DB;
        list($usql, $params) = $DB->get_in_or_equal($this->mainobject->students->ids->$courseid, SQL_PARAMS_NAMED);

        $sql = "SELECT je.id as entryid, je.userid, j.intro as description, j.course, j.name,
                       j.timemodified, j.id, c.id as cmid
                  FROM {journal_entries} je
            INNER JOIN {journal} j
                    ON je.journal = j.id
            INNER JOIN {course_modules} c
                    ON j.id = c.instance
                 WHERE c.module = :moduleid
                   AND c.visible = 1
                   AND j.assessed <> 0
                   AND je.modified > je.timemarked
                   AND je.userid $usql
                   AND j.course = :courseid";
        $params['moduleid'] = $this->mainobject->modulesettings['journal']->id;
        $params['courseid'] = $courseid;

        $unmarked = $DB->get_records_sql($sql, $params);
        return $unmarked;
    }

    /**
     * gets all journals for all courses ready for the config tree
     */
    function get_all_gradable_items() {

        global $CFG, $DB;

        list($usql, $params) = $DB->get_in_or_equal($this->mainobject->courseids, SQL_PARAMS_NAMED);

        $sql = "SELECT j.id, j.intro as summary, j.name, j.course, c.id as cmid
                  FROM {journal} j
            INNER JOIN {course_modules} c
                    ON j.id = c.instance
                 WHERE c.module = :moduleid
                   AND c.visible = 1
                   AND j.assessed <> 0
                   AND j.course $usql";
        $params['moduleid'] = $this->mainobject->modulesettings['journal']->id;

        $journals = $DB->get_records_sql($sql, $params);
        $this->assessments = $journals;
    }

    /**
     * this will never actually lead to submissions, but will only be called if there are group
     * nodes to show.
     */
    function submissions() {

        global $USER, $CFG, $DB;
        // need to get course id in order to retrieve students
        $journal = $DB->get_record('journal', array('id' => $this->mainobject->id));
        $courseid = $journal->course;

        $coursemodule = $DB->get_record('course_modules', array('module' => '1', 'instance' => $journal->id));
        $modulecontext = get_context_instance(CONTEXT_MODULE, $coursemodule->id);

        if (!has_capability($this->capability, $modulecontext, $USER->id)) {
            return;
        }

        $this->mainobject->get_course_students($courseid);
        list($usql, $params) = $DB->get_in_or_equal($this->mainobject->students->ids->$courseid, SQL_PARAMS_NAMED);

        $sql = "SELECT je.id as entryid, je.userid, j.intro as description, j.name, j.timemodified,
                       j.id, c.id as cmid
                  FROM {journal_entries} je
            INNER JOIN {journal} j
                    ON je.journal = j.id
            INNER JOIN {course_modules} c
                    ON j.id = c.instance
                 WHERE c.module = :moduleid
                   AND c.visible = 1
                   AND j.assessed <> 0
                   AND je.modified > je.timemarked
                   AND je.userid $usql
                   AND j.id = :journalid";
        $params['moduleid'] = $this->mainobject->modulesettings['journal']->id;
        $params['journalid'] = $journal->id;
        $submissions = $DB->get_records_sql($sql, $params);

        // TODO: does this work with 'journal' rather than 'journal_final'?

        // This function does not need any checks for group status as it will only be called if groups are set.
        $group_filter = $this->mainobject->assessment_groups_filter($submissions, 'journal', $journal->id, $journal->course);

        // group nodes have now been printed by the groups function
        return;
    }

    /**
     * MAkes a HTML link for the popup
     *
     * @param object $item a journal object with cmid property
     * @return string
     */
    function make_html_link($item) {

        global $CFG;
        $address = $CFG->wwwroot.'/mod/journal/report.php?id='.$item->cmid;
        return $address;
    }

}