<?php

defined('MOODLE_INTERNAL') || die;

require_once $CFG->dirroot . '/mod/talkpoint/backup/moodle2/backup_talkpoint_stepslib.php';

class backup_talkpoint_activity_task extends backup_activity_task {

    protected function define_my_settings() {
        // empty
    }

    protected function define_my_steps() {
        $this->add_step(new backup_talkpoint_activity_structure_step('talkpoint_structure', 'talkpoint.xml'));
    }

    static public function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot,"/");

        // link to the list of pages
        $search="/(".$base."\/mod\/talkpoint\/index.php\?id\=)([0-9]+)/";
        $content= preg_replace($search, '$@TALKPOINTINDEX*$2@$', $content);

        // link to page view by moduleid
        $search="/(".$base."\/mod\/talkpoint\/view.php\?id\=)([0-9]+)/";
        $content= preg_replace($search, '$@TALKPOINTVIEWBYID*$2@$', $content);

        return $content;
    }

}
