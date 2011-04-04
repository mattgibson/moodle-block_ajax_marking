M.block_ajax_marking.quiz = (function() {

    return {
        
//        pop_up_post_data : function(clickednode) {
//            return 'mode=grading&action=grade&q='+clickednode.parent.parent.data.id+'&questionid='+
//                   clickednode.data.aid+'&userid='+clickednode.data.sid;
//        },
        
        pop_up_closing_url : function() {
            return '/mod/quiz/report.php';
        },
        
        pop_up_opening_url : function(clickednode) {
            return '/blocks/ajax_marking/actions/grading_popup.php?module=quiz&attempt='+clickednode.data.attemptid+
                   '&question='+clickednode.data.questionid+'&uniqueid='+clickednode.data.uniqueid;
        },
        
        pop_up_arguments : function() {
            return 'menubar=0,location=0,scrollbars,resizable,width=780,height=670';
        },
        
        extra_ajax_request_arguments : function(clickednode) {
        
            if (clickednode.data.type == 'quiz_question') {
                return '&secondary_id='+clickednode.parent.data.id;
            } else {
                return '';
            }
        },
        
        /**
         * adds onclick stuff to the quiz popup
         */
        alter_popup : function(clickednode) {
        
            var inputelements = '';
            var lastbutone = '';
        
            if (typeof(M.block_ajax_marking.popupholder.document.getElementsByTagName('input')) != 'undefined') {
                // window is open with some input. could be loading lots though.
                inputelements = M.block_ajax_marking.popupholder.document.getElementsByTagName('input');
        
                if (inputelements.length > 14) {
                
                    // there is at least the DOM present for a single attempt, but if the student has
                    // made a couple of attempts, there will be a larger window.
                    lastbutone = inputelements.length - 1;
        
                    if (inputelements[lastbutone].value == amVariables.quizSaveString) {
        
                        // the onclick carries out the functions that are already specified in lib.php,
                        // followed by the function to update the tree
                        // TODO - did this change work?
                        var functiontext = "return M.block_ajax_marking.markingtree.remove_node_from_tree('/mod/quiz/report.php', '"
                                         + clickednode.data.uniqueid+"'); ";
                        inputelements[lastbutone]['onclick'] = new Function(functiontext);
                        //inputelements[lastButOne]["onclick"] = new Function("return M.block_ajax_marking.remove_node_from_tree('/mod/quiz/report.php', M.block_ajax_marking.main, '"+me+"'); ");
                        // cancel the loop for this function
        
                        window.clearInterval(M.block_ajax_marking.popuptimer);
                    }
                }
            }
        }
    };
})();
