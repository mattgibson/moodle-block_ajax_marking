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

    /**
     * Name of this module as used by YUI.
     * @type {String}
     */
    var CONTEXTMENUNAME = 'contextmenu';

    var CONTEXTMENU = function () {
        CONTEXTMENU.superclass.constructor.apply(this, arguments);
    };

    Y.extend(CONTEXTMENU, YAHOO.widget.ContextMenu, {

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
            clickednode = M.block_ajax_marking.get_current_tab().displaywidget.getNodeByElement(target);
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
                    title = M.str.block_ajax_marking.choosegroups+':';
                    menuitem = {
                        classname : 'nocheck'
                    };
                    break;
            }

            menuitem = new YAHOO.widget.ContextMenuItem(title, menuitem);
            menuitem = this.addItem(menuitem);

            if (clickednode.get_current_filter_name() === 'groupid') {
                clickednode = clickednode.parent;
            }

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



