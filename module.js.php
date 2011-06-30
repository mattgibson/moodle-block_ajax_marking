<?php

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
 * This file conatins all the javascript for the AJAX Marking block
 * 
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2007 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 

if(0) { ?><script><?php } // Get the IDE to do proper script highlighting for the javascript
?>

 
//YAHOO.namespace('ajax_marking_block');
M.block_ajax_marking = {};

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
// this holds the timer that keeps trying to add the onclick stuff to the pop ups as the pop up loads
M.block_ajax_marking.popuptimer = '';

M.block_ajax_marking.ajaxnodesurl = M.cfg.wwwroot+'/blocks/ajax_marking/actions/ajax_nodes.php';
M.block_ajax_marking.ajaxgradingurl = M.cfg.wwwroot+'/blocks/ajax_marking/actions/grading_popup.php';


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
 * New unified build nodes function
 * 
 * @param array nodesarray
 */
M.block_ajax_marking.tree_base.prototype.build_nodes = function(nodesarray) {
    
    var newnode = '';
    var nodedata = '';
    var seconds = 0;
    // TODO what if server time and browser time are mismatche?
    var currenttime = Math.round((new Date()).getTime() / 1000); // current unix time
    var iconstyle = '';
    var numberofnodes = nodesarray.length;
    
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
        
        // Add a count unless we only have one thing and we're at the final node e.g. student name
        if (nodedata.display.count > 1 || typeof(nodedata.returndata.callbackfunction) !== 'undefined') {
            nodedata.label = '(<span class="AMB_count">'+nodedata.display.count+'</span>) '+nodedata.label;
        } 
        
        newnode = new YAHOO.widget.TextNode(nodedata, M.block_ajax_marking.parentnodeholder, false);

        // set the node to load data dynamically, unless it has not sent a callback i.e. it's a final node
        if (typeof(nodedata.returndata.callbackfunction) !== 'undefined' && 
            nodedata.returndata.callbackfunction !== false) {
            
            newnode.setDynamicLoad(this.request_node_data);
        }
        
        // We assume that the modules have added any css they want to add in styles.php
        newnode.labelStyle = 'block_ajax_marking_node_'+nodedata.display.style;
        
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
    
    // finally, run the function that updates the original node and adds the children. Won't be there
    // if we have just built the tree
    if (typeof(M.block_ajax_marking.oncompletefunctionholder) === 'function') {
        M.block_ajax_marking.oncompletefunctionholder();
    } else { 
        this.render();
        this.subscribe('clickEvent', M.block_ajax_marking.treenodeonclick);
        //this.subscribe('clickEvent', M.block_ajax_marking.show_modal_grading_interface);
    }

    // the main tree will need the counts updated, but not the config tree
    // TODO test this
    //if (typeof(M.block_ajax_marking.parentnodeholder) === 'object' && 
    //    typeof(M.block_ajax_marking.parentnodeholder.count) === 'integer') {
    this.update_parent_node(M.block_ajax_marking.parentnodeholder);
    //}
    
}

/**
 *
 * @param nodesarray
 */
//M.block_ajax_marking.tree_base.prototype.build_assessment_nodes = function(nodesarray) {
//
//    var tempnode = '';
//    
//    // cycle through the array and make the nodes
//    var nodeslength = nodesarray.length;
//    
//    for (var m = 0; m < nodeslength; m++) {
//
//        // use the object to create a new node
//        tempnode = new YAHOO.widget.TextNode(nodesarray[m], M.block_ajax_marking.parentnodeholder , false);
//
//        // set the node to load data dynamically, unless it has not sent a callback i.e. it's a final node
//        if ((!this.config) && 
//            typeof(nodesarray[m].callbackfunction) !== 'undefined' && 
//            nodesarray[m].callbackfunction !== false) {
//            
//           tempnode.setDynamicLoad(this.request_node_data);
//        }
//    }
//
//    // finally, run the function that updates the original node and adds the children
//    M.block_ajax_marking.oncompletefunctionholder();
//
//    // the main tree will need the counts updated
//    if (!this.config) {
//        this.update_parent_node(M.block_ajax_marking.parentnodeholder);
//    }
//
//};

/**
 * This function is called when a node is clicked (expanded) and makes the ajax request
 * 
 * @param clickednode
 * @param callbackfunction
 */
M.block_ajax_marking.tree_base.prototype.request_node_data = function(clickednode, callbackfunction) {

    // store details of the node that has been clicked for reference by later
    // callback function
    M.block_ajax_marking.parentnodeholder = clickednode;
    M.block_ajax_marking.oncompletefunctionholder = callbackfunction;
    
    var postdata = [];
    var varname = '';
    
    postdata = M.block_ajax_marking.getreturndata(clickednode);

//    for (varname in clickednode.data.returndata) {
//        postdata.push(varname + '=' + clickednode.data.returndata[varname]);
//    }
    
    postdata = postdata.join('&');
    
    // request data using AJAX
//    var postdata = 'callbackparamone='+clickednode.data.returndata.callbackparamone+
//                   '&callbackfunction='+clickednode.data.returndata.callbackfunction;
//               
//    // Send extra data if it's there
//
//    // Some nodes e.g. quiz questions need 2 parameters to be sent
//    if (typeof(clickednode.data.callbackparamtwo) != 'undefined') {
//        postdata += '&callbackparamtwo='+clickednode.data.callbackparamtwo;
//    }
//    
//    // Some nodes e.g. quiz questions need 2 parameters to be sent
//    if (typeof(clickednode.data.modulename) != 'undefined') {
//        postdata += '&modulename='+clickednode.data.modulename;
//    }
//    
//    // If a group has been clicked, we sent that too so the nodes can be filtered to only include those group members
//    if (typeof(clickednode.data.group) != 'undefined') {
//        postdata += '&group='+clickednode.data.group;
//    }

    // Allow modules to add extra arguments to the AJAX request if necessary
    //var callbackarray = clickednode.data.callbackfunction.split('_');
    // TODO eval is evil - can this work? not tried
    // var type_object = eval('M.block_ajax_marking.'+callbackarray[0]);
//    var callbackobject = M.block_ajax_marking[clickednode.data.modulename];
    //var type_object = M.block_ajax_marking[typearray[0]];

//    if ((typeof(callbackobject) != 'undefined') && (typeof(callbackobject.extra_ajax_request_arguments) != 'undefined')) {
//        postdata += callbackobject.extra_ajax_request_arguments(clickednode);
//    }
 
    YAHOO.util.Connect.asyncRequest('POST', M.block_ajax_marking.ajaxnodesurl, block_ajax_marking_callback, postdata);
};

/**
 * function to update the parent assessment node when it is refreshed dynamically so that
 * if more work has been found, or a piece has now been marked, the count for that label will be
 * accurate along with the counts of all its parent nodes and the total count.
 * 
 * @param parentnodetoupdate the node of the treeview object to alter the count of
 * @return void
 */
M.block_ajax_marking.tree_base.prototype.update_parent_node = function(parentnodetoupdate) {

    // stop at the root one to end the recursion
    if (parentnodetoupdate.isRoot()) {
        // updates the tree's HTML after child nodes are added
        this.root.refresh();
        this.update_total_count();
        
    } else {
    
        var nextnodeup = parentnodetoupdate.parent;
        var nodechildrenlength = parentnodetoupdate.children.length;
    
        // if the last child node was just removed, this one is now empty with all
        // outstanding work marked, so we remove it.
        if (nodechildrenlength === 0) {
    
            this.removeNode(parentnodetoupdate, true);
    
        } else {
    
            // sum the counts of all the child nodes, then update with the new count
            var runningtotal = 0;
            var childcount   = 0;
            var i = 0;
            
            for (i = 0; i < nodechildrenlength; i++) {
                childcount = parentnodetoupdate.children[i].data.display.count;
                runningtotal += parseInt(childcount, 10);
            }
    
            this.update_node_count(parentnodetoupdate, runningtotal);
        }
        // move up one level so that the change propagates to the whole tree recursively
        if (nextnodeup != null) {
            this.update_parent_node(nextnodeup);
        }
    }
};

/**
 * function to alter a node's label with a new count once the children are removed or reloaded
 * 
 * @param newnode the node of the tree whose count we wish to change
 * @param newcount the new number of items to display
 * @return void
 */
M.block_ajax_marking.tree_base.prototype.update_node_count = function(newnode, newcount) {

    var newlabel       = '(<span class="AMB_count">'+newcount+'</span>) '+newnode.data.display.name;
    newnode.data.display.count = newcount;
    newnode.label      = newlabel;
};

/**
 * 
 */
M.block_ajax_marking.treenodeonclick = function(oArgs) {

    // refs save space
    var node = oArgs.node;
    var mbam = window.M.block_ajax_marking;

    // we only need to do anything if the clicked node is one of
    // the final ones with no children to fetch.
    if (typeof(node.data.returndata.callbackfunction) !== 'undefined' 
        && node.data.returndata.callbackfunction !== false) {
        
        return false;
    }

    // putting window.open into the switch statement causes it to fail in IE6.
    // No idea why.
//                    var timer_function = '';

    // Load the correct javascript object from the files that have been included.
    // The type attached to the node data should always start with the name of the module, so
    // we extract that first and then use it to access the object of that
    // name that was created when the page was built by the inclusion
    // of all the module_grading.js files.
//                    var typearray = node.data.ca.split('_');
//                    var type = typearray[0];

    var module_javascript = mbam[node.data.returndata.modulename];

    // Open a pop up with the url and arguments as specified in the module specific object
    //var popupurl = M.cfg.wwwroot + module_javascript.pop_up_opening_url(node);
    var popupurl = window.M.cfg.wwwroot + '/blocks/ajax_marking/actions/grading_popup.php?';

    // using an array so we can join neatly with &.
    var popupget = [];
    
    for (varname in node.data.returndata) {
        
        popupget.push(varname + '=' + node.data.returndata[varname]);
    }
    
    // Add the index of the clicked node so we can remove it if the marking succeeds
    popupget.push('node='+node.index);

    if (popupget.length == 0) {
        // TODO handle error here - there should always be parameters for the pop up, otherwise we stop.
        return false;
    }

    popupurl += popupget.join('&');
//    postdata = popupget.join('&');

    // Get window size, etc
    var popupargs = module_javascript.pop_up_arguments(node);
    
    // AJAX version
//    M.block_ajax_marking.show_modal_grading_interface(postdata);

    // Pop-up version
    mbam.popupholder = window.open(popupurl, 'ajax_marking_popup', popupargs);
    mbam.popupholder.focus();
    
    return false;
};

/**
 * Rcursive function to get the return data from this node and all its parents in the right order
 * 
 * @param object node
 * @param bool main This tells us whether to return the callbackfunction or not
 */
M.block_ajax_marking.getreturndata = function(node) {
    
    var returndata = [];
    
    // The callback function is the GROUP BY for the next set of nodes, so this comes first
    returndata.push('callbackfunction='+node.data.returndata.callbackfunction);
    
    var nextparentnode = node;
    
    while (!nextparentnode.isRoot()) {
        // Add the other item
        for (varname in nextparentnode.data.returndata) {
            // Add all the non-callbackfunction stuff
            if (varname != 'callbackfunction' && nextparentnode.data.returndata[varname] != '') {
                returndata.push(varname + '=' + nextparentnode.data.returndata[varname]); 
            }      

        }
        
        nextparentnode = nextparentnode.parent;
        
    }
    
    return returndata;
}


M.block_ajax_marking.configonclick = function(clickargumentsobject) {

    var ajaxdata  = '';

    // function to make checkboxes for each of the three main options
    function make_box(value, id, label) {

        try{
            box = document.createElement('<input type="radio" name="display" />');
        }catch(error){
            box = document.createElement('input');
        }
        box.setAttribute('type','radio');
        box.setAttribute('name','display');
        box.value = value;
        box.id    = id;

        box.onclick = function() {
            M.block_ajax_marking.request_config_checkbox_data(this);
        };
        formDiv.appendChild(box);

        var boxText = document.createTextNode(label);
        formDiv.appendChild(boxText);

        var breaker = document.createElement('br');
        formDiv.appendChild(breaker);
    }

    // remove group nodes from the previous item if they are there.
    M.block_ajax_marking.remove_config_right_panel_contents();

    var title = document.getElementById('configInstructions');
    title.innerHTML = clickargumentsobject.node.data.icon+clickargumentsobject.node.data.name;


    var formDiv = document.getElementById('configshowform');
    // grey out the form before ajax call - it will be un-greyed later
    formDiv.style.color = '#AAA';

    // add hidden variables so they can be used for the later AJAX calls
    // If it's a course, we send things a bit differently

    var hiddeninput1   = document.createElement('input');
    hiddeninput1.type  = 'hidden';
    hiddeninput1.name  = 'course';
    hiddeninput1.value = (clickargumentsobject.node.data.type == 'config_course') ? clickargumentsobject.node.data.id : clickargumentsobject.node.parent.data.id;
    formDiv.appendChild(hiddeninput1);

    var hidden2       = document.createElement('input');
        hidden2.type  = 'hidden';
        hidden2.name  = 'assessment';
        hidden2.value = clickargumentsobject.node.data.id;
    formDiv.appendChild(hidden2);

    var hidden3   = document.createElement('input');
        hidden3.type  = 'hidden';
        hidden3.name  = 'assessmenttype';
        hidden3.value = (clickargumentsobject.node.data.type == 'config_course') ? 'course' : clickargumentsobject.node.data.type;
    formDiv.appendChild(hidden3);

    // For non courses, add a default checkbox that will remove the record
    if (clickargumentsobject.node.data.type != 'config_course') {
        M.block_ajax_marking.make_box('default', 'config0', M.str.block_ajax_marking.coursedefault, formDiv);
        // make the three main checkboxes, appending them to the form as we go along
        M.block_ajax_marking.make_box('display',    'config1', M.str.block_ajax_marking.showthisassessment, formDiv);
        M.block_ajax_marking.make_box('groups',  'config2', M.str.block_ajax_marking.showwithgroups, formDiv);
        M.block_ajax_marking.make_box('hide',    'config3', M.str.block_ajax_marking.hidethisassessment, formDiv);

    } else {
        M.block_ajax_marking.make_box('display',    'config1', M.str.block_ajax_marking.showthiscourse, formDiv);
        M.block_ajax_marking.make_box('groups',  'config2', M.str.block_ajax_marking.showwithgroups, formDiv);
        M.block_ajax_marking.make_box('hide',    'config3', M.str.block_ajax_marking.hidethiscourse, formDiv);
    }

    // now, we need to find out what the current group mode is and display that box as checked.
    if (clickargumentsobject.node.data.type !== 'config_course') {
        ajaxdata += 'courseid='       +clickargumentsobject.node.parent.data.id;
        ajaxdata += '&assessmenttype='+clickargumentsobject.node.data.type;
        ajaxdata += '&assessmentid='  +clickargumentsobject.node.data.id;
        ajaxdata += '&type=config_check';
    } else {
        ajaxdata += 'courseid='             +clickargumentsobject.node.data.id;
        ajaxdata += '&assessmenttype=course';
        ajaxdata += '&type=config_check';
    }

    var request = YAHOO.util.Connect.asyncRequest('POST', M.block_ajax_marking.ajaxnodesurl, block_ajax_marking_callback, ajaxdata);

    return true;
};

/**
 * Creates the initial nodes for both the main block tree or configuration tree.
 */
//M.block_ajax_marking.tree_base.prototype.build_course_nodes = function(nodesarray) {
//
//    var label = '';
//
//    // store the array of nodes length so that loops are slightly faster
//    var nodeslength = nodesarray.length;
//    
//    // if the array is empty, say that there is nothing to mark
//    if (nodeslength === 0) {
//
//        if (this.config) {
//            label = document.createTextNode(M.str.block_ajax_marking.nogradedassessments);
//        } else {
//            label = document.createTextNode(M.str.block_ajax_marking.nothingtomark);
//        }
//        message_div = document.createElement('div');
//        messagediv.appendChild(label);
//        this.div.appendChild(messagediv);
//        this.icon.removeAttribute('class', 'loaderimage');
//        this.icon.removeAttribute('className', 'loaderimage');
//        
//        if (!document.getElementById('block_ajax_marking_collapse')) {
//            M.block_ajax_marking.make_footer();
//        }
//
//    } else {
//        // there is a tree to be drawn
//        
//        //Remove any old nodes if this is a refresh
//        if (this.root.children.length > 0) {
//
//            this.removeChildren(this.root);
//            this.root.refresh();
//        }
//
//        // cycle through the array and make the nodes
//        for (var n=0; n<nodeslength; n++) {
//        	
//        	//only show the marking totals if its not a config tree
//            if (!this.config) { 
//                label = '('+nodesarray[n].count+') '+nodesarray[n].name;
//            } else {
//                label = nodesarray[n].name;
//            }
//
//            var tempnode = new YAHOO.widget.TextNode(nodesarray[n], this.root, false);
//
//            tempnode.labelStyle = 'icon-course';
//            tempnode.setDynamicLoad(this.request_node_data);
//        }
//
//        // now make the tree, add the total at the top and remove the loading icon
//        this.render();
//
//        // get rid of the loading icon - IE6 is rubbish so use 2 methods
////        this.icon.removeAttribute('class', 'loaderimage');
////        this.icon.removeAttribute('className', 'loaderimage');
//        
//        YAHOO.util.Dom.removeClass(this.icon, 'loaderimage');
//
//        // add onclick events
//        // Main tree option first:
//        if (!this.config) {
//
//            // Alter total count above tree
//            label = document.createTextNode(M.str.block_ajax_marking.totaltomark);
//            var total = document.getElementById('totalmessage');
//            M.block_ajax_marking.remove_all_child_nodes(total);
//            total.appendChild(label);
//            this.update_total_count();
//
//            // this function is the listener for the main tree. Event bubbling means that this
//            // will catch all node clicks
//            
//            // M.block_ajax_marking.treenodeonclick
//            
//            this.subscribe(
//                "clickEvent",
//                function(oArgs) {
//
//                    // ref saves space
//                    var node = oArgs.node;
//                    
//                    // we only need to do anything if the clicked node is one of
//                    // the final ones with no children to fetch.
//                    if (typeof(node.data.callbackfunction) !== 'undefined' && node.data.callbackfunction !== false) {
//                        return false;
//                    }
//
//                    // putting window.open into the switch statement causes it to fail in IE6.
//                    // No idea why.
////                    var timer_function = '';
//                            
//                    // Load the correct javascript object from the files that have been included.
//                    // The type attached to the node data should always start with the name of the module, so
//                    // we extract that first and then use it to access the object of that
//                    // name that was created when the page was built by the inclusion
//                    // of all the module_grading.js files.
////                    var typearray = node.data.ca.split('_');
////                    var type = typearray[0];
//                    
//                    // TODO does this work?
//                    // it used to make a string then eval it
//                    var module_javascript = M.block_ajax_marking[node.data.modulename];
//
//                    // Open a pop up with the url and arguments as specified in the module specific object
//                    //var popupurl = M.cfg.wwwroot + module_javascript.pop_up_opening_url(node);
//                    var popupurl = M.cfg.wwwroot + '/blocks/ajax_marking/actions/grading_popup.php?';
//                    
//                    // using an array so we can join neatly with &.
//                    var popupget = [];
//                    
//                    for (varname in node.data.returndata) {
//                        popupget.push(varname + '=' + node.data.returndata[varname]);
//                    }
//                    
//                    if (popupget.length == 0) {
//                        // handle error here - there should always be parameters for the pop up
//                    }
//                    
//                    popupurl += popupget.join('&');
//                    
//                    var popupargs = module_javascript.pop_up_arguments(node);
//
//                    M.block_ajax_marking.popupholder = window.open(popupurl, 'ajax_marking_popup', popupargs);
//
//                    M.block_ajax_marking.popupholder.focus();                 
//                    return false;
//                }
//            );
//            
//            // Make the footer divs if they don't exist
//            if (!document.getElementById('block_ajax_marking_collapse')) {
//                M.block_ajax_marking.make_footer();
//            }
//
//        } else {
//
//            // procedure for config tree nodes:
//            // This function is the listener for the config tree that makes the onclick stuff happen
//            this.subscribe(
//                'clickEvent',
//                function(clickargumentsobject) {
//
//                    var ajaxdata  = '';
//
//                    // function to make checkboxes for each of the three main options
//                    function make_box(value, id, label) {
//                        
//                        try{
//                            box = document.createElement('<input type="radio" name="show" />');
//                        }catch(error){
//                            box = document.createElement('input');
//                        }
//                        box.setAttribute('type','radio');
//                        box.setAttribute('name','show');
//                        box.value = value;
//                        box.id    = id;
//                        
//                        box.onclick = function() {
//                            M.block_ajax_marking.request_config_checkbox_data(this);
//                        };
//                        formDiv.appendChild(box);
//
//                        var boxText = document.createTextNode(label);
//                        formDiv.appendChild(boxText);
//
//                        var breaker = document.createElement('br');
//                        formDiv.appendChild(breaker);
//                    }
//
//                    // remove group nodes from the previous item if they are there.
//                    M.block_ajax_marking.remove_config_right_panel_contents();
//
//                    var title = document.getElementById('configInstructions');
//                    title.innerHTML = clickargumentsobject.node.data.icon+clickargumentsobject.node.data.name;
//
//
//                    var formDiv = document.getElementById('configshowform');
//                    // grey out the form before ajax call - it will be un-greyed later
//                    formDiv.style.color = '#AAA';
//
//                    // add hidden variables so they can be used for the later AJAX calls
//                    // If it's a course, we send things a bit differently
//
//                    var hiddeninput1   = document.createElement('input');
//                    hiddeninput1.type  = 'hidden';
//                    hiddeninput1.name  = 'course';
//                    hiddeninput1.value = (clickargumentsobject.node.data.type == 'config_course') ? clickargumentsobject.node.data.id : clickargumentsobject.node.parent.data.id;
//                    formDiv.appendChild(hiddeninput1);
//
//                    var hidden2       = document.createElement('input');
//                        hidden2.type  = 'hidden';
//                        hidden2.name  = 'assessment';
//                        hidden2.value = clickargumentsobject.node.data.id;
//                    formDiv.appendChild(hidden2);
//
//                    var hidden3   = document.createElement('input');
//                        hidden3.type  = 'hidden';
//                        hidden3.name  = 'assessmenttype';
//                        hidden3.value = (clickargumentsobject.node.data.type == 'config_course') ? 'course' : clickargumentsobject.node.data.type;
//                    formDiv.appendChild(hidden3);
//
//                    // For non courses, add a default checkbox that will remove the record
//                    if (clickargumentsobject.node.data.type != 'config_course') {
//                        M.block_ajax_marking.make_box('default', 'config0', M.str.block_ajax_marking.coursedefault, formDiv);
//                        // make the three main checkboxes, appending them to the form as we go along
//                        M.block_ajax_marking.make_box('show',    'config1', M.str.block_ajax_marking.showthisassessment, formDiv);
//                        M.block_ajax_marking.make_box('groups',  'config2', M.str.block_ajax_marking.showwithgroups, formDiv);
//                        M.block_ajax_marking.make_box('hide',    'config3', M.str.block_ajax_marking.hidethisassessment, formDiv);
//
//                    } else {
//                        M.block_ajax_marking.make_box('show',    'config1', M.str.block_ajax_marking.showthiscourse, formDiv);
//                        M.block_ajax_marking.make_box('groups',  'config2', M.str.block_ajax_marking.showwithgroups, formDiv);
//                        M.block_ajax_marking.make_box('hide',    'config3', M.str.block_ajax_marking.hidethiscourse, formDiv);
//                    }
//
//                    // now, we need to find out what the current group mode is and display that box as checked.
//                    if (clickargumentsobject.node.data.type !== 'config_course') {
//                        ajaxdata += 'courseid='       +clickargumentsobject.node.parent.data.id;
//                        ajaxdata += '&assessmenttype='+clickargumentsobject.node.data.type;
//                        ajaxdata += '&assessmentid='  +clickargumentsobject.node.data.id;
//                        ajaxdata += '&type=config_check';
//                    } else {
//                        ajaxdata += 'courseid='             +clickargumentsobject.node.data.id;
//                        ajaxdata += '&assessmenttype=course';
//                        ajaxdata += '&type=config_check';
//                    }
//                    
//                    var request = YAHOO.util.Connect.asyncRequest('POST', M.block_ajax_marking.ajaxnodesurl, block_ajax_marking_callback, ajaxdata);
//
//                    return true;
//                }
//            );
//        }
//    }
//};

/**
 * Make the group nodes for an assessment
 * 
* @param ajaxresponsearray Takes ajax data array as input
* @retrun void
 */
M.block_ajax_marking.tree_base.prototype.build_group_nodes = function(ajaxresponsearray) {
    // need to turn the groups for this course into an array and attach it to the course
    // node. Then make the groups bit on screen
    // for the config screen??

    var arrayLength = ajaxresponsearray.length;
    var tempnode = '';

    for (var n =0; n<arrayLength; n++) {

        tempnode = new YAHOO.widget.TextNode(ajaxresponsearray[n], M.block_ajax_marking.parentnodeholder, false);
        tempnode.labelStyle = 'icon-group';

        // if the groups are for journals, it is impossible to display individuals, so we make the
        // node clickable so that the pop up will have the group screen.
        // TODO make this into a dynamic thing based on another attribute of the data object
        if (typeof(node.data.callbackfunction) !== 'undefined') {
            tempnode.setDynamicLoad(this.request_node_data);
        }
    }

    this.update_parent_node(M.block_ajax_marking.parentnodeholder);
    M.block_ajax_marking.oncompletefunctionholder();
};


/**
 * Builds the tree when the block is loaded, or refresh is clicked
 * 
 * @return void
 */
M.block_ajax_marking.tree_base.prototype.build_ajax_tree = function() {
    
    // show that the ajax request has been initialised
    YAHOO.util.Dom.addClass(this.icon, 'loaderimage');
    
    // send the ajax request
    var request = YAHOO.util.Connect.asyncRequest('POST', M.block_ajax_marking.ajaxnodesurl, block_ajax_marking_callback, this.course_post_data);
    
};

/**
* function to update the total marking count by a specified number and display it
* 
* @return void
*/
M.block_ajax_marking.tree_base.prototype.update_total_count = function() {

    var totalcount = 0;
    var childcount = 0;
    var children = this.root.children;
    var childrenlength = children.length;

    for (var i=0; i<childrenlength; i++) {
        childcount = children[i].data.display.count;
        totalcount += parseInt(childcount, 10);
    }
    
    if (totalcount > 0) {
        document.getElementById('totalmessage').style.visibility = 'visible';
        document.getElementById('count').innerHTML = totalcount.toString();
        
    } else {
        // hide the count
        document.getElementById('totalmessage').style.visibility = 'collapse';
        M.block_ajax_marking.remove_all_child_nodes(document.getElementById('count'));
    }
};

/**
* This function updates the tree to remove the node of the pop up that has just been marked,
* then it updates the parent nodes and refreshes the tree, then sets a timer so that the popup will
* be closed when it goes to the 'changes saved' url.
*
* @param windowurl The changes saved url. Can be passed as an empty string if the windows shuts itself anyway
* @param nodeuniqueid The id of the node to remove
* @return void
*/
M.block_ajax_marking.tree_base.prototype.remove_node_from_tree = function(nodeuniqueid, windowurl) {

    /// get the node that was just marked and its parent node
    var nodetoremove = this.getNodeByProperty('index', nodeuniqueid);

    var parentnode = nodetoremove.parent;
    // remove the node that was just marked
    this.removeNode(nodetoremove, true);

    this.update_parent_node(parentnode);
   
    // refresh the tree to redraw the nodes with the new labels
    // TODO make sure this fires the function
    M.block_ajax_marking.refresh_tree_after_changes(this);

    this.update_total_count();

    // no need if there's no url as the pop up is self closing
//    if (windowurl !== undefined) {
//        var window_closer = "M.block_ajax_marking.popup_closing_timer('"+windowurl+"')";
//        setTimeout(window_closer, 500);
//    }
};

/**
 * Ajax success function called when the server responds with valid data, which checks the data 
 * coming in and calls the right function depending on the type of data it is
 * 
 * @param o the ajax response object, passed automatically
 * @return void
 */
M.block_ajax_marking.ajax_success_handler = function(o) {

    var label = '';
    var mbam = M.block_ajax_marking;
    
    try {
        ajaxresponsearray = YAHOO.lang.JSON.parse(o.responseText);
    } catch (error) {
        // add an empty array of nodes so we trigger all the update and cleanup stuff
        // TODO - error handling code to prevent silent failure if data is mashed
    }

    // first object holds data about what kind of nodes we have so we can
    // fire the right function.
    var nodesarray = [];
    var payload = 'default';
    if (typeof(ajaxresponsearray) === 'object') {
        payload = ajaxresponsearray.data.payloadtype;
        nodesarray = ajaxresponsearray.nodes
    }
        
    // TODO - these are inconsistent. Some refer to where the request
    // is triggered and some to what it creates.

    switch (payload) {

        case 'config_main_tree':

            mbam.tree.build_course_nodes(nodesarray);
            break;

        case 'config_course':

            mbam.tree.build_assessment_nodes(nodesarray);
            break;

        case 'config_groups':

            // called when the groups settings have been updated.

            // TODO - only change the select value, don't totally re build them
            mbam.make_config_groups_list(nodesarray);
            break;

        case 'config_set':

            //just need to un-disable the radio button

            if (ajaxresponsearray.nodes[0].value === false) {
                label = document.createTextNode(mbam.variables.nogradedassessments);
                mbam.remove_all_child_nodes(mbam.tree.status);
                mbam.tree.status.appendChild(label);
            } else {
                mbam.enable_config_radio_buttons();
            }
            break;

        case 'config_check':
            // called when any data about config comes back after a request (not a data
            // setting request)

            // make the id of the checkbox div
            var checkboxid = 'config'+nodesarray[0].value;

            // make the radio button on screen match the value in the database that was just 
            // returned.
            document.getElementById(checkboxid).checked = true;

            // if its set to 'display by groups', make the groups bit underneath
            if (ajaxresponsearray.nodes[0].value == 2) {
                // remove the config bit leaving just the groups, which were tacked onto the
                // end of the returned array
                ajaxresponsearray.nodes.shift();
                //make the groups bit
                mbam.make_config_groups_list(nodesarray);
            }
            //allow the radio buttons to be clicked again
            mbam.enable_config_radio_buttons();
            break;

        case 'config_group_save':

            if (ajaxresponsearray.nodes[0].value === false) {
                mbam.tree.status.innerHTML = 'AJAX error';
            } else {
                mbam.enable_config_radio_buttons();
            }

            break;

        case 'gradinginterface':
            // We have gotten the grading form back. Need to add the HTML to the modal overlay


            M.block_ajax_marking.gradinginterface.setHeader(''); 
            M.block_ajax_marking.gradinginterface.setBody(ajaxresponsearray.content); 

            // Initialise any editors
            // foreach editor in the div

            //M.editor_tinymce.init_editor(Y, editorid, options);

            // now we need to add an onclick handler for the submit button and cancel button

            break;

        default:

           // if (ajaxresponsearray.nodes.length > 0) {
            mbam.tree.build_nodes(ajaxresponsearray.nodes);
            //} else {
                // No nodes returned. Do something
            //}

    } // end switch
        
    
    // rebuild nodes and optionally add 'nothing to mark' if we have none left
    M.block_ajax_marking.refresh_tree_after_changes(M.block_ajax_marking.tree);
    
    YAHOO.util.Dom.removeClass(M.block_ajax_marking.tree.icon, 'loaderimage');
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
        M.block_ajax_marking.tree.div.innerHTML =  M.str.block_ajax_marking.collapseString;
    }
    
    // communication failure
    if (o.tId === 0) {
        M.block_ajax_marking.tree.div.innerHTML = M.str.block_ajax_marking.connectfail;
        //YAHOO.util.Dom.removeClass(M.block_ajax_marking.markingtree.icon, 'loaderimage');
        
        if (!document.getElementById('block_ajax_marking_collapse')) {
            M.block_ajax_marking.make_footer();
        }
    }
    
    YAHOO.util.Dom.removeClass(this.icon, 'loaderimage');
};

/**
 * This function enables the config popup radio buttons again after the AJAX request has
 * returned a success code.
 *
 * @return void
 */
//M.block_ajax_marking.enable_config_radio_buttons = function() {
//    
//    var radio = document.getElementById('configshowform');
//    YAHOO.util.setStyle(radio, 'color', '#000');
//
//    var radiolength = radio.childNodes.length;
//
//    for (var h = 0; h < radiolength; h++) {
//        
//        if (radio.childNodes[h].name == 'show') {
//            YAHOO.util.Dom.setAttribute(radio.childNodes[h], 'disabled', false);
//        }
//    }
//    
//    var configgroupsdiv = document.getElementById('configgroupsdiv');
//    YAHOO.util.setStyle(configgroupsdiv, 'color', '#000');
//
//    for (var i = 0; i < configgroupsdiv.childNodes.length; i++) {
//        
//        if (configgroupsdiv.childNodes[i].type == 'checkbox') {
//            YAHOO.util.Dom.setAttribute(configgroupsdiv.childNodes[i], 'disabled', false);
//        }
//    }
//};

/**
 * Makes one of the config checkboxes and attaches it to the form next to the config tree
 */
//M.block_ajax_marking.makeBox = function(value, id, label, formDiv) {
//
//    var box = '';
//
//    try{
//        box = document.createElement('<input type="radio" name="show" />');
//    }catch(error){
//        box = document.createElement('input');
//    }
//    box.setAttribute('type','radio');
//    box.setAttribute('name','show');
//    box.value = value;
//    box.id    = id;
//    box.onclick = function() {
//        M.block_ajax_marking.request_config_checkbox_data(this);
//    };
//    formDiv.appendChild(box);
//
//    var boxText = document.createTextNode(label);
//    formDiv.appendChild(boxText);
//
//    var breaker = document.createElement('br');
//    formDiv.appendChild(breaker);
//
//};

/**
 * This function disables the radio buttons when AJAX request is sent so that
 * more than one request can't be sent at once.
 * 
 * @return void
 */
//M.block_ajax_marking.disable_config_radio_buttons = function() {
//
//    var radio = document.getElementById('configshowform');
//    YAHOO.util.setStyle(radio, 'color', '#aaa');
//    
//    var radiolength = radio.childNodes.length;
//    
//    for (var h = 0; h < radiolength; h++) {
//        
//        if (radio.childNodes[h].type == 'radio') {
//            YAHOO.util.Dom.setAttribute(radio.childNodes[h], 'disabled', true);
//        }
//    }
//    
//    var configgroupsdiv = document.getElementById('configgroupsdiv');
//    YAHOO.util.setStyle(configgroupsdiv, 'color', '#aaa');
//
//    for (var i = 0; i < configgroupsdiv.childNodes.length; i++) {
//        
//        if (configgroupsdiv.childNodes[i].type == 'checkbox') {
//            YAHOO.util.Dom.setAttribute(configgroupsdiv.childNodes[i], 'disabled', true);
//        }
//    }
//};

/**
 * funtion to refresh all the nodes once the update operations have all been carried out by
 * remove_node_from_tree()
 * 
 * @param treeobject the tree to be refreshed
 * @return void
 */
M.block_ajax_marking.refresh_tree_after_changes = function(treeobject) {
    
    treeobject.render();
    
    treeobject.root.refresh();

    // If there are no nodes left, we need to remove the tree altogether
    if (treeobject.root.children.length === 0) {
        
        M.block_ajax_marking.remove_all_child_nodes(document.getElementById("totalmessage"));
        M.block_ajax_marking.remove_all_child_nodes(document.getElementById("count"));
        
        //TODO this bit used to be only for the workshop - is it OK all the time, or even needed?
        //M.block_ajax_marking.remove_all_child_nodes(treeobject.div);
        
        //treeobject.div.appendChild(document.createTextNode(M.str.block_ajax_marking.nothingtomark));
    }
};

/**
* Refresh tree function - for Collapse & refresh link in the main block
* 
* @param treeobject the YUI treeview object to refresh (config or main)
* @return void
*/
M.block_ajax_marking.refresh_tree = function(treeobject) {

    // might be no nodes left after marking them all previously
    // TODO hard coded to one tree
//    if (M.block_ajax_marking.tree.root.nodesLength === 0) {
//        M.block_ajax_marking.tree.build_ajax_tree();
//    }

    // Get rid of the existing tree nodes first, but don't rerender to avoid flicker
    var rootnode = treeobject.getRoot();
    var numberofnodes = rootnode.children.length;
    for (var i = 0; i < numberofnodes; i++) {
        treeobject.removeNode(rootnode.children[0], true);
    }
    
    // Reload the data for the root node. We keep the tree object intect rather than destroying
    // and recreating in order to improve responsiveness.
    M.block_ajax_marking.parentnodeholder = rootnode;
    M.block_ajax_marking.oncompletefunctionholder = 'rootnode.loadcomplete';
    treeobject.build_ajax_tree();
    
};

/**
 * Makes a list of groups as checkboxes and appends them to the config div next to the config
 * tree. Called when the 'show by groups' check box is selected for a node.
 * 
 * @param data the list of groups for this assessment returned from the ajax call
 * @return void
 */
M.block_ajax_marking.make_config_groups_list = function(data) {

    varconfiggroupsdiv = document.getElementById('configgroupsdiv');
    M.block_ajax_marking.remove_all_child_nodes(groupsdiv);

    // Closure holding onclick function.
    var config_checkbox_onclick = function() {
        M.block_ajax_marking.config_checkbox_onclick();
    };
    
    // Continue the numbering of the ids from 4 (main checkboxes are 1-3). This allows us to
    // disable/enable them
    var idcounter = 4;
    
    var datalength = data.length;
    
    if (datalength === 0) {
        var emptylabel = document.createTextNode(M.str.block_ajax_marking.nogroups);
        configgroupsdiv.appendChild(emptylabel);
    }
    
    for (var v = 0; v < datalength; v++) {

        var checkbox = '';
        
        try {
            checkbox = document.createElement('<input type="checkbox" name="display" />');
        } catch(err) {
            checkbox = document.createElement('input');
        }
        
        checkbox.setAttribute('type','checkbox');
        checkbox.setAttribute('name','groups');
        checkbox.id = 'config'+idcounter;
        checkbox.value = data[v].id;
        configgroupsdiv.appendChild(checkbox);

        if (data[v].display == 'true') {
            checkbox.checked = true;
        } else {
            checkbox.checked = false;
        }
        checkbox.onclick = config_checkbox_onclick;

        var label = document.createTextNode(data[v].name);
        configgroupsdiv.appendChild(label);

        var breaker = document.createElement('br');
        configgroupsdiv.appendChild(breaker);
        
        idcounter++;
    }
    
    // remove the ajax loader icon and re-enable the radio buttons
    M.block_ajax_marking.tree.icon.removeAttribute('class', 'loaderimage');
    M.block_ajax_marking.tree.icon.removeAttribute('className', 'loaderimage');
    M.block_ajax_marking.enable_config_radio_buttons();
};

M.block_ajax_marking.ajax_timeout_handler = function(o) {
    // Do something sensible
   
};

/**
 * on click function for the groups check boxes on the config screen. clicking sets or unsets
 * a particular group for display.
 * 
 * @return void
 */
M.block_ajax_marking.config_checkbox_onclick = function() {

    var form = document.getElementById('configshowform');

    M.block_ajax_marking.disable_config_radio_buttons();

    // hacky IE6 compatible fix
    for (var c = 0; c < form.childNodes.length; c++) {
        
        switch (form.childNodes[c].name) {
            
            case 'course':
                var course = form.childNodes[c].value;
                break;
                
            case 'assessmenttype':
                var assessmentType = form.childNodes[c].value;
                break;
                
            case 'assessment':
                var assessment = form.childNodes[c].value;
                break;
        }
    }

    // need to construct a space separated list of group ids.
    var groupids = '';
    var configgroupsdiv = document.getElementById('configgroupsdiv');
    var groups = configgroupsdiv.getElementsByTagName('input');
    var groupslength = groups.length;

    for (var a = 0; a < groupslength; a++) {
        
        if (groups[a].checked === true) {
            groupids += groups[a].value+' ';
        }
    }
    // there are no checked boxes
    if (groupids === '') {
        // Don't leave the db field empty as it will cause confusion between no groups chosen
        // and first time we set this.
        groupids = 'none';
    }

    var postdata = 'id='+course+'&assessmenttype='+assessmentType+'&assessmentid='+assessment
                   +'&type=config_group_save&userid='+M.str.block_ajax_marking.userid
                   +'&display=2&groups='+groupids;

    var request = YAHOO.util.Connect.asyncRequest('POST', M.block_ajax_marking.ajaxnodesurl, block_ajax_marking_callback, postdata);
};

/**
 * function that waits till the pop up has a particular location,
 * i.e. the one it gets to when the data has been saved, and then shuts it.
 * 
 * @param urltoclose the url to wait for, which is normally the 'data has been saved' page
 * @return void
 */
M.block_ajax_marking.popup_closing_timer = function (urltoclose) {

    if (!M.block_ajax_marking.popupholder.closed) {

        if (M.block_ajax_marking.popupholder.location.href == M.cfg.wwwroot+urltoclose) {

            M.block_ajax_marking.popupholder.close();
            delete  M.block_ajax_marking.popupholder;

        } else {

            setTimeout(M.block_ajax_marking.popup_closing_timer(urltoclose), 1000);
        }
    }
};

/**
 * IE seems not to want to expand the block when the tree becomes wider.
 * This provides a one-time resizing so that it is a bit bigger
 * 
 * @return void
 */
M.block_ajax_marking.adjust_width_for_ie = function () {
    if (/MSIE (\d+\.\d+);/.test(navigator.userAgent)){

        var treediv = document.getElementById('treediv');
        var width = treediv.offsetWidth;
        // set width of main content div to the same as treediv
        var contentdiv = treediv.parentNode;
        contentdiv.style.width = width;
    }
};

/**
 * The panel for the config tree and the pop ups is the same and is created
 * here if it doesn't exist yet
 * 
 * @return void
 */
M.block_ajax_marking.initialise_config_panel = function () {
    M.block_ajax_marking.configoverlaypanel = new YAHOO.widget.Panel(
            'configoverlaypanel',
            {
                width       : '470px',
                height      : '530px',
                fixedcenter : true,
                close       : true,
                draggable   : false,
                zindex      : 110,
                modal       : true,
                visible     : false,
                iframe      : true
            }
        );
};

/**
 * Builds the greyed out overlay and panel for the config overlay
 * 
 * @return void
 */
M.block_ajax_marking.build_config_overlay = function() {

    var mbam = M.block_ajax_marking;

    if (!mbam.configoverlaypanel) {

        mbam.initialise_config_panel();

        var settingsheadertext = mbam.variables.settingsheadertext+' '+mbam.variables.fullname;
        mbam.configoverlaypanel.setHeader(settingsheadertext);

        // TODO use yui layout widget for this
        var bodytext = "<div id='configicon' class='block_ajax_marking_hidden'></div>"
        		     + "<div id='configstatus'></div>"
        		     + "<div id='configtree'></div>"
        		     + "<div id='configSettings'>"
                         + "<div id='configInstructions'>"+mbam.variables.instructions+"</div>"
                         + "<div id='configCheckboxes'>"
                         		+ "<form id='configshowform' name='configshowform'></form>"
                         + "</div>"
                         + "<div id='configgroupsdiv'></div>"
                     + "</div>";

        mbam.configoverlaypanel.setBody(bodytext);

        mbam.configoverlaypanel.beforeHideEvent.subscribe(function() {
            mbam.refresh_tree(mbam.tree);
        });

        mbam.configoverlaypanel.render(document.body);
        mbam.configoverlaypanel.show();
        
        // Now that the grey overlay is in place with all the divs ready, we build the config tree
        if (typeof (mbam.tree) != 'object') {
            mbam.tree = mbam.tree_factory('config');
            mbam.tree.build_ajax_tree();
        }
        
        YAHOO.util.Dom.addClass(mbam.tree.icon, 'loaderimage');

    } else {
        // It's all there from earlier, so just show it
        mbam.configoverlaypanel.show();
        mbam.remove_config_right_panel_contents();
        mbam.refresh_tree(mbam.tree);
    }
};

M.block_ajax_marking.config_checkbox_ = function(show) {


};

/**
 * the onclick function for the radio buttons in the config screen. If show by group is clicked, 
 * the groups checkboxes appear below. If another one is, the groups thing is hidden.
 * 
 * @param checkbox the box that was clicked
 * @return void
 */
M.block_ajax_marking.request_config_checkbox_data = function(checkbox) {

    //TODO did these changes work?
    
    var show = '';

    var form = document.getElementById('configshowform');
    
    // use proper constants here for database values?
    switch (checkbox.value) {

        case 'default':

            display = 0;
            break;

        case 'show':

            display = 1;
            break;
            
        case 'groups':
            
            display = 2;
            break;
            
        case 'hide':
        
            display = 3;
            break; 
    }

    //empty the groups area in case there were groups there last time
    M.block_ajax_marking.remove_all_child_nodes(document.getElementById('configgroupsdiv'));
    
    //Construct the url and data, with variations depending on whether it's option 2 (where groups
    // need to be requested to make the checkboxes) or not
    var postData   = 'userid='+M.str.block_ajax_marking.userid;
        postData  += '&assessmenttype='+form.elements['assessmenttype'].value;
        postData  += '&assessmentid='+form.elements['assessment'].value;
        postData  += '&display='+display;
    
    var nongroupvalues = '0 1 3';
    
    if (nongroupvalues.search(show) == -1) {
        // submit show value and do groups stuff
        postData  += '&id='+form.elements['course'].value;
        postData  += '&type=config_groups';
        
    } else {
        // just submit the new show value
        postData += '&id='+form.elements['assessment'].value;
        postData += '&type=config_set';
    }
    
    M.block_ajax_marking.disable_config_radio_buttons();
    
    YAHOO.util.Connect.asyncRequest('POST', M.block_ajax_marking.ajaxnodesurl, block_ajax_marking_callback, postData);
};

/**
 * Wipes all the config and group options away when another node or a course node is clicked in 
 * the config tree
 * 
 * @return void
 */
M.block_ajax_marking.remove_config_right_panel_contents = function() {

    M.block_ajax_marking.remove_all_child_nodes(document.getElementById('configshowform'));
    M.block_ajax_marking.remove_all_child_nodes(document.getElementById('configInstructions'));
    M.block_ajax_marking.remove_all_child_nodes(document.getElementById('configgroupsdiv'));
    return true;
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
            onclick   : {fn: function() {M.block_ajax_marking.refresh_tree(M.block_ajax_marking.tree);}},
            container : 'block_ajax_marking_refresh_button'});

//    var configurebutton = new YAHOO.widget.Button({
//            label     : M.str.block_ajax_marking.configure,
//            id        : 'block_ajax_marking_configure_button_button',
//            onclick   : {fn: function() {M.block_ajax_marking.build_config_overlay();}},
//            container : 'block_ajax_marking_configure_button'});

    // Add bits to them like onclick
    // append them to each other and the DOM
};

/**
 * This factory function is to instantiate the tree_base class in order to create the
 * main and config trees.
 * 
 * @return object the Yahoo treeview object
 */
M.block_ajax_marking.tree_factory = function (type) {

    var treeobject = '';

    switch (type) {
        
        case 'main':
            treeobject                  = new M.block_ajax_marking.tree_base('treediv');
            treeobject.icon             = document.getElementById('mainicon');
            treeobject.div              = document.getElementById('status');
            treeobject.course_post_data = 'id='+M.str.block_ajax_marking.userid+'&callbackfunction=main';
            treeobject.config           = false;

            // Set the removal of all child nodes each time a node is collapsed (forces refresh)
            // not needed for config tree
            treeobject.subscribe('collapseComplete', function(node) {
                // TODO - make this not use a hardcoded reference
                M.block_ajax_marking.main.tree.removeChildren(node);
            });
            break;

        case 'config':
            treeobject                  = new M.block_ajax_marking.tree_base('configtree');
            treeobject.icon             = document.getElementById('configicon');
            treeobject.div              = document.getElementById('configstatus');
            treeobject.course_post_data = 'id='+M.str.block_ajax_marking.userid+'&type=config_main_tree&userid=';
            treeobject.course_post_data += M.str.block_ajax_marking.userid;
            treeobject.config           = true;
            break;
    }
    
    // TODO can this be missed out and getRoot() used throughout the code instead where it's needed?
    treeobject.root = treeobject.getRoot();
    return treeobject;
};


M.block_ajax_marking.show_modal_grading_interface = function(postdata) {
    
    // Make the overlay 
    // Initialize the temporary Panel to display while waiting for external content to load 
    if (typeof(M.block_ajax_marking.gradinginterface) === 'undefined') {
                    
        M.block_ajax_marking.gradinginterface =  
            new YAHOO.widget.Panel('gradinginterface',   
                { width:"600px",  
                  fixedcenter:true,  
                  close:true,  
                  draggable:false,  
                  zindex:4, 
                  modal:true, 
                  visible:false 
                }  
            ); 
    }
	 
    // display it with a loading icon
    M.block_ajax_marking.gradinginterface.setHeader("Loading, please wait..."); 
	M.block_ajax_marking.gradinginterface.setBody('<img src="'+M.cfg.wwwroot+'/blocks/ajax_marking/images/ajax-loader.gif" />'); 
	M.block_ajax_marking.gradinginterface.render(document.body); 
    M.block_ajax_marking.gradinginterface.cfg.setProperty('visible', true);
    // display it with a loading icon
    
    // send off the ajax request for it's contents
//    var postdata = clickednode.data.returndata.join();
    postdata += '&sesskey='+M.cfg.sesskey;

    YAHOO.util.Connect.asyncRequest('POST', M.block_ajax_marking.ajaxgradingurl, block_ajax_marking_callback, postdata);
    
    // store the target url so we can use it for adding the ajax submit listener.
    M.block_ajax_marking.gradingholder = postdata;
    
}

M.block_ajax_marking.hide_modal_grading_interface = function(url) {
    M.block_ajax_marking.gradinginterface.cfg.setProperty('visible', false);
    
}


/**
 * Callback object for the AJAX call, which fires the correct function. Doesn't work when part 
 * of the main class. Don't know why - something to do with scope.
 * 
 * @return void
 */
var  block_ajax_marking_callback = {

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
 * This is run before the block does anything so that we can't see the HTML stuff if we don't need to
 */
M.block_ajax_marking.hide_html_list = function() {
    
    var styleElement = document.createElement("style");
    styleElement.type = "text/css";
    
    if (styleElement.styleSheet) {
        styleElement.styleSheet.cssText = "#block_ajax_marking_html_list { display: none; }";
    } else {
        styleElement.appendChild(document.createTextNode("#block_ajax_marking_html_list {display: none;}"));
    }
    document.getElementsByTagName("head")[0].appendChild(styleElement);
}

/**
 * The initialising stuff to get everything started
 * 
 * @return void
 */
M.block_ajax_marking.initialise = function() {
    
    // workaround for odd https setups. Probably not needed in most cases
    if (document.location.toString().indexOf('https://') != -1) {
        M.cfg.wwwroot = M.cfg.wwwroot.replace('http:', 'https:');
    }
    
    // the context menu needs this for the skin to show up, as do other bits
    // YAHOO.util.Dom.addClass(document.body, 'yui-skin-sam');
    
    // Make total message:
    //label = document.createTextNode(M.str.block_ajax_marking.totaltomark+': ');
    //var total = document.getElementById('totalmessage');
    // M.block_ajax_marking.remove_all_child_nodes(total);
    //total.appendChild(label);
    
    M.block_ajax_marking.tree = M.block_ajax_marking.tree_factory('main');

    M.block_ajax_marking.tree.build_ajax_tree();
    
    // Make the 
    
    // Make the footer
    if (!document.getElementById('block_ajax_marking_collapse')) {
        M.block_ajax_marking.make_footer();
    }
}
<?php
// Get the IDE to do proper script highlighting
if(0) { ?></script><?php } 


// We need to append all of the plugin specific javascript. This file will be requested as part of a 
// separate http request after the PHP has all been finished with, so we do this cheaply to keep 
// overheads low by not using setup.php and having the js in static functions.

if (!defined('MOODLE_INTERNAL')) { // necessary because class files are expecting it
    define('MOODLE_INTERNAL', true);
}

$moduledir = opendir(dirname(__FILE__).'/modules');

if ($moduledir) {
    
    // We never instantiate the classes, but it complains if it can't find the base class
    //require_once(dirname(__FILE__).'/classes/module_base.class.php');
    
    // Loop through the module files, including each one, then echoing the extra javascript from it
    while (($moddir = readdir($moduledir)) !== false) {
        
        // Ignore any that don't fit the pattern, like . and ..
        if (preg_match('/^([a-z]*)$/', $moddir, $matches)) {
            require_once(dirname(__FILE__).'/modules/'.$moddir.'/'.$moddir.'.js');
            
//            $modclassname = 'block_ajax_marking_'.$matches[1];
//            echo "\n\n// Including extra javascript for the ".$matches[1]." module\n\n";
//            echo $modclassname::extra_javascript();
        }
        
    }
    
    closedir($moduledir);
}


?>