<?php

use Functional as F;

// use the Client and Request classes
use Symfony\Component\HttpKernel\Client;
use Symfony\Component\HttpFoundation\Request;

defined('MOODLE_INTERNAL') || die();

class mod_talkpoint_web_test extends advanced_testcase {

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
        $this->_app = require __DIR__ . '/../app.php';
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
     * sets up a single user in a single talkpoint
     * @global moodle_database $DB
     * @param string $state ('open' or 'closed')
     * @return array
     */
    protected function _setup_single_user_in_single_talkpoint($state = 'open') {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a course module
        $talkpoint = $this->getDataGenerator()->create_module('talkpoint', array(
            'name' => 'Talkpoint activity name',
            'course' => $course->id,
            'closed' => $state == 'closed' ? 1 : 0,
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
     * seeds the database with groups and groups members (for testing separate groups)
     * @return array
     */
    protected function _seed_groups_and_groups_members() {
        // create course and module
        $course = $this->getDataGenerator()->create_course([
            'groupmode'      => SEPARATEGROUPS,
            'groupmodeforce' => true,
        ]);
        $module = $this->getDataGenerator()->create_module('talkpoint', [
            'course' => $course->id,
        ]);

        // create users and groups
        list($user1a, $user1b, $user2a, $user2b, $user3a) = F\map([1, 2, 3, 4, 5], function ($_) use ($course) {
            $user = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
            return $user;
        });
        list($group1, $group2) = F\map([1, 2], function ($_) use ($course) {
            return $this->getDataGenerator()->create_group([
                'courseid' => $course->id,
            ]);
        });

        // assign group membership
        $this->getDataGenerator()->create_group_member([
            'userid' => $user1a->id,
            'groupid' => $group1->id,
        ]);
        $this->getDataGenerator()->create_group_member([
            'userid' => $user1b->id,
            'groupid' => $group1->id,
        ]);
        $this->getDataGenerator()->create_group_member([
            'userid' => $user2a->id,
            'groupid' => $group2->id,
        ]);
        $this->getDataGenerator()->create_group_member([
            'userid' => $user2b->id,
            'groupid' => $group2->id,
        ]);

        // rebuild course cache
        rebuild_course_cache($course->id);

        // some times
        $times = [
            mktime( 9, 0, 0, 11, 5, 2013),
            mktime( 7, 0, 0, 11, 5, 2013),
            mktime( 6, 0, 0, 11, 5, 2013),
            mktime( 8, 0, 0, 11, 5, 2013),
        ];

        // load dataset
        $this->loadDataSet($this->createArrayDataSet([
            'talkpoint_talkpoint' => [
                ['id', 'instanceid', 'userid', 'title', 'uploadedfile', 'nimbbguid', 'mediatype', 'closed', 'timecreated', 'timemodified'],
                [1, $module->id, $user1a->id, 'Talkpoint 001a', 'foo.jpg', null, 'file', 0, $times[0], $times[0]],
                [2, $module->id, $user1b->id, 'Talkpoint 001b', 'foo.jpg', null, 'file', 0, $times[1], $times[1]],
                [3, $module->id, $user2a->id, 'Talkpoint 002a', 'foo.jpg', null, 'file', 0, $times[2], $times[2]],
                [4, $module->id, $user2b->id, 'Talkpoint 002b', 'foo.jpg', null, 'file', 0, $times[3], $times[3]],
                [5, $module->id, $user3a->id, 'Talkpoint 003a', 'foo.jpg', null, 'file', 0, $times[3], $times[3]],
            ],
        ]));

        // return seeded data
        return [$module, $user1a, $user1b, $user2a, $user2b, $user3a, $group1, $group2];
    }

    /**
     * tests the Silex 'Imagine' service provider
     */
    public function test_imagine() {
        $this->assertInstanceOf('Imagine\Gd\Imagine', $this->_app['imagine']);
    }

    /**
     * tests a non-existent route
     * @expectedException Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function test_non_existent_route() {
        $client = new Client($this->_app);
        $client->request('GET', '/does_not_exist');
    }

    /**
     * tests the instances route that shows all activity instances (i.e. course modules) in a certain course
     * @global moodle_database $DB
     */
    public function test_instances_route() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // create a handful of modules within the course
        foreach (range(1, 5) as $i) {
            $module = $this->getDataGenerator()->create_module('talkpoint', array(
                'course' => $course->id,
            ));
        }

        // login the user
        $this->setUser($user);

        // request the page
        $client = new Client($this->_app);
        $client->request('GET', '/instances/' . $course->id);
        $this->assertTrue($client->getResponse()->isOk());

        // check the page content
        foreach (range(1, 5) as $i) {
            $this->assertContains('Talkpoint ' . $i, $client->getResponse()->getContent());
        }
        $this->assertNotContains('Talkpoint 6', $client->getResponse()->getContent());
    }

    /**
     * tests the 'byinstanceid' route that lets you view a talkpoint by instance id (as opposed to course module id)
     */
    public function test_byinstanceid_route() {
        list(, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        // request the page
        $client = new Client($this->_app);
        $client->request('GET', '/' . $talkpoint->id);
        $this->assertTrue($client->getResponse()->isOk());

        $this->assertContains('<h2>Talkpoint activity name</h2>', $client->getResponse()->getContent());
    }

    /**
     * tests the 'bycmid' route that lets you view a talkpoint by course module id
     */
    public function test_bycmid_route() {
        global $CFG;
        list(, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        // request the page
        $client = new Client($this->_app);
        $client->request('GET', '/cmid/' . $talkpoint->cmid);
        $url = $CFG->wwwroot . SLUG . $this->_app['url_generator']->generate('byinstanceid', array(
            'id' => $talkpoint->id,
        ));
        $this->assertTrue($client->getResponse()->isRedirect($url));
    }

    /**
     * tests serving up a partial
     */
    public function test_partials_1() {
        $client = new Client($this->_app);
        $client->request('GET', '/partials/talkpointListItem.twig');
        $this->assertTrue($client->getResponse()->isOk());
    }

    /**
     * tests serving up a partial when none exists
     */
    public function test_partials_2() {
        $client = new Client($this->_app);
        $client->request('GET', '/partials/does_not_exist.twig');
        $this->assertTrue($client->getResponse()->isNotFound());
        $this->assertContains(get_string('storedfilecannotread', 'error'), $client->getResponse()->getContent());
    }

    /**
     * tests serving up a particular talkpoint
     */
    public function test_talkpoint_route() {
        global $CFG;
        list($user, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        // current time
        $now = time();

        // spit a dummy existing file
        check_dir_exists($CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/1/');
        copy(__DIR__ . '/img/dancer180x139.jpg', $CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/1/foo.jpg');

        // create a talkpoint
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_talkpoint' => array(
                array('id', 'instanceid', 'userid', 'title', 'uploadedfile', 'nimbbguid', 'mediatype', 'closed', 'timecreated', 'timemodified'),
                array(1, $talkpoint->id, $user->id, 'Talkpoint 001', 'foo.jpg', null, 'file', 0, $now, $now),
            ),
        )));

        // request the page
        $client = new Client($this->_app);
        $client->request('GET', '/talkpoint/1');
        $this->assertTrue($client->getResponse()->isOk());
    }

    /**
     * tests that it's not possible to request a particular talkpoint which is not visible due to separate groups
     * @expectedException dml_missing_record_exception
     */
    public function test_talkpoint_route_separate_groups_invisible() {
        global $CFG;

        list($module, $user1a, , , ,) = $this->_seed_groups_and_groups_members();
        $this->setUser($user1a);

        // a talkpoint created by a user not currently in user1a's groups
        $three = 3;

        // spit a dummy existing file
        check_dir_exists($CFG->dataroot . '/into/mod_talkpoint/' . $module->id . "/{$three}/");
        copy(__DIR__ . '/img/dancer180x139.jpg', $CFG->dataroot . '/into/mod_talkpoint/' . $module->id . "/{$three}/foo.jpg");

        // request the page
        $client = new Client($this->_app);
        $client->request('GET', '/talkpoint/' . $three);
    }

    /**
     * tests that it is possible to request a particular talkpoint which is visible when in separate groups mode
     */
    public function test_talkpoint_route_separate_groups_visible() {
        global $CFG;

        list($module, $user1a, , , ,) = $this->_seed_groups_and_groups_members();
        $this->setUser($user1a);

        // a talkpoint created by a user currently in user1a's groups
        $two = 2;

        // spit a dummy existing file
        check_dir_exists($CFG->dataroot . '/into/mod_talkpoint/' . $module->id . "/{$two}/");
        copy(__DIR__ . '/img/dancer180x139.jpg', $CFG->dataroot . '/into/mod_talkpoint/' . $module->id . "/{$two}/foo.jpg");

        // request the page
        $client = new Client($this->_app);
        $client->request('GET', '/talkpoint/' . $two);
        $this->assertTrue($client->getResponse()->isOk());
    }

    /**
     * tests that admin can request a particular talkpoint which would otherwise be invisible due to separate groups
     */
    public function test_talkpoint_route_separate_groups_as_admin() {
        global $CFG;

        list($module, , , , ,) = $this->_seed_groups_and_groups_members();
        $this->setAdminUser();

        // any talkpoint
        $three = 3;

        // spit a dummy existing file
        check_dir_exists($CFG->dataroot . '/into/mod_talkpoint/' . $module->id . "/{$three}/");
        copy(__DIR__ . '/img/dancer180x139.jpg', $CFG->dataroot . '/into/mod_talkpoint/' . $module->id . "/{$three}/foo.jpg");

        // request the page
        $client = new Client($this->_app);
        $client->request('GET', '/talkpoint/' . $three);
        $this->assertTrue($client->getResponse()->isOk());
    }

    /**
     * tests that requesting a particular talkpoint for which there are videos to convert shows the talkpoint
     * but with an alert saying the talkpoint won't be visible until its video has been converted
     * @global moodle_database $DB
     */
    public function test_talkpoint_route_has_videos_to_convert_as_owner() {
        global $CFG;
        list($user, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        // current time
        $now = time();

        // copy the .webm file in the upload directory
        check_dir_exists($CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/1/');
        copy(__DIR__ . '/video/Chrome_ImF.webm', $CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/1/foo.webm');

        // create a talkpoint
        $this->loadDataSet($this->createArrayDataSet([
            'talkpoint_talkpoint' => [
                ['id', 'instanceid', 'userid', 'title', 'uploadedfile', 'nimbbguid', 'mediatype', 'closed', 'timecreated', 'timemodified'],
                [1, $talkpoint->id, $user->id, 'Talkpoint 001', 'foo.webm', null, 'file', 0, $now, $now],
            ],
            'talkpoint_video_conversion' => [
                ['talkpointid', 'src', 'dst', 'is_converting', 'timecreated'],
                [1, 'foo.webm', 'foo.webm.mp4', 0, $now],
            ],
        ]));

        // request the page
        $client = new Client($this->_app);
        $client->request('GET', '/talkpoint/1');

        // check response content contains alert
        $content = $client->getResponse()->getContent();
        $this->assertContains(get_string('videostoconvert1', $this->_app['plugin']), $content);
        $this->assertContains(get_string('videostoconvert2', $this->_app['plugin']), $content);
    }

    /**
     * tests that requesting a particular talkpoint for which there are videos to convert redirects
     * @global moodle_database $DB
     */
    public function test_talkpoint_route_has_videos_to_convert_not_owner() {
        global $CFG, $DB;
        list($user, $course, $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        // create, enrol and login a different user to the one who created the talkpoint
        $another_user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($another_user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));
        $this->setUser($another_user);

        // current time
        $now = time();

        // copy the .webm file in the upload directory
        check_dir_exists($CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/1/');
        copy(__DIR__ . '/video/Chrome_ImF.webm', $CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/1/foo.webm');

        // create a talkpoint
        $this->loadDataSet($this->createArrayDataSet([
            'talkpoint_talkpoint' => [
                ['id', 'instanceid', 'userid', 'title', 'uploadedfile', 'nimbbguid', 'mediatype', 'closed', 'timecreated', 'timemodified'],
                [1, $talkpoint->id, $user->id, 'Talkpoint 001', 'foo.webm', null, 'file', 0, $now, $now],
            ],
            'talkpoint_video_conversion' => [
                ['talkpointid', 'src', 'dst', 'is_converting', 'timecreated'],
                [1, 'foo.webm', 'foo.webm.mp4', 0, $now],
            ],
        ]));

        // request the page
        $client = new Client($this->_app);
        $client->request('GET', '/talkpoint/1');

        // check redirects
        $url = $CFG->wwwroot . SLUG . $this->_app['url_generator']->generate('byinstanceid', ['id' => $talkpoint->id]);
        $this->assertTrue($client->getResponse()->isRedirect($url));
    }

    /**
     * tests adding a new talkpoint to an existing talkpoint activity
     * @global moodle_database $DB
     */
    public function test_add_new_talkpoint_route() {
        global $CFG, $DB;
        list(, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        // spit a dummy existing file
        check_dir_exists($CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/temp/');
        file_put_contents($CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/temp/mod_talkpoint_web_test.txt', 'dummy contents');

        // request the page
        $client = new Client($this->_app);
        $crawler = $client->request('GET', '/' . $talkpoint->id . '/add');
        $this->assertTrue($client->getResponse()->isOk());

        // post some data
        $form = $crawler->selectButton(get_string('viewyourtalkpoint', $this->_app['plugin']))->form();
        $client->submit($form, array(
            'form[title]' => 'Title 001',
            'form[uploadedfile]' => 'mod_talkpoint_web_test.txt',
            'form[mediatype]' => 'file',
        ));

        $created = $DB->get_record('talkpoint_talkpoint', array('instanceid' => $talkpoint->id));
        $url = $CFG->wwwroot . SLUG . $this->_app['url_generator']->generate('talkpoint', array(
            'id' => $created->id,
        ));
        $this->assertTrue($client->getResponse()->isRedirect($url));
    }

    /**
     * tests trying to add a new talkpoint as a guest user is not permitted (and results in a redirect to the talkpoint activity page)
     * @global moodle_database $DB
     */
    public function test_add_new_talkpoint_as_guest() {
        global $CFG, $DB;

        // login as guest
        $this->setGuestUser();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // set the instance of the 'guest' enrolment plugin to enabled
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_ENABLED, array(
            'courseid' => $course->id,
            'enrol' => 'guest',
        ));

        // create a course module
        $talkpoint = $this->getDataGenerator()->create_module('talkpoint', array(
            'course' => $course->id,
            'closed' => 0,
        ));

        // request the page
        $client = new Client($this->_app);
        $client->request('GET', '/' . $talkpoint->id . '/add');

        // ensure we cannot add a new talkpoint
        $url = $CFG->wwwroot . SLUG . $this->_app['url_generator']->generate('byinstanceid', array(
            'id' => $talkpoint->id,
        ));
        $this->assertTrue($client->getResponse()->isRedirect($url));
    }

    /**
     * tests trying to add a new talkpoint to a closed talkpoint activity redirects to the talkpoint activity page
     */
    public function test_add_new_talkpoint_route_to_closed_talkpoint() {
        global $CFG;
        list(, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint('closed');

        // request the page
        $client = new Client($this->_app);
        $client->request('GET', '/' . $talkpoint->id . '/add');

        // ensure the user got redirected
        $url = $CFG->wwwroot . SLUG . $this->_app['url_generator']->generate('byinstanceid', array(
            'id' => $talkpoint->id,
        ));
        $this->assertTrue($client->getResponse()->isRedirect($url));
    }

    /**
     * tests trying to add a new talkpoint to a non-existent talkpoint activity
     * @expectedException dml_missing_record_exception
     */
    public function test_add_new_talkpoint_to_non_existent_activity() {
        $client = new Client($this->_app);
        $client->request('GET', '/999/add');
    }

    /**
     * tests editing an existing talkpoint in an existing talkpoint activity
     * @global moodle_database $DB
     */
    public function test_edit_existing_talkpoint_route() {
        global $CFG, $DB;
        list($user, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        // current time
        $now = time();

        // create a talkpoint
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_talkpoint' => array(
                array('id', 'instanceid', 'userid', 'title', 'uploadedfile', 'nimbbguid', 'mediatype', 'closed', 'timecreated', 'timemodified'),
                array(1, $talkpoint->id, $user->id, 'Talkpoint 001', 'mod_talkpoint_web_test.txt', null, 'file', 0, $now, $now),
            ),
        )));

        // request the page
        $client = new Client($this->_app);
        $crawler = $client->request('GET', '/' . $talkpoint->id . '/edit/1');
        $this->assertTrue($client->getResponse()->isOk());

        // spit a dummy existing file
        check_dir_exists($CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/temp/');
        check_dir_exists($CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/1/');
        file_put_contents($CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/1/mod_talkpoint_web_test.txt', 'dummy contents');
        file_put_contents($CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/temp/mod_talkpoint_web_test_2.txt', 'dummy contents');

        // post some data
        $form = $crawler->selectButton(get_string('viewyourtalkpoint', $this->_app['plugin']))->form();
        $client->submit($form, array(
            'form[title]' => 'Talkpoint 001a',
            'form[uploadedfile]' => 'mod_talkpoint_web_test_2.txt',
            'form[mediatype]' => 'file',
        ));
        $url = $CFG->wwwroot . SLUG . $this->_app['url_generator']->generate('talkpoint', array(
            'id' => 1,
        ));
        $this->assertTrue($client->getResponse()->isRedirect($url));

        // ensure the title got changed as expected
        $this->assertEquals('Talkpoint 001a', $DB->get_field('talkpoint_talkpoint', 'title', array('id' => 1)));

        // ensure the new file exists in the expected location
        $this->assertFileNotExists($CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/1/mod_talkpoint_web_test.txt');
        $this->assertStringEqualsFile($CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/1/mod_talkpoint_web_test_2.txt', 'dummy contents');
    }

    /**
     * tests trying to edit a non-existing talkpoint to a non-existent talkpoint activity
     * @expectedException dml_missing_record_exception
     */
    public function test_edit_non_existent_talkpoint_in_non_existent_activity() {
        $client = new Client($this->_app);
        $client->request('GET', '/999/edit/999');
    }

    /**
     * tests trying to edit a non-existing talkpoint
     * @expectedException dml_missing_record_exception
     */
    public function test_edit_non_existent_talkpoint() {
        list(, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();
        $client = new Client($this->_app);
        $client->request('GET', '/' . $talkpoint->id . '/edit/999');
    }

    /**
     * tests trying to edit an existing talkpoint when the user isn't the owner (or an admin)
     */
    public function test_edit_existing_talkpoint_when_not_owner_or_admin() {
        global $CFG;
        list(, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        // current time
        $now = time();

        // create a talkpoint
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_talkpoint' => array(
                array('id', 'instanceid', 'userid', 'title', 'uploadedfile', 'nimbbguid', 'mediatype', 'closed', 'timecreated', 'timemodified'),
                array(1, $talkpoint->id, 2, 'Talkpoint 001', 'mod_talkpoint_web_test.txt', null, 'file', 0, $now, $now),
            ),
        )));

        // request the page
        $client = new Client($this->_app);
        $client->request('GET', '/' . $talkpoint->id . '/edit/1');
        $url = $CFG->wwwroot . SLUG . $this->_app['url_generator']->generate('talkpoint', array(
            'id' => 1,
        ));
        $this->assertTrue($client->getResponse()->isRedirect($url));
    }

    /**
     * tests editing an existing talkpoint in an existing talkpoint activity (without uploading a new file)
     * @global moodle_database $DB
     */
    public function test_edit_existing_talkpoint_no_change_of_file() {
        global $CFG, $DB;
        list($user, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        // current time
        $now = time();

        // create a talkpoint
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_talkpoint' => array(
                array('id', 'instanceid', 'userid', 'title', 'uploadedfile', 'nimbbguid', 'mediatype', 'closed', 'timecreated', 'timemodified'),
                array(1, $talkpoint->id, $user->id, 'Talkpoint 001', 'mod_talkpoint_web_test.txt', null, 'file', 0, $now, $now),
            ),
        )));

        // request the page
        $client = new Client($this->_app);
        $crawler = $client->request('GET', '/' . $talkpoint->id . '/edit/1');
        $this->assertTrue($client->getResponse()->isOk());

        // spit a dummy existing file
        check_dir_exists($CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/1');
        file_put_contents($CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/1/mod_talkpoint_web_test.txt', 'dummy contents');

        // post some data
        $form = $crawler->selectButton(get_string('viewyourtalkpoint', $this->_app['plugin']))->form();
        $client->submit($form, array(
            'form[title]' => 'Talkpoint 001a',
            'form[mediatype]' => 'file',
        ));
        $url = $CFG->wwwroot . SLUG . $this->_app['url_generator']->generate('talkpoint', array(
            'id' => 1,
        ));
        $this->assertTrue($client->getResponse()->isRedirect($url));

        // ensure the title got changed as expected
        $this->assertEquals('Talkpoint 001a', $DB->get_field('talkpoint_talkpoint', 'title', array('id' => 1)));

        // ensure the old file still exists in the expected location
        $this->assertStringEqualsFile($CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/1/mod_talkpoint_web_test.txt', 'dummy contents');
    }

    /**
     * test that when editing a current Talkpoint with a file returns the filetype of that file
     */
    public function test_edit_existing_talkpoint_uploaded_file_returns_correct_filetype1() {
        global $CFG;
        list($user, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        // current time
        $now = time();

        // create a talkpoint
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_talkpoint' => array(
                array('id', 'instanceid', 'userid', 'title', 'uploadedfile', 'nimbbguid', 'mediatype', 'closed', 'timecreated', 'timemodified'),
                array(1, $talkpoint->id, $user->id, 'Talkpoint 001', 'foo.mp4', null, 'file', 0, $now, $now),
            ),
        )));


        check_dir_exists($CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/1');
        copy(__DIR__ . '/video/Chrome_ImF.mp4', $CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/1/foo.mp4');

        // request the page
        $client = new Client($this->_app);
        $client->request('GET', '/' . $talkpoint->id . '/edit/1');
        $this->assertTrue($client->getResponse()->isOk());

        $filepath = $CFG->wwwroot . '/uploadedfile/1';
        $this->assertRegExp("<video.*ng-show=\"true\".*src=\"$filepath\".*controls>", $client->getResponse()->getContent());
    }

    /**
     * test that when editing a current Talkpoint with a file returns the filetype of that file
     */
    public function test_edit_existing_talkpoint_uploaded_file_returns_correct_filetype2() {
        global $CFG;
        list($user, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        // current time
        $now = time();

        // create a talkpoint
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_talkpoint' => array(
                array('id', 'instanceid', 'userid', 'title', 'uploadedfile', 'nimbbguid', 'mediatype', 'closed', 'timecreated', 'timemodified'),
                array(1, $talkpoint->id, $user->id, 'Talkpoint 001', 'mod_talkpoint_web_test.txt', null, 'file', 0, $now, $now),
            ),
        )));

        // request the page
        $client = new Client($this->_app);
        $client->request('GET', '/' . $talkpoint->id . '/edit/1');
        $this->assertTrue($client->getResponse()->isOk());

        // spit a dummy existing file
        check_dir_exists($CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/1');
        file_put_contents($CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/1/mod_talkpoint_web_test.txt', 'dummy contents');

        $filepath = $CFG->wwwroot . '/uploadedfile/1';
        $this->assertRegExp("<a.*ng-show=\"true\".*href=\"$filepath\".*>", $client->getResponse()->getContent());

    }

    /**
     * tests the route that lets you download a previously uploaded file
     */
    public function test_uploadedfile_route() {
        global $CFG;
        list($user, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        // current time
        $now = time();

        // create a talkpoint
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_talkpoint' => array(
                array('id', 'instanceid', 'userid', 'title', 'uploadedfile', 'nimbbguid', 'mediatype', 'closed', 'timecreated', 'timemodified'),
                array(1, $talkpoint->id, $user->id, 'Talkpoint 001', 'mod_talkpoint_web_test.txt', null, 'file', 0, $now, $now),
            ),
        )));

        // spit a dummy existing file
        check_dir_exists($CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/1');
        file_put_contents($CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/1/mod_talkpoint_web_test.txt', 'dummy contents');

        // request the file
        $client = new Client($this->_app);
        $client->request('GET', '/uploadedfile/1');
        $this->assertTrue($client->getResponse()->isOk());
    }

    /**
     * tests the route that serves up a file
     */
    public function test_servefile_route() {
        global $CFG;
        list($user, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        // current time
        $now = time();

        // create a talkpoint
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_talkpoint' => array(
                array('id', 'instanceid', 'userid', 'title', 'uploadedfile', 'nimbbguid', 'mediatype', 'closed', 'timecreated', 'timemodified'),
                array(1, $talkpoint->id, $user->id, 'Talkpoint 001', 'mod_talkpoint_web_test.txt', null, 'file', 0, $now, $now),
            ),
        )));

        // spit a dummy existing file
        check_dir_exists($CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/1');
        file_put_contents($CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/1/mod_talkpoint_web_test.txt', 'dummy contents');

        // request the file
        $client = new Client($this->_app);
        $client->request('GET', '/servefile/1');
        $this->assertTrue($client->getResponse()->isOk());
    }

    /**
     * tests the route that serves up a video file
     */
    public function test_servevideofile_route_for_m4v() {
        global $CFG;
        list($user, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        // current time
        $now = time();

        // create a talkpoint
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_talkpoint' => array(
                array('id', 'instanceid', 'userid', 'title', 'uploadedfile', 'nimbbguid', 'mediatype', 'closed', 'timecreated', 'timemodified'),
                array(1, $talkpoint->id, $user->id, 'Talkpoint 001', 'Chrome_ImF.webm', null, 'file', 0, $now, $now),
            ),
        )));

        // spit some video files (one that corresponds to the file that was uploaded, another to the format that'll be requested)
        check_dir_exists($CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/1');
        copy(__DIR__ . '/video/Chrome_ImF.webm', $CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/1/foo.webm');
        copy(__DIR__ . '/video/Chrome_ImF.mp4', $CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/1/foo.mp4');

        // request the file
        $client = new Client($this->_app);
        $client->request('GET', '/servevideofile/1/m4v');
        $this->assertTrue($client->getResponse()->isOk());
    }

    /**
     * tests the route that lets you download a previously uploaded file when that file is missing
     */
    public function test_non_existent_uploadedfile() {
        list($user, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        // current time
        $now = time();

        // create a talkpoint
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_talkpoint' => array(
                array('id', 'instanceid', 'userid', 'title', 'uploadedfile', 'nimbbguid', 'mediatype', 'closed', 'timecreated', 'timemodified'),
                array(1, $talkpoint->id, $user->id, 'Talkpoint 001', 'mod_talkpoint_web_test.txt', null, 'file', 0, $now, $now),
            ),
        )));

        // request the file
        $client = new Client($this->_app);
        $client->request('GET', '/uploadedfile/1');
        $this->assertTrue($client->getResponse()->isNotFound());
        $this->assertContains(get_string('storedfilecannotread', 'error'), $client->getResponse()->getContent());
    }

    /**
     * tests the route that serves up a file when that file is missing
     */
    public function test_non_existent_servefile() {
        list($user, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        // current time
        $now = time();

        // create a talkpoint
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_talkpoint' => array(
                array('id', 'instanceid', 'userid', 'title', 'uploadedfile', 'nimbbguid', 'mediatype', 'closed', 'timecreated', 'timemodified'),
                array(1, $talkpoint->id, $user->id, 'Talkpoint 001', 'mod_talkpoint_web_test.txt', null, 'file', 0, $now, $now),
            ),
        )));

        // request the file
        $client = new Client($this->_app);
        $client->request('GET', '/servefile/1');
        $this->assertTrue($client->getResponse()->isNotFound());
        $this->assertContains(get_string('storedfilecannotread', 'error'), $client->getResponse()->getContent());
    }

    /**
     * tests the route that lets you download a previously uploaded file when you don't own the file
     */
    public function test_no_access_to_uploadedfile() {
        global $CFG;
        list(, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        // current time
        $now = time();

        // create a talkpoint
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_talkpoint' => array(
                array('id', 'instanceid', 'userid', 'title', 'uploadedfile', 'nimbbguid', 'mediatype', 'closed', 'timecreated', 'timemodified'),
                array(1, $talkpoint->id, 2, 'Talkpoint 001', 'mod_talkpoint_web_test.txt', null, 'file', 0, $now, $now),
            ),
        )));

        // request the file
        $client = new Client($this->_app);
        $client->request('GET', '/uploadedfile/1');
        $url = $CFG->wwwroot . SLUG . $this->_app['url_generator']->generate('talkpoint', array(
            'id' => 1,
        ));
        $this->assertTrue($client->getResponse()->isRedirect($url));
    }

    /**
     * tests mp4 upload
     * @global moodle_database $DB
     */
    public function test_mp4_upload() {
        global $DB, $CFG;
        list(, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        // set different file constraints
        $this->_app['file_constraints'] = array(
            'maxSize' => '20M',
            'mimeTypes' => array(
                'video/mp4',
            ),
        );

        check_dir_exists($CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/temp/');
        check_dir_exists($CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/1');
        copy(__DIR__ . '/video/Chrome_ImF.mp4', $CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/temp/foo.mp4');

        // request the page
        $client = new Client($this->_app);
        $crawler = $client->request('GET', '/' . $talkpoint->id . '/add');
        $this->assertTrue($client->getResponse()->isOk());

        // post some data
        $form = $crawler->selectButton(get_string('viewyourtalkpoint', $this->_app['plugin']))->form();
        $client->submit($form, array(
            'form[title]' => 'Title 001',
            'form[uploadedfile]' => 'foo.mp4',
            'form[mediatype]' => 'file',
        ));

        $created = $DB->get_record('talkpoint_talkpoint', array('instanceid' => $talkpoint->id));
        $url = $CFG->wwwroot . SLUG . $this->_app['url_generator']->generate('talkpoint', array(
            'id' => $created->id,
        ));
        $this->assertTrue($client->getResponse()->isRedirect($url));

        // ensure the new file exists in the expected location
        $this->assertFileExists($CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/' . $created->id . '/foo.mp4');

        $this->assertTrue($DB->record_exists('talkpoint_talkpoint', [
            'id' => $created->id,
            'title' => 'Title 001',
            'uploadedfile' => 'foo.mp4',
            'nimbbguid' => null,
            'mediatype' => 'file',
        ]));
    }

    /**
     * tests ogv upload
     * @global moodle_database $DB
     */
    public function test_ogv_upload() {
        global $DB, $CFG;
        list(, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        // set different file constraints
        $this->_app['file_constraints'] = array(
            'maxSize' => '20M',
            'mimeTypes' => array(
                'application/ogg',
            ),
        );

        check_dir_exists($CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/temp/');
        check_dir_exists($CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/1');
        copy(__DIR__ . '/video/Chrome_ImF.ogv', $CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/temp/foo.ogv');

        // request the page
        $client = new Client($this->_app);
        $crawler = $client->request('GET', '/' . $talkpoint->id . '/add');
        $this->assertTrue($client->getResponse()->isOk());

        // post some data
        $form = $crawler->selectButton(get_string('viewyourtalkpoint', $this->_app['plugin']))->form();
        $client->submit($form, array(
            'form[title]' => 'Title 001',
            'form[uploadedfile]' => 'foo.ogv',
            'form[mediatype]' => 'file',
        ));
        $created = $DB->get_record('talkpoint_talkpoint', array('instanceid' => $talkpoint->id));
        $url = $CFG->wwwroot . SLUG . $this->_app['url_generator']->generate('talkpoint', array(
            'id' => $created->id,
        ));
        $this->assertTrue($client->getResponse()->isRedirect($url));

        // ensure the new file exists in the expected location
        $this->assertFileExists($CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/' . $created->id . '/foo.ogv');

        // ensure a video conversion has been queued
        $this->assertCount(1, $DB->get_records('talkpoint_video_conversion', [
            'talkpointid' => $created->id,
            'src' => 'foo.ogv',
            'dst' => 'foo.ogv.mp4',
            'is_converting' => 0,
        ]));

        $this->assertTrue($DB->record_exists('talkpoint_talkpoint', [
            'id' => $created->id,
            'title' => 'Title 001',
            'uploadedfile' => 'foo.ogv',
            'nimbbguid' => null,
            'mediatype' => 'file',
        ]));
    }

    /**
     * tests webm upload
     * @global moodle_database $DB
     */
    public function test_webm_upload() {
        global $DB, $CFG;
        list(, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        // set different file constraints
        $this->_app['file_constraints'] = array(
            'maxSize' => '20M',
            'mimeTypes' => array(
                'video/webm',
                'application/octet-stream',
            ),
        );

        check_dir_exists($CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/temp/');
        check_dir_exists($CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/1');
        copy(__DIR__ . '/video/Chrome_ImF.webm', $CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/temp/foo.webm');

        // request the page
        $client = new Client($this->_app);
        $crawler = $client->request('GET', '/' . $talkpoint->id . '/add');
        $this->assertTrue($client->getResponse()->isOk());

        // post some data
        $form = $crawler->selectButton(get_string('viewyourtalkpoint', $this->_app['plugin']))->form();
        $client->submit($form, array(
            'form[title]' => 'Title 001',
            'form[uploadedfile]' => 'foo.webm',
            'form[mediatype]' => 'file',
        ));
        $created = $DB->get_record('talkpoint_talkpoint', array('instanceid' => $talkpoint->id));
        $url = $CFG->wwwroot . SLUG . $this->_app['url_generator']->generate('talkpoint', array(
            'id' => $created->id,
        ));
        $this->assertTrue($client->getResponse()->isRedirect($url));

        // ensure the new file exists in the expected location
        $this->assertFileExists($CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/' . $created->id . '/foo.webm');

        // ensure a video conversion has been queued
        $this->assertCount(1, $DB->get_records('talkpoint_video_conversion', [
            'talkpointid' => $created->id,
            'src' => 'foo.webm',
            'dst' => 'foo.webm.mp4',
            'is_converting' => 0,
        ]));

        $this->assertTrue($DB->record_exists('talkpoint_talkpoint', [
            'id' => $created->id,
            'title' => 'Title 001',
            'uploadedfile' => 'foo.webm',
            'nimbbguid' => null,
            'mediatype' => 'file',
        ]));
    }

    /**
     * test adding webcam feed
     * @global moodle_database $DB
     */
    public function test_webcam_upload() {
        global $CFG, $DB;
        list(, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();
        // request the page
        $client = new Client($this->_app);
        $crawler = $client->request('GET', '/' . $talkpoint->id . '/add');
        $this->assertTrue($client->getResponse()->isOk());

        // post some data
        $form = $crawler->selectButton(get_string('viewyourtalkpoint', $this->_app['plugin']))->form();
        $client->submit($form, array(
            'form[title]' => 'Title 001',
            'form[nimbbguid]' => 'ABC123',
            'form[mediatype]' => 'webcam',
        ));
        $created = $DB->get_record('talkpoint_talkpoint', array('instanceid' => $talkpoint->id));
        $url = $CFG->wwwroot . SLUG . $this->_app['url_generator']->generate('talkpoint', array(
            'id' => $created->id,
        ));
        $this->assertTrue($client->getResponse()->isRedirect($url));

        $this->assertTrue($DB->record_exists('talkpoint_talkpoint', [
            'id' => $created->id,
            'title' => 'Title 001',
            'nimbbguid' => 'ABC123',
            'mediatype' => 'webcam',
        ]));
    }

    /**
     * test adding audio feed
     * @global moodle_database $DB
     */
    public function test_audio_upload() {
        global $CFG, $DB;
        list(, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();
        // request the page
        $client = new Client($this->_app);
        $crawler = $client->request('GET', '/' . $talkpoint->id . '/add');
        $this->assertTrue($client->getResponse()->isOk());

        // post some data
        $form = $crawler->selectButton(get_string('viewyourtalkpoint', $this->_app['plugin']))->form();
        $client->submit($form, array(
            'form[title]' => 'Title 001',
            'form[nimbbguid]' => 'ABC123',
            'form[mediatype]' => 'audio',
        ));
        $created = $DB->get_record('talkpoint_talkpoint', array('instanceid' => $talkpoint->id));
        $url = $CFG->wwwroot . SLUG . $this->_app['url_generator']->generate('talkpoint', array(
            'id' => $created->id,
        ));
        $this->assertTrue($client->getResponse()->isRedirect($url));

        $this->assertTrue($DB->record_exists('talkpoint_talkpoint', [
            'id' => $created->id,
            'title' => 'Title 001',
            'nimbbguid' => 'ABC123',
            'mediatype' => 'audio',
        ]));
    }

    /**
     * tests the route that lets you download a temporaryly uploaded file
     */
    public function test_temp_uploaded_route() {
        global $CFG;
        list(, , $talkpoint) = $this->_setup_single_user_in_single_talkpoint();

        check_dir_exists($CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/temp/');
        copy(__DIR__ . '/video/Chrome_ImF.mp4', $CFG->dataroot . '/into/mod_talkpoint/' . $talkpoint->id . '/temp/Chrome_ImF.mp4');

        // request the file
        $client = new Client($this->_app);
        $client->request('GET', '/tempuploaded/' . $talkpoint->id . '/Chrome_ImF.mp4');
        $this->assertTrue($client->getResponse()->isOk());
    }

}
