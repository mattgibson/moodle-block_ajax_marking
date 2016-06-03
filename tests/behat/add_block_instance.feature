@block_ajax_marking
Feature: Installing the AJAX Marking Block

    In order to use the block
    As a teacher
    I need to be able to add an instance to a course
    So that I can  see it and configure it

    Scenario: The block is available in the blocks dropdown
        Given the following "courses" exist:
            | fullname | shortname | format | category |
            | Course 1 | C1        | topics | 0        |
        And I log in as "admin"
        And I follow "Courses"
        And I follow "Course 1"
        When I turn editing mode on
        Then I should see the AJAX marking block in the blocks dropdown