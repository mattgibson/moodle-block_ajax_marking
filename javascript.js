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
 * @package   block-ajax_marking
 * @copyright 2008-2010 Matt Gibson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

YAHOO.namespace('ajax_marking_block');

// used to deterine whether to log everything to console
//const debugdeveloper = 38911;
//const debugall       = 6143;

//this holds the parent node so it can be referenced by other functions                                                    
YAHOO.ajax_marking_block.parentnodeholder = '';
// this holds the callback function of the parent node so it can be called once all the child
// nodes have been built
YAHOO.ajax_marking_block.oncompletefunctionholder = '';
// this is the variable used by the openPopup function on the front page. 
YAHOO.ajax_marking_block.popupholder = '';
// this holds the timer that keeps trying to add the onclick stuff to the pop ups as the pop up loads
YAHOO.ajax_marking_block.popuptimer = '';


/**
 * Base class that can be used for the main and config trees. This extends the
 * YUI treeview class ready to add some new functions to it which are common to both the
 * main and config trees.
 */
YAHOO.ajax_marking_block.tree_base = function(treediv) {

    YAHOO.ajax_marking_block.tree_base.superclass.constructor.call(this, treediv);
};

// make the base class into a subclass of the YUI treeview widget
YAHOO.lang.extend(YAHOO.ajax_marking_block.tree_base, YAHOO.widget.TreeView);

/**
 * function to build the assessment nodes once the AJAX request has returned a data object
 * 
 * @param array nodes_array the nodes to be rendered
 */
YAHOO.ajax_marking_block.tree_base.prototype.build_assessment_nodes = function(nodesarray) {

    var tempnode = '';
    
    // cycle through the array and make the nodes
    var  nodeslength = nodesarray.length;
    
    for (var m=0; m<nodeslength; m++) {

        // use the object to create a new node
        tempnode = new YAHOO.widget.TextNode(nodesarray[m], YAHOO.ajax_marking_block.parentnodeholder , false);

        // set the node to load data dynamically, unless it is marked as not dynamic e.g. journal
        if ((!this.config) && (nodesarray[m].dynamic == 'true')) {
           tempnode.setDynamicLoad(this.request_node_data);
        }
    }

    // finally, run the function that updates the original node and adds the children
    YAHOO.ajax_marking_block.oncompletefunctionholder();

    // the main tree will need the counts updated
    if (!this.config) {
        this.update_parent_node(YAHOO.ajax_marking_block.parentnodeholder);
    }

};

/**
 * This function is called when a node is clicked (expanded) and makes the ajax request
 * 
 * @param object clicked_node
 * @param string onCompleteCallback
 */
YAHOO.ajax_marking_block.tree_base.prototype.request_node_data = function(clickednode, callbackfunction) {

    // store details of the node that has been clicked for reference by later
    // callback function
    YAHOO.ajax_marking_block.parentnodeholder = clickednode;
    YAHOO.ajax_marking_block.oncompletefunctionholder = callbackfunction;
    
    // request data using AJAX
    var requesturl = YAHOO.ajax_marking_block.variables.wwwroot+'/blocks/ajax_marking/ajax.php';
    var postdata = 'id='+clickednode.data.id+'&type='+clickednode.data.type
                   +'&userid='+YAHOO.ajax_marking_block.variables.userid;

    if (typeof(clickednode.data.group) != 'undefined') {
        //add group id if its there
        postdata += '&group='+clickednode.data.group;
    }

    // Allow modules to add extra arguments to the AJAX request if necessary
    var typearray = clickednode.data.type.split('_');
    // TODO eval is evil - can this work? not tried
    var type_object = eval('YAHOO.ajax_marking_block.'+typearray[0]);
    //var type_object = YAHOO.ajax_marking_block[typearray[0]];

    
    if ((typeof(type_object) != 'undefined') && (typeof(type_object.extra_ajax_request_arguments) != 'undefined')) {
        postdata += type_object.extra_ajax_request_arguments(clickednode);
    }
 
    var request = YAHOO.util.Connect.asyncRequest('POST', requesturl, ajax_marking_block_callback, postdata);
};

/**
 * function to update the parent assessment node when it is refreshed dynamically so that
 * if more work has been found, or a piece has now been marked, the count for that label will be
 * accurate along with the counts of all its parent nodes and the total count.
 * 
 * @param object parentnodetoupdate the node of the treeview object to alter the count of
 * @return void
 */
YAHOO.ajax_marking_block.tree_base.prototype.update_parent_node = function(parentnodetoupdate) {

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
    
            for (var i = 0; i < nodechildrenlength; i++) {
                childcount = parentnodetoupdate.children[i].data.count;
                runningtotal += parseInt(childcount, 10);
            }
    
            this.update_node_count(parentnodetoupdate, runningtotal);
        }
        // move up one level so that the change propagates to the whole tree recursively
        this.update_parent_node(nextnodeup);   
    }
};

/**
 * function to alter a node's label with a new count once the children are removed or reloaded
 * 
 * @param object newnode the node of the tree whose count we wish to change
 * @param int newcount the new number of items to display
 * @return void
 */
YAHOO.ajax_marking_block.tree_base.prototype.update_node_count = function(newnode, newcount) {

    var newlabel       = newnode.data.icon+'(<span class="AMB_count">'+newcount+'</span>) '+newnode.data.name;
    newnode.data.count = newcount;
    newnode.label      = newlabel;
};

/**
 * Creates the initial nodes for both the main block tree or configuration tree.
 */
YAHOO.ajax_marking_block.tree_base.prototype.build_course_nodes = function(nodesarray) {

    var label = '';

    // store the array of nodes length so that loops are slightly faster
    var nodeslength = nodesarray.length;
    
    // if the array is empty, say that there is nothing to mark
    if (nodeslength === 0) {

        if (this.config) {
            label = document.createTextNode(YAHOO.ajax_marking_block.variables.nogradedassessments);
        } else {
            label = document.createTextNode(YAHOO.ajax_marking_block.variables.nothingtomark);
        }
        message_div = document.createElement('div');
        messagediv.appendChild(label);
        this.div.appendChild(messagediv);
        this.icon.removeAttribute('class', 'loaderimage');
        this.icon.removeAttribute('className', 'loaderimage');
        
        if (!document.getElementById('block_ajax_marking_collapse')) {
            YAHOO.ajax_marking_block.make_footer();
        }

    } else {
        // there is a tree to be drawn
        
        //Remove any old nodes if this is a refresh
        if (this.root.children.length > 0) {

            this.removeChildren(this.root);
            this.root.refresh();
        }

        // cycle through the array and make the nodes
        for (var n=0; n<nodeslength; n++) {
        	
        	//only show the marking totals if its not a config tree
            if (!this.config) { 
                label = '('+nodesarray[n].count+') '+nodesarray[n].name;
            } else {
                label = nodesarray[n].name;
            }

            var tempnode = new YAHOO.widget.TextNode(nodesarray[n], this.root, false);

            tempnode.labelStyle = 'icon-course';
            tempnode.setDynamicLoad(this.request_node_data);
        }

        // now make the tree, add the total at the top and remove the loading icon
        this.render();

        // get rid of the loading icon - IE6 is rubbish so use 2 methods
        this.icon.removeAttribute('class', 'loaderimage');
        this.icon.removeAttribute('className', 'loaderimage');

        // add onclick events
        // Main tree option first:
        if (!this.config) {

            // Alter total count above tree
            label = document.createTextNode(YAHOO.ajax_marking_block.variables.totaltomark);
            var total = document.getElementById('totalmessage');
            YAHOO.ajax_marking_block.remove_all_child_nodes(total);
            total.appendChild(label);
            this.update_total_count();

            // this function is the listener for the main tree. Event bubbling means that this
            // will catch all node clicks
            this.subscribe(
                "clickEvent",
                function(oArgs) {

                    // ref saves space
                    var node = oArgs.node;
                    
                    // we only need to do anything if the clicked node is one of
                    // the final ones with no children to fetch.
                    if (node.data.dynamic == 'true') {
                        return true;
                    }

                    // putting window.open into the switch statement causes it to fail in IE6.
                    // No idea why.
                    var timer_function = '';
                            
                    // Load the correct javascript object from the files that have been included.
                    // The type attached to the node data should always start with the name of the module, so
                    // we extract that first and then use it to access the object of that
                    // name that was created when the page was built by the inclusion
                    // of all the module_grading.js files.
                    var typearray = node.data.type.split('_');
                    var type = typearray[0];
                    
                    // TODO does this work?
                    // it used to make a string then eval it
                    var module_javascript = YAHOO.ajax_marking_block[type];

                    // Open a pop up with the url and arguments as specified in the module specific object
                    var popupurl = YAHOO.ajax_marking_block.variables.wwwroot+module_javascript.pop_up_opening_url(node);
                    var popupargs = module_javascript.pop_up_arguments(node);
                    YAHOO.ajax_marking_block.popupholder = window.open(popupurl, '_blank', popupargs);

                    // This function will add the module specifi javascript to the pop up. It is necessary
                    // in order to make the pop up update the main tree and close itself once
                    // the work has been graded
                    timer_function = function() {
                        module_javascript.alter_popup(node);
                    };

                    // keep trying to run the function every 2 seconds till it executes (the pop up
                    // takes time to load)
                    // TODO: can an onload event work for a popup like this?
                    YAHOO.ajax_marking_block.popuptimer = window.setInterval(timer_function, 2000);
                    YAHOO.ajax_marking_block.popupholder.focus();                 
                    return true;
                }
            );
            
            // Make the footer divs if they don't exist
            if (!document.getElementById('block_ajax_marking_collapse')) {
                YAHOO.ajax_marking_block.make_footer();
            }

        } else {

            // procedure for config tree nodes:
            // This function is the listener for the config tree that makes the onclick stuff happen
            this.subscribe(
                'clickEvent',
                function(clickargumentsobject) {

                    var ajaxdata  = '';

                    // function to make checkboxes for each of the three main options
                    function make_box(value, id, label) {
                        
                        try{
                            box = document.createElement('<input type="radio" name="showhide" />');
                        }catch(error){
                            box = document.createElement('input');
                        }
                        box.setAttribute('type','radio');
                        box.setAttribute('name','showhide');
                        box.value = value;
                        box.id    = id;
                        
                        box.onclick = function() {
                            YAHOO.ajax_marking_block.request_config_checkbox_data(this);
                        };
                        formDiv.appendChild(box);

                        var boxText = document.createTextNode(label);
                        formDiv.appendChild(boxText);

                        var breaker = document.createElement('br');
                        formDiv.appendChild(breaker);
                    }

                    // remove group nodes from the previous item if they are there.
                    YAHOO.ajax_marking_block.remove_config_right_panel_contents();

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
                        make_box('default', 'config0', YAHOO.ajax_marking_block.variables.coursedefault);
                        // make the three main checkboxes, appending them to the form as we go along
                        make_box('show',    'config1', YAHOO.ajax_marking_block.variables.showthisassessment);
                        make_box('groups',  'config2', YAHOO.ajax_marking_block.variables.showwithgroups);
                        make_box('hide',    'config3', YAHOO.ajax_marking_block.variables.hidethisassessment);

                    } else {
                        make_box('show',    'config1', YAHOO.ajax_marking_block.variables.showthiscourse);
                        make_box('groups',  'config2', YAHOO.ajax_marking_block.variables.showwithgroups);
                        make_box('hide',    'config3', YAHOO.ajax_marking_block.variables.hidethiscourse);
                    }

                    // now, we need to find out what the current group mode is and display that box as checked.
                    var ajaxurl   = YAHOO.ajax_marking_block.variables.wwwroot+'/blocks/ajax_marking/ajax.php';
                    
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
                    
                    var request = YAHOO.util.Connect.asyncRequest('POST', ajaxurl, ajax_marking_block_callback, ajaxdata);

                    return true;
                }
            );
        }
    }
};

/**
 * Make the group nodes for an assessment
 * 
* @param array ajaxresponsearray Takes ajax data array as input
* @retrun void
 */
YAHOO.ajax_marking_block.tree_base.prototype.build_group_nodes = function(ajaxresponsearray) {
    // need to turn the groups for this course into an array and attach it to the course
    // node. Then make the groups bit on screen
    // for the config screen??

    var arrayLength = ajaxresponsearray.length;
    var tempnode = '';

    for (var n =0; n<arrayLength; n++) {

        tempnode = new YAHOO.widget.TextNode(ajaxresponsearray[n], YAHOO.ajax_marking_block.parentnodeholder, false);
        tempnode.labelStyle = 'icon-group';

        // if the groups are for journals, it is impossible to display individuals, so we make the
        // node clickable so that the pop up will have the group screen.
        // TODO make this into a dynamic thing based on another attribute of the data object
        if (ajaxresponsearray[n].type !== 'journal') {
            tempnode.setDynamicLoad(this.request_node_data);
        }
    }

    this.update_parent_node(YAHOO.ajax_marking_block.parentnodeholder);
    YAHOO.ajax_marking_block.oncompletefunctionholder();
};

/**
* Makes the submission nodes for each student with unmarked work. 
* 
* @param array ajaxresponsearray Takes ajax data array as input
* @retrun void
*/
YAHOO.ajax_marking_block.tree_base.prototype.build_submission_nodes = function(ajaxresponsearray) {

    var tempnode = '';
    var arraylength = ajaxresponsearray.length;
    var uniqueid = '';
    var seconds = 0;
    
    for (var k=0; k<arraylength; k++) {

        // set up a unique id so the node can be removed when needed
        uniqueid = ajaxresponsearray[k].type + ajaxresponsearray[k].assessmentid + 'submissionid' + ajaxresponsearray[k].submissionid + '';

        // set up time-submitted thing for tooltip. This is set to make the time match the
        // browser's local timezone, but I can't find a way to use the user's specified timezone
        // from $USER. Not sure if this really matters.

        seconds = parseInt(ajaxresponsearray[k].seconds, 10);
        // TODO is the comment below relevant?
        // javascript likes to work in miliseconds, whereas moodle uses unix format (whole seconds)

        // altered - does this work?
        tempnode = new YAHOO.widget.TextNode(ajaxresponsearray[k], YAHOO.ajax_marking_block.parentnodeholder, false);
        tempnode.data.uniqueid = uniqueid;

        // apply a style according to how long since it was submitted

        var iconstyle = '';
        
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
        
        tempnode.labelStyle = iconstyle;
    }
    
    // update all the counts on the various nodes
    this.update_parent_node(YAHOO.ajax_marking_block.parentnodeholder);

    // TODO is this really needed?
    // finally, run the function that updates the original node and adds the children
    YAHOO.ajax_marking_block.oncompletefunctionholder();
    this.update_total_count();

};

/**
 * Builds the tree when the block is loaded, or refresh is clicked
 * 
 * @return bool true
 */
YAHOO.ajax_marking_block.tree_base.prototype.build_ajax_tree = function() {

    this.icon.setAttribute('class', 'loaderimage');
    this.icon.setAttribute('className', 'loaderimage');
    
    var ajaxurl = YAHOO.ajax_marking_block.variables.wwwroot+'/blocks/ajax_marking/ajax.php';
    var request = YAHOO.util.Connect.asyncRequest('POST', ajaxurl, ajax_marking_block_callback, this.course_post_data);
    
    return true;
};

/**
* function to update the total marking count by a specified number and display it
* 
* @return void
*/
YAHOO.ajax_marking_block.tree_base.prototype.update_total_count = function() {

    var totalcount = 0;
    var childcount = 0;
    var children = this.root.children;
    var childrenlength = children.length;

    for (var i=0; i<childrenlength; i++) {
        childcount = children[i].data.count;
        totalcount += parseInt(childcount, 10);
    }
    
    if (totalcount > 0) {
        document.getElementById('totalmessage').style.visibility = 'visible';
        document.getElementById('count').innerHTML = totalcount.toString();
        
    } else {
        // hide the count
        document.getElementById('totalmessage').style.visibility = 'collapse';
        YAHOO.ajax_marking_block.remove_all_child_nodes(document.getElementById('count'));
    }
};

/**
* This function updates the tree to remove the node of the pop up that has just been marked,
* then it updates the parent nodes and refreshes the tree, then sets a timer so that the popup will
* be closed when it goes to the 'changes saved' url.
*
* @param string windowurl The changes saved url. If not present, it shuts by itself, so no timer needed.
* @return void
*/
YAHOO.ajax_marking_block.tree_base.prototype.remove_node_from_tree = function(windowurl, nodeuniqueid) {

    /// get the node that was just marked and its parent node
    var nodetoremove = this.getNodeByProperty("uniqueid", nodeuniqueid);

    var parentnode = nodetoremove.parent;
    // remove the node that was just marked
    this.removeNode(nodetoremove, true);

    this.update_parent_node(parentnode);
   
    // refresh the tree to redraw the nodes with the new labels
    // TODO make sure this fires the function
    YAHOO.ajax_marking_block.refresh_tree_after_changes(this);

    this.update_total_count();

    // no need if there's no url as the pop up is self closing
    if (windowurl != -1) {
        var window_closer = "YAHOO.ajax_marking_block.popup_closing_timer('"+windowurl+"')";
        setTimeout(window_closer, 500);
    }
};

/**
 * Ajax success function called when the server responds with valid data, which checks the data 
 * coming in and calls the right function depending on the type of data it is
 * 
 * @param object o the ajax response object, passed automatically
 * @return void
 */
YAHOO.ajax_marking_block.ajax_success_handler = function (o) {

    var label = '';

    var yamb = YAHOO.ajax_marking_block;
    
    // TODO make this so that it goes to YUI logger if in developer debug mode

    /* uncomment for debugging output for the admin user

    if (userid == 2) {
        var checkDiv = document.getElementById("block_ajax_marking_refresh_button");
        checkDiv.innerHTML = o.responseText;
    }

    */
    try {
        var ajaxresponsearray = YAHOO.lang.JSON.parse(o.responseText);
    } catch (error) {
       // TODO - error handling code to prevent silent failure
    }

    // first object holds data about what kind of nodes we have so we can
    // fire the right function.
    if (ajaxresponsearray != null) {

        
         // remove the data object, leaving just the node objects
        var type = ajaxresponsearray[0].type;
        ajaxresponsearray.shift();

        // TODO - these are inconsistent. Some refer to where the request
        // is triggered and some to what it creates.

        switch (type) {

            case 'main':

                yamb.markingtree.build_course_nodes(ajaxresponsearray);
                break;

            case 'course':

                yamb.markingtree.build_assessment_nodes(ajaxresponsearray);
                yamb.adjust_width_for_ie();
                break;

            case 'groups':

                yamb.markingtree.build_group_nodes(ajaxresponsearray);
                break;

            case 'submissions':

                yamb.markingtree.build_submission_nodes(ajaxresponsearray);
                break;

            case 'config_main_tree':

                yamb.configtree.build_course_nodes(ajaxresponsearray);
                break;

            case 'config_course':

                yamb.configtree.build_assessment_nodes(ajaxresponsearray);
                break;

            case 'config_groups':

                // called when the groups settings have been updated.

                // TODO - only change the select value, don't totally re build them
                yamb.make_config_groups_list(ajaxresponsearray);
                break;

            case 'config_set':

                //just need to un-disable the radio button

                if (ajaxresponsearray[0].value === false) {
                    label = document.createTextNode(yamb.variables.nogradedassessments);
                    yamb.remove_all_child_nodes(yamb.configtree.status);
                    yamb.configtree.status.appendChild(label);
                } else {
                    yamb.enable_config_radio_buttons();
                }
                break;

            case 'config_check':
                // called when any data about config comes back after a request (not a data
                // setting request)

                // make the id of the checkbox div
                var checkboxid = 'config'+ajaxresponsearray[0].value;

                // make the radio button on screen match the value in the database that was just 
                // returned.
                document.getElementById(checkboxid).checked = true;
                
                // if its set to 'display by groups', make the groups bit underneath
                if (ajaxresponsearray[0].value == 2) {
                    // remove the config bit leaving just the groups, which were tacked onto the
                    // end of the returned array
                    ajaxresponsearray.shift();
                    //make the groups bit
                    yamb.make_config_groups_list(ajaxresponsearray);
                }
                //allow the radio buttons to be clicked again
                yamb.enable_config_radio_buttons();
                break;

            case 'config_group_save':

                if (ajaxresponsearray[0].value === false) {
                    yamb.configtree.status.innerHTML = 'AJAX error';
                } else {
                    yamb.enable_config_radio_buttons();
                }

                break;

            default:
                
                // aplies to extra node levels from modules
                yamb.markingtree.build_assessment_nodes(ajaxresponsearray);
                break;

        }
    }
};

/**
 * function which fires if the AJAX call fails
 * TODO: why does this not fire when the connection times out?
 * 
 * @param object o the ajax response object, passed automatically
 * @return void
 */
YAHOO.ajax_marking_block.ajax_failure_handler = function(o) {
    
    if (o.tId == -1) {
        YAHOO.ajax_marking_block.markingtree.div.innerHTML =  YAHOO.ajax_marking_block.variables.collapseString;
    }
    
    if (o.tId === 0) {
        YAHOO.ajax_marking_block.markingtree.div.innerHTML = YAHOO.ajax_marking_block.variables.connectfail;
        YAHOO.util.Dom.removeClass(YAHOO.ajax_marking_block.markingtree.icon, 'loaderimage');
        
        if (!document.getElementById('block_ajax_marking_collapse')) {
            YAHOO.ajax_marking_block.make_footer();
        }
    }
};

/**
 * This function enables the config popup radio buttons again after the AJAX request has
 * returned a success code.
 *
 * @return void
 */
YAHOO.ajax_marking_block.enable_config_radio_buttons = function() {
    
    var radio = document.getElementById('configshowform');
    YAHOO.util.setStyle(radio, 'color', '#000');

    var radiolength = radio.childNodes.length;

    for (var h = 0; h < radiolength; h++) {
        
        if (radio.childNodes[h].name == 'showhide') {
            YAHOO.util.Dom.setAttribute(radio.childNodes[h], 'disabled', false);
        }
    }
    
    var configgroupsdiv = document.getElementById('configgroupsdiv');
    YAHOO.util.setStyle(configgroupsdiv, 'color', '#000');

    for (var i = 0; i < configgroupsdiv.childNodes.length; i++) {
        
        if (configgroupsdiv.childNodes[i].type == 'checkbox') {
            YAHOO.util.Dom.setAttribute(configgroupsdiv.childNodes[i], 'disabled', false);
        }
    }
};

/**
 * This function disables the radio buttons when AJAX request is sent so that
 * more than one request can't be sent at once.
 * 
 * @return void
 */
YAHOO.ajax_marking_block.disable_config_radio_buttons = function() {

    var radio = document.getElementById('configshowform');
    YAHOO.util.setStyle(radio, 'color', '#aaa');
    
    var radiolength = radio.childNodes.length;
    
    for (var h = 0; h < radiolength; h++) {
        
        if (radio.childNodes[h].type == 'radio') {
            YAHOO.util.Dom.setAttribute(radio.childNodes[h], 'disabled', true);
        }
    }
    
    var configgroupsdiv = document.getElementById('configgroupsdiv');
    YAHOO.util.setStyle(configgroupsdiv, 'color', '#aaa');

    for (var i = 0; i < configgroupsdiv.childNodes.length; i++) {
        
        if (configgroupsdiv.childNodes[i].type == 'checkbox') {
            YAHOO.util.Dom.setAttribute(configgroupsdiv.childNodes[i], 'disabled', true);
        }
    }
};

/**
 * funtion to refresh all the nodes once the update operations have all been carried out by
 * remove_node_from_tree()
 * 
 * @param object treeobject the tree to be refreshed
 * @return void
 */
YAHOO.ajax_marking_block.refresh_tree_after_changes = function(treeobject) {
    
    treeobject.root.refresh();

    // If there are no nodes left, we need to remove the tree altogether
    if (treeobject.root.children.length === 0) {
        
        YAHOO.ajax_marking_block.remove_all_child_nodes(document.getElementById("totalmessage"));
        YAHOO.ajax_marking_block.remove_all_child_nodes(document.getElementById("count"));
        
        //TODO this bit used to be only for the workshop - is it OK all the time, or even needed?
        YAHOO.ajax_marking_block.remove_all_child_nodes(treeobject.div);
        
        treeobject.div.appendChild(document.createTextNode(YAHOO.ajax_marking_block.variables.nothingtomark));
    }
};

/**
* Refresh tree function - for Collapse & refresh link in the main block
* 
* @param objecttreeobject the YUI treeview object to refresh (config or main)
* @return void
*/
YAHOO.ajax_marking_block.refresh_tree = function(treeobject) {

    // might be no nodes left after marking them all previously
    if (YAHOO.ajax_marking_block.markingtree.root.nodesLength === 0) {
        YAHOO.ajax_marking_block.markingtree.build_ajax_tree();
    }
    
    treeobject.build_ajax_tree();
};

/**
 * Makes a list of groups as checkboxes and appends them to the config div next to the config
 * tree. Called when the 'show by groups' check box is selected for a node.
 * 
 * @param object data the list of groups for this assessment returned from the ajax call
 * @return void
 */
YAHOO.ajax_marking_block.make_config_groups_list = function(data) {

    varconfiggroupsdiv = document.getElementById('configgroupsdiv');
    YAHOO.ajax_marking_block.remove_all_child_nodes(groupsdiv);

    // Closure holding onclick function.
    var config_checkbox_onclick = function() {
        YAHOO.ajax_marking_block.config_checkbox_onclick();
    };
    
    // Continue the numbering of the ids from 4 (main checkboxes are 1-3). This allows us to
    // disable/enable them
    var idcounter = 4;
    
    var datalength = data.length;
    
    if (datalength === 0) {
        var emptylabel = document.createTextNode(YAHOO.ajax_marking_block.variables.nogroups);
        configgroupsdiv.appendChild(emptylabel);
    }
    
    for (var v = 0; v < datalength; v++) {

        var checkbox = '';
        
        try {
            checkbox = document.createElement('<input type="checkbox" name="showhide" />');
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
    YAHOO.ajax_marking_block.configtree.icon.removeAttribute('class', 'loaderimage');
    YAHOO.ajax_marking_block.configtree.icon.removeAttribute('className', 'loaderimage');
    YAHOO.ajax_marking_block.enable_config_radio_buttons();
};

/**
 * on click function for the groups check boxes on the config screen. clicking sets or unsets
 * a particular group for display.
 * 
 * @return void
 */
YAHOO.ajax_marking_block.config_checkbox_onclick = function() {

    var form = document.getElementById('configshowform');

    YAHOO.ajax_marking_block.disable_config_radio_buttons();

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

    var ajaxurl = YAHOO.ajax_marking_block.variables.wwwroot+'/blocks/ajax_marking/ajax.php';
    var postdata = 'id='+course+'&assessmenttype='+assessmentType+'&assessmentid='+assessment
                   +'&type=config_group_save&userid='+YAHOO.ajax_marking_block.variables.userid
                   +'&showhide=2&groups='+groupids;

    var request = YAHOO.util.Connect.asyncRequest('POST', ajaxurl, ajax_marking_block_callback, postdata);
};

/**
 * function that waits till the pop up has a particular location,
 * i.e. the one it gets to when the data has been saved, and then shuts it.
 * 
 * @param string urltoclose the url to wait for, which is normally the 'data has been saved' page
 * @return void
 */
YAHOO.ajax_marking_block.popup_closing_timer = function (urltoclose) {

    if (!YAHOO.ajax_marking_block.popupholder.closed) {

        if (YAHOO.ajax_marking_block.popupholder.location.href == YAHOO.ajax_marking_block.variables.wwwroot+urltoclose) {

            YAHOO.ajax_marking_block.popupholder.close();
            delete  YAHOO.ajax_marking_block.popupholder;

        } else {

            setTimeout(YAHOO.ajax_marking_block.popup_closing_timer(urltoclose), 1000);
        }
    }
};

/**
 * IE seems not to want to expand the block when the tree becomes wider.
 * This provides a one-time resizing so that it is a bit bigger
 * 
 * @return void
 */
YAHOO.ajax_marking_block.adjust_width_for_ie = function () {
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
YAHOO.ajax_marking_block.initialise_config_panel = function () {
    YAHOO.ajax_marking_block.configoverlaypanel = new YAHOO.widget.Panel(
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
YAHOO.ajax_marking_block.build_config_overlay = function() {

    var yamb = YAHOO.ajax_marking_block;

    if (!yamb.configoverlaypanel) {

        yamb.initialise_config_panel();

        var settingsheadertext = yamb.variables.settingsheadertext+' '+yamb.variables.fullname;
        yamb.configoverlaypanel.setHeader(settingsheadertext);

        // TODO use yui layout widget for this
        var bodytext = "<div id='configicon' class='block_ajax_marking_hidden'></div>"
        		     + "<div id='configstatus'></div>"
        		     + "<div id='configtree'></div>"
        		     + "<div id='configSettings'>"
                         + "<div id='configInstructions'>"+yamb.variables.instructions+"</div>"
                         + "<div id='configCheckboxes'>"
                         		+ "<form id='configshowform' name='configshowform'></form>"
                         + "</div>"
                         + "<div id='configgroupsdiv'></div>"
                     + "</div>";

        yamb.configoverlaypanel.setBody(bodytext);

        yamb.configoverlaypanel.beforeHideEvent.subscribe(function() {
            yamb.refresh_tree(yamb.markingtree);
        });

        yamb.configoverlaypanel.render(document.body);
        yamb.configoverlaypanel.show();
        
        // Now that the grey overlay is in place with all the divs ready, we build the config tree
        if (typeof (yamb.configtree) != 'object') {
            yamb.configtree = yamb.tree_factory('config');
            yamb.configtree.build_ajax_tree();
        }
        
        YAHOO.util.Dom.addClass(yamb.configtree.icon, 'loaderimage');

    } else {
        // It's all there from earlier, so just show it
        yamb.configoverlaypanel.show();
        yamb.remove_config_right_panel_contents();
        yamb.refresh_tree(yamb.configtree);
    }
};

YAHOO.ajax_marking_block.config_checkbox_ = function(showHide) {


};

/**
 * the onclick function for the radio buttons in the config screen. If show by group is clicked, 
 * the groups checkboxes appear below. If another one is, the groups thing is hidden.
 * 
 * @param object checkbox the box that was clicked
 * @return void
 */
YAHOO.ajax_marking_block.request_config_checkbox_data = function(checkbox) {

    //TODO did these changes work?
    
    var showHide = '';

    var form = document.getElementById('configshowform');
    
    // use proper constants here for database values?
    switch (checkbox.value) {

        case 'default':

            showHide = 0;
            break;

        case 'show':

            showHide = 1;
            break;
            
        case 'groups':
            
            showHide = 2;
            break;
            
        case 'hide':
        
            showHide = 3;
            break; 
    }

    //empty the groups area in case there were groups there last time
    YAHOO.ajax_marking_block.remove_all_child_nodes(document.getElementById('configgroupsdiv'));
    
    //Construct the url and data, with variations depending on whether it's option 2 (where groups
    // need to be requested to make the checkboxes) or not
    var url        = YAHOO.ajax_marking_block.variables.wwwroot+'/blocks/ajax_marking/ajax.php';
    var postData   = 'userid='+YAHOO.ajax_marking_block.variables.userid;
        postData  += '&assessmenttype='+form.elements['assessmenttype'].value;
        postData  += '&assessmentid='+form.elements['assessment'].value;
        postData  += '&showhide='+showHide;
    
    var nongroupvalues = '0 1 3';
    
    if (nongroupvalues.search(showHide) == -1) {
        // submit showhide value and do groups stuff
        postData  += '&id='+form.elements['course'].value;
        postData  += '&type=config_groups';
        
    } else {
        // just submit the new showhide value
        postData += '&id='+form.elements['assessment'].value;
        postData += '&type=config_set';
    }
    
    YAHOO.ajax_marking_block.disable_config_radio_buttons();
    
    var request = YAHOO.util.Connect.asyncRequest('POST', url, ajax_marking_block_callback, postData);
};

/**
 * Wipes all the config and group options away when another node or a course node is clicked in 
 * the config tree
 * 
 * @return void
 */
YAHOO.ajax_marking_block.remove_config_right_panel_contents = function() {

    YAHOO.ajax_marking_block.remove_all_child_nodes(document.getElementById('configshowform'));
    YAHOO.ajax_marking_block.remove_all_child_nodes(document.getElementById('configInstructions'));
    YAHOO.ajax_marking_block.remove_all_child_nodes(document.getElementById('configgroupsdiv'));
    return true;
};

/**
 * Used by other functions to clear all child nodes from some element.
 * 
 * @param object parentnode a dom reference
 * @return void
 */
YAHOO.ajax_marking_block.remove_all_child_nodes = function (parentnode) {
    
    if (parentnode.hasChildNodes()) {
        
        while (parentnode.hasChildNodes()) {
            parentnode.removeChild(parentnode.firstChild);
        }
    }
};

/**
 * This is to generate the footer controls once the tree has loaded
 * 
 * @return void
 */
YAHOO.ajax_marking_block.make_footer = function () {
    // Create all text nodes

    // the two links
    var refreshbutton = new YAHOO.widget.Button({
            label     : YAHOO.ajax_marking_block.variables.refresh,
            id        : 'block_ajax_marking_collapse',
            onclick   : {fn: function() {YAHOO.ajax_marking_block.refresh_tree(YAHOO.ajax_marking_block.markingtree);}},
            container : 'block_ajax_marking_refresh_button'});

    var configurebutton = new YAHOO.widget.Button({
            label     : YAHOO.ajax_marking_block.variables.configure,
            id        : 'block_ajax_marking_configure_button_button',
            onclick   : {fn: function() {YAHOO.ajax_marking_block.build_config_overlay();}},
            container : 'block_ajax_marking_configure_button'});

    // Add bits to them like onclick
    // append them to each other and the DOM
};

/**
 * This factory function is to instantiate the tree_base class in order to create the
 * main and config trees.
 * 
 * @return object the Yahoo treeview object
 */
YAHOO.ajax_marking_block.tree_factory = function (type) {

    var treeobject = '';

    switch (type) {
        
        case 'main':
            treeobject                  = new YAHOO.ajax_marking_block.tree_base('treediv');
            treeobject.icon             = document.getElementById('mainicon');
            treeobject.div              = document.getElementById('status');
            treeobject.course_post_data = 'id='+YAHOO.ajax_marking_block.variables.userid+'&type=main&userid=';
            treeobject.course_post_data += YAHOO.ajax_marking_block.variables.userid;
            treeobject.config           = false;

            // Set the removal of all child nodes each time a node is collapsed (forces refresh)
            // not needed for config tree
            treeobject.subscribe('collapseComplete', function(node) {
                // TODO - make this not use a hardcoded reference
                YAHOO.ajax_marking_block.main.tree.removeChildren(node);
            });
            break;

        case 'config':
            treeobject                  = new YAHOO.ajax_marking_block.tree_base('configtree');
            treeobject.icon             = document.getElementById('configicon');
            treeobject.div              = document.getElementById('configstatus');
            treeobject.course_post_data = 'id='+YAHOO.ajax_marking_block.variables.userid+'&type=config_main_tree&userid=';
            treeobject.course_post_data += YAHOO.ajax_marking_block.variables.userid;
            treeobject.config           = true;
            break;
    }
    
    // TODO can this be missed out and getRoot() used throughout the code instead where it's needed?
    treeobject.root = treeobject.getRoot();
    return treeobject;
};

/**
 * Callback object for the AJAX call, which fires the correct function. Doesn't work when part 
 * of the main class. Don't know why - something to do with scope.
 * 
 * @return void
 */
var  ajax_marking_block_callback = {

    cache    : false,
    success  : YAHOO.ajax_marking_block.ajax_success_handler,
    failure  : YAHOO.ajax_marking_block.ajax_failure_handler,
    scope    : YAHOO.ajax_marking_block,
    // TODO: find out what this was for as the timeouts seem not to be working
    // argument : 1200,
    timeout  : 10000

};

/**
 * The initialising stuff to get everything started
 * 
 * @return void
 */
YAHOO.ajax_marking_block.initialise = function() {
    
    // workaround for odd https setups. Probably not needed in most cases
    if (document.location.toString().indexOf('https://') != -1) {
        YAHOO.ajax_marking_block.variables.wwwroot = YAHOO.ajax_marking_block.variables.wwwroot.replace('http:', 'https:');
    }
    
    // the context menu needs this for the skin to show up, as do other bits
    YAHOO.util.Dom.addClass(document.body, 'yui-skin-sam');
    
    YAHOO.ajax_marking_block.markingtree = YAHOO.ajax_marking_block.tree_factory('main');

    YAHOO.ajax_marking_block.markingtree.build_ajax_tree();
}