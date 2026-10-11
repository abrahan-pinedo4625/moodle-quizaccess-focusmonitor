<?php
// Standalone analysis regressions; no Moodle instance or student data required.
$source = file_get_contents(__DIR__ . '/../report.php');
$start = strpos($source, 'function quizaccess_focusmonitor_decode_eventdata');
$end = strpos($source, '$records = $DB->get_records_sql');
eval(substr($source, $start, $end - $start));

function check($condition, $message) {
    if (!$condition) {
        throw new Exception($message);
    }
}
function get_string($key, $component = '') { return $key; }
function record($type, $time, $page = 0, $extra = array()) {
    $data = array_merge(array('path' => '/mod/quiz/attempt.php', 'page' => (string)$page,
        'time' => $time, 'visibility' => 'visible'), $extra);
    return (object)array('eventtype' => $type, 'timecreated' => $time, '_fmtime' => $time,
        '_fmdata' => $data, 'attemptid' => 9, 'attempttimestart' => 100, 'attempttimefinish' => 300);
}

// Mobile navigation without beforeunload must not turn an unpaired blur into 75 seconds.
$summary = quizaccess_focusmonitor_analyse_records(array(
    record('window_blur', 120, 19), record('pagehide', 193, 19, array('persisted' => 1)),
    record('page_loaded', 193, 20), record('visibility_visible', 195, 20),
));
check($summary['focustime'] === 0 && $summary['risklevel'] === 'low', 'Navigation inflated focus time/risk');
check(count($summary['episodes']) === 1 && !$summary['episodes'][0]['confirmed'], 'Unconfirmed loss was hidden');
check($summary['episodes'][0]['context']['page'] === 20, 'Page must be displayed one-based');
check($summary['episodes'][0]['context']['question'] === '', 'Legacy question was invented');

// Distinct blur/hidden and focus/visible signals are one episode. Hidden focus is not a return.
$context = array('pageindex' => 2, 'question' => array('number' => '7', 'slot' => 5),
    'contextsource' => 'only_question', 'elapsed' => 20, 'documentid' => 'a');
$summary = quizaccess_focusmonitor_analyse_records(array(
    record('window_blur', 120, 2, array('context' => $context)),
    record('visibility_hidden', 121, 2, array('visibility' => 'hidden', 'context' => $context)),
    record('window_focus', 125, 2, array('visibility' => 'hidden', 'context' => $context)),
    record('visibility_visible', 130, 2, array('context' => $context)),
    record('window_focus', 131, 2, array('context' => $context)),
));
check($summary['focusepisodes'] === 1 && $summary['focustime'] === 10, 'Duplicate signals or premature return');
$c = $summary['episodes'][0]['context'];
check($c['question'] === '7' && $c['slot'] === 5 && $c['elapsed'] === 20, 'Departure context lost');

// A return from another document cannot confirm an episode even with missing navigation logs.
$other = $context; $other['documentid'] = 'b';
$summary = quizaccess_focusmonitor_analyse_records(array(
    record('window_blur', 120, 2, array('context' => $context)),
    record('window_focus', 180, 2, array('context' => $other)),
));
check($summary['focustime'] === 0 && !$summary['episodes'][0]['confirmed'], 'Cross-document pairing');
$summary = quizaccess_focusmonitor_analyse_records(array(record('window_blur', 120), record('contextmenu', 200)));
check($summary['focustime'] === 0 && $summary['episodes'][0]['duration'] === null, 'Open loss got an invented duration');

// Context menus remain counted but cannot create high risk on their own.
$events = array();
for ($i = 0; $i < 20; $i++) { $events[] = record('contextmenu', 120 + $i); }
$summary = quizaccess_focusmonitor_analyse_records($events);
check($summary['contextmenucount'] === 20 && $summary['strongcount'] === 0 && $summary['risklevel'] === 'medium',
    'Context menus remain strong/high risk');
$summary = quizaccess_focusmonitor_analyse_records(array(record('copy', 120)));
check($summary['copycount'] === 1 && $summary['strongcount'] === 1 && $summary['risklevel'] === 'high',
    'Copy signal was weakened');
$summary = quizaccess_focusmonitor_analyse_records(array(record('focus_warning', 120,
    0, array('warning' => 3, 'maxwarnings' => 3))));
check($summary['warningcount'] === 3 && $summary['risklevel'] === 'high', 'Warning risk changed');

// Legacy fallback uses server receipt time rather than an unverified client clock.
$legacy = record('window_blur', 120); $legacy->_fmdata['time'] = 999999;
$c = quizaccess_focusmonitor_event_context($legacy);
check($c['elapsed'] === 20 && $c['question'] === '', 'Legacy clock/question fallback');
check(count(quizaccess_focusmonitor_context_headers(true)) === count(quizaccess_focusmonitor_context_cells($c, true)),
    'CSV context columns misaligned');

// Mobile background delivery can lose one endpoint; preserve warning evidence once and label it.
$warning = record('focus_warning', 130, 2, array('context' => $context,
    'duration' => 10, 'warning' => 1, 'maxwarnings' => 10, 'source' => 'visibility_visible'));
$summary = quizaccess_focusmonitor_analyse_records(array($warning));
check($summary['focusepisodes'] === 1 && $summary['focustime'] === 10 && $summary['episodes'][0]['reconstructed'],
    'Warning-only episode was omitted');
$summary = quizaccess_focusmonitor_analyse_records(array(
    record('window_blur', 120, 2, array('context' => $context)), $warning));
check(count($summary['episodes']) === 1 && $summary['episodes'][0]['reconstructed'], 'Recovered loss was duplicated');
$summary = quizaccess_focusmonitor_analyse_records(array(
    record('window_blur', 120, 2, array('context' => $context)),
    record('window_focus', 130, 2, array('context' => $context)), $warning));
check($summary['focusepisodes'] === 1 && $summary['focustime'] === 10 && !$summary['episodes'][0]['reconstructed'],
    'Warning duplicated a raw episode');
$warning->_fmdata['source'] = 'page_loaded_return';
check(quizaccess_focusmonitor_analyse_records(array($warning))['focustime'] === 0, 'Cross-page warning was reconstructed');
$warning->_fmdata['source'] = 'visibility_visible';
$summary = quizaccess_focusmonitor_analyse_records(array(
    record('pagehide', 120, 2, array('context' => $context)), $warning));
check($summary['focustime'] === 0, 'Stale navigation warning was reconstructed');

// A late departure from another document must not discard the active document's loss.
$summary = quizaccess_focusmonitor_analyse_records(array(
    record('window_blur', 120, 2, array('context' => $context, 'navigating' => 0)),
    record('pagehide', 121, 2, array('context' => $other)),
    record('window_focus', 130, 2, array('context' => $context, 'navigating' => 0))));
check($summary['focustime'] === 10, 'Unrelated departure cancelled an episode');
$timed = record('window_blur', 120); $timed->_fmclockoffset = 3000;
check(quizaccess_focusmonitor_event_time($timed) === 3120, 'Legacy context clock offset ignored');
$timed->_fmdata['servertime'] = 500;
check(quizaccess_focusmonitor_event_time($timed) === 500, 'Server-anchored event time ignored');

// Updating an installed RC does not touch the database schema or existing settings/logs.
define('MOODLE_INTERNAL', true);
function upgrade_plugin_savepoint($ok, $version, $type, $name) {
    check($ok && in_array($version, array(2026101000, 2026101001), true) &&
        $type === 'quizaccess' && $name === 'focusmonitor', 'Wrong savepoint');
    $GLOBALS['savepoints'][] = $version;
}
class FakeUpgradeDB { function get_manager() { return new stdClass(); } }
$DB = new FakeUpgradeDB();
require(__DIR__ . '/../db/upgrade.php');
check(xmldb_quizaccess_focusmonitor_upgrade(2026071509), 'Upgrade failed');
check($GLOBALS['savepoints'] === array(2026101000, 2026101001), 'RC1 to RC2 upgrade path missing');
$GLOBALS['savepoints'] = array();
check(xmldb_quizaccess_focusmonitor_upgrade(2026101000) && $GLOBALS['savepoints'] === array(2026101001), 'Installed RC1 upgrade failed');
echo "Analysis and upgrade regressions passed\n";
