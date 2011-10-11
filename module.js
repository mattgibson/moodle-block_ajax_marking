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
 * This file conatins all the javascript for the AJAX Marking block. The script tag at the top is
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

// used to deterine whether to log everything to console
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
 * Base class that can be used for the main and config trees. This extends the
 * YUI treeview class ready to add some new functions to it which are common to both the
 * main and config trees.
 */
M.block_ajax_marking.tree_base = function(treediv) {
    M.block_ajax_marking.tree_base.superclass.constructor.call(this, treediv);
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
 */
M.block_ajax_marking.cohorts_tree = function() {
    this.initial_nodes_data = 'nextnodefilter=cohortid';
    M.block_ajax_marking.tree_base.superclass.constructor.call(this, 'cohortstree');
};

// make the groups tree into a subclass of the base class
YAHOO.lang.extend(M.block_ajax_marking.cohorts_tree, M.block_ajax_marking.tree_base);

/**
 * Constructor for the groups tree
 */
M.block_ajax_marking.users_tree = function() {
    this.initial_nodes_data = 'nextnodefilter=userid';
    M.block_ajax_marking.tree_base.superclass.constructor.call(this, 'usersstree');
};

// make the groups tree into a subclass of the base class
YAHOO.lang.extend(M.block_ajax_marking.users_tree, M.block_ajax_marking.tree_base);

/**
 * New unified build nodes function
 *
 * @param array nodesarray
 */
M.block_ajax_marking.tree_base.prototype.build_nodes = function(nodesarray) {

    var newnode = '';
    var nodedata = '';
    var seconds = 0;
    var currentfilter = '';
    var modulename = '';
    // TODO what if server time and browser time are mismatche?
    var currenttime = Math.round((new Date()).getTime() / 1000); // current unix time
    var iconstyle = '';
    var numberofnodes = nodesarray.length;

    // we need to attach nodes to the root node if this is the initial build after a refresh
    var holdertype = typeof(M.block_ajax_marking.parentnodeholder);
    if (holdertype !== 'object') {
        M.block_ajax_marking.parentnodeholder = this.getRoot();
    }

    // cycle through the array and make the nodes
    for (var m = 0; m < numberofnodes; m++) {

        nodedata = nodesarray[m];

        // Make the display data accessible to the node creation code
        nodedata.label = nodedata.display.name;
        nodedata.title = nodedata.display.tooltip;

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
        modulename = (typeof(nodedata.display.modulename) !== 'undefined') ?
                nodedata.display.modulename : false;
        nodedata.returndata.nextnodefilter = this.nextnodetype(currentfilter, modulename);

        // Add a count if we have more than one thing or we're not at the final node
        // e.g. student name
        if (nodedata.display.count > 1 || nodedata.returndata.nextnodefilter !== false) {
            nodedata.label = '(<span class="AMB_count">' + nodedata.display.count + '</span>) ' +
                             nodedata.label;
        }

        newnode = new YAHOO.widget.TextNode(nodedata, M.block_ajax_marking.parentnodeholder, false);

        // set the node to load data dynamically, unless it has not sent a callback i.e. it's a
        // final node

        if (typeof(nodedata.returndata.nextnodefilter) !== 'undefined' &&
                nodedata.returndata.nextnodefilter !== false) {

            newnode.setDynamicLoad(this.request_node_data);
        }

        // We assume that the modules have added any css they want to add in styles.php
        newnode.labelStyle = 'block_ajax_marking_node_' + nodedata.display.style;

        // If the node has a time (of oldest submission) show urgency by adding a background colour
        if (typeof(nodedata.display) !== 'undefined' &&
                typeof(nodedata.display.time) !== 'undefined') {

            iconstyle = '';

            seconds = currenttime - parseInt(nodedata.display.time, 10);

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

    this.render();
    // finally, run the function that updates the original node and adds the children. Won't be
    // there if we have just built the tree
    if (typeof(M.block_ajax_marking.oncompletefunctionholder) === 'function') {
        // Take care - this will be executed in the wrong scope if not careful. it needs this to
        // be the tree
        M.block_ajax_marking.oncompletefunctionholder();
    }

    this.subscribe('clickEvent', M.block_ajax_marking.treenodeonclick);
    //this.subscribe('clickEvent', M.block_ajax_marking.show_modal_grading_interface);
    //}

    // the main tree will need the counts updated, but not the config tree
    // TODO test this
    //if (typeof(M.block_ajax_marking.parentnodeholder) === 'object' &&
    //    typeof(M.block_ajax_marking.parentnodeholder.count) === 'integer') {
    this.update_parent_node(M.block_ajax_marking.parentnodeholder);

}

/**
 * This function is called when a node is clicked (expanded) and makes the ajax request. It sends
 * thefilters from all parent nodes and the nextnodetype
 *
 * @param obj clickednode
 * @param string callbackfunction
 */
M.block_ajax_marking.tree_base.prototype.request_node_data = function(clickednode,
                                                                      callbackfunction) {

    // store details of the node that has been clicked for reference by later
    // callback function
    M.block_ajax_marking.parentnodeholder = clickednode;
    M.block_ajax_marking.oncompletefunctionholder = callbackfunction;

    var postdata = [];

    // The callback function is the SQL GROUP BY for the next set of nodes, so this is separate
    var nodefilters = M.block_ajax_marking.getnodefilters(clickednode);
    nodefilters.push('nextnodefilter=' + clickednode.data.returndata.nextnodefilter);
    nodefilters = nodefilters.join('&');

    YAHOO.util.Connect.asyncRequest('POST', M.block_ajax_marking.ajaxnodesurl,
                                    block_ajax_marking_callback, nodefilters);
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
        nodecount += parseInt(parentnodetoupdate.children[i].data.display.count, 10);
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
            var newlabel = '(<span class="AMB_count">' + nodecount + '</span>) ' +
                           parentnodetoupdate.data.display.name;
            parentnodetoupdate.data.display.count = nodecount;
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

    //    var nextnodetpye = node.tree.nextnodetype(node);

    // we only need to do anything if the clicked node is one of
    // the final ones with no children to fetch.
    if (typeof(node.data.returndata.nextnodefilter) !== 'undefined'
            && node.data.returndata.nextnodefilter !== false) {

        return false;
    }

    // Get window size, etc
    var popupurl = window.M.cfg.wwwroot + '/blocks/ajax_marking/actions/grading_popup.php?';
    var modulejavascript = mbam[node.data.display.modulename];
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
 * Rcursive function to get the return data from this node and all its parents. Each parent
 * represents a filter e.g. 'only this course', so we need to specify the id numbers for the SQL
 *
 * @param object node
 */
M.block_ajax_marking.getnodefilters = function(node) {

    var nodefilters = [];

    // The callback function is the SQL GROUP BY for the next set of nodes, so this is separate
    //    returndata.push('callbackfunction='+node.data.returndata.callbackfunction);

    var nextparentnode = node;

    while (!nextparentnode.isRoot()) {
        // Add the other item
        for (varname in nextparentnode.data.returndata) {
            // Add all the non-callbackfunction stuff e.g. courseid so we can use it to filter the
            // unmarked work
            if (varname != 'nextnodefilter' && nextparentnode.data.returndata[varname] != '') {
                nodefilters.push(varname + '=' + nextparentnode.data.returndata[varname]);
            }
        }
        nextparentnode = nextparentnode.parent;
    }
    return nodefilters;
}

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

    // Reload the data for the root node. We keep the tree object intect rather than destroying
    // and recreating in order to improve responsiveness.
    M.block_ajax_marking.parentnodeholder = rootnode;
    // If we don't do this, then after refresh, we get it trying to run the oncomplete thing from
    // the last node that was expanded.
    M.block_ajax_marking.oncompletefunctionholder = null;

    // show that the ajax request has been initialised
    YAHOO.util.Dom.addClass(document.getElementById('mainicon'), 'loaderimage');

    // send the ajax request
    YAHOO.util.Connect.asyncRequest('POST', M.block_ajax_marking.ajaxnodesurl,
                                    block_ajax_marking_callback, this.initial_nodes_data);

};

/**
 * function to recalculate the total marking count by totalling the node counts of the tree
 *
 * @return void
 */
M.block_ajax_marking.tree_base.prototype.recalculate_total_count = function() {

    this.totalcount = 0;
    var children = this.getRoot().children;
    var childrenlength = children.length;

    for (var i = 0; i < childrenlength; i++) {
        this.totalcount += parseInt(children[i].data.display.count, 10);
    }
};

/**
 * Makes it so that the total count displays the count of this tree
 */
M.block_ajax_marking.tree_base.prototype.update_total_count = function() {
    document.getElementById('count').innerHTML = this.totalcount.toString();
}

/**
 * This is to control what node the tree asks for next when a user clicks on a node
 *
 * @param string currentfilter
 * @param string modulename can be false or undefined if not there
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
        // fall through because we need to offer the option to alter things after coursemoduleid.

        default:
        // any special nodes that came back from a module addition
    }

    // what module do we have? Stored as currentnode.data.display.modulename
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

}

/**
 * This is to control what node the tree asks for next when a user clicks on a node
 *
 * @param string currentfilter
 * @param string modulename can be false or undefined if not there
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
        // fall through because we need to offer the option to alter things after coursemoduleid.

        default:
        // any special nodes that came back from a module addition
    }

    // what module do we have? Stored as currentnode.data.display.modulename
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

}

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
 * Ajax success function called when the server responds with valid data, which checks the data
 * coming in and calls the right function depending on the type of data it is
 *
 * @param o the ajax response object, passed automatically
 * @return void
 */
M.block_ajax_marking.ajax_success_handler = function(o) {

    var mbam = M.block_ajax_marking;
    var ajaxresponsearray = '';

    try {
        var ajaxresponsearray = YAHOO.lang.JSON.parse(o.responseText);
    } catch (error) {
        // add an empty array of nodes so we trigger all the update and cleanup stuff
        // TODO - error handling code to prevent silent failure if data is mashed
    }

    // first object holds data about what kind of nodes we have so we can
    // fire the right function.
    //var payload = 'default';
    if (typeof(ajaxresponsearray) !== 'object') {
        // TODO error handling here
    } else {
        if (typeof(ajaxresponsearray['gradinginterface']) !== 'undefined') {
            // We have gotten the grading form back. Need to add the HTML to the modal overlay
            M.block_ajax_marking.gradinginterface.setHeader('');
            M.block_ajax_marking.gradinginterface.setBody(ajaxresponsearray.content);
        } else if (typeof(ajaxresponsearray['nodes']) !== 'undefined') {
            M.block_ajax_marking.get_current_tab().displaywidget.build_nodes(ajaxresponsearray.nodes);
        }
    }

    YAHOO.util.Dom.removeClass(document.getElementById('mainicon'), 'loaderimage');
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
        document.getElementById('status').innerHTML = M.str.block_ajax_marking.collapseString;
    }

    // communication failure
    if (o.tId === 0) {
        document.getElementById('status').innerHTML = M.str.block_ajax_marking.connectfail;
        //YAHOO.util.Dom.removeClass(M.block_ajax_marking.markingtree.icon, 'loaderimage');

        if (!document.getElementById('block_ajax_marking_collapse')) {
            M.block_ajax_marking.make_footer();
        }
    }

    YAHOO.util.Dom.removeClass(this.icon, 'loaderimage');
};

M.block_ajax_marking.ajax_timeout_handler = function(o) {
    // Do something sensible

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

            M.block_ajax_marking.get_current_tab().displaywidget.initialise();
        }}, // TODO refresh all trees
        container : 'block_ajax_marking_refresh_button'});
};

/**
 * Callback object for the AJAX call, which fires the correct function. Doesn't work when part
 * of the main class. Don't know why - something to do with scope.
 *
 * @return void
 */
var block_ajax_marking_callback = {

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
 * @param int nodeid
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
            'content':'<div id="coursestree"></div>'});
        M.block_ajax_marking.tabview.add(coursestab);
        coursestab.displaywidget = new M.block_ajax_marking.courses_tree();

        var cohortstab = new Y.Tab({
            'label':'Cohorts',
            'content':'<div id="cohortstree"></div>'});
        M.block_ajax_marking.tabview.add(cohortstab);
        cohortstab.displaywidget = new M.block_ajax_marking.cohorts_tree();

        // Set event that makes a new tree if it's needed when the tabs change
        M.block_ajax_marking.tabview.after('selectionChange', function(e) {

            // get current tab and keep a reference to it
            var newtabindex = e.newVal.get('index');
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
}

//// Get the IDE to do proper script highlighting
//if (0) {
//    ?><!--</script>--><?php
//}
//
//
//// We need to append all of the plugin specific javascript. This file will be requested as part of a
//// separate http request after the PHP has all been finished with, so we do this cheaply to keep
//// overheads low by not using setup.php and having the js in static functions.
//
//if (!defined('MOODLE_INTERNAL')) { // necessary because class files are expecting it
//    define('MOODLE_INTERNAL', true);
//}
//
//$moduledir = opendir(dirname(__FILE__) . '/modules');
//
//if ($moduledir) {
//
//    // We never instantiate the classes, but it complains if it can't find the base class
//    //require_once(dirname(__FILE__).'/classes/module_base.class.php');
//
//    // Loop through the module files, including each one, then echoing the extra javascript from it
//    while (($moddir = readdir($moduledir)) !== false) {
//
//        // Ignore any that don't fit the pattern, like . and ..
//        if (preg_match('/^([a-z]*)$/', $moddir, $matches)) {
//            require_once(dirname(__FILE__) . '/modules/' . $moddir . '/' . $moddir . '.js');
//
//        }
//    }
//    closedir($moduledir);
//}