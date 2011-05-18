<?php

/**
 * Base class for core queries and the decorator classes that will add filters to them
 */
class block_ajax_marking_abstract_query {
    
    protected $select = array();
    
    protected $from = array();
    
    protected $where = array();
    
    protected $groupby = array();
    
    protected $orderby = array();
    
    
    
    public function __construct() {
        
    }
    
    public function get_select() {
        return $this->select;
    }
    
    public function get_from() {
        return $this->from;
    }
    
    public function get_where() {
        return $this->where;
    }
    
    public function get_groupby() {
        return $this->groupby;
    }
    
    public function get_orderby() {
        return $this->orderby;
    }
    
    
}

?>
