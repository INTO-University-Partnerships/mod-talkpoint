<?php

defined('MOODLE_INTERNAL') || die;

class restore_talkpoint_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {
        $paths = array();
        $paths[] = new restore_path_element('talkpoint', '/activity/talkpoint');
        return $this->prepare_activity_structure($paths);
    }

    protected function process_talkpoint($data) {
        global $DB;

        $data = (object)$data;
        $data->course = $this->get_courseid();

        $data->timecreated = $data->timemodified = time();

        $newitemid = $DB->insert_record('talkpoint', $data);
        $this->apply_activity_instance($newitemid);
    }

}
