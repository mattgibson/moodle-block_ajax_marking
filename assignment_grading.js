
/**
 * @param object clickednode the node of the tree that was clicked to open the popup
 */
YAHOO.ajax_marking_block.assignment = (function() {

    return {
        
        pop_up_arguments : function() {
            return 'menubar=0,location=0,scrollbars,resizable,width=900,height=630';
        },
        
        // pop_up_post_data : function (clickednode) {
        //    return 'id='+node.data.aid+'&userid='+clickednode.data.submissionid+'&mode=single&offset=0';
        // };
        
        pop_up_closing_url : function() {
            return '/mod/assignment/submissions.php';
        },
        
        pop_up_opening_url : function (clickednode) {
        	
        	var url = '/mod/assignment/submissions.php?id='+clickednode.data.coursemoduleid
        	          +'&userid='+clickednode.data.userid+'&mode=single&offset=0';
        	return url;
            //return '';
        },
        
        extra_ajax_request_arguments : function () {
            return '';
        },
        
        /**
         * this function is called every 100 milliseconds once the assignment pop up is called
         * and tries to add the onclick handlers until it is successful. There are a few extra
         * checks in the following functions that appear to be redundant but which are
         * necessary to avoid errors. The part of /mod/assignment/lib.php at line 509 tries to
         * update the main window with $this->update_main_listing($submission). This fails because
         * there is no main window with the submissions table as there would have been if the pop
         * up had been generated from the submissions grading screen. To avoid the errors,
         *
         * NOTE: the offset system for saveandnext depends on the sort state having been stored in the
         * $SESSION variable when the grading screen was accessed (which may not have happened, as we
         * are not coming from the submissions.php grading screen or may have been a while ago). The
         * sort reflects the last sort mode the user asked for when ordering the list of pop-ups, e.g.
         * by clicking on the firstname column header. I have not yet found a way to alter this variable
         * using javascript - ideally, the sort would be the same as it is in the list presented in the
         * marking block. Until a work around is found, the save and next function is be a bit wonky,
         * sometimes showing next when there is only one submission, so I have hidden it.
         */
        alter_popup : function(clickednode) {
        
            var submitelements  = '';
            var saveelements    = '';
            var nextelements    = '';
            
            this.counter = 0;


            //do nothing if it's been shut
            if (YAHOO.ajax_marking_block.pop_up_holder.closed) {
                window.clearInterval(YAHOO.ajax_marking_block.timerVar);
                return true;
            }

            // is the pop up even open? Hard to check if the elements of the DOM are not present yet,
            // so we check for the existence of each one before trying to add the onclick.
            if (YAHOO.ajax_marking_block.popupholder.document) {

                var buttons = YAHOO.util.Dom.getElementsByClassName('buttons', 'div', YAHOO.ajax_marking_block.popupholder.document);

                if(buttons.length > 0) {

                    // hide the unecessary buttons
                    YAHOO.util.Dom.setStyle(buttons[0].childNodes[2], 'display', 'none');
                    YAHOO.util.Dom.setStyle(buttons[0].childNodes[3], 'display', 'none');

                    // TODO pass the value needed in obj?
                    // TODO can 'this' be used to get the data object from the node?

                    var functiontext  = "return YAHOO.ajax_marking_block.markingtree.remove_node_from_tree('', '"
                                      + clickednode.data.uniqueid+"'); ";
                    YAHOO.util.on(buttons[0].childNodes[0], 'click', functiontext);

                    return true;
                }
            }

            return false;

            // cancel the timer loop for this function
            window.clearInterval(YAHOO.ajax_marking_block.popuptimer);
         
        }
    };
})();

// included here because the assignment pop up expects to have it

var assignment = {};

function setNext(){
    document.getElementById('submitform').mode.value = 'next';
    document.getElementById('submitform').userid.value = assignment.nextid;
}

function saveNext(){
    document.getElementById('submitform').mode.value = 'saveandnext';
    document.getElementById('submitform').userid.value = assignment.nextid;
    document.getElementById('submitform').saveuserid.value = assignment.userid;
    document.getElementById('submitform').menuindex.value = document.getElementById('submitform').grade.selectedIndex;
}

function initNext(nextid, usserid) {
    assignment.nextid = nextid;
    assignment.userid = userid;
}





