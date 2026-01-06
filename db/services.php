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
 * Services for mooin4 format.
 *
 * @package   format_mooin4
 * @copyright 2023 ISy TH Lübeck <dev.ild@th-luebeck.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'format_mooin4_check_completion_status' => [
        'classname' => 'format_mooin4_external',
        'methodname' => 'check_completion_status',
        'classpath' => 'course/format/mooin4/externallib.php',
        'description' => 'check completion status',
        'type' => 'write',
        'ajax' => true,
    ],
    'format_mooin4_setgrade' => [
        'classname' => 'format_mooin4_external',
        'methodname' => 'setgrade',
        'classpath' => 'course/format/mooin4/externallib.php',
        'description' => 'Set H5P grade',
        'type' => 'write',
        'ajax' => true,
    ],
];

$services = [
    'mooin4_check_completion_status' => [
        'functions' => ['format_mooin4_check_completion_status', 'format_mooin4_setgrade'],
        'restrictedusers' => 0,
        'enabled' => 1,
    ],
];

