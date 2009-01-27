<?php

require_login(1, false);

class quiz_functions extends module_base {

    function quiz_functions(&$reference) {
        $this->reference = $reference;
        // must be the same as th DB modulename
        $this->type = 'quiz';
        $this->capability = 'mod/quiz:grade';
    }


}

?>