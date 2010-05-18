YAHOO.ajax_marking_block.quiz = (function() {

    return {
        
        pop_up_post_data : function(clickednode) {
            return 'mode=grading&action=grade&q='+clickednode.parent.parent.data.id+'&questionid='+clickednode.data.aid+'&userid='+clickednode.data.sid;
        },
        
        pop_up_closing_url : function() {
            return '/mod/quiz/report.php';
        },
        
        pop_up_opening_url : function(clickednode) {
            return '/mod/quiz/report.php?mode=grading&q='+clickednode.parent.parent.data.id+'&questionid='+clickednode.data.aid+'&userid='+clickednode.data.sid;
        },
        
        pop_up_arguments : function() {
            return 'menubar=0,location=0,scrollbars,resizable,width=780,height=630';
        },
        
        extra_ajax_request_arguments : function(clickednode) {
        
            if (clickednode.data.type == 'quiz_question') {
                return '&secondary_id='+clickednode.parent.data.id;
            } else {
                return true;
            }
        },
        
        /**
         * adds onclick stuff to the quiz popup
         */
        alter_popup : function(clickednode) {
        
            var inputelements = '';
            var lastbutone = '';
        
            if (typeof(YAHOO.ajax_marking_block.pop_up_holder.document.getElementsByTagName('input')) != 'undefined') {
                // window is open with some input. could be loading lots though.
                inputelements = YAHOO.ajax_marking_block.pop_up_holder.document.getElementsByTagName('input');
        
                if (inputelements.length > 14) {
                
                    // there is at least the DOM present for a single attempt, but if the student has
                    // made a couple of attempts, there will be a larger window.
                    lastbutone = inputelements.length - 1;
        
                    if (inputelements[lastbutone].value == amVariables.quizSaveString) {
        
                        // the onclick carries out the functions that are already specified in lib.php,
                        // followed by the function to update the tree
                        // TODO - did this change work?
                        var functiontext = "return YAHOO.ajax_marking_block.main_instance.remove_node_from_tree('/mod/quiz/report.php', '"
                                         + clickednode.data.uniqueid+"', false); ";
                        inputelements[lastbutone]['onclick'] = new Function(functiontext);
                        //inputelements[lastButOne]["onclick"] = new Function("return YAHOO.ajax_marking_block.remove_node_from_tree('/mod/quiz/report.php', YAHOO.ajax_marking_block.main, '"+me+"'); ");
                        // cancel the loop for this function
        
                        window.clearInterval(YAHOO.ajax_marking_block.timerVar);
                    }
                }
            }
        }
    };
})();
