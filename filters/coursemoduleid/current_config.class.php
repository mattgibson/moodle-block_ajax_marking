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
 * These classes provide filters that modify the dynamic query that fetches the nodes. Depending on
 * what node is being requested and what that node's ancestor nodes are, a different combination
 * of filters will be applied. There is one class per type of node, and one method with the class
 * for the type of operation. If there is a courseid node as an ancestor, we want to use the
 * courseid::where_filter, but if we are asking for courseid nodes, we want the
 * courseid::count_select filter.
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2012 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot.'/blocks/ajax_marking/filters/coursemoduleid/current.class.php');

/**
 * Holds the filters to deal with coursemoduleid nodes.
 */
class block_ajax_marking_filter_coursemoduleid_current_config extends block_ajax_marking_filter_coursemoduleid_current {

    /**
     * Used when we are looking at the coursemodule nodes in the config tree
     * Awkwardly, the course_module table doesn't hold the name and description of the
     * module instances, so we need to join to the module tables. This will cause a mess
     * unless we specify that only course modules with a specific module id should join
     * to a specific module table.
     *
     */
    protected function alter_query() {

        global $USER;

        // We need the same details as the main tree, but just need to tack on the settings as well.
        $this->add_coursemodule_details($this->wrappedquery);

        // We need the config settings too, if there are any.
        // TODO should be in a separate decorator.
        $this->wrappedquery->add_from(array(
                              'join' => 'LEFT JOIN',
                              'table' => 'block_ajax_marking',
                              'alias' => 'settings',
                              'on' => "settings.instanceid = {$this->wrappedquery->get_column('coursemoduleid')}
                                    AND settings.tablename = 'course_modules'
                                    AND settings.userid = :settingsuserid"
                         ));
        $this->wrappedquery->add_param('settingsuserid', $USER->id);
        $this->wrappedquery->add_select(array(
                                'table' => 'settings',
                                'column' => 'display'));
        $this->wrappedquery->add_select(array(
                                'table' => 'settings',
                                'column' => 'groupsdisplay'));
        $this->wrappedquery->add_select(array(
                                'table' => 'settings',
                                'column' => 'id',
                                'alias' => 'settingsid'));

        $this->wrappedquery->add_from(
            array(
                 'join' => 'INNER JOIN',
                 'table' => 'modules',
                 'on' => 'modules.id = course_modules.module'
            )
        );

        // The javascript needs this for styling.
        $this->wrappedquery->add_select(
            array(
                 'table' => 'modules',
                 'column' => 'name',
                 'alias' => 'modulename'));

        // TODO get them in order of module type first?
    }
}

