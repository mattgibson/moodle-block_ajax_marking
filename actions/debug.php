<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.


/**
 * This provides a simple interface to investigate what comes back from the AJAX calls without
 * having to constantly reload the page
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2011 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// standard login to the front page. Redirect to login.php if not authenticated, then bounce back


// Present a form with a text box for the file name and another for the query string
echo html_writer::start_tag('form');


echo html_writer::empty_tag('imput', array('type' => 'submit', 'name' => 'Submit'));

echo html_writer::end_tag('form');

// Add a submit button

// Add a div to hold the results

// Add JS to intercept the submit thing and construct the AJAX request

// Add JS to catch the returned AJAX data and put line breaks and indents in

// Add JS to put the data into the innerhtml of the display div






