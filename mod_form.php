<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Config all BigBlueButtonBN instances in this course.
 *
 * @package   mod_bigbluebuttonbn
 * @author    Fred Dixon  (ffdixon [at] blindsidenetworks [dt] com)
 * @author    Jesus Federico  (jesus [at] blindsidenetworks [dt] com)
 * @copyright 2010-2017 Blindside Networks Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v2 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/locallib.php');
require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_bigbluebuttonbn_mod_form extends moodleform_mod {

    public function definition() {
        global $CFG, $DB, $OUTPUT, $PAGE;

        // Validates if the BigBlueButton server is running.
        $serverversion = bigbluebuttonbn_get_server_version();
        if (is_null($serverversion)) {
            print_error('general_error_unable_connect', 'bigbluebuttonbn',
                $CFG->wwwroot.'/admin/settings.php?section=modsettingbigbluebuttonbn');
        }

        $bigbluebuttonbn = null;
        $course = null;

        $courseid = optional_param('course', 0, PARAM_INT);
        if ($courseid) {
            $course = get_course($courseid);
        }

        if (!$course) {
            $cm = get_coursemodule_from_id('bigbluebuttonbn',
                optional_param('update', 0, PARAM_INT), 0, false, MUST_EXIST);
            $course = $DB->get_record('course', array('id' => $cm->course),
                '*', MUST_EXIST);
            $bigbluebuttonbn = $DB->get_record('bigbluebuttonbn',
                array('id' => $cm->instance), '*', MUST_EXIST);
        }

        $context = context_course::instance($course->id);

        // UI configuration options.
        $cfg = bigbluebuttonbn_get_cfg_options();

        $mform = &$this->_form;
        $currentactivity = &$this->current;

        $jsvars = [];

        if ($cfg['instance_type_enabled']) {
            $typeprofiles = bigbluebuttonbn_get_instance_type_profiles();
            $this->bigbluebuttonbn_mform_add_block_profiles($mform, $cfg, ['instance_type_profiles' => $typeprofiles]);
            $jsvars['instance_type_profiles'] = $typeprofiles;
        }

        // Data for participant selection.
        $participantlist = bigbluebuttonbn_get_participant_list($bigbluebuttonbn, $context);

        // Add block 'General'.
        $this->bigbluebuttonbn_mform_add_block_general($mform, $cfg);

        // Add block 'Room'.
        $this->bigbluebuttonbn_mform_add_block_room($mform, $cfg);

        // Add block 'Preuploads'.
        $this->bigbluebuttonbn_mform_add_block_preuploads($mform, $cfg);

        // Add block 'Participant List'.
        $this->bigbluebuttonbn_mform_add_block_participants($mform, ['participant_list' => $participantlist]);

        // Add block 'Schedule'.
        $this->bigbluebuttonbn_mform_add_block_schedule($mform, ['activity' => $currentactivity]);

        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();

        // Add standard buttons, common to all modules.
        $this->add_action_buttons();

        // JavaScript for locales.
        $PAGE->requires->strings_for_js(array_keys(bigbluebuttonbn_get_strings_for_js()), 'bigbluebuttonbn');

        $jsvars['icons_enabled'] = $cfg['recording_icons_enabled'];
        $jsvars['pix_icon_delete'] = (string)$OUTPUT->pix_url('t/delete', 'moodle');
        $jsvars['participant_data'] = bigbluebuttonbn_get_participant_data($context);
        $jsvars['participant_list'] = bigbluebuttonbn_get_participant_list($bigbluebuttonbn, $context);
        $PAGE->requires->yui_module('moodle-mod_bigbluebuttonbn-modform',
            'M.mod_bigbluebuttonbn.modform.init', array($jsvars));

    }

    public function data_preprocessing(&$defaultvalues) {
        if ($this->current->instance) {
            // Editing existing instance - copy existing files into draft area.
            try {
                $draftitemid = file_get_submitted_draft_itemid('presentation');
                file_prepare_draft_area($draftitemid, $this->context->id, 'mod_bigbluebuttonbn', 'presentation', 0,
                    array('subdirs' => 0, 'maxbytes' => 0, 'maxfiles' => 1, 'mainfile' => true)
                );
                $defaultvalues['presentation'] = $draftitemid;
            } catch (Exception $e) {
                //debugging('Presentation could not be loaded: '.$e->getMessage(), DEBUG_DEVELOPER);
                return null;
            }
        }
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (isset($data['openingtime']) && isset($data['closingtime'])) {
            if ($data['openingtime'] != 0 && $data['closingtime'] != 0 &&
                $data['closingtime'] < $data['openingtime']) {
                $errors['closingtime'] = get_string('bbbduetimeoverstartingtime', 'bigbluebuttonbn');
            }
        }

        if (isset($data['voicebridge'])) {
            if (!bigbluebuttonbn_voicebridge_unique($data['voicebridge'], $data['instance'])) {
                $errors['voicebridge'] = get_string('mod_form_field_voicebridge_notunique_error', 'bigbluebuttonbn');
            }
        }

        return $errors;
    }

    private function bigbluebuttonbn_mform_add_block_profiles($mform, $cfg, $data) {
        if ($cfg['instance_type_enabled']) {
            $mform->addElement('select', 'type', get_string('mod_form_field_instanceprofiles', 'bigbluebuttonbn'),
                bigbluebuttonbn_get_instance_profiles_array($data['instance_type_profiles']),
                array('onchange' => 'M.mod_bigbluebuttonbn.modform.update_instance_type_profile(this);'));
            $mform->addHelpButton('type', 'mod_form_field_instanceprofiles', 'bigbluebuttonbn');

            return;
        }

        $mform->addElement('hidden', 'type', $cfg['instance_type_default']);
    }

    private function bigbluebuttonbn_mform_add_block_general($mform, $cfg) {
        global $CFG;

        $mform->addElement('header', 'general', get_string('mod_form_block_general', 'bigbluebuttonbn'));

        $mform->addElement('text', 'name', get_string('mod_form_field_name', 'bigbluebuttonbn'),
            'maxlength="64" size="32"');
        $mform->setType('name', empty($CFG->formatstringstriptags) ? PARAM_CLEANHTML : PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $versionmajor = bigbluebuttonbn_get_moodle_version_major();
        if ($versionmajor < '2015051100') {
            // This is valid before v2.9.
            $this->add_intro_editor(false, get_string('mod_form_field_intro', 'bigbluebuttonbn'));
        } else {
            // This is valid after v2.9.
            $this->standard_intro_elements(get_string('mod_form_field_intro', 'bigbluebuttonbn'));
        }
        $mform->setAdvanced('introeditor');
        $mform->setAdvanced('showdescription');

        if ($cfg['sendnotifications_enabled']) {
            $mform->addElement('checkbox', 'notification', get_string('mod_form_field_notification',
                'bigbluebuttonbn'));
            $mform->addHelpButton('notification', 'mod_form_field_notification', 'bigbluebuttonbn');
            $mform->setDefault('notification', 0);
            $mform->setType('notification', PARAM_INT);
        }
    }

    private function bigbluebuttonbn_mform_add_block_room_room($mform, $cfg) {
        $field = ['type' => 'textarea', 'name' => 'welcome', 'data_type' => PARAM_TEXT,
            'description_key' => 'mod_form_field_welcome'];
        $this->bigbluebuttonbn_mform_add_element($mform, $field['type'], $field['name'], $field['data_type'],
            $field['description_key'], '', ['wrap' => 'virtual', 'rows' => 5, 'cols' => '60']);

        $field = ['type' => 'hidden', 'name' => 'voicebridge', 'data_type' => PARAM_INT,
            'description_key' => null];
        if ($cfg['voicebridge_editable']) {
            $field['type'] = 'text';
            $field['data_type'] = PARAM_TEXT;
            $field['description_key'] = 'mod_form_field_voicebridge';
            $this->bigbluebuttonbn_mform_add_element($mform, $field['type'], $field['name'], $field['data_type'],
                $field['description_key'], 0, ['maxlength' => 4, 'size' => 6],
                ['message' => get_string('mod_form_field_voicebridge_format_error', 'bigbluebuttonbn'),
                 'type' => 'numeric', 'rule' => '####', 'validator' => 'server']
              );
        } else {
            $this->bigbluebuttonbn_mform_add_element($mform, $field['type'], $field['name'], $field['data_type'],
                $field['description_key'], 0, ['maxlength' => 4, 'size' => 6]);
        }

        $field = ['type' => 'hidden', 'name' => 'wait', 'data_type' => PARAM_INT, 'description_key' => null];
        if ($cfg['waitformoderator_editable']) {
            $field['type'] = 'checkbox';
            $field['description_key'] = 'mod_form_field_wait';
        }
        $this->bigbluebuttonbn_mform_add_element($mform, $field['type'], $field['name'], $field['data_type'],
            $field['description_key'], $cfg['waitformoderator_default']);

        $field = ['type' => 'hidden', 'name' => 'userlimit', 'data_type' => PARAM_INT, 'description_key' => null];
        if ($cfg['userlimit_editable']) {
            $field['type'] = 'text';
            $field['data_type'] = PARAM_TEXT;
            $field['description_key'] = 'mod_form_field_userlimit';
        }
        $this->bigbluebuttonbn_mform_add_element($mform, $field['type'], $field['name'], $field['data_type'],
            $field['description_key'], $cfg['userlimit_default']);

        $field = ['type' => 'hidden', 'name' => 'record', 'data_type' => PARAM_INT, 'description_key' => null];
        if ($cfg['recording_editable']) {
            $field['type'] = 'checkbox';
            $field['description_key'] = 'mod_form_field_record';
        }
        $this->bigbluebuttonbn_mform_add_element($mform, $field['type'], $field['name'], $field['data_type'],
            $field['description_key'], $cfg['recording_default']);
    }

    private function bigbluebuttonbn_mform_add_block_room_recordings($mform, $cfg) {
        $field = ['type' => 'hidden', 'name' => 'recordings_html', 'data_type' => PARAM_INT, 'description_key' => null];
        if ($cfg['recordings_html_editable']) {
            $field['type'] = 'checkbox';
            $field['description_key'] = 'mod_form_field_recordings_html';
        }
        $this->bigbluebuttonbn_mform_add_element($mform, $field['type'], $field['name'], $field['data_type'],
            $field['description_key'], $cfg['recordings_html_default']);

        $field = ['type' => 'hidden', 'name' => 'recordings_deleted_activities', 'data_type' => PARAM_INT,
                  'description_key' => null];
        if ($cfg['recordings_deleted_activities_editable']) {
            $field['type'] = 'checkbox';
            $field['description_key'] = 'mod_form_field_recordings_deleted_activities';
        }
        $this->bigbluebuttonbn_mform_add_element($mform, $field['type'], $field['name'], $field['data_type'],
            $field['description_key'], $cfg['recordings_deleted_activities_default']);
    }

    private function bigbluebuttonbn_mform_add_block_room($mform, $cfg) {
        if ($cfg['voicebridge_editable'] || $cfg['waitformoderator_editable'] ||
            $cfg['userlimit_editable'] || $cfg['recording_editable']) {
            $mform->addElement('header', 'room', get_string('mod_form_block_room', 'bigbluebuttonbn'));
            $this->bigbluebuttonbn_mform_add_block_room_room($mform, $cfg);
        }

        if ($cfg['recordings_html_editable'] || $cfg['recordings_deleted_activities_editable']) {
            $mform->addElement('header', 'recordings', get_string('mod_form_block_recordings', 'bigbluebuttonbn'));
            $this->bigbluebuttonbn_mform_add_block_room_recordings($mform, $cfg);
        }
    }

    private function bigbluebuttonbn_mform_add_block_preuploads($mform, $cfg) {
        if ($cfg['preuploadpresentation_enabled']) {
            $mform->addElement('header', 'preuploadpresentation',
                get_string('mod_form_block_presentation', 'bigbluebuttonbn'));
            $mform->setExpanded('preuploadpresentation');

            $filemanageroptions = array();
            $filemanageroptions['accepted_types'] = '*';
            $filemanageroptions['maxbytes'] = 0;
            $filemanageroptions['subdirs'] = 0;
            $filemanageroptions['maxfiles'] = 1;
            $filemanageroptions['mainfile'] = true;

            $mform->addElement('filemanager', 'presentation', get_string('selectfiles'),
                null, $filemanageroptions);
        }
    }

    private function bigbluebuttonbn_mform_add_block_participants($mform, $data) {
        $participantselection = bigbluebuttonbn_get_participant_selection_data();
        $participantlist = $data['participant_list'];

        $mform->addElement('header', 'permissions', get_string('mod_form_block_participants', 'bigbluebuttonbn'));
        $mform->setExpanded('permissions');

        $mform->addElement('hidden', 'participants', json_encode($participantlist));
        $mform->setType('participants', PARAM_TEXT);

        // Render elements for participant selection.
        $htmlselection = html_writer::tag('div',
            html_writer::select($participantselection['type_options'], 'bigbluebuttonbn_participant_selection_type',
                $participantselection['type_selected'], array(),
                array('id' => 'bigbluebuttonbn_participant_selection_type',
                      'onchange' => 'M.mod_bigbluebuttonbn.modform.participant_selection_set(); return 0;')).'&nbsp;&nbsp;'.
            html_writer::select($participantselection['options'], 'bigbluebuttonbn_participant_selection',
                $participantselection['selected'], array(),
                array('id' => 'bigbluebuttonbn_participant_selection', 'disabled' => 'disabled')).'&nbsp;&nbsp;'.
            '<input value="'.get_string('mod_form_field_participant_list_action_add', 'bigbluebuttonbn').
            '" class="btn btn-secondary" type="button" id="id_addselectionid" '.
            'onclick="M.mod_bigbluebuttonbn.modform.participant_add(); return 0;" />'
        );

        $mform->addElement('html', "\n\n");
        $mform->addElement('static', 'static_add_participant',
            get_string('mod_form_field_participant_add', 'bigbluebuttonbn'), $htmlselection);
        $mform->addElement('html', "\n\n");

        // Declare the table.
        $table = new html_table();
        $table->id = 'participant_list_table';
        $table->data = array();

        // Render elements for participant list.
        $htmllist = html_writer::tag('div',
            html_writer::label(get_string('mod_form_field_participant_list', 'bigbluebuttonbn'),
                'bigbluebuttonbn_participant_list').
            html_writer::table($table)
        );

        $mform->addElement('html', "\n\n");
        $mform->addElement('static', 'participant_list', '', $htmllist);
        $mform->addElement('html', "\n\n");
    }

    private function bigbluebuttonbn_mform_add_block_schedule($mform, $data) {
        $mform->addElement('header', 'schedule', get_string('mod_form_block_schedule', 'bigbluebuttonbn'));
        if (isset($data['activity']->openingtime) && $data['activity']->openingtime != 0 ||
            isset($data['activity']->closingtime) && $data['activity']->closingtime != 0) {
            $mform->setExpanded('schedule');
        }

        $mform->addElement('date_time_selector', 'openingtime', get_string('mod_form_field_openingtime', 'bigbluebuttonbn'),
            array('optional' => true));
        $mform->setDefault('openingtime', 0);
        $mform->addElement('date_time_selector', 'closingtime', get_string('mod_form_field_closingtime', 'bigbluebuttonbn'),
            array('optional' => true));
        $mform->setDefault('closingtime', 0);
    }

    private function bigbluebuttonbn_mform_add_element($mform, $type, $name, $datatype,
            $descriptionkey, $defaultvalue = null, $options = [], $rule = []) {
        if ($type === 'hidden') {
            $mform->addElement($type, $name, $defaultvalue);
            $mform->setType($name, $datatype);
            return;
        }

        $mform->addElement($type, $name, get_string($descriptionkey, 'bigbluebuttonbn'), $options);
        if (get_string_manager()->string_exists($descriptionkey.'_help', 'bigbluebuttonbn')) {
            $mform->addHelpButton($name, $descriptionkey, 'bigbluebuttonbn');
        }
        if (!empty($rule)) {
            $mform->addRule($name, $rule['message'], $rule['type'], $rule['rule'], $rule['validator']);
        }
        $mform->setDefault($name, $defaultvalue);
        $mform->setType($name, $datatype);
    }
}
