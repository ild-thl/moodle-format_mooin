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
 * Renderer for outputting the mooin course format.
 *
 * @package format_mooin
 * @copyright 2022 ISy TH Lübeck <dev.ild@th-luebeck.de>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/course/format/renderer.php');
require_once('locallib.php');

/**
 * Basic renderer for mooin format.
 *
 * @copyright 2022 ISy TH Lübeck <dev.ild@th-luebeck.de>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_mooin_renderer extends format_section_renderer_base {

    /**
     * Constructor method, calls the parent constructor.
     *
     * @param moodle_page $page
     * @param string $target one of rendering target constants
     */
    public function __construct(moodle_page $page, $target) {
        parent::__construct($page, $target);

        // Since format_mooin_renderer::section_edit_control_items() only displays the 'Highlight' control
        // when editing mode is on we need to be sure that the link 'Turn editing mode on' is available for a user
        // who does not have any other managing capability.
        $page->set_other_editing_capability('moodle/course:setcurrentsection');
    }

    /**
     * Generate the starting container html for a list of sections.
     *
     * @return string HTML to output.
     */
    protected function start_section_list() {
        return html_writer::start_tag('ul', ['class' => 'topics']);
    }

    /**
     * Generate the closing container html for a list of sections.
     *
     * @return string HTML to output.
     */
    protected function end_section_list() {
        return html_writer::end_tag('ul');
    }

    /**
     * Generate the title for this section page.
     *
     * @return string the page title
     */
    protected function page_title() {
        return get_string('topicoutline');
    }

    /**
     * Generate the section title, wraps it in a link to the section page if page is to be displayed on a separate page.
     *
     * @param section_info|stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section));
    }

    /**
     * Generate the section title to be displayed on the section page, without a link.
     *
     * @param section_info|stdClass $section The course_section entry from DB
     * @param int|stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title_without_link($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section, false));
    }

    /**
     * Generate the edit control items of a section.
     *
     * @param int|stdClass $course The course entry from DB
     * @param section_info|stdClass $section The course_section entry from DB
     * @param bool $onsectionpage true if being printed on a section page
     * @return array of edit control items
     */
    protected function section_edit_control_items($course, $section, $onsectionpage = false) {
        if (!$this->page->user_is_editing()) {
            return [];
        }

        $coursecontext = context_course::instance($course->id);

        if ($onsectionpage) {
            $url = course_get_url($course, $section->section);
        } else {
            $url = course_get_url($course);
        }
        $url->param('sesskey', sesskey());

        $controls = [];
        if ($section->section && has_capability('moodle/course:setcurrentsection', $coursecontext)) {
            if ($course->marker == $section->section) {  // Show the "light globe" on/off.
                $url->param('marker', 0);
                $highlightoff = get_string('highlightoff');
                $controls['highlight'] = [
                    'url' => $url,
                    'icon' => 'i/marked',
                    'name' => $highlightoff,
                    'pixattr' => ['class' => ''],
                    'attr' => [
                        'class' => 'editing_highlight',
                        'data-action' => 'removemarker'
                    ],
                ];
            } else {
                $url->param('marker', $section->section);
                $highlight = get_string('highlight');
                $controls['highlight'] = [
                    'url' => $url,
                    'icon' => 'i/marker',
                    'name' => $highlight,
                    'pixattr' => ['class' => ''],
                    'attr' => [
                        'class' => 'editing_highlight',
                        'data-action' => 'setmarker'
                    ],
                ];
            }
        }

        $parentcontrols = parent::section_edit_control_items($course, $section, $onsectionpage);

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

     /**
     * Return the navbar content in specific section so that it can be echoed out by the layout
     *
     * @return string XHTML navbar
     */
    public function navbar($displaysection = 0) {
        global $COURSE;
        $items = $this->page->navbar->get_items();
        $itemcount = count($items);
        if ($itemcount === 0) {
            return '';
        }

        $htmlblocks = array();
        // Iterate the navarray and display each node
        $separator = get_separator();
        for ($i=0;$i < $itemcount;$i++) {
            if( $displaysection == 0) {
                $val = $COURSE->shortname;
                $item = $items[$i];
                $item->hideicon = true;
                if ($i===0) {
                    $content = html_writer::tag('li', $this->render($item));
                } else
                if($i === $itemcount - 2) {
                    $content = html_writer::tag('li', '  ');
                }else
                if ($i === $itemcount - 1) {
                    $content = html_writer::tag('li', '  '. ' / '.$val); // $separator.$this->render($item)
                } else {
                    $content = '';
                }
            } else {
                
                $val  = ' Kap. '. ' '.$displaysection .' / Lek.  '. ' ' .$displaysection .'.'. $displaysection . ':';
                $item = $items[$i];
                $item->hideicon = true;
                if ($i===0) {
                    $content = html_writer::tag('li', '  '); // $this->render($item)
                } else
                if($i === $itemcount - 2) {
                    $content = html_writer::tag('li', '  '. $this->render($item));
                }else
                if ($i === $itemcount - 1) {
                    $content = html_writer::tag('li', '  '. ' / '.$val); // $separator.$this->render($item)
                } else {
                    $content = '';
                }
            }
            /*  {
                $content = html_writer::tag('li', $separator.$this->render($item));
            } */
            $htmlblocks[] = $content;
        }

        //accessibility: heading for navbar list  (MDL-20446)
        $navbarcontent = html_writer::tag('span', get_string('pagepath'),
                array('class' => 'accesshide', 'id' => 'navbar-label'));
        // $navbarcontent .= html_writer::start_tag('nav', array('aria-labelledby' => 'navbar-label'));
        
        $navbarcontent .= html_writer::tag('nav',
                html_writer::tag('ul', join('', $htmlblocks),array('class' => "navmenu", 'id'=> 'menu'),array('aria-labelledby' => 'navbar-label')),
                );
        // $navbarcontent .= html_writer::start_tag('ul', array('id' => "menu"));
        // XHTML
        return $navbarcontent;
    }
    /**
     * Output the html for a multiple section page
     *
     * @param stdClass $course The course entry from DB
     * @param array $sections (argument not used)
     * @param array $mods (argument not used)
     * @param array $modnames (argument not used)
     * @param array $modnamesused (argument not used)
     */
    public function print_multiple_section_page($course, $sections, $mods, $modnames, $modnamesused) {
        global $DB;
        $modinfo = get_fast_modinfo($course);
        $course = course_get_format($course)->get_course();

        $context = context_course::instance($course->id);
        echo $this->output->heading($this->page_title(), 2, 'accesshide');

        // echo $this->navbar();
        // Copy activity clipboard..
        echo $this->course_activity_clipboard($course, 0);

        // Now the list of sections..
        echo $this->start_section_list();
        $numsections = course_get_format($course)->get_last_section_number();
        //print_object($modinfo->get_section_info_all());
        foreach ($modinfo->get_section_info_all() as $section => $thissection) {
            // mooin: do we need to print a chapter before?
            // first check if there is a previous section and a chapter with that section as sectionid
            if ($section > 0) {
                // get id of previous section
                $previoussectionid = $modinfo->get_section_info($section - 1)->id;
                if ($chapter = $DB->get_record('format_mooin_chapter', array('sectionid' => $previoussectionid))) {
                    if (!$this->page->user_is_editing() && $course->coursedisplay == COURSE_DISPLAY_MULTIPAGE) {
                        // Display Chapter title
                        echo $this->chapter_title($thissection, $course, $chapter->title);
                    //*
                    }
                    else {
                        echo $this->chapter_header($thissection, $course, false, 0, $chapter->title);
                        //echo $this->chapter_title($thissection, $course, $chapter->title);
                        echo $this->section_footer();
                    }
                    //*/
                }
            }
            /*
            if ($section == 0) {
                // 0-section is displayed a little different then the others
                if ($thissection->summary or !empty($modinfo->sections[0]) or $this->page->user_is_editing()) {
                    echo $this->section_header($thissection, $course, false, 0);
                    echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                    echo $this->courserenderer->course_section_add_cm_control($course, 0, 0);
                    echo $this->section_footer();
                }
                continue;
            }
            //*/
            /*
            if ($section == 0 && $this->page->user_is_editing()) {
                $section0url = new moodle_url('/course/view.php', array('id' => $course->id, 'section' => 0));
                $section0_link = html_writer::link($section0url, get_string('section0', 'format_mooin'), array('title' => get_string('section0', 'format_mooin')));
                echo $section0_link;
                continue;
            }
            //*/
            if ($section == 0) {
                continue;
            }
            if ($section > $numsections) {
                // activities inside this section are 'orphaned', this section will be printed as 'stealth' below
                continue;
            }
            // Show the section if the user is permitted to access it, OR if it's not available
            // but there is some available info text which explains the reason & should display,
            // OR it is hidden but the course has a setting to display hidden sections as unavilable.
            $showsection = $thissection->uservisible ||
                    ($thissection->visible && !$thissection->available && !empty($thissection->availableinfo)) ||
                    (!$thissection->visible && !$course->hiddensections);
            if (!$showsection) {
                continue;
            }

            if (!$this->page->user_is_editing() && $course->coursedisplay == COURSE_DISPLAY_MULTIPAGE) {
                // Display section summary only.
                // mooin: Section title and Summary (Table of contents)
                echo $this->section_summary($thissection, $course, null);
            } else {
                echo $this->section_header($thissection, $course, false, 0);
                /* mooin: do not show cm's here
                if ($thissection->uservisible) {
                    echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                    echo $this->courserenderer->course_section_add_cm_control($course, $section, 0);
                }
                */
                echo $this->section_footer();
            }
        }

        if ($this->page->user_is_editing() and has_capability('moodle/course:update', $context)) {
            // Print stealth sections if present.
            foreach ($modinfo->get_section_info_all() as $section => $thissection) {
                if ($section <= $numsections or empty($modinfo->sections[$section])) {
                    // this is not stealth section or it is empty
                    continue;
                }
                echo $this->stealth_section_header($section);
                echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                echo $this->stealth_section_footer();
            }

           echo $this->end_section_list();

            echo $this->change_number_sections($course, 0);
        } else {
            echo $this->end_section_list();
        }

    }

    /**
     * Output the html for a single section page .
     *
     * @param stdClass $course The course entry from DB
     * @param array $sections (argument not used)
     * @param array $mods (argument not used)
     * @param array $modnames (argument not used)
     * @param array $modnamesused (argument not used)
     * @param int $displaysection The section number in the course which is being displayed
     */
    public function print_single_section_page($course, $sections, $mods, $modnames, $modnamesused, $displaysection) {
        global $PAGE, $DB, $USER;

        $modinfo = get_fast_modinfo($course);
        $course = course_get_format($course)->get_course();
        $modules_course = $DB->get_records('course_modules', array('course' => $course->id));
        $section_course = $DB->get_records('course_sections', array('course' =>$course->id));
        $sections = ($DB->count_records('course_sections', ['course' =>$course->id])) - 1;

        echo $this->navbar($displaysection);
        
        $modinfo = get_fast_modinfo($course);
        $course = course_get_format($course)->get_course();

        // Can we view the section in question?
        if (!($sectioninfo = $modinfo->get_section_info($displaysection)) || !$sectioninfo->uservisible) {
            // This section doesn't exist or is not available for the user.
            // We actually already check this in course/view.php but just in case exit from this function as well.
            print_error('unknowncoursesection', 'error', course_get_url($course),
                format_string($course->fullname));
        }
        $PAGE->navbar->ignore_active();
        $PAGE->navbar->add('/ Kap.'.$displaysection);
        // Copy activity clipboard..

        
        $thissection = $modinfo->get_section_info(0);
        if ($this->page->user_is_editing()) {
            echo $this->start_section_list();
            echo $this->section_header($thissection, $course, true, $displaysection);
            echo $this->courserenderer->course_section_cm_list($course, $thissection, $displaysection);
            echo $this->courserenderer->course_section_add_cm_control($course, 0, $displaysection);
            echo $this->section_footer();
            echo $this->end_section_list();
        }
        
        
        
        // Start single-section div
        echo html_writer::start_tag('div', array('class' => 'single-section'));

        // The requested section page.
        $thissection = $modinfo->get_section_info($displaysection);
        //$PAGE->navbar->add('Perial'.$displaysection);
        // nav_bar_in_single_section($course, $displaysection);
       // var_dump($PAGE->context );
        
       for ($i=1; $i <= $sections; $i++) { 
            if (array_key_exists('btnComplete-'.$i, $_POST) && $i == $displaysection) {
                complete_section($USER->id, $course->id, $i);
            }
       }
        // Title with section navigation links.
        $sectionnavlinks = $this->get_nav_links($course, $modinfo->get_section_info_all(), $displaysection);
        $sectiontitle = '';
        $sectiontitle .= html_writer::start_tag('div', array('class' => 'section-navigation navigationtitle'));
        $sectiontitle .= html_writer::tag('span', $sectionnavlinks['previous'], array('class' => 'mdl-left'));
        $sectiontitle .= html_writer::tag('span', $sectionnavlinks['next'], array('class' => 'mdl-right'));
        // Title attributes
        $classes = 'sectionname';
        if (!$thissection->visible) {
            $classes .= ' dimmed_text';
            
        }
        $sectionname = html_writer::tag('span', $this->section_title_without_link($thissection, $course));
        $sectiontitle .= $this->output->heading($sectionname, 3, $classes);

        $sectiontitle .= html_writer::end_tag('div');
        
        
        // Progress bar anzeige
        $check_sequence = $DB->get_records('course_sections', ['course' => $course->id, 'section' => $displaysection], '', 'sequence');
        $val = array_values($check_sequence);
        if (!$this->page->user_is_editing() &&  !empty($val[0]->sequence) ) {
            $v = get_section_grades($displaysection);
            $ocp = round($v);
            if ($ocp != -1) {
                $sectiontitle .= '<br />' . get_progress_bar($ocp, 100, $displaysection);
            } else {
                $completionthistile = section_progress($modinfo->sections[$displaysection], $modinfo->cms);
                
                // use the completion_indicator to show the right percentage in secton
                $section_percent = completion_indicator($completionthistile['completed'], $completionthistile['outof'], true, false);
                    
                $sectiontitle .= '<br />' . get_progress_bar($section_percent['percent'], 100, $displaysection);
            }
        }
        

        echo $sectiontitle;
        // Now the list of sections..
        
        echo $this->start_section_list();

        echo $this->section_header($thissection, $course, true, $displaysection);

        echo $this->courserenderer->course_section_cm_list($course, $thissection, $displaysection);
        echo $this->courserenderer->course_section_add_cm_control($course, $displaysection, $displaysection);
        
        if($this->courserenderer->course_section_cm_list($course, $thissection, $displaysection)) {
           
        }
        foreach ($section_course as $k => $v) {
            foreach ($modules_course as $key => $value) {
               
               if ($value->section == $v->id && $v->section == $displaysection) {
                       //  var_dump($v ) ;
                        //echo 'JiJo'.$displaysection;
                        //echo $value->module . '\n';
                        $del = strpos($v->sequence, $value->id);
                        if ($del !== false) { // str_contains($v->sequence, $value->id)
                            if ($value->module === '24') {
                                continue;
                            } else if ($value->module === '13'){
                               //  Button bar
                                $bar = '';
                                $q = $USER->id .' ' . $course->id . ' ' . $displaysection;
                                $check_in_up = $DB->get_record('user_preferences', ['value' => $q]);
                                if (!$check_in_up) {
                                    if (!$this->page->user_is_editing()) {
                                        $bar .= html_writer::start_tag('form', array('method' => 'post', 'style' => 'margin-top: 40px;'));
                                        $bar .= html_writer::start_tag('input', array('class'=>'bottom_complete btn btn-outline-secondary', 'id' => 'id_bottom_complete-' .$displaysection, 'type' => 'submit', 'name'=> 'btnComplete-'.$displaysection,'value' => 'Seite als bearbeitet markieren', 'onclick' => complete_section($USER->id, $course->id, $displaysection) )); // 'onclick' => $this->the_click( $section, $course->id, $USER->id)
                                                
                                        // $bar .= html_writer::start_span('bottom_button') . 'Seite als bearbeitet markieren' . html_writer::end_span();
                                        $bar .= html_writer::end_tag('input');
                                        $bar .= html_writer::end_tag('form');
                                    }
                                } else {
                                    if (!$this->page->user_is_editing()) {
                                        $bar .= html_writer::start_tag('div', array('class'=>'complete_section btn btn-outline-secondary', 'id' => 'id_bottom_complete-' .$displaysection, 'style' => 'margin-top: 40px;'));
                
                                        $bar .= html_writer::start_span('bottom_button') . 'Seite als bearbeitet markieren' . html_writer::end_span();
                                        $bar .= html_writer::end_tag('div');
                                    }
                                }
                                

                                echo $bar;
                            }
                        }
                        /* if (strpos($value->module, '11') == true) {
                           
                        } else if ($value->module == '13' ) {
                           
                        }  */
               }
            }
                
        }
        
        echo $this->section_footer();
        echo $this->end_section_list();

        // Display section bottom navigation.
        $sectionbottomnav = '';
        $sectionbottomnav .= html_writer::start_tag('div', array('class' => 'section-navigation mdl-bottom'));
        $sectionbottomnav .= html_writer::tag('span', $sectionnavlinks['previous'], array('class' => 'mdl-left'));
        $sectionbottomnav .= html_writer::tag('span', $sectionnavlinks['next'], array('class' => 'mdl-right'));
        /* $sectionbottomnav .= html_writer::tag('div', $this->section_nav_selection($course, $sections, $displaysection),
            array('class' => 'mdl-align')); */
       /*  $sectionbottomnav .= html_writer::tag('div', $this->section_nav_selection($course, $sections, $displaysection),
            array('class' => 'mdl-align')); */
        $sectionbottomnav .= html_writer::end_tag('div');
        echo $sectionbottomnav;

        // Close single-section div.
        echo html_writer::end_tag('div');
    }

    /**
     * Generate a summary of a section for display on the 'course index page'
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @param array    $mods (argument not used)
     * @return string HTML to output.
     */
    protected function section_summary($section, $course, $mods) {
        $classattr = 'section main section-summary clearfix';
        $linkclasses = '';

        // If section is hidden then display grey section link
        if (!$section->visible) {
            $classattr .= ' hidden';
            $linkclasses .= ' dimmed_text';
        } else if (course_get_format($course)->is_section_current($section)) {
            $classattr .= ' current';
        }

        $title = get_section_name($course, $section);
        $o = '';
        $o .= html_writer::start_tag('li', [
            'id' => 'section-'.$section->section,
            'class' => $classattr,
            'role' => 'region',
            'aria-label' => $title,
            'data-sectionid' => $section->section
        ]);

        $o .= html_writer::tag('div', '', array('class' => 'left side'));
        $o .= html_writer::tag('div', '', array('class' => 'right side'));
        $o .= html_writer::start_tag('div', array('class' => 'content'));

        if ($section->uservisible) {
            $title = html_writer::tag('a', $title,
                    array('href' => course_get_url($course, $section->section), 'class' => $linkclasses));
        }
        $o .= $this->output->heading($title, 3, 'section-title');

        $o .= $this->section_availability($section);
        $o.= html_writer::start_tag('div', array('class' => 'summarytext'));

        if ($section->uservisible || $section->visible) {
            // Show summary if section is available or has availability restriction information.
            // Do not show summary if section is hidden but we still display it because of course setting
            // "Hidden sections are shown in collapsed form".
            $o .= $this->format_summary_text($section);
        }
        $o.= html_writer::end_tag('div');
        $o.= $this->section_activity_summary($section, $course, null);

        $o .= html_writer::end_tag('div');
        $o .= html_writer::end_tag('li');

        return $o;
    }

    /**
     * Generate the title of a chapter for display on the 'course index page'
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @param array    $mods (argument not used)
     * @return string HTML to output.
     */
    protected function chapter_title($section, $course, $title) {
        $classattr = 'section main section-summary clearfix';
        $linkclasses = '';

        // If section is hidden then display grey section link
        if (!$section->visible) {
            $classattr .= ' hidden';
            $linkclasses .= ' dimmed_text';
        } else if (course_get_format($course)->is_section_current($section)) {
            $classattr .= ' current';
        }

        $o = '';
        $o .= html_writer::start_tag('li', [
            'id' => 'section-'.$section->section,
            'class' => $classattr,
            'role' => 'region',
            'aria-label' => $title,
            'data-sectionid' => $section->section
        ]);

        $o .= html_writer::tag('div', '', array('class' => 'left side'));
        $o .= html_writer::tag('div', '', array('class' => 'right side'));
        $o .= html_writer::start_tag('div', array('class' => 'content'));
/*
        if ($section->uservisible) {
            $title = html_writer::tag('a', $title,
                    array('href' => course_get_url($course, $section->section), 'class' => $linkclasses));
        }
        */
        $o .= $this->output->heading($title, 3, 'section-title');

        //$o .= $this->section_availability($section);
        $o.= html_writer::start_tag('div', array('class' => 'summarytext'));
/*
        if ($section->uservisible || $section->visible) {
            // Show summary if section is available or has availability restriction information.
            // Do not show summary if section is hidden but we still display it because of course setting
            // "Hidden sections are shown in collapsed form".
            $o .= $this->format_summary_text($section);
        }
        */
        $o.= html_writer::end_tag('div');
        //$o.= $this->section_activity_summary($section, $course, null);

        $o .= html_writer::end_tag('div');
        $o .= html_writer::end_tag('li');

        return $o;
    }

    /**
     * Generate the display of the header part of a chapter 
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @param bool $onsectionpage true if being printed on a single-section page
     * @param int $sectionreturn The section to return to after an action
     * @return string HTML to output.
     */
    protected function chapter_header($section, $course, $onsectionpage, $sectionreturn=null, $title) {
        $o = '';
        $currenttext = '';
        $sectionstyle = '';

        if ($section->section != 0) {
            // Only in the non-general sections.
            if (!$section->visible) {
                $sectionstyle = ' hidden';
            }
            if (course_get_format($course)->is_section_current($section)) {
                $sectionstyle = ' current';
            }
        }

        $o .= html_writer::start_tag('li', [
            'id' => 'section-'.$section->section,
            'class' => 'section main clearfix'.$sectionstyle,
            'role' => 'region',
            'aria-labelledby' => "sectionid-{$section->id}-title",
            'data-sectionid' => $section->section,
            'data-sectionreturnid' => $sectionreturn // 0 
        ]);

        $leftcontent = $this->section_left_content($section, $course, $onsectionpage);
        $o.= html_writer::tag('div', $leftcontent, array('class' => 'left side'));

        $rightcontent = $this->section_right_content($section, $course, $onsectionpage);
        $o.= html_writer::tag('div', $rightcontent, array('class' => 'right side'));
        $o.= html_writer::start_tag('div', array('class' => 'content'));

        // When not on a section page, we display the section titles except the general section if null
        $hasnamenotsecpg = (!$onsectionpage && ($section->section != 0 || !is_null($section->name)));

        // When on a section page, we only display the general section title, if title is not the default one
        $hasnamesecpg = ($onsectionpage && ($section->section == 0 && !is_null($section->name)));

        $classes = ' accesshide';
        if ($hasnamenotsecpg || $hasnamesecpg) {
            $classes = '';
        }
        $sectionname = html_writer::tag('span', $title);
        $o .= $this->output->heading($sectionname, 3, 'sectionname' . $classes, "sectionid-{$section->id}-title");

        $o .= $this->section_availability($section);
/*
        $o .= html_writer::start_tag('div', array('class' => 'summary'));
        if ($section->uservisible || $section->visible) {
            // Show summary if section is available or has availability restriction information.
            // Do not show summary if section is hidden but we still display it because of course setting
            // "Hidden sections are shown in collapsed form".
            $o .= $this->format_summary_text($section);
        }
        $o .= html_writer::end_tag('div');
*/
        return $o;
    }
}
