<?php

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../../course/moodleform_mod.php';

class mod_talkpoint_mod_form extends moodleform_mod {

    /**
     * definition
     */
    protected function definition() {
        global $CFG;

        $mform =& $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        // name
        $mform->addElement('text', 'name', get_string('talkpointname', 'talkpoint'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // header & footer
        foreach (array('header', 'footer') as $element) {
            $mform->addElement('editor', $element, get_string($element, 'talkpoint'), null, array(
                'maxfiles' => 0,
                'maxbytes' => 0,
                'trusttext' => false,
                'forcehttps' => false,
            ));
        }

        // closed
        $mform->addElement('checkbox', 'closed', get_string('closed', 'talkpoint'));
        $mform->addHelpButton('closed', 'talkpointclosed', 'talkpoint');
        $mform->setDefault('closed', 0);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * @param array $default_values
     */
    function data_preprocessing(&$default_values) {
        if ($this->current->instance) {
            $header = $default_values['header'];
            $default_values['header'] = array(
                'format' => FORMAT_HTML,
                'text' => $header,
            );
            $footer = $default_values['footer'];
            $default_values['footer'] = array(
                'format' => FORMAT_HTML,
                'text' => $footer,
            );
        }
    }

    /**
     * Display module-specific activity completion rules.
     * Part of the API defined by moodleform_mod
     * @return array Array of string IDs of added items, empty array if none
     */
    public function add_completion_rules() {
        $mform = $this->_form;

        $mform->addElement(
            'checkbox',
            'completioncreatetalkpoint',
            get_string('completioncreatetalkpoint', 'talkpoint'),
            get_string('completioncreatetalkpoint_desc', 'talkpoint')
        );

        $mform->addElement(
            'checkbox',
            'completioncommentontalkpoint',
            get_string('completioncommentontalkpoint', 'talkpoint'),
            get_string('completioncommentontalkpoint_desc', 'talkpoint')
        );

        return [
            'completioncreatetalkpoint',
            'completioncommentontalkpoint'
        ];
    }

    /**
     * determines if completion is enabled for this module
     * @param array $data
     * @return bool
     */
    function completion_rule_enabled($data) {
        return !empty($data['completioncreatetalkpoint']) || !empty($data['completioncommentontalkpoint']);
    }

    /**
     * return the data that will be used upon saving
     * @return bool|object
     */
    function get_data() {
        $data = parent::get_data();
        if (!$data) {
            return false;
        }
        if (!empty($data->completionunlocked)) {
            // turn off completion settings if the checkboxes aren't ticked
            $autocompletion = !empty($data->completion) && $data->completion == COMPLETION_TRACKING_AUTOMATIC;
            if (empty($data->completioncreatetalkpoint) || !$autocompletion) {
                $data->completioncreatetalkpoint = 0;
            }
            if (empty($data->completioncommentontalkpoint) || !$autocompletion) {
                $data->completioncommentontalkpoint = 0;
            }
        }
        return $data;
    }

}
