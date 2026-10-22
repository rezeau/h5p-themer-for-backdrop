<?php
/** Native API bodies with strict memory database/cache boundaries. GPL-3.0-or-later. */
class H5PVisibilityNativeFixture {
  public static $schema = 0, $states = array(), $cache = array(), $stateWrites = 0, $schemaWrites = 0, $fault = '';
}

class H5PVisibilityRows implements IteratorAggregate {
  private $rows;
  public function __construct(array $rows) { $this->rows = $rows; }
  public function getIterator(): Traversable { return new ArrayIterator($this->rows); }
  public function fetchAllKeyed() { return H5PVisibilityNativeFixture::$states; }
}
function visibility_fixture_query($query = NULL, ...$args) {
  if ($query === "SELECT name, schema_version FROM {system} WHERE type = :type") {
    if (H5PVisibilityNativeFixture::$fault === 'witness_read') { throw new RuntimeException('Fixture schema read failure.'); }
    $value = H5PVisibilityNativeFixture::$schema;
    return new H5PVisibilityRows($value === NULL ? array() : array((object) array('name' => 'h5p_themer', 'schema_version' => $value)));
  }
  if ($query === 'SELECT name, value FROM {state}') {
    if (H5PVisibilityNativeFixture::$fault === 'state_read') { throw new RuntimeException('Fixture state read failure.'); }
    return new H5PVisibilityRows(array());
  }
  throw new LogicException('Database access is forbidden outside isolated state/schema SQL.');
}

class H5PVisibilityQuery {
  private $table, $operation, $key = array(), $fields = array();
  public function __construct($table, $operation) {
    if (!in_array($table, array('system', 'state'), TRUE)) { throw new LogicException('Forbidden table.'); }
    $this->table = $table; $this->operation = $operation;
  }
  public function key($key) { $this->key = $key; return $this; }
  public function fields($fields) { $this->fields = $fields; return $this; }
  public function condition($key, $value) { $this->key[$key] = $value; return $this; }
  public function execute() {
    if ($this->table === 'system') {
      if ($this->key !== array('name' => 'h5p_themer') || !isset($this->fields['schema_version'])) { throw new LogicException('Forbidden schema write.'); }
      H5PVisibilityNativeFixture::$schema = $this->fields['schema_version'];
      H5PVisibilityNativeFixture::$schemaWrites++;
      return 1;
    }
    $key = $this->key['name'];
    if ($this->operation === 'delete') {
      unset(H5PVisibilityNativeFixture::$states[$key]); H5PVisibilityNativeFixture::$stateWrites++; return 1;
    }
    $data = unserialize($this->fields['value']);
    if ($key === 'h5p_themer.visibility_form_generation' && H5PVisibilityNativeFixture::$fault === 'generation_write') {
      throw new RuntimeException('Fixture visibility generation write failure.');
    }
    if ($key === 'h5p_themer.visibility_activation') {
      $fault = H5PVisibilityNativeFixture::$fault;
      if (($fault === 'prepare_write' && $data['status'] === 'prepared') ||
          ($fault === 'pending_write' && $data['status'] === 'pending') ||
          ($fault === 'complete_write' && $data['status'] === 'complete' && $data['cache_pending']) ||
          ($fault === 'final_write' && $data['status'] === 'complete' && !$data['cache_pending'])) {
        throw new RuntimeException('Fixture state write failure.');
      }
    }
    H5PVisibilityNativeFixture::$states[$key] = $this->fields['value'];
    if ($key === 'h5p_themer.visibility_form_generation' && H5PVisibilityNativeFixture::$fault === 'generation_verify') {
      H5PVisibilityNativeFixture::$states[$key] = serialize('invalid');
    }
    H5PVisibilityNativeFixture::$stateWrites++;
    if ($key === 'h5p_themer.visibility_activation' && H5PVisibilityNativeFixture::$fault === 'final_write_after' && $data['status'] === 'complete' && !$data['cache_pending']) { throw new RuntimeException('Fixture post-persistence failure.'); }
    return 1;
  }
}
function db_merge($table, $options = array()) { return new H5PVisibilityQuery($table, 'merge'); }
function db_update($table, $options = array()) { return new H5PVisibilityQuery($table, 'update'); }
function db_delete($table, $options = array()) { return new H5PVisibilityQuery($table, 'delete'); }

class H5PVisibilityCache {
  public function get($key) { return isset(H5PVisibilityNativeFixture::$cache[$key]) ? (object) array('data' => H5PVisibilityNativeFixture::$cache[$key]) : FALSE; }
  public function set($key, $value) { H5PVisibilityNativeFixture::$cache[$key] = $value; }
  public function delete($key) { unset(H5PVisibilityNativeFixture::$cache[$key]); }
}
function watchdog_exception($type, $exception) { /* No live logger or database. */ }
function cache($bin = 'cache') {
  if ($bin !== 'cache') { throw new LogicException('Unexpected cache bin.'); }
  return new H5PVisibilityCache();
}

/** Extract only named, unmodified function bodies; do not execute site bootstrap. */
function visibility_native_functions($path, array $names) {
  $tokens = token_get_all(file_get_contents($path)); $found = array();
  for ($i = 0; $i < count($tokens); $i++) {
    if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) { continue; }
    $j = $i + 1;
    while ($j < count($tokens) && (!is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING)) { $j++; }
    $name = $j < count($tokens) ? $tokens[$j][1] : '';
    if (!in_array($name, $names, TRUE)) { continue; }
    $body = ''; $depth = 0; $started = FALSE;
    for ($j = $i; $j < count($tokens); $j++) {
      $token = $tokens[$j]; $body .= is_array($token) ? $token[1] : $token;
      if ($token === '{') { $depth++; $started = TRUE; }
      if ($token === '}') { $depth--; }
      if ($started && $depth === 0) { break; }
    }
    eval($body); $found[] = $name; $i = $j;
  }
  if (count($found) !== count($names)) { throw new RuntimeException('Missing native fixture API.'); }
}
if (!defined('SCHEMA_UNINSTALLED')) { define('SCHEMA_UNINSTALLED', -1); }
visibility_native_functions($backdrop_root . '/core/includes/install.inc', array('backdrop_get_installed_schema_version', 'backdrop_set_installed_schema_version'));
visibility_native_functions($backdrop_root . '/core/includes/bootstrap.inc', array('state_initialize', 'state_get', 'state_set', 'state_del'));
visibility_native_functions($backdrop_root . '/core/includes/update.inc', array('update_do_one'));

function visibility_native_reset() {
  H5PVisibilityNativeFixture::$schema = 0; H5PVisibilityNativeFixture::$states = array();
  H5PVisibilityNativeFixture::$stateWrites = H5PVisibilityNativeFixture::$schemaWrites = 0;
  H5PVisibilityNativeFixture::$fault = '';
  H5PVisibilityNativeFixture::$cache = array('states' => array());
  backdrop_static('states', NULL, TRUE);
  $GLOBALS['visibility_test_lock_owned'] = FALSE;
  $GLOBALS['visibility_test_lock_calls'] = 0;
  $GLOBALS['visibility_test_lease_loss'] = 0;
}
visibility_native_reset();
