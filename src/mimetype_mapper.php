<?php

use Functional as F;

defined('MOODLE_INTERNAL') || die();

class mimetype_mapper {

    /**
     * @var array
     */
    protected $_mapping;

    /**
     * c'tor
     */
    public function __construct() {
        // empty
    }

    /**
     * @param string $fullpath
     * @return array
     * @throws coding_exception
     */
    public function map($fullpath) {
        // determine the mime type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimetype = $finfo->file($fullpath);

        // define mapping
        if (!isset($this->_mapping)) {
            $this->_mapping = array(
                'image/jpeg' => array('image', null),
                'image/gif' => array('image', null),
                'image/png' => array('image', null),
                'audio/mpeg' => array('audio', 'mp3'),                 // jPlayer essential audio format
//                'audio/mp4' => array('audio', 'm4a'),                // jPlayer essential audio format
//                'audio/webm' => array('audio', 'webma'),             // jPlayer counterpart audio format
//                'audio/ogg' => array('audio', 'oga'),                // jPlayer counterpart audio format
                'video/mp4' => array('video', 'm4v'),                  // jPlayer essential video format
                'video/webm' => array('video', 'webmv'),               // jPlayer counterpart video format
//                'video/ogg' => array('video', 'ogv'),                // jPlayer counterpart video format
                'application/ogg' => array('video', 'ogv'),            // jPlayer counterpart video format
                'application/octet-stream' => array('video', 'webmv'), // jPlayer counterpart video format
                'video/quicktime' => array('video', 'mov'),            // not supported by jPlayer
            );
        }

        // ensure mime type is supported
        if (!array_key_exists($mimetype, $this->_mapping)) {
            throw new coding_exception(get_class($this) . ' does not appear to support mime type ' . $mimetype);
        }

        // return the type (image, audio or video) and its jPlayer-friendly format
        return $this->_mapping[$mimetype];
    }

    /**
     * given a path/directory and an uploaded file in that directory, returns the format type of the uploaded file
     * (i.e. 'image', 'audio' or 'video')
     * along with jPlayer format(s) (in the case of audio and video)
     * @param string $upload_path
     * @param string $uploaded_file
     * @return array
     * @throws coding_exception
     */
    public function type_and_formats_from_upload_path_and_uploaded_file($upload_path, $uploaded_file) {
        list($format_type, $format) = $this->map($upload_path . '/' . $uploaded_file);
        return [$format_type, $format_type === 'video' ? $this->video_formats_from_upload_path($upload_path) : $format];
    }

    /**
     * given a path/directory, finds files that have a video mimetype and returns the jPlayer format of those files
     * as a comma-separated string (e.g. "m4v,ogv")
     * @param string $upload_path
     * @return string
     */
    public function video_formats_from_upload_path($upload_path) {
        return join(',', F\map(F\filter(scandir($upload_path), function ($file) use ($upload_path) {
            if ($file === '.' || $file === '..') {
                return false;
            }
            try {
                list($ft, $f) = $this->map($upload_path . '/' . $file);
            } catch (coding_exception $e) {
                return false;
            }
            return $ft === 'video' && F\contains(['m4v', 'webmv', 'ogv'], $f);
        }), function ($file) use ($upload_path) {
            return $this->map($upload_path . '/' . $file)[1];
        }));
    }

    /**
     * given a path/directory and a video format (e.g. 'm4v'), returns a file with the corresponding mimetype
     * @param string $upload_path
     * @param string $format
     * @return string
     */
    public function video_file_from_upload_path_and_video_format($upload_path, $format) {
        return F\head(F\filter(scandir($upload_path), function ($file) use ($upload_path, $format) {
            if ($file === '.' || $file === '..') {
                return false;
            }
            try {
                list($ft, $f) = $this->map($upload_path . '/' . $file);
            } catch (coding_exception $e) {
                return false;
            }
            return $ft === 'video' && $f === $format;
        }));
    }

}
