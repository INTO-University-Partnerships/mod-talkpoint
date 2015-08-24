<?php

define('CLI_SCRIPT', true);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config.php';
require_once $CFG->libdir . '/clilib.php';
require_once __DIR__ . '/models/talkpoint_model.php';
require_once __DIR__ . '/src/video_converter.php';

if (CLI_MAINTENANCE) {
    echo "CLI maintenance mode active, execution suspended.\n";
    exit(1);
}

if (moodle_needs_upgrading()) {
    echo "Moodle upgrade pending, execution suspended.\n";
    exit(1);
}

cli_heading('Converting video files');

/**
 * given a Talkpoint id, returns its upload path
 * @global moodle_database $DB
 * @param integer $talkpointid
 * @return string
 */
$get_talkpoint_upload_path = function ($talkpointid) {
    global $DB;
    $talkpoint_model = new talkpoint_model();
    $base_upload_path = $talkpoint_model->get_upload_path();
    $instanceid = (integer)$DB->get_field('talkpoint_talkpoint', 'instanceid', ['id' => $talkpointid], MUST_EXIST);
    return $base_upload_path . '/' . $instanceid . '/' . $talkpointid;
};

$ffmpeg_binary = isset($CFG->ffmpeg_binary) ? $CFG->ffmpeg_binary : realpath(__DIR__ . '/bin/ffmpeg');

$video_converter = new video_converter();
$video_converter->convert($get_talkpoint_upload_path, $ffmpeg_binary);
