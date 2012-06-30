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

class block_ajax_marking_quiz_generator extends phpunit_module_generator {

    /**
     * Create a test module
     * @param array|stdClass $record
     * @param array $options
     * @throws coding_exception
     * @return \stdClass activity record
     */
    public function create_instance($record = null, array $options = null) {

        global $DB;

        // So we can give unique names, just in case.
        static $instancecount;

        if (!isset($record->course)) {
            throw new coding_exception('Need course to make new quiz instance via generator');
        }

        $prototypequiz = new stdClass();
        $prototypequiz->name = 'Quiz name '.$instancecount;
        $prototypequiz->intro = 'Standard fake intro';
        $prototypequiz->introformat = FORMAT_HTML;
        $prototypequiz->timeopen = 0;
        $prototypequiz->timeclose = 0;
        $prototypequiz->preferredbehaviour = 'deferredfeedback';
        $prototypequiz->attempts = 0;
        $prototypequiz->attemptonlast = 0;
        $prototypequiz->grademethod = 0;
        $prototypequiz->decimalpoints = 2;
        $prototypequiz->questiondecimalpoints = -1;
        $prototypequiz->reviewattempt = 69904;
        $prototypequiz->reviewcorrectness = 4368;
        $prototypequiz->reviewmarks = 4368;
        $prototypequiz->reviewspecificfeedback = 4368;
        $prototypequiz->reviewgeneralfeedback = 4368;
        $prototypequiz->reviewrightanswer = 4368;
        $prototypequiz->reviewoverallfeedback = 4368;
        $prototypequiz->questionsperpage = 1;
        $prototypequiz->navmethod = 'free';
        $prototypequiz->shufflequestion = 0;
        $prototypequiz->shuffleanswers = 1;
        $prototypequiz->questions = '';
        $prototypequiz->sumgrades = 0.00000;
        $prototypequiz->grade = 100.00000;
        $prototypequiz->timecreated = 0;
        $prototypequiz->timemodified = time();
        $prototypequiz->timelimit = 0;
        $prototypequiz->overduehandling = 'autoabandon';
        $prototypequiz->graceperiod = '';
        $prototypequiz->password = '';
        $prototypequiz->subnet = '';
        $prototypequiz->browsersecurity = '-';
        $prototypequiz->delay1 = 0;
        $prototypequiz->delay2 = 0;
        $prototypequiz->showuserpicture = 0;
        $prototypequiz->showblocks = 0;

        $extended = (object)array_merge((array)$prototypequiz, (array)$record);

        $extended->coursemodule = $this->precreate_course_module($prototypequiz->course, $options);
        $extended->id = $DB->insert_record('assignment_submissions', $extended);
        return $this->post_add_instance($extended->id, $extended->coursemodule);
    }
}
