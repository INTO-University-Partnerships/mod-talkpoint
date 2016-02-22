<?php

use Functional as F;

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../models/talkpoint_video_conversion_model.php';

class video_converter {

    /**
     * deletes all files in the given directory
     * @param string $upload_path
     */
    public function clean_upload_path($upload_path) {
        F\each(F\filter(scandir($upload_path), function ($file) use ($upload_path) {
            if ($file === '.' || $file === '..') {
                return false;
            }
            return is_file($upload_path . '/' . $file);
        }), function ($file) use ($upload_path) {
            unlink($upload_path . '/' . $file);
        });
    }

    /**
     * if the single file in the given directory is one of the below mime types, then queue a conversion to m4v
     * 'video/webm', 'application/ogg', 'application/octet-stream', 'video/quicktime'
     * (see 'file_constraints' in app.php and the jPlayer essential/counterpart formats in mimetype_mapper.php)
     * @param string $upload_path
     * @param integer $talkpointid
     * @return stdClass
     * @throws coding_exception
     */
    public function queue_convert_non_m4v_to_m4v($upload_path, $talkpointid) {
        // get files in given directory (there should be exactly one)
        $files = F\filter(scandir($upload_path), function ($file) use ($upload_path) {
            if ($file === '.' || $file === '..') {
                return false;
            }
            return is_file($upload_path . '/' . $file);
        });
        if (count($files) !== 1) {
            throw new coding_exception(get_class($this) . ' expected to find exactly one file in directory');
        }

        // ensure the mime type of the single file is one of the ones we need to convert
        $file = F\head($files);
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($upload_path . '/' . $file);
        if (!F\contains(['video/webm', 'application/ogg', 'application/octet-stream', 'video/quicktime'], $mime_type, true)) {
            return null;
        }

        // queue video conversion
        $model = new talkpoint_video_conversion_model();
        return $model->insert((object)[
            'talkpointid' => $talkpointid,
            'src' => $file,
            'dst' => $file . '.mp4',
        ]);
    }

    /**
     * convert all videos queued for conversion
     * @param callable $get_talkpoint_upload_path
     * @param string $ffmpeg_binary
     */
    public function convert(callable $get_talkpoint_upload_path, $ffmpeg_binary) {
        $talkpoint_conversion_model = new talkpoint_video_conversion_model();
        F\each(
            $talkpoint_conversion_model->get_all_needing_converting(),
            function ($to_convert) use ($get_talkpoint_upload_path, $ffmpeg_binary, $talkpoint_conversion_model) {
                try {
                    $talkpoint_conversion_model->set_converting($to_convert->id, true);
                    $upload_path = $get_talkpoint_upload_path($to_convert->talkpointid);
                    $src = $upload_path . '/' . $to_convert->src;
                    $dst = $upload_path . '/' . $to_convert->dst;
                    if (!is_file($src)) {
                        $talkpoint_conversion_model->delete($to_convert->id);
                        return;
                    }
                    echo "$src -> $dst\n";
                    shell_exec($ffmpeg_binary . ' -v 0 -i ' . $src . ' ' . $dst);
                    if (is_file($dst)) {
                        $talkpoint_conversion_model->delete($to_convert->id);
                    } else {
                        $talkpoint_conversion_model->set_converting($to_convert->id, false);
                    }
                } catch (dml_missing_record_exception $e) {
                    $talkpoint_conversion_model->set_converting($to_convert->id, false);
                }
            }
        );
    }

}
