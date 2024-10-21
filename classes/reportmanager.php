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
 * @category    reportmanager
 * @copyright   2024 Veronica Bermegui
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_psgrading_downloader;

use TCPDF;

use Dompdf\Dompdf;
use Dompdf\Options;
use mod_psgrading\external\task_exporter;
use mod_psgrading\persistents\task;
use stdClass;

require_once($CFG->libdir . '/tcpdf/tcpdf.php');
require 'vendor\autoload.php';


defined('MOODLE_INTERNAL') || die();

global $CFG;

class reportmanager {

      /**
     *
     * @courseid course id
     */
    public function get_psgrading_activities($courseid) {
        global $DB;

        $sql = "SELECT pg.name,
                pg.id AS psgradingid,
                pg.course AS course,
                pg.timecreated AS timecreated,
                pg.timemodified AS timemodified,
                cm.id AS cmid
                FROM {psgrading} pg
                JOIN {course_modules} cm ON pg.id = cm.instance
                WHERE pg.course = ?
                AND cm.module = (SELECT id FROM {modules} WHERE name = 'psgrading')
                ORDER BY pg.name";

        $params = ['course' => $courseid];

        $results = $DB->get_records_sql($sql, $params);

        return $results;
    }


    public function get_students_in_course($courseid) {
        global $DB;

        $sql = "SELECT u.id , u.firstname, u.lastname, u.email, u.username
                FROM {user} u
                JOIN {user_enrolments} ue ON u.id = ue.userid
                JOIN {enrol} e ON ue.enrolid = e.id
                JOIN {role_assignments} ra ON ra.userid = u.id
                JOIN {context} ct ON ra.contextid = ct.id
                JOIN {course} c ON c.id = e.courseid
                JOIN {role} r ON ra.roleid = r.id
                WHERE c.id = :courseid;";

        $params = ['courseid' => $courseid];

        $students = $DB->get_records_sql($sql, $params);


        return $students;
    }

    public function get_activity_tasks($activityid, $includeunpublished) {
        global $DB;
        $ids = implode(',', $activityid);

        $sql = "SELECT t.id, ps.name AS activity_name, t.*
                FROM {psgrading_tasks} t
                JOIN {course_modules} cm ON t.cmid = cm.id
                JOIN {psgrading} ps ON ps.id = cm.instance
                WHERE cm.id IN ($ids)
                AND cm.module = (SELECT id FROM {modules} WHERE name = 'psgrading')
                AND t.deleted = 0";


        if (!$includeunpublished) {
            $sql .= " AND t.published = 1";
        }

        $sql .= " ORDER BY ps.name";

        $tasks = $DB->get_records_sql($sql);

        return $tasks;

    }


    public function download_reports($activities, $selectedstudents, $courseid) {
        global $DB, $CFG, $OUTPUT, $PAGE;
        // Increase the server timeout to handle the creation and sending of large zip files.
        \core_php_time_limit::raise();

        // Remove brackets and quotes from the string
        $selectedstudents = explode(',', str_replace(['[', ']', '"'], '', $selectedstudents));

        $studentidusername = [];

        // Loop through each item and split into username and userid
        foreach ($selectedstudents as $student) {
            list($username, $userid) = explode('_', trim($student));
            $studentidusername[$username] = intval($userid);
        }

        $activities = json_decode($activities);
        $grouptasksbyactivity = [];
        $studentnames = [];
        $output = $PAGE->get_renderer('report_psgrading_downloader');

        foreach($activities as $activity) {
            foreach($activity->taskids as $taskid) {
                foreach($studentidusername as $username => $studentid) {
                    $data = $output->task_details($taskid, $studentid, $username, $activity);
                    $studentnames[$studentid] = $data['student'];
                    $grouptasksbyactivity[$activity->cmid][$studentid][] = $output->render_from_template('report_psgrading_downloader/report_template', $data);
                }

            }
        }


        list($pdfs, $tempDir) = $this->generate_pdf($grouptasksbyactivity, $studentnames);
        $this->save_generated_reports($pdfs, $courseid, $tempDir);


    }

    private function generate_pdf($studenttasktemplates, $studentnames) {
        global $PAGE;
        $options = new Options();
        $options->set('isRemoteEnabled', true); // To be able to display the profile image
        $pdfs = [];

        $tempDir = make_temp_directory('report_psgrading_downloader');
        $renderer = $PAGE->get_renderer('report_psgrading_downloader');

        foreach($studenttasktemplates as $cmid => $module) {
            foreach($module as $studentid => $studenttemplates) {
                $renderer->sanitisetemplate($studenttemplates);
                $tasks = implode($studenttemplates);
                $dompdf = new Dompdf($options);
                $dompdf->loadHtml($tasks);
                $dompdf->setPaper('a0', 'landscape');
                $dompdf->render();
                $output = $dompdf->output();

                $filename = str_replace(' ', '_', $studentnames[$studentid]->fullname) . '.pdf';
                // In case there is a student with the same name
                if (array_key_exists($filename, $pdfs)) {
                    $r = strval(rand());
                    $filename = str_replace(' ', '_', $studentnames[$studentid]->fullname) . '_'. $r .'.pdf';
                }

                // Save the PDF to a temporary file
                $tempFilePath = $tempDir . '/' . $filename;
                file_put_contents($tempFilePath, $output);

                $pdfs[$filename] = $tempFilePath;

            }
        }

        return [$pdfs, $tempDir];

    }

    private function save_generated_reports($pdfs, $courseid, $tempDir) {
        global $DB;
        $coursename = $DB->get_field('course', 'fullname', array('id' => $courseid));
        $dirname = clean_filename($coursename . '.zip'); // Main folder.
        // echo '<pre>';
        // echo  'pdfs '. print_r($pdfs, true);
        // echo '</pre>';  exit;
        // Use the provided tempDir for the zip file
        $zipFilePath = $tempDir . '/' . $dirname;

        // Create the zip.
        $zipfile = new \zip_archive();
        @unlink($zipFilePath);
        $zipfile->open($zipFilePath);

        foreach ($pdfs as $filename => $tempFilePath) {
            $zipfile->add_file_from_pathname($filename, $tempFilePath);
        }

        $zipfile->close();

        header("Content-Type: application/zip");
        header("Content-Disposition: attachment; filename=$dirname");
        header("Content-Length: " . filesize($zipFilePath));
        readfile($zipFilePath);
        unlink($zipFilePath);

        // Clean up temporary files
        foreach ($pdfs as $tempFilePath) {
            unlink($tempFilePath);
        }

        // Remove the temporary directory
        // remove_dir($tempDir);

        die(); // If not set, an invalid zip file error is thrown.
    }



}