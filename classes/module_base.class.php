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

if (!defined('MOODLE_INTERNAL')) {
    die();
}

/**
 * This class forms the basis of the objects that hold and process the module data. The aim is for
 * node data to be returned ready for output in JSON or HTML format. Each module that's active will
 * provide a class definition in it's modname_grading.php file, which will extend this base class
 * and add methods specific to that module which can return the right nodes.
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2008 Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
     * This returns the column name in the submissions table that holds the userid. It varies
     * according to module, but the default is here. Other modules can override if they need to
     *
     * @return string
     */
    protected function get_sql_userid_column() {
        return 'sub.userid';
    }

    /**
     * Returns the name of this module as used in the DB
     *
     * @return string
     */
    protected function get_module_name() {
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
     * This is to allow the ajax call to be sent to the correct function. When the
     * type of one of the pluggable modules is sent back via the ajax call, the
     * ajax_marking_response constructor will refer to this function in each of the module objects
     * in turn from the default in the switch statement
     *
     * @param string $type the type name variable from the ajax call
     * @param array $args
     * @return bool
     */
    public function return_function($type, $args) {

        if (array_key_exists($type, $this->functions)) {
            $function = $this->functions[$type];
            call_user_func_array(array($this, $function), $args);
            return true;
        } else {
            return false;
        }
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

        $context = get_context_instance(CONTEXT_MODULE, $assessment->cmid);

        if (has_capability($this->capability, $context, $USER->id, false)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Checks the functions array to see if this module has a function that corresponds to it
     *
     * @param string $callback
     * @return bool
     */
    public function contains_callback($callback) {
        return method_exists($this, $callback);
    }

    /**
     * This will find the module's javascript file and add it to the page. Used by the main block.
     *
     * @return void
     */
    public function include_javascript() {

        global $CFG, $PAGE;

        $blockdirectoryfile = "/blocks/ajax_marking/{$this->modulename}_grading.js";
        $moddirectoryfile   = "/mod/{$this->modulename}/{$this->modulename}_grading.js";

        if (file_exists($CFG->dirroot.$blockdirectoryfile)) {

            $PAGE->requires->js($blockdirectoryfile);

        } else {

            if (file_exists($CFG->dirroot.$moddirectoryfile)) {
                $PAGE->requires->js($moddirectoryfile);
            }
        }
    }

    /**
     * The submissions nodes may be aggregating actual work so that it is easier to view/grade e.g.
     * seeing a whole forum discussion at once because posts are meaninless without context. This
     * allows modules to override the default label text of the node, which is the user's name.
     *
     * @param object $submission
     * @param int $moduleid
     * @return string
     */
    protected function submission_title(&$submission, $moduleid=null) {
        $title = fullname($submission);
        unset($submission->firstname, $submission->lastname);
        return $title;
    }

    /**
     * Returns a query object that has been set up to retrieve all unmarked submissions for this
     * teacher and this (subclassed) module
     *
     * @param bool $callback
     * @return block_ajax_marking_query_base
     */
    abstract public function query_factory($callback = false);

    /**
     * Sometimes there will need to be extra processing of the nodes that is specific to this module
     * e.g. the title to be displayed for submissions needs to be formatted with firstname and
     * lastname in the way that makes sense for the user's chosen language.
     *
     * This function provides a default that can be overidden by the subclasses.
     *
     * @param array $nodes Array of objects
     * @param array $filters as sent via $_POST
     * @internal param string $nodetype the name of the filter that provides the SELECT statements
     * for the query
     * @return array of objects - the altered nodes
     */
    public function postprocess_nodes_hook($nodes, $filters) {

        foreach ($nodes as &$node) {

            // just so we know (for styling and accessing js in the client)
            $node->modulename = $this->modulename;

            switch ($filters['nextnodefilter']) {

                case 'userid':
                    // Sort out the firstname/lastname thing
                    $node->name = fullname($node);
                    unset($node->firstname, $node->lastname);

                    break;

                default:
                    break;
            }
        }

        return $nodes;

    }

    /**
     * Getter for the module
     * @return string
     */
    public function get_module_table() {
        return $this->moduletable;
    }

    /**
     * This function will take the data returned by the grading popup and process it. Not always
     * implemented as not all modules have a grading popup yet
     *
     * @return void
     */
    public function process_data() {

    }

}