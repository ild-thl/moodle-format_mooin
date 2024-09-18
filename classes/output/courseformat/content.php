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
 * Contains the default content output class.
 *
 * @package   format_mooin4
 * @copyright 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mooin4\output\courseformat;

use core_courseformat\output\local\content as content_base;
use core_courseformat\base as course_format;
use format_mooin4\output\courseformat\content\coursefrontpage as coursefrontpage;
use renderer_base;

/**
 * Base class to render a course content.
 *
 * @package   format_mooin4
 * @copyright 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content extends content_base {

    /** @var coursefrontpage the course frontpage class */
    protected $coursefrontpage;

    public function __construct(course_format $format) {
        parent::__construct($format);
        $this->coursefrontpage = new coursefrontpage($format);
    }

    public function get_template_name(\renderer_base $renderer): string {
        return 'format_mooin4/local/content';
    }


    /**
     * Export this data so it can be used as the context for a mustache template (core/inplace_editable).
     *
     * @param renderer_base $output typically, the renderer that's calling this function
     * @return stdClass data context for a mustache template
     */
    public function export_for_template(\renderer_base $output) {
        global $PAGE;
        $format = $this->format;
        $coursefrontpage = $this->coursefrontpage;

        // Most formats uses section 0 as a separate section so we remove from the list.
        $sections = $this->export_sections($output);
        $initialsection = '';
        if (!empty($sections)) {
            $initialsection = array_shift($sections);
        }

        $data = (object)[
            'title' => $format->page_title(), // This method should be in the course_format class.
            'initialsection' => $initialsection,
            'sections' => $sections,
            'format' => $format->get_format(),
            'sectionreturn' => 0,
            
        ];

        // The single section format has extra navigation.
        $singlesection = $this->format->get_section_number();
        $data->editing = $format->show_editor();
        if ($singlesection) {
            
                $sectionnavigation = new $this->sectionnavigationclass($format, $singlesection);
                $data->sectionnavigation = $sectionnavigation->export_for_template($output);

                $sectionselector = new $this->sectionselectorclass($format, $sectionnavigation);
                $data->sectionselector = $sectionselector->export_for_template($output);
            
            $data->hasnavigation = true;
            $data->singlesection = array_shift($data->sections);
            $data->sectionreturn = $singlesection;
        }

        if (empty($singlesection)) {
            $data->frontpage = $coursefrontpage->export_for_template($output);
        }

        if ($this->hasaddsection) {
            $addsection = new $this->addsectionclass($format);
            $data->numsections = $addsection->export_for_template($output);
        }

        return $data;
    }

    /**
     * @var bool Topic format has add section after each topic.
     *
     * The responsible for the buttons is core_courseformat\output\local\content\section.
     */
    protected $hasaddsection = true;

}
