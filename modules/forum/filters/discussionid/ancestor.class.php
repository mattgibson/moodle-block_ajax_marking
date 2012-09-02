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
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2012 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot.'/blocks/ajax_marking/filters/base.class.php');

/**
 * Deals with SQL wrapper stuff for the discussion nodes.
 */
class block_ajax_marking_forum_filter_discussionid_ancestor extends block_ajax_marking_query_decorator_base {

    /**
     * Adds SQL for when there is a discussion node as an ancestor of the current nodes.
     */
    protected function alter_query() {

        $sql = 'discussion.id = :discussionidfilterdiscussionid';
        $this->wrappedquery->add_where($sql, array('discussionidfilterdiscussionid' => $this->get_parameter()));
    }
}
