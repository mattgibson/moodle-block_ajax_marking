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
 * YUI3 JavaScript module for the nodes in the courses tree.
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  20012 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

YUI.add('moodle-block_ajax_marking-coursestreenode', function (Y) {
    "use strict";

    /**
     * Name of this module as used by YUI.
     * @type {String}
     */
    var COURSESTREENODENAME = 'coursestreenode',

        COURSESTREENODE = function () {

            // Prevents IDE complaining abut undefined vars.
            this.data = {};
            this.data.returndata = {};
            this.data.displaydata = {};
            this.data.configdata = {};

            COURSESTREENODE.superclass.constructor.apply(this, arguments);
        };

    /**
     * M.block_ajax_marking.coursestreenode
     */
    Y.extend(COURSESTREENODE, M.block_ajax_marking.markingtreenode, {

        set_config_setting : function (settingtype, newsetting, childnode) {

            this.constructor.superclass.set_config_setting.call(this, settingtype, newsetting, childnode);

            var currentfiltername = this.get_current_filter_name();

            // Remove this node, but don't bother with the child nodes as it will just add CPU cycles
            // seeing as the removal of the parent will deal with them.
            if (!childnode &&
                settingtype === 'display' &&
                newsetting === 0) {

                // This should only be for the context menu - drop-down can't do hide.
                if (this.tree && this.tree.tab && this.tree.tab.contextmenu) {
                    this.tree.tab.contextmenu.hide();
                }

                // Node set to hide. Remove it from the tree.
                // TODO may also be an issue if site default is set to hide - null here ought to
                // mean 'hide'.
                this.tree.remove_node(this.index);

            } else if (this.expanded &&
                settingtype === 'groupsdisplay' &&
                currentfiltername === 'coursemoduleid') {

                // Need to reload with groups icons or non-groups icons as appropriate.
                this.tree.request_node_data(this);

            }
        },

        /**
         * Store the new setting and also update the node's appearance to reflect it.
         *
         * @param groupid
         * @param newsetting
         * @param ischildnode was the original call to set this on a parent node? true if so.
         */
        set_group_setting : function (groupid, newsetting, ischildnode) {

            var currenttab = M.block_ajax_marking.block.get_current_tab(),
                // Get child node for this group if there is one.
                groupchildnode = this.get_child_node_by_filter_id('groupid', groupid),
                actualsetting = this.get_setting_to_display('group', groupid),
                currentfiltername = this.get_current_filter_name();

            if (typeof newsetting === 'undefined') {
                newsetting = null;
            }

            // Superclass will store the value and trigger the process in child nodes.
            this.constructor.superclass.set_group_setting.call(this, groupid, newsetting);

            if (this.expanded && groupchildnode && actualsetting === 0) {

                // Might be that the group is being hidden via the context menu on a group child node.
                if (currenttab.contextmenu.clickednode === groupchildnode) {
                    currenttab.contextmenu.hide();
                }
                // Remove this group node from the tree if the inherited setting says 'hide'.
                this.tree.remove_node(groupchildnode.index);

            } else if (this.expanded &&
                !groupchildnode &&
                actualsetting === 1 &&
                currentfiltername === 'coursemoduleid') {

                // There are nodes there currently, so we need to refresh them to add the new one.
                this.tree.request_node_data(this);

            } else if (!this.expanded &&
                !ischildnode &&
                (currentfiltername === 'coursemoduleid' ||
                    currentfiltername === 'courseid')) {

                // We need to update the count via an AJAX call as we don't know how much of the
                // current count is due to which group.
                this.request_new_count();

            } else if (this.expanded &&
                !ischildnode &&
                currentfiltername === 'courseid') {

                // Need to get all child counts in one go to make it faster for client and server.
                this.request_new_child_counts();

            }
        },

        /**
         * Sends an AJAX request that will ask for a new count to be sent back when groups settings
         * have changed, but the node is not expanded.
         */
        request_new_count : function () {

            // Get the current ancestors' filters.
            var nodefilters = this.get_filters(false),
                // Add this particular node's filters.
                currentfilter = this.get_current_filter_name(),
                filtervalue = this.get_current_filter_value();

            nodefilters.push('currentfilter='+currentfilter);
            nodefilters.push('filtervalue='+filtervalue);
            // This lets the AJAX success code find the right node to add stuff to.
            nodefilters.push('nodeindex='+this.index);
            nodefilters = nodefilters.join('&');

//            Y.YUI2.util.Connect.asyncRequest('POST',
//                                            M.block_ajax_marking.ajaxcounturl,
//                                            M.block_ajax_marking.callback,
//                                            nodefilters);
            Y.io(this.tree.ajaxcounturl, {
                on: {
                    success: this.tree.mainwidget.ajax_success_handler,
                    failure: this.tree.mainwidget.ajax_failure_handler
                }, context: this.tree.mainwidget, method: 'post', data: nodefilters});
        },

        /**
         * Gets new counts for all child nodes so that when a group is hidden, we don't need to redraw.
         */
        request_new_child_counts : function () {

            var nodefilters = this.get_filters(true);
            nodefilters.push(this.get_nextnodefilter()+'=nextnodefilter');
            // This lets the AJAX success code find the right node to add stuff to.
            nodefilters.push('nodeindex='+this.index);
            nodefilters = nodefilters.join('&');

//            Y.YUI2.util.Connect.asyncRequest('POST',
//                                            M.block_ajax_marking.childnodecountsurl,
//                                            M.block_ajax_marking.callback,
//                                            nodefilters);
            Y.io(this.tree.childnodecountsurl, {
                on: {
                    success: this.tree.mainwidget.ajax_success_handler,
                    failure: this.tree.mainwidget.ajax_failure_handler
                }, context: this.tree.mainwidget, method: 'post', data: nodefilters});
        }

    }, {
        NAME : COURSESTREENODENAME,
        ATTRS : {}
    });

    M.block_ajax_marking = M.block_ajax_marking || {};

    /**
     * Makes the new class accessible.
     *
     * @param config
     * @return {*}
     */
    M.block_ajax_marking.coursestreenode = COURSESTREENODE;

}, '1.0', {
    requires : ['moodle-block_ajax_marking-markingtreenode']
});
