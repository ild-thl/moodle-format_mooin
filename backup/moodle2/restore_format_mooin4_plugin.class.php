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


defined('MOODLE_INTERNAL') || die();

/**
 * Specialised restore for mooin4 course format.
 *
 * Processes 'numsections' from the old backup files and hides sections that used to be "orphaned".
 *
 * @package   format_mooin4
 * @category  backup
 * @copyright 2022 ISy TH Lübeck <dev.ild@th-luebeck.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_format_mooin4_plugin extends restore_format_plugin {

    /** @var int */
    protected $originalnumsections = 0;

    var $chapters = null;

    /**
     * Checks if backup file was made on Moodle before 3.3 and we should respect the 'numsections'
     * and potential "orphaned" sections in the end of the course.
     *
     * @return bool
     */
    protected function need_restore_numsections() {
        $backupinfo = $this->step->get_task()->get_info();
        $backuprelease = $backupinfo->backup_release; // The major version: 2.9, 3.0, 3.10...
        return version_compare($backuprelease, '3.3', '<');
    }

    /**
     * Creates a dummy path element in order to be able to execute code after restore.
     *
     * @return restore_path_element[]
     */
    public function define_course_plugin_structure() {
        $paths = array();

        $elepath = $this->get_pathfor('/format_mooin4_chapter');

        //Dummy path element is needed in order for after_restore_course() to be called.
        return [new restore_path_element('dummy_course', $this->get_pathfor('/dummycourse')), new restore_path_element('chapter', $elepath)];
    }

    function after_execute_course() {
        global $DB;

        $this->add_related_files('format_mooin4', 'headerimagedesktop', null);
        $this->add_related_files('format_mooin4', 'headerimagemobile', null);
    }

    public function process_chapter($data) {
        global $DB;
        $data = (object)$data;

        $data->courseid = $this->task->get_courseid();
        $this->chapters[] = $data;
        //$DB->insert_record('format_mooin4_chapter', $data);
    }

    public function restore_files($area) {
        $courseid = $this->task->get_courseid();
        $fs = get_file_storage();

        $files = $fs->get_area_files($this->task->get_contextid(), 'format_mooin4', $area);
        foreach ($files as $file) {
            $newfilerecord = new stdClass();
            $newfilerecord->itemid = $courseid;
            $fs->create_file_from_storedfile($newfilerecord, $file);
        }
    }

    /**
     * Dummy process method.
     *
     * @return void
     */
    public function process_dummy_course($data) {
    }

    /**
     * Executed after course restore is complete.
     *
     * This method is only executed if course configuration was overridden.
     *
     * @return void
     */
    public function after_restore_course() {
        global $DB;

        $backupinfo = $this->step->get_task()->get_info();
        $contextid = $this->task->get_contextid();

        $this->restore_files('headerimagedesktop');
        $this->restore_files('headerimagemobile');

        $DB->delete_records('files', array('contextid' => $contextid, 'itemid' => $backupinfo->original_course_id));


        foreach ($backupinfo->sections as $section) {
            $id = $this->get_mappingid('course_section', $section->sectionid);
            $DB->execute(
                "UPDATE {course_sections} SET name = ? WHERE course = ? AND id = ?",
                [$section->title, $this->step->get_task()->get_courseid(), $id]
            );
        }

        $DB->delete_records('format_mooin4_chapter', array('chapter' => 1, 'courseid' => $this->step->get_task()->get_courseid()));

        foreach ($this->chapters as $chapter) {
            $id = $this->get_mappingid('course_section', $chapter->sectionid);
            $chapter->sectionid = $id;
            $DB->insert_record('format_mooin4_chapter', $chapter);
        }






        if (!$this->need_restore_numsections()) {
            // Backup file was made in Moodle 3.3 or later, we don't need to process 'numsecitons'.
            return;
        }





        if ($backupinfo->original_course_format !== 'mooin4' || !isset($data['tags']['numsections'])) {
            // Backup from another course format or backup file does not even have 'numsections'.
            return;
        }

        $numsections = (int)$data['tags']['numsections'];
        foreach ($backupinfo->sections as $key => $section) {
            // For each section from the backup file check if it was restored and if was "orphaned" in the original
            // course and mark it as hidden. This will leave all activities in it visible and available just as it was
            // in the original course.
            // Exception is when we restore with merging and the course already had a section with this section number,
            // in this case we don't modify the visibility.
            if ($this->step->get_task()->get_setting_value($key . '_included')) {
                $sectionnum = (int)$section->title;
                if ($sectionnum > $numsections && $sectionnum > $this->originalnumsections) {
                    $DB->execute(
                        "UPDATE {course_sections} SET visible = 0 WHERE course = ? AND section = ?",
                        [$this->step->get_task()->get_courseid(), $sectionnum]
                    );
                }
            }
        }
    }
}
