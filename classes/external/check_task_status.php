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
 *  Gets the onlinetext submissions for the assessments that
 *  were created to either collect reflections (EE form) or forms (ToK)
 *
 * @package    report_psgrading_downloader
 * @copyright  2022 Veronica Bermegui
 */

namespace report_psgrading_downloader\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;

require_once($CFG->libdir . '/externallib.php');

/**
 * Undocumented trait
 */
trait check_task_status {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     *
     */
    public static function check_task_status_parameters() {
        return new external_function_parameters(
            ['taskid' => new external_value(PARAM_RAW, 'record id from mdl_report_reflectionexporter')]
        );
    }
    /**
     * Returns the status of the adhock task
     *
     * @param mixed $taskid
     * @return array
     */
    public static function check_task_status($taskid) {
        global $COURSE;

        $context = \context_user::instance($COURSE->id);

        self::validate_context($context);

        // Parameters validation.
        self::validate_parameters( self::check_task_status_parameters(), ['taskid' => $taskid]);

        $manager = new \report_psgrading_downloader\reportmanager();
        $status = $manager->get_adhoc_task_status($taskid);

        return ['status' => $status];
    }

    /**
     * Describes the structure of the function return value.
     * Returns the status of the adhock task
     * @return external_single_structure
     *
     */
    public static function check_task_status_returns() {
        return new external_single_structure(
            [ 'status' => new external_value(PARAM_TEXT, 'adhoc task status')]);
    }
}
