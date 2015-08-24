<?php

use Symfony\Component\HttpFoundation\Response;

defined('MOODLE_INTERNAL') || die();

$controller = $app['controllers_factory'];

// serve partials
$controller->get('/{file}', function ($file) use ($app) {
    $path = __DIR__ . '/../templates/partials';
    if (!file_exists($path . '/' . $file)) {
        return new Response(get_string('storedfilecannotread', 'error'), 404);
    }
    return $app['twig']->render('partials/' . $file);
})
->assert('file', '[A-Za-z_/]+\.twig');

// return the controller
return $controller;
