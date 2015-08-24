<?php

use Mockery as m;

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../src/mimetype_mapper.php';

class mimetype_mapper_test extends advanced_testcase {

    /**
     * @var mimetype_mapper
     */
    protected $_cut;

    /**
     * setUp
     */
    protected function setUp() {
        $this->_cut = new mimetype_mapper();
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
        $this->assertInstanceOf('mimetype_mapper', $this->_cut);
    }

    /**
     * test text files (which are not supported)
     * @expectedException coding_exception
     * @expectedExceptionMessage mimetype_mapper does not appear to support mime type text/plain
     */
    public function test_txt_file() {
        $this->_cut->map(__DIR__ . '/img/not_an_image.txt');
    }

    /**
     * test jpg image
     */
    public function test_jpg_image() {
        list($template, $format) = $this->_cut->map(__DIR__ . '/img/dancer180x139.jpg');
        $this->assertSame('image', $template);
        $this->assertNull($format);
    }

    /**
     * test png image
     */
    public function test_png_image() {
        list($template, $format) = $this->_cut->map(__DIR__ . '/img/django1920x1080.png');
        $this->assertSame('image', $template);
        $this->assertNull($format);
    }

    /**
     * test gif image
     */
    public function test_gif_image() {
        list($template, $format) = $this->_cut->map(__DIR__ . '/img/xerxes.gif');
        $this->assertSame('image', $template);
        $this->assertNull($format);
    }

    /**
     * test m4v video
     */
    public function test_m4v_video() {
        list($template, $format) = $this->_cut->map(__DIR__ . '/video/Chrome_ImF.mp4');
        $this->assertSame('video', $template);
        $this->assertSame('m4v', $format);
    }

    /**
     * test ogv video
     */
    public function test_ogv_video() {
        list($template, $format) = $this->_cut->map(__DIR__ . '/video/Chrome_ImF.ogv');
        $this->assertSame('video', $template);
        $this->assertSame('ogv', $format);
    }

    /**
     * test webmv video
     */
    public function test_webmv_video() {
        list($template, $format) = $this->_cut->map(__DIR__ . '/video/Chrome_ImF.webm');
        $this->assertSame('video', $template);
        $this->assertSame('webmv', $format);
    }

    /**
     * test mp3 audio
     */
    public function test_mp3_audio() {
        list($template, $format) = $this->_cut->map(__DIR__ . '/audio/TSP-01-Cro_magnon_man.mp3');
        $this->assertSame('audio', $template);
        $this->assertSame('mp3', $format);
    }

    /**
     * test m4a audio
     */
    /*
    public function test_m4a_audio() {
        list($template, $format) = $this->_cut->map(__DIR__ . '/audio/TSP-01-Cro_magnon_man.m4a');
        $this->assertSame('audio', $template);
        $this->assertSame('m4a', $format);
    }
    */

    /**
     * test oga audio
     */
    /*
    public function test_oga_audio() {
        list($template, $format) = $this->_cut->map(__DIR__ . '/audio/TSP-01-Cro_magnon_man.ogg');
        $this->assertSame('audio', $template);
        $this->assertSame('oga', $format);
    }
    */

    /**
     * tests getting type ('image', 'audio', 'video') from a given directory and an uploaded txt file
     * @expectedException coding_exception
     * @expectedExceptionMessage mimetype_mapper does not appear to support mime type text/plain
     */
    public function test_type_and_formats_from_upload_path_and_uploaded_file_for_txt() {
        $this->_cut->type_and_formats_from_upload_path_and_uploaded_file(
            __DIR__ . '/img/',
            'not_an_image.txt'
        );
    }

    /**
     * tests getting type ('image', 'audio', 'video') from a given directory and an uploaded m4v file
     */
    public function test_type_and_formats_from_upload_path_and_uploaded_file_for_m4v() {
        list($format_type, $formats) = $this->_cut->type_and_formats_from_upload_path_and_uploaded_file(
            __DIR__ . '/video/',
            'Chrome_ImF.mp4'
        );
        $this->assertSame('video', $format_type);
        $this->assertSame('m4v,ogv,webmv', $formats);
    }

    /**
     * tests getting type ('image', 'audio', 'video') from a given directory and an uploaded ogv file
     */
    public function test_type_and_formats_from_upload_path_and_uploaded_file_for_ogv() {
        list($format_type, $formats) = $this->_cut->type_and_formats_from_upload_path_and_uploaded_file(
            __DIR__ . '/video/',
            'Chrome_ImF.ogv'
        );
        $this->assertSame('video', $format_type);
        $this->assertSame('m4v,ogv,webmv', $formats);
    }

    /**
     * tests getting type ('image', 'audio', 'video') from a given directory and an uploaded webm file
     */
    public function test_type_and_formats_from_upload_path_and_uploaded_file_for_webm() {
        list($format_type, $formats) = $this->_cut->type_and_formats_from_upload_path_and_uploaded_file(
            __DIR__ . '/video/',
            'Chrome_ImF.webm'
        );
        $this->assertSame('video', $format_type);
        $this->assertSame('m4v,ogv,webmv', $formats);
    }

    /**
     * tests getting type ('image', 'audio', 'video') from a given directory and an uploaded mp3 file
     */
    public function test_type_and_formats_from_upload_path_and_uploaded_file_for_mp3() {
        list($format_type, $formats) = $this->_cut->type_and_formats_from_upload_path_and_uploaded_file(
            __DIR__ . '/audio/',
            'TSP-01-Cro_magnon_man.mp3'
        );
        $this->assertSame('audio', $format_type);
        $this->assertSame('mp3', $formats);
    }

    /**
     * tests getting type ('image', 'audio', 'video') from a given directory and an uploaded jpg file
     */
    public function test_type_and_formats_from_upload_path_and_uploaded_file_for_jpg() {
        list($format_type, $formats) = $this->_cut->type_and_formats_from_upload_path_and_uploaded_file(
            __DIR__ . '/img/',
            'dancer180x139.jpg'
        );
        $this->assertSame('image', $format_type);
        $this->assertNull($formats);
    }

    /**
     * tests getting type ('image', 'audio', 'video') from a given directory and an uploaded png file
     */
    public function test_type_and_formats_from_upload_path_and_uploaded_file_for_png() {
        list($format_type, $formats) = $this->_cut->type_and_formats_from_upload_path_and_uploaded_file(
            __DIR__ . '/img/',
            'django1920x1080.png'
        );
        $this->assertSame('image', $format_type);
        $this->assertNull($formats);
    }

    /**
     * tests getting type ('image', 'audio', 'video') from a given directory and an uploaded gif file
     */
    public function test_type_and_formats_from_upload_path_and_uploaded_file_for_gif() {
        list($format_type, $formats) = $this->_cut->type_and_formats_from_upload_path_and_uploaded_file(
            __DIR__ . '/img/',
            'xerxes.gif'
        );
        $this->assertSame('image', $format_type);
        $this->assertNull($formats);
    }

    /**
     * test getting video formats from a given path
     */
    public function test_video_formats_from_upload_path() {
        $video_formats = $this->_cut->video_formats_from_upload_path(__DIR__ . '/video/');
        $this->assertSame('m4v,ogv,webmv', $video_formats);
    }

    /**
     * tests getting a video file name from a given path and m4v format
     */
    public function test_video_file_from_upload_path_and_video_format_for_m4v() {
        $video_file = $this->_cut->video_file_from_upload_path_and_video_format(
            __DIR__ . '/video/',
            'm4v'
        );
        $this->assertSame('Chrome_ImF.mp4', $video_file);
    }

    /**
     * tests getting a video file name from a given path and ogv format
     */
    public function test_video_file_from_upload_path_and_video_format_for_ogv() {
        $video_file = $this->_cut->video_file_from_upload_path_and_video_format(
            __DIR__ . '/video/',
            'ogv'
        );
        $this->assertSame('Chrome_ImF.ogv', $video_file);
    }

    /**
     * tests getting a video file name from a given path and webm format
     */
    public function test_video_file_from_upload_path_and_video_format_for_webm() {
        $video_file = $this->_cut->video_file_from_upload_path_and_video_format(
            __DIR__ . '/video/',
            'webmv'
        );
        $this->assertSame('Chrome_ImF.webm', $video_file);
    }

    /**
     * tests getting a video file name from a given path that doesn't contain a video file (of the given format)
     */
    public function test_video_file_from_upload_path_and_video_format_when_no_video_file_exists() {
        $video_file = $this->_cut->video_file_from_upload_path_and_video_format(
            __DIR__ . '/img/',
            'm4v'
        );
        $this->assertNull($video_file);
    }

}
