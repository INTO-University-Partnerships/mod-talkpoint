<?php

use Mockery as m;
use Functional as F;

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../models/talkpoint_model.php';

class talkpoint_model_test extends advanced_testcase {

    /**
     * @var talkpoint_model
     */
    protected $_cut;

    /**
     * setUp
     */
    protected function setUp() {
        $this->_cut = new talkpoint_model();
        $this->resetAfterTest();
    }

    /**
     * tearDown
     */
    public function tearDown() {
        m::close();
    }

    /**
     * tests instantiation
     */
    public function test_instantiation() {
        $this->assertInstanceOf('talkpoint_model', $this->_cut);
    }

    /**
     * tests getting talkpoints by instanceid when no talkpoints exist
     * @global moodle_database $DB
     */
    public function test_get_all_by_instanceid_1() {
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('talkpoint', array(
            'course' => $course->id,
        ));
        $talkpoints = $this->_cut->get_all_by_instanceid($module->id);
        $this->assertEquals(array(), $talkpoints);
    }

    /**
     * tests getting talkpoints by instanceid when a few talkpoints exist
     * ensures ordering is by time created descending (i.e. newest first)
     * @global moodle_database $DB
     */
    public function test_get_all_by_instanceid_2() {
        $times = array(
            mktime( 9, 0, 0, 11, 5, 2013),
            mktime( 7, 0, 0, 11, 5, 2013),
            mktime( 6, 0, 0, 11, 5, 2013),
            mktime( 8, 0, 0, 11, 5, 2013),
            mktime(10, 0, 0, 11, 5, 2013),
        );
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('talkpoint', array(
            'course' => $course->id,
        ));
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_talkpoint' => array(
                array('id', 'instanceid', 'userid', 'title', 'uploadedfile', 'nimbbguid', 'mediatype', 'closed', 'timecreated', 'timemodified'),
                array(1, $module->id, 2, 'Talkpoint 001', '', 'ABC123', 'webcam', 0, $times[0], $times[0]),
                array(2, $module->id, 2, 'Talkpoint 002', '', 'DEF456', 'webcam', 0, $times[1], $times[1]),
                array(3, $module->id, 2, 'Talkpoint 003', 'foo.mp4', null, 'file', 0, $times[2], $times[2]),
                array(4, $module->id, 2, 'Talkpoint 004', 'foo.mp4', null, 'file', 0, $times[3], $times[3]),
                array(5, $module->id, 2, 'Talkpoint 005', 'foo.mp4', null, 'file', 0, $times[4], $times[4]),
            ),
        )));
        $this->_cut->set_userid(2);
        $talkpoints = $this->_cut->get_all_by_instanceid($module->id);
        $this->assertCount(5, $talkpoints);
        $this->assertEquals(5, $talkpoints[0]['id']);
        $this->assertEquals(1, $talkpoints[1]['id']);
        $this->assertEquals(4, $talkpoints[2]['id']);
        $this->assertEquals(2, $talkpoints[3]['id']);
        $this->assertEquals(3, $talkpoints[4]['id']);
    }

    /**
     * tests getting viewable talkpoints by instanceid when a few talkpoints exist
     * ensures ordering is by time created descending (i.e. newest first)
     */
    public function test_get_all_viewable_by_instanceid() {
        $times = [
            mktime( 9, 0, 0, 11, 5, 2013),
            mktime( 7, 0, 0, 11, 5, 2013),
            mktime( 6, 0, 0, 11, 5, 2013),
            mktime( 8, 0, 0, 11, 5, 2013),
            mktime(10, 0, 0, 11, 5, 2013),
            mktime(11, 0, 0, 11, 5, 2013),
        ];
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('talkpoint', [
            'course' => $course->id,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->loadDataSet($this->createArrayDataSet([
            'talkpoint_talkpoint' => [
                ['id', 'instanceid', 'userid', 'title', 'uploadedfile', 'nimbbguid', 'mediatype', 'closed', 'timecreated', 'timemodified'],
                [1, $module->id, 2, 'Talkpoint 001', '', 'ABC123', 'webcam', 0, $times[0], $times[0]],
                [2, $module->id, 2, 'Talkpoint 002', '', 'DEF456', 'webcam', 0, $times[1], $times[1]],
                [3, $module->id, 2, 'Talkpoint 003', 'foo.webm', null, 'file', 0, $times[2], $times[2]],
                [4, $module->id, 2, 'Talkpoint 004', 'foo.ogv', null, 'file', 0, $times[3], $times[3]],
                [5, $module->id, 2, 'Talkpoint 005', 'foo.mp4', null, 'file', 0, $times[4], $times[4]],
                [6, $module->id, $user->id, 'Talkpoint 006', 'bar.webm', null, 'file', 0, $times[5], $times[5]],
            ],
            'talkpoint_video_conversion' => [
                ['talkpointid', 'src', 'dst', 'is_converting', 'timecreated'],
                [3, 'foo.webm', 'foo.webm.mp4', 1, $times[2]],
                [4, 'foo.ogv',  'foo.ogv.mp4',  0, $times[3]],
                [6, 'bar.webm', 'bar.webm.mp4', 0, $times[5]],
            ],
        ]));
        $this->_cut->set_userid($user->id);
        $talkpoints = $this->_cut->get_all_viewable_by_instanceid($module->id);
        $this->assertCount(4, $talkpoints);
        $this->assertEquals(6, $talkpoints[0]['id']);
        $this->assertEquals(5, $talkpoints[1]['id']);
        $this->assertEquals(1, $talkpoints[2]['id']);
        $this->assertEquals(2, $talkpoints[3]['id']);
    }

    /**
     * tests getting viewable talkpoints by instanceid when in separate groups mode
     */
    public function test_get_all_viewable_by_instanceid_separate_groups() {
        list($module, $user1a, , , $user2b, $user3a) = $this->_seed_groups_and_groups_members();

        // set group mode
        $this->_cut->set_groupmode(SEPARATEGROUPS);

        // sorting function
        $f = function ($left, $right) {
            if ($left === $right) {
                return 0;
            }
            return $left < $right ? -1 : 1;
        };

        // from user1a's point of view, they should be able to see their own talkpoint and user 1b's talkpoint
        $this->_cut->set_userid($user1a->id);
        $talkpoints = $this->_cut->get_all_viewable_by_instanceid($module->id);
        $this->assertCount(2, $talkpoints);
        $this->assertEquals([1, 2], F\sort(F\pluck($talkpoints, 'id'), $f));

        // from user2b's point of view, they should be able to see their own talkpoint and user 2a's talkpoint
        $this->_cut->set_userid($user2b->id);
        $talkpoints = $this->_cut->get_all_viewable_by_instanceid($module->id);
        $this->assertCount(2, $talkpoints);
        $this->assertEquals([3, 4], F\sort(F\pluck($talkpoints, 'id'), $f));

        // from user3a's point of view, they should only be able to see their own talkpoint
        $this->_cut->set_userid($user3a->id);
        $talkpoints = $this->_cut->get_all_viewable_by_instanceid($module->id);
        $this->assertCount(1, $talkpoints);
        $this->assertEquals(5, F\head($talkpoints)['id']);
    }

    /**
     * tests getting a given talkpoint
     */
    public function test_get() {
        $times = array(
            mktime( 9, 0, 0, 11, 5, 2013),
            mktime( 7, 0, 0, 11, 5, 2013),
            mktime( 6, 0, 0, 11, 5, 2013),
            mktime( 8, 0, 0, 11, 5, 2013),
            mktime(10, 0, 0, 11, 5, 2013),
        );
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('talkpoint', array(
            'course' => $course->id,
        ));
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_talkpoint' => array(
                array('id', 'instanceid', 'userid', 'title', 'uploadedfile', 'nimbbguid', 'mediatype', 'closed', 'timecreated', 'timemodified'),
                array(1, $module->id, 2, 'Talkpoint 001', 'foo.mp4', null, 'file', 0, $times[0], $times[0]),
                array(2, $module->id, 2, 'Talkpoint 002', 'foo.mp4', null, 'file', 0, $times[1], $times[1]),
                array(3, $module->id, 2, 'Talkpoint 003', 'foo.mp4', null, 'file', 0, $times[2], $times[2]),
                array(4, $module->id, 2, 'Talkpoint 004', 'foo.mp4', null, 'file', 0, $times[3], $times[3]),
                array(5, $module->id, 2, 'Talkpoint 005', 'foo.mp4', null, 'file', 0, $times[4], $times[4]),
            ),
        )));
        $this->_cut->set_userid(2);
        $talkpoint = $this->_cut->get(3);
        $this->assertEquals(array(
            'id' => 3,
            'instanceid' => $module->id,
            'userid' => 2,
            'userfullname' => 'Admin User',
            'is_owner' => true,
            'title' => 'Talkpoint 003',
            'uploadedfile' => 'foo.mp4',
            'nimbbguid' => null,
            'mediatype' => 'file',
            'closed' => false,
            'timecreated' => userdate($times[2]),
            'timemodified' => userdate($times[2]),
        ), $talkpoint);
    }

    /**
     * tests trying to get a talkpoint that's visible when in separate groups mode
     */
    public function test_get_separate_groups_visible_talkpoints() {
        list(, $user1a, , $user2a, , $user3a) = $this->_seed_groups_and_groups_members();

        // set user and group mode
        $this->_cut->set_groupmode(SEPARATEGROUPS);

        // user1a should be able to see their own and user1b's talkpoint
        $this->_cut->set_userid($user1a->id);
        F\each([1, 2], function ($id) {
            $talkpoint = $this->_cut->get($id);
            $this->assertEquals($id, $talkpoint['id']);
        });

        // user2a should be able to see their own and user2b's talkpoint
        $this->_cut->set_userid($user2a->id);
        F\each([3, 4], function ($id) {
            $talkpoint = $this->_cut->get($id);
            $this->assertEquals($id, $talkpoint['id']);
        });

        // user3a should only be able to see their own talkpoint
        $this->_cut->set_userid($user3a->id);
        $talkpoint = $this->_cut->get(5);
        $this->assertEquals(5, $talkpoint['id']);
    }

    /**
     * tests trying to get a talkpoint that's not visible when in separate groups mode
     * @expectedException dml_missing_record_exception
     */
    public function test_get_separate_groups_not_visible_talkpoint_3() {
        $this->_test_get_separate_groups_not_visible_talkpoint(3);
    }

    /**
     * tests trying to get a talkpoint that's not visible when in separate groups mode
     * @expectedException dml_missing_record_exception
     */
    public function test_get_separate_groups_not_visible_talkpoint_4() {
        $this->_test_get_separate_groups_not_visible_talkpoint(4);
    }

    /**
     * tests trying to get a talkpoint that's not visible when in separate groups mode
     * @expectedException dml_missing_record_exception
     */
    public function test_get_separate_groups_not_visible_talkpoint_5() {
        $this->_test_get_separate_groups_not_visible_talkpoint(5);
    }

    /**
     * tests trying to get a non-existent talkpoint
     * @expectedException dml_missing_record_exception
     */
    public function test_get_non_existent() {
        $this->_cut->get(1);
    }

    /**
     * tests saving a talkpoint for the first time
     * @global moodle_database $DB
     */
    public function test_save_1() {
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('talkpoint', array(
            'course' => $course->id,
        ));
        $data = array(
            'instanceid' => $module->id,
            'userid' => 2,
            'title' => 'Talkpoint 001',
            'uploadedfile' => 'foo.mp4',
            'mediatype' => 'file',
            'closed' => 0,
        );
        $data = $this->_cut->save($data, time());
        $this->assertArrayHasKey('id', $data);
    }

    /**
     * tests saving a talkpoint for a subsequent time
     * @global moodle_database $DB
     */
    public function test_save_2() {
        global $DB;
        $times = array(
            mktime(9, 0, 0, 11, 5, 2013),
        );
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('talkpoint', array(
            'course' => $course->id,
        ));
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_talkpoint' => array(
                array('id', 'instanceid', 'userid', 'title', 'uploadedfile', 'nimbbguid', 'mediatype', 'closed', 'timecreated', 'timemodified'),
                array(1, $module->id, 2, 'Talkpoint 004', 'foo.mp4', null, 'file', 0, $times[0], $times[0]),
            ),
        )));
        $data = array(
            'instanceid' => $module->id,
            'id' => 1,
            'userid' => 2,
            'title' => 'Talkpoint 004a',
            'uploadedfile' => 'bar.mp4',
            'nimbbguid' => '',
            'mediatype' => 'file',
            'closed' => 0,
        );
        $data = $this->_cut->save($data, time());
        $this->assertGreaterThan($times[0], (integer)$DB->get_field('talkpoint_talkpoint', 'timemodified', array('id' => $data['id'])));
        $this->assertEquals('bar.mp4', $DB->get_field('talkpoint_talkpoint', 'uploadedfile', array('id' => 1)));
    }

    /**
     * tests deleting a talkpoint
     * @global moodle_database $DB
     */
    public function test_delete() {
        global $DB;
        $times = array(
            mktime(9, 0, 0, 11, 5, 2013),
        );
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('talkpoint', array(
            'course' => $course->id,
        ));
        check_dir_exists($this->_cut->get_upload_path() . '/' . $module->id . '/1');
        file_put_contents($this->_cut->get_upload_path() . '/' . $module->id . '/1/foo.txt', 'whatever');
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_talkpoint' => array(
                array('id', 'instanceid', 'userid', 'title', 'uploadedfile', 'nimbbguid', 'mediatype', 'closed', 'timecreated', 'timemodified'),
                array(1, $module->id, 2, 'Talkpoint 004', 'foo.txt', null, 'file', 0, $times[0], $times[0]),
            ),
            'talkpoint_comment' => array(
                array('talkpointid', 'userid', 'finalfeedback', 'timecreated', 'timemodified'),
                array(1, 2, 0, $times[0], $times[0]),
                array(1, 2, 0, $times[0], $times[0]),
                array(1, 2, 0, $times[0], $times[0]),
            ),
            'talkpoint_video_conversion' => array(
                array('talkpointid', 'src', 'dst', 'is_converting', 'timecreated'),
                array(1, 'foo.webm', 'foo.webm.mp4', 0, $times[0]),
                array(1, 'foo.ogv', 'foo.ogv.mp4', 0, $times[0]),
            )
        )));
        $this->assertEquals(1, $DB->count_records('talkpoint_talkpoint', array('id' => 1)));
        $this->assertEquals(3, $DB->count_records('talkpoint_comment', array('talkpointid' => 1)));
        $this->assertEquals(2, $DB->count_records('talkpoint_video_conversion', array('talkpointid' => 1)));
        $this->assertFileExists($this->_cut->get_upload_path() . '/' . $module->id . '/1');
        $this->assertFileExists($this->_cut->get_upload_path() . '/' . $module->id . '/1/foo.txt');
        $this->_cut->delete(1);
        $this->assertFalse($DB->record_exists('talkpoint_talkpoint', array('id' => 1)));
        $this->assertFalse($DB->record_exists('talkpoint_comment', array('talkpointid' => 1)));
        $this->assertFalse($DB->record_exists('talkpoint_video_conversion', array('talkpointid' => 1)));
        $this->assertFileNotExists($this->_cut->get_upload_path() . '/' . $module->id . '/1/foo.txt');
        $this->assertFileNotExists($this->_cut->get_upload_path() . '/' . $module->id . '/1');
    }

    /**
     * tests counting the total number of talkpoints by instance id
     */
    public function test_get_total_by_instanceid() {
        $times = array(
            mktime( 9, 0, 0, 11, 5, 2013),
            mktime( 7, 0, 0, 11, 5, 2013),
            mktime( 6, 0, 0, 11, 5, 2013),
            mktime( 8, 0, 0, 11, 5, 2013),
            mktime(10, 0, 0, 11, 5, 2013),
        );
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('talkpoint', array(
            'course' => $course->id,
        ));
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_talkpoint' => array(
                array('instanceid', 'userid', 'title', 'uploadedfile', 'nimbbguid', 'mediatype', 'closed', 'timecreated', 'timemodified'),
                array($module->id, 2, 'Talkpoint 001', '', 'ABC123', 'webcam', 0, $times[0], $times[0]),
                array($module->id, 2, 'Talkpoint 002', '', 'DEF456', 'webcam', 0, $times[1], $times[1]),
                array($module->id, 2, 'Talkpoint 003', 'foo.mp4', null, 'file', 0, $times[2], $times[2]),
                array($module->id, 2, 'Talkpoint 004', 'foo.mp4', null, 'file', 0, $times[3], $times[3]),
                array($module->id, 2, 'Talkpoint 005', 'foo.mp4', null, 'file', 0, $times[4], $times[4]),
            ),
        )));
        $this->assertEquals(5, $this->_cut->get_total_by_instanceid($module->id));
    }

    /**
     * tests counting the total viewable number of talkpoints by instance id
     */
    public function test_get_total_viewable_by_instanceid() {
        $times = [
            mktime( 9, 0, 0, 11, 5, 2013),
            mktime( 7, 0, 0, 11, 5, 2013),
            mktime( 6, 0, 0, 11, 5, 2013),
            mktime( 8, 0, 0, 11, 5, 2013),
            mktime(10, 0, 0, 11, 5, 2013),
            mktime(11, 0, 0, 11, 5, 2013),
        ];
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('talkpoint', [
            'course' => $course->id,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->loadDataSet($this->createArrayDataSet([
            'talkpoint_talkpoint' => [
                ['id', 'instanceid', 'userid', 'title', 'uploadedfile', 'nimbbguid', 'mediatype', 'closed', 'timecreated', 'timemodified'],
                [1, $module->id, 2, 'Talkpoint 001', '', 'ABC123', 'webcam', 0, $times[0], $times[0]],
                [2, $module->id, 2, 'Talkpoint 002', '', 'DEF456', 'webcam', 0, $times[1], $times[1]],
                [3, $module->id, 2, 'Talkpoint 003', 'foo.mp4', null, 'file', 0, $times[2], $times[2]],
                [4, $module->id, 2, 'Talkpoint 004', 'foo.mp4', null, 'file', 0, $times[3], $times[3]],
                [5, $module->id, 2, 'Talkpoint 005', 'foo.mp4', null, 'file', 0, $times[4], $times[4]],
                [6, $module->id, $user->id, 'Talkpoint 006', 'bar.webm', null, 'file', 0, $times[5], $times[5]],
            ],
            'talkpoint_video_conversion' => [
                ['talkpointid', 'src', 'dst', 'is_converting', 'timecreated'],
                [3, 'foo.webm', 'foo.webm.mp4', 1, $times[2]],
                [4, 'foo.ogv',  'foo.ogv.mp4',  0, $times[3]],
                [6, 'bar.webm', 'bar.webm.mp4', 0, $times[5]],
            ],
        ]));
        $this->_cut->set_userid($user->id);
        $this->assertEquals(4, $this->_cut->get_total_viewable_by_instanceid($module->id));
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
                [1, $module->id, $user1a->id, 'Talkpoint 001a', 'foo.png', null, 'file', 0, $times[0], $times[0]],
                [2, $module->id, $user1b->id, 'Talkpoint 001b', 'foo.png', null, 'file', 0, $times[1], $times[1]],
                [3, $module->id, $user2a->id, 'Talkpoint 002a', 'foo.png', null, 'file', 0, $times[2], $times[2]],
                [4, $module->id, $user2b->id, 'Talkpoint 002b', 'foo.png', null, 'file', 0, $times[3], $times[3]],
                [5, $module->id, $user3a->id, 'Talkpoint 003a', 'foo.png', null, 'file', 0, $times[3], $times[3]],
            ],
        ]));

        // return seeded data
        return [$module, $user1a, $user1b, $user2a, $user2b, $user3a, $group1, $group2];
    }

    /**
     * helper method
     * @param integer $id
     */
    protected function _test_get_separate_groups_not_visible_talkpoint($id) {
        list(, $user1a, , , ,) = $this->_seed_groups_and_groups_members();

        // set user and group mode
        $this->_cut->set_userid($user1a->id);
        $this->_cut->set_groupmode(SEPARATEGROUPS);

        // user1a should not be able to see the given talkpoint
        $this->_cut->get($id);
    }

}
