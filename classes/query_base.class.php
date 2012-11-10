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

global $CFG;

require_once($CFG->dirroot.'/blocks/ajax_marking/classes/query.interface.php');

/**
 * Base class for core queries, allowing the parameters and various other filters to be added
 * dynamically.
 */
class block_ajax_marking_query_base implements block_ajax_marking_query {

    /**
     * @var string
     */
    protected $columns = array();

    /**
     * This hold arrays, with each one being a table column. Each array needs 'function', 'table',
     * 'column', 'alias', 'distinct'. Each array is keyed by its column alias or name, if there is
     * no alias. Its important that these are in the right order as the GROUP BY will be generated
     * from them.
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
     * @var string Name used to refer to this query if it is a subquery.
     */
    protected $subqueryname;

    /**
     * @var bool Do we want to add WHERE courseid IN (x, y, z) to tall tables that can take it to improve performance?
     */
    protected $courselimitstatus;

    /**
     * @var block_ajax_marking_module_base|null Reference to the module object with all the data about the
     * relevant module.
     */
    protected $moduleclass;

    /**
     * @param block_ajax_marking_module_base $moduleclass
     */
    public function __construct($moduleclass = null) {
        $this->moduleclass = $moduleclass;
    }

    /**
     * Crunches the SELECT array into a valid SQL query string. Each has 'function', 'table',
     * 'column', 'alias', 'distinct'
     *
     * wrapper via UNION?
     *
     * @param bool $nocache if true, SQL_NO_CACHE will be added to the start of the query.
     * @return string SQL
     */
    protected function get_select($nocache = false) {

        global $CFG;

        $selectarray = array();

        foreach ($this->select as $select) {
            $selectarray[] = self::build_select_item($select);
        }

        // For development, we don't want the cache in use - it makes it hard to debug via SQL tools etc.
        $nocachestring = '';
        if ($nocache && ($CFG->dbtype == 'mysql' || $CFG->dbtype == 'mysqli')) {
            $nocachestring = ' SQL_NO_CACHE ';
        }
        return 'SELECT '.$nocachestring.implode(", \n", $selectarray).' ';
    }

    /**
     * Makes one array of the SELECT options into a string of SQL.
     *
     * @param $select
     * @param bool $forgroupby If we are using this to make the COALESCE bit for GROUP BY, we don't
     * want an alias
     * @return string
     */
    protected function build_select_item($select, $forgroupby = false) {

        // The 'column' will not be set if this is COALESCE. We have an associative array of
        // table=>column pairs.
        if (isset($select['function']) && strtoupper($select['function']) === 'COALESCE') {
            $selectstring = self::get_coalesce_from_table($select['table']);
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
        if (isset($select['alias']) && !$forgroupby) {

            if (isset($select['column']) && $this->is_postgres()) {
                if (preg_match('#^(\'|").+\1$#', $select['column'])) {
                    $selectstring .= '::text ';
                }
            }
            $selectstring .= ' AS '.$select['alias'];
        }

        return $selectstring;

    }

    /**
     * Returns the SQL fragment for the join tables
     *
     * @throws coding_exception
     * @return string SQL
     */
    protected function get_from() {

        $fromarray = array();

        foreach ($this->from as $from) {

            if (!isset($from['join'])) {
                $from['join'] = 'INNER JOIN'; // Default.
            }

            if ($from['table'] instanceof block_ajax_marking_query) { // Allow for recursion.
                /* @define block_ajax_marking_query $from['table'] */
                $fromstring = '('.$from['table']->get_sql().')';

            } else if (isset($from['subquery'])) {

                if (isset($from['union'])) {
                    $fromstring = $this->make_union_subquery($from);
                } else {
                    $fromstring = '('.$from['table'].")\n";
                }
            } else {
                $fromstring = '{'.$from['table'].'}';
            }
            if (isset ($from['alias'])) {
                $fromstring .= ' '.$from['alias'];
            } else {
                // Default to table name as alias.
                $fromstring .= ' '.$from['table'];
            }
            if (!empty($fromarray)) {
                // No JOIN keyword for the first table.
                $fromstring = $from['join'].' '.$fromstring;

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
     * Glues together the bits of an array of other queries in order to join them as a UNIONed
     * query. Currently only used for sticking the module queries together.
     *
     * @param array $from
     * @return string SQL
     * @throws coding_exception
     */
    protected function make_union_subquery($from) {

        if (!is_array($from['table'])) {
            $error = 'Union subqueries must have an array supplied as their table item';
            throw new coding_exception($error);
        }
        $this->validate_union_array($from['table']);
        $unionarray = array();
        /* @var block_ajax_marking_query_base $table */
        foreach ($from['table'] as $table) {
            $unionarray[] = $table->get_sql();
        }
        $fromstring = '(';
        $fromstring .= implode("\n\n UNION ALL \n\n", $unionarray);
        $fromstring .= ")";
        return $fromstring;
    }

    /**
     * Joins all the WHERE clauses with AND (or whatever) and returns them
     *
     * @return string SQL
     */
    protected function get_where() {

        if (!empty($this->where)) {
            return "\n\n WHERE ".implode("\n AND ", $this->where).' ';
        } else {
            return '';
        }
    }

    /**
     * Need to construct the GROUP BY here from the SELECT bits as Oracle complains if you have an
     * aggregate and then leave out some of the bits. Possible that Oracle doesn't accept aliases
     * for GROUP BY?
     *
     * @return string SQL
     */
    protected function get_groupby() {

        $groupby = array();

        if ($this->has_select_aggregate()) {
            // Can't miss out any of the things or Oracle will be unhappy.

            foreach ($this->select as $column) {
                // If the column is not a MAX, COUNT, etc, add it to the groupby.
                $functioniscoalesce = (isset($column['function']) &&
                                       strtoupper($column['function']) == 'COALESCE');
                $notafunctioncolumn = !isset($column['function']) && $column['column'] !== '*';
                if ($functioniscoalesce) {
                    $groupby[] = self::build_select_item($column, true);
                } else if ($notafunctioncolumn) {
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
     * If we have been given a COALESCE function as part of the SELECT, we need to construct the
     * sequence of table.column options and defaults.
     *
     * @param array $table
     * @throws coding_exception
     * @return string
     */
    protected function get_coalesce_from_table($table) {

        if (!is_array($table)) {
            $error = 'get_select() has a COALESCE function with a $table that\'s not an array';
            throw new coding_exception($error);
        }

        $tablesarray = array();
        foreach ($table as $tab => $col) {
            // COALESCE may have non-SQL defaults, which are just added with numeric keys.
            if (is_string($tab)) {
                $tablesarray[] = $tab.'.'.$col;
            } else {
                // The default value may be a number, or a string, in which case, it needs quotes.
                $tablesarray[] = is_string($col) ? "'".$col."'" : $col;
            }
        }
        return implode(', ', $tablesarray);

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

        $requiredkeys = array('function', 'table', 'column', 'alias', 'distinct');
        $key = isset($column['alias']) ? $column['alias'] : $column['column'];

        if (isset($select['function']) &&
            strtoupper($select['function']) === 'COALESCE' &&
            !is_array($column['table'])) {

            // COALESCE takes an array of tables and columns to add together.
            $errorstring = 'add_select() called with COALESCE function and non-array list of '.
                           'tables';
            throw new invalid_parameter_exception($errorstring);
        }

        if (array_diff(array_keys($column), $requiredkeys) !== array()) {
            throw new coding_exception('Wrong array items specified for new SELECT column');
        }

        if ($prefix) { // Put it at the start.
            // Array_unshift does not allow us to add using a specific key.
            $this->select = array($key => $column) + $this->select;
        } else {
            $this->select[$key] = $column;
        }

    }

    /**
     * This will add a join table. No alias means it'll use the table name.
     *
     * @param array $table containing 'join', 'table', 'alias', 'on', 'subquery' (last one optional)
     * @throws coding_exception
     * @throws invalid_parameter_exception
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
     * @param string $sql Always added using AND
     * @param array $params
     * @throws coding_exception
     * @return void
     */
    public function add_where($sql, $params = array()) {

        $this->where[] = $sql;

        $this->add_params($params);
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
        // Must differentiate between the modules, which will be duplicating params. Prefixing with
        // the module name means that when we do array_merge, we won't have a problem.
        $this->params[$name] = $value;
    }

    /**
     * Adds an associative array of parameters to the query
     *
     * @param array $params
     * @throws coding_exception
     * @return void
     */
    public function add_params(array $params) {

        if (empty($params)) {
            return;
        }

        $dupes = array_intersect(array_keys($params), array_keys($this->params));
        if ($dupes) {
            throw new coding_exception('Duplicate keys when adding query params',
                                       implode(', ', $dupes));
        }

        $this->params = array_merge($this->params, $params);
    }

    /**
     * Will check to see if any of the select clauses have aggregates. Needed to know if GROUP BY is
     * necessary.
     *
     * @return bool
     */
    protected function has_select_aggregate() {

        // We don't want to have a GROUP BY if it's a COALESCE function, otherwise
        // Oracle complains.
        $nogroupbyfunctions = array('COALESCE');

        foreach ($this->select as $select) {
            if (isset($select['function']) && !in_array(strtoupper($select['function']), $nogroupbyfunctions)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get all the bits and join them together, then return a query ready to use in
     * $DB->get_records(). Must be public or else we cannot wrap the queries in each other as
     * subqueries.
     *
     * @param bool $nocache if true, SQL_NO_CACHE will be set
     * @return string
     */
    public function get_sql($nocache = false) {

        // Stick it all together.
        $query = $this->get_select($nocache).
                 $this->get_from().
                 $this->get_where().
                 $this->get_groupby().
                 $this->get_orderby();

        return $query;

    }

    /**
     * Getter function for the params. Works recursively to allow subqueries an union (arrays) of
     * subqueries. Must be public so we can wrap queries in other queries as subqueries.
     *
     * @return array
     */
    public function get_params() {
        $params = array();
        foreach ($this->from as $jointable) {
            $table = $jointable['table'];
            if ($table instanceof block_ajax_marking_query) {
                /* @var block_ajax_marking_query_base $table */
                $params = array_merge($params, $table->get_params());
            } else if (is_array($table)) {
                /* @var array $table */
                $this->validate_union_array($table);
                /* @var block_ajax_marking_query_base $uniontable */
                foreach ($table as $uniontable) {
                    $params = array_merge($params, $uniontable->get_params());
                }
            }
        }
        return array_merge($params, $this->params);
    }

    /**
     * Makes sure that we have an array of query objects rather than strings or anything else
     *
     * @throws coding_exception
     * @param array $unionarray
     * @return void
     */
    private function validate_union_array($unionarray) {
        foreach ($unionarray as $table) {
            if (!($table instanceof block_ajax_marking_query)) {
                $error = 'Array of queries for union are not all instances of '.
                         'block_ajax_marking_query_base';
                throw new coding_exception($error);
            }
        }
    }

    /**
     * Runs the query and returns the result. Optionally return a recordset in case we are testing
     * and expect duplicate values in the first column e.g. if it's a subquery.
     *
     * @todo check the query for completeness first e.g. all select tables are present in the joins
     * @param bool $returnrecordset
     * @global moodle_database $DB
     * @return array|moodle_recordset
     */
    public function execute($returnrecordset = false) {

        global $DB, $CFG;

        $nocache = $CFG->debug == DEBUG_DEVELOPER;

        if ($returnrecordset) {
            return $DB->get_recordset_sql($this->get_sql($nocache), $this->get_params());
        } else {
            return $DB->get_records_sql($this->get_sql($nocache), $this->get_params());
        }
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

    /**
     * This is not used for output, but just converts the parametrised query to one that can be
     * copy/pasted into an SQL GUI in order to debug SQL errors
     *
     * @throws coding_exception
     * @global stdClass $CFG
     * @return string
     */
    public function debuggable_query() {

        global $CFG;

        $params = $this->get_params();
        $sql = $this->get_sql($CFG->debug == DEBUG_DEVELOPER);

        // We may have a problem with params being missing. Check here (assuming the params ar in
        // SQL_PARAMS_NAMED format And tell us the names of the offending params via an exception.
        $pattern = '/[^:]:([\w]+)/';
        $expectedparamcount = preg_match_all($pattern, $sql, $paramnames);
        if ($expectedparamcount) {
            $arrayparamnames = array_keys($params);
            $queryparamnames = $paramnames[1];
            if ($expectedparamcount > count($params)) {
                // Params are indexed by the name we gave, whereas the $paramnames are indexed by
                // numeric position in $query. First array has colons at start of keys.
                $missingparams = array_diff($queryparamnames, $arrayparamnames);
                throw new coding_exception('Missing parameters: '.implode(', ', $missingparams));
            } else if ($expectedparamcount < count($params)) {
                $extraparams = array_diff($arrayparamnames, $queryparamnames);
                throw new coding_exception('Too many parameters: '.implode(', ', $extraparams));
            }
        }

        // Substitute all the {tablename} bits.
        $sql = preg_replace('/\{/', $CFG->prefix, $sql);
        $sql = preg_replace('/}/', '', $sql);

        // Now put all the params in place.
        foreach ($params as $name => $value) {
            $pattern = '/:'.$name.'/';
            $replacevalue = (is_numeric($value) ? $value : "'".$value."'");
            $sql = preg_replace($pattern, $replacevalue, $sql);
        }

        return $sql;
    }

    /**
     * If this is a subquery, we need to be able to have decorators do things like SELECT nameofsubquery.column
     * for different subqueries, all of which will have the same column name. This returns it, which needs to
     * have been set somehow.
     *
     * @return string
     */
    public function get_subquery_name() {
        return $this->subqueryname;
    }

    /**
     * Sets the name of this subquery so that we can refer to it from decorators when they attach stuff.
     *
     * @param string $name
     */
    public function set_subquery_name($name) {
        $this->subqueryname = $name;
    }

    /**
     * Returns the SQL fragment of tablealias.column which has previously been stored. Exception if we ask for
     * one that's not there.
     *
     * @param string $columnname
     * @return string
     * @throws coding_exception
     */
    public function get_column($columnname) {
        if (array_key_exists($columnname, $this->columns)) {
            return $this->columns[$columnname];
        }

        throw new coding_exception("Looking for a non-existent {$columnname} column");
    }

    /**
     * Stores the name of a column that we have just added to the dynamic query so that other decorators can get to
     * it later.
     *
     * @param string $columnname
     * @param string $sql
     * @throws coding_exception
     */
    public function set_column($columnname, $sql) {

        if (array_key_exists($columnname, $this->columns)) {
            $message = "Trying to add a {$columnname} column, but it's already been stored as ".
                $this->columns[$columnname];
            throw new coding_exception($message);
        }
        $this->columns[$columnname] = $sql;

    }

    /**
     * If there is a moduleclass, return the name of it.
     *
     * @return string
     * @throws coding_exception
     */
    public function get_module_name() {
        if (!($this->moduleclass instanceof block_ajax_marking_module_base)) {
            return '';
        }

        return $this->moduleclass->get_module_name();
    }

    /**
     * If there is a moduleclass, return the id of that module. Used to attache coursemodule by instance and moduleid.
     *
     * @return string
     * @throws coding_exception
     */
    public function get_module_id() {
        if (!($this->moduleclass instanceof block_ajax_marking_module_base)) {
            throw new coding_exception('Asking for module name without any module attached to the query');
        }

        return $this->moduleclass->get_module_id();
    }

    /**
     * Sets the course limit status so that we know whether this query needs to have all appropriate tables limited
     * to courses that the user has access to. This makes the whole thing much faster for normal users. Only admins
     * who want to see all courses at once need this off.
     *
     * @abstract
     * @param bool $on
     */
    public function set_course_limit_status($on = true) {
        $this->courselimitstatus = $on;
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
        return $this->courselimitstatus;
    }

    /**
     * Slightly awkward way of making sure we can add bits and pieces of SQL to unioned queries without
     * duplicating param names. If we just use the query_union class to add the same fragment to all of the bits,
     * @todo use an array iterator of all the queries or something instead.
     *
     * @return mixed
     */
    public function get_number_of_unioned_subqueries() {
        return 1;
    }

    /**
     * Function to enable ugly hacks that allow us to discriminate between postgres and other DBs.
     *
     * @return bool
     */
    protected function is_postgres() {

        global $CFG;

        $postgrestypes = array(
            'pgsql'
        );

        return in_array($CFG->dbtype, $postgrestypes);

    }
}

