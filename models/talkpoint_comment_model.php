<?php

defined('MOODLE_INTERNAL') || die();

class talkpoint_comment_model {

    /**
     * the id of the logged in user
     * @var integer
     */
    protected $_userid;

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
     * @global moodle_database $DB
     * @param integer $talkpointid
     * @return integer
     */
    public function get_total_by_talkpointid($talkpointid) {
        global $DB;
        return (integer)$DB->count_records('talkpoint_comment', array(
            'talkpointid' => $talkpointid,
        ));
    }

    /**
     * @global moodle_database $DB
     * @param integer $talkpointid
     * @param integer $limitfrom
     * @param integer $limitnum
     * @return array
     */
    public function get_all_by_talkpointid($talkpointid, $limitfrom = 0, $limitnum = 0) {
        global $DB;
        $retval = array();
        $userfields = user_picture::fields('u', null, 'userid');
        $sql = <<<SQL
            SELECT tc.*, $userfields
            FROM {talkpoint_comment} tc
            INNER JOIN {user} u ON u.id = tc.userid AND u.deleted = 0
            WHERE tc.talkpointid = :talkpointid
            ORDER BY tc.timecreated DESC
SQL;
        $results = $DB->get_records_sql($sql, array(
            'talkpointid' => $talkpointid,
        ), $limitfrom, $limitnum);
        if (empty($results)) {
            return $retval;
        }
        foreach ($results as $result) {
            $retval[] = $this->_obj_to_array($result);
        }
        return $retval;
    }

    /**
     * @global moodle_database $DB
     * @param integer $id
     * @return array
     */
    public function get($id) {
        global $DB;
        $userfields = user_picture::fields('u', null, 'userid');
        $sql = <<<SQL
            SELECT tc.*, $userfields
            FROM {talkpoint_comment} tc
            INNER JOIN {user} u ON u.id = tc.userid AND u.deleted = 0
            WHERE tc.id = :id
SQL;
        $result = $DB->get_record_sql($sql, array(
            'id' => $id,
        ), MUST_EXIST);
        return $this->_obj_to_array($result);
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

        $transaction = $DB->start_delegated_transaction();

        if (array_key_exists('id', $data)) {
            $DB->update_record('talkpoint_comment', (object)$data);
        } else {
            $data['timecreated'] = $data['timemodified'];
            $data['id'] = (integer)$DB->insert_record('talkpoint_comment', (object)$data);
        }

        // close the talkpoint for further comments if final feedback was left
        if (!empty($data['finalfeedback'])) {
            $DB->set_field('talkpoint_talkpoint', 'closed', 1, array('id' => $data['talkpointid']));
        }

        $transaction->allow_commit();

        return $this->get($data['id']);
    }

    /**
     * @global moodle_database $DB
     * @param integer $id
     */
    public function delete($id) {
        global $DB;

        $talkpointid = $DB->get_field('talkpoint_comment', 'talkpointid', array('id' => $id), MUST_EXIST);
        $DB->delete_records('talkpoint_comment', array('id' => $id));

        // reopen the talkpoint for further comments if (all) final feedback comments have been deleted
        $count = $DB->count_records('talkpoint_comment', array(
            'talkpointid' => $talkpointid,
            'finalfeedback' => 1,
        ));
        if ($count == 0 && $DB->get_field('talkpoint_talkpoint', 'closed', array('id' => $talkpointid), MUST_EXIST) == 1) {
            $DB->set_field('talkpoint_talkpoint', 'closed', 0, array('id' => $talkpointid));
        }
    }

    /**
     * @param object $obj
     * @return array
     */
    protected function _obj_to_array($obj) {
        return array(
            'id' => (integer)$obj->id,
            'talkpointid' => (integer)$obj->talkpointid,
            'userid' => (integer)$obj->userid,
            'userfullname' => $obj->firstname . ' ' . $obj->lastname,
            'picture' => $obj->picture,
            'firstname' => $obj->firstname,
            'lastname' => $obj->lastname,
            'firstnamephonetic' => $obj->firstnamephonetic,
            'lastnamephonetic' => $obj->lastnamephonetic,
            'middlename' => $obj->middlename,
            'alternatename' => $obj->alternatename,
            'email' => $obj->email,
            'is_owner' => (isset($this->_userid) && $obj->userid == $this->_userid),
            'textcomment' => $obj->textcomment,
            'nimbbguidcomment' => $obj->nimbbguidcomment,
            'finalfeedback' => !empty($obj->finalfeedback),
            'timecreated' => userdate($obj->timecreated),
            'timemodified' => userdate($obj->timemodified),
        );
    }

}
