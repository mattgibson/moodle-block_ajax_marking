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
 * Basic readme file in English.
 *
 * @package   blocks-ajax_marking
 * @copyright 2008-2011 Matt Gibson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

Full documentation is available at: http://docs.moodle.org/en/Ajax_marking_block

This block displays all of your marking from all of your courses in one place and allows you to
grade the work in single-student pop ups without leaving the page. It is most useful as a front
page block, but works just as well in a course, although the pieces of work on display will still
be from the whole site, not just that course. Be aware that it will ignore site-wide roles for
performance reasons (very slow on large sites + too much space needed to display output) and only
teacher roles assigned at category or course level will result in work showing up here.

The block displays grading in a tree structure in the form of Course -> Assessment item -> Student.
There are exceptions for some assessment types as their structure needs extra levels e.g. quizzes:
Course -> Quiz -> Question -> Student.

There is an option to enable 'display by group' for each individual assessment (currently disabled
for Moodle 2.1). This will add an extra level: Course -> Assessment -> GROUP -> Student, with the
option to choose which groups to show or hide. To enable this, click on the 'Configure' link at the
bottom of the block to open a settings pop up. The tree in the pop up shows all of the assessment
items you have permission to grade and you can set you personal display preferences by clicking on
the name of the assesment. Changes are saved instantly when you select any option, and you just
need to close the settings pop up when you are done.

Currently supported types:

Assignment
Forum
Quiz
Workshop

test for jenkin
