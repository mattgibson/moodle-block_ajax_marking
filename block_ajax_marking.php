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
 * Main block file
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2008 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot.'/blocks/ajax_marking/lib.php');

/**
 * This class builds a marking block on the front page which loads assignments and submissions
 * dynamically into a tree structure using AJAX. All marking occurs in pop-up windows and each node
 * removes itself from the tree after its pop up is graded.
 *
 * @copyright 2008-2010 Matt Gibson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_ajax_marking extends block_base {

    /**
     * Standard init function, sets block title and version number
     *
     * @return void
     */
    public function init() {
        $this->title = get_string('ajaxmarking', 'block_ajax_marking');
    }

    /**
     * Standard specialization function
     *
     * @return void
     */
    public function specialization() {
        $this->title = get_string('marking', 'block_ajax_marking');
    }

    /**
     * Standard get content function returns $this->content containing the block HTML etc
     *
     * @return bool|\stdClass
     */
    public function get_content() {

        global $CFG, $PAGE;

        if (!isloggedin()) {
            // Save all the DB stuff for the non-logged in front page.
            return false;
        }

        $modclasses = block_ajax_marking_get_module_classes();

        // If the user has switched role, we want to hide the block if the role would not normally
        // see the block. Normally, we don't check at this level so that we can have overrides
        // at module level.
        if (!empty($PAGE->course->id) && is_role_switched($PAGE->course->id)) {
            $canseeblock = false;
            foreach ($modclasses as $mod) {
                if (has_capability($mod->get_capability(), $PAGE->context)) {
                    $canseeblock = true;
                }
                if (!$canseeblock) {
                    return false;
                }
            }
        }

        if ($this->content !== null) {
            return $this->content;
        }

        require_once($CFG->dirroot.'/blocks/ajax_marking/lib.php');

        $courses = block_ajax_marking_get_my_teacher_courses();
        // Grading permissions exist in at least one course, so display.
        if (count($courses) > 0 || block_ajax_marking_admin_see_all()) {

            $this->content = new stdClass();

            // Start building content output.
            $this->content->footer = '';
            $this->content->text = '<div id="block_ajax_marking">';

            // Add a style to hide the HTML list and prevent flicker.
            $this->content->text .= $this->anti_flicker_js();

            // Add the basic HTML for the rest of the stuff to fit into.
            $divs = '
                <div id="block_ajax_marking_hidden">
                    <div id="dynamicicons">';
            $divs .= $this->get_dynamic_icons_html();
            $divs .= '
                    </div>
                </div>
                <div id="treetabs"></div>
                <div id="block_ajax_marking_error"></div>';
            $this->content->text .= $divs;

            // Don't warn about javascript if the screenreader option is set - it was deliberate.
            $noscript = '<noscript>
                             <p>'.
                                 get_string('nojavascript', 'block_ajax_marking').
                            '</p>
                         </noscript>';
            $this->content->text .= $noscript;
            $this->content->text .= ' <div class="block_ajax_marking_spacer"></div>'.
                                    '</div>'; // End of #block_ajax_marking container.

            // Set things going.
            $PAGE->requires->js_init_call('M.block_ajax_marking.init_block', array(), true,
                                          $this->js_module());

            // We need to append all of the plugin specific javascript. This file will be
            // requested as part of a separate http request after the PHP has all been finished
            // with, so we do this cheaply to keep overheads low by not using setup.php and
            // having the js in static functions.
            foreach (array_keys($modclasses) as $modname) {
                $filename = '/blocks/ajax_marking/modules/'.$modname.'/'.$modname.'.js';
                if (file_exists($CFG->dirroot.$filename)) {
                    $PAGE->requires->js($filename);
                }
            }

        } else {
            // No grading permissions in any courses - don't display the block (student). Exception.
            // for when the block is just installed and the user can edit. Might look broken
            // otherwise.
            if (has_capability('moodle/course:manageactivities', $PAGE->context)) {
                $this->content = new stdClass();
                $this->content->text .= get_string('nogradedassessments', 'block_ajax_marking');
            } else {
                // This will stop the other functions like has_content() from running all the way
                // through this again.
                $this->content = false;
            }
        }

        return $this->content;
    }

    /**
     * Standard function - does the block allow configuration for specific instances of itself
     * rather than site-wide?
     *
     * @return bool false
     */
    public function instance_allow_config() {
        return false;
    }

    /**
     *
     * We need a rendered icon for each node type. We can't rely on CSS to do this
     * as there is no mechanism for generating it dynamically, i.e. having an arbitrary
     * number of CSS rules generated, one for each module plugin. These icons are
     * transplanted using JS to the nodes as needed.
     *
     * @return string HTML
     */
    private function get_dynamic_icons_html() {

        global $OUTPUT;

        $modclasses = block_ajax_marking_get_module_classes();

        $html = '';
        foreach (array_keys($modclasses) as $modname) {
            $html .= '<img id="block_ajax_marking_'.$modname.'_icon"
                                 class="dynamicicon"
                                 src="'.$OUTPUT->pix_url('icon', $modname).'"
                                 alt="'.$modname.'"
                                 title="'.$modname.'" />';
        }
        $html .= '<img id="block_ajax_marking_course_icon" class="dynamicicon"
                                 src="'.$OUTPUT->pix_url('c/course').'"
                                 alt="'.get_string('course').'"
                                 title="'.get_string('course').'" />';
        $html .= '<img id="block_ajax_marking_group_icon" class="dynamicicon"
                                 src="'.$OUTPUT->pix_url('c/group').'"
                                 alt="'.get_string('group').'"
                                 title="'.get_string('group').'" />';
        $html .= '<img id="block_ajax_marking_cohort_icon" class="dynamicicon"
                                 src="'.$OUTPUT->pix_url('c/group').'"
                                 alt="'.get_string('cohort', 'cohort').'"
                                 title="'.get_string('cohort', 'cohort').'" />';
        $html .= '<img id="block_ajax_marking_hide_icon" class="dynamicicon"
                                 src="'.$OUTPUT->pix_url('t/hide').'"
                                 alt="'.get_string('hide').'"
                                 title="'.get_string('hide').'" />';
        $html .= '<img id="block_ajax_marking_show_icon" class="dynamicicon"
                                 src="'.$OUTPUT->pix_url('t/show').'"
                                 alt="'.get_string('show').'"
                                 title="'.get_string('show').'" />';
        $html .= '<img id="block_ajax_marking_showgroups_icon" class="dynamicicon"
                                 src="'.$OUTPUT->pix_url('group-disabled', 'block_ajax_marking').'"
                                 alt="'.get_string('showgroups', 'block_ajax_marking').'"
                                 title="'.get_string('showgroups', 'block_ajax_marking').'" />';
        $html .= '<img id="block_ajax_marking_hidegroups_icon" class="dynamicicon"
                                 src="'.$OUTPUT->pix_url('group', 'block_ajax_marking').'"
                                 alt="'.get_string('hidegroups', 'block_ajax_marking').'"
                                 title="'.get_string('hidegroups', 'block_ajax_marking').'" />';

        return $html;
    }

    /**
     * Provides the inline javascript to hide stuff whilst the page is loading, preventing
     * flicker.
     *
     * @return string Script tag
     */
    private function anti_flicker_js() {
        return '<script type="text/javascript" defer="defer">
                  /* <![CDATA[ */
                  var styleElement = document.createElement("style");
                  styleElement.type = "text/css";
                  var hidehtml = "#block_ajax_marking_html_list, "+
                                 "#treetabs, "+
                                 "#totalmessage {display: none;}";
                  if (styleElement.styleSheet) {
                      styleElement.styleSheet.cssText = hidehtml;
                  } else {
                      styleElement.appendChild(document.createTextNode(hidehtml));
                  }
                  document.getElementsByTagName("head")[0].appendChild(styleElement);
                  /* ]]> */</script>';

    }

    /**
     * Provides the definition for the javascript module for the page to include
     *
     * @return array
     */
    private function js_module() {

        // Set up the javascript module, with any data that the JS will need.
        return array(
            'name' => 'block_ajax_marking',
            'fullpath' => '/blocks/ajax_marking/yui/mainwidget/mainwidget.js',
            'requires' => array(
                                'moodle-block_ajax_marking-mainwidget'
            ),
            'strings' => array(
                array('totaltomark',
                      'block_ajax_marking'),
                array('nogradedassessments',
                      'block_ajax_marking'),
                array('nothingtomark',
                      'block_ajax_marking'),
                array('refresh',
                      'block_ajax_marking'),
                array('configure',
                      'block_ajax_marking'),
                array('connectfail',
                      'block_ajax_marking'),
                array('connecttimeout',
                      'block_ajax_marking'),
                array('nogroups',
                      'block_ajax_marking'),
                array('showthisactivity',
                      'block_ajax_marking'),
                array('showthiscourse',
                      'block_ajax_marking'),
                array('showwithgroups',
                      'block_ajax_marking'),
                array('hidethisactivity',
                      'block_ajax_marking'),
                array('hidethiscourse',
                      'block_ajax_marking'),
                array('hidethiscourse',
                      'block_ajax_marking'),
                array('show',
                      'block_ajax_marking'),
                array('showgroups',
                      'block_ajax_marking'),
                array('choosegroups',
                      'block_ajax_marking'),
                array('recentitems',
                      'block_ajax_marking'),
                array('mediumitems',
                      'block_ajax_marking'),
                array('overdueitems',
                      'block_ajax_marking'),
                array('errorcontactadmin',
                      'block_ajax_marking'),
                array('recentitem',
                      'block_ajax_marking'),
                array('mediumitem',
                      'block_ajax_marking'),
                array('overdueitem',
                      'block_ajax_marking')
            )
        );
    }

}
