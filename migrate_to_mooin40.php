<?php
/*

Important: walk through the following steps in correct order

1. change format manually
2. run this script
3. check course if everything is ok

script will:
- add additional sections for use as chapter
- rename chapters and sections if informations 
  are available in old chapter config and section headers
- change theme for course
- delete blocks
- delete empty sections at the end of the course

*/

require_once('../../../config.php');
require_once( $CFG->libdir.'/blocklib.php' );
//require_once($CFG->dirroot. '/course/format/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once('locallib.php');

if (!has_capability('moodle/site:manageblocks', context_system::instance())) {
    die('error no permission');
}

$courseid = optional_param('id', 0, PARAM_INT);
$confirm = optional_param('confirm', 1, PARAM_INT);

if ($courseid == 0) die('error missing courseid');

$coursecontext = context_course::instance($courseid);

echo '<p>Migrating course '.$courseid.' to courseformat Mooin 4.0</p>';


if ($course = $DB->get_record('course', array('id' => $courseid))) {
    // check if course already migrated (check theme and format)
    if($course->format == 'mooin4' && $course->theme == 'mooin4') {
        die('error: course already migrated to mooin 4.0');
    }
}
else {
    die('error: course not found');
}

if ($confirm == 1) {
    echo '<p>Der Kurs "'.$course->fullname.'" (id: '.$courseid.') wird in das neue Kursformat Mooin 4.0 konvertiert!</p>';
    echo '<p>Bitte beachte auch folgende Hinweise zur Umstellung der MOOCs auf das neue Kursformat: <a href="https://futurelearnlab.de/hub/blocks/ildmetaselect/detailpage.php?id=364" target="blank">Anleitung</a></p>';
    echo '<p><a href="'.$CFG->wwwroot.'/course/format/mooin4/migrate_to_mooin40.php?id='.$courseid.'&confirm=0">OK</a> ';
    echo '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$courseid.'">cancel</a></p>';
    die();
}

// get Chapter Navigation Block 
if (!$blockrecord = $DB->get_record('block_instances', array('blockname' => 'oc_mooc_nav',
    'parentcontextid' => $coursecontext->id), '*', MUST_EXIST)) {
    die('error: block_oc_mooc_nav not found');
}

$block_oc_mooc_nav = block_instance('oc_mooc_nav', $blockrecord);

$chapter_configtext = $block_oc_mooc_nav->config->chapter_configtext;
//print_object($chapter_configtext);

// Walk throug chapters and add new chapter,
// configure names of chapters and lessons
$lines = preg_split("/[\r\n]+/", trim($chapter_configtext));
// each line is a chapter
$sectioncount = 1;
foreach ($lines as $line) {
    //echo '<p><strong>New line!</strong> sectioncount: '.$sectioncount.' | '.$line.'</p>';
    // name=Kapitel 1;lections=8;enabled=true;img=kapitel_0_896.png
    $elements = explode(';', $line);
    $chapter = array();
    foreach ($elements as $element) {
        $ex = explode('=', $element);
        if (isset($ex[1])) {
            $chapter[$ex[0]] = $ex[1];
        }
    }
    $chaptertitle = get_string('chapter', 'format_mooin4');
    //print_object($chapter);continue;
    if ($sectioncount > 1) {
        // add new chapter
        echo '<p>Add new chapter (section '.$sectioncount.')</p>';
        $sectionnumber = $DB->count_records('course_sections', array('course' => $courseid));
        if ($sectionnumber > 0) {
            if (isset($chapter['name']) && $chapter['name'] != '') {
                $chaptertitle = $chapter['name'];
            }
            else {
                $chaptertitle = get_string('chapter', 'format_mooin4').' 1';
            }
            $newsection = new stdClass();
            $newsection->course = $courseid;
            $newsection->section = $sectionnumber;
            $newsection->name = $chaptertitle;
            $newsection->summaryformat = 1;
            $newsection->visible = 1;
            $newsection->timemodified = time();
//*
            if ($newsectionid = $DB->insert_record('course_sections', $newsection)) {
                // move new section to position of $sectioncount
                if ($course = $DB->get_record('course', array('id' => $courseid))) {
                    move_section_to($course, $sectionnumber, $sectioncount, true);
                    // convert new section to chapter
                    $newchapter = new stdClass();
                    $newchapter->courseid = $courseid;
                    $newchapter->title = $chaptertitle;
                    $newchapter->sectionid = $newsectionid;
                    $newchapter->chapter = 1;
                    if ($DB->insert_record('format_mooin4_chapter', $newchapter)) {
                        // sort chapters
                        sort_course_chapters($courseid);
                        echo '<p>New chapter title: '.$chaptertitle.'</p>';
                        $sectioncount++;
                    }
                }
                else {
                    die('error get course');
                }
            }
            else {
                die('error insert new section (chapter)');
            }
//*/
        }
        else {
            die('error count course sections');
        }
    }
    else {
        // first chapter should already exist
        echo '<p>First chapter (section): '.$sectioncount.' already exists</p>';
        // rename/add name
        if (isset($chapter['name']) && $chapter['name'] != '') {
            $chaptertitle = $chapter['name'];
            if ($section = $DB->get_record('course_sections', array('course' => $courseid, 'section' => $sectioncount))) {
                $section->name = $chapter['name'];
                $DB->update_record('course_sections', $section);
                if($firstchapter = $DB->get_record('format_mooin4_chapter', array('sectionid' => $section->id, 'courseid' => $courseid))) {
                    $firstchapter->title = $chapter['name'];
                    $DB->update_record('format_mooin4_chapter', $firstchapter);
                }
            }

        }
        $sectioncount++;
    }

    // walk throug lessons and add name
    echo '<p>Add names for: '.$chapter['lections'].' lessons</p>';
    $modlabel = $DB->get_record('modules', array('name' => 'label'));
    $i = 0;
    while ($i < $chapter['lections']) {
        // add lesson name
        if ($section = $DB->get_record('course_sections', array('course' => $courseid, 'section' => $sectioncount))) {
            if (isset($section->sequence)) {
                $coursemodules = explode(',', $section->sequence);
                $firstmoduleid = $coursemodules[0];
                if ($firstmodule = $DB->get_record('course_modules', 
                    array('course' => $courseid, 'module' => $modlabel->id, 'id' => $firstmoduleid))) {
                    if ($label = $DB->get_record('label', array('id' => $firstmodule->instance))) {
                        $labellines = preg_split("/[\r\n]+/", trim($label->name));
                        $lessonname = $labellines[0];
                        //print_object($labellines[0]);
                        $section->name = $lessonname;
                        $DB->update_record('course_sections', $section);
                    }
                }
                else {
                    //die('error no label at position 1');
                }
            }
        }
        else {
            die('error get section');
        }
        $i++;
        $sectioncount++;
    }
    //echo '<p>sectioncount: '.$sectioncount.'</p>';
}

// Get unnessessary blocks and remove from course
if ($blockinstances = $DB->get_records('block_instances', array('parentcontextid' => $coursecontext->id))) {
    foreach($blockinstances as $blockinstance) {
        //print_object($blockinstance->blockname.' instance: '.$blockinstance->id);
        if ($blockinstance->blockname == 'online_users_map' ||
            $blockinstance->blockname == 'oc_course_footer' ||
            $blockinstance->blockname == 'oc_mooc_nav') {
                blocks_delete_instance($blockinstance);
        }
    }
}

// delete all sections that are not configured and empty
$sql = "select * 
          from {course_sections} 
         where course = :courseid 
           and section >= :sectioncount 
           and (sequence is null 
            or sequence = '')";

$params = array('courseid' => $courseid, 'sectioncount' => $sectioncount);//, 'empty' => '');

if ($emptysections = $DB->get_records_sql($sql, $params)) {
    //print_object($emptysections);
    foreach($emptysections as $emptysection) {
        $DB->delete_records('course_sections', array('id' => $emptysection->id));
        echo '<p>deleted empty section: '.$emptysection->id.'</p>';
    }
}

// change theme
$course->theme = 'mooin4';
$DB->update_record('course', $course);

// link to course
echo '<p><a href="'.$CFG->wwwroot.'/course/view.php?id='.$courseid.'">to course</a></p>';
