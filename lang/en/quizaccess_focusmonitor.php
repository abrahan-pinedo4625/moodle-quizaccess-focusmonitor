<?php
/**
 * English strings for quizaccess_focusmonitor.
 *
 * @package    quizaccess_focusmonitor
 * @copyright  2026 Abrahan Pinedo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Focus monitor';
$string['focusmonitorheader'] = 'Focus monitor';
$string['focusmonitorenabled'] = 'Enable focus monitoring';
$string['focusmonitorenabled_help'] = 'When enabled, Moodle will log basic browser events during the quiz attempt, such as tab changes, window focus changes, copy, paste, right click, fullscreen changes and page unload attempts. This does not prevent cheating; it only records signals for teacher review.';
$string['focusmonitorshowwarnings'] = 'Student warning mode';
$string['focusmonitorshowwarnings_help'] = 'If visible warnings are enabled, students will see a notice at the start of the attempt and a warning when they return to the quiz after staying out of focus longer than the configured threshold.';
$string['warningssilent'] = 'Silent logging only';
$string['warningsvisible'] = 'Show student warnings';
$string['descriptionstudent'] = 'This quiz has focus monitoring enabled. Browser focus changes and related events may be recorded for academic review.';
$string['studentnotice'] = 'Focus monitoring is active for this quiz. Leaving the quiz window, changing tabs, copying, pasting or using context menu actions may be recorded.';
$string['returnwarning'] = 'You were detected outside the exam for {duration} seconds. Warning {count} of {max}. This event was recorded.';
$string['viewreport'] = 'View focus monitor report';
$string['reporttitle'] = 'Focus monitor report';
$string['reportintro'] = 'This report lists browser focus events recorded during attempts. It is evidence for review, not automatic proof of misconduct.';
$string['reportinterpretationnote'] = 'Interpretation: page loads and focus events close to question navigation are classified as normal navigation. The percentage out of the exam is calculated from the total recorded exam time shown in the table. Strong signals such as copy, paste, cut, print, desktop screenshot key or context menu require teacher review.';
$string['norecords'] = 'No focus monitor events have been recorded for this quiz.';
$string['summary'] = 'Summary by student';
$string['attemptsummary'] = 'Summary by attempt';
$string['events'] = 'Recorded events';
$string['student'] = 'Student';
$string['eventcount'] = 'Total events';
$string['riskcount'] = 'Risk events';
$string['navigationevents'] = 'Navigation events';
$string['focusrawcount'] = 'Raw focus changes';
$string['focuslosscount'] = 'Focus losses';
$string['focusepisodes'] = 'Out-of-focus episodes';
$string['focustime'] = 'Time out of focus';
$string['strongevents'] = 'Strong signals';
$string['copycount'] = 'Copy';
$string['pastecount'] = 'Paste';
$string['cutcount'] = 'Cut';
$string['contextmenucount'] = 'Right click / menu';
$string['printcount'] = 'Print';
$string['risklevel'] = 'Risk level';
$string['risklow'] = 'Low';
$string['riskmedium'] = 'Medium';
$string['riskhigh'] = 'High';
$string['eventcategory'] = 'Category';
$string['categorynavigation'] = 'Normal navigation';
$string['categorynavigationnoise'] = 'Focus near navigation';
$string['categoryfocus'] = 'Focus loss/return';
$string['categorystrong'] = 'Strong signal';
$string['categorytechnical'] = 'Technical';
$string['lasttime'] = 'Last event';
$string['attemptid'] = 'Attempt ID';
$string['eventtype'] = 'Event type';
$string['eventdata'] = 'Event data';
$string['ipaddress'] = 'IP address';
$string['missingcmidorquizid'] = 'Missing course module id or quiz id.';
$string['warninglimitreached'] = 'You have reached the configured warning limit; your attempt will be sent for teacher review.';
$string['focusmonitorwarningthreshold'] = 'Seconds out of focus before warning';
$string['focusmonitorwarningthreshold_help'] = 'Number of continuous seconds out of exam focus required to show a warning to the student and record a focus_warning event. Recommended initial value: 10 seconds.';
$string['focusmonitormaxwarnings'] = 'Maximum visible warnings';
$string['focusmonitormaxwarnings_help'] = 'Number of visible warnings before the configured action is executed. It is recommended to test this first in practice quizzes.';
$string['focuspercent'] = 'Percentage out of exam';
$string['warningcount'] = 'Visible warnings';
$string['screenshotcount'] = 'Screenshot / key';
$string['categorywarning'] = 'Monitor warning';
$string['filterstudent'] = 'Filter student';
$string['filterstudentplaceholder'] = 'Name or email';
$string['filterrisk'] = 'Risk level';
$string['filterallrisks'] = 'All';
$string['showattemptsummary'] = 'Show attempt summary';
$string['showrawevents'] = 'Show detailed events for debugging';
$string['finalgrade'] = 'Final grade';

$string['attemptduration'] = 'Total exam time';
$string['exportsummarycsv'] = 'Export summary CSV';
$string['exporteventscsv'] = 'Export events CSV';
$string['sortinstruction'] = 'Click any table heading to sort ascending or descending.';
$string['focusmonitoraction'] = 'Action after maximum warnings';
$string['focusmonitoraction_help'] = 'Defines what happens when the student reaches the maximum warning count. "Log only" keeps the attempt open. "Send to summary page" moves the student out of the question page. "Block return to attempt" also prevents the student from returning to the question page after reaching the limit. It is not a definitive automatic submission.';
$string['actionreviewonly'] = 'Log only for teacher review';
$string['actionsummary'] = 'Send to attempt summary page';
$string['actionblock'] = 'Send to summary and block return to attempt';
$string['strictmodereredirecting'] = 'You will be sent to the attempt summary page.';
$string['strictmodeblockredirecting'] = 'You will be sent to the summary page and will not be able to return to the question page.';
$string['strictblockmessage'] = 'This attempt reached the focus monitor warning limit. Return to the question page is blocked, and the attempt must be submitted or reviewed by the teacher.';
