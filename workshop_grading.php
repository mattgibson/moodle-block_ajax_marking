<?php

require_login(1, false);

class workshop_functions extends module_base {

    function workshop_functions(&$reference) {
        $this->reference = $reference;
        // must be the same as th DB modulename
        $this->type = 'workshop';
        $this->capability = 'mod/workshop:manage';
    }


}

?>