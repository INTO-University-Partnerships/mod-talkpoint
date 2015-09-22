<?php

use Symfony\Component\HttpFoundation\Request;

defined('MOODLE_INTERNAL') || die();

if (!defined('AJAX_SCRIPT')) {
    define('AJAX_SCRIPT', true);
}

$controller = $app['controllers_factory'];

// get viewable talkpoints
$controller->get('/talkpoint/{instanceid}', function (Request $request, $instanceid) use ($app) {
    global $USER;

    // get pagination parameters
    $limitfrom = (integer)$request->get('limitfrom');
    $limitnum = (integer)$request->get('limitnum');

    // require course login
    list($course, $cm) = $app['get_course_and_course_module']($instanceid);
    $app['require_course_login']($course, $cm);

    // determine whether the user can manage
    $context = context_module::instance($cm->id);
    $can_manage = $app['has_capability']('moodle/course:manageactivities', $context);

    // determine whether the user can view all talkpoints (or only 'viewable' talkpoints)
    require_once __DIR__ . '/../models/talkpoint_model.php';
    $talkpoint_model = new talkpoint_model();
    $talkpoint_model->set_userid($USER->id);
    if (!$can_manage) {
        $talkpoint_model->set_groupmode($app['get_groupmode']($course->id, $cm->id));
    }
    $f = $can_manage ? 'get_total_by_instanceid' : 'get_total_viewable_by_instanceid';
    $g = $can_manage ? 'get_all_by_instanceid' : 'get_all_viewable_by_instanceid';

    // count and fetch talkpoints and return JSON response
    $total = $talkpoint_model->$f($instanceid);
    $talkpoints = $talkpoint_model->$g($instanceid, $limitfrom, $limitnum);
    return $app->json((object)array(
        'talkpoints' => $talkpoints,
        'total' => $total,
    ));
})
->bind('all')
->assert('instanceid', '\d+');

// get one talkpoint
$controller->get('/talkpoint/{instanceid}/{talkpointid}', function ($instanceid, $talkpointid) use ($app) {
    global $DB, $USER;

    // require course login
    list($course, $cm) = $app['get_course_and_course_module']($instanceid);
    $app['require_course_login']($course, $cm);

    // see whether the talkpoint actually exists
    if (!$DB->record_exists('talkpoint_talkpoint', array(
        'instanceid' => $instanceid,
        'id' => $talkpointid,
    ))) {
        return $app->json('', 404);
    }

    // create talkpoint model
    require_once __DIR__ . '/../models/talkpoint_model.php';
    $talkpoint_model = new talkpoint_model();
    $talkpoint_model->set_userid($USER->id);

    // fetch talkpoint and return JSON response
    $talkpoint = $talkpoint_model->get($talkpointid);
    return $app->json($talkpoint);
})
->assert('instanceid', '\d+')
->assert('talkpointid', '\d+');

// delete one talkpoint
$controller->delete('/talkpoint/{instanceid}/{talkpointid}', function ($instanceid, $talkpointid) use ($app) {
    global $DB, $USER;

    // require course login
    list($course, $cm) = $app['get_course_and_course_module']($instanceid);
    $app['require_course_login']($course, $cm);

    // see whether the talkpoint actually exists
    if (!$DB->record_exists('talkpoint_talkpoint', array(
        'instanceid' => $instanceid,
        'id' => $talkpointid,
    ))) {
        return $app->json('', 404);
    }

    // create talkpoint model and get talkpoint
    require_once __DIR__ . '/../models/talkpoint_model.php';
    $talkpoint_model = new talkpoint_model();
    $talkpoint = $talkpoint_model->get($talkpointid);

    // get module context
    $context = context_module::instance($cm->id);

    // check that the logged in user can either manage activities or is the owner of the talkpoint
    if (!$app['has_capability']('moodle/course:manageactivities', $context) && ($USER->id != $talkpoint['userid'])) {
        return $app->json(get_string('jsonapi:notowneroftalkpoint', $app['plugin']), 403);
    }

    // delete talkpoint
    $talkpoint_model->delete($talkpointid);
    return $app->json('', 204);
})
->assert('instanceid', '\d+')
->assert('talkpointid', '\d+');

// save file on a talkpoint
$controller->post('/talkpoint/{instanceid}/upload', function (Request $request, $instanceid) use ($app) {
    global $CFG, $USER;

    // require course login
    list($course, $cm) = $app['get_course_and_course_module']($instanceid);
    $app['require_course_login']($course, $cm);

    // ensure the user isn't the guest user
    if (isguestuser()) {
        return $app->json(get_string('jsonapi:talkpointasguestdenied', $app['plugin']), 400);
    }

    // load the talkpoint
    require_once __DIR__ . '/../models/talkpoint_model.php';
    $talkpoint_model = new talkpoint_model();

    if (($id = $request->request->get('id')) != null) {
        // get module context
        $context = context_module::instance($cm->id);
        $talkpoint = $talkpoint_model->get($id);
        // check that the logged in user can either manage activities or is the owner of the talkpoint
        if (!$app['has_capability']('moodle/course:manageactivities', $context) && ($USER->id != $talkpoint['userid'])) {
            return $app->json(get_string('jsonapi:notowneroftalkpoint', $app['plugin']), 403);
        }
    }

    // request the file
    $file = $request->files->get('file');
    $error = $app['validator']->validateValue($file, new Symfony\Component\Validator\Constraints\File($app['file_constraints']));
    if (count($error) > 0) {
        return $app->json($error[0]->getMessage(), 500);
    }

    if (!empty($file)) {
        // move the uploaded file to a temporary location
        $uploadpath = $talkpoint_model->get_upload_path() . '/' . $instanceid . '/temp/';
        $file->move($uploadpath, $file->getClientOriginalName());
    }

    return $app->json(array(
        'uploadedfile' => $file->getClientOriginalName()
    ));
})
->assert('instanceid', '\d+');

// get all comments
$controller->get('/talkpoint/{instanceid}/{talkpointid}/comment', function (Request $request, $instanceid, $talkpointid) use ($app) {
    global $DB, $USER, $OUTPUT;

    // require course login
    list($course, $cm) = $app['get_course_and_course_module']($instanceid);
    $app['require_course_login']($course, $cm);

    // see whether the talkpoint actually exists
    if (!$DB->record_exists('talkpoint_talkpoint', array(
        'instanceid' => $instanceid,
        'id' => $talkpointid,
    ))) {
        return $app->json('', 404);
    }

    // determine whether the talkpoint is closed
    $closed = (boolean)$DB->get_field('talkpoint_talkpoint', 'closed', array('id' => $talkpointid), MUST_EXIST);

    // create talkpoint comment model
    require_once __DIR__ . '/../models/talkpoint_comment_model.php';
    $talkpoint_comment_model = new talkpoint_comment_model();
    $talkpoint_comment_model->set_userid($USER->id);

    // get pagination parameters
    $limitfrom = (integer)$request->get('limitfrom');
    $limitnum = (integer)$request->get('limitnum');

    // count and fetch comments
    $total = $talkpoint_comment_model->get_total_by_talkpointid($talkpointid);
    $comments = $talkpoint_comment_model->get_all_by_talkpointid($talkpointid, $limitfrom, $limitnum);

    // for each comment, determine the user profile picture (shouldn't hit the database)
    foreach ($comments as $key => $comment) {
        $comments[$key]['userpicture'] = $OUTPUT->user_picture((object)array(
            'id' => $comment['userid'],
            'picture' => $comment['picture'],
            'firstname' => $comment['firstname'],
            'lastname' => $comment['lastname'],
            'firstnamephonetic' => $comment['firstnamephonetic'],
            'lastnamephonetic' => $comment['lastnamephonetic'],
            'middlename' => $comment['middlename'],
            'alternatename' => $comment['alternatename'],
            'imagealt' => get_string('jsonapi:clicktoplaycomment', $app['plugin'], $comment['userfullname']),
            'email' => $comment['email'],
        ), array(
            'size' => 50,
            'link' => false,
        ));
        foreach (array('picture', 'firstname', 'lastname', 'email') as $exclude_from_json) {
            unset($comments[$key][$exclude_from_json]);
        }
    }

    // return JSON response
    return $app->json((object)array(
        'comments' => $comments,
        'total' => $total,
        'talkpointClosed' => $closed,
    ));
})
->assert('instanceid', '\d+')
->assert('talkpointid', '\d+');

// get one comment
$controller->get('/talkpoint/{instanceid}/{talkpointid}/comment/{commentid}', function ($instanceid, $talkpointid, $commentid) use ($app) {
    global $DB, $USER;
    // require course login
    list($course, $cm) = $app['get_course_and_course_module']($instanceid);
    $app['require_course_login']($course, $cm);

    // see whether the talkpoint actually exists
    if (!$DB->record_exists('talkpoint_talkpoint', array(
        'instanceid' => $instanceid,
        'id' => $talkpointid,
    ))) {
        return $app->json('', 404);
    }

    // see whether the talkpoint comment actually exists
    if (!$DB->record_exists('talkpoint_comment', array(
        'talkpointid' => $talkpointid,
        'id' => $commentid,
    ))) {
        return $app->json('', 404);
    }

    // create talkpoint comment model
    require_once __DIR__ . '/../models/talkpoint_comment_model.php';
    $talkpoint_comment_model = new talkpoint_comment_model();
    $talkpoint_comment_model->set_userid($USER->id);

    // fetch talkpoint comment and return JSON response
    $comment = $talkpoint_comment_model->get($commentid);
    return $app->json($comment);
})
->bind('getcomment')
->assert('instanceid', '\d+')
->assert('talkpointid', '\d+')
->assert('commentid', '\d+');

// create one comment
$controller->post('/talkpoint/{instanceid}/{talkpointid}/comment', function (Request $request, $instanceid, $talkpointid) use ($app) {
    global $CFG, $DB, $USER, $OUTPUT;

    // require course login
    list($course, $cm) = $app['get_course_and_course_module']($instanceid);
    $app['require_course_login']($course, $cm);

    // see whether the talkpoint actually exists
    if (!$DB->record_exists('talkpoint_talkpoint', array(
        'instanceid' => $instanceid,
        'id' => $talkpointid,
    ))) {
        return $app->json('', 404);
    }

    // ensure the user isn't the guest user
    if (isguestuser()) {
        return $app->json(get_string('jsonapi:commentasguestdenied', $app['plugin']), 400);
    }

    // get module context
    $context = context_module::instance($cm->id);

    // ensure the talkpoint isn't closed for comments
    if (!$app['has_capability']('moodle/course:manageactivities', $context)) {
        if ($DB->get_field('talkpoint_talkpoint', 'closed', array(
            'instanceid' => $instanceid,
            'id' => $talkpointid,
        ), MUST_EXIST) == 1) {
            return $app->json(get_string('jsonapi:talkpointclosed', $app['plugin']), 400);
        }
    }

    // create talkpoint comment model
    require_once __DIR__ . '/../models/talkpoint_comment_model.php';
    $talkpoint_comment_model = new talkpoint_comment_model();
    $talkpoint_comment_model->set_userid($USER->id);

    // create comment
    $uploaded = (array)json_decode($request->getContent());
    if (!array_key_exists('textcomment', $uploaded) && !array_key_exists('nimbbguidcomment', $uploaded)) {
        // client error - one or the other must be provided
        return $app->json(get_string('jsonapi:commentmissing', $app['plugin']), 400);
    }
    if (array_key_exists('textcomment', $uploaded) && array_key_exists('nimbbguidcomment', $uploaded)) {
        // client error - both cannot be provided
        return $app->json(get_string('jsonapi:commentambiguous', $app['plugin']), 400);
    }
    if (!array_key_exists('finalfeedback', $uploaded)) {
        $uploaded['finalfeedback'] = false;
    }
    $data = array(
        'talkpointid' => $talkpointid,
        'userid' => $USER->id,
        'textcomment' => isset($uploaded['textcomment']) ? $uploaded['textcomment'] : null,
        'nimbbguidcomment' => isset($uploaded['nimbbguidcomment']) ? $uploaded['nimbbguidcomment'] : null,
        'finalfeedback' => $uploaded['finalfeedback'],
    );

    // save talkpoint
    $data = $talkpoint_comment_model->save($data, $app['now']());

    // determine the user profile picture (shouldn't hit the database)
    $data['userpicture'] = $OUTPUT->user_picture((object)array(
        'id' => $data['userid'],
        'picture' => $data['picture'],
        'firstname' => $data['firstname'],
        'lastname' => $data['lastname'],
        'firstnamephonetic' => $data['firstnamephonetic'],
        'lastnamephonetic' => $data['lastnamephonetic'],
        'middlename' => $data['middlename'],
        'alternatename' => $data['alternatename'],
        'imagealt' => get_string('jsonapi:clicktoplaycomment', $app['plugin'], $data['userfullname']),
        'email' => $data['email'],
    ), array(
        'size' => 50,
        'link' => false,
    ));
    foreach (array('picture', 'firstname', 'lastname', 'email', 'firstnamephonetic', 'lastnamephonetic', 'middlename', 'alternatename') as $exclude_from_json) {
        unset($data[$exclude_from_json]);
    }
    $url = $CFG->wwwroot . SLUG . $app['url_generator']->generate('getcomment', array(
        'instanceid' => $instanceid,
        'talkpointid' => $talkpointid,
        'commentid' => $data['id'],
    ));

    // return JSON response
    return $app->json($data, 201, array(
        'Location' => $url,
    ));
})
->assert('instanceid', '\d+')
->assert('talkpointid', '\d+');

// update one comment
$controller->put('/talkpoint/{instanceid}/{talkpointid}/comment/{commentid}', function (Request $request, $instanceid, $talkpointid, $commentid) use ($app) {
    global $DB, $USER, $OUTPUT;

    // require course login
    list($course, $cm) = $app['get_course_and_course_module']($instanceid);
    $app['require_course_login']($course, $cm);

    // see whether the talkpoint actually exists
    if (!$DB->record_exists('talkpoint_talkpoint', array(
        'instanceid' => $instanceid,
        'id' => $talkpointid,
    ))) {
        return $app->json('', 404);
    }

    // get module context
    $context = context_module::instance($cm->id);

    // ensure the talkpoint isn't closed for comments
    if (!$app['has_capability']('moodle/course:manageactivities', $context)) {
        if ($DB->get_field('talkpoint_talkpoint', 'closed', array(
            'instanceid' => $instanceid,
            'id' => $talkpointid,
        ), MUST_EXIST) == 1) {
            return $app->json(get_string('jsonapi:talkpointclosed', $app['plugin']), 400);
        }
    }

    // see whether the talkpoint comment actually exists
    if (!$DB->record_exists('talkpoint_comment', array(
        'talkpointid' => $talkpointid,
        'id' => $commentid,
    ))) {
        return $app->json('', 404);
    }

    // create talkpoint comment model and get the comment
    require_once __DIR__ . '/../models/talkpoint_comment_model.php';
    $talkpoint_comment_model = new talkpoint_comment_model();
    $talkpoint_comment_model->set_userid($USER->id);
    $comment = $talkpoint_comment_model->get($commentid);

    // check that the logged in user can either manage activities or is the owner of the comment
    if (!$app['has_capability']('moodle/course:manageactivities', $context) && ($USER->id != $comment['userid'])) {
        return $app->json(get_string('jsonapi:notownerofcomment', $app['plugin']), 403);
    }

    // create comment
    $uploaded = (array)json_decode($request->getContent());
    if (!array_key_exists('textcomment', $uploaded) && !array_key_exists('nimbbguidcomment', $uploaded)) {
        // client error - one or the other must be provided
        return $app->json(get_string('jsonapi:commentmissing', $app['plugin']), 400);
    }
    if (array_key_exists('textcomment', $uploaded) && array_key_exists('nimbbguidcomment', $uploaded)) {
        // client error - both cannot be provided
        return $app->json(get_string('jsonapi:commentambiguous', $app['plugin']), 400);
    }
    $data = array(
        'id' => $commentid,
        'talkpointid' => $talkpointid,
        'textcomment' => isset($uploaded['textcomment']) ? $uploaded['textcomment'] : null,
        'nimbbguidcomment' => isset($uploaded['nimbbguidcomment']) ? $uploaded['nimbguidcomment'] : null,
        'finalfeedback' => $comment['finalfeedback'],
    );

    // save talkpoint
    $data = $talkpoint_comment_model->save($data, $app['now']());

    // determine the user profile picture (shouldn't hit the database)
    $data['userpicture'] = $OUTPUT->user_picture((object)array(
        'id' => $data['userid'],
        'picture' => $data['picture'],
        'firstname' => $data['firstname'],
        'lastname' => $data['lastname'],
        'firstnamephonetic' => $data['firstnamephonetic'],
        'lastnamephonetic' => $data['lastnamephonetic'],
        'middlename' => $data['middlename'],
        'alternatename' => $data['alternatename'],
        'imagealt' => get_string('jsonapi:clicktoplaycomment', $app['plugin'], $data['userfullname']),
        'email' => $data['email'],
    ), array(
        'size' => 50,
        'link' => false,
    ));
    foreach (array('picture', 'firstname', 'lastname', 'email', 'firstnamephonetic', 'lastnamephonetic', 'middlename', 'alternatename') as $exclude_from_json) {
        unset($data[$exclude_from_json]);
    }

    // return JSON response
    return $app->json($data, 200);
})
->assert('instanceid', '\d+')
->assert('talkpointid', '\d+')
->assert('commentid', '\d+');

// delete one comment
$controller->delete('/talkpoint/{instanceid}/{talkpointid}/comment/{commentid}', function ($instanceid, $talkpointid, $commentid) use ($app) {
    global $DB, $USER;

    // require course login
    list($course, $cm) = $app['get_course_and_course_module']($instanceid);
    $app['require_course_login']($course, $cm);

    // see whether the talkpoint actually exists
    if (!$DB->record_exists('talkpoint_talkpoint', array(
        'instanceid' => $instanceid,
        'id' => $talkpointid,
    ))) {
        return $app->json('', 404);
    }

    // get module context
    $context = context_module::instance($cm->id);

    // ensure the talkpoint isn't closed for comments
    if (!$app['has_capability']('moodle/course:manageactivities', $context)) {
        if ($DB->get_field('talkpoint_talkpoint', 'closed', array(
            'instanceid' => $instanceid,
            'id' => $talkpointid,
        ), MUST_EXIST) == 1) {
            return $app->json(get_string('jsonapi:talkpointclosed', $app['plugin']), 400);
        }
    }

    // see whether the talkpoint comment actually exists
    if (!$DB->record_exists('talkpoint_comment', array(
        'talkpointid' => $talkpointid,
        'id' => $commentid,
    ))) {
        return $app->json('', 404);
    }

    // create talkpoint comment model
    require_once __DIR__ . '/../models/talkpoint_comment_model.php';
    $talkpoint_comment_model = new talkpoint_comment_model();
    $comment = $talkpoint_comment_model->get($commentid);

    // check that the logged in user can either manage activities or is the owner of the comment
    if (!$app['has_capability']('moodle/course:manageactivities', $context) && ($USER->id != $comment['userid'])) {
        return $app->json(get_string('jsonapi:notownerofcomment', $app['plugin']), 403);
    }

    // delete the comment
    $talkpoint_comment_model->delete($commentid);
    return $app->json('', 204);
})
->assert('instanceid', '\d+')
->assert('talkpointid', '\d+')
->assert('commentid', '\d+');

// return the controller
return $controller;
