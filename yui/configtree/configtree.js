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
 * YUI3 JavaScript module for the config tree
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  20012 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

YUI.add('moodle-block_ajax_marking-configtree', function (Y) {
    "use strict";

    /**
     * Name of this module as used by YUI.
     * @type {String}
     */
    var CONFIGTREENAME = 'configtree',
        CONFIGTREE = function () {
            CONFIGTREE.superclass.constructor.apply(this, arguments);
            this.singleNodeHighlight = true;
            this.subscribe('clickEvent', this.clickhandler);
        };

    /**
     * @class M.block_ajax_marking.configtree
     */
    Y.extend(CONFIGTREE, M.block_ajax_marking.markingtree, {

        /**
         * All nodes will instances of this
         */
        nodetype : M.block_ajax_marking.configtreenode,

        /**
         * Sent when the tree is first loaded in order to get the first nodes
         */
        initial_nodes_data : 'courseid=nextnodefilter&config=1',

        /**
         * Sent when the tree asks for any AJAX data
         */
        supplementaryreturndata : 'config=1',

        /**
         * This is to control what node the tree asks for next when a user clicks on a node
         *
         * @param {M.block_ajax_marking.markingtreenode} node
         * @return string|bool false if nothing
         */
        nextnodetype : function (node) {
            // if nothing else is found, make the node into a final one with no children
            var nextnodefilter = false,
                currentfilter = node.get_current_filter_name();

            switch (currentfilter) {

                case 'courseid':
                    return 'coursemoduleid';

                case 'coursemoduleid':
                    nextnodefilter = false;
                    break;

                default:
                // any special nodes that came back from a module addition
            }

            return nextnodefilter;
        },

        /**
         * Sorts things out after nodes have been added, or an error happened (so refresh still works).
         * Overriding the main one so we can do the thing to add the buttons to the rendered nodes
         *
         * @param {Object} ajaxresponsearray
         */
        rebuild_parent_and_tree_count_after_new_nodes : function (ajaxresponsearray) {
            // finally, run the function that updates the original node and adds the children. Won't be
            // there if we have just built the tree.
            if (typeof M.block_ajax_marking.oncompletefunctionholder === 'function') {
                // Take care - this will be executed in the wrong scope if not careful. it needs this to
                // be the tree.
                if (typeof ajaxresponsearray.configsave === 'undefined') {
                    // Config tree updates it's own nodes after ajax saves as the config things are
                    // set via node.set_config_setting()
                    // node.loadComplete()
                    M.block_ajax_marking.oncompletefunctionholder();
                }
                M.block_ajax_marking.oncompletefunctionholder = ''; // prevent it firing next time.
            } else {
                // The initial build doesn't set oncompletefunctionholder for the root node, so
                // we do it manually.
                this.getRoot().loadComplete();
                this.add_groups_buttons();
            }
            // The main tree will need the counts updated, but not the config tree. This will hide
            // the count.
            this.update_total_count();

        },

        /**
         * Called by render(). Adds all the groups to the nodes when the tree is built.
         */
        add_groups_buttons : function (node) {

            var i,
                root = node || this.getRoot();

            for (i = 0; i < root.children.length; i++) {
                root.children[i].add_groups_button();
            }
        },

        /**
         * Tell other trees they need a refresh. Subclasses to override
         */
        notify_refresh_needed_after_config : function () {
            this.mainwidget.coursestab_tree.set_needs_refresh(true);
        },

        /**
         * Does not need refresh after marking, so deliberately empty. Should never be called, but here
         * in order to fulfil Liskov principle.
         */
        notify_refresh_needed_after_marking : function () {},

        /**
         * Should empty the count
         */
        update_total_count : function () {
            // Deliberately empty
        },

        /**
         * Adds the post-build javascript stuff tot make groups buttons
         *
         * @param nodesdata
         * @param nodeindex
         */
        build_nodes : function (nodesdata, nodeindex) {
            this.constructor.superclass.build_nodes.call(this, nodesdata, nodeindex);
            if (!nodeindex) { // Only missing for root node
                // RootNode is built into the tree libray so it cannot be subclassed to add this
                // function call to it's loadComplete() function.
                this.add_groups_buttons();
            }
        },

        /**
         * Handles the clicks on the config tree icons so that they can toggle settings state.
         * Overrides the function that makes the marking popups appear.
         *
         * @param {object} data
         */
        clickhandler : function (data) {

            var clickednode = data.node,
                settingtype,
                target,
                coursenodeclicked = false,
                currentsetting,
                defaultsetting,
                settingtorequest,
                requestdata = {};

            Y.YUI2.util.Event.stopEvent(data.event); // Stop it from expanding the tree

            // is the clicked thing an icon that needs to trigger some thing?
            target = Y.YUI2.util.Event.getTarget(data.event); // the img
            target = target.parentNode.parentNode; // the spacer <div> -> the <td>

            target = Y.one(target); // Get YUI3 node functions.

            if (clickednode.get_current_filter_name() === 'courseid') {
                coursenodeclicked = true;
            }

            if (target.hasClass('block_ajax_marking_display_icon')) {
                settingtype = 'display';
            } else if (target.hasClass('block_ajax_marking_groupsdisplay_icon')) {
                settingtype = 'groupsdisplay';
            } else if (target.hasClass('block_ajax_marking_groups_icon')) {
                settingtype = 'groups';
                return false; // TODO is this meant to be here?
            } else {
                // Not one of the ones we want. Ignore this click.
                return false;
            }

            currentsetting = clickednode.get_config_setting(settingtype);
            defaultsetting = clickednode.get_default_setting(settingtype);
            settingtorequest = 1;
            // Whatever it is, the user will probably want to toggle it, seeing as they have clicked it.
            // This means we want to assume that it needs to be the opposite of the default if there is
            // no current setting.
            if (currentsetting === null) {
                settingtorequest = defaultsetting ? 0 : 1;
            } else {
                // There is an existing setting. The user toggled from the default last time, so
                // will want to toggle back to default. No point deliberately making a setting when we can
                // just use the default, leaving more flexibility if the defaults are changed (rare)
                settingtorequest = null;
            }

            // do the AJAX request for the settings change
            // gather data.
            requestdata.nodeindex = clickednode.index;
            requestdata.settingtype = settingtype;
            if (settingtorequest !== null) { // leaving out defaults to null on the other end
                requestdata.settingvalue = settingtorequest;
            }
            requestdata.tablename = coursenodeclicked ? 'course' : 'course_modules';
            requestdata.instanceid = clickednode.get_current_filter_value();

            // send request
            this.save_setting_ajax_request(requestdata, clickednode);

            return false;
        },

        /**
         * Asks the server to change the setting for something
         *
         * @param {object} requestdata
         * @param clickednode
         */
        save_setting_ajax_request: function (requestdata, clickednode) {

            var poststring,
                temparray = [],
                key;

            M.block_ajax_marking.oncompletefunctionholder = function (justrefreshchildren) {
                // Sometimes the node will have been removed
                if (clickednode.tree) {
                    clickednode.refresh(justrefreshchildren);
                }
            };

            // Turn our object into a string that the AJAX stuff likes.

            for (key in requestdata) {
                if (requestdata.hasOwnProperty(key)) {
                    temparray.push(key + '=' + requestdata[key]);
                }
            }
            poststring = temparray.join('&');

//            Y.YUI2.util.Connect.asyncRequest('POST',
//                                            M.cfg.wwwroot + '/blocks/ajax_marking/actions/config_save.php',
//                                            this.callback,
//                                            poststring);

            Y.io(M.cfg.wwwroot + '/blocks/ajax_marking/actions/config_save.php', {
                on: {
                    success: clickednode.tree.mainwidget.ajax_success_handler,
                    failure: clickednode.tree.mainwidget.ajax_failure_handler
                }, context: clickednode.tree.mainwidget, method: 'post', data: poststring});
        }

    }, {
        NAME : CONFIGTREENAME, //module name is something mandatory.
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
    M.block_ajax_marking.configtree = CONFIGTREE;

}, '1.0', {
    requires : ['moodle-block_ajax_marking-markingtree', 'moodle-block_ajax_marking-configtreenode']
});
