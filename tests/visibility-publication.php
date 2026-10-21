<?php
/**
 * @file
 * Native Form API/Config publication ordering; isolated storage, no live services.
 * SPDX-License-Identifier: GPL-3.0-or-later.
 */
$publication_standalone = !function_exists('check');
if ($publication_standalone) {
  require __DIR__ . '/bootstrap.php'; require __DIR__ . '/admin-bootstrap.php';
  require_once $h5p_root . '/h5p.module';
  $mapping = h5p_autoload_info(); require_once $h5p_root . '/' . $mapping['H5PCore'];
}

function publication_fixture($activate = TRUE) {
  admin_reset();
  seed('h5p_themer.settings', array('schema_version' => 2, 'default_theme' => NULL, 'learner_selection_enabled' => TRUE));
  seed('h5p_themer.theme.legacy', fixture_theme('legacy'));
  $pinned = fixture_theme('pinned'); $pinned['schema_version'] = 2;
  $pinned['base_preset'] = array('id' => 'mint', 'version' => 'moodle-478fed30d2a4');
  seed('h5p_themer.theme.pinned', $pinned);
  if ($activate) {
    if (!_h5p_themer_visibility_prepare('upgrade')) { throw new RuntimeException('Fixture preparation failed.'); }
    H5PVisibilityNativeFixture::$schema = 1000;
    if (!h5p_themer_visibility_initialize()['ok']) { throw new RuntimeException('Fixture activation failed.'); }
  }
  $GLOBALS['runtime_cache_invalidations'] = 0;
}
function publication_input($id = 'new_theme') {
  return array('id' => $id, 'label' => 'New theme', 'description' => '', 'density' => 'medium',
    'colors' => array('--h5p-theme-background' => '#336699'));
}
function publication_policy() {
  return _h5p_themer_visibility_policy_read(FALSE)['data'];
}
function publication_visible($id, $visible) {
  $policy = publication_policy(); $policy['themes']['custom:' . $id] = $visible;
  seed('h5p_themer.learner_visibility', $policy);
}
function publication_hidden($id) {
  return publication_policy()['themes']['custom:' . $id] === FALSE &&
    !isset(h5p_themer_learner_catalog()['data']['custom:' . $id]);
}

foreach (array(array('add', NULL), array('duplicate', 'legacy'), array('duplicate', 'pinned'), array('preset_duplicate', 'mint')) as $case) {
  publication_fixture();
  $definition_bytes = H5PThemerMemoryStorage::$raw['./active']['h5p_themer.theme.legacy'];
  $source_policy = publication_policy(); $settings_bytes = H5PThemerMemoryStorage::$raw['./active']['h5p_themer.settings'];
  $input = publication_input();
  if ($case[0] === 'preset_duplicate') { $input['colors'] = array(); }
  if ($case[0] === 'duplicate') { $input['colors'] = fixture_theme($case[1])['colors']; }
  $bundle = admin_form('theme', $case[0], $case[1], 'moodle-478fed30d2a4');
  $writes = H5PThemerMemoryStorage::$writes; $cache_before = $runtime_cache_invalidations;
  $result = admin_post($bundle, $input);
  check(!$result['errors'] && isset($result['state']['redirect']), 'Verified creation succeeds: ' . $case[0] . '/' . $case[1]);
  check(publication_hidden('new_theme'), 'New destination hidden independently of public source: ' . $case[0] . '/' . $case[1]);
  check(H5PThemerMemoryStorage::$writes === $writes + 2, 'Restrictive policy then definition are the only config writes');
  $theme = h5p_themer_theme_load('new_theme')['data'];
  check($theme['colors'] === $input['colors'], 'Creation does not invent or normalize palette values');
  check($theme['schema_version'] === ($case[0] === 'preset_duplicate' || $case[1] === 'pinned' ? 2 : 1), 'Source schema/pinned inheritance preserved');
  if ($theme['schema_version'] === 2) { check($theme['base_preset'] === array('id' => 'mint', 'version' => 'moodle-478fed30d2a4'), 'Exact pinned base preserved'); }
  check(publication_policy()['themes']['custom:legacy'] === $source_policy['themes']['custom:legacy'] &&
    H5PThemerMemoryStorage::$raw['./active']['h5p_themer.theme.legacy'] === $definition_bytes, 'Source publication and definition unchanged');
  check(H5PThemerMemoryStorage::$raw['./active']['h5p_themer.settings'] === $settings_bytes, 'Global selection/settings unchanged');
  check($runtime_cache_invalidations === $cache_before, 'Hidden unselected creation needs no output invalidation');
  $public = h5p_themer_learner_bundle()['data'];
  check(!isset($public['keys']['custom:new_theme']), 'Public CSS/options/key projection excludes new hidden destination');
}
publication_fixture(); publication_visible('legacy', FALSE);
$result = admin_post(admin_form('theme', 'duplicate', 'legacy'), publication_input('hidden_copy'));
check(!$result['errors'] && publication_hidden('hidden_copy'), 'Hidden custom source is copied without publication inheritance');

foreach (array(TRUE, FALSE) as $visible) {
  publication_fixture(); publication_visible('legacy', $visible);
  $bytes = H5PThemerMemoryStorage::$raw['./active']['h5p_themer.learner_visibility'];
  $input = publication_input('legacy');
  $result = admin_post(admin_form('theme', 'edit', 'legacy'), $input);
  check(!$result['errors'] && h5p_themer_theme_load('legacy')['data']['colors'] === array_merge(fixture_theme('legacy')['colors'], $input['colors']), 'Editing applies only validated definition changes');
  check(H5PThemerMemoryStorage::$raw['./active']['h5p_themer.learner_visibility'] === $bytes, 'Edit preserves exact visibility policy bytes for true/false state');
}
publication_fixture();
$result = admin_post(admin_form('delete', 'legacy'), array('confirm' => 1));
check(!$result['errors'] && config_get_config_storage()->exists('h5p_themer.theme.legacy') === FALSE, 'Deletion is verified against storage');
check(publication_hidden('legacy') && $runtime_cache_invalidations === 1, 'Deleted public theme leaves false tombstone and invalidates output once');
$result = admin_post(admin_form('theme'), publication_input('legacy'));
check(!$result['errors'] && publication_hidden('legacy'), 'Deleted machine ID reuse remains hidden');
publication_fixture(); publication_visible('reused', TRUE);
$result = admin_post(admin_form('theme'), publication_input('reused'));
check(!$result['errors'] && publication_hidden('reused'), 'Stale true entry cannot publish reused ID');

foreach (array('policy_save', 'policy_save_false', 'policy_save_after', 'policy_verify') as $fault) {
  publication_fixture(); publication_visible('new_theme', TRUE);
  $bundle = admin_form('theme'); H5PVisibilityNativeFixture::$fault = $fault;
  $result = admin_post($bundle, publication_input());
  check((bool) $result['errors'] && !isset($result['state']['redirect']), 'Policy write/read-back failure blocks success: ' . $fault);
  check(!config_get_config_storage()->exists('h5p_themer.theme.new_theme'), 'Policy failure prevents definition save: ' . $fault);
  check(!$write_locked, 'Policy failure releases shared lock');
  H5PVisibilityNativeFixture::$fault = '';
}
foreach (array('theme_save', 'theme_save_false', 'theme_save_after', 'theme_verify') as $fault) {
  publication_fixture(); $bundle = admin_form('theme'); H5PVisibilityNativeFixture::$fault = $fault;
  $result = admin_post($bundle, publication_input());
  check((bool) $result['errors'] && !isset($result['state']['redirect']), 'Definition failure/read-back mismatch is reported: ' . $fault);
  check(publication_hidden('new_theme'), 'Failed definition remains hidden without restoring true: ' . $fault);
  check(strpos(implode(' ', $result['errors']), 'may have been saved') !== FALSE, 'Partial-save diagnostic acknowledges possible writes');
  check(!$write_locked, 'Definition failure releases shared lock');
  H5PVisibilityNativeFixture::$fault = '';
}
foreach (array('throw', 'theme_delete_false', 'theme_delete_after') as $fault) {
  publication_fixture(); $bundle = admin_form('delete', 'legacy');
  if ($fault === 'throw') { H5PThemerMemoryStorage::$failDelete = TRUE; }
  else { H5PVisibilityNativeFixture::$fault = $fault; }
  $result = admin_post($bundle, array('confirm' => 1));
  check((bool) $result['errors'] && !isset($result['state']['redirect']), 'Delete failure/ignored backend FALSE is detected: ' . $fault);
  check(publication_hidden('legacy'), 'Failed deletion leaves restrictive tombstone: ' . $fault);
  check($runtime_cache_invalidations === 1, 'Partial deletion still invalidates changed public output');
  check(!$write_locked, 'Failed deletion releases lock');
  H5PVisibilityNativeFixture::$fault = ''; H5PThemerMemoryStorage::$failDelete = FALSE;
}
publication_fixture(); $bundle = admin_form('delete', 'legacy');
H5PVisibilityNativeFixture::$fault = 'policy_save';
$result = admin_post($bundle, array('confirm' => 1));
check((bool) $result['errors'] && config_get_config_storage()->exists('h5p_themer.theme.legacy'), 'Policy failure prevents deletion');
H5PVisibilityNativeFixture::$fault = '';

foreach (array('legacy', 'missing_witness', 'unreadable_witness', 'journal_absent', 'journal_pending', 'journal_malformed', 'policy_absent', 'policy_malformed', 'override', 'sync', 'permission', 'lock', 'stale_policy', 'stale_journal', 'stale_definition') as $failure) {
  foreach (array('add', 'edit', 'delete') as $operation) {
    publication_fixture();
    $bundle = $operation === 'delete' ? admin_form('delete', 'legacy') : admin_form('theme', $operation, $operation === 'edit' ? 'legacy' : NULL);
    if ($failure === 'legacy') { H5PVisibilityNativeFixture::$schema = 0; state_del('h5p_themer.visibility_activation'); config('h5p_themer.learner_visibility')->delete(); }
    if ($failure === 'missing_witness') { H5PVisibilityNativeFixture::$schema = NULL; }
    if ($failure === 'unreadable_witness') { H5PVisibilityNativeFixture::$fault = 'witness_read'; }
    if ($failure === 'journal_absent') { state_del('h5p_themer.visibility_activation'); }
    if ($failure === 'journal_pending' || $failure === 'stale_journal') {
      $j = state_get('h5p_themer.visibility_activation');
      if ($failure === 'journal_pending') { $j['status'] = 'pending'; $j['cache_pending'] = TRUE; }
      else { $j['operation'] = str_repeat('a', 32); }
      state_set('h5p_themer.visibility_activation', $j);
    }
    if ($failure === 'journal_malformed') { state_set('h5p_themer.visibility_activation', FALSE); }
    if ($failure === 'policy_absent') { config('h5p_themer.learner_visibility')->delete(); }
    if ($failure === 'policy_malformed') { H5PThemerMemoryStorage::$raw['./active']['h5p_themer.learner_visibility'] = 'false'; }
    if ($failure === 'override') { $GLOBALS['config']['h5p_themer.learner_visibility'] = array('themes' => publication_policy()['themes']); }
    if ($failure === 'sync') { state_set('config_sync', 1); }
    if ($failure === 'permission') { $admin_allowed = FALSE; }
    if ($failure === 'lock') { $write_locked = TRUE; $visibility_test_lock_owned = FALSE; }
    if ($failure === 'stale_policy') { publication_visible('legacy', FALSE); }
    if ($failure === 'stale_definition') {
      if ($operation === 'add') { seed('h5p_themer.theme.new_theme', fixture_theme('new_theme')); }
      else { config('h5p_themer.theme.legacy')->set('label', 'Concurrent edit')->save(); }
    }
    $bytes = H5PThemerMemoryStorage::$raw; $states = H5PVisibilityNativeFixture::$stateWrites;
    $result = admin_post($bundle, $operation === 'delete' ? array('confirm' => 1) : publication_input($operation === 'edit' ? 'legacy' : 'new_theme'));
    check((bool) $result['errors'], 'Publication guard rejects ' . $operation . ': ' . $failure);
    check(H5PThemerMemoryStorage::$raw === $bytes && H5PVisibilityNativeFixture::$stateWrites === $states, 'Rejected mutation makes no config/journal writes: ' . $operation . '/' . $failure);
  }
}
publication_fixture(FALSE);
$bytes = H5PThemerMemoryStorage::$raw; $writes = H5PVisibilityNativeFixture::$stateWrites;
$state = form_state_defaults(); $form = h5p_themer_theme_form(array(), $state);
check(!empty($form['actions']['submit']['#disabled']), 'Legacy activation cannot enable mutation in an ordinary form build');
check(H5PThemerMemoryStorage::$raw === $bytes && H5PVisibilityNativeFixture::$stateWrites === $writes, 'Actual production form reads do not initialize or persist policy');
publication_fixture(); publication_visible('pinned', FALSE);
config('h5p_themer.settings')->set('default_theme', array('kind' => 'custom', 'id' => 'pinned'))->save();
$resolved = h5p_themer_runtime_css()['data']; $bundle = h5p_themer_learner_bundle()['data'];
check(!isset($bundle['keys']['custom:pinned']) && $resolved !== '' && strpos($bundle['css'], '#006FBF') !== FALSE, 'Hidden pinned custom global theme remains effective through Site default');
$bytes = H5PThemerMemoryStorage::$raw;
$result = admin_post(admin_form('delete', 'pinned'), array('confirm' => 1));
check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Hidden global default remains protected against deletion');
publication_fixture(); $bundle = admin_form('theme'); $visibility_test_lock_calls = 0; $visibility_test_lease_loss = 2;
$bytes = H5PThemerMemoryStorage::$raw; $result = admin_post($bundle, publication_input());
check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Lease loss before policy write prevents all mutation');
publication_fixture(); $bundle = admin_form('delete', 'legacy'); H5PVisibilityNativeFixture::$fault = 'output_cache';
$result = admin_post($bundle, array('confirm' => 1));
check((bool) $result['errors'] && !isset($result['state']['redirect']) && publication_hidden('legacy'), 'Cache failure reports partial hidden deletion without success');
check(!$write_locked, 'Cache failure releases lock');
H5PVisibilityNativeFixture::$fault = '';

publication_fixture(); publication_visible('new_theme', FALSE);
$bundle = admin_form('theme'); H5PVisibilityNativeFixture::$fault = 'policy_save_false';
$result = admin_post($bundle, publication_input());
check((bool) $result['errors'] && !config_get_config_storage()->exists('h5p_themer.theme.new_theme'), 'Backend FALSE blocks creation even when tombstone was already false');
H5PVisibilityNativeFixture::$fault = '';
publication_fixture(); $bundle = admin_form('delete', 'legacy');
H5PVisibilityNativeFixture::$fault = 'policy_verify';
$result = admin_post($bundle, array('confirm' => 1));
check((bool) $result['errors'] && config_get_config_storage()->exists('h5p_themer.theme.legacy'), 'Corrupted restrictive policy prevents definition deletion');
check($runtime_cache_invalidations === 1 && count(h5p_themer_learner_catalog()['data']) === 3, 'Malformed partial policy invalidates previously public output and fails closed');
H5PVisibilityNativeFixture::$fault = '';
publication_fixture(); $bundle = admin_form('theme', 'edit', 'legacy');
$policy_bytes = H5PThemerMemoryStorage::$raw['./active']['h5p_themer.learner_visibility'];
H5PVisibilityNativeFixture::$fault = 'theme_save';
$result = admin_post($bundle, publication_input('legacy'));
check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw['./active']['h5p_themer.learner_visibility'] === $policy_bytes, 'Failed editing never rewrites existing publication state');
H5PVisibilityNativeFixture::$fault = '';
publication_fixture();
$bundle = admin_form('theme'); $journal = state_get('h5p_themer.visibility_activation');
$journal['failure'] = 'interrupted'; state_set('h5p_themer.visibility_activation', $journal);
$result = admin_post($bundle, publication_input());
check((bool) $result['errors'] && !config_get_config_storage()->exists('h5p_themer.theme.new_theme'), 'Contradictory completed failure journal cannot authorize mutation');

// Exercise native file write/read/delete, not only a normalized memory object.
// Only this unlink collaborator is substituted; it is confined to our fixture.
function backdrop_unlink($path, $context = NULL) {
  $root = $GLOBALS['publication_native_root'];
  $real = realpath($path);
  if (!$real || strpos(str_replace('\\', '/', $real), str_replace('\\', '/', $root) . '/') !== 0) {
    throw new LogicException('Unlink outside private publication fixture.');
  }
  return unlink($real);
}
publication_fixture();
$publication_native_root = sys_get_temp_dir() . '/h5p-themer-publication-' . bin2hex(random_bytes(12));
if (!mkdir($publication_native_root, 0700)) { throw new RuntimeException('Cannot create native publication fixture.'); }
$publication_native_root = realpath($publication_native_root);
$previous_directories = $config_directories; $previous_directory = getcwd();
try {
  foreach (H5PThemerMemoryStorage::$raw['./active'] as $name => $json) {
    if (file_put_contents($publication_native_root . '/' . $name . '.json', $json) === FALSE) { throw new RuntimeException('Cannot seed native fixture.'); }
  }
  // Core treats a drive-letter path as site-relative; use its dot-relative
  // contract inside the private fixture, as in the malformed-policy tests.
  if (!chdir($publication_native_root)) { throw new RuntimeException('Cannot enter private fixture.'); }
  $config_directories['active'] = '.';
  $GLOBALS['visibility_test_storage_class'] = 'ConfigFileStorage';
  backdrop_static('config', array(), TRUE);
  $result = admin_post(admin_form('theme', 'preset_duplicate', 'mint', 'moodle-478fed30d2a4'), array_replace(publication_input('native_copy'), array('colors' => array())));
  check(!$result['errors'] && publication_hidden('native_copy'), 'Actual ConfigFileStorage creates hidden pinned copy');
  $raw_policy = file_get_contents($publication_native_root . '/h5p_themer.learner_visibility.json');
  $input = publication_input('native_copy');
  $result = admin_post(admin_form('theme', 'edit', 'native_copy'), $input);
  check(!$result['errors'] && file_get_contents($publication_native_root . '/h5p_themer.learner_visibility.json') === $raw_policy, 'Actual file-backed edit preserves policy bytes and pinned schema');
  check(h5p_themer_theme_load('native_copy')['data']['base_preset'] === array('id' => 'mint', 'version' => 'moodle-478fed30d2a4'), 'Native file edit retains exact pinned base');
  $result = admin_post(admin_form('delete', 'native_copy'), array('confirm' => 1));
  check(!$result['errors'] && !file_exists($publication_native_root . '/h5p_themer.theme.native_copy.json') && publication_hidden('native_copy'), 'Actual file-backed deletion verifies absence and retains false tombstone');
}
finally {
  if (!chdir($previous_directory)) { throw new RuntimeException('Cannot restore test directory.'); }
  $config_directories = $previous_directories;
  unset($GLOBALS['visibility_test_storage_class']);
  backdrop_static('config', array(), TRUE);
  foreach (glob($publication_native_root . '/*.json') as $file) { backdrop_unlink($file); }
  if (!rmdir($publication_native_root)) { throw new RuntimeException('Native fixture cleanup failed.'); }
  unset($GLOBALS['publication_native_root']);
}

admin_reset();
if ($publication_standalone) {
  printf("PHP %s focused publication: %d passed, %d failed\n", PHP_VERSION, $passed, $failed);
  exit($failed ? 1 : 0);
}
