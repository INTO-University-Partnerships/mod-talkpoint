<?php

defined('MOODLE_INTERNAL') || die;

class backup_talkpoint_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {
        $talkpoint = new backup_nested_element('talkpoint', array('id'), array(
            'name',
            'header',
            'footer',
            'closed',
            'timecreated',
            'timemodified',
            'completioncreatetalkpoint',
            'completioncommentontalkpoint'
        ));

        $talkpoint->set_source_table('talkpoint', array('id' => backup::VAR_ACTIVITYID));

        return $this->prepare_activity_structure($talkpoint);
    }

}
