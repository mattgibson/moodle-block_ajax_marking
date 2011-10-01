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
     * An array of arrays. Each needsm 'join', 'table', 'on'
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
    private $groupby = array();

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
     * @param \block_ajax_marking_query_base|bool $moduleobject
     * @return \block_ajax_marking_query_base
     */
    public function __construct($moduleobject = false) {
        $this->moduleobject = $moduleobject;
    }

    /**
     * Crunches the SELECT array into a valid SQL query string. Each has 'function', 'table',
     * 'column', 'alias', 'distinct'
     *
     * @param bool $union Are we asking for a version of the SELECT clauses that can be used as a
     * wrapper via UNION?
     * @return string SQL
     */
    public function get_select($union = false) {

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

            if ($from['table'] instanceof block_ajax_marking_query_base) { //allow for recursion
                $fromstring = '('.$from['table']->to_string().')';
            } else if (isset($from['subquery'])) {
                $fromstring = '('.$from['table'].')';
            } else {
                $fromstring = '{'.$from['table'].'}';
            }
            if (isset($from['join'])) {
                $fromstring = $from['join'].' '.$fromstring;
            }
            if (isset ($from['alias'])) {
                $fromstring .= ' '.$from['alias'];
            } else {
                $fromstring .= ' '.$from['table'];
            }
            if (isset($from['join'])) {
                if (isset($from['on'])) {
                    $fromstring .= ' ON '.$from['on'];
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
     * Need to construct the groupby here from the SELECT bits as Oracle compalins if you have an
     * aggregate and then leave out some of the bits. Possible that Oracle doesn't accept aliases
     * for GROUP BY?
     *
     * @param bool $union
     * @return string SQL
     */
    public function get_groupby($union = false) {

        $groupby = array();

        if ($this->has_select_aggregate()) {
            // Can't miss out any of the things or Oracle will be unhappy

            foreach ($this->select as $alias => $column) {
                // if the column is not a MAX, COUNT, etc, add it to the groupby
                if (!isset($column['function']) && $column['column'] !== '*') {
                    $groupby[] = (isset($column['table']) ? $column['table'].'.' : '').
                                 $column['column'];
                }
            }
            if ($groupby) {
                return "\n\n GROUP BY ".implode(', ', $groupby).' ';
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
     * This will add a join table.
     *
     * @param array $table containing 'join', 'table', 'alias', 'on'
     * @return void
     */
    public function add_from(array $table) {

        if (!is_array($table)) {
            throw new invalid_parameter_exception($table);
        }

        $requiredkeys = array('join', 'table', 'alias', 'on', 'subquery');

        if (array_diff(array_keys($table), $requiredkeys) === array()) {
            $this->from[] = $table;
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
     * @param string $column
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
        $key = $this->prefix_param($name);
        $this->params[$key] = $value;
    }

    /**
     * Avoid naming collisions when using similar subqueries
     *
     * @param string $name
     * @return string
     */
    public function prefix_param($name) {
        if ($this->moduleobject) {
            return $this->moduleobject->modulename.'xx'.$name;
        }
        return 'mainqueryxx'.$name;
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
     * @param bool $prefix do we want to make these paremters unique to this module? Use false if
     * get_in_or_equal() has been used
     * @return void
     */
    public function add_params(array $params, $prefix = true) {
        // TODO throw error if same keys as existing ones are fed in.
        $dupes = array_intersect_key($params, $this->params);
        if ($dupes) {
            throw new coding_exception('Duplicate keys when adding query params', $dupes);
        }

        // Avoid collisions by prefixing all key names unless otherwise specified
        if ($prefix) {
            foreach ($params as $oldkey => $value) {
                $newkey = $this->prefix_param($oldkey);
                $params[$newkey] = $value;
                unset($params[$oldkey]);
            }
        }

        $this->params = array_merge($this->params, $params);
    }

    /**
     * Will check to see if any of the select clauses have aggregates. Needed to know if GROUP BY is
     * necesary.
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
     * Getter function for the params
     *
     * @return array
     */
    public function get_params() {
        return $this->params;
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
     * Geter function for the associated module's capability so we can check for permissions
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
     * Returns the name of the userid column of the submissions table. Currently either userid or
     * authorid. This is so that other components of the SQL query can reference the userid
     *
     * @return string table.column
     */
    public function get_userid_column() {
        return $this->useridcolumn ? $this->useridcolumn : false;
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

        $useridfield = $this->get_userid_column();

        $groupsql = " EXISTS (SELECT 1
                                FROM {groups_members} gm
                          INNER JOIN {groups} g
                                  ON gm.groupid = g.id
                          INNER JOIN {block_ajax_marking_groups} gs
                                  ON g.id = gs.groupid
                               WHERE gm.userid = {$useridfield}
                                 AND gs.configid = {$configalias}.id) ";

        return $groupsql;

    }

    /**
     * Looks to the associated module to make any specific changes
     *
     * @param bool $groupby
     * @return void
     */
    public function alter_query_hook($groupby = false) {
        $this->moduleobject->alter_query_hook($this, $groupby);
    }

    /**
     * This is not used for output, but just converts the parametrised query to one that can be
     * copy/pasted into an SQL GUI in order to debug SQL errors
     *
     *
     * @internal param string $query
     * @internal param array $params
     * @global type $CFG
     * @return string
     */
    public function debuggable_query() {

        global $CFG;

        $query = $this->to_string();
        $params = $this->get_params();

        // Substitute all the {tablename} bits
        $query = preg_replace('/\{/', $CFG->prefix, $query);
        $query = preg_replace('/}/', '', $query);

        // Now put all the params in place
        foreach ($params as $name => $value) {
            $pattern = '/:'.$name.'/';
            $replacevalue = (is_numeric($value) ? $value : "'".$value."'");
            $query = preg_replace($pattern, $replacevalue, $query);
        }

        return $query;
    }

}