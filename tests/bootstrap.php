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
  if ($name === 'h5p_themer_native_density_enabled') {
    return isset($GLOBALS['native_density_test_enabled']) ? $GLOBALS['native_density_test_enabled'] : $default;
  }
  // Test-only backend injection for native file-storage safety regressions.
  $class = isset($GLOBALS['visibility_test_storage_class']) ? $GLOBALS['visibility_test_storage_class'] : 'H5PThemerMemoryStorage';
  return in_array($name, array('config_active_class', 'config_staging_class'), TRUE) ? $class : $default;
}

/** Site-free double of the read-only H5P Backdrop API; never constructs services. */
class H5PBackdrop {
  public static $libraries = array();
  public static $contents = array();
  public static $reads = 0;
  public static $fail = FALSE;
  public function isInDevMode() { return FALSE; }
  public function loadLibrary($name, $major, $minor) {
    self::$reads++;
    if (self::$fail) { throw new RuntimeException('Unavailable library API'); }
    return isset(self::$libraries[$name]) ? self::$libraries[$name] : FALSE;
  }
  public function loadContent($id) {
    self::$reads++;
    if (self::$fail) { throw new RuntimeException('Unavailable content API'); }
    return isset(self::$contents[$id]) ? self::$contents[$id] : NULL;
  }
}
function native_density_fixture($ids = array(6, 7, 8)) {
  H5PBackdrop::$fail = FALSE;
  H5PBackdrop::$libraries = array('H5P.TrueFalse' => array('libraryId' => '17', 'machineName' => 'H5P.TrueFalse', 'majorVersion' => '1', 'minorVersion' => '8', 'patchVersion' => '24'));
  H5PBackdrop::$contents = array();
  foreach ($ids as $id) {
    H5PBackdrop::$contents[$id] = array('id' => $id, 'libraryId' => '17', 'libraryName' => 'H5P.TrueFalse', 'libraryMajorVersion' => '1', 'libraryMinorVersion' => '8', 'embedType' => 'div', 'libraryEmbedTypes' => 'iframe');
  }
}
function module_exists($name) {
  return $name === 'h5p' && $GLOBALS['h5p_enabled'];
}
function backdrop_get_path($type, $name) {
  if ($type === 'module' && $name === 'config') { return 'core/modules/config'; }
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
  if (is_array($type) && in_array('form_config_import_single_form', $type, TRUE)) {
    h5p_themer_form_config_import_single_form_alter($data, $args[0]);
  }
}
function backdrop_json_encode($data, $pretty = FALSE) {
  return json_encode($data, $pretty ? JSON_PRETTY_PRINT : 0);
}
$runtime_cache_invalidations = 0;
function cache_clear_all($cid = NULL, $bin = NULL, $wildcard = FALSE) {
  if ($cid !== NULL || $bin !== NULL || $wildcard !== FALSE) { throw new LogicException('Unexpected cache invalidation scope.'); }
  if (H5PVisibilityNativeFixture::$fault === 'output_cache') { throw new RuntimeException('Fixture output cache failure.'); }
  $GLOBALS['runtime_cache_invalidations']++;
}
function db_query(...$args) {
  return visibility_fixture_query(...$args);
}
require_once __DIR__ . '/visibility-native-fixture.php';

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
  public static $failList = FALSE;
  public static $beforeWrite = NULL;
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
    if (self::$beforeWrite !== NULL) { call_user_func(self::$beforeWrite, $name, $data); }
    if (self::$failWrite || ($name === 'h5p_themer.learner_visibility' && H5PVisibilityNativeFixture::$fault === 'policy_save')) { throw new ConfigStorageException('Simulated unavailable storage.'); }
    if (strpos($name, 'h5p_themer.theme.') === 0 && in_array(H5PVisibilityNativeFixture::$fault, array('theme_save', 'theme_save_false'), TRUE)) {
      if (H5PVisibilityNativeFixture::$fault === 'theme_save_false') { return FALSE; }
      throw new ConfigStorageException('Fixture definition failure.');
    }
    if ($name === 'h5p_themer.learner_visibility' && H5PVisibilityNativeFixture::$fault === 'policy_save_false') { return FALSE; }
    self::$writes++;
    self::$raw[$this->directory][$name] = $this->encode(array_merge(array('_config_name' => $name), $data));
    if ($name === 'h5p_themer.learner_visibility' && H5PVisibilityNativeFixture::$fault === 'policy_verify') { self::$raw[$this->directory][$name] = '{}'; }
    if ($name === 'h5p_themer.learner_visibility' && H5PVisibilityNativeFixture::$fault === 'policy_save_after') { throw new ConfigStorageException('Fixture post-persistence failure.'); }
    if (strpos($name, 'h5p_themer.theme.') === 0 && H5PVisibilityNativeFixture::$fault === 'theme_save_after') { throw new ConfigStorageException('Fixture post-definition persistence failure.'); }
    if (strpos($name, 'h5p_themer.theme.') === 0 && H5PVisibilityNativeFixture::$fault === 'theme_verify') { self::$raw[$this->directory][$name] = '{}'; }
    return TRUE;
  }
  public function delete($name) {
    if (self::$failDelete) { throw new ConfigStorageException('Simulated unavailable storage.'); }
    if (H5PVisibilityNativeFixture::$fault === 'theme_delete_false') { return FALSE; }
    self::$writes++;
    unset(self::$raw[$this->directory][$name]);
    if (H5PVisibilityNativeFixture::$fault === 'theme_delete_after') { throw new ConfigStorageException('Fixture post-deletion failure.'); }
    return TRUE;
  }
  public function listAll($prefix = '') {
    if (self::$failList) { throw new ConfigStorageException('Simulated unavailable listing.'); }
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
  visibility_native_reset();
  H5PThemerMemoryStorage::$raw = array();
  H5PThemerMemoryStorage::$writes = 0;
  H5PThemerMemoryStorage::$failWrite = FALSE;
  H5PThemerMemoryStorage::$failDelete = FALSE;
  H5PThemerMemoryStorage::$failList = FALSE;
  H5PThemerMemoryStorage::$beforeWrite = NULL;
  $cache = &backdrop_static('config');
  $cache = array();
  $GLOBALS['config'] = array();
}

function current_path() { return isset($GLOBALS['learner_test_path']) ? $GLOBALS['learner_test_path'] : 'node/7'; }
function path_is_admin($path) { return !empty($GLOBALS['learner_test_admin_path']); }
function backdrop_add_http_header($name, $value, $append = FALSE) { $GLOBALS['learner_headers'][] = array($name, $value, $append); }
function backdrop_add_js($data = NULL, $options = NULL) { $GLOBALS['learner_js'][] = array('data' => $data, 'options' => $options); }
function backdrop_add_css($data = NULL, $options = NULL) { $GLOBALS['learner_css'][] = array('data' => $data, 'options' => $options); }


/**
 * Reference/resolver unit fixtures, not supported imports. Direct Config writes
 * deliberately model external storage to retain schema/reference coverage.
 * Native import rejection is exercised separately without a bypass flag.
 */
function reference_fixture_validate($name, $action, $changes = NULL, $data = NULL) {
  $staged = config($name, 'staging');
  if ($data === NULL) { $staged->load(); } else { $staged->setData($data); }
  if ($action === 'delete') { _h5p_themer_validate_theme_deletion(config($name)->load(), $changes); }
  else { $staged->validateData(); _h5p_themer_validate_default_reference($staged, $changes); }
}
function reference_fixture_apply($name, $action, $data = NULL) {
  if ($action === 'delete') { config($name)->delete(); }
  else {
    if ($data === NULL) { $data = config($name, 'staging')->load()->getOriginal(); }
    config($name)->setData($data)->save();
  }
}
