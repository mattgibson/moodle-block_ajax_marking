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

require_once($CFG->dirroot.'/blocks/ajax_marking/classes/query.interface.php');

/**
 * Provides the base functions for the decorators that alter the query. We are combining the
 * template and decorator patterns, so to avoid the template functions of the wrapped class being defeated
 * by using $this and then not getting any of the decorator stuff, all of the wrappers are being given all
 * of the template functions
 */
abstract class block_ajax_marking_filter_base implements block_ajax_marking_query {

    /**
     * @var block_ajax_marking_query The wrapped object that this decorator operates on.
     */
    protected $wrappedquery;

    /**
     * Constructor assigns the wrapped object ot the member variable.
     *
     * @param block_ajax_marking_query $query
     */
    public function __construct(block_ajax_marking_query $query) {
        $this->wrappedquery = $query;
    }

    /**
     * @param array $column
     * @param bool $prefix
     * @return void
     */
    public function add_select(array $column, $prefix = false) {
        $this->wrappedquery->add_select($column, $prefix);
    }

    /**
     * @param array $table containing 'join', 'table', 'alias', 'on', 'subquery' (last one optional)
     * @throws coding_exception
     * @throws invalid_parameter_exception
     */
    public function add_from(array $table) {
        $this->wrappedquery->add_from($table);
    }

    /**
     * @param array $clause
     * @return string|void
     */
    public function add_where(array $clause) {
        $this->wrappedquery->add_where($clause);
    }

    /**
     * @param string $column
     * @param bool $prefix
     * @return string|void
     */
    public function add_orderby($column, $prefix = false) {
        $this->wrappedquery->add_orderby($column, $prefix);
    }

    /**
     * Adds an array of params.
     *
     * @abstract
     * @param array $params
     * @param bool $arraytoaddto
     */
    public function add_params(array $params, $arraytoaddto = false) {
        $this->wrappedquery->add_params($params, $arraytoaddto);
    }

    /**
     * Adds one item to the params array. Always use SQL_PARAMS_NAMED.
     *
     * @param string $name
     * @param string $value
     */
    public function add_param($name, $value) {
        $this->wrappedquery->add_param($name, $value);
    }

    /**
     * Runs the query using standard Moodle DB functions and returns the result.
     *
     * @abstract
     * @param bool $returnrecordset
     */
    public function execute($returnrecordset = false) {
        return $this->wrappedquery->execute($returnrecordset);
    }

    /**
     * Returns the SQL with the placeholders in it ready for the Moodle DB functions.
     *
     * @abstract
     * @return string
     */
    public function get_sql() {
        return $this->wrappedquery->get_sql();
    }

    /**
     * Returns the params array ready for the Moodle DB functions.
     *
     * @abstract
     * @return array
     */
    public function get_params() {
        return $this->wrappedquery->get_params();
    }

}
