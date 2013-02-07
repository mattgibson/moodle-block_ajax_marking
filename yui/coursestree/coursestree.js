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
 * YUI3 JavaScript module for the courses tree
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  20012 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

YUI.add('moodle-block_ajax_marking-coursestree', function (Y) {
    "use strict";

    /**
     * Name of this module as used by YUI.
     * @type {String}
     */
    var COURSESTREENAME = 'coursestree',
        COURSESTREE = function () {
            COURSESTREE.superclass.constructor.apply(this, arguments);
        };

    /**
     * @class M.block_ajax_marking.coursestree
     */
    Y.extend(COURSESTREE, M.block_ajax_marking.markingtree, {

        /**
         * All nodes will be instances of this type
         */
        nodetype : M.block_ajax_marking.coursestreenode,

        /**
         * Used by initialise() to start the first AJAX call
         */
        initial_nodes_data : 'courseid=nextnodefilter',

        /**
         * This is to control what node the tree asks for next when a user clicks on a node
         *
         * @param {M.block_ajax_marking.markingtreenode} node can be false or undefined if not there
         * @return string|bool false if nothing
         */
        nextnodetype : function (node) {
            // If nothing else is found, make the node into a final one with no children.
            var nextnodefilter = false,
                groupsdisplay,
                moduleoverride,
                modulename = node.get_modulename(),
                currentfilter = node.get_current_filter_name();

            // Allow override by modules.
            moduleoverride = this.get_next_nodefilter_from_module(modulename,
                                                                                  currentfilter);

            // Groups first if there are any. Always coursemodule -> group, to keep it consistent.
            // Workshop has no meaningful way to display by group (so far), so we hide the groups if
            // The module says that the coursemodule nodes are final ones.
            groupsdisplay = node.get_calculated_groupsdisplay_setting();
            if (currentfilter === 'coursemoduleid' && groupsdisplay === 1 && moduleoverride !== false) {
                // This takes precedence over the module override
                return 'groupid';
            }

            if (moduleoverride !== null) {
                return moduleoverride;
            }

            // these are the standard progressions of nodes in the basic trees. Modules may wish
            // to modify these e.g. by adding extra nodes, stopping early without showing individual
            // students, or by allowing the user to choose a different order.
            //    var coursesnodes = {
            //        'root'           : 'courseid',
            //        'courseid'       : 'coursemoduleid',
            //        'coursemoduleid' : 'submissions'};

            //    var groupsnodes = {
            //        'root'           : 'groupid',
            //        'groupid'        : 'coursemoduleid',
            //        'cohortid'       : 'coursemoduleid',
            //        'coursemoduleid' : 'submissions'};

            // Courses tree

            switch (currentfilter) {

                case 'courseid':
                    return 'coursemoduleid';

                case 'userid': // the submissions nodes in the course tree
                    return false;

                case 'coursemoduleid':
                    nextnodefilter = 'userid';
                    break;

                case 'groupid':
                    nextnodefilter = 'userid';
                    break;

                default:
            }

            return nextnodefilter;
        },

        /**
         * Tell other trees they need a refresh.
         */
        notify_refresh_needed_after_config : function () {
            this.mainwidget.configtab_tree.set_needs_refresh(true);
            // M.block_ajax_marking.cohorts_tree.set_refresh_needed(true);
        },

        /**
         * Tells other trees to refresh after marking.
         */
        notify_refresh_needed_after_marking : function () {
            this.mainwidget.cohortstab_tree.set_needs_refresh(true);
        }

    }, {
        NAME : COURSESTREENAME, //module name is something mandatory.
        // It should be in lower case without space
        // as YUI use it for name space sometimes.
        ATTRS : {
            // TODO make this so that courses and cohorts tree use exactly the same code. Difference
            // will be params passed in: order of nodes and init code.
        }
    });

    M.block_ajax_marking = M.block_ajax_marking || {};

    /**
     * Makes the new class accessible.
     *
     * @param config
     * @return {*}
     */
    M.block_ajax_marking.coursestree = COURSESTREE;

}, '1.0', {
    requires : ['moodle-block_ajax_marking-markingtree',
                'moodle-block_ajax_marking-coursestreenode',
                'moodle-block_ajax_marking-contextmenu']
});
