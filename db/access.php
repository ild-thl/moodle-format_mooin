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

/* $capabilities = array(
    'moodle/mooin4:aluhatsoff' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'editingteacher' => CAP_ALLOW,
			'manager' => CAP_ALLOW
        )
    )
); */
$capabilities = array(
    /* 'format/mooin4:myaddinstance' => array(
    'captype' => 'write',
    'contextlevel' => CONTEXT_SYSTEM,
    'archetypes' => array(
        'student' => CAP_ALLOW,
        'teacher' => CAP_ALLOW,
        'editingteacher' => CAP_ALLOW,
        'manager' => CAP_ALLOW,
        'user' => CAP_ALLOW
    )
    ),
    'format/mooin4:addinstance' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
            'user' => CAP_ALLOW
        )
    )
    , */
    'format/mooin4:readuserpage' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
            'student' => CAP_ALLOW
        )
    ),
    'format/mooin4:aluhatsoff' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'editingteacher' => CAP_ALLOW,
			'manager' => CAP_ALLOW,
            'student' => CAP_ALLOW
        )
    ),
    'format/mooin4:createchapter' => [
        'riskbitmask' => RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archtypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
            'teacher' => CAP_ALLOW
        ]

    ],
    'format/mooin4:managechapter' => [
        'riskbitmask' => RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archtypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
            'teacher' => CAP_ALLOW
        ]
    ],
    'format/mooin4:managesection' => [
        'riskbitmask' => RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archtypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
            'teacher' => CAP_ALLOW
        ]
    ],
);