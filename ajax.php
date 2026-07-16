<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AJAX endpoint for Focus monitor events.
 *
 * @package    quizaccess_focusmonitor
 * @copyright  2026 Abrahan Pinedo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');

require_sesskey();

$quizid = required_param('quizid', PARAM_INT);
$cmid = required_param('cmid', PARAM_INT);
$attemptid = optional_param('attemptid', 0, PARAM_INT);
$eventtype = required_param('eventtype', PARAM_ALPHANUMEXT);
$eventdata = optional_param('eventdata', '', PARAM_RAW_TRIMMED);

$cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$quiz = $DB->get_record('quiz', array('id' => $quizid), '*', MUST_EXIST);

require_login($course, false, $cm);

$context = context_module::instance($cm->id);

// Only log if this quiz actually has the rule enabled.
$settings = $DB->get_record('quizaccess_focusmonitor', array('quizid' => $quizid));
if (!$settings || empty($settings->focusmonitorenabled)) {
    header('Content-Type: application/json');
    echo json_encode(array('status' => 'disabled'));
    die();
}

if ($attemptid) {
    $attempt = $DB->get_record('quiz_attempts', array('id' => $attemptid), '*', MUST_EXIST);
    if ((int)$attempt->quiz !== (int)$quizid) {
        throw new moodle_exception('invalidattemptid', 'quiz');
    }
    if ((int)$attempt->userid !== (int)$USER->id && !has_capability('mod/quiz:viewreports', $context)) {
        throw new required_capability_exception($context, 'mod/quiz:viewreports', 'nopermissions', '');
    }
}

$record = new stdClass();
$record->courseid = $course->id;
$record->cmid = $cm->id;
$record->quizid = $quizid;
$record->attemptid = $attemptid;
$record->userid = $USER->id;
$record->eventtype = $eventtype;
$record->eventdata = $eventdata;
$record->ipaddress = getremoteaddr();
$record->useragent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 1024) : '';
$record->timecreated = time();

$DB->insert_record('quizaccess_focusmonitor_log', $record);

header('Content-Type: application/json');
echo json_encode(array('status' => 'ok'));
die();
