<?php

class block_ajax_marking_output {

    // Hold the output data as a nested set of arrays
    private $output;
    //remembers where the last node to be added was. Takes the form of nested array keys e.g. '[1][2][5]'
    private $pointer;

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct() {
        $this->output = '';
    }

    /**
     * starts a list of sub-nodes
     */
    public function start_node() {
        if (substr($this->output, -1) == '}') {
            $this->output .= ',';
        }
        $this->output .= '{';
    }

    public function end_node() {
        $this->output .= '}';
    }

    /**
     * Adds a seried of key => value pairs to the json string
     *
     * @param array $newnode a collection of key => value pairs
     * @return void
     */
    public function add_json_node($newnode) {

        if (count ($newnode) > 0) {

            $first = true;

            foreach ($newnode as $key => $value) {

                if (!$first) {
                    $this->output .= ',';
                } else {
                    $first = false;
                }

                $this->output .= '"'.$key.'":"'.$value.'"';

            }
        }
    }

    /**
     * Sets the type of the data which is beig returned
     *
     * @param string $type
     * @return void
     */
    public function set_type($type) {
        $this->data->type = $type;
    }


    /**
     * Will return the output array in json format. Needs to iterate recursively over $output
     */
    public function json_render() {

        if (count($this->outputtree) > 0) {
            //start the data
            $output = '[';
            $first = true;

            Foreach ($this->outputtree as $key => $node) {

                if (!$first) {
                    $output .= ',';
                    $first = false;
                }
                // recurive approach renders the whole tree with this function
                if ($key == 'subnodes') {

                    $this->ajax_render($node);

                } else {

                    // render this node's data
                    $output .= '{"'.$key.'":"'.$$node.'"}';
                }
            }

            // end the data
            $output .= ']';
            return $output;

        } else {
            return false;
        }
    }

    public function log_event($text) {
        global $CFG;
        if ($CFG->debug == DEBUG_DEVELOPER) {
            $this->data->log .= $text.PHP_EOL;
        }
    }

    /**
     * returns the final output string
     */
    public function get_output() {

        $final = '[';
        $final .= $this->start_node();
        $final .= $this->add_json_node($this->data);
        $final .= $this->end_node();
        $final .= $this->output;
        $final .= ']';

    }

}