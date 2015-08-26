<?php

// use the Client and Request classes
use Symfony\Component\HttpKernel\Client;
use Symfony\Component\HttpFoundation\Request;

defined('MOODLE_INTERNAL') || die();

class mod_talkpoint_v1_api_test extends advanced_testcase {

    /**
     * @var Silex\Application
     */
    protected $_app;

    /**
     * setUp
     */
    public function setUp() {
        global $CFG;

        if (!defined('SLUG')) {
            define('SLUG', '');
        }

        // create Silex app
        $this->_app = require __DIR__ . '/../app.php';
        $this->_app['debug'] = true;
        $this->_app['exception_handler']->disable();

        // add middleware to work around Moodle expecting non-empty $_GET or $_POST
        $this->_app->before(function (Request $request) {
            if (empty($_GET) && 'GET' == $request->getMethod()) {
                $_GET = $request->query->all();
            }
            if (empty($_POST) && 'POST' == $request->getMethod()) {
                $_POST = $request->request->all();
            }
        });

        // reset the database after each test
        $this->resetAfterTest();
    }

    /**
     * tearDown
     */
    public function tearDown() {
        $_GET = array();
        $_POST = array();
    }

    /**
     * sets up a single user in a single talkpoint
     * @global moodle_database $DB
     * @return array
     */
    protected function _setup_single_user_in_single_talkpoint() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a course module
        $talkpoint = $this->getDataGenerator()->create_module('talkpoint', array(
            'course' => $course->id,
        ));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // create some talkpoints within the talkpoint activity
        $times = array(
            mktime( 9, 0, 0, 11, 5, 2013),
            mktime( 7, 0, 0, 11, 5, 2013),
            mktime( 6, 0, 0, 11, 5, 2013),
            mktime( 8, 0, 0, 11, 5, 2013),
            mktime(10, 0, 0, 11, 5, 2013),
        );
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_talkpoint' => array(
                array('id', 'instanceid', 'userid', 'title', 'uploadedfile', 'nimbbguid', 'mediatype', 'closed', 'timecreated', 'timemodified'),
                array(1, $talkpoint->id, $user->id, 'Talkpoint 001', '001.mp4', null, 'file', 0, $times[0], $times[0]),
                array(2, $talkpoint->id, $user->id, 'Talkpoint 002', '002.mp4', null, 'file', 0, $times[1], $times[1]),
                array(3, $talkpoint->id, $user->id, 'Talkpoint 003', 'Chrome_ImF.mp4', null, 'file', 0, $times[2], $times[2]),
                array(4, $talkpoint->id, $user->id, 'Talkpoint 004', '004.mp4', null, 'file', 1, $times[3], $times[3]),
                array(5, $talkpoint->id, 2, 'Talkpoint 005', '005.mp4', null, 'file', 0, $times[4], $times[4]),
            ),
        )));

        // login the user
        $this->setUser($user);

        // return the objects
        return array($user, $course, $talkpoint);
    }

    /**
     * tests the route that fetches all talkpoints within a particular talkpoint activity
     */
    public function test_talkpoints_route() {
        list(, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        // request page 2 of the collection (2 items per page)
        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/talkpoint/' . $talkpoint->id, array(
            'limitfrom' => 2,
            'limitnum' => 2,
        ), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isOk());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // get the JSON content
        $content = json_decode($client->getResponse()->getContent());
        $this->assertEquals(5, $content->total);
        $this->assertCount(2, $content->talkpoints);

        // first item
        $this->assertEquals(4, $content->talkpoints[0]->id);
        $this->assertEquals('Talkpoint 004', $content->talkpoints[0]->title);

        // second item
        $this->assertEquals(2, $content->talkpoints[1]->id);
        $this->assertEquals('Talkpoint 002', $content->talkpoints[1]->title);
    }

    /**
     * tests the route that requests a single talkpoint
     */
    public function test_talkpoint_get_route() {
        list(, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        // request talkpoint 4
        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/talkpoint/' . $talkpoint->id . '/4', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isOk());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // get the JSON content
        $content = json_decode($client->getResponse()->getContent());
        $this->assertEquals(4, $content->id);
        $this->assertEquals('Talkpoint 004', $content->title);
        $this->assertTrue($content->is_owner);
    }

    /**
     * tests the route that requests a single talkpoint when the requested talkpoint doesn't exist
     */
    public function test_talkpoint_get_route_non_existent_talkpoint() {
        list(, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        // request talkpoint 999
        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/talkpoint/' . $talkpoint->id . '/999', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isNotFound());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));
    }

    /**
     * tests the route that deletes a single talkpoint
     */
    public function test_talkpoint_delete_route() {
        list(, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        // request talkpoint 4
        $client = new Client($this->_app);
        $client->request('DELETE', '/api/v1/talkpoint/' . $talkpoint->id . '/4', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertEquals(204, $client->getResponse()->getStatusCode());
    }

    /**
     * tests the route that deletes a single talkpoint when the requested talkpoint doesn't exist
     */
    public function test_talkpoint_delete_route_non_existent_talkpoint() {
        list(, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        // request talkpoint 999
        $client = new Client($this->_app);
        $client->request('DELETE', '/api/v1/talkpoint/' . $talkpoint->id . '/999', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isNotFound());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));
    }

    /**
     * tests the route that deletes a single talkpoint when the user doesn't own the requested talkpoint
     */
    public function test_talkpoint_delete_route_not_owner_of_talkpoint() {
        list(, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        // request talkpoint 5
        $client = new Client($this->_app);
        $client->request('DELETE', '/api/v1/talkpoint/' . $talkpoint->id . '/5', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isForbidden());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // get the JSON content
        $this->assertTrue($client->getResponse()->isClientError());
        $this->assertEquals(403, $client->getResponse()->getStatusCode());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));
        $this->assertEquals(get_string('jsonapi:notowneroftalkpoint', $this->_app['plugin']), json_decode($client->getResponse()->getContent()));
    }

    /**
     * tests the route that fetches all comments within a particular talkpoint within a particular talkpoint activity
     */
    public function test_comments_route() {
        list($user, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        // seed the database with a comment
        $now = time();
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_comment' => array(
                array('id', 'talkpointid', 'userid', 'textcomment', 'nimbbguidcomment', 'finalfeedback', 'timecreated', 'timemodified'),
                array(1, 2, $user->id, 'That is really awesome!', null, 0, $now + 1, $now + 1),
                array(2, 2, $user->id, 'That is totally awesome!', null, 0, $now + 2, $now + 2),
                array(3, 2, 2, null, 'ABC123', 1, $now + 3, $now + 3),
            ),
        )));

        // request the comments
        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/talkpoint/' . $talkpoint->id . '/2/comment', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isOk());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // get the JSON content
        $content = json_decode($client->getResponse()->getContent());
        $this->assertEquals(3, $content->total);
        $this->assertCount(3, $content->comments);

        // second item
        $this->assertEquals(2, $content->comments[1]->id);
        $this->assertEquals('That is totally awesome!', $content->comments[1]->textcomment);
        $this->assertNull($content->comments[1]->nimbbguidcomment);
        $this->assertTrue($content->comments[1]->is_owner);
        $this->assertFalse($content->comments[1]->finalfeedback);

        // first item
        $this->assertEquals(3, $content->comments[0]->id);
        $this->assertNull($content->comments[0]->textcomment);
        $this->assertEquals('ABC123', $content->comments[0]->nimbbguidcomment);
        $this->assertFalse($content->comments[0]->is_owner);
        $this->assertTrue($content->comments[0]->finalfeedback);

        // third item
        $this->assertEquals(1, $content->comments[2]->id);
        $this->assertEquals('That is really awesome!', $content->comments[2]->textcomment);
        $this->assertNull($content->comments[2]->nimbbguidcomment);
        $this->assertTrue($content->comments[2]->is_owner);
        $this->assertFalse($content->comments[2]->finalfeedback);
    }

    /**
     * tests fetching an existing comment
     */
    public function test_comment_get_route() {
        list($user, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        // seed the database with a comment
        $now = time();
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_comment' => array(
                array('id', 'talkpointid', 'userid', 'textcomment', 'nimbbguidcomment', 'finalfeedback', 'timecreated', 'timemodified'),
                array(1, 3, $user->id, 'That is really awesome!', null, 0, $now, $now + 1),
            ),
        )));

        // request the comment
        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/talkpoint/' . $talkpoint->id . '/3/comment/1', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));

        // check the response
        $this->assertTrue($client->getResponse()->isOk());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // get the JSON content
        $content = json_decode($client->getResponse()->getContent());
        $this->assertEquals(array(
            'id' => 1,
            'talkpointid' => 3,
            'userid' => $user->id,
            'userfullname' => $user->firstname . ' ' . $user->lastname,
            'picture' => 0,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'firstnamephonetic' => $user->firstnamephonetic,
            'lastnamephonetic' => $user->lastnamephonetic,
            'middlename' => $user->middlename,
            'alternatename' => $user->alternatename,
            'email' => $user->email,
            'is_owner' => true,
            'textcomment' => 'That is really awesome!',
            'nimbbguidcomment' => null,
            'finalfeedback' => false,
            'timecreated' => userdate($now),
            'timemodified' => userdate($now + 1),
        ), (array)$content);
    }

    /**
     * tests the route that requests a single comment when the requested comment doesn't exist
     */
    public function test_comment_get_route_with_non_existent_comment() {
        list(, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        // request comment 999
        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/talkpoint/' . $talkpoint->id . '/3/comment/999', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isNotFound());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));
    }

    /**
     * tests creating a new comment
     * @global moodle_database $DB
     */
    public function test_comment_post_route() {
        global $CFG, $DB, $OUTPUT;
        list($user, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        $now = time();
        $this->_app['now'] = $this->_app->protect(function () use ($now) {
            return $now;
        });

        // create a comment to post
        $content = json_encode(array(
            'textcomment' => 'That is really awesome!',
        ));

        // post a new comment
        $client = new Client($this->_app);
        $client->request('POST', '/api/v1/talkpoint/' . $talkpoint->id . '/3/comment', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ), $content);
        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertEquals(201, $client->getResponse()->getStatusCode());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // get the newly created comment
        $comment = (array)$DB->get_record('talkpoint_comment', array('talkpointid' => 3));

        // location
        $location = $client->getResponse()->headers->get('location');
        $this->assertEquals($CFG->wwwroot . SLUG . '/api/v1/talkpoint/' . $talkpoint->id . '/3/comment/' . $comment['id'], $location);

        // DB object
        $comment['userfullname'] = $user->firstname . ' ' . $user->lastname;
        $comment['is_owner'] = true;
        $comment['finalfeedback'] = !empty($comment['finalfeedback']);
        $comment['timecreated'] = userdate($comment['timecreated']);
        $comment['timemodified'] = userdate($comment['timemodified']);
        $comment['userpicture'] = $OUTPUT->user_picture((object)array(
            'id' => $user->id,
            'picture' => $user->picture,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'firstnamephonetic' => $user->firstnamephonetic,
            'lastnamephonetic' => $user->lastnamephonetic,
            'middlename' => $user->middlename,
            'alternatename' => $user->alternatename,
            'imagealt' => get_string('jsonapi:clicktoplaycomment', $this->_app['plugin'], $comment['userfullname']),
            'email' => $user->email,
        ), array(
            'size' => 50,
            'link' => false,
        ));
        $this->assertEquals((array)json_decode($client->getResponse()->getContent()), $comment);
        $this->assertEquals(array(
            'id' => $comment['id'],
            'talkpointid' => 3,
            'userid' => $user->id,
            'textcomment' => 'That is really awesome!',
            'nimbbguidcomment' => null,
            'finalfeedback' => false,
            'timecreated' => userdate($now),
            'timemodified' => userdate($now),
            'userfullname' => $comment['userfullname'],
            'userpicture' => $comment['userpicture'],
            'is_owner' => $comment['is_owner'],
        ), $comment);
    }

    /**
     * tests trying to create a new comment but specifying a non-existent talkpoint to comment on
     */
    public function test_comment_post_route_with_non_existent_talkpoint() {
        list(, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        $now = time();
        $this->_app['now'] = $this->_app->protect(function () use ($now) {
            return $now;
        });

        // create a comment to post
        $content = json_encode(array(
            'textcomment' => 'That is really awesome!',
        ));

        // post a new comment
        $client = new Client($this->_app);
        $client->request('POST', '/api/v1/talkpoint/' . $talkpoint->id . '/999/comment', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ), $content);
        $this->assertTrue($client->getResponse()->isNotFound());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));
    }

    /**
     * tests trying to create a new comment but specifying a talkpoint that's closed for further comments
     */
    public function test_comment_post_route_with_closed_talkpoint() {
        list(, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        $now = time();
        $this->_app['now'] = $this->_app->protect(function () use ($now) {
            return $now;
        });

        // create a comment to post
        $content = json_encode(array(
            'textcomment' => 'That is really awesome!',
        ));

        // post a new comment
        $client = new Client($this->_app);
        $client->request('POST', '/api/v1/talkpoint/' . $talkpoint->id . '/4/comment', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ), $content);
        $this->assertTrue($client->getResponse()->isClientError());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));
        $this->assertEquals(get_string('jsonapi:talkpointclosed', $this->_app['plugin']), json_decode($client->getResponse()->getContent()));
    }

    /**
    * tests trying to add a new comment as a guest user is not permitted (and results in a redirect to the talkpoint activity page)
    * @global moodle_database $DB
    */
    public function test_comment_post_route_as_guest() {
        global $DB;

        list(, $course, $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        // login as guest
        $this->setGuestUser();

        // set the instance of the 'guest' enrolment plugin to enabled
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_ENABLED, array(
            'courseid' => $course->id,
            'enrol' => 'guest',
        ));

        // create a comment to post
        $content = json_encode(array(
            'textcomment' => 'That is really awesome!',
        ));

        // post a new comment
        $client = new Client($this->_app);
        $client->request('POST', '/api/v1/talkpoint/' . $talkpoint->id . '/3/comment', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ), $content);
        $this->assertTrue($client->getResponse()->isClientError());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));
        $this->assertEquals(get_string('jsonapi:commentasguestdenied', $this->_app['plugin']), json_decode($client->getResponse()->getContent()));
    }

    /**
     * tests creating a new comment as final feedback
     * @global moodle_database $DB
     */
    public function test_comment_post_route_with_final_feedback() {
        global $CFG, $DB;
        list($user, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        $now = time();
        $this->_app['now'] = $this->_app->protect(function () use ($now) {
            return $now;
        });

        // create a comment to post
        $content = json_encode(array(
            'textcomment' => 'That is all folks.',
            'finalfeedback' => true,
        ));

        // post a new comment
        $client = new Client($this->_app);
        $client->request('POST', '/api/v1/talkpoint/' . $talkpoint->id . '/3/comment', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ), $content);
        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertEquals(201, $client->getResponse()->getStatusCode());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // get the newly created comment
        $comment = (array)$DB->get_record('talkpoint_comment', array('talkpointid' => 3));

        $location = $client->getResponse()->headers->get('location');
        $this->assertEquals($CFG->wwwroot . SLUG . '/api/v1/talkpoint/' . $talkpoint->id . '/3/comment/' . $comment['id'], $location);
        $this->assertEquals(array(
            'id' => $comment['id'],
            'talkpointid' => 3,
            'userid' => $user->id,
            'textcomment' => 'That is all folks.',
            'nimbbguidcomment' => null,
            'finalfeedback' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ), $comment);

        // ensure the talkpoint is now closed for comments
        $this->assertEquals(1, $DB->get_field('talkpoint_talkpoint', 'closed', array('id' => 3), MUST_EXIST));
    }

    /**
     * tests creating a new comment when both text and nimbbguid feedback was given
     */
    public function test_comment_post_route_with_both_text_and_nimbbguid_feedback() {
        list(, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        $now = time();
        $this->_app['now'] = $this->_app->protect(function () use ($now) {
            return $now;
        });

        // create a comment to post
        $content = json_encode(array(
            'textcomment' => 'That is all folks.',
            'nimbbguidcomment' => 'ABC123',
        ));

        // post a new comment
        $client = new Client($this->_app);
        $client->request('POST', '/api/v1/talkpoint/' . $talkpoint->id . '/3/comment', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ), $content);
        $this->assertTrue($client->getResponse()->isClientError());
        $this->assertEquals(400, $client->getResponse()->getStatusCode());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));
        $this->assertEquals(get_string('jsonapi:commentambiguous', $this->_app['plugin']), json_decode($client->getResponse()->getContent()));
    }

    /**
     * tests creating a new comment when both text and nimbbguid feedback was given
     */
    public function test_comment_post_route_with_neither_text_nor_nimbbguid_feedback() {
        list(, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        $now = time();
        $this->_app['now'] = $this->_app->protect(function () use ($now) {
            return $now;
        });

        // create a comment to post
        $content = json_encode(array());

        // post a new comment
        $client = new Client($this->_app);
        $client->request('POST', '/api/v1/talkpoint/' . $talkpoint->id . '/3/comment', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ), $content);
        $this->assertTrue($client->getResponse()->isClientError());
        $this->assertEquals(400, $client->getResponse()->getStatusCode());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));
        $this->assertEquals(get_string('jsonapi:commentmissing', $this->_app['plugin']), json_decode($client->getResponse()->getContent()));
    }

    /**
     * tests updating an existing comment
     * (should only be able to do this if an admin or owner of the comment)
     * @global moodle_database $DB
     */
    public function test_comment_put_route() {
        global $DB, $OUTPUT;
        list($user, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        $now = time();
        $this->_app['now'] = $this->_app->protect(function () use ($now) {
            return $now;
        });

        // seed the database with a comment
        $now = time();
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_comment' => array(
                array('id', 'talkpointid', 'userid', 'textcomment', 'nimbbguidcomment', 'finalfeedback', 'timecreated', 'timemodified'),
                array(1, 3, $user->id, 'That is really awesome!', null, 0, $now, $now),
            ),
        )));

        // create a comment to put
        $content = json_encode(array(
            'textcomment' => 'That is really awesome! But please fix the broken thing.',
        ));

        // put an existing comment
        $client = new Client($this->_app);
        $client->request('PUT', '/api/v1/talkpoint/' . $talkpoint->id . '/3/comment/1', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ), $content);
        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // get the updated comment
        $comment = (array)$DB->get_record('talkpoint_comment', array('id' => 1));
        $comment['userfullname'] = $user->firstname . ' ' . $user->lastname;
        $comment['is_owner'] = true;
        $comment['finalfeedback'] = !empty($comment['finalfeedback']);
        $comment['timecreated'] = userdate($comment['timecreated']);
        $comment['timemodified'] = userdate($comment['timemodified']);
        $comment['userpicture'] = $OUTPUT->user_picture((object)array(
            'id' => $user->id,
            'picture' => $user->picture,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'firstnamephonetic' => $user->firstnamephonetic,
            'lastnamephonetic' => $user->lastnamephonetic,
            'middlename' => $user->middlename,
            'alternatename' => $user->alternatename,
            'imagealt' => get_string('jsonapi:clicktoplaycomment', $this->_app['plugin'], $comment['userfullname']),
            'email' => $user->email,
        ), array(
            'size' => 50,
            'link' => false,
        ));
        $this->assertEquals((array)json_decode($client->getResponse()->getContent()), $comment);
        $this->assertEquals(array(
            'id' => 1,
            'talkpointid' => 3,
            'userid' => $user->id,
            'textcomment' => 'That is really awesome! But please fix the broken thing.',
            'nimbbguidcomment' => null,
            'finalfeedback' => false,
            'timecreated' => userdate($now),
            'timemodified' => userdate($now),
            'userfullname' => $comment['userfullname'],
            'userpicture' => $comment['userpicture'],
            'is_owner' => $comment['is_owner'],
        ), $comment);
    }

    /**
     * tests trying to update an existing comment but specifying a non-existent talkpoint
     */
    public function test_comment_put_route_with_non_existent_talkpoint() {
        list($user, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        $now = time();
        $this->_app['now'] = $this->_app->protect(function () use ($now) {
            return $now;
        });

        // seed the database with a comment
        $now = time();
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_comment' => array(
                array('id', 'talkpointid', 'userid', 'textcomment', 'nimbbguidcomment', 'finalfeedback', 'timecreated', 'timemodified'),
                array(1, 3, $user->id, 'That is really awesome!', null, 0, $now, $now),
            ),
        )));

        // create a comment to put
        $content = json_encode(array(
            'textcomment' => 'That is really awesome! But please fix that broken thing.',
        ));

        // put an existing comment
        $client = new Client($this->_app);
        $client->request('PUT', '/api/v1/talkpoint/' . $talkpoint->id . '/999/comment/1', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ), $content);
        $this->assertTrue($client->getResponse()->isNotFound());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));
    }

    /**
     * tests trying to update an existing comment but specifying a non-existent comment
     */
    public function test_comment_put_route_with_non_existent_comment() {
        list($user, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        $now = time();
        $this->_app['now'] = $this->_app->protect(function () use ($now) {
            return $now;
        });

        // seed the database with a comment
        $now = time();
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_comment' => array(
                array('id', 'talkpointid', 'userid', 'textcomment', 'nimbbguidcomment', 'finalfeedback', 'timecreated', 'timemodified'),
                array(1, 3, $user->id, 'That is really awesome!', null, 0, $now, $now),
            ),
        )));

        // create a comment to put
        $content = json_encode(array(
            'textcomment' => 'That is really awesome! But please fix that broken thing',
        ));

        // put an existing comment
        $client = new Client($this->_app);
        $client->request('PUT', '/api/v1/talkpoint/' . $talkpoint->id . '/3/comment/999', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ), $content);
        $this->assertTrue($client->getResponse()->isNotFound());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));
    }

    /**
     * tests trying to update an existing comment but specifying a talkpoint that's closed for comments
     */
    public function test_comment_put_route_with_closed_talkpoint() {
        list($user, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        $now = time();
        $this->_app['now'] = $this->_app->protect(function () use ($now) {
            return $now;
        });

        // seed the database with a comment
        $now = time();
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_comment' => array(
                array('id', 'talkpointid', 'userid', 'textcomment', 'nimbbguidcomment', 'finalfeedback', 'timecreated', 'timemodified'),
                array(1, 4, $user->id, 'That is really awesome!', null, 0, $now, $now),
            ),
        )));

        // create a comment to put
        $content = json_encode(array(
            'textcomment' => 'That is really awesome! But please fix that broken thing.',
        ));

        // put an existing comment
        $client = new Client($this->_app);
        $client->request('PUT', '/api/v1/talkpoint/' . $talkpoint->id . '/4/comment/1', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ), $content);
        $this->assertTrue($client->getResponse()->isClientError());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));
        $this->assertEquals(get_string('jsonapi:talkpointclosed', $this->_app['plugin']), json_decode($client->getResponse()->getContent()));
    }

    /**
     * tests trying to change the 'final feedback' flag when updating a comment has no effect
     * @global moodle_database $DB
     */
    public function test_comment_put_route_with_changed_final_feedback_flag() {
        global $DB;
        list($user, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        $now = time();
        $this->_app['now'] = $this->_app->protect(function () use ($now) {
            return $now;
        });

        // seed the database with a comment (that's not final feedback)
        $now = time();
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_comment' => array(
                array('id', 'talkpointid', 'userid', 'textcomment', 'nimbbguidcomment', 'finalfeedback', 'timecreated', 'timemodified'),
                array(1, 3, $user->id, 'That is really awesome!', null, 0, $now, $now),
            ),
        )));

        // create a comment to put
        $content = json_encode(array(
            'textcomment' => 'That is really awesome! But please fix that broken thing.',
            'finalfeedback' => true,
        ));

        // put an existing comment
        $client = new Client($this->_app);
        $client->request('PUT', '/api/v1/talkpoint/' . $talkpoint->id . '/3/comment/1', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ), $content);
        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // ensure the final feedback flag is its original value (zero) and hasn't been changed
        $this->assertEquals(0, $DB->get_field('talkpoint_comment', 'finalfeedback', array('id' => 1), MUST_EXIST));
    }

    /**
     * tests updating an existing comment when both text and nimbbguid feedback are given
     */
    public function test_comment_put_route_with_both_text_and_nimbbguid_feedback() {
        list($user, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        $now = time();
        $this->_app['now'] = $this->_app->protect(function () use ($now) {
            return $now;
        });

        // seed the database with a comment
        $now = time();
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_comment' => array(
                array('id', 'talkpointid', 'userid', 'textcomment', 'nimbbguidcomment', 'finalfeedback', 'timecreated', 'timemodified'),
                array(1, 3, $user->id, 'That is really awesome!', null, 0, $now, $now),
            ),
        )));

        // create a comment to put
        $content = json_encode(array(
            'textcomment' => 'That is all folks.',
            'nimbbguidcomment' => 'ABC123',
        ));

        // put an existing comment
        $client = new Client($this->_app);
        $client->request('PUT', '/api/v1/talkpoint/' . $talkpoint->id . '/3/comment/1', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ), $content);
        $this->assertTrue($client->getResponse()->isClientError());
        $this->assertEquals(400, $client->getResponse()->getStatusCode());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));
        $this->assertEquals(get_string('jsonapi:commentambiguous', $this->_app['plugin']), json_decode($client->getResponse()->getContent()));
    }

    /**
     * tests updating an existing comment when both text and nimbbguid feedback are given
     */
    public function test_comment_put_route_with_neither_text_nor_nimbbguid_feedback() {
        list($user, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        $now = time();
        $this->_app['now'] = $this->_app->protect(function () use ($now) {
            return $now;
        });

        // seed the database with a comment
        $now = time();
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_comment' => array(
                array('id', 'talkpointid', 'userid', 'textcomment', 'nimbbguidcomment', 'finalfeedback', 'timecreated', 'timemodified'),
                array(1, 3, $user->id, 'That is really awesome!', null, 0, $now, $now),
            ),
        )));

        // create a comment to put
        $content = json_encode(array());

        // put an existing comment
        $client = new Client($this->_app);
        $client->request('PUT', '/api/v1/talkpoint/' . $talkpoint->id . '/3/comment/1', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ), $content);
        $this->assertTrue($client->getResponse()->isClientError());
        $this->assertEquals(400, $client->getResponse()->getStatusCode());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));
        $this->assertEquals(get_string('jsonapi:commentmissing', $this->_app['plugin']), json_decode($client->getResponse()->getContent()));
    }

    /**
     * tests trying to update an existing comment when not the owner of the comment
     */
    public function test_comment_put_route_not_owner_of_comment() {
        list(, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        $now = time();
        $this->_app['now'] = $this->_app->protect(function () use ($now) {
            return $now;
        });

        // create another user
        $user2 = $this->getDataGenerator()->create_user();

        // seed the database with a comment made by a different user
        $now = time();
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_comment' => array(
                array('id', 'talkpointid', 'userid', 'textcomment', 'nimbbguidcomment', 'finalfeedback', 'timecreated', 'timemodified'),
                array(1, 3, $user2->id, 'That is really awesome!', null, 0, $now, $now),
            ),
        )));

        // create a comment to put
        $content = json_encode(array(
            'textcomment' => 'That is really awesome! But please fix the broken thing.',
        ));

        // put an existing comment
        $client = new Client($this->_app);
        $client->request('PUT', '/api/v1/talkpoint/' . $talkpoint->id . '/3/comment/1', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ), $content);
        $this->assertTrue($client->getResponse()->isClientError());
        $this->assertEquals(403, $client->getResponse()->getStatusCode());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));
        $this->assertEquals(get_string('jsonapi:notownerofcomment', $this->_app['plugin']), json_decode($client->getResponse()->getContent()));
    }

    /**
     * tests trying to update an existing comment when logged in as admin
     * @global moodle_database $DB
     */
    public function test_comment_put_route_as_admin_not_owner_of_comment() {
        global $DB, $USER;
        list($user, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        $now = time();
        $this->_app['now'] = $this->_app->protect(function () use ($now) {
            return $now;
        });

        // login the admin user
        $this->setAdminUser();
        $USER->email = 'admin@into.uk.com';
        $this->assertEquals(2, $USER->id);

        // seed the database with a comment made by a different user
        $now = time();
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_comment' => array(
                array('id', 'talkpointid', 'userid', 'textcomment', 'nimbbguidcomment', 'finalfeedback', 'timecreated', 'timemodified'),
                array(1, 1, $user->id, 'That is really awesome!', null, 0, $now, $now),
            ),
        )));

        // create a comment to put
        $content = json_encode(array(
            'textcomment' => 'That is really awesome! But please fix the broken thing.',
        ));

        // put an existing comment
        $client = new Client($this->_app);
        $client->request('PUT', '/api/v1/talkpoint/' . $talkpoint->id . '/1/comment/1', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ), $content);
        $this->assertTrue($client->getResponse()->isOk());
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // ensure the user recorded as who made the original comment didn't change (to the user who made the edit)
        $this->assertEquals($user->id, $DB->get_field('talkpoint_comment', 'userid', array('id' => 1), MUST_EXIST));
    }

    /**
     * tests deleting an existing comment
     * (should only be able to do this if an admin or owner of the comment)
     * @global moodle_database $DB
     */
    public function test_comment_delete_route() {
        global $DB;
        list($user, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        $now = time();
        $this->_app['now'] = $this->_app->protect(function () use ($now) {
            return $now;
        });

        // seed the database with a comment
        $now = time();
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_comment' => array(
                array('id', 'talkpointid', 'userid', 'textcomment', 'nimbbguidcomment', 'finalfeedback', 'timecreated', 'timemodified'),
                array(1, 3, $user->id, 'That is really awesome!', null, 0, $now, $now),
            ),
        )));

        // delete an existing comment
        $client = new Client($this->_app);
        $client->request('DELETE', '/api/v1/talkpoint/' . $talkpoint->id . '/3/comment/1', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertEquals(204, $client->getResponse()->getStatusCode());

        // ensure the comment no longer exists
        $this->assertFalse($DB->record_exists('talkpoint_comment', array('id' => 1)));
    }

    /**
     * tests trying to delete an existing comment but specifying a non-existent talkpoint
     */
    public function test_comment_delete_route_with_non_existent_talkpoint() {
        list($user, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        $now = time();
        $this->_app['now'] = $this->_app->protect(function () use ($now) {
            return $now;
        });

        // seed the database with a comment
        $now = time();
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_comment' => array(
                array('id', 'talkpointid', 'userid', 'textcomment', 'nimbbguidcomment', 'finalfeedback', 'timecreated', 'timemodified'),
                array(1, 3, $user->id, 'That is really awesome!', null, 0, $now, $now),
            ),
        )));

        // delete an existing comment
        $client = new Client($this->_app);
        $client->request('DELETE', '/api/v1/talkpoint/' . $talkpoint->id . '/999/comment/1', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isNotFound());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));
    }

    /**
     * tests trying to delete an existing comment but specifying a non-existent comment
     */
    public function test_comment_delete_route_with_non_existent_comment() {
        list($user, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        $now = time();
        $this->_app['now'] = $this->_app->protect(function () use ($now) {
            return $now;
        });

        // seed the database with a comment
        $now = time();
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_comment' => array(
                array('id', 'talkpointid', 'userid', 'textcomment', 'nimbbguidcomment', 'finalfeedback', 'timecreated', 'timemodified'),
                array(1, 3, $user->id, 'That is really awesome!', null, 0, $now, $now),
            ),
        )));

        // delete an existing comment
        $client = new Client($this->_app);
        $client->request('DELETE', '/api/v1/talkpoint/' . $talkpoint->id . '/3/comment/999', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isNotFound());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));
    }

    /**
     * tests trying to delete an existing comment but specifying a talkpoint that's closed for comments
     */
    public function test_comment_delete_route_with_closed_talkpoint() {
        list($user, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        $now = time();
        $this->_app['now'] = $this->_app->protect(function () use ($now) {
            return $now;
        });

        // seed the database with a comment
        $now = time();
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_comment' => array(
                array('id', 'talkpointid', 'userid', 'textcomment', 'nimbbguidcomment', 'finalfeedback', 'timecreated', 'timemodified'),
                array(1, 4, $user->id, 'That is really awesome!', null, 0, $now, $now),
            ),
        )));

        // delete an existing comment
        $client = new Client($this->_app);
        $client->request('DELETE', '/api/v1/talkpoint/' . $talkpoint->id . '/4/comment/1', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isClientError());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));
        $this->assertEquals(get_string('jsonapi:talkpointclosed', $this->_app['plugin']), json_decode($client->getResponse()->getContent()));
    }

    /**
     * tests deleting a comment that's been left as final feedback reopens the talkpoint for further comments
     * @global moodle_database $DB
     */
    public function test_comment_delete_route_reopens_talkpoint() {
        global $DB;
        list($user, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        $now = time();
        $this->_app['now'] = $this->_app->protect(function () use ($now) {
            return $now;
        });

        // skip capability check by having the below always return true (normal users cannot delete comments on a closed talkpoint)
        $this->_app['has_capability'] = $this->_app->protect(function ($capability, $context) {
            return true;
        });

        // seed the database with a couple of comments, one of which is the final feedback
        $now = time();
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_comment' => array(
                array('id', 'talkpointid', 'userid', 'textcomment', 'nimbbguidcomment', 'finalfeedback', 'timecreated', 'timemodified'),
                array(1, 4, $user->id, 'That is all, folks!', null, 1, $now, $now),
                array(2, 4, $user->id, 'That is really awesome!', null, 0, $now, $now),
                array(3, 4, $user->id, 'That is really awesome!', null, 0, $now, $now),
            ),
        )));

        // delete the existing comment marked as 'finalfeedback'
        $client = new Client($this->_app);
        $client->request('DELETE', '/api/v1/talkpoint/' . $talkpoint->id . '/4/comment/1', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));

        // ensure the talkpoint is now open
        $this->assertEquals(0, $DB->get_field('talkpoint_talkpoint', 'closed', array('id' => 4)));
    }

    /**
     * tests trying to delete an existing comment when not the owner of the comment
     */
    public function test_comment_delete_route_not_owner_of_comment() {
        list(, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        $now = time();
        $this->_app['now'] = $this->_app->protect(function () use ($now) {
            return $now;
        });

        // create another user
        $user2 = $this->getDataGenerator()->create_user();

        // seed the database with a comment made by a different user
        $now = time();
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_comment' => array(
                array('id', 'talkpointid', 'userid', 'textcomment', 'nimbbguidcomment', 'finalfeedback', 'timecreated', 'timemodified'),
                array(1, 3, $user2->id, 'That is really awesome!', null, 0, $now, $now),
            ),
        )));

        // delete an existing comment
        $client = new Client($this->_app);
        $client->request('DELETE', '/api/v1/talkpoint/' . $talkpoint->id . '/3/comment/1', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isClientError());
        $this->assertEquals(403, $client->getResponse()->getStatusCode());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));
        $this->assertEquals(get_string('jsonapi:notownerofcomment', $this->_app['plugin']), json_decode($client->getResponse()->getContent()));
    }

}
