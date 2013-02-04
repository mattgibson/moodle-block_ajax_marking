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
 * Class file for the forum grading functions
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2008 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die();
}

global $CFG;

require_once($CFG->dirroot.'/blocks/ajax_marking/classes/query_base.class.php');
require_once($CFG->dirroot.'/blocks/ajax_marking/classes/module_base.class.php');

/**
 * Provides functionality for grading of forum discussions
 */
class block_ajax_marking_forum extends block_ajax_marking_module_base {

    /**
     * Constructor
     *
     * @return \block_ajax_marking_forum
     * @internal param object $mainobject parent object passed in by reference
     */
    public function __construct() {

        // Call parent constructor with the same arguments.
        parent::__construct();

        // Must be the same as the DB modulename.
        $this->modulename = 'forum';
        $this->capability = 'mod/forum:viewhiddentimedposts';
        $this->icon = 'mod/forum/icon.gif';
    }

    /**
     * Checks to make sure that it has not been rated by anyone else.
     *
     * @return array sql and named parameters
     */
    private function get_teacher_sql() {

        global $USER;

        $params = array();
        $notmyrating = "NOT EXISTS (SELECT 1
                                      FROM {rating} rating
                                     WHERE rating.component = 'mod_forum'
                                       AND rating.contextid = forumcontext.id
                                       AND rating.ratingarea = 'post'
                                       AND rating.itemid = sub.id
                                       AND rating.userid != :forumratinguserid
                                      )";
        $params['forumratinguserid'] = $USER->id;

        return array($notmyrating,
                     $params);
    }

    /**
     * Is this a forum of type 'eachuser' or not?
     *
     * @param int $coursemoduleid
     * @global moodle_database $DB
     * @return bool|null
     */
    public static function forum_is_eachuser($coursemoduleid) {

        global $DB;

        // TODO do we need to cache this?

        $sql = "SELECT f.type
                  FROM {forum} f
            INNER JOIN {course_modules} c
                    ON f.id = c.instance
                 WHERE c.id = :coursemoduleid ";
        $forumtype = $DB->get_field_sql($sql, array('coursemoduleid' => $coursemoduleid));

        return ($forumtype == 'eachuser') ? true : false;
    }

    /**
     * Makes the pop up contents for the grading interface
     *
     * @param array $params
     * @param $coursemodule
     * @global moodle_database $DB
     * @global $PAGE
     * @global stdClass $CFG
     * @global $SESSION
     * @global $USER
     * @global $OUTPUT
     * @params object $coursemodule
     * @return string HTML
     */
    public function grading_popup(array $params, $coursemodule) {

        global $DB, $CFG, $SESSION, $USER, $OUTPUT;

        $output = '';

        // Lifted from /mod/forum/discuss.php...

        /*
         $parent = $params['parent'];       // If set, then display this post and all children.
         $mode   = $params['mode'];         // If set, changes the layout of the thread
         $move   = $params['move'];         // If set, moves this discussion to another forum
         $mark   = $params['mark'];       // Used for tracking read posts if user initiated.
         $postid = $params['postid'];       // Used for tracking read posts if user initiated.
        */

        $discussion = $DB->get_record('forum_discussions', array('id' => $params['discussionid']),
                                      '*', MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $discussion->course),
                                  '*', MUST_EXIST);
        $forum = $DB->get_record('forum', array('id' => $discussion->forum),
                                 '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id,
                                             false, MUST_EXIST);
        $modcontext = context_module::instance($cm->id);

        // Security - cmid is used to check context permissions earlier on, so it must match when
        // derived from the discussion.
        if (!($cm->id == $params['coursemoduleid'])) {
            print_error('Bad params!');
            return false;
        }

        // Add ajax-related libs.
//        $PAGE->requires->yui2_lib('event');
//        $PAGE->requires->yui2_lib('connection');
//        $PAGE->requires->yui2_lib('json');

        // Move this down fix for MDL-6926.
        require_once($CFG->dirroot.'/mod/forum/lib.php');

        // Restrict news forums - should not be graded.
        if ($forum->type == 'news') {
            print_error('invaliddiscussionid', 'forum',
                        "$CFG->wwwroot/mod/forum/view.php?f=$forum->id");
        }

        unset($SESSION->fromdiscussion);
        // In case the user has used the drop-down to change from threaded to flat or something.
        if (isset($params['mode'])) {
            set_user_preference('forum_displaymode', $params['mode']);
        }
        $displaymode = get_user_preferences('forum_displaymode', $CFG->forum_displaymode);

        $parent = $discussion->firstpost;
        $post = forum_get_post_full($parent);
        if (!$post) {
            print_error("notexists", 'forum', "$CFG->wwwroot/mod/forum/view.php?f=$forum->id");
        }
        if (!forum_user_can_see_post($forum, $discussion, $post, $USER, $cm)) {
            print_error('nopermissiontoview', 'forum',
                        "$CFG->wwwroot/mod/forum/view.php?id=$forum->id");
        }

        // For now, restrict to rating only.
        $canreply = false;

        // Without this, the nesting doesn't work properly as the css isn't picked up.
        $output .= html_writer::start_tag('div', array('class' => 'path-mod-forum'));
        $output .= html_writer::start_tag('div', array('class' => 'discussioncontrols clearfix'));
        $output .= html_writer::start_tag('div', array('class' => 'discussioncontrol displaymode'));
        // We don't want to have the current mode returned in the url as well as the new one.
        unset($params['mode']);
        $newurl = new moodle_url('/blocks/ajax_marking/actions/grading_popup.php', $params);
        $select = new single_select($newurl, 'mode', forum_get_layout_modes(), $displaymode,
                                    null, "mode");
        $output .= $OUTPUT->render($select);
        $output .= html_writer::end_tag('div');

        // If user has not already posted and it's a Q & A forum...
        $forumisqanda = $forum->type == 'qanda';
        $noviewwithoutposting = !has_capability('mod/forum:viewqandawithoutposting', $modcontext);
        $hasnotposted = !forum_user_has_posted($forum->id, $discussion->id, $USER->id);
        if ($forumisqanda && $noviewwithoutposting && $hasnotposted) {
            $output .= $OUTPUT->notification(get_string('qandanotify', 'forum'));
        }

        $canrate = has_capability('mod/forum:rate', $modcontext);
        ob_start();
        forum_print_discussion($course, $cm, $forum, $discussion, $post, $displaymode,
                               $canreply, $canrate);
        $output .= ob_get_contents();
        ob_end_clean();

        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');

        return $output;
    }

    /**
     * This will save the data for the finished forums. Namely that they are now not to be shown as
     * in need of marking unless the posts date from beyond this point in time.
     *
     * @param $data
     * @param $params
     * @return string|void
     */
    public function process_data($data, $params) {
        // All done via forms AJAX functions, so deliberately empty.
    }

    /**
     * Returns a query object with the basics all set up to get assignment stuff
     *
     * @global moodle_database $DB
     * @return block_ajax_marking_query_base
     */
    public function query_factory() {

        global $USER;

        $query = new block_ajax_marking_query_base($this);
        // This currently does a simple check for whether or not the current user has added a
        // rating or not. No scope for another teacher to do all the marking, or some of it.
        list($notmyratingsql, $notmyratingparams) = $this->get_teacher_sql();

        $query->add_from(array(
                              'table' => 'forum_posts',
                              'alias' => 'sub',
                         ));

        $query->add_from(array(
                              'table' => 'forum_discussions',
                              'alias' => 'discussions',
                              'on' => 'sub.discussion = discussions.id'
                         ));
        $query->add_from(array(
                              'table' => $this->modulename,
                              'alias' => 'moduletable',
                              'on' => 'discussions.forum = moduletable.id'
                         ));
        $query->set_column('courseid', 'moduletable.course');

        // We need the context id to check the ratings table in the teacher SQL.
        $query->add_from(array(
                              'table' => 'course_modules',
                              'alias' => 'forumcoursemodules',
                              'on' => 'moduletable.id = forumcoursemodules.instance '.
                                  'AND forumcoursemodules.module = '.$this->get_module_id()
                         ));
        $query->add_from(array(
                              'table' => 'context',
                              'alias' => 'forumcontext',
                              'on' => 'forumcoursemodules.id = forumcontext.instanceid '.
                                  'AND forumcontext.contextlevel = '.CONTEXT_MODULE
                         ));
        // Standard userid for joins.
        $query->add_select(array('table' => 'sub',
                                 'column' => 'userid'));
        $query->set_column('userid', 'sub.userid');

        $query->add_select(array('table' => 'sub',
                                 'column' => 'modified',
                                 'alias' => 'timestamp'));

        $query->add_where('sub.userid <> :forumuserid', array('forumuserid' => $USER->id));
        $query->add_where('moduletable.assessed > 0');
        $query->add_where(" {$notmyratingsql} ", $notmyratingparams);
        $query->add_where('( (moduletable.assesstimestart = 0) OR
                                                 (sub.created >= moduletable.assesstimestart) ) ');
        $query->add_where('( (moduletable.assesstimefinish = 0) OR
                                                 (sub.created <= moduletable.assesstimefinish) )');

        return $query;
    }

    /**
     * Sometimes there will need to be extra processing of the nodes that is specific to this module
     * e.g. the title to be displayed for submissions needs to be formatted with firstname and
     * lastname in the way that makes sense for the user's chosen language.
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

                case 'discussionid':

                    if (self::forum_is_eachuser($filters['coursemoduleid'])) {
                        $node->name = fullname($node);
                    }
                    break;

                default:
                    break;
            }
        }
        return $nodes;
    }
}


