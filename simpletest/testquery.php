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
 * Class file for the block_ajax_marking_nodes_factory class
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2011 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); //  It must be included from a Moodle page
}

global $CFG;
/**
 *
 */
require_once($CFG->dirroot.'/blocks/ajax_marking/classes/nodes_factory.class.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/classes/query_base.class.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/classes/module_base.class.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/lib.php');


class block_ajax_marking_query_test extends UnitTestCaseUsingDatabase {

    /**
     * @var block_ajax_marking_module_base[] holds the instantiated module classes so we can
     * use them to make module specific tests
     */
    public $moduleclasses;

    /**
     * @var array Shows what modules need to have an mform for the module creation process to work
     */
    private $modules_needing_mform = array('glossary', 'lesson');
    private $glossary_formats = array('continuous',
                                      'encyclopedia',
                                      'entrylist',
                                      'faq',
                                      'fullwithauthor',
                                      'fullwithoutauthor',
                                      'dictionary');
    private $forum_types = array('general');
    public $scales; // others include 'single', 'eachuser', 'qanda'


    /**
     * This will create a shared test environment with a course, some users, some enrolments, etc
     *
     * @return void
     */
    public function setUp() {

        global $DB;

        // Get the module classes so we can use them to set things up
        $this->moduleclasses = block_ajax_marking_get_module_classes();

        $this->switch_to_test_db();

        // Make all the tables we will need
        // courses, users, enrolments, contexts,
        $tablestomake = array('course_categories' => 'lib',
                              'course' => 'lib',
                              'log' => 'lib',
                              'course_sections' => 'lib',
                              'course_modules' => 'lib',
                              'filter_active' => 'lib',
                              'filter_config' => 'lib',
                              'cache_text' => 'lib',
                              'cache_flags' => 'lib',
                              'config_plugins' => 'lib',
                              'role_assignments' => 'lib',
                              'role_capabilities' => 'lib',
                              'events_handlers' => 'lib',
                              'event' => 'lib',
                              'grade_items' => 'lib',
                              'grade_categories' => 'lib',
                              'grade_categories_history' => 'lib',
                              'grade_items_history' => 'lib',
                              'modules' => 'lib',
                              'scale' => 'lib',
                              'files' => 'lib',
                              'block' => 'lib',
                              'block_instances' => 'lib',
                              'context' => 'lib',

                              'workshopform_accumulative' => 'mod/workshop/form/accumulative',
        );
        foreach ($tablestomake as $table => $file) {
            $this->create_test_table($table, $file);
        }

        // Make a copy of the basic site stuff from the main DB
        $blocktoget = array('site_main_menu',
                            'course_summary',
                            'news_items',
                            'calendar_upcoming',
                            'recent_activity',
                            'calendar_month',
                            'search_forums');
        $this->revert_to_real_db();
        $retrievedblocks = array();
        foreach ($blocktoget as $block) {
            $retrievedblocks[] = $DB->get_record('block', array('name' => $block));
        }
        $misccategory = $DB->get_record('course_categories', array('id' => 1));
        $sitecontext = $DB->get_record('context', array('id' => 1));
        $frontcourse = $DB->get_record('course', array('id' => 1));
        $retrievedmodules = array();
        foreach ($this->moduleclasses as $moduleclass) {
            $retrievedmodules[] =
                $DB->get_record('modules', array('name' => $moduleclass->get_module_name()));
        }
        $scales = $DB->get_records_select('scale', "courseid = 0", array());


        // Put the stuff into the unit test DB
        $this->switch_to_test_db();
        foreach ($retrievedblocks as $block) {
            $DB->insert_record('block', $block);
        }
        $testmodules = array();
        foreach ($retrievedmodules as &$module) {
            $module->id = $DB->insert_record('modules', $module);
            $testmodules[$module->name] = $module;
        }
        foreach ($this->moduleclasses as $moduleclass) {
            $modname = $moduleclass->get_module_name();
            $this->create_test_table($modname, 'mod/'.$modname);
        }
        foreach ($scales as &$scale) {
            $scale->id = $DB->insert_record('scale', $scale);
        }
        $this->scales = $scales;
        $misccategory->id = $DB->insert_record('course_categories', $misccategory);
        $sitecontext->id = $DB->insert_record('context', $sitecontext);
        $misccontext = create_context(CONTEXT_COURSECAT, $misccategory->id);
        $frontcourse->id = $DB->insert_record('course', $frontcourse);

        // Make a new course
        $count = 0;
        $data = new stdClass();
        $data->category = $misccategory->id;
        $data->shortname = 'Test course '.$count.' '.date("j F, Y, g:i a");
        $data->fullname = 'Test course '.$count.' '.date("j F, Y, g:i a");
        $options = array();
        $testcourse = create_course($data, $options);


        // Make a new module
        /**
         * @var block_ajax_marking_module_base $testmoduleclass
         */
        $testmoduleclass = reset($this->moduleclasses);
        $this->make_module($testmodules['workshop'], $testcourse);


        // Make some new users

        // Make the current user into the teacher

        // Enrol the others

        // Make submissions

    }

    public function test_assignment_enrol() {

    }

    /**
     * This will generate one instance of a specific module and it's associated coursemodule and
     * attach it to the supplied course.
     *
     * @param $moduledata
     * @param $course
     * @throws coding_exception
     */
    private function make_module($moduledata, $course) {

        global $CFG, $DB;

        $libfile = "$CFG->dirroot/mod/$moduledata->name/lib.php";

        if (file_exists($libfile)) {

            require_once($libfile);

            // some need a dummy mform for the instance_add method. Include the library here
            if (array_search($moduledata->name, $this->modules_needing_mform) !== false) {
                $mod_form_lib = $CFG->dirroot.'/mod/'.$moduledata->name.'/mod_form.php';

                if (file_exists($mod_form_lib)) {
                    require_once($mod_form_lib);
                }
            }
        } else {
            throw new coding_exception("Could not load lib file for module $moduledata->name!");
        }

        // Basically 2 types of text fields: description and content
        $description =
            "This $moduledata->name has been randomly generated for unit testing the ajax_marking block";
        $content = 'Should never be seen';

        $module = new stdClass();

        // Special module-specific config
        switch ($moduledata->name) {

            case 'assignment':
                $module->intro = $description;
                $assignmenttypes = array('upload', 'uploadsingle', 'online', 'offline');
                $module->assignmenttype = $assignmenttypes[array_rand($assignmenttypes)];
                $module->timedue = mktime() + 89487321;
                $module->grade = rand(50, 100);
                $module->type = 'online';
                break;

            case 'data':
                $module->intro = $description;
                $module->name = 'test';
                break;

            case 'comments':
                $module->intro = $description;
                $module->comments = $content;
                break;

            case 'feedback':
                $module->intro = $description;
                $module->page_after_submit = $description;
                $module->comments = $content;
                break;

            case 'forum':
                $module->intro = $description;
                $forumtypes = array('general');
                $module->type = $forumtypes[array_rand($forumtypes)]; // others include 'single', 'eachuser', 'qanda'
                $module->forcesubscribe = rand(0, 1);
                $module->assessed = 1;
                $module->scale = 5;
                $module->format = 1;
                break;

            case 'glossary':
                $module->intro = $description;
                $module->displayformat =
                    $this->glossary_formats[rand(0, count($this->glossary_formats) - 1)];
                $module->cmidnumber = rand(0, 999999);
                $module->assessed = 1;
                $module->scale = $this->scales[array_rand($this->scales)]->id; // TODO broken?
                break;

            case 'lesson':
                $module->lessondefault = 1;
                $module->available = mktime();
                $module->deadline = mktime() + 719891987;
                $module->grade = 100;
                //$module->instance = false;
                break;

            case 'quiz':
                $module->intro = $description;
                $module->feedbacktext = array(
                    array(
                        'text' => 'feeback for first level',
                        'format' => FORMAT_MOODLE,
                        'itemid' => false),
                    array(
                        'text' => 'feedback for second level',
                        'format' => FORMAT_MOODLE,
                        'itemid' => false)
                );
                $module->feedback = 1;
                $module->feedbackboundaries = array(2);
                $module->grade = 10;
                $module->timeopen = time();
                // Close quiz after a week
                $module->timeclose = time() + 604800;
                $module->shufflequestions = true;
                $module->shuffleanswers = true;
                $module->quizpassword = '';
                $module->questionsperpage = 0;
                break;

            case 'workshop':
                // Not marked as required in the Moodle form, but causes error without it.
                // needs to be an array in the new editors format
                $module->grade = rand(50, 100);
                $module->gradinggrade = rand(0, $module->grade);
                $module->strategy = 'accumulative';
                $module->gradingdecimals = 0;
                $module->usepeerassessment = 1;

                $grades = workshop::available_maxgrades_list();
                $module->gradecategory = array_rand($grades);
                $gradecategories = grade_get_categories_menu($course->id);
                $module->gradinggradecategory = array_rand($gradecategories);


                $module->intro = '<p>Introduction text would go here</p>';
                $module->instructauthorseditor = array(
                    'text' => '<p>Instructions for submission would go here</p>',
                    'format' => 1,
                    'itemid' => 798797
                );
                $module->instructreviewerseditor = array(
                    'text' => '<p>Instructions for asessment would go here</p>',
                    'format' => 1,
                    'itemid' => 798797
                );
                break;
        }

        // Standard stuff
        $module->introformat = FORMAT_MOODLE;
        $module->messageformat = FORMAT_MOODLE;
        $module->completion = COMPLETION_DISABLED;
        $module->completionview = COMPLETION_VIEW_NOT_REQUIRED;
        $module->completiongradeitemnumber = null;
        $module->instance = '';
        $module->name = ucfirst($moduledata->name).' '.$moduledata->count++;
        $module->course = $course->id;
        $module->module = $moduledata->id;
        $module->modulename = $moduledata->name;
        $module->add = $moduledata->name;
        $module->section = 1; // only need one section in use as it's a test
        $module->groupmode = 0;
        $module->groupingid = 0;
        $module->groupmembersonly = 0;

        // first add course_module record because we need the context.
        // Lifted from /course/modedit.php
        $newcm = new stdClass();
        $newcm->course = $course->id;
        $newcm->module = $moduledata->id;
        $newcm->instance = 0; // not known yet, will be updated after instance is created
        $newcm->visible = 1;

        $module->coursemodule = $newcm->id = add_course_module($newcm);
        $module->cmidnumber = set_coursemodule_idnumber($module->coursemodule, '');

        $add_instance_function = $moduledata->name.'_add_instance';

        if (function_exists($add_instance_function)) {

            $dummymform = new stdClass;

            // Don't always make the form as some modules e.g. assignment look for GET
            // parameters, which it doesn't have when called from this script.
            if (array_search($moduledata->name, $this->modules_needing_mform) !== false) {
                // needed to stand in for the module edit form
                $mformclassname = 'mod_'.$moduledata->name.'_mod_form';

                if (class_exists($mformclassname)) {
                    $dummycourse = new stdClass;
                    $dummycourse->id = $course->id;
                    $dummycourse->maxbytes = 41943040;
                    $dummymform = new $mformclassname($module, 1, null, $dummycourse);
                }
            }

            // Make the module instance
            $module->instance = $add_instance_function($module, $dummymform);
            $newcm->instance = $DB->set_field('course_modules', 'instance', $module->instance,
                                              array('id' => $module->coursemodule));

        } else {
            throw new coding_exception("Function $add_instance_function does not exist!");
        }

        // needs section as number in course, not id in section.
        // expects coursemodule id as the coursemodule property
        $module->section = add_mod_to_section($module);

        $DB->set_field('course_modules', 'section', $module->section,
                       array('id' => $module->coursemodule));

        rebuild_course_cache($course->id);

        $module->id =
            $DB->get_field('course_modules', 'instance', array('id' => $module->coursemodule));
        $module_record = $DB->get_record($moduledata->name, array('id' => $module->id));
        //$module_record->instance = $module_instance;

        if (empty($modules_array[$moduledata->name])) {
            $modules_array[$moduledata->name] = array();
        }

        // Extra stuff needed for specific modules, currently workshop

        if ($moduledata->name == 'workshop') {

            // This is to make the dimensions for the workshop

            $workshop = $DB->get_record('workshop', array('id' => $module->id), '*', MUST_EXIST);
            $workshopobject = new workshop($workshop, $newcm, $course);
            $strategy = $workshopobject->grading_strategy_instance();

            $assessmentformdata = new stdClass;
            $assessmentformdata->workshopid = $workshop->id;
            $assessmentformdata->strategy = $workshop->strategy;
            $assessmentformdata->norepeats = 3;
            $assessmentformdata->saveandclose = 'Save and close';

            for ($k = 0; $k < 3; $k++) {
                $propertyname = 'dimensionid__idx_'.$k;
                $assessmentformdata->{$propertyname} = '';

                $propertyname = 'description__idx_'.$k.'_editor';
                $assessmentformdata->{$propertyname} = array(
                    'text' => 'Aspect '.$k.' description text',
                    'format' => FORMAT_HTML,
                    'itemid' => false
                );

                $propertyname = 'grade__idx_'.$k;
                $assessmentformdata->{$propertyname} = 10;

                $propertyname = 'weight__idx_'.$k;
                $assessmentformdata->{$propertyname} = 1;
            }

            /**
             * @var workshop_strategy $strategy
             */
            $strategy->save_edit_strategy_form($assessmentformdata);

            // Set it to submisions phase
            $workshop->phase = 20;
            $DB->update_record('workshop', $workshop);

        }
    }


}
