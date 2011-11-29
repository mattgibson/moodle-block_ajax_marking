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
 * This file contains all the javascript for the AJAX Marking block. The script tag at the top is
 * so that the ide will do
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2007 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


if (typeof(M.block_ajax_marking) === 'undefined') {
    M.block_ajax_marking = {};
}

// used to determine whether to log everything to console
//const debugdeveloper = 38911;
//const debugall       = 6143;

//this holds the parent node so it can be referenced by other functions
M.block_ajax_marking.parentnodeholder = '';
// this holds the callback function of the parent node so it can be called once all the child
// nodes have been built
M.block_ajax_marking.oncompletefunctionholder = '';
// this is the variable used by the openPopup function on the front page.
M.block_ajax_marking.popupholder = '';
// this holds the timer that keeps trying to add the onclick stuff to the pop ups as the pop up
// loads
M.block_ajax_marking.popuptimer = '';

M.block_ajax_marking.ajaxnodesurl = M.cfg.wwwroot+'/blocks/ajax_marking/actions/ajax_nodes.php';
M.block_ajax_marking.ajaxgradeurl = M.cfg.wwwroot+'/blocks/ajax_marking/actions/grading_popup.php';

/**
 * Change to true to see what settings are null (inherited) by having them marked in grey on the
 * context menu
 */
M.block_ajax_marking.showinheritance = true;

/**
 * Base class that can be used for the main and config trees. This extends the
 * YUI treeview class ready to add some new functions to it which are common to both the
 * main and config trees.
 *
 */
M.block_ajax_marking.tree_base = function(treediv) {
    M.block_ajax_marking.tree_base.superclass.constructor.call(this, treediv);

    this.singleNodeHighlight = true;
};

// make the base class into a subclass of the YUI treeview widget
YAHOO.lang.extend(M.block_ajax_marking.tree_base, YAHOO.widget.TreeView);


/**
 * Constructor for the courses tree
 */
M.block_ajax_marking.courses_tree = function() {
    this.initial_nodes_data = 'nextnodefilter=courseid';
    M.block_ajax_marking.tree_base.superclass.constructor.call(this, 'coursestree');
};

// make the courses tree into a subclass of the base class
YAHOO.lang.extend(M.block_ajax_marking.courses_tree, M.block_ajax_marking.tree_base);

/**
 * Constructor for the groups tree
 *
 * @constructor
 */
M.block_ajax_marking.cohorts_tree = function() {
    this.initial_nodes_data = 'nextnodefilter=cohortid';
    M.block_ajax_marking.tree_base.superclass.constructor.call(this, 'cohortstree');
};

// make the groups tree into a subclass of the base class
YAHOO.lang.extend(M.block_ajax_marking.cohorts_tree, M.block_ajax_marking.tree_base);

/**
 * Constructor for the users tree
 */
M.block_ajax_marking.users_tree = function() {
    this.initial_nodes_data = 'nextnodefilter=userid';
    M.block_ajax_marking.tree_base.superclass.constructor.call(this, 'usersstree');
};

// make the users tree into a subclass of the base class
YAHOO.lang.extend(M.block_ajax_marking.users_tree, M.block_ajax_marking.tree_base);

/**
 * Constructor for the config tree
 */
M.block_ajax_marking.config_tree = function() {
    this.initial_nodes_data = 'nextnodefilter=courseid&config=1';
    this.supplementaryreturndata = 'config=1';
    M.block_ajax_marking.tree_base.superclass.constructor.call(this, 'configtree');
};

// make the config tree into a subclass of the base class
YAHOO.lang.extend(M.block_ajax_marking.config_tree, M.block_ajax_marking.tree_base);

/**
 * Makes a label out of the text (name of the node) and the count of unmarked items
 *
 * @param text
 * @param count
 * @return string
 */
M.block_ajax_marking.tree_base.prototype.node_label = function(text, count) {
    return '('+count+') '+text;
};

/**
 * New unified build nodes function
 *
 * @param nodesarray array
 */
M.block_ajax_marking.tree_base.prototype.build_nodes = function(nodesarray) {

    var newnode,
        nodedata,
        currentfilter,
        modulename,
        iconstyle,
        seconds = 0, currenttime = Math.round((new Date()).getTime() / 1000), // current unix time
        numberofnodes = nodesarray.length, m = 0;
    if (typeof(M.block_ajax_marking.parentnodeholder) !== 'object') {
        M.block_ajax_marking.parentnodeholder = this.getRoot();
    }

    // cycle through the array and make the nodes
    for (m = 0; m < numberofnodes; m++) {

        nodedata = nodesarray[m];

        // Make the display data accessible to the node creation code
        nodedata.label = nodedata.displaydata.name;
        nodedata.title = nodedata.displaydata.tooltip;

        // Get current filter name. Assumes only one value in returndata
        for (var filtername in nodedata.returndata) {
            if (typeof(filtername) !== 'undefined') {
                // TODO no need for the callbackfunction check once sorted out
                currentfilter = filtername;
                break; // should only be one of them
            }
        }
        // Some nodes won't be specific to a module, but this needs to be specified to avoid
        // silent errors
        modulename = (typeof(nodedata.displaydata.modulename) !== 'undefined') ?
                nodedata.displaydata.modulename : false;
        nodedata.returndata.nextnodefilter = this.nextnodetype(currentfilter, modulename);

        // Add a count if we have more than one thing or we're not at the final node
        // e.g. student name
        if (typeof(nodedata.displaydata.count) !== 'undefined' &&
            (nodedata.displaydata.count > 1 || nodedata.returndata.nextnodefilter !== false)) {

            nodedata.label = this.node_label(nodedata.label, nodedata.displaydata.count);
        }

        newnode = new YAHOO.widget.TextNode(nodedata, M.block_ajax_marking.parentnodeholder, false);

        // set the node to load data dynamically, unless it has not sent a callback i.e. it's a
        // final node

        if (typeof(nodedata.returndata.nextnodefilter) !== 'undefined' &&
                nodedata.returndata.nextnodefilter !== false) {

            newnode.setDynamicLoad(this.request_node_data);
        }

        // We assume that the modules have added any css they want to add in styles.php
        if (typeof(nodedata.displaydata.style) !== 'undefined') {
            newnode.labelStyle = 'block_ajax_marking_node_' + nodedata.displaydata.style;
        }

        // If the node has a time (of oldest submission) show urgency by adding a background colour
        if (typeof(nodedata.displaydata.time) !== 'undefined') {

            iconstyle = '';

            seconds = currenttime - parseInt(nodedata.displaydata.time, 10);

            if (seconds < 21600) {
                // less than 6 hours
                iconstyle = 'icon-user-one';

            } else if (seconds < 43200) {
                // less than 12 hours
                iconstyle = 'icon-user-two';

            } else if (seconds < 86400) {
                // less than 24 hours
                iconstyle = 'icon-user-three';

            } else if (seconds < 172800) {
                // less than 48 hours
                iconstyle = 'icon-user-four';

            } else if (seconds < 432000) {
                // less than 5 days
                iconstyle = 'icon-user-five';

            } else if (seconds < 864000) {
                // less than 10 days
                iconstyle = 'icon-user-six';

            } else if (seconds < 1209600) {
                // less than 2 weeks
                iconstyle = 'icon-user-seven';

            } else {
                // more than 2 weeks
                iconstyle = 'icon-user-eight';
            }

            newnode.labelStyle = iconstyle;
        }
    }

};

/**
 * Sorts things out after nodes have been added, or an error happened (so refresh still works)
 */
M.block_ajax_marking.tree_base.prototype.rebuild_tree_after_ajax = function() {
    this.render();
    // finally, run the function that updates the original node and adds the children. Won't be
    // there if we have just built the tree
    if (typeof(M.block_ajax_marking.oncompletefunctionholder) === 'function') {
        // Take care - this will be executed in the wrong scope if not careful. it needs this to
        // be the tree
        M.block_ajax_marking.oncompletefunctionholder();
    }
    // the main tree will need the counts updated, but not the config tree
    this.update_parent_node(M.block_ajax_marking.parentnodeholder);
};

/**
 * This function is called when a node is clicked (expanded) and makes the ajax request. It sends
 * thefilters from all parent nodes and the nextnodetype
 *
 * @param clickednode
 * @param callbackfunction
 */
M.block_ajax_marking.tree_base.prototype.request_node_data = function(clickednode,
                                                                      callbackfunction) {

    // store details of the node that has been clicked for reference by later
    // callback function
    M.block_ajax_marking.parentnodeholder = clickednode;
    M.block_ajax_marking.oncompletefunctionholder = callbackfunction;

    // The callback function is the SQL GROUP BY for the next set of nodes, so this is separate
    var nodefilters = M.block_ajax_marking.getnodefilters(clickednode);
    nodefilters.push('nextnodefilter=' + clickednode.data.returndata.nextnodefilter);
    nodefilters = nodefilters.join('&');

    YAHOO.util.Connect.asyncRequest('POST', M.block_ajax_marking.ajaxnodesurl,
                                    M.block_ajax_marking.callback, nodefilters);
};

/**
 * function to update the parent node when anything about its children changes. It recalculates the
 * total count and displays it, then recurses to the next node up until it hits root, when it
 * updates the total count and stops
 *
 * @param parentnodetoupdate the node of the treeview object to alter the count of
 * @return void
 */
M.block_ajax_marking.tree_base.prototype.update_parent_node = function(parentnodetoupdate) {

    // Sum the counts of all child nodes to get a total
    var nodechildrenlength = parentnodetoupdate.children.length;
    var nodecount = 0;
    for (var i = 0; i < nodechildrenlength; i++) {
        // stored as a string
        nodecount += parseInt(parentnodetoupdate.children[i].data.displaydata.count, 10);
    }

    // If root, we want to stop recursing, after updating the count
    if (parentnodetoupdate.isRoot()) {

        this.render();
        // update the tree's HTML after child nodes are added
        parentnodetoupdate.refresh();

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

            var newlabel = this.node_label(parentnodetoupdate.data.displaydata.name, nodecount);

            parentnodetoupdate.data.displaydata.count = nodecount;
            parentnodetoupdate.label = newlabel
        }

        this.update_parent_node(nextnodeup);
    }

};


/**
 * OnClick handler for the nodes of the tree. Attached to the root node in order to catch all events
 * via bubbling. Deals with making the marking popup appear.
 */
M.block_ajax_marking.treenodeonclick = function(oArgs) {

    // refs save space
    var node = oArgs.node;
    var mbam = window.M.block_ajax_marking;

    // we only need to do anything if the clicked node is one of
    // the final ones with no children to fetch.
    if (typeof(node.data.returndata.nextnodefilter) !== 'undefined'
            && node.data.returndata.nextnodefilter !== false) {

        return false;
    }

    // Keep track of what we clicked
    node.toggleHighlight();

    // Get window size, etc
    var popupurl = window.M.cfg.wwwroot + '/blocks/ajax_marking/actions/grading_popup.php?';
    var modulejavascript = mbam[node.data.displaydata.modulename];
    var popupargs = modulejavascript.pop_up_arguments(node);

    // New way:
    var nodefilters = M.block_ajax_marking.getnodefilters(node);
    nodefilters.push('node=' + node.index);
    popupurl += nodefilters.join('&');

    // AJAX version
    //    M.block_ajax_marking.show_modal_grading_interface(postdata);

    // Pop-up version
    mbam.popupholder = window.open(popupurl, 'ajax_marking_popup', popupargs);
    mbam.popupholder.focus();

    return false;
};

/**
 * Recursive function to get the return data from this node and all its parents. Each parent
 * represents a filter e.g. 'only this course', so we need to specify the id numbers for the SQL
 *
 * @param node object
 */
M.block_ajax_marking.getnodefilters = function(node) {

    var nodefilters = [];

    if (typeof(node.tree.supplementaryreturndata) !== 'undefined') {
        nodefilters.push(node.tree.supplementaryreturndata);
    }

    // The callback function is the SQL GROUP BY for the next set of nodes, so this is separate
    //    returndata.push('callbackfunction='+node.data.returndata.callbackfunction);

    var nextparentnode = node;

    while (!nextparentnode.isRoot()) {
        // Add the other item
        if (typeof (nextparentnode.data.returndata !== 'undefined')) {
            for (varname in nextparentnode.data.returndata) {
                // Add all the non-callbackfunction stuff e.g. courseid so we can use it to filter the
                // unmarked work
                if (varname != 'nextnodefilter' && nextparentnode.data.returndata[varname] != '') {
                    nodefilters.push(varname + '=' + nextparentnode.data.returndata[varname]);
                }
            }
            nextparentnode = nextparentnode.parent;
        }
    }
    return nodefilters;
};

/**
 * Builds the tree when the block is loaded, or refresh is clicked
 *
 * @return void
 */
M.block_ajax_marking.tree_base.prototype.initialise = function() {

    // Get rid of the existing tree nodes first (if there are any), but don't re-render to avoid
    // flicker
    var rootnode = this.getRoot();
    this.removeChildren(rootnode);
    this.render();

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

};

/**
 * Recalculates the total marking count by totalling the node counts of the tree
 *
 * @return void
 */
M.block_ajax_marking.tree_base.prototype.recalculate_total_count = function() {

    this.totalcount = 0;
    var children = this.getRoot().children;
    var childrenlength = children.length;

    for (var i = 0; i < childrenlength; i++) {
        this.totalcount += parseInt(children[i].data.displaydata.count, 10);
    }
};

/**
 * Makes it so that the total count displays the count of this tree
 */
M.block_ajax_marking.tree_base.prototype.update_total_count = function() {
    this.recalculate_total_count();
    document.getElementById('count').innerHTML = this.totalcount.toString();
};

/**
 * This is to control what node the cohorts tree asks for next when a user clicks on a node
 *
 * @param currentfilter
 * @param modulename can be false or undefined if not there
 * @return string|bool false if nothing
 */
M.block_ajax_marking.cohorts_tree.prototype.nextnodetype = function(currentfilter, modulename) {
    // if nothing else is found, make the node into a final one with no children
    var nextnodefilter = false;

    // Courses tree
    switch (currentfilter) {

        case 'cohortid':
            return 'coursemoduleid';

        case 'userid': // the submissions nodes in the course tree
            return false;

        case 'coursemoduleid':
            nextnodefilter = 'userid';
            break;

        default:
        // any special nodes that came back from a module addition
    }

    // what module do we have? Stored as currentnode.data.displaydata.modulename
    // possibly we may not have any javascript?
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
 * This is to control what node the tree asks for next when a user clicks on a node
 *
 * @param currentfilter
 * @return string|bool false if nothing
 */
M.block_ajax_marking.config_tree.prototype.nextnodetype = function(currentfilter) {
    // if nothing else is found, make the node into a final one with no children
    var nextnodefilter = false;

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
};

/**
 * Empty function - the config tree has no need to update parent counts.
 */
M.block_ajax_marking.config_tree.prototype.update_parent_node = function () {};

/**
 * This is to control what node the tree asks for next when a user clicks on a node
 *
 * @param currentfilter
 * @param modulename can be false or undefined if not there
 * @return string|bool false if nothing
 */
M.block_ajax_marking.courses_tree.prototype.nextnodetype = function(currentfilter, modulename) {
    // if nothing else is found, make the node into a final one with no children
    var nextnodefilter = false;

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
            nextnodefilter = 'userid';
            break;

        default:
        // any special nodes that came back from a module addition
    }

    // what module do we have? Stored as currentnode.data.displaydata.modulename
    // possibly we may not have any javascript?
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
 * This function updates the tree to remove the node of the pop up that has just been marked,
 * then it updates the parent nodes and refreshes the tree, then sets a timer so that the popup will
 * be closed when it goes to the 'changes saved' url.
 *
 * @param nodeuniqueid The id of the node to remove
 * @return void
 */
M.block_ajax_marking.tree_base.prototype.remove_node = function(nodeuniqueid) {
    var nodetoremove = this.getNodeByProperty('index', nodeuniqueid);
    var parentnode = nodetoremove.parent;
    this.removeNode(nodetoremove, true);
    this.update_parent_node(parentnode);
};

/**
 * Gets the groups from the course node and displays them in the contextmenu.
 *
 * Coming from an onclick in the context menu, so 'this' is the contextmenu instance
 */
M.block_ajax_marking.contextmenu_load_settings = function() {

    // Remove previous groups. If there are none, we don't want to offer the option to show or hide
    // them
    var submenus = this.getSubmenus();
    if (submenus.length > 0) {
        var choosegroupsmenu = submenus[0];
        var submenuitems = choosegroupsmenu.getItems();
        var numberofitems = submenuitems.length;
        for (var k = 0; k < numberofitems; k++) {
            // This eats array items and renumbers them as it goes, so we keep removing item 0
            choosegroupsmenu.removeItem(submenuitems[0]);
        }
    }

    var target = this.contextEventTarget;
    var coursenode = false;
    var clickednode = M.block_ajax_marking.get_current_tab().displaywidget.getNodeByElement(target);
    //clickednode.highlight(); // adds highlight so we know what we right-clicked
    if (typeof(clickednode.data.returndata.courseid) == 'undefined') {
        coursenode = clickednode.parent;
    } else {
        coursenode = clickednode;
    }

    // Make sure the setting items reflect the current settings as stored in the tree node
    M.block_ajax_marking.contextmenu_make_setting(this, 'display', clickednode);
    M.block_ajax_marking.contextmenu_make_setting(this, 'groupsdisplay', clickednode);

    // We need all of the groups, even if there are no settings
    var groups = [];
    if (typeof(coursenode.data.configdata.groups) !== 'undefined') {
        groups = coursenode.data.configdata.groups;
    }
    if (groups.length) {
        M.block_ajax_marking.contextmenu_make_groups(groups, choosegroupsmenu, clickednode);
        // Enable the menu item, since we have groups in it
        choosegroupsmenu.parent.cfg.setProperty("disabled", false);
    } else {
        // disable it so people can see that this is an option, but there are no groups
        choosegroupsmenu.parent.cfg.setProperty("disabled", true);
    }

    this.render();

    clickednode.toggleHighlight();
//    clickednode.tree.render();

};

/**
 * Make sure the item reflects the current settings as stored in the tree node.
 *
 * @param contextmenu
 * @param settingname
 * @param clickednode
 */
M.block_ajax_marking.contextmenu_make_setting = function(contextmenu, settingname, clickednode) {

    var settingindex = false;

    // What item number is this setting in the context menu?
    switch (settingname) {
        case 'display':
            settingindex = 0;
            break;
        case 'groupsdisplay':
            settingindex = 1;
            break;
        default:
            M.block_ajax_marking.show_error('No settingindex for '+settingname);
    }

    var menuitem = contextmenu.getItem(settingindex);
    var checked = false;
    var currentsetting = M.block_ajax_marking.get_node_setting(clickednode,
                                                               settingname);
    if (currentsetting !== null) {
        checked = currentsetting ? true : false;
        if (M.block_ajax_marking.showinheritance) {
            menuitem.cfg.setProperty("classname", 'notinherited');
        }
    } else {
        var defaultsetting = M.block_ajax_marking.get_node_setting_default(clickednode,
                                                                           settingname);
        checked = defaultsetting ? true : false;
        if (M.block_ajax_marking.showinheritance) {

            menuitem.cfg.setProperty("classname", 'inherited');
        }
    }
    menuitem.cfg.setProperty('checked', checked);
};

/**
 * Turns the raw groups data from the tree node into menu items and attaches them to the menu
 *
 * @param {Array} rawgroups
 * @param menu
 * @param clickednode
 * @return void
 */
M.block_ajax_marking.contextmenu_make_groups = function(rawgroups, menu, clickednode) {

    var newgroup, groupdefault;
    var numberofgroups = rawgroups.length;

    for (var i = 0; i < numberofgroups; i++) {
        newgroup = { "text"    : rawgroups[i].name,
                     "value"   : { "groupid" : rawgroups[i].id},
                     onclick   : { fn: M.block_ajax_marking.contextmenu_setting_onclick,
                                   obj: {'settingtype' : 'group'} } };
        // Make sure the items' appearance reflect their current settings
        if (rawgroups[i].display === "1") { // JSON seems to like sending back ints as strings
            // Make sure it is checked
            newgroup.checked = true;

        } else if (rawgroups[i].display === "0") {
            newgroup.checked = false;

        } else if (rawgroups[i].display === null) {
            // We want to show that this node inherits it's setting for this group
            // newgroup.classname = 'inherited';
            // Now we need to get the right default for it and show it as checked or not
            groupdefault = M.block_ajax_marking.get_node_setting_default(clickednode,
                                                                         'group',
                                                                         rawgroups[i].id);
            newgroup.checked = groupdefault ? true : false;
            if (M.block_ajax_marking.showinheritance) {
               newgroup.classname = 'inherited';
            }
        }
        menu.addItem(newgroup);
    }
};

/**
 * Gets the current setting for the clicked node
 *
 * @param {object} clickednode
 * @param {string} settingtype
 * @param {int} groupid
 */
M.block_ajax_marking.get_node_setting = function(clickednode, settingtype, groupid) {

    var setting;

    switch (settingtype) {

        case 'display':

        case 'groupsdisplay':

            setting = clickednode.data.configdata[settingtype];
            break;

        case 'group':
            var groups = clickednode.data.configdata.groups;
            var group = M.block_ajax_marking.get_group_by_id(groups, groupid);
            setting = group.display;
            break;

        default:
            M.block_ajax_marking.error('Invalid setting type: '+settingtype);

    }

    // Moodle sends the settings as strings, but we want ints so we can do proper comparisons
    if (setting !== null) {
        setting = parseInt(setting);
    }

    return setting;

};

/**
 * Ajax success function called when the server responds with valid data, which checks the data
 * coming in and calls the right function depending on the type of data it is
 *
 * @param o the ajax response object, passed automatically
 * @return void
 */
M.block_ajax_marking.ajax_success_handler = function(o) {

    var errormessage = '';
    var ajaxresponsearray = '';

    try {
        ajaxresponsearray = YAHOO.lang.JSON.parse(o.responseText);
    } catch (error) {
        // add an empty array of nodes so we trigger all the update and cleanup stuff
        errormessage = '<strong>An error occurred:</strong><br />';
        errormessage += o.responseText;
        M.block_ajax_marking.error(errormessage);
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
            M.block_ajax_marking.get_current_tab().displaywidget.build_nodes(ajaxresponsearray.nodes);

        } else if (typeof(ajaxresponsearray['configsave']) !== 'undefined') {

            if (ajaxresponsearray['configsave'].success !== true) {
                M.block_ajax_marking.show_error('Config setting failed to save');
                // TODO handle error gracefully
                // TODO deal with exceptions
            } else {
                // We want to toggle the display of the menu item by setting it to the new value.
                // Don't assume that the default hasn't changed.
                M.block_ajax_marking.contextmenu_ajax_callback(ajaxresponsearray);
            }
        }
    }

    M.block_ajax_marking.get_current_tab().displaywidget.rebuild_tree_after_ajax();
    YAHOO.util.Dom.removeClass(document.getElementById('mainicon'), 'loaderimage');
};

/**
 * Sorts out what needs to happen once a response is received from the server that a setting
 * has been saved for an individual group
 */
M.block_ajax_marking.contextmenu_ajax_callback = function(ajaxresponsearray) {

    var settingtype = ajaxresponsearray['configsave'].settingtype;
    var menuitemindex = ajaxresponsearray['configsave'].menuitemindex;
    var menu = null;

    if (settingtype === 'group') {
        var submenus = M.block_ajax_marking.contextmenu.getSubmenus();
        menu = submenus[0];
    } else {
        menu =  M.block_ajax_marking.contextmenu;
    }

    var clickeditem = menu.getItem(menuitemindex, 0);
    var target = M.block_ajax_marking.contextmenu.contextEventTarget;
    var configtab = M.block_ajax_marking.get_current_tab();
    var clickednode = configtab.displaywidget.getNodeByElement(target);

    var groupid = null;
    if (settingtype === 'group') {
        groupid = clickeditem.value.groupid;
    }

    // Update the menu item so the user can see the change
    var newsetting = ajaxresponsearray['configsave'].newsetting;
    if (newsetting === null) {
        // get default

        var defaultsetting = M.block_ajax_marking.get_node_setting_default(clickednode,
                                                                           settingtype,
                                                                           groupid);
        //set default
        var checked = defaultsetting ? true : false;
        clickeditem.cfg.setProperty("checked", checked);
        // set inherited class
        if (M.block_ajax_marking.showinheritance) {
            clickeditem.cfg.setProperty("classname", 'inherited');
        }
    } else if (newsetting == 1) {
        clickeditem.cfg.setProperty("checked", true);
        if (M.block_ajax_marking.showinheritance) {
            clickeditem.cfg.setProperty("classname", 'notinherited');
        }
    } else if (newsetting == 0) {
        clickeditem.cfg.setProperty("checked", false);
        if (M.block_ajax_marking.showinheritance) {
            clickeditem.cfg.setProperty("classname", 'notinherited');
        }
    }

    // Update the menuitem display value so that if it is clicked again, it will know not to the
    // same ajax request and will toggle properly
    clickeditem.value.display = newsetting;
    // We also need to update the data held in the tree node, so that future requests are not
    // all the same as this one
    if (settingtype === 'group') {
        var groups = clickednode.data.configdata.groups;
        var group = M.block_ajax_marking.get_group_by_id(groups, groupid);
        group.display = newsetting;
    } else {
        clickednode.data.configdata[settingtype] = newsetting;
    }
};

/**
 * Shows an error message in the div below the tree.
 * @param errormessage
 */
M.block_ajax_marking.show_error = function(errormessage) {

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
M.block_ajax_marking.ajax_failure_handler = function(o) {

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
M.block_ajax_marking.ajax_timeout_handler = function() {
    document.getElementById('status').innerHTML = M.str.block_ajax_marking.connecttimeout;
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
    var refreshbutton = new YAHOO.widget.Button({
        label     : M.str.block_ajax_marking.refresh,
        id        : 'block_ajax_marking_collapse',
        onclick   : {fn: function() {
            document.getElementById('status').innerHTML = '';
            YAHOO.util.Dom.setStyle('block_ajax_marking_error', 'display', 'none');
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

    cache    : false,
    success  : M.block_ajax_marking.ajax_success_handler,
    failure  : M.block_ajax_marking.ajax_failure_handler,
    abort    : M.block_ajax_marking.ajax_timeout_handler,
    scope    : M.block_ajax_marking,
    // TODO: find out what this was for as the timeouts seem not to be working
    // argument : 1200,
    timeout  : 10000

};

/**
 * Returns the currently selected node from the tabview widget
 *
 * @return object
 */
M.block_ajax_marking.get_current_tab = function() {
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
M.block_ajax_marking.remove_node_from_current_tab = function(nodeid) {

    var currenttab = M.block_ajax_marking.tabview.get('selection');
    currenttab.displaywidget.remove_node(nodeid);
};

/**
 * The initialising stuff to get everything started
 *
 * @return void
 */
M.block_ajax_marking.initialise = function() {

    YUI().use('tabview', function(Y) {
        M.block_ajax_marking.tabview = new Y.TabView({ // this waits till much too late.
            srcNode: '#treetabs'
        });

        // Must render first so treeviews have container divs ready
        M.block_ajax_marking.tabview.render();

        // Define the tabs here and add them dynamically.
        var coursestab = new Y.Tab({
            'label':'Courses',
            'content':'<div id="coursestree" class="ygtv-highlight"></div>'});
        M.block_ajax_marking.tabview.add(coursestab);
        coursestab.displaywidget = new M.block_ajax_marking.courses_tree();
        coursestab.displaywidget.subscribe('clickEvent', M.block_ajax_marking.treenodeonclick);

        var cohortstab = new Y.Tab({
            'label':'Cohorts',
            'content':'<div id="cohortstree" class="ygtv-highlight"></div>'});
        M.block_ajax_marking.tabview.add(cohortstab);
        cohortstab.displaywidget = new M.block_ajax_marking.cohorts_tree();
        cohortstab.displaywidget.subscribe('clickEvent', M.block_ajax_marking.treenodeonclick);

        var configtab = new Y.Tab({
            'label':'Config',
            'content':'<div id="configtree" class="ygtv-highlight"></div>'});
        M.block_ajax_marking.tabview.add(configtab);
        configtab.displaywidget = new M.block_ajax_marking.config_tree();


        // Make the context menu for the config tree
        // Attach a listener to the root div which will activate the menu
        // menu needs to be repositioned next to the clicked node
        // menu

        // Menu needs to be made of
        // - show/hide toggle
        // - show/hide group nodes
        // - submenu to show/hide specific groups

    	M.block_ajax_marking.contextmenu = new YAHOO.widget.ContextMenu(
            "configcontextmenu",
            {
                trigger: "configtree",
                keepopen: true,
                lazyload: false,
                itemdata: [
                    {   text: M.str.block_ajax_marking.show,
                        onclick: { fn: M.block_ajax_marking.contextmenu_setting_onclick,
                                   obj: {'settingtype' : 'display'} },
                        checked: true,
                        id: 'block_ajax_marking_context_show',
                        value : {}},
                    {   text: M.str.block_ajax_marking.showgroups,
                        onclick: { fn: M.block_ajax_marking.contextmenu_setting_onclick,
                                   obj: {'settingtype' : 'groupsdisplay'} },
                        checked: false,
                        id: 'block_ajax_marking_context_showgroups',
                        value : {}},
                    {   text: M.str.block_ajax_marking.choosegroups,
                        submenu: {
                            id : 'choosegroupssubmenu',
                            keepopen: true,
                            lazyload: true,
                            itemdata: {}
                        }
                    }
                ]
            }
        );
        // Initial render makes sure we have something to add and takeaway items from
        M.block_ajax_marking.contextmenu.render(document.body);

        // Make sure the menu is updated to be current with the node it matches
        M.block_ajax_marking.contextmenu.subscribe("triggerContextMenu",
                                                   M.block_ajax_marking.contextmenu_load_settings);
        M.block_ajax_marking.contextmenu.subscribe("beforeHide",
                                                   M.block_ajax_marking.contextmenu_unhighlight);


        // Set event that makes a new tree if it's needed when the tabs change
        M.block_ajax_marking.tabview.after('selectionChange', function() {

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
    YUI().use('node', function(Y) {
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
 * adjusting the settings for
 */
M.block_ajax_marking.contextmenu_unhighlight = function() {

    var target = this.contextEventTarget;
    var clickednode = M.block_ajax_marking.get_current_tab().displaywidget.getNodeByElement(target);

    clickednode.unhighlight();
};


/**
 * OnClick handler for the show context menu item. It will ajax a request to change the node's
 * config and make the context menu item checked if successful
 */
//M.block_ajax_marking.context_setting_onclick = function(event, otherthing, obj) {
//
//    var settingtype = obj.settingtype;
//
//    var coursenodeclicked = false;
//    var target = this.parent.contextEventTarget;
//    var clickednode = M.block_ajax_marking.get_current_tab().displaywidget.getNodeByElement(target);
//    if (typeof(clickednode.data.returndata.courseid) !== 'undefined') {
//        coursenodeclicked = true;
//    }
//
//    // What is the current setting
//    var currentsetting = clickednode.data.configdata[settingtype]
//
//    // What is the default setting
//    var defaultsetting = M.block_ajax_marking.get_node_setting_default(clickednode, settingtype);
//
//    // Whatever it is, the user will probably want to toggle it, seeing as they have clicked it.
//    // This means we want to assume that it needs to be the opposite of the default if there is
//    // no current setting.
//    if (currentsetting === null) {
//        settingtorequest = defaultsetting ? 0 : 1;
//    } else {
//        // There is an existing setting. The user toggled from the default last time, so
//        // will want to toggle back to default. No point deliberately making a setting when we can
//        // just use the default, leaving more flexibility if the defaults are changed (rare)
//        settingtorequest = null;
//    }
//
//    // gather data
//    var requestdata = {};
//    requestdata.menuitemindex = this.index;
//    requestdata.settingtype = settingtype;
//    if (settingtorequest !== null) { // leaving this out defaults to null on the other end
//        requestdata.settingvalue = settingtorequest;
//    }
//    requestdata.tablename = coursenodeclicked ? 'course' : 'course_modules';
//    requestdata.instanceid = coursenodeclicked ?
//                             clickednode.data.returndata.courseid :
//                             clickednode.data.returndata.coursemoduleid;
//
//    // send request
//    M.block_ajax_marking.contextmenu_ajax_request(requestdata);
//
//    // ..d success handler should check/uncheck the menu item and undisable it
//    // also disable other menu items if this is set to not show
//
//};


M.block_ajax_marking.contextmenu_setting_onclick = function(event, otherthing, obj) {

    // we want to set the class so that we can indicate whether the checked (show) status is
    // directly set, or whether it is inherited
    var settingtype = obj.settingtype;

    var coursenodeclicked = false;
    var target = M.block_ajax_marking.contextmenu.contextEventTarget;
    var clickednode = M.block_ajax_marking.get_current_tab().displaywidget.getNodeByElement(target);
    if (typeof(clickednode.data.returndata.courseid) !== 'undefined') {
        coursenodeclicked = true;
    }

    // What do we have as the current setting?
    var groupid = null;
    if (settingtype === 'group') {
        groupid = this.value.groupid;
    }
    var currentsetting = M.block_ajax_marking.get_node_setting(clickednode, settingtype, groupid);
    var defaultsetting = M.block_ajax_marking.get_node_setting_default(clickednode,
                                                                       settingtype, groupid);
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

    // gather data
    var requestdata = {};
    requestdata.groupid = groupid;
    requestdata.menuitemindex = this.index;
    requestdata.settingtype = settingtype;
    if (settingtorequest !== null) { // leaving out defaults to null on the other end
        requestdata.settingvalue = settingtorequest;
    }
    requestdata.tablename = coursenodeclicked ? 'course' : 'course_modules';
    requestdata.instanceid = coursenodeclicked ?
                             clickednode.data.returndata.courseid :
                             clickednode.data.returndata.coursemoduleid;

    // send request
    M.block_ajax_marking.contextmenu_ajax_request(requestdata);

};

/**
 * Given an array of groups and an id, this will loop over them till it gets the right one and
 * return it.
 *
 * @param {Array} groups
 * @param {int} groupid
 * @return array|bool
 */
M.block_ajax_marking.get_group_by_id = function(groups, groupid) {

    var numberofgroups = groups.length;
    for (var i = 0; i < numberofgroups; i++) {
        if (groups[i].id == groupid) {
            return groups[i];
        }
    }
    M.block_ajax_marking.error('No group found for groupid '+groupid);
    return false;
};

/**
 * Finds out what the default is for this group node, if it has no display setting
 * @param {object} node
 */
M.block_ajax_marking.get_node_setting_default = function(node, settingtype, groupid) {

    var defaultsetting = null;

    /**
     * @var {Array} node.data.returndata
     */
    if (typeof(node.data.returndata.courseid) === 'undefined') { // Must be a coursemodule
        switch (settingtype) {

            case 'group':
                var groups = node.parent.data.configdata.groups;
                var group = M.block_ajax_marking.get_group_by_id(groups, groupid);
                defaultsetting = group.display;
                break;

            case 'display':
                defaultsetting = node.parent.data.configdata.display;
                break;

            case 'groupsdisplay':
                defaultsetting = node.parent.data.configdata.groupsdisplay;
                break;
        }
    }
    if (defaultsetting !== null) {
        return parseInt(defaultsetting);
    }

    // This is the root default until there's a better way of setting it
    switch (settingtype) {

        case 'group':

        case 'display':
            return 1;
            break;

        case 'groupsdisplay':
            return 0; // Cleaner if we hide the group nodes by default
            break;
    }

};

/**
 * Asks the server to change the setting for something
 */
M.block_ajax_marking.contextmenu_ajax_request = function(requestdata) {

    // Turn our pbject into a string that the AJAX stuff likes.
    var poststring = '';
    var temparray = [];
    for (var key in requestdata) {
        temparray.push(key + '=' + requestdata[key]);
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

   YAHOO.util.Event.addListener(window, 'unload', function(args) {

       // Get tree
       var tree = window.opener.M.block_ajax_marking.currenttab().displaywidget;

       // get node
       var node = tree.getNodeById(args.nodeid);

       // unhighlight node
       node.unhighlight();

   }, {'nodeid':node});

};

