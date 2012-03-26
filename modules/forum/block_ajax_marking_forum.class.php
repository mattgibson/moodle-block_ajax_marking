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

/**
 * Provides functionality for grading of forum discussions
 */
class block_ajax_marking_forum extends block_ajax_marking_module_base {

    /**
     * When in submissions mode, this will store the forum's type to save on db queries.
     * Won't get used at any other stage
     *
     * @var bool
     */
    private $iseachuser = null;

    /**
     * Constructor
     *
     * @return \block_ajax_marking_forum
     * @internal param object $mainobject parent object passed in by reference
     */
    public function __construct() {

        // call parent constructor with the same arguments
        parent::__construct();

        // must be the same as the DB modulename
        $this->modulename  = $this->moduletable = 'forum';
        $this->capability  = 'mod/forum:viewhiddentimedposts';
        $this->icon        = 'mod/forum/icon.gif';
    }


    /**
     * Puts all the generation of the sql for 'is this person not a teacher in this course' This is
     * so that anything already marked by another teacher will not show up.
     *
     * Assumes that it is part of a larger query with course aliased as c
     *
     * @internal param int $forumid
     * @return array named parameters
     */
    private function get_teacher_sql() {

        // making a where not exists to make sure the rating is not a teacher.
        // assume that the main query has a 'course c' and 'ratings r' clause in FROM

        // correlated sub query for the where should be OK if the joins only give a small number of
        // rows

        // role_assignment -> context -> cat1 -> coursecategory
        //                            -> cat1 -> coursecategory

        $categorylevels = block_ajax_marking_get_number_of_category_levels();

        // get category and course role assignments separately
        // for category level, we look for users with a role assignment where the contextinstance
        // can be left joined to any category that's a parent of the supplied course
        $select = "NOT EXISTS( SELECT 1
                                 FROM {role_assignments} ra
                           INNER JOIN {context} cx
                                   ON ra.contextid = cx.id
                           INNER JOIN {course_categories} cat1
                                   ON cx.instanceid = cat1.id ";

        $selectwhere = array('course.category = cat1.id');

        for ($i = 2; $i <= $categorylevels; $i++) {

            $onebefore = $i - 1;
            $select .= "LEFT JOIN {course_categories} cat{$i}
                                   ON cat{$onebefore}.parent = cat{$i}.id ";
            $selectwhere[] =      "course.category = cat{$i}.id";

        }

        $select .= 'WHERE('.implode(' OR ', $selectwhere).') ';
        $select .= 'AND ra.userid = r.userid
                    AND cx.contextlevel = '.CONTEXT_COURSECAT.' ';
        $select .=    " UNION

                       SELECT 1
                         FROM {role_assignments} ra
                   INNER JOIN {context} cx
                           ON ra.contextid = cx.id
                        WHERE cx.contextlevel = ".CONTEXT_COURSE."
                          AND ra.userid = r.userid
                          AND cx.instanceid = course.id
                        ) ";
        return $select;
    }

    /**
     * Is this a forum of type 'eachuser' or not?
     *
     * @param int $coursemoduleid
     * @global moodle_database $DB
     * @return bool|null
     */
    private function forum_is_eachuser($coursemoduleid) {

        global $DB;

        if (!is_null($this->iseachuser)) {
            return $this->iseachuser;
        }

        $sql = "SELECT f.type
                  FROM {forum} f
            INNER JOIN {course_modules} c
                    ON f.id = c.instance
                 WHERE c.id = :coursemoduleid ";
        $forumtype = $DB->get_field_sql($sql, array('coursemoduleid' => $coursemoduleid));

        $this->iseachuser = ($forumtype == 'eachuser') ? true : false;

        return $this->iseachuser;
    }

    /**
     * See parent class for docs
     *
     * @param object $submission
     * @param int $forumid
     * @return string
     */
    protected function submission_title(&$submission, $forumid) {

        if ($this->forum_is_eachuser($forumid)) {
            return fullname($submission);
        }

        // Keep the $submission object clean
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
     * @global type $PAGE
     * @global stfClass $CFG
     * @global type $SESSION
     * @global type $USER
     * @global type $OUTPUT
     * @params object $coursemodule
     * @return string HTML
     */
    public function grading_popup($params, $coursemodule) {

        global $DB, $PAGE, $CFG, $SESSION, $USER, $OUTPUT;

        $output = '';

        // Lifted from /mod/forum/discuss.php

        //$parent = $params['parent'];       // If set, then display this post and all children.
        //$mode   = $params['mode'];         // If set, changes the layout of the thread
        //$move   = $params['move'];         // If set, moves this discussion to another forum
        //$mark   = $params['mark'];       // Used for tracking read posts if user initiated.
        //$postid = $params['postid'];       // Used for tracking read posts if user initiated.

        $discussion = $DB->get_record('forum_discussions', array('id' => $params['discussionid']),
                                      '*', MUST_EXIST);
        $course     = $DB->get_record('course', array('id' => $discussion->course),
                                      '*', MUST_EXIST);
        $forum      = $DB->get_record('forum', array('id' => $discussion->forum),
                                      '*', MUST_EXIST);
        $cm         = get_coursemodule_from_instance('forum', $forum->id, $course->id,
                                                     false, MUST_EXIST);

        // security - cmid is used to check context permissions earlier on, so it must match when
        // derived from the discussion
        if (!($cm->id == $params['coursemoduleid'])) {
            print_error('Bad params!');
            return false;
        }

        // Add ajax-related libs
        $PAGE->requires->yui2_lib('event');
        $PAGE->requires->yui2_lib('connection');
        $PAGE->requires->yui2_lib('json');

        // move this down fix for MDL-6926
        require_once($CFG->dirroot.'/mod/forum/lib.php');

        // Possibly, the view permission is being used to prevent certain forums from being
        // accessed. Might be best not to rely on just the rate one.
        $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
        require_capability('mod/forum:viewdiscussion', $modcontext, null, true,
                           'noviewdiscussionspermission', 'forum');

        // restrict news to allowed times
        if ($forum->type == 'news') {
            if (!($USER->id == $discussion->userid || (($discussion->timestart == 0
                || $discussion->timestart <= time())
                && ($discussion->timeend == 0 || $discussion->timeend > time())))) {

                print_error('invaliddiscussionid', 'forum',
                            "$CFG->wwwroot/mod/forum/view.php?f=$forum->id");
            }
        }

        unset($SESSION->fromdiscussion);

        if (isset($params['mode'])) {
            set_user_preference('forum_displaymode', $params['mode']);
        }

        $displaymode = get_user_preferences('forum_displaymode', $CFG->forum_displaymode);

        $parent = $discussion->firstpost;

        if (! $post = forum_get_post_full($parent)) {
            print_error("notexists", 'forum', "$CFG->wwwroot/mod/forum/view.php?f=$forum->id");
        }

        if (!forum_user_can_view_post($post, $course, $cm, $forum, $discussion)) {
            print_error('nopermissiontoview', 'forum',
                        "$CFG->wwwroot/mod/forum/view.php?id=$forum->id");
        }

        // TODO what does this do? Does read tracking get updated?
        /*
        if ($mark == 'read' or $mark == 'unread') {
            if ($CFG->forum_usermarksread && forum_tp_can_track_forums($forum) &&
                forum_tp_is_tracked($forum)) {
                if ($mark == 'read') {
                    forum_tp_add_read_record($USER->id, $postid);
                } else {
                    // unread
                    forum_tp_delete_read_records($USER->id, $postid);
                }
            }
        } */

        // Check to see if groups are being used in this forum
        // If so, make sure the current person is allowed to see this discussion
        // Also, if we know they should be able to reply, then explicitly set $canreply for
        // performance reasons

        // DO NOT DELETE (yet)/////////////////////////////////////////////////////////
        // Do we want to allow replies? might break the ajax bit?
        /*
        $canreply = forum_user_can_post($forum, $discussion, $USER, $cm, $course, $modcontext);

        if (!$canreply and $forum->type !== 'news') {

            if (isguestuser() or !isloggedin()) {
                $canreply = true;
            }

            if (!is_enrolled($modcontext) and !is_viewing($modcontext)) {
                // allow guests and not-logged-in to see the link - they are prompted to log in
                // after clicking the link normal users with temporary guest access see this link
                // too, they are asked to enrol instead
                $canreply = enrol_selfenrol_available($course->id);
            }
        }*/
        /////////////////////////////////////////////////////////////////////////////////

        // For now, restrict to rating only
        $canreply = false;

        // wWithout this, the nesting doesn't work properly as the css isn't picked up
        $output .= html_writer::start_tag('div', array('class' => 'path-mod-forum'));
        $output .= html_writer::start_tag('div', array('class' => 'discussioncontrols clearfix'));
        $output .= html_writer::start_tag('div', array('class' => 'discussioncontrol displaymode'));
        // we don't want to have the current mode returned in the url as well as the new one
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
     * @param type $data
     */
    public function process_data($data) {

        // Validate everything

    }

    /**
     * Returns a query object with the basics all set up to get assignment stuff
     *
     * @global type $DB
     * @return block_ajax_marking_query_base
     */
    public function query_factory() {

        global $DB, $USER;

        $query = new block_ajax_marking_query_base($this);
        $teachersql = $this->get_teacher_sql();

        // TODO this is broken - I think multiple ratings per post will make multiple submissions
        // appear.
        // TODO Ought to be NOT EXISTS(). OR will it be OK as it disregards the rows with
        // other users?

        $query->add_from(array(
                'table' => 'forum_posts',
                'alias' => 'sub',
        ));
        $query->add_from(array(
                'join' => 'LEFT JOIN',
                'table' => 'rating',
                'alias' => 'r',
                'on' => 'sub.id = r.itemid'
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
        $query->add_from(array(
                'table' => 'course',
                'on' => 'discussions.course = course.id'
        ));
        // Standard userid for joins
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
                           'condition' => "( ( ( r.userid <> :forumuserid2 )
                                AND {$teachersql})
                              OR r.userid IS NULL)
                            AND ( ( ".$DB->sql_compare_text('moduletable.type')." != 'eachuser')
                                  OR ( ".$DB->sql_compare_text('moduletable.type')." = 'eachuser'
                                       AND sub.id = discussions.firstpost))"));
        $query->add_where(array(
                               'type' => 'AND',
                               'condition' => '( (moduletable.assesstimestart = 0) OR
                                                 (sub.created >= moduletable.assesstimestart) ) '));
        $query->add_where(array(
                               'type' => 'AND',
                               'condition' => '( (moduletable.assesstimefinish = 0) OR
                                                 (sub.created <= moduletable.assesstimefinish) )'));

        $query->add_param('forumuserid', $USER->id);
        $query->add_param('forumuserid2', $USER->id);

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

            // just so we know (for styling and accessing js in the client)
            $node->modulename = $this->modulename;

            switch ($filters['nextnodefilter']) {

                case 'discussionid':

                    if ($this->forum_is_eachuser($filters['coursemoduleid'])) {
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

    /**
     * This will alter a query to send back the stuff needed for quiz questions
     *
     * @param block_ajax_marking_query_base $query
     * @param $operation
     * @param int $discussionid
     * @return void
     */
    public function apply_discussionid_filter(block_ajax_marking_query_base $query, $operation,
                                              $discussionid = 0) {

        $countwrapper = $query->get_subquery('countwrapperquery');

        switch ($operation) {

            case 'where':
                $countwrapper->add_where(array(
                        'type' => 'AND',
                        'condition' => 'discussion.id = :discussionidfilterdiscussionid'));
                $query->add_param('discussionidfilterdiscussionid', $discussionid);
                break;

            case 'countselect':

                // This will be derived form the coursemoduleid, but how to get it cleanly?
                // The query will know, but not easy to get it out. Might have been prefixed.
                // TODO pass this properly somehow
                $coursemoduleid = required_param('coursemoduleid', PARAM_INT);
                // normal forum needs discussion title as label, participant usernames as
                // description eachuser needs username as title and discussion subject as
                // description
                if ($this->forum_is_eachuser($coursemoduleid)) {
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
                    // TODO need a SELECT bit to get all userids of people in the discussion instead
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
                        'table'    => 'forum_posts',
                        'on'       => 'moduleunion.subid = post.id',
                        'alias'    => 'post')
                );
                $countwrapper->add_from(array(
                        'table'    => 'forum_discussions',
                        'on'       => 'discussion.id = post.discussion',
                        'alias'    => 'discussion')
                );
                $countwrapper->add_select(array(
                        'table'    => 'discussion',
                        'column'   => 'id'), true
                );

                break;
        }

    }


}
