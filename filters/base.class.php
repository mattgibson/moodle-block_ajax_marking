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
abstract class block_ajax_marking_query_decorator_base implements block_ajax_marking_query {

    /**
     * @var block_ajax_marking_query The wrapped object that this decorator operates on.
     */
    protected $wrappedquery;

    /**
     * @var mixed optional e.g. course id
     */
    protected $parameter;

    /**
     * Constructor assigns the wrapped object ot the member variable.
     *
     * @param block_ajax_marking_query $query
     * @param bool $parameter
     */
    public function __construct(block_ajax_marking_query $query, $parameter = false) {
        $this->wrappedquery = $query;
        $this->parameter = $parameter;
        $this->alter_query();
    }

    /**
     * Getter that throws an exception if the parameter is missing.
     *
     * @return bool|mixed
     * @throws coding_exception
     */
    protected function get_parameter() {
        if (!isset($this->parameter)) {
            throw new coding_exception('Trying to get a parameter, but there isn\'t one.');
        }
        return $this->parameter;
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
     * @param array $params
     * @param string $sql Always added using AND
     * @return string|void
     */
    public function add_where($sql, $params = array()) {
        $this->wrappedquery->add_where($sql, $params);
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
     */
    public function add_params(array $params) {
        $this->wrappedquery->add_params($params);
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
     * @return array|\moodle_recordset
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

    /**
     * This is not used for output, but just converts the parametrised query to one that can be
     * copy/pasted into an SQL GUI in order to debug SQL errors
     *
     * @throws coding_exception
     * @global stdClass $CFG
     * @return string
     */
    public function debuggable_query() {
        return $this->wrappedquery->debuggable_query();
    }

    /**
     * Returns the SQL of a column that was previously stored. Allows decorators to attach stuff to different queries
     * that have the same stuff from different tables or aliases.
     *
     * @param string $columnname
     * @return mixed
     */
    public function get_column($columnname) {
        return $this->wrappedquery->get_column($columnname);
    }

    /**
     * Saves the SQL used to get a particular column, which other filters may need.
     *
     * @abstract
     * @param string $columnname
     * @param string $sql
     */
    public function set_column($columnname, $sql) {
         $this->wrappedquery->set_column($columnname, $sql);
    }

    /**
     * Gets the name of whatever module may be there.
     *
     * @return string
     */
    public function get_module_name() {
        return $this->wrappedquery->get_module_name();
    }

    /**
     * Gets the DB id of the associated module from the module table.
     *
     * @return int
     */
    public function get_module_id() {
        return $this->wrappedquery->get_module_id();
    }

    /**
     * Does the actual work of the decorator.
     *
     * @abstract
     * @return mixed
     */
    abstract protected function alter_query();

    /**
     * Sets the course limit status so that we know whether this query needs to have all appropriate tables limited
     * to courses that the user has access to. This makes the whole thing much faster for normal users. Only admins
     * who want to see all courses at once need this off.
     *
     * @abstract
     * @param bool $on
     */
    public function set_course_limit_status($on = true) {
        $this->wrappedquery->set_course_limit_status($on);
    }

    /**
     * Tells us whether to apply the limit code that makes all the join tables have a WHERE courseid IN(x, y, z).
     * When on a very large site, this can be make a huge difference to performance. Only admins who want to
     * view everything need to have it turned off.
     *
     * @abstract
     * @return bool
     */
    public function get_course_limit_status() {
        return $this->wrappedquery->get_course_limit_status();
    }

    /**
     * Slightly awkward way of making sure we can add bits and pieces of SQL to unioned queries without
     * duplicating param names. If we just use the query_union class to add the same fragment to all of the bits,
     * @todo use an array iterator of all the queries or something instead.
     *
     * @return mixed
     */
    public function get_number_of_unioned_subqueries() {
        return $this->wrappedquery->get_number_of_unioned_subqueries();
    }
}
