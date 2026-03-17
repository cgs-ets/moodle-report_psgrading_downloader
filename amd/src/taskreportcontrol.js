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
 * Manage Importing task from one psgrading to another.
 *
 * @package   mod_psgrading
 * @copyright 2026, Veronica Bermegui
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/log', 'core/templates'], function (Ajax, Log, Templates) {

    function init() {
        Log.debug('report_psgrading_downloader/taskreportcontrol: initialising');
        var taskreportcontrol = new TaskReportControl();
        taskreportcontrol.main();
    }

    /**
     * Constructor.
     */
    function TaskReportControl() {
        var self = this;
        self.activitySelection = document.getElementById('id_psgradinactivities');
        self.activitySelectionJSON = document.getElementById('id_selectedactivitiesJSON').value; // this has the cmids.
        self.taskSelectedJSON = document.getElementById('id_selectedtasksJSON').value;
    }

    TaskReportControl.prototype.main = function () {
        var self = this;

        self.initEventListeners();

        // Ensure selectedtasksJSON is synced before form submit.
        var form = self.activitySelection.closest('form');
        if (form) {
            form.addEventListener('submit', function () {
                document.getElementById('id_selectedtasksJSON').value = self.taskSelectedJSON;
                Log.debug('taskreportcontrol: submitting with taskSelectedJSON = ' + self.taskSelectedJSON);
            });
        }

        // Restore the task select if activities were previously selected (after page reload on filter).
        self.restoreTaskSelect();

        // Auto-load tasks if there's only one activity or one is already selected.
        if (self.activitySelection.options.length === 1 ||
            (self.activitySelection.selectedIndex >= 0 && self.taskSelectedJSON === '[]')) {
            self.findTasks();
        }
    };

    TaskReportControl.prototype.restoreTaskSelect = function () {
        var self = this;
        var savedActivities = [];

        try {
            savedActivities = JSON.parse(this.activitySelectionJSON);
        } catch (e) {
            savedActivities = [];
        }

        if (savedActivities.length > 0) {
            // Re-select the activities in the select element.
            for (var i = 0; i < this.activitySelection.options.length; i++) {
                if (savedActivities.indexOf(this.activitySelection.options[i].value) !== -1) {
                    this.activitySelection.options[i].selected = true;
                }
            }

            // Reload the tasks select via AJAX.
            this.getActivitiesTasks(savedActivities, true);
        }
    };

    TaskReportControl.prototype.initEventListeners = function () {
        var self = this;
        self.activitySelection.addEventListener('input', function () {
            self.findTasks();
        });

    }

    TaskReportControl.prototype.findTasks = function (e) {
        var selectedCMIDS = [];

        for (var i = 0; i < this.activitySelection.options.length; i++) {
            if (this.activitySelection.options[i].selected) {
                selectedCMIDS.push(this.activitySelection.options[i].value);
            }
        }

        // Update the activitySelectionJSON property
        this.activitySelectionJSON = JSON.stringify(selectedCMIDS);
        document.getElementById('id_selectedactivitiesJSON').value = this.activitySelectionJSON;

        this.getActivitiesTasks(selectedCMIDS);

    }

    TaskReportControl.prototype.getActivitiesTasks = function (selectedCMIDS, isRestore) {
        var self = this;

          Ajax.call([{
            methodname: 'mod_psgrading_get_tasks_in_activity',
            args: {
                data: JSON.stringify(selectedCMIDS)
            },
            done: function (response) {
                var templatecontext = JSON.parse(response.templatecontext);
                var context = {
                    id: templatecontext.id,
                    tasks: templatecontext.tasks,
                    size: templatecontext.size,
                }

                Templates.render('report_psgrading_downloader/import_task_tasks_in_activity', context)
                    .then(function (html, js) {
                        Templates.replaceNodeContents('.report-psgrading-tasks-in-selected-activity', html, js);
                        self.setListenerForTasks(isRestore);
                    })
                    .catch(function (error) {
                        console.error('Error rendering template:', error);
                    });
            },
            fail: function (reason) {
                console.log(reason);
            }

        }]);

    }

    TaskReportControl.prototype.setListenerForTasks = function (isRestore) {
        var self = this;
        var selector = document.getElementById('id_tasksinselectedactivity');

        if (selector) {
            selector.addEventListener('input', function () {
                self.taskselected();
            });
        }

        if (isRestore) {
            // Re-select previously selected tasks.
            var savedTasks = [];
            try {
                savedTasks = JSON.parse(this.taskSelectedJSON);
            } catch (e) {
                savedTasks = [];
            }
            if (selector && savedTasks.length > 0) {
                for (var i = 0; i < selector.options.length; i++) {
                    if (savedTasks.indexOf(selector.options[i].value) !== -1) {
                        selector.options[i].selected = true;
                    }
                }
            }
        } else {
            // Reset task selection when activity changes.
            this.taskSelectedJSON = '[]';
            document.getElementById('id_selectedtasksJSON').value = '[]';

            // Auto-select if there's only one task.
            if (selector && selector.options.length === 1) {
                selector.options[0].selected = true;
                self.taskselected();
            }
        }
    }

    TaskReportControl.prototype.taskselected = function () {
        var selectedtaskid = [];
        var activitiesSelectedEl = document.getElementById('id_tasksinselectedactivity');

        for (var i = 0; i < activitiesSelectedEl.options.length; i++) {
            if (activitiesSelectedEl.options[i].selected) {
                selectedtaskid.push(activitiesSelectedEl.options[i].value);
            }
        }

        this.taskSelectedJSON = JSON.stringify(selectedtaskid);
        document.getElementById('id_selectedtasksJSON').value = this.taskSelectedJSON;
    }




    return {
        init: init
    };
});
