<?php if(0) { ?><script><?php } // Get the IDE to do proper script highlighting for the javascript
// put at the top so we get the right line numbers in firebug
?>

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
 * Used by the factory method to set any specific overrides for this tree from the module plugins.
 * e.g. instead of coursemodule node (quiz) -> quiz submissions, we can have coursemodule node (quiz) 
 * -> quiz questions -> quiz submissions
 *
 * @param string modulename
 * @param object override in the form of name : value pairs, both strings.
 */
//M.block_ajax_marking.tree_base.prototype.setmoduleoverride = function(modulename, override) {
//    
//    if (typeof(this.moduleoverrides) === 'undefined') {
//        this.moduleoverrides = [];
//    }
//    
//    this.moduleoverrides[modulename] = override;
//}

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
            if (typeof(filtername) !== 'undefined') { // TODO no need for the callbackfunction check once sorted out
                currentfilter = filtername;
                break; // should only be one of them
            }
        }
        // Some nodes won't be specific to a module, but this needs to be specified to avoid silent errors
        modulename = (typeof(nodedata.display.modulename) !== 'undefined') ? nodedata.display.modulename : false;
        nodedata.returndata.nextnodefilter = this.nextnodetype(currentfilter, modulename);
        
        // Add a count if we have more than one thing or we're not at the final node e.g. student name
        if (nodedata.display.count > 1 || nodedata.returndata.nextnodefilter !== false) {
            nodedata.label = '(<span class="AMB_count">'+nodedata.display.count+'</span>) '+nodedata.label;
        } 
        
        newnode = new YAHOO.widget.TextNode(nodedata, M.block_ajax_marking.parentnodeholder, false);

        // set the node to load data dynamically, unless it has not sent a callback i.e. it's a final node
        
        if (typeof(nodedata.returndata.nextnodefilter) !== 'undefined' && 
            nodedata.returndata.nextnodefilter !== false) {
            
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
    
    this.render();
    // finally, run the function that updates the original node and adds the children. Won't be there
    // if we have just built the tree
    if (typeof(M.block_ajax_marking.oncompletefunctionholder) === 'function') {
        // Take care - this will be executed in the wrong scope if not careful. it needs this to be the
        // tree
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
 * This function is called when a node is clicked (expanded) and makes the ajax request. It sends the 
 * filters from all parent nodes and the nextnodetype
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
    
    // The callback function is the SQL GROUP BY for the next set of nodes, so this is separate
    var nodefilters = M.block_ajax_marking.getnodefilters(clickednode);
    nodefilters.push('nextnodefilter='+clickednode.data.returndata.nextnodefilter);
    nodefilters = nodefilters.join('&');
    
    // Get all of the other filters from parent nodes
//    var nextparentnode = clickednode;
//    while (!nextparentnode.isRoot()) {
//        // Add the other item
//        for (var varname in nextparentnode.data.returndata) {
//            // Add all the non-callbackfunction stuff e.g. courseid so we can use it to filter the unmarked work
//            if (varname !== 'nextnodefilter' && nextparentnode.data.returndata[varname] != '') {
//                postdata.push(varname + '=' + nextparentnode.data.returndata[varname]); 
//            }      
//        }
//        nextparentnode = nextparentnode.parent;
//    }
    
//    postdata = postdata.join('&');
    YAHOO.util.Connect.asyncRequest('POST', M.block_ajax_marking.ajaxnodesurl, block_ajax_marking_callback, nodefilters);
};

/**
 * function to update the parent node when anything about its children changes. It recalculates the
 * total count and displays it, then recurses to the next node up until it hits root, when it updates 
 * the total count and stops
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

        var nextnodeup = parentnodetoupdate.parent; // get this before the node is (possibly) destroyed
        // Dump any nodes with no children, but don't dump the root node - we want to be able to refresh it
        if (nodechildrenlength === 0) {
            this.removeNode(parentnodetoupdate, true);
        } else { // Update the node with its new total
            var newlabel = '(<span class="AMB_count">'+nodecount+'</span>) '+parentnodetoupdate.data.display.name;
            parentnodetoupdate.data.display.count = nodecount;
            parentnodetoupdate.label = newlabel
        }

        this.update_parent_node(nextnodeup);
    }
    
};


/**
 * OnClick handler for the nodes of the tree. Attached to the root node in order to catch all events via
 * bubbling. Deals with making the marking popup appear.
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
    nodefilters.push('node='+node.index);
    popupurl += nodefilters.join('&');
    
    // AJAX version
//    M.block_ajax_marking.show_modal_grading_interface(postdata);

    // Pop-up version
    mbam.popupholder = window.open(popupurl, 'ajax_marking_popup', popupargs);
    mbam.popupholder.focus();
    
    return false;
};

/**
 * Rcursive function to get the return data from this node and all its parents. Each parent represents
 * a filter e.g. 'only this course', so we need to specify the id numbers for the SQL
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
            // Add all the non-callbackfunction stuff e.g. courseid so we can use it to filter the unmarked work
            if (varname != 'nextnodefilter' && nextparentnode.data.returndata[varname] != '') {
                nodefilters.push(varname + '=' + nextparentnode.data.returndata[varname]); 
            }      
        }
        nextparentnode = nextparentnode.parent;
    }
    return nodefilters;
}


//M.block_ajax_marking.configonclick = function(clickargumentsobject) {
//
//    var ajaxdata  = '';
//
//    // function to make checkboxes for each of the three main options
//    function make_box(value, id, label) {
//
//        try{
//            box = document.createElement('<input type="radio" name="display" />');
//        }catch(error){
//            box = document.createElement('input');
//        }
//        box.setAttribute('type','radio');
//        box.setAttribute('name','display');
//        box.value = value;
//        box.id    = id;
//
//        box.onclick = function() {
//            M.block_ajax_marking.request_config_checkbox_data(this);
//        };
//        formDiv.appendChild(box);
//
//        var boxText = document.createTextNode(label);
//        formDiv.appendChild(boxText);
//
//        var breaker = document.createElement('br');
//        formDiv.appendChild(breaker);
//    }
//
//    // remove group nodes from the previous item if they are there.
//    M.block_ajax_marking.remove_config_right_panel_contents();
//
//    var title = document.getElementById('configInstructions');
//    title.innerHTML = clickargumentsobject.node.data.icon+clickargumentsobject.node.data.name;
//
//
//    var formDiv = document.getElementById('configshowform');
//    // grey out the form before ajax call - it will be un-greyed later
//    formDiv.style.color = '#AAA';
//
//    // add hidden variables so they can be used for the later AJAX calls
//    // If it's a course, we send things a bit differently
//
//    var hiddeninput1   = document.createElement('input');
//    hiddeninput1.type  = 'hidden';
//    hiddeninput1.name  = 'course';
//    hiddeninput1.value = (clickargumentsobject.node.data.type == 'config_course') ? clickargumentsobject.node.data.id : clickargumentsobject.node.parent.data.id;
//    formDiv.appendChild(hiddeninput1);
//
//    var hidden2       = document.createElement('input');
//        hidden2.type  = 'hidden';
//        hidden2.name  = 'assessment';
//        hidden2.value = clickargumentsobject.node.data.id;
//    formDiv.appendChild(hidden2);
//
//    var hidden3   = document.createElement('input');
//        hidden3.type  = 'hidden';
//        hidden3.name  = 'assessmenttype';
//        hidden3.value = (clickargumentsobject.node.data.type == 'config_course') ? 'course' : clickargumentsobject.node.data.type;
//    formDiv.appendChild(hidden3);
//
//    // For non courses, add a default checkbox that will remove the record
//    if (clickargumentsobject.node.data.type != 'config_course') {
//        M.block_ajax_marking.make_box('default', 'config0', M.str.block_ajax_marking.coursedefault, formDiv);
//        // make the three main checkboxes, appending them to the form as we go along
//        M.block_ajax_marking.make_box('display',    'config1', M.str.block_ajax_marking.showthisassessment, formDiv);
//        M.block_ajax_marking.make_box('groups',  'config2', M.str.block_ajax_marking.showwithgroups, formDiv);
//        M.block_ajax_marking.make_box('hide',    'config3', M.str.block_ajax_marking.hidethisassessment, formDiv);
//
//    } else {
//        M.block_ajax_marking.make_box('display',    'config1', M.str.block_ajax_marking.showthiscourse, formDiv);
//        M.block_ajax_marking.make_box('groups',  'config2', M.str.block_ajax_marking.showwithgroups, formDiv);
//        M.block_ajax_marking.make_box('hide',    'config3', M.str.block_ajax_marking.hidethiscourse, formDiv);
//    }
//
//    // now, we need to find out what the current group mode is and display that box as checked.
//    if (clickargumentsobject.node.data.type !== 'config_course') {
//        ajaxdata += 'courseid='       +clickargumentsobject.node.parent.data.id;
//        ajaxdata += '&assessmenttype='+clickargumentsobject.node.data.type;
//        ajaxdata += '&assessmentid='  +clickargumentsobject.node.data.id;
//        ajaxdata += '&type=config_check';
//    } else {
//        ajaxdata += 'courseid='             +clickargumentsobject.node.data.id;
//        ajaxdata += '&assessmenttype=course';
//        ajaxdata += '&type=config_check';
//    }
//
//    var request = YAHOO.util.Connect.asyncRequest('POST', M.block_ajax_marking.ajaxnodesurl, block_ajax_marking_callback, ajaxdata);
//
//    return true;
//};

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
//M.block_ajax_marking.tree_base.prototype.build_group_nodes = function(ajaxresponsearray) {
//    // need to turn the groups for this course into an array and attach it to the course
//    // node. Then make the groups bit on screen
//    // for the config screen??
//
//    var arrayLength = ajaxresponsearray.length;
//    var tempnode = '';
//
//    for (var n =0; n<arrayLength; n++) {
//
//        tempnode = new YAHOO.widget.TextNode(ajaxresponsearray[n], M.block_ajax_marking.parentnodeholder, false);
//        tempnode.labelStyle = 'icon-group';
//
//        // if the groups are for journals, it is impossible to display individuals, so we make the
//        // node clickable so that the pop up will have the group screen.
//        // TODO make this into a dynamic thing based on another attribute of the data object
//        if (typeof(node.data.callbackfunction) !== 'undefined') {
//            tempnode.setDynamicLoad(this.request_node_data);
//        }
//    }
//
//    this.update_parent_node(M.block_ajax_marking.parentnodeholder);
//    M.block_ajax_marking.oncompletefunctionholder();
//};


/**
 * Builds the tree when the block is loaded, or refresh is clicked
 * 
 * @return void
 */
M.block_ajax_marking.tree_base.prototype.initialise = function() {
    
    // Get rid of the existing tree nodes first (if there are any), but don't re-render to avoid flicker
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
    YAHOO.util.Connect.asyncRequest('POST', M.block_ajax_marking.ajaxnodesurl, block_ajax_marking_callback, this.initial_nodes_data);
    
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
    
    var nextnodefilter = false; // if nothing else is found, make the node into a final one with no children

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
    
//    if (typeof(currentfilter) === 'undefined') {
//        return 'courseid'; // default for the root node
//    }
    
    var nextnodefilter = false; // if nothing else is found, make the node into a final one with no children
//    var currentfilter = 'root';
    
    // Get the name of the current filter. Assumes there will only be 1
//    for (var filtername in currentnode.data.returndata) {
//        if (typeof(filtername) !== 'undefined') { // TODO no need for the callbackfunction check once sorted out
//            currentfilter = filtername;
//            break; // should only be one of them
//        }
//    }
    
    // these are the standard progressions of nodes in the basic trees. Modules may wish to modify these
    // e.g. by adding extra nodes, stopping early without showing individual students, or by allowing
    // the user to choose a different order.
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
        
//        case 'root':
//            return 'courseid'; // no overrides allowed - keep it simple
            
        case 'courseid':
            return 'coursemoduleid';
            
        case 'userid': // the submissions nodes in the course tree
            return false;
            
        case 'coursemoduleid': 
            nextnodefilter = 'userid';
            // fall through because we need to offer the option to alter things after coursemoduleid.
            
        default:
            // any special nodes that came back from a module addition
            
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
    }
        
    // TODO - these are inconsistent. Some refer to where the request
    // is triggered and some to what it creates.

//    switch (payload) {

//        case 'config_main_tree':
//
//            mbam.tree.build_course_nodes(nodesarray);
//            break;
//
//        case 'config_course':
//
//            mbam.tree.build_assessment_nodes(nodesarray);
//            break;
//
//        case 'config_groups':
//
//            // called when the groups settings have been updated.
//
//            // TODO - only change the select value, don't totally re build them
//            mbam.make_config_groups_list(nodesarray);
//            break;
//
//        case 'config_set':
//
//            //just need to un-disable the radio button
//
//            if (ajaxresponsearray.nodes[0].value === false) {
//                label = document.createTextNode(mbam.variables.nogradedassessments);
//                mbam.remove_all_child_nodes(mbam.tree.status);
//                mbam.tree.status.appendChild(label);
//            } else {
//                mbam.enable_config_radio_buttons();
//            }
//            break;
//
//        case 'config_check':
//            // called when any data about config comes back after a request (not a data
//            // setting request)
//
//            // make the id of the checkbox div
//            var checkboxid = 'config'+nodesarray[0].value;
//
//            // make the radio button on screen match the value in the database that was just 
//            // returned.
//            document.getElementById(checkboxid).checked = true;
//
//            // if its set to 'display by groups', make the groups bit underneath
//            if (ajaxresponsearray.nodes[0].value == 2) {
//                // remove the config bit leaving just the groups, which were tacked onto the
//                // end of the returned array
//                ajaxresponsearray.nodes.shift();
//                //make the groups bit
//                mbam.make_config_groups_list(nodesarray);
//            }
//            //allow the radio buttons to be clicked again
//            mbam.enable_config_radio_buttons();
//            break;
//
//        case 'config_group_save':
//
//            if (ajaxresponsearray.nodes[0].value === false) {
//                mbam.tree.status.innerHTML = 'AJAX error';
//            } else {
//                mbam.enable_config_radio_buttons();
//            }
//
//            break;

    if (typeof(ajaxresponsearray['gradinginterface']) !== 'undefined') {
        // We have gotten the grading form back. Need to add the HTML to the modal overlay

        M.block_ajax_marking.gradinginterface.setHeader(''); 
        M.block_ajax_marking.gradinginterface.setBody(ajaxresponsearray.content); 
    } else if (typeof(ajaxresponsearray['nodes']) !== 'undefined') {

//        mbam.trees.current.build_nodes(ajaxresponsearray.nodes);
//        var currenttab = M.block_ajax_marking.tabview.get('selection');
        M.block_ajax_marking.get_current_tab().displaywidget.build_nodes(ajaxresponsearray.nodes);

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
        document.getElementById('status').innerHTML =  M.str.block_ajax_marking.collapseString;
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
 * remove_node_from_tree() or by build_nodes
 * 
 * @param treeobject the tree to be refreshed
 * @return void
 */
//M.block_ajax_marking.tree_base.prototype.refresh_tree_after_changes = function() {
//    
//    this.render();
//    this.root.refresh();
//
//    // If there are no nodes left, we need to remove the tree altogether
//    if (this.root.children.length === 0) {
//        // Need this to be just show/hide, not destroy
//        M.block_ajax_marking.remove_all_child_nodes(document.getElementById("totalmessage"));
//        M.block_ajax_marking.remove_all_child_nodes(document.getElementById("count"));
//    }
//};

/**
* Refresh tree function - for Collapse & refresh link in the main block
* 
* @param treeobject the YUI treeview object to refresh (config or main)
* @return void
*/
//M.block_ajax_marking.tree_base.prototype.refresh_tree = function() {
//
//    // Get rid of the existing tree nodes first, but don't re-render to avoid flicker
//    var rootnode = this.getRoot();
//    var numberofnodes = rootnode.children.length;
//    for (var i = 0; i < numberofnodes; i++) {
//        this.removeNode(rootnode.children[0], true);
//    }
//    
//    // Reload the data for the root node. We keep the tree object intect rather than destroying
//    // and recreating in order to improve responsiveness.
//    M.block_ajax_marking.parentnodeholder = rootnode;
//    M.block_ajax_marking.oncompletefunctionholder = 'rootnode.loadcomplete';
//    this.initialise();
//    
//};

/**
 * Makes a list of groups as checkboxes and appends them to the config div next to the config
 * tree. Called when the 'show by groups' check box is selected for a node.
 * 
 * @param data the list of groups for this assessment returned from the ajax call
 * @return void
 */
//M.block_ajax_marking.make_config_groups_list = function(data) {
//
//    varconfiggroupsdiv = document.getElementById('configgroupsdiv');
//    M.block_ajax_marking.remove_all_child_nodes(groupsdiv);
//
//    // Closure holding onclick function.
//    var config_checkbox_onclick = function() {
//        M.block_ajax_marking.config_checkbox_onclick();
//    };
//    
//    // Continue the numbering of the ids from 4 (main checkboxes are 1-3). This allows us to
//    // disable/enable them
//    var idcounter = 4;
//    
//    var datalength = data.length;
//    
//    if (datalength === 0) {
//        var emptylabel = document.createTextNode(M.str.block_ajax_marking.nogroups);
//        configgroupsdiv.appendChild(emptylabel);
//    }
//    
//    for (var v = 0; v < datalength; v++) {
//
//        var checkbox = '';
//        
//        try {
//            checkbox = document.createElement('<input type="checkbox" name="display" />');
//        } catch(err) {
//            checkbox = document.createElement('input');
//        }
//        
//        checkbox.setAttribute('type','checkbox');
//        checkbox.setAttribute('name','groups');
//        checkbox.id = 'config'+idcounter;
//        checkbox.value = data[v].id;
//        configgroupsdiv.appendChild(checkbox);
//
//        if (data[v].display == 'true') {
//            checkbox.checked = true;
//        } else {
//            checkbox.checked = false;
//        }
//        checkbox.onclick = config_checkbox_onclick;
//
//        var label = document.createTextNode(data[v].name);
//        configgroupsdiv.appendChild(label);
//
//        var breaker = document.createElement('br');
//        configgroupsdiv.appendChild(breaker);
//        
//        idcounter++;
//    }
//    
//    // remove the ajax loader icon and re-enable the radio buttons
//    M.block_ajax_marking.tree.icon.removeAttribute('class', 'loaderimage');
//    M.block_ajax_marking.tree.icon.removeAttribute('className', 'loaderimage');
//    M.block_ajax_marking.enable_config_radio_buttons();
//};

M.block_ajax_marking.ajax_timeout_handler = function(o) {
    // Do something sensible
   
};

/**
 * on click function for the groups check boxes on the config screen. clicking sets or unsets
 * a particular group for display.
 * 
 * @return void
 */
//M.block_ajax_marking.config_checkbox_onclick = function() {
//
//    var form = document.getElementById('configshowform');
//
//    M.block_ajax_marking.disable_config_radio_buttons();
//
//    // hacky IE6 compatible fix
//    for (var c = 0; c < form.childNodes.length; c++) {
//        
//        switch (form.childNodes[c].name) {
//            
//            case 'course':
//                var course = form.childNodes[c].value;
//                break;
//                
//            case 'assessmenttype':
//                var assessmentType = form.childNodes[c].value;
//                break;
//                
//            case 'assessment':
//                var assessment = form.childNodes[c].value;
//                break;
//        }
//    }
//
//    // need to construct a space separated list of group ids.
//    var groupids = '';
//    var configgroupsdiv = document.getElementById('configgroupsdiv');
//    var groups = configgroupsdiv.getElementsByTagName('input');
//    var groupslength = groups.length;
//
//    for (var a = 0; a < groupslength; a++) {
//        
//        if (groups[a].checked === true) {
//            groupids += groups[a].value+' ';
//        }
//    }
//    // there are no checked boxes
//    if (groupids === '') {
//        // Don't leave the db field empty as it will cause confusion between no groups chosen
//        // and first time we set this.
//        groupids = 'none';
//    }
//
//    var postdata = 'id='+course+'&assessmenttype='+assessmentType+'&assessmentid='+assessment
//                   +'&type=config_group_save&userid='+M.str.block_ajax_marking.userid
//                   +'&display=2&groups='+groupids;
//
//    var request = YAHOO.util.Connect.asyncRequest('POST', M.block_ajax_marking.ajaxnodesurl, block_ajax_marking_callback, postdata);
//};

/**
 * function that waits till the pop up has a particular location,
 * i.e. the one it gets to when the data has been saved, and then shuts it.
 * 
 * @param urltoclose the url to wait for, which is normally the 'data has been saved' page
 * @return void
 */
//M.block_ajax_marking.popup_closing_timer = function (urltoclose) {
//
//    if (!M.block_ajax_marking.popupholder.closed) {
//
//        if (M.block_ajax_marking.popupholder.location.href == M.cfg.wwwroot+urltoclose) {
//
//            M.block_ajax_marking.popupholder.close();
//            delete  M.block_ajax_marking.popupholder;
//
//        } else {
//
//            setTimeout(M.block_ajax_marking.popup_closing_timer(urltoclose), 1000);
//        }
//    }
//};

/**
 * IE seems not to want to expand the block when the tree becomes wider.
 * This provides a one-time resizing so that it is a bit bigger
 * 
 * @return void
 */
//M.block_ajax_marking.adjust_width_for_ie = function () {
//    if (/MSIE (\d+\.\d+);/.test(navigator.userAgent)){
//
//        var treediv = document.getElementById('treediv');
//        var width = treediv.offsetWidth;
//        // set width of main content div to the same as treediv
//        var contentdiv = treediv.parentNode;
//        contentdiv.style.width = width;
//    }
//};

/**
 * The panel for the config tree and the pop ups is the same and is created
 * here if it doesn't exist yet
 * 
 * @return void
 */
//M.block_ajax_marking.initialise_config_panel = function () {
//    M.block_ajax_marking.configoverlaypanel = new YAHOO.widget.Panel(
//            'configoverlaypanel',
//            {
//                width       : '470px',
//                height      : '530px',
//                fixedcenter : true,
//                close       : true,
//                draggable   : false,
//                zindex      : 110,
//                modal       : true,
//                visible     : false,
//                iframe      : true
//            }
//        );
//};

/**
 * Builds the greyed out overlay and panel for the config overlay
 * 
 * @return void
 */
//M.block_ajax_marking.build_config_overlay = function() {
//
//    var mbam = M.block_ajax_marking;
//
//    if (!mbam.configoverlaypanel) {
//
//        mbam.initialise_config_panel();
//
//        var settingsheadertext = mbam.variables.settingsheadertext+' '+mbam.variables.fullname;
//        mbam.configoverlaypanel.setHeader(settingsheadertext);
//
//        // TODO use yui layout widget for this
//        var bodytext = "<div id='configicon' class='block_ajax_marking_hidden'></div>"
//        		     + "<div id='configstatus'></div>"
//        		     + "<div id='configtree'></div>"
//        		     + "<div id='configSettings'>"
//                         + "<div id='configInstructions'>"+mbam.variables.instructions+"</div>"
//                         + "<div id='configCheckboxes'>"
//                         		+ "<form id='configshowform' name='configshowform'></form>"
//                         + "</div>"
//                         + "<div id='configgroupsdiv'></div>"
//                     + "</div>";
//
//        mbam.configoverlaypanel.setBody(bodytext);
//
//        mbam.configoverlaypanel.beforeHideEvent.subscribe(function() {
//            mbam.refresh_tree(mbam.tree);
//        });
//
//        mbam.configoverlaypanel.render(document.body);
//        mbam.configoverlaypanel.show();
//        
//        // Now that the grey overlay is in place with all the divs ready, we build the config tree
//        if (typeof (mbam.tree) != 'object') {
//            mbam.tree = mbam.tree_factory('config');
//            mbam.tree.build_ajax_tree();
//        }
//        
//        YAHOO.util.Dom.addClass(mbam.tree.icon, 'loaderimage');
//
//    } else {
//        // It's all there from earlier, so just show it
//        mbam.configoverlaypanel.show();
//        mbam.remove_config_right_panel_contents();
//        mbam.refresh_tree(mbam.tree);
//    }
//};

//M.block_ajax_marking.config_checkbox_ = function(show) {
//
//
//};

/**
 * the onclick function for the radio buttons in the config screen. If show by group is clicked, 
 * the groups checkboxes appear below. If another one is, the groups thing is hidden.
 * 
 * @param checkbox the box that was clicked
 * @return void
 */
//M.block_ajax_marking.request_config_checkbox_data = function(checkbox) {
//
//    //TODO did these changes work?
//    
//    var show = '';
//
//    var form = document.getElementById('configshowform');
//    
//    // use proper constants here for database values?
//    switch (checkbox.value) {
//
//        case 'default':
//
//            display = 0;
//            break;
//
//        case 'show':
//
//            display = 1;
//            break;
//            
//        case 'groups':
//            
//            display = 2;
//            break;
//            
//        case 'hide':
//        
//            display = 3;
//            break; 
//    }
//
//    //empty the groups area in case there were groups there last time
//    M.block_ajax_marking.remove_all_child_nodes(document.getElementById('configgroupsdiv'));
//    
//    //Construct the url and data, with variations depending on whether it's option 2 (where groups
//    // need to be requested to make the checkboxes) or not
//    var postData   = 'userid='+M.str.block_ajax_marking.userid;
//        postData  += '&assessmenttype='+form.elements['assessmenttype'].value;
//        postData  += '&assessmentid='+form.elements['assessment'].value;
//        postData  += '&display='+display;
//    
//    var nongroupvalues = '0 1 3';
//    
//    if (nongroupvalues.search(show) == -1) {
//        // submit show value and do groups stuff
//        postData  += '&id='+form.elements['course'].value;
//        postData  += '&type=config_groups';
//        
//    } else {
//        // just submit the new show value
//        postData += '&id='+form.elements['assessment'].value;
//        postData += '&type=config_set';
//    }
//    
//    M.block_ajax_marking.disable_config_radio_buttons();
//    
//    YAHOO.util.Connect.asyncRequest('POST', M.block_ajax_marking.ajaxnodesurl, block_ajax_marking_callback, postData);
//};

///**
// * Wipes all the config and group options away when another node or a course node is clicked in 
// * the config tree
// * 
// * @return void
// */
//M.block_ajax_marking.remove_config_right_panel_contents = function() {
//
//    M.block_ajax_marking.remove_all_child_nodes(document.getElementById('configshowform'));
//    M.block_ajax_marking.remove_all_child_nodes(document.getElementById('configInstructions'));
//    M.block_ajax_marking.remove_all_child_nodes(document.getElementById('configgroupsdiv'));
//    return true;
//};

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
 * This factory function is to instantiate the tree_base class in order to create the
 * main and config trees.
 * 
 * @return object the Yahoo treeview object
 */
//M.block_ajax_marking.tree_factory = function (type) {
//
//    var treeobject = '';
//
//    switch (type) {
//        
//        case 'courses':
//            treeobject                  = new M.block_ajax_marking.courses_tree();
//            break;
//
//        case 'config':
//            treeobject                  = new M.block_ajax_marking.tree_base('configtree');
//            treeobject.icon             = document.getElementById('configicon');
//            treeobject.course_post_data = 'id='+M.str.block_ajax_marking.userid+'&type=config_main_tree&userid=';
//            treeobject.course_post_data += M.str.block_ajax_marking.userid;
//            break;
//            
//        case 'groups':
//            treeobject                  = new M.block_ajax_marking.cohorts_tree();
//            break;
//            
//        case 'users':
//            treeobject                  = new M.block_ajax_marking.users_tree();
//            break;
//    }
//    
//    return treeobject;
//};


//M.block_ajax_marking.show_modal_grading_interface = function(postdata) {
//    
//    // Make the overlay 
//    // Initialize the temporary Panel to display while waiting for external content to load 
//    if (typeof(M.block_ajax_marking.gradinginterface) === 'undefined') {
//                    
//        M.block_ajax_marking.gradinginterface =  
//            new YAHOO.widget.Panel('gradinginterface',   
//                { width:"600px",  
//                  fixedcenter:true,  
//                  close:true,  
//                  draggable:false,  
//                  zindex:4, 
//                  modal:true, 
//                  visible:false 
//                }  
//            ); 
//    }
//	 
//    // display it with a loading icon
//    M.block_ajax_marking.gradinginterface.setHeader("Loading, please wait..."); 
//	M.block_ajax_marking.gradinginterface.setBody('<img src="'+M.cfg.wwwroot+'/blocks/ajax_marking/images/ajax-loader.gif" />'); 
//	M.block_ajax_marking.gradinginterface.render(document.body); 
//    M.block_ajax_marking.gradinginterface.cfg.setProperty('visible', true);
//    // display it with a loading icon
//    
//    // send off the ajax request for it's contents
////    var postdata = clickednode.data.returndata.join();
//    postdata += '&sesskey='+M.cfg.sesskey;
//
//    YAHOO.util.Connect.asyncRequest('POST', M.block_ajax_marking.ajaxgradingurl, block_ajax_marking_callback, postdata);
//    
//    // store the target url so we can use it for adding the ajax submit listener.
//    M.block_ajax_marking.gradingholder = postdata;
//    
//}

//M.block_ajax_marking.hide_modal_grading_interface = function(url) {
//    M.block_ajax_marking.gradinginterface.cfg.setProperty('visible', false);
//    
//}


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
            'content':'<div id="coursestree">course tree goes here</div>'});
        M.block_ajax_marking.tabview.add(coursestab);
        coursestab.displaywidget = new M.block_ajax_marking.courses_tree();
        
        var cohortstab = new Y.Tab({
            'label':'Cohorts',
            'content':'<div id="cohortstree">Still in beta for 2.0 - groups tree will go here once done</div>'});
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

        
//        var imagehtml = Y.get('coursestabsicons');
//        var coursesicon = '';
//        var cohortssicon = '';
//        var userssicon = '';
//        M.block_ajax_marking.tabview.item(0).set('label', '<img src="http://moodle20dev.localhost:8888/theme/image.php?theme=fusion&image=c%2Fcourse" alt="Courses" />'); // works
        
        
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
    
    // TODO use cookies/session to store the one the user wants between sessions
    M.block_ajax_marking.tabview.selectChild(0); // this will initialise the courses tree
    
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