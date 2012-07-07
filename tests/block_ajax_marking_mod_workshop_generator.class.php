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
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2012 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot.'/lib/phpunit/classes/module_generator.php');

/**
 * Makes test data for the workshop module for use with phpunit tests.
 */
class block_ajax_marking_mod_workshop_generator extends phpunit_module_generator {

    /**
     * Create a test module
     * @param array|stdClass $record
     * @param array $options
     * @throws coding_exception
     * @return \stdClass activity record
     */
    public function create_instance($record = null, array $options = array()) {

        global $DB;

        // So we can give unique names, just in case.
        static $instancecount;

        if (!isset($record->course)) {
            throw new coding_exception('Need course to make new quiz instance via generator');
        }

        $prototypeworkshop = new stdClass();
        $prototypeworkshop->name = 'Workshop name '.$instancecount;
        $prototypeworkshop->intro = 'Standard fake intro';
        $prototypeworkshop->introformat = FORMAT_MOODLE;
        $prototypeworkshop->instructauthors = 'Instructions for authors';
        $prototypeworkshop->instructauthorsformat = FORMAT_MOODLE;
        $prototypeworkshop->instructreviewers = 'Instructions for reviewers';
        $prototypeworkshop->instructreviewersformat = FORMAT_MOODLE;
        $prototypeworkshop->timemodified = time();
        $prototypeworkshop->phase = workshop::PHASE_SUBMISSION;
        $prototypeworkshop->useexamples = 0;
        $prototypeworkshop->usepeerassessment = 1;
        $prototypeworkshop->useselfassessment = 0;
        $prototypeworkshop->grade = 97;
        $prototypeworkshop->gradinggrade = 87;
        $prototypeworkshop->strategy = 'accumulative';
        $prototypeworkshop->evaluation = 'best';
        $prototypeworkshop->gradedecimals = 0;
        $prototypeworkshop->nattachments = 0;
        $prototypeworkshop->latesubmissions = 0;
        $prototypeworkshop->maxbytes = 100000;
        $prototypeworkshop->examplesmode = 0;
        $prototypeworkshop->submissionstart = 0;
        $prototypeworkshop->submissionend = 0;
        $prototypeworkshop->assessmentstart = 0;
        $prototypeworkshop->assessmentend = 0;
        $prototypeworkshop->phaseswitchassessment = 0;

        $extended = (object)array_merge((array)$prototypeworkshop, (array)$record);

        $extended->coursemodule = $this->precreate_course_module($extended->course, $options);
        $extended->id = $DB->insert_record('workshop', $extended);
        $workshop = $this->post_add_instance($extended->id, $extended->coursemodule);

        // Now make assessment targets for accumulative strategy.
        $prototypeaspect = new stdClass();
        $prototypeaspect->workshopid = $workshop->id;
        $prototypeaspect->description = 'Description';
        $prototypeaspect->descriptionformat = FORMAT_MOODLE;
        $prototypeaspect->grade = 10;
        $prototypeaspect->weight = 1;

        for ($i = 1; $i <= 3; $i++) {
            $prototypeaspect->sort = $i;
            $DB->insert_record('workshopform_accumulative', $prototypeaspect);
        }

        return $workshop;
    }

    /**
     * Makes a single student submission for the supplied workshop.
     *
     * @param $student
     * @param $workshop
     * @return int Number of submissions we just made (1)
     */
    public function make_student_submission($student, $workshop) {

        global $DB;

        $submission = new stdClass();
        $submission->workshopid = $workshop->id;
        $submission->example = 0;
        $submission->authorid = $student->id;
        $submission->timecreated = time();
        $submission->timemodified = time();
        $submission->title = 'Submission title';
        $submission->content = 'Content text fo submission';
        $submission->contentformat = FORMAT_MOODLE;
        $submission->contenttrust = 0;
        $submission->attachment = 0;
        $submission->grade = null;
        $submission->gradeover = null;
        $submission->gradeoverby = null;
        $submission->feedbackauthor = null;
        $submission->feedbackauthorformat = FORMAT_MOODLE;
        $submission->timegraded = null;
        $submission->published = 0;
        $submission->late = 0;

        $DB->insert_record('workshop_submissions', $submission);

        return 1;

    }

    /**
     * Returns the name of the module that this generates things for. 'workshop in this case'.
     *
     * @return string
     */
    public function get_modulename() {
        return 'workshop';
    }
}
