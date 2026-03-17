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
use mod_psgrading\persistents\task;
use PSgGradingDownloaderExcelWorkbook;

defined('MOODLE_INTERNAL') || die();

require_once('../../config.php');
global $CFG;
require($CFG->dirroot . '//report//psgrading_downloader//vendor//autoload.php');
/**
 * Undocumented class
 */
class reportmanager {


    private const PS_GRADING_DOWNLOADER_HEADINGSROW = 4;
    private const PS_GRADING_DOWNLOADER_HEADINGTITLES = array('size' => 12, 'bold' => 1, 'text_wrap' => true, 'align' => 'centre');
    private const PS_GRADING_DOWNLOADER_HEADINGSUBTITLES = array('bold' => 1, 'text_wrap' => true, 'align' => 'fill');


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
     * Process activity tasks
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
     * Start full report generation process
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
     * Start task report generation process
     *
     * @param mixed $activities
     * @param mixed $selectedstudents
     * @param mixed $courseid
     * @return string
     */
    public function start_task_report_generation($activities, $selectedstudents, $courseid, $tasksversion, $selectedtasks) {

        $task = new \report_psgrading_downloader\task\generate_task_reports();
        $task->set_custom_data([
            'activities' => $activities,
            'selectedstudents' => $selectedstudents,
            'courseid' => $courseid,
            'tasksversion' => $tasksversion,
            'selectedtasks' => $selectedtasks
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

    /**
     * Generate an Excel file with the PS grading task grades.
     *
     * @param string $activities JSON-encoded activities array.
     * @param string $selectedstudents Comma-separated list of username_userid pairs.
     * @param int $courseid The course ID.
     * @param string $tasksversion JSON-encoded task versions.
     * @param string $selectedtasks Comma-separated task IDs.
     */
    public function download_task_reports($activities, $selectedstudents, $courseid, $tasksversion, $selectedtasks) {
        global $DB;

        \core_php_time_limit::raise();

        $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
        $activities = json_decode($activities);
        $activity = $activities[0];

        // Parse students: "username_userid,username_userid,...".
        $selectedstudents = explode(',', str_replace(['[', ']', '"'], '', $selectedstudents));
        $studentidusername = [];

        foreach ($selectedstudents as $student) {
            $parts = explode('_', trim($student));
            if (count($parts) >= 2) {
                $username = $parts[0];
                $userid = intval($parts[1]);
                $studentidusername[$username] = $userid;
            }
        }

        // Get task details (criterions and engagements).
        $taskdetails = $this->get_activity_task_details($activity->taskids);

        // Get students enrolled in the course that have grades for these tasks.
        $taskidsarray = array_keys($taskdetails);
        $taskidsstr = implode(',', $taskidsarray);
        $students = $this->get_students_in_course($courseid, [0], $taskidsstr);

        // Filter to only selected students.
        $selecteduserids = array_values($studentidusername);
        $students = array_filter($students, function($student) use ($selecteduserids) {
            return in_array($student->id, $selecteduserids);
        });

        // Load Excel dependencies.
        global $CFG;
        require_once($CFG->dirroot . '/report/psgrading_downloader/classes/excelmanager.php');

        // Create workbook.
        $filename = $this->report_psgrading_downloader_clean_filename(
            $course->shortname . '_PS_grading_Tasks_Report'
        );
        $workbook = new PSgGradingDownloaderExcelWorkbook($filename);

        // For each task, create a worksheet.
        foreach ($taskdetails as $taskid => $taskdata) {
            $psgrtask = new task($taskid);
            $taskname = $psgrtask->get('taskname');

            $sheetname = $this->report_psgrading_downloader_clean_filename($taskname);
            $sheetname = substr($sheetname, 0, 31); // Excel sheet name limit.
            $sheet = $workbook->add_worksheet($sheetname);

            // Rows 0-2: Course name, Activity name, Task name.
            $this->report_psgrading_downloader_add_header(
                $workbook, $sheet, $course->fullname, $activity->activity_name, $taskname
            );

            // Filter out hidden criterions and engagements.
            $criterions = array_filter($taskdata->criterions, function($c) {
                return !$c->hidden;
            });
            $engagements = array_filter($taskdata->engagement, function($e) {
                return !$e->hidden;
            });

            // Rows 4-5: Column headers with merged cells.
            $totalcols = $this->write_task_headers($workbook, $sheet, $criterions, $engagements);

            // Merge the title rows across all columns.
            $titleformat = $workbook->add_format(array('size' => 18, 'bold' => 1));
            if ($totalcols > 0) {
                $sheet->merge_cells(0, 0, 0, $totalcols, $titleformat);
                $sheet->merge_cells(1, 0, 1, $totalcols, $titleformat);
                $sheet->merge_cells(2, 0, 2, $totalcols, $titleformat);
            }

            // Row 6+: Student data rows.
            $this->write_student_rows($sheet, $students, $criterions, $engagements, $taskid);
        }

        // Save workbook to temp directory.
        $tempdir = make_temp_directory('report_psgrading_downloader');
        $excelpath = $workbook->savetotempdir($tempdir);

        // Wrap in a zip for download_reports.php compatibility.
        $coursename = $DB->get_field('course', 'fullname', ['id' => $courseid]);
        $dirname = clean_filename($coursename . '.zip');
        $zipfilepath = $tempdir . '/' . $dirname;

        $zipfile = new \zip_archive();
        @unlink($zipfilepath);
        $zipfile->open($zipfilepath);
        $zipfile->add_file_from_pathname(basename($excelpath), $excelpath);
        $zipfile->close();

        // Clean up the xlsx temp file.
        @unlink($excelpath);
    }

    /**
     * Get the active (non-null, non-empty) levels for a criterion or engagement item.
     *
     * @param \stdClass $item A criterion or engagement record.
     * @param int $maxlevels Maximum number of levels (5 for criterions, 4 for engagements).
     * @return array Associative array of level number => level text.
     */
    private function get_active_levels($item, $maxlevels) {
        $levels = [];
        for ($i = 1; $i <= $maxlevels; $i++) {
            $key = 'level' . $i;
            if (isset($item->$key) && $item->$key !== null && trim($item->$key) !== '') {
                $levels[$i] = $item->$key;
            }
        }
        return $levels;
    }

    /**
     * Write the column headers for criterions and engagements (rows 4 and 5).
     *
     * @param PSgGradingDownloaderExcelWorkbook $workbook The workbook instance.
     * @param \MoodleExcelWorksheet $sheet The worksheet.
     * @param array $criterions Array of criterion records.
     * @param array $engagements Array of engagement records.
     * @return int The last column position used.
     */
    private function write_task_headers($workbook, $sheet, $criterions, $engagements) {
        $format = $workbook->add_format(self::PS_GRADING_DOWNLOADER_HEADINGTITLES);
        $format2 = $workbook->add_format(self::PS_GRADING_DOWNLOADER_HEADINGSUBTITLES);
        $groupformat = $workbook->add_format(array('size' => 14, 'bold' => 1, 'align' => 'centre'));

        $pos = 0;

        // "Student" header merged across 2 columns.
        $sheet->write_string(self::PS_GRADING_DOWNLOADER_HEADINGSROW, $pos, 'Student', $format);
        $sheet->merge_cells(self::PS_GRADING_DOWNLOADER_HEADINGSROW, $pos, self::PS_GRADING_DOWNLOADER_HEADINGSROW, $pos + 1, $format);

        // Sub-headers for student columns.
        $sheet->write_string(5, $pos++, 'First Name', $format2);
        $sheet->write_string(5, $pos++, 'Last Name', $format2);

        // Calculate total columns for criterions to write the group title.
        $criterionstart = $pos;
        $criteriontotalcols = 0;
        foreach ($criterions as $criterion) {
            $activelevels = $this->get_active_levels($criterion, 5);
            $criteriontotalcols += count($activelevels);
        }

        // Row 3: "Criterions" group title merged across all criterion columns.
        if ($criteriontotalcols > 0) {
            $sheet->write_string(3, $criterionstart, 'Criterions', $groupformat);
            if ($criteriontotalcols > 1) {
                $sheet->merge_cells(3, $criterionstart, 3, $criterionstart + $criteriontotalcols - 1, $groupformat);
            }
        }

        // Criterion headers (row 4 and 5).
        foreach ($criterions as $criterion) {
            $activelevels = $this->get_active_levels($criterion, 5);
            $levelcount = count($activelevels);

            if ($levelcount == 0) {
                continue;
            }

            // Write criterion description merged across its level columns.
            $sheet->write_string(self::PS_GRADING_DOWNLOADER_HEADINGSROW, $pos, $criterion->description, $format);
            $lastcol = $pos + $levelcount - 1;
            if ($levelcount > 1) {
                $sheet->merge_cells(self::PS_GRADING_DOWNLOADER_HEADINGSROW, $pos, self::PS_GRADING_DOWNLOADER_HEADINGSROW, $lastcol, $format);
            }
            $sheet->set_column($pos, $lastcol, 20);

            // Write level sub-headers.
            foreach ($activelevels as $leveltext) {
                $sheet->write_string(5, $pos++, $leveltext, $format2);
            }
        }

        // Calculate total columns for engagements to write the group title.
        $engagementstart = $pos;
        $engagementtotalcols = 0;
        foreach ($engagements as $engagement) {
            $activelevels = $this->get_active_levels($engagement, 4);
            $engagementtotalcols += count($activelevels);
        }

        // Row 3: "Engagement" group title merged across all engagement columns.
        if ($engagementtotalcols > 0) {
            $sheet->write_string(3, $engagementstart, 'Engagement', $groupformat);
            if ($engagementtotalcols > 1) {
                $sheet->merge_cells(3, $engagementstart, 3, $engagementstart + $engagementtotalcols - 1, $groupformat);
            }
        }

        // Engagement headers (row 4 and 5).
        foreach ($engagements as $engagement) {
            $activelevels = $this->get_active_levels($engagement, 4);
            $levelcount = count($activelevels);

            if ($levelcount == 0) {
                continue;
            }

            // Write engagement description merged across its level columns.
            $sheet->write_string(self::PS_GRADING_DOWNLOADER_HEADINGSROW, $pos, $engagement->description, $format);
            $lastcol = $pos + $levelcount - 1;
            if ($levelcount > 1) {
                $sheet->merge_cells(self::PS_GRADING_DOWNLOADER_HEADINGSROW, $pos, self::PS_GRADING_DOWNLOADER_HEADINGSROW, $lastcol, $format);
            }
            $sheet->set_column($pos, $lastcol, 20);

            // Write level sub-headers.
            foreach ($activelevels as $leveltext) {
                $sheet->write_string(5, $pos++, $leveltext, $format2);
            }
        }

        // Grading info headers: Comment and Graded By.
        $sheet->write_string(self::PS_GRADING_DOWNLOADER_HEADINGSROW, $pos, 'Grading Info', $format);
        $sheet->merge_cells(self::PS_GRADING_DOWNLOADER_HEADINGSROW, $pos, self::PS_GRADING_DOWNLOADER_HEADINGSROW, $pos + 1, $format);
        $sheet->write_string(5, $pos, 'Comment', $format2);
        $sheet->set_column($pos, $pos, 30);
        $pos++;
        $sheet->write_string(5, $pos, 'Graded By', $format2);
        $sheet->set_column($pos, $pos, 20);
        $pos++;

        $sheet->set_row(3, 25);
        $sheet->set_row(self::PS_GRADING_DOWNLOADER_HEADINGSROW, 30, $format);

        return $pos - 1;
    }

    /**
     * Write the student data rows starting at row 6.
     *
     * @param \MoodleExcelWorksheet $sheet The worksheet.
     * @param array $students Array of student user records.
     * @param array $criterions Array of criterion records (non-hidden).
     * @param array $engagements Array of engagement records (non-hidden).
     * @param int $taskid The task ID.
     */
    private function write_student_rows($sheet, $students, $criterions, $engagements, $taskid) {
        $row = 5; // Data starts at row 6 (0-indexed = 5, but incremented before writing).
        $format = array('text_wrap' => true);
        $gradeformat = array('align' => 'centre');

        foreach ($students as $student) {
            $row++;
            $col = 0;

            // Student info (no username).
            $sheet->write_string($row, $col++, $student->firstname, $format);
            $sheet->write_string($row, $col++, $student->lastname, $format);

            // Get grade info for this student and task.
            $gradeinfo = task::get_task_user_gradeinfo($taskid, $student->id);

            // Criterion columns — show grade label e.g. "3(MS)", "1(FH)".
            foreach ($criterions as $criterion) {
                $activelevels = $this->get_active_levels($criterion, 5);

                foreach ($activelevels as $levelnumber => $leveltext) {
                    $value = '';
                    if (!empty($gradeinfo) && isset($gradeinfo->criterions[$criterion->id])) {
                        if ($gradeinfo->criterions[$criterion->id]->gradelevel == $levelnumber) {
                            $value = \mod_psgrading\utils::GRADELANG[(string)$levelnumber]['full'] ?? '';
                        }
                    }
                    $sheet->write_string($row, $col++, $value, $gradeformat);
                }
            }

            // Engagement columns — show grade label e.g. "1(E)", "3(ACC)".
            foreach ($engagements as $engagement) {
                $activelevels = $this->get_active_levels($engagement, 4);

                foreach ($activelevels as $levelnumber => $leveltext) {
                    $value = '';
                    if (!empty($gradeinfo) && isset($gradeinfo->engagements[$engagement->id])) {
                        if ($gradeinfo->engagements[$engagement->id]->gradelevel == $levelnumber) {
                            $value = \mod_psgrading\utils::GRADEENGAGEMENTLANG[(string)$levelnumber]['full'] ?? '';
                        }
                    }
                    $sheet->write_string($row, $col++, $value, $gradeformat);
                }
            }

            // Comment and Graded By columns.
            $comment = '';
            $grader = '';
            if (!empty($gradeinfo)) {
                $comment = $gradeinfo->comment ?? '';
                $grader = $gradeinfo->graderusername ?? '';
            }
            $sheet->write_string($row, $col++, $comment, $format);
            $sheet->write_string($row, $col++, $grader, $format);
        }
    }

    //  Get the criterions and engagement descriptors
    // and levels.
    private function get_activity_task_details($taskids) {
        global $DB;

        // Handle both JSON string and already-decoded array.
        if (is_string($taskids)) {
            $taskids = json_decode($taskids);
        }
        $psgtasks = [];

        foreach($taskids as $id) {
            $psgrtask = new task($id);
            $psgt = new \stdClass();
            $psgt->taskid = $id;
            $psgt->criterions = $psgrtask->get_criterions($id);
            $psgt->engagement = $psgrtask->get_engagement($id);
            $psgtasks[$id] = $psgt;

        }


        return $psgtasks;

    }


    private function report_psgrading_downloader_clean_filename($filename) {
        // Remove or replace problematic characters for Windows file systems
        $filename = preg_replace('/[<>:"|?*;]/', '_', $filename);

        // Replace spaces and dashes with underscores
        $filename = str_replace([' ', '-'], '_', $filename);

        // Remove leading/trailing spaces, dots and underscores
        $filename = trim($filename, ' ._');

        // Replace multiple consecutive underscores with single underscore
        $filename = preg_replace('/_+/', '_', $filename);

        // Ensure filename is not empty
        if (empty($filename)) {
            $filename = 'file';
        }

        // Limit filename length (Windows has 255 char limit)
        if (strlen($filename) > 200) {
            $filename = substr($filename, 0, 200);
            $filename = rtrim($filename, '_');
        }

        return $filename;
    }

    private function report_psgrading_downloader_add_header(\MoodleExcelWorkbook $workbook, \MoodleExcelWorksheet $sheet, $coursename, $modname, $methodname) {

        $format = $workbook->add_format(array('size' => 18, 'bold' => 1));
        $sheet->write_string(0, 0, $coursename, $format);
        $sheet->set_row(0, 24, $format);
        $format = $workbook->add_format(array('size' => 16, 'bold' => 1));
        $sheet->write_string(1, 0, $modname, $format);
        $sheet->set_row(1, 21, $format);
        $sheet->write_string(2, 0, $methodname, $format);
        $sheet->set_row(2, 21, $format);
        
    }



}
