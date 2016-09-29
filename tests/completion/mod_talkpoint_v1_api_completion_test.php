<?php

// use the Client and Request classes
use Symfony\Component\HttpKernel\Client;
use Symfony\Component\HttpFoundation\Request;

defined('MOODLE_INTERNAL') || die();

class mod_talkpoint_v1_api_completion_test extends advanced_testcase {

    /**
     * @var Silex\Application
     */
    protected $_app;

    /**
     * setUp
     */
    public function setUp() {
        if (!defined('SLUG')) {
            define('SLUG', '');
        }

        // create Silex app
        $this->_app = require __DIR__ . '/../../app.php';
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
     * sets up a single talkpoint activity that is completed on viewing
     * @global moodle_database $DB
     * @param string $state ('open' or 'closed')
     * @return array
     */
    protected function _setup_talkpoint_complete_on_view($state = 'open') {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course(array(
            'enablecompletion' => true,
        ));

        // create a course module
        $talkpoint = $this->getDataGenerator()->create_module('talkpoint', array(
            'name' => 'Talkpoint activity name',
            'course' => $course->id,
            'closed' => $state == 'closed' ? 1 : 0,
            'completionview' => COMPLETION_VIEW_REQUIRED,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completioncreatetalkpoint' => false,
            'completioncommentontalkpoint' => false,
        ));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // create some talkpoints within the talkpoint activity
        $times = $this->_get_times();

        $this->loadDataSet($this->_get_talkpoint_talkpoints($talkpoint, $user, $times));

        // login the user
        $this->setUser($user);

        // return the objects
        return array($user, $course, $talkpoint);
    }

    /**
     * sets up a single talkpoint activity that is completed on commenting on a talkpoint
     * @global moodle_database $DB
     * @return array
     */
    protected function _setup_talkpoint_complete_on_comment() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();
        $talkpoint_creator_user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course(array(
            'enablecompletion' => true
        ));

        // create a course module
        $talkpoint = $this->getDataGenerator()->create_module('talkpoint', array(
            'course' => $course->id,
            'completionview' => COMPLETION_VIEW_NOT_REQUIRED,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completioncreatetalkpoint' => false,
            'completioncommentontalkpoint' => true,
        ));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // create some talkpoints within the talkpoint activity
        $times = $this->_get_times();

        $this->loadDataSet($this->_get_talkpoint_talkpoints($talkpoint, $talkpoint_creator_user, $times));

        // login the user
        $this->setUser($user);

        // return the objects
        return array($user, $course, $talkpoint);
    }

    /**
     * tests that posting a comment marks the talkpoint activity complete when configured with completioncommentontalkpoint
     */
    public function test_posting_comment_marks_talkpoint_activity_complete() {
        list($user, $course, $talkpoint) = $this->_setup_talkpoint_complete_on_comment();
        $completion_info = new completion_info($course);

        $now = time();
        $this->_app['now'] = $this->_app->protect(function () use ($now) {
            return $now;
        });

        // create a comment to post
        $content = json_encode(array(
            'textcomment' => 'That is really awesome!',
        ));

        list(, $cm) = $this->_app['get_course_and_course_module']($talkpoint->id);

        // check module is not already completed
        $completion_pre = $completion_info->get_data($cm, false, $user->id);
        $this->assertEquals(COMPLETION_INCOMPLETE, $completion_pre->completionstate);

        // post a new comment
        $client = new Client($this->_app);
        $client->request('POST', '/api/v1/talkpoint/' . $talkpoint->id . '/3/comment', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ), $content);
        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertEquals(201, $client->getResponse()->getStatusCode());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        $completion_post = $completion_info->get_data($cm, false, $user->id);
        $this->assertEquals(COMPLETION_COMPLETE, $completion_post->completionstate);
    }

    /**
     * tests that posting a comment does not mark the talkpoint activity complete when configured with COMPLETION_VIEW_REQUIRED
     */
    public function test_posting_comment_does_not_mark_talkpoint_activity_complete() {
        list($user, $course, $talkpoint) = $this->_setup_talkpoint_complete_on_view();
        $completion_info = new completion_info($course);

        $now = time();
        $this->_app['now'] = $this->_app->protect(function () use ($now) {
            return $now;
        });

        // create a comment to post
        $content = json_encode(array(
            'textcomment' => 'That is really awesome!',
        ));

        list(, $cm) = $this->_app['get_course_and_course_module']($talkpoint->id);

        // check module is not already completed
        $completion_pre = $completion_info->get_data($cm, false, $user->id);
        $this->assertEquals(COMPLETION_INCOMPLETE, $completion_pre->completionstate);

        // post a new comment
        $client = new Client($this->_app);
        $client->request('POST', '/api/v1/talkpoint/' . $talkpoint->id . '/3/comment', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ), $content);
        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertEquals(201, $client->getResponse()->getStatusCode());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        $completion_post = $completion_info->get_data($cm, false, $user->id);
        $this->assertEquals(COMPLETION_INCOMPLETE, $completion_post->completionstate);
    }

    /**
     * @return array
     */
    protected function _get_times() {
        return [
            mktime( 9, 0, 0, 11, 5, 2013),
            mktime( 7, 0, 0, 11, 5, 2013),
            mktime( 6, 0, 0, 11, 5, 2013),
            mktime( 8, 0, 0, 11, 5, 2013),
            mktime(10, 0, 0, 11, 5, 2013),
        ];
    }

    /**
     * @param object $talkpoint
     * @param object $user
     * @param array $times
     * @return phpunit_ArrayDataSet
     */
    protected function _get_talkpoint_talkpoints($talkpoint, $user, $times) {
        return $this->createArrayDataSet([
            'talkpoint_talkpoint' => [
                ['id', 'instanceid', 'userid', 'title', 'uploadedfile', 'nimbbguid', 'mediatype', 'closed', 'timecreated', 'timemodified'],
                [1, $talkpoint->id, $user->id, 'Talkpoint 001', '001.mp4', null, 'file', 0, $times[0], $times[0]],
                [2, $talkpoint->id, $user->id, 'Talkpoint 002', '002.mp4', null, 'file', 0, $times[1], $times[1]],
                [3, $talkpoint->id, $user->id, 'Talkpoint 003', 'Chrome_ImF.mp4', null, 'file', 0, $times[2], $times[2]],
                [4, $talkpoint->id, $user->id, 'Talkpoint 004', '004.mp4', null, 'file', 1, $times[3], $times[3]],
                [5, $talkpoint->id, 2, 'Talkpoint 005', '005.mp4', null, 'file', 0, $times[4], $times[4]],
            ],
        ]);
    }

}
