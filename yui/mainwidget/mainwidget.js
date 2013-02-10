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
        "use strict";
        return this.replace(/^\s+|\s+$/g, '');
    };
}

// Modules that add their own javascript will have already defined this, but here just in case.

YUI().add('moodle-block_ajax_marking-mainwidget', function(Y) {
    "use strict";

    var widgetNAME = 'mainwidget';
    var MAINWIDGET = function () {
        MAINWIDGET.superclass.constructor.apply(this, arguments);
    };

    var self = this;

    /**
     * These are all the functions that used to be in module.js.
     *
     * @class M.block_ajax_marking.mainwidget
     */
    Y.extend(MAINWIDGET, Y.Base, {

        /**
         * Stored reference to the Yahoo global object. Added via initialise().
         */
        Y : null,

        /**
         * This holds the parent node so it can be referenced by other functions.
         */
        parentnodeholder : '',

        /**
         * This holds the callback function of the parent node so it can be called once all the child
         * nodes have been built.
         */
        oncompletefunctionholder : '',

        /**
         * This is the variable used by the openPopup function on the front page.
         */
        popupholder : '',

        /**
         * URL for getting the count for a specific node.
         * @type {String}
         */
        ajaxcounturl : M.cfg.wwwroot + '/blocks/ajax_marking/actions/ajax_node_count.php',

        /**
         * URL for getting the nodes details.
         * @type {String}
         */
        childnodecountsurl : M.cfg.wwwroot + '/blocks/ajax_marking/actions/ajax_child_node_counts.php',

        /**
         * Change to true to see what settings are null (inherited) by having them marked in grey on the
         * context menu.
         */
        showinheritance : false,

        /**
         * Ajax success function called when the server responds with valid data, which checks the data
         * coming in and calls the right function depending on the type of data it is
         *
         * @param id
         * @param {Object} o the ajax response object, passed automatically
         * @param args
         * @return void
         */
        ajax_success_handler : function (id, o, args) {

            var errormessage,
                ajaxresponsearray,
                currenttab = this.get_current_tab(),
                index;

            try {
                ajaxresponsearray = Y.JSON.parse(o.responseText);
            } catch (error) {
                // add an empty array of nodes so we trigger all the update and cleanup stuff
                errormessage = '<strong>An error occurred:</strong><br />';
                errormessage += o.responseText;
                this.show_error(errormessage, false);
            }

            // first object holds data about what kind of nodes we have so we can
            // fire the right function.
            if (typeof ajaxresponsearray === 'object') {

                // If we are doing something with a specific node, this will be there
                index = false;
                if (ajaxresponsearray.nodeindex) {
                    index = ajaxresponsearray.nodeindex;
                }

                // If we have a neatly structured Moodle error, we want to display it
                if (ajaxresponsearray.hasOwnProperty('error')) {

                    errormessage = '';

                    // Special case for 'not logged in' message
                    if (ajaxresponsearray.hasOwnProperty('debuginfo') &&
                            ajaxresponsearray.debuginfo === 'sessiontimedout') {

                        this.show_error(ajaxresponsearray.error, true);

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
                        this.show_error(errormessage, false);
                        // The tree will fail to expand its nodes after refresh unless we tell it
                        // that this operation to expand a node worked.
                        currenttab.displaywidget.locked = false;
                    }

                } else if (typeof ajaxresponsearray.counts !== 'undefined') {
                    currenttab.displaywidget.update_node_count(ajaxresponsearray.counts, index);
                } else if (typeof ajaxresponsearray.childnodecounts !== 'undefined') {
                    currenttab.displaywidget.update_child_node_counts(ajaxresponsearray.childnodecounts,
                                                                      index);
                } else if (typeof ajaxresponsearray.nodes !== 'undefined') {
                    currenttab.displaywidget.build_nodes(ajaxresponsearray.nodes, index);
                } else if (typeof ajaxresponsearray.configsave !== 'undefined') {

                    if (ajaxresponsearray.configsave.success === true) {
                        // Maybe it's a contextmenu settings change, maybe it's an icon click.
                        if (ajaxresponsearray.configsave.menuitemindex === false) { // assume a nodeid
                            this.config_icon_success_handler(ajaxresponsearray);
                        } else {
                            // We want to toggle the display of the menu item by setting it to
                            // the new value. Don't assume that the default hasn't changed.
                            this.contextmenu_ajax_callback(ajaxresponsearray);
                        }

                        // Notify other trees to refresh now that settings have changed
                        this.get_current_tab().displaywidget.notify_refresh_needed_after_config();
                    } else {
                        this.show_error('Config setting failed to save', false);
                    }
                }
            }

            // TODO this needs to get the right tab from the request details in case we switch tabs quickly.
            this.get_current_tab().displaywidget.remove_loading_icon();

        },

        /**
         * Once something in a menu has been clicked, we need to change it to reflect it's new setting.
         *
         * @param newsetting
         * @param defaultsetting
         * @param clickeditem
         */
        update_menu_item : function (newsetting, defaultsetting, clickeditem) {

            // Update the menu item so the user can see the change
            if (newsetting === null) {
                //set default
                var checked = defaultsetting ? true : false;
                clickeditem.cfg.setProperty("checked", checked);
                // set inherited class
                if (this.showinheritance) {
                    clickeditem.cfg.setProperty("classname", 'inherited');
                }
            } else if (newsetting === 1) {
                clickeditem.cfg.setProperty("checked", true);
                if (this.showinheritance) {
                    clickeditem.cfg.setProperty("classname", 'notinherited');
                }
            } else if (newsetting === 0) {
                clickeditem.cfg.setProperty("checked", false);
                if (this.showinheritance) {
                    clickeditem.cfg.setProperty("classname", 'notinherited');
                }
            }
        },

        /**
         * Sorts out what needs to happen once a response is received from the server that a setting
         * has been saved for an individual group
         *
         * @param {Object} ajaxresponsearray
         */
        contextmenu_ajax_callback : function (ajaxresponsearray) {

            var data = ajaxresponsearray.configsave,
                currenttab = this.get_current_tab(),
                settingtype = data.settingtype,
                newsetting = data.newsetting,
                menutype = data.menutype,
                clickednodeindex = data.nodeindex,
                menuitemindex = data.menuitemindex,
                menugroupindex = data.menugroupindex,
                clickedmenuitem,
                clickednode,
                defaultsetting,
                groupid = null;

            clickednode = currenttab.displaywidget.getNodeByProperty('index', clickednodeindex);

            if (menutype === 'buttonmenu') {
                clickedmenuitem = clickednode.renderedmenu.getItem(menuitemindex, menugroupindex);
            } else if (menutype === 'contextmenu') {
                clickedmenuitem = currenttab.contextmenu.getItem(menuitemindex, menugroupindex);
            }

            if (menutype === 'contextmenu' && clickednode.get_current_filter_name() === 'groupid') {
                // we deal with groups by dealing with the parent node. There's only one operation (hide),
                // so as long as we hide the context menu too, it's fine.
                clickednode = clickednode.parent;
            }

            if (settingtype === 'group') {
                groupid = data.groupid;
            }

            defaultsetting = clickednode.get_default_setting(settingtype, groupid);
            this.update_menu_item(newsetting, defaultsetting, clickedmenuitem);
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
        },

        /**
         * Shows an error message in the div below the tree.
         * @param {string} errormessage
         * @param {Boolean} notloggedin ignores the fact that a user is not an admin. Used to show 'not logged in'
         */
        show_error : function (errormessage, notloggedin) {
            var errordiv = Y.one('#block_ajax_marking_error');

            if (typeof M.cfg.developerdebug === 'undefined' && !notloggedin) {
                errormessage = M.str.block_ajax_marking.errorcontactadmin;
            }

            if (typeof errormessage === 'string') {
                errordiv.innerHTML = errormessage;
            }
            errordiv.setStyle('display', 'block');
            if (notloggedin) {
                // Login message never needs scroll bars and they mess it up
                errordiv.setStyle('overflow-x', 'auto');
            }
        },

        /**
         * function which fires if the AJAX call fails
         * TODO: why does this not fire when the connection times out?
         *
         * @param {Object} o the ajax response object, passed automatically
         * @return void
         */
        ajax_failure_handler : function (id, o, args) {

            // Transaction aborted.
            if (o.tId === -1) {
                document.getElementById('status').innerHTML = M.str.block_ajax_marking.connectfail;

            }

            // Communication failure.
            if (o.tId === 0) {
                document.getElementById('status').innerHTML = M.str.block_ajax_marking.connectfail;
            }
            var tree = this.get_current_tab().displaywidget;
            tree.rebuild_parent_and_tree_count_after_new_nodes();
            this.icon.removeClass('loaderimage');
        },

        /**
         * If the AJAX connection times out, this will handle things so we know what happened
         */
        ajax_timeout_handler : function (id, o, args) {

            this.show_error(M.str.block_ajax_marking.connecttimeout, false);
            this.get_current_tab().displaywidget.rebuild_parent_and_tree_count_after_new_nodes();
            this.icon.removeClass('loaderimage');
        },


        /**
         * Callback object for the AJAX call, which fires the correct function. Doesn't work when part
         * of the main class. Don't know why - something to do with scope.
         */
        callback : {

            cache : false,
            success :this.ajax_success_handler,
            failure :this.ajax_failure_handler,
            abort :this.ajax_timeout_handler,
            scope :this,
            // TODO: timeouts seem not to be working all the time
            timeout : 20000
        },

        /**
         * Handles the process of updating the node's settings and altering the display of the clicked icon.
         *
         * @param {Object} ajaxresponsearray
         */
        config_icon_success_handler : function (ajaxresponsearray) {

            var settingtype = ajaxresponsearray.configsave.settingtype,
                nodeindex = ajaxresponsearray.configsave.nodeindex,
                newsetting = ajaxresponsearray.configsave.newsetting,
                groupid = ajaxresponsearray.configsave.groupid,
                configtab = this.get_current_tab(),
                clickednode = configtab.displaywidget.getNodeByIndex(nodeindex);

            // Update the node's icon to reflect the new status.
            if (settingtype === 'group') {
                clickednode.set_group_setting(groupid, newsetting);
            } else {
                clickednode.set_config_setting(settingtype, newsetting);
            }
        },

        /**
         * Returns the currently selected node from the tabview widget.
         *
         * @return object
         */
        get_current_tab : function () {
            return this.tabview.get('selection');
        },

        /**
         * We don't know what tab is current, but once the grading popup shuts, we need to remove the node
         * with the id it specifies. This decouples the popup from the tabs. Might want to extend this
         * to make sure that it sticks to the right tree too, in case someone fiddles with the tabs
         * whilst having the popup open.
         *
         * This may show up as an unused function as it is called from a string that's included from PHP.
         *
         * @param node either a node object, or a nodeid
         * @return void
         */
        remove_node_from_current_tab : function (node) {

            var currenttree = this.get_current_tab().displaywidget;

            if (typeof node === 'number') {
                node = currenttree.getNodeByIndex(node);
            }

            currenttree.remove_node(node.index);
            currenttree.notify_refresh_needed_after_marking();
        },

        /**
         * When the page is loaded, we need to make CSS styles dynamically using the icon urls that
         * the theme has provided for us. This isn't possible in normal CSS because the Theme has various
         * defaults and overrides so the icon path is not the same for all modules, users, themes etc.
         */
        make_icon_styles : function () {

            var images,
                imagediv,
                style,
                styletext,
                iconname,
                iconidarray,
                image,
                i;

            // Get all the image urls from the hidden div that holds them
            imagediv = Y.one('#dynamicicons');
            images = imagediv.all('.dynamicicon', 'img');

            // Loop over them making CSS styles with those images as the background
            for (i = 0; i < images.length; i++) {

                // e.g. block_ajax_marking_assignment_icon
                image = images[i];
                iconidarray = image.id.split('_');
                iconname = iconidarray[3];

                style = document.createElement("style");
                style.type = "text/css";

                styletext = '.block_ajax_marking td.ygtvcell.' + iconname + ' {' +
                                    'background-image: url(' + image.src + '); ' +
                                    'padding-left: 20px; ' +
                                    'background-repeat: no-repeat; ' +
                                    'background-position: 0 2px;}';
                if (style.styleSheet) {
                    style.styleSheet.cssText = styletext;
                } else {
                    style.appendChild(document.createTextNode(styletext));
                }
                document.getElementsByTagName("head")[0].appendChild(style);
            }

        },

        /**
         * Config tab.
         * @param Y
         */
        add_config_tab:function (Y) {
            var configtabconfig = {
                label:'<img src="' + M.cfg.wwwroot + '/blocks/ajax_marking/pix/cog.png" alt="cogs" id="configtabicon" />',
                id:'configtab',
                content:'<div id="configheader" class="treetabheader">' +
                    '<div id="configrefresh" class="refreshbutton"></div>' +
                    '<div id="configstatus" class="statusdiv"></div>' +
                    '<div class="block_ajax_marking_spacer"></div>' +
                    '</div>' +
                    '<div id="configtree" class="ygtv-highlight markingtree"></div>'
            };
            var configtab = new Y.Tab(configtabconfig);
            this.tabview.add(configtab);
            configtab.displaywidget = new M.block_ajax_marking.configtree(this, 'configtree');
            this.configtab_tree = configtab.displaywidget;
            configtab.displaywidget.tab = configtab; // reference to allow links back to tab from tree

            configtab.displaywidget.render();
            // We want the dropdown for the current node to hide when an expand action happens (if it's
            // open)
            configtab.displaywidget.subscribe('expand', this.hide_open_menu);

            configtab.refreshbutton = new Y.YUI2.widget.Button({
                label:'<img src="' + M.cfg.wwwroot + '/blocks/ajax_marking/pix/refresh-arrow.png" ' +
                    'class="refreshicon" alt="' + M.str.block_ajax_marking.refresh + '" />',
                id:'configrefresh_button',
                onclick:{fn:function () {
                    Y.one('#block_ajax_marking_error').setStyle('display', 'none');
                    configtab.displaywidget.initialise();
                }},
                container:'configrefresh'
            });
        },

        /**
         * Cohorts tab.
         *
         * @param Y
         */
        add_cohorts_tab:function (Y) {
            var cohortstabconfig = {
                label:'Cohorts',
                id:'cohortstab',
                content:'<div id="cohortsheader" class="treetabheader">' +
                    '<div id="cohortsrefresh" class="refreshbutton"></div>' +
                    '<div id="cohortsstatus" class="statusdiv">' +
                    M.str.block_ajax_marking.totaltomark +
                    ' <span id="cohortscount" class="countspan"></span>' +
                    '</div>' +
                    '<div class="block_ajax_marking_spacer"></div>' +
                    '</div>' +
                    '<div id="cohortstree" class="ygtv-highlight markingtree"></div>'
            };
            var cohortstab = new Y.Tab(cohortstabconfig);
            this.tabview.add(cohortstab);
            cohortstab.displaywidget = new M.block_ajax_marking.cohortstree(this, 'cohortstree');
            this.cohortstab_tree = cohortstab.displaywidget;
            // Reference to allow links back to tab from tree.
            cohortstab.displaywidget.tab = cohortstab;
            cohortstab.displaywidget.render();

            // reference to allow links back to tab from tree
            cohortstab.displaywidget.countdiv = document.getElementById('cohortscount');

            cohortstab.refreshbutton = new Y.YUI2.widget.Button({
                label:'<img src="' + M.cfg.wwwroot +
                    '/blocks/ajax_marking/pix/refresh-arrow.png" class="refreshicon" ' +
                    'alt="' + M.str.block_ajax_marking.refresh + '" />',
                id:'cohortsrefresh_button',
                title:M.str.block_ajax_marking.refresh,
                onclick:{fn:function () {
                    Y.one('#block_ajax_marking_error').setStyle('display', 'none');
                    cohortstab.displaywidget.initialise();
                }},
                container:'cohortsrefresh'
            });
        },

        /**
         *
         * @param Y
         */
        add_courses_tab:function (Y) {
            var coursetabconfig = {
                label:'Courses',
                id:'coursestab',

                'content':'<div id="coursessheader" class="treetabheader">' +
                    '<div id="coursesrefresh" class="refreshbutton"></div>' +
                    '<div id="coursesstatus" class="statusdiv">' +
                    M.str.block_ajax_marking.totaltomark +
                    ' <span id="coursescount" class="countspan"></span>' +
                    '</div>' +
                    '<div class="block_ajax_marking_spacer"></div>' +
                    '</div>' +
                    '<div id="coursestree" class="ygtv-highlight markingtree"></div>'
            };
            var coursestab = new Y.Tab(coursetabconfig);

            this.tabview.add(coursestab);

            coursestab.displaywidget = new M.block_ajax_marking.coursestree(this, 'coursestree');
            // reference so we can tell the tree to auto-refresh
            this.coursestab_tree = coursestab.displaywidget;
            coursestab.displaywidget.render();
            coursestab.displaywidget.tab = coursestab; // reference to allow links back to tab from tree
            coursestab.displaywidget.countdiv = document.getElementById('coursescount'); // reference to allow links back to tab from tree

            coursestab.refreshbutton = new Y.YUI2.widget.Button({
                label:'<img src="' + M.cfg.wwwroot + '/blocks/ajax_marking/pix/refresh-arrow.png" class="refreshicon"' +
                    ' alt="' + M.str.block_ajax_marking.refresh + '" />',
                id:'coursesrefresh_button',
                title:M.str.block_ajax_marking.refresh,
                onclick:{fn:function () {
                    Y.one('#block_ajax_marking_error').setStyle('display', 'none');
                    coursestab.displaywidget.initialise();
                }},
                container:'coursesrefresh'
            });

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
                    trigger:"coursestree",
                    keepopen:true,
                    lazyload:false,
                    zindex:1001
                }
            );
            // Initial render makes sure we have something to add and takeaway items from
            coursestab.contextmenu.render(document.body);
            // Make sure the menu is updated to be current with the node it matches
            coursestab.contextmenu.subscribe("triggerContextMenu",
                coursestab.contextmenu.load_settings);
            coursestab.contextmenu.subscribe("beforeHide", function () {
                coursestab.contextmenu.clickednode.unhighlight();
                coursestab.contextmenu.clickednode = null;
            });

            return coursestab;
        }, /**
         * The initialising stuff to get everything started
         *
         * @return void
         */
        initialise : function (Y, block_id) {

            var coursestab,
                coursetabconfig,
                cohortstabconfig,
                cohortstab,
                configtabconfig,
                configtab,
                tabs_div_selector = '#treetabs';

            this.make_icon_styles();

            // this waits till much too late. Can we trigger earlier?
            this.tabview = new Y.TabView({
                srcNode : tabs_div_selector
            });

            // Must render first so treeviews have container divs ready
            this.tabview.render();


            // Add all the tabs.
            this.add_courses_tab(Y);
            this.add_cohorts_tab(Y);
            this.add_config_tab(Y);

            // Set event that makes a new tree if it's needed when the tabs change.
            this.tabview.after('selectionChange', function () {

                // get current tab and keep a reference to it
                var currenttab = this.get('selection'); // this = the tabview instance.

                // If settings have changed on another tab, we must refresh so that things reflect
                // the new settings.
                if (currenttab.displaywidget.needs_refresh()) {
                    currenttab.displaywidget.initialise();
                    currenttab.displaywidget.set_needs_refresh(false);
                }

                if (typeof currenttab.alreadyinitialised === 'undefined') {
                    currenttab.displaywidget.initialise();
                    currenttab.alreadyinitialised = true;
                } else {
                    currenttab.displaywidget.update_total_count();
                }
            });

            // TODO use cookies/session to store the one the user wants between sessions.
            this.tabview.selectChild(0); // this will initialise the courses tree

            // Unhide that tabs block - preventing flicker

            Y.one(tabs_div_selector).setStyle('display', 'block');
            // Y.one('#totalmessage').setStyle('display', 'block');

            // workaround for odd https setups. Probably not needed in most cases, but you can get an error
            // without it if using non-https ajax on a https page
            if (document.location.toString().indexOf('https://') !== -1) {
                M.cfg.wwwroot = M.cfg.wwwroot.replace('http:', 'https:');
            }

        },

        /**
         * OnClick handler for the contextmenu that sends an ajax request for the setting to be changed on
         * the server.
         *
         * @param eventname e.g. 'click'
         * @param thing_with_event
         * @param params
         */
        contextmenu_setting_onclick : function (eventname, thing_with_event, params) {

            var clickednode,
                settingtorequest = 1,
                groupid = null,
                tree = params.mainwidget.get_current_tab().displaywidget,
                settingtype = params.settingtype,
                currentfiltername,
                currentsetting,
                defaultsetting,
                iscontextmenu,
                requestdata = {};

            if (typeof this.parent.contextEventTarget !== 'undefined') {
                // Main context menu does not work for menu button.
                clickednode = tree.getNodeByElement(this.parent.contextEventTarget);
            }
            if (!clickednode && typeof this.parent.element !== 'undefined') {
                // Config tree menu button.
                clickednode = tree.getNodeByElement(this.parent.element.parentElement);
            }
            if (!clickednode && typeof this.parent.parent !== 'undefined') {
                // Groups submenu.
                clickednode = tree.getNodeByElement(this.parent.parent.parent.contextEventTarget);
            }
            // By this point, we should have a node.
            if (!clickednode) {
                return;
            }

            currentfiltername = clickednode.get_current_filter_name();

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
            currentsetting = clickednode.get_config_setting(settingtype, groupid);
            defaultsetting = clickednode.get_default_setting(settingtype, groupid);
            if (currentsetting === null) {
                settingtorequest = defaultsetting ? 0 : 1;
            } else {
                // There is an existing setting. The user toggled from the default last time, so
                // will want to toggle back to default. No point deliberately making a setting when we can
                // just use the default, leaving more flexibility if the defaults are changed (rare).
                settingtorequest = null;
            }

            // Gather data.
            requestdata.groupid = groupid;
            requestdata.menuitemindex = this.index;
            requestdata.menugroupindex = this.groupIndex;
            requestdata.nodeindex = clickednode.index;
            requestdata.settingtype = settingtype;
            if (settingtorequest !== null) { // Leaving out defaults to null on the other end.
                requestdata.settingvalue = settingtorequest;
            }
            requestdata.tablename = (currentfiltername === 'courseid') ? 'course' : 'course_modules';
            iscontextmenu = this.parent instanceof Y.YUI2.widget.ContextMenu;
            requestdata.menutype = iscontextmenu ? 'contextmenu' : 'buttonmenu';
            requestdata.instanceid = clickednode.get_current_filter_value();

            // Send request.
            // TODO this is not good. Need to somehow reduce the convoluted duplication between the two menus.
            params.mainwidget.configtab_tree.save_setting_ajax_request(requestdata, clickednode);
        },

        /**
         * Hides the drop-down menu that may be open on this node
         */
        hide_open_menu : function (expandednode) {

            expandednode.renderedmenu.hide();
        },

        /**
         * This is for the IDE to know what fields are expected for the AJAX response object.
         */
        ajax_response_type : {

        },


        initializer: function (config) { // 'config' contains the parameter values
            // TODO copy init function here
            // TODO replace names of divs etc with config vars.
            this.initialise(Y);
        },



    }, {
        NAME: widgetNAME, //module name is something mandatory.
        // It should be in lower case without space
        // as YUI use it for name space sometimes.
        ATTRS: {
        } // Attributes are the parameters sent when the $PAGE->requires->yui_module calls the module.
        // Here you can declare default values or run functions on the parameter.
        // The param names must be the same as the ones declared
        // in the $PAGE->requires->yui_module call.
    });

    M.block_ajax_marking = M.block_ajax_marking || {};

    M.block_ajax_marking.mainwidget = MAINWIDGET;

    // Holds the instance of the block for the current page. Allows easy access to the callback function from the
    // grading popup so it can ask the widget to remove the node that was just marked.

    M.block_ajax_marking.init_block = function () { // 'config' contains the parameter values
        M.block_ajax_marking.block = new M.block_ajax_marking.mainwidget(); // 'config' contains the parameter values
    };

}, '@VERSION@', {
    requires:[
        'base',
        'yui2-treeview',
        'yui2-button',
        'yui2-connection',
        'yui2-json',
        'yui2-container',
        'yui2-menu',
        'tabview',
        'moodle-block_ajax_marking-coursestree',
        'moodle-block_ajax_marking-cohortstree',
        'moodle-block_ajax_marking-configtree',
        'io-base']
});
