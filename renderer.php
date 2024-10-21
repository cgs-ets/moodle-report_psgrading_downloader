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
 * @package    report_psgrading_downloader
 * @copyright  2024 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use mod_psgrading\external\task_exporter;
use mod_psgrading\utils;
use report_psgrading_downloader\reportmanager;
use \mod_psgrading\persistents\task;

class report_psgrading_downloader_renderer extends plugin_renderer_base {

    public function render_selection($courseid, $activityids = [], $includeunpublished, $url) {
        $manager = new reportmanager();
        // Get the students
        $students = $manager->get_students_in_course($courseid);
    
        // Get the tasks for each activity
        $tasks = $manager->get_activity_tasks($activityids, $includeunpublished);

        // echo '<pre> TASKS';
        // echo print_r($tasks, true);
        // echo '</pre>'; exit;

        $psgradingjson = [];

    
        // Group tasks by activity
        $tasksByActivity = [];
        foreach ($tasks as $task) {
            $tasksByActivity[$task->activity_name][] = $task->taskname;
            // Collect the details
            if (array_key_exists($task->activity_name, $psgradingjson)) {
                $details = $psgradingjson[$task->activity_name];
                array_push($details->taskids , $task->id);
                $psgradingjson[$task->activity_name] = $details;
            } else {
                $activitydetail = new stdClass();
                $activitydetail->cmid = $task->cmid;
                $activitydetail->activity_name = $task->activity_name;
                $activitydetail->taskids = [$task->id];
                $psgradingjson[$task->activity_name] = $activitydetail;
            }
        }

        // echo print_r($tasksByActivity, true);
        // echo '</pre>'; exit;
        $psgradingjson = array_values($psgradingjson);
    
        // Format tasks for each activity
        $formattedTasksByActivity = [];
        foreach ($tasksByActivity as $activity => $taskNames) {
            $formattedTasksByActivity[$activity] = implode("<br>", array_filter(array_map('trim', $taskNames)));
        }
        
        $allstudents = [];
        foreach ($students as $student) {
            $value = $student->username . '_'.  $student->id;
            $checkbox = '<input type="checkbox" name="select_student[]" value="' . $value . '">';
            $profile_picture_url = $this->user_picture($student, array('size' => 50, 'link' => false));
            $name_with_picture = $profile_picture_url . ' ' . $student->firstname . ' ' . $student->lastname;
            $allstudents[] = $student->username . '_'.  $student->id;
    
            // Create a row for each student with tasks grouped by activity
            $taskColumns = [];
            foreach ($formattedTasksByActivity as $activity => $tasks) {
                $taskColumns[] = $tasks;
            }
    
            $row = array_merge([$checkbox, $name_with_picture], $taskColumns);
            $dataaux['rows'][] = $row;
        }
    
        $selectall = '<input type="checkbox" name="select_all" value="all" title ="Select all">';

        $headers = array_merge([$selectall, 'Name'], array_keys($formattedTasksByActivity));
    
        $data = [
            'headers' => $headers,
            'action' => $url,
            'rows' => array_map(function($row) {
                return ['columns' => $row];
            }, $dataaux['rows']),
            'psgradingjson' => json_encode($psgradingjson),
            'allstudents' => json_encode($allstudents),
            'id' => $courseid
        ];

        // echo '<pre>';
        // echo print_r($data, true);
        // echo '</pre>'; exit;
    
        $template = $this->render_from_template('report_psgrading_downloader/main', $data);
    
        // Output the rendered template
        echo $template;
    }
    

    // Get a task context details. 
    //Based on get_other_values from  mod\psgrading\classes\external\details_exporter.php
    public function task_details($taskid, $studentid, $username, $activity) {
        global $CFG;
    
        $task = new Task($taskid);
        $taskexporter = new task_exporter($task, array('userid' => $username)); // userid? username userid in synergetic
        $task = $taskexporter->export($this);

        $student = \core_user::get_user($studentid);
    
        utils::load_user_display_info($student);

        // Get existing marking values for this user and incorporate into task criterion data.
        $gradeinfo = task::get_task_user_gradeinfo($task->id, $username);

        // Load task criterions.
        $task->criterions = task::get_criterions($task->id);
    
        foreach ($task->criterions as $i => $criterion) {
            if ($criterion->hidden) {
                unset($task->criterions[$i]);
                continue;
            }
            // Add selections only if task is released. And not hidden from student.
            if (isset($gradeinfo->criterions[$criterion->id]) && $task->released && !utils::is_hide_ps_grades()) {
                // There is a gradelevel chosen for this criterion.
                $criterion->{'level' . $gradeinfo->criterions[$criterion->id]->gradelevel . 'selected'} = true;
            }
        }
    
        // TODO: comment the engagement section until you get the OK for the new format
        // Zero indexes so templates work.
        $task->criterions = array_values($task->criterions);

         // Load task engagements.
        $task->engagements = task::get_engagement($task->id);
        foreach ($task->engagements as $i => $engagement) {
            if ($engagement->hidden) {
                unset($task->engagements[$i]);
                continue;
            }
            // Add selections only if task is released. And not hidden from student.
            if (isset($gradeinfo->engagements[$engagement->id]) && $task->released && !utils::is_hide_ps_grades()) {
                // There is a gradelevel chosen for this engagement.
                $engagement->{'level' . $gradeinfo->engagements[$engagement->id]->gradelevel . 'selected'} = true;
            }
        }
 
        // Zero indexes so templates work.
        $task->engagements = array_values($task->engagements);


        if ($task->released && !utils::is_hide_ps_grades()) {
            // Get selected MyConnect grade evidences.
            $task->myconnectevidences = array();
            $task->myconnectevidencejson = '';
            $myconnectids = array();
            if ($gradeinfo) {
                // Get selected ids
                $myconnectids = task::get_myconnect_grade_evidences($gradeinfo->id);
                if ($myconnectids) {
                    // Convert to json.
                    $task->myconnectevidencejson = json_encode($myconnectids);
                }
                // Get data for selected ids.
                $myconnectdata = utils::get_myconnect_data_for_attachments($student->username, $myconnectids);
                if (isset($myconnectdata->attachments)) {
                    $task->myconnectevidences = array_values($myconnectdata->attachments);
                }
            }

            // Add additional evidences for this user.
            $modulecontext = \context_module::instance($activity->cmid);
            $fs = get_file_storage();
            $uniqueid = sprintf( "%d%d", $task->id, $studentid ); // Join the taskid and userid to make a unique itemid.
            $files = $fs->get_area_files($modulecontext->id, 'mod_psgrading', 'evidences', $uniqueid, "filename", false);
            if ($files) {
                foreach ($files as $file) {
                    $filename = $file->get_filename();
                    $path = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$modulecontext->id.'/mod_psgrading/evidences/'.$uniqueid.'/'.$filename);
                    $task->evidences[] = array(
                        'icon' => $path . '?preview=thumb',
                        'url' => $path,
                        'name' => $filename,
                    );
                }
            }
        } else {
            // Unset some things if task has not been released yet.
            unset($gradeinfo->engagement);
            unset($gradeinfo->engagementlang);
            unset($gradeinfo->comment);
        }

        $stylesheet  = '';
        $stylesheet .= file_get_contents($CFG->wwwroot . '/report/psgrading_downloader/styles.css');
        $stylesheet .= file_get_contents($CFG->wwwroot . '/report/psgrading_downloader/bootstrap.min.css');

        error_log(print_r($stylesheet, true));
        return array(
            'task' => $task,
            'activityname' => $activity->activity_name,
            'student' =>$student,
            'gradeinfo' => $gradeinfo,
            'isstaff' => false,
            'stylesheet'=> $stylesheet
        );



    }
    /**
     * get all the tasks templates for a student and
     * Remove repeated student name and activity name.
     * Only leave the values for the first page.
     * 
     */
    public function sanitisetemplate(&$templates) {

        $headerPattern = '/<!-- SELECTED STUDENT HEADER START -->.*?<!-- SELECTED STUDENT HEADER END-->/s';
        
        for($i = 1; $i < count($templates); $i++) {
            $templates[$i] = preg_replace($headerPattern, '', $templates[$i]);
        }

    }


    
}