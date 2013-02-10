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
 * YUI3 JavaScript module for the nodes in the config tree.
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  20012 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

YUI.add('moodle-block_ajax_marking-configtreenode', function (Y) {
    "use strict";

    /**
     * Name of this module as used by YUI.
     * @type {String}
     */
    var CONFIGTREENODENAME = 'configtreenode',
        CONFIGTREENODE = function () {

            // Prevents IDE complaining abut undefined vars.
            this.data = {};
            this.data.returndata = {};
            this.data.displaydata = {};
            this.data.configdata = {};

            CONFIGTREENODE.superclass.constructor.apply(this, arguments);
        };

    /**
     * @class M.block_ajax_marking.configtreenode
     */
    Y.extend(CONFIGTREENODE, M.block_ajax_marking.markingtreenode, {

        /**
         * Get the markup for the config tree node.
         *
         * @method getNodeHtml
         * @return {string} The HTML that will render this node.
         */
        getNodeHtml : function () {

            var sb = [],
                i;

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

            // Spacers cells to make indents.
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

            // Make main label on its own row.
            sb[sb.length] = '<td id="'+this.contentElId;
            sb[sb.length] = '" class="ygtvcell ';
            sb[sb.length] = this.contentStyle+' ygtvcontent" ';
            sb[sb.length] = (this.nowrap) ? ' nowrap="nowrap" ' : '';
            sb[sb.length] = ' colspan="4">';

            sb[sb.length] = this.getContentHtml();

            sb[sb.length] = '</td>';
            sb[sb.length] = '</tr>';
            sb[sb.length] = '</table>';

            return sb.join("");
        },

        /**
         * Overrides YAHOO.widget.Node.
         * If property html is a string, it sets the innerHTML for the node.
         * If it is an HTMLElement, it defers appending it to the tree until the HTML basic
         * structure is built.
         */
        getContentHtml : function () {

            var displaysetting,
                sb = [],
                groupsdisplaysetting,
                groupscount = this.get_groups_count(),
                icon = this.get_dynamic_icon(this.get_icon_style()),
                displaytype,
                displayicon,
                groupsicon,
                groupstype;


            sb[sb.length] = '<table class="ygtvtable configtreenode">'; // New.
            sb[sb.length] = '<tr >';
            sb[sb.length] = '<td class="ygtvcell" colspan="8">';
            // TODO alt text

            if (icon) {
                icon.className += ' nodeicon';
                try {
                    delete icon.id;
                }
                catch (e) {
                    // Keep IE9 happy.
                    icon.id = undefined;
                }
                sb[sb.length] = this.get_dynamic_icon_string(icon);
            }

            sb[sb.length] = '<div class="nodelabel" title="'+this.get_tooltip()+'">';
            sb[sb.length] = this.html;
            sb[sb.length] = '</div>';

            sb[sb.length] = '</td>';
            sb[sb.length] = '</tr>';

            // Info row.
            sb[sb.length] = '<tr class="block_ajax_marking_info_row">';

            // Make display icon.
            sb[sb.length] = '<td id="'+"block_ajax_marking_display_icon"+this.index;
            sb[sb.length] = '" class="ygtvcell ';

            displaysetting = this.get_setting_to_display('display');
            displaytype = displaysetting ? 'hide' : 'show'; // Icons are named after their actions.

            displayicon = this.get_dynamic_icon(displaytype);

            try {
                delete displayicon.id;
            }
            catch (othere) {
                // Keep IE9 happy.
                displayicon.id = undefined;
            }
            displayicon = this.get_dynamic_icon_string(displayicon);

            sb[sb.length] = ' block_ajax_marking_node_icon block_ajax_marking_display_icon ';
            sb[sb.length] = '"><div class="ygtvspacer">'+displayicon+'</div></td>';

            // Make groupsdisplay icon.
            sb[sb.length] = '<td id="'+'block_ajax_marking_groupsdisplay_icon'+this.index;
            sb[sb.length] = '" class="ygtvcell ';

            groupsicon = '&#160;';
            if (groupscount) {
                groupsdisplaysetting = this.get_setting_to_display('groupsdisplay');

                // Icons are named after their actions.
                groupstype = groupsdisplaysetting ? 'hidegroups' : 'showgroups';
                groupsicon = this.get_dynamic_icon(groupstype);
                try {
                    delete groupsicon.id;
                }
                catch (yetanothere) {
                    // Keep IE9 happy.
                    groupsicon.id = undefined;
                }
                groupsicon = this.get_dynamic_icon_string(groupsicon);

            }
            sb[sb.length] = ' block_ajax_marking_node_icon block_ajax_marking_groupsdisplay_icon ';
            sb[sb.length] = '"><div class="ygtvspacer">'+groupsicon+'</div></td>';

            // Make groups icon.
            sb[sb.length] = '<td id="'+'block_ajax_marking_groups_icon'+this.index;
            sb[sb.length] = '" class="ygtvcell ';
            sb[sb.length] = ' block_ajax_marking_node_icon block_ajax_marking_groups_icon ';
            sb[sb.length] = '"><div class="ygtvspacer">';

            // Leave it empty if there's no groups.
            if (groupscount !== false) {
                sb[sb.length] = groupscount+' ';
            }

            sb[sb.length] = '</div></td>';

            // Spacer cell - fixed width means we need a few.
            sb[sb.length] = '<td class="ygtvcell"><div class="ygtvspacer"></div></td>';
            sb[sb.length] = '<td class="ygtvcell"><div class="ygtvspacer"></div></td>';
            sb[sb.length] = '<td class="ygtvcell"><div class="ygtvspacer"></div></td>';
            sb[sb.length] = '<td class="ygtvcell"><div class="ygtvspacer"></div></td>';
            sb[sb.length] = '<td class="ygtvcell"><div class="ygtvspacer"></div></td>';

            sb[sb.length] = '</tr>';
            sb[sb.length] = '</table>';

            return sb.join("");
        },

        /**
         * Regenerates the html for this node and its children. To be used when the
         * node is expanded and new children have been added.
         *
         * @method refresh
         */
        refresh : function () {
            this.constructor.superclass.refresh.call(this);
            this.tree.add_groups_buttons(this);
        },

        /**
         * Load complete is the callback function we pass to the data provider
         * in dynamic load situations. Altered to add the bit about adding groups.
         *
         * @method loadComplete
         */
        loadComplete : function (justrefreshchildren) {

            var i,
                j;

            this.getChildrenEl().innerHTML = this.completeRender();
            this.tree.add_groups_buttons(this, justrefreshchildren); // Groups stuck onto all children.
            if (this.propagateHighlightDown) {
                if (this.highlightState === 1 && !this.tree.singleNodeHighlight) {
                    for (i = 0; i < this.children.length; i++) {
                        this.children[i].highlight(true);
                    }
                } else if (this.highlightState === 0 || this.tree.singleNodeHighlight) {
                    for (j = 0; j < this.children.length; j++) {
                        this.children[j].unhighlight(true);
                    }
                } // If (highlightState == 2) leave child nodes with whichever highlight state
                // they are set.
            }

            this.dynamicLoadComplete = true;
            this.isLoading = false;
            this.expand(true);
            this.tree.locked = false;
        },

        /**
         * Getter for the TD element that ought to have the node's groups dropdown. This is used so that
         * we can render the dropdown to the HTML elements before they are appended to the tree, which
         * will prevent flicker.
         */
        get_group_dropdown_div : function () {
            return document.getElementById('block_ajax_marking_groups_icon'+this.index);
        },

        /**
         * Turns the raw groups data from the tree node into menu items and attaches them to the menu. Uses
         * the course groups (courses will have all groups even if there are no settings) to make the full
         * list and combines course defaults and coursemodule settings when it needs to for coursemodules
         *
         * @param {Y.YUI2.widget.Menu} menu A pre-existing context menu
         * @param {M.block_ajax_marking.markingtreenode} clickednode
         * @return void
         */
        add_groups_to_menu: function (menu, clickednode) {

            var newgroup,
                groups,
                groupdefault,
                numberofgroups,
                groupindex,
                i;

            groups = clickednode.get_groups();
            numberofgroups = groups.length;

            var self = this;

            for (i = 0; i < numberofgroups; i += 1) {

                newgroup = {
                    "text": groups[i].name,
                    "value": { "groupid": groups[i].id },
                    "onclick": {
                        fn: self.tree.mainwidget.contextmenu_setting_onclick,
                        obj: {'settingtype': 'group', mainwidget: self.tree.mainwidget}
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
                    if (this.tree.showinheritance) { // TODO does this work?
                        newgroup.classname = 'inherited';
                    }
                }

                // Add to group 1 so we can keep it separate from group 0 with the basic settings so that
                // the contextmenu will have these all grouped together with a title
                groupindex = 0;
                menu.addItem(newgroup, groupindex);
            }

            // If there are no groups, we want to show this rather than have the context menu fail to
            // pop up at all, leaving the normal one to appear in it's place
            if (numberofgroups === 0) {
                // TODO probably don't need this now - never used?
                menu.addItem({"text": M.str.block_ajax_marking.nogroups, "value": 0 });
            }
        },

        /**
         * Will attach a YUI menu button to all nodes with all of the groups so that they can be set
         * to show or hide. Better than a non-obvious context menu. Not part of the config_node object.
         */
        add_groups_button : function () {

            var node,
                menu,
                groupsdiv,
                nodecontents,
                menuconfig,
                buttonconfig;

            node = this;

            // We don't want to render a button if there's no groups.
            groupsdiv = node.get_group_dropdown_div();
            nodecontents = groupsdiv.firstChild.innerHTML;
            if (nodecontents.trim() === '') {
                return;
            }

            // Not possible to re-render so we wipe it.
            if (typeof node.groupsmenubutton !== 'undefined') {
                node.groupsmenubutton.destroy(); // TODO test me.
            }
            if (typeof node.renderedmenu !== 'undefined') {
                node.renderedmenu.destroy(); // TODO test me.
            }
            menuconfig = {
                keepopen : true
            };
            node.renderedmenu = new Y.YUI2.widget.Menu('groupsdropdown'+node.index,
                                                      menuconfig);
            this.add_groups_to_menu(node.renderedmenu, node);

            // The strategy here is to keep the menu open if we are doing an AJAX refresh as we may have
            // a dropdown that has just had a group chosen, so we don't want to make people open it up
            // again to choose another one. They need to click elsewhere to blur it. However, the node
            // refresh will redraw this node's HTML.

            node.renderedmenu.render(node.getEl());

            groupsdiv.removeChild(groupsdiv.firstChild);
            buttonconfig = {
                type : "menu",
                label : nodecontents,
                title : M.str.block_ajax_marking.choosegroups,
                name : 'groupsbutton-'+node.index,
                menu : node.renderedmenu,
                lazyload : false, // Can't add events otherwise.
                container : groupsdiv
            };

            node.groupsmenubutton = new Y.YUI2.widget.Button(buttonconfig);
            // Hide the button if the user clicks elsewhere on the page.
            node.renderedmenu.cfg.queueProperty('clicktohide', true);

            // Click event hides the menu by default for buttons.
            node.renderedmenu.unsubscribe('click', node.groupsmenubutton._onMenuClick);
        },

        /**
         * Returns groupsvisible / totalgroups for the button text.
         */
        get_groups_count : function () {

            // We want to show how many groups are currently displayed, as well as how many there are.
            var groupscurrentlydisplayed = 0,
                groups = this.get_groups(),
                numberofgroups = groups.length,
                display,
                h;

            if (numberofgroups === 0) {
                return false;
            }

            for (h = 0; h < numberofgroups; h++) {

                display = groups[h].display;

                if (display === null) {
                    display = this.get_default_setting('group', groups[h].id);
                }
                if (parseInt(display, 10) === 1) {
                    groupscurrentlydisplayed++;
                }
            }

            return groupscurrentlydisplayed+'/'+numberofgroups;

        },

        /**
         * Saves a new setting into the nodes internal store, so we can keep track of things.
         */
        set_config_setting : function (settingtype, newsetting) {

            var iconcontainer,
                containerid,
                settingtoshow,
                iconname,
                icon,
                spacerdiv;

            // Superclass will store the value and trigger the process in child nodes.
            this.constructor.superclass.set_config_setting.call(this, settingtype, newsetting);

            // Might be inherited...
            settingtoshow = this.get_setting_to_display(settingtype);

            // Set the node's appearance. Might need to refer to parent if it's inherit.
            containerid = '#block_ajax_marking_'+settingtype+'_icon'+this.index;

            iconcontainer = Y.one(containerid);
            spacerdiv = iconcontainer.one('*');
            if (settingtoshow === 1) {
                if (settingtype === 'display') {
                    // Names of icons are for the actions on clicking them. Not what they look like.
                    iconname = 'hide';
                } else if (settingtype === 'groupsdisplay') {
                    iconname = 'hidegroups';
                }
            } else {
                if (settingtype === 'display') {
                    // Names of icons are for the actions on clicking them. Not what they look like.
                    iconname = 'show';
                } else if (settingtype === 'groupsdisplay') {
                    iconname = 'showgroups';
                }
            }

            // Get the icon, remove the old one and put it in place. Includes the title attribute,
            // which matters for accessibility.
            icon = this.get_dynamic_icon(iconname);
            spacerdiv.get('childNodes').remove();
            spacerdiv.appendChild(icon);
        },

        /**
         * Store the new setting and also update the node's appearance to reflect it.
         *
         * @param groupid
         * @param newsetting
         */
        set_group_setting : function (groupid, newsetting) {

            var groupsdetails,
                menuitems,
                i,
                groupdefault,
                checked,
                inherited;

            if (typeof newsetting === 'undefined') {
                newsetting = null;
            }

            // Superclass will store the value and trigger the process in child nodes.
            this.constructor.superclass.set_group_setting.call(this, groupid, newsetting);

            // Update the display on the button label.
            groupsdetails = this.get_groups_count();
            this.groupsmenubutton.set("label", groupsdetails);

            // Get menu items.
            menuitems = this.renderedmenu.getItems();

            for (i = 0; i < menuitems.length; i++) {
                if (menuitems[i].value.groupid === groupid) {
                    // Might be inherited now, so check parent values.
                    groupdefault = this.get_setting_to_display('group', groupid);
                    checked = groupdefault ? true : false;
                    menuitems[i].cfg.setProperty("checked", checked);

                    // TODO set inherited CSS.
                    inherited = (newsetting === null) ? 'notinherited' : 'inherited';
                    menuitems[i].cfg.setProperty("classname", inherited);

                    break; // Only one node with a particular groupid.
                }
            }
        }

    }, {
        NAME : CONFIGTREENODENAME,
        ATTRS : {}
    });

    M.block_ajax_marking = M.block_ajax_marking || {};

    /**
     * Makes the new class accessible.
     *
     * @param config
     * @return {*}
     */
    M.block_ajax_marking.configtreenode = CONFIGTREENODE;

}, '1.0', {
    requires : ['moodle-block_ajax_marking-markingtreenode']
});
