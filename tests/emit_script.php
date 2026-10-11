<?php
// Emit the actual PHP-generated monitor script with a minimal Moodle test harness.
define('MOODLE_INTERNAL', true);
define('PARAM_INT', 'int');
define('PARAM_ALPHA', 'alpha');
$stubroot = sys_get_temp_dir() . '/focusmonitor-test-' . getmypid();
mkdir($stubroot . '/mod/quiz/accessrule', 0777, true);
$stubfile = $stubroot . '/mod/quiz/accessrule/accessrulebase.php';
file_put_contents($stubfile, '<?php class quiz_access_rule_base { public $quiz; public $quizobj; }');
$CFG = (object)array('dirroot' => $stubroot);
$USER = (object)array('id' => 42);
class ScriptTestDB {
    function get_field($table, $field, $conditions) {
        if ($table !== 'quiz_attempts' || $field !== 'timestart' || $conditions['userid'] !== 42) {
            throw new Exception('Attempt start must be scoped to current user');
        }
        return time() - 120;
    }
}
$DB = new ScriptTestDB();
function optional_param($key, $default, $type) { return $key === 'attempt' ? 9 : $default; }
function sesskey() { return 'test-session-key'; }
function clean_param($value, $type) { return $value; }
function get_string($key, $component = '') { return $key; }
class moodle_url {
    private $url;
    function __construct($url) { $this->url = $url; }
    function out($escape = true) { return $this->url; }
}
class ScriptTestRequires { function js_init_code($code) { echo $code; } }
class ScriptTestQuiz { function get_cmid() { return 3; } }
require(__DIR__ . '/../rule.php');
$rule = new quizaccess_focusmonitor();
$rule->quiz = (object)array('id' => 5, 'focusmonitorshowwarnings' => getenv('FM_WARNINGS') !== '0',
    'focusmonitorwarningthreshold' => (int)(getenv('FM_THRESHOLD') ?: 10),
    'focusmonitormaxwarnings' => (int)(getenv('FM_MAXWARNINGS') ?: 3),
    'focusmonitoraction' => getenv('FM_ACTION') ?: 'review');
$rule->quizobj = new ScriptTestQuiz();
try {
    $rule->setup_attempt_page((object)array('requires' => new ScriptTestRequires()));
} finally {
    unlink($stubfile);
    rmdir($stubroot . '/mod/quiz/accessrule');
    rmdir($stubroot . '/mod/quiz');
    rmdir($stubroot . '/mod');
    rmdir($stubroot);
}
