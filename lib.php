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
 * This file contains main class for mooin4 course format.
 *
 * @package   format_mooin4
 * @copyright 2022 ISy TH Lübeck <dev.ild@th-luebeck.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot. '/course/format/lib.php');

use core\output\inplace_editable;

/**
 * Main class for the mooin4 course format.
 *
 * @package    format_mooin4
 * @copyright  2022 ISy TH Lübeck <dev.ild@th-luebeck.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_mooin4 extends format_base {

    /**
     * Returns true if this course format uses sections.
     *
     * @return bool
     */
    public function uses_sections() {
        return true;
    }

    public function uses_indentation(): bool {
        return false;
    }

    // public function supports_components() {
    //     return true;
    // }

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
            return format_string($section->name, true,
                ['context' => context_course::instance($this->courseid)]);
        } else {
            return $this->get_default_section_name($section);
        }
    }

    /**
     * Returns the default section name for the mooin4 course format.
     *
     * If the section number is 0, it will use the string with key = section0name from the course format's lang file.
     * If the section number is not 0, the base implementation of format_base::get_default_section_name which uses
     * the string with the key = 'sectionname' from the course format's lang file + the section number will be used.
     *
     * @param stdClass $section Section object from database or just field course_sections section
     * @return string The default value for the section name.
     */
    public function get_default_section_name($section) {
        if ($section->section == 0) {
            // Return the general section.
            return get_string('section0name', 'format_mooin4');
        } else {
            // Use format_base::get_default_section_name implementation which
            // will display the section name in "Topic n" format.
            return parent::get_default_section_name($section);
        }
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
                if(isset($course->coursedisplay)) {
                    $usercoursedisplay = $course->coursedisplay;
                }
                else {
                    $usercoursedisplay = 1;
                }
            }
            //if ($sectionno != 0 && $usercoursedisplay == COURSE_DISPLAY_MULTIPAGE) {
                $url->param('section', $sectionno);
            //} else {
            //    if (empty($CFG->linkcoursesections) && !empty($options['navigation'])) {
            //        return null;
            //    }
            //    $url->set_anchor('section-'.$sectionno);
            //}
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

    /**
     * Loads all of the course sections into the navigation.
     *
     * @param global_navigation $navigation
     * @param navigation_node $node The course node within the navigation
     * @return void
     */
    public function extend_course_navigation($navigation, navigation_node $node) {
        global $PAGE, $DB, $CFG, $USER;
        // If section is specified in course/view.php, make sure it is expanded in navigation.
        if ($navigation->includesectionnum === false) {
            $selectedsection = optional_param('section', null, PARAM_INT);
            if ($selectedsection !== null && (!defined('AJAX_SCRIPT') || AJAX_SCRIPT == '0') &&
                    $PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)) {
                $navigation->includesectionnum = $selectedsection;
            }
        }

        // Check if there are callbacks to extend course navigation.
        parent::extend_course_navigation($navigation, $node);

        $courseid = $this->get_course()->id;

        if ($badgesnode = $node->get('badgesview', navigation_node::TYPE_SETTING)) {
            $badgesnode->remove();
        }

        if($competenciesnode = $node->get('competencies', navigation_node::TYPE_SETTING)) {
            $competenciesnode->remove();
        }

        if($gradesnode = $node->get('grades', navigation_node::TYPE_SETTING)) {
            $gradesnode->remove();
        }



        if ($forum = $DB->get_record('forum', array('course' => $courseid, 'type' => 'news'))) {
            if ($module = $DB->get_record('modules', array('name' => 'forum'))) {
                if($cm = $DB->get_record('course_modules', array('module' => $module->id, 'instance'=>$forum->id))){
                    $node->add(
                        get_string('news', 'format_mooin4'),
                        new moodle_url('/mod/forum/view.php', array('id' => $cm->id)),
                        navigation_node::TYPE_CUSTOM,
                        null,
                        'format_mooin4_newsforum',
                        new pix_icon('i/news', '')
                    );

                }
            }

        }

        $overview = $node->add(
            $this->get_course()->shortname,
            //get_string('course_overview', 'format_mooin4'),
            null,
            //new moodle_url('/course/view.php', array('id' => $courseid)),
            navigation_node::TYPE_CUSTOM,
            null,
            'format_mooin4_course_overview',
            new pix_icon('i/location', '')
        );
        $overview->showinflatnavigation=true;
        //$overview->add_class('overview_node');

        $node->add(
            get_string('badges', 'format_mooin4'),
            new moodle_url('/course/format/mooin4/badges.php', array('id' => $courseid)),
            navigation_node::TYPE_CUSTOM,
            null,
            'format_mooin4_badges',
            new pix_icon('i/badge', '')
        );


        $node->add(
            get_string('certificates', 'format_mooin4'),
            new moodle_url('/course/format/mooin4/certificates.php', array('id' => $courseid)),
            navigation_node::TYPE_CUSTOM,
            null,
            'format_mooin4_certificates',
            new pix_icon('t/award', '')
        );

        $node->add(
            get_string('forums', 'format_mooin4'),
            new moodle_url('/course/format/mooin4/alle_forums.php', array('id' => $courseid)),
            navigation_node::TYPE_CUSTOM,
            null,
            'format_mooin4_discussions',
            new pix_icon('t/messages', '')
        );

        $participantsnode = $node->get('participants', navigation_node::TYPE_CONTAINER);
        if ($participantsnode) {
            $participantsnode->remove();
            $participantsnode->action = $url = new moodle_url('/course/format/mooin4/participants.php', array('id' => $courseid));
            $participantsnode->text = get_string('participants', 'format_mooin4');
        $node->add_node($participantsnode);
        }
       
        

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

        require_once($CFG->dirroot.'/course/format/mooin4/locallib.php');

        


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

                    if ($chapter = $DB->get_record('format_mooin4_chapter', array('sectionid' => $section->id))) {

                        $pre = get_string('chapter','format_mooin4').' '.$chapter->chapter.': ';
                        $title = $pre.get_section_name($this->get_course(), $section);
                        if (count(get_sectionids_for_chapter($chapter->id)) > 0) {
                            $url = new moodle_url('/course/view.php', array('id' => $courseid, 'section' => $section->section + 1));
                        }
                        $icon = new pix_icon('i/folder', '');

                        $chapterinfo = get_chapter_info($chapter);
                        if ($chapterinfo['completed'] == true) {
                            $completed .= ' completed';
                        }


                    $chapter_node = $node->add($title,
                    null,
                    navigation_node::TYPE_SECTION,
                    get_string('chapter_short','format_mooin4').' '.$chapter->chapter,
                    $chapter->sectionid,
                    $icon
                    );
                    $chapter_node->showinflatnavigation = true;
                    $chapter_node->isexpandable = true;
                    $chapter_node->collapse = true;
                    $chapter_node->mainnavonly = false;
                    $chapter_node ->isactive = false;

                    if($chapter->chapter==1) {
                        $chapter_node->preceedwithhr = false;
                    } else {
                        $chapter_node->preceedwithhr = true;
                    }

                    $chapter_node->add_class('chapter'.$completed.$lastvisitedsection);
                    $chapter_node->add_class('collapsed');


                    }
                    else {
                        $pre = get_string('lesson','format_mooin4').' '.get_section_prefix($section).': ';
                        if ($section->name) {
                            $title = $pre.get_section_name($this->get_course(), $section);
                        }
                        else {
                            $title = $pre.$title;
                        }
                        $url = new moodle_url('/course/view.php', array('id' => $courseid, 'section' => $section->section));
                        $icon = new pix_icon('i/navigationitem', '');

                        // mark as completed
                        $progress_result = get_section_progress($courseid, $section->id, $USER->id);
                        if ($progress_result == 100) {
                            $completed .= ' completed';
                        }

                        // if (isset($icon)) {
                        //     $sectionnodeNew->icon = $icon;
                        // }
                    // $sectionnode->$key = null;
                    if ($parentchapter = get_parent_chapter($section)) {
                        $chapter_node = $node->get($parentchapter->sectionid);
                    }
                    // var_dump($parent_node -> key);
                    // exit();
                    if($parentchapter && $chapter_node) {
                        $section_node = $chapter_node->add($title,
                        $url,
                        navigation_node::TYPE_SECTION,
                        get_string('lesson_short','format_mooin4').' '.get_section_prefix($section).': ',
                        $section->id,
                        $icon
                        );
                        $section_node->showinflatnavigation = true;
                        $section_node->collapse = true;
                        $section_node->preceedwithhr = true;

                        // highlight as last visited section only if we are not in a section
                        $urlparams = $PAGE->url->params();
                        if (!isset($urlparams['section'])) {
                            if (get_user_preferences('format_mooin4_last_section_in_course_'.$courseid, 0, $USER->id) == $section->section) {
                                $section_node->add_Class('lastvisitedsection');
                                //$section_node->make_active();
                                //$section_node->parent->isexpandable = true;
                                $section_node->parent->collapse = false;
                                $section_node->parent->remove_class('collapsed');
                            }
                        }
                        $section_node->add_Class('lesson'.$completed.$lastvisitedsection);
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
        if ($unenrolurl = get_unenrol_url($courseid)) {
            $unenrol_node = $node->add(
                get_string('unenrol', 'format_mooin4'),
                $unenrolurl,
                navigation_node::TYPE_CUSTOM,
                null,
                'format_mooin4_unenrol',
                new pix_icon('i/user', '')
            );
            $unenrol_node->add_class("unenrol-btn");
        }
    }

    /**
     * Custom action after section has been moved in AJAX mode.
     *
     * Used in course/rest.php
     *
     * @return array This will be passed in ajax respose
     */
    public function ajax_section_move() {
        global $PAGE, $DB;
        $titles = [];
        $course = $this->get_course();

        /*
        if ($firstsection = $DB->get_record('course_sections', array('course' => $course->id, 'section' => 1))) {
            if (!$firstchapter = $DB->get_record('format_mooin4_chapter', array('courseid' => $course->id, 'sectionid' => $firstsection->id))) {
                // So section with number 1 is not a chapter
                // We need to change this
                $newchapter = new stdClass();
                $newchapter->courseid = $course->id;
                $newchapter->title = get_string('chapter', 'format_mooin4').' 1';
                $newchapter->sectionid = $firstsection->id;
                $newchapter->chapter = 1;

                $DB->insert_record('format_mooin4_chapter', $newchapter);
            }
        }
        */

        $modinfo = get_fast_modinfo($course);
        $renderer = $this->get_renderer($PAGE);
        if ($renderer && ($sections = $modinfo->get_section_info_all())) {
            foreach ($sections as $number => $section) {
                if ($chapter = $DB->get_record('format_mooin4_chapter', array('sectionid' => $section->id))) {
                    sort_course_chapters($course->id);
                    $section->name = $chapter->title;
                    $titles[$number] = get_string('chapter', 'format_mooin4').' '.$chapter->chapter.' '.$renderer->section_title_without_link($section, $course);
                }
                else {
                    $titles[$number] = get_string('lesson', 'format_mooin4').' '.get_section_prefix($section).' '.$renderer->section_title($section, $course);
                }
            }
        }
        return ['sectiontitles' => $titles, 'action' => 'move'];
    }

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
     * Definitions of the additional options that this course format uses for course.
     *
     * mooin4 format uses the following options:
     * - coursedisplay
     * - hiddensections
     *
     * @param bool $foreditform
     * @return array of options
     */
    public function course_format_options($foreditform = false) {
        static $courseformatoptions = false;
        if ($courseformatoptions === false) {
            $courseconfig = get_config('moodlecourse');
            $courseformatoptions = [
                'hiddensections' => [
                    'default' => 0, // mooin4: show hint
                    'type' => PARAM_INT,
                ],
                'coursedisplay' => [
                    'default' => 1, // mooin4: only one section per page
                    'type' => PARAM_INT,
                ],
            ];
        }
        if ($foreditform && !isset($courseformatoptions['coursedisplay']['label'])) {
            $courseformatoptionsedit = [
                'hiddensections' => [
                    'label' => new lang_string('hiddensections'),
                    'help' => 'hiddensections',
                    'help_component' => 'moodle',
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            0 => new lang_string('hiddensectionscollapsed'),
                            1 => new lang_string('hiddensectionsinvisible')
                        ],
                    ],
                ],
                'coursedisplay' => [
                    'label' => new lang_string('coursedisplay'),
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            COURSE_DISPLAY_SINGLEPAGE => new lang_string('coursedisplay_single'),
                            COURSE_DISPLAY_MULTIPAGE => new lang_string('coursedisplay_multi'),
                        ],
                    ],
                    'help' => 'coursedisplay',
                    'help_component' => 'moodle',
                ],
            ];
            $courseformatoptions = array_merge_recursive($courseformatoptions, $courseformatoptionsedit);
        }
        return array();
        return $courseformatoptions;
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
     * In case if course format was changed to 'mooin4', we try to copy options
     * 'coursedisplay' and 'hiddensections' from the previous format.
     *
     * @param stdClass|array $data return value from {@link moodleform::get_data()} or array with data
     * @param stdClass $oldcourse if this function is called from {@link update_course()}
     *     this object contains information about the course before update
     * @return bool whether there were any changes to the options values
     */
    /*
    public function update_course_format_options($data, $oldcourse = null) {
        $data = (array)$data;
        if ($oldcourse !== null) {
            $oldcourse = (array)$oldcourse;
            $options = $this->course_format_options();
            foreach ($options as $key => $unused) {
                if (!array_key_exists($key, $data)) {
                    if (array_key_exists($key, $oldcourse)) {
                        $data[$key] = $oldcourse[$key];
                    }
                }
            }
        }
        return $this->update_format_options($data);
    }
    //*/
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
    public function inplace_editable_render_section_name($section, $linkifneeded = true,
            $editable = null, $edithint = null, $editlabel = null) {
        if (empty($edithint)) {
            $edithint = new lang_string('editsectionname', 'format_mooin4');
        }
        if (empty($editlabel)) {
            $title = get_section_name($section->course, $section);
            $editlabel = new lang_string('newsectionname', 'format_mooin4', $title);
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
    public function section_action($section, $action, $sr) {
        global $PAGE, $DB;
        require_once('locallib.php');

        if ($section->section && ($action === 'setmarker' || $action === 'removemarker')) {
            // Format 'mooin4' allows to set and remove markers in addition to common section actions.
            require_capability('moodle/course:setcurrentsection', context_course::instance($this->courseid));
            course_set_marker($this->courseid, ($action === 'setmarker') ? $section->section : 0);
            return null;
        }

        // For show/hide actions call the parent method and return the new content for .section_availability element.
        $rv = parent::section_action($section, $action, $sr);
        if ($action == 'hide') {
            // if chapter
            if ($chapter = $DB->get_record('format_mooin4_chapter', array('sectionid' => $section->id))) {
                // hide also child sections
                if ($course = $DB->get_record('course', array('id' => $section->course))) {
                    // get children
                    $sectionids = get_sectionids_for_chapter($chapter->id);
                    foreach ($sectionids as $sectionid) {
                        if ($sec = $DB->get_record('course_sections', array('id' => $sectionid))) {
                            course_update_section($course, $sec, array('visible' => 0));
                        }
                    }
                }
            }
        }
        if ($action == 'show') {
            // if chapter
            if ($chapter = $DB->get_record('format_mooin4_chapter', array('sectionid' => $section->id))) {
                // show also child sections
                if ($course = $DB->get_record('course', array('id' => $section->course))) {
                    // get children
                    $sectionids = get_sectionids_for_chapter($chapter->id);
                    foreach ($sectionids as $sectionid) {
                        if ($sec = $DB->get_record('course_sections', array('id' => $sectionid))) {
                            course_update_section($course, $sec, array('visible' => 1));
                        }
                    }
                }
            }
        }
        $renderer = $PAGE->get_renderer('format_mooin4');
        $rv['section_availability'] = $renderer->section_availability($this->get_section($section));
        return $rv;
    }

    /**
     * Return the plugin configs for external functions.
     *
     * @return array the list of configuration settings
     * @since Moodle 3.5
     */
    public function get_config_for_external() {
        // Return everything (nothing to hide).
        return $this->get_format_options();
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
            if ($chapter = $DB->get_record('format_mooin4_chapter', array('sectionid' => $section->id))) {
                $chapter->title = $newtitle;
                $DB->update_record('format_mooin4_chapter', $chapter);
            }
            return $this->inplace_editable_render_section_name($section, ($itemtype === 'sectionname'), true);
        }
    }

    /**
     * Deletes a section
     *
     * Do not call this function directly, instead call {@link course_delete_section()}
     *
     * @param int|stdClass|section_info $section
     * @param bool $forcedeleteifnotempty if set to false section will not be deleted if it has modules in it.
     * @return bool whether section was deleted
     */
    public function delete_section($section, $forcedeleteifnotempty = false) {
        global $DB;
        if (!$this->uses_sections()) {
            // Not possible to delete section if sections are not used.
            return false;
        }
        if (!is_object($section)) {
            $section = $DB->get_record('course_sections', array('course' => $this->get_courseid(), 'section' => $section),
                'id,section,sequence,summary');
        }
        if (!$section || !$section->section) {
            // Not possible to delete 0-section.
            return false;
        }

        if (!$forcedeleteifnotempty && (!empty($section->sequence) || !empty($section->summary))) {
            return false;
        }

        $course = $this->get_course();

        // Remove the marker if it points to this section.
        if ($section->section == $course->marker) {
            course_set_marker($course->id, 0);
        }

        $lastsection = $DB->get_field_sql('SELECT max(section) from {course_sections}
                            WHERE course = ?', array($course->id));

        // Find out if we need to descrease the 'numsections' property later.
        $courseformathasnumsections = array_key_exists('numsections',
            $this->get_format_options());
        $decreasenumsections = $courseformathasnumsections && ($section->section <= $course->numsections);

        // Move the section to the end.
        move_section_to($course, $section->section, $lastsection, true);

        // Delete all modules from the section.
        foreach (preg_split('/,/', $section->sequence, -1, PREG_SPLIT_NO_EMPTY) as $cmid) {
            course_delete_module($cmid);
        }

        // Delete section and it's format options.
        $DB->delete_records('course_format_options', array('sectionid' => $section->id));
        $DB->delete_records('course_sections', array('id' => $section->id));
        rebuild_course_cache($course->id, true);

        // Delete section summary files.
        $context = \context_course::instance($course->id);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'course', 'section', $section->id);

        // Descrease 'numsections' if needed.
        if ($decreasenumsections) {
            $this->update_course_format_options(array('numsections' => $course->numsections - 1));
        }

        if ($chapter = $DB->get_record('format_mooin4_chapter', array('sectionid' => $section->id))) {
            require_once('locallib.php');
            $DB->delete_records('format_mooin4_chapter', array('id' => $chapter->id));
            sort_course_chapters($course->id);
        }

        return true;
    }

    /**
     * Updates format options for a course
     *
     * If $data does not contain property with the option name, the option will not be updated
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
            $chaptertitle = get_string('chapter', 'format_mooin4').' 1';

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

                $DB->insert_record('format_mooin4_chapter', $newchapter);
            }
        }
        else { // add new chapter at position 1 if format is changed to mooin4
            // was format of oldcourse not mooin4?
            if ($oldcourse->format != 'mooin4') {
                // is there no chapter at position 1?
                if ($section1 = $DB->get_record('course_sections', array('course' => $this->courseid, 'section' => 1))) {
                    if (!$DB->get_record('format_mooin4_chapter', array('courseid' => $this->courseid, 'sectionid' => $section1->id))) {
                        // add new section
                        //print_object($section1);die();
                        $sectionnumber = $DB->count_records('course_sections', array('course' => $this->courseid));
                        if ($sectionnumber > 0) {
                            $chaptertitle = get_string('chapter', 'format_mooin4').' 1';
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
                                    $DB->insert_record('format_mooin4_chapter', $newchapter);
                                    // sort chapters
                                    require_once('locallib.php');
                                    sort_course_chapters($this->courseid);
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
            $DB->update_record('course', $course);
        }

        return $this->update_format_options($data);
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
function format_mooin4_inplace_editable($itemtype, $itemid, $newvalue) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/course/lib.php');
    if ($itemtype === 'sectionname' || $itemtype === 'sectionnamenl') {
        $section = $DB->get_record_sql(
            'SELECT s.* FROM {course_sections} s JOIN {course} c ON s.course = c.id WHERE s.id = ? AND c.format = ?',
            [$itemid, 'mooin4'], MUST_EXIST);
        return course_get_format($section->course)->inplace_editable_update_section_name($section, $itemtype, $newvalue);
    }
}

function format_mooin4_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
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
    $file = $fs->get_file($context->id, 'format_mooin4', $filearea, $itemid, '/', $filename);
    if (!$file) {
        return false; // The file does not exist.
    }

    // Finally send the file - in this case with a cache lifetime of 0 seconds and no filtering.
    send_stored_file($file, 0, 0, $forcedownload, $options);
}