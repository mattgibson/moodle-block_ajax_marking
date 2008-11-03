<?php

function xmldb_block_ajax_marking_upgrade($oldversion=0) {

    echo "oldversion: ".$oldversion;
    global $CFG, $THEME, $db;

    $result = true;

/// And upgrade begins here. For each one, you'll need one 
/// block of code similar to the next one. Please, delete 
/// this comment lines once this file start handling proper
/// upgrade code.

    if ($result && $oldversion < 2007052901) { //New version in version.php
    
    

    /// Define table block_ajax_marking to be created
        $table = new XMLDBTable('block_ajax_marking');

    /// Adding fields to table block_ajax_marking
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, null, null);
        $table->addFieldInfo('assessmenttype', XMLDB_TYPE_CHAR, '40', null, null, null, null, null, null);
        $table->addFieldInfo('assessmentid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, null, null);
        $table->addFieldInfo('showhide', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null, null, '1');
        $table->addFieldInfo('groups', XMLDB_TYPE_TEXT, 'small', null, null, null, null, null, null);
       

    /// Adding keys to table block_ajax_marking
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->addKeyInfo('useridkey', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));

    /// Launch create table for block_ajax_marking
        $result = $result && create_table($table);
    }

    return $result;
}

?>