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
     * @param object $mainobject parent object passed in by reference
     */
    public function __construct() {
        
        // call parent constructor with the same arguments
        //call_user_func_array(array($this, 'parent::__construct'), func_get_args());
        parent::__construct();
        
        // must be the same as the DB modulename
        $this->modulename        = 'forum';
        $this->capability  = 'mod/forum:viewhiddentimedposts';
        $this->icon        = 'mod/forum/icon.gif';
        $this->callbackfunctions   = array(
                'submissions'
        );
        
        
    }

   
    /**
     * Puts all the generation of the sql for 'is this person not a teacher in this course' This is so
     * that anything already marked by another teacher will not show up.
     *
     * Assumes that it is part of a larger query with course aliased as c
     *
     * @param int $forumid
     * @return array named parameters
     */
    function get_teacher_sql() {

        // making a where not exists to make sure the rating is not a teacher.
        // asume that the main query has a 'course c' and 'ratings r' clause in FROM

        // correlated sub query for the where should be OK if the joins only give a small number of
        // rows

        // role_assignment -> context -> cat1 -> coursecategory
        //                            -> cat1 -> coursecategory
        //

        $categorylevels = block_ajax_marking_get_number_of_category_levels();

        // get category and course role assignments separately
        // for category level, we look for users with a role assignment where the contextinstance can be
        // left joined to any category that's a parent of the suplied course
        $select = "NOT EXISTS( SELECT 1
                                 FROM {role_assignments} ra
                           INNER JOIN {context} cx
                                   ON ra.contextid = cx.id
                           INNER JOIN {course_categories} cat1
                                   ON cx.instanceid = cat1.id ";

        $selectwhere = array('c.category = cat1.id');

        for ($i = 2; $i <= $categorylevels; $i++) {

            $onebefore = $i - 1;

            $select .=     "LEFT JOIN {course_categories} cat{$i}
                                   ON cat{$onebefore}.parent = cat{$i}.id ";
            $selectwhere[] =         "c.category = cat{$i}.id";

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
                          AND cx.instanceid = c.id
                        ) ";

        return $select;

    }
    
    /**
     *
     * @param type $forumid
     * @param type $reset returns the forum type
     */
    private function forum_is_eachuser($forumid) {
        
        global $DB;
        
        if (!is_null($this->iseachuser)) {
            return $this->iseachuser;
        }
        
        $forumtype = $DB->get_field('forum', 'type', array('id' => $forumid));
        
        $this->iseachuser = ($forumtype == 'eachuser') ? true : false;
        
        return $this->iseachuser;
    }
    
    /**
     * See parent class for docs
     * 
     * @return string
     */
    protected function get_sql_submissions_unique_column() {
        return 'd.id AS subid';
    }
    
    /**
     * See parent class for docs
     * 
     * @param int $forumid
     * @return array
     */
    protected function get_sql_submissions_select($forumid) {
        
        
        
        $extras =  array(
                //'d.id AS discussionid',
                'd.id AS subid',
                'COUNT(sub.id) AS count', // post count
                'MIN(sub.modified) AS time', // oldest unrated post
               // 'cm.id AS coursemoduleid',
        );
        
        // normal forum needs discussion title as label, participant usernames as description
        // eachuser needs username as title and discussion subject as description
        if ($this->forum_is_eachuser($forumid)) {
            array_push($extras, 'firstpost.subject AS description');
        } else {
            // TODO need a SELECT bit to get all userids of people in the discussion
            array_push($extras, 'firstpost.subject AS label', 'firstpost.message AS description');
        }
        
        return $extras;
    }
    
    /**
     * See parent class for docs
     * 
     * @return string
     */
    protected function get_sql_submissions_from() {

        $submissionstable = $this->get_sql_submission_table();
        
        return "INNER JOIN {{$submissionstable}} firstpost 
                        ON firstpost.id = d.firstpost ";
    }
    
    /**
     * See parent class for docs
     * 
     * @return string
     */
    protected function get_sql_submissions_groupby() {
        return 'd.id';
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
        return 'd.userid';
    }

    /**
     * gets all of the forums for all courses, ready for the config tree. Stores them as an object property
     *
     * @global object $CFG
     *
     * @return void
     */
    function get_all_gradable_items($courseids) {

        global $CFG, $DB;

        list($usql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);

        $sql = "SELECT f.id, f.course, f.intro as summary, f.name, f.type, c.id as cmid
                  FROM {forum} f
            INNER JOIN {course_modules} c
                    ON f.id = c.instance
                 WHERE c.module = :moduleid
                   AND c.visible = 1
                   AND f.course $usql
                   AND f.assessed > 0
              ORDER BY f.id";
        $params['moduleid'] = $this->get_module_id();
        $forums = $DB->get_records_sql($sql, $params);
        $this->assessments = $forums;
    }

    /**
     * Returns a HTML link allowing a student's work to be marked
     *
     * @param object $item a row of the database tabe representing one discussion post
     *
     * @return string
     */
    function make_html_link($item) {
        global $CFG;
        $address = $CFG->wwwroot.'/mod/forum/view.php?id='.$item->cmid;
        return $address;
    }
    
    /**
     * See superclass for details
     * 
     * @return array the select, join and where clauses, params array, and the aliases for module and submission tables
     */
    function get_sql_count() {
        
        global $USER, $DB;
        
        $submissiontable = $this->get_sql_submission_table();
        
        $teachersql = $this->get_teacher_sql();
        $moduletable = $this->get_sql_module_table();
        
        $from =     "FROM {{$submissiontable}} sub
                LEFT JOIN {rating} r
                       ON sub.id = r.itemid
               INNER JOIN {forum_discussions} d
                       ON sub.discussion = d.id
               INNER JOIN {{$moduletable}} moduletable
                       ON d.forum = moduletable.id ";
        
               //$DB->sql_compare_text('q.qtype')
               
        // TODO assuming here that we only want to rate the first post of an eachuser.
        // is it even possible to have other posts?
        $where =   "WHERE sub.userid <> :userid
                      AND moduletable.assessed > 0
                      AND ( ( ( r.userid <> :userid2) 
                                AND {$teachersql})
                              OR r.userid IS NULL)
                            AND ( ( .".$DB->sql_compare_text('moduletable.type')." != 'eachuser') 
                                  OR ( ".$DB->sql_compare_text('moduletable.type')." = 'eachuser' 
                                       AND sub.id = d.firstpost))
                       
                      AND ( (moduletable.assesstimestart = 0) 
                         OR (sub.created >= moduletable.assesstimestart) ) 
                      AND ( (moduletable.assesstimefinish = 0) 
                         OR (sub.created <= moduletable.assesstimefinish) ) ";
                      
        $params = array(
                'userid' => $USER->id, 
                'userid2' => $USER->id);
        
        return array($from, $where, $params);
    }
    
    /**
     * See parent class for docs
     * 
     * @return string
     */
    protected function get_sql_submission_table() {
        return 'forum_posts';
    }  
     
    /**
     * Makes the pop up contents for the grading interface
     * 
     * @global type $DB
     * @global type $PAGE
     * @global object $CFG
     * @global type $SESSION
     * @global type $USER
     * @global type $OUTPUT
     * @param array $params
     * @return string HTML
     */
    function grading_popup($params) {
        
        global $DB, $PAGE, $CFG, $SESSION, $USER, $OUTPUT;
        
        // Lifted from /mod/forum/discuss.php
        
//        $parent = $params['parent'];       // If set, then display this post and all children.
        //$mode   = $params['mode'];         // If set, changes the layout of the thread
//        $move   = $params['move'];         // If set, moves this discussion to another forum
        //$mark   = $params['mark'];       // Used for tracking read posts if user initiated.
        //$postid = $params['postid'];       // Used for tracking read posts if user initiated.

        $discussion = $DB->get_record('forum_discussions', array('id' => $params['subid']), '*', MUST_EXIST);
        $course     = $DB->get_record('course', array('id' => $discussion->course), '*', MUST_EXIST);
        $forum      = $DB->get_record('forum', array('id' => $discussion->forum), '*', MUST_EXIST);
        $cm         = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);
        
        // security - cmid is used to check context permissions earlier on, so it must match when derived 
        // from the discussion
        if (!($cm->id == $params['cmid'])) {
            print_error('Bad params!');
            return false;
        }

    /// Add ajax-related libs
        $PAGE->requires->yui2_lib('event');
        $PAGE->requires->yui2_lib('connection');
        $PAGE->requires->yui2_lib('json');

        // move this down fix for MDL-6926
        require_once($CFG->dirroot.'/mod/forum/lib.php');

        // Possibly, the view permission is being used to prevent certian forums from being accessed.
        // Might be best not to rely on just the rate one.
        $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
        require_capability('mod/forum:viewdiscussion', $modcontext, NULL, true, 'noviewdiscussionspermission', 'forum');

        // restrict news to allowed times
        if ($forum->type == 'news') {
            if (!($USER->id == $discussion->userid || (($discussion->timestart == 0
                || $discussion->timestart <= time())
                && ($discussion->timeend == 0 || $discussion->timeend > time())))) {
                
                print_error('invaliddiscussionid', 'forum', "$CFG->wwwroot/mod/forum/view.php?f=$forum->id");
            }
        }

        //add_to_log($course->id, 'forum', 'view discussion', $PAGE->url->out(false), $discussion->id, $cm->id);

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
            print_error('nopermissiontoview', 'forum', "$CFG->wwwroot/mod/forum/view.php?id=$forum->id");
        }

        // TODO what does this do?
//        if ($mark == 'read' or $mark == 'unread') {
//            if ($CFG->forum_usermarksread && forum_tp_can_track_forums($forum) && forum_tp_is_tracked($forum)) {
//                if ($mark == 'read') {
//                    forum_tp_add_read_record($USER->id, $postid);
//                } else {
//                    // unread
//                    forum_tp_delete_read_records($USER->id, $postid);
//                }
//            }
//        }


    /// Check to see if groups are being used in this forum
    /// If so, make sure the current person is allowed to see this discussion
    /// Also, if we know they should be able to reply, then explicitly set $canreply for performance reasons

        // DO NOT DELETE (yet)/////////////////////////////////////////////////////////
        // Do we want to allow replies? might break the ajax bit?
//        $canreply = forum_user_can_post($forum, $discussion, $USER, $cm, $course, $modcontext);
//        
//        if (!$canreply and $forum->type !== 'news') {
//            
//            if (isguestuser() or !isloggedin()) {
//                $canreply = true;
//            }
//            
//            if (!is_enrolled($modcontext) and !is_viewing($modcontext)) {
//                // allow guests and not-logged-in to see the link - they are prompted to log in after clicking the link
//                // normal users with temporary guest access see this link too, they are asked to enrol instead
//                $canreply = enrol_selfenrol_available($course->id);
//            }
//        }
        /////////////////////////////////////////////////////////////////////////////////
        
        // For now, restrict to rating only
        $canreply = false;
        
        // wWithout this, the nesting doesn't work properly as the css isn't picked up
        echo html_writer::start_tag('div', array('class' => 'path-mod-forum'));

        echo html_writer::start_tag('div', array('class' => 'discussioncontrols clearfix'));
        
        echo html_writer::start_tag('div', array('class' => 'discussioncontrol displaymode'));
        // we don't want to have the current mode returned in the url as well as the new one
        unset($params['mode']);
        $newurl = new moodle_url('/blocks/ajax_marking/actions/grading_popup.php', $params);
//        if ($forum->type == 'single') {
            // TODO needs testing
//            $select = new single_select($newurl, 'mode', forum_get_layout_modes(), $displaymode, null, "mode");
//            $select->class = "forummode";
//        } else {
            $select = new single_select($newurl, 'mode', forum_get_layout_modes(), $displaymode, null, "mode");
//        }
        echo $OUTPUT->render($select);
        echo html_writer::end_tag('div');
        
        // This will be how we mark the thing as completed, so that users don't have to rate everything
//        echo html_writer::start_tag('div', array('class' => 'discussioncontrol displaymode'));
//        echo html_writer::checkbox('markcomplete', 1, false).''.get_string('forummarkcomplete', 'block_ajax_marking');
//        echo html_writer::end_tag('div');
//        
//        echo html_writer::end_tag('div');

        // If user has not already posted and it's a Q & A forum...
        if ($forum->type == 'qanda' && !has_capability('mod/forum:viewqandawithoutposting', $modcontext) &&
                    !forum_user_has_posted($forum->id,$discussion->id,$USER->id)) {
            
            echo $OUTPUT->notification(get_string('qandanotify','forum'));
        }

        $canrate = has_capability('mod/forum:rate', $modcontext);
        forum_print_discussion($course, $cm, $forum, $discussion, $post, $displaymode, $canreply, $canrate);
        
        echo html_writer::end_tag('div');
        
        // Add JS to make the 

    }
    
//    static function print_css() {
//        
//        echo '#page-blocks-ajax_marking-actions-grading_popup .indent {}';
//        
//    }
    
    /**
     * This will save the data for the finished forums. Namely that they are now not to be shown as 
     * in need of marking unless the posts date from beyond this point in time.
     * 
     * @param type $data 
     */
    public function process_data($data) {
        
        // Validate everything
        
    }


}