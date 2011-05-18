<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

abstract class block_ajax_marking_query_decorator extends block_ajax_marking_abstract_query {
    
    protected $wrappedquery;
    
    public function __construct(abstract_query $wrappedquery) {
        $this->wrappedquery = $wrappedquery;
    }
    
    // TODO make these generically alter the wrapped query's result with class variables holding the extra bits
    
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
    
    /**
     * Magic method allows 
     */
    public function __call($name, $arguments) {
        
        // If called method doesn't exist, try it on the wrapped query.
        if (!method_exists($this, $name)) {
            return call_user_func_array($this->wrappedquery->$name, $arguments);
        }
        
        // If it doesn't, throw an error
        
    }
    
}

?>
