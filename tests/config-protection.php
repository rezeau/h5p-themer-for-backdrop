<?php
/** Stateless native configuration boundary; standalone needs no external source. */
$protection_standalone = !function_exists('check');
if ($protection_standalone) {
  if (PHP_SAPI !== 'cli') { exit('CLI only.'); }
  error_reporting(E_ALL);
  set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
  });
  $passed = $failed = 0;
  function check($condition, $label) {
    $GLOBALS[$condition ? 'passed' : 'failed']++;
    if (!$condition) { fwrite(STDERR, 'FAIL: ' . $label . "\n"); }
  }
  function rejects(callable $callback, $label) {
    try { $callback(); check(FALSE, $label); }
    catch (ConfigValidateException $e) { check(TRUE, $label); }
  }
  function t($message, array $args = array()) { return strtr($message, $args); }
  define('MENU_DEFAULT_LOCAL_TASK', 1);
  define('MENU_LOCAL_ACTION', 2);
  define('MENU_LOCAL_TASK', 3);
  define('MENU_CALLBACK', 4);
  class ConfigValidateException extends RuntimeException {}
  // Only the Config name/data accessors used by the tested hook boundary.
  class Config {
    private $name, $data = array();
    public function __construct($name) { $this->name = $name; }
    public function getName() { return $this->name; }
    public function setData(array $data) { $this->data = $data; return $this; }
    public function getOriginal($key = NULL) { return $key === NULL ? $this->data : $this->data[$key]; }
  }
  // Any lifecycle dependency or journal access makes this test fail.
  function state_get(...$args) { throw new LogicException('Unexpected state read.'); }
  function state_set(...$args) { throw new LogicException('Unexpected state write.'); }
  function state_del(...$args) { throw new LogicException('Unexpected state deletion.'); }
  require dirname(__DIR__) . '/h5p_themer.module';
  require dirname(__DIR__) . '/h5p_themer.install';
}

function protection_config($name) {
  return $GLOBALS['protection_standalone'] ? new Config($name) : new Config($name, config_get_config_storage());
}

foreach (array('h5p_themer.theme.example', 'h5p_themer.learner_visibility') as $name) {
  $candidate = protection_config($name);
  foreach (array('create', 'update', 'delete') as $action) {
    rejects(function () use ($candidate, $action, $name) {
      if ($action === 'update') { h5p_themer_config_update_validate($candidate, $candidate, array($name => $action)); }
      else { call_user_func('h5p_themer_config_' . $action . '_validate', $candidate, array($name => $action)); }
    }, 'Validation rejects ' . $action . ' of ' . $name);
    rejects(function () use ($candidate, $action) {
      if ($action === 'update') { h5p_themer_config_update($candidate, $candidate); }
      else { call_user_func('h5p_themer_config_' . $action, $candidate); }
    }, 'Application rejects without prior validation: ' . $action . ' of ' . $name);
    rejects(function () use ($name, $action) {
      h5p_themer_config_create_validate(protection_config('fixture_unrelated.settings'), array('fixture_unrelated.settings' => 'create', $name => $action));
    }, 'Whole-plan validation rejects protected ' . $action . ' when unrelated object is first');
  }
}

$candidate = protection_config('fixture_unrelated.settings');
$unchanged = array('h5p_themer.theme.example' => NULL, 'h5p_themer.learner_visibility' => NULL);
h5p_themer_config_create_validate($candidate, $unchanged);
h5p_themer_config_update_validate($candidate, $candidate, $unchanged);
h5p_themer_config_delete_validate($candidate, $unchanged);
h5p_themer_config_create($candidate);
h5p_themer_config_update($candidate, $candidate);
h5p_themer_config_delete($candidate);
check(TRUE, 'Unrelated native operations and unchanged protected objects remain supported');

$candidate = protection_config('h5p_themer.settings');
$candidate->setData(array('schema_version' => 1, 'default_theme' => NULL));
h5p_themer_config_create_validate($candidate, NULL);
h5p_themer_config_update_validate($candidate, $candidate, NULL);
h5p_themer_config_delete_validate($candidate, NULL);
h5p_themer_config_create($candidate);
h5p_themer_config_update($candidate, $candidate);
h5p_themer_config_delete($candidate);
check(TRUE, 'Settings-only native operations retain schema/reference validation');
$candidate->setData(array('schema_version' => 1, 'default_theme' => NULL, 'unexpected' => TRUE));
rejects(function () use ($candidate) { h5p_themer_config_create_validate($candidate, NULL); }, 'Settings import still rejects invalid schema');

foreach (array(1002, 1003) as $number) {
  check(is_string(call_user_func('h5p_themer_update_' . $number)), 'Retired update remains callable without lifecycle or storage APIs: ' . $number);
}
$routes = h5p_themer_menu();
check(count($routes) === 9, 'Theme management retains nine routes');
foreach (array_keys($routes) as $path) { check(strpos($path, '/import-definition') === FALSE, 'No custom import route: ' . $path); }

if ($protection_standalone) {
  printf("PHP %s focused configuration protection: %d passed, %d failed\n", PHP_VERSION, $passed, $failed);
  exit($failed ? 1 : 0);
}
