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
 * A report to display  assignments files and allow to  download
 *
 * @package    report
 * @subpackage psgrading_downloader
 * @copyright  2024 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once('../../config.php');
require_once('psgrading_downloader_form.php');


$id                      = optional_param('id', 0, PARAM_INT); // Course ID.
$selectedactivities      = optional_param('selectedactivities', '', PARAM_TEXT);
$selectedusers           = optional_param('selectedusers', '', PARAM_TEXT);

$manager                 = new report_psgrading_downloader\reportmanager();

// Download

if ($selectedusers != '') {
   
    $manager->download_reports($selectedactivities, $selectedusers, $id);
}

$url = new moodle_url('/report/psgrading_downloader/index.php', array('id' => $id /*, 'cmid' => $cmid*/));
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->add_body_class('report_psgrading_downloader');

if (!$course = $DB->get_record('course', array('id' => $id))) {
    $message = get_string('invalidcourse', 'report_assignfeedback_download');
    $level = core\output\notification::NOTIFY_ERROR;
    \core\notification::add($message, $level);
}

require_login($course);
$context = context_course::instance($course->id);
require_capability('mod/psgrading:addinstance', $context);

$PAGE->set_title(format_string($course->shortname, true, array('context' => $context)));
$PAGE->set_heading(format_string($course->fullname, true, array('context' => $context)));

// Add css.
// $PAGE->requires->css(new moodle_url($CFG->wwwroot . '/mod/psgrading/psgrading.css', array('nocache' => rand())));


echo $OUTPUT->header();

$psids = $manager->get_psgrading_activities($id);
$mform =  new psgrading_downloader_form(null, ['id'=>$id, 'cmid' => $cmid, 'psids' => $psids]);
$filter = false;
$activityids = '';

// Form processing and displaying is done here.
if ($data = $mform->get_data()) {
    // In this case you process validated data. $mform->get_data() returns data posted in form.
    $activityids = $data->psgradinactivities; // Get the selected assessments.
    $includeunpublished = $data->includeunreleased;
    $filter        = true;
} else {
    if (count($psids) == 0) {
        $noasses = 1;
    }
}

if ($id == 0 || $id == 1) {  // 1 is the main page.
    $message = get_string('cantdisplayerror', 'report_assignfeedback_download');
    $level   = core\output\notification::NOTIFY_ERROR;
    \core\notification::add($message, $level);
} else {
    echo $OUTPUT->box_start();
    $renderer = $PAGE->get_renderer('report_psgrading_downloader');

    if ($noasses) {
        // echo $renderer->render_no_assessment_in_course();
    } else {
        $mform->display();
    }

    // Only if the user clicked filter display this.
    if ($filter) {
        $url            = $PAGE->url;
        $coursename     = $DB->get_field('course', 'fullname', ['id' => $id], $strictness = IGNORE_MISSING);
        
        echo $renderer->render_selection($id, $activityids, $includeunpublished, $url);
    }

    echo $OUTPUT->box_end();
}

echo $OUTPUT->footer();
