<?php

use Functional as F;

defined('MOODLE_INTERNAL') || die;

require_once __DIR__ . '/../../../vendor/autoload.php';

/**
 * installs one table from the install.xml file in the same directory
 * @param string $table_name
 */
function _install_one_table($table_name) {
    global $DB;

    // get XML DB structure
    $xmldb_file = new xmldb_file(__DIR__ . '/install.xml');
    $xmldb_file->loadXMLStructure();
    $structure = $xmldb_file->getStructure();

    // get exactly one table matching the given table name
    $filtered_tables = F\filter($structure->getTables(), function ($table) use ($table_name) {
        /** @var xmldb_table $table */
        return $table->getName() === $table_name;
    });
    if (count($filtered_tables) !== 1) {
        return;
    }

    /** @var xmldb_table $tbl */
    $tbl = F\head($filtered_tables);
    $tbl->setPrevious(null);
    $tbl->setNext(null);

    // install
    $structure->setTables([$tbl]);
    $DB->get_manager()->install_from_xmldb_structure($structure);
}

/**
 * upgrades the database
 * @global moodle_database $DB
 * @param integer $oldversion
 * @return bool
 */
function xmldb_talkpoint_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // add the 'nimbbguidcomment' column and change 'uploadedfile' to be not required.
    if ($oldversion < 2014081300) {
        $table = new xmldb_table('talkpoint_talkpoint');
        $field = new xmldb_field('nimbbguid', XMLDB_TYPE_CHAR, '100', null, false);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('uploadedfile', XMLDB_TYPE_CHAR, '100', null, false);
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }
        upgrade_plugin_savepoint(true, 2014081300, 'mod', 'talkpoint');
    }

    // install talkpoint_video_conversion table
    if ($oldversion < 2015042100) {
        _install_one_table('talkpoint_video_conversion');
        upgrade_plugin_savepoint(true, 2015042100, 'mod', 'talkpoint');
    }

    // add 'mediatype' column to database (e.g. 'audio' or 'video')
    if ($oldversion < 2015070900) {
        $table = new xmldb_table('talkpoint_talkpoint');
        $field = new xmldb_field('mediatype', XMLDB_TYPE_CHAR, '100', null, true);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // set initial values for the new 'mediatype' field.
        // talkpoints that have a nimbbguid are 'webcam' videos at this point
        $DB->set_field_select('talkpoint_talkpoint', 'mediatype', 'webcam', "nimbbguid != ''");
        $DB->set_field_select('talkpoint_talkpoint', 'mediatype', 'file', "uploadedfile != ''");
        upgrade_plugin_savepoint(true, 2015070900, 'mod', 'talkpoint');
    }

    return true;
}
