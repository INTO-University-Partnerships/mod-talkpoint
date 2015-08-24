<?php

use Symfony\Component\HttpFoundation\Response;

defined('MOODLE_INTERNAL') || die();

$controller = $app['controllers_factory'];

// serve JavaScript (or SWFs)
$controller->get('/{file}', function ($file) use ($app) {
    $path = __DIR__ . '/../static/js/';
    if (!file_exists($path . '/' . $file)) {
        return new Response(get_string('storedfilecannotread', 'error'), 404);
    }
    $splFileInfo = new SplFileInfo($path . '/' . $file);
    return $app->sendFile($splFileInfo);
})
->assert('file', '[A-Za-z_/]+\.(js|swf)');

// return the controller
return $controller;
