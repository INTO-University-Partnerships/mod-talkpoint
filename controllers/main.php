<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormError;

defined('MOODLE_INTERNAL') || die();

$controller = $app['controllers_factory'];

// view the given activity
$controller->get('/cmid/{cmid}', function ($cmid) use ($app) {
    global $CFG, $DB;

    // get instanceid
    $instanceid = (integer)$DB->get_field('course_modules', 'instance', array(
        'id' => $cmid,
    ), MUST_EXIST);

    // redirect
    return $app->redirect($CFG->wwwroot . SLUG . $app['url_generator']->generate('byinstanceid', array(
        'id' => $instanceid,
    )));
})
->bind('bycmid')
->assert('cmid', '\d+');

// view the given activity
$controller->get('/{id}', function ($id) use ($app) {
    global $CFG, $DB;

    // get module id from modules table
    $moduleid = (integer)$DB->get_field('modules', 'id', array(
        'name' => $app['module_table'],
    ), MUST_EXIST);

    // get instance
    $instance = $DB->get_record($app['module_table'], array(
        'id' => $id,
    ), '*', MUST_EXIST);

    // get course module
    $cm = $DB->get_record('course_modules', array(
        'module' => $moduleid,
        'instance' => $id,
    ), '*', MUST_EXIST);

    // get course
    $course = $DB->get_record('course', array(
        'id' => $cm->course,
    ), '*', MUST_EXIST);

    // require course login
    $app['require_course_login']($course, $cm);

    // mark viewed
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);

    // get module context
    $context = context_module::instance($cm->id);

    // log it
    $app['course_module_viewed']($cm, $instance, $course, $context);

    // set heading and title
    $app['heading_and_title']($course->fullname, $instance->name);

    // render
    return $app['twig']->render('talkpoints.twig', array(
        'baseurl' => $CFG->wwwroot . SLUG,
        'cm' => $cm,
        'api' => '/api/v1',
        'instance' => $instance,
        'course' => $course,
        'can_manage' => $app['has_capability']('moodle/course:manageactivities', $context),
        'is_guest' => isguestuser(),
    ));
})
->bind('byinstanceid')
->assert('id', '\d+');

// view the given talkpoint (within a talkpoint activity)
$controller->get('/talkpoint/{id}', function ($id) use ($app) {
    global $CFG, $DB, $USER;

    // get talkpoint
    $talkpoint = $DB->get_record('talkpoint_talkpoint', array(
        'id' => $id,
    ), '*', MUST_EXIST);

    // get instance
    $instance = $DB->get_record($app['module_table'], array(
        'id' => $talkpoint->instanceid,
    ), '*', MUST_EXIST);

    // require course login
    list($course, $cm) = $app['get_course_and_course_module']($instance->id);
    $app['require_course_login']($course, $cm);

    // determine whether the logged in user can manage
    $context = context_module::instance($cm->id);
    $can_manage = $app['has_capability']('moodle/course:manageactivities', $context);

    // load the talkpoint (taking group mode into consideration if the logged in user can't manage activities)
    require_once __DIR__ . '/../models/talkpoint_model.php';
    $talkpoint_model = new talkpoint_model();
    $talkpoint_model->set_userid($USER->id);
    if (!$can_manage) {
        $talkpoint_model->set_groupmode($app['get_groupmode']($course->id, $cm->id));
    }
    $talkpoint = $talkpoint_model->get($id);

    // check if there is either a video or a nimbbguid
    if (empty($talkpoint['nimbbguid']) && empty($talkpoint['uploadedfile'])) {
        return new Response(get_string('nomediatoshow', $app['plugin']), 404);
    }

    // check if there's videos that need converting, and if so, redirect back to the list of talkpoints
    $has_videos_to_convert = $talkpoint_model->has_videos_to_convert($id);
    if ($has_videos_to_convert) {
        if (!$can_manage && ($USER->id != $talkpoint['userid'])) {
            return $app->redirect($CFG->wwwroot . SLUG . $app['url_generator']->generate('byinstanceid', array(
                'id' => $instance->id,
            )));
        }
    }

    // check that the uploaded file exists
    $uploadpath = $talkpoint_model->get_upload_path() . '/' . $talkpoint['instanceid'] . '/' . $id;
    if (!file_exists($uploadpath . '/' . $talkpoint['uploadedfile']) && !empty($talkpoint['uploadedfile'])) {
        return new Response(get_string('storedfilecannotread', 'error'), 404);
    }

    // determine (Twig) template and (jPlayer) formats
    $template = '';
    $formats = '';
    if (!empty($talkpoint['uploadedfile']) && file_exists($uploadpath . '/' . $talkpoint['uploadedfile'])) {
        list($template, $formats) = $app['mimetype_mapper']->type_and_formats_from_upload_path_and_uploaded_file(
            $uploadpath,
            $talkpoint['uploadedfile']
        );
    }
    if (!empty($talkpoint['nimbbguid'])) {
        $template = 'webcam';
    }

    // set heading and title
    $instance_title = sprintf(
        '%s %s %s',
        $talkpoint['title'],
        strtolower(get_string('by', $app['plugin'])),
        $talkpoint['userfullname']
    );
    $app['heading_and_title']($course->fullname, $instance_title);

    // render
    return $app['twig']->render('talkpoint/' . $template . '.twig', array(
        'baseurl' => $CFG->wwwroot . SLUG,
        'cm' => $cm,
        'api' => '/api/v1',
        'instance' => $instance,
        'course' => $course,
        'talkpoint' => $talkpoint,
        'formats' => $formats,
        'has_videos_to_convert' => $has_videos_to_convert,
        'can_manage' => $can_manage,
        'is_guest' => isguestuser(),
        'nimbb_force_html5_player' => empty($CFG->nimbb_force_html5_player) ? false : $CFG->nimbb_force_html5_player,
        'instance_title' => $instance_title,
    ));
})
->bind('talkpoint')
->assert('id', '\d+');

// form for adding a new talkpoint
$controller->match('/{instanceid}/add', function (Request $request, $instanceid) use ($app) {
    global $CFG, $DB, $USER;

    // require course login
    list($course, $cm) = $app['get_course_and_course_module']($instanceid);
    $app['require_course_login']($course, $cm);

    // ensure the user isn't the guest user
    if (isguestuser()) {
        return $app->redirect($CFG->wwwroot . SLUG . $app['url_generator']->generate('byinstanceid', array(
            'id' => $instanceid,
        )));
    }

    // get module context
    $context = context_module::instance($cm->id);

    // ensure the talkpoint is not closed
    $closed = $DB->get_field('talkpoint', 'closed', array('id' => $instanceid), MUST_EXIST) == 1;
    if (!$app['has_capability']('moodle/course:manageactivities', $context) && $closed) {
        return $app->redirect($CFG->wwwroot . SLUG . $app['url_generator']->generate('byinstanceid', array(
            'id' => $instanceid,
        )));
    }

    // create a form builder and populate a form with form elements
    $form = $app['form.factory']->createBuilder('form', null, array('csrf_protection' => false))
        ->add('title', 'text', array(
            'label' => get_string('title', $app['plugin']),
            'required' => true,
            'constraints' => new Symfony\Component\Validator\Constraints\Length(array(
                'max' => 100,
            )),
        ))
        ->add('uploadedfile', 'hidden')
        ->add('nimbbguid', 'hidden')
        ->add('mediatype', 'hidden')
        ->getForm();

    // the media type is empty on a GET and non-empty on a POST (so the form can 'remember' the media type)
    $mediaType = '';

    // handle form submission
    if ('POST' == $request->getMethod()) {
        require_sesskey();
        $form->bind($request);

        if ($form->isValid()) {
            $data = $form->getData();

            // fill in the blanks
            $data['instanceid'] = $instanceid;
            $data['userid'] = $USER->id;
            $data['closed'] = false;

            // create talkpoint model and save the talkpoint
            require_once __DIR__ . '/../models/talkpoint_model.php';
            $talkpoint_model = new talkpoint_model();
            $data = $talkpoint_model->save($data, $app['now']());

            if (!empty($data['uploadedfile'])) {
                // remove existing files
                $uploadpath = $talkpoint_model->get_upload_path() . '/' . $instanceid . '/' . $data['id'];
                check_dir_exists($uploadpath);

                // move the uploaded file to its permanent location
                $uploadpath_temp = $talkpoint_model->get_upload_path() . '/' . $instanceid . '/temp';
                if (file_exists($uploadpath_temp . '/' . $data['uploadedfile'])) {
                    rename($uploadpath_temp . '/' . $data['uploadedfile'], $uploadpath . '/' . $data['uploadedfile']);
                }

                // empty 'temp' folder
                $talkpoint_model->clean_tempupload_path($uploadpath_temp);

                $app['video_converter']->queue_convert_non_m4v_to_m4v(
                    $uploadpath,
                    $data['id']
                );

                // if the uploaded file is an image, resize it
                $app['image_resizer']->resize($uploadpath, $data['uploadedfile']);
            }

            // redirect to the new talkpoint itself
            return $app->redirect($CFG->wwwroot . SLUG . $app['url_generator']->generate('talkpoint', array(
                'id' => $data['id'],
            )));
        }
    }

    // set heading and title
    $app['heading_and_title'](
        $course->fullname,
        get_string('adding', $app['plugin']) . ' ' . get_string('pluginname', $app['plugin'])
    );

    // render
    return $app['twig']->render('add.twig', array(
        'baseurl' => $CFG->wwwroot . SLUG,
        'cm' => $cm,
        'api' => '/api/v1',
        'form' => $form->createView(),
        'instanceid' => $instanceid,
        'mediaType' => $mediaType,
        'sesskey' => $USER->sesskey,
    ));
})
->bind('add')
->assert('instanceid', '\d+');

// form for editing an existing talkpoint
$controller->match('/{instanceid}/edit/{id}', function (Request $request, $instanceid, $id) use ($app) {
    global $CFG, $USER;

    // require course login
    list($course, $cm) = $app['get_course_and_course_module']($instanceid);
    $app['require_course_login']($course, $cm);

    // get module context
    $context = context_module::instance($cm->id);

    // load the talkpoint
    require_once __DIR__ . '/../models/talkpoint_model.php';
    $talkpoint_model = new talkpoint_model();
    $talkpoint = $talkpoint_model->get($id);

    // check that the logged in user can either manage activities or is the owner of the talkpoint
    if (!$app['has_capability']('moodle/course:manageactivities', $context) && ($USER->id != $talkpoint['userid'])) {
        return $app->redirect($CFG->wwwroot . SLUG . $app['url_generator']->generate('talkpoint', array(
            'id' => $id,
        )));
    }

    // create a form builder and populate a form with form elements
    $form = $app['form.factory']->createBuilder('form', null, array('csrf_protection' => false))
        ->add('title', 'text', array(
            'label' => get_string('title', $app['plugin']),
            'required' => true,
            'constraints' => new Symfony\Component\Validator\Constraints\Length(array(
                'max' => 100,
            )),
            'data' => $talkpoint['title'],
        ))
        ->add('uploadedfile', 'hidden')
        ->add('nimbbguid', 'hidden')
        ->add('mediatype', 'hidden')
        ->getForm();

    // handle form submission
    if ('POST' == $request->getMethod()) {
        require_sesskey();
        $form->bind($request);

        if ($form->isValid()) {
            $data = $form->getData();

            // fill in the blanks
            $data['instanceid'] = $instanceid;
            $data['id'] = $id;

            if (isset($data['uploadedfile'])
                && $data['uploadedfile'] !== $talkpoint['uploadedfile']) {

                // remove existing files
                $uploadpath = $talkpoint_model->get_upload_path() . '/' . $instanceid . '/' . $id;
                $app['video_converter']->clean_upload_path($uploadpath);

                // move the uploaded file to its permanent location
                $uploadpath_temp = $talkpoint_model->get_upload_path() . '/' . $instanceid . '/temp';
                if (file_exists($uploadpath_temp . '/' . $data['uploadedfile'])) {
                    rename($uploadpath_temp . '/' . $data['uploadedfile'], $uploadpath . '/' . $data['uploadedfile']);
                }

                // empty 'temp' folder
                $talkpoint_model->clean_tempupload_path($uploadpath_temp);

                $app['video_converter']->queue_convert_non_m4v_to_m4v(
                    $uploadpath,
                    $data['id']
                );

                // if the uploaded file is an image, resize it
                $app['image_resizer']->resize($uploadpath, $data['uploadedfile']);
            }

            // if no new file was uploaded and also no nimbb was recorded, keep the original file.
            if (empty($data['nimbbguid']) && !isset($data['uploadedfile'])) {
                $data['uploadedfile'] = $talkpoint['uploadedfile'];
            }

            // save the talkpoint
            $talkpoint_model->save($data, $app['now']());

            // redirect to the talkpoint itself
            return $app->redirect($CFG->wwwroot . SLUG . $app['url_generator']->generate('talkpoint', array(
                'id' => $id,
            )));
        }
    }

    // set heading and title
    $app['heading_and_title'](
        $course->fullname,
        get_string('editinga', 'moodle', get_string('pluginname', $app['plugin']))
    );

    if ($talkpoint['uploadedfile']) {
        $uploadpath = $talkpoint_model->get_upload_path() . '/' . $talkpoint['instanceid'] . '/' . $id;
        $filetype = get_file_type($uploadpath . '/' . $talkpoint['uploadedfile']);
    }

    // render
    return $app['twig']->render('edit.twig', array(
        'baseurl' => $CFG->wwwroot . SLUG,
        'cm' => $cm,
        'api' => '/api/v1',
        'form' => $form->createView(),
        'instanceid' => $instanceid,
        'id' => $id,
        'nimbbguid' => $talkpoint['nimbbguid'],
        'mediaType' => $talkpoint['mediatype'],
        'uploadedfile' => $talkpoint['uploadedfile'],
        'filetype' => empty($filetype) ? '' : $filetype,
        'sesskey' => $USER->sesskey,
    ));
})
->bind('edit')
->assert('instanceid', '\d+')
->assert('id', '\d+');

// serve the uploaded file
$app->get('/servefile/{id}', function ($id) use ($app) {
    // load the talkpoint
    require_once __DIR__ . '/../models/talkpoint_model.php';
    $talkpoint_model = new talkpoint_model();
    $talkpoint = $talkpoint_model->get($id);

    // require course login
    list($course, $cm) = $app['get_course_and_course_module']($talkpoint['instanceid']);
    $app['require_course_login']($course, $cm);

    // check that the uploaded file exists
    $uploadpath = $talkpoint_model->get_upload_path() . '/' . $talkpoint['instanceid'] . '/' . $id;
    if (!file_exists($uploadpath . '/' . $talkpoint['uploadedfile'])) {
        return new Response(get_string('storedfilecannotread', 'error'), 404);
    }

    // close the session to avoid locking issues
    \core\session\manager::write_close();

    // send the file
    $splFileInfo = new SplFileInfo($uploadpath . '/' . $talkpoint['uploadedfile']);
    return $app->sendFile($splFileInfo);
})
->bind('servefile')
->assert('id', '\d+');

// serve a video file of a given format (not necessarily the file that was uploaded)
$app->get('/servevideofile/{id}/{format}', function ($id, $format) use ($app) {
    // load the talkpoint
    require_once __DIR__ . '/../models/talkpoint_model.php';
    $talkpoint_model = new talkpoint_model();
    $talkpoint = $talkpoint_model->get($id);

    // require course login
    list($course, $cm) = $app['get_course_and_course_module']($talkpoint['instanceid']);
    $app['require_course_login']($course, $cm);

    // determine the file to serve
    $upload_path = $talkpoint_model->get_upload_path() . '/' . $talkpoint['instanceid'] . '/' . $id;
    $file_to_serve = $app['mimetype_mapper']->video_file_from_upload_path_and_video_format($upload_path, $format);

    // close the session to avoid locking issues
    \core\session\manager::write_close();

    // send the file
    $splFileInfo = new SplFileInfo($upload_path . '/' . $file_to_serve);
    return $app->sendFile($splFileInfo);
})
->bind('servevideofile')
->assert('id', '\d+')
->assert('format', '(m4v|ogv|webmv)');

// download the uploaded file
$app->get('/uploadedfile/{id}', function ($id) use ($app) {
    global $CFG, $USER;

    // load the talkpoint
    require_once __DIR__ . '/../models/talkpoint_model.php';
    $talkpoint_model = new talkpoint_model();
    $talkpoint = $talkpoint_model->get($id);

    // require course login
    list($course, $cm) = $app['get_course_and_course_module']($talkpoint['instanceid']);
    $app['require_course_login']($course, $cm);

    // get module context
    $context = context_module::instance($cm->id);

    // check that the logged in user can either manage activities or is the owner of the talkpoint
    if (!$app['has_capability']('moodle/course:manageactivities', $context) && ($USER->id != $talkpoint['userid'])) {
        return $app->redirect($CFG->wwwroot . SLUG . $app['url_generator']->generate('talkpoint', array(
            'id' => $id,
        )));
    }
    $uploadpath = $talkpoint_model->get_upload_path() . '/' . $talkpoint['instanceid'] . '/' . $id;
    if (!file_exists($uploadpath . '/' . $talkpoint['uploadedfile'])) {
        return new Response(get_string('storedfilecannotread', 'error'), 404);
    }

    // send the file
    $splFileInfo = new SplFileInfo($uploadpath . '/' . $talkpoint['uploadedfile']);
    return $app->sendFile($splFileInfo);
})
->bind('uploadedfile')
->assert('id', '\d+');

// download temp file
$app->get('/tempuploaded/{instanceid}/{file}', function ($instanceid, $file) use ($app) {
    global $CFG;

    // require course login
    list($course, $cm) = $app['get_course_and_course_module']($instanceid);
    $app['require_course_login']($course, $cm);

    // ensure the user isn't the guest user
    if (isguestuser()) {
        return $app->redirect($CFG->wwwroot . SLUG . $app['url_generator']->generate('byinstanceid', array(
            'id' => $instanceid,
        )));
    }

    // load the talkpoint
    require_once __DIR__ . '/../models/talkpoint_model.php';
    $talkpoint_model = new talkpoint_model();

    $uploadpath = $talkpoint_model->get_upload_path() . '/' . $instanceid . '/temp/';
    if (!file_exists($uploadpath . $file)) {
        return new Response(get_string('storedfilecannotread', 'error'), 404);
    }

    // send the file
    $splFileInfo = new SplFileInfo($uploadpath . $file);
    return $app->sendFile($splFileInfo);
})
->assert('instanceid', '\d+');

// return the controller
return $controller;
