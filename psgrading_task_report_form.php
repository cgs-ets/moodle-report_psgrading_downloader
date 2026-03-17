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
 * A report to display the outcome of scheduled assignfeedback_download
 *
 * @package    report_psgrading_downloader
 * @subpackage psgrading_task_report_form
 * @copyright  2026 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class psgrading_task_report_form extends moodleform {

    /**
     * Form definition
     */
    public function definition() {
        global $PAGE;

        $mform = $this->_form;

        $mform->addElement('hidden', 'id', $this->_customdata['id']);
        $mform->settype('id', PARAM_INT); // To be able to pre-fill the form.

         // Filter by group.
        $coursegroups = [];
        // $coursegroups[] = get_string('all', 'report_psgrading_downloader');
        $groups = $this->_customdata['groups'];
        $allgroups = [];

        foreach ($groups as $group) {
            $coursegroups[$group->id] = $group->name;
            $allgroups[] = $group->id;
        }

        $mform->addElement('select', 'filterbygroup', get_string('allgroups', 'report_psgrading_downloader'), $coursegroups);
        $mform->getElement('filterbygroup')->setMultiple(false);
        $mform->setDefault('filterbygroup', 0);

        // PS grading activities.
        $psgactivities = [];

        $activities = $this->_customdata['psids'];
        $allcmids = [];
        foreach ($activities as $activity) {
            $psgactivities[$activity->cmid] = $activity->name;
            $allcmids[] = $activity->cmid;
        }

        $allcmids = implode(',', $allcmids);

        $mform->addElement('select',
                            'psgradinactivities',
                            get_string('allpsgactivities', 'report_psgrading_downloader'),
                            $psgactivities);
        $mform->getElement('psgradinactivities')->setMultiple(false);
        $mform->setDefault('psgradinactivities', 0);

     

        // PS Tasks will show the tasks based on the activity
        $mform->addElement('text', 'selectedactivitiesJSON', 'Select activity(s) JSON');
        $mform->settype('selectedactivitiesJSON', PARAM_RAW);
        $mform->setDefault('selectedactivitiesJSON', '[]');

        $mform->addElement('html', '<div class="report-psgrading-tasks-in-selected-activity"></div>');

        $mform->addElement('text', 'selectedtasksJSON', 'Select task(s) JSON');
        $mform->settype('selectedtasksJSON', PARAM_RAW);
        $mform->setDefault('selectedtasksJSON', '[]');


        // Published.

        $mform->addElement('advcheckbox',
                            'includeunreleased',
                            get_string('includeunreleased', 'report_psgrading_downloader'),
                            '', [],
                            [0, 1]);
        $mform->addHelpButton('includeunreleased', 'includeunreleased', 'report_psgrading_downloader');
        $buttonarray = [];

        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('filter', 'report_psgrading_downloader'));
        $buttonarray[] = &$mform->createElement('cancel', 'canceltbutton', get_string('cancel', 'report_psgrading_downloader'));

        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);

        $mform->closeHeaderBefore('buttonar');
        
        $PAGE->requires->js_call_amd('report_psgrading_downloader/taskreportcontrol', 'init', []);

        
    }

}