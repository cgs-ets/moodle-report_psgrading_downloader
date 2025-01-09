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

namespace report_psgrading_downloader;


use Dompdf\Dompdf;
use Dompdf\Options;

defined('MOODLE_INTERNAL') || die();

require_once('../../config.php');
global $CFG;
require($CFG->dirroot . '//report//psgrading_downloader//vendor//autoload.php');
/**
 * Undocumented class
 */
class reportmanager {

    /**
     * Get all the activities of type psgrading for a given course.
     * The condition are that the tasks associated to the activity must have
     * criterions that are not empty.
     *
     * @param mixed $courseid
     * @return array
     */
    public function get_psgrading_activities($courseid) {
        global $DB;

        $sql = "SELECT DISTINCT pg.name,
                pg.id AS psgradingid,
                pg.course AS course,
                pg.timecreated AS timecreated,
                pg.timemodified AS timemodified,
                cm.id AS cmid
                FROM {psgrading} pg
                JOIN {course_modules} cm ON pg.id = cm.instance
                JOIN {psgrading_tasks} pt ON pt.cmid = cm.id
                JOIN {psgrading_task_criterions} ptc ON ptc.taskid = pt.id
                WHERE pg.course = :courseid
                AND cm.module = (SELECT id FROM {modules} WHERE name = 'psgrading')
                AND ptc.description IS NOT NULL AND ptc.description <> ''
                AND ptc.level4 IS NOT NULL AND ptc.level4 <> ''
                AND ptc.level3 IS NOT NULL AND ptc.level3 <> ''
                AND ptc.level2 IS NOT NULL AND ptc.level2 <> ''
                AND ptc.subject IS NOT NULL AND ptc.subject <> ''
                ORDER BY pg.name; ";

        $params = ['courseid' => $courseid];

        $results = $DB->get_records_sql($sql, $params);

        return $results;
    }

    /**
     * Filter the students in the course that have at least one graded task (from the selected tasks).
     *
     * @param mixed $courseid
     * @param mixed $groups
     * @param mixed $taskids
     * @return array
     */
    public function get_students_in_course($courseid, $groups, $taskids) {
        global $DB;

        // Get the required user fields including the picture field.
        $userfieldsapi = \core_user\fields::for_userpic();
        $userfields = $userfieldsapi->get_sql('u', false, '', '', false)->selects;
        $userfields .= ', u.username';
        if (count($groups) === 1 && $groups[0] == 0 || empty($groups[0])) {
            // Get all students in the course.
            $sql = "SELECT DISTINCT $userfields
                    FROM {user} u
                    JOIN {user_enrolments} ue ON u.id = ue.userid
                    JOIN {enrol} e ON ue.enrolid = e.id
                    JOIN {role_assignments} ra ON ra.userid = u.id
                    JOIN {context} ct ON ra.contextid = ct.id
                    JOIN {course} c ON c.id = e.courseid
                    JOIN {role} r ON ra.roleid = r.id
                    JOIN {psgrading_grades} pg ON pg.studentusername = u.username
                    WHERE c.id = :courseid
                    AND r.shortname = :shortname
                    AND ct.contextlevel = :contextlevel
                    AND ct.instanceid = c.id
                    AND pg.taskid IN ($taskids)
                    ORDER BY u.lastname";
            $params = ['courseid' => $courseid, 'shortname' => 'student', 'contextlevel' => CONTEXT_COURSE];
        } else {
            // Get students in specific groups.
            list($insql, $inparams) = $DB->get_in_or_equal($groups, SQL_PARAMS_NAMED, 'groupid');
            $sql = "SELECT DISTINCT $userfields
                    FROM {user} u
                    JOIN {user_enrolments} ue ON u.id = ue.userid
                    JOIN {enrol} e ON ue.enrolid = e.id
                    JOIN {role_assignments} ra ON ra.userid = u.id
                    JOIN {context} ct ON ra.contextid = ct.id
                    JOIN {course} c ON c.id = e.courseid
                    JOIN {role} r ON ra.roleid = r.id
                    JOIN {groups_members} gm ON gm.userid = u.id
                    JOIN {psgrading_grades} pg ON pg.studentusername = u.username
                    WHERE c.id = :courseid
                    AND r.shortname = :shortname
                    AND ct.contextlevel = :contextlevel
                    AND ct.instanceid = c.id
                    AND pg.taskid IN ($taskids)
                    AND gm.groupid $insql";
            $params = array_merge(['courseid' => $courseid, 'shortname' => 'student', 'contextlevel' => CONTEXT_COURSE], $inparams);
        }

        $students = $DB->get_records_sql($sql, $params);



        return $students;
    }


    /**
     * Undocumented function
     *
     * @param mixed $activityid
     * @param mixed $includeunpublished
     * @return array
     */
    public function get_activity_tasks($activityid, $includeunreleased) {
        global $DB;
        $ids = implode(',', $activityid);

        $sql = "SELECT t.id, ps.name AS activity_name, t.*
                FROM {psgrading_tasks} t
                JOIN {course_modules} cm ON t.cmid = cm.id
                JOIN {psgrading} ps ON ps.id = cm.instance
                WHERE cm.id IN ($ids)
                AND cm.module = (SELECT id FROM {modules} WHERE name = 'psgrading')
                AND t.deleted = 0
                AND t.published = 1"; // Only published (visible) tasks.

        if (!$includeunreleased) {
            $sql .= " AND t.timerelease <> 0";
        }

        $sql .= " ORDER BY ps.name";

        $tasks = $DB->get_records_sql($sql);

        return $tasks;

    }

     /**
      * Get all groups in the course.
      *
      * @param mixed $courseid
      * @return void
      */
    public function get_groups_in_course($courseid) {

        $groups = groups_get_all_groups($courseid);

        $groupswithstudents = [];

        foreach ($groups as $group) {
            $members = groups_get_members($group->id, 'u.id');
            if (!empty($members)) {
                $groupswithstudents[$group->id] = $group;
            }
        }

        return $groupswithstudents;

    }

    /**
     * Generates a PDF version of the PS grading reports
     *
     * @param mixed $activities
     * @param mixed $selectedstudents
     * @param mixed $courseid
     * @return void
     *
     */
    public function download_reports($activities, $selectedstudents, $courseid, $tasksversion) {
        global $PAGE;

        // Increase the server timeout to handle the creation and sending of large zip files.
        \core_php_time_limit::raise();

        // Remove brackets and quotes from the string.
        $selectedstudents = explode(',', str_replace(['[', ']', '"'], '', $selectedstudents));
        $studentidusername = [];
        $tasksversion = self::tasks_version($tasksversion);
        //

        // Loop through each item and split into username and userid.
        foreach ($selectedstudents as $student) {
            list($username, $userid) = explode('_', trim($student));
            $studentidusername[$username] = intval($userid);
        }

        $activities = json_decode($activities);
        $grouptasksbyactivity = [];
        $studentnames = [];
        $activitynames = [];
        $batchsize = 5;
        $output = $PAGE->get_renderer('report_psgrading_downloader');

        foreach ($activities as $activity) {
            $activitynames[$activity->cmid] = trim($activity->activity_name);
            foreach (array_chunk($activity->taskids, $batchsize) as $taskchunk) {
                foreach (array_chunk($studentidusername, $batchsize, true) as $studentchunk) {
                    $this->process_activity_tasks($taskchunk,
                                                    $activity,
                                                    $studentchunk,
                                                    $output,
                                                    $grouptasksbyactivity,
                                                    $studentnames,
                                                    $tasksversion);
                }
            }
        }

        list($pdfs, $tempdir) = $this->generate_pdf($grouptasksbyactivity, $studentnames, $activitynames);
        $this->save_generated_reports($pdfs, $courseid, $tempdir);

    }

    /**
     * Undocumented function
     *
     * @param mixed $taskchunk
     * @param mixed $activity
     * @param mixed $studentchunk
     * @param mixed $output
     * @param mixed $grouptasksbyactivity
     * @param mixed $studentnames
     * @return void
     */
    private function process_activity_tasks($taskchunk, $activity, $studentchunk, $output, &$grouptasksbyactivity, &$studentnames, $tasksversion) {
        \core_php_time_limit::raise();


        foreach ($taskchunk as $taskid) {
            foreach ($studentchunk as $username => $studentid) {
                $data = $output->task_details($taskid, $studentid, $username, $activity, $tasksversion[$taskid]);
                $studentnames[$studentid] = $data['student'];
                $template = 'report_psgrading_downloader/report_template';
                $grouptasksbyactivity[$activity->cmid][$studentid][] = $output->render_from_template($template, $data);
            }
        }
    }

    /**
     * Undocumented function
     *
     * @param mixed $studenttasktemplates
     * @param mixed $studentnames
     * @param mixed $activitynames
     * @return void
     */
    private function generate_pdf($studenttasktemplates, $studentnames, $activitynames) {
        global $PAGE;

        $pdfs = [];
        \core_php_time_limit::raise();

        $tempdir = make_temp_directory('report_psgrading_downloader');
        $renderer = $PAGE->get_renderer('report_psgrading_downloader');

        foreach ($studenttasktemplates as $cmid => $module) {
            foreach ($module as $studentid => $studenttemplates) {


                $renderer->sanitisetemplate($studenttemplates);
                $tasks = implode($studenttemplates);

                $options = new Options();
                $options->set('tempDir', $tempdir);
                $options->set('isRemoteEnabled', true);
                $options->set('isHtml5ParserEnabled', true);
                $dompdf = new Dompdf($options);
                $dompdf->loadHtml($tasks);
                $dompdf->setPaper('a2', 'landscape');
                $dompdf->render();

                $output = $dompdf->output();
                // Add the activity name as part of the name.
                $filename = str_replace(' ', '_', $studentnames[$studentid]->fullname . '_' .$activitynames[$cmid]) .'.pdf';
                // Save the PDF to a temporary file.
                $tempfilepath = $tempdir . '/' . $filename;
                file_put_contents($tempfilepath, $output);
                $pdfs[$filename] = $tempfilepath;

            }
        }

        return [$pdfs, $tempdir];
    }

    /**
     * Undocumented function
     *
     * @param mixed $pdfs
     * @param mixed $courseid
     * @param mixed $tempdir
     * @return void
     */
    private function save_generated_reports_ORIGINAL($pdfs, $courseid, $tempdir) {
        global $DB;
        \core_php_time_limit::raise();

        $coursename = $DB->get_field('course', 'fullname', ['id' => $courseid]);
        $dirname = clean_filename($coursename . '.zip'); // Main folder.
        $zipfilepath = $tempdir . '/' . $dirname;

        // Create the zip.
        $zipfile = new \zip_archive();
        @unlink($zipfilepath);
        $zipfile->open($zipfilepath);

        foreach ($pdfs as $filename => $tempfilepath) {
            $zipfile->add_file_from_pathname($filename, $tempfilepath);
        }

        $zipfile->close();

        header("Content-Type: application/zip");
        header("Content-Disposition: attachment; filename=$dirname");
        header("Content-Length: " . filesize($zipfilepath));
        readfile($zipfilepath);
        unlink($zipfilepath);

        // Clean up temporary files.
        foreach ($pdfs as $tempfilepath) {
            unlink($tempfilepath);
        }

        die(); // If not set, an invalid zip file error is thrown.
    }

    /**
     * Generate a .zip file with the PDF reports.
     *
     * @param mixed $pdfs
     * @param mixed $courseid
     * @param mixed $tempdir
     * @return void
     */
    private function save_generated_reports_NEW($pdfs, $courseid, $tempdir) {
        global $DB;
        \core_php_time_limit::raise();

        $coursename = $DB->get_field('course', 'fullname', ['id' => $courseid]);
        $dirname = clean_filename($coursename . '.zip'); // Main folder.
        $zipfilepath = $tempdir . '/' . $dirname;

        // Create the zip.
        $zipfile = new \zip_archive();
        @unlink($zipfilepath);
        $zipfile->open($zipfilepath);

        foreach ($pdfs as $filename => $tempfilepath) {
            $zipfile->add_file_from_pathname($filename, $tempfilepath);
        }

        $zipfile->close();

        // Clean up temporary files.
        foreach ($pdfs as $tempfilepath) {
            unlink($tempfilepath);
        }

        // Return the path to the zip file.
        return $zipfilepath;
    }

    private function save_generated_reports($pdfs, $courseid, $tempdir) {
        global $DB;
        \core_php_time_limit::raise();

        $coursename = $DB->get_field('course', 'fullname', ['id' => $courseid]);
        $baseDirname = clean_filename($coursename);
        $zipIndex = 1;
        $zipFilepaths = [];



        // Create the first zip file
        list($zipfile, $zipfilepath) = $this->create_new_zip($baseDirname, $zipIndex, $tempdir);
        $zipFilepaths[] = $zipfilepath;

        foreach ($pdfs as $filename => $tempfilepath) {
            // Check if the current zip file has reached a limit (e.g., 1000 files)
            if ($zipfile->numFiles >= 1000) {
                $zipfile->close();
                $zipIndex++;
                list($zipfile, $zipfilepath) = $this->create_new_zip($baseDirname, $zipIndex, $tempdir);
                $zipFilepaths[] = $zipfilepath;
            }
            $zipfile->add_file_from_pathname($filename, $tempfilepath);
        }

        $zipfile->close();

        // Clean up temporary files
        foreach ($pdfs as $tempfilepath) {
            unlink($tempfilepath);
        }

        // Create a master zip file containing all the individual zip files
        $masterZipFilepath = $tempdir . '/' . $baseDirname . '.zip';
        $masterZipfile = new \zip_archive();
        @unlink($masterZipFilepath);
        $masterZipfile->open($masterZipFilepath);

        foreach ($zipFilepaths as $zipFilepath) {
            $masterZipfile->add_file_from_pathname(basename($zipFilepath), $zipFilepath);
        }

        $masterZipfile->close();

        // Clean up individual zip files
        foreach ($zipFilepaths as $zipFilepath) {
            unlink($zipFilepath);
        }

        // Return the path to the master zip file
        return $masterZipFilepath;
    }

    // Function to create a new zip file.
    private function create_new_zip($baseDirname, $zipIndex, $tempdir) {
        $dirname = $baseDirname . '_' . $zipIndex . '.zip';
        $zipfilepath = $tempdir . '/' . $dirname;
        $zipfile = new \zip_archive();
        @unlink($zipfilepath);
        $zipfile->open($zipfilepath);
        return [$zipfile, $zipfilepath];
    }


    /**
     * Undocumented function
     *
     * @param mixed $activities
     * @param mixed $selectedstudents
     * @param mixed $courseid
     * @return string
     */
    public function start_report_generation($activities, $selectedstudents, $courseid, $tasksversion) {

        $task = new \report_psgrading_downloader\task\generate_reports();
        $task->set_custom_data([
            'activities' => $activities,
            'selectedstudents' => $selectedstudents,
            'courseid' => $courseid,
            'tasksversion' => $tasksversion,
        ]);

        $taskid = \core\task\manager::queue_adhoc_task($task);

        // Return the task ID to the user.
        return json_encode(['status' => 'started', 'taskid' => $taskid]);
    }

    /**
     * Undocumented function
     *
     * @return string
     */
    public function get_adhoc_task_status($taskid) {
        global $DB;

        $record = $DB->get_record('report_psgrading_downloader_tasks', ['taskid' => $taskid]);

        if ($record) {
            return $record->status;
        } else {
            return 'not_found';
        }
    }

    /**
     * unstructure the tasksversion parameter
     *
     * @param mixed $tasksversion
     * @return array
     */
    public function tasks_version($tasksversion) {
        $tasksversion = json_decode($tasksversion);

        $aux = [];
        foreach ($tasksversion as $version) {
            $v = explode('_', $version);
            $taskid = $v[0];
            $version = $v[1];
            $aux[$taskid] = $version;
        }

        return $aux;
    }


}
