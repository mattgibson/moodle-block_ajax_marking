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
 * Class file for the union query that allows many queries to be joined together and still have the same
 * interface as others.
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
 * Special type of query that takes a bunch of other queries and holds them so they can be joined as a load
 * of union queries. Many operations don't make sense, so they throw exceptions.
 */
class block_ajax_marking_query_union extends block_ajax_marking_query_base {

    /**
     * @var block_ajax_marking_query[]
     */
    protected $queries = array();

    /**
     * @var string The bit of SQL that ties the queries together. Defaults to 'UNION ALL'
     */
    protected $unionstring = 'UNION ALL';

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
    public function add_select(array $column, $prefix = false) {
        foreach ($this->queries as $query) {
            $query->add_select($column, $prefix);
        }
    }

    /**
     * This will add a join table. No alias means it'll use the table name.
     *
     * @param array $table containing 'join', 'table', 'alias', 'on', 'subquery' (last one optional)
     * @throws coding_exception
     * @throws invalid_parameter_exception
     */
    public function add_from(array $table) {
        foreach ($this->queries as $query) {
            $query->add_from($table);
        }
    }

    /**
     * Adds a condition to the WHERE part of the query. Needs 'type' and 'condition' in the array.
     *
     * @param array $clause
     * @throws coding_exception
     * @return void
     */
    public function add_where(array $clause) {
        foreach ($this->queries as $query) {
            $query->add_where($clause);
        }
    }

    /**
     * Adds a clause to the ORDER BY part of the query after joining together all the bits
     * of the orderby array.
     *
     * @param string $column
     * @param bool $prefix
     */
    public function add_orderby($column, $prefix = false) {
        foreach ($this->queries as $query) {
            $query->add_orderby($column, $prefix);
        }
    }

    /**
     * Adds an array of params.
     *
     * @param array $params
     * @param bool $arraytoaddto
     * @throws coding_exception
     * @return array|bool|void
     */
    public function add_params(array $params, $arraytoaddto = false) {
        throw new coding_exception('Cannot add params to union query');
    }

    /**
     * Adds one item to the params array. Always use SQL_PARAMS_NAMED.
     *
     * @param string $name
     * @param string $value
     * @throws coding_exception
     * @return void
     */
    public function add_param($name, $value) {
        throw new coding_exception('Cannot add param to union query');
    }

    /**
     * Returns the SQL with the placeholders in it ready for the Moodle DB functions.
     *
     * @return string
     */
    public function get_sql() {

        $sql = array();

        foreach ($this->queries as $query) {
            $sql[] = $query->get_sql();
        }

        return implode(' UNION ALL ', $sql);
    }

    /**
     * Returns the params array ready for the Moodle DB functions.
     *
     * @return array
     */
    public function get_params() {

        $params = array();

        foreach ($this->queries as $query) {
            $params = array_merge($params, $query->get_params());
        }

        return $params;
    }

    /**
     * Returns the SQL of a column that was previously stored. Allows decorators to attach stuff to different queries
     * that have the same stuff from different tables or aliases.
     *
     * @param string $columnname
     * @throws coding_exception
     * @return mixed
     */
    public function get_column($columnname) {
        throw new coding_exception('Trying to get column from union query');
    }

    /**
     * Saves the SQL used to get a particular column, which other filters may need.
     *
     * @param string $columnname
     * @param string $sql
     * @throws coding_exception
     * @return void
     */
    public function set_column($columnname, $sql) {
        throw new coding_exception('Trying to set column from union query');
    }

    /**
     * Only makes sense if we have all the union queries as instances of the same module.
     *
     * @throws coding_exception
     * @return string
     */
    public function get_module_name() {

        if (count($this->queries) == 1) {
            /* @var block_ajax_marking_query $onlyquery */
            $onlyquery = reset($this->queries);
            return $onlyquery->get_module_name();
        } else {
            throw new coding_exception('Trying to get module name of a union query with <> 1 query.');
        }
    }

    /**
     * Only makes sense if we have all the union queries as instances of the same module.
     *
     * @throws coding_exception
     * @return int
     */
    public function get_module_id() {

        if (count($this->queries) == 1) {
            /* @var block_ajax_marking_query $onlyquery */
            $onlyquery = reset($this->queries);
            return $onlyquery->get_module_id();
        } else {
            throw new coding_exception('Trying to get module id of a union query with <> 1 query.');
        }
    }

    /**
     * Sets the course limit status so that we know whether this query needs to have all appropriate tables limited
     * to courses that the user has access to. This makes the whole thing much faster for normal users. Only admins
     * who want to see all courses at once need this off.
     *
     * @param bool $on
     * @throws coding_exception
     * @return void
     */
    public function set_course_limit_status($on = true) {
        throw new coding_exception('cannot set course status limit on a union query');
    }

    /**
     * Tells us whether to apply the limit code that makes all the join tables have a WHERE courseid IN(x, y, z).
     * When on a very large site, this can be make a huge difference to performance. Only admins who want to
     * view everything need to have it turned off.
     *
     * @throws coding_exception
     * @return bool
     */
    public function get_course_limit_status() {
        throw new coding_exception('cannot get course status limit on a union query');
    }

    /**
     * Add a fully parameterised query to the array.
     *
     * @param block_ajax_marking_query $query
     */
    public function add_query(block_ajax_marking_query $query) {
        $this->queries[] = $query;
    }

    /**
     * Set the SQL fragment used to join the queries together. the default is UNION ALL, only UNION is allowed apart
     * from this.
     *
     * @param $string
     * @throws coding_exception
     */
    public function set_union_string($string) {

        $allowedstrings = array(
            'UNION',
            'UNION ALL'
        );

        $string = strtoupper($string);

        if (in_array($string, $allowedstrings)) {
            $this->unionstring = $string;
        } else {
            throw new coding_exception('Not allowed this as a union string: '.$string);
        }
    }

}
