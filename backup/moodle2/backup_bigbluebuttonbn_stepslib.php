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
 * @author    Fred Dixon  (ffdixon [at] blindsidenetworks [dt] com)
 * @author    Jesus Federico  (jesus [at] blindsidenetworks [dt] com)
 * @copyright 2010-2017 Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v2 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Define all the backup steps that will be used by the backup_bigbluebuttonbn_activity_task.
 */

/**
 * Define the complete bigbluebuttonbn structure for backup, with file and id annotations.
 */
class backup_bigbluebuttonbn_activity_structure_step extends backup_activity_structure_step
{
    protected function define_structure() {

        // Define each element separated.
        $bigbluebuttonbn = new backup_nested_element('bigbluebuttonbn', array('id'), array(
                            'course', 'name', 'intro', 'introformat', 'meetingid',
                            'moderatorpass', 'viewerpass', 'wait', 'record', 'tagging',
                            'welcome', 'voicebridge', 'openingtime', 'closingtime',
                            'timecreated', 'timemodified', 'presentation', 'participants',
                            'userlimit', ));

        $logs = new backup_nested_element('logs');

        $log = new backup_nested_element('log', array('id'), array(
                'courseid', 'bigbluebuttonbnid', 'userid', 'timecreated', 'meetingid', 'log', 'meta', ));

        // Build the tree.
        $bigbluebuttonbn->add_child($logs);
        $logs->add_child($log);

        // Define sources.
        $bigbluebuttonbn->set_source_table('bigbluebuttonbn', array('id' => backup::VAR_ACTIVITYID));
        $log->set_source_table('bigbluebuttonbn_logs', array('bigbluebuttonbnid' => backup::VAR_PARENTID));

        // Define id annotations.
        $log->annotate_ids('user', 'userid');

        // Define file annotations.
        $bigbluebuttonbn->annotate_files('mod_bigbluebuttonbn', 'intro', null);

        // Return the root element (bigbluebuttonbn), wrapped into standard activity structure.
        return $this->prepare_activity_structure($bigbluebuttonbn);
    }
}
