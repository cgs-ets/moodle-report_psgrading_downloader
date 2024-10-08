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
 * Public API of the competency report.
 *
 * Defines the APIs used by competency reports
 *
 * @package    report_psgrading_downloader
 * @copyright  2024 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


function report_psgrading_downloader_extend_navigation_course($navigation, $course, $context) {

    if (has_capability('moodle/site:viewuseridentity', $context)) {
        $url = new moodle_url('/report/psgrading_downloader/index.php', array('id' => $course->id, 'cmid' => $context->id));
        $navigation->add(get_string('pluginname', 'report_psgrading_downloader'), $url, navigation_node::COURSE_INDEX_PAGE, null, null, new pix_icon('i/report', ''));
    }
}