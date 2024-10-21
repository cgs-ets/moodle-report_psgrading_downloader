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
 * Web service function declarations for the plugintype_pluginname plugin.
 *
 * @package   report_psgrading_downloader
 * @copyright 2024, Veronica Bermegui
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$functions = [
    'report_psgrading_downloader_download_reports' => [
        'classname' => 'report_psgrading_downloader\external\api',
        'methodname' => 'download_reports',
        'description' => 'Get the html representation of the reports',
        'type' => 'read',
        'loginrequired' => true,
        'ajax' => true,
    ],
];