<?php

use Mockery as m;

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../lib.php';

class mod_talkpoint_lib_test extends advanced_testcase {

    /**
     * @var string
     */
    protected $_upload_path;

    /**
     * setUp
     */
    protected function setUp() {
        // ask talkpoint_model what the upload path is
        require_once __DIR__ . '/../models/talkpoint_model.php';
        $talkpoint_model = new talkpoint_model();
        $this->_upload_path = $talkpoint_model->get_upload_path();

        // reset after test
        $this->resetAfterTest();
    }

    /**
     * tearDown
     */
    public function tearDown() {
        m::close();
    }

    /**
     * @global moodle_database $DB
     */
    public function test_talkpoint_delete_instance() {
        global $DB;
        $times = array(
            mktime(9, 0, 0, 11, 5, 2013),
        );
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('talkpoint', array(
            'course' => $course->id,
        ));
        check_dir_exists($this->_upload_path . '/' . $module->id . '/1');
        check_dir_exists($this->_upload_path . '/' . $module->id . '/2');
        file_put_contents($this->_upload_path . '/' . $module->id . '/1/foo.txt', 'whatever');
        file_put_contents($this->_upload_path . '/' . $module->id . '/2/bar.txt', 'whatever');
        $this->loadDataSet($this->createArrayDataSet(array(
            'talkpoint_talkpoint' => array(
                array('id', 'instanceid', 'userid', 'title', 'uploadedfile', 'nimbguid', 'mediatype', 'closed', 'timecreated', 'timemodified'),
                array(1, $module->id, 2, 'Talkpoint 001', 'foo.txt', null, 'file', 0, $times[0], $times[0]),
                array(2, $module->id, 2, 'Talkpoint 002', 'bar.txt', null, 'file', 0, $times[0], $times[0]),
            ),
            'talkpoint_comment' => array(
                array('talkpointid', 'userid', 'finalfeedback', 'timecreated', 'timemodified'),
                array(1, 2, 0, $times[0], $times[0]),
                array(1, 2, 0, $times[0], $times[0]),
                array(1, 2, 0, $times[0], $times[0]),
                array(2, 2, 0, $times[0], $times[0]),
                array(2, 2, 0, $times[0], $times[0]),
            ),
        )));
        $this->assertEquals(1, $DB->count_records('talkpoint_talkpoint', array('id' => 1)));
        $this->assertEquals(3, $DB->count_records('talkpoint_comment', array('talkpointid' => 1)));
        $this->assertEquals(1, $DB->count_records('talkpoint_talkpoint', array('id' => 2)));
        $this->assertEquals(2, $DB->count_records('talkpoint_comment', array('talkpointid' => 2)));
        $this->assertFileExists($this->_upload_path . '/' . $module->id . '/1');
        $this->assertFileExists($this->_upload_path . '/' . $module->id . '/2');
        $this->assertFileExists($this->_upload_path . '/' . $module->id . '/1/foo.txt');
        $this->assertFileExists($this->_upload_path . '/' . $module->id . '/2/bar.txt');
        talkpoint_delete_instance($module->id);
        $this->assertFalse($DB->record_exists('talkpoint_talkpoint', array('id' => 1)));
        $this->assertFalse($DB->record_exists('talkpoint_comment', array('talkpointid' => 1)));
        $this->assertFalse($DB->record_exists('talkpoint_talkpoint', array('id' => 2)));
        $this->assertFalse($DB->record_exists('talkpoint_comment', array('talkpointid' => 2)));
        $this->assertFalse($DB->record_exists('talkpoint', array('id' => $module->id)));
        $this->assertFileNotExists($this->_upload_path . '/' . $module->id);
    }

    /**
     * tests the features that talkpoint supports
     */
    public function test_talkpoint_supports() {
        $features = array(
            FEATURE_COMPLETION_TRACKS_VIEWS,
            FEATURE_BACKUP_MOODLE2,
            FEATURE_GROUPS,
        );
        foreach ($features as $feature) {
            $this->assertTrue(plugin_supports('mod', 'talkpoint', $feature));
        }
    }

    /**
     * tests the feature that talkpoint does not support
     */
    public function test_talkpoint_not_supports() {
        $features = array(
            FEATURE_GRADE_HAS_GRADE,
            FEATURE_GRADE_OUTCOMES,
            FEATURE_ADVANCED_GRADING,
            FEATURE_CONTROLS_GRADE_VISIBILITY,
            FEATURE_PLAGIARISM,
            FEATURE_COMPLETION_HAS_RULES,
            FEATURE_NO_VIEW_LINK,
            FEATURE_IDNUMBER,
            FEATURE_GROUPINGS,
            FEATURE_MOD_ARCHETYPE,
            FEATURE_MOD_INTRO,
            FEATURE_MODEDIT_DEFAULT_COMPLETION,
            FEATURE_COMMENT,
            FEATURE_RATE,
            FEATURE_SHOW_DESCRIPTION,
        );
        foreach ($features as $feature) {
            $this->assertFalse(plugin_supports('mod', 'talkpoint', $feature));
        }
    }

}
