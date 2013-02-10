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
 * Class file for module_base
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2008 Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die();
}

/**
 * This class forms the basis of the objects that hold and process the module data. The aim is for
 * node data to be returned ready for output in JSON or HTML format. Each module that's active will
 * provide a class definition in it's modname_grading.php file, which will extend this base class
 * and add methods specific to that module which can return the right nodes.
 */
abstract class block_ajax_marking_module_base {

    /**
     * The name of the module as it appears in the DB modules table
     *
     * @var string
     */
    public $modulename;

    /**
     * The id of the module in the database
     *
     * @var int
     */
    public $moduleid;

    /**
     * The capability that determines whether a user can grade items for this module
     *
     * @var string
     */
    public $capability;

    /**
     * The url of the icon for this module
     *
     * @var string
     */
    public $icon;

    /**
     * An array showing what callback functions to use for each ajax request
     *
     * @var array
     */
    public $functions;

    /**
     * The items that could potentially have graded work
     *
     * @var array of objects
     */
    public $assessments;

    /**
     * This will hold an array of totals keyed by courseid. Each total corresponds to the number of
     * unmarked submissions that course has for modules of this type.
     *
     * @var array of objects
     */
    protected $coursetotals = null;

    /**
     * Name of the table that this module stores it's instances in. Needed so that we can join to it
     * from apply_coursemoduleid_filter(). At the moment it will always be the same as $modulename,
     * but could conceivably vary.
     *
     * @var string
     */
    protected $moduletable;

    /**
     * Constructor. Overridden by all subclasses.
     */
    public function __construct() {

    }

    /**
     * Returns the name of this module as used in the DB
     *
     * @return string
     */
    public function get_module_name() {
        return $this->modulename;
    }

    /**
     * Getter function for the capability allowing this module's submissions to be graded
     *
     * @return string
     */
    public function get_capability() {
        return $this->capability;
    }

    /**
     * Find the id of this module in the DB. It may vary from site to site
     *
     * @param bool $reset clears the cache
     * @return int the id of the module in the DB
     */
    public function get_module_id($reset=false) {

        global $DB;

        if (isset($this->moduleid) && !empty($this->moduleid) && !$reset) {
            return $this->moduleid;
        }

        $this->moduleid = $DB->get_field('modules', 'id', array('name' => $this->modulename));

        if (empty($this->moduleid)) {
            debugging('No module id for '.$this->modulename);
        }

        return $this->moduleid;
    }

    /**
     * Checks whether the user has grading permission for this assessment
     *
     * @param object $assessment a row from the db
     * @return bool
     */
    protected function permission_to_grade($assessment) {

        global $USER;

        $context = context_module::instance($assessment->cmid);

        if (has_capability($this->capability, $context, $USER->id, false)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns a query object that has been set up to retrieve all unmarked submissions for this
     * teacher and this (subclassed) module
     *
     * @return block_ajax_marking_query
     */
    abstract public function query_factory();

    /**
     * Sometimes there will need to be extra processing of the nodes that is specific to this module
     * e.g. the title to be displayed for submissions needs to be formatted with firstname and
     * lastname in the way that makes sense for the user's chosen language.
     *
     * This function provides a default that can be overridden by the subclasses.
     *
     * @param array $nodes Array of objects
     * @param array $filters as sent via $_POST
     * @internal param string $nodetype the name of the filter that provides the SELECT statements
     * for the query
     * @return array of objects - the altered nodes
     */
    public function postprocess_nodes_hook($nodes, $filters) {

        foreach ($nodes as &$node) {

            // Just so we know (for styling and accessing js in the client).
            $node->modulename = $this->modulename;

            $nextnodefilter = block_ajax_marking_get_nextnodefilter_from_params($filters);

            switch ($nextnodefilter) {

                case 'userid':
                    // Sort out the firstname/lastname thing.
                    $node->name = fullname($node);
                    unset($node->firstname, $node->lastname);

                    $node->tooltip = userdate($node->tooltip);

                    break;

                default:
                    break;
            }
        }

        return $nodes;

    }

    /**
     * This function will take the data returned by the grading popup and process it. Not always
     * implemented as not all modules have a grading popup yet
     *
     * @param $data
     * @param $params
     * @return string
     */
    abstract public function process_data($data, $params);

    /**
     * Makes the contents of the pop up grading window
     *
     * @param $params
     * @param $coursemodule
     * @return string HTML
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    abstract public function grading_popup(array $params, $coursemodule);
}
