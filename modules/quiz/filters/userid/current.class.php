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
 * Class file for the quiz userid filter functions
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
class block_ajax_marking_quiz_filter_userid_current extends block_ajax_marking_query_decorator_base {

    /**
     * Makes a bunch of user nodes by grouping quiz submissions by the user id. The grouping is
     * automatic, but the text labels for the nodes are specified here.
     *
     * @static
     * @param block_ajax_marking_query $query
     */
    protected function alter_query(block_ajax_marking_query $query) {

        $query->add_select(array(
                                'table' => 'countwrapperquery',
                                'column' => 'timestamp',
                                'alias' => 'tooltip')
        );

        $query->add_select(array(
                                'table' => 'usertable',
                                'column' => 'firstname'));
        $query->add_select(array(
                                'table' => 'usertable',
                                'column' => 'lastname'));

        $query->add_from(array(
                              'join' => 'INNER JOIN',
                              'table' => 'user',
                              'alias' => 'usertable',
                              'on' => 'usertable.id = countwrapperquery.id'
                         ));

        // This is only needed to add the right callback function.
        $query->add_select(array(
                                'column' => "'quiz'",
                                'alias' => 'modulename'
                           ));
    }
}
