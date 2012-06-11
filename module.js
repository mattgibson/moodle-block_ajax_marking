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
 * Javascript for the AJAX Marking block
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2007 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Emulates the trim function if it's not there.
if (typeof String.prototype.trim !== 'function') {
    String.prototype.trim = function () {
        return this.replace(/^\s+|\s+$/g, '');
    }
}

// Modules that add their own javascript will have already defined this, but here just in case.

if (typeof(M.block_ajax_marking) === 'undefined') {
    M.block_ajax_marking = {};
}

/**
 * This holds the parent node so it can be referenced by other functions.
 */
M.block_ajax_marking.parentnodeholder = '';

/**
 * This holds the callback function of the parent node so it can be called once all the child
 * nodes have been built.
 */
M.block_ajax_marking.oncompletefunctionholder = '';

/**
 * This is the variable used by the openPopup function on the front page.
 */
M.block_ajax_marking.popupholder = '';

/**
 * URL for getting the nodes details.
 * @type {String}
 */
M.block_ajax_marking.ajaxnodesurl = M.cfg.wwwroot + '/blocks/ajax_marking/actions/ajax_nodes.php';

/**
 * URL for getting the count for a specific node.
 * @type {String}
 */
M.block_ajax_marking.ajaxcounturl = M.cfg.wwwroot + '/blocks/ajax_marking/actions/ajax_node_count.php';

/**
 * URL for getting the nodes details.
 * @type {String}
 */
M.block_ajax_marking.childnodecountsurl = M.cfg.wwwroot+'/blocks/ajax_marking/actions/ajax_child_node_counts.php';

/**
 * Change to true to see what settings are null (inherited) by having them marked in grey on the
 * context menu.
 */
M.block_ajax_marking.showinheritance = false;

/**
 * Should add all the groups from a config node to it's menu button.
 *
 */
M.block_ajax_marking.groups_menu_button_render = function() {

    // Get node.

    // Get groups from node.

    // Add groups to this menu button.
    this.addItems([

          { text : "Four", value : 4 },
          { text : "Five", value : 5 }

      ]);
};





/**
 * OnClick handler for the nodes of the tree. Attached to the root node in order to catch all events
 * via bubbling. Deals with making the marking popup appear.
 *
 * @param {object} oArgs from the YUI event
 */
M.block_ajax_marking.treenodeonclick = function (oArgs) {

    /**
     * @var M.block_ajax_marking.markingtreenode
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

    var nodefilters = node.get_filters(true);
    nodefilters.push('node='+node.index);
    // Add any extra stuff e.g. assignments always need mode=single to make optional_param() stuff
    // work internally in assignment classes.
    var popupstuff = node.get_popup_stuff();
    nodefilters = nodefilters.concat(popupstuff);
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
 * @param {YAHOO.widget.ContextMenu} menu A pre-existing context menu
 * @param {M.block_ajax_marking.tree_node} clickednode
 * @return void
 */
M.block_ajax_marking.contextmenu_add_groups_to_menu = function (menu, clickednode) {

    var newgroup,
        groups,
        groupdefault,
        numberofgroups,
        iscontextmenu = menu instanceof YAHOO.widget.ContextMenu;

    groups = clickednode.get_groups();
    numberofgroups = groups.length;

    for (var i = 0; i < numberofgroups; i++) {

        newgroup = {
            "text" : groups[i].name,
            "value" : { "groupid" : groups[i].id},
            "onclick" : { fn : M.block_ajax_marking.contextmenu_setting_onclick,
                          obj : {'settingtype' : 'group'} } };

        // Make sure the items' appearance reflect their current settings
        // JSON seems to like sending back integers as strings

        if (groups[i].display === "1") {
            // Make sure it is checked
            newgroup.checked = true;

        } else if (groups[i].display === "0") {
            newgroup.checked = false;

        } else if (groups[i].display === null) {
            // We want to show that this node inherits it's setting for this group
            // newgroup.classname = 'inherited';
            // Now we need to get the right default for it and show it as checked or not
            var groupdefault = clickednode.get_default_setting('group', groups[i].id);
            newgroup.checked = groupdefault ? true : false;
            if (M.block_ajax_marking.showinheritance) {
                newgroup.classname = 'inherited';
            }
        }

        // Add to group 1 so we can keep it separate from group 0 with the basic settings so that
        // the contextmenu will have these all grouped to gether with a title
        var groupindex = iscontextmenu ? 1 :0;
        menu.addItem(newgroup, groupindex);
    }

    // If there are no groups, we want to show this rather than have the context menu fail to
    // pop up at all, leaving the normal one to appear in it's place
    if (numberofgroups === 0) {
        // TODO probably don't need this now - never used?
        menu.addItem({"text" : M.str.block_ajax_marking.nogroups,
                      "value" : 0 });
    } else if (iscontextmenu) {
        menu.setItemGroupTitle(M.str.block_ajax_marking.choosegroups+':', 1);
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
        M.block_ajax_marking.show_error(errormessage, false);
    }

    // first object holds data about what kind of nodes we have so we can
    // fire the right function.
    if (typeof(ajaxresponsearray) === 'object') {

        // If we are doing something with a specific node, this will be there
        var index = false;
        if (ajaxresponsearray.nodeindex) {
            index = ajaxresponsearray.nodeindex;
        }

        // If we have a neatly structured Moodle error, we want to display it
        if (ajaxresponsearray.hasOwnProperty('error')) {

            errormessage = '';

            // Special case for 'not logged in' message
            if (ajaxresponsearray.hasOwnProperty('debuginfo') &&
                ajaxresponsearray.debuginfo == 'sessiontimedout') {

                M.block_ajax_marking.show_error(ajaxresponsearray.error, true);

            } else {
                // Developer message.
                errormessage += '<strong>A Moodle error occurred:</strong><br />';
                errormessage += ajaxresponsearray.error;
                if (ajaxresponsearray.hasOwnProperty('debuginfo')) {
                    errormessage += '<br /><strong>Debug info:</strong><br />';
                    errormessage += ajaxresponsearray.debuginfo;
                }
                if (ajaxresponsearray.hasOwnProperty('stacktrace')) {
                    errormessage += '<br /><strong>Stacktrace:</strong><br />';
                    errormessage += ajaxresponsearray.stacktrace;
                }
                M.block_ajax_marking.show_error(errormessage, false);
                // The tree will fail to expand its nodes after refresh unless we tell it
                // that this operation to expand a node worked.
                currenttab.displaywidget.locked = false;
            }

        } else if (typeof(ajaxresponsearray['gradinginterface']) !== 'undefined') {
            // We have gotten the grading form back. Need to add the HTML to the modal overlay
            // M.block_ajax_marking.gradinginterface.setHeader('');
            // M.block_ajax_marking.gradinginterface.setBody(ajaxresponsearray.content);

        } else if (typeof(ajaxresponsearray['counts']) !== 'undefined') {
            currenttab.displaywidget.update_node_count(ajaxresponsearray.counts, index);
        } else if (typeof(ajaxresponsearray['childnodecounts']) !== 'undefined') {
            currenttab.displaywidget.update_child_node_counts(ajaxresponsearray.childnodecounts,
                                                              index);
        } else if (typeof(ajaxresponsearray['nodes']) !== 'undefined') {
            currenttab.displaywidget.build_nodes(ajaxresponsearray.nodes, index);
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

                // Notify other trees to refresh now that settings have changed
                M.block_ajax_marking.get_current_tab().displaywidget.notify_refresh_needed_after_config();
            }
        }
    }

    // TODO this needs to get the right tab from the request details in case we switch tabs quickly
    M.block_ajax_marking.get_current_tab().displaywidget.remove_loading_icon();

};

M.block_ajax_marking.update_menu_item = function(newsetting,
                                                 defaultsetting,
                                                 clickeditem) {

    // Update the menu item so the user can see the change
    if (newsetting === null) {
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
};

/**
 * Sorts out what needs to happen once a response is received from the server that a setting
 * has been saved for an individual group
 *
 * @param {object} ajaxresponsearray
 */
M.block_ajax_marking.contextmenu_ajax_callback = function (ajaxresponsearray) {

    var data = ajaxresponsearray.configsave;
    var currenttab = M.block_ajax_marking.get_current_tab(),
        settingtype = data.settingtype,
        newsetting = data.newsetting,
        menutype = data.menutype,
        clickednodeindex = data.nodeindex,
        menuitemindex = data.menuitemindex,
        menugroupindex = data.menugroupindex,
        clickedmenuitem,
        clickednode,
        groupid = null;

    clickednode = currenttab.displaywidget.getNodeByProperty('index', clickednodeindex);

    if (menutype == 'buttonmenu') {
        clickedmenuitem = clickednode.renderedmenu.getItem(menuitemindex, menugroupindex);
    } else if (menutype == 'contextmenu') {
        clickedmenuitem = currenttab.contextmenu.getItem(menuitemindex, menugroupindex);
    }

    if (menutype == 'contextmenu' && clickednode.get_current_filter_name() === 'groupid') {
        // we deal with groups by dealing with the parent node. There's only one operation (hide),
        // so as long as we hide the context menu too, it's fine.
        clickednode = clickednode.parent;
    }

    if (settingtype === 'group') {
        groupid = data.groupid;
    }

    var defaultsetting = clickednode.get_default_setting(settingtype, groupid);
    M.block_ajax_marking.update_menu_item(newsetting, defaultsetting, clickedmenuitem);
    // Update the menu item display value so that if it is clicked again, it will know
    // not to send the same ajax request and will toggle properly
    clickedmenuitem.value.display = newsetting;

    // We also need to update the data held in the tree node, so that future requests are not
    // all the same as this one. The tree will take care of presentation changes.
    if (settingtype === 'group') {
        clickednode.set_group_setting(groupid, newsetting);
    } else {
        clickednode.set_config_setting(settingtype, newsetting);
    }
};

/**
 * Shows an error message in the div below the tree.
 * @param errormessage
 * @param notloggedin ignores the fact that a user is not an admin. Used to show 'not logged in'
 */
M.block_ajax_marking.show_error = function (errormessage, notloggedin) {

    if (typeof(M.cfg.developerdebug) === 'undefined' && !notloggedin) {
        errormessage = M.str.block_ajax_marking.errorcontactadmin;
    }

    if (typeof(errormessage) === 'string') {
        document.getElementById('block_ajax_marking_error').innerHTML = errormessage;
    }
    YAHOO.util.Dom.setStyle('block_ajax_marking_error', 'display', 'block');
    if (notloggedin) {
        // Login message never needs scrollbars and they mess it up
        YAHOO.util.Dom.setStyle('block_ajax_marking_error', 'overflow-x', 'auto');
    }
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
        // TODO set cleaner error
        document.getElementById('status').innerHTML = M.str.block_ajax_marking.connectfail;
        //YAHOO.util.Dom.removeClass(M.block_ajax_marking.markingtree.icon, 'loaderimage');

    }
    var tree = M.block_ajax_marking.get_current_tab().displaywidget;
    tree.rebuild_parent_and_tree_count_after_new_nodes();
    YAHOO.util.Dom.removeClass(this.icon, 'loaderimage');
};

/**
 * If the AJAX connection times out, this will handle things so we know what happened
 */
M.block_ajax_marking.ajax_timeout_handler = function () {
    M.block_ajax_marking.show_error(M.str.block_ajax_marking.connecttimeout, false);
    M.block_ajax_marking.get_current_tab().displaywidget.rebuild_parent_and_tree_count_after_new_nodes();
    YAHOO.util.Dom.removeClass(this.icon, 'loaderimage');
};

/**
 * Used by other functions to clear all child nodes from some element. Also clears all children
 * from a tree node.
 *
 * @param parentnode a dom reference
 * @return void
 */
M.block_ajax_marking.remove_all_child_nodes = function (parentnode) {

    if (typeof(parentnode.hasChildNodes) === 'function' && parentnode.hasChildNodes()) {
        while (parentnode.childNodes.length >= 1) {
            parentnode.removeChild(parentnode.firstChild);
        }
    }
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
 * @param node either a node object, or a nodeid
 * @return void
 */
M.block_ajax_marking.remove_node_from_current_tab = function (node) {

    var currenttree = M.block_ajax_marking.get_current_tab().displaywidget;

    if (typeof(node) === 'number') {
        node = currenttree.getNodeByIndex(node);
    }

    currenttree.remove_node(node.index);
    currenttree.notify_refresh_needed_after_marking();
};

/**
 * Fetches an icon from the hidden div where the theme has rendered them (taking all the theme and
 * module overrides into account), then returns it, optionally changing the alt text on the way
 */
M.block_ajax_marking.get_dynamic_icon = function(iconname, alttext) {

    var icon = YAHOO.util.Dom.get('block_ajax_marking_'+iconname+'_icon'),
        newicon;

    if (!icon) {
        return false;
    }

    newicon = icon.cloneNode(true); // avoid altring the original one

    if (newicon && typeof(alttext) !== 'undefined') {
        newicon.alt = alttext;
    }

    // avoid collisions
    try {
        delete newicon.id;
    }
    catch (e) {
        // keep IE9 happy
        newicon["id"] = null;
    }

    return newicon;
};

/**
 * Returns a HTML string representation of a dynamic icon
 */
M.block_ajax_marking.get_dynamic_icon_string = function (icon) {

    var html = '';

    if (icon) {
        // hacky way to get a string representation of an icon.
        // Without cloneNode(), it takes the node away from the original hidden div
        // so it only works once
        var tmp = document.createElement("div");
        tmp.appendChild(icon);
        html += tmp.innerHTML;
    }

    return html;
};

/**
 * When the page is loaded, we need to make CSS styles dynamically using the icon urls that
 * the theme has provided for us. This isn't possible in normal CSS because the Theme has various
 * defaults and overrides so the icon path is not the same for all modules, users, themes etc.
 */
M.block_ajax_marking.make_icon_styles = function() {

    var images,
        imagediv,
        style,
        styletext,
        iconname,
        iconidarray;

    // Get all the image urls from the hidden div that holds them
    imagediv = document.getElementById('dynamicicons');
    images = YAHOO.util.Dom.getElementsByClassName('dynamicicon', 'img', imagediv);

    // Loop over them making CSS styles with those images as the background
    for (var i = 0; i < images.length; i++) {

        // e.g. block_ajax_marking_assignment_icon
        var image = images[i];
        iconidarray = image.id.split('_');
        iconname = iconidarray[3];

        style = document.createElement("style");
        style.type = "text/css";

        styletext = '.block_ajax_marking td.ygtvcell.'+iconname+' {'+
                            'background-image: url('+image.src+'); '+
                            'padding-left: 20px; '+
                            'background-repeat: no-repeat; '+
                            'background-position: 0 2px;}';
        if (style.styleSheet) {
            style.styleSheet.cssText = styletext;
        } else {
            style.appendChild(document.createTextNode(styletext));
        }
        document.getElementsByTagName("head")[0].appendChild(style);
    }

};

/**
 * The initialising stuff to get everything started
 *
 * @return void
 */
M.block_ajax_marking.initialise = function () {

    M.block_ajax_marking.make_icon_styles();

    YUI().use('tabview', 'node', function (Y) {
        // this waits till much too late. Can we trigger earlier?
        M.block_ajax_marking.tabview = new Y.TabView({
                                                         srcNode : '#treetabs'
                                                     });

        // Must render first so treeviews have container divs ready
        M.block_ajax_marking.tabview.render();

        // Courses tab
        var coursetabconfig = {
            label : 'Courses',
            id : 'coursestab',

            'content' : '<div id="coursessheader" class="treetabheader">'+
                                    '<div id="coursesrefresh" class="refreshbutton"></div>'+
                                    '<div id="coursesstatus" class="statusdiv">'+
                                        M.str.block_ajax_marking.totaltomark+
                                        ' <span id="coursescount" class="countspan"></span>'+
                                    '</div>'+
                                    '<div class="block_ajax_marking_spacer"></div>'+
                                 '</div>'+
                                 '<div id="coursestree" class="ygtv-highlight markingtree"></div>'};
        var coursestab = new Y.Tab(coursetabconfig);
        M.block_ajax_marking.tabview.add(coursestab);

        coursestab.displaywidget = new M.block_ajax_marking.coursestree('coursestree');
        // reference so we can tell the tree to auto-refresh
        M.block_ajax_marking.coursestab_tree = coursestab.displaywidget;
        coursestab.displaywidget.render();
        coursestab.displaywidget.subscribe('clickEvent', M.block_ajax_marking.treenodeonclick);
        coursestab.displaywidget.tab = coursestab; // reference to allow links back to tab from tree
        coursestab.displaywidget.countdiv = document.getElementById('coursescount'); // reference to allow links back to tab from tree

        coursestab.refreshbutton = new YAHOO.widget.Button({
            label : '<img src="'+M.cfg.wwwroot+'/blocks/ajax_marking/pix/refresh-arrow.png" class="refreshicon"' +
                        ' alt="'+M.str.block_ajax_marking.refresh+'" />',
            id : 'coursesrefresh_button',
            title : M.str.block_ajax_marking.refresh,
            onclick : {fn : function () {
                YAHOO.util.Dom.setStyle('block_ajax_marking_error',
                                        'display',
                                        'none');
                coursestab.displaywidget.initialise();
            }},
            container : 'coursesrefresh'});

        // Cohorts tab
        var cohortstabconfig = {
            label : 'Cohorts',
            id : 'cohortstab',
            content : '<div id="cohortsheader" class="treetabheader">'+
                            '<div id="cohortsrefresh" class="refreshbutton"></div>'+
                            '<div id="cohortsstatus" class="statusdiv">'+
                                M.str.block_ajax_marking.totaltomark+
                                ' <span id="cohortscount" class="countspan"></span>'+
                            '</div>'+
                            '<div class="block_ajax_marking_spacer"></div>'+
                        '</div>'+
                        '<div id="cohortstree" class="ygtv-highlight markingtree"></div>'};
        var cohortstab = new Y.Tab(cohortstabconfig);
        M.block_ajax_marking.tabview.add(cohortstab);
        cohortstab.displaywidget = new M.block_ajax_marking.cohortstree('cohortstree');
        M.block_ajax_marking.cohortstab_tree = cohortstab.displaywidget;
        // Reference to allow links back to tab from tree.
        cohortstab.displaywidget.tab = cohortstab;
        cohortstab.displaywidget.render();
        cohortstab.displaywidget.subscribe('clickEvent', M.block_ajax_marking.treenodeonclick);

        // reference to allow links back to tab from tree
        cohortstab.displaywidget.countdiv = document.getElementById('cohortscount');

        cohortstab.refreshbutton = new YAHOO.widget.Button({
           label : '<img src="'+M.cfg.wwwroot+
                    '/blocks/ajax_marking/pix/refresh-arrow.png" class="refreshicon" ' +
                        'alt="'+M.str.block_ajax_marking.refresh+'" />',
           id : 'cohortsrefresh_button',
           title : M.str.block_ajax_marking.refresh,
           onclick : {fn : function () {
               YAHOO.util.Dom.setStyle('block_ajax_marking_error',
                                       'display',
                                       'none');
               cohortstab.displaywidget.initialise();
           }},
           container : 'cohortsrefresh'});

        // Config tab
        var configtabconfig = {
            label : '<img src="'+M.cfg.wwwroot+'/blocks/ajax_marking/pix/cog.png" alt="cogs" id="configtabicon" />',
             id : 'configtab',
            content : '<div id="configheader" class="treetabheader">'+
                            '<div id="configrefresh" class="refreshbutton"></div>'+
                            '<div id="configstatus" class="statusdiv"></div>'+
                            '<div class="block_ajax_marking_spacer"></div>'+
                        '</div>'+
                        '<div id="configtree" class="ygtv-highlight markingtree"></div>'};
        var configtab = new Y.Tab(configtabconfig);
        M.block_ajax_marking.tabview.add(configtab);
        configtab.displaywidget = new M.block_ajax_marking.configtree('configtree');
        M.block_ajax_marking.configtab_tree = configtab.displaywidget;
        configtab.displaywidget.tab = configtab; // reference to allow links back to tab from tree

        configtab.displaywidget.render();
//        configtab.displaywidget.subscribe('clickEvent',
//                                          M.block_ajax_marking.config_treenodeonclick);
        // We want the dropdown for the current node to hide when an expand action happens (if it's
        // open)
        configtab.displaywidget.subscribe('expand', M.block_ajax_marking.hide_open_menu);

        configtab.refreshbutton = new YAHOO.widget.Button({
               label : '<img src="'+M.cfg.wwwroot+'/blocks/ajax_marking/pix/refresh-arrow.png" ' +
                             'class="refreshicon" alt="'+M.str.block_ajax_marking.refresh+'" />',
               id : 'configrefresh_button',
               onclick : {fn : function () {
                   YAHOO.util.Dom.setStyle('block_ajax_marking_error',
                                           'display',
                                           'none');
                   configtab.displaywidget.initialise();
               }},
               container : 'configrefresh'});

        // Make the context menu for the courses tree
        // Attach a listener to the root div which will activate the menu
        // menu needs to be repositioned next to the clicked node
        // menu
        //
        // Menu needs to be made of
        // - show/hide toggle
        // - show/hide group nodes
        // - submenu to show/hide specific groups
        coursestab.contextmenu = new M.block_ajax_marking.contextmenu(
            "maincontextmenu",
            {
                trigger : "coursestree",
                keepopen : true,
                lazyload : false,
                zindex: 1001
            }
        );
        // Initial render makes sure we have something to add and takeaway items from
        coursestab.contextmenu.render(document.body);
        // Make sure the menu is updated to be current with the node it matches
        coursestab.contextmenu.subscribe("triggerContextMenu",
                                         coursestab.contextmenu.load_settings);
        coursestab.contextmenu.subscribe("beforeHide", function() {
                                          coursestab.contextmenu.clickednode.unhighlight();
                                          coursestab.contextmenu.clickednode = null;});

        // Set event that makes a new tree if it's needed when the tabs change
        M.block_ajax_marking.tabview.after('selectionChange', function () {

            // get current tab and keep a reference to it
            var currenttab = M.block_ajax_marking.get_current_tab();

            // If settings have changed on another tab, we must refresh so that things reflect
            // the new settings.
            if (currenttab.displaywidget.needs_refresh()) {
                currenttab.displaywidget.initialise();
                currenttab.displaywidget.set_needs_refresh(false);
            }

            if (typeof(currenttab.alreadyinitialised) === 'undefined') {
                currenttab.displaywidget.initialise();
                currenttab.alreadyinitialised = true;
            } else {
                currenttab.displaywidget.update_total_count();
            }
        });

        // TODO use cookies/session to store the one the user wants between sessions
        M.block_ajax_marking.tabview.selectChild(0); // this will initialise the courses tree

        // Unhide that tabs block - preventing flicker

        Y.one('#treetabs').setStyle('display', 'block');
       // Y.one('#totalmessage').setStyle('display', 'block');

    });

    // workaround for odd https setups. Probably not needed in most cases, but you can get an error
    // without it if using non-https ajax on a https page
    if (document.location.toString().indexOf('https://') != -1) {
        M.cfg.wwwroot = M.cfg.wwwroot.replace('http:', 'https:');
    }

};

/**
 * OnClick handler for the contextmenu that sends an ajax request for the setting to be changed on
 * the server.
 *
 * @param event e.g. 'click'
 * @param otherthing
 * @param obj
 */
M.block_ajax_marking.contextmenu_setting_onclick = function (event, otherthing, obj) {

    var clickednode,
        settingtorequest = 1,
        groupid = null,
        tree = M.block_ajax_marking.get_current_tab().displaywidget;

    var settingtype = obj.settingtype;

    if (typeof(this.parent.contextEventTarget) !== 'undefined') {
        // main context menu does not work for menu button
        clickednode = tree.getNodeByElement(this.parent.contextEventTarget);
    }
    if (!clickednode && typeof(this.parent.element) !== 'undefined') {
        // config tree menu button
        clickednode = tree.getNodeByElement(this.parent.element.parentElement);
    }
    if (!clickednode && typeof(this.parent.parent) !== 'undefined') {
        // groups submenu
        clickednode = tree.getNodeByElement(this.parent.parent.parent.contextEventTarget);
    }
    // by this point, we should have a node.
    if (!clickednode) {
        return;
    }

    var currentfiltername = clickednode.get_current_filter_name();

    // For a group, we are really dealing with the parent node
    if (currentfiltername === 'groupid') {
        groupid = clickednode.get_current_filter_value();
        clickednode = clickednode.parent;
        settingtype = 'group';
    }

    // What do we have as the current setting?
    if (settingtype === 'group' && groupid === null) {
        // Whatever it is, the user will probably want to toggle it, seeing as they have clicked it.
        // This means we want to assume that it needs to be the opposite of the default if there is
        // no current setting.
        groupid = this.value.groupid;
    }
    // Work out what we should be requesting based on parent node, inheritance, etc.
    var currentsetting = clickednode.get_config_setting(settingtype, groupid);
    var defaultsetting = clickednode.get_default_setting(settingtype, groupid);
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
    requestdata.menugroupindex = this.groupIndex;
    requestdata.nodeindex = clickednode.index;
    requestdata.settingtype = settingtype;
    if (settingtorequest !== null) { // leaving out defaults to null on the other end
        requestdata.settingvalue = settingtorequest;
    }
    requestdata.tablename = (currentfiltername === 'courseid') ? 'course' : 'course_modules';
    var iscontextmenu = this.parent instanceof YAHOO.widget.ContextMenu;
    requestdata.menutype = iscontextmenu ? 'contextmenu' : 'buttonmenu';
    requestdata.instanceid = clickednode.get_current_filter_value();

    // send request
    M.block_ajax_marking.save_setting_ajax_request(requestdata, clickednode);
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
M.block_ajax_marking.save_setting_ajax_request = function (requestdata, clickednode) {

    M.block_ajax_marking.oncompletefunctionholder = function (justrefreshchildren) {
        // Sometimes the node will have been removed
        if (clickednode.tree) {
            clickednode.refresh(justrefreshchildren)
        }
    };

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
 * Hides the drop-down menu that may be open on this node
 */
M.block_ajax_marking.hide_open_menu = function(expandednode) {
    expandednode.renderedmenu.hide();
};
