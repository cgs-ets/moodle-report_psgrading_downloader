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
 * @package    report
 * @subpackage psgrading_downloader
 * @copyright  2024 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class psgrading_downloader_form extends moodleform {

    public function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id', $this->_customdata['id']);
        $mform->settype('id', PARAM_INT); // To be able to pre-fill the form.
        $mform->addElement('hidden', 'cmid', $this->_customdata['cmid']);
        $mform->settype('cmid', PARAM_INT); // To be able to pre-fill the form.

        $psgactivities = array();
        $psgactivities[] = get_string('all', 'report_psgrading_downloader');

        $results = $this->_customdata['psids'];

        foreach($results as $row) {
            $psgactivities[$row->id] = $row->name;
        }

        $mform->addElement('select', 'psgradinactivities', get_string('allpsgactivities', 'report_psgrading_downloader'), $psgactivities);
        $mform->getElement('psgradinactivities')->setMultiple(true);
        $mform->setDefault('psgradinactivities', 0);

        $buttonarray = array();

        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('filter', 'report_psgrading_downloader'));
        $buttonarray[] = &$mform->createElement('cancel', 'canceltbutton', get_string('cancel', 'report_psgrading_downloader'));

        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);

        $mform->closeHeaderBefore('buttonar');
        

    }

}