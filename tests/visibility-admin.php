<?php
/**
 * @file
 * Native Form API visibility controls; isolated Config/state and private files.
 * SPDX-License-Identifier: GPL-3.0-or-later.
 */
$visibility_admin_standalone = !function_exists('check');
if ($visibility_admin_standalone) {
  require __DIR__ . '/bootstrap.php'; require __DIR__ . '/admin-bootstrap.php';
  require_once $h5p_root . '/h5p.module';
  $mapping = h5p_autoload_info(); require_once $h5p_root . '/' . $mapping['H5PCore'];
}

function visibility_admin_fixture($activate = TRUE) {
  admin_reset();
  seed('h5p_themer.settings', array('schema_version' => 2, 'default_theme' => NULL, 'learner_selection_enabled' => TRUE));
  seed('h5p_themer.theme.legacy', fixture_theme('legacy'));
  $theme = fixture_theme('pinned'); $theme['schema_version'] = 2;
  $theme['base_preset'] = array('id' => 'mint', 'version' => 'moodle-478fed30d2a4');
  seed('h5p_themer.theme.pinned', $theme);
  if ($activate) { admin_publication_fixture(); }
  $GLOBALS['runtime_cache_invalidations'] = 0;
}
function visibility_admin_input(array $bundle) {
  $input = array('visibility' => array());
  $snapshot = $bundle[2]['h5p_themer_visibility_snapshot'];
  foreach ($snapshot['choices'] as $token => $entry) {
    if (!in_array($token, h5p_themer_visibility_fixed_tokens(), TRUE) && !empty($snapshot['publication']['policy']['themes'][$token])) {
      $input['visibility'][$token] = '1';
    }
  }
  return $input;
}
function visibility_admin_control(array $form, $token) {
  foreach (array('site_default', 'core', 'builtins', 'themes') as $table) {
    foreach ($form[$table]['#visibility_rows'] as $key => $row) {
      if ($form[$table][$key]['#parents'] === array('visibility', $token)) { return $form[$table][$key]; }
    }
  }
  return NULL;
}
function visibility_admin_definitions() {
  return array_filter(H5PThemerMemoryStorage::$raw['./active'], function ($key) { return strpos($key, 'h5p_themer.theme.') === 0; }, ARRAY_FILTER_USE_KEY);
}
visibility_admin_fixture();
$raw = H5PThemerMemoryStorage::$raw; $writes = H5PThemerMemoryStorage::$writes; $sw = H5PVisibilityNativeFixture::$stateWrites;
$bundle = admin_form('visibility'); $form = $bundle[1];
check($bundle[0] === 'h5p_themer_overview' && $form['actions']['submit']['#value'] === 'Save visibility', 'Existing overview is native explicit Save visibility form');
check(!$form['actions']['submit']['#disabled'] && $form['#method'] === 'post', 'Activated administrator can submit native POST form');
check(!isset($form['#attached']['js']), 'Visibility requires no JavaScript dependency');
check(H5PThemerMemoryStorage::$raw === $raw && H5PThemerMemoryStorage::$writes === $writes && H5PVisibilityNativeFixture::$stateWrites === $sw, 'Building overview performs no configuration/journal writes');
foreach (h5p_themer_visibility_fixed_tokens() as $token) {
  $control = visibility_admin_control($form, $token);
  check($control['#disabled'] && $control['#default_value'] === 1 && $control['#description'] === 'Always available.', 'Fixed choice checked and accessibly explained: ' . $token);
}
foreach (array('builtin:mint', 'custom:legacy', 'custom:pinned') as $token) {
  $control = visibility_admin_control($form, $token);
  check(!$control['#disabled'] && $control['#default_value'] === 1 && strpos($control['#title'], $token) !== FALSE, 'Initialized optional choice and theme-specific accessible label: ' . $token);
}
foreach (array('site_default', 'core', 'builtins', 'themes') as $table) {
  check(end($form[$table]['#header']) === 'Available to learners', 'Visibility column appended without disturbing existing headings: ' . $table);
}
$input = visibility_admin_input($bundle); unset($input['visibility']['builtin:mint'], $input['visibility']['custom:legacy']);
$defs = visibility_admin_definitions(); $settings = H5PThemerMemoryStorage::$raw['./active']['h5p_themer.settings'];
$result = admin_post($bundle, $input);
check(!$result['errors'] && isset($result['state']['redirect']), 'Native submission hides optional built-in and schema-1 custom');
$map = _h5p_themer_visibility_policy_read(FALSE)['data']['themes'];
check($map['builtin:mint'] === FALSE && $map['custom:legacy'] === FALSE && $map['custom:pinned'] === TRUE, 'Strict Boolean policy persisted with other choices preserved');
check($runtime_cache_invalidations === 1 && H5PThemerMemoryStorage::$writes === $writes + 1, 'One policy write and one relevant output invalidation');
check(visibility_admin_definitions() === $defs && H5PThemerMemoryStorage::$raw['./active']['h5p_themer.settings'] === $settings, 'Visibility save changes neither palettes/definitions nor global settings');
check(array_keys(h5p_themer_learner_catalog()['data']) === array('site_default', 'core', 'builtin:daylight', 'builtin:lavender', 'builtin:sunset', 'builtin:dark', 'custom:pinned'), 'All public projections receive the filtered catalog');
$processed = h5p_themer_visibility_table_pre_render($result['form']['builtins']);
check(count($processed['#rows'][0]) === 7 && $processed['#visibility_rows'] === array(), 'Pre-render moves processed checkbox into table cell');
check($processed['#rows'][0][6]['data']['#parents'] === array('visibility', 'builtin:daylight') && !isset($processed['visibility_0']), 'Rendered checkbox retains native processed identity without duplicate child');
check(h5p_themer_visibility_table_pre_render($processed) === $processed, 'Table pre-render is idempotent');
$cb = $processed['#rows'][0][6]['data']; $cb['#checked'] = TRUE;
$html = theme_checkbox(array('element' => $cb));
check(strpos($html, 'type="checkbox"') !== FALSE && strpos($html, 'checked="checked"') !== FALSE && strpos($html, 'id=') !== FALSE, 'Actual native checkbox renderer emits processed control identity');
$input = visibility_admin_input(admin_form('visibility')); $before = H5PThemerMemoryStorage::$writes; $cache_before = $runtime_cache_invalidations;
$result = admin_post(admin_form('visibility'), $input);
check(!$result['errors'] && H5PThemerMemoryStorage::$writes === $before && $runtime_cache_invalidations === $cache_before, 'Unchanged fresh form save performs no write or invalidation');
$input['visibility']['builtin:mint'] = '1'; $input['visibility']['custom:legacy'] = '1';
$result = admin_post(admin_form('visibility'), $input);
check(!$result['errors'] && isset(h5p_themer_learner_catalog()['data']['custom:legacy']) && isset(h5p_themer_learner_catalog()['data']['builtin:mint']), 'Optional built-in and custom can be explicitly published again');

visibility_admin_fixture();
$result = admin_post(admin_form('theme', 'preset_duplicate', 'mint', 'moodle-478fed30d2a4'), array('id' => 'new_copy', 'label' => 'New copy', 'description' => '', 'colors' => array()));
check(!$result['errors'] && visibility_admin_control(admin_form('visibility')[1], 'custom:new_copy')['#default_value'] === 0, 'New copied theme appears unchecked');
$bundle = admin_form('visibility'); $input = visibility_admin_input($bundle);
$bytes = H5PThemerMemoryStorage::$raw; $before = H5PThemerMemoryStorage::$writes;
$result = admin_post($bundle, $input);
check(!$result['errors'] && H5PThemerMemoryStorage::$raw === $bytes && H5PThemerMemoryStorage::$writes === $before, 'Unchecked new hidden theme remains a no-op save');
seed('h5p_themer.theme.implicit_hidden', fixture_theme('implicit_hidden'));
$bundle = admin_form('visibility'); $before = H5PThemerMemoryStorage::$writes;
$result = admin_post($bundle, visibility_admin_input($bundle));
check(!$result['errors'] && H5PThemerMemoryStorage::$writes === $before, 'Implicit false missing entry does not cause unnecessary write');
$policy = _h5p_themer_visibility_policy_read(FALSE)['data']; $policy['themes']['custom:pinned'] = FALSE; seed('h5p_themer.learner_visibility', $policy);
config('h5p_themer.settings')->set('default_theme', array('kind' => 'custom', 'id' => 'pinned'))->save();
check(!isset(h5p_themer_learner_catalog()['data']['custom:pinned']) && h5p_themer_runtime_css()['data'] !== '', 'Hidden global pinned theme remains effective through Site default');
check(isset(admin_form('theme', 'edit', 'pinned')[1]['saved_preview']) && isset(h5p_themer_preset_view('mint', 'moodle-478fed30d2a4')['saved_preview']), 'Hidden custom/built-in previews remain authorized and available');

foreach (array('site_default', 'core', 'builtin:daylight', 'builtin:forged', 'custom:missing', '../bad') as $token) {
  visibility_admin_fixture(); $bundle = admin_form('visibility'); $input = visibility_admin_input($bundle); $input['visibility'][$token] = '0';
  $bytes = H5PThemerMemoryStorage::$raw; $result = admin_post($bundle, $input);
  check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Forged token or attempted fixed-choice removal rejected: ' . $token);
}
foreach (array(0, '0', TRUE, FALSE, '', 'true', 'on', array('1'), 1.0) as $bad) {
  visibility_admin_fixture(); $bundle = admin_form('visibility'); $input = visibility_admin_input($bundle); $input['visibility']['builtin:mint'] = $bad;
  $bytes = H5PThemerMemoryStorage::$raw; $result = admin_post($bundle, $input);
  check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Malformed raw checkbox value rejected without writes');
}
foreach (array('invalid_group', 'extra_field', 'csrf') as $bad) {
  visibility_admin_fixture(); $bundle = admin_form('visibility'); $input = visibility_admin_input($bundle);
  if ($bad === 'invalid_group') { $input['visibility'] = 'invalid'; }
  if ($bad === 'extra_field') { $input['unexpected'] = '1'; }
  $bytes = H5PThemerMemoryStorage::$raw; $result = admin_post($bundle, $input, $bad !== 'csrf');
  check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Malformed/CSRF form rejected: ' . $bad);
}
foreach (array('legacy', 'witness', 'witness_read', 'journal', 'pending', 'policy', 'override', 'theme_override', 'settings_override', 'sync', 'permission', 'lock', 'stale_policy', 'new_theme', 'theme_edit', 'theme_delete', 'invalid_theme_edit', 'settings_edit') as $failure) {
  visibility_admin_fixture();
  seed('h5p_themer.theme.broken', array('schema_version' => 1));
  $bundle = admin_form('visibility'); $input = visibility_admin_input($bundle); unset($input['visibility']['builtin:mint']);
  if ($failure === 'legacy') { H5PVisibilityNativeFixture::$schema = 0; state_del('h5p_themer.visibility_activation'); }
  if ($failure === 'witness') { H5PVisibilityNativeFixture::$schema = NULL; }
  if ($failure === 'witness_read') { H5PVisibilityNativeFixture::$fault = 'witness_read'; }
  if ($failure === 'journal') { state_del('h5p_themer.visibility_activation'); }
  if ($failure === 'pending') { $j = state_get('h5p_themer.visibility_activation'); $j['cache_pending'] = TRUE; state_set('h5p_themer.visibility_activation', $j); }
  if ($failure === 'policy') { H5PThemerMemoryStorage::$raw['./active']['h5p_themer.learner_visibility'] = 'false'; }
  if ($failure === 'override') { $GLOBALS['config']['h5p_themer.learner_visibility'] = array('themes' => array()); }
  if ($failure === 'theme_override') { $GLOBALS['config']['h5p_themer.theme.pinned'] = array('label' => 'Overridden'); }
  if ($failure === 'settings_override') { $GLOBALS['config']['h5p_themer.settings'] = array('learner_selection_enabled' => FALSE); }
  if ($failure === 'sync') { state_set('config_sync', 1); }
  if ($failure === 'permission') { $admin_allowed = FALSE; }
  if ($failure === 'lock') { $write_locked = TRUE; $visibility_test_lock_owned = FALSE; }
  if ($failure === 'stale_policy') { config('h5p_themer.learner_visibility')->set('themes.custom:legacy', FALSE)->save(); }
  if ($failure === 'new_theme') { seed('h5p_themer.theme.new_theme', fixture_theme('new_theme')); }
  if ($failure === 'theme_edit') { config('h5p_themer.theme.pinned')->set('colors', array('--h5p-theme-background' => '#ABCDEF'))->save(); }
  if ($failure === 'theme_delete') { config('h5p_themer.theme.pinned')->delete(); }
  if ($failure === 'invalid_theme_edit') { seed('h5p_themer.theme.broken', array('schema_version' => 2)); }
  if ($failure === 'settings_edit') { config('h5p_themer.settings')->set('default_theme', array('kind' => 'builtin', 'id' => 'mint', 'version' => 'moodle-478fed30d2a4'))->save(); }
  $bytes = H5PThemerMemoryStorage::$raw; $sw = H5PVisibilityNativeFixture::$stateWrites;
  $result = admin_post($bundle, $input);
  check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes && H5PVisibilityNativeFixture::$stateWrites === $sw, 'Guard/stale catalog rejects mutation: ' . $failure);
}
visibility_admin_fixture(); $admin_allowed = FALSE;
check(!isset(admin_form('visibility')[1]['actions']), 'Unauthorized viewer receives no visibility controls or action');
visibility_admin_fixture(FALSE); $bundle = admin_form('visibility');
check($bundle[1]['actions']['submit']['#disabled'] && isset($bundle[1]['visibility_diagnostics']), 'Incomplete activation is read-only with visible diagnostics');

foreach (array('policy_save', 'policy_save_false', 'policy_save_after', 'policy_verify', 'output_cache') as $fault) {
  visibility_admin_fixture(); $bundle = admin_form('visibility'); $input = visibility_admin_input($bundle); unset($input['visibility']['builtin:mint']);
  $defs = visibility_admin_definitions(); $settings = H5PThemerMemoryStorage::$raw['./active']['h5p_themer.settings'];
  H5PVisibilityNativeFixture::$fault = $fault; $result = admin_post($bundle, $input);
  check((bool) $result['errors'] && !isset($result['state']['redirect']), 'Persistence/cache failure never claims success: ' . $fault);
  check(visibility_admin_definitions() === $defs && H5PThemerMemoryStorage::$raw['./active']['h5p_themer.settings'] === $settings, 'Failure leaves definitions, palette and global settings unchanged');
  check(!$write_locked, 'Failure releases shared lock');
  if ($fault === 'policy_save_after' || $fault === 'output_cache') { check(!isset(h5p_themer_learner_catalog()['data']['builtin:mint']), 'Partial persisted hide is not automatically restored'); }
  if ($fault === 'policy_verify') { check(count(h5p_themer_learner_catalog()['data']) === 3 && $runtime_cache_invalidations === 1, 'Malformed partial policy fails closed and invalidates stale output'); }
  H5PVisibilityNativeFixture::$fault = '';
}
visibility_admin_fixture(); $bundle = admin_form('visibility');
$input = visibility_admin_input($bundle); unset($input['visibility']['builtin:mint']);
$visibility_test_lock_calls = 0; $visibility_test_lease_loss = 2; $bytes = H5PThemerMemoryStorage::$raw;
$result = admin_post($bundle, $input);
check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Lost write lease blocks persistence');

visibility_admin_fixture();
seed('h5p_themer.theme.broken', array('schema_version' => 1));
$overview = admin_form('visibility')[1];
check(count($overview['themes']['#rows']) === 3 && end($overview['themes']['#rows'][2]) === 'Unavailable: invalid theme.', 'Invalid theme row and diagnostic remain present without a publication checkbox');
check(array_keys($overview['builtins']['#rows'][0][5]['data']['#links']) === array('view', 'duplicate') &&
  array_keys($overview['themes']['#rows'][0][4]['data']['#links']) === array('edit', 'duplicate', 'delete'), 'Existing operations remain in place alongside final visibility column');
$routes = h5p_themer_menu();
check($routes['admin/config/content/h5p-themer']['page callback'] === 'backdrop_get_form' &&
  $routes['admin/config/content/h5p-themer/list']['page arguments'] === array('h5p_themer_overview'), 'Both existing overview routes invoke native Form API');
$bundle = admin_form('visibility'); $input = visibility_admin_input($bundle); unset($input['visibility']['builtin:mint']);
$result = admin_post($bundle, $input, TRUE, FALSE);
check(!$result['errors'] && _h5p_themer_visibility_policy_read(FALSE)['data']['themes']['builtin:mint'] === TRUE, 'Checkbox changes without explicit submit do not save');
$state = $bundle[2]; $state['values'] = array('visibility' => array()); $state['input'] = array('visibility' => array());
$admin_allowed = FALSE; form_clear_error(); $before = H5PThemerMemoryStorage::$raw;
h5p_themer_visibility_form_submit($bundle[1], $state);
check((bool) form_get_errors() && H5PThemerMemoryStorage::$raw === $before, 'Direct submit revalidates permission even if validation was bypassed');

visibility_admin_fixture();
seed('h5p_themer.theme.escaped', array_replace(fixture_theme('escaped'), array('label' => '<script>alert(1)</script>')));
$bundle = admin_form('visibility'); $control = visibility_admin_control($bundle[1], 'custom:escaped');
check(strpos($control['#title'], '<script>') === FALSE && strpos($control['#title'], '&lt;script&gt;') !== FALSE, 'Theme-specific accessible title escapes hostile theme labels');
$state = $bundle[2]; $state['input'] = array('visibility' => array()); $state['values'] = array('visibility' => array_fill_keys(array_keys($state['h5p_themer_visibility_snapshot']['choices']), 1));
form_clear_error(); h5p_themer_visibility_form_validate($bundle[1], $state);
check((bool) form_get_errors(), 'Direct callback rejects processed checkbox values inconsistent with raw input');


foreach (array('policy', 'catalog') as $concurrent) {
  visibility_admin_fixture(); $bundle = admin_form('visibility'); $input = visibility_admin_input($bundle);
  unset($input['visibility']['builtin:mint']); $external_bytes = NULL;
  $GLOBALS['visibility_test_on_lock'] = function () use ($concurrent, &$external_bytes) {
    if ($concurrent === 'policy') { config('h5p_themer.learner_visibility')->set('themes.custom:legacy', FALSE)->save(); }
    else { config('h5p_themer.theme.pinned')->set('label', 'Changed before lock')->save(); }
    $external_bytes = H5PThemerMemoryStorage::$raw;
  };
  $result = admin_post($bundle, $input);
  check((bool) $result['errors'] && $external_bytes !== NULL && H5PThemerMemoryStorage::$raw === $external_bytes, 'Under-lock revalidation preserves concurrent change after initial validation: ' . $concurrent);
}

// Actual native file persistence, with no site settings or database bootstrap.
visibility_admin_fixture();
$visibility_admin_root = sys_get_temp_dir() . '/h5p-themer-visibility-admin-' . bin2hex(random_bytes(12));
if (!mkdir($visibility_admin_root, 0700)) { throw new RuntimeException('Cannot create private visibility fixture.'); }
$visibility_admin_root = realpath($visibility_admin_root);
$previous_directories = $config_directories; $previous_directory = getcwd();
try {
  foreach (H5PThemerMemoryStorage::$raw['./active'] as $name => $json) {
    if (file_put_contents($visibility_admin_root . '/' . $name . '.json', $json) === FALSE) { throw new RuntimeException('Cannot seed private visibility fixture.'); }
  }
  if (!chdir($visibility_admin_root)) { throw new RuntimeException('Cannot enter private fixture.'); }
  $config_directories['active'] = '.'; $GLOBALS['visibility_test_storage_class'] = 'ConfigFileStorage';
  backdrop_static('config', array(), TRUE);
  $native_before = array(); foreach (glob('*.json') as $file) { $native_before[$file] = hash_file('sha256', $file); }
  $bundle = admin_form('visibility'); $input = visibility_admin_input($bundle); unset($input['visibility']['builtin:mint'], $input['visibility']['custom:pinned']);
  $result = admin_post($bundle, $input);
  check(!$result['errors'] && _h5p_themer_visibility_policy_read(FALSE)['data']['themes']['builtin:mint'] === FALSE &&
    _h5p_themer_visibility_policy_read(FALSE)['data']['themes']['custom:pinned'] === FALSE, 'Actual ConfigFileStorage saves and verifies built-in/pinned visibility');
  $native_changed = array();
  foreach ($native_before as $file => $hash) { if (hash_file('sha256', $file) !== $hash) { $native_changed[] = $file; } }
  check($native_changed === array('h5p_themer.learner_visibility.json'), 'Native file-backed save changes only policy bytes');
  $native_policy = file_get_contents('h5p_themer.learner_visibility.json');
  $bundle = admin_form('visibility'); $result = admin_post($bundle, visibility_admin_input($bundle));
  check(!$result['errors'] && file_get_contents('h5p_themer.learner_visibility.json') === $native_policy, 'Native file-backed no-op preserves exact policy bytes');
}
finally {
  if (!chdir($previous_directory)) { throw new RuntimeException('Cannot restore test directory.'); }
  $config_directories = $previous_directories; unset($GLOBALS['visibility_test_storage_class']);
  backdrop_static('config', array(), TRUE);
  foreach (glob($visibility_admin_root . '/*.json') as $file) {
    $real = realpath($file);
    if (!$real || strpos(str_replace('\\', '/', $real), str_replace('\\', '/', $visibility_admin_root) . '/') !== 0 || !unlink($real)) { throw new RuntimeException('Private fixture cleanup failed.'); }
  }
  if (!rmdir($visibility_admin_root)) { throw new RuntimeException('Cannot remove private fixture.'); }
}

admin_reset();
if ($visibility_admin_standalone) {
  printf("PHP %s focused visibility administration: %d passed, %d failed\n", PHP_VERSION, $passed, $failed);
  exit($failed ? 1 : 0);
}
