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
 * User id ancestor filter for the assign module
 */
class block_ajax_marking_coursework_filter_userid_ancestor extends block_ajax_marking_query_decorator_base {

    /**
     * @static
     * @return mixed|void
     */
    protected function alter_query() {

        $clause = array(
            'type' => 'AND',
            'condition' => 'sub.userid = :assignuseridfilteruserid');
        $this->wrappedquery->add_where($clause);
        $this->wrappedquery->add_param('assignuseridfilteruserid', $this->parameter);
    }
}
