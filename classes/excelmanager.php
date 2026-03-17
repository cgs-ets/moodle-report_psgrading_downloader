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
 * Excel writer abstraction layer extension.
 * Allow to save excel files in a zip file.
 * 
 * @copyright  2026 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package    report_assignfeedback_download
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/excellib.class.php');

use PhpOffice\PhpSpreadsheet\IOFactory;

class PSgGradingDownloaderExcelWorkbook extends MoodleExcelWorkbook {

    /**
     *  Save the excel file to a temp dir to be able to download more than one excel
     *  per request. savetotempdir
     */
    /**
     * Save the excel file to a temp dir.
     * This method works in both HTTP and CLI (adhoc task) contexts.
     *
     * @param string $tempdir The directory to save the file to.
     * @return string The full path to the saved file.
     */
    public function savetotempdir($tempdir) {

        foreach ($this->objspreadsheet->getAllSheets() as $sheet) {
            $sheet->setSelectedCells('A1');
        }
        $this->objspreadsheet->setActiveSheetIndex(0);

        $filename = preg_replace('/\.xlsx?$/i', '', $this->filename);
        $filename = $filename . '.xlsx';

        $filepath = "$tempdir/$filename";
        $objwriter = IOFactory::createWriter($this->objspreadsheet, $this->type);
        $objwriter->save($filepath);

        return $filepath;
    }
}
