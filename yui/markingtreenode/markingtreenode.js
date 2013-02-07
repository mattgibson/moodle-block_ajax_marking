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
 * YUI3 JavaScript module for the nodes in the marking tree. This acts as a base which the config
 * tree overrides. This subclasses the textnode to make a node that will have an icon, and methods
 * to get.
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  20012 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

YUI.add('moodle-block_ajax_marking-markingtreenode', function (Y) {
    "use strict";

    /**
     * Name of this module as used by YUI.
     * @type {String}
     */
    var MARKINGTREENODENAME = 'markingtreenode',
        MARKINGTREENODE = function () {

            // Prevents IDE complaining abut undefined vars.
            this.data = {};
            this.data.returndata = {};
            this.data.displaydata = {};
            this.data.configdata = {};

            MARKINGTREENODE.superclass.constructor.apply(this, arguments);
        };

    /**
     * @class M.block_ajax_marking.markingtreenode
     */
    Y.extend(MARKINGTREENODE, Y.YUI2.widget.HTMLNode, {

        /**
         * Getter for the count of unmarked items for this node.
         *
         * @param type recent, medium, overdue, or null|false|0 to get the total.
         */
        get_count : function (type) {

            var result;

            if (type && typeof this.data.displaydata[type + 'count'] !== 'undefined') {
                result = parseInt(this.data.displaydata[type + 'count'], 10);
            } else if (typeof this.data.displaydata.itemcount !== 'undefined') {
                result = parseInt(this.data.displaydata.itemcount, 10);
            } else {
                result = false;
            }

            return result;
        },

        /**
         * Gets the current setting for the clicked node.
         *
         * @param {string} settingtype
         * @param {Integer|null} groupid
         */
        get_config_setting : function (settingtype, groupid) {

            var setting = null,
                errormessage,
                groups,
                group;

            switch (settingtype) {

                case 'display':

                case 'groupsdisplay':

                    setting = this.data.configdata[settingtype];
                    break;

                case 'group':

                    if (typeof groupid === 'undefined' || groupid === false) {
                        errormessage = 'Trying to get a group setting without specifying groupid';
                        M.block_ajax_marking.show_error(errormessage, false);
                        break;
                    }

                    groups = this.get_groups();
                    if (typeof groups === 'undefined') {
                        setting = null;
                    } else {
                        group = this.get_group_by_id(groups, groupid);
                        if (group === null) {
                            setting = null;
                        } else {
                            setting = group.display;
                        }
                    }
                    break;

                default:
                    M.block_ajax_marking.error('Invalid setting type: ' + settingtype);
            }

            // Moodle sends the settings as strings, but we want integers so we can do proper
            // comparisons.
            if (setting !== null) {
                setting = parseInt(setting, 10);
            }

            return setting;
        },

        /**
         * Given an array of groups and an id, this will loop over them till it gets the right one and
         * return it.
         *
         * @param {Array} groups
         * @param {int} groupid
         * @return array|bool
         */
        get_group_by_id: function (groups, groupid) {

            var numberofgroups = groups.length,
                i;

            for (i = 0; i < numberofgroups; i += 1) {
                if (groups[i].id === groupid) {
                    return groups[i];
                }
            }
            return null;
        },

        /**
         * Starts with this node and moves up the tree of ancestors until it finds one with a not null
         * value for groupsdisplay. Needed so we know whether to ask for groups nodes or user nodes
         * when a coursemodule is clicked.
         *
         * @return {int} 0 or 1
         */
        get_calculated_groupsdisplay_setting : function () {

            var groupsdisplay = null,
                node = this;

            while (groupsdisplay === null && !node.isRoot()) {
                groupsdisplay = node.get_config_setting('groupsdisplay', null);
                node = node.parent;
            }

            if (groupsdisplay === null) {
                groupsdisplay = 0; // Site default.
            }

            return groupsdisplay;
        },

        /**
         * Returns the name of whatever is in return data which isn't nextnodefilter.
         */
        get_current_filter_name : function () {
            return this.data.returndata.currentfilter;
        },

        /**
         * Setter for the name of the next filter to request for the server when this node is clicked.
         */
        set_nextnodefilter : function (newvalue) {
            this.data.returndata.nextnodefilter = newvalue;
        },

        /**
         * Returns the value of whatever is in return data which isn't nextnodefilter.
         */
        get_current_filter_value : function () {
            return this.data.returndata[this.data.returndata.currentfilter];
        },

        /**
         * Finds out what the default is for this group node, if it has no display setting.
         *
         * @param {string} settingtype
         * @param {int|Boolean} groupid
         * @return {int} the default - 1 or 0
         */
        get_default_setting : function (settingtype, groupid) {

            var defaultsetting = null,
                errormessage;

            if (!this.parent.isRoot()) { // Must be a coursemodule or lower.

                switch (settingtype) {

                    case 'group':
                        if (typeof groupid === 'undefined' || groupid === false) {
                            errormessage = 'Trying to get a group setting without specifying groupid';
                            M.block_ajax_marking.show_error(errormessage, false);
                        }
                        defaultsetting = this.parent.get_config_setting('group', groupid);
                        break;

                    case 'display':
                        defaultsetting = this.parent.get_config_setting('display', false);
                        break;

                    case 'groupsdisplay':
                        defaultsetting = this.parent.get_config_setting('groupsdisplay', false);
                        break;
                }
            }
            if (defaultsetting !== null) {
                return parseInt(defaultsetting, 10);
            }

            // This is the root default until there's a better way of setting it.
            switch (settingtype) {

                case 'group':
                case 'display':
                    return 1;

                case 'groupsdisplay':
                    return 0; // Cleaner if we hide the group nodes by default.
            }

            return 1; // Should never get to here.
        },

        /**
         * Coursemodules will have a modulename sent along with the other data. This gets it.
         *
         * @return {string} name of the module
         */
        get_modulename : function () {

            var result;

            if (typeof this.data.displaydata.modulename === 'undefined') {
                result = false;
            } else {
                result = this.data.displaydata.modulename;
            }

            return result;
        },

        /**
         * Returns the name of this node as it should be displayed on screen (without the count,
         * icon, etc).
         */
        get_displayname : function () {
            return this.data.displaydata.name;
        },

        /**
         * Recursive function to get the return data from this node and all its parents. Each parent
         * represents a filter e.g. 'only this course', so we need to send the id numbers and names
         * for the SQL to use in WHERE clauses.
         *
         */
        get_filters : function (includethis) {

            var filtername,
                filtervalue,
                nodefilters = [],
                node;

            // If requesting a set of child nodes, we treat this node as a parent. Otherwise, we want
            // to get a new count or something for this node, so want to include it's current filters
            // in a different way.
            if (includethis) {
                node = this;
            } else {
                node = this.parent;
            }

            if (typeof node.tree.supplementaryreturndata !== 'undefined') {
                nodefilters.push(this.tree.supplementaryreturndata);
            }

            while (!node.isRoot()) {
                filtername = node.get_current_filter_name();
                filtervalue = node.get_current_filter_value();
                nodefilters.push(filtername + '=' + filtervalue);

                node = node.parent;
            }
            return nodefilters;
        },

        /**
         * Gets the extra stuff that may be necessary for the pop up to be displayed properly.
         */
        get_popup_stuff : function () {

            var popupstuff = [],
                thing;

            for (thing in this.data.popupstuff) {

                if (this.data.popupstuff.hasOwnProperty(thing)) {
                    popupstuff.push(thing + '=' + this.data.popupstuff[thing]);
                }

            }
            return popupstuff;
        },

        /**
         * Helper function to get the config groups array or return an empty array if it's not there.
         *
         * @return {Array}
         */
        get_groups : function () {

            var arrayofgroups = [],
                group,
                typeofthing = Object.prototype.toString.call(this.data.configdata.groups);

            if (typeof this.data.configdata === 'object' && typeof this.data.configdata.groups === 'object') {

                if (typeofthing === '[object Object]') {
                    // JSON parsing turns the groups array into an object. More convenient as an array, so
                    // we convert.
                    for (group in this.data.configdata.groups) {
                        if (this.data.configdata.groups.hasOwnProperty(group)) {
                            arrayofgroups.push(this.data.configdata.groups[group]);
                        }
                    }

                    this.data.configdata.groups = arrayofgroups;
                }

                typeofthing = Object.prototype.toString.call(this.data.configdata.groups);
                if (typeofthing === '[object Array]') {
                    return this.data.configdata.groups;
                }

            }

            return [];
        },

        /**
         * Saves a new setting into the nodes internal store, so we can keep track of things.
         */
        set_config_setting : function (settingtype, newsetting) {

            var i,
                childnodes = this.children;

            // Allows for lazily not passing a value in.
            if (typeof newsetting === 'undefined') {
                newsetting = null;
            }

            this.data.configdata[settingtype] = newsetting;
            // Groupsdisplay will alter the type of nodes we should see next.
            if (settingtype === 'groupsdisplay') {
                this.set_nextnodefilter(this.tree.nextnodetype(this));
            }

            // All children now need to be set to 'inherit'.
            for (i = 0; i < childnodes.length; i += 1) {
                childnodes[i].set_config_setting(settingtype, null, true);
            }

        },

        /**
         * Helper function to update the display setting stored in a node of the tree, so that the tree
         * stores the settings as the database currently has them.
         *
         * @param {int} groupid
         * @param {int|Null} newsetting 1 or 0 or null
         */
        set_group_setting : function (groupid, newsetting) {

            var groups,
                group,
                childnodes = this.children,
                i;

            // Allows for lazily not passing a value in.
            if (typeof newsetting === 'undefined') {
                newsetting = null;
            }

            groups = this.get_groups();
            group = this.get_group_by_id(groups, groupid);

            if (group) { // Some child nodes are groups or users.
                group.display = newsetting;
                for (i = 0; i < childnodes.length; i += 1) {
                    childnodes[i].set_group_setting(groupid, null, true);
                }
            }

        },

        /**
         * Getter for the name of the filter that will supply the child nodes when the request is sent.
         */
        get_nextnodefilter : function () {

            var result;

            if (typeof this.data.returndata.nextnodefilter === 'undefined') {
                result = false;
            } else {
                result = this.data.returndata.nextnodefilter;
            }

            return result;
        },

        /**
         * Getter for the time that this node or it's oldest piece of work was submitted.
         * Oldest = urgency
         */
        get_time : function () {

            var result;

            if (typeof this.data.displaydata.timestamp === 'undefined') {
                result = false;
            } else {
                result = parseInt(this.data.displaydata.timestamp, 10);
            }

            return result;
        },

        /**
         * Setter for the count of unmarked items for this node.
         */
        set_count : function (newvalue, type) {

            var div,
                countbits;

            switch (type) {
                case 'recent':
                    this.data.displaydata.recentcount = parseInt(newvalue, 10);
                    break;

                case 'medium':
                    this.data.displaydata.mediumcount = parseInt(newvalue, 10);
                    break;

                case 'overdue':
                    this.data.displaydata.overduecount = parseInt(newvalue, 10);
                    break;

                default:
                    this.data.displaydata.itemcount = parseInt(newvalue, 10);

            }

            // Make the adjustment to the node's count (unless it's the non-displayed total).
            if (type) {
                div = document.getElementById('nodecount'+this.index);
                countbits = this.make_triple_count();
                if (div) {
                    div.innerHTML = countbits.count;
                    div.title = countbits.title;

                }
            }

        },

        /**
         * Takes the existing time and makes a css class based on it so we can see how late work is.
         * style is.
         */
        set_time_style : function () {

            var iconstyle = '',
                onethousandmilliseconds = 1000,
                fourdays = 345600,
                tendays = 864000,
                seconds,
                // Current unix time.
                currenttime = Math.round((new Date()).getTime() / onethousandmilliseconds);

            if (this.get_time() === false) {
                return;
            }

            seconds = currenttime-this.get_time();

            if (seconds < fourdays) {
                iconstyle = 'time-recent';
            } else if (seconds < tendays) {
                iconstyle = 'time-medium';
            } else {
                iconstyle = 'time-overdue';
            }

            this.contentStyle += ' '+iconstyle+' ';

        },

        /**
         * Takes the component counts and makes them into a HTML thingy for the node title and a tooltip
         * string. Returns them as an array to avoid looping over the counts twice.
         *
         * @return array the HTML count spans and the title
         */
        make_triple_count : function () {

            var suffix,
                titlearray = [],
                countarray = [],
                componentcounts;

            componentcounts = this.get_component_counts();

            if (componentcounts.recent) {
                countarray.push('<span id="recent'+this.index+'" class="recent">'+
                                    componentcounts.recent+'</span>');
                suffix = componentcounts.recent === 1 ? 'item' : 'items';
                titlearray.push(componentcounts.recent+' '+
                                    M.str.block_ajax_marking['recent'+suffix]);
            }
            if (componentcounts.medium) {
                countarray.push('<span id="medium'+this.index+'" class="medium">'+
                                    componentcounts.medium+'</span>');
                suffix = componentcounts.medium === 1 ? 'item' : 'items';
                titlearray.push(componentcounts.medium+' '+
                                    M.str.block_ajax_marking['medium'+suffix]);
            }
            if (componentcounts.overdue) {
                countarray.push('<span id="overdue'+this.index+'" class="overdue">'+
                                    componentcounts.overdue+'</span>');
                suffix = componentcounts.overdue === 1 ? 'item' : 'items';
                titlearray.push(componentcounts.overdue+' '+
                                    M.str.block_ajax_marking['overdue'+suffix]);
            }

            return {
                count : '<strong>(</strong>'+countarray.join('|')+'<strong>)</strong>',
                title : titlearray.join(', ')
            };
        },

        /**
         * Overrides the parent class method so we can ad in the count and icon.
         */
        getContentHtml : function () {

            var html,
                countbits,
                icon = this.get_dynamic_icon(this.get_icon_style());

            if (this.get_count(false)) {

                countbits = this.make_triple_count();

                html = '';

                if (icon) {
                    icon.className += ' nodeicon';
                    html += this.get_dynamic_icon_string(icon);
                }

                html += '<div class="nodelabelwrapper">';

                html += '<div class="nodecount" id="nodecount'+this.index+'"';
                html += ' title="'+countbits.title+'">';
                html += countbits.count;
                html += '</div> ';

                html += '<div class="nodelabel" >'+
                    this.get_displayname();
                html += '</div>';
                html += '<div class="block_ajax_marking_spacer">';
                html += '</div>';

                html += '</div>'; // End of wrapper.

            } else {
                html = this.get_displayname();
            }

            return html;
        },

        /**
         * Each node with a count will have three component counts: recent, medium and overdue.
         * This returns them as an object.
         */
        get_component_counts : function () {
            return {
                recent : parseInt(this.data.displaydata.recentcount, 10),
                medium : parseInt(this.data.displaydata.mediumcount, 10),
                overdue : parseInt(this.data.displaydata.overduecount, 10)};
        },

        /**
         * When child counts are altered, this needs to be called so that the node updates itself.
         * Should only be called when a node is marked as it will wipe any existing count. If a node
         * has no children right now, that will mean it is set to 0 and is removed from the tree.
         * This will also remove the current node from the tree if it has no children.
         */
        recalculate_counts : function () {

            var componentcounts,
                parentnode = this.parent,
                recentcount = 0,
                mediumcount = 0,
                overduecount = 0,
                itemcount = 0,
                numberofchildren = this.children.length,
                i,
                haschanged = false;

            // Loop over children, counting to get new totals.
            if (numberofchildren) {
                for (i = 0; i < numberofchildren; i += 1) {

                    componentcounts = this.children[i].get_component_counts();

                    recentcount += componentcounts.recent;
                    mediumcount += componentcounts.medium;
                    overduecount += componentcounts.overdue;
                }

                // Add those totals to the config for this node.
                if (recentcount !== this.get_count('recent')) {
                    haschanged = true;
                    this.set_count(recentcount, 'recent');
                }
                if (mediumcount !== this.get_count('medium')) {
                    haschanged = true;
                    this.set_count(mediumcount, 'medium');
                }
                if (overduecount !== this.get_count('overdue')) {
                    haschanged = true;
                    this.set_count(overduecount, 'overdue');
                }
                itemcount = recentcount+mediumcount+overduecount;
                this.set_count(itemcount);

            } else {
                haschanged = true;
                this.tree.hide_context_menu_before_node_removal(this);
                this.tree.removeNode(this, true);
            }

            // Tell the parent to do the same if it's not root and there has been a change.
            if (haschanged && !parentnode.isRoot()) {
                parentnode.recalculate_counts();
            } else if (haschanged && parentnode.isRoot()) {
                this.tree.update_total_count();
            }

        },

        /**
         * Makes the node's icon reflect it's type, which cannot be set through regular CSS.
         */
        get_icon_style : function () {

            var iconstyle,
                currentfilter = this.get_current_filter_name();


            // TODO what about extra ones like question?
            // TODO make sure this is called from refresh().
            currentfilter = currentfilter.substr(0, currentfilter.length-2); // Remove 'id' from end.
            if (currentfilter === 'coursemodule') {
                iconstyle = this.get_modulename();
            } else {
                iconstyle = currentfilter;
            }

            return iconstyle;
        },

        /**
         * Returns the long name of this node.
         */
        get_tooltip : function () {

            var tooltipexists,
                tooltip;

            tooltipexists = (typeof this.data.displaydata.tooltip !== 'undefined');
            tooltip = (tooltipexists) ? this.data.displaydata.tooltip : '';

            return this.data.displaydata.name+': '+tooltip;
        },

        /**
         * Fetches an icon from the hidden div where the theme has rendered them (taking all the theme and
         * module overrides into account), then returns it, optionally changing the alt text on the way
         */
        get_dynamic_icon: function (iconname, alttext) {

            var icon = Y.one('#block_ajax_marking_' + iconname + '_icon'),
                newicon;

            if (!icon) {
                return false;
            }

            newicon = icon.cloneNode(true); // Avoid altering the original one.

            if (newicon && typeof alttext !== 'undefined') {
                newicon.alt = alttext;
            }

            // avoid collisions
            try {
                delete newicon.id;
            } catch (e) {
                // keep IE9 happy
                newicon.id = null;
            }

            return newicon;
        },

        /**
         * Returns a HTML string representation of a dynamic icon
         */
        get_dynamic_icon_string: function (icon) {

            var html = '',
                tmp;

            if (icon) {
                // hacky way to get a string representation of an icon.
                // Without cloneNode(), it takes the node away from the original hidden div
                // so it only works once
                tmp = document.createElement("div");
                tmp.appendChild(icon.getDOMNode());
                html += tmp.innerHTML;
            }

            return html;
        },

        /**
         * If we want to get the child node for e.g. a group with ID of 567, this function will do it.
         * The idea is that we may wish to remove some nodes as right-click settings change.
         */
        get_child_node_by_filter_id : function (filtername, filtervalue) {

            var i,
                currentfiltervalue;

            for (i = 0; i < this.children.length; i += 1) {

                currentfiltervalue = parseInt(this.children[i].get_current_filter_value(), 10);

                if ((this.children[i].get_current_filter_name() === filtername) &&
                    (currentfiltervalue === parseInt(filtervalue, 10))) {

                    return this.children[i];
                }
            }

            return false;
        },

        /**
         * Gets the current setting, or the inherited setting as appropriate so we can show the right
         * thing.
         *
         * @param {string} settingtype group, display, groupsdisplay
         * @param {Integer} groupid
         */
        get_setting_to_display : function (settingtype, groupid) {

            if (typeof groupid === 'undefined') {
                groupid = false;
            }

            var setting = this.get_config_setting(settingtype, groupid);
            if (setting === null) {
                setting = this.get_default_setting(settingtype, groupid);
            }
            return setting;
        }

    }, {
        NAME : MARKINGTREENODENAME,
        ATTRS : {}
    });

    M.block_ajax_marking = M.block_ajax_marking || {};

    /**
     * Makes the new class accessible.
     *
     * @param config
     * @return {*}
     */
    M.block_ajax_marking.markingtreenode = MARKINGTREENODE;

}, '1.0', {
    requires : ['yui2-treeview']
});
