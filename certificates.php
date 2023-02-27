<?php
require_once('../../../config.php');
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir.'/completionlib.php');
require_once('locallib.php');
// require_once('../../../mod/ilddigitalcert/overview.php');

global $USER, $PAGE, $CFG, $DB, $OUTPUT;

// $courseid = optional_param('courseid', 1, PARAM_INT);
$courseid     = optional_param('id', 0, PARAM_INT);
$id = optional_param('id', null, PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance(SITEID); // $course->id, MUST_EXIST



require_login($course);

$systemcontext = context_system::instance();

// $PAGE->set_pagelayout('incourse');
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_context(\context_course::instance($course->id));
$PAGE->set_title("$course->shortname: " . get_string('my_certificate', 'format_mooin'));
$PAGE->set_heading($course->fullname);

// $PAGE->set_pagetype('course-view-' . $course->format);
// $PAGE->add_body_class('path-user');                     // So we can style it independently.
$PAGE->set_other_editing_capability('moodle/course:manageactivities');
// $PAGE->navbar->add(get_string('my_certificate', 'format_mooin'));

// require_once('./locallib.php');

$PAGE->set_url('/course/format/mooin/certificates.php', array('id' => $course->id));

echo $OUTPUT->header();

$out_certificat = null;
$val = false;
/* echo '<br />';
echo '<br />'; */
echo html_writer::div(navbar('certificates'), 'sticky-container');

echo html_writer::start_div('mooin-md-container'); //open outer div
//echo navbar('certificates');

echo html_writer::tag('h2', html_writer::tag('div', get_string('my_certificate', 'format_mooin'), array('class' => 'oc_badges_text')));

echo html_writer::tag('p', get_string('certificate_overview_description', 'format_mooin'));
echo '<br />';



/* if ($cm_id != 0) {

    $templatedata['certificate_name'] = 'Certificate'; // $moduleinstance->name;
    $templatedata['preview_url'] = (
        new moodle_url(
            '/mod/ilddigitalcert/view.php',
            array("id" => $cm_id, 'view' => "preview")
        )
    )->out(false);
    $templatedata['course_name'] = $course->fullname;
} else {
    if ($hascapviewall) {
        echo $OUTPUT->heading(get_string('overview_certifier', 'mod_ilddigitalcert'));
    } else {
        echo $OUTPUT->heading(get_string('overview', 'mod_ilddigitalcert'));
    }
} */
/* $a = 1;
    foreach ($ze as $key => $value) {
        foreach ($te as $k => $v) {
            if ($value->id == $v->section) {
                // var_dump($v);
                $cm_id = $v->id;
                array_push($templatedata, (object)[
                    'id'=> $v->id,
                    'index' => $a++,
                    'module' => $value->module,
                    'section' => $v->section
                ]) ;
            }
        }
    }
    // echo $cm_id;
    if (count($templatedata) > 0) { //
        for ($i=0; $i < count($templatedata); $i++) {

                $templatedata[$i]->certificate_name = 'Certificate';
                $templatedata[$i]->preview_url = (
                new moodle_url(
                    '/mod/ilddigitalcert/view.php',
                    array("id" => $templatedata[$i]->id, 'issuedid' => $templatedata[$i]->section)
                )
                )->out(false);
                $templatedata[$i]->course_name = $course->fullname;
                // $templatedata[$i]->image = '../images/certificat.png';
            }
    }else {
        $templatedata =  $OUTPUT->heading(get_string('certificate_overview', 'format_mooin'));
    }

    if (gettype($templatedata) == 'string') {
    $val = false;
} else {
    $val = true;
}
$templatedatas = (object)[
    'data' => $templatedata,
    'value' => $val

];

    */


/* $out_certificat .= html_writer::start_tag('div', ['class'=>'certificat_card', 'style'=>'display:flex']); // certificat_card

if (is_string($templatedata) == 1) {
    $out_certificat = $templatedata;
}
if (is_string($templatedata) != 1) {

    $imageurl = 'images/certificat.png';
    for ($i=0; $i < count($templatedata); $i++) {

        $out_certificat .= html_writer::start_tag('div', ['class'=>'certificat_body', 'style'=>'display:grid; cursor:pointer']); // certificat_card

        $out_certificat .= html_writer::empty_tag('img', array('src' => $imageurl, 'class' => '', 'style' => 'width: 100px; height: 100px; margin: 0 auto' . $opacity));

        // $out_certificat .= html_writer::start_tag('button', ['class'=>'btn btn-primary btn-lg certificat-image', 'style'=>'margin-right:2rem']);
        $certificat_url = $templatedata[$i]->preview_url;
        $out_certificat .= html_writer::link($certificat_url, ' ' . $templatedata[$i]->course_name . ' ' . $templatedata[$i]->index);
        // $out_certificat .= html_writer::end_tag('button'); // button
        $out_certificat .= html_writer::end_tag('div'); // certificat_body

    }

}

$out_certificat .= html_writer::end_tag('div'); // certificat_card

echo $out_certificat; */
$value = null;
$result = show_certificat($courseid);

if($result){
    $value .= $result;
}

echo $value;

echo html_writer::end_div(); //close outer div
echo $OUTPUT->footer();
