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
 * YUI3 JavaScript module for the cohorts tree.
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  20012 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

YUI.add('moodle-block_ajax_marking-cohortstree', function (Y) {
    "use strict";

    /**
     * Name of this module as used by YUI.
     * @type {String}
     */
    var COHORTSTREENAME = 'cohortstree',
        COHORTSTREE = function () {
            COHORTSTREE.superclass.constructor.apply(this, arguments);
        };

    /**
     * @class M.block_ajax_marking.cohortstree
     */
    Y.extend(COHORTSTREE, M.block_ajax_marking.markingtree, {

        /**
         * Used by initialise() to start the AJAX call
         */
        initial_nodes_data : 'cohortid=nextnodefilter',

        /**
         * This is to control what node the cohorts tree asks for next when a user clicks on a node
         *
         * @param {M.block_ajax_marking.markingtreenode} node
         * @return string|bool false if nothing
         */
        nextnodetype : function (node) {

            // if nothing else is found, make the node into a final one with no children
            var nextnodefilter = false,
                moduleoverride,
                groupsdisplay,
                modulename = node.get_modulename(),
                currentfilter = node.get_current_filter_name();

            // Courses tree
            switch (currentfilter) {

                case 'cohortid':
                    return 'coursemoduleid';

                case 'userid': // the submissions nodes in the course tree
                    return false;

                case 'coursemoduleid':
                    // If we don't have a setting for this node (null), keep going up the tree
                    // till we find an ancestor that does, or we hit root, when we use the default.
                    groupsdisplay = node.get_calculated_groupsdisplay_setting();
                    if (groupsdisplay === 1) {
                        nextnodefilter = 'groupid';
                    } else {
                        nextnodefilter = 'userid';
                    }
                    break;

                case 'groupid':
                    nextnodefilter = 'userid';
                    break;

                default:
            }

            // Allow override by modules
            moduleoverride = this.get_next_nodefilter_from_module(modulename,
                                                                                  currentfilter);
            if (moduleoverride) {
                return moduleoverride;
            }

            return nextnodefilter;
        },

        /**
         * Tells other trees to refresh after marking.
         */
        notify_refresh_needed_after_marking : function () {
            this.mainwidget.coursestab_tree.set_needs_refresh(true);
        }

    }, {
        NAME : COHORTSTREENAME, //module name is something mandatory.
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
    M.block_ajax_marking.cohortstree = COHORTSTREE;

}, '1.0', {
    requires : ['moodle-block_ajax_marking-markingtree',
                'moodle-block_ajax_marking-markingtreenode',
                'moodle-block_ajax_marking-contextmenu']
});
