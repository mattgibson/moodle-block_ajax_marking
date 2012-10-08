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
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2012 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Defines methods needed by the query objects and decorators
 */
interface block_ajax_marking_query {

    /**
     * Adds a column to the select. Needs a table, column, function (optional). If 'function' is
     * COALESCE, 'table' is an array of 'table' => 'column' pairs, which can include defaults as
     * strings or integers, which are added as they are, with no key specified.
     *
     * This one is complex.
     *
     * Issues:
     * - may need DISTINCT in there, possibly inside a count.
     * - Need to extract the column aliases for use in the GROUP BY later
     *
     * One column array can have:
     * - table
     * - column
     * - alias
     * - function e.g. COUNT
     * - distinct (bool)
     *
     * @param array $column containing: 'function', 'table', 'column', 'alias', 'distinct' in any
     * order
     * @param bool $prefix Do we want this at the start, rather than the end?
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @internal param bool $replace If true, the start or end element will be replaced with the incoming
     * one. Default: false
     * @return void
     */
    public function add_select(array $column, $prefix = false);

    /**
     * This will add a join table. No alias means it'll use the table name.
     *
     * @param array $table containing 'join', 'table', 'alias', 'on', 'subquery' (last one optional)
     * @throws coding_exception
     * @throws invalid_parameter_exception
     */
    public function add_from(array $table);

    /**
     * Adds a condition to the WHERE part of the query. Needs 'type' and 'condition' in the array.
     *
     * @abstract
     * @param string $sql
     * @param array $params
     * @return
     */
    public function add_where($sql, $params = array());

    /**
     * Adds a clause to the ORDER BY part of the query after joining together all the bits
     * of the orderby array.
     *
     * @abstract
     * @param string $column
     * @param bool $prefix
     */
    public function add_orderby($column, $prefix = false);

    /**
     * Adds an array of params.
     *
     * @abstract
     * @param array $params
     */
    public function add_params(array $params);

    /**
     * Adds one item to the params array. Always use SQL_PARAMS_NAMED.
     *
     * @param string $name
     * @param string $value
     */
    public function add_param($name, $value);

    /**
     * Runs the query using standard Moodle DB functions and returns the result.
     *
     * @abstract
     * @param bool $returnrecordset
     */
    public function execute($returnrecordset = false);

    /**
     * Returns the SQL with the placeholders in it ready for the Moodle DB functions.
     *
     * @abstract
     * @return string
     */
    public function get_sql();

    /**
     * Returns the params array ready for the Moodle DB functions.
     *
     * @abstract
     * @return array
     */
    public function get_params();

    /**
     * This is not used for output, but just converts the parametrised query to one that can be
     * copy/pasted into an SQL GUI in order to debug SQL errors
     *
     * @throws coding_exception
     * @global stdClass $CFG
     * @return string
     */
    public function debuggable_query();

    /**
     * Returns the SQL of a column that was previously stored. Allows decorators to attach stuff to different queries
     * that have the same stuff from different tables or aliases.
     *
     * @abstract
     * @param string $columnname
     * @return mixed
     */
    public function get_column($columnname);

    /**
     * Saves the SQL used to get a particular column, which other filters may need.
     *
     * @abstract
     * @param string $columnname
     * @param string $sql
     */
    public function set_column($columnname, $sql);

    /**
     * @abstract
     * @return string
     */
    public function get_module_name();

    /**
     * @abstract
     * @return int
     */
    public function get_module_id();

    /**
     * Sets the course limit status so that we know whether this query needs to have all appropriate tables limited
     * to courses that the user has access to. This makes the whole thing much faster for normal users. Only admins
     * who want to see all courses at once need this off.
     *
     * @abstract
     * @param bool $on
     */
    public function set_course_limit_status($on = true);

    /**
     * Tells us whether to apply the limit code that makes all the join tables have a WHERE courseid IN(x, y, z).
     * When on a very large site, this can be make a huge difference to performance. Only admins who want to
     * view everything need to have it turned off.
     *
     * @abstract
     * @return bool
     */
    public function get_course_limit_status();

    /**
     * Slightly awkward way of making sure we can add bits and pieces of SQL to unioned queries without
     * duplicating param names. If we just use the query_union class to add the same fragment to all of the bits,
     * @todo use an array iterator of all the queries or something instead.
     *
     * @abstract
     * @return mixed
     */
    public function get_number_of_unioned_subqueries();
}
