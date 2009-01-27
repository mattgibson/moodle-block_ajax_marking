<?php

require_login(1, false);

class journal_functions extends module_base {
    function journal_functions(&$reference) {
        $this->reference = $reference;
        // must be the same as th DB modulename
        $this->type = 'journal';
        // doesn't seem to be a journal capability :s
        $this->capability = 'mod/assignment:grade';
    }


}

?>