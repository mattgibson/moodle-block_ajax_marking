// TODO - this used to be workshop_final. Why?
YAHOO.ajax_marking_block.workshop = (function() {

    // TODO - did this cahnge work?
    
    
    return {
        
        pop_up_arguments : function() {
            return 'menubar=0,location=0,scrollbars,resizable,width=980,height=630';
        },
        
        //YAHOO.ajax_marking_block.workshop_final.pop_up_post_data = function (node) {
        //    return 'id='+node.data.aid+'&sid='+node.data.sid+'&redirect='+amVariables.wwwroot;
        //}
        
        pop_up_closing_url : function () {
            return '/mod/workshop/assess.php';
        },
        
        pop_up_opening_url : function (clickednode) {
            return '/mod/workshop/view.php?id='+clickednode.data.cmid;
        },

        extra_ajax_request_arguments : function () {
            return '';
        },
        /**
         * workshop pop up stuff
         * function to add workshop onclick stuff and shut the pop up after its been graded.
         * the pop -up goes to a redirect to display the grade, so we have to wait until
         * then before closing it so that the grade is processed properly.
         *
         * note: this looks odd because there are 2 things that needs doing, one after the pop up loads
         * (add onclicks)and one after it goes to its redirect (close window).it is easier to check for
         * a fixed url (i.e. the redirect page) than to mess around with regex stuff to detect a dynamic
         * url, so the else will be met first, followed by the if. The loop will keep running whilst the
         * pop up is open, so this is not very elegant or efficient, but should not cause any problems
         * unless the client is horribly slow. A better implementation will follow sometime soon.
         */
        alter_popup : function (clickednode) {
        
            var els ='';
            // check that the frames are loaded - this can vary according to conditions
            
            if (typeof YAHOO.ajax_marking_block.popupholder.frames[0] != 'undefined') {
            
                //var currenturl = YAHOO.ajax_marking_block.popupholder.frames[0].location.href;
               // var targeturl = amVariables.wwwroot+'/mod/workshop/assessments.php';
                
                if (currenturl != targeturl) {
                    // this is the early stage, pop up has loaded and grading is occurring
                    // annoyingly, the workshop module has not named its submit button, so we have to
                    // get it using another method as the 11th input
                    els = YAHOO.ajax_marking_block.popupholder.frames[0].document.getElementsByTagName('input');
                    
                    if (els.length == 11) {
                        // TODO - did this change work?
                        var functiontext = "return YAHOO.ajax_marking_block.markingtree.remove_node_from_tree("
                                         + "'/mod/workshop/assessments.php', '"
                                         + clickednode.data.uniqueid+"');";
                        els[10]['onclick'] = new Function(functiontext);
                        // els[10]["onclick"] = new Function("return YAHOO.ajax_marking_block.remove_node_from_tree('/mod/workshop/assessments.php', YAHOO.ajax_marking_block.main, '"+me+"', true);"); // IE
                        
                        // cancel timer loop
                        window.clearInterval(YAHOO.ajax_marking_block.popuptimer);
                    }
                }
            }
        }
    }
})();

