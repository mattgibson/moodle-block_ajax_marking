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
 * YUI3 JavaScript module for the context menu that allows changes to be made to activity settings
 * in the marking trees.
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  20012 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

YUI.add('moodle-block_ajax_marking-contextmenu', function (Y) {
    "use strict";

    /**
     * Name of this module as used by YUI.
     * @type {String}
     */
    var CONTEXTMENUNAME = 'contextmenu',
        CONTEXTMENU = function () {
           CONTEXTMENU.superclass.constructor.apply(this, arguments);
        };

    /**
     * @class M.block_ajax_marking.contextmenu
     */
    Y.extend(CONTEXTMENU, Y.YUI2.widget.ContextMenu, {

        /**
         * Gets the groups from the course node and displays them in the contextmenu.
         *
         * Coming from an onclick in the context menu, so 'this' is the contextmenu instance.
         */
        load_settings : function () {

            // Make the settings items and make sure they reflect the current settings as stored in the
            // tree node.
            var groups,
                target,
                clickednode,
                currentfilter;

            this.clearContent();

            target = this.contextEventTarget;
            clickednode = M.block_ajax_marking.block.get_current_tab().displaywidget.getNodeByElement(target);
            this.clickednode = clickednode; // Makes it easier later.
            groups = clickednode.get_groups();

            // We don't want to allow the context menu for items that we can't hide yet. Right now it
            // only applies to courses and course modules.
            currentfilter = clickednode.get_current_filter_name();
            if (currentfilter !== 'courseid' &&
                currentfilter !== 'coursemoduleid' &&
                currentfilter !== 'groupid') {

                this.cancel();
                return false;
            }

            this.make_setting_menuitem('display', clickednode);

            // If there are no groups, no need to show the groups settings. Also if there are no
            // child nodes e.g. for workshop, or if this is a group node itself.
            if (currentfilter !== 'groupid' &&
                clickednode.isDynamic() && groups.length) {

                this.make_setting_menuitem('groupsdisplay', clickednode);

                if (groups.length) {
                    // Wipe all groups out of the groups sub-menu.
                    M.block_ajax_marking.contextmenu_add_groups_to_menu(this, clickednode);
                    // Enable the menu item, since we have groups in it.
                }
            }

            this.render();
            clickednode.highlight(); // So the user knows what node this menu is for.

            return true;
        },

        /**
         * Turns the raw groups data from the tree node into menu items and attaches them to the menu. Uses
         * the course groups (courses will have all groups even if there are no settings) to make the full
         * list and combines course defaults and coursemodule settings when it needs to for coursemodules
         *
         * @param {M.block_ajax_marking.contextmenu} menu A pre-existing context menu
         * @param {M.block_ajax_marking.markingtreenode} clickednode
         * @return void
         */
        contextmenu_add_groups_to_menu: function (menu, clickednode) {

            var newgroup,
                groups,
                groupdefault,
                numberofgroups,
                groupindex,
                i;

            groups = clickednode.get_groups();
            numberofgroups = groups.length;

            for (i = 0; i < numberofgroups; i += 1) {

                newgroup = {
                    "text": groups[i].name,
                    "value": { "groupid": groups[i].id },
                    "onclick": {
                        fn: this.contextmenu_setting_onclick,
                        obj: {'settingtype': 'group'}
                    }
                };

                // Make sure the items' appearance reflect their current settings
                // JSON seems to like sending back integers as strings

                if (groups[i].display === "1") { // TODO check that types are working here.
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
                    if (clickednode.tree.showinheritance) {
                        newgroup.classname = 'inherited';
                    }
                }

                // Add to group 1 so we can keep it separate from group 0 with the basic settings so that
                // the contextmenu will have these all grouped together with a title
                groupindex = 1;
                menu.addItem(newgroup, groupindex);
            }

            // If there are no groups, we want to show this rather than have the context menu fail to
            // pop up at all, leaving the normal one to appear in it's place
            if (numberofgroups === 0) {
                // TODO probably don't need this now - never used?
                menu.addItem({"text": M.str.block_ajax_marking.nogroups,
                    "value": 0 });
            } else {
                menu.setItemGroupTitle(M.str.block_ajax_marking.choosegroups + ':', 1);
            }
        },

        /**
         * Make sure the item reflects the current settings as stored in the tree node.
         *
         * @param {string} settingname
         * @param {M.block_ajax_marking.markingtreenode} clickednode
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
                    title = M.str.block_ajax_marking.choosegroups+':';
                    menuitem = {
                        classname : 'nocheck'
                    };
                    break;
            }

            menuitem = new Y.YUI2.widget.ContextMenuItem(title, menuitem);
            menuitem = this.addItem(menuitem);

            if (clickednode.get_current_filter_name() === 'groupid') {
                clickednode = clickednode.parent;
            }

            if (settingname !== 'groups') {
                checked = false;
                currentsetting = clickednode.get_config_setting(settingname);
                if (currentsetting === null) {
                    defaultsetting = clickednode.get_default_setting(settingname);
                    checked = defaultsetting ? true : false;
                    if (M.block_ajax_marking.showinheritance) {

                        menuitem.cfg.setProperty("classname", 'inherited');
                    }
                } else {
                    checked = currentsetting ? true : false;
                    if (M.block_ajax_marking.showinheritance) {
                        menuitem.cfg.setProperty("classname", 'notinherited');
                    }
                }
                menuitem.cfg.setProperty('checked', checked);
            }

            return menuitem;
        },

        ajaxcallback : function () {
            // No need for action. Nothing more to update. Drop-down is different.
        }

    }, {
        NAME : CONTEXTMENUNAME, //module name is something mandatory.
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
    M.block_ajax_marking.contextmenu = CONTEXTMENU;

}, '1.0', {
    requires : ['yui2-menu']
});
