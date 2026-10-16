<?php
/**
 * @file
 * Site-free test harness. SPDX-License-Identifier: GPL-2.0-or-later.
 *
 * Uses real Backdrop Config and config-sync definitions with in-memory storage.
 * Never includes a site's settings.php or bootstrap, or opens a database.
 */

if (PHP_SAPI !== 'cli') {
  exit('CLI only.');
}
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
  throw new ErrorException($message, 0, $severity, $file, $line);
});
$options = getopt('', array('backdrop-root:', 'h5p-root:'));
if (empty($options['backdrop-root'])) {
  fwrite(STDERR, "Usage: php -n tests/run.php --backdrop-root=<existing-source-root> [--h5p-root=<module-source-root>]\n");
  exit(2);
}
$backdrop_root = realpath($options['backdrop-root']);
$h5p_root = realpath(isset($options['h5p-root']) ? $options['h5p-root'] : $backdrop_root . '/modules/h5p');
if (!$backdrop_root || !$h5p_root) {
  throw new RuntimeException('Source roots do not exist.');
}
define('BACKDROP_ROOT', $backdrop_root);
define('REQUIREMENT_OK', 0);
define('REQUIREMENT_ERROR', 2);

// Narrow collaborators for native Config/import code. No real site services.
$config = array();
$config_directories = array('active' => './active', 'staging' => './staging');
$h5p_enabled = TRUE;
function t($message, array $args = array(), array $options = array()) {
  foreach ($args as $key => $value) {
    $args[$key] = check_plain((string) $value);
  }
  return strtr($message, $args);
}
function check_plain($value) {
  return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
function format_string($message, array $args = array()) {
  return t($message, $args);
}
function &backdrop_static($name, $default = NULL, $reset = FALSE) {
  static $values = array();
  if ($reset || !array_key_exists($name, $values)) {
    $values[$name] = $default;
  }
  return $values[$name];
}
function backdrop_valid_test_ua() {
  return FALSE;
}
function settings_get($name, $default = NULL) {
  return in_array($name, array('config_active_class', 'config_staging_class'), TRUE) ? 'H5PThemerMemoryStorage' : $default;
}
function module_exists($name) {
  return $name === 'h5p' && $GLOBALS['h5p_enabled'];
}
function backdrop_get_path($type, $name) {
  if ($type === 'module' && in_array($name, array('h5p', 'h5p_themer'), TRUE)) {
    return 'modules/' . $name;
  }
  throw new LogicException('Unexpected path lookup.');
}
function module_invoke_all($hook, ...$args) {
  $function = 'h5p_themer_' . $hook;
  $result = function_exists($function) ? call_user_func_array($function, $args) : NULL;
  return is_array($result) ? $result : array();
}
function backdrop_alter($type, &$data, ...$args) {
  if ($type !== 'config_info' && !(is_array($type) && reset($type) === 'form')) {
    throw new LogicException('Unexpected alter hook.');
  }
}
function backdrop_json_encode($data, $pretty = FALSE) {
  return json_encode($data, $pretty ? JSON_PRETTY_PRINT : 0);
}
$runtime_cache_invalidations = 0;
function cache_clear_all($cid = NULL, $bin = NULL, $wildcard = FALSE) {
  if ($cid !== NULL || $bin !== NULL || $wildcard !== FALSE) { throw new LogicException('Unexpected cache invalidation scope.'); }
  $GLOBALS['runtime_cache_invalidations']++;
}
function db_query() {
  throw new LogicException('Database access is forbidden in this harness.');
}

require_once $backdrop_root . '/core/includes/config.inc';
require_once $backdrop_root . '/core/modules/config/config.module';
require_once $backdrop_root . '/core/modules/config/config.sync.inc';

/**
 * In-memory JSON backend; inherits the native encoder/decoder and interface.
 */
class H5PThemerMemoryStorage extends ConfigFileStorage {
  public static $raw = array();
  public static $writes = 0;
  public static $failWrite = FALSE;
  public static $failDelete = FALSE;
  public function exists($name) {
    return isset(self::$raw[$this->directory][$name]);
  }
  public function read($name) {
    if (!$this->exists($name)) {
      return FALSE;
    }
    $data = $this->decode(self::$raw[$this->directory][$name]);
    if (is_array($data)) {
      unset($data['_config_name']);
    }
    return $data;
  }
  public function write($name, array $data) {
    if (self::$failWrite) { throw new ConfigStorageException('Simulated unavailable storage.'); }
    self::$writes++;
    self::$raw[$this->directory][$name] = $this->encode(array_merge(array('_config_name' => $name), $data));
    return TRUE;
  }
  public function delete($name) {
    if (self::$failDelete) { throw new ConfigStorageException('Simulated unavailable storage.'); }
    self::$writes++;
    unset(self::$raw[$this->directory][$name]);
    return TRUE;
  }
  public function listAll($prefix = '') {
    return array_values(array_filter(array_keys(isset(self::$raw[$this->directory]) ? self::$raw[$this->directory] : array()), function ($name) use ($prefix) {
      return strpos($name, $prefix) === 0;
    }));
  }
  public function isInitialized() {
    return TRUE;
  }
  public function initializeStorage() {
    throw new LogicException('Storage initialization is forbidden.');
  }
  public function rename($name, $new_name) {
    throw new LogicException('Storage renaming is not needed by these tests.');
  }
  public function getModifiedTime($name) {
    return $this->exists($name) ? 1 : FALSE;
  }
  public function importArchive($file_uri) {
    throw new LogicException('Archive operations are forbidden.');
  }
  public function exportArchive($file_uri) {
    throw new LogicException('Archive operations are forbidden.');
  }
}

require_once dirname(__DIR__) . '/h5p_themer.module';
require_once dirname(__DIR__) . '/h5p_themer.install';

$passed = 0;
$failed = 0;
function check($condition, $label) {
  if ($condition) {
    $GLOBALS['passed']++;
  }
  else {
    $GLOBALS['failed']++;
    fwrite(STDERR, "FAIL: $label\n");
  }
}
function rejects(callable $callback, $label) {
  try {
    $callback();
    check(FALSE, $label);
  }
  catch (ConfigValidateException $e) {
    check(TRUE, $label);
  }
}
function fixture_theme($id = 'ocean') {
  return array('schema_version' => 1, 'id' => $id, 'label' => 'Ocean', 'description' => '', 'density' => 'large', 'colors' => array('--h5p-theme-main-cta-base' => '#006FBF'));
}
function fixture_settings($id = NULL) {
  return array('schema_version' => 1, 'default_theme' => $id);
}
function seed($name, array $data, $type = 'active') {
  config($name, $type)->setData($data)->save();
}
function reset_storage() {
  H5PThemerMemoryStorage::$raw = array();
  H5PThemerMemoryStorage::$writes = 0;
  H5PThemerMemoryStorage::$failWrite = FALSE;
  H5PThemerMemoryStorage::$failDelete = FALSE;
  $cache = &backdrop_static('config');
  $cache = array();
  $GLOBALS['config'] = array();
}
