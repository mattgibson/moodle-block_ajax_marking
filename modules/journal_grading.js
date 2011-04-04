// uses 'journal' as the node that will be clicked on will have this type.
M.block_ajax_marking.journal = (function(clickednode) {

    return {
        
        pop_up_post_data : function () {
            return 'id='+clickednode.data.cmid;
        },
        
        pop_up_closing_url : function () {
            return '/mod/journal/report.php';
        },
        
        pop_up_arguments : function () {
            return 'menubar=0,location=0,scrollbars,resizable,width=900,height=500';
        },
        
        pop_up_opening_url : function () {
            var url  = '/mod/journal/report.php?id='+clickednode.data.cmid+'&group=';
                url += ((typeof(clickednode.data.group)) != 'undefined') ? clickednode.data.group : '0' ;
            return url;
        },

        extra_ajax_request_arguments : function () {
            return '';
        },
        
        /**
         * adds onclick stuff to the journal pop up elements once they are ready.
         * me is the id number of the journal we want
         */
        alter_popup : function () {
        
            // get the form submit input, which is always last but one (length varies)
            var input_elements = M.block_ajax_marking.popupholder.document.getElementsByTagName('input');
        
            // TODO - might catch the pop up half loaded. Not ideal.
            if (typeof(input_elements) != 'undefined' && input_elements.length > 0) {
            
                var key = input_elements.length -1;
        
                YAHOO.util.Event.on(
                    input_elements[key],
                    'click',
                    function(){
                        return M.block_ajax_marking.markingtree.remove_node_from_tree(
                            '/mod/journal/report.php',
                            clickednode.data.uniqueid
                        );
                    }
                );
                // cancel the timer loop for this function
                window.clearInterval(M.block_ajax_marking.popuptimer);
            }
        }
    };
})();
