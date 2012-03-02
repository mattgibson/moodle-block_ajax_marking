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

// Modules that add their own javascript will have already defined this, but here just in case.

if (typeof(M.block_ajax_marking) === 'undefined') {
    M.block_ajax_marking = {};
}

/**
 * This holds the parent node so it can be referenced by other functions
 */
M.block_ajax_marking.parentnodeholder = '';

/**
 * This holds the callback function of the parent node so it can be called once all the child
 * nodes have been built
 */
M.block_ajax_marking.oncompletefunctionholder = '';

/**
 * This is the variable used by the openPopup function on the front page.
 */
M.block_ajax_marking.popupholder = '';

/**
 *
 */
M.block_ajax_marking.ajaxnodesurl = M.cfg.wwwroot + '/blocks/ajax_marking/actions/ajax_nodes.php';

/**
 * Change to true to see what settings are null (inherited) by having them marked in grey on the
 * context menu
 */
M.block_ajax_marking.showinheritance = true;


M.block_ajax_marking.tree_node = function (oData, oParent, expanded) {

    // Prevents IDE complaining abut undefined vars
    this.data = {};
    this.data.returndata = {};
    this.data.displaydata = {};
    this.data.configdata = {};

    /**
     * This subclasses the textnode to make a node that will have an icon, and methods to get
     */
    M.block_ajax_marking.tree_node.superclass.constructor.call(this, oData,
                                                               oParent, expanded);
};
YAHOO.lang.extend(M.block_ajax_marking.tree_node, YAHOO.widget.HTMLNode, {

    /**
     * Getter for the count of unmarked items for this node
     */
    get_count : function () {
        if (typeof this.data.displaydata.itemcount !== 'undefined') {
            return this.data.displaydata.itemcount;
        } else {
            return false;
        }
    },

    /**
     * Gets the current setting for the clicked node
     *
     * @param {string} settingtype
     * @param {int|Boolean} groupid
     */
    get_config_setting : function (settingtype, groupid) {

        var setting,
            errormessage,
            groups;

        switch (settingtype) {

            case 'display':

            case 'groupsdisplay':

                setting = this.data.configdata[settingtype];
                break;

            case 'group':

                if (typeof(groupid) === 'undefined' || groupid === false) {
                    errormessage = 'Trying to get a group setting without specifying groupid';
                    M.block_ajax_marking.show_error(errormessage);
                }

                groups = this.get_groups();
                if (typeof(groups) !== 'undefined') {
                    var group = M.block_ajax_marking.get_group_by_id(groups, groupid);
                    if (group === null) {
                        setting = null;
                    } else {
                        setting = group.display;
                    }
                } else {
                    setting = null;
                }
                break;

            default:
                M.block_ajax_marking.error('Invalid setting type: '+settingtype);
        }

        // Moodle sends the settings as strings, but we want integers so we can do proper comparisons
        if (setting !== null) {
            setting = parseInt(setting, 10);
        }

        return setting;
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
            groupsdisplay = 0; // site default
        }

        return groupsdisplay;
    },

    /**
     * Returns the name of whatever is in return data which isn't nextnodefilter
     */
    get_current_filter_name : function () {
        return this.data.returndata.currentfilter;
    },

    /**
     * Setter for the name of the next filter to request for the server when this node is clicked
     */
    set_nextnodefilter : function (newvalue) {
        this.data.returndata.nextnodefilter = newvalue;
    },

    /**
     * Returns the value of whatever is in return data which isn't nextnodefilter
     */
    get_current_filter_value : function () {
        return this.data.returndata[this.data.returndata.currentfilter];
    },

    /**
     * Finds out what the default is for this group node, if it has no display setting
     *
     * @param {string} settingtype
     * @param {int|Boolean} groupid
     * @return {int} the default - 1 or 0
     */
    get_default_setting : function (settingtype, groupid) {

        var defaultsetting = null,
            errormessage;

        if (!this.parent.isRoot()) { // Must be a coursemodule or lower

            switch (settingtype) {

                case 'group':
                    if (typeof(groupid) === 'undefined' || groupid === false) {
                        errormessage = 'Trying to get a group setting without specifying groupid';
                        M.block_ajax_marking.show_error(errormessage);
                    }
                    defaultsetting = this.parent.get_config_setting('group', groupid);
                    break;

                case 'display':
                    defaultsetting = this.parent.get_config_setting('display');
                    break;

                case 'groupsdisplay':
                    defaultsetting = this.parent.get_config_setting('groupsdisplay');
                    break;
            }
        }
        if (defaultsetting !== null) {
            return parseInt(defaultsetting, 10);
        }

        // This is the root default until there's a better way of setting it
        switch (settingtype) {

            case 'group':
            case 'display':
                return 1;

            case 'groupsdisplay':
                return 0; // Cleaner if we hide the group nodes by default
        }

        return 1; // should never get to here

    },

    /**
     * When settings are changed on a course node, the child nodes (coursemodules) need to know about
     * it so that subsequent right-clicks show settings as they currently are, not how the outdated
     * original data was from when the tree first loaded. The principle is that changing a course
     * resets all child nodes to the default.
     *
     * @param {string} settingtype either display, groupsdisplay or group
     * @param {int} groupid
     */
    update_child_nodes_config_settings : function (settingtype, groupid) {

        // get children
        var childnodes = this.children,
            groups;

        // loop
        for (var i = 0; i < childnodes.length; i++) {
            // if the child is a node, recurse
            if (childnodes[i].children.length > 0) {
                childnodes[i].update_child_nodes_config_settings(settingtype, groupid);
            }
            // update node config data
            switch (settingtype) {

                case 'display':
                case 'groupsdisplay':
                    childnodes[i].set_config_setting(settingtype, null);
                    break;

                case 'group':
                    childnodes[i].set_group_setting(groupid);
                    break;

                default:
            }
        }
    },

    /**
     * Coursemodules will have a modulename sent along with the other data. This gets it.
     *
     * @return {string} name of the module
     */
    get_modulename : function () {
        if (typeof(this.data.displaydata.modulename) !== 'undefined') {
            return this.data.displaydata.modulename;
        } else {
            return false;
        }
    },

    /**
     * Returns the name of this node as it should be displayed on screen (without the count, icon, etc)
     */
    get_displayname : function () {
        return this.data.displaydata.name;
    },

    /**
     * Recursive function to get the return data from this node and all its parents. Each parent
     * represents a filter e.g. 'only this course', so we need to send the id numbers and names for the
     * SQL to use in WHERE clauses.
     *
     */
    get_filters : function () {

        var filtername,
            filtervalue,
            nodefilters = [],
            node = this;

        if (typeof(node.tree.supplementaryreturndata) !== 'undefined') {
            nodefilters.push(this.tree.supplementaryreturndata);
        }

        while (!node.isRoot()) {
            filtername = node.get_current_filter_name();
            filtervalue = node.get_current_filter_value();
            nodefilters.push(filtername+'='+filtervalue);

            node = node.parent;
        }
        return nodefilters;
    },

    /**
     * Helper function to get the config groups array or return an empty array if it's not there.
     *
     * @return {Array}
     */
    get_groups : function () {

        if (typeof(this.data.configdata) === 'object' &&
            typeof(this.data.configdata.groups) === 'object') {

            return this.data.configdata.groups;
        } else {
            return [];
        }
    },


    /**
     * Saves a new setting into the nodes internal store, so we can keep track of things
     */
    set_config_setting : function (
        settingtype, newsetting) {
        this.data.configdata[settingtype] = newsetting;
    },

    /**
     * Helper function to update the display setting stored in a node of the tree, so that the tree
     * stores the settings as the database currently has them
     *
     * @param {YAHOO.widget.Node} groupid
     * @param {int|Null} newsetting 1 or 0 or null
     */
    set_group_setting : function (groupid, newsetting) {

        var groups,
            group;

        if (typeof(newsetting) === 'undefined') {
            newsetting = null;
        }

        groups = this.get_groups();
        group = M.block_ajax_marking.get_group_by_id(groups, groupid);
        group.display = newsetting;
    },

    /**
     * Getter for the name of the filter that will supply the child nodes when the request is sent
     */
    get_nextnodefilter : function () {

        if (typeof(this.data.returndata.nextnodefilter) !== 'undefined') {
            return this.data.returndata.nextnodefilter;
        } else {
            return false;
        }
    },

    /**
     * Getter for the time that this node or it's oldest piece of work was submitted. Oldest = urgency
     */
    get_time : function () {
        if (typeof(this.data.displaydata.time) === 'undefined') {
            return parseInt(this.data.displaydata.time, 10);
        } else {
            return false;
        }
    },

    /**
     * Setter for the count of unmarked items for this node
     */
    set_count : function (newvalue) {
        this.data.displaydata.itemcount = parseInt(newvalue, 10);
    },

    /**
     * Takes the existing time and makes a css class based on it so we can see how late work is
     */
    set_time_style : function () {

        var iconstyle = '',
            onethousandmilliseconds = 1000,
            sixhours = 21600,
            twelvehours = 43200,
            oneday = 86400,
            twodays = 172800,
            fivedays = 432000,
            tendays = 864000,
            twoweeks = 1209600,
            seconds,
            // current unix time
            currenttime = Math.round((new Date()).getTime() / onethousandmilliseconds);

        if (typeof(this.get_time()) === false) {
            return;
        }

        seconds = currenttime-this.get_time();

        if (seconds < sixhours) {
            // less than 6 hours
            iconstyle = 'icon-user-one';

        } else if (seconds < twelvehours) {
            // less than 12 hours
            iconstyle = 'icon-user-two';

        } else if (seconds < oneday) {
            // less than 24 hours
            iconstyle = 'icon-user-three';

        } else if (seconds < twodays) {
            // less than 48 hours
            iconstyle = 'icon-user-four';

        } else if (seconds < fivedays) {
            // less than 5 days
            iconstyle = 'icon-user-five';

        } else if (seconds < tendays) {
            // less than 10 days
            iconstyle = 'icon-user-six';

        } else if (seconds < twoweeks) {
            // less than 2 weeks
            iconstyle = 'icon-user-seven';

        } else {
            // more than 2 weeks
            iconstyle = 'icon-user-eight';
        }

        this.labelStyle += ' '+iconstyle;

    },

    /**
     * Overrides the parent class method so we can ad in the count and icon
     */
    getContentHtml : function() {

        var islastnode = (this.get_nextnodefilter() === false);

        if (this.get_count() && (this.get_count() > 1 || !islastnode)) {
            return '<strong>('+this.get_count()+')</strong> '+this.get_displayname();
        } else {
            return this.get_displayname();
        }
    }


});

/**
 * This subclasses the treenode to make a node that will have extra icons to show what the current
 * settings are for an item in the config tree
 */
M.block_ajax_marking.configtree_node = function (oData, oParent, expanded) {
    M.block_ajax_marking.configtree_node.superclass.constructor.call(this, oData,
                                                                     oParent, expanded);
};
YAHOO.lang.extend(M.block_ajax_marking.configtree_node, M.block_ajax_marking.tree_node, {

    /**
     * Get the markup for the configtree node.
     *
     * @method getNodeHtml
     * @return {string} The HTML that will render this node.
     */
    getNodeHtml : function () {

        var sb = [],
            i,
            displaysetting,
            groupsdisplaysetting,
            groupscurrentlydisplayed,
            groups,
            numberofgroups,
            display;

        sb[sb.length] = '<table id="ygtvtableel'+this.index+
            '" border="0" cellpadding="0" cellspacing="0" class="ygtvtable ygtvdepth'+
            this.depth;
        sb[sb.length] = ' ygtv-'+(this.expanded ? 'expanded' : 'collapsed');
        if (this.enableHighlight) {
            sb[sb.length] = ' ygtv-highlight'+this.highlightState;
        }
        if (this.className) {
            sb[sb.length] = ' '+this.className;
        }

        sb[sb.length] = '"><tr class="ygtvrow block_ajax_marking_label_row">';

        // Spacers cells to make indents
        for (i = 0; i < this.depth; ++i) {
            sb[sb.length] = '<td class="ygtvcell '+this.getDepthStyle(i)+
                '"><div class="ygtvspacer"></div></td>';
        }

        if (this.hasIcon) {
            sb[sb.length] = '<td id="'+this.getToggleElId();
            sb[sb.length] = '" class="ygtvcell ';
            sb[sb.length] = this.getStyle();
            sb[sb.length] = '"><a href="#" class="ygtvspacer">&#160;</a></td>';
        }

        // Make main label on its own row
        sb[sb.length] = '<td id="'+this.contentElId;
        sb[sb.length] = '" class="ygtvcell ';
        sb[sb.length] = this.contentStyle+' ygtvcontent" ';
        sb[sb.length] = (this.nowrap) ? ' nowrap="nowrap" ' : '';
        sb[sb.length] = ' colspan="4">';

        sb[sb.length] = '<table class="ygtvtable">'; //new
        sb[sb.length] = '<tr >';
        sb[sb.length] = '<td class="ygtvcell" colspan="5">';

        sb[sb.length] = this.getContentHtml();

        sb[sb.length] = '</td>';
        sb[sb.length] = '</tr>';

        // Info row
        sb[sb.length] = '<tr class="block_ajax_marking_info_row">';

        // Make display icon
        sb[sb.length] = '<td id="'+"block_ajax_marking_display_icon"+this.index;
        sb[sb.length] = '" class="ygtvcell ';
        displaysetting = this.get_config_setting('display', false);
        if (displaysetting === null) {
            displaysetting = this.get_default_setting('display', false);
        }
        if (displaysetting === 1) {
            sb[sb.length] = ' enabled ';
        } else {
            sb[sb.length] = ' disabled ';
        }
        sb[sb.length] = ' block_ajax_marking_node_icon block_ajax_marking_display_icon ';
        sb[sb.length] = '"><div class="ygtvspacer">&#160;</div></td>';

        // Make groupsdisplay icon
        sb[sb.length] = '<td id="'+'block_ajax_marking_groupsdisplay_icon'+this.index;
        sb[sb.length] = '" class="ygtvcell ';
        groupsdisplaysetting = this.get_config_setting('groupsdisplay', false);
        if (groupsdisplaysetting === null) {
            groupsdisplaysetting = this.get_default_setting('groupsdisplay', false);
        }
        if (groupsdisplaysetting === 1) {
            sb[sb.length] = ' enabled ';
        } else {
            sb[sb.length] = ' disabled ';
        }
        sb[sb.length] = ' block_ajax_marking_node_icon block_ajax_marking_groupsdisplay_icon ';
        sb[sb.length] = '"><div class="ygtvspacer">&#160;</div></td>';

        // Make groups icon
        sb[sb.length] = '<td id="'+'block_ajax_marking_groups_icon'+this.index;
        sb[sb.length] = '" class="ygtvcell ';
        sb[sb.length] = ' block_ajax_marking_node_icon block_ajax_marking_groups_icon ';
        sb[sb.length] = '"><div class="ygtvspacer">';

        // We want to show how many groups are currently displayed, as well as how many there are
        groupscurrentlydisplayed = 0;
        // Could be a course or coursemodule node

        groups = this.get_groups();
        numberofgroups = groups.length;

        if (this.get_current_filter_name() === 'courseid') {

            for (var h = 0; h < numberofgroups; h++) {

                display = groups[h].display;

                if (display === null) {
                    display = this.get_default_setting('group', groups[h].id);
                }
                if (parseInt(display, 10) === 1) {
                    groupscurrentlydisplayed++;
                }
            }
        }

        sb[sb.length] = groupscurrentlydisplayed+'/'+numberofgroups+' ';
        sb[sb.length] = '</div></td>';

        // Spacer cell
        sb[sb.length] = '<td class="ygtvcell"><div class="ygtvspacer"></div></td>';

        sb[sb.length] = '</tr>';
        sb[sb.length] = '</table>';

        sb[sb.length] = '</td>';
        sb[sb.length] = '</tr>';
        sb[sb.length] = '</table>';

        return sb.join("");

    }


});

/**
 * Should add all the groups from a config node to it's menu button
 *
 */
M.block_ajax_marking.groups_menu_button_render = function() {

    // Get node

    // Get groups from node

    // Add groups to this menu button
    this.addItems([

          { text : "Four", value : 4 },
          { text : "Five", value : 5 }

      ]);


};


M.block_ajax_marking.context_menu_base = function (p_oElement, p_oConfig) {
    M.block_ajax_marking.context_menu_base.superclass.constructor.call(this, p_oElement, p_oConfig);
};
YAHOO.lang.extend(M.block_ajax_marking.context_menu_base, YAHOO.widget.ContextMenu, {

    /**
     * Gets the groups from the course node and displays them in the contextmenu.
     *
     * Coming from an onclick in the context menu, so 'this' is the contextmenu instance
     */
    load_settings : function () {

        // Make the settings items and make sure they reflect the current settings as stored in the
        // tree node
        var groups,
            target,
            clickednode,
            currentfilter,
            choosegroupsmenu,
            choosegroupsmenuitem;

        this.clearContent();

        target = this.contextEventTarget;
        clickednode = M.block_ajax_marking.get_current_tab().displaywidget.getNodeByElement(target);

        // We don't want to allow the contextmenu for items that we can't hide yet. Right now it
        // only applies to courses and coursemodules
        currentfilter = clickednode.get_current_filter_name();
        if (currentfilter !== 'courseid' &&
            currentfilter !== 'coursemoduleid') {

            this.cancel();
            return false;
        }

        this.make_setting_menuitem('display', clickednode);
        this.make_setting_menuitem('groupsdisplay', clickednode);

        choosegroupsmenuitem = this.make_setting_menuitem('groups', clickednode);
        choosegroupsmenu = choosegroupsmenuitem.cfg.getProperty('submenu');

        groups = clickednode.get_groups();
        if (groups.length) {
            // Wipe all groups out of the groups sub-menu
            M.block_ajax_marking.contextmenu_add_groups_to_menu(choosegroupsmenu, clickednode);
            // Enable the menu item, since we have groups in it
            choosegroupsmenu.parent.cfg.setProperty("disabled", false);
        } else {
            // disable it so people can see that this is an option, but there are no groups
            choosegroupsmenu.parent.cfg.setProperty("disabled", true);
        }

        this.render();
        clickednode.toggleHighlight(); // so the user knows what node this menu is for

    },

    /**
     * Make sure the item reflects the current settings as stored in the tree node.
     *
     * @param {string} settingname
     * @param {YAHOO.widget.HTMLNode} clickednode
     */
    make_setting_menuitem : function (settingname, clickednode) {

        var menuitem,
            title,
            checked,
            currentsetting,
            defaultsetting;

        switch (settingname) {

            case 'display':
                title = M.str.block_ajax_marking.show;
                menuitem = {
                    onclick : { fn : M.block_ajax_marking.contextmenu_setting_onclick,
                        obj : {'settingtype' : 'display'} },
                    checked : true,
                    id : 'block_ajax_marking_context_show',
                    value : {}};
                break;

            case 'groupsdisplay':
                title = M.str.block_ajax_marking.showgroups;
                menuitem = {
                    onclick : { fn : M.block_ajax_marking.contextmenu_setting_onclick,
                        obj : {'settingtype' : 'groupsdisplay'} },
                    checked : false,
                    id : 'block_ajax_marking_context_showgroups',
                    value : {}};
                break;

            case 'groups':
                title = M.str.block_ajax_marking.choosegroups;
                menuitem = {
                    submenu : {
                        id : 'choosegroupssubmenu',
                        keepopen : true,
                        lazyload : true,
                        itemdata : []}
                };
                break;
        }

        menuitem = new YAHOO.widget.ContextMenuItem(title, menuitem);
        menuitem = this.addItem(menuitem);

        if (settingname !== 'groups') {
            checked = false;
            currentsetting = clickednode.get_config_setting(settingname);
            if (currentsetting !== null) {
                checked = currentsetting ? true : false;
                if (M.block_ajax_marking.showinheritance) {
                    menuitem.cfg.setProperty("classname", 'notinherited');
                }
            } else {
                defaultsetting = clickednode.get_default_setting(settingname);
                checked = defaultsetting ? true : false;
                if (M.block_ajax_marking.showinheritance) {

                    menuitem.cfg.setProperty("classname", 'inherited');
                }
            }
            menuitem.cfg.setProperty('checked', checked);
        }

        return menuitem;
    }

});


/**
 * Base class that can be used for the main and config trees. This extends the
 * YUI treeview class ready to add some new functions to it which are common to both the
 * main and config trees.
 *
 */
M.block_ajax_marking.tree_base = function (treediv) {
    M.block_ajax_marking.tree_base.superclass.constructor.call(this, treediv);

    this.singleNodeHighlight = true;
};

// make the base class into a subclass of the YUI treeview widget.
YAHOO.lang.extend(M.block_ajax_marking.tree_base, YAHOO.widget.TreeView, {

    /**
     * Subclasses may wish to have different nodes
     */
    nodetype : M.block_ajax_marking.tree_node,

    /**
     * New unified build nodes function
     *
     * @param nodesarray array
     */
    build_nodes : function (nodesarray) {

        var newnode,
            nodedata,
            islastnode,
            numberofnodes = nodesarray.length,
            m;

        if (typeof(M.block_ajax_marking.parentnodeholder) !== 'object') {
            M.block_ajax_marking.parentnodeholder = this.getRoot();
        }

        // cycle through the array and make the nodes
        for (m = 0; m < numberofnodes; m++) {

            nodedata = nodesarray[m];

            // Make the display data accessible to the node creation code
            nodedata.html = nodedata.displaydata.name;
            nodedata.title = nodedata.displaydata.tooltip;

            newnode = new this.nodetype(nodedata, M.block_ajax_marking.parentnodeholder, false);
            newnode.set_count(newnode.get_count()); // needed to convert to int

            // Some nodes won't be specific to a module, but this needs to be specified to avoid
            // silent errors
            // TODO make this happen as part of the constructor process
            newnode.set_nextnodefilter(this.nextnodetype(newnode));

            islastnode = (newnode.get_nextnodefilter() === false);

            // Set the node to load data dynamically, unless it has not sent a callback i.e. it's a
            // final node
            if (!islastnode) {
                newnode.setDynamicLoad(this.request_node_data);
            }

            // If the node has a time (of oldest submission) show urgency by adding a background colour
            newnode.set_time_style();
        }

    },

    /**
     * Builds the tree when the block is loaded, or refresh is clicked
     *
     * @return void
     */
    initialise : function () {

        // Get rid of the existing tree nodes first (if there are any), but don't re-render to avoid
        // flicker
        var rootnode = this.getRoot();
        this.removeChildren(rootnode);

        // Reload the data for the root node. We keep the tree object intact rather than destroying
        // and recreating in order to improve responsiveness.
        M.block_ajax_marking.parentnodeholder = rootnode;
        // If we don't do this, then after refresh, we get it trying to run the oncomplete thing from
        // the last node that was expanded.
        M.block_ajax_marking.oncompletefunctionholder = null;

        // show that the ajax request has been initialised
        YAHOO.util.Dom.addClass(document.getElementById('mainicon'), 'loaderimage');

        // send the ajax request
        YAHOO.util.Connect.asyncRequest('POST', M.block_ajax_marking.ajaxnodesurl,
                                        M.block_ajax_marking.callback, this.initial_nodes_data);

    },

    /**
     * Sorts things out after nodes have been added, or an error happened (so refresh still works)
     */
    rebuild_tree_after_ajax : function () {
        // finally, run the function that updates the original node and adds the children. Won't be
        // there if we have just built the tree
        if (typeof(M.block_ajax_marking.oncompletefunctionholder) === 'function') {
            // Take care - this will be executed in the wrong scope if not careful. it needs this to
            // be the tree
            M.block_ajax_marking.oncompletefunctionholder(); // node.loadComplete()
            M.block_ajax_marking.oncompletefunctionholder = ''; // prevent it firing next time
        } else {
            // The initial build doesn't set oncompletefunctionholder for the root node, so
            // we do it manually
            this.getRoot().loadComplete();
        }
        // the main tree will need the counts updated, but not the config tree
        this.update_parent_node(M.block_ajax_marking.parentnodeholder);

        //this.after_build(); // add any widgets that need to be rendered onto the nodes
    },

    /**
     * This function is called when a node is clicked (expanded) and makes the ajax request. It sends
     * thefilters from all parent nodes and the nextnodetype
     *
     * @param clickednode
     * @param callbackfunction
     */
    request_node_data : function (
        clickednode, callbackfunction) {

        // store details of the node that has been clicked for reference by later
        // callback function
        M.block_ajax_marking.parentnodeholder = clickednode;
        M.block_ajax_marking.oncompletefunctionholder = callbackfunction;

        // The callback function is the SQL GROUP BY for the next set of nodes, so this is separate
        var nodefilters = clickednode.get_filters();
        nodefilters.push('nextnodefilter='+clickednode.get_nextnodefilter());
        nodefilters = nodefilters.join('&');

        YAHOO.util.Connect.asyncRequest('POST',
                                        M.block_ajax_marking.ajaxnodesurl,
                                        M.block_ajax_marking.callback,
                                        nodefilters);
    },

    /**
     * function to update the parent node when anything about its children changes. It recalculates the
     * total count and displays it, then recurses to the next node up until it hits root, when it
     * updates the total count and stops
     *
     * @param parentnodetoupdate the node of the treeview object to alter the count of
     * @return void
     */
    update_parent_node : function (parentnodetoupdate) {

        // Sum the counts of all child nodes to get a total
        var nodechildrenlength = parentnodetoupdate.children.length;
        var nodecount = 0;
        for (var i = 0; i < nodechildrenlength; i++) {
            // stored as a string
            nodecount += parentnodetoupdate.children[i].get_count();
        }

        // If root, we want to stop recursing, after updating the count
        if (parentnodetoupdate.isRoot()) {

            //this.render();
            // update the tree's HTML after child nodes are added
            //parentnodetoupdate.loadComplete();

            this.totalcount = nodecount;
            document.getElementById('count').innerHTML = nodecount.toString();

        } else { // not the root, so update, then recurse

            // get this before the node is (possibly) destroyed:
            var nextnodeup = parentnodetoupdate.parent;
            // Dump any nodes with no children, but don't dump the root node - we want to be able to
            // refresh it
            if (nodechildrenlength === 0) {
                this.removeNode(parentnodetoupdate, true);
            } else { // Update the node with its new total

                parentnodetoupdate.set_count(nodecount);

               // parentnodetoupdate.update_node_label();
            }

            this.update_parent_node(nextnodeup);
        }

    },

    /**
     * Recalculates the total marking count by totalling the node counts of the tree
     *
     * @return void
     */
    recalculate_total_count : function () {
        var count = 0;
        this.totalcount = 0;
        var children = this.getRoot().children;
        var childrenlength = children.length;

        for (var i = 0; i < childrenlength; i++) {
            count = children[i].get_count();
            if (count) {
                this.totalcount += count;
            }
        }
    },

    /**
     * Makes it so that the total count displays the count of this tree
     */
    update_total_count : function () {
        this.recalculate_total_count();
        document.getElementById('count').innerHTML = this.totalcount.toString();
    },


    /**
     * This function updates the tree to remove the node of the pop up that has just been marked,
     * then it updates the parent nodes and refreshes the tree, then sets a timer so that the popup will
     * be closed when it goes to the 'changes saved' url.
     *
     * @param nodeuniqueid The id of the node to remove
     * @return void
     */
    remove_node : function (nodeuniqueid) {
        var nodetoremove = this.getNodeByProperty('index', nodeuniqueid);
        var parentnode = nodetoremove.parent;
        this.removeNode(nodetoremove, true);
        this.update_parent_node(parentnode);
    },

    /**
     * Empty function so that different tree subtypes can overrride. Used to initialise any stuff
     * that appears as part of the nodes e.g. groups dropdowns in the config tree.
     */
    add_groups_buttons : function () {}


});

/**
 * Constructor for the courses tree
 */
M.block_ajax_marking.courses_tree = function () {
    M.block_ajax_marking.tree_base.superclass.constructor.call(this, 'coursestree');
};

// make the courses tree into a subclass of the base class
YAHOO.lang.extend(M.block_ajax_marking.courses_tree, M.block_ajax_marking.tree_base, {

    /**
     * Used by initialise() to start the first AJAX call
     */
    initial_nodes_data : 'nextnodefilter=courseid',

    /**
     * This is to control what node the tree asks for next when a user clicks on a node
     *
     * @param {M.block_ajax_marking.tree_node} node can be false or undefined if not there
     * @return string|bool false if nothing
     */
    nextnodetype : function (node) {
        // if nothing else is found, make the node into a final one with no children
        var nextnodefilter = false,
            groupsdisplay,
            moduleoverride,
            modulename = node.get_modulename(),
            currentfilter = node.get_current_filter_name();

        // these are the standard progressions of nodes in the basic trees. Modules may wish to modify
        // these e.g. by adding extra nodes, stopping early without showing individual students, or by
        // allowing the user to choose a different order.
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
                groupsdisplay = node.get_calculated_groupsdisplay_setting();
                if (groupsdisplay == 1) {
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
        moduleoverride = M.block_ajax_marking.get_next_nodefilter_from_module(modulename,
                                                                              currentfilter);
        if (moduleoverride !== null) {
            return moduleoverride;
        }

        return nextnodefilter;

    }


});

/**
 * Constructor for the groups tree
 *
 * @constructor
 */
M.block_ajax_marking.cohorts_tree = function () {

    M.block_ajax_marking.tree_base.superclass.constructor.call(this, 'cohortstree');
};

// make the groups tree into a subclass of the base class
YAHOO.lang.extend(M.block_ajax_marking.cohorts_tree, M.block_ajax_marking.tree_base, {

    /**
     * Used by initialise() to start the AJAX call
     */
    initial_nodes_data : 'nextnodefilter=cohortid',

    /**
     * This is to control what node the cohorts tree asks for next when a user clicks on a node
     *
     * @param {M.block_ajax_marking.tree_node} node
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
                // If we don't have a setting for this node (null), keep going up the tree till we find an
                // ancestor that does, or we hit root, when we use the default.
                groupsdisplay = node.get_calculated_groupsdisplay_setting();
                if (groupsdisplay == 1) {
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
        moduleoverride = M.block_ajax_marking.get_next_nodefilter_from_module(modulename,
                                                                              currentfilter);
        if (moduleoverride) {
            return moduleoverride;
        }

        return nextnodefilter;

    }


});

/**
 * Constructor for the users tree
 */
M.block_ajax_marking.users_tree = function () {
    M.block_ajax_marking.tree_base.superclass.constructor.call(this, 'usersstree');
};

// make the users tree into a subclass of the base class
YAHOO.lang.extend(M.block_ajax_marking.users_tree, M.block_ajax_marking.tree_base,
                  {initial_nodes_data : 'nextnodefilter=userid'});

/**
 * Constructor for the config tree
 */
M.block_ajax_marking.config_tree = function () {

    M.block_ajax_marking.tree_base.superclass.constructor.call(this, 'configtree');
};

// make the config tree into a subclass of the base class. Tell it to use custom nodes
YAHOO.lang.extend(M.block_ajax_marking.config_tree, M.block_ajax_marking.tree_base, {

    nodetype : M.block_ajax_marking.configtree_node,

    initial_nodes_data : 'nextnodefilter=courseid&config=1',

    supplementaryreturndata : 'config=1',

    /**
     * This is to control what node the tree asks for next when a user clicks on a node
     *
     * @param {M.block_ajax_marking.tree_node} node
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
     * Empty function - the config tree has no need to update parent counts.
     */
    update_parent_node : function (parentnodetoupdate) {
    },

    /**
     * Will attach a YUI menu button to all nodes with all of the groups so that they can be set
     * to show or hide. Better than a non-obvious context menu.
     */
    add_groups_buttons : function () {

        var node,
            menu,
            groups,
            formattedgroups = [],
            nodecontents,
            groupsdivs = YAHOO.util.Dom.getElementsByClassName('block_ajax_marking_groups_icon');

        for (var i = 0; i < groupsdivs.length; i++) {

            node = this.getNodeByElement(groupsdivs[i]);

            // Check we don't already have a menu button. Skip if so as groups will not change
            // TODO destroy these items when refresh is pressed in order to prevent memory leaks.
            if (typeof node.groupsmenubutton !== 'undefined') {
                node.groupsmenubutton.destroy(); // todo test me
            }

            nodecontents = groupsdivs[i].firstChild.innerHTML;
            groupsdivs[i].innerHTML = '';

            // Instantiate a Menu Button with groups data. menu.addItem() doesn't work and stops
            // the menu from appearing. Possibly it doesn't add the items in the right place.
            groups = node.get_groups();
            formattedgroups = [];
            for (var k = 0; k < groups.length; k++) {
                formattedgroups.push({
                    text : groups[k].name,
                    value : groups[k].id
                                     });
            }
            node.groupsmenubutton = new YAHOO.widget.Button({ type : "menu",
                                                              label : nodecontents,
                                                              title : 'Show/hide individual groups',
                                                              name : 'groupsbutton-'+node.index,
                                                              menu : formattedgroups,
                                                              container : groupsdivs[i] });
        }


    },

    /**
     * Renders the tree boilerplate and visible nodes
     * @method render
     */
    render : function () {
        var Event = YAHOO.util.Event,
            html = this.root.getHtml(),
            el = this.getEl();
        el.innerHTML = html;
        if (!this._hasEvents) {
            Event.on(el, 'click', this._onClickEvent, this, true);
            Event.on(el, 'dblclick', this._onDblClickEvent, this, true);
            Event.on(el, 'mouseover', this._onMouseOverEvent, this, true);
            Event.on(el, 'mouseout', this._onMouseOutEvent, this, true);
            Event.on(el, 'keydown', this._onKeyDownEvent, this, true);
        }
        this._hasEvents = true;

        this.add_groups_buttons();
    },

    /**
     * Fets rid of the menus so we
     */
    destroy_groups_menus : function() {



    }


});


/**
 * Handles the clicks on the config tree icons so that they can toggle settings state
 *
 * @param {object} data
 */
M.block_ajax_marking.config_treenodeonclick = function (data) {

    var clickednode = data.node,
        settingtype;

    YAHOO.util.Event.stopEvent(data.event); // Stop it from expanding the tree

    // is the clicked thing an icon that needs to trigger some thing?
    var target = YAHOO.util.Event.getTarget(data.event); // the spacer <div>
    target = target.parentNode; // the <td>
    var coursenodeclicked = false;
    if (clickednode.get_current_filter_name() == 'courseid') {
        coursenodeclicked = true;
    }

    if (YAHOO.util.Dom.hasClass(target, 'block_ajax_marking_display_icon')) {
        settingtype = 'display';
    } else if (YAHOO.util.Dom.hasClass(target, 'block_ajax_marking_groupsdisplay_icon')) {
        settingtype = 'groupsdisplay';
    } else if (YAHOO.util.Dom.hasClass(target, 'block_ajax_marking_groups_icon')) {
        settingtype = 'groups';
        return false;
    } else {
        // Not one of the ones we want. ignore this click
        return false;
    }

    var currentsetting = clickednode.get_config_setting(settingtype);
    var defaultsetting = clickednode.get_default_setting(settingtype);
    var settingtorequest = 1;
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
    // gather data
    var requestdata = {};
    requestdata.nodeindex = clickednode.index;
    requestdata.settingtype = settingtype;
    if (settingtorequest !== null) { // leaving out defaults to null on the other end
        requestdata.settingvalue = settingtorequest;
    }
    requestdata.tablename = coursenodeclicked ? 'course' : 'course_modules';
    requestdata.instanceid = clickednode.get_current_filter_value();

    // send request
    M.block_ajax_marking.save_setting_ajax_request(requestdata);

    return false;
};


/**
 * OnClick handler for the nodes of the tree. Attached to the root node in order to catch all events
 * via bubbling. Deals with making the marking popup appear.
 *
 * @param {object} oArgs from the YUI event
 */
M.block_ajax_marking.treenodeonclick = function (oArgs) {

    // refs save space
    /**
     * @var M.block_ajax_marking.tree_node
     */
    var node = oArgs.node;
    var mbam = window.M.block_ajax_marking;

    // we only need to do anything if the clicked node is one of
    // the final ones with no children to fetch.
    if (node.get_nextnodefilter() !== false) {
        return false;
    }

    // Keep track of what we clicked so the user won't wonder what's in the pop up
    node.toggleHighlight();

    // Get window size, etc
    var popupurl = window.M.cfg.wwwroot+'/blocks/ajax_marking/actions/grading_popup.php?';
    var modulejavascript = mbam[node.get_modulename()];
    var popupargs = modulejavascript.pop_up_arguments(node);

    // New way:
    var nodefilters = node.get_filters();
    nodefilters.push('node='+node.index);
    popupurl += nodefilters.join('&');

    // Pop-up version
    mbam.popupholder = window.open(popupurl, 'ajax_marking_popup', popupargs);
    mbam.popupholder.focus();

    return false;
};


/**
 * Finds out whether there is a custom nextnodefilter defined by the specific module e.g.
 * quiz question. Allows the standard progression of nodes to be overridden.
 *
 * @param {string} modulename
 * @param {string} currentfilter
 * @return bool|string
 */
M.block_ajax_marking.get_next_nodefilter_from_module = function (modulename, currentfilter) {

    var nextnodefilter = null;
    if (typeof(modulename) === 'string') {
        if (typeof(M.block_ajax_marking[modulename]) === 'object') {
            var modulejavascript = M.block_ajax_marking[modulename];
            if (typeof(modulejavascript.nextnodetype) === 'function') {
                nextnodefilter = modulejavascript.nextnodetype(currentfilter);
            }
        }
    }

    return nextnodefilter;
};


/**
 * Turns the raw groups data from the tree node into menu items and attaches them to the menu. Uses
 * the course groups (courses will have all groups even if there are no settings) to make the full
 * list and combines course defaults and coursemodule settings when it needs to for coursemodules
 *
 * @param {YAHOO.widget.Menu} menu A pre-existing context menu
 * @param {YAHOO.widget.Node} clickednode
 * @return void
 */
M.block_ajax_marking.contextmenu_add_groups_to_menu = function (menu, clickednode) {

    var newgroup,
        groupdefault,
        groups,
        numberofgroups;

    // Remove all existing groups
    /*
    var existinggroups = menu.getItems();
    var numberofitems = existinggroups.length;
    for (var k = 0; k < numberofitems; k++) {
        // This eats array items and renumbers them as it goes, so we keep removing item 0
        menu.removeItem(existinggroups[0]);
    }
    */

    groups = clickednode.get_groups();
    numberofgroups = groups.length;

    for (var i = 0; i < numberofgroups; i++) {


        newgroup = { "text" : groups[i].name,
            "value" : { "groupid" : groups[i].id},
            "onclick" : { fn : M.block_ajax_marking.contextmenu_setting_onclick,
                obj : {'settingtype' : 'group'} } };

        newgroup = { "text" : 'text',
                     "value" : 2 };

        // Make sure the items' appearance reflect their current settings
        // JSON seems to like sending back integers as strings
        /*
        if (groups[i].display === "1") {
            // Make sure it is checked
            newgroup.checked = true;

        } else if (groups[i].display === "0") {
            newgroup.checked = false;

        } else if (groups[i].display === null) {
            // We want to show that this node inherits it's setting for this group
            // newgroup.classname = 'inherited';
            // Now we need to get the right default for it and show it as checked or not
            groupdefault = clickednode.get_default_setting('group', groups[i].id);
            newgroup.checked = groupdefault ? true : false;
            if (M.block_ajax_marking.showinheritance) {
                newgroup.classname = 'inherited';
            }
        }
        */
        menu.addItem(newgroup);
    }

    // If there are no groups, we want to show this rather than have the contextmenu fail to
    // pop up at all, leaving the normal one to apear in it's place
    if (numberofgroups === 0) {
        menu.addItem({"text" : M.str.block_ajax_marking.nogroups,
                         "value" : 0 });
    }

};


/**
 * Ajax success function called when the server responds with valid data, which checks the data
 * coming in and calls the right function depending on the type of data it is
 *
 * @param o the ajax response object, passed automatically
 * @return void
 */
M.block_ajax_marking.ajax_success_handler = function (o) {

    var errormessage;
    var ajaxresponsearray;
    var currenttab = M.block_ajax_marking.get_current_tab();

    try {
        ajaxresponsearray = YAHOO.lang.JSON.parse(o.responseText);
    } catch (error) {
        // add an empty array of nodes so we trigger all the update and cleanup stuff
        errormessage = '<strong>An error occurred:</strong><br />';
        errormessage += o.responseText;
        M.block_ajax_marking.show_error(errormessage);
    }

    // first object holds data about what kind of nodes we have so we can
    // fire the right function.
    //var payload = 'default';
    if (typeof(ajaxresponsearray) === 'object') {
        // If we have a neatly structured Moodle error, we want to display it
        if (typeof(ajaxresponsearray.error) !== 'undefined') {
            errormessage = '<strong>A Moodle error occurred:</strong><br />';
            errormessage += ajaxresponsearray.error+'<br />';
            errormessage += '<strong>Debug info:</strong><br />';
            errormessage += ajaxresponsearray.debuginfo+'<br />';
            errormessage += '<strong>Stacktrace:</strong><br />';
            errormessage += ajaxresponsearray.stacktrace+'<br />';
            M.block_ajax_marking.show_error(errormessage);

        } else if (typeof(ajaxresponsearray['gradinginterface']) !== 'undefined') {
            // We have gotten the grading form back. Need to add the HTML to the modal overlay
            // M.block_ajax_marking.gradinginterface.setHeader('');
            // M.block_ajax_marking.gradinginterface.setBody(ajaxresponsearray.content);

        } else if (typeof(ajaxresponsearray['nodes']) !== 'undefined') {
            currenttab.displaywidget.build_nodes(ajaxresponsearray.nodes);

        } else if (typeof(ajaxresponsearray['configsave']) !== 'undefined') {

            if (ajaxresponsearray['configsave'].success !== true) {
                M.block_ajax_marking.show_error('Config setting failed to save');
            } else {
                // Maybe it's a contextmenu settings change, maybe it's an icon click.
                if (ajaxresponsearray['configsave'].menuitemindex !== false) {
                    // We want to toggle the display of the menu item by setting it to
                    // the new value. Don't assume that the default hasn't changed.
                    M.block_ajax_marking.contextmenu_ajax_callback(ajaxresponsearray);
                } else { // assume a nodeid
                    M.block_ajax_marking.config_icon_success_handler(ajaxresponsearray);
                }
            }
        }
    }

    currenttab.displaywidget.rebuild_tree_after_ajax();
    YAHOO.util.Dom.removeClass(document.getElementById('mainicon'), 'loaderimage');
};

/**
 * Sorts out what needs to happen once a response is received from the server that a setting
 * has been saved for an individual group
 *
 * @param {object} ajaxresponsearray
 */
M.block_ajax_marking.contextmenu_ajax_callback = function (ajaxresponsearray) {

    var settingtype = ajaxresponsearray['configsave'].settingtype,
        menuitemindex = ajaxresponsearray['configsave'].menuitemindex,
        newsetting = ajaxresponsearray['configsave'].newsetting,
        menu;

    var submenus = M.block_ajax_marking.get_current_tab().contextmenu.getSubmenus();
    if (settingtype === 'group' && submenus.length > 0) {
        // groups are only in a submenu for main context menu. The config one, they're in the menu
        menu = submenus[0];
    } else {
        menu = M.block_ajax_marking.get_current_tab().contextmenu;
    }

    var clickeditem = menu.getItem(menuitemindex, 0);
    var target = M.block_ajax_marking.get_current_tab().contextmenu.contextEventTarget;
    var configtab = M.block_ajax_marking.get_current_tab();
    var clickednode = configtab.displaywidget.getNodeByElement(target);

    if (settingtype == 'display' && newsetting === 0) {
        // Item set to hide. Remove it from the tree.
        // TODO may also be an issue if sitedefault is set to hide - null here ought to mean 'hide'
        M.block_ajax_marking.remove_node_from_current_tab(clickednode.index);

        // this should only be for the contextmenu
        menu.hide();
        return;
    }

    var groupid = null;
    if (settingtype === 'group') {
        groupid = clickeditem.value.groupid;
    }

    // Update the menu item so the user can see the change
    if (newsetting === null) {
        // get default

        var defaultsetting = clickednode.get_default_setting(settingtype, groupid);
        //set default
        var checked = defaultsetting ? true : false;
        clickeditem.cfg.setProperty("checked", checked);
        // set inherited class
        if (M.block_ajax_marking.showinheritance) {
            clickeditem.cfg.setProperty("classname", 'inherited');
        }
    } else if (newsetting === 1) {
        clickeditem.cfg.setProperty("checked", true);
        if (M.block_ajax_marking.showinheritance) {
            clickeditem.cfg.setProperty("classname", 'notinherited');
        }
    } else if (newsetting === 0) {
        clickeditem.cfg.setProperty("checked", false);
        if (M.block_ajax_marking.showinheritance) {
            clickeditem.cfg.setProperty("classname", 'notinherited');
        }
    }

    // Update the menu item display value so that if it is clicked again, it will know
    // not to send the same ajax request and will toggle properly
    clickeditem.value.display = newsetting;
    // We also need to update the data held in the tree node, so that future requests are not
    // all the same as this one
    if (settingtype === 'group') {
        clickednode.set_group_setting(groupid, newsetting);
    } else {
        clickednode.set_config_setting(settingtype, newsetting);
    }

    // Update any child nodes to be 'inherited' now that this will be the way the settings
    // are on the server
    clickednode.update_child_nodes_config_settings(settingtype, groupid);

};


/**
 * Shows an error message in the div below the tree.
 * @param errormessage
 */
M.block_ajax_marking.show_error = function (errormessage) {

    if (typeof(M.cfg.developerdebug) === 'undefined') {
        errormessage = 'Error: Please contact your administrator.';
    }

    if (typeof(errormessage) === 'string') {
        document.getElementById('block_ajax_marking_error').innerHTML = errormessage;
    }
    YAHOO.util.Dom.setStyle('block_ajax_marking_error', 'display', 'block');
    document.getElementById('count').innerHTML = '?';
};

/**
 * function which fires if the AJAX call fails
 * TODO: why does this not fire when the connection times out?
 *
 * @param o the ajax response object, passed automatically
 * @return void
 */
M.block_ajax_marking.ajax_failure_handler = function (o) {

    // transaction aborted
    if (o.tId == -1) {
        // TODO what is this meant to do?
        // document.getElementById('status').innerHTML = M.str.block_ajax_marking.collapseString;
    }

    // communication failure
    if (o.tId === 0) {
        document.getElementById('status').innerHTML = M.str.block_ajax_marking.connectfail;
        //YAHOO.util.Dom.removeClass(M.block_ajax_marking.markingtree.icon, 'loaderimage');

        if (!document.getElementById('block_ajax_marking_collapse')) {
            M.block_ajax_marking.make_footer();
        }
    }
    M.block_ajax_marking.get_current_tab().displaywidget.rebuild_tree_after_ajax();
    YAHOO.util.Dom.removeClass(this.icon, 'loaderimage');
};

/**
 * If the AJAX connection times out, this will handle things so we know what happened
 */
M.block_ajax_marking.ajax_timeout_handler = function () {
    M.block_ajax_marking.show_error(M.str.block_ajax_marking.connecttimeout);
    M.block_ajax_marking.get_current_tab().displaywidget.rebuild_tree_after_ajax();
    YAHOO.util.Dom.removeClass(this.icon, 'loaderimage');
};

/**
 * Used by other functions to clear all child nodes from some element.
 *
 * @param parentnode a dom reference
 * @return void
 */
M.block_ajax_marking.remove_all_child_nodes = function (parentnode) {

    if (parentnode.hasChildNodes()) {
        while (parentnode.childNodes.length >= 1) {
            parentnode.removeChild(parentnode.firstChild);
        }
    }
};

/**
 * This is to generate the footer controls once the tree has loaded
 *
 * @return void
 */
M.block_ajax_marking.make_footer = function () {
    // Create all text nodes

    // the two links
    return new YAHOO.widget.Button({
                                       label : M.str.block_ajax_marking.refresh,
                                       id : 'block_ajax_marking_collapse',
                                       onclick : {fn : function () {
                                           document.getElementById('status').innerHTML = '';
                                           YAHOO.util.Dom.setStyle('block_ajax_marking_error',
                                                                   'display', 'none');
                                           M.block_ajax_marking.get_current_tab().displaywidget.initialise();
                                       }},
                                       container : 'block_ajax_marking_refresh_button'});

};


/**
 * Callback object for the AJAX call, which fires the correct function. Doesn't work when part
 * of the main class. Don't know why - something to do with scope.
 *
 * @return void
 */
M.block_ajax_marking.callback = {

    cache : false,
    success : M.block_ajax_marking.ajax_success_handler,
    failure : M.block_ajax_marking.ajax_failure_handler,
    abort : M.block_ajax_marking.ajax_timeout_handler,
    scope : M.block_ajax_marking,
    // TODO: timeouts seem not to be working all the time
    timeout : 10000

};


/**
 * Handles the process of updating the node's settings and altering the display of the clicked icon.
 */
M.block_ajax_marking.config_icon_success_handler = function (ajaxresponsearray) {

    var settingtype = ajaxresponsearray['configsave'].settingtype,
        nodeindex = ajaxresponsearray['configsave'].nodeindex,
        newsetting = ajaxresponsearray['configsave'].newsetting,
        groupid = ajaxresponsearray['configsave'].groupid;

    var configtab = M.block_ajax_marking.get_current_tab();
    var clickednode = configtab.displaywidget.getNodeByIndex(nodeindex);

    // Update the node's icon to reflect the new status
    if (settingtype === 'group') {
        clickednode.set_group_setting(groupid, newsetting);
    } else {
        clickednode.set_config_setting(settingtype, newsetting);
    }

    // Update any child nodes to be 'inherited' now that this will be the way the settings
    // are on the server
    clickednode.update_child_nodes_config_settings(settingtype, groupid);

};

/**
 * Returns the currently selected node from the tabview widget
 *
 * @return object
 */
M.block_ajax_marking.get_current_tab = function () {
    return M.block_ajax_marking.tabview.get('selection');
};

/**
 * We don't know what tab is current, but once the grading popup shuts, we need to remove the node
 * with the id it specifies. This decouples the popup from the tabs. Might want to extend this
 * to make sure that it sticks to the right tree too, in case someone fiddles with the tabs
 * whilst having the popup open.
 *
 * @param nodeid
 * @return void
 */
M.block_ajax_marking.remove_node_from_current_tab = function (nodeid) {

    var currenttab = M.block_ajax_marking.tabview.get('selection');
    currenttab.displaywidget.remove_node(nodeid);
};

/**
 * The initialising stuff to get everything started
 *
 * @return void
 */
M.block_ajax_marking.initialise = function () {

    YUI().use('tabview', function (Y) {
        // this waits till much too late. Can we trigger earlier?
        M.block_ajax_marking.tabview = new Y.TabView({
                                                         srcNode : '#treetabs'
                                                     });

        // Must render first so treeviews have container divs ready
        M.block_ajax_marking.tabview.render();

        // Define the tabs here and add them dynamically.
        var coursestab = new Y.Tab({
                                       'label' : 'Courses',
                                       'content' : '<div id="coursestree" class="ygtv-highlight"></div>'});
        M.block_ajax_marking.tabview.add(coursestab);
        coursestab.displaywidget = new M.block_ajax_marking.courses_tree();
        coursestab.displaywidget.render();
        coursestab.displaywidget.subscribe('clickEvent', M.block_ajax_marking.treenodeonclick);

        var cohortstab = new Y.Tab({
                                       'label' : 'Cohorts',
                                       'content' : '<div id="cohortstree" class="ygtv-highlight"></div>'});
        M.block_ajax_marking.tabview.add(cohortstab);
        cohortstab.displaywidget = new M.block_ajax_marking.cohorts_tree();
        cohortstab.displaywidget.render();
        cohortstab.displaywidget.subscribe('clickEvent', M.block_ajax_marking.treenodeonclick);

        var configtab = new Y.Tab({
                                      'label' : 'Config',
                                      'content' : '<div id="configtree" class="ygtv-highlight"></div>'});
        M.block_ajax_marking.tabview.add(configtab);
        configtab.displaywidget = new M.block_ajax_marking.config_tree();
        configtab.displaywidget.render();
        configtab.displaywidget.subscribe('clickEvent',
                                          M.block_ajax_marking.config_treenodeonclick);

        // Make the context menu for the courses tree
        // Attach a listener to the root div which will activate the menu
        // menu needs to be repositioned next to the clicked node
        // menu
        //
        // Menu needs to be made of
        // - show/hide toggle
        // - show/hide group nodes
        // - submenu to show/hide specific groups
        coursestab.contextmenu = new M.block_ajax_marking.context_menu_base(
            "maincontextmenu",
            {
                trigger : "coursestree",
                keepopen : true,
                lazyload : false
            }
        );
        // Initial render makes sure we have something to add and takeaway items from
        coursestab.contextmenu.render(document.body);
        // Make sure the menu is updated to be current with the node it matches
        coursestab.contextmenu.subscribe("triggerContextMenu",
                                         coursestab.contextmenu.load_settings);
        coursestab.contextmenu.subscribe("beforeHide",
                                         M.block_ajax_marking.contextmenu_unhighlight);

        // Make the groups menu for the config tree nodes. They don't need to have the main
        // context menu as they have clickable icons, so we just show the groups
        /*
        configtab.contextmenu = new M.block_ajax_marking.context_menu_base(
            "configcontextmenu",
            {
                trigger : "configtree",
                keepopen : true,
                lazyload : false
            }
        );
        configtab.contextmenu.render(document.body);

        configtab.contextmenu.subscribe("triggerContextMenu",
                                        M.block_ajax_marking.config_contextmenu_load_groups);
        configtab.contextmenu.subscribe("beforeHide",
                                        M.block_ajax_marking.contextmenu_unhighlight);
        */

        // Set event that makes a new tree if it's needed when the tabs change
        M.block_ajax_marking.tabview.after('selectionChange', function () {

            // get current tab and keep a reference to it
            var currenttab = M.block_ajax_marking.get_current_tab();

            if (typeof(currenttab.alreadyinitialised) === 'undefined') {
                currenttab.displaywidget.initialise();
                currenttab.alreadyinitialised = true;
            } else {
                currenttab.displaywidget.update_total_count();
            }
        });

        // TODO use cookies/session to store the one the user wants between sessions
        M.block_ajax_marking.tabview.selectChild(0); // this will initialise the courses tree

    });

    // unhide that tabs block - preventing flicker
    YUI().use('node', function (Y) {
        Y.one('#treetabs').setStyle('display', 'block');
        Y.one('#totalmessage').setStyle('display', 'block');
    });

    // workaround for odd https setups. Probably not needed in most cases, but you can get an error
    // without it if using non-https ajax on a https page
    if (document.location.toString().indexOf('https://') != -1) {
        M.cfg.wwwroot = M.cfg.wwwroot.replace('http:', 'https:');
    }

    // Make the footer
    if (!document.getElementById('block_ajax_marking_collapse')) {
        M.block_ajax_marking.make_footer();
    }
};

/**
 * This is to remove the highlight from the tree so we don't have any nodes highlighted whilst the
 * context menu is not shown. The idea of the highlights is so that you know what item you are
 * adjusting the settings for.
 */
M.block_ajax_marking.contextmenu_unhighlight = function () {

    var target = this.contextEventTarget;
    var clickednode = M.block_ajax_marking.get_current_tab().displaywidget.getNodeByElement(target);

    clickednode.unhighlight();
};


/**
 * OnClick handler for the contextmenu that sends an ajax request for the setting to be changed on
 * the server.
 *
 * @param event
 * @param otherthing
 * @param obj
 */
M.block_ajax_marking.contextmenu_setting_onclick = function (event, otherthing, obj) {

    // we want to set the class so that we can indicate whether the checked (show) status is
    // directly set, or whether it is inherited
    var settingtype = obj.settingtype;

    var target = this.parent.contextEventTarget;
    var clickednode = M.block_ajax_marking.get_current_tab().displaywidget.getNodeByElement(target);
    var coursenodeclicked = false;
    if (clickednode.get_current_filter_name() == 'courseid') {
        coursenodeclicked = true;
    }

    // What do we have as the current setting?
    var groupid = null;
    if (settingtype === 'group') {
        // Whatever it is, the user will probably want to toggle it, seeing as they have clicked it.
        // This means we want to assume that it needs to be the opposite of the default if there is
        // no current setting.
        groupid = this.value.groupid;
    }
    var currentsetting = clickednode.get_config_setting(settingtype, groupid);
    var defaultsetting = clickednode.get_default_setting(settingtype, groupid);
    var settingtorequest = 1;
    if (currentsetting === null) {
        settingtorequest = defaultsetting ? 0 : 1;
    } else {
        // There is an existing setting. The user toggled from the default last time, so
        // will want to toggle back to default. No point deliberately making a setting when we can
        // just use the default, leaving more flexibility if the defaults are changed (rare)
        settingtorequest = null;
    }

    // gather data
    var requestdata = {};
    requestdata.groupid = groupid;
    requestdata.menuitemindex = this.index;
    requestdata.settingtype = settingtype;
    if (settingtorequest !== null) { // leaving out defaults to null on the other end
        requestdata.settingvalue = settingtorequest;
    }
    requestdata.tablename = coursenodeclicked ? 'course' : 'course_modules';
    requestdata.instanceid = clickednode.get_current_filter_value();

    // send request
    M.block_ajax_marking.save_setting_ajax_request(requestdata);

};

/**
 * Given an array of groups and an id, this will loop over them till it gets the right one and
 * return it.
 *
 * @param {Array} groups
 * @param {int} groupid
 * @return array|bool
 */
M.block_ajax_marking.get_group_by_id = function (groups, groupid) {

    var numberofgroups = groups.length;
    for (var i = 0; i < numberofgroups; i++) {
        if (groups[i].id == groupid) {
            return groups[i];
        }
    }
    return null;
};


/**
 * Asks the server to change the setting for something
 *
 * @param {object} requestdata
 */
M.block_ajax_marking.save_setting_ajax_request = function (requestdata) {

    // Turn our object into a string that the AJAX stuff likes.
    var poststring;
    var temparray = [];
    for (var key in requestdata) {
        temparray.push(key+'='+requestdata[key]);
    }
    poststring = temparray.join('&');

    YAHOO.util.Connect.asyncRequest('POST',
                                    M.cfg.wwwroot+'/blocks/ajax_marking/actions/config_save.php',
                                    M.block_ajax_marking.callback,
                                    poststring);
};

/**
 * Attaches a listener to the pop up so that we will have the relevant node unhighlighted when
 * the pop up shuts
 *
 * @param {int} node
 */
M.block_ajax_marking.grading_unhighlight = function (node) {

    YAHOO.util.Event.addListener(window, 'unload', function (args) {

        // Get tree
        var tree = window.opener.M.block_ajax_marking.currenttab().displaywidget;

        // get node
        var node = tree.getNodeById(args.nodeid);

        // unhighlight node
        node.unhighlight();

    }, {'nodeid' : node});

};


/**
 * Updates the config menu context menu so that it has the groups and settings for that particular
 * node.
 *
 */
M.block_ajax_marking.config_contextmenu_load_groups = function () {

    // this = the contextmenu because it's an event handler calling the function

    var target = this.contextEventTarget;
    var clickednode = M.block_ajax_marking.get_current_tab().displaywidget.getNodeByElement(target);

    // add new groups
    M.block_ajax_marking.contextmenu_add_groups_to_menu(this, clickednode);

    // display
    this.render();

    clickednode.toggleHighlight();

};

