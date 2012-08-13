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
 * This holds the class definition for the module query base class
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2012 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot.'/blocks/ajax_marking/classes/query_base.class.php');

/**
 * So that we can have specific things added for the module queries, especially the ability to store a string
 * specifying a particular column alias e.g. the one to use to get the userid, course, coursemodule, etc.
 */
class block_ajax_marking_module_query extends block_ajax_marking_query_base {

    /**
     * @var block_ajax_marking_module_base
     */
    protected $moduleclass;

    /**
     * @var string
     */
    protected $useridcolumn = 'sub.userid';

    /**
     * @var string
     */
    protected $courseidcolumn = 'moduletable.course';

    /**
     * @var string
     */
    protected $coursemoduleidcolumn;

    /**
     * @param block_ajax_marking_module_base $moduleclass
     */
    public function __construct($moduleclass) {
        $this->moduleclass = $moduleclass;
    }

    /**
     * Returns the table.column pair to access the userid. Best not to use the alias because you can't always use
     * that in every DB for every function.
     *
     * @return string table.column
     */
    public function get_userid_column() {
        return $this->useridcolumn;
    }

    /**
     * Sets the table.column pair used to access the user id. Defaults to sub.id.
     *
     * @param string $useridcolumn
     */
    public function set_userid_column($useridcolumn) {
        $this->useridcolumn = $useridcolumn;
    }

    /**
     * Returns the table.column pair to access the courseid. Best not to use the alias because you can't always use
     * that in every DB for every function.
     *
     * @return string table.column
     */
    public function get_courseid_column() {
        return $this->courseidcolumn;
    }

    /**
     * Sets the table.column pair used to access the courseid. Defaults to moduletable.course.
     *
     * @param string $courseidcolumn
     */
    public function set_courseid_column($courseidcolumn) {
        $this->courseidcolumn = $courseidcolumn;
    }

    /**
     * Returns the table.column pair to access the userid. Best not to use the alias because you can't always use
     * that in every DB for every function.
     *
     * @return string table.column
     */
    public function get_coursemoduleid_column() {
        return $this->coursemoduleidcolumn;
    }

    /**
     * Sets the table.column pair used to access the coursemoduleid.
     *
     * @param string $coursemoduleidcolumn
     * @throws coding_exception
     * @return void
     */
    public function set_coursemoduleid_column($coursemoduleidcolumn) {

        if (empty($this->coursemoduleidcolumn)) {
            throw new coding_exception('Course module id column left blank, but has no default, so must be set');
        }

        $this->coursemoduleidcolumn = $coursemoduleidcolumn;
    }

    /**
     * Tells us the name of the module that made this query e.g. 'assignment', 'forum'.
     *
     * @return string
     */
    public function get_modulename() {
        return $this->moduleclass->get_module_name();
    }

}
