YAHOO.ajax_marking_block.forum_final = {};

YAHOO.ajax_marking_block.forum_final.pop_up_post_data = function (node) {
    return 'd='+node.data.aid+'#p'+node.data.sid;
}
YAHOO.ajax_marking_block.forum_final.pop_up_closing_url = function (node) {
    return '/mod/forum/discuss.php';
}
YAHOO.ajax_marking_block.forum_final.pop_up_opening_url = function (node) {
    return '/mod/forum/discuss.php?d='+node.data.aid+'#p'+node.data.sid;
}
YAHOO.ajax_marking_block.forum_final.pop_up_arguments = function (node) {
    return 'menubar=0,location=0,scrollbars,resizable,width=780,height=630';  
}


/**
 * function to add onclick stuff to the forum ratings button. This button also has no name or id
 * so we identify it by getting the last tag in the array of inputs. The function is triggered
 * on an interval of 1/2 a second until it manages to close the pop up after it has gone to the
 * confirmation page
 */
YAHOO.ajax_marking_block.forum_final.alter_popup = function (me) {
    var els ='';

    // first, add the onclick if possible
    // TODO - did this change work?
    var inputType = typeof YAHOO.ajax_marking_block.pop_up_holder.document.getElementsByTagName('input');
    if (inputType != 'undefined') {
    // if (typeof YAHOO.ajax_marking_block.pop_up_holder.document.getElementsByTagName('input') != 'undefined') {
        // The window is open with some input. could be loading lots though.
        els = YAHOO.ajax_marking_block.pop_up_holder.document.getElementsByTagName('input');

        if (els.length > 0) {
            var key = els.length -1;
            // Does the last input have the 'send in my ratings string as label, showing that
            // all the rating are loaded?
            if (els[key].value == amVariables.forumSaveString) {
                // IE friendly
                //TODO - did this change work?
                var functionText = "return YAHOO.ajax_marking_block.main_instance.remove_node_from_tree('/mod/forum/rate.php', ";
                    functionText += "'"+me+"');";
                els[key]["onclick"] = new Function(functionText);
                //els[key]["onclick"] = new Function("return YAHOO.ajax_marking_block.remove_node_from_tree('/mod/forum/rate.php', YAHOO.ajax_marking_block.main, '"+me+"');");
                // cancel loop for this function
                window.clearInterval(YAHOO.ajax_marking_block.timerVar);

            }
        }
    }
};



