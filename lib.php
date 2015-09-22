<?php

defined('MOODLE_INTERNAL') || die();

/**
 * @global moodle_database $DB
 * @param object $obj
 * @param mod_talkpoint_mod_form $mform
 * @return integer
 */
function talkpoint_add_instance($obj, mod_talkpoint_mod_form $mform = null) {
    global $DB;
    $obj->timecreated = $obj->timemodified = time();
    $obj->closed = !empty($obj->closed);
    $obj->header = (isset($obj->header) && array_key_exists('text', $obj->header)) ? $obj->header['text'] : null;
    $obj->footer = (isset($obj->footer) && array_key_exists('text', $obj->footer)) ? $obj->footer['text'] : null;
    $obj->id = $DB->insert_record('talkpoint', $obj);
    return $obj->id;
}

/**
 * @global moodle_database $DB
 * @param object $obj
 * @param mod_talkpoint_mod_form $mform
 * @return boolean
 */
function talkpoint_update_instance($obj, mod_talkpoint_mod_form $mform) {
    global $DB;
    $obj->id = $obj->instance;
    $obj->timemodified = time();
    $obj->closed = !empty($obj->closed);
    $obj->header = (isset($obj->header) && array_key_exists('text', $obj->header)) ? $obj->header['text'] : null;
    $obj->footer = (isset($obj->footer) && array_key_exists('text', $obj->footer)) ? $obj->footer['text'] : null;
    $success = $DB->update_record('talkpoint', $obj);
    return $success;
}

/**
 * @global moodle_database $DB
 * @param integer $id
 * @return boolean
 */
function talkpoint_delete_instance($id) {
    global $DB;
    require_once __DIR__ . '/models/talkpoint_model.php';
    $talkpoint_model = new talkpoint_model();
    $talkpoints = $talkpoint_model->get_all_by_instanceid($id);
    foreach ($talkpoints as $talkpoint) {
        $talkpoint_model->delete($talkpoint['id']);
    }
    $success = $DB->delete_records('talkpoint', array('id' => $id));
    remove_dir($talkpoint_model->get_upload_path() . '/' . $id);
    return $success;
}

/**
 * @param string $feature
 * @return boolean
 */
function talkpoint_supports($feature) {
    $support = array(
        FEATURE_COMPLETION_TRACKS_VIEWS => true,
        FEATURE_GRADE_HAS_GRADE => false,
        FEATURE_GRADE_OUTCOMES => false,
        FEATURE_ADVANCED_GRADING => false,
        FEATURE_CONTROLS_GRADE_VISIBILITY => false,
        FEATURE_PLAGIARISM => false,
        FEATURE_COMPLETION_HAS_RULES => false,
        FEATURE_NO_VIEW_LINK => false,
        FEATURE_IDNUMBER => false,
        FEATURE_GROUPS => true,
        FEATURE_GROUPINGS => false,
        FEATURE_MOD_ARCHETYPE => false,
        FEATURE_MOD_INTRO => false,
        FEATURE_MODEDIT_DEFAULT_COMPLETION => false,
        FEATURE_COMMENT => false,
        FEATURE_RATE => false,
        FEATURE_BACKUP_MOODLE2 => true,
        FEATURE_SHOW_DESCRIPTION => false,
    );
    if (!array_key_exists($feature, $support)) {
        return null;
    }
    return $support[$feature];
}

/**
 * given a file path, return its type (one of 'image', 'video' or 'audio')
 * @param string $path
 * @return string
 */
function get_file_type($path) {
    if (!file_exists($path)) {
        return '';
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimetype = finfo_file($finfo, $path);
    finfo_close($finfo);
    preg_match('(image|video|audio)', $mimetype, $match);
    return empty($match) ? '' : $match[0];
}
