<?php

use Functional as F;

// use the Client and Request classes
use Symfony\Component\HttpKernel\Client;
use Symfony\Component\HttpFoundation\Request;

defined('MOODLE_INTERNAL') || die();

class mod_talkpoint_web_completion_test extends advanced_testcase {

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

        // set different file constraints
        $this->_app['file_constraints'] = array(
            'maxSize' => '1M',
            'mimeTypes' => array(
                'text/plain',
            ),
        );

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

        // login the user
        $this->setUser($user);

        // return the objects
        return array($user, $course, $talkpoint);
    }

    /**
     * sets up a single talkpoint activity that is completed on creating a talkpoint
     * @global moodle_database $DB
     * @param string $state ('open' or 'closed')
     * @return array
     */
    protected function _setup_talkpoint_complete_on_create($state = 'open') {
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
            'completionview' => COMPLETION_VIEW_NOT_REQUIRED,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completioncreatetalkpoint' => true,
            'completioncommentontalkpoint' => false,
        ));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the user
        $this->setUser($user);

        // return the objects
        return array($user, $course, $talkpoint);
    }

    /**
     * tests the get action marks the talkpoint viewed when configured with COMPLETION_VIEW_REQUIRED
     */
    public function test_get_action_marks_viewed() {
        list($user, $course, $talkpoint) = $this->_setup_talkpoint_complete_on_view();
        $completion_info = new completion_info($course);

        list(, $cm) = $this->_app['get_course_and_course_module']($talkpoint->id);

        // check module is not already completed
        $completion_pre = $completion_info->get_data($cm, false, $user->id);
        $this->assertEquals(COMPLETION_NOT_VIEWED, $completion_pre->viewed);

        // request the page
        $client = new Client($this->_app);
        $client->request('GET', '/' . $talkpoint->id);
        $this->assertTrue($client->getResponse()->isOk());

        $completion_post = $completion_info->get_data($cm, false, $user->id);
        $this->assertEquals(COMPLETION_VIEWED, $completion_post->viewed);
    }

    /**
     * tests the get action does not mark the talkpoint viewed when configured with completioncreatetalkpoint
     */
    public function test_get_action_does_not_mark_viewed() {
        list($user, $course, $talkpoint) = $this->_setup_talkpoint_complete_on_create();
        $completion_info = new completion_info($course);

        list(, $cm) = $this->_app['get_course_and_course_module']($talkpoint->id);

        // check module is not already completed
        $completion_pre = $completion_info->get_data($cm, false, $user->id);
        $this->assertEquals(COMPLETION_NOT_VIEWED, $completion_pre->viewed);

        // request the page
        $client = new Client($this->_app);
        $client->request('GET', '/' . $talkpoint->id);
        $this->assertTrue($client->getResponse()->isOk());

        $completion_post = $completion_info->get_data($cm, false, $user->id);
        $this->assertEquals(COMPLETION_NOT_VIEWED, $completion_post->viewed);
    }

    /**
     * tests adding a new talkpoint to an existing talkpoint activity marks talkpoint activity complete when configured
     * with completioncreatetalkpoint
     */
    public function test_add_new_talkpoint_marks_talkpoint_complete_create() {
        list($user, $course, $talkpoint) = $this->_setup_talkpoint_complete_on_create();
        $completion_info = new completion_info($course);

        // request the page
        $client = new Client($this->_app);
        $crawler = $client->request('GET', '/' . $talkpoint->id . '/add');
        $this->assertTrue($client->getResponse()->isOk());

        // spit a dummy file
        file_put_contents('/tmp/mod_talkpoint_web_test.txt', 'dummy contents');

        // create a dummy file
        $uploadedfile = new Symfony\Component\HttpFoundation\File\UploadedFile(
            '/tmp/mod_talkpoint_web_test.txt',
            'mod_talkpoint_web_test.txt',
            'text/plain'
        );

        list(, $cm) = $this->_app['get_course_and_course_module']($talkpoint->id);

        // check module is not already viewed
        $completion_pre = $completion_info->get_data($cm, false, $user->id);
        $this->assertEquals(COMPLETION_INCOMPLETE, $completion_pre->completionstate);

        // post some data
        $form = $crawler->selectButton(get_string('savechanges'))->form();
        $client->submit($form, array(
            'form[title]' => 'Title 001',
            'form[uploadedfile]' => $uploadedfile,
            'form[mediatype]' => 'file',
        ));

        $completion_post = $completion_info->get_data($cm, false, $user->id);
        $this->assertEquals(COMPLETION_COMPLETE, $completion_post->completionstate);
    }

    /**
     * tests adding a new talkpoint to an existing talkpoint activity does not mark talkpoint complete when configured
     * with COMPLETION_VIEW_REQUIRED
     */
    public function test_add_new_talkpoint_does_not_mark_talkpoint_complete_completion_view_required() {
        list($user, $course, $talkpoint) = $this->_setup_talkpoint_complete_on_view();
        $completion_info = new completion_info($course);

        // request the page
        $client = new Client($this->_app);
        $crawler = $client->request('GET', '/' . $talkpoint->id . '/add');
        $this->assertTrue($client->getResponse()->isOk());

        // spit a dummy file
        file_put_contents('/tmp/mod_talkpoint_web_test.txt', 'dummy contents');

        // create a dummy file
        $uploadedfile = new Symfony\Component\HttpFoundation\File\UploadedFile(
            '/tmp/mod_talkpoint_web_test.txt',
            'mod_talkpoint_web_test.txt',
            'text/plain'
        );

        list(, $cm) = $this->_app['get_course_and_course_module']($talkpoint->id);

        // check module is not already viewed
        $completion_pre = $completion_info->get_data($cm, false, $user->id);
        $this->assertEquals(COMPLETION_INCOMPLETE, $completion_pre->completionstate);

        // post some data
        $form = $crawler->selectButton(get_string('savechanges'))->form();
        $client->submit($form, array(
            'form[title]' => 'Title 001',
            'form[uploadedfile]' => $uploadedfile,
            'form[mediatype]' => 'file',
        ));

        $completion_post = $completion_info->get_data($cm, false, $user->id);
        $this->assertEquals(COMPLETION_INCOMPLETE, $completion_post->completionstate);
    }

}
