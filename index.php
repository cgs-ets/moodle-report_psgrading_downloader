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
 * @package    report_psgrading_downloader
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

// Download.

if ($selectedusers != '') {

    $manager->download_reports($selectedactivities, $selectedusers, $id);
}

$url = new moodle_url('/report/psgrading_downloader/index.php', ['id' => $id /*, 'cmid' => $cmid*/]);
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->add_body_class('report_psgrading_downloader');

if (!$course = $DB->get_record('course', ['id' => $id])) {
    $message = get_string('invalidcourse', 'report_assignfeedback_download');
    $level = core\output\notification::NOTIFY_ERROR;
    \core\notification::add($message, $level);
}

require_login($course);
$context = context_course::instance($course->id);
require_capability('mod/psgrading:addinstance', $context);

$PAGE->set_title(format_string($course->shortname, true, ['context' => $context]));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));

echo $OUTPUT->header();

$psids = $manager->get_psgrading_activities($id);
$groups = $manager->get_groups_in_course($id);
$mform = new psgrading_downloader_form(null, ['id' => $id, /*'cmid' => $cmid, */'psids' => $psids, 'groups' => $groups]);
$filter = false;
$activityids = '';

// Form processing and displaying is done here.
if ($data = $mform->get_data()) {
    // In this case you process validated data. $mform->get_data() returns data posted in form.
    $activityids        = in_array(0, $data->psgradinactivities) ? explode(',', $data->allcmids) : $data->psgradinactivities;
    $includeunpublished = $data->includeunreleased;
    $groups             = $data->filterbygroup;
    $filter             = true;

} else {
    if ($activityids == '') {
     //   $noactivities = 1;
    }
}

if ($id == 0 || $id == 1) {  // 1 is the main page.
    $message = get_string('cantdisplayerror', 'report_assignfeedback_download');
    $level   = core\output\notification::NOTIFY_ERROR;
    \core\notification::add($message, $level);
} else {
    echo $OUTPUT->box_start();
    $renderer = $PAGE->get_renderer('report_psgrading_downloader');

    if ($noactivities) {
        $message = get_string('nopsgradingactivities', 'report_assignfeedback_download');
        $level   = core\output\notification::NOTIFY_INFO;
        \core\notification::add($message, $level);
    } else {
        $mform->display();
    }

    // Only if the user clicked filter display this.
    if ($filter) {
        $url            = $PAGE->url;
        $coursename     = $DB->get_field('course', 'fullname', ['id' => $id], $strictness = IGNORE_MISSING);
        echo $renderer->render_selection($id, $includeunpublished, $url, $groups, $activityids);
    }

    echo $OUTPUT->box_end();
}

echo $OUTPUT->footer();
