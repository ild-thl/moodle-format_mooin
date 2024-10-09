<?php

$functions = array(
    'format_mooin4_check_completion_status' => array(
        'classname' => 'format_mooin4_external',
        'methodname' => 'check_completion_status',
        'classpath' => 'course/format/mooin4/externallib.php',
        'description' => 'check completion status',
        'type' => 'write',
        'ajax' => true
    ),
    'format_mooin4_setgrade' => array(
        'classname' => 'format_mooin4_external',
        'methodname' => 'setgrade',
        'classpath' => 'course/format/mooin4/externallib.php',
        'description' => 'Set H5P grade',
        'type' => 'write',
        'ajax' => true
    ),
);

$services = array(
    'mooin4_check_completion_status' => array(
        'functions' => array('format_mooin4_check_completion_status', 'format_mooin4_setgrade'),
        'restrictedusers' => 0,
        'enabled' => 1,
    )
);
