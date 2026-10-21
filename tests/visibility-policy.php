<?php
/**
 * @file
 * Native file-storage policy safety; private fixtures, never a site bootstrap.
 * SPDX-License-Identifier: GPL-3.0-or-later.
 */

$visibility_policy_standalone = !function_exists('check');
if ($visibility_policy_standalone) {
  require __DIR__ . '/bootstrap.php';
}

/** Only fault injection replaces native behavior; normal cases use Core as-is. */
class H5PThemerVisibilityFaultStorage extends ConfigFileStorage {
  public static $mode = '';
  public function exists($name) {
    if ($name === 'h5p_themer.learner_visibility') {
      if (self::$mode === 'exists') { throw new ConfigStorageReadException('Private existence failure detail.'); }
      if (self::$mode === 'unknown') { return NULL; }
    }
    return parent::exists($name);
  }
  public function read($name) {
    if ($name === 'h5p_themer.learner_visibility') {
      if (self::$mode === 'read') { throw new ConfigStorageReadException('Private read failure detail.'); }
      // Model a disappearing object or backend FALSE after confirmed existence.
      if (self::$mode === 'false-read') { return FALSE; }
    }
    return parent::read($name);
  }
}

(function () {
  $root = sys_get_temp_dir() . '/h5p-themer-policy-' . bin2hex(random_bytes(12));
  if (!mkdir($root, 0700)) { throw new RuntimeException('Cannot create private policy fixture.'); }
  $root = realpath($root);
  if (!$root || strpos(str_replace('\\', '/', $root), str_replace('\\', '/', BACKDROP_ROOT) . '/') === 0) {
    throw new RuntimeException('Unsafe policy fixture location.');
  }
  $saved_cwd = getcwd();
  $saved_directories = $GLOBALS['config_directories'];
  $saved_overrides = $GLOBALS['config'];
  $saved_class = isset($GLOBALS['visibility_test_storage_class']) ? $GLOBALS['visibility_test_storage_class'] : NULL;
  $saved_cache = backdrop_static('config');
  $memory_bytes = H5PThemerMemoryStorage::$raw;
  $memory_writes = H5PThemerMemoryStorage::$writes;
  $name = 'h5p_themer.learner_visibility';
  $policy_file = $root . '/' . $name . '.json';
  $theme_file = $root . '/h5p_themer.theme.storage_custom.json';
  $fixed = array('site_default', 'core', 'builtin:daylight');
  $snapshot = function () use ($root) {
    $result = array();
    foreach (scandir($root) as $entry) {
      if ($entry === '.' || $entry === '..') { continue; }
      if (!is_file($root . '/' . $entry)) { throw new RuntimeException('Unexpected policy fixture entry.'); }
      $result[$entry] = hash_file('sha256', $root . '/' . $entry);
    }
    return $result;
  };
  $raw_policy = function ($raw) use ($policy_file) {
    if (file_put_contents($policy_file, $raw) !== strlen($raw)) { throw new RuntimeException('Cannot write private policy fixture.'); }
    clearstatcache(TRUE, $policy_file);
    backdrop_static('config', NULL, TRUE);
  };
  try {
    // Core treats Windows drive-letter paths as site-relative. Use its native
    // dot-relative directory contract while this process is inside the fixture.
    if (!chdir($root)) { throw new RuntimeException('Cannot enter private policy fixture.'); }
    $GLOBALS['config_directories'] = array('active' => '.', 'staging' => '.');
    $GLOBALS['config'] = array();
    $GLOBALS['visibility_test_storage_class'] = 'ConfigFileStorage';
    backdrop_static('config', NULL, TRUE);
    file_put_contents($theme_file, json_encode(fixture_theme('storage_custom')));
    $storage = config_get_config_storage();
    check(get_class($storage) === 'ConfigFileStorage', 'Policy safety cases use actual unmodified Backdrop file storage');
    $before = $snapshot();
    check($storage->exists($name) === FALSE, 'Native storage confirms genuine policy absence');
    check(h5p_themer_visibility_policy_load()['state'] === 'absent', 'Only genuine absent storage selects temporary legacy fallback');
    $legacy = array_keys(h5p_themer_learner_catalog()['data']);
    check(count($legacy) === 8 && in_array('custom:storage_custom', $legacy, TRUE), 'Absent legacy policy preserves built-ins and saved custom');
    check($snapshot() === $before, 'Absent-policy reads create no policy or other file');

    $malformed = array('false', 'null', '{}', '[]', '[1]', '[{"schema_version":1,"themes":{}}]',
      '0', '42', '-1.5', '""', '"false"', '"private text"', 'true', '{broken');
    foreach (array(NULL, 0, 1, 'true', 'false', array()) as $value) {
      $malformed[] = json_encode(array('schema_version' => 1, 'themes' => array('builtin:mint' => $value)));
    }
    foreach ($malformed as $index => $raw) {
      $raw_policy($raw);
      $before = $snapshot();
      check($storage->exists($name) === TRUE, 'Malformed native fixture really exists: ' . $index);
      if ($raw === 'false') {
        check($storage->read($name) === FALSE, 'Actual Core decoder returns FALSE for present JSON false');
        $native = new Config($name, $storage);
        $native->load();
        check($native->isNew() === TRUE, 'Actual Config load demonstrates why isNew alone is unsafe');
      }
      $policy = h5p_themer_visibility_policy_load();
      check($policy['state'] === 'malformed' && $policy['data'] === NULL && (bool) $policy['errors'], 'Malformed native bytes fail closed: ' . $index);
      check(array_keys(h5p_themer_learner_catalog()['data']) === $fixed, 'Malformed native policy retains only fixed choices: ' . $index);
      check(strpos(implode(' ', $policy['errors']), 'private text') === FALSE, 'Malformed native policy diagnostics do not disclose input: ' . $index);
      check($snapshot() === $before, 'Malformed-policy read never repairs or rewrites native files: ' . $index);
      $GLOBALS['config'][$name] = array('schema_version' => 1, 'themes' => array('builtin:mint' => TRUE));
      check(h5p_themer_visibility_policy_load()['state'] === 'malformed', 'Valid overrides cannot conceal malformed native storage: ' . $index);
      check(array_keys(h5p_themer_learner_catalog()['data']) === $fixed, 'Overrides cannot republish optional choices from malformed storage: ' . $index);
      $GLOBALS['config'] = array();
    }

    $valid = array('schema_version' => 1, 'themes' => array('builtin:mint' => TRUE, 'builtin:dark' => FALSE, 'custom:storage_custom' => TRUE));
    $raw_policy(json_encode($valid));
    $before = $snapshot();
    check(h5p_themer_visibility_policy_load()['state'] === 'initialized', 'Valid native policy stays initialized');
    check(array_keys(h5p_themer_learner_catalog()['data']) === array_merge($fixed, array('builtin:mint', 'custom:storage_custom')), 'Native policy honors strict true/false without changing ordering');
    check($snapshot() === $before, 'Valid initialized policy reads preserve native bytes');
    $GLOBALS['config'][$name] = array('themes' => array('builtin:dark' => TRUE));
    check(array_keys(h5p_themer_learner_catalog()['data']) === array_merge($fixed, array('builtin:dark')), 'Native Config effective whole-map override still works');
    $GLOBALS['config'] = array();

    $GLOBALS['visibility_test_storage_class'] = 'H5PThemerVisibilityFaultStorage';
    foreach (array('exists', 'unknown', 'read', 'false-read') as $mode) {
      H5PThemerVisibilityFaultStorage::$mode = $mode;
      backdrop_static('config', NULL, TRUE);
      $before = $snapshot();
      $policy = h5p_themer_visibility_policy_load();
      check($policy['state'] === 'malformed' && $policy['data'] === NULL && (bool) $policy['errors'], 'Storage uncertainty/failure is not legacy absence: ' . $mode);
      check(array_keys(h5p_themer_learner_catalog()['data']) === $fixed, 'Storage uncertainty/failure hides optional choices: ' . $mode);
      check(strpos(implode(' ', $policy['errors']), 'Private') === FALSE, 'Storage exception details remain private: ' . $mode);
      check($snapshot() === $before, 'Storage failure handling performs no file writes: ' . $mode);
    }
    H5PThemerVisibilityFaultStorage::$mode = '';
    $GLOBALS['visibility_test_storage_class'] = 'ConfigFileStorage';

    // A cached valid object must not mask subsequently malformed stored bytes.
    $raw_policy(json_encode($valid + array('_config_static' => TRUE)));
    $cached = config($name);
    check($cached->getOriginal('schema_version') === 1, 'Native request-static valid Config fixture loaded');
    file_put_contents($policy_file, 'false');
    clearstatcache(TRUE, $policy_file);
    $before = $snapshot();
    check(h5p_themer_visibility_policy_load()['state'] === 'malformed', 'Fresh policy read bypasses stale request-static valid object');
    check(array_keys(h5p_themer_learner_catalog()['data']) === $fixed, 'Stale request-static object cannot expose optional themes');
    check($snapshot() === $before, 'Fresh malformed-policy read does not rewrite cached or stored configuration');
    check(H5PThemerMemoryStorage::$raw === $memory_bytes && H5PThemerMemoryStorage::$writes === $memory_writes, 'Native safety tests leave the original memory collaborator unchanged');
  }
  finally {
    H5PThemerVisibilityFaultStorage::$mode = '';
    $GLOBALS['config_directories'] = $saved_directories;
    $GLOBALS['config'] = $saved_overrides;
    if ($saved_class === NULL) { unset($GLOBALS['visibility_test_storage_class']); }
    else { $GLOBALS['visibility_test_storage_class'] = $saved_class; }
    $cache = &backdrop_static('config');
    $cache = $saved_cache;
    if (!chdir($saved_cwd)) { throw new RuntimeException('Cannot restore test working directory.'); }
    // Remove only the two explicit fixture paths; never recurse or follow links.
    foreach (array($policy_file, $theme_file) as $file) {
      if (is_file($file) && !unlink($file)) { throw new RuntimeException('Cannot remove private policy fixture.'); }
    }
    if (!rmdir($root)) { throw new RuntimeException('Cannot remove private policy fixture directory.'); }
  }
})();

if ($visibility_policy_standalone) {
  printf("PHP %s focused policy safety: %d passed, %d failed\n", PHP_VERSION, $passed, $failed);
  exit($failed ? 1 : 0);
}
