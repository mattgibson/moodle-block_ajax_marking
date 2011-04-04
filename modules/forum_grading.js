M.block_ajax_marking.forum = (function() {

    return {
        
//        pop_up_post_data : function(clickednode) {
//            return 'd='+clickednode.data.assessmentid+'#p'+clickednode.data.firstpostid;
//        },
        
        pop_up_closing_url : function() {
            return '/mod/forum/discuss.php';
        },
        
        pop_up_opening_url : function (clickednode) {
            return '/mod/forum/discuss.php/?d='+clickednode.data.discussionid;
        },
        
        pop_up_arguments : function () {
            return 'menubar=0,location=0,scrollbars,resizable,width=780,height=630'; 
        },

        extra_ajax_request_arguments : function () {
            return '';
        },
        /**
         * function to add onclick stuff to the forum ratings button. This button also has no name or id
         * so we identify it by getting the last tag in the array of inputs. The function is triggered
         * on an interval of 1/2 a second until it manages to close the pop up after it has gone to the
         * confirmation page
         */
        alter_popup : function (clickednode) {
        
            var inputelements ='';
        
            // first, add the onclick if possible
            // TODO - did this change work?
            var inputtype = typeof(M.block_ajax_marking.popupholder.document.getElementsByTagName('input'));
            
            if (inputtype != 'undefined') {
                // if (typeof M.block_ajax_marking.popupholder.document.getElementsByTagName('input') != 'undefined') {
                // The window is open with some input. could be loading lots though.
                inputelements = M.block_ajax_marking.popupholder.document.getElementsByTagName('input');
        
                if (inputelements.length > 0) {
                
                    var key = inputelements.length -1;
                    
                    // Does the last input have the 'send in my ratings string as label, showing that
                    // all the rating are loaded?
                    if (inputelements[key].value == amVariables.forumSaveString) {
                        // IE friendly
                        // TODO - did this change work?
                        var functionText = "return M.block_ajax_marking.markingtree.remove_node_from_tree('/mod/forum/rate.php', "
                                           +"'"+clickednode.data.uniqueid+"');";
                        inputelements[key]["onclick"] = new Function(functionText);
                        //els[key]["onclick"] = new Function("return M.block_ajax_marking.remove_node_from_tree('/mod/forum/rate.php', M.block_ajax_marking.main, '"+me+"');");
                        // cancel loop for this function
                        window.clearInterval(M.block_ajax_marking.popuptimer);
        
                    }
                }
            }
        }
    };
})();

