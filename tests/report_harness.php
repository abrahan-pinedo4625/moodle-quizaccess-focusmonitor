<?php
// Exercise the complete report/export paths against a Moodle API test double.
define('MOODLE_INTERNAL', true);
define('PARAM_INT', 'int'); define('PARAM_ALPHA', 'alpha'); define('PARAM_ALPHANUMEXT', 'text');
define('MUST_EXIST', true);
function optional_param($key, $default, $type) {
    if ($key === 'cmid') { return 3; }
    if ($key === 'download') { return isset($GLOBALS['argv'][1]) ? $GLOBALS['argv'][1] : ''; }
    return $default;
}
function get_coursemodule_from_id() { return (object)array('id' => 3, 'instance' => 5, 'course' => 2); }
function require_login() {}
function require_capability() {}
class context_module { static function instance($id) { return new stdClass(); } }
function s($text) { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
function fullname($user) { return $user->firstname . ' ' . $user->lastname; }
function userdate($time) { return gmdate('Y-m-d H:i:s', $time); }
function format_string($text) { return s($text); }
function format_float($value, $precision) { return number_format($value, $precision, '.', ''); }
function html_to_text($text) { return strip_tags($text); }
require(__DIR__ . '/../lang/en/quizaccess_focusmonitor.php');
function get_string($key, $component = '') { global $string; return isset($string[$key]) ? $string[$key] : $key; }
class core_text { static function strtolower($value) { return strtolower($value); } }
class moodle_url {
    public $url;
    function __construct($url, $params = array()) { $this->url = (string)$url . ($params ? '?' . http_build_query($params) : ''); }
    function __toString() { return $this->url; }
}
class html_table { public $head = array(); public $data = array(); public $attributes = array(); }
class html_table_row { public $cells; public $attributes = array(); function __construct($cells) { $this->cells = $cells; } }
class html_writer {
    static function tag($name, $content, $attributes = array()) { return '<' . $name . '>' . $content . '</' . $name . '>'; }
    static function start_tag($name, $attributes = array()) { return '<' . $name . '>'; }
    static function end_tag($name) { return '</' . $name . '>'; }
    static function link($url, $label, $attributes = array()) { return '<a href="' . s($url) . '">' . $label . '</a>'; }
    static function table($table) {
        $html = '<table><tr><th>' . implode('</th><th>', $table->head) . '</th></tr>';
        foreach ($table->data as $row) {
            $cells = is_object($row) ? $row->cells : $row;
            if (count($cells) !== count($table->head)) { throw new Exception('Misaligned report table'); }
            $html .= '<tr><td>' . implode('</td><td>', $cells) . '</td></tr>';
        }
        return $html . '</table>';
    }
}
class ReportTestPage {
    function set_url($x) {} function set_context($x) {} function set_pagelayout($x) {}
    function set_title($x) {} function set_heading($x) {}
}
class ReportTestOutput {
    function header() { return '<html><body>'; } function footer() { return '</body></html>'; }
    function heading($value, $level = 2) { return '<h' . $level . '>' . $value . '</h' . $level . '>'; }
    function notification($value, $type) { return $value; }
}
class ReportTestDB {
    function get_record($table, $conditions, $fields = '*', $strictness = false) {
        return $table === 'quiz' ? (object)array('id' => 5, 'course' => 2, 'name' => 'Test quiz', 'grade' => 100) :
            (object)array('id' => 2, 'fullname' => 'Test course');
    }
    function get_records_sql($sql, $params, $offset = 0, $limit = 0) {
        $rows = array();
        for ($i = 0; $i < 2105; $i++) {
            $type = 'page_loaded'; $time = 1000 + $i;
            if ($i >= 2101) {
                $type = array('window_blur', 'visibility_hidden', 'visibility_visible', 'contextmenu')[$i - 2101];
                $time = array(5000, 5001, 5007, 5008)[$i - 2101];
            }
            $data = array('time' => $time + (int)getenv('FM_CLIENT_CLOCK_OFFSET'), 'path' => '/mod/quiz/attempt.php', 'page' => '2',
                'visibility' => $type === 'visibility_hidden' ? 'hidden' : 'visible',
                'context' => array('pageindex' => 2, 'question' => array('number' => '7', 'slot' => 5),
                    'contextsource' => 'only_question', 'elapsed' => $time - 900, 'documentid' => 'a'));
            $rows[$i + 1] = (object)array('id' => $i + 1, 'userid' => 42, 'firstname' => 'Test', 'lastname' => 'Student',
                'email' => 'student@example.invalid', 'finalgrade' => 55, 'attemptid' => 9,
                'attempttimestart' => 900, 'attempttimefinish' => 8000, 'eventtype' => $type,
                'eventdata' => json_encode($data), 'timecreated' => $time, 'ipaddress' => '127.0.0.1');
        }
        return $limit ? array_slice($rows, $offset, $limit, true) : $rows;
    }
}
$DB = new ReportTestDB(); $PAGE = new ReportTestPage(); $OUTPUT = new ReportTestOutput();
$source = file_get_contents(__DIR__ . '/../report.php');
$source = preg_replace('/^require_once\(.*\);$/m', '', $source);
eval(substr($source, 5));
