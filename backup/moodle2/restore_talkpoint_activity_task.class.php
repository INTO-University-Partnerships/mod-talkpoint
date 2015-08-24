<?php

defined('MOODLE_INTERNAL') || die();

require_once $CFG->dirroot . '/mod/talkpoint/backup/moodle2/restore_talkpoint_stepslib.php';

class restore_talkpoint_activity_task extends restore_activity_task {

    protected function define_my_settings() {
        // empty
    }

    protected function define_my_steps() {
        $this->add_step(new restore_talkpoint_activity_structure_step('talkpoint_structure', 'talkpoint.xml'));
    }

    static public function define_decode_contents() {
        $contents = array();

        $contents[] = new restore_decode_content('talkpoint', array('header', 'footer'), 'talkpoint');

        return $contents;
    }

    static public function define_decode_rules() {
        $rules = array();

        $rules[] = new restore_decode_rule('TALKPOINTVIEWBYID', '/mod/talkpoint/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('TALKPOINTINDEX', '/mod/talkpoint/index.php?id=$1', 'course');

        return $rules;
    }

}
