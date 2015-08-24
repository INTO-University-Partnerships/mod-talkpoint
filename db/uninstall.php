<?php

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../models/talkpoint_model.php';

/**
 * removes the uploaded files directory
 * @return boolean
 */
function xmldb_talkpoint_uninstall() {
    $talkpoint_model = new talkpoint_model();
    remove_dir($talkpoint_model->get_upload_path());
    return true;
}
