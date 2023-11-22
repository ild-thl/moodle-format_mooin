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

class backup_format_mooin4_plugin extends backup_format_plugin {

    public function define_course_plugin_structure() {

        $plugin = $this->get_plugin_element(null, $this->get_format_condition(), 'mooin4');
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($pluginwrapper);
        $chapter = new backup_nested_element('format_mooin4_chapter', ['id'], ['courseid', 'title', 'sectionid', 'chapter']);
        $pluginwrapper->add_child($chapter);
        $chapter->set_source_table('format_mooin4_chapter', ['courseid' => backup::VAR_COURSEID]);
        $pluginwrapper->annotate_files('format_mooin4', 'headerimagedesktop', null);
        $pluginwrapper->annotate_files('format_mooin4', 'headerimagemobile', null);
        return $plugin;



        // //$chapters = new backup_nested_element('chapters');
        // $chapter = new backup_nested_element('format_mooin4_chapter', ['id'], ['courseid', 'title', 'sectionid', 'chapter']);
        // $chapter->set_source_table('format_mooin4_chapter', ['courseid' => backup::VAR_COURSEID]);
        // $plugin = $this->get_plugin_element(null, $this->get_format_condition(), 'mooin4');
        // //$chapters -> add_child($chapter);
        // return $plugin->add_child($chapter);
    }
}