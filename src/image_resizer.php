<?php

defined('MOODLE_INTERNAL') || die();

class image_resizer {

    /**
     * @var Imagine\Gd\Imagine
     */
    protected $_imagine;

    /**
     * @var integer
     */
    protected $_max_width;

    /**
     * c'tor
     */
    public function __construct() {
        // empty
    }

    /**
     * accessor
     * @param Imagine\Gd\Imagine $imagine
     */
    public function set_imagine(Imagine\Gd\Imagine $imagine) {
        $this->_imagine = $imagine;
    }

    /**
     * accessor
     * @param integer $max_width
     */
    public function set_max_width($max_width) {
        $this->_max_width = $max_width;
    }

    /**
     * resizes the given image
     * @param string $uploadpath
     * @param string $filename
     * @return array
     */
    public function resize($uploadpath, $filename) {
        if (!$this->_is_image($uploadpath, $filename)) {
            return null;
        }

        // open the image, get its existing size, work out its aspect ratio
        $image = $this->_imagine->open($uploadpath . '/' . $filename);
        $size = $image->getSize();
        $width = $size->getWidth();
        $height = $size->getHeight();
        $ratio = $width / $height;

        // if it's already <= max width pixels wide, don't bother resizing
        if ($width <= $this->_max_width) {
            return array($width, $height);
        }

        // set the new dimensions
        $width = $this->_max_width;
        $height = (integer)round($width / $ratio);

        // ask 'Imagine' to resize it for us
        $image->resize(new \Imagine\Image\Box($width, $height))
            ->save($uploadpath . '/' . $filename);
        return array($width, $height);
    }

    /**
     * determines whether the given file is an image (by checking its mime type)
     * @param string $uploadpath
     * @param string $filename
     * @return boolean
     */
    protected function _is_image($uploadpath, $filename) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimetype = $finfo->file($uploadpath . '/' . $filename);
        if (in_array($mimetype, array('image/jpeg', 'image/gif', 'image/png'))) {
            return true;
        }
        return false;
    }

}
