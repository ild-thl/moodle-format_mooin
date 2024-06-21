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
 * This file contains main class for Topics course format.
 *
 * @since     Moodle 2.0
 * @package   format_moointopics
 * @copyright 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/format/lib.php');

use core\output\inplace_editable;
use core\plugininfo\format;
use format_moointopics\local\chapterlib as chapterlib;

/**
 * Main class for the Topics course format.
 *
 * @package    format_moointopics
 * @copyright  2012 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_moointopics extends core_courseformat\base {

    /**
     * Returns true if this course format uses sections.
     *
     * @return bool
     */
    public function uses_sections() {
        return true;
    }

    public function uses_course_index() {
        return true;
    }

    public function uses_indentation(): bool {
        return false;
    }

    /**
     * Returns the display name of the given section that the course prefers.
     *
     * Use section name is specified by user. Otherwise use default ("Topic #").
     *
     * @param int|stdClass $section Section object from database or just field section.section
     * @return string Display name that the course format prefers, e.g. "Topic 2"
     */
    public function get_section_name($section) {
        $section = $this->get_section($section);
        if ((string)$section->name !== '') {
            return format_string(
                $section->name,
                true,
                ['context' => context_course::instance($this->courseid)]
            );
        } else {
            return $this->get_default_section_name($section);
        }
    }

    /**
     * Returns the default section name for the topics course format.
     *
     * If the section number is 0, it will use the string with key = section0name from the course format's lang file.
     * If the section number is not 0, the base implementation of course_format::get_default_section_name which uses
     * the string with the key = 'sectionname' from the course format's lang file + the section number will be used.
     *
     * @param stdClass $section Section object from database or just field course_sections section
     * @return string The default value for the section name.
     */
    public function get_default_section_name($section) {
        if ($section->section == 0) {
            // Return the general section.
            return get_string('section0name', 'format_moointopics');
        } else {
            // Use course_format::get_default_section_name implementation which
            // will display the section name in "Topic n" format.
            return parent::get_default_section_name($section);
        }
    }

    /**
     * Generate the title for this section page.
     *
     * @return string the page title
     */
    public function page_title(): string {
        return get_string('topicoutline');
    }

    /**
     * Get the course display value for the current course.
     *
     * Formats extending topics or weeks will use coursedisplay as this setting name
     * so they don't need to override the method. However, if the format uses a different
     * display logic it must override this method to ensure the core renderers know
     * if a COURSE_DISPLAY_MULTIPAGE or COURSE_DISPLAY_SINGLEPAGE is being used.
     *
     * @return int The current value (COURSE_DISPLAY_MULTIPAGE or COURSE_DISPLAY_SINGLEPAGE)
     */
    public function get_course_display(): int {
        return COURSE_DISPLAY_MULTIPAGE;
    }

    /**
     * The URL to use for the specified course (with section).
     *
     * @param int|stdClass $section Section object from database or just field course_sections.section
     *     if omitted the course view page is returned
     * @param array $options options for view URL. At the moment core uses:
     *     'navigation' (bool) if true and section has no separate page, the function returns null
     *     'sr' (int) used by multipage formats to specify to which section to return
     * @return null|moodle_url
     */
    public function get_view_url($section, $options = []) {
        global $CFG;
        $course = $this->get_course();
        $url = new moodle_url('/course/view.php', ['id' => $course->id]);

        $sr = null;
        if (array_key_exists('sr', $options)) {
            $sr = $options['sr'];
        }
        if (is_object($section)) {
            $sectionno = $section->section;
        } else {
            $sectionno = $section;
        }
        if ($sectionno !== null) {
            if ($sr !== null) {
                if ($sr) {
                    $usercoursedisplay = COURSE_DISPLAY_MULTIPAGE;
                    $sectionno = $sr;
                } else {
                    $usercoursedisplay = COURSE_DISPLAY_SINGLEPAGE;
                }
            } else {
                $usercoursedisplay = $course->coursedisplay ?? COURSE_DISPLAY_MULTIPAGE;
            }
            if ($sectionno != 0 && $usercoursedisplay == COURSE_DISPLAY_MULTIPAGE) {
                $url->param('section', $sectionno);
            } else {
                if (empty($CFG->linkcoursesections) && !empty($options['navigation'])) {
                    return null;
                }
                $url->set_anchor('section-' . $sectionno);
            }
        }
        return $url;
    }

    /**
     * Returns the information about the ajax support in the given source format.
     *
     * The returned object's property (boolean)capable indicates that
     * the course format supports Moodle course ajax features.
     *
     * @return stdClass
     */
    public function supports_ajax() {
        $ajaxsupport = new stdClass();
        $ajaxsupport->capable = true;
        return $ajaxsupport;
    }

    public function supports_components() {
        return true;
    }


    public function extend_course_navigation($navigation, navigation_node $node) {
        global $PAGE, $DB, $CFG, $USER;
        // If section is specified in course/view.php, make sure it is expanded in navigation.
        if ($navigation->includesectionnum === false) {
            $selectedsection = optional_param('section', null, PARAM_INT);
            if (
                $selectedsection !== null && (!defined('AJAX_SCRIPT') || AJAX_SCRIPT == '0') &&
                $PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)
            ) {
                $navigation->includesectionnum = $selectedsection;
            }
        }

        // Check if there are callbacks to extend course navigation.
        parent::extend_course_navigation($navigation, $node);

        $courseid = $this->get_course()->id;

        // if ($badgesnode = $node->get('badgesview', navigation_node::TYPE_SETTING)) {
        //     $badgesnode->remove();
        // }

        // if($competenciesnode = $node->get('competencies', navigation_node::TYPE_SETTING)) {
        //     $competenciesnode->remove();
        // }

        // if($gradesnode = $node->get('grades', navigation_node::TYPE_SETTING)) {
        //     $gradesnode->remove();
        // }



        // if ($forum = $DB->get_record('forum', array('course' => $courseid, 'type' => 'news'))) {
        //     if ($module = $DB->get_record('modules', array('name' => 'forum'))) {
        //         if($cm = $DB->get_record('course_modules', array('module' => $module->id, 'instance'=>$forum->id))){
        //             $node->add(
        //                 get_string('news', 'format_moointopics'),
        //                 new moodle_url('/mod/forum/view.php', array('id' => $cm->id)),
        //                 navigation_node::TYPE_CUSTOM,
        //                 null,
        //                 'format_moointopics_newsforum',
        //                 new pix_icon('i/news', '')
        //             );

        //         }
        //     }

        // }

        // $overview = $node->add(
        //     $this->get_course()->shortname,
        //     //get_string('course_overview', 'format_moointopics'),
        //     null,
        //     //new moodle_url('/course/view.php', array('id' => $courseid)),
        //     navigation_node::TYPE_CUSTOM,
        //     null,
        //     'format_moointopics_course_overview',
        //     new pix_icon('i/location', '')
        // );
        // $overview->showinflatnavigation=true;
        // //$overview->add_class('overview_node');

        $node->add(
            get_string('badges', 'format_moointopics'),
            new moodle_url('/course/format/moointopics/badges.php', array('id' => $courseid)),
            navigation_node::TYPE_CUSTOM,
            null,
            'format_moointopics_badges',
            new pix_icon('i/badge', '')
        );


        $node->add(
            get_string('certificates', 'format_moointopics'),
            new moodle_url('/course/format/moointopics/certificates.php', array('id' => $courseid)),
            navigation_node::TYPE_CUSTOM,
            null,
            'format_moointopics_certificates',
            new pix_icon('t/award', '')
        );

        $node->add(
            get_string('forums', 'format_moointopics'),
            new moodle_url('/course/format/moointopics/all_discussionforums.php', array('id' => $courseid)),
            navigation_node::TYPE_CUSTOM,
            null,
            'format_moointopics_discussions',
            new pix_icon('t/messages', '')
        );

        $node->add(
            get_string('participants', 'format_moointopics'),
            new moodle_url('/course/format/moointopics/participants.php', array('id' => $courseid)),
            navigation_node::TYPE_CUSTOM,
            null,
            'format_moointopics_participants',
            new pix_icon('t/messages', '')
        );

        // $participantsnode = $node->get('participants', navigation_node::TYPE_CONTAINER);
        // if ($participantsnode) {
        //     $participantsnode->remove();
        //     $participantsnode->action = $url = new moodle_url('/course/format/moointopics/participants.php', array('id' => $courseid));
        //     $participantsnode->text = get_string('participants', 'format_moointopics');
        // $node->add_node($participantsnode);
        // }



        // We want to remove the general section if it is empty.
        $modinfo = get_fast_modinfo($this->get_course());
        $sections = $modinfo->get_sections();
        if (!isset($sections[0])) {
            // The general section is empty to find the navigation node for it we need to get its ID.
            $section = $modinfo->get_section_info(0);
            $generalsection = $node->get($section->id, navigation_node::TYPE_SECTION);
            if ($generalsection) {
                // We found the node - now remove it.
                $generalsection->remove();
            }
        }

        if ($sections = $DB->get_records('course_sections', array('course' => $courseid), 'section')) {
            foreach ($sections as $section) {
                if ($sectionnode = $node->get($section->id, navigation_node::TYPE_SECTION)) {
                    $sectionnode->remove();

                    if ($section->section == 0) {
                        continue;
                    }
                    $title = 'NULL';
                    $url = '';
                    $pre = $section->name;
                    $completed = '';
                    $lastvisitedsection = '';

                    if ($chapter = $DB->get_record('format_moointopics_chapter', array('sectionid' => $section->id))) {

                        $pre = get_string('chapter', 'format_moointopics') . ' ' . $chapter->chapter . ': ';
                        $title = $pre . get_section_name($this->get_course(), $section);
                        if (count(\format_moointopics\local\chapterlib::get_sectionids_for_chapter($chapter->id)) > 0) {
                            $url = new moodle_url('/course/view.php', array('id' => $courseid, 'section' => $section->section + 1));
                        }
                        $icon = new pix_icon('i/folder', '');

                        $chapterinfo = \format_moointopics\local\chapterlib::get_chapter_info($chapter);
                        if ($chapterinfo['completed'] == true) {
                            $completed .= ' completed';
                        }

                        $chapter_node = $node->add(
                            $title,
                            null,
                            navigation_node::TYPE_SECTION,
                            get_string('chapter_short', 'format_moointopics') . ' ' . $chapter->chapter,
                            $chapter->sectionid,
                            $icon
                        );

                        // $chapter_node->showinflatnavigation = true;
                        // $chapter_node->isexpandable = true;
                        // $chapter_node->collapse = true;
                        // $chapter_node->mainnavonly = false;
                        // $chapter_node->isactive = false;

                        // if ($chapter->chapter == 1) {
                        //     $chapter_node->preceedwithhr = false;
                        // } else {
                        //     $chapter_node->preceedwithhr = true;
                        // }

                        $chapter_node->add_class('chapter' . $completed . $lastvisitedsection);
                        // $chapter_node->add_class('collapsed');
                    } else {
                        $pre = get_string('lesson', 'format_moointopics') . ' ' . \format_moointopics\local\chapterlib::get_section_prefix($section) . ': ';
                        if ($section->name) {
                            $title = $pre . get_section_name($this->get_course(), $section);
                        } else {
                            $title = $pre . $title;
                        }
                        $url = new moodle_url('/course/view.php', array('id' => $courseid, 'section' => $section->section));
                        $icon = new pix_icon('i/navigationitem', '');

                        // mark as completed
                        $progress_result = \format_moointopics\local\chapterlib::get_section_progress($courseid, $section->id, $USER->id);
                        if ($progress_result == 100) {
                            $completed .= ' completed';
                        }

                        // if (isset($icon)) {
                        //     $sectionnodeNew->icon = $icon;
                        // }
                        // $sectionnode->$key = null;
                        if ($parentchapter = \format_moointopics\local\chapterlib::get_parent_chapter($section)) {
                            $chapter_node = $node->get($parentchapter->sectionid);
                        }
                      
                        if ($parentchapter && $chapter_node) {
                            $section_node = $chapter_node->add(
                                $title,
                                $url,
                                navigation_node::TYPE_SECTION,
                                get_string('lesson_short', 'format_moointopics') . ' ' . \format_moointopics\local\chapterlib::get_section_prefix($section) . ': ',
                                $section->id,
                                $icon
                            );
                            // $section_node->showinflatnavigation = true;
                            // $section_node->collapse = true;
                            // $section_node->preceedwithhr = true;

                            // highlight as last visited section only if we are not in a section
                            $urlparams = $PAGE->url->params();
                            if (!isset($urlparams['section'])) {
                                if (get_user_preferences('format_moointopics_last_section_in_course_' . $courseid, 0, $USER->id) == $section->section) {
                                    $section_node->add_Class('lastvisitedsection');
                                    //$section_node->make_active();
                                    //$section_node->parent->isexpandable = true;
                                    $section_node->parent->collapse = false;
                                    $section_node->parent->remove_class('collapsed');
                                }
                            }
                            $section_node->add_Class('lesson' . $completed . $lastvisitedsection);
                        }


                        //$sectionnodeNew -> showinflatnavigation = true;
                        //$parent_node->add_node($sectionnodeNew);
                    }

                    // $sectionnode->text = '<span class="media-body'.$completed.$lastvisitedsection.'">'.$title.'</span>';
                    // $sectionnode->shorttext = $pre;
                    // $sectionnode->action = $url;
                    // if (isset($icon)) {
                    //     $sectionnode->icon = $icon;
                    // }
                    // // $sectionnode->$key = null;
                    // $node->add_node($sectionnode);
                    //}

                }
            }
        }

        // unenrol from course
        if ($unenrolurl = \format_moointopics\local\chapterlib::get_unenrol_url($courseid)) {
            $unenrol_node = $node->add(
                get_string('unenrol', 'format_moointopics'),
                $unenrolurl,
                navigation_node::TYPE_CUSTOM,
                null,
                'format_moointopics_unenrol',
                new pix_icon('i/user', '')
            );
            $unenrol_node->add_class("unenrol-btn");
        }
    }



    /**
     * Loads all of the course sections into the navigation.
     *
     * @param global_navigation $navigation
     * @param navigation_node $node The course node within the navigation
     * @return void
     */
    // public function extend_course_navigation($navigation, navigation_node $node) {
    //     global $PAGE;
    //     // If section is specified in course/view.php, make sure it is expanded in navigation.
    //     if ($navigation->includesectionnum === false) {
    //         $selectedsection = optional_param('section', null, PARAM_INT);
    //         if ($selectedsection !== null && (!defined('AJAX_SCRIPT') || AJAX_SCRIPT == '0') &&
    //                 $PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)) {
    //             $navigation->includesectionnum = $selectedsection;
    //         }
    //     }

    //     // Check if there are callbacks to extend course navigation.
    //     parent::extend_course_navigation($navigation, $node);

    //     // We want to remove the general section if it is empty.
    //     $modinfo = get_fast_modinfo($this->get_course());
    //     $sections = $modinfo->get_sections();
    //     if (!isset($sections[0])) {
    //         // The general section is empty to find the navigation node for it we need to get its ID.
    //         $section = $modinfo->get_section_info(0);
    //         $generalsection = $node->get($section->id, navigation_node::TYPE_SECTION);
    //         if ($generalsection) {
    //             // We found the node - now remove it.
    //             $generalsection->remove();
    //         }
    //     }
    // }

    // /**
    //  * Custom action after section has been moved in AJAX mode.
    //  *
    //  * Used in course/rest.php
    //  *
    //  * @return array This will be passed in ajax respose
    //  */
    // public function ajax_section_move() {
    //     global $DB, $PAGE;
    //     $titles = [];
    //     $course = $this->get_course();
    //     $modinfo = get_fast_modinfo($course);
    //     $renderer = $this->get_renderer($PAGE);
    //     if ($renderer && ($sections = $modinfo->get_section_info_all())) {
    //         foreach ($sections as $number => $section) {

    //             if ($chapter = $DB->get_record('format_moointopics_chapter', array('sectionid' => $section->id))) {
    //                 \format_moointopics\local\chapterlib::sort_course_chapters($course->id);
    //                 //$section->name = $chapter->title;
    //                 $titles[$number] = get_string('chapter', 'format_moointopics').' '.$chapter->chapter.' '.$renderer->section_title_without_link($section, $course);
    //             }
    //             else {
    //                 $titles[$number] = get_string('lesson', 'format_moointopics').' '.\format_moointopics\local\chapterlib::get_section_prefix($section).' '.$renderer->section_title($section, $course);
    //             }
    //         }
    //     }
    //     return ['sectiontitles' => $titles, 'action' => 'move'];
    // }

    /**
     * Returns the list of blocks to be automatically added for the newly created course.
     *
     * @return array of default blocks, must contain two keys BLOCK_POS_LEFT and BLOCK_POS_RIGHT
     *     each of values is an array of block names (for left and right side columns)
     */
    public function get_default_blocks() {
        return [
            BLOCK_POS_LEFT => [],
            BLOCK_POS_RIGHT => [],
        ];
    }

    

    /**
     * Adds format options elements to the course/section edit form.
     *
     * This function is called from {@link course_edit_form::definition_after_data()}.
     *
     * @param MoodleQuickForm $mform form the elements are added to.
     * @param bool $forsection 'true' if this is a section edit form, 'false' if this is course edit form.
     * @return array array of references to the added form elements.
     */
    public function create_edit_form_elements(&$mform, $forsection = false) {
        global $COURSE;
        $elements = parent::create_edit_form_elements($mform, $forsection);

        if (!$forsection && (empty($COURSE->id) || $COURSE->id == SITEID)) {
            // Add "numsections" element to the create course form - it will force new course to be prepopulated
            // with empty sections.
            // The "Number of sections" option is no longer available when editing course, instead teachers should
            // delete and add sections when needed.
            $courseconfig = get_config('moodlecourse');
            $max = (int)$courseconfig->maxsections;
            $element = $mform->addElement('select', 'numsections', get_string('numberweeks'), range(0, $max ?: 52));
            $mform->setType('numsections', PARAM_INT);
            if (is_null($mform->getElementValue('numsections'))) {
                $mform->setDefault('numsections', $courseconfig->numsections);
            }
            array_unshift($elements, $element);
        }

        return $elements;
    }

    /**
     * Updates format options for a course.
     *
     * In case if course format was changed to 'topics', we try to copy options
     * 'coursedisplay' and 'hiddensections' from the previous format.
     *
     * @param stdClass|array $data return value from {@link moodleform::get_data()} or array with data
     * @param stdClass $oldcourse if this function is called from {@link update_course()}
     *     this object contains information about the course before update
     * @return bool whether there were any changes to the options values
     */
    public function update_course_format_options($data, $oldcourse = null) {
        global $DB;

        if (!$oldcourse) {
            // Add first chapter, there must be no sections without parent chapter
            $chaptertitle = get_string('chapter', 'format_moointopics') . ' 1';

            $newsection = new stdClass();
            $newsection->course = $this->courseid;
            $newsection->section = 1;
            $newsection->name = $chaptertitle;
            $newsection->summaryformat = 1;
            $newsection->visible = 1;
            $newsection->timemodified = time();

            if ($newsectionid = $DB->insert_record('course_sections', $newsection)) {
                $newchapter = new stdClass();
                $newchapter->courseid = $this->courseid;
                $newchapter->title = $chaptertitle;
                $newchapter->sectionid = $newsectionid;
                $newchapter->chapter = 1;
                $DB->insert_record('format_moointopics_chapter', $newchapter);
            }
        } else { // add new chapter at position 1 if format is changed to moointopics
            // was format of oldcourse not moointopics?
            if ($oldcourse->format != 'moointopics') {
                // is there no chapter at position 1?
                if ($section1 = $DB->get_record('course_sections', array('course' => $this->courseid, 'section' => 1))) {
                    if (!$DB->get_record('format_moointopics_chapter', array('courseid' => $this->courseid, 'sectionid' => $section1->id))) {
                        // add new section
                        $sectionnumber = $DB->count_records('course_sections', array('course' => $this->courseid));
                        if ($sectionnumber > 0) {
                            $chaptertitle = get_string('chapter', 'format_moointopics') . ' 1';
                            $newsection = new stdClass();
                            $newsection->course = $this->courseid;
                            $newsection->section = $sectionnumber;
                            $newsection->name = $chaptertitle;
                            $newsection->summaryformat = 1;
                            $newsection->visible = 1;
                            $newsection->timemodified = time();

                            if ($newsectionid = $DB->insert_record('course_sections', $newsection)) {
                                // move new section to position 1
                                if ($course = $DB->get_record('course', array('id' => $this->courseid))) {
                                    move_section_to($course, $sectionnumber, 1, true);
                                    // convert new section to chapter
                                    $newchapter = new stdClass();
                                    $newchapter->courseid = $this->courseid;
                                    $newchapter->title = $chaptertitle;
                                    $newchapter->sectionid = $newsectionid;
                                    $newchapter->chapter = 1;
                                    $DB->insert_record('format_moointopics_chapter', $newchapter);
                                    \format_moointopics\local\chapterlib::sort_course_chapters($this->courseid);
                                }
                            }
                        }
                    }
                }
            }
        }

        if ($course = $DB->get_record('course', array('id' => $this->courseid))) {
            $course->enablecompletion = 1;
            $course->showcompletionconditions = 0;
            $course->newsitems = 1;
            $DB->update_record('course', $course);
        }

        return $this->update_format_options($data);
    }

    /**
     * Whether this format allows to delete sections.
     *
     * Do not call this function directly, instead use {@link course_can_delete_section()}
     *
     * @param int|stdClass|section_info $section
     * @return bool
     */
    public function can_delete_section($section) {
        return true;
    }

    /**
     * Prepares the templateable object to display section name.
     *
     * @param \section_info|\stdClass $section
     * @param bool $linkifneeded
     * @param bool $editable
     * @param null|lang_string|string $edithint
     * @param null|lang_string|string $editlabel
     * @return inplace_editable
     */
    public function inplace_editable_render_section_name(
        $section,
        $linkifneeded = true,
        $editable = null,
        $edithint = null,
        $editlabel = null
    ) {
        if (empty($edithint)) {
            $edithint = new lang_string('editsectionname', 'format_moointopics');
        }
        if (empty($editlabel)) {
            $title = get_section_name($section->course, $section);
            $editlabel = new lang_string('newsectionname', 'format_moointopics', $title);
        }
        return parent::inplace_editable_render_section_name($section, $linkifneeded, $editable, $edithint, $editlabel);
    }

    /**
     * Indicates whether the course format supports the creation of a news forum.
     *
     * @return bool
     */
    public function supports_news() {
        return true;
    }

    /**
     * Returns whether this course format allows the activity to
     * have "triple visibility state" - visible always, hidden on course page but available, hidden.
     *
     * @param stdClass|cm_info $cm course module (may be null if we are displaying a form for adding a module)
     * @param stdClass|section_info $section section where this module is located or will be added to
     * @return bool
     */
    public function allow_stealth_module_visibility($cm, $section) {
        // Allow the third visibility state inside visible sections or in section 0.
        return !$section->section || $section->visible;
    }

    /**
     * Callback used in WS core_course_edit_section when teacher performs an AJAX action on a section (show/hide).
     *
     * Access to the course is already validated in the WS but the callback has to make sure
     * that particular action is allowed by checking capabilities
     *
     * Course formats should register.
     *
     * @param section_info|stdClass $section
     * @param string $action
     * @param int $sr
     * @return null|array any data for the Javascript post-processor (must be json-encodeable)
     */
    // public function section_action($section, $action, $sr) {
    //     global $PAGE;

    //     if ($section->section && ($action === 'setmarker' || $action === 'removemarker')) {
    //         // Format 'topics' allows to set and remove markers in addition to common section actions.
    //         require_capability('moodle/course:setcurrentsection', context_course::instance($this->courseid));
    //         course_set_marker($this->courseid, ($action === 'setmarker') ? $section->section : 0);
    //         return null;
    //     }

    //     \format_moointopics\local\chapterlib::sort_course_chapters($section->course);

    //     // For show/hide actions call the parent method and return the new content for .section_availability element.
    //     $rv = parent::section_action($section, $action, $sr);
    //     $renderer = $PAGE->get_renderer('format_moointopics');

    //     if (!($section instanceof section_info)) {
    //         $modinfo = course_modinfo::instance($this->courseid);
    //         $section = $modinfo->get_section_info($section->section);
    //     }
    //     $elementclass = $this->get_output_classname('content\\section\\availability');
    //     $availability = new $elementclass($this, $section);

    //     $rv['section_availability'] = $renderer->render($availability);
    //     return $rv;
    // }

    public function section_action($section, $action, $sr) {
        global $PAGE;
        if (!$this->uses_sections() || !$section->section) {
            // No section actions are allowed if course format does not support sections.
            // No actions are allowed on the 0-section by default (overwrite in course format if needed).
            throw new moodle_exception('sectionactionnotsupported', 'core', null, s($action));
        }

        $course = $this->get_course();
        $coursecontext = context_course::instance($course->id);
        $modinfo = $this->get_modinfo();
        $renderer = $this->get_renderer($PAGE);

        \format_moointopics\local\chapterlib::sort_course_chapters($section->course);

        if (!($section instanceof section_info)) {
            $section = $modinfo->get_section_info($section->section);
        }

        if ($sr) {
            $this->set_section_number($sr);
        }

        switch ($action) {
            case 'hide':
            case 'show':
                require_capability('moodle/course:sectionvisibility', $coursecontext);
                $visible = ($action === 'hide') ? 0 : 1;
                course_update_section($course, $section, array('visible' => $visible));
                break;
            case 'sectionSetChapter':
                //TODO: Add capability
                //format_moointopics\local\chapterlib::set_chapter($section->id);
                //course_update_section($course, $section, array('chapterstatus' => true));
                break;
            case 'sectionUnsetChapter':
                //TODO: Add capability
                //format_moointopics\local\chapterlib::unset_chapter($section->id);
                //course_update_section($course, $section, array('chapterstatus' => false));
                break;
            case 'refresh':
                return [
                    'content' => $renderer->course_section_updated($this, $section),
                ];
            default:
                throw new moodle_exception('sectionactionnotsupported', 'core', null, s($action));
        }

        return ['modules' => $this->get_section_modules_updated($section)];
    }

    /**
     * Return the plugin configs for external functions.
     *
     * @return array the list of configuration settings
     * @since Moodle 3.5
     */
    public function get_config_for_external() {
        // Return everything (nothing to hide).
        $formatoptions = $this->get_format_options();
        $formatoptions['indentation'] = get_config('format_moointopics', 'indentation');
        return $formatoptions;
    }

    /**
     * Updates the value in the database and modifies this object respectively.
     *
     * ALWAYS check user permissions before performing an update! Throw exceptions if permissions are not sufficient
     * or value is not legit.
     *
     * @param stdClass $section
     * @param string $itemtype
     * @param mixed $newvalue
     * @return \core\output\inplace_editable
     */
    public function inplace_editable_update_section_name($section, $itemtype, $newvalue) {
        global $DB;
        if ($itemtype === 'sectionname' || $itemtype === 'sectionnamenl') {
            $context = context_course::instance($section->course);
            external_api::validate_context($context);
            require_capability('moodle/course:update', $context);

            $newtitle = clean_param($newvalue, PARAM_TEXT);
            if (strval($section->name) !== strval($newtitle)) {
                course_update_section($section->course, $section, array('name' => $newtitle));
            }
            if ($chapter = $DB->get_record('format_moointopics_chapter', array('sectionid' => $section->id))) {
                $chapter->title = $newtitle;
                $DB->update_record('format_moointopics_chapter', $chapter);
            }
            return $this->inplace_editable_render_section_name($section, ($itemtype === 'sectionname'), true);
        }
    }



/**
     * Returns if an specific section is visible to the current user.
     *
     * Formats can overrride this method to implement any special section logic.
     *
     * @param section_info $section the section modinfo
     * @return bool;
     */
    public function is_section_visible(section_info $section): bool {
        // Previous to Moodle 4.0 thas logic was hardcoded. To prevent errors in the contrib plugins
        // the default logic is the same required for topics and weeks format and still uses
        // a "hiddensections" format setting.
        $course = $this->get_course();
        //$hidesections = false;
        $hidesections = $course->hiddensections ?? true;
        // Show the section if the user is permitted to access it, OR if it's not available
        // but there is some available info text which explains the reason & should display,
        // OR it is hidden but the course has a setting to display hidden sections as unavailable.
        return $section->uservisible ||
            ($section->visible && !$section->available && !empty($section->availableinfo)) ||
            (!$section->visible && !$hidesections);
    }
}

/**
 * Implements callback inplace_editable() allowing to edit values in-place.
 *
 * @param string $itemtype
 * @param int $itemid
 * @param mixed $newvalue
 * @return inplace_editable
 */
function format_moointopics_inplace_editable($itemtype, $itemid, $newvalue) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/course/lib.php');
    if ($itemtype === 'sectionname' || $itemtype === 'sectionnamenl') {
        $section = $DB->get_record_sql(
            'SELECT s.* FROM {course_sections} s JOIN {course} c ON s.course = c.id WHERE s.id = ? AND c.format = ?',
            [$itemid, 'moointopics'],
            MUST_EXIST
        );
        return course_get_format($section->course)->inplace_editable_update_section_name($section, $itemtype, $newvalue);
    }
}

function format_moointopics_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    require_login($course, true);

    if ($filearea != 'headerimagemobile' and $filearea != 'headerimagedesktop') {
        return false;
    }

    $itemid = (int)array_shift($args); // The first item in the $args array.

    // Extract the filename / filepath from the $args array.
    $filename = array_pop($args); // The last item in the $args array.
    if (!$args) {
        $filepath = '/'; // Array $args is empty => the path is '/'.
    } else {
        $filepath = '/' . implode('/', $args) . '/'; // Array $args contains elements of the filepath.
    }

    // Retrieve the file from the Files API.
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'format_moointopics', $filearea, $itemid, '/', $filename);
    if (!$file) {
        return false; // The file does not exist.
    }

    // Finally send the file - in this case with a cache lifetime of 0 seconds and no filtering.
    send_stored_file($file, 0, 0, $forcedownload, $options);
}

