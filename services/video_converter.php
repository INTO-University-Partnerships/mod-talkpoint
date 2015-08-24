<?php

defined('MOODLE_INTERNAL') || die();

$app['video_converter'] = $app->share(function ($app) {
    require_once __DIR__ . '/../src/video_converter.php';
    $video_converter = new video_converter();
    return $video_converter;
});
