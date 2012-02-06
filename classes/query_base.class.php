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
 * This holds the class definition for the block_ajax_marking_query_base class
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2011 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Defines methods needed by the query objects and it's decorators
 */
interface block_ajax_marking_query {

    public function get_select();

    public function get_from();

    public function get_where();

    public function get_orderby();

    public function get_params();

    public function execute();

}

/**
 * Base class for core queries, allowing the parameters and various other filters to be added
 * dynamically
 */
class block_ajax_marking_query_base {

    /**
     * This hold arrays, with each one being a table column. Each array needs 'function', 'table',
     * 'column', 'alias', 'distinct'. Each array is keyed by its column alias or name, if there is
     * no alias. Its important that these are in the right order as the GROUP BY will be generated
     * from them
     *
     * @var array
     */
    private $select = array();

    /**
     * An array of arrays. Each needs 'join', 'table', 'on'
     * @var array
     */
    private $from = array();

    /**
     * Just a straight array of clauses, no matter how big, each one capable of being tacked on with
     * an AND.
     *
     * @var array
     */
    private $where = array();

    /**
     * Array of column aliases
     * @var array
     */
    private $orderby = array();

    /**
     * Associative array of parameters using SQL_PARAM_NAMED format
     * @var array
     */
    private $params = array();

    /**
     * Holds the name of the useridcolumn of the submissions table. Needed as it varies across
     * different modules
     *
     * @var string
     */
    private $useridcolumn;

    /**
     * Hold the the module type object that this query came from.
     *
     * @var block_ajax_marking_module_base
     */
    private $moduleobject;

    /**
     * Constructor
     *
     * @param \block_ajax_marking_module_base|bool $moduleobject
     * @return \block_ajax_marking_query_base
     */
    public function __construct($moduleobject = false) {
        $this->moduleobject = $moduleobject;
    }

    /**
     * Crunches the SELECT array into a valid SQL query string. Each has 'function', 'table',
     * 'column', 'alias', 'distinct'
     *
     * wrapper via UNION?
     * @return string SQL
     */
    public function get_select() {

        $selectarray = array();

        foreach ($this->select as $select) {
            $selectstring = '';
            // 'column' will not be set if this is COALESCE. We have an associative array of
            // table=>column pairs
            if (isset($select['function']) && strtoupper($select['function']) === 'COALESCE') {
                $tablesarray = array();
                foreach ($select['table'] as $tab => $col) {
                    $tablesarray[] = $tab.'.'.$col;
                }
                $selectstring .= implode(', ', $tablesarray);
            } else {
                $selectstring = isset($select['column']) ? $select['column'] : '';
                if (isset($select['table'])) {
                    $selectstring = $select['table'].'.'.$selectstring;
                }
            }
            if (isset($select['distinct'])) {
                $selectstring = 'DISTINCT '.$selectstring;
            }
            if (isset($select['function'])) {
                $selectstring = ' '.$select['function'].'('.$selectstring.') ';
            }
            if (isset($select['alias'])) {
                $selectstring .= ' AS '.$select['alias'];
            }

            $selectarray[] = $selectstring;

        }

        return 'SELECT '.implode(", \n", $selectarray).' ';
    }

    /**
     * Some modules will need to remove part of the standard SELECT array to make the query work
     * e.g. Forum needs to remove userid from the submissions query to make it do GROUP BY properly
     *
     * @param string $alias The alias or tablename that was used to key this SELECT statement to
     * the array
     */
    public function remove_select($alias) {
        unset($this->select[$alias]);
    }

    /**
     * Returns the SQL fragment for the join tables
     *
     * @return string SQL
     */
    protected function get_from() {

        $fromarray = array();

        foreach ($this->from as $from) {

            // allow shorthand
            if (isset($from['join'])) {
                $from['join'] = ($from['join'] == 'left') ? 'LEFT JOIN' : $from['join'];
            }

            if ($from['table'] instanceof block_ajax_marking_query_base) { //allow for recursion
                $fromstring = '('.$from['table']->to_string().')';

            } else if (isset($from['subquery'])) {

                if (isset($from['union'])) {
                    if (!is_array($from['table'])) {
                        $error = 'Union subqueries must have an array supplied as their table item';
                        throw new coding_exception($error);
                    }
                    $this->validate_union_array($from['table']);
                    $unionarray = array();
                    /**
                     * @var block_ajax_marking_query_base $table
                     */
                    foreach ($from['table'] as $table) {
                        $unionarray[] = $table->to_string();
                    }
                    $fromstring = '(';
                    $fromstring .= implode("\n\n UNION ALL \n\n", $unionarray);
                    $fromstring .= ")";

                } else {
                    $fromstring = '('.$from['table'].")\n";
                }
            } else {
                $fromstring = '{'.$from['table'].'}';
            }
            if (!empty($fromarray)) { // No JOIN keyword for the first table
                if (isset($from['join'])) {
                    $fromstring = $from['join'].' '.$fromstring;
                } else {
                    // Default to INNER JOIN
                    $fromstring = 'INNER JOIN '.$fromstring;
                }
            }
            if (isset ($from['alias'])) {
                $fromstring .= ' '.$from['alias'];
            } else {
                $fromstring .= ' '.$from['table'];
            }
            if (!empty($fromarray)) {
                if (isset($from['on'])) {
                    $fromstring .= " ON ".$from['on'];
                } else {
                    throw new coding_exception('No on bit specified for query join');
                }
            }

            $fromarray[] = $fromstring;

        }

        return "\n\n FROM ".implode(" \n", $fromarray).' ';
    }

    /**
     * Joins all the WHERE clauses with AND (or whatever) and returns them
     *
     * @return string SQL
     */
    protected function get_where() {

        // The first clause should not have AND at the start
        $first = true;

        $wherearray = array();

        foreach ($this->where as $clause) {
            $wherearray[] = ($first ? '' : $clause['type']).' '.$clause['condition'];
            $first = false;
        }

        if ($wherearray) {
            return "\n\n WHERE ".implode(" \n", $wherearray).' ';
        } else {
            return '';
        }
    }

    /**
     * Need to construct the groupby here from the SELECT bits as Oracle complains if you have an
     * aggregate and then leave out some of the bits. Possible that Oracle doesn't accept aliases
     * for GROUP BY?
     *
     * @return string SQL
     */
    public function get_groupby() {

        $groupby = array();

        if ($this->has_select_aggregate()) {
            // Can't miss out any of the things or Oracle will be unhappy

            foreach ($this->select as $column) {
                // if the column is not a MAX, COUNT, etc, add it to the groupby
                if (!isset($column['function']) && $column['column'] !== '*') {
                    $groupby[] = (isset($column['table']) ? $column['table'].'.' : '').
                                 $column['column'];
                }
            }
            if ($groupby) {
                return "\n\n GROUP BY ".implode(", \n", $groupby).' ';
            } else {
                return '';
            }
        } else {
            return '';
        }
    }

    /**
     * Puts all the ORDER BY stuff together in a string
     *
     * @return string SQL
     */
    protected function get_orderby() {

        if ($this->orderby) {
            return "\n\n ORDER BY ".implode(', ', $this->orderby).' ';
        } else {
            return '';
        }
    }

    /**
     * Adds a column to the select. Needs a table, column, function (optional).
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
     * @param bool $replace If true, the start or end element will be replaced with the incoming
     * one. Default: false
     * @return void
     */
    public function add_select(array $column, $prefix = false, $replace = false) {

        $requiredkeys = array('function', 'table', 'column', 'alias', 'distinct');
        $key = isset($column['alias']) ? $column['alias'] : $column['column'];

        if (isset($select['function']) && strtoupper($select['function']) === 'COALESCE') {
            // COALESCE takes an array of tables and columns to add together
            if (!is_array($column['table'])) {
                $errorstring = 'add_select() called with COALESCE function and non-array list of '.
                               'tables';
                throw new invalid_parameter_exception($errorstring);
            }
        }

        if (is_array($column) && (array_diff(array_keys($column), $requiredkeys) === array())) {
            if ($prefix) { // put it at the start
                if ($replace) { // knock off the existing one
                    array_shift($this->select);
                }
                // array_unshift does not allow us to add using a specific key
                $this->select = array($key => $column) + $this->select;
            } else {
                if ($replace) {
                    array_pop($this->select);
                }
                $this->select[$key] = $column;
            }

        } else {
            throw new coding_exception('Wrong array items specified for new SELECT column');
        }
    }

    /**
     * This will add a join table. No alias means it'll use the table name.
     *
     * @param array $table containing 'join', 'table', 'alias', 'on', 'subquery' (last one optional)
     * @return void
     */
    public function add_from(array $table) {

        if (!is_array($table)) {
            throw new invalid_parameter_exception($table);
        }

        $requiredkeys = array('join',
                              'table',
                              'alias',
                              'on',
                              'subquery',
                              'union');

        if (array_diff(array_keys($table), $requiredkeys) === array()) {
            $key = isset($table['alias']) ? $table['alias'] : $table['table'];
            $this->from[$key] = $table;
        } else {
            $errorstring = 'Wrong array items specified for new FROM table';
            throw new coding_exception($errorstring, array_keys($table));
        }
    }

    /**
     * Adds a where clause. All clauses will be imploded with AND
     *
     * Issues:
     * - what if it's a nested thing e.g. AND ( X AND Y )
     * - what if it's a subquery e.g. EXISTS ()
     *
     * @param array $clause containing 'type' e.g. 'AND' & 'condition' which is something that can
     * be added to other things using AND
     * @internal param $
     * @return void
     */
    public function add_where(array $clause) {

        $requiredkeys = array('type', 'condition');

        if (is_array($clause) && (array_diff(array_keys($clause), $requiredkeys) === array())) {
            $this->where[] = $clause;
        } else {
            throw new coding_exception('Wrong array items specified for new WHERE clause');
        }
    }

    /**
     * Adds an item to the ORDER BY array
     *
     * @param string $column table.column
     * @param bool $prefix If true, this will be added at the start rather than the end
     * @return void
     */
    public function add_orderby($column, $prefix = false) {

        if ($prefix) {
            array_unshift($this->orderby, $column);
        } else {
            $this->orderby[] = $column;
        }
    }

    /**
     * Adds one item to the params array. Always use SQL_PARAMS_NAMED.
     *
     * @param string $name
     * @param string $value
     * @return void
     */
    public function add_param($name, $value) {
        // must differentiate between the modules, which will be duplicating params. Prefixing with
        // the module name means that when we do array_merge, we won't have a problem
        $this->params[$name] = $value;
    }

    /**
     * Getter for the DB module name
     *
     * @return string
     */
    public function get_modulename() {
        if ($this->moduleobject) {
            return $this->moduleobject->modulename;
        }
        throw new coding_exception('Trying to get a modulename from a query with no module');
    }

    /**
     * Getter for the DB module name
     *
     * @return string
     */
    public function get_module_id() {
        if ($this->moduleobject) {
            return $this->moduleobject->get_module_id();
        }
        throw new coding_exception('Trying to get a module id from a query with no module');
    }

    /**
     * Adds an associative array of parameters to the query
     *
     * @param array $params
     * @param bool|array $arraytoaddto
     * @return bool|array
     */
    public function add_params(array $params, $arraytoaddto = false) {

        $dupes = array_intersect(array_keys($params), array_keys($this->params));
        if ($dupes) {
            throw new coding_exception('Duplicate keys when adding query params',
                                       implode(', ', $dupes));
        }
        if ($arraytoaddto === false) {
            $this->params = array_merge($this->params, $params);
            return true;
        } else {
            return array_merge($params, $arraytoaddto);
        }
    }

    /**
     * Will check to see if any of the select clauses have aggregates. Needed to know if GROUP BY is
     * necessary.
     *
     * @return bool
     */
    protected function has_select_aggregate() {

        // We don't want to have a GROUP BY if it's a COALESCE function, otherwise
        // Oracle complains
        $nogroupbyfunctions = array('COALESCE');

        foreach ($this->select as $select) {
            if (isset($select['function']) && !in_array($select['function'], $nogroupbyfunctions)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get all the bits and join them together, then return a query ready to use in
     * $DB->get_records()
     *
     * @return array query string, then params
     */
    public function to_string() {

        // Stick it all together
        $query = $this->get_select().
                 $this->get_from().
                 $this->get_where().
                 $this->get_groupby().
                 $this->get_orderby();

        return $query;
    }

    /**
     * Getter function for the params. Works recursively to allow subqueries an union (arrays) of
     * subqueries.
     *
     * @return array
     */
    public function get_params() {
        $params = array();
        foreach ($this->from as $jointable) {
            /**
             * @var block_ajax_marking_query_base $table
             */
            $table = $jointable['table'];
            if ($table instanceof block_ajax_marking_query_base) {
                $params = $this->add_params($table->get_params(), $params);
            } else if (is_array($table)) {
                $this->validate_union_array($table);
                /**
                 * @var block_ajax_marking_query_base $uniontable
                 */
                foreach ($table as $uniontable) {
                    $params = $this->add_params($uniontable->get_params(), $params);
                }
            }
        }
        return array_merge($params, $this->params);
    }

    /**
     * Makes sure that we have an array of query objects rather than strings or anything else
     *
     * @throws coding_exception
     * @param $unionarray
     * @return void
     */
    private function validate_union_array($unionarray) {
        foreach ($unionarray as $table) {
            if (!($table instanceof block_ajax_marking_query_base)) {
                $error = 'Array of queries for union are not all instances of '.
                         'block_ajax_marking_query_base';
                throw new coding_exception($error);
            }
        }
    }

    /**
     * Setter for the userid column
     *
     * @param string $column the userid column in the submissions (sub) table
     */
    public function set_userid_column($column) {
        $this->useridcolumn = $column;
    }

    /**
     * Getter function for the associated module's capability so we can check for permissions
     *
     * @return string
     */
    public function get_capability() {
        if ($this->moduleobject) {
            return $this->moduleobject->get_capability();
        }
        throw new coding_exception('Trying to get a capability from a query with no module');
    }

    /**
     * Runs the query and returns the result
     *
     * @todo check the query for completeness first e.g. all select tables are present in the joins
     * @global moodle_database $DB
     * @return array
     */
    public function execute() {
        global $DB;
        return $DB->get_records_sql($this->to_string(), $this->get_params());
    }

    /**
     * Provides an EXISTS(xxx) subquery that tells us whether there is a group with user x in it
     *
     * @param string $configalias this is the alias of the config table in the SQL
     * @return string SQL fragment
     */
    private function get_sql_groups_subquery($configalias) {

        $groupsql = " EXISTS (SELECT 1
                                FROM {groups_members} gm
                          INNER JOIN {groups} g
                                  ON gm.groupid = g.id
                          INNER JOIN {block_ajax_marking_groups} gs
                                  ON g.id = gs.groupid
                               WHERE gm.userid = moduleunion.userid
                                 AND gs.configid = {$configalias}.id) ";

        return $groupsql;

    }

    /**
     * Sometimes we need to know if we can safely add a clause
     *
     * @param string$tablename
     * @return bool
     */
    public function has_join_table($tablename) {

        foreach ($this->from as $join) {
            if ($join['table'] == $tablename) {
                return true;
            }
        }

        return false;
    }

    /**
     * This will retrieve a subquery object so that filters can be applied to it.
     *
     * @param string $queryname
     * @return block_ajax_marking_query_base
     * @throws coding_exception
     */
    public function &get_subquery($queryname) {

        foreach ($this->from as $jointable) {

            if (isset($jointable['subquery']) &&
                isset($jointable['alias']) &&
                $jointable['alias'] === $queryname) {

                return $jointable['table'];
            }
        }
        throw new coding_exception('Trying to retrieve a non-existent subquery: '.$queryname);
    }


}

