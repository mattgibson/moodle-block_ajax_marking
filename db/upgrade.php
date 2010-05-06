<?php

/**
 * Check here for new modules to add to the database. This happens every time the block is upgraded.
 * If you have added your own extension, increment the version number in block_ajax_marking.php
 * to trigger this process. Also called after install.
 */

function AMB_update_modules() {

    global $CFG, $DB;

    $modules = array();
    echo "<br /><br />Scanning site for modules which have an AJAX Marking Block plugin... <br />";

    // make a list of directories to check for module grading files
    $installed_modules = get_list_of_plugins('mod');
    $directories = array($CFG->dirroot.'/blocks/ajax_marking');
    foreach ($installed_modules as $module) {
        $directories[] = $CFG->dirroot.'/mod/'.$module;
    }

    // get module ids so that we can store these later
//    $comma_modules = $installed_modules;
//    foreach($comma_modules as $key => $comma_module) {
//        $comma_modules[$key] = "'".$comma_module."'";
//    }
//    $comma_modules = implode(', ', $comma_modules);

    list($usql, $params) = $DB->get_in_or_equal($installed_modules);
    $sql = "
        SELECT name, id FROM {modules}
        WHERE name $usql
    ";
    $module_ids = $DB->get_records_sql($sql, $params);

    // Get files in each directory and check if they fit the naming convention
    foreach ($directories as $directory) {
        $files = scandir($directory);

        // check to see if they end in _grading.php
        foreach ($files as $file) {
            // this should lead to 'modulename' and 'grading.php'
            $pieces = explode('_', $file);
            if ((isset($pieces[1])) && ($pieces[1] == 'grading.php')) {
                if(in_array($pieces[0], $installed_modules)) {

                    $modname = $pieces[0];

                    // add the modulename part of the filename to the array
                    $modules[$modname] = new stdClass;
                    $modules[$modname]->name = $modname;

                    // do not store $CFG->dirroot so that any changes to it will not break the block
                    $modules[$modname]->dir  = str_replace($CFG->dirroot, '', $directory);
                    //$modules[$modname]->dir  = $directory;

                    $modules[$modname]->id   = $module_ids[$modname]->id;

                    echo "Registered $modname module <br />";
                }
            }
        }
    }

    echo '<br />For instructions on how to write extensions for this block, see the documentation on Moodle Docs<br /><br />';

    set_config('modules', serialize($modules), 'block_ajax_marking');
}

function xmldb_block_ajax_marking_upgrade($oldversion=0) {

    //echo "oldversion: ".$oldversion;
    global $CFG, $THEME, $DB;

    $dbman = $DB->get_manager();

    $result = true;

    if ($result && $oldversion < 2007052901) { //New version in version.php

    /// Define table block_ajax_marking to be created
        $table = new xmldb_table('block_ajax_marking');

    /// Adding fields to table block_ajax_marking
        $table->add_field('id',             XMLDB_TYPE_INTEGER, '10'    , null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->add_field('userid',         XMLDB_TYPE_INTEGER, '10'    , null, null, null, null, null, null);
        $table->add_field('assessmenttype', XMLDB_TYPE_CHAR,    '40'    , null, null, null, null, null, null);
        $table->add_field('assessmentid',   XMLDB_TYPE_INTEGER, '10'    , null, null, null, null, null, null);
        $table->add_field('showhide',       XMLDB_TYPE_INTEGER, '1'     , null, XMLDB_NOTNULL, null, null, null, '1');
        $table->add_field('groups',         XMLDB_TYPE_TEXT,    'small' , null, null, null, null, null, null);


    /// Adding keys to table block_ajax_marking
        $table->add_key('primary',   XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('useridkey', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));

    /// Launch create table for block_ajax_marking
        $result = $result && $dbman->create_table($table);
    }

    if ($result && $oldversion < 2010050101) {


    }

    // run this on every upgrade.
    AMB_update_modules();

    return $result;
}