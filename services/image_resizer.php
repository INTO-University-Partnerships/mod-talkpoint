<?php

defined('MOODLE_INTERNAL') || die();

$app['image_resizer'] = $app->share(function ($app) {
    require_once __DIR__ . '/../src/image_resizer.php';
    $image_resizer = new image_resizer();
    $image_resizer->set_imagine($app['imagine']);
    $image_resizer->set_max_width(300);
    return $image_resizer;
});
