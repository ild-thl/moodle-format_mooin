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
 * Contains the default activity list from a section.
 *
 * @package   core_courseformat
 * @copyright 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 namespace format_mooin4\output\courseformat\content;

 use core_courseformat\output\local\content\cm as cm_base;
use cm_info;
use core\activity_dates;
use core\output\named_templatable;
use core_availability\info_module;
use core_completion\cm_completion_details;
use core_course\output\activity_information;
use core_courseformat\base as course_format;
use core_courseformat\output\local\courseformat_named_templatable;
use renderable;
use renderer_base;
use section_info;
use stdClass;

/**
 * Base class to render a course module inside a course format.
 *
 * @package   core_courseformat
 * @copyright 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cm extends cm_base {
    
    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output typically, the renderer that's calling this function
     * @return stdClass data context for a mustache template
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $USER, $CFG, $DB;
        $data = parent::export_for_template($output);

        if ($this->mod->modname == 'hvp' && $USER->editing != 1) {
            $link = '<iframe src="' . $CFG->httpswwwroot . '/mod/hvp/embed.php?id=' . $this->mod->id . '" class="parent-iframe" frameborder="0" allowfullscreen="allowfullscreen"></iframe>';
            $link .= '<script src="' . $CFG->httpswwwroot . '/mod/hvp/library/js/h5p-resizer.js" charset="UTF-8"></script>';
            
            //$link = '<iframe src="' . $CFG->httpswwwroot . '/mod/hvp/embed.php?id=' . $this->mod->id . '"class="parent-iframe"" frameborder="0" allowfullscreen="allowfullscreen"></iframe><script src="' . $CFG->httpswwwroot . '/mod/hvp/library/js/h5p-resizer.js" charset="UTF-8"></script>';
            //$link = '<iframe src="' . $CFG->httpswwwroot . '/mod/hvp/embed.php?id=' . $this->mod->id . '" class="parent-iframe" style="height: 400px;" frameborder="0" allowfullscreen="allowfullscreen"></iframe><script src="' . $CFG->httpswwwroot . '/mod/hvp/library/js/h5p-resizer.js" charset="UTF-8"></script>';
            $this->mod->set_content($link);
            $data->hvpcontent = $this->mod->content;
        }

        if ($this->mod->modname === 'h5pactivity' && $USER->editing != 1) {
            $context = $this->mod->context ?? \context_module::instance($this->mod->id);
            $fs = get_file_storage();
            $files = $fs->get_area_files($context->id, 'mod_h5pactivity', 'package', 0, 'id', false);
            $file = reset($files);

            if ($file) {
                $displayoptions = 0;
                if ($activity = $DB->get_record('h5pactivity', ['id' => $this->mod->instance], 'displayoptions')) {
                    $displayoptions = (int) ($activity->displayoptions ?? 0);
                }

                $fileurl = \moodle_url::make_pluginfile_url(
                    $file->get_contextid(),
                    $file->get_component(),
                    $file->get_filearea(),
                    $file->get_itemid(),
                    $file->get_filepath(),
                    $file->get_filename(),
                    false
                );

                $factory = new \core_h5p\factory();
                $core = $factory->get_core();
                $config = \core_h5p\helper::decode_display_options($core, $displayoptions);

                $playerhtml = \core_h5p\player::display($fileurl, $config, true, 'mod_h5pactivity', false, []);

                $this->mod->set_content($playerhtml);
                $data->hvpcontent = $this->mod->content;
            }
        }
        

        return $data;
    }

}
