<?php

defined('MOODLE_INTERNAL') || die();

class talkpoint_video_conversion_model {

    /**
     * gets all videos that need converting
     * @global moodle_database $DB
     * @return array
     */
    public function get_all_needing_converting() {
        global $DB;
        $sql = <<<SQL
            SELECT tvc.*
            FROM {talkpoint_video_conversion} tvc
            WHERE tvc.is_converting = 0
            ORDER BY tvc.timecreated ASC
SQL;
        return $DB->get_records_sql($sql);
    }

    /**
     * @param stdClass $obj
     * @return bool|int
     */
    public function insert($obj) {
        global $DB;
        $obj->is_converting = 0;
        $obj->timecreated = time();
        return $DB->insert_record('talkpoint_video_conversion', $obj);
    }

    /**
     * sets as converting
     * @global moodle_database $DB
     * @param integer $id
     * @param boolean $is_converting
     */
    public function set_converting($id, $is_converting) {
        global $DB;
        $obj = $DB->get_record('talkpoint_video_conversion', ['id' => $id], '*', MUST_EXIST);
        $obj->is_converting = $is_converting ? 1 : 0;
        $DB->update_record('talkpoint_video_conversion', $obj);
    }

    /**
     * @global moodle_database $DB
     * @param integer $id
     */
    public function delete($id) {
        global $DB;
        $DB->get_field('talkpoint_video_conversion', 'id', ['id' => $id], MUST_EXIST);
        $DB->delete_records('talkpoint_video_conversion', ['id' => $id]);
    }

}
