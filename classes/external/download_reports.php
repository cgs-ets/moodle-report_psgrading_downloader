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
 * 
 * @package   report_psgrading_downloader
 * @copyright 2024 Veronica Bermegui
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_psgrading_downloader\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;


require_once($CFG->libdir . '/externallib.php');

trait download_reports {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */

    public static  function download_reports_parameters() {
        return new external_function_parameters(
            array(
                'selectedusers' => new external_value(PARAM_RAW, 'array of usernames'),
                'selectedactivities' => new external_value(PARAM_RAW, 'array of selected activities with its associated tasks')
            )
        );
    }

    /**
     * Return context.
     */
    public static function download_reports($selectedusers, $selectedactivities) {
        global $USER, $PAGE;

        $context = \context_user::instance($USER->id);


        self::validate_context($context);
        //Parameters validation
        self::validate_parameters(self::download_reports_parameters(), 
                                  array('selectedusers' => $selectedusers,
                                        'selectedactivities' => $selectedactivities));

        $selectedactivities = json_decode($selectedactivities);
        $selectedusers = json_decode($selectedusers);

       
        $taskhtml = [];

                $output = $PAGE->get_renderer('core');
      

        $test = $output->render_from_template('report_psgrading_downloader/test', '');
        $html = json_encode($test);
        return array(
            'html' => $html,
        );
    }

    /**
     * Describes the structure of the function return value.
     * @return external_single_structures
     */
    public static function download_reports_returns() {
        return new external_single_structure(array(
            'html' =>  new external_value(PARAM_RAW, 'HTML with the student(s) report'),
        ));
    }
}