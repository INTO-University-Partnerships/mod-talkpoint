<?php

use Functional as F;

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../src/video_converter.php';

class video_converter_test extends advanced_testcase {

    /**
     * @var string
     */
    protected $_ffmpeg_binary;

    /**
     * @var string
     */
    protected $_tmp_directory;

    /**
     * @var video_converter
     */
    protected $_cut;

    /**
     * setUp
     */
    protected function setUp() {
        // ffmpeg is needed for video conversion
        $this->_ffmpeg_binary = realpath(__DIR__ . '/../bin/ffmpeg');

        // create a temporary directory
        $tmp_directory = tempnam(sys_get_temp_dir(), '');
        unlink($tmp_directory);
        $this->assertFileNotExists($tmp_directory);
        mkdir($tmp_directory);
        $this->assertFileExists($tmp_directory);
        $this->_tmp_directory = $tmp_directory;

        // instantiate class under test
        $this->_cut = new video_converter();
        $this->resetAfterTest();
    }

    /**
     * tearDown
     */
    public function tearDown() {
        // clean up temporary directory
        F\each(F\filter(scandir($this->_tmp_directory), function ($file) {
            if ($file === '.' || $file === '..') {
                return false;
            }
            return is_file($this->_tmp_directory . '/' . $file);
        }), function ($file) {
            unlink($this->_tmp_directory . '/' . $file);
        });
        rmdir($this->_tmp_directory);
    }

    /**
     * tests instantiation
     */
    public function test_instantiation() {
        $this->assertInstanceOf('video_converter', $this->_cut);
    }

    /**
     * tests cleaning a given upload path
     */
    public function test_clean_upload_path() {
        // fill the temporary directory
        F\each(['foo', 'bar', 'wibble'], function ($f) {
            file_put_contents($this->_tmp_directory . '/' . $f . '.txt', 'dummy contents');
        });
        mkdir($this->_tmp_directory . '/some_directory');
        $this->assertCount(6, scandir($this->_tmp_directory));

        // invoke functionality under test
        $this->_cut->clean_upload_path($this->_tmp_directory);

        // make sure the temporary directory is empty of files
        $this->assertCount(3, scandir($this->_tmp_directory));
        $this->assertEquals(['.', '..', 'some_directory'], scandir($this->_tmp_directory));
        rmdir($this->_tmp_directory . '/some_directory');
    }

    /**
     * tests queue_convert_non_m4v_to_m4v
     * @expectedException coding_exception
     * @expectedExceptionMessage video_converter expected to find exactly one file in directory
     */
    public function test_queue_convert_non_m4v_to_m4v_throws_invalid_state_exception_when_given_upload_path_empty() {
        $this->assertEquals(['.', '..'], scandir($this->_tmp_directory));
        $this->_cut->queue_convert_non_m4v_to_m4v($this->_tmp_directory, 1);
    }

    /**
     * tests queue_convert_non_m4v_to_m4v
     * @expectedException coding_exception
     * @expectedExceptionMessage video_converter expected to find exactly one file in directory
     */
    public function test_queue_convert_non_m4v_to_m4v_throws_invalid_state_exception_when_given_upload_path_has_multiple_files() {
        // fill the temporary directory
        F\each(['foo', 'bar', 'wibble'], function ($f) {
            file_put_contents($this->_tmp_directory . '/' . $f . '.txt', 'dummy contents');
        });
        $this->assertCount(5, scandir($this->_tmp_directory));

        // invoke functionality under test
        $this->_cut->queue_convert_non_m4v_to_m4v($this->_tmp_directory, 1);
    }

    /**
     * tests queue_convert_non_m4v_to_m4v
     * @global moodle_database $DB
     */
    public function test_queue_convert_non_m4v_to_m4v_does_nothing_if_file_not_video() {
        global $DB;

        // fill the temporary directory
        file_put_contents($this->_tmp_directory . '/foo.txt', 'dummy contents');

        // invoke functionality under test
        $result = $this->_cut->queue_convert_non_m4v_to_m4v($this->_tmp_directory, 1);
        $this->assertNull($result);

        // expect there to be no records (i.e. no conversion was queued)
        $this->assertCount(0, $DB->get_records('talkpoint_video_conversion'));
    }

    /**
     * tests queue_convert_non_m4v_to_m4v
     * @global moodle_database $DB
     */
    public function test_queue_convert_non_m4v_to_m4v_does_nothing_if_file_is_m4v() {
        global $DB;

        copy(__DIR__ . '/video/Chrome_ImF.mp4', $this->_tmp_directory . '/foo.mp4');

        // invoke functionality under test
        $result = $this->_cut->queue_convert_non_m4v_to_m4v($this->_tmp_directory, 1);
        $this->assertNull($result);

        // expect there to be no records (i.e. no conversion was queued)
        $this->assertCount(0, $DB->get_records('talkpoint_video_conversion'));
    }

    /**
     * tests queue_convert_non_m4v_to_m4v
     * ogv -> m4v
     * @global moodle_database $DB
     */
    public function test_queue_convert_non_m4v_to_m4v_converts_to_m4v_if_file_is_ogv() {
        global $DB;

        copy(__DIR__ . '/video/Chrome_ImF.ogv', $this->_tmp_directory . '/foo.ogv');

        // invoke functionality under test
        $result = $this->_cut->queue_convert_non_m4v_to_m4v($this->_tmp_directory, 1);
        $this->assertNotNull($result);

        // expect there to be exactly 1 record (i.e. a conversion was queued)
        $this->assertCount(1, $DB->get_records('talkpoint_video_conversion', [
            'talkpointid' => 1,
            'src' => 'foo.ogv',
            'dst' => 'foo.ogv.mp4',
            'is_converting' => 0,
        ]));
    }

    /**
     * tests queue_convert_non_m4v_to_m4v
     * webmv -> m4v
     * @global moodle_database $DB
     */
    public function test_queue_convert_non_m4v_to_m4v_converts_to_m4v_if_file_is_webmv() {
        global $DB;

        copy(__DIR__ . '/video/Chrome_ImF.webm', $this->_tmp_directory . '/foo.webm');

        // invoke functionality under test
        $result = $this->_cut->queue_convert_non_m4v_to_m4v($this->_tmp_directory, 1);
        $this->assertNotNull($result);

        // expect there to be exactly 1 record (i.e. a conversion was queued)
        $this->assertCount(1, $DB->get_records('talkpoint_video_conversion', [
            'talkpointid' => 1,
            'src' => 'foo.webm',
            'dst' => 'foo.webm.mp4',
            'is_converting' => 0,
        ]));
    }

    /**
     * tests converting two files that need to be converted
     * @global moodle_database $DB
     */
    public function test_convert() {
        global $DB;

        // fill the temporary directory
        copy(__DIR__ . '/video/Chrome_ImF.webm', $this->_tmp_directory . '/foo.webm');
        copy(__DIR__ . '/video/Chrome_ImF.webm', $this->_tmp_directory . '/bar.webm');
        copy(__DIR__ . '/video/Chrome_ImF.ogv', $this->_tmp_directory . '/foo.ogv');
        copy(__DIR__ . '/video/Chrome_ImF.ogv', $this->_tmp_directory . '/bar.ogv');
        $this->assertCount(4, F\filter(scandir($this->_tmp_directory), function ($file) {
            return is_file($this->_tmp_directory . '/' . $file);
        }));

        // seed the database
        $irrelevant = 0;
        $now = time();
        $this->loadDataSet($this->createArrayDataSet([
            'talkpoint_video_conversion' => [
                ['talkpointid', 'src', 'dst', 'is_converting', 'timecreated'],
                [$irrelevant, 'foo.webm', 'foo.webm.mp4', 0, $now],
                [$irrelevant, 'foo.ogv',  'foo.ogv.mp4',  0, $now],
                [$irrelevant, 'bar.webm', 'bar.webm.mp4', 1, $now],
                [$irrelevant, 'bar.ogv',  'bar.ogv.mp4',  1, $now],
            ],
        ]));

        // invoke functionality under test
        $this->_cut->convert(function ($_) {
            return $this->_tmp_directory;
        }, $this->_ffmpeg_binary);

        // expect there to be two more files than we started with (the two that have been converted)
        $this->assertCount(6, F\filter(scandir($this->_tmp_directory), function ($file) {
            return is_file($this->_tmp_directory . '/' . $file);
        }));

        // expect there to be no records requiring conversion
        $this->assertCount(0, $DB->get_records('talkpoint_video_conversion', [
            'is_converting' => 0,
        ]));

        // expect there to be two records left over
        $this->assertCount(2, $DB->get_records('talkpoint_video_conversion'));

        // expect an mp4 converted from a webm and an ogv
        $this->assertFileExists($this->_tmp_directory . '/foo.webm.mp4');
        $this->assertFileExists($this->_tmp_directory . '/foo.ogv.mp4');

        // check the newly converted mp4 mime types
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $this->assertSame('video/mp4', $finfo->file($this->_tmp_directory . '/foo.webm.mp4'));
        $this->assertSame('video/mp4', $finfo->file($this->_tmp_directory . '/foo.ogv.mp4'));
    }

    /**
     * tests that a video conversion record with a non-existent source is deleted
     * @global moodle_database $DB
     */
    public function test_convert_non_existent_source() {
        global $DB;

        // seed the database
        $irrelevant = 0;
        $now = time();
        $this->loadDataSet($this->createArrayDataSet([
            'talkpoint_video_conversion' => [
                ['talkpointid', 'src', 'dst', 'is_converting', 'timecreated'],
                [$irrelevant, 'bar.ogv', 'bar.ogv.mp4', 0, $now], // source file does not exist
            ],
        ]));

        // invoke functionality under test
        $this->_cut->convert(function ($_) {
            return $this->_tmp_directory;
        }, $this->_ffmpeg_binary);

        // expect there to be no records requiring conversion
        $this->assertCount(0, $DB->get_records('talkpoint_video_conversion'));
    }

}
