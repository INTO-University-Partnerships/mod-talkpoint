<?php

use Mockery as m;

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../src/image_resizer.php';

class image_resizer_test extends advanced_testcase {

    /**
     * @var image_resizer
     */
    protected $_cut;

    /**
     * setUp
     */
    protected function setUp() {
        $this->_cut = new image_resizer();
        $this->_cut->set_imagine(new Imagine\Gd\Imagine());
        $this->_cut->set_max_width(200);
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
        $this->assertInstanceOf('image_resizer', $this->_cut);
    }

    /**
     * tests trying to resize an image when a non-image is given
     */
    public function test_resize_image_when_not_an_image() {
        $result = $this->_cut->resize(__DIR__ . '/img', 'not_an_image.txt');
        $this->assertNull($result);
    }

    /**
     * tests trying to resize an image that's already narrower than a threshold
     */
    public function test_resize_image_when_already_small() {
        $result = $this->_cut->resize(__DIR__ . '/img', 'dancer180x139.jpg');
        $this->assertEquals(array(180, 139), $result);
    }

    /**
     * tests trying to resize an image that's larger than a threshold
     */
    public function test_resize_image() {
        copy(__DIR__ . '/img/django1920x1080.png', '/tmp/django1920x1080.png');
        $result = $this->_cut->resize('/tmp', 'django1920x1080.png');
        $ratio = 1920.0 / 1080.0;
        $height = (integer)round(200.0 / $ratio);
        $this->assertEquals(array(200, $height), $result);
        unlink('/tmp/django1920x1080.png');
    }

}
