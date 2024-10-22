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
 * @subpackage psgrading_downloader
 * @copyright  2024 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Undocumented class
 */
class psgrading_downloader_form extends moodleform {
    /**
     * Undocumented function
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id', $this->_customdata['id']);
        $mform->settype('id', PARAM_INT); // To be able to pre-fill the form.

        // Filter by group.
        $coursegroups = [];
        $coursegroups[] = get_string('all', 'report_psgrading_downloader');
        $groups = $this->_customdata['groups'];

        foreach ($groups as $group) {
            $coursegroups[$group->id] = $group->name;
        }

        $mform->addElement('select', 'filterbygroup', get_string('allgroups', 'report_psgrading_downloader'), $coursegroups);
        $mform->getElement('filterbygroup')->setMultiple(true);
        $mform->setDefault('filterbygroup', 0);

        // PS grading activities.

        $psgactivities = [];
        $psgactivities[] = get_string('all', 'report_psgrading_downloader');

        $results = $this->_customdata['psids'];

        foreach ($results as $row) {
            $psgactivities[$row->cmid] = $row->name;
        }

        $allcmids = implode(',', array_keys($psgactivities));

        $mform->addElement('select',
                            'psgradinactivities',
                            get_string('allpsgactivities', 'report_psgrading_downloader'),
                            $psgactivities);
        $mform->getElement('psgradinactivities')->setMultiple(true);
        $mform->setDefault('psgradinactivities', 0);

        // All the cmids.

        $mform->addElement('hidden', 'allcmids', $allcmids);

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
    }

}
