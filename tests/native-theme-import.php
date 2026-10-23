<?php
/**
 * @file
 * Native single-import form with existing isolated Form API/Config fixtures.
 * SPDX-License-Identifier: GPL-3.0-or-later.
 */
$import_standalone = !function_exists('check');
if ($import_standalone) {
  require __DIR__ . '/bootstrap.php'; require __DIR__ . '/admin-bootstrap.php';
  require_once $h5p_root . '/h5p.module';
  $mapping = h5p_autoload_info(); require_once $h5p_root . '/' . $mapping['H5PCore'];
}
// Execute the native constructor/handlers; only unrelated framework services
// are substituted, as in the existing administration fixtures.
load_native_helpers($backdrop_root . '/core/modules/config/config.admin.inc', array('config_import_single_form', 'config_import_single_form_validate', 'config_import_single_form_submit'));
function url($path, array $options = array()) { return '/' . $path; }
function module_load_include($type, $module, $name = NULL) {
  if ($type !== 'inc' || $module !== 'config' || $name !== 'config.sync') { throw new LogicException('Unexpected import include.'); }
  $path = BACKDROP_ROOT . '/core/modules/config/config.sync.inc'; require_once $path; return $path;
}
function backdrop_flush_all_caches() { $GLOBALS['native_import_full_flushes']++; }

function import_fixture() {
  admin_reset();
  seed('h5p_themer.settings', array('schema_version' => 2, 'default_theme' => array('kind' => 'custom', 'id' => 'existing'), 'learner_selection_enabled' => TRUE));
  seed('h5p_themer.theme.existing', fixture_theme('existing'));
  admin_publication_fixture();
  $GLOBALS['messages'] = array();
  $GLOBALS['runtime_cache_invalidations'] = $GLOBALS['native_import_full_flushes'] = 0;
  $GLOBALS['visibility_test_lock_calls'] = 0;
}
function import_definition($id = 'imported') {
  return array('_config_name' => 'h5p_themer.theme.' . $id, 'schema_version' => 2, 'id' => $id,
    'label' => '  Été <Example> "exact"  ', 'description' => "Line one\nLine two",
    'density' => 'small', 'colors' => array('--h5p-theme-background' => '#aBc', '--h5p-theme-main-cta-base' => '#12AbEf'),
    'base_preset' => array('id' => 'mint', 'version' => 'moodle-478fed30d2a4'));
}
function import_form() {
  $state = form_state_defaults(); $state['build_info'] = array('args' => array());
  return array('config_import_single_form', config_import_single_form(array(), $state), $state);
}
function import_post($data, $submit = TRUE, $token = TRUE) {
  return admin_post(import_form(), array('import' => is_string($data) ? $data : json_encode($data)), $token, $submit);
}
function import_policy($id, $visible) {
  $policy = _h5p_themer_visibility_policy_read(FALSE)['data']; $policy['themes']['custom:' . $id] = $visible;
  seed('h5p_themer.learner_visibility', $policy);
}
function import_hidden($id = 'imported') {
  $policy = _h5p_themer_visibility_policy_read(FALSE);
  return $policy['state'] === 'initialized' && isset($policy['data']['themes']['custom:' . $id]) && $policy['data']['themes']['custom:' . $id] === FALSE &&
    !isset(h5p_themer_learner_catalog()['data']['custom:' . $id]);
}
function import_success_messages() {
  return array_filter($GLOBALS['messages'], function ($entry) { return $entry[0] === 'status'; });
}
function import_stored_values(array $data) {
  unset($data['_config_name']);
  ksort($data);
  return $data;
}
function import_submit_validated(array $result) {
  form_clear_error();
  h5p_themer_config_import_single_form_submit($result['form'], $result['state']);
  return form_get_errors();
}

import_fixture();
$data = import_definition();
$before = H5PThemerMemoryStorage::$raw; $states = H5PVisibilityNativeFixture::$states;
$writes = H5PThemerMemoryStorage::$writes;
// Seed a stale request-local missing object before native form submission.
check(config($data['_config_name'])->isNew(), 'Destination initially absent in request cache');
$order = array();
H5PThemerMemoryStorage::$beforeWrite = function ($name) use (&$order) {
  $order[] = $name;
  if ($name === 'h5p_themer.theme.imported') {
    check(import_hidden(), 'Restrictive policy is persisted and readable before the definition write');
    check($GLOBALS['write_locked'], 'Shared administration lock surrounds the definition write');
  }
};
$result = import_post($data);
H5PThemerMemoryStorage::$beforeWrite = NULL;
check(!$result['errors'], 'Native form imports a valid schema-2 custom theme');
check($order === array('h5p_themer.learner_visibility', $data['_config_name']), 'Only policy then destination are written');
check(H5PThemerMemoryStorage::$writes === $writes + 2, 'Successful import performs exactly two configuration writes');
check(import_stored_values(config($data['_config_name'])->getOriginal()) === import_stored_values($data), 'Exact decoded JSON values and sparse overrides survive native Config save');
check(import_hidden() && !$write_locked, 'Successful import remains hidden and releases lock');
check(count($messages) === 1 && strpos($messages[0][1], 'Imported theme') !== FALSE, 'Success is reported after verification');
check($before['./active']['h5p_themer.settings'] === H5PThemerMemoryStorage::$raw['./active']['h5p_themer.settings'] &&
  $before['./active']['h5p_themer.theme.existing'] === H5PThemerMemoryStorage::$raw['./active']['h5p_themer.theme.existing'], 'Existing themes and Global Default remain byte-identical');
$policy_before = json_decode($before['./active']['h5p_themer.learner_visibility'], TRUE);
$policy_after = _h5p_themer_visibility_policy_read(FALSE)['data']; unset($policy_after['themes']['custom:imported']);
check($policy_after === array_diff_key($policy_before, array('_config_name' => TRUE)), 'All unrelated visibility entries are preserved');
check(H5PVisibilityNativeFixture::$states === $states, 'Import changes no activation journal or other state');
check($runtime_cache_invalidations === 0 && $native_import_full_flushes === 0, 'Hidden unselected import refreshes Config caches without unnecessary output flush');
check($result['form']['#validate'] === array('h5p_themer_config_import_single_form_validate') &&
  $result['form']['#submit'] === array('h5p_themer_config_import_single_form_submit'), 'Native Form API invokes the module form alteration');
$form = array('#validate' => array('before', 'config_import_single_form_validate', 'after'), '#submit' => array('before_submit', 'config_import_single_form_submit', 'after_submit'));
$state = array(); h5p_themer_form_config_import_single_form_alter($form, $state);
check($form['#validate'] === array('before', 'h5p_themer_config_import_single_form_validate', 'after') &&
  $form['#submit'] === array('before_submit', 'h5p_themer_config_import_single_form_submit', 'after_submit'), 'Form alteration preserves other modules handler ordering');

import_fixture(); $data = import_definition(); $data['colors'] = array(); unset($data['description']);
$result = import_post($data);
check(!$result['errors'] && import_stored_values(config($data['_config_name'])->getOriginal()) === import_stored_values($data), 'Empty sparse colors and absent optional metadata retain their values');
foreach (array(TRUE, FALSE) as $static) {
  import_fixture(); $data = import_definition(); $data['_config_static'] = $static;
  $result = import_post($data);
  check(!$result['errors'] && import_stored_values(config($data['_config_name'])->getOriginal()) === import_stored_values($data), 'Native metadata ordering does not change imported values or reject valid Boolean metadata');
}
import_fixture(); $data = import_definition('mint');
$result = import_post($data);
check(!$result['errors'] && import_hidden('mint') && import_stored_values(config($data['_config_name'])->getOriginal()) === import_stored_values($data), 'Custom ID matching a built-in remains a separate hidden custom theme');

import_fixture(); import_policy('imported', TRUE);
$stale_visibility = admin_form('visibility');
$result = import_post(import_definition()); $bytes = H5PThemerMemoryStorage::$raw;
$result = admin_post($stale_visibility, array('visibility' => array('custom:existing' => '1')));
check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes && import_hidden(), 'Pre-import visibility form cannot republish a reused destination token');

foreach (array(TRUE, FALSE) as $visible) {
  import_fixture(); import_policy('imported', $visible); import_policy('unrelated_stale', TRUE);
  $result = import_post(import_definition());
  check(!$result['errors'] && import_hidden(), 'Stale true/false destination visibility is reset to hidden');
  check(_h5p_themer_visibility_policy_read(FALSE)['data']['themes']['custom:unrelated_stale'] === TRUE, 'Unrelated stale visibility token remains unchanged');
}

foreach (array('valid', '{}', 'false', 'null', '"scalar"', '{broken') as $destination) {
  import_fixture();
  H5PThemerMemoryStorage::$raw['./active']['h5p_themer.theme.imported'] = $destination === 'valid' ? json_encode(import_definition()) : $destination;
  $bytes = H5PThemerMemoryStorage::$raw; $writes = H5PThemerMemoryStorage::$writes;
  $result = import_post(import_definition());
  check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes && H5PThemerMemoryStorage::$writes === $writes, 'Existing destination is rejected without writes: ' . $destination);
  check(!import_success_messages(), 'Duplicate rejection has no success message');
}

$invalid = array();
foreach (array('../unsafe', 'Uppercase', '', str_repeat('a', 65)) as $id) {
  $invalid['id:' . $id] = import_definition($id);
}
foreach (array(array('schema_version', 1), array('schema_version', '2'), array('schema_version', 3), array('id', 'different'),
  array('colors', array('--h5p-theme-background' => 'red')), array('colors', array('--h5p-theme-background' => 'var(--x)')),
  array('colors', array('--unsupported' => '#123')), array('label', ''), array('label', "bad\x00text"),
  array('description', "bad\x01text"), array('density', 'wide'), array('visible', TRUE), array('_config_static', 'true'),
  array('base_preset', array('id' => 'unknown', 'version' => 'moodle-478fed30d2a4')),
  array('base_preset', array('id' => 'mint', 'version' => 'latest')),
  array('base_preset', array('id' => 'mint', 'version' => 'moodle-478fed30d2a4', 'extra' => TRUE))) as $i => $change) {
  $invalid['field:' . $i] = array_replace(import_definition(), array($change[0] => $change[1]));
}
$invalid['missing pin'] = import_definition(); unset($invalid['missing pin']['base_preset']);
foreach (array_merge(array_values($invalid), array('null', 'false', '42', '[]', '{broken', '{"_config_name":[]}', '{"id":"imported"}')) as $i => $data) {
  import_fixture(); $bytes = H5PThemerMemoryStorage::$raw;
  $result = import_post($data);
  check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Invalid import rejected without writes: ' . $i);
}

foreach (array('native_permission', 'theme_permission', 'anonymous', 'incompatible', 'policy_override', 'theme_override', 'sync', 'activation', 'token') as $failure) {
  import_fixture();
  if ($failure === 'native_permission') { $native_config_allowed = FALSE; }
  if ($failure === 'theme_permission') { $admin_allowed = FALSE; }
  if ($failure === 'anonymous') { $user->uid = 0; }
  if ($failure === 'incompatible') { $h5p_enabled = FALSE; }
  if ($failure === 'policy_override') { $config['h5p_themer.learner_visibility']['themes'] = _h5p_themer_visibility_policy_read(FALSE)['data']['themes']; }
  if ($failure === 'theme_override') { $config['h5p_themer.theme.imported']['colors'] = array(); }
  if ($failure === 'sync') { state_set('config_sync', 1); }
  if ($failure === 'activation') { state_del('h5p_themer.visibility_activation'); }
  $bytes = H5PThemerMemoryStorage::$raw; $states = H5PVisibilityNativeFixture::$states;
  $result = import_post(import_definition(), TRUE, $failure !== 'token');
  check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes && H5PVisibilityNativeFixture::$states === $states, 'Access/publication rejection writes nothing: ' . $failure);
}

foreach (array('stored_legacy', 'stored_typed', 'effective_legacy', 'effective_typed') as $reference) {
  import_fixture(); $typed = strpos($reference, 'typed') !== FALSE;
  $settings = array('schema_version' => $typed ? 2 : 1, 'default_theme' => $typed ? array('kind' => 'custom', 'id' => 'imported') : 'imported');
  if (strpos($reference, 'stored') === 0) { seed('h5p_themer.settings', $settings); }
  else { $config['h5p_themer.settings'] = $settings; }
  $bytes = H5PThemerMemoryStorage::$raw; $result = import_post(import_definition());
  check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Unresolved Global Default reference prevents creation: ' . $reference);
}

foreach (array('duplicate', 'default', 'policy', 'permission', 'native_permission', 'input', 'candidate', 'lock') as $race) {
  import_fixture(); $validated = import_post(import_definition(), FALSE);
  check(!$validated['errors'], 'Race fixture initially validates: ' . $race);
  if ($race === 'duplicate') { seed('h5p_themer.theme.imported', fixture_theme('imported')); }
  if ($race === 'default') { seed('h5p_themer.settings', fixture_settings('imported')); }
  if ($race === 'policy') { import_policy('existing', FALSE); }
  if ($race === 'permission') { $admin_allowed = FALSE; }
  if ($race === 'native_permission') { $native_config_allowed = FALSE; }
  if ($race === 'input') { $validated['state']['values']['import'] = json_encode(import_definition('changed')); }
  if ($race === 'candidate') { $validated['state']['config_data']['label'] = 'Changed after validation'; }
  if ($race === 'lock') { $write_locked = TRUE; $visibility_test_lock_owned = FALSE; }
  $bytes = H5PThemerMemoryStorage::$raw;
  check((bool) import_submit_validated($validated) && H5PThemerMemoryStorage::$raw === $bytes, 'Submit rechecks dependencies under the lock: ' . $race);
  if ($race !== 'lock') { check(!$write_locked, 'Rejected locked submit releases lock: ' . $race); }
}
import_fixture(); $validated = import_post(import_definition(), FALSE);
$visibility_test_on_lock = function () { seed('h5p_themer.theme.imported', fixture_theme('imported')); };
check((bool) import_submit_validated($validated) && config('h5p_themer.theme.imported')->getOriginal('schema_version') === 1, 'Destination appearing at lock acquisition is never overwritten');

foreach (array('policy_save', 'policy_save_false', 'policy_save_after', 'policy_verify', 'theme_save', 'theme_save_false', 'theme_save_after', 'theme_verify') as $fault) {
  import_fixture(); import_policy('imported', TRUE);
  H5PVisibilityNativeFixture::$fault = $fault;
  $result = import_post(import_definition());
  check((bool) $result['errors'] && !import_success_messages() && !$write_locked, 'Storage failure prevents success and releases lock: ' . $fault);
  if (strpos($fault, 'policy') === 0) {
    check(!config_get_config_storage()->exists('h5p_themer.theme.imported'), 'Unverified policy prevents definition write: ' . $fault);
  }
  else { check(import_hidden(), 'Partial definition failure retains restrictive visibility: ' . $fault); }
  check(strpos(implode(' ', $result['errors']), 'may have been saved') !== FALSE, 'Partial failure reports uncertainty: ' . $fault);
  H5PVisibilityNativeFixture::$fault = '';
}
import_fixture(); import_policy('imported', FALSE); H5PVisibilityNativeFixture::$fault = 'policy_save_false';
$result = import_post(import_definition());
check((bool) $result['errors'] && !config_get_config_storage()->exists('h5p_themer.theme.imported'), 'Backend FALSE prevents definition write even with an already hidden tombstone');
import_fixture(); $visibility_test_lease_loss = 2; $bytes = H5PThemerMemoryStorage::$raw;
$result = import_post(import_definition());
check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes && !$write_locked, 'Lost lease before hiding prevents all writes');
import_fixture(); $visibility_test_lease_loss = 3;
$result = import_post(import_definition());
check((bool) $result['errors'] && import_hidden() && !config_get_config_storage()->exists('h5p_themer.theme.imported'), 'Lost lease after hiding retains tombstone and prevents definition write');

// Prove both successful and failing output invalidation using existing faults.
import_fixture(); H5PVisibilityNativeFixture::$fault = 'policy_verify';
$result = import_post(import_definition());
check((bool) $result['errors'] && $runtime_cache_invalidations === 1, 'Partial malformed policy invalidates previously delivered learner output');
import_fixture(); $validated = import_post(import_definition(), FALSE);
H5PThemerMemoryStorage::$beforeWrite = function ($name) {
  if ($name === 'h5p_themer.learner_visibility') {
    H5PThemerMemoryStorage::$raw['./active'][$name] = '{}';
    H5PVisibilityNativeFixture::$fault = 'output_cache';
    throw new ConfigStorageException('Interrupted policy persistence.');
  }
};
$errors = import_submit_validated($validated); H5PThemerMemoryStorage::$beforeWrite = NULL;
check(isset($errors['import_cache']) && !import_success_messages() && !$write_locked, 'Cache failure after a partial write reports uncertainty and releases lock');

import_fixture(); $policy = _h5p_themer_visibility_policy_read(FALSE)['data'];
$policy['_config_name'] = 'h5p_themer.learner_visibility'; $bytes = H5PThemerMemoryStorage::$raw;
$result = import_post($policy);
check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Visibility-policy single import remains blocked through the actual form');
// Generic create/update/delete hooks and mixed plans retain their stateless block.
require_once __DIR__ . '/config-protection.php';
import_fixture();
$data = array('_config_name' => 'h5p_themer.settings', 'schema_version' => 1, 'default_theme' => NULL);
$result = import_post($data);
check(!$result['errors'] && config('h5p_themer.settings')->getOriginal('default_theme') === NULL && $native_import_full_flushes === 1, 'Settings import delegates to native validation, persistence and full cache flush');
check(strpos($messages[0][1], 'imported successfully') !== FALSE, 'Delegated settings import retains native success message');
// config_get_info() accepts an injected unrelated owner without changing hooks.
$info = &backdrop_static('config_get_info');
$info['unrelated.settings'] = array('label' => 'Unrelated');
$admin_allowed = FALSE;
$data = array('_config_name' => 'unrelated.settings', 'enabled' => TRUE);
$result = import_post($data);
check(!$result['errors'] && import_stored_values(config('unrelated.settings')->getOriginal()) === import_stored_values($data) && $native_import_full_flushes === 2, 'Unrelated configuration uses unchanged native handlers');

// Exercise the same native Form API handler against actual ConfigFileStorage,
// confined to a new private temporary directory, never a site's active store.
import_fixture();
$import_native_root = sys_get_temp_dir() . '/h5p-themer-import-' . bin2hex(random_bytes(12));
if (!mkdir($import_native_root, 0700)) { throw new RuntimeException('Cannot create private import fixture.'); }
$import_native_root = realpath($import_native_root);
$previous_directories = $config_directories; $previous_directory = getcwd();
try {
  foreach (H5PThemerMemoryStorage::$raw['./active'] as $name => $json) {
    if (file_put_contents($import_native_root . '/' . $name . '.json', $json) === FALSE) { throw new RuntimeException('Cannot seed private import fixture.'); }
  }
  if (!chdir($import_native_root)) { throw new RuntimeException('Cannot enter private import fixture.'); }
  $config_directories['active'] = '.'; $GLOBALS['visibility_test_storage_class'] = 'ConfigFileStorage';
  backdrop_static('config', array(), TRUE);
  $data = import_definition(); $result = import_post($data);
  check(!$result['errors'] && import_hidden(), 'Actual native file storage imports a hidden schema-2 theme');
  check(json_decode(file_get_contents($data['_config_name'] . '.json'), TRUE) === $data, 'Actual file JSON preserves all supplied keys and values');
  $bytes = file_get_contents($data['_config_name'] . '.json');
  $result = import_post($data);
  check((bool) $result['errors'] && file_get_contents($data['_config_name'] . '.json') === $bytes, 'Actual file duplicate is rejected without overwrite');
  file_put_contents('h5p_themer.theme.broken.json', 'false');
  $result = import_post(import_definition('broken'));
  check((bool) $result['errors'] && file_get_contents('h5p_themer.theme.broken.json') === 'false', 'Actual malformed destination remains untouched');
}
finally {
  if (!chdir($previous_directory)) { throw new RuntimeException('Cannot restore test directory.'); }
  $config_directories = $previous_directories; unset($GLOBALS['visibility_test_storage_class']);
  backdrop_static('config', array(), TRUE);
  foreach (glob($import_native_root . '/*.json') as $file) {
    $resolved = realpath($file);
    if (!$resolved || is_link($file) || strcasecmp(str_replace('\\', '/', dirname($resolved)), str_replace('\\', '/', $import_native_root)) !== 0 || !unlink($resolved)) {
      throw new RuntimeException('Private import fixture cleanup failed.');
    }
  }
  if (!rmdir($import_native_root)) { throw new RuntimeException('Cannot remove private import fixture.'); }
}

admin_reset();
if ($import_standalone) {
  printf("PHP %s focused native theme import: %d passed, %d failed\n", PHP_VERSION, $passed, $failed);
  exit($failed ? 1 : 0);
}
