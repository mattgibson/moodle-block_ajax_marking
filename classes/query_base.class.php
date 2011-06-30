<?php

/**
 * Base class for core queries and the decorator classes that will add filters to them
 */
class block_ajax_marking_query_base {
    
    /**
     * This hold arrays, with each one being a table column. Each array needs 'function', 'table', 'column', 'alias', 'distinct'.
     * Each array is keyed by its column alias or name, if there is no alias. Its important that these are in 
     * the right order as the GROUP BY will be generated from them
     * @var array
     */
    private $select = array();
    
    /**
     * An array of arrays. Each needsm 'join', 'table', 'on'
     * @var array
     */
    private $from = array();
    
    /**
     * Just a straight array of clauses, no matter how big, each one capable of being tacked on with an AND.
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
     * Holds the name of the useridcolumn of the submissions table. Needed as it varies across different modules
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
     * @param block_ajax_marking_query_base $moduleobject
     */
    public function __construct($moduleobject) {
        $this->moduleobject = $moduleobject;
    }
    
    /**
     * Crunches the SELECT array into a valid SQL query string. Each has 'function', 'table', 'column', 
     * 'alias', 'distinct'
     * 
     * @param bool $union Are we asking for a version of the SELECT clauses that can be used as a wrapper
     *                    via UNION?
     * @return string SQL
     */
    public function get_select($union = false) {
        
        $selectarray = array();
        
        foreach ($this->select as $select) {
            
            // Doing it all as one so we don't destroy $select before trying to use more bits of it
            // later on e.g. deriving GROUP BY
            if ($union) {
                $selectstring = (isset($select['function']) ?' SUM(' : ''). // We need to add them, all as they've already been counted
                                (isset($select['distinct']) ? 'DISTINCT ' : '').
                                'unionquery.'.(isset($select['alias']) ? $select['alias'] : $select['column']).
                                (isset($select['function']) ? ')' : '').
                                (isset($select['alias']) ? ' AS '.$select['alias'] : '');
            } else {
                $selectstring = (isset($select['function']) ? strtoupper($select['function']).'( ' : '').
                                (isset($select['distinct']) ? 'DISTINCT ' : '').
                                (isset($select['table']) ? $select['table'].'.' : '').
                                $select['column'].' '.
                                (isset($select['function']) ? ') ' : '').
                                (isset($select['alias']) ? ' AS '.$select['alias'].' ' : '');
            }
                             
            $selectarray[] = $selectstring;
            
        }
        
        return 'SELECT '.implode(', ', $selectarray).' ';
    }
    
    /**
     * Some modules will need to remove part of the standard SELECT array to make the query work e.g. 
     * Forum needs to remove userid from the submissions query to make it do GROUP BY properly
     * 
     * @param string $alias The alias or tablename that was used to key this SELECT statement to the array
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
            $fromarray[] = (isset($from['join']) ? ' '.$from['join'].' ' : '').
                           ' {'.$from['table'].'} '.
                           (isset($from['alias']) ? ' '.$from['alias'].' ' : ' '.$from['table']). // without an alias the prefix messes it up
                           (isset($from['join']) ? ' ON '.$from['on'].' ' : '');
        }
        
        return ' FROM '.implode(' ', $fromarray).' ';
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
            return ' WHERE '.implode(' ', $wherearray).' ';
        } else {
            return '';
        }
    }
    
    /**
     * Need to construct the groupby here from the SELECT bits as Oracle compalins if you have an aggregate
     * and then leave out some of the bits. Possible that Oracle doesn't accept aliases for GROUP BY?
     * 
     * @return string SQL 
     */
    public function get_groupby($union = false) {
        
        $groupby = array();
        
        if ($this->has_select_aggregate()) {
            
            foreach ($this->select as $alias => $column) {
                // if the column is not a MAX, COUNT, etc, add it to the groupby
                if (!isset($column['function'])) {
                    
                    if ($union) {
                        $groupby[] = 'unionquery.'.(isset($column['alias']) ? $column['alias'] : $column['column']);
                    } else {
                        // some won't have a column e.g. 'COALESCE()', which just need the alias
                        $groupby[] = (isset($column['table']) ? $column['table'].'.' : '').$column['column'];
                    }
                }
            }
            if ($groupby) {
                return ' GROUP BY '.implode(', ', $groupby).' ';
            } else {
                return '';
            }
        } else {
            // just group by the unique id
            if ($this->select) {
                $firstcolumn = reset($this->select);
                if ($union) {
                    return ' GROUP BY unionquery.'.$firstcolumn['column'].' ';
                } else {
                    return ' GROUP BY '.$firstcolumn['table'].'.'.$firstcolumn['column'].' ';
                }
            } else {
                return '';
            }
        }
    }
    
    /**
     * Puts all the ORDER BY stuff together in a string
     * 
     * @return string SQL
     */
    protected function get_orderby() {
        
        if ($this->orderby) {
            return ' ORDER BY '.implode(', ', $this->orderby).' ';
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
     * @param array $column containing: 'function', 'table', 'column', 'alias', 'distinct' in any order
     * @param bool $prefix Do we want this at the start, rather than the end?
     * @param bool $replace If true, the start or end element will be replaced with the incoming one. Default: false
     * @return void
     */
    public function add_select(array $column, $prefix = false, $replace = false) {
        
        $requiredkeys = array('function', 'table', 'column', 'alias', 'distinct');
        
        $key = isset($column['alias']) ? $column['alias'] : $column['column'];
        
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
            print_error('Wrong array items specified for new SELECT column');
        }
    }
    
    /**
     * This will add a join table.
     * 
     * Issues:
     * - what if it's a subquery?
     * 
     * @param array $table containing 'join', 'table', 'alias', 'on'
     * @return void
     */
    public function add_from(array $table) {
        
        $requiredkeys = array('join', 'table', 'alias', 'on');
        
        if (is_array($table) && (array_diff(array_keys($table), $requiredkeys) === array())) {
            $this->from[] = $table;
        } else {
            print_error('Wrong array items specified for new FROM table');
        }
    }

    /**
     * Adds a where clause. All clauses will be imploded with AND
     * 
     * Issues:
     * - what if it's a nested thing e.g. AND ( X AND Y )
     * - what if it's a subquery e.g. EXISTS ()
     * 
     * @param
     * @param array $clause containing 'type' e.g. 'AND' & 'condition' which is something that can be 
     *                      added to other things using AND
     * @return void
     */
    public function add_where(array $clause) {
        
        $requiredkeys = array('type', 'condition');
        
        if (is_array($clause) && (array_diff(array_keys($clause), $requiredkeys) === array())) {
            $this->where[] = $clause;
        } else {
            print_error('Wrong array items specified for new WHERE clause');
        }
    }
    
    /**
     * Adds an item to the orderby array
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
        // the modulename means that when we do array_merge, we won't have a problem
        $key = $this->prefix_param_name($name);
        $this->params[$key] = $value;
    }
    
    public function prefix_param_name($name) {
        return $this->moduleobject->modulename.'xx'.$name;
    }
    
    /**
     * Getter for the DB module name
     * 
     * @return string
     */
    public function get_modulename() {
        return $this->moduleobject->modulename;
    }
    
    /**
     * Getter for the DB module name
     * 
     * @return string
     */
    public function get_module_id() {
        return $this->moduleobject->get_module_id();
    }
    
    /**
     * Adds an associative array of parameters to the query
     * 
     * @param array $params 
     * @param bool $prefix do we want to make these paremters unique to this module? Use false if get_in_or_equal()
     *                     has been used
     * @return void
     */
    public function add_params(array $params, $prefix = true) {
        // TODO throw error if same keys.
        
        // Avoid collisions by prefixing all key names unless otherwise specified
        if ($prefix) {
            foreach ($params as $oldkey => $value) {
                $newkey = $this->prefix_param_name($oldkey);
                $params[$newkey] = $value;
                unset($params[$oldkey]);
            }
        }
        
        $this->params = array_merge($this->params, $params);
    }

    /**
     * Will check to see if any of the select clauses have aggregates
     * 
     * @return bool
     */
    protected function has_select_aggregate() {
        
        foreach ($this->select as $select) {
            if (isset($select['function'])) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Get all the bits and join them together, then return a query ready to use
     * 
     * @return array query string, then params 
     */
    public function to_string() {
        
        // Add all the standard filters?
//        list($countfrom, $countwhere, $countparams)       = $this->get_sql_count();
//        list($displayjoin, $displaywhere)                 = $this->get_sql_display_settings();
//        list($enroljoin, $enrolwhere, $enrolparams)       = $this->get_sql_enrolled_students();
//        list($visiblejoin, $visiblewhere, $visibleparams) = $this->get_sql_visible();
//        
//        $query['from'] = $countfrom.
//                         $enroljoin.
//                         $visiblejoin.
//                         $displayjoin;
//                       
//        $query['where'] = $countwhere.
//                          $displaywhere.
//                          $enrolwhere.
//                          $visiblewhere;
//                
//        $query['params'] = array_merge($countparams, $enrolparams, $visibleparams);
        
        
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
    
    public function get_capability() {
        return $this->moduleobject->get_capability();
    }

        /**
     * Returns the name of the userid column of the submissions table. Currently either userid or authorid.
     * This first method entirely relies on one column being defined with 'userid' as the alias
     * 
     * @return string table.column
     */
    public function get_userid_column() {
        // possibly too ambitious as the selects are defined
//        return $this->select['userid']['table'].'.'.$this->select['userid']['column'];
        
        return $this->useridcolumn;
    }

    /**
     * Runs the query and returns the result
     * 
     * @todo check the query for completeness first e.g. all select tables are present in the joins
     * @global moodle_database $DB 
     */
    public function execute() {
        global $DB;
        return $DB->get_records_sql($this->to_string(), $this->get_params());
    }
    
    
    
    /**
     * Prepares the query with the stuff it always needs
     * 
     * @return void
     */
//    public function apply_standard_filters() {
//        $this->apply_sql_enrolled_students($this);
//        $this->apply_sql_visible($this);
//        $this->apply_sql_display_settings($this);
//        $this->apply_sql_owncourses($this);
//    }
    
    
    
    /**
     * Provides an EXISTS(xxx) subquery that tells us whether there is a group with user x in it
     * 
     * @param string $configalias this is the alias of the config table in the SQL
     * @return string SQL fragment 
     */
    private function get_sql_groups_subquery($configalias) {
        
//        $submissiontablealias = $this->get_sql_submission_table();
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
     * Looks to the parent module to make any specific changes
     * 
     * @return void
     */
    public function alter_query_hook($groupby = false) {
        $this->moduleobject->alter_query_hook($this, $groupby);
    }

}



?>
