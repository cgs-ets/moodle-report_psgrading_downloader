<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 *
 * @package     report_psgrading_downloader
 * @copyright   2024 Veronica Bermegui
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace report_psgrading_downloader\task;

defined('MOODLE_INTERNAL') || die();


/**
 * Manage the adhoc task to generate the pdf reports
 */
class generate_reports extends \core\task\adhoc_task {
    /**
     * Undocumented function
     *
     * @return void
     */
    public function execute() {
        global $CFG;
        require_once($CFG->dirroot . '//report//psgrading_downloader//vendor//autoload.php');

        $data = $this->get_custom_data();
        $taskid = $this->get_id();

        // Update status to 'in_progress'.
        $this->update_task_status($taskid, 'in_progress');

        try {

            $manager = new \report_psgrading_downloader\reportmanager();
            $manager->download_reports($data->activities, $data->selectedstudents, $data->courseid);

            // Update status to 'complete'.
            $this->update_task_status($taskid, 'complete');
        } catch (\Exception $e) {
            // Update status to 'failed'.
            $this->update_task_status($taskid, 'failed');
            throw $e;
        }
    }

    /**
     * Undocumented function
     *
     * @param mixed $taskid
     * @param mixed $status
     * @return void
     */
    private function update_task_status($taskid, $status) {
        global $DB;

        $record = $DB->get_record('report_psgrading_downloader_tasks', ['taskid' => $taskid]);
        if ($record) {
            $record->status = $status;
            $record->timemodified = time();
            $DB->update_record('report_psgrading_downloader_tasks', $record);
        } else {
            $record = new \stdClass();
            $record->taskid = $taskid;
            $record->status = $status;
            $record->timecreated = time();
            $record->timemodified = time();
            $DB->insert_record('report_psgrading_downloader_tasks', $record);
        }
    }
}

