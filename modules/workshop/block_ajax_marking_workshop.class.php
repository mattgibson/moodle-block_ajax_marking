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

global $CFG;
require_once($CFG->dirroot.'/blocks/ajax_marking/classes/query_base.class.php');
require_once($CFG->dirroot.'/mod/workshop/locallib.php'); // For constants.
require_once($CFG->dirroot.'/blocks/ajax_marking/classes/module_base.class.php');

/**
 * Provides marking functionality for the workshop module
 *
 * @copyright 2008-2010 Matt Gibson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_ajax_marking_workshop extends block_ajax_marking_module_base {

    /**
     * Constructor
     *
     * @return \block_ajax_marking_workshop
     */
    public function __construct() {

        // Call parent constructor with the same arguments.
        parent::__construct();

        $this->modulename           = 'workshop';
        $this->capability           = 'mod/workshop:editdimensions';
        $this->icon                 = 'mod/workshop/icon.gif';

    }

    /**
     * Returns a query object with the basics all set up to get assignment stuff
     *
     * @global moodle_database $DB
     * @return block_ajax_marking_query_base
     */
    public function query_factory() {

        global $USER;

        $query = new block_ajax_marking_query_base($this);
        $query->set_column('userid', 'sub.authorid');

        $query->add_from(array(
                'table' => $this->modulename,
                'alias' => 'moduletable',
        ));
        $query->set_column('courseid', 'moduletable.course');
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

        // Standard userid for joins.
        $query->add_select(array('table' => 'sub',
                                 'column' => 'authorid',
                                 'alias' => 'userid'));
        $query->add_select(array('table' => 'sub',
                                'column' => 'timemodified',
                                'alias'  => 'timestamp'));

        // Assumes that we want to see stuff that has not been assessed by the current user yet. Perhaps
        // we have more than one assessor? Perhaps it's peer assessment only?
        $query->add_where('NOT EXISTS(
                                   SELECT 1
                                     FROM {workshop_assessments} workshop_assessments
                                    WHERE workshop_assessments.submissionid = sub.id
                                      AND workshop_assessments.reviewerid = :workshopuserid
                                      AND workshop_assessments.grade != -1
                               )');
        $query->add_where('moduletable.phase < '.workshop::PHASE_CLOSED);

        // Do we want to only see stuff when the workshop has been put into a later phase?
        // If it has, a teacher will have done this manually and will know about the grading work.
        // Unless there are two teachers.

        $query->add_param('workshopuserid', $USER->id);

        return $query;

    }


    /**
     * Makes the grading interface for the pop up
     *
     * @param array $params From $_GET
     * @param $coursemodule
     * @global $PAGE
     * @global $CFG
     * @global moodle_database $DB
     * @global $OUTPUT
     * @global $USER
     *
     * @return string|void
     */
    public function grading_popup(array $params, $coursemodule) {

        $workshopurl = new moodle_url('/mod/workshop/view.php?id='.$coursemodule->id);
        redirect($workshopurl);
    }

    /**
     * This function will take the data returned by the grading popup and process it. Not always
     * implemented as not all modules have a grading popup yet
     *
     * @param $data
     * @param $params
     * @return string
     */
    public function process_data($data, $params) {
        return '';
    }
}
