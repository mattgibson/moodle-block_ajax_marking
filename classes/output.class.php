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
 * This is the beginning of an as yet unused output renderer
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2011  Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class block_ajax_marking_output {

    // Hold the output data as a nested set of arrays.
    private $output;

    private $outputtree;

    /**
     * Constructor
     *
     * @return \block_ajax_marking_output
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
     * Adds a series of key => value pairs to the json string
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
     * Sets the type of the data which is being returned
     *
     * @param string $type
     * @return void
     */
    public function set_type($type) {
        $this->data->type = $type;
    }


    /**
     * Will return the output array in json format. Needs to iterate recursively over $output
     *
     * @return bool|string
     */
    public function json_render() {

        if (count($this->outputtree) > 0) {
            // Start the data.
            $output = '[';
            $first = true;

            foreach ($this->outputtree as $key => $node) {

                if (!$first) {
                    $output .= ',';
                    $first = false;
                }
                // Recursive approach renders the whole tree with this function.
                if ($key == 'subnodes') {

                    $this->ajax_render($node);

                } else {

                    // Render this node's data.
                    $output .= '{"'.$key.'":"'.$$node.'"}';
                }
            }

            // End the data.
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
     *
     * @return string
     */
    public function get_output() {

        $final = '[';
        $final .= $this->start_node();
        $final .= $this->add_json_node($this->data);
        $final .= $this->end_node();
        $final .= $this->output;
        $final .= ']';

        return $final;

    }

}
