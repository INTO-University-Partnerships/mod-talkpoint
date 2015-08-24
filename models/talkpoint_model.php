<?php

use Functional as F;

defined('MOODLE_INTERNAL') || die();

class talkpoint_model {

    /**
     * the id of the logged in user
     * @var integer
     */
    protected $_userid;

    /**
     * either NOGROUPS or SEPARATEGROUPS
     * @var integer
     */
    protected $_group_mode = NOGROUPS;

    /**
     * c'tor
     */
    public function __construct() {
        // empty
    }

    /**
     * @param integer $userid
     */
    public function set_userid($userid) {
        $this->_userid = $userid;
    }

    /**
     * @param integer $group_mode - either NOGROUPS or SEPARATEGROUPS
     */
    public function set_groupmode($group_mode) {
        $this->_group_mode = $group_mode;
    }

    /**
     * @global moodle_database $DB
     * @param integer $instanceid
     * @return integer
     */
    public function get_total_by_instanceid($instanceid) {
        global $DB;
        return (integer)$DB->count_records('talkpoint_talkpoint', array(
            'instanceid' => $instanceid,
        ));
    }

    /**
     * @global moodle_database $DB
     * @param integer $instanceid
     * @return integer
     */
    public function get_total_viewable_by_instanceid($instanceid) {
        global $DB;
        list($sql, $params) = $this->_get_total_viewable_by_instanceid($instanceid);
        return (integer)$DB->count_records_sql($sql, $params);
    }

    /**
     * @param integer $instanceid
     * @param integer $limitfrom
     * @param integer $limitnum
     * @return array
     */
    public function get_all_by_instanceid($instanceid, $limitfrom = 0, $limitnum = 0) {
        $sql = <<<SQL
            SELECT tt.*, u.firstname, u.lastname
            FROM {talkpoint_talkpoint} tt
            INNER JOIN {user} u ON u.id = tt.userid AND u.deleted = 0
            WHERE tt.instanceid = :instanceid
            ORDER BY tt.timecreated DESC
SQL;
        return $this->_sql_query_to_array($sql, [
            'instanceid' => $instanceid,
        ], $limitfrom, $limitnum);
    }

    /**
     * gets all 'viewable' talkpoints (talkpoints with videos that need converting are not considered 'viewable')
     * @param integer $instanceid
     * @param integer $limitfrom
     * @param integer $limitnum
     * @return array
     */
    public function get_all_viewable_by_instanceid($instanceid, $limitfrom = 0, $limitnum = 0) {
        list($sql, $params) = $this->_get_all_viewable_by_instanceid($instanceid);
        return $this->_sql_query_to_array($sql, $params, $limitfrom, $limitnum);
    }

    /**
     * @global moodle_database $DB
     * @param integer $id
     * @return array
     */
    public function get($id) {
        global $DB;
        $select = 'SELECT tt.*, u.firstname, u.lastname';
        $from = 'FROM {talkpoint_talkpoint} tt';
        if ($this->_group_mode === SEPARATEGROUPS) {
            $subquery = require __DIR__ . '/sql/separate_groups_subquery.php';
            $body = <<<SQL
                INNER JOIN {talkpoint} t ON tt.instanceid = t.id
                INNER JOIN {user} u ON u.id = tt.userid AND u.deleted = 0
                LEFT JOIN ({$subquery}) g ON g.userid = tt.userid AND g.courseid = t.course
                WHERE tt.id = :id
                    AND (tt.userid = :userid3 OR g.userid IS NOT NULL)
SQL;
            $result = $DB->get_record_sql(join(' ', [$select, $from, $body]), [
                'id' => $id,
                'userid2' => $this->_userid,
                'userid3' => $this->_userid,
            ], MUST_EXIST);
        } else {
            $body = <<<SQL
                INNER JOIN {user} u ON u.id = tt.userid AND u.deleted = 0
                WHERE tt.id = :id
SQL;
            $result = $DB->get_record_sql(join(' ', [$select, $from, $body]), [
                'id' => $id,
            ], MUST_EXIST);
        }
        return $this->_obj_to_array($result);
    }

    /**
     * determines whether the given talkpoint has any videos to convert (or videos currently converting)
     * @param integer $id
     * @return bool
     */
    public function has_videos_to_convert($id) {
        global $DB;
        return (integer)$DB->count_records('talkpoint_video_conversion', ['talkpointid' => $id]) > 0;
    }

    /**
     * @global moodle_database $DB
     * @param array $data
     * @param integer $now
     * @return array
     */
    public function save(array $data, $now) {
        global $DB;
        $data['timemodified'] = $now;
        if (array_key_exists('id', $data)) {
            $DB->update_record('talkpoint_talkpoint', (object)$data);
        } else {
            $data['timecreated'] = $data['timemodified'];
            $data['id'] = (integer)$DB->insert_record('talkpoint_talkpoint', (object)$data);
        }
        return $this->get($data['id']);
    }

    /**
     * @global moodle_database $DB
     * @param integer $id
     */
    public function delete($id) {
        global $DB;
        $instanceid = $DB->get_field('talkpoint_talkpoint', 'instanceid', array(
            'id' => $id,
        ), MUST_EXIST);
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('talkpoint_video_conversion', array('talkpointid' => $id));
        $DB->delete_records('talkpoint_comment', array('talkpointid' => $id));
        $DB->delete_records('talkpoint_talkpoint', array('id' => $id));
        remove_dir($this->get_upload_path() . '/' . $instanceid . '/' . $id);
        $transaction->allow_commit();
    }

    /**
     * @return string
     */
    public function get_upload_path() {
        global $CFG;
        return $CFG->dataroot . '/into/mod_talkpoint';
    }

    /**
     * @param integer $instanceid
     * @return array
     */
    protected function _get_total_viewable_by_instanceid($instanceid) {
        $select = 'SELECT COUNT(tt.id)';
        if ($this->_group_mode === SEPARATEGROUPS) {
            $body = require __DIR__ . '/sql/separate_groups.php';
            return [join(' ', [$select, $body]), [
                'instanceid' => $instanceid,
                'userid1' => $this->_userid,
                'userid2' => $this->_userid,
                'userid3' => $this->_userid,
            ]];
        } else {
            $body = require __DIR__ . '/sql/no_groups.php';
            return [join(' ', [$select, $body]), [
                'instanceid' => $instanceid,
                'userid' => $this->_userid,
            ]];
        }
    }

    /**
     * @param integer $instanceid
     * @return array
     */
    protected function _get_all_viewable_by_instanceid($instanceid) {
        $select = 'SELECT tt.*, u.firstname, u.lastname';
        $order_by = 'ORDER BY tt.timecreated DESC';
        if ($this->_group_mode === SEPARATEGROUPS) {
            $body = require __DIR__ . '/sql/separate_groups.php';
            return [join(' ', [$select, $body, $order_by]), [
                'instanceid' => $instanceid,
                'userid1' => $this->_userid,
                'userid2' => $this->_userid,
                'userid3' => $this->_userid,
            ]];
        } else {
            $body = require __DIR__ . '/sql/no_groups.php';
            return [join(' ', [$select, $body, $order_by]), [
                'instanceid' => $instanceid,
                'userid' => $this->_userid,
            ]];
        }
    }

    /**
     * @global moodle_database $DB
     * @param string $sql
     * @param array $params
     * @param integer $limitfrom
     * @param integer $limitnum
     * @return array
     */
    protected function _sql_query_to_array($sql, $params, $limitfrom, $limitnum) {
        global $DB;
        $results = $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
        if (empty($results)) {
            return [];
        }
        return array_values(F\map($results, function ($result) {
            return $this->_obj_to_array($result);
        }));
    }

    /**
     * @param object $obj
     * @return array
     */
    protected function _obj_to_array($obj) {
        return array(
            'id' => (integer)$obj->id,
            'instanceid' => (integer)$obj->instanceid,
            'userid' => (integer)$obj->userid,
            'userfullname' => $obj->firstname . ' ' . $obj->lastname,
            'is_owner' => (isset($this->_userid) && $obj->userid == $this->_userid),
            'title' => $obj->title,
            'uploadedfile' => $obj->uploadedfile,
            'nimbbguid' => $obj->nimbbguid,
            'mediatype' => $obj->mediatype,
            'closed' => !empty($obj->closed),
            'timecreated' => userdate($obj->timecreated),
            'timemodified' => userdate($obj->timemodified),
        );
    }

}
