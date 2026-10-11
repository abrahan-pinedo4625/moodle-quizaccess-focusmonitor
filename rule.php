<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Implementation of the Focus monitor quiz access rule.
 *
 * @package    quizaccess_focusmonitor
 * @copyright  2026 Abrahan Pinedo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/accessrule/accessrulebase.php');

/**
 * Quiz access rule that logs basic browser focus events during quiz attempts.
 */
class quizaccess_focusmonitor extends quiz_access_rule_base {

    /**
     * Return an instance when the rule is enabled for this quiz.
     *
     * @param quiz $quizobj Quiz object.
     * @param int $timenow Current timestamp.
     * @param bool $canignoretimelimits Whether current user can ignore time limits.
     * @return quizaccess_focusmonitor|null
     */
    public static function make(quiz $quizobj, $timenow, $canignoretimelimits) {
        $quiz = $quizobj->get_quiz();
        if (empty($quiz->focusmonitorenabled)) {
            return null;
        }
        return new self($quizobj, $timenow);
    }

    /**
     * Add settings to the quiz settings form.
     *
     * @param mod_quiz_mod_form $quizform Quiz form.
     * @param MoodleQuickForm $mform QuickForm.
     */
    public static function add_settings_form_fields(mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
        $mform->addElement('header', 'focusmonitorheader', get_string('focusmonitorheader', 'quizaccess_focusmonitor'));

        $mform->addElement('select', 'focusmonitorenabled', get_string('focusmonitorenabled', 'quizaccess_focusmonitor'), array(
            0 => get_string('no'),
            1 => get_string('yes'),
        ));
        $mform->addHelpButton('focusmonitorenabled', 'focusmonitorenabled', 'quizaccess_focusmonitor');
        $mform->setDefault('focusmonitorenabled', 0);

        $mform->addElement('select', 'focusmonitorshowwarnings', get_string('focusmonitorshowwarnings', 'quizaccess_focusmonitor'), array(
            0 => get_string('warningssilent', 'quizaccess_focusmonitor'),
            1 => get_string('warningsvisible', 'quizaccess_focusmonitor'),
        ));
        $mform->addHelpButton('focusmonitorshowwarnings', 'focusmonitorshowwarnings', 'quizaccess_focusmonitor');
        $mform->setDefault('focusmonitorshowwarnings', 1);
        $mform->disabledIf('focusmonitorshowwarnings', 'focusmonitorenabled', 'eq', 0);

        $mform->addElement('text', 'focusmonitorwarningthreshold', get_string('focusmonitorwarningthreshold', 'quizaccess_focusmonitor'));
        $mform->addHelpButton('focusmonitorwarningthreshold', 'focusmonitorwarningthreshold', 'quizaccess_focusmonitor');
        $mform->setType('focusmonitorwarningthreshold', PARAM_INT);
        $mform->setDefault('focusmonitorwarningthreshold', 10);
        $mform->disabledIf('focusmonitorwarningthreshold', 'focusmonitorenabled', 'eq', 0);
        $mform->disabledIf('focusmonitorwarningthreshold', 'focusmonitorshowwarnings', 'eq', 0);

        $mform->addElement('text', 'focusmonitormaxwarnings', get_string('focusmonitormaxwarnings', 'quizaccess_focusmonitor'));
        $mform->addHelpButton('focusmonitormaxwarnings', 'focusmonitormaxwarnings', 'quizaccess_focusmonitor');
        $mform->setType('focusmonitormaxwarnings', PARAM_INT);
        $mform->setDefault('focusmonitormaxwarnings', 3);
        $mform->disabledIf('focusmonitormaxwarnings', 'focusmonitorenabled', 'eq', 0);
        $mform->disabledIf('focusmonitormaxwarnings', 'focusmonitorshowwarnings', 'eq', 0);

        $mform->addElement('select', 'focusmonitoraction', get_string('focusmonitoraction', 'quizaccess_focusmonitor'), array(
            'review' => get_string('actionreviewonly', 'quizaccess_focusmonitor'),
            'summary' => get_string('actionsummary', 'quizaccess_focusmonitor'),
            'block' => get_string('actionblock', 'quizaccess_focusmonitor'),
        ));
        $mform->addHelpButton('focusmonitoraction', 'focusmonitoraction', 'quizaccess_focusmonitor');
        $mform->setDefault('focusmonitoraction', 'review');
        $mform->disabledIf('focusmonitoraction', 'focusmonitorenabled', 'eq', 0);
        $mform->disabledIf('focusmonitoraction', 'focusmonitorshowwarnings', 'eq', 0);
    }

    /**
     * Save settings for a quiz.
     *
     * @param stdClass $quiz Quiz object.
     */
    public static function save_settings($quiz) {
        global $DB;

        $enabled = empty($quiz->focusmonitorenabled) ? 0 : 1;
        $showwarnings = empty($quiz->focusmonitorshowwarnings) ? 0 : 1;
        $warningthreshold = isset($quiz->focusmonitorwarningthreshold) ? (int)$quiz->focusmonitorwarningthreshold : 10;
        $maxwarnings = isset($quiz->focusmonitormaxwarnings) ? (int)$quiz->focusmonitormaxwarnings : 3;
        $actionafterwarnings = isset($quiz->focusmonitoraction) ? clean_param($quiz->focusmonitoraction, PARAM_ALPHA) : 'review';
        if (!in_array($actionafterwarnings, array('review', 'summary', 'block'), true)) {
            $actionafterwarnings = 'review';
        }
        $warningthreshold = max(3, min(300, $warningthreshold));
        $maxwarnings = max(1, min(20, $maxwarnings));

        if (!$enabled) {
            $DB->delete_records('quizaccess_focusmonitor', array('quizid' => $quiz->id));
            return;
        }

        $record = $DB->get_record('quizaccess_focusmonitor', array('quizid' => $quiz->id));
        if ($record) {
            $record->focusmonitorenabled = $enabled;
            $record->focusmonitorshowwarnings = $showwarnings;
            $record->focusmonitorwarningthreshold = $warningthreshold;
            $record->focusmonitormaxwarnings = $maxwarnings;
            $record->focusmonitoraction = $actionafterwarnings;
            $DB->update_record('quizaccess_focusmonitor', $record);
        } else {
            $record = new stdClass();
            $record->quizid = $quiz->id;
            $record->focusmonitorenabled = $enabled;
            $record->focusmonitorshowwarnings = $showwarnings;
            $record->focusmonitorwarningthreshold = $warningthreshold;
            $record->focusmonitormaxwarnings = $maxwarnings;
            $record->focusmonitoraction = $actionafterwarnings;
            $DB->insert_record('quizaccess_focusmonitor', $record);
        }
    }

    /**
     * Delete settings when a quiz is deleted.
     *
     * @param stdClass $quiz Quiz object.
     */
    public static function delete_settings($quiz) {
        global $DB;
        $DB->delete_records('quizaccess_focusmonitor', array('quizid' => $quiz->id));
    }

    /**
     * Load extra settings efficiently with the quiz object.
     *
     * @param int $quizid Quiz id.
     * @return array SQL fragments.
     */
    public static function get_settings_sql($quizid) {
        return array(
            'focusmonitor.focusmonitorenabled, focusmonitor.focusmonitorshowwarnings, focusmonitor.focusmonitorwarningthreshold, focusmonitor.focusmonitormaxwarnings, focusmonitor.focusmonitoraction',
            'LEFT JOIN {quizaccess_focusmonitor} focusmonitor ON focusmonitor.quizid = quiz.id',
            array(),
        );
    }

    /**
     * Display a short description on the quiz view page.
     *
     * @return string
     */
    public function description() {
        global $CFG;

        $output = html_writer::tag('p', get_string('descriptionstudent', 'quizaccess_focusmonitor'));

        $context = null;
        if (method_exists($this->quizobj, 'get_context')) {
            $context = $this->quizobj->get_context();
        }

        if ($context && has_capability('mod/quiz:viewreports', $context)) {
            $cmid = 0;
            if (method_exists($this->quizobj, 'get_cmid')) {
                $cmid = $this->quizobj->get_cmid();
            }
            $quizid = $this->quiz->id;
            $url = new moodle_url('/mod/quiz/accessrule/focusmonitor/report.php', array('cmid' => $cmid, 'quizid' => $quizid));
            $output .= html_writer::tag('p', html_writer::link($url, get_string('viewreport', 'quizaccess_focusmonitor')));
        }

        return $output;
    }

    /**
     * Prevent returning to an attempt after the warning limit has been reached
     * when strict block mode is enabled.
     *
     * @return string|false False when access is allowed; otherwise message shown to student.
     */
    public function prevent_access() {
        global $DB, $USER;

        if (empty($this->quiz->focusmonitoraction) || $this->quiz->focusmonitoraction !== 'block') {
            return false;
        }

        $attemptid = optional_param('attempt', 0, PARAM_INT);
        if (!$attemptid) {
            $attemptid = optional_param('attemptid', 0, PARAM_INT);
        }
        if (!$attemptid) {
            return false;
        }

        $params = array(
            'quizid' => $this->quiz->id,
            'attemptid' => $attemptid,
            'userid' => $USER->id,
            'eventtype' => 'warning_limit_reached',
        );
        if ($DB->record_exists('quizaccess_focusmonitor_log', $params)) {
            return get_string('strictblockmessage', 'quizaccess_focusmonitor');
        }

        return false;
    }

    /**
     * Add JavaScript to the attempt page.
     *
     * @param moodle_page $page Page object.
     */
    public function setup_attempt_page($page) {
        global $CFG, $DB, $USER;

        $quiz = $this->quiz;
        $cmid = 0;
        if (method_exists($this->quizobj, 'get_cmid')) {
            $cmid = $this->quizobj->get_cmid();
        }

        $attemptid = optional_param('attempt', 0, PARAM_INT);
        if (!$attemptid) {
            $attemptid = optional_param('attemptid', 0, PARAM_INT);
        }
        $attemptstart = $DB->get_field('quiz_attempts', 'timestart', array(
            'id' => $attemptid, 'quiz' => $quiz->id, 'userid' => $USER->id,
        ));
        $config = array(
            'attemptstart' => $attemptstart ? (int)$attemptstart : 0,
            'servertime' => time(),
            'ajaxurl' => (new moodle_url('/mod/quiz/accessrule/focusmonitor/ajax.php'))->out(false),
            'quizid' => (int)$quiz->id,
            'cmid' => (int)$cmid,
            'sesskey' => sesskey(),
            'showwarnings' => empty($quiz->focusmonitorshowwarnings) ? 0 : 1,
            'warningthreshold' => empty($quiz->focusmonitorwarningthreshold) ? 10 : (int)$quiz->focusmonitorwarningthreshold,
            'maxwarnings' => empty($quiz->focusmonitormaxwarnings) ? 3 : (int)$quiz->focusmonitormaxwarnings,
            'actionafterwarnings' => empty($quiz->focusmonitoraction) ? 'review' : clean_param($quiz->focusmonitoraction, PARAM_ALPHA),
            'summaryurl' => (new moodle_url('/mod/quiz/summary.php'))->out(false),
            'strings' => array(
                'notice' => get_string('studentnotice', 'quizaccess_focusmonitor'),
                'returnwarning' => get_string('returnwarning', 'quizaccess_focusmonitor'),
                'warninglimitreached' => get_string('warninglimitreached', 'quizaccess_focusmonitor'),
                'redirecting' => get_string('strictmodereredirecting', 'quizaccess_focusmonitor'),
                'blockredirecting' => get_string('strictmodeblockredirecting', 'quizaccess_focusmonitor'),
            ),
        );

        $json = json_encode($config);
        $js = <<<JS
(function() {
    if (window.__quizaccessFocusMonitorLoaded) {
        return;
    }
    window.__quizaccessFocusMonitorLoaded = true;

    var cfg = $json;
    var lastSent = {};
    var initialClientTime = nowSeconds();
    var documentId = String(new Date().getTime()) + '_' + Math.random().toString(36).slice(2);
    var lastQuestion = null;
    var navigating = false;

    function questionElement(target) {
        while (target && target !== document) {
            if (target.nodeType === 1 && /(^|\\s)que(\\s|$)/.test(target.className)) {
                return target;
            }
            target = target.parentNode;
        }
        return null;
    }

    function questionInfo(el) {
        if (!el) {
            return null;
        }
        // Moodle 3.3 uses q<slot>; newer renderers use question-<usage>-<slot>.
        var match = /^(?:q|question-\\d+-)(\\d+)$/.exec(el.id || '');
        var number = el.querySelector('.info .qno');
        return {
            slot: match ? parseInt(match[1], 10) : null,
            number: number ? (number.textContent || '').trim().slice(0, 40) : ''
        };
    }

    function captureQuestion(event) {
        var el = questionElement(event.target);
        if (el) {
            lastQuestion = el;
        }
    }

    function eventContext() {
        var elements = document.querySelectorAll('.que');
        var questions = [];
        var candidate = null;
        var bestVisibleHeight = 0;
        var viewportHeight = window.innerHeight || document.documentElement.clientHeight;
        for (var i = 0; i < elements.length; i++) {
            questions.push(questionInfo(elements[i]));
            var rect = elements[i].getBoundingClientRect();
            var height = Math.max(0, Math.min(rect.bottom, viewportHeight) - Math.max(rect.top, 0));
            if (height > bestVisibleHeight) {
                bestVisibleHeight = height;
                candidate = elements[i];
            }
        }
        var source = 'unknown';
        if (elements.length === 1) {
            candidate = elements[0];
            source = 'only_question';
        } else if (lastQuestion && document.documentElement.contains(lastQuestion)) {
            candidate = lastQuestion;
            source = 'last_interaction';
        } else if (candidate) {
            source = 'viewport';
        }
        var rawpage = param('page');
        var isattempt = /\\/attempt\\.php$/.test(window.location.pathname);
        return {
            contextversion: 1,
            documentid: documentId,
            pageindex: isattempt ? (rawpage === '' ? 0 : parseInt(rawpage, 10)) : null,
            question: questionInfo(candidate),
            questions: questions,
            contextsource: source,
            elapsed: cfg.attemptstart ? Math.max(0, cfg.servertime - cfg.attemptstart + nowSeconds() - initialClientTime) : null
        };
    }

    function param(name) {
        var match = new RegExp('[?&]' + name + '=([^&]*)').exec(window.location.search);
        return match ? decodeURIComponent(match[1].replace(/\+/g, ' ')) : '';
    }

    function nowSeconds() {
        return Math.floor(new Date().getTime() / 1000);
    }

    function attemptId() {
        return param('attempt') || param('attemptid') || '0';
    }

    function cmId() {
        return cfg.cmid || param('cmid') || '0';
    }

    function storageBaseKey() {
        return 'quizaccess_focusmonitor_' + cfg.quizid + '_' + attemptId();
    }

    function warningStorageKey() {
        return storageBaseKey() + '_warnings';
    }

    function focusLossStorageKey() {
        return storageBaseKey() + '_focuslost';
    }

    function storageGet(key) {
        try {
            return window.sessionStorage.getItem(key) || window.localStorage.getItem(key) || '';
        } catch (e) {
            return '';
        }
    }

    function storageSet(key, value) {
        try {
            window.sessionStorage.setItem(key, value);
        } catch (e) {
            // Ignore storage failures.
        }
        try {
            window.localStorage.setItem(key, value);
        } catch (e) {
            // Ignore storage failures.
        }
    }

    function storageRemove(key) {
        try {
            window.sessionStorage.removeItem(key);
        } catch (e) {
            // Ignore storage failures.
        }
        try {
            window.localStorage.removeItem(key);
        } catch (e) {
            // Ignore storage failures.
        }
    }

    function readWarningCount() {
        return parseInt(storageGet(warningStorageKey()) || '0', 10) || 0;
    }

    function writeWarningCount(count) {
        storageSet(warningStorageKey(), String(count));
    }

    function readFocusLoss() {
        var raw = storageGet(focusLossStorageKey());
        if (!raw) {
            return null;
        }
        try {
            var data = JSON.parse(raw);
            if (data && data.time) {
                return data;
            }
        } catch (e) {
            return null;
        }
        return null;
    }

    function writeFocusLoss(data) {
        storageSet(focusLossStorageKey(), JSON.stringify(data));
    }

    function clearFocusLoss() {
        storageRemove(focusLossStorageKey());
    }

    function rememberFocusLoss(time, source) {
        if (navigating) {
            return;
        }
        var existing = readFocusLoss();
        if (existing && existing.time) {
            return;
        }
        writeFocusLoss({time: time, source: source || '', navigating: 0, context: eventContext()});
    }

    function markStoredNavigation() {
        navigating = true;
        outOfFocus = false;
        focusLostAt = 0;
        var existing = readFocusLoss();
        if (existing && existing.time) {
            existing.navigating = 1;
            existing.navigationtime = nowSeconds();
            writeFocusLoss(existing);
        }
    }

    function post(eventtype, data) {
        var t = new Date().getTime();
        if (lastSent[eventtype] && (t - lastSent[eventtype]) < 700) {
            return;
        }
        lastSent[eventtype] = t;

        var attemptid = attemptId();
        var cmid = cmId();
        data = data || {};
        if (!data.context) {
            data.context = eventContext();
        }
        if (!data.time) {
            data.time = nowSeconds();
        }
        data.servertime = cfg.servertime + nowSeconds() - initialClientTime;
        data.navigating = navigating ? 1 : 0;
        data.path = window.location.pathname;
        data.query = window.location.search;
        data.url = window.location.pathname + window.location.search;
        data.page = param('page') || '';
        data.slot = param('slot') || '';
        data.visibility = document.visibilityState || '';

        var payload = 'sesskey=' + encodeURIComponent(cfg.sesskey) +
            '&quizid=' + encodeURIComponent(cfg.quizid) +
            '&cmid=' + encodeURIComponent(cmid) +
            '&attemptid=' + encodeURIComponent(attemptid) +
            '&eventtype=' + encodeURIComponent(eventtype) +
            '&eventdata=' + encodeURIComponent(JSON.stringify(data));

        try {
            if (navigator.sendBeacon && (eventtype === 'beforeunload' || eventtype === 'pagehide' ||
                    eventtype === 'window_blur' || eventtype === 'visibility_hidden')) {
                var blob = new Blob([payload], {type: 'application/x-www-form-urlencoded'});
                if (navigator.sendBeacon(cfg.ajaxurl, blob)) {
                    return;
                }
            }
            var xhr = new XMLHttpRequest();
            xhr.open('POST', cfg.ajaxurl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send(payload);
        } catch (e) {
            // Never interrupt the quiz because of monitor logging.
        }
    }

    function redirectToSummaryAfterLimit() {
        if (cfg.actionafterwarnings !== 'summary' && cfg.actionafterwarnings !== 'block') {
            return;
        }
        try {
            window.setTimeout(function() {
                var url = cfg.summaryurl + '?attempt=' + encodeURIComponent(attemptId()) + '&cmid=' + encodeURIComponent(cmId());
                window.location.href = url;
            }, 2500);
        } catch (e) {
            // Ignore redirect failures.
        }
    }

    function warn(duration, count, limitReached) {
        if (!cfg.showwarnings) {
            if (limitReached) {
                redirectToSummaryAfterLimit();
            }
            return;
        }
        try {
            var el = document.getElementById('quizaccess-focusmonitor-warning');
            if (!el) {
                el = document.createElement('div');
                el.id = 'quizaccess-focusmonitor-warning';
                el.style.position = 'fixed';
                el.style.left = '10px';
                el.style.right = '10px';
                el.style.bottom = '12px';
                el.style.zIndex = '2147483647';
                el.style.maxWidth = '720px';
                el.style.margin = '0 auto';
                el.style.padding = '14px 16px';
                el.style.background = '#fff3cd';
                el.style.border = '2px solid #856404';
                el.style.borderRadius = '6px';
                el.style.boxShadow = '0 3px 14px rgba(0,0,0,.25)';
                el.style.color = '#222';
                el.style.fontSize = '15px';
                el.style.lineHeight = '1.4';
                el.style.textAlign = 'left';
                document.body.appendChild(el);
            }
            var text = cfg.strings.returnwarning;
            text = text.replace('{duration}', duration).replace('{count}', count).replace('{max}', cfg.maxwarnings);
            if (limitReached) {
                text += ' ' + cfg.strings.warninglimitreached;
                if (cfg.actionafterwarnings === 'summary') {
                    text += ' ' + cfg.strings.redirecting;
                } else if (cfg.actionafterwarnings === 'block') {
                    text += ' ' + cfg.strings.blockredirecting;
                }
            }
            el.textContent = text;
            el.style.display = 'block';
            if (limitReached) {
                redirectToSummaryAfterLimit();
            }
            window.setTimeout(function() { el.style.display = 'none'; }, 10000);
        } catch (e) {
            // Ignore UI failures.
        }
    }

    function showNotice() {
        if (!cfg.showwarnings) {
            return;
        }
        try {
            if (document.getElementById('quizaccess-focusmonitor-notice')) {
                return;
            }
            var el = document.createElement('div');
            el.id = 'quizaccess-focusmonitor-notice';
            el.style.padding = '8px 12px';
            el.style.margin = '8px 0';
            el.style.background = '#e7f3fe';
            el.style.border = '1px solid #b8daff';
            el.style.borderRadius = '4px';
            el.textContent = cfg.strings.notice;
            var main = document.querySelector('#region-main') || document.body;
            main.insertBefore(el, main.firstChild);
        } catch (e) {
            // Ignore UI failures.
        }
    }

    var outOfFocus = false;
    var focusLostAt = 0;
    var warningThreshold = parseInt(cfg.warningthreshold || 10, 10);
    var maxWarnings = parseInt(cfg.maxwarnings || 3, 10);
    if (!warningThreshold || warningThreshold < 3) {
        warningThreshold = 10;
    }
    if (!maxWarnings || maxWarnings < 1) {
        maxWarnings = 3;
    }

    function maybeWarnForReturn(now, source) {
        var stored = readFocusLoss();
        // A different document's marker can belong to normal navigation or bfcache.
        // It does not establish a focus loss on this page.
        if (stored && stored.context && stored.context.documentid !== documentId) {
            stored = null;
        }
        var start = outOfFocus ? focusLostAt : (stored && stored.time ? parseInt(stored.time, 10) : 0);
        var navigating = stored && stored.navigating ? 1 : 0;

        outOfFocus = false;
        focusLostAt = 0;
        clearFocusLoss();

        if (!start || navigating) {
            return;
        }

        var duration = Math.max(0, now - start);
        if (duration >= warningThreshold) {
            var previousCount = readWarningCount();
            var count = Math.min(previousCount + 1, maxWarnings);
            writeWarningCount(count);
            var limitReached = count >= maxWarnings;
            post('focus_warning', {time: now, duration: duration, warning: count, maxwarnings: maxWarnings, source: source || '', repeatedlimit: previousCount >= maxWarnings ? 1 : 0, context: stored && stored.context ? stored.context : eventContext()});
            if (limitReached && previousCount < maxWarnings) {
                post('warning_limit_reached', {time: now, duration: duration, warning: count, maxwarnings: maxWarnings, source: source || '', context: stored && stored.context ? stored.context : eventContext()});
            }
            warn(duration, count, limitReached);
        }
    }

    function markFocusLost(source) {
        var now = nowSeconds();
        if (navigating) {
            post(source, {time: now});
            return;
        }
        if (!outOfFocus) {
            outOfFocus = true;
            focusLostAt = now;
            rememberFocusLoss(now, source);
        }
        post(source, {time: now});
    }

    function markFocusReturned(source) {
        if (document.hidden) {
            return;
        }
        var now = nowSeconds();
        post(source, {time: now});
        maybeWarnForReturn(now, source);
    }

    function initialise() {
        showNotice();
        post('page_loaded', {url: window.location.pathname + window.location.search, time: nowSeconds()});

        // If the previous page unloaded due to normal Moodle navigation, clear the persisted focus marker.
        // This avoids false warnings on slow question-to-question page loads.
        var stored = readFocusLoss();
        if (stored && (stored.navigating || (stored.context && stored.context.documentid !== documentId))) {
            clearFocusLoss();
        } else if (stored && stored.time && !document.hidden) {
            window.setTimeout(function() {
                if (!document.hidden) {
                    maybeWarnForReturn(nowSeconds(), 'page_loaded_return');
                }
            }, 500);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialise, false);
    } else {
        initialise();
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            markFocusLost('visibility_hidden');
        } else {
            markFocusReturned('visibility_visible');
        }
    });

    window.addEventListener('blur', function() {
        markFocusLost('window_blur');
    });

    window.addEventListener('focus', function() {
        markFocusReturned('window_focus');
    });

    document.addEventListener('copy', function() {
        post('copy', {time: nowSeconds()});
    });

    document.addEventListener('focusin', captureQuestion, false);
    document.addEventListener('mousedown', captureQuestion, false);
    document.addEventListener('touchstart', captureQuestion, false);

    document.addEventListener('paste', function() {
        post('paste', {time: nowSeconds()});
    });

    document.addEventListener('cut', function() {
        post('cut', {time: nowSeconds()});
    });

    document.addEventListener('contextmenu', function() {
        post('contextmenu', {time: nowSeconds()});
    });

    document.addEventListener('keydown', function(event) {
        event = event || window.event;
        if (event && (event.key === 'PrintScreen' || event.code === 'PrintScreen')) {
            post('screenshot_key', {time: nowSeconds(), key: 'PrintScreen'});
        }
    });

    window.addEventListener('beforeprint', function() {
        post('print_attempt', {time: nowSeconds()});
    });

    document.addEventListener('fullscreenchange', function() {
        post(document.fullscreenElement ? 'fullscreen_enter' : 'fullscreen_exit', {time: nowSeconds()});
    });

    window.addEventListener('pagehide', function(event) {
        if (!document.hidden) {
            markStoredNavigation();
        }
        post('pagehide', {time: nowSeconds(), persisted: event && event.persisted ? 1 : 0});
        // Some mobile browsers fire pagehide when the app is backgrounded without a reliable blur event.
        if (document.hidden) {
            markFocusLost('visibility_hidden');
        }
    });

    window.addEventListener('pageshow', function(event) {
        if (navigating) {
            // A cached document resumes after navigation. Its old in-memory loss is stale.
            navigating = false;
            outOfFocus = false;
            focusLostAt = 0;
            clearFocusLoss();
        }
        post('pageshow', {time: nowSeconds(), persisted: event && event.persisted ? 1 : 0});
        if (!document.hidden) {
            window.setTimeout(function() {
                if (!document.hidden) {
                    markFocusReturned('visibility_visible');
                }
            }, 300);
        }
    });

    window.addEventListener('beforeunload', function() {
        markStoredNavigation();
        post('beforeunload', {time: nowSeconds()});
    });
})();
JS;
        $page->requires->js_init_code($js);
    }
}
