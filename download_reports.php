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
 * @copyright  2024 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

global $CFG;
$taskid = required_param('taskid', PARAM_ALPHANUM);
$courseid = required_param('courseid', PARAM_ALPHANUM);
$record = $DB->get_record('report_psgrading_downloader_tasks', ['taskid' => $taskid]);

if ($record && $record->status === 'complete') {

    $coursename = $DB->get_field('course', 'fullname', ['id' => $courseid]);
        $dirname = clean_filename($coursename . '.zip'); // Main folder.
        $tempdir = $CFG->tempdir . '/report_psgrading_downloader';
        $zipfilepath = $tempdir . '/' . $dirname;

    if (file_exists($zipfilepath)) {
        header("Content-Type: application/zip");
        header("Content-Disposition: attachment; filename=" . basename($zipfilepath));
        header("Content-Length: " . filesize($zipfilepath));
        readfile($zipfilepath);

        // Delete the zip file after it has been downloaded.
        unlink($zipfilepath);
        exit;
    } else {

        $message = get_string('filenotfound', 'report_assignfeedback_download');
        $level = core\output\notification::NOTIFY_ERROR;
        \core\notification::add($message, $level);
    }
} else {
    $message = get_string('tasknotfound', 'report_assignfeedback_download');
    $level = core\output\notification::NOTIFY_ERROR;
    \core\notification::add($message, $level);
}

