<?php
// This file is part of Moodle - http://moodle.org/
//
// Quick test script for the Excel task report generation.
// Run from browser: /report/psgrading_downloader/test_excel.php?id=COURSEID
// Optional params: &taskid=TASKID &cmid=CMID
//
// This script bypasses the adhoc task and calls download_task_reports() directly,
// allowing quick iteration without waiting for cron.
//
// @package    report_psgrading_downloader
// @copyright  2026 Veronica Bermegui

require_once('../../config.php');

// Force OPcache to pick up file changes.
if (function_exists('opcache_reset')) {
    opcache_reset();
}

global $CFG, $DB, $PAGE, $OUTPUT;

$courseid = required_param('id', PARAM_INT);
$taskid   = optional_param('taskid', 0, PARAM_INT);
$cmid     = optional_param('cmid', 0, PARAM_INT);
$download = optional_param('download', 0, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($course->id);
require_capability('mod/psgrading:addinstance', $context);

// Handle direct file download.
if ($download) {
    $tempdir = $CFG->tempdir . '/report_psgrading_downloader';
    $coursename = $DB->get_field('course', 'fullname', ['id' => $courseid]);
    $dirname = clean_filename($coursename . '.zip');
    $zipfilepath = $tempdir . '/' . $dirname;

    if (file_exists($zipfilepath)) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $dirname . '"');
        header('Content-Length: ' . filesize($zipfilepath));
        readfile($zipfilepath);
        unlink($zipfilepath);
        die();
    } else {
        die('File not found: ' . $zipfilepath);
    }
}

$manager = new report_psgrading_downloader\reportmanager();

// If no taskid provided, show available tasks and let the user pick.
if (empty($taskid)) {
    $PAGE->set_url(new moodle_url('/report/psgrading_downloader/test_excel.php', ['id' => $courseid]));
    $PAGE->set_title('Test Excel Report');
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    echo '<h2>Test Excel Task Report</h2>';

    // Get all psgrading activities for this course.
    $activities = $manager->get_psgrading_activities($courseid);

    if (empty($activities)) {
        echo '<p>No PS Grading activities found in this course.</p>';
        echo $OUTPUT->footer();
        die();
    }

    echo '<h3>Available activities and tasks:</h3>';

    foreach ($activities as $activity) {
        echo '<h4>' . format_string($activity->name) . ' (cmid: ' . $activity->cmid . ')</h4>';

        // Get tasks for this activity.
        $tasks = $manager->get_activity_tasks([$activity->cmid], true); // Include unreleased.

        if (empty($tasks)) {
            echo '<p>No tasks found.</p>';
            continue;
        }

        echo '<table class="table table-striped">';
        echo '<tr><th>Task ID</th><th>Task Name</th><th>Has Grades</th><th>Action</th></tr>';

        foreach ($tasks as $task) {
            $hasgrades = \mod_psgrading\persistents\task::has_grades($task->id) ? 'Yes' : 'No';
            $url = new moodle_url('/report/psgrading_downloader/test_excel.php', [
                'id' => $courseid,
                'taskid' => $task->id,
                'cmid' => $activity->cmid,
            ]);
            echo '<tr>';
            echo '<td>' . $task->id . '</td>';
            echo '<td>' . format_string($task->taskname) . '</td>';
            echo '<td>' . $hasgrades . '</td>';
            echo '<td><a href="' . $url . '" class="btn btn-primary btn-sm">Generate Excel</a></td>';
            echo '</tr>';
        }
        echo '</table>';
    }

    echo $OUTPUT->footer();
    die();
}

// A task was selected — generate the Excel directly.

// We need to build the same parameters that download_task_reports() expects.

// 1. Build activities JSON.
if (empty($cmid)) {
    // Find the cmid for this task.
    $taskrecord = $DB->get_record('psgrading_tasks', ['id' => $taskid], 'cmid', MUST_EXIST);
    $cmid = $taskrecord->cmid;
}

$cm = get_coursemodule_from_id('psgrading', $cmid, $courseid, false, MUST_EXIST);
$psgrading = $DB->get_record('psgrading', ['id' => $cm->instance], '*', MUST_EXIST);

$activityobj = new stdClass();
$activityobj->cmid = $cmid;
$activityobj->activity_name = $psgrading->name;
$activityobj->taskids = json_encode([$taskid]);

$activitiesjson = json_encode([$activityobj]);

// 2. Get all students that have grades for this task.
$students = $manager->get_students_in_course($courseid, [0], (string)$taskid);

if (empty($students)) {
    $PAGE->set_url(new moodle_url('/report/psgrading_downloader/test_excel.php', ['id' => $courseid]));
    $PAGE->set_title('Test Excel Report');
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    echo '<div class="alert alert-warning">No students with grades found for this task (ID: ' . $taskid . ').</div>';
    echo '<a href="' . new moodle_url('/report/psgrading_downloader/test_excel.php', ['id' => $courseid]) . '">Back</a>';
    echo $OUTPUT->footer();
    die();
}

// Build selectedstudents string: "username_userid,username_userid,...".
$selectedstudentsarr = [];
foreach ($students as $student) {
    $selectedstudentsarr[] = $student->username . '_' . $student->id;
}
$selectedstudentsstr = implode(',', $selectedstudentsarr);

// 3. Build tasksversion JSON.
$taskrecord = $DB->get_record('psgrading_tasks', ['id' => $taskid], '*', MUST_EXIST);
$tasksversion = json_encode([$taskid . '_' . ($taskrecord->oldorder ?? 0)]);

// 4. selectedtasks (not used directly in current implementation but passed through).
$selectedtasks = json_encode([$taskid]);

// Debug output before generating.
echo '<pre>';
echo "Course: {$course->shortname} (ID: {$courseid})\n";
echo "Activity: {$psgrading->name} (cmid: {$cmid})\n";
echo "Task ID: {$taskid}\n";
echo "Task Name: {$taskrecord->taskname}\n";
echo "Students: " . count($students) . "\n";
echo "Selected students: {$selectedstudentsstr}\n";
echo "\nActivities JSON: {$activitiesjson}\n";
echo "Tasks version: {$tasksversion}\n";
echo '</pre>';

echo '<p>Generating Excel report...</p>';

try {
    $manager->download_task_reports(
        $activitiesjson,
        $selectedstudentsstr,
        $courseid,
        $tasksversion,
        $selectedtasks
    );

    // Check if the zip was created.
    $tempdir = $CFG->tempdir . '/report_psgrading_downloader';
    $coursename = $DB->get_field('course', 'fullname', ['id' => $courseid]);
    $dirname = clean_filename($coursename . '.zip');
    $zipfilepath = $tempdir . '/' . $dirname;

    if (file_exists($zipfilepath)) {
        echo '<div class="alert alert-success">';
        echo 'Excel report generated successfully!<br>';
        echo 'Zip file: ' . $zipfilepath . '<br>';
        echo 'Size: ' . number_format(filesize($zipfilepath)) . ' bytes<br>';

        // Provide a direct download link.
        $downloadurl = new moodle_url('/report/psgrading_downloader/test_excel.php', [
            'id' => $courseid,
            'taskid' => $taskid,
            'cmid' => $cmid,
            'download' => 1,
        ]);
        echo '<a href="' . $downloadurl . '" class="btn btn-success">Download Excel Report</a>';
        echo '</div>';
    } else {
        echo '<div class="alert alert-danger">';
        echo 'Zip file was not found at expected location: ' . $zipfilepath . '<br>';
        // List what is in tempdir.
        if (is_dir($tempdir)) {
            echo 'Files in temp dir:<br>';
            foreach (scandir($tempdir) as $file) {
                if ($file !== '.' && $file !== '..') {
                    echo '  - ' . $file . ' (' . filesize($tempdir . '/' . $file) . ' bytes)<br>';
                }
            }
        } else {
            echo 'Temp directory does not exist: ' . $tempdir;
        }
        echo '</div>';
    }
} catch (\Exception $e) {
    echo '<div class="alert alert-danger">';
    echo '<strong>Error:</strong> ' . $e->getMessage() . '<br>';
    echo '<pre>' . $e->getTraceAsString() . '</pre>';
    echo '</div>';
}

echo '<br><a href="' . new moodle_url('/report/psgrading_downloader/test_excel.php', ['id' => $courseid]) . '">Back to task list</a>';
