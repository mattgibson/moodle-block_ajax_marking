<?php

//defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ElementNotFoundException as ElementNotFoundException;

/**
 * Holds all the step definitions for the behat tests in the AJAX Marking block
 */
class behat_block_ajax_marking extends behat_base {

    /**
     * @Then /^I should see the AJAX marking block in the blocks dropdown$/
     */
    public function i_should_see_the_ajax_marking_block_in_the_blocks_dropdown() {
        $xpath = "//select[@name='bui_addblock']/option[text()='AJAX marking']";
        $exception = new ElementNotFoundException($this->getSession(), "AJAX marking in add block dropdown");
        $this->find('xpath', $xpath, $exception);
    }
}