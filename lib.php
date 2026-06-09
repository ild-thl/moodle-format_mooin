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
 * @package   format_mooin4
 * @copyright 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/format/lib.php');

use core\output\inplace_editable;
use core\plugininfo\format;
use format_mooin4\local\utils as utils;
use core_external\external_api;


/**
 * Main class for the Topics course format.
 *
 * @package    format_mooin4
 * @copyright  2012 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_mooin4 extends core_courseformat\base {

    /**
     * Returns true if this course format uses sections.
     *
     * @return bool
     */
    public function uses_sections() {
        return true;
    }

    /**
     * Returns true if this course format uses course index.
     *
     * @return bool
     */
    public function uses_course_index() {
        $course = $this->get_course();
        $courseid = $course->id;
        if (get_toggle_courseindex_visibility($courseid) === 1) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns whether this course format uses indentation.
     *
     * @return bool
     */
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
            return get_string('section0name', 'format_mooin4');
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
        return get_string('topicoutline', 'format_mooin4');
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
                    // Added tinjohn - return null throws error call on method call out() on null.
                    // In moodle/course/format/classes/output/local/state/section.php.
                    // Do not return null;.
                    // Display section on separate page.
                    $sectioninfo = $this->get_section($sectionno);
                    return new moodle_url('/course/section.php', ['id' => $sectioninfo->id]);
                }
                $url->set_anchor('section-' . $sectionno);
            }
        }
        return $url;
    }

    /**
     * Not in use but raw version for comparison: The URL to use for the specified course (with section).
     *
     * @param int|stdClass $section Section object from database or just field course_sections.section
     *     if omitted the course view page is returned
     * @param array $options options for view URL. At the moment core uses:
     *     'navigation' (bool) if true and section has no separate page, the function returns null
     *     'sr' (int) used by multipage formats to specify to which section to return
     * @return null|moodle_url
     */
    public function get_view_url_raw($section, $options = []) {
        $course = $this->get_course();
        if (array_key_exists('sr', $options) && !is_null($options['sr'])) {
            $sectionno = $options['sr'];
        } else if (is_object($section)) {
            $sectionno = $section->section;
        } else {
            $sectionno = $section;
        }
        if ((!empty($options['navigation']) || array_key_exists('sr', $options)) && $sectionno !== null) {
            // Display section on separate page.
            $sectioninfo = $this->get_section($sectionno);
            return new moodle_url('/course/section.php', ['id' => $sectioninfo->id]);
        }

        return new moodle_url('/course/view.php', ['id' => $course->id]);
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
     * Returns true if this course format supports components.
     *
     * @return bool
     */
    public function supports_components() {
        return true;
    }


    /**
     * Loads all of the course sections into the navigation.
     *
     * @param global_navigation $navigation The navigation object
     * @param navigation_node $node The course node within the navigation
     * @return void
     */
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

        $node->add(
            get_string('badges', 'format_mooin4'),
            new moodle_url('/course/format/mooin4/badges.php', ['id' => $courseid]),
            navigation_node::TYPE_CUSTOM,
            null,
            'format_mooin4_badges',
            new pix_icon('i/badge', '')
        );

        $node->add(
            get_string('certificates', 'format_mooin4'),
            new moodle_url('/course/format/mooin4/certificates.php', ['id' => $courseid]),
            navigation_node::TYPE_CUSTOM,
            null,
            'format_mooin4_certificates',
            new pix_icon('t/award', '')
        );

        $node->add(
            get_string('forums', 'format_mooin4'),
            new moodle_url('/course/format/mooin4/all_discussionforums.php', ['id' => $courseid]),
            navigation_node::TYPE_CUSTOM,
            null,
            'format_mooin4_discussions',
            new pix_icon('t/messages', '')
        );

        $node->add(
            get_string('participants', 'format_mooin4'),
            new moodle_url('/course/format/mooin4/participants.php', ['id' => $courseid]),
            navigation_node::TYPE_CUSTOM,
            null,
            'format_mooin4_participants',
            new pix_icon('t/messages', '')
        );

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

        if ($sections = $DB->get_records('course_sections', ['course' => $courseid], 'section')) {
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

                    if ($chapter = $DB->get_record('format_mooin4_chapter', ['sectionid' => $section->id])) {
                        // Show breadcrumb chapter prefix according to settings.
                        if (get_toggle_section_number_visibility($courseid) === 1) {
                            $pre = get_string('chapter', 'format_mooin4') . ' ' . $chapter->chapter . ': ';
                        } else {
                            $pre = '';
                        }
                        $title = $pre . get_section_name($this->get_course(), $section);
                        if (count(utils::get_sectionids_for_chapter($chapter->id)) > 0) {
                            $url = new moodle_url('/course/view.php', ['id' => $courseid, 'section' => $section->section + 1]);
                        }
                        $icon = new pix_icon('i/folder', '');

                        $chapterinfo = utils::get_chapter_info($chapter);
                        if ($chapterinfo['completed'] == true) {
                            $completed .= ' completed';
                        }

                        $chapternode = $node->add(
                            $title,
                            null,
                            navigation_node::TYPE_SECTION,
                            get_string('chapter_short', 'format_mooin4') . ' ' . $chapter->chapter,
                            $chapter->sectionid,
                            $icon
                        );

                        $chapternode->add_class('chapter' . $completed . $lastvisitedsection);
                    } else {
                        // Show breadcrumb lesson prefix according to settings.
                        if (get_toggle_section_number_visibility($courseid) === 1) {
                            $pre = get_string('lesson', 'format_mooin4') . ' ' . utils::get_section_prefix($section) . ': ';
                        } else {
                            $pre = '';
                        }
                        if ($section->name) {
                            $title = $pre . get_section_name($this->get_course(), $section);
                        } else {
                            $title = $pre . $title;
                        }
                        $url = new moodle_url('/course/view.php', ['id' => $courseid, 'section' => $section->section]);
                        $icon = new pix_icon('i/navigationitem', '');

                        // Mark as completed.
                        $progressresult = utils::get_section_progress($courseid, $section->id, $USER->id);
                        if ($progressresult == 100) {
                            $completed .= ' completed';
                        }

                        if ($parentchapter = utils::get_parent_chapter($section)) {
                            $chapternode = $node->get($parentchapter->sectionid);
                        }

                        if ($parentchapter && $chapternode) {
                            $sectionnode = $chapternode->add(
                                $title,
                                $url,
                                navigation_node::TYPE_SECTION,
                                get_string('lesson_short', 'format_mooin4') . ' ' . $pre . ': ',
                                $section->id,
                                $icon
                            );

                            // Highlight as last visited section only if we are not in a section.
                            $urlparams = $PAGE->url->params();
                            if (!isset($urlparams['section'])) {
                                $lastcoursepreference = 'format_mooin4_last_section_in_course_' . $courseid;
                                if (get_user_preferences($lastcoursepreference, 0, $USER->id) == $section->section) {
                                    $sectionnode->add_Class('lastvisitedsection');

                                    $sectionnode->parent->collapse = false;
                                    $sectionnode->parent->remove_class('collapsed');
                                }
                            }
                            $sectionnode->add_Class('lesson' . $completed . $lastvisitedsection);
                        }

                    }

                }
            }
        }

        // Unenrol from course.
        if ($unenrolurl = utils::get_unenrol_url($courseid)) {
            $unenrolnode = $node->add(
                get_string('unenrol', 'format_mooin4'),
                $unenrolurl,
                navigation_node::TYPE_CUSTOM,
                null,
                'format_mooin4_unenrol',
                new pix_icon('i/user', '')
            );
            $unenrolnode->add_class("unenrol-btn");
        }
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
     * Adds format options elements to the course/section edit form.
     *
     * This function is called from {@see course_edit_form::definition_after_data()}.
     *
     * @param MoodleQuickForm $mform form the elements are added to.
     * @param bool $forsection 'true' if this is a section edit form, 'false' if this is course edit form.
     * @return array array of references to the added form elements.
     */
    public function create_edit_form_elements(&$mform, $forsection = false) {
        global $CFG, $COURSE;
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

        if (!$forsection && self::is_mooin4_format_selected($mform)) {
            global $PAGE;
            $context = context_course::instance($COURSE->id, IGNORE_MISSING);
            if (empty($COURSE->id)) {
                $context = $PAGE->context;
            }
            
            $hascapability = true;
            if ($context && !has_capability('format/mooin4:allowmooin4', $context)) {
                $hascapability = false;
                $message = get_string('error_mooin4_format_not_allowed', 'format_mooin4');

                $element = $mform->addElement(
                    'static',
                    'mooin4formatnotallowed',
                    '',
                    html_writer::div($message, 'alert alert-warning')
                );
                array_unshift($elements, $element);
            }

            // Only show theme requirement warning if the user actually has the capability.
            if ($hascapability) {
                $message = self::get_mooin4_theme_requirement_message($mform);
                if ($message !== '') {
                    $element = $mform->addElement(
                        'static',
                        'mooin4themerequirementwarning',
                        '',
                        html_writer::div($message, 'alert alert-warning')
                    );
                    array_unshift($elements, $element);
                }
            }
        }

        return $elements;
    }

    /**
     * Validates course edit form data for this format.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @param array $errors errors already discovered in edit form validation
     * @return array of "element_name"=>"error_description" if there are errors
     */
    public function edit_form_validation($data, $files, $errors) {
        global $COURSE, $PAGE;

        if (($data['format'] ?? '') === 'mooin4') {
            $context = context_course::instance($COURSE->id, IGNORE_MISSING);
            if (empty($COURSE->id)) {
                $context = $PAGE->context;
            }
            if ($context && !has_capability('format/mooin4:allowmooin4', $context)) {
                $errors['format'] = get_string('error_mooin4_format_not_allowed', 'format_mooin4');
            }
        }

        if (!isset($errors['format'])) {
            foreach (self::get_mooin4_theme_validation_errors($data) as $field => $message) {
                $errors[$field] = $message;
            }
        }

        return $errors;
    }

    /**
     * Theme plugin name required for the mooin4 course format.
     *
     * @return string
     */
    public static function get_required_course_theme(): string {
        return 'mooin4';
    }

    /**
     * Validation errors when mooin4 is selected but course theme requirements are not met.
     *
     * @param array $data submitted course edit form data
     * @return array field name => error message
     */
    protected static function get_mooin4_theme_validation_errors(array $data): array {
        global $CFG;

        if (($data['format'] ?? '') !== 'mooin4') {
            return [];
        }

        if (empty($CFG->allowcoursethemes)) {
            return [
                'format' => get_string(
                    'error_allowcoursethemes_required',
                    'format_mooin4',
                    get_string('allowcoursethemes', 'admin')
                ),
            ];
        }

        $theme = $data['theme'] ?? '';
        if ($theme !== self::get_required_course_theme()) {
            return ['theme' => get_string('error_mooin4_theme_required', 'format_mooin4')];
        }

        return [];
    }

    /**
     * Warning message for the course edit form when mooin4 theme requirements are not met.
     *
     * @param MoodleQuickForm $mform
     * @return string empty string if no warning should be shown
     */
    protected static function get_mooin4_theme_requirement_message($mform): string {
        global $CFG;

        if (empty($CFG->allowcoursethemes)) {
            return self::get_allowcoursethemes_warning_message();
        }

        if (self::get_selected_course_theme($mform) !== self::get_required_course_theme()) {
            return get_string('mooin4themerequirementwarning', 'format_mooin4');
        }

        return '';
    }

    /**
     * URL to the site setting that enables per-course themes.
     *
     * @return moodle_url
     */
    protected static function get_allowcoursethemes_settings_url(): moodle_url {
        return new moodle_url('/admin/settings.php', ['section' => 'themesettingsadvanced']);
    }

    /**
     * Warning HTML when allowcoursethemes is disabled (includes admin link when permitted).
     *
     * @return string
     */
    protected static function get_allowcoursethemes_warning_message(): string {
        $settingname = get_string('allowcoursethemes', 'admin');

        if (has_capability('moodle/site:config', context_system::instance())) {
            $link = html_writer::link(
                self::get_allowcoursethemes_settings_url(),
                get_string('themesettingsadvanced', 'admin')
            );
            return trim(
                get_string('allowcoursethemeswarning_prefix', 'format_mooin4') . ' '
                . $link . ' '
                . get_string('allowcoursethemeswarning_suffix', 'format_mooin4', $settingname)
            );
        }

        return get_string('allowcoursethemeswarning_noadmin', 'format_mooin4', $settingname);
    }

    /**
     * Whether the course edit form currently has the mooin4 format selected.
     *
     * @param MoodleQuickForm $mform
     * @return bool
     */
    protected static function is_mooin4_format_selected($mform): bool {
        if (!$mform->elementExists('format')) {
            return false;
        }
        $formatvalue = $mform->getElementValue('format');
        if (is_array($formatvalue)) {
            return ($formatvalue[0] ?? '') === 'mooin4';
        }
        return $formatvalue === 'mooin4';
    }

    /**
     * Returns the course theme currently selected in the course edit form.
     *
     * @param MoodleQuickForm $mform
     * @return string theme name or empty string if not forced
     */
    protected static function get_selected_course_theme($mform): string {
        if (!$mform->elementExists('theme')) {
            return '';
        }
        $themevalue = $mform->getElementValue('theme');
        if (is_array($themevalue)) {
            return (string)($themevalue[0] ?? '');
        }
        return (string)$themevalue;
    }

    /**
     * Updates format options for a course.
     *
     * In case if course format was changed to 'topics', we try to copy options
     * 'coursedisplay' and 'hiddensections' from the previous format.
     *
     * @param stdClass|array $data return value from {@see moodleform::get_data()} or array with data
     * @param stdClass $oldcourse if this function is called from {@see update_course()}
     *     this object contains information about the course before update
     * @return bool whether there were any changes to the options values
     */
    public function update_course_format_options($data, $oldcourse = null) {
        global $DB;

        // Function update_course_format_options for format_topics_test.php only.
        if (!$oldcourse) {
            // Add first chapter, there must be no sections without parent chapter.
            $chaptertitle = get_string('chapter', 'format_mooin4') . ' 1';

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
        } else {
            // Add new chapter at position 1 if format is changed to mooin4.
            // was format of oldcourse not mooin4?
            if ($oldcourse->format != 'mooin4') {
                // Is there no chapter at position 1?
                if ($section1 = $DB->get_record('course_sections', ['course' => $this->courseid, 'section' => 1])) {
                    $chapterexists = $DB->get_record(
                        'format_mooin4_chapter',
                        ['courseid' => $this->courseid, 'sectionid' => $section1->id]
                    );
                    if (!$chapterexists) {
                        // Add new section.
                        $sectionnumber = $DB->count_records('course_sections', ['course' => $this->courseid]);
                        if ($sectionnumber > 0) {
                            $chaptertitle = get_string('chapter', 'format_mooin4') . ' 1';
                            $newsection = new stdClass();
                            $newsection->course = $this->courseid;
                            $newsection->section = $sectionnumber;
                            $newsection->name = $chaptertitle;
                            $newsection->summaryformat = 1;
                            $newsection->visible = 1;
                            $newsection->timemodified = time();

                            if ($newsectionid = $DB->insert_record('course_sections', $newsection)) {
                                // Move new section to position 1.
                                if ($course = $DB->get_record('course', ['id' => $this->courseid])) {
                                    move_section_to($course, $sectionnumber, 1, true);
                                    // Convert new section to chapter.
                                    $newchapter = new stdClass();
                                    $newchapter->courseid = $this->courseid;
                                    $newchapter->title = $chaptertitle;
                                    $newchapter->sectionid = $newsectionid;
                                    $newchapter->chapter = 1;
                                    $DB->insert_record('format_mooin4_chapter', $newchapter);
                                    utils::sort_course_chapters($this->courseid);
                                }
                            }
                        }
                    }
                }
            }
        }

        if ($course = $DB->get_record('course', ['id' => $this->courseid])) {
            $course->newsitems = 1;
            $DB->update_record('course', $course);
        }

        return $this->update_format_options($data);
    }

    /**
     * Whether this format allows to delete sections.
     *
     * Do not call this function directly, instead use {@see course_can_delete_section()}
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
     * Callback used when teacher performs an AJAX action on a section.
     *
     * @param section_info|stdClass $section
     * @param string $action
     * @param int $sr
     * @return array Data for the Javascript post-processor
     */
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

        utils::sort_course_chapters($section->course);

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
                course_update_section($course, $section, ['visible' => $visible]);
                break;
            case 'sectionSetChapter':
            case 'sectionUnsetChapter':
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
        $formatoptions['indentation'] = get_config('format_mooin4', 'indentation');
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
                course_update_section($section->course, $section, ['name' => $newtitle]);
            }
            if ($chapter = $DB->get_record('format_mooin4_chapter', ['sectionid' => $section->id])) {
                $chapter->title = $newtitle;
                $DB->update_record('format_mooin4_chapter', $chapter);
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
        $hidesections = $course->hiddensections ?? true;
        // Show the section if the user is permitted to access it, OR if it's not available
        // but there is some available info text which explains the reason & should display,
        // OR it is hidden but the course has a setting to display hidden sections as unavailable.
        return $section->uservisible ||
            ($section->visible && !$section->available && !empty($section->availableinfo)) ||
            (!$section->visible && !$hidesections);
    }


    /**
     * Returns the course format options, with the possibility to include additional options for the edit form.
     *
     * This method defines and retrieves the course format options, allowing formats to include custom settings
     * and modify their behavior depending on whether they are being used for editing or display purposes.
     *
     * @param bool $foreditform Whether to include additional options specific to the edit form.
     * @return array The course format options, including defaults and edit form specifics.
     */
    public function course_format_options($foreditform = false) {
        static $courseformatoptions = false;
        if ($courseformatoptions === false) {
            $courseconfig = get_config('moodlecourse');
            $courseformatoptions = [
                'toggle_section_number_visibility' => [
                    'default' => 1,  // Default value (0 = not selected).
                    'type' => PARAM_BOOL,  // Boolean value (Checkbox).
                ],
                'toggle_courseindex_visibility' => [
                    'default' => 1,  // Default value (0 = not selected).
                    'type' => PARAM_BOOL,  // Boolean value (Checkbox).
                ],
                'toggle_newssection_visibility' => [
                    'default' => 1,  // Default value (0 = not selected).
                    'type' => PARAM_BOOL,  // Boolean value (Checkbox).
                ],
                'toggle_progressbar_visibility' => [
                    'default' => 1,  // Default value (0 = not selected).
                    'type' => PARAM_BOOL,  // Boolean value (Checkbox).
                ],
                'show_right_sidebar' => [
                    'default' => 0,
                    'type' => PARAM_BOOL,
                ],
            ];
            if (get_config('format_mooin4', "toggle_global_badge_visibility") == 1) {
                $courseformatoptions['toggle_badge_visibility'] = [
                    'default' => 1,  // Default value (0 = not selected).
                    'type' => PARAM_BOOL,  // Boolean value (Checkbox).

                ];
            }
            if (get_config('format_mooin4', "toggle_global_certificate_visibility") == 1) {
                $courseformatoptions['toggle_certificate_visibility'] = [
                    'default' => 1,  // Default value (0 = not selected).
                    'type' => PARAM_BOOL,  // Boolean value (Checkbox).

                ];
            }
            if (get_config('format_mooin4', "toggle_global_discussion_visibility") == 1) {
                $courseformatoptions['toggle_discussion_visibility'] = [
                    'default' => 1,  // Default value (0 = not selected).
                    'type' => PARAM_BOOL,  // Boolean value (Checkbox).

                ];
            }
            if (get_config('format_mooin4', "toggle_global_userlist_visibility") == 1) {
                $courseformatoptions['toggle_userlist_visibility'] = [
                    'default' => 1,  // Standardwert (0 = nicht ausgewählt).
                    'type' => PARAM_BOOL,  // Boolean-Wert (Checkbox).

                ];
            }
            if (get_config('format_mooin4', "toggle_global_participant_list_for_students") == 1) {
                $courseformatoptions['toggle_participant_list_for_students'] = [
                    'default' => 0,
                    'type' => PARAM_BOOL,
                ];
            }
        }
        if ($foreditform) {
            $courseformatoptionsedit = [];
            if (!isset($courseformatoptions['toggle_section_number_visibility']['label'])) {
                $courseformatoptionsedit['toggle_section_number_visibility'] = [
                    'label' => new lang_string('toggle_section_number_visibility', 'format_mooin4'),
                    'element_type' => 'advcheckbox',  // Checkbox type for edit form.
                    'help' => 'toggle_section_number_visibility',
                    'help_component' => 'format_mooin4',
                ];
            }
            if (!isset($courseformatoptions['show_right_sidebar']['label'])) {
                $courseformatoptionsedit['show_right_sidebar'] = [
                    'label' => new lang_string('show_right_sidebar', 'format_mooin4'),
                    'element_type' => 'advcheckbox',
                    'help' => 'show_right_sidebar',
                    'help_component' => 'format_mooin4',
                ];
            }
            $courseformatoptionsedit['toggle_courseindex_visibility'] = [
                'label' => new lang_string('toggle_courseindex_visibility', 'format_mooin4'),
                'element_type' => 'advcheckbox',  // Checkbox-Typ für das Bearbeitungsformular.
                'help' => 'toggle_courseindex_visibility',
                'help_component' => 'format_mooin4',
            ];
            $courseformatoptionsedit['toggle_newssection_visibility'] = [
                'label' => new lang_string('toggle_newssection_visibility', 'format_mooin4'),
                'element_type' => 'advcheckbox',  // Checkbox-Typ für das Bearbeitungsformular.
                'help' => 'toggle_newssection_visibility',
                'help_component' => 'format_mooin4',
            ];
            $courseformatoptionsedit['toggle_progressbar_visibility'] = [
                'label' => new lang_string('toggle_progressbar_visibility', 'format_mooin4'),
                'element_type' => 'advcheckbox',  // Checkbox-Typ für das Bearbeitungsformular.
                'help' => 'toggle_progressbar_visibility',
                'help_component' => 'format_mooin4',
            ];
            if (get_config('format_mooin4', "toggle_global_badge_visibility") == 1) {
                $courseformatoptionsedit['toggle_badge_visibility'] = [
                    'label' => new lang_string('toggle_badge_visibility', 'format_mooin4'),
                    'element_type' => 'advcheckbox',  // Checkbox type for edit form.
                    'help' => 'toggle_badge_visibility',
                    'help_component' => 'format_mooin4',
                ];
            }
            if (get_config('format_mooin4', "toggle_global_certificate_visibility") == 1) {
                $courseformatoptionsedit['toggle_certificate_visibility'] = [
                    'label' => new lang_string('toggle_certificate_visibility', 'format_mooin4'),
                    'element_type' => 'advcheckbox',  // Checkbox type for edit form.
                    'help' => 'toggle_certificate_visibility',
                    'help_component' => 'format_mooin4',
                ];
            }
            if (get_config('format_mooin4', "toggle_global_discussion_visibility") == 1) {
                $courseformatoptionsedit['toggle_discussion_visibility'] = [
                    'label' => new lang_string('toggle_discussion_visibility', 'format_mooin4'),
                    'element_type' => 'advcheckbox',  // Checkbox type for edit form.
                    'help' => 'toggle_discussion_visibility',
                    'help_component' => 'format_mooin4',
                ];
            }
            if (get_config('format_mooin4', "toggle_global_userlist_visibility") == 1) {
                $courseformatoptionsedit['toggle_userlist_visibility'] = [
                    'label' => new lang_string('toggle_userlist_visibility', 'format_mooin4'),
                    'element_type' => 'advcheckbox',  // Checkbox-Typ für das Bearbeitungsformular.
                    'help' => 'toggle_userlist_visibility',
                    'help_component' => 'format_mooin4',
                ];
            }
            if (get_config('format_mooin4', "toggle_global_participant_list_for_students") == 1) {
                $courseformatoptionsedit['toggle_participant_list_for_students'] = [
                    'label' => new lang_string('toggle_participant_list_for_students', 'format_mooin4'),
                    'element_type' => 'advcheckbox',
                    'help' => 'toggle_participant_list_for_students',
                    'help_component' => 'format_mooin4',
                ];
            }
            if (!empty($courseformatoptionsedit)) {
                $courseformatoptions = array_merge_recursive($courseformatoptions, $courseformatoptionsedit);
            }
        }

        return $courseformatoptions;
    }

    /**
     * Course deletion hook.
     *
     * Removes all format_mooin4-specific data when a course is deleted:
     *   - Chapter records from mdl_format_mooin4_chapter
     *   - All format_mooin4_* user preferences tied to this course,
     *     its sections, and its course modules.
     */
    public function delete_format_data() {
        global $DB;

        // Always call parent to remove the core 'coursesectionspreferences_' preference.
        parent::delete_format_data();

        $courseid = $this->courseid;

        // 1. Delete all chapter records for this course.
        $DB->delete_records('format_mooin4_chapter', ['courseid' => $courseid]);

        // 2. Collect section IDs before they are removed (called while sections still exist).
        $sectionids = $DB->get_fieldset_select('course_sections', 'id', 'course = ?', [$courseid]);

        // 3. Collect course-module IDs for this course.
        $cms = $DB->get_records('course_modules', ['course' => $courseid], '', 'id, instance');

        // 4. Delete course-level user preference: last visited section.
        $DB->delete_records('user_preferences', [
            'name' => 'format_mooin4_last_section_in_course_' . $courseid,
        ]);

        // 5. Delete section-level user preferences.
        foreach ($sectionids as $sectionid) {
            $DB->delete_records('user_preferences', [
                'name' => 'format_mooin4_section_completed_' . $sectionid,
            ]);
            $DB->delete_records('user_preferences', [
                'name' => 'format_mooin4_hide_modal_for_section_' . $sectionid,
            ]);
        }

        // 6. Delete course-module-level user preferences (H5P / HVP progress).
        foreach ($cms as $cm) {
            $DB->delete_records('user_preferences', [
                'name' => 'format_mooin4_hvp_progress_cmid_' . $cm->id,
            ]);
            $DB->delete_records('user_preferences', [
                'name' => 'format_mooin4_hvp_progress_' . $cm->instance,
            ]);
        }
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
            [$itemid, 'mooin4'],
            MUST_EXIST
        );
        return course_get_format($section->course)->inplace_editable_update_section_name($section, $itemtype, $newvalue);
    }
}
/**
 * Serve the files from the format_mooin4 file areas.
 *
 * @param stdClass $course Course object
 * @param stdClass $cm Course module object
 * @param context $context Context object
 * @param string $filearea File area
 * @param array $args Extra arguments
 * @param bool $forcedownload Whether to force download
 * @param array $options Additional options
 * @return bool False if file not found, does not return if found
 */
function format_mooin4_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    // Handle placeholder images (system context, no login required).
    $placeholderareas = ['placeholder_badges', 'placeholder_certificates', 'placeholder_participants'];
    if (in_array($filearea, $placeholderareas)) {
        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return false;
        }

        $fs = get_file_storage();
        
        // For placeholder areas, the itemid is always 0 and comes first in args
        $itemid = (int)array_shift($args); // Remove itemid from beginning of args
        $filename = array_pop($args); // Get filename from end of args
        $filepath = $args ? '/' . implode('/', $args) . '/' : '/'; // Remaining items form the path

        $file = $fs->get_file($context->id, 'format_mooin4', $filearea, $itemid, $filepath, $filename);

        if (!$file || $file->is_directory()) {
            return false;
        }

        send_stored_file($file, 86400, 0, $forcedownload, $options);
        return;
    }

    // Handle other file areas (requires login).
    require_login($course, true);

    if ($filearea != 'headerimagemobile' && $filearea != 'headerimagedesktop') {
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





/**
 * Get the custom setting 'toggle_section_number_visibility' of a course.
 *
 * @param int $courseid The ID of the course.
 * @return int The value of the setting (1 for visible, 0 for not visible).
 */
function get_toggle_section_number_visibility($courseid) {
    // Get course data.
    $course = get_course($courseid);

    // Get course format.
    $format = course_get_format($courseid); // Get the format for the current course.
    $formatoptions = $format->get_format_options(); // Get all course format options.

    // Check if the custom option is set.
    if (isset($formatoptions['toggle_section_number_visibility'])) {
        // If value is set, use it.
        return $formatoptions['toggle_section_number_visibility'];
    } else {
        // Otherwise use default value.
        $courseformatoptions = $format->course_format_options(false); // Get default options.
        return $courseformatoptions['toggle_section_number_visibility']['default'];
    }
}

/**
 * Get the custom setting 'toggle_newssection_visibility' of a course.
 *
 * @param int $courseid The ID of the course.
 * @return int The value of the setting (1 for visible, 0 for not visible).
 */
function get_toggle_newssection_visibility($courseid) {
    $format = course_get_format($courseid); // Get the format for the current course.
    $formatoptions = $format->get_format_options(); // Get all course format options.
    // Check if the custom option is set.
    if (isset($formatoptions['toggle_newssection_visibility'])) {
        return $formatoptions['toggle_newssection_visibility'];
    } else {
        $courseformatoptions = $format->course_format_options(false); // Get default options.
        return $courseformatoptions['toggle_newssection_visibility']['default'];
    }
}

/**
 * Get the custom setting 'toggle_progressbar_visibility' of a course.
 *
 * @param int $courseid The ID of the course.
 * @return int The value of the setting (1 for visible, 0 for not visible).
 */
function get_toggle_progressbar_visibility($courseid) {
    $format = course_get_format($courseid); // Get the format for the current course.
    $formatoptions = $format->get_format_options(); // Get all course format options.
    // Check if the custom option is set.
    if (isset($formatoptions['toggle_progressbar_visibility'])) {
        return $formatoptions['toggle_progressbar_visibility'];
    } else {
        $courseformatoptions = $format->course_format_options(false); // Get default options.
        return $courseformatoptions['toggle_progressbar_visibility']['default'];
    }
}

/**
 * Get the custom setting 'toggle_discussion_visibility' of a course.
 *
 * @param int $courseid The ID of the course.
 * @return int The value of the setting (1 for visible, 0 for not visible).
 */
function get_toggle_discussion_visibility($courseid) {
    if (get_config('format_mooin4', "toggle_global_discussion_visibility") != 1) {
        return 0; // Or false – depending on desired behavior.
    }
    $format = course_get_format($courseid); // Get the format for the current course.
    $formatoptions = $format->get_format_options(); // Get all course format options.
    // Check if the custom option is set.
    if (isset($formatoptions['toggle_discussion_visibility'])) {
        return $formatoptions['toggle_discussion_visibility'];
    } else {
        $courseformatoptions = $format->course_format_options(false); // Get default options.
        return $courseformatoptions['toggle_discussion_visibility']['default'];
    }
}

/**
 * Get the custom setting 'toggle_badge_visibility' of a course.
 *
 * @param int $courseid The ID of the course.
 * @return int The value of the setting (1 for visible, 0 for not visible).
 */
function get_toggle_badge_visibility($courseid) {
    if (get_config('format_mooin4', "toggle_global_badge_visibility") != 1) {
        return 0; // Or false – depending on desired behavior.
    }
    $format = course_get_format($courseid); // Get the format for the current course.
    $formatoptions = $format->get_format_options(); // Get all course format options.
    // Check if the custom option is set.
    if (isset($formatoptions['toggle_badge_visibility'])) {
        return $formatoptions['toggle_badge_visibility'];
    } else {
        $courseformatoptions = $format->course_format_options(false); // Get default options.
        return $courseformatoptions['toggle_badge_visibility']['default'];
    }
}

/**
 * Get the custom setting 'toggle_certificate_visibility' of a course.
 *
 * @param int $courseid The ID of the course.
 * @return int The value of the setting (1 for visible, 0 for not visible).
 */
function get_toggle_certificate_visibility($courseid) {
    if (get_config('format_mooin4', "toggle_global_certificate_visibility") != 1) {
        return 0; // Or false – depending on desired behavior.
    }
    $format = course_get_format($courseid); // Get the format for the current course.
    $formatoptions = $format->get_format_options(); // Get all course format options.
    // Check if the custom option is set.
    if (isset($formatoptions['toggle_certificate_visibility'])) {
        return $formatoptions['toggle_certificate_visibility'];
    } else {
        $courseformatoptions = $format->course_format_options(false); // Get default options.
        return $courseformatoptions['toggle_certificate_visibility']['default'];
    }
}

/**
 * Get the custom setting 'toggle_userlist_visibility' of a course.
 *
 * @param int $courseid The ID of the course.
 * @return int The value of the setting (1 for visible, 0 for not visible).
 */
function get_toggle_userlist_visibility($courseid) {
    if (get_config('format_mooin4', "toggle_global_userlist_visibility") != 1) {
        return 0; // Or false – depending on desired behavior.
    }
    $format = course_get_format($courseid); // Get the format for the current course.
    $formatoptions = $format->get_format_options(); // Get all course format options.
    // Check if the custom option is set.
    if (isset($formatoptions['toggle_userlist_visibility'])) {
        return $formatoptions['toggle_userlist_visibility'];
    } else {
        $courseformatoptions = $format->course_format_options(false); // Get default options.
        return $courseformatoptions['toggle_userlist_visibility']['default'];
    }
}

/**
 * Get the custom setting 'toggle_participant_list_for_students' of a course.
 *
 * @param int $courseid The ID of the course.
 * @return int The value of the setting (1 for visible, 0 for not visible).
 */
function get_toggle_participant_list_for_students($courseid) {
    if (get_config('format_mooin4', 'toggle_global_participant_list_for_students') != 1) {
        return 0;
    }
    $format = course_get_format($courseid);
    $formatoptions = $format->get_format_options();
    if (isset($formatoptions['toggle_participant_list_for_students'])) {
        return $formatoptions['toggle_participant_list_for_students'];
    }
    $courseformatoptions = $format->course_format_options(false);
    return $courseformatoptions['toggle_participant_list_for_students']['default'];
}

/**
 * Whether the current user should see the full participant list and map.
 *
 * @param int $courseid The ID of the course.
 * @param \context $context The course context.
 * @return bool
 */
function format_mooin4_show_full_participants($courseid, $context) {
    if (has_capability('moodle/course:manageactivities', $context)) {
        return true;
    }
    if (!has_capability('moodle/course:viewparticipants', $context)) {
        return false;
    }
    return get_toggle_participant_list_for_students($courseid) == 1;
}

/**
 * Get the custom setting 'toggle_courseindex_visibility' of a course.
 *
 * @param int $courseid The ID of the course.
 * @return int The value of the setting (1 for visible, 0 for not visible).
 */
function get_toggle_courseindex_visibility($courseid) {
    $format = course_get_format($courseid); // Get the format for the current course.
    $formatoptions = $format->get_format_options(); // Get all course format options.
    // Check if the custom option is set.
    if (isset($formatoptions['toggle_courseindex_visibility'])) {
        return $formatoptions['toggle_courseindex_visibility'];
    } else {
        $courseformatoptions = $format->course_format_options(false); // Get default options.
        return $courseformatoptions['toggle_courseindex_visibility']['default'];
    }
}


/**
 * Get the custom setting 'show_right_sidebar' of a course.
 *
 * @param int $courseid The ID of the course.
 * @return int The value of the setting (1 for visible, 0 for not visible).
 */
function get_show_right_sidebar($courseid) {
    $course = get_course($courseid);
    $format = course_get_format($courseid);
    $formatoptions = $format->get_format_options();

    // Check if the custom option is set.
    if (isset($formatoptions['show_right_sidebar'])) {
        // If value is set, use it.
        return $formatoptions['show_right_sidebar'];
    } else {
        // Otherwise use default value.
        $courseformatoptions = $format->course_format_options(false);
        return $courseformatoptions['show_right_sidebar']['default'];
    }
}
