<?php

$functions = array(
    'format_moointopics_check_completion_status' => array(
        'classname' => 'format_moointopics_external',
        'methodname' => 'check_completion_status',
        'classpath' => 'course/format/moointopics/externallib.php',
        'description' => 'check completion status',
        'type' => 'write',
        'ajax' => true
    ),
    'format_moointopics_setgrade' => array(
        'classname' => 'format_moointopics_external',
        'methodname' => 'setgrade',
        'classpath' => 'course/format/moointopics/externallib.php',
        'description' => 'Set H5P grade',
        'type' => 'write',
        'ajax' => true
    ),
);

// $services = array(
//   'mooin4_check_completion_status' => array(
//       'functions' => array('format_mooin4_check_completion_status'),
//       'restrictedusers' => 0,
//       'enabled' => 1,
//   )
// );