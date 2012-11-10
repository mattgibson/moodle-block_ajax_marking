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
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2012 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot.'/enrol/locallib.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/lib.php');
require_once($CFG->dirroot.'/mod/assign/lib.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/tests/block_ajax_marking_mod_assign_generator.class.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/tests/block_ajax_marking_mod_quiz_generator.class.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/tests/block_ajax_marking_mod_workshop_generator.class.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/classes/nodes_builder_base.class.php');

/**
 * Unit test for the nodes_builder class.
 */
class test_nodes_builder_base extends advanced_testcase {

    /**
     * @var stdClass
     */
    protected $course;

    /**
     * @var array of stdClass objects
     */
    protected $students;

    /**
     * @var array of stdClass objects
     */
    protected $teachers;

    /**
     * @var int How many submissions ought to come back
     */
    protected $submissioncount;

    /**
     * @var array of assign module instances made by the generator.
     */
    protected $assigns;

    /**
     * Gets a blank course with 2 teachers and 10 students ready for each test.
     */
    protected function setUp() {

        global $PAGE, $DB;

        // First test will set up DB submissions, so we keep it hanging around for the others.
        $this->resetAfterTest();

        // Make a course.
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();

        $this->setAdminUser();
        $PAGE->set_course($this->course);

        $manager = new course_enrolment_manager($PAGE, $this->course);
        $plugins = $manager->get_enrolment_plugins();
        $instances = $manager->get_enrolment_instances();
        /* @var enrol_manual_plugin $manualenrolplugin */
        $manualenrolplugin = reset($plugins);
        $manualenrolinstance = reset($instances);

        $studentroleid = $DB->get_field('role', 'id', array('shortname' => 'student'));
        $teacherroleid = $DB->get_field('role', 'id', array('shortname' => 'teacher'));

        // Make some students.
        for ($i = 0; $i < 10; $i++) {
            $student = $generator->create_user();
            $this->students[$student->id] = $student;
            $manualenrolplugin->enrol_user($manualenrolinstance, $student->id, $studentroleid);
        }

        // Make a couple of teachers.
        $teacher1 = $generator->create_user();
        $this->teachers[$teacher1->id] = $teacher1;
        $manualenrolplugin->enrol_user($manualenrolinstance, $teacher1->id, $teacherroleid);
        $teacher2 = $generator->create_user();
        $this->teachers[$teacher2->id] = $teacher2;
        $manualenrolplugin->enrol_user($manualenrolinstance, $teacher2->id, $teacherroleid);

    }

    /**
     * Makes data for all the modules:
     * - one assign and 10 submissions
     * - 4 assignments and 40 submissions
     * - 5 forums and 500 forum posts
     * - 1 quiz with one question and 10 answers
     * - 1 workshop with 10 submissions
     *
     * Submissions are counted and stored as $this->submissioncount.
     *
     * @param array $modstoinclude the modules to mke submissions for. Default: all.
     * @return int
     */
    private function make_module_submissions($modstoinclude = array()) {

        global $DB;

        $submissioncount = 0;

        // Assignment module is disabled in the PHPUnit DB, so we need to re-enable it.
        $DB->set_field('modules', 'visible', 1, array('name' => 'assignment'));

        $classes = block_ajax_marking_get_module_classes(true);

        foreach ($classes as $modclass) {

            $modname = $modclass->get_module_name();

            if (!empty($modstoinclude) && !in_array($modname, $modstoinclude)) {
                continue;
            }

            // We need some submissions, but these are different for every module.
            // Without a standardised way of doing this, we will use methods in this class to do
            // the job until a better way emerges.
            $createdatamethod = 'create_'.$modname.'_submission_data';
            if (method_exists($this, $createdatamethod)) {
                // Let the modules decide what number of things should be expected. Some are more
                // complex than others.
                $count = $this->$createdatamethod();
                $submissioncount += $count;

            }
        }

        return $submissioncount;
    }

    /**
     * For each module, we need to see if we can actually get any data back using the query from
     * the module's query factory. Possible problem with third (fourth?) party module access code,
     * so check first to see if the generator can handle making one to test with.
     */
    public function test_module_query_factories() {

        global $DB;

        // Assignment module is disabled in the PHPUnit DB, so we need to re-enable it.
        $DB->set_field('modules', 'visible', 1, array('name' => 'assignment'));

        $classes = block_ajax_marking_get_module_classes();

        foreach ($classes as $modclass) {

            $modname = $modclass->get_module_name();

            // We need some submissions, but these are different for every module.
            // Without a standardised way of doing this, we will use methods in this class to do
            // the job until a better way emerges.
            $createdatamethod = 'create_'.$modname.'_submission_data';
            if (method_exists($this, $createdatamethod)) {
                // Let the modules decide what number of things should be expected. Some are more
                // complex than others.
                $expectedcount = $this->$createdatamethod();

                if (empty($expectedcount)) {
                    continue;
                }
            } else {
                // No point carrying on without some data to check.
                continue;
            }

            // Make query.
            $query = $modclass->query_factory();
            // We will get an error if we leave it like this as the userids in the first
            // column are not unique.
            $wrapper = new block_ajax_marking_query_base();
            $wrapper->add_select(array('function' => 'COUNT',
                                       'column' => '*',
                                       'alias' => 'count'));
            $wrapper->add_from(array('table' => $query,
                                     'alias' => 'modulequery'));

            // Run query. Get one stdClass with a count property.
            $unmarkedstuff = $wrapper->execute();

            // Make sure we get the right number of things back.
            $this->assertEquals($expectedcount, reset($unmarkedstuff)->count);

        }
    }

    /**
     * Makes submissions that ought to be picked up by test_basic_module_retrieval() as well as
     * a few others that shouldn't be. This is for the 2.2 and earlier assignment module.
     *
     * @return int how many to expect
     */
    private function create_assignment_submission_data() {

        $submissioncount = 0;
        // Provide defaults to prevent IDE griping.
        $student = new stdClass();
        $student->id = 3;

        /* @var phpunit_module_generator $assignmentgenerator */
        $assignmentgenerator = $this->getDataGenerator()->get_plugin_generator('mod_assignment');
        $assignments = array();
        $assignmentrecord = new stdClass();
        $assignmentrecord->course = $this->course->id;
        $assignmentrecord->timedue = strtotime('1 hour ago');
        // Make offline assignment to confuse things.
        $assignmentrecord->assignmenttype = 'offline';
        $assignments[] = $assignmentgenerator->create_instance($assignmentrecord);
        // Make online text assignment.
        $assignmentrecord->assignmenttype = 'online';
        $assignments[] = $assignmentgenerator->create_instance($assignmentrecord);
        // Make single file submission assignment.
        $assignmentrecord->assignmenttype = 'upload';
        $assignments[] = $assignmentgenerator->create_instance($assignmentrecord);
        // Make advanced upload assignment.
        $assignmentrecord->assignmenttype = 'uploadsingle';
        $assignments[] = $assignmentgenerator->create_instance($assignmentrecord);

        // Currently, we only have create_instance() in the generator, so we need to do this
        // from scratch.
        foreach ($assignments as $assignment) {

            foreach ($this->students as $student) {

                // Make new submission.
                // Stuff common to all types first.
                $submission = new stdClass();

                $submission->assignment = $assignment->id;
                $submission->userid = $student->id;

                // Now stuff that varies across types to alter the defaults.
                switch ($assignment->assignmenttype) {

                    case 'offline':
                        // Theoretically impossible, but good to check.
                        break;

                    case 'online':
                        $submission->data1 = 'Text of online essay here';
                        $submission->data2 = 1;
                        break;

                    case 'upload':
                        $submission->numfiles = 1;
                        $submission->data2 = 'submitted';
                        break;

                    case 'uploadsingle':
                        $submission->numfiles = 0;
                        $submission->data1 = '';
                        $submission->data2 = '';
                        break;
                }

                $this->make_assignment_submission($submission);

                // Add one to the count.
                if ($assignment->assignmenttype != 'offline') {
                    $submissioncount++;
                }
            }
        }

        // Make some data to test edge cases.

        // Deadline not passed. Does not matter. Ought to be picked up.
        $assignmentrecord = new stdClass();
        $assignmentrecord->assignmenttype = 'online';
        $assignmentrecord->course = $this->course->id;
        $assignmentrecord->timedue = strtotime('1 hour');
        $assignment = $assignmentgenerator->create_instance($assignmentrecord);
        $submission = new stdClass();
        $submission->assignment = $assignment->id;
        $submission->userid = $student->id; // One will be left over from the loop.
        $submission->data1 = 'Text of online essay here';
        $submission->data2 = 1;
        $this->make_assignment_submission($submission);
        $submissioncount++;

        // Not finalised.
        $assignmentrecord = new stdClass();
        $assignmentrecord->assignmenttype = 'upload';
        $assignmentrecord->course = $this->course->id;
        $assignment = $assignmentgenerator->create_instance($assignmentrecord);
        $submission = new stdClass();
        $submission->assignment = $assignment->id;
        $submission->userid = $student->id; // One will be left over from the loop.
        $submission->numfiles = 1;
        $submission->data2 = ''; // Should be 'submitted' to be picked up.
        $this->make_assignment_submission($submission);

        // Empty feedback?
        $assignmentrecord = new stdClass();
        $assignmentrecord->assignmenttype = 'upload';
        $assignmentrecord->course = $this->course->id;
        $assignment = $assignmentgenerator->create_instance($assignmentrecord);
        $submission = new stdClass();
        $submission->assignment = $assignment->id;
        $submission->userid = $student->id; // One will be left over from the loop.
        $submission->numfiles = 1;
        $submission->data2 = 'submitted';
        $submission->submissioncomment = '';
        $submission->format = 1;
        $submission->grade = -1;
        $this->make_assignment_submission($submission);
        $submissioncount++;

        $this->submissioncount += $submissioncount;

        return $submissioncount;
    }

    /**
     * Makes a fake assignment submission.
     *
     * @param stdClass $submissionrecord
     * @throws coding_exception
     * @return bool|\stdClass
     */
    private function make_assignment_submission($submissionrecord) {

        global $DB;

        if (!isset($submissionrecord->assignment)) {
            throw new coding_exception('Make submission needs an assignment id.');
        }
        if (!isset($submissionrecord->userid)) {
            throw new coding_exception('Make submission needs a user id.');
        }

        // Make new submission.
        // Stuff common to all types first.
        $submission = new stdClass();
        $submission->timecreated = time();
        $submission->timemodified = time();

        // Now defaults.
        $submission->numfiles = 0;
        $submission->data1 = null;
        $submission->data2 = null;
        $submission->grade = -1;
        $submission->submissioncomment = '';
        $submission->format = 0;
        $submission->teacher = 0;
        $submission->timemarked = 0;
        $submission->mailed = 0;

        $extended = (object)array_merge((array)$submission, (array)$submissionrecord);

        return $DB->insert_record('assignment_submissions', $extended);
    }

    /**
     * Makes forum discussions to be checked against the module query factory.
     *
     * @return int how many to expect.
     */
    private function create_forum_submission_data() {

        global $DB;

        $submissioncount = 0;
        // Provide defaults to prevent IDE griping.
        $student = new stdClass();
        $student->id = 3;

        // Make forums
        /* @var mod_forum_generator $forumgenerator */
        $forumgenerator = $this->getDataGenerator()->get_plugin_generator('mod_forum');
        $forumrecord = new stdClass();
        $forumrecord->assessed = 1;
        $forumrecord->scale = 4;
        $forumrecord->course = $this->course->id;

        $forumtypes = array(
            'single',
            'eachuser',
            'qanda',
            'blog',
            'general'
        );
        foreach ($forumtypes as $type) {
            $forumrecord->type = $type;
            if (isset($forumrecord->id)) {
                unset($forumrecord->id);
            }

            // Single post forums make a discussion and first post. Needs to be by student.
            $this->setUser($student->id);
            $forum = $forumgenerator->create_instance($forumrecord);
            $this->setAdminUser();

            if ($type == 'single') { // Generator will have made a related discussion.
                $submissioncount++;
            }
                // Make discussion. Make sure each student makes one (in case we violate
            // eachuser constraints).
            reset($this->students); // Reset counter due to nested loops.
            foreach ($this->students as $student) {

                $discussion = new stdClass();
                $discussion->course = $this->course->id;
                $discussion->forum = $forum->id;
                $discussion->userid = $student->id;
                $discussion->timemodified = strtotime('1 hour ago');
                $discussion->id = $DB->insert_record('forum_discussions', $discussion);

                $firstpost = new stdClass();
                $firstpost->discussion = $discussion->id;
                $firstpost->parent = 0;
                $firstpost->created = time();
                $firstpost->userid = $student->id;
                $firstpost->modified = time();
                $firstpost->subject = 'First post subject';
                $firstpost->message = 'First post message';
                $firstpost->id = $DB->insert_record('forum_posts', $firstpost);

                $discussion->firstpost = $firstpost->id;
                $DB->update_record('forum_discussions', $discussion);
                $submissioncount++;

                // Make some reply posts. Need to copy the array so we can have the pointer in two places at once.
                $tempstudents = $this->students;
                reset($tempstudents);
                foreach ($tempstudents as $replystudent) {
                    if ($replystudent->id == $student->id) {
                        // Eachuser won't like this.
                        continue;
                    }

                    $post = new stdclass();
                    $post->discussion = $discussion->id;
                    $post->parent = $firstpost->id; // All direct replies to the first post for simplicity.
                    $post->userid = $replystudent->id;
                    $post->created = time();
                    $post->modified = time();
                    $post->subject = 'blah';
                    $post->message = 'blah';

                    $post->id = $DB->insert_record('forum_posts', $post);
                    $submissioncount++;

                }
            }
        }

        $this->submissioncount += $submissioncount;

        return $submissioncount;
    }

    /**
     * Makes test submission data for the assign module. Leaves us with one assignment and a single submission
     * for each user.
     *
     * @return int how many things to expect.
     */
    private function create_assign_submission_data () {

        $submissioncount = 0;
        // Provide defaults to prevent IDE griping.
        $student = new stdClass();
        $student->id = 3;

        /* @var phpunit_module_generator $assigngenerator */
        $assigngenerator = new block_ajax_marking_mod_assign_generator($this->getDataGenerator());
        $assigns = array();
        $assignrecord = new stdClass();
        $assignrecord->assessed = 1;
        $assignrecord->scale = 4;
        $assignrecord->course = $this->course->id;
        $this->assigns[] = $assigngenerator->create_instance($assignrecord);

        foreach ($this->assigns as $assign) {
            foreach ($this->students as $student) {
                $submission = new stdClass();
                $submission->userid = $student->id;
                $submission->assignment = $assign->id;
                $assigngenerator->create_assign_submission($submission);
                $submissioncount++;
            }
        }

        $this->submissioncount += $submissioncount;

        return $submissioncount;

    }

    /**
     * Makes test submission data for the quiz module. One quiz, with one essay question and one answer per student.
     *
     * @return int how many things to expect.
     */
    private function create_quiz_submission_data() {

        $submissioncount = 0;

        // Provide defaults to prevent the IDE griping.
        $student = new stdClass();
        $student->id = 3;

        /* @var block_ajax_marking_mod_quiz_generator $quizgenerator */
        $quizgenerator = new block_ajax_marking_mod_quiz_generator($this->getDataGenerator());
        $quizrecord = new stdClass();
        $quizrecord->course = $this->course->id;
        $quiz = $quizgenerator->create_instance($quizrecord);

        $question = $quizgenerator->make_question($this->course->id);

        $quizgenerator->add_question_to_quiz($question->id, $quiz);

        foreach ($this->students as $student) {
            $this->setUser($student->id);
            $submissioncount += $quizgenerator->make_student_quiz_atttempt($student, $quiz);
        }

        $this->setAdminUser();

        $this->submissioncount += $submissioncount;

        return $submissioncount;
    }

    /**
     * Makes fake submission data for the workshop module so we can see if the block retrieves it
     * OK.
     */
    private function create_workshop_submission_data() {

        global $DB;

        $submissionscount = 0;

        // Make a workshop.
        $workshopgenerator = new block_ajax_marking_mod_workshop_generator($this->getDataGenerator());
        $workshoprecord = new stdClass();
        $workshoprecord->course = $this->course->id;

        $workshop = $workshopgenerator->create_instance($workshoprecord);

        // Make a submission for each student.
        foreach ($this->students as $student) {
            $submissionscount += $workshopgenerator->make_student_submission($student, $workshop);
        }

        // Take it into evaluation mode.
        $workshop->phase = workshop::PHASE_EVALUATION;
        $DB->update_record('workshop', $workshop);

        $this->submissioncount += $submissionscount;

        return $submissionscount;

    }

    /**
     * This function will run the whole query with all filters against a data set which ought
     * to all come back, i.e. none of the items will be intercepted by any filters.
     *
     * @todo different courses - one in, one out.
     */
    public function test_unmarked_nodes_basic() {

        // Make all the test data and get a total count back.
        $this->make_module_submissions();

        // Make sure the current user is a teacher in the course.
        $this->setUser(key($this->teachers));

        // Make a full nodes query. Doesn't work without a filter of some sort.
        $filters = array('courseid' => 'nextnodefilter');
        $nodes = block_ajax_marking_nodes_builder_base::unmarked_nodes($filters);

        $this->assertNotEmpty($nodes, 'No nodes returned at all');

        // Compare result.
        $actual = reset($nodes)->itemcount;
        $message = 'Wrong number of course nodes: '.$actual.' instead of '.$this->submissioncount;
        $this->assertEquals($this->submissioncount, $actual, $message);

        // Now try with coursemoduleid. Counts should be the same as we have only one course.
        $filters = array(
            'courseid' => $this->course->id,
            'coursemoduleid' => 'nextnodefilter'
        );
        $nodes = block_ajax_marking_nodes_builder_base::unmarked_nodes($filters);
        // Compare result.
        $actual = 0;
        foreach ($nodes as $node) {
            $actual += $node->itemcount;
        }
        $message = 'Wrong number of coursemodule nodes: '.$actual.' instead of '.$this->submissioncount;
        $this->assertEquals($this->submissioncount, $actual, $message);

    }

    /**
     * This will test to make sure that when we tell it to group by courseid, we get the right count back.
     */
    public function test_courseid_current() {

    }

    /**
     * This tests the function that takes a load of coursemodule nodes, then attaches the groups and each group's
     * current display status.
     *
     * Need to test:
     * - Get the groups that are there
     * - Group to have display 1 if no settings
     * - Group to have display 1/0 at course level with no coursemodule level setting
     *- Group to have 1/0 at coursemodule level if set, regardless of the course level setting.
     */
    public function test_attach_groups_to_coursemodule_nodes() {

        global $USER, $DB;

        // The setUp() leaves us with 10 users and two teachers in one course.

        // Make two coursemodules.
        /* @var phpunit_module_generator $assigngenerator */
        $generator = $this->getDataGenerator();
        $assigngenerator = new block_ajax_marking_mod_assign_generator($generator);
        $assignrecord = new stdClass();
        $assignrecord->assessed = 1;
        $assignrecord->scale = 4;
        $assignrecord->course = $this->course->id;
        $assign1 = $assigngenerator->create_instance($assignrecord);
        $assignrecord = new stdClass();
        $assignrecord->assessed = 1;
        $assignrecord->scale = 4;
        $assignrecord->course = $this->course->id;
        $assign2 = $assigngenerator->create_instance($assignrecord);

        // Make two groups.
        $protptypegroup = new stdClass();
        $protptypegroup->courseid = $this->course->id;
        $group1 = $generator->create_group($protptypegroup);
        $protptypegroup = new stdClass();
        $protptypegroup->courseid = $this->course->id;
        $group2 = $generator->create_group($protptypegroup);

        // Make some fake nodes.
        $nodeswithgroups = array();
        $node = new stdClass();
        $node->coursemoduleid = $assign1->cmid;
        $nodeswithgroups[$assign1->cmid] = $node;
        $node = new stdClass();
        $node->coursemoduleid = $assign2->cmid;
        $nodeswithgroups[$assign2->cmid] = $node;

        // Test that we get the groups. All should have display = 1.
        $class = new ReflectionClass('block_ajax_marking_nodes_builder_base');
        $method = $class->getMethod('attach_groups_to_coursemodule_nodes');
        $method->setAccessible(true);
        $nodeswithgroups = $method->invokeArgs($class, array($nodeswithgroups));

        $this->assertEquals(2, count($nodeswithgroups));
        $this->assertEquals(2, count($nodeswithgroups[$assign1->cmid]->groups));
        $this->assertEquals(2, count($nodeswithgroups[$assign2->cmid]->groups));
        $this->assertArrayHasKey($group1->id, $nodeswithgroups[$assign1->id]->groups);
        $this->assertArrayHasKey($group2->id, $nodeswithgroups[$assign1->id]->groups);
        $this->assertEquals(1, $nodeswithgroups[$assign1->id]->groups[$group1->id]->display);
        $this->assertEquals(1, $nodeswithgroups[$assign1->id]->groups[$group2->id]->display);

        // Hide one group at course level.
        $coursesetting = new stdClass();
        $coursesetting->userid = $USER->id;
        $coursesetting->tablename = 'course';
        $coursesetting->instanceid = $this->course->id;
        $coursesetting->display = 1;
        $coursesetting->groupsdisplay = 1;
        $coursesetting->showorphans = 1;
        $coursesetting->id = $DB->insert_record('block_ajax_marking', $coursesetting);

        $groupsetting = new stdClass();
        $groupsetting->configid = $coursesetting->id;
        $groupsetting->groupid = $group1->id;
        $groupsetting->display = 0;
        $groupsetting->id = $DB->insert_record('block_ajax_marking_groups', $groupsetting);

        // Make some fake nodes.
        $nodescoursehidden = array();
        $node = new stdClass();
        $node->coursemoduleid = $assign1->cmid;
        $nodescoursemodulehidden[$assign1->cmid] = $node;
        $node = new stdClass();
        $node->coursemoduleid = $assign2->cmid;
        $nodescoursemodulehidden[$assign2->cmid] = $node;
        $nodescoursemodulehidden = $method->invokeArgs($class, array($nodescoursemodulehidden));

        $this->assertEquals(2, count($nodescoursemodulehidden));
        $message = 'Wrong number of groups';
        $this->assertEquals(2, count($nodescoursemodulehidden[$assign1->cmid]->groups), $message);
        $this->assertEquals(2, count($nodescoursemodulehidden[$assign2->cmid]->groups), $message);
        $this->assertArrayHasKey($group1->id, $nodescoursemodulehidden[$assign1->id]->groups);
        $this->assertArrayHasKey($group2->id, $nodescoursemodulehidden[$assign1->id]->groups);
        $message = 'Display should be 0 after group was hidden at course level';
        $this->assertEquals(0, $nodescoursemodulehidden[$assign1->id]->groups[$group1->id]->display, $message);
        $this->assertEquals(1, $nodescoursemodulehidden[$assign1->id]->groups[$group2->id]->display);

        // Now try hiding at course module level.
        $coursemodulesetting = new stdClass();
        $coursemodulesetting->userid = $USER->id;
        $coursemodulesetting->tablename = 'course_modules';
        $coursemodulesetting->instanceid = $assign1->cmid;
        $coursemodulesetting->display = 1;
        $coursemodulesetting->groupsdisplay = 1;
        $coursemodulesetting->showorphans = 1;
        $coursemodulesetting->id = $DB->insert_record('block_ajax_marking', $coursemodulesetting);

        $groupsetting = new stdClass();
        $groupsetting->configid = $coursemodulesetting->id;
        $groupsetting->groupid = $group1->id;
        $groupsetting->display = 1;
        $groupsetting->id = $DB->insert_record('block_ajax_marking_groups', $groupsetting);

        // Make some fake nodes.
        $nodescoursemodulehidden = array();
        $node = new stdClass();
        $node->coursemoduleid = $assign1->cmid;
        $nodescoursehidden[$assign1->cmid] = $node;
        $node = new stdClass();
        $node->coursemoduleid = $assign2->cmid;
        $nodescoursehidden[$assign2->cmid] = $node;
        $nodescoursehidden = $method->invokeArgs($class, array($nodescoursehidden));

        // Now, group 1 should be hidden for assign 2 (no override), but visible for assign 1.
        $this->assertEquals(2, count($nodescoursehidden));
        $message = 'Wrong number of groups';
        $this->assertEquals(2, count($nodescoursehidden[$assign1->cmid]->groups), $message);
        $this->assertEquals(2, count($nodescoursehidden[$assign2->cmid]->groups), $message);
        $this->assertArrayHasKey($group1->id, $nodescoursehidden[$assign1->id]->groups);
        $this->assertArrayHasKey($group2->id, $nodescoursehidden[$assign1->id]->groups);
        $message = 'Display should be 1 after group was made visible at course module level';
        $this->assertEquals(1, $nodescoursehidden[$assign1->id]->groups[$group1->id]->display, $message);
        $this->assertEquals(0, $nodescoursehidden[$assign2->id]->groups[$group1->id]->display);

    }

    /**
     * In case we want to get a coursemodule or something, we may need to know what the id of a module is.
     *
     * @param string $modulename
     * @return int
     */
    protected function get_module_id($modulename) {

        global $DB;

        static $cachedids = array();

        if (array_key_exists($modulename, $cachedids)) {
            return $cachedids[$modulename];
        }

        $moduleid = $DB->get_field('modules', 'id', array('name' => $modulename));

        $cachedids[$modulename] = $moduleid;

        return $moduleid;

    }

    /**
     * Makes sure we can get one node only for when the counts will have changed due to a settings tweak.
     *
     */
    public function test_get_count_for_single_node() {

        // Make all the test data and get a total count back.
        $this->make_module_submissions();

        // Make sure the current user is a teacher in the course.
        $this->setUser(key($this->teachers));

        $filters = array();
        $filters['currentfilter'] = 'courseid';
        $filters['filtervalue'] = $this->course->id;

        $node = block_ajax_marking_nodes_builder_base::get_count_for_single_node($filters);

        $message = "Wrong number of things returned as the count for a single node. Expected {$this->submissioncount} ".
            "but got {$node['itemcount']}";
        $this->assertEquals($this->submissioncount, $node['itemcount'], $message);

    }

    /**
     * Make nodes for quiz questions to make sure they're there.
     */
    public function test_quiz_question_nodes_work() {

        global $DB;

        $this->make_module_submissions();
        $this->setUser(key($this->teachers));

        // Quiz.
        $quizmoduleid = $this->get_module_id('quiz');
        $quizcoursemodules = $DB->get_records('course_modules', array('module' => $quizmoduleid));
        $quizone = reset($quizcoursemodules);

        $filters = array();
        $filters['coursemoduleid'] = $quizone->id;
        $filters['questionid'] = 'nextnodefilter'; // We know at least one question was made in an empty DB.
        $nodes = block_ajax_marking_nodes_builder_base::unmarked_nodes($filters);
        $this->assertInternalType('array', $nodes);
    }

    /**
     * Make sure we get some nodes back for assignment submissions rather than an error.
     */
    public function test_assignment_userid_nodes_work() {

        global $DB;

        $this->make_module_submissions();
        $this->setUser(key($this->teachers));

        // Assignment.
        $assignmentmoduleid = $this->get_module_id('assignment');
        $assignmentcoursemodules = $DB->get_records('course_modules', array('module' => $assignmentmoduleid));
        $assignmentone = reset($assignmentcoursemodules);
        $filters = array();
        $filters['coursemoduleid'] = $assignmentone->id;
        $filters['userid'] = 'nextnodefilter';

        $nodes = block_ajax_marking_nodes_builder_base::unmarked_nodes($filters);
        $this->assertInternalType('array', $nodes);

    }

    /**
     * Makes sure we have the settings attached to the nodes when we ask for them.
     */
    public function test_attach_config_settings_to_nodes() {

        global $DB;

        // Make all the test data and get a total count back.
        $this->make_module_submissions();

        // Make sure the current user is a teacher in the course.
        $teacher = reset($this->teachers);
        $this->setUser($teacher->id);

        $filters = array();
        $filters['courseid'] = 'nextnodefilter';

        // Make a setting to see if we get the value attached.
        $coursesetting = new stdClass();
        $coursesetting->userid = $teacher->id;
        $coursesetting->tablename = 'course';
        $coursesetting->instanceid = $this->course->id;
        $coursesetting->groupsdisplay = 0;
        $DB->insert_record('block_ajax_marking', $coursesetting);

        $nodes = block_ajax_marking_nodes_builder_base::unmarked_nodes($filters);

        $this->assertNotEmpty($nodes, 'No nodes returned at all');

        // Should only be one.
        $coursenode = reset($nodes);

        $this->assertObjectHasAttribute('groupsdisplay', $coursenode,
                                        'Course node does not have a groupsdisplay field');
        $this->assertNotNull($coursenode->groupsdisplay, 'Course groupsdisplay setting should be zero but is null.');

        $message = 'Expected to get the groupsdisplay setting attached to the course node, but it\'s not';
        $this->assertEquals(0, $coursenode->groupsdisplay, $message);

        // Now check for coursemodules.
        $assign = reset($this->assigns);

        $filters = array();
        $filters['courseid'] = $this->course->id;
        $filters['coursemoduleid'] = 'nextnodefilter';
        $nodes = block_ajax_marking_nodes_builder_base::unmarked_nodes($filters);

        $foundit = false;
        foreach ($nodes as $node) {
            if ($node->coursemoduleid == $assign->cmid) {
                $foundit = true;
                $this->assertObjectHasAttribute('groupsdisplay', $node, 'coursemodule node does not have a groupsdisplay field');
                $this->assertNull($node->groupsdisplay, 'Did not get groups display as null on coursemodule node');
            }
        }
        $this->assertTrue($foundit, 'Did not get coursemodule back in nodes array');

        // Now override so that 0 at course level becomes 1.
        $cmsetting = new stdClass();
        $cmsetting->userid = $teacher->id;
        $cmsetting->tablename = 'course_modules';
        $cmsetting->instanceid = $assign->cmid;
        $cmsetting->groupsdisplay = 1;
        $DB->insert_record('block_ajax_marking', $cmsetting);

        $nodes = block_ajax_marking_nodes_builder_base::unmarked_nodes($filters);
        $foundit = false;
        foreach ($nodes as $node) {
            if ($node->coursemoduleid == $assign->cmid) {
                $foundit = true;
                $this->assertObjectHasAttribute('groupsdisplay', $node,
                                                'coursemodule node does not have a groupsdisplay field');
                $this->assertEquals(1, $node->groupsdisplay, 'Did not get groups display as 1 on coursemodule node after override');
            }
        }
        $this->assertTrue($foundit, 'Did not get coursemodule back in nodes array');

    }

    /**
     * Makes fake submission data for the coursework module so we can do the tests. There's a need to cover all
     * possible use cases, so we need:
     * - One with allocations enabled, one without. The one without should always show up, but the allocations one
     *   should only be OK for when there is an allocation.
     * - One with feedbacks from another teacher that should still show up
     * - One with the right number of feedbacks already, which shouldn't show up
     * - One empty feedback with no grade or comment which should show up.
     *
     * @return int the number of submissions to expect.
     */
    private function create_coursework_submission_data() {

        global $USER;

        $expectedcount = 0;

        // Make two submissions, one with and one without allocations.
        // Make one have single and one multiple marker.
        $generator = $this->getDataGenerator();
        /* @var mod_coursework_generator $courseworkgenerator */
        $courseworkgenerator = $generator->get_plugin_generator('mod_coursework');

        $firststudent = reset($this->students);
        $laststudent = end($this->students);

        $firstteacher = reset($this->teachers);
        $lastteacher = end($this->teachers);

        $singlewithallocation = new stdClass();
        $singlewithallocation->course = $this->course->id;
        $singlewithallocation->numberofmarkers = 1;
        $singlewithallocation->allocationenabled = 1;
        $singlewithallocation = $courseworkgenerator->create_instance($singlewithallocation);

        $singlenoallocation = new stdClass();
        $singlenoallocation->course = $this->course->id;
        $singlenoallocation->numberofmarkers = 1;
        $singlenoallocation->allocationenabled = 0;
        $singlenoallocation = $courseworkgenerator->create_instance($singlenoallocation);

        $multiplepartialgraded = new stdClass();
        $multiplepartialgraded->course = $this->course->id;
        $multiplepartialgraded->numberofmarkers = 2;
        $multiplepartialgraded->allocationenabled = 0;
        $multiplepartialgraded = $courseworkgenerator->create_instance($multiplepartialgraded);

        $singleemptyfeedback = new stdClass();
        $singleemptyfeedback->course = $this->course->id;
        $singleemptyfeedback->numberofmarkers = 2;
        $singleemptyfeedback->allocationenabled = 0;
        $singleemptyfeedback = $courseworkgenerator->create_instance($singleemptyfeedback);

        $singleresubmitted = new stdClass();
        $singleresubmitted->course = $this->course->id;
        $singleresubmitted->numberofmarkers = 2;
        $singleresubmitted->allocationenabled = 0;
        $singleresubmitted = $courseworkgenerator->create_instance($singleresubmitted);

        // Now make some submissions for the allocation one. Then one allocation so we can make sure we just
        // get the right one. The others should remain hidden.
        foreach ($this->students as $student) {
            $submission = new stdClass();
            $submission->userid = $student->id;
            $submission->courseworkid = $singlewithallocation->id;
            $submission = $courseworkgenerator->create_submission($submission, $singlewithallocation);
        }
        // The $submission variable will be left as the last one in the list. Make an allocation for just
        // this one and expect that the others will not show up.
        $allocation = new stdClass();
        $allocation->assessorid = $USER->id;
        $allocation->studentid = $submission->userid;
        $allocation->courseworkid = $singlewithallocation->id;
        $allocation = $courseworkgenerator->create_allocation($allocation);
        $expectedcount++;

        // Now try with no allocations and they should all turn up.
        reset($this->students);
        foreach ($this->students as $student) {
            $submission = new stdClass();
            $submission->userid = $student->id;
            $submission->courseworkid = $singlenoallocation->id;
            $submission = $courseworkgenerator->create_submission($submission, $singlenoallocation);
            $expectedcount++;
        }

        // Now check that when another teacher has made a feedback, we still get it back when there is room
        // for more feedbacks.
        $submission = new stdClass();
        $submission->userid = $student->id;
        $submission->courseworkid = $multiplepartialgraded->id;
        $submission = $courseworkgenerator->create_submission($submission, $multiplepartialgraded);
        $expectedcount++;

        // This feedback ought not to matter.
        $feedback = new stdClass();
        $feedback->assessorid = 67; // Using random large teacher ids so we won't have interference.
        $feedback->submissionid = $submission->id;
        $feedback->timemodified = time() + 1; // Need to be later than the submission.
        $feedback = $courseworkgenerator->create_feedback($feedback);

        // Now make one with the maximum number of feedbacks and make sure it doesn't turn up.
        // The one we just made will be for the last student in the list.
        $submission = new stdClass();
        $submission->userid = $firststudent->id;
        $submission->courseworkid = $multiplepartialgraded->id;
        $submission = $courseworkgenerator->create_submission($submission, $multiplepartialgraded);

        $feedback = new stdClass();
        $feedback->assessorid = 45;
        $feedback->submissionid = $submission->id;
        $feedback->timemodified = time() + 1; // Need to be later than the submission.
        $feedback = $courseworkgenerator->create_feedback($feedback);
        $feedback = new stdClass();
        $feedback->assessorid = 87;
        $feedback->submissionid = $submission->id;
        $feedback->timemodified = time() + 1; // Need to be later than the submission.
        $feedback = $courseworkgenerator->create_feedback($feedback);

        // Now make one with a feedback by the current user, which should still show up because the feedback
        // has no grade or comment. We also check that ones which have been graded are missing.
        $submission = new stdClass();
        $submission->userid = $student->id;
        $submission->courseworkid = $singleemptyfeedback->id;
        $submission = $courseworkgenerator->create_submission($submission, $singleemptyfeedback);
        $expectedcount++;
        // Empty comment and grade - should show up.
        $feedback = new stdClass();
        $feedback->assessorid = $USER->id;
        $feedback->submissionid = $submission->id;
        $feedback->timemodified = time() + 1; // Need to be later than the submission.
        $feedback = $courseworkgenerator->create_feedback($feedback);
        // This one has a comment and grade, so it ought to not show up.
        $submission = new stdClass();
        $submission->userid = $laststudent->id;
        $submission->courseworkid = $singleemptyfeedback->id;
        $submission = $courseworkgenerator->create_submission($submission, $singleemptyfeedback);
        $feedback = new stdClass();
        $feedback->assessorid = $USER->id;
        $feedback->grade = 65;
        $feedback->feedbackcomment = 'some text';
        $feedback->submissionid = $submission->id;
        $feedback->timemodified = time() + 1; // Need to be later than the submission.
        $feedback = $courseworkgenerator->create_feedback($feedback);

        // TODO test comment OR grade.

        // TODO Now make one which has been resubmitted after a previous grading to make sure it'll turn up again.

        $this->submissioncount += $expectedcount;

        return $expectedcount;

    }

    public function test_coursework_query_single_with_allocation() {

        global $USER, $DB;

        // Perhaps the coursework module is not here?
        if (!$DB->record_exists('modules', array('name' => 'coursework', 'visible' => 1))) {
            return;
        }

        $generator = $this->getDataGenerator();
        /* @var mod_coursework_generator $courseworkgenerator */
        $courseworkgenerator = $generator->get_plugin_generator('mod_coursework');

        $singlewithallocation = new stdClass();
        $singlewithallocation->course = $this->course->id;
        $singlewithallocation->numberofmarkers = 1;
        $singlewithallocation->allocationenabled = 1;
        $singlewithallocation = $courseworkgenerator->create_instance($singlewithallocation);

        // Now make some submissions for the allocation one. Then one allocation so we can make sure we just
        // get the right one. The others should remain hidden.
        $submission = new stdClass();
        foreach ($this->students as $student) {
            $submission = new stdClass();
            $submission->userid = $student->id;
            $submission->courseworkid = $singlewithallocation->id;
            $submission = $courseworkgenerator->create_submission($submission, $singlewithallocation);
        }
        // The $submission variable will be left as the last one in the list. Make an allocation for just
        // this one and expect that the others will not show up.
        $allocation = new stdClass();
        $allocation->assessorid = $USER->id;
        $allocation->studentid = $submission->userid;
        $allocation->courseworkid = $singlewithallocation->id;
        $allocation = $courseworkgenerator->create_allocation($allocation);

        $expectedcount = 1;

        $actualcount = $this->get_modulequery_count('coursework');

        $this->assertEquals($expectedcount, $actualcount, 'Allocations are not showing up right');
    }

    /**
     * Counts how many items the module query gives us.
     *
     * @param string $modulename
     * @throws coding_exception
     * @return int
     */
    protected function get_modulequery_count($modulename) {
        $modclasses = block_ajax_marking_get_module_classes();

        if (!array_key_exists($modulename, $modclasses)) {
            throw new coding_exception('Missing '.$modulename.' module class');
        }

        $modclass = $modclasses[$modulename];

        // Make query.
        $query = $modclass->query_factory();
        // We will get an error if we leave it like this as the userids in the first
        // column are not unique.
        $wrapper = new block_ajax_marking_query_base();
        $wrapper->add_select(array('function' => 'COUNT',
                                   'column' => '*',
                                   'alias' => 'count'));
        $wrapper->add_from(array('table' => $query,
                                 'alias' => 'modulequery'));

        // Run query. Get one stdClass with a count property.
        $unmarkedstuff = $wrapper->execute();

        // Make sure we get the right number of things back.
        return reset($unmarkedstuff)->count;
    }

    /**
     * We need to find out if each individual module works or not. This loops over them testing that we get
     * something back.
     */
    public function test_individual_modules() {
        // Make all the test data and get a total count back.
        // Make sure the current user is a teacher in the course.

        $moduleclasses = block_ajax_marking_get_module_classes();

        foreach ($moduleclasses as $moduleclass) {

            // Make the test data for just this module.
            $modname = $moduleclass->get_module_name();
            $this->setAdminUser();
            $expected = $this->make_module_submissions(array($modname));
            $this->setUser(key($this->teachers));

            // Make a query for just that module.
            $countindb = $this->get_modulequery_count($modname);

            // Make sure we get the right number of things back.
            $message = 'Found '.$countindb.' things for '.$modname.' instead of '.$expected;
            $this->assertEquals($expected, $countindb, $message);

        }

    }


}

