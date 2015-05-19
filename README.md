## Please note: this plugin is no longer supported. 

Sadly, I just don't have time to keep up with the chages to core which keep breaking different parts of it. It really needs a full rewrite, but there's not going to be time for me to do that given my current commitments.

# Instructions

Full documentation is available at: http://docs.moodle.org/en/Ajax_marking_block

This block displays all of your marking from all of your courses in one place and allows you to
grade the work in single-student pop ups without leaving the page. It is most useful as a front
page block, but works just as well in a course, although the pieces of work on display will still
be from the whole site, not just that course. Be aware that it will ignore site-wide roles for
performance reasons (very slow on large sites + too much space needed to display output) and only
teacher roles assigned at category or course level will result in work showing up here.

The block displays grading in a tree structure in the form of `Course -> Assessment item -> Student`.
There are exceptions for some assessment types as their structure needs extra levels e.g. quizzes:
`Course -> Quiz -> Question -> Student`.

There is an option to enable 'display by group' for each individual assessment (currently disabled
for Moodle 2.1). This will add an extra level: `Course -> Assessment -> GROUP -> Student`, with the
option to choose which groups to show or hide. To enable this, click on the 'Configure' link at the
bottom of the block to open a settings pop up. The tree in the pop up shows all of the assessment
items you have permission to grade and you can set you personal display preferences by clicking on
the name of the assesment. Changes are saved instantly when you select any option, and you just
need to close the settings pop up when you are done.

Currently supported types:

* Assignment
* Forum
* Quiz
* Workshop
