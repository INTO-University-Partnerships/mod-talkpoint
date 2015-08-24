<?php

defined('MOODLE_INTERNAL') || die();

$app['mimetype_mapper'] = $app->share(function ($app) {
    require_once __DIR__ . '/../src/mimetype_mapper.php';
    $mimetype_mapper = new mimetype_mapper();
    return $mimetype_mapper;
});
