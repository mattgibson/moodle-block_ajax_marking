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
require_once($CFG->dirroot.'/blocks/ajax_marking/classes/filters.class.php');

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
        $this->modulename  = 'forum';
        $this->capability  = 'mod/forum:viewhiddentimedposts';
        $this->icon        = 'mod/forum/icon.gif';
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

        return array($notmyrating, $params);
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
     * See parent class for docs
     *
     * @param object $submission
     * @param int $moduleinstanceid
     * @return string
     */
    protected function submission_title(&$submission, $moduleinstanceid) {

        global $DB;

        // We will only get to this bit repeatedly for a single forum, so we can cache this and
        // save some queries.
        static $iseachuser;

        if (!isset($iseachuser)) {
            $type = $DB->get_field('forum', 'type', array('id' => $moduleinstanceid));
            $iseachuser = ($type == 'eachuser') ? true : false;
        }

        if ($iseachuser) {
            return fullname($submission);
        }

        // Keep the $submission object clean.
        $label = $submission->label;
        unset($submission->label);

        return $label;
    }

    /**
     * See parent class for docs
     *
     * @return string
     */
    protected function get_sql_userid_column() {
        return 'discussions.userid';
    }


    /**
     * Returns a HTML link allowing a student's work to be marked
     *
     * @param object $item a row of the database table representing one discussion post
     *
     * @return string
     */
    public function make_html_link($item) {
        global $CFG;
        $address = $CFG->wwwroot.'/mod/forum/view.php?id='.$item->cmid;
        return $address;
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
    public function grading_popup($params, $coursemodule) {

        global $DB, $PAGE, $CFG, $SESSION, $USER, $OUTPUT;

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
        $course     = $DB->get_record('course', array('id' => $discussion->course),
                                      '*', MUST_EXIST);
        $forum      = $DB->get_record('forum', array('id' => $discussion->forum),
                                      '*', MUST_EXIST);
        $cm         = get_coursemodule_from_instance('forum', $forum->id, $course->id,
                                                     false, MUST_EXIST);
        $modcontext = context_module::instance($cm->id);

        // Security - cmid is used to check context permissions earlier on, so it must match when
        // derived from the discussion.
        if (!($cm->id == $params['coursemoduleid'])) {
            print_error('Bad params!');
            return false;
        }

        // Add ajax-related libs.
        $PAGE->requires->yui2_lib('event');
        $PAGE->requires->yui2_lib('connection');
        $PAGE->requires->yui2_lib('json');

        // Move this down fix for MDL-6926.
        require_once($CFG->dirroot.'/mod/forum/lib.php');

        // Restrict news forums - should not be graded.
        if ($forum->type == 'news') {
            print_error('invaliddiscussionid', 'forum',
                        "$CFG->wwwroot/mod/forum/view.php?f=$forum->id");
        }

        unset($SESSION->fromdiscussion);
        // In case the user has used the dropdown to change from threaded to flat or something.
        if (isset($params['mode'])) {
            set_user_preference('forum_displaymode', $params['mode']);
        }
        $displaymode = get_user_preferences('forum_displaymode', $CFG->forum_displaymode);

        $parent = $discussion->firstpost;
        $post = forum_get_post_full($parent);
        if (!$post) {
            print_error("notexists", 'forum', "$CFG->wwwroot/mod/forum/view.php?f=$forum->id");
        }
        if (!forum_user_can_view_post($post, $course, $cm, $forum, $discussion)) {
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
     */
    public function process_data($data, $params) {

        // Validate everything.

    }

    /**
     * Returns a query object with the basics all set up to get assignment stuff
     *
     * @global moodle_database $DB
     * @return block_ajax_marking_query_base
     */
    public function query_factory() {

        global $DB, $USER;

        $query = new block_ajax_marking_query_base($this);
        // This currently does a simple check for whether or not the current user has added a
        // rating or not. No scope for another teacher to do all the marking, or some of it.
        list($notmyratingsql, $notmyratingparams) = $this->get_teacher_sql();
        $query->add_params($notmyratingparams);

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
        $query->add_select(array('table' => 'sub',
                                'column' => 'modified',
                                'alias'  => 'timestamp'));

        $query->add_where(array(
                           'type' => 'AND',
                           'condition' => 'sub.userid <> :forumuserid'));
        $query->add_where(array(
                           'type' => 'AND',
                           'condition' => 'moduletable.assessed > 0'));
        $query->add_where(array(
                           'type' => 'AND',
                           'condition' => "(
                           {$notmyratingsql}
                            AND ( ( ".$DB->sql_compare_text('moduletable.type')." != 'eachuser')
                                  OR ( ".$DB->sql_compare_text('moduletable.type')." = 'eachuser'
                                       AND sub.id = discussions.firstpost)))"));
        $query->add_where(array(
                               'type' => 'AND',
                               'condition' => '( (moduletable.assesstimestart = 0) OR
                                                 (sub.created >= moduletable.assesstimestart) ) '));
        $query->add_where(array(
                               'type' => 'AND',
                               'condition' => '( (moduletable.assesstimefinish = 0) OR
                                                 (sub.created <= moduletable.assesstimefinish) )'));

        $query->add_param('forumuserid', $USER->id);

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
                    } else {
                        $node->name = $node->label;
                    }
                    break;

                default:
                    break;
            }
        }
        return $nodes;
    }

}

/**
 * Deals with SQL for the discussion nodes
 */
class block_ajax_marking_forum_discussionid extends block_ajax_marking_filter_base {

    /**
     * Adds SQL for when there is a discussion node as an ancestor of the current nodes.
     *
     * @static
     * @param block_ajax_marking_query_base $query
     * @param int $discussionid
     */
    public static function where_filter($query, $discussionid) {

        $countwrapper = self::get_countwrapper_subquery($query);

        $clause = array(
            'type' => 'AND',
            'condition' => 'discussion.id = :discussionidfilterdiscussionid');
        $countwrapper->add_where($clause);
        $query->add_param('discussionidfilterdiscussionid', $discussionid);
    }

    /**
     * Adds SQL to construct a set of discussion nodes.
     *
     * @static
     * @param block_ajax_marking_query_base $query
     */
    public static function nextnodetype_filter($query) {

        $countwrapper = self::get_countwrapper_subquery($query);
        // This will be derived form the coursemodule id, but how to get it cleanly?
        // The query will know, but not easy to get it out. Might have been prefixed.
        // TODO pass this properly somehow.
        $coursemoduleid = required_param('coursemoduleid', PARAM_INT);
        // Normal forum needs discussion title as label, participant usernames as
        // description eachuser needs username as title and discussion subject as
        // description.
        if (block_ajax_marking_forum::forum_is_eachuser($coursemoduleid)) {
            $query->add_select(array(
                                    'table' => 'firstpost',
                                    'column' => 'subject',
                                    'alias' => 'description'
                               ));
        } else {
            $query->add_select(array(
                                    'table' => 'firstpost',
                                    'column' => 'subject',
                                    'alias' => 'label'
                               ));
            // TODO need a SELECT bit to get all userids of people in the discussion
            // instead.
            $query->add_select(array(
                                    'table' => 'firstpost',
                                    'column' => 'message',
                                    'alias' => 'tooltip'
                               ));
        }

        $query->add_from(array(
                              'join' => 'INNER JOIN',
                              'table' => 'forum_discussions',
                              'alias' => 'outerdiscussions',
                              'on' => 'countwrapperquery.id = outerdiscussions.id'
                         ));

        $query->add_from(array(
                              'join' => 'INNER JOIN',
                              'table' => 'forum_posts',
                              'alias' => 'firstpost',
                              'on' => 'firstpost.id = outerdiscussions.firstpost'
                         ));

        // We join like this because we can't put extra stuff into the UNION ALL bit
        // unless all modules have it and this is unique to forums.
        $countwrapper->add_from(array(
                                     'table' => 'forum_posts',
                                     'on' => 'moduleunion.subid = post.id',
                                     'alias' => 'post')
        );
        $countwrapper->add_from(array(
                                     'table' => 'forum_discussions',
                                     'on' => 'discussion.id = post.discussion',
                                     'alias' => 'discussion')
        );
        $countwrapper->add_select(array(
                                       'table' => 'discussion',
                                       'column' => 'id'), true
        );

        $query->add_orderby("timestamp ASC");
    }

}
