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

/**
 * Provides the base functions for the decorators that alter the query. We are combining the
 * template and decorator patterns, so to avoid the template functions of the wrapped class being defeated
 * by using $this and then not getting any of the decorator stuff, all of the wrappers are being given all
 * of the template functions
 */
class block_ajax_marking_filter_base extends block_ajax_marking_query_base {

    /**
     * @var block_ajax_marking_query_base The wrapped object that this decorator operates on.
     */
    protected $wrappedquery;

    /**
     * Constructor assigns the wrapped object ot the member variable.
     */
    public function __construct(block_ajax_marking_query_base $query) {
        $this->wrappedquery = $query;

    }

//    /**
//     * Fetches the subquery from within the main query. Assumes that we have the outer displayquery
//     * wrapped around it already.
//     *
//     * @param block_ajax_marking_query_base $query
//     * @return block_ajax_marking_query_base
//     */
//    protected static function get_countwrapper_subquery(block_ajax_marking_query_base $query) {
//        return $query->get_subquery('countwrapperquery');
//    }
//
//    /**
//     * Fetches the subquery from within the main query. Assumes that we have the outer displayquery
//     * and middle-level countwrapper query wrapped around it already.
//     *
//     * @param block_ajax_marking_query_base $query
//     * @return block_ajax_marking_query_base
//     */
//    protected static function get_moduleunion_subquery(block_ajax_marking_query_base $query) {
//        $coutwrapper = self::get_countwrapper_subquery($query);
//        return $coutwrapper->get_subquery('moduleunion');
//    }

    /**
     * @return string|void
     */
    public function get_select() {
        return $this->wrappedquery->get_select();
    }

    /**
     * @return string|void
     */
    public function get_from() {
        return $this->wrappedquery->get_from();
    }

    /**
     * @return string|void
     */
    public function get_where() {
        return $this->wrappedquery->get_where();
    }

    /**
     * @return string|void
     */
    public function get_orderby() {
        return $this->wrappedquery->get_orderby();
    }

    /**
     * @return string|void
     */
    public function get_groupby() {
        return $this->wrappedquery->get_groupby();
    }

}
