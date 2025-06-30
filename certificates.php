<?php
require_once('../../../config.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/gradelib.php');

use format_mooin4\local\utils as utils;

global $USER, $PAGE, $CFG, $DB;

$courseid = optional_param('id', 0, PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);

$PAGE->set_course($course);
$PAGE->set_pagelayout('course');
$PAGE->set_context(context_course::instance($course->id));
$PAGE->set_title("$course->shortname: " . get_string('my_certificate', 'format_mooin4'));
$PAGE->set_heading($course->fullname);
$PAGE->set_url('/course/format/mooin4/certificates.php', ['id' => $course->id]);

echo $OUTPUT->header();

$breadcrumb = utils::subpage_navbar();
$certificates = utils::show_certificat($courseid);

$context = context_course::instance($courseid);
$users = get_enrolled_users($context, '', 0, 'u.id, u.firstname, u.lastname');
$userids = array_keys($users);

$gradesdata = grade_get_course_grades($courseid, $userids);
$grades = $gradesdata->grades;

$ranking = [];
$mygrade = '-';
$myrank = '-';
$avggrade = '-';
$inTop10 = false;
$badgeimage = null;

foreach ($grades as $userid => $gradeobj) {
    if (!isset($users[$userid])) {
        continue;
    }

    $fullname = fullname($users[$userid]);

    if (is_numeric($gradeobj->grade)) {
        $ranking[] = [
            'userid' => $userid,
            'fullname' => $fullname,
            'grade' => round($gradeobj->grade, 2)
        ];
    }
}

if (!empty($ranking)) {
    usort($ranking, fn($a, $b) => $b['grade'] <=> $a['grade']);
    $top10count = max(1, floor(count($ranking) * 0.1));
    $top10 = array_slice($ranking, 0, $top10count);

    foreach ($ranking as $index => $entry) {
        if ($entry['userid'] == $USER->id) {
            $mygrade = $entry['grade'];
            $myrank = $index + 1;
            break;
        }
    }

    foreach ($top10 as $entry) {
        if ($entry['userid'] == $USER->id) {
            $inTop10 = true;
            $badgeimage = 'https://i.ibb.co/3yHZLcFX/Badges.png';
            break;
        }
    }

    $gradesOnly = array_column($ranking, 'grade');
    $avggrade = round(array_sum($gradesOnly) / count($gradesOnly), 2);
} else {
    $top10 = [];
}

$data = [
    'breadcrumb' => $breadcrumb,
    'certificates' => $certificates,
    'top10' => $top10,
    'mygrade' => $mygrade,
    'myrank' => $myrank,
    'avggrade' => $avggrade,
    'inTop10' => $inTop10,
    'badgeimage' => $badgeimage
];

echo $OUTPUT->render_from_template('format_mooin4/local/content/subpages/certificates', $data);
echo $OUTPUT->footer();
