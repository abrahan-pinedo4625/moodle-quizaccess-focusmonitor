<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Version information for the Focus monitor quiz access rule.
 *
 * @package    quizaccess_focusmonitor
 * @copyright  2026 Abrahan Pinedo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'quizaccess_focusmonitor';
$plugin->version   = 2026101001;
$plugin->requires  = 2017051500; // Moodle 3.3.
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '0.6.2-rc2 for Moodle 3.3+';
