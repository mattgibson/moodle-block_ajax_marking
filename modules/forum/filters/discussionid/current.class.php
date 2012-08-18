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
class block_ajax_marking_forum_filter_discussionid_current extends block_ajax_marking_query_decorator_base {

    /**
     * Adds SQL to construct a set of discussion nodes.
     */
    protected function alter_query() {

        $this->wrappedquery->add_select(array(
                                'table' => 'firstpost',
                                'column' => 'message',
                                'alias' => 'tooltip'
                           ));

        $this->wrappedquery->add_from(array(
                              'join' => 'INNER JOIN',
                              'table' => 'forum_discussions',
                              'alias' => 'outerdiscussions',
                              'on' => 'countwrapperquery.id = outerdiscussions.id'
                         ));

        $this->wrappedquery->add_from(array(
                              'join' => 'INNER JOIN',
                              'table' => 'forum_posts',
                              'alias' => 'firstpost',
                              'on' => 'firstpost.id = outerdiscussions.firstpost'
                         ));

        // This will be derived form the coursemodule id, but how to get it cleanly?
        // The query will know, but not easy to get it out. Might have been prefixed.
        // TODO pass this properly somehow. Might be possible to get it from the parameter.
        $coursemoduleid = required_param('coursemoduleid', PARAM_INT);
        // Normal forum needs discussion title as label, participant usernames as
        // description eachuser needs username as title and discussion subject as
        // description.
        if (block_ajax_marking_forum::forum_is_eachuser($coursemoduleid)) {

            // We want the each user forums to have the user names.
            $this->wrappedquery->add_select(array(
                                    'column' => 'outerusers.firstname',
                                    'alias' => 'firstname'
                               ));
            $this->wrappedquery->add_select(array(
                                    'column' => 'outerusers.lastname',
                                    'alias' => 'lastname'
                               ));
            $this->wrappedquery->add_from(array(
                                  'join' => 'INNER JOIN',
                                  'table' => 'user',
                                  'alias' => 'outerusers',
                                  'on' => 'firstpost.userid = outerusers.id'
                             ));
        } else {
            $this->wrappedquery->add_select(array(
                                    'table' => 'outerdiscussions',
                                    'column' => 'name',
                                    'alias' => 'name'
                               ));
        }

        $this->wrappedquery->add_orderby("timestamp ASC");
    }
}
