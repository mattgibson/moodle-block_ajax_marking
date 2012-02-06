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
 * Class file for the journal grading functions
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2008 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die();
}

global $CFG;
require_once($CFG->dirroot.'/blocks/ajax_marking/classes/query_base.class.php');

/**
 * Provides marking functions for the journal module
 *
 * @copyright 2008-2010 Matt Gibson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_ajax_marking_journal extends block_ajax_marking_module_base {

    /**
     * Constructor
     *
     * @internal param object $reference the parent object, passed in so it's functions can be
     * referenced
     * @return \block_ajax_marking_journal
     */
    public function __construct() {

        // TODO why did the IDE put that $reference bit into the docblock?

        // must be the same as the DB modulename
        $this->modulename        = $this->moduletable = 'journal';
        // doesn't seem to be a journal capability :s
        $this->capability  = 'mod/assignment:grade';
        // How many nodes in total when fully expanded (no groups)?
        // function to trigger for the third level nodes (might be different if there are four
        $this->icon        = 'mod/journal/icon.gif';

        // call parent constructor with the same arguments
        call_user_func_array(array($this, 'parent::__construct'), func_get_args());
    }


    /**
     * this will never actually lead to submissions, but will only be called if there are group
     * nodes to show.
     *
     * @param $journalid
     * @return void
     */
    public function submissions($journalid) {

        global $USER, $DB;
        // need to get course id in order to retrieve students
        $journal = $DB->get_record('journal', array('id' => $journalid));
        $courseid = $journal->course;

        $coursemodule = $DB->get_record('course_modules', array('module' => '1',
                                                               'instance' => $journalid));
        $modulecontext = get_context_instance(CONTEXT_MODULE, $coursemodule->id);

        if (!has_capability($this->capability, $modulecontext, $USER->id)) {
            return;
        }

        $context = get_context_instance(CONTEXT_COURSE, $courseid);

        list($studentsql, $params) = $this->get_sql_role_users($context);

        $sql = 'SELECT je.id as entryid, je.userid, j.intro as description, j.name, j.timemodified,
                       u.firstname, u.lastname, j.id, c.id as cmid
                  FROM {journal_entries} je
            INNER JOIN {user} u
                    ON s.userid = u.id
            INNER JOIN {journal} j
                    ON je.journal = j.id
            INNER JOIN {course_modules} course_modules
                    ON j.id = course_modules.instance
            INNER JOIN ({$studentsql}) stsql
                    ON je.userid = stsql.id
                 WHERE course_modules.module = :'.$this->prefix_param_name('moduleid')."
                   AND course_modules.visible = 1
                   AND j.assessed <> 0
                   AND je.modified > je.timemarked
                   AND je.userid '.'$usql.''
                   AND j.id = :".$this->prefix_param_name('journalid');
        $params['moduleid'] = $this->get_module_id();
        $params['journalid'] = $journal->id;
        $submissions = $DB->get_records_sql($sql, $params);

        // TODO: does this work with 'journal' rather than 'journal_final'?

        return;
    }

    /**
     * Makes a HTML link for the popup
     *
     * @param object $item a journal object with cmid property
     * @return string
     */
    public function make_html_link($item) {

        global $CFG;

        $address = $CFG->wwwroot.'/mod/journal/report.php?id='.$item->cmid;
        return $address;
    }

    /**
     * Returns a query object with the basics all set up to get assignment stuff
     *
     * @return block_ajax_marking_query_base
     */
    public function query_factory() {

        $query = new block_ajax_marking_query_base($this);

        $query->add_from(array(
                'table' => $this->modulename,
                'alias' => 'moduletable',
        ));

        $query->add_from(array(
                'join' => 'INNER JOIN',
                'table' => 'journal_entries',
                'alias' => 'sub',
                'on' => 'sub.journal = moduletable.id'
        ));
        // Standard userid for joins
        $query->add_select(array('table' => 'sub',
                                 'column' => 'userid'));

        $query->add_where(array('type' => 'AND', 'condition' => 'moduletable.assessed <> 0'));
        $query->add_where(array('type' => 'AND', 'condition' => 'sub.modified > sub.timemarked'));

        return $query;
    }

}
