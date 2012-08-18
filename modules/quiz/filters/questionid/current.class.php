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
 * Class file for the quiz questionid current filter functions
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2008 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot.'/blocks/ajax_marking/filters/base.class.php');

/**
 * User id filter for the quiz module
 */
class block_ajax_marking_quiz_filter_questionid_current extends block_ajax_marking_query_decorator_base {

    /**
     * Makes a set of question nodes by grouping submissions by questionid.
     */
    protected function alter_query() {

        // Outer bit to get display name.
        $this->wrappedquery->add_from(array(
                              'join' => 'INNER JOIN',
                              'table' => 'question',
                              'on' => 'question.id = countwrapperquery.id'));
        $this->wrappedquery->add_select(array(
                                'table' => 'question',
                                'column' => 'name'));
        $this->wrappedquery->add_select(array(
                                'table' => 'question',
                                'column' => 'questiontext',
                                'alias' => 'tooltip'));

        // This is only needed to add the right callback function.
        $this->wrappedquery->add_select(array(
                                'column' => "'quiz'",
                                'alias' => 'modulename'));

        $this->wrappedquery->add_orderby("question.name ASC");
    }
}
