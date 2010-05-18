
/**
 * @param object clickednode the node of the tree that was clicked to open the popup
 */
YAHOO.ajax_marking_block.assignment = (function() {

    return {
        
        pop_up_arguments : function() {
            return 'menubar=0,location=0,scrollbars,resizable,width=780,height=630';
        },
        
        // pop_up_post_data : function (clickednode) {
        //    return 'id='+node.data.aid+'&userid='+clickednode.data.submissionid+'&mode=single&offset=0';
        // };
        
        pop_up_closing_url : function() {
            return '/mod/assignment/submissions.php';
        },
        
        pop_up_opening_url : function (clickednode) {
        	
        	var url = '/mod/assignment/submissions.php?id='+clickednode.data.assessmentid
        	          +'&userid='+clickednode.data.submissionid+'&mode=single&offset=0';
        	return url;
        },
        
        extra_ajax_request_arguments : function () {
            return true;
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
        
            var submitelements  ='';
            var saveelements = '';
            var nextelements = '';
        
            // when the DOM is ready, add the onclick events and hide the other buttons
            if (YAHOO.ajax_marking_block.pop_up_holder.document) {
            	
                if (YAHOO.ajax_marking_block.pop_up_holder.document.getElementsByName) {
                	
                    submitelements = YAHOO.ajax_marking_block.pop_up_holder.document.getElementsByName('submit');
                    
                    // the above line will not return anything until the pop up is fully loaded
                    if (submitelements.length > 0) {
                        // To keep the assignment javascript happy, we need to make some divs for it to
                        // copy the grading data to, just as it would if it was called from the main
                        // submission grading screen. Line 710-728 of /mod/assignment/lib.php can't be
                        // dealt with easily, so there will be an error if outcomes are in use, but
                        // hopefully, that won't be so frequent.
        
                        // TODO see if there is a way to grab the outcome ids from the pop up and make
                        // divs using them that will match the ones that the javascript is looking for
                    	
                    	// TODO is this even needed in 2.0?
                        var div = document.createElement('div');
                        div.setAttribute('id', 'com'+clickednode.data.submissionid);
                        div.style.display = 'none';
        
                        var textArea = document.createElement('textarea');
                        textArea.setAttribute('id', 'submissioncomment'+clickednode.data.submissionid);
                        textArea.style.display = 'none';
                        textArea.setAttribute('rows', "2");
                        textArea.setAttribute('cols', "20");
                        div.appendChild(textArea);
                        window.document.getElementById('javaValues').appendChild(div);
        
                        var div2 = document.createElement('div');
                        div2.setAttribute('id', 'g'+clickednode.data.submissionid);
                        div2.style.display = 'none';
                        window.document.getElementById('javaValues').appendChild(div2);
        
                        var textArea2 = document.createElement('textarea');
                        textArea2.setAttribute('id', 'menumenu'+clickednode.data.submissionid);
                        textArea2.style.display = 'none';
                        textArea2.setAttribute('rows', "2");
                        textArea2.setAttribute('cols', "20");
                        window.document.getElementById('g'+clickednode.data.submissionid).appendChild(textArea2);
        
                        var div3 = document.createElement('div');
                        div3.setAttribute('id', 'ts'+clickednode.data.submissionid);
                        div3.style.display = 'none';
                        window.document.getElementById('javaValues').appendChild(div3);
        
                        var div4 = document.createElement('div');
                        div4.setAttribute('id', 'tt'+clickednode.data.submissionid);
                        div4.style.display = 'none';
                        window.document.getElementById('javaValues').appendChild(div4);
        
                        var div5 = document.createElement('div');
                        div5.setAttribute('id', 'up'+clickednode.data.submissionid);
                        div5.style.display = 'none';
                        window.document.getElementById('javaValues').appendChild(div5);
        
                        var div6 = document.createElement('div');
                        div6.setAttribute('id', 'finalgrade_'+clickednode.data.submissionid);
                        div6.style.display = 'none';
                        window.document.getElementById('javaValues').appendChild(div6);
        
                        // now add onclick
                        var functionText  = "return YAHOO.ajax_marking_block.main_instance.remove_node_from_tree(-1, '";
                            functionText += clickednode.data.uniqueid+"', false); ";
        
                        submitelements[0]["onclick"] = new Function(functionText);
                        //submitelements[0]["onclick"] = new Function("return YAHOO.ajax_marking_block.remove_node_from_tree(-1, YAHOO.ajax_marking_block.main, '"+me+"', false); "); // IE
                        saveelements = YAHOO.ajax_marking_block.pop_up_holder.document.getElementsByName('saveandnext');
        
                        if (saveelements.length > 0) {
                            saveelements[0].style.display = "none";
                            nextelements = YAHOO.ajax_marking_block.pop_up_holder.document.getElementsByName('next');
                            nextelements[0].style.display = "none";
                        }
                        // cancel the timer loop for this function
                        window.clearInterval(YAHOO.ajax_marking_block.timerVar);
                    }
                }
            }
        }
    };
})();





