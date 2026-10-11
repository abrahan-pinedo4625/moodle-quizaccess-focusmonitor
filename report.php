<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Interpreted report for Focus monitor events.
 *
 * @package    quizaccess_focusmonitor
 * @copyright  2026 Abrahan Pinedo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/tablelib.php');

$cmid = optional_param('cmid', 0, PARAM_INT);
$quizid = optional_param('quizid', 0, PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);
$sort = optional_param('sort', 'risklevel', PARAM_ALPHANUMEXT);
$dir = optional_param('dir', 'desc', PARAM_ALPHA);

if ($cmid) {
    $cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
    $quiz = $DB->get_record('quiz', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($quizid) {
    $quiz = $DB->get_record('quiz', array('id' => $quizid), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('quiz', $quiz->id, $quiz->course, false, MUST_EXIST);
} else {
    throw new moodle_exception('missingcmidorquizid', 'quizaccess_focusmonitor');
}

$course = $DB->get_record('course', array('id' => $quiz->course), '*', MUST_EXIST);
require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/quiz:viewreports', $context);

$url = new moodle_url('/mod/quiz/accessrule/focusmonitor/report.php', array('cmid' => $cm->id));
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('reporttitle', 'quizaccess_focusmonitor'));
$PAGE->set_heading(format_string($course->fullname));

/**
 * Decode eventdata safely.
 *
 * @param string $eventdata Raw JSON string.
 * @return array
 */
function quizaccess_focusmonitor_decode_eventdata($eventdata) {
    $data = json_decode($eventdata, true);
    return is_array($data) ? $data : array();
}

/**
 * Get the event time, preferring the browser-side timestamp when available.
 *
 * @param stdClass $record Log record.
 * @return int
 */
function quizaccess_focusmonitor_event_time($record) {
    if (isset($record->_fmdata['servertime']) && is_numeric($record->_fmdata['servertime'])) {
        return (int)$record->_fmdata['servertime'];
    }
    if (isset($record->_fmdata['time']) && is_numeric($record->_fmdata['time'])) {
        return (int)$record->_fmdata['time'] + (isset($record->_fmclockoffset) ? $record->_fmclockoffset : 0);
    }
    return (int)$record->timecreated;
}

/**
 * Decide whether an event type is a normal Moodle/navigation event.
 *
 * @param string $eventtype Event type.
 * @return bool
 */
function quizaccess_focusmonitor_is_navigation_event($eventtype) {
    return in_array($eventtype, array('page_loaded', 'beforeunload', 'pagehide', 'pageshow'), true);
}

/**
 * Decide whether an event type is a focus-loss signal.
 *
 * @param string $eventtype Event type.
 * @return bool
 */
function quizaccess_focusmonitor_is_focus_loss_event($eventtype) {
    return in_array($eventtype, array('visibility_hidden', 'window_blur', 'fullscreen_exit'), true);
}

/**
 * Decide whether an event type is a focus-return signal.
 *
 * @param string $eventtype Event type.
 * @return bool
 */
function quizaccess_focusmonitor_is_focus_return_event($eventtype) {
    return in_array($eventtype, array('visibility_visible', 'window_focus', 'fullscreen_enter'), true);
}

/**
 * Decide whether an event type is a strong interaction signal.
 *
 * @param string $eventtype Event type.
 * @return bool
 */
function quizaccess_focusmonitor_is_strong_event($eventtype) {
    return in_array($eventtype, array('copy', 'paste', 'cut', 'print_attempt', 'screenshot_key'), true);
}

/**
 * Read context from new events, with only verifiable page/time fallback for old logs.
 * Question numbers are never guessed from page indexes or slot numbers.
 *
 * @param stdClass $record Log record.
 * @return array
 */
function quizaccess_focusmonitor_event_context($record) {
    $data = $record->_fmdata;
    $context = isset($data['context']) && is_array($data['context']) ? $data['context'] : array();
    $page = isset($context['pageindex']) && is_numeric($context['pageindex']) ? (int)$context['pageindex'] : null;
    if ($page === null && isset($data['path']) && basename($data['path']) === 'attempt.php') {
        if (isset($data['page']) && $data['page'] !== '' && is_numeric($data['page'])) {
            $page = (int)$data['page'];
        } else if (!empty($data['query'])) {
            $params = array();
            parse_str(ltrim($data['query'], '?'), $params);
            $page = isset($params['page']) && is_numeric($params['page']) ? (int)$params['page'] : 0;
        }
    }
    $question = isset($context['question']) && is_array($context['question']) ? $context['question'] : array();
    $elapsed = isset($context['elapsed']) && is_numeric($context['elapsed']) ? max(0, (int)$context['elapsed']) : null;
    if ($elapsed === null && !empty($record->attempttimestart)) {
        // Legacy client clocks may differ; use the server receipt time for this fallback.
        $elapsed = max(0, (int)$record->timecreated - (int)$record->attempttimestart);
    }
    return array(
        'page' => $page === null ? null : $page + 1,
        'question' => isset($question['number']) && is_scalar($question['number']) ? (string)$question['number'] : '',
        'slot' => isset($question['slot']) && is_numeric($question['slot']) ? (int)$question['slot'] : null,
        'source' => isset($context['contextsource']) && is_string($context['contextsource']) ? $context['contextsource'] : 'unknown',
        'elapsed' => $elapsed,
    );
}

/** Return a translated, bounded description of the question attribution method. */
function quizaccess_focusmonitor_context_source($source) {
    $allowed = array('only_question', 'last_interaction', 'viewport', 'unknown');
    if (!in_array($source, $allowed, true)) {
        $source = 'unknown';
    }
    return get_string('context_' . $source, 'quizaccess_focusmonitor');
}

/** Build an episode with the question context captured at departure. */
function quizaccess_focusmonitor_episode($record, $end, $confirmed) {
    return array(
        'start' => $record->_fmtime,
        'end' => $end,
        'duration' => $confirmed ? max(0, $end - $record->_fmtime) : null,
        'confirmed' => $confirmed,
        'reconstructed' => false,
        'documentid' => isset($record->_fmdata['context']['documentid']) ? $record->_fmdata['context']['documentid'] : '',
        'attemptid' => (int)$record->attemptid,
        'context' => quizaccess_focusmonitor_event_context($record),
    );
}

/** State clearly whether both raw endpoints or only a client warning support an episode. */
function quizaccess_focusmonitor_episode_status($episode) {
    if (!empty($episode['reconstructed'])) {
        return get_string('episodereconstructed', 'quizaccess_focusmonitor');
    }
    return get_string($episode['confirmed'] ? 'episodeconfirmed' : 'episodeunconfirmed', 'quizaccess_focusmonitor');
}

/** Check known document identities; legacy logs can only compare path and page. */
function quizaccess_focusmonitor_same_document($a, $b) {
    $ad = $a->_fmdata;
    $bd = $b->_fmdata;
    if (!empty($ad['context']['documentid']) && !empty($bd['context']['documentid'])) {
        return $ad['context']['documentid'] === $bd['context']['documentid'];
    }
    if (isset($ad['path'], $bd['path']) && $ad['path'] !== $bd['path']) {
        return false;
    }
    $ac = quizaccess_focusmonitor_event_context($a);
    $bc = quizaccess_focusmonitor_event_context($b);
    return $ac['page'] === null || $bc['page'] === null || $ac['page'] === $bc['page'];
}

/** Return plain context cells for HTML tables and CSV exports. */
function quizaccess_focusmonitor_context_cells($context, $csv = false) {
    return array(
        $context['page'] === null ? '-' : $context['page'],
        $context['question'] === '' ? '-' : $context['question'],
        $context['slot'] === null ? '-' : $context['slot'],
        quizaccess_focusmonitor_context_source($context['source']),
        $context['elapsed'] === null ? '-' : ($csv ? $context['elapsed'] :
            quizaccess_focusmonitor_format_duration($context['elapsed'])),
    );
}

/** Return context column headings, with explicit units in CSV. */
function quizaccess_focusmonitor_context_headers($csv = false) {
    return array(
        get_string('eventpage', 'quizaccess_focusmonitor'),
        get_string('eventquestion', 'quizaccess_focusmonitor'),
        get_string('eventslot', 'quizaccess_focusmonitor'),
        get_string('contextsource', 'quizaccess_focusmonitor'),
        get_string($csv ? 'elapsedseconds' : 'elapsedtime', 'quizaccess_focusmonitor'),
    );
}

/**
 * Decide whether an event type is a warning generated by the monitor itself.
 *
 * @param string $eventtype Event type.
 * @return bool
 */
function quizaccess_focusmonitor_is_warning_event($eventtype) {
    return in_array($eventtype, array('focus_warning', 'warning_limit_reached'), true);
}

/**
 * Check if an event happened near a normal page navigation.
 *
 * @param int $time Event timestamp.
 * @param array $navigationtimes Timestamps of page navigation events.
 * @param int $window Time window in seconds.
 * @return bool
 */
function quizaccess_focusmonitor_near_navigation($time, $navigationtimes, $window = 3) {
    foreach ($navigationtimes as $navtime) {
        if (abs((int)$time - (int)$navtime) <= $window) {
            return true;
        }
    }
    return false;
}

/**
 * Format duration in seconds.
 *
 * @param int $seconds Duration in seconds.
 * @return string
 */
function quizaccess_focusmonitor_format_duration($seconds) {
    $seconds = max(0, (int)$seconds);
    $minutes = floor($seconds / 60);
    $remaining = $seconds % 60;
    if ($minutes > 0) {
        return $minutes . ' min ' . $remaining . ' s';
    }
    return $remaining . ' s';
}

/** Display event times to the second in the viewer's Moodle timezone. */
function quizaccess_focusmonitor_datetime($time) {
    return userdate($time, '%Y-%m-%d %H:%M:%S');
}

/**
 * Format percentage.
 *
 * @param float $percent Percentage value.
 * @return string
 */
function quizaccess_focusmonitor_format_percent($percent) {
    $percent = max(0, (float)$percent);
    if ($percent >= 10) {
        return round($percent, 0) . '%';
    }
    return round($percent, 1) . '%';
}

/**
 * Format final quiz grade.
 *
 * @param mixed $grade Final grade value.
 * @param stdClass $quiz Quiz object.
 * @return string
 */

/**
 * Format sort-safe decimal percentage.
 *
 * @param float $percent Percentage value.
 * @return string
 */
function quizaccess_focusmonitor_csv_percent($percent) {
    return (string)round(max(0, (float)$percent), 2);
}

/**
 * Send CSV content and finish the request.
 *
 * @param string $filename CSV filename.
 * @param array $headers Header row.
 * @param array $rows Data rows.
 */
function quizaccess_focusmonitor_send_csv($filename, $headers, $rows) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    $out = fopen('php://output', 'w');
    // UTF-8 BOM improves compatibility with Excel in Spanish-language Windows installs.
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    die();
}

function quizaccess_focusmonitor_format_grade($grade, $quiz) {
    if ($grade === null || $grade === '') {
        return '-';
    }
    $maxgrade = isset($quiz->grade) ? (float)$quiz->grade : 0;
    if ($maxgrade > 0) {
        return format_float((float)$grade, 2) . ' / ' . format_float($maxgrade, 2);
    }
    return format_float((float)$grade, 2);
}


/**
 * Apply advisory risk rules to an analysis summary.
 *
 * @param array $summary Analysis summary, passed by reference.
 * @return void
 */
function quizaccess_focusmonitor_apply_risk(&$summary) {
    // Cap impossible percentages caused by browser restore quirks or old cross-attempt data.
    if (!empty($summary['attemptduration']) && $summary['focustime'] > $summary['attemptduration']) {
        $summary['focustime'] = $summary['attemptduration'];
    }
    if (!empty($summary['attemptduration'])) {
        $summary['focuspercent'] = ($summary['focustime'] / $summary['attemptduration']) * 100;
    } else {
        $summary['focuspercent'] = 0;
    }

    // Risk is advisory. It is designed to guide teacher review, not to prove misconduct.
    $criticalstrong = $summary['copycount'] + $summary['pastecount'] + $summary['cutcount'] +
        $summary['printcount'] + $summary['screenshotcount'];
    if ($criticalstrong > 0 || $summary['strongcount'] >= 2 || $summary['focustime'] >= 45 ||
            $summary['focusepisodes'] >= 4 || $summary['focuspercent'] >= 5 ||
            $summary['warningcount'] >= 3 || $summary['warninglimitcount'] > 0) {
        $summary['risklevel'] = 'high';
    } else if ($summary['contextmenucount'] > 0 || $summary['focustime'] >= 15 ||
            $summary['focusepisodes'] >= 2 || $summary['focuspercent'] >= 2 || $summary['warningcount'] >= 1) {
        $summary['risklevel'] = 'medium';
    } else {
        $summary['risklevel'] = 'low';
    }
}

/**
 * Aggregate several attempt summaries into one student-level summary.
 * This prevents a focus-loss event from one attempt being accidentally paired
 * with a focus-return event from a different attempt.
 *
 * @param array $summaries List of analysis summaries.
 * @return array Aggregated summary.
 */
function quizaccess_focusmonitor_aggregate_summaries($summaries) {
    $summary = array(
        'eventcount' => 0,
        'navigationcount' => 0,
        'focusrawcount' => 0,
        'focuslosscount' => 0,
        'focusnavigationnoise' => 0,
        'focusepisodes' => 0,
        'focustime' => 0,
        'attemptduration' => 0,
        'focuspercent' => 0,
        'strongcount' => 0,
        'copycount' => 0,
        'pastecount' => 0,
        'cutcount' => 0,
        'contextmenucount' => 0,
        'printcount' => 0,
        'screenshotcount' => 0,
        'warningcount' => 0,
        'warninglimitcount' => 0,
        'firsttime' => 0,
        'lasttime' => 0,
        'risklevel' => 'low',
        'episodes' => array(),
    );

    $numeric = array('eventcount', 'navigationcount', 'focusrawcount', 'focuslosscount',
        'focusnavigationnoise', 'focusepisodes', 'focustime', 'attemptduration', 'strongcount',
        'copycount', 'pastecount', 'cutcount', 'contextmenucount', 'printcount', 'screenshotcount',
        'warninglimitcount');

    foreach ($summaries as $part) {
        foreach ($numeric as $key) {
            $summary[$key] += isset($part[$key]) ? (int)$part[$key] : 0;
        }
        // Warning count is the highest visible warning reached in any attempt,
        // not a raw sum of repeated warning events across old test attempts.
        if (isset($part['warningcount']) && (int)$part['warningcount'] > $summary['warningcount']) {
            $summary['warningcount'] = (int)$part['warningcount'];
        }
        if (!empty($part['firsttime']) && (empty($summary['firsttime']) || $part['firsttime'] < $summary['firsttime'])) {
            $summary['firsttime'] = (int)$part['firsttime'];
        }
        if (!empty($part['lasttime']) && $part['lasttime'] > $summary['lasttime']) {
            $summary['lasttime'] = (int)$part['lasttime'];
        }
        if (!empty($part['episodes'])) {
            $summary['episodes'] = array_merge($summary['episodes'], $part['episodes']);
        }
    }

    quizaccess_focusmonitor_apply_risk($summary);
    return $summary;
}

/**
 * Analyse a set of records belonging to one student or one attempt.
 *
 * @param array $grouprecords Records sorted chronologically.
 * @return array Analysis summary.
 */
function quizaccess_focusmonitor_analyse_records($grouprecords) {
    $navigationtimes = array();
    $departures = array();
    foreach ($grouprecords as $record) {
        if (quizaccess_focusmonitor_is_navigation_event($record->eventtype)) {
            $navigationtimes[] = $record->_fmtime;
        }
        if ($record->eventtype === 'beforeunload' || ($record->eventtype === 'pagehide' &&
                isset($record->_fmdata['visibility']) && $record->_fmdata['visibility'] === 'visible')) {
            $departures[] = $record;
        }
    }

    $summary = array(
        'eventcount' => 0,
        'navigationcount' => 0,
        'focusrawcount' => 0,
        'focuslosscount' => 0,
        'focusnavigationnoise' => 0,
        'focusepisodes' => 0,
        'focustime' => 0,
        'attemptduration' => 0,
        'focuspercent' => 0,
        'strongcount' => 0,
        'copycount' => 0,
        'pastecount' => 0,
        'cutcount' => 0,
        'contextmenucount' => 0,
        'printcount' => 0,
        'screenshotcount' => 0,
        'warningcount' => 0,
        'warninglimitcount' => 0,
        'firsttime' => 0,
        'lasttime' => 0,
        'risklevel' => 'low',
        'episodes' => array(),
    );

    $outoffocus = false;
    $focusstart = 0;
    $focusrecord = null;
    $attemptwindows = array();

    foreach ($grouprecords as $record) {
        if (!empty($record->attemptid) && !empty($record->attempttimestart)) {
            $aid = (int)$record->attemptid;
            if (!isset($attemptwindows[$aid])) {
                $attemptwindows[$aid] = array('start' => (int)$record->attempttimestart, 'end' => 0);
            }
            $candidateend = !empty($record->attempttimefinish) ? (int)$record->attempttimefinish : (int)$record->_fmtime;
            if ($candidateend > $attemptwindows[$aid]['end']) {
                $attemptwindows[$aid]['end'] = $candidateend;
            }
        }
        $summary['eventcount']++;
        $summary['lasttime'] = max($summary['lasttime'], (int)$record->timecreated);
        if (empty($summary['firsttime'])) {
            $summary['firsttime'] = (int)$record->_fmtime;
        }

        $type = $record->eventtype;
        $time = $record->_fmtime;
        $nearnav = quizaccess_focusmonitor_near_navigation($time, $navigationtimes, 3);
        // New logs identify document navigation explicitly. For RC1 context logs,
        // only a departure on the same document can suppress a loss, not a page load elsewhere.
        if (isset($record->_fmdata['navigating'])) {
            $nearnav = !empty($record->_fmdata['navigating']);
        } else if (!empty($record->_fmdata['context']['documentid'])) {
            $nearnav = false;
            foreach ($departures as $departure) {
                if (quizaccess_focusmonitor_same_document($departure, $record) && abs($departure->_fmtime - $time) <= 3) {
                    $nearnav = true;
                    break;
                }
            }
        }
        $record->_fmnearnavigation = $nearnav;

        if (quizaccess_focusmonitor_is_navigation_event($type)) {
            $summary['navigationcount']++;
            // A return on another page does not confirm how long the previous page lost focus.
            // Visible pagehide also covers mobile bfcache navigation without beforeunload.
            $visiblehide = $type === 'pagehide' && isset($record->_fmdata['visibility']) &&
                $record->_fmdata['visibility'] === 'visible';
            if ($outoffocus && ($type === 'page_loaded' ||
                    (($type === 'beforeunload' || $visiblehide) && quizaccess_focusmonitor_same_document($focusrecord, $record)))) {
                $summary['episodes'][] = quizaccess_focusmonitor_episode($focusrecord, $time, false);
                $outoffocus = false;
                $focusstart = 0;
                $focusrecord = null;
            }
            continue;
        }

        if (quizaccess_focusmonitor_is_focus_loss_event($type) || quizaccess_focusmonitor_is_focus_return_event($type)) {
            $summary['focusrawcount']++;
        }

        if (quizaccess_focusmonitor_is_focus_loss_event($type)) {
            if ($nearnav) {
                $summary['focusnavigationnoise']++;
                continue;
            }
            $summary['focuslosscount']++;
            if ($outoffocus && !quizaccess_focusmonitor_same_document($focusrecord, $record)) {
                $summary['episodes'][] = quizaccess_focusmonitor_episode($focusrecord, $time, false);
                $outoffocus = false;
            }
            if (!$outoffocus) {
                $outoffocus = true;
                $focusstart = $time;
                $focusrecord = $record;
            }
            continue;
        }

        if (quizaccess_focusmonitor_is_focus_return_event($type)) {
            if ($outoffocus) {
                if (isset($record->_fmdata['visibility']) && $record->_fmdata['visibility'] === 'hidden') {
                    continue;
                }
                if (!quizaccess_focusmonitor_same_document($focusrecord, $record)) {
                    $summary['episodes'][] = quizaccess_focusmonitor_episode($focusrecord, $time, false);
                    $outoffocus = false;
                    $focusstart = 0;
                    $focusrecord = null;
                    continue;
                }
                $duration = max(0, $time - $focusstart);
                // Ignore very short browser flickers. They are usually UI noise.
                if ($duration >= 2) {
                    $summary['focusepisodes']++;
                    $summary['focustime'] += $duration;
                    $summary['episodes'][] = quizaccess_focusmonitor_episode($focusrecord, $time, true);
                }
                $outoffocus = false;
                $focusstart = 0;
                $focusrecord = null;
            }
            continue;
        }

        if ($type === 'contextmenu') {
            $summary['contextmenucount']++;
        }
        if (quizaccess_focusmonitor_is_strong_event($type)) {
            $summary['strongcount']++;
            if ($type === 'copy') {
                $summary['copycount']++;
            } else if ($type === 'paste') {
                $summary['pastecount']++;
            } else if ($type === 'cut') {
                $summary['cutcount']++;
            } else if ($type === 'print_attempt') {
                $summary['printcount']++;
            } else if ($type === 'screenshot_key') {
                $summary['screenshotcount']++;
            }
        }

        if (quizaccess_focusmonitor_is_warning_event($type)) {
            if ($type === 'focus_warning') {
                $warningnumber = 0;
                if (isset($record->_fmdata['warning']) && is_numeric($record->_fmdata['warning'])) {
                    $warningnumber = (int)$record->_fmdata['warning'];
                } else {
                    $warningnumber = $summary['warningcount'] + 1;
                }
                if (isset($record->_fmdata['maxwarnings']) && is_numeric($record->_fmdata['maxwarnings'])) {
                    $warningnumber = min($warningnumber, (int)$record->_fmdata['maxwarnings']);
                }
                if ($warningnumber > $summary['warningcount']) {
                    $summary['warningcount'] = $warningnumber;
                }
            } else if ($type === 'warning_limit_reached') {
                $summary['warninglimitcount']++;
                if (isset($record->_fmdata['maxwarnings']) && is_numeric($record->_fmdata['maxwarnings']) &&
                        (int)$record->_fmdata['maxwarnings'] > $summary['warningcount']) {
                    $summary['warningcount'] = (int)$record->_fmdata['maxwarnings'];
                }
            }
        }
    }

    if ($outoffocus && !empty($grouprecords)) {
        $last = end($grouprecords);
        $summary['episodes'][] = quizaccess_focusmonitor_episode($focusrecord, $last->_fmtime, false);
    }

    // A warning can arrive even when the backgrounded browser failed to deliver the loss event.
    // Recover those episodes once, with explicit attribution; reject cross-page/navigation warnings.
    foreach ($grouprecords as $record) {
        $data = $record->_fmdata;
        if ($record->eventtype !== 'focus_warning' || empty($data['context']['documentid']) ||
                !isset($data['duration']) || !is_numeric($data['duration']) || (int)$data['duration'] < 2 ||
                empty($data['source']) || $data['source'] === 'page_loaded_return') {
            continue;
        }
        $duration = (int)$data['duration'];
        $start = $record->_fmtime - $duration;
        $neardeparture = false;
        foreach ($departures as $departure) {
            if (quizaccess_focusmonitor_same_document($departure, $record) && abs($departure->_fmtime - $start) <= 3) {
                $neardeparture = true;
                break;
            }
        }
        if ($neardeparture || (!empty($record->attempttimestart) && $start < $record->attempttimestart) ||
                (!empty($record->attempttimefinish) && $record->_fmtime > $record->attempttimefinish)) {
            continue;
        }
        $exists = false;
        $replaceindex = null;
        foreach ($summary['episodes'] as $index => $episode) {
            if (!$episode['confirmed'] && $episode['documentid'] === $data['context']['documentid'] &&
                    abs($episode['start'] - $start) <= 2) {
                $replaceindex = $index;
            }
            if ($episode['confirmed'] && $episode['documentid'] === $data['context']['documentid'] &&
                    abs($episode['start'] - $start) <= 2 && abs($episode['end'] - $record->_fmtime) <= 2) {
                $exists = true;
                break;
            }
        }
        if ($exists) {
            continue;
        }
        $episode = quizaccess_focusmonitor_episode($record, $record->_fmtime, true);
        $episode['start'] = $start;
        $episode['duration'] = $duration;
        $episode['reconstructed'] = true;
        if ($replaceindex === null) {
            $summary['episodes'][] = $episode;
        } else {
            $summary['episodes'][$replaceindex] = $episode;
        }
        $summary['focusepisodes']++;
        $summary['focustime'] += $duration;
    }

    if (!empty($attemptwindows)) {
        $duration = 0;
        foreach ($attemptwindows as $window) {
            if (!empty($window['start']) && !empty($window['end']) && $window['end'] >= $window['start']) {
                $duration += ($window['end'] - $window['start']);
            }
        }
        $summary['attemptduration'] = max(0, (int)$duration);
    } else if (!empty($grouprecords)) {
        $last = end($grouprecords);
        $summary['attemptduration'] = max(0, (int)$last->_fmtime - (int)$summary['firsttime']);
    }
    quizaccess_focusmonitor_apply_risk($summary);

    return $summary;
}

/**
 * Human-readable event category for the detailed table.
 *
 * @param stdClass $record Log record.
 * @return string
 */
function quizaccess_focusmonitor_event_category($record) {
    if ($record->eventtype === 'contextmenu') {
        return get_string('categoryinteraction', 'quizaccess_focusmonitor');
    }
    if (quizaccess_focusmonitor_is_strong_event($record->eventtype)) {
        return get_string('categorystrong', 'quizaccess_focusmonitor');
    }
    if (quizaccess_focusmonitor_is_navigation_event($record->eventtype)) {
        return get_string('categorynavigation', 'quizaccess_focusmonitor');
    }
    if (quizaccess_focusmonitor_is_warning_event($record->eventtype)) {
        return get_string('categorywarning', 'quizaccess_focusmonitor');
    }
    if (quizaccess_focusmonitor_is_focus_loss_event($record->eventtype) || quizaccess_focusmonitor_is_focus_return_event($record->eventtype)) {
        if (!empty($record->_fmnearnavigation)) {
            return get_string('categorynavigationnoise', 'quizaccess_focusmonitor');
        }
        return get_string('categoryfocus', 'quizaccess_focusmonitor');
    }
    return get_string('categorytechnical', 'quizaccess_focusmonitor');
}

/**
 * Build a human readable risk badge.
 *
 * @param string $risk Risk key.
 * @return string
 */
function quizaccess_focusmonitor_risk_label($risk) {
    if ($risk === 'high') {
        return get_string('riskhigh', 'quizaccess_focusmonitor');
    }
    if ($risk === 'medium') {
        return get_string('riskmedium', 'quizaccess_focusmonitor');
    }
    return get_string('risklow', 'quizaccess_focusmonitor');
}

/**
 * Build a risk badge with CSS class.
 *
 * @param string $risk Risk key.
 * @return string
 */
function quizaccess_focusmonitor_risk_badge($risk) {
    $label = quizaccess_focusmonitor_risk_label($risk);
    return html_writer::tag('span', s($label), array('class' => 'focusmonitor-risk-badge focusmonitor-risk-' . s($risk)));
}

/**
 * Build a table row with a risk-level class.
 *
 * @param array $cells Cells.
 * @param string $risk Risk key.
 * @return html_table_row
 */

/**
 * Return a sortable heading link for the main summary table.
 *
 * @param string $key Sort key.
 * @param string $label Header label.
 * @param string $currentsort Current sort key.
 * @param string $currentdir Current sort direction.
 * @param moodle_url $baseurl Current page URL.
 * @return string
 */
function quizaccess_focusmonitor_sort_heading($key, $label, $currentsort, $currentdir, moodle_url $baseurl) {
    $nextdir = 'asc';
    $arrow = '';
    if ($currentsort === $key) {
        if ($currentdir === 'asc') {
            $nextdir = 'desc';
            $arrow = ' ▲';
        } else {
            $nextdir = 'asc';
            $arrow = ' ▼';
        }
    }
    $url = new moodle_url($baseurl, array('sort' => $key, 'dir' => $nextdir));
    return html_writer::link($url, s($label) . $arrow, array('class' => 'focusmonitor-sort-link'));
}

/**
 * Build a comparison value for sorting analysed groups.
 *
 * @param array $group Analysed user group.
 * @param string $key Sort key.
 * @return mixed
 */
function quizaccess_focusmonitor_sort_value($group, $key) {
    $user = $group['user'];
    $summary = $group['summary'];
    $riskorder = array('low' => 1, 'medium' => 2, 'high' => 3);
    switch ($key) {
        case 'student':
            return core_text::strtolower(fullname($user));
        case 'email':
            return core_text::strtolower($user->email);
        case 'grade':
            return isset($user->finalgrade) ? (float)$user->finalgrade : -1;
        case 'events':
            return (int)$summary['eventcount'];
        case 'navigation':
            return (int)$summary['navigationcount'];
        case 'episodes':
            return (int)$summary['focusepisodes'];
        case 'focustime':
            return (int)$summary['focustime'];
        case 'duration':
            return (int)$summary['attemptduration'];
        case 'focuspercent':
            return (float)$summary['focuspercent'];
        case 'warnings':
            return (int)$summary['warningcount'];
        case 'copy':
            return (int)$summary['copycount'];
        case 'paste':
            return (int)$summary['pastecount'];
        case 'contextmenu':
            return (int)$summary['contextmenucount'];
        case 'strong':
            return (int)$summary['strongcount'];
        case 'risklevel':
            return isset($riskorder[$summary['risklevel']]) ? $riskorder[$summary['risklevel']] : 0;
        case 'lasttime':
            return (int)$summary['lasttime'];
        default:
            return isset($riskorder[$summary['risklevel']]) ? $riskorder[$summary['risklevel']] : 0;
    }
}

function quizaccess_focusmonitor_table_row($cells, $risk) {
    $row = new html_table_row($cells);
    $row->attributes['class'] = 'focusmonitor-row focusmonitor-risk-row-' . $risk;
    $row->attributes['data-risk'] = $risk;
    $row->attributes['data-risk-label'] = quizaccess_focusmonitor_risk_label($risk);
    $search = array();
    foreach ($cells as $cell) {
        $search[] = html_to_text((string)$cell, 0, false);
    }
    $row->attributes['data-search'] = core_text::strtolower(implode(' ', $search));
    return $row;
}

$records = $DB->get_records_sql("SELECT l.*, u.firstname, u.lastname, u.email, qg.grade AS finalgrade, qa.timestart AS attempttimestart, qa.timefinish AS attempttimefinish
                                  FROM {quizaccess_focusmonitor_log} l
                                  JOIN {user} u ON u.id = l.userid
                             LEFT JOIN {quiz_grades} qg ON qg.quiz = l.quizid AND qg.userid = l.userid
                             LEFT JOIN {quiz_attempts} qa ON qa.id = l.attemptid
                                 WHERE l.quizid = :quizid
                              ORDER BY l.timecreated DESC, l.id DESC",
    array('quizid' => $quiz->id));

$clockoffsets = array();
foreach ($records as $record) {
    $record->_fmdata = quizaccess_focusmonitor_decode_eventdata($record->eventdata);
    $data = $record->_fmdata;
    if ($record->eventtype === 'page_loaded' && !empty($record->attempttimestart) &&
            !empty($data['context']['documentid']) && isset($data['context']['elapsed'], $data['time']) &&
            is_numeric($data['context']['elapsed']) && is_numeric($data['time'])) {
        $clockoffsets[$record->attemptid . ':' . $data['context']['documentid']] =
            (int)$record->attempttimestart + (int)$data['context']['elapsed'] - (int)$data['time'];
    }
}
foreach ($records as $record) {
    if (!empty($record->_fmdata['context']['documentid'])) {
        $key = $record->attemptid . ':' . $record->_fmdata['context']['documentid'];
        if (isset($clockoffsets[$key])) {
            $record->_fmclockoffset = $clockoffsets[$key];
        }
    }
    $record->_fmtime = quizaccess_focusmonitor_event_time($record);
    $record->_fmnearnavigation = false;
}

$chronological = array_values($records);
usort($chronological, function($a, $b) {
    if ($a->_fmtime == $b->_fmtime) {
        return $a->id - $b->id;
    }
    return $a->_fmtime - $b->_fmtime;
});

$byuser = array();
$byattempt = array();
foreach ($chronological as $record) {
    $userkey = (string)$record->userid;
    if (!isset($byuser[$userkey])) {
        $byuser[$userkey] = array('user' => $record, 'records' => array());
    }
    $byuser[$userkey]['records'][] = $record;

    if ((int)$record->attemptid > 0) {
        $attemptkey = $record->userid . ':' . $record->attemptid;
        if (!isset($byattempt[$attemptkey])) {
            $byattempt[$attemptkey] = array('user' => $record, 'attemptid' => $record->attemptid, 'records' => array());
        }
        $byattempt[$attemptkey]['records'][] = $record;
    }
}

foreach ($byattempt as $key => $group) {
    $byattempt[$key]['summary'] = quizaccess_focusmonitor_analyse_records($group['records']);
}
foreach ($byuser as $key => $group) {
    $attemptsummaries = array();
    $zerorecords = array();
    foreach ($group['records'] as $record) {
        if ((int)$record->attemptid > 0) {
            $attemptkey = $record->userid . ':' . $record->attemptid;
            if (isset($byattempt[$attemptkey]['summary'])) {
                $attemptsummaries[$attemptkey] = $byattempt[$attemptkey]['summary'];
            }
        } else {
            $zerorecords[] = $record;
        }
    }
    if (!empty($zerorecords)) {
        $attemptsummaries['attempt0'] = quizaccess_focusmonitor_analyse_records($zerorecords);
    }
    if (!empty($attemptsummaries)) {
        $byuser[$key]['summary'] = quizaccess_focusmonitor_aggregate_summaries(array_values($attemptsummaries));
    } else {
        $byuser[$key]['summary'] = quizaccess_focusmonitor_analyse_records($group['records']);
    }
}

// Recompute event categories after per-group analysis has marked navigation noise.
foreach ($chronological as $record) {
    $record->_fmcategory = quizaccess_focusmonitor_event_category($record);
}

usort($chronological, function($a, $b) {
    if ($a->_fmtime == $b->_fmtime) {
        return $b->id - $a->id;
    }
    return $b->_fmtime - $a->_fmtime;
});

$allowedsorts = array('student', 'email', 'grade', 'events', 'navigation', 'episodes', 'focustime', 'duration', 'focuspercent', 'warnings', 'copy', 'paste', 'contextmenu', 'strong', 'risklevel', 'lasttime');
if (!in_array($sort, $allowedsorts, true)) {
    $sort = 'risklevel';
}
$dir = core_text::strtolower($dir) === 'asc' ? 'asc' : 'desc';

usort($byuser, function($a, $b) use ($sort, $dir) {
    $av = quizaccess_focusmonitor_sort_value($a, $sort);
    $bv = quizaccess_focusmonitor_sort_value($b, $sort);
    if ($av == $bv) {
        $av = core_text::strtolower(fullname($a['user']));
        $bv = core_text::strtolower(fullname($b['user']));
    }
    if ($av == $bv) {
        return 0;
    }
    $result = ($av < $bv) ? -1 : 1;
    return $dir === 'asc' ? $result : -$result;
});

$riskorder = array('high' => 3, 'medium' => 2, 'low' => 1);
usort($byattempt, function($a, $b) use ($riskorder) {
    $as = $a['summary'];
    $bs = $b['summary'];
    if ($as['risklevel'] !== $bs['risklevel']) {
        return $riskorder[$bs['risklevel']] - $riskorder[$as['risklevel']];
    }
    if ($as['lasttime'] == $bs['lasttime']) {
        return 0;
    }
    return $bs['lasttime'] - $as['lasttime'];
});


$episoderows = array();
foreach ($byuser as $group) {
    foreach ($group['summary']['episodes'] as $episode) {
        $episoderows[] = array('user' => $group['user'], 'episode' => $episode);
    }
}
usort($episoderows, function($a, $b) {
    return $b['episode']['start'] - $a['episode']['start'];
});

if ($download === 'episodecsv') {
    $headers = array_merge(array(
        get_string('student', 'quizaccess_focusmonitor'),
        get_string('attemptid', 'quizaccess_focusmonitor'),
        get_string('episodestart', 'quizaccess_focusmonitor'),
        get_string('episodeend', 'quizaccess_focusmonitor'),
        get_string('episodedurationseconds', 'quizaccess_focusmonitor'),
        get_string('episodestatus', 'quizaccess_focusmonitor'),
    ), quizaccess_focusmonitor_context_headers(true));
    $rows = array();
    foreach ($episoderows as $item) {
        $episode = $item['episode'];
        $rows[] = array_merge(array(
            fullname($item['user']),
            $episode['attemptid'],
            quizaccess_focusmonitor_datetime($episode['start']),
            $episode['confirmed'] ? quizaccess_focusmonitor_datetime($episode['end']) : '-',
            $episode['confirmed'] ? $episode['duration'] : '-',
            quizaccess_focusmonitor_episode_status($episode),
        ), quizaccess_focusmonitor_context_cells($episode['context'], true));
    }
    quizaccess_focusmonitor_send_csv('focusmonitor_episodes_quiz_' . $quiz->id . '.csv', $headers, $rows);
}

if ($download === 'summarycsv' || $download === 'eventscsv') {
    if ($download === 'summarycsv') {
        $headers = array(
            get_string('student', 'quizaccess_focusmonitor'),
            get_string('email'),
            get_string('finalgrade', 'quizaccess_focusmonitor'),
            get_string('eventcount', 'quizaccess_focusmonitor'),
            get_string('navigationevents', 'quizaccess_focusmonitor'),
            get_string('focusepisodes', 'quizaccess_focusmonitor'),
            get_string('focustime', 'quizaccess_focusmonitor'),
            get_string('attemptduration', 'quizaccess_focusmonitor'),
            get_string('focuspercent', 'quizaccess_focusmonitor'),
            get_string('warningcount', 'quizaccess_focusmonitor'),
            get_string('copycount', 'quizaccess_focusmonitor'),
            get_string('pastecount', 'quizaccess_focusmonitor'),
            get_string('contextmenucount', 'quizaccess_focusmonitor'),
            get_string('strongevents', 'quizaccess_focusmonitor'),
            get_string('risklevel', 'quizaccess_focusmonitor'),
            get_string('lasttime', 'quizaccess_focusmonitor'),
        );
        $rows = array();
        foreach ($byuser as $group) {
            $row = $group['user'];
            $summary = $group['summary'];
            $rows[] = array(
                fullname($row),
                $row->email,
                quizaccess_focusmonitor_format_grade(isset($row->finalgrade) ? $row->finalgrade : null, $quiz),
                (int)$summary['eventcount'],
                (int)$summary['navigationcount'],
                (int)$summary['focusepisodes'],
                (int)$summary['focustime'],
                (int)$summary['attemptduration'],
                quizaccess_focusmonitor_csv_percent($summary['focuspercent']),
                (int)$summary['warningcount'],
                (int)$summary['copycount'],
                (int)$summary['pastecount'],
                (int)$summary['contextmenucount'],
                (int)$summary['strongcount'],
                quizaccess_focusmonitor_risk_label($summary['risklevel']),
                userdate($summary['lasttime']),
            );
        }
        quizaccess_focusmonitor_send_csv('focusmonitor_summary_quiz_' . $quiz->id . '.csv', $headers, $rows);
    }

    $headers = array(
        get_string('time'),
        get_string('student', 'quizaccess_focusmonitor'),
        get_string('email'),
        get_string('attemptid', 'quizaccess_focusmonitor'),
        get_string('eventtype', 'quizaccess_focusmonitor'),
        get_string('eventcategory', 'quizaccess_focusmonitor'),
        get_string('eventdata', 'quizaccess_focusmonitor'),
        get_string('ipaddress', 'quizaccess_focusmonitor'),
    );
    $headers = array_merge($headers, quizaccess_focusmonitor_context_headers(true));
    $rows = array();
    foreach ($chronological as $record) {
        $user = new stdClass();
        $user->firstname = $record->firstname;
        $user->lastname = $record->lastname;
        $rows[] = array_merge(array(
            quizaccess_focusmonitor_datetime($record->_fmtime),
            fullname($user),
            $record->email,
            (int)$record->attemptid,
            $record->eventtype,
            $record->_fmcategory,
            $record->eventdata,
            $record->ipaddress,
        ), quizaccess_focusmonitor_context_cells(quizaccess_focusmonitor_event_context($record), true));
    }
    quizaccess_focusmonitor_send_csv('focusmonitor_events_quiz_' . $quiz->id . '.csv', $headers, $rows);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reporttitle', 'quizaccess_focusmonitor') . ': ' . format_string($quiz->name));

echo html_writer::tag('p', get_string('reportintro', 'quizaccess_focusmonitor'));
echo html_writer::tag('p', get_string('reportinterpretationnote', 'quizaccess_focusmonitor'));

if (empty($records)) {
    echo $OUTPUT->notification(get_string('norecords', 'quizaccess_focusmonitor'), 'notifymessage');
    echo $OUTPUT->footer();
    die();
}

$style = '<style>
.focusmonitor-exportlinks{margin:12px 0 16px 0;padding:10px;border:1px solid #ddd;background:#f8f9fa;border-radius:4px;}
.focusmonitor-exportlinks a{margin-right:14px;font-weight:bold;}
.focusmonitor-sort-link{font-weight:bold;text-decoration:none;}
.focusmonitor-risk-badge{display:inline-block;padding:2px 8px;border-radius:10px;font-weight:bold;}
.focusmonitor-risk-low{background:#d4edda;color:#155724;}
.focusmonitor-risk-medium{background:#fff3cd;color:#856404;}
.focusmonitor-risk-high{background:#f8d7da;color:#721c24;}
.focusmonitor-risk-row-low td{background:#f6fff8;}
.focusmonitor-risk-row-medium td{background:#fffdf3;}
.focusmonitor-risk-row-high td{background:#fff6f6;}
.focusmonitor-details{margin-top:16px;margin-bottom:16px;}
.focusmonitor-details summary{cursor:pointer;font-weight:bold;}
.focusmonitor-muted{color:#666;font-size:.92em;}
</style>';
echo $style;

$summarycsvurl = new moodle_url($url, array('download' => 'summarycsv', 'sort' => $sort, 'dir' => $dir));
$eventscsvurl = new moodle_url($url, array('download' => 'eventscsv', 'sort' => $sort, 'dir' => $dir));
$episodecsvurl = new moodle_url($url, array('download' => 'episodecsv'));
$exporthtml = html_writer::start_tag('div', array('class' => 'focusmonitor-exportlinks'));
$exporthtml .= html_writer::link($summarycsvurl, get_string('exportsummarycsv', 'quizaccess_focusmonitor'));
$exporthtml .= html_writer::link($eventscsvurl, get_string('exporteventscsv', 'quizaccess_focusmonitor'));
$exporthtml .= html_writer::link($episodecsvurl, get_string('exportepisodecsv', 'quizaccess_focusmonitor'));
$exporthtml .= html_writer::tag('span', get_string('sortinstruction', 'quizaccess_focusmonitor'), array('class' => 'focusmonitor-muted'));
$exporthtml .= html_writer::end_tag('div');

echo $OUTPUT->heading(get_string('summary', 'quizaccess_focusmonitor'), 3);
echo $exporthtml;

$summarytable = new html_table();
$summarytable->attributes['id'] = 'focusmonitor-summary-table';
$summarytable->attributes['class'] = 'generaltable focusmonitor-table';
$summarytable->head = array(
    quizaccess_focusmonitor_sort_heading('student', get_string('student', 'quizaccess_focusmonitor'), $sort, $dir, $url),
    quizaccess_focusmonitor_sort_heading('email', get_string('email'), $sort, $dir, $url),
    quizaccess_focusmonitor_sort_heading('grade', get_string('finalgrade', 'quizaccess_focusmonitor'), $sort, $dir, $url),
    quizaccess_focusmonitor_sort_heading('events', get_string('eventcount', 'quizaccess_focusmonitor'), $sort, $dir, $url),
    quizaccess_focusmonitor_sort_heading('navigation', get_string('navigationevents', 'quizaccess_focusmonitor'), $sort, $dir, $url),
    quizaccess_focusmonitor_sort_heading('episodes', get_string('focusepisodes', 'quizaccess_focusmonitor'), $sort, $dir, $url),
    quizaccess_focusmonitor_sort_heading('focustime', get_string('focustime', 'quizaccess_focusmonitor'), $sort, $dir, $url),
    quizaccess_focusmonitor_sort_heading('duration', get_string('attemptduration', 'quizaccess_focusmonitor'), $sort, $dir, $url),
    quizaccess_focusmonitor_sort_heading('focuspercent', get_string('focuspercent', 'quizaccess_focusmonitor'), $sort, $dir, $url),
    quizaccess_focusmonitor_sort_heading('warnings', get_string('warningcount', 'quizaccess_focusmonitor'), $sort, $dir, $url),
    quizaccess_focusmonitor_sort_heading('copy', get_string('copycount', 'quizaccess_focusmonitor'), $sort, $dir, $url),
    quizaccess_focusmonitor_sort_heading('paste', get_string('pastecount', 'quizaccess_focusmonitor'), $sort, $dir, $url),
    quizaccess_focusmonitor_sort_heading('contextmenu', get_string('contextmenucount', 'quizaccess_focusmonitor'), $sort, $dir, $url),
    quizaccess_focusmonitor_sort_heading('strong', get_string('strongevents', 'quizaccess_focusmonitor'), $sort, $dir, $url),
    quizaccess_focusmonitor_sort_heading('risklevel', get_string('risklevel', 'quizaccess_focusmonitor'), $sort, $dir, $url),
    quizaccess_focusmonitor_sort_heading('lasttime', get_string('lasttime', 'quizaccess_focusmonitor'), $sort, $dir, $url),
);
foreach ($byuser as $group) {
    $row = $group['user'];
    $summary = $group['summary'];
    $cells = array(
        fullname($row),
        s($row->email),
        quizaccess_focusmonitor_format_grade(isset($row->finalgrade) ? $row->finalgrade : null, $quiz),
        (int)$summary['eventcount'],
        (int)$summary['navigationcount'],
        (int)$summary['focusepisodes'],
        quizaccess_focusmonitor_format_duration($summary['focustime']),
        quizaccess_focusmonitor_format_duration($summary['attemptduration']),
        quizaccess_focusmonitor_format_percent($summary['focuspercent']),
        (int)$summary['warningcount'],
        (int)$summary['copycount'],
        (int)$summary['pastecount'],
        (int)$summary['contextmenucount'],
        (int)$summary['strongcount'],
        quizaccess_focusmonitor_risk_badge($summary['risklevel']),
        userdate($summary['lasttime']),
    );
    $summarytable->data[] = quizaccess_focusmonitor_table_row($cells, $summary['risklevel']);
}
echo html_writer::table($summarytable);

echo html_writer::start_tag('details', array('class' => 'focusmonitor-details'));
echo html_writer::tag('summary', get_string('showattemptsummary', 'quizaccess_focusmonitor'));
$attempttable = new html_table();
$attempttable->attributes['class'] = 'generaltable focusmonitor-table';
$attempttable->head = array(
    get_string('student', 'quizaccess_focusmonitor'),
    get_string('attemptid', 'quizaccess_focusmonitor'),
    get_string('finalgrade', 'quizaccess_focusmonitor'),
    get_string('eventcount', 'quizaccess_focusmonitor'),
    get_string('navigationevents', 'quizaccess_focusmonitor'),
    get_string('focusepisodes', 'quizaccess_focusmonitor'),
    get_string('focustime', 'quizaccess_focusmonitor'),
    get_string('attemptduration', 'quizaccess_focusmonitor'),
    get_string('focuspercent', 'quizaccess_focusmonitor'),
    get_string('warningcount', 'quizaccess_focusmonitor'),
    get_string('copycount', 'quizaccess_focusmonitor'),
    get_string('pastecount', 'quizaccess_focusmonitor'),
    get_string('contextmenucount', 'quizaccess_focusmonitor'),
    get_string('risklevel', 'quizaccess_focusmonitor'),
    get_string('lasttime', 'quizaccess_focusmonitor'),
);
foreach ($byattempt as $group) {
    $row = $group['user'];
    $summary = $group['summary'];
    $cells = array(
        fullname($row),
        (int)$group['attemptid'],
        quizaccess_focusmonitor_format_grade(isset($row->finalgrade) ? $row->finalgrade : null, $quiz),
        (int)$summary['eventcount'],
        (int)$summary['navigationcount'],
        (int)$summary['focusepisodes'],
        quizaccess_focusmonitor_format_duration($summary['focustime']),
        quizaccess_focusmonitor_format_duration($summary['attemptduration']),
        quizaccess_focusmonitor_format_percent($summary['focuspercent']),
        (int)$summary['warningcount'],
        (int)$summary['copycount'],
        (int)$summary['pastecount'],
        (int)$summary['contextmenucount'],
        quizaccess_focusmonitor_risk_badge($summary['risklevel']),
        userdate($summary['lasttime']),
    );
    $attempttable->data[] = quizaccess_focusmonitor_table_row($cells, $summary['risklevel']);
}
echo html_writer::table($attempttable);
echo html_writer::end_tag('details');

echo html_writer::start_tag('details', array('class' => 'focusmonitor-details'));
echo html_writer::tag('summary', get_string('showepisodes', 'quizaccess_focusmonitor'));
echo html_writer::tag('p', get_string('episodecontextnote', 'quizaccess_focusmonitor'));
$episodetable = new html_table();
$episodetable->attributes['class'] = 'generaltable focusmonitor-table';
$episodetable->head = array_merge(array(
    get_string('student', 'quizaccess_focusmonitor'),
    get_string('attemptid', 'quizaccess_focusmonitor'),
    get_string('episodestart', 'quizaccess_focusmonitor'),
    get_string('episodeend', 'quizaccess_focusmonitor'),
    get_string('episodeduration', 'quizaccess_focusmonitor'),
    get_string('episodestatus', 'quizaccess_focusmonitor'),
), quizaccess_focusmonitor_context_headers());
foreach ($episoderows as $item) {
    $episode = $item['episode'];
    $cells = array_merge(array(
        fullname($item['user']),
        $episode['attemptid'],
        quizaccess_focusmonitor_datetime($episode['start']),
        $episode['confirmed'] ? quizaccess_focusmonitor_datetime($episode['end']) : '-',
        $episode['confirmed'] ? quizaccess_focusmonitor_format_duration($episode['duration']) : '-',
        quizaccess_focusmonitor_episode_status($episode),
    ), quizaccess_focusmonitor_context_cells($episode['context']));
    $episodetable->data[] = array_map('s', $cells);
}
echo html_writer::table($episodetable);
echo html_writer::end_tag('details');

echo html_writer::start_tag('details', array('class' => 'focusmonitor-details'));
echo html_writer::tag('summary', get_string('showrawevents', 'quizaccess_focusmonitor'));
$table = new html_table();
$table->attributes['class'] = 'generaltable focusmonitor-table';
$table->head = array(
    get_string('time'),
    get_string('student', 'quizaccess_focusmonitor'),
    get_string('attemptid', 'quizaccess_focusmonitor'),
    get_string('eventtype', 'quizaccess_focusmonitor'),
    get_string('eventcategory', 'quizaccess_focusmonitor'),
    get_string('eventdata', 'quizaccess_focusmonitor'),
    get_string('ipaddress', 'quizaccess_focusmonitor'),
);
$table->head = array_merge($table->head, quizaccess_focusmonitor_context_headers());
foreach ($chronological as $record) {
    $user = new stdClass();
    $user->firstname = $record->firstname;
    $user->lastname = $record->lastname;
    $table->data[] = array_merge(array(
        quizaccess_focusmonitor_datetime($record->_fmtime),
        fullname($user),
        (int)$record->attemptid,
        s($record->eventtype),
        s($record->_fmcategory),
        html_writer::tag('small', s($record->eventdata)),
        s($record->ipaddress),
    ), array_map('s', quizaccess_focusmonitor_context_cells(quizaccess_focusmonitor_event_context($record))));
}
echo html_writer::table($table);
echo html_writer::end_tag('details');


echo $OUTPUT->footer();
