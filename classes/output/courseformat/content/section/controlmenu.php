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
 * Contains the default section controls output class.
 *
 * @package   format_moointopics
 * @copyright 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_moointopics\output\courseformat\content\section;

use context_course;
use core_courseformat\output\local\content\section\controlmenu as controlmenu_base;
use moodle_url;

/**
 * Base class to render a course section menu.
 *
 * @package   format_moointopics
 * @copyright 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class controlmenu extends controlmenu_base {

    /** @var course_format the course format class */
    protected $format;

    /** @var section_info the course section class */
    protected $section;

    /**
     * Generate the edit control items of a section.
     *
     * This method must remain public until the final deprecation of section_edit_control_items.
     *
     * @return array of edit control items
     */
    public function section_control_items() {
        global $DB;

        $format = $this->format;
        $section = $this->section;
        $course = $format->get_course();
        $sectionreturn = $format->get_section_number();
        $usecomponents = $format->supports_components();

        $coursecontext = context_course::instance($course->id);

        if ($sectionreturn) {
            $url = course_get_url($course, $section->section);
        } else {
            $url = course_get_url($course);
        }
        $url->param('sesskey', sesskey());
        

        $controls = [];


        $parentcontrols = parent::section_control_items();

        unset($parentcontrols['movesection']);
        unset($parentcontrols['delete']);

        if ($section->section && $section->section > 1 && has_capability('moodle/course:setcurrentsection', $coursecontext)) {
            //Add the chapter set/unset controlls
            if ($chapter = $DB->get_record('format_moointopics_chapter', array('sectionid' => $section->id))) {
                //$url = new moodle_url('/course/view.php');
                $url->param('unsetchapter', $section->section);
                $controls['chapter'] = array(
                    'url' => $url,
                    'icon' => 'i/settings',
                    'name' => get_string('unsetchapter', 'format_moointopics'),
                    'pixattr' => array('class' => ''),
                    'attr' => [
                        'class' => 'icon editing_showhide',
                        'data-sectionreturn' => $sectionreturn,
                        'data-action' => ($usecomponents) ? 'sectionUnsetChapter' : 'unsetChapter',
                        'data-id' => $section->id,
                        'data-swapname' => get_string('setchapter', 'format_moointopics'),
                        'data-swapicon' => 'i/settings',
                    ],
                );
            } else {
                //$url = new moodle_url('/course/view.php');
                $url->param('setchapter', $section->section);
                $controls['chapter'] = array(
                    'url' => $url,
                    'icon' => 'i/settings',
                    'name' => get_string('setchapter', 'format_moointopics'),
                    'pixattr' => array('class' => ''),
                    'attr' => [
                        'class' => 'icon editing_showhide',
                        'data-sectionreturn' => $sectionreturn,
                        'data-action' => ($usecomponents) ? 'sectionSetChapter' : 'setChapter',
                        'data-id' => $section->id,
                        'data-swapname' => get_string('unsetchapter', 'format_moointopics'),
                        'data-swapicon' => 'i/settings',
                    ],
                );
            }
        }
        if ($section->section) {
            $chapter = $DB->get_record('format_moointopics_chapter', array('sectionid' => $section->id));
            if (course_can_delete_section($course, $section) && !$chapter) {
                if (get_string_manager()->string_exists('deletesection', 'format_' . $course->format)) {
                    $strdelete = get_string('deletesection', 'format_' . $course->format);
                } else {
                    $strdelete = get_string('deletesection');
                }
                $url = new moodle_url('/course/editsection.php', array(
                    'id' => $section->id,
                    'sr' => $sectionreturn,
                    'delete' => 1,
                    'sesskey' => sesskey()
                ));
                $controls['delete'] = array(
                    'url' => $url,
                    'icon' => 'i/delete',
                    'name' => $strdelete,
                    'pixattr' => array('class' => ''),
                    'attr' => array('class' => 'icon editing_delete')
                );
            }
        }

        // If the edit key exists, we are going to insert our controls after it.
        if (array_key_exists("edit", $parentcontrols)) {
            $merged = [];
            // We can't use splice because we are using associative arrays.
            // Step through the array and merge the arrays.
            foreach ($parentcontrols as $key => $action) {
                $merged[$key] = $action;
                if ($key == "edit") {
                    // If we have come to the edit key, merge these controls here.
                    $merged = array_merge($merged, $controls);
                }
            }

            return $merged;
        } else {
            return array_merge($controls, $parentcontrols);
        }
    }
}
