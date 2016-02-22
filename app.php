<?php

// bootstrap Moodle
require_once __DIR__ . '/../../config.php';
global $CFG, $FULLME;

// fix $FULLME
$FULLME = str_replace($CFG->wwwroot, $CFG->wwwroot . SLUG, $FULLME);

// create Silex app
require_once __DIR__ . '/../../vendor/autoload.php';
$app = new Silex\Application();
$app['debug'] = debugging('', DEBUG_MINIMAL);

// enable Twig service provider
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/templates',
    'twig.options' => array(
        'cache' => empty($CFG->disable_twig_cache) ? "{$CFG->dataroot}/twig_cache" : false,
        'auto_reload' => debugging('', DEBUG_MINIMAL),
    ),
));

// register form, validation, translation and URL generator service providers
$app->register(new Silex\Provider\FormServiceProvider());
$app->register(new Silex\Provider\ValidatorServiceProvider());
$app->register(new Silex\Provider\TranslationServiceProvider());
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

// register 3rd party service providers
$app->register(new Neutron\Silex\Provider\ImagineServiceProvider());

// set Twig constants
$app['twig']->addGlobal('plugin', 'mod_talkpoint');
$app['twig']->addGlobal('wwwroot', $CFG->wwwroot);
$app['twig']->addGlobal('slug', SLUG);
$app['twig']->addGlobal('bower_url', isset($CFG->bower_url) ? $CFG->bower_url : $CFG->wwwroot . '/mod/talkpoint/static/js/components/');

// require Twig library functions
require __DIR__ . '/twiglib.php';

// module settings
$app['plugin'] = 'mod_talkpoint';
$app['module_table'] = 'talkpoint';
$app['file_constraints'] = array(
    'maxSize' => '20M',
    'mimeTypes' => array(
        'image/jpeg',
        'image/gif',
        'image/png',
        'audio/mpeg',
//        'audio/mp4',
//        'audio/webm',
//        'audio/ogg',
        'video/mp4',
        'video/webm',
        'video/quicktime',
//        'video/ogg',
        'application/ogg',
        'application/octet-stream',
    ),
);

// require the services
foreach (array(
    'course_module_viewed',
    'course_module_instance_list_viewed',
    'get_course_and_course_module',
    'get_groupmode',
    'has_capability',
    'heading_and_title',
    'image_resizer',
    'mimetype_mapper',
    'now',
    'require_course_login',
    'video_converter'
) as $service) {
    require __DIR__ . '/services/' . $service . '.php';
}

// mount the controllers
foreach (array(
    'instances' => 'instances',
    'main' => '',
    'partials' => 'partials',
    'js' => 'js',
    'v1_api' => 'api/v1',
) as $controller => $mount_point) {
    $app->mount('/' . $mount_point, require __DIR__ . '/controllers/' . $controller . '.php');
}

// return the app
return $app;
