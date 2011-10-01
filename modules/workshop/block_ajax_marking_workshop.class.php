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
 * Class file for the workshop grading functions
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

require_once($CFG->dirroot.'/blocks/ajax_marking/classes/query_base.class.php');
require_once($CFG->dirroot.'/mod/workshop/locallib.php'); // for constants

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
     * @internal param object $mainobject the parent object passed in by reference
     * @return \block_ajax_marking_workshop
     */
    public function __construct() {

        // call parent constructor with the same arguments
        parent::__construct();

        $this->modulename           = $this->moduletable = 'workshop';
        $this->capability           = 'mod/workshop:editdimensions';
        $this->icon                 = 'mod/workshop/icon.gif';

    }

    /**
     * Makes the HTML link for the popup
     *
     * @param object $item that has the workshop's courseid as cmid property
     * @return string
     */
    public function make_html_link($item) {

        global $CFG;
        $address = $CFG->wwwroot.'/mod/workshop/view.php?id='.$item->cmid;
        return $address;
    }

    /**
     * Returns the column from the workshop_submissions table that has the userid in it
     *
     * @return string
     */
    protected function get_sql_userid_column() {
        return 'sub.authorid';
    }

    /**
     * Returns a query object with the basics all set up to get assignment stuff
     *
     * @param bool $callback
     * @global type $DB
     * @return block_ajax_marking_query_base
     */
    public function query_factory($callback = false) {

        global $USER;

        $query = new block_ajax_marking_query_base($this);
        $query->set_userid_column('sub.authorid');

        $query->add_from(array(
                'table' => $this->modulename,
                'alias' => 'moduletable',
        ));
        $query->add_from(array(
                'join' => 'INNER JOIN',
                'table' => 'workshop_submissions',
                'alias' => 'sub',
                'on' => 'sub.workshopid = moduletable.id'
        ));
        $query->add_from(array(
                'join' => 'LEFT JOIN',
                'table' => 'workshop_assessments',
                'alias' => 'a',
                'on' => 'sub.id = a.submissionid'
        ));

        // Assumes that we want to see stuff that has not been assessed yet. Perhaps we still want
        // this but also ones where we have not reviewed the assessments?
        $query->add_where(array(
                'type' => 'AND',
                'condition' => '(a.reviewerid != :'.$query->prefix_param('userid').'
                                   OR (a.reviewerid = :'.$query->prefix_param('userid2').'
                                       AND a.grade = -1))'));
        $query->add_where(array(
            'type' => 'AND',
            'condition' => 'moduletable.phase < '.workshop::PHASE_CLOSED
            ));

        // Do we want to only see stuff when the workshop has been put into a later phase?
        // If it has, a teacher will have done this manually and will know about the grading work.
        // Unless there are two teachers.

        $query->add_param('userid', $USER->id);
        $query->add_param('userid2', $USER->id);

        return $query;

    }


    /**
     * Makes the grading interface for the pop up
     *
     * @param array $params From $_GET
     * @param $coursemodule
     * @global type $PAGE
     * @global type $CFG
     * @global type $DB
     * @global type $OUTPUT
     * @global type $USER
     *
     */
    public function grading_popup($params, $coursemodule) {

        $workshopurl = new moodle_url('/mod/workshop/view.php?id='.$coursemodule->id);
        redirect($workshopurl);

    }

    /**
     * Applies the module-specifi stuff for the user nodes
     *
     * @param block_ajax_marking_query_base $query
     * @param int|\type $userid
     * @param bool $outerquery
     * @return void
     */
    public function apply_userid_filter(block_ajax_marking_query_base $query, $userid = 0,
                                        $outerquery = false) {

        if ($userid) {
            // Applies if users are not the final nodes
            $query->add_where(array(
                    'type' => 'AND',
                    'condition' => 'sub.authorid = :'.$query->prefix_param('submissionid')));
            $query->add_param('submissionid', $userid);
            return;
        }

        if ($outerquery) {

            $selects = array(

                array(
                    'table'    => 'usertable',
                    'column'   => 'firstname'),
                array(
                    'table'    => 'usertable',
                    'column'   => 'lastname')
            );

            foreach ($selects as $select) {
                $query->add_select($select);
            }

            $query->add_from(array(
                    'join'  => 'INNER JOIN',
                    'table' => 'user',
                    'alias' => 'usertable',
                    'on'    => 'usertable.id = combinedmodulesubquery.id'
            ));

        } else {
            $selects = array(
                array(
                    'table'    => 'sub',
                    'column'   => 'authorid'),
                array( // Count in case we have user as something other than the last node
                    'function' => 'COUNT',
                    'table'    => 'sub',
                    'column'   => 'id',
                    'alias'    => 'count'),
                // This is only needed to add the right callback function.
                array(
                    'column' => "'".$query->get_modulename()."'",
                    'alias' => 'modulename'
                    ));
            foreach ($selects as $select) {
                $query->add_select($select);
            }
        }
    }

}