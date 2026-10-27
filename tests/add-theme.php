<?php
/**
 * Add initialization and snapshot regressions through native Form API/Config.
 * Standalone also runs existing admin/copy/publication compatibility checks.
 * No site bootstrap, database, installed-module writes or network access.
 * SPDX-License-Identifier: GPL-3.0-or-later.
 */
$add_standalone = !function_exists('check');
if ($add_standalone) {
  require __DIR__ . '/bootstrap.php';
  require_once $h5p_root . '/h5p.module';
  $mapping = h5p_autoload_info(); require_once $h5p_root . '/' . $mapping['H5PCore'];
  require __DIR__ . '/admin.php';
  require __DIR__ . '/config-compatibility.php';
  require __DIR__ . '/preset-admin.php';
  require __DIR__ . '/form-enhancements.php';
}
$add_before = $passed;
$add_version = 'moodle-478fed30d2a4';

function add_theme_fixture($schema = 2, $settings_schema = 2) {
  admin_reset(); admin_publication_fixture();
  $theme = array_replace(fixture_theme('daylight'), array('schema_version' => $schema,
    'label' => 'Custom <Daylight>', 'density' => 'small',
    'colors' => array('--h5p-theme-background' => '#aBc', '--h5p-theme-text-primary' => '#123456')));
  if ($schema === 2) { $theme['base_preset'] = array('id' => 'mint', 'version' => 'moodle-478fed30d2a4'); }
  seed('h5p_themer.theme.daylight', $theme);
  seed('h5p_themer.settings', $settings_schema === 1 ? fixture_settings('daylight') : fixture_typed_settings(array('kind' => 'custom', 'id' => 'daylight')));
  return $theme;
}
function add_theme_input($id = 'added') {
  return array('id' => $id, 'label' => 'Independent copy', 'description' => '', 'colors' => array());
}
function add_theme_hidden($id) {
  $policy = _h5p_themer_visibility_policy_read(FALSE);
  return isset($policy['data']['themes']['custom:' . $id]) && $policy['data']['themes']['custom:' . $id] === FALSE &&
    !isset(h5p_themer_learner_catalog()['data']['custom:' . $id]);
}

foreach (array('daylight', 'lavender', 'mint', 'sunset', 'dark') as $id) {
  add_theme_fixture();
  seed('h5p_themer.settings', fixture_typed_settings(array('kind' => 'builtin', 'id' => $id, 'version' => $add_version)));
  $before = H5PThemerMemoryStorage::$raw; $writes = H5PThemerMemoryStorage::$writes;
  $bundle = admin_form('theme'); $snapshot = $bundle[2]['h5p_themer']['add_snapshot'];
  $preset = h5p_themer_preset_load($id, $add_version)['data'];
  check($snapshot['seed']['schema_version'] === 2 && $snapshot['seed']['base_preset'] === array('id' => $id, 'version' => $add_version), 'Add pins exact built-in identity: ' . $id);
  check($snapshot['seed']['colors'] === array() && $snapshot['seed']['density'] === 'large' && !$snapshot['fallback'], 'Built-in Add starts with empty overrides and large density: ' . $id);
  check($snapshot['palette']['colors'] === $preset['colors'] && strpos($bundle[1]['help']['#markup'], $preset['label']) !== FALSE, 'Actual built-in name and complete starting palette: ' . $id);
  check(H5PThemerMemoryStorage::$raw === $before && H5PThemerMemoryStorage::$writes === $writes, 'Add build is read-only: ' . $id);
  $input = add_theme_input();
  $result = admin_post($bundle, $input); $saved = h5p_themer_theme_load('added')['data'];
  check(!$result['errors'] && $saved['colors'] === array() && h5p_themer_theme_resolve('added')['data']['colors'] === $preset['colors'], 'Unchanged Add preserves all catalog colors and expressions without flattening: ' . $id);
  check(add_theme_hidden('added') && H5PThemerMemoryStorage::$writes === $writes + 2, 'Only hidden policy and new definition are written: ' . $id);
  check(H5PThemerMemoryStorage::$raw['./active']['h5p_themer.settings'] === $before['./active']['h5p_themer.settings'] &&
    H5PThemerMemoryStorage::$raw['./active']['h5p_themer.theme.daylight'] === $before['./active']['h5p_themer.theme.daylight'], 'Same-name custom source and global selection untouched: ' . $id);
}

foreach (array(1, 2) as $schema) {
  foreach (array(1, 2) as $settings_schema) {
    $source = add_theme_fixture($schema, $settings_schema);
    $before = H5PThemerMemoryStorage::$raw;
    $bundle = admin_form('theme'); $snapshot = $bundle[2]['h5p_themer']['add_snapshot'];
    check($snapshot['seed']['schema_version'] === $schema && $snapshot['seed']['colors'] === $source['colors'] && $snapshot['seed']['density'] === 'small' && !isset($bundle[1]['general']['density']), 'Custom Add preserves source schema, sparse overrides and density: ' . $schema . '/' . $settings_schema);
    check(!$snapshot['fallback'] && strpos($bundle[1]['help']['#markup'], 'Custom &lt;Daylight&gt;') !== FALSE, 'Custom daylight ID resolves as custom with escaped actual name');
    $field = $bundle[1]['colors']['surfaces']['--h5p-theme-background'];
    check($field['#default_value'] === '' && $field['#attributes']['data-h5p-themer-inherited'] === '#aBc', 'Copied custom color is a starting swatch, never a filled override');
    $input = add_theme_input(); $input['colors'] = array('--h5p-theme-background' => '', '--h5p-theme-text-primary' => '');
    $result = admin_post($bundle, $input); $saved = h5p_themer_theme_load('added')['data'];
    check(!$result['errors'] && $saved['colors'] === $source['colors'] && $saved['schema_version'] === $schema, 'Blank Add fields retain captured sparse overrides and exact spelling');
    check(($schema === 1 && !isset($saved['base_preset'])) || ($schema === 2 && $saved['base_preset'] === $source['base_preset']), 'Custom copy retains only its original preset relationship');
    check(array_diff(array_keys($saved), array('schema_version', 'id', 'label', 'description', 'density', 'colors', 'base_preset')) === array(), 'No custom parent/provenance or snapshot fields are persisted');
    check(add_theme_hidden('added') && H5PThemerMemoryStorage::$raw['./active']['h5p_themer.settings'] === $before['./active']['h5p_themer.settings'] &&
      H5PThemerMemoryStorage::$raw['./active']['h5p_themer.theme.daylight'] === $before['./active']['h5p_themer.theme.daylight'], 'Custom Add stays hidden and preserves source/settings bytes');
    $override = add_theme_input('overridden'); $override['colors'] = array('--h5p-theme-background' => '#0aF');
    $result = admin_post(admin_form('theme'), $override);
    check(!$result['errors'] && h5p_themer_theme_load('overridden')['data']['colors'] === array_replace($source['colors'], $override['colors']), 'Explicit Add hex replaces a captured override while omitted fields retain their starting colors');
    if ($schema === 2) {
      $expression = '--h5p-theme-contrast-cta-light';
      check(h5p_themer_theme_resolve('overridden')['data']['colors'][$expression] === h5p_themer_preset_load('mint', $add_version)['data']['colors'][$expression] &&
        !isset(h5p_themer_theme_load('overridden')['data']['colors'][$expression]), 'Custom preset expression survives a copied hex override without flattening');
    }
    $resolved = h5p_themer_theme_resolve('added');
    config('h5p_themer.theme.daylight')->delete();
    check(h5p_themer_theme_resolve('added') === $resolved, 'Saved Add copy has no dependency on its source custom theme');
    $edit = admin_form('theme', 'edit', 'added');
    check(strpos($edit[1]['help']['#markup'], 'previous custom copy source is not recorded') !== FALSE && strpos($edit[1]['help']['#markup'], 'Custom &lt;Daylight&gt;') === FALSE, 'Edit invents no earlier custom provenance');
    check(strpos($edit[1]['colors']['surfaces']['--h5p-theme-background']['#description'], 'Saved administrator override') !== FALSE, 'Both saved schemas distinguish explicit overrides');
    $input['colors'] = array('--h5p-theme-background' => '', '--h5p-theme-text-primary' => '');
    $result = admin_post($edit, $input);
    check(!$result['errors'] && h5p_themer_theme_load('added')['data']['colors'] === array(), 'Edit clearing retains original preset/Core semantics');
  }
  add_theme_fixture($schema);
  $GLOBALS['config']['h5p_themer.settings'] = array('default_theme' => array('kind' => 'custom', 'id' => 'daylight'));
  $GLOBALS['config']['h5p_themer.theme.daylight'] = array('label' => 'Effective custom', 'density' => 'medium', 'colors' => array('--h5p-theme-background' => '#DEF'));
  if ($schema === 2) { $GLOBALS['config']['h5p_themer.theme.daylight']['base_preset'] = array('id' => 'dark', 'version' => $add_version); }
  $before = H5PThemerMemoryStorage::$raw;
  $bundle = admin_form('theme');
  check($bundle[2]['h5p_themer']['original']['density'] === 'medium' && !isset($bundle[1]['general']['density']) && strpos($bundle[1]['help']['#markup'], 'Effective custom') !== FALSE, 'Add reads effective custom metadata');
  $input = add_theme_input();
  $input['colors'] = array('--h5p-theme-background' => '', '--h5p-theme-text-secondary' => '#aB0');
  $result = admin_post($bundle, $input); $saved = h5p_themer_theme_load('added')['data'];
  check(!$result['errors'] && $saved['colors'] === array('--h5p-theme-background' => '#DEF', '--h5p-theme-text-secondary' => '#aB0') && $saved['density'] === 'medium', 'Effective colors are copied as a complete sparse map and explicit overrides replace starting values');
  check($schema === 1 || $saved['base_preset'] === array('id' => 'dark', 'version' => $add_version), 'Effective preset pin is retained');
  check(H5PThemerMemoryStorage::$raw['./active']['h5p_themer.theme.daylight'] === $before['./active']['h5p_themer.theme.daylight'], 'Effective source overrides never write back to source');
}

add_theme_fixture();
$GLOBALS['config']['h5p_themer.settings'] = array('default_theme' => array('kind' => 'builtin', 'id' => 'sunset', 'version' => $add_version));
check(admin_form('theme')[2]['h5p_themer']['original']['base_preset']['id'] === 'sunset', 'Effective selection override wins over stored custom default');

add_theme_fixture(1, 1);
$GLOBALS['config']['h5p_themer.settings'] = array('schema_version' => 2, 'default_theme' => array('kind' => 'builtin', 'id' => 'dark', 'version' => $add_version));
$result = admin_post(admin_form('theme'), add_theme_input());
check(!$result['errors'] && h5p_themer_theme_load('added')['data']['base_preset'] === array('id' => 'dark', 'version' => $add_version) &&
  config('h5p_themer.settings')->getOriginal() === fixture_settings('daylight'), 'Effective settings schema and selection can seed Add without migrating stored settings');

add_theme_fixture(1);
$GLOBALS['config']['h5p_themer.theme.daylight'] = array('schema_version' => 2, 'base_preset' => array('id' => 'dark', 'version' => $add_version));
$result = admin_post(admin_form('theme'), add_theme_input());
check(!$result['errors'] && h5p_themer_theme_load('added')['data']['schema_version'] === 2 &&
  h5p_themer_theme_load('added')['data']['base_preset'] === array('id' => 'dark', 'version' => $add_version) &&
  config('h5p_themer.theme.daylight')->getOriginal('schema_version') === 1, 'Effective custom schema/base overrides seed an independent copy without migrating the source');

foreach (array('core1', 'core2', 'missing_settings', 'malformed_settings', 'invalid_reference', 'missing_custom', 'invalid_custom', 'invalid_pin', 'invalid_effective', 'invalid_version') as $failure) {
  add_theme_fixture();
  if ($failure === 'core1') { seed('h5p_themer.settings', fixture_settings()); }
  elseif ($failure === 'core2') { seed('h5p_themer.settings', fixture_typed_settings()); }
  elseif ($failure === 'missing_settings') { config('h5p_themer.settings')->delete(); }
  elseif ($failure === 'malformed_settings') { H5PThemerMemoryStorage::$raw['./active']['h5p_themer.settings'] = 'false'; }
  elseif ($failure === 'invalid_reference') { seed('h5p_themer.settings', array('schema_version' => 2, 'default_theme' => array('kind' => 'custom', 'id' => '../bad'))); }
  elseif ($failure === 'missing_custom') { config('h5p_themer.theme.daylight')->delete(); }
  elseif ($failure === 'invalid_custom') { config('h5p_themer.theme.daylight')->set('colors', array('--bad' => '#abc'))->save(); }
  elseif ($failure === 'invalid_pin') { config('h5p_themer.theme.daylight')->set('base_preset', array('id' => 'mint', 'version' => 'missing'))->save(); }
  elseif ($failure === 'invalid_effective') { $GLOBALS['config']['h5p_themer.theme.daylight'] = array('colors' => array('--h5p-theme-background' => 'red')); }
  else { seed('h5p_themer.settings', fixture_typed_settings(array('kind' => 'builtin', 'id' => 'mint', 'version' => 'latest'))); }
  $before = H5PThemerMemoryStorage::$raw; $bundle = admin_form('theme');
  $snapshot = $bundle[2]['h5p_themer']['add_snapshot'];
  check($snapshot['fallback'] && $snapshot['seed']['base_preset'] === array('id' => 'daylight', 'version' => $add_version) && strpos($bundle[1]['help']['#markup'], 'Daylight fallback') !== FALSE, 'Explicit pinned Daylight fallback: ' . $failure);
  check(H5PThemerMemoryStorage::$raw === $before, 'Fallback build never repairs configuration: ' . $failure);
  $result = admin_post($bundle, add_theme_input());
  check(!$result['errors'] && h5p_themer_theme_load('added')['data']['colors'] === array() && add_theme_hidden('added'), 'Unchanged invalid/missing default permits a hidden Daylight copy: ' . $failure);
  unset($before['./active']['h5p_themer.learner_visibility']); $after = H5PThemerMemoryStorage::$raw;
  unset($after['./active']['h5p_themer.learner_visibility'], $after['./active']['h5p_themer.theme.added']);
  check($after === $before, 'Fallback save leaves all existing settings/themes untouched: ' . $failure);
}

add_theme_fixture(); $bundle = admin_form('theme'); $captured = $bundle[2]['h5p_themer'];
$input = add_theme_input(); $input['colors'] = array('--h5p-theme-background' => '#12'); $input['label'] = '';
$result = admin_post($bundle, $input); $state = $result['state'];
config('h5p_themer.theme.daylight')->set('label', 'Changed after invalid input')->save();
$rebuilt = h5p_themer_theme_form(array(), $state);
backdrop_prepare_form('h5p_themer_theme_form', $rebuilt, $state);
$rebuilt = form_builder('h5p_themer_theme_form', $rebuilt, $state);
check($state['h5p_themer'] === $captured && strpos($rebuilt['help']['#markup'], 'Custom &lt;Daylight&gt;') !== FALSE, 'Rebuild preserves captured source identity and initialization');
check($rebuilt['colors']['surfaces']['--h5p-theme-background']['#value'] === '#12' && $rebuilt['general']['label']['#value'] === '', 'Rebuild preserves invalid submitted input');
check($rebuilt['colors']['surfaces']['--h5p-theme-background']['#attributes']['data-h5p-themer-inherited'] === '#aBc', 'Rebuilt form keeps original swatches even after its source changes');

foreach (array('settings', 'effective_selection', 'colors', 'density', 'label', 'pin', 'source_deleted', 'effective_colors', 'effective_pin', 'fallback_repaired', 'invalid_changed', 'under_lock') as $change) {
  add_theme_fixture();
  if (in_array($change, array('fallback_repaired', 'invalid_changed'), TRUE)) { seed('h5p_themer.settings', fixture_typed_settings(array('kind' => 'custom', 'id' => 'missing'))); }
  $bundle = admin_form('theme');
  if ($change === 'settings') { seed('h5p_themer.settings', fixture_typed_settings()); }
  elseif ($change === 'effective_selection') { $GLOBALS['config']['h5p_themer.settings'] = array('default_theme' => array('kind' => 'builtin', 'id' => 'dark', 'version' => $add_version)); }
  elseif ($change === 'colors') { config('h5p_themer.theme.daylight')->set('colors', array('--h5p-theme-background' => '#fff'))->save(); }
  elseif ($change === 'density') { config('h5p_themer.theme.daylight')->set('density', 'large')->save(); }
  elseif ($change === 'label') { config('h5p_themer.theme.daylight')->set('label', 'New label')->save(); }
  elseif ($change === 'pin') { config('h5p_themer.theme.daylight')->set('base_preset', array('id' => 'dark', 'version' => $add_version))->save(); }
  elseif ($change === 'source_deleted') { config('h5p_themer.theme.daylight')->delete(); }
  elseif ($change === 'effective_colors') { $GLOBALS['config']['h5p_themer.theme.daylight'] = array('colors' => array('--h5p-theme-background' => '#fff')); }
  elseif ($change === 'effective_pin') { $GLOBALS['config']['h5p_themer.theme.daylight'] = array('base_preset' => array('id' => 'dark', 'version' => $add_version)); }
  elseif ($change === 'fallback_repaired') { seed('h5p_themer.theme.missing', fixture_theme('missing')); }
  elseif ($change === 'invalid_changed') { seed('h5p_themer.settings', fixture_typed_settings(array('kind' => 'custom', 'id' => 'still_missing'))); }
  else { $GLOBALS['visibility_test_on_lock'] = function () { $GLOBALS['config']['h5p_themer.theme.daylight'] = array('density' => 'large'); }; }
  $before = H5PThemerMemoryStorage::$raw; $writes = H5PThemerMemoryStorage::$writes;
  $result = admin_post($bundle, add_theme_input());
  check((bool) $result['errors'] && !isset($result['state']['redirect']), 'Stale Add rejects relevant dependency change: ' . $change);
  check(H5PThemerMemoryStorage::$raw === $before && H5PThemerMemoryStorage::$writes === $writes && !$write_locked, 'Stale rejection writes nothing and releases lock: ' . $change);
}

// A source can change after locked validation but before the definition write.
// The third lock/lease acquisition follows persistence of the hidden policy.
add_theme_fixture(); $bundle = admin_form('theme');
$add_lease_calls = 0;
$add_late_change = function () use (&$add_lease_calls, &$add_late_change) {
  $add_lease_calls++;
  if ($add_lease_calls === 3) {
    $GLOBALS['config']['h5p_themer.theme.daylight'] = array('density' => 'large');
  }
  else { $GLOBALS['visibility_test_on_lock'] = $add_late_change; }
};
$GLOBALS['visibility_test_on_lock'] = $add_late_change;
$writes = H5PThemerMemoryStorage::$writes;
$result = admin_post($bundle, add_theme_input());
unset($GLOBALS['visibility_test_on_lock']);
check($add_lease_calls === 3 && (bool) $result['errors'] && !isset($result['state']['redirect']), 'Final pre-save check rejects source changes after hidden-policy persistence');
check(!config_get_config_storage()->exists('h5p_themer.theme.added') && add_theme_hidden('added') && H5PThemerMemoryStorage::$writes === $writes + 1 && !$write_locked, 'Late stale rejection retains only a restrictive tombstone and releases the lock');

// Existing validation must still reject raw forged fields before publication.
foreach (array(array('colors', array('--unknown' => '')), array('colors', array('--h5p-theme-background' => 'rgba(1,2,3,.5)')),
  array('base_preset', array('id' => 'dark', 'version' => $add_version)), array('add_snapshot', array()), array('schema_version', 1), array('id', 'daylight')) as $bad) {
  add_theme_fixture(); $input = add_theme_input(); $input[$bad[0]] = $bad[1];
  $bundle = admin_form('theme'); $before = H5PThemerMemoryStorage::$raw;
  $result = admin_post($bundle, $input);
  check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $before, 'Add rejects forged/non-hex/colliding input without writes: ' . $bad[0]);
}
foreach (array(1, 2) as $schema) {
  foreach (array('policy_save', 'policy_save_false', 'policy_verify', 'theme_save', 'theme_save_false', 'theme_save_after', 'theme_verify') as $fault) {
    add_theme_fixture($schema); $bundle = admin_form('theme');
    H5PVisibilityNativeFixture::$fault = $fault;
    $result = admin_post($bundle, add_theme_input()); H5PVisibilityNativeFixture::$fault = '';
    check((bool) $result['errors'] && !isset($result['state']['redirect']) && !$write_locked, 'Custom-seeded Add reports publication failure and releases lock: ' . $schema . '/' . $fault);
    check(!isset(h5p_themer_learner_catalog()['data']['custom:added']), 'Failed custom-seeded Add never exposes destination: ' . $schema . '/' . $fault);
  }
}
foreach (array(1, 2) as $settings_schema) {
  foreach (array('add', 'duplicate', 'preset_duplicate') as $operation) {
    add_theme_fixture();
    seed('h5p_themer.settings', $settings_schema === 1 ? fixture_settings('missing_default') : fixture_typed_settings(array('kind' => 'custom', 'id' => 'missing_default')));
    $source_id = $operation === 'duplicate' ? 'daylight' : 'mint';
    $bundle = admin_form('theme', $operation, $operation === 'add' ? NULL : $source_id, $add_version);
    $before = H5PThemerMemoryStorage::$raw; $writes = H5PThemerMemoryStorage::$writes;
    $result = admin_post($bundle, add_theme_input('missing_default'));
    check(isset($result['errors']['id']) && strpos($result['errors']['id'], 'global default') !== FALSE && H5PThemerMemoryStorage::$raw === $before && H5PThemerMemoryStorage::$writes === $writes,
      'Every creation path rejects unresolved default-ID activation without writes: ' . $settings_schema . '/' . $operation);
    $result = admin_post(admin_form('theme', $operation, $operation === 'add' ? NULL : $source_id, $add_version), add_theme_input('unrelated'));
    check(!$result['errors'] && add_theme_hidden('unrelated') && (bool) h5p_themer_default_selection_load()['errors'], 'Unrelated creation remains allowed without repairing broken default: ' . $operation);
  }
}
// Recheck default references under the write lock, including after publication.
foreach (array(1, 3) as $at_lease) {
  add_theme_fixture(); $bundle = admin_form('theme', 'duplicate', 'daylight');
  $collision_calls = 0;
  $collision_change = function () use (&$collision_calls, &$collision_change, $at_lease) {
    $collision_calls++;
    if ($collision_calls === $at_lease) { seed('h5p_themer.settings', fixture_typed_settings(array('kind' => 'custom', 'id' => 'race_copy'))); }
    else { $GLOBALS['visibility_test_on_lock'] = $collision_change; }
  };
  $GLOBALS['visibility_test_on_lock'] = $collision_change;
  $result = admin_post($bundle, add_theme_input('race_copy')); unset($GLOBALS['visibility_test_on_lock']);
  check((bool) $result['errors'] && !config_get_config_storage()->exists('h5p_themer.theme.race_copy') && !$write_locked, 'Default-ID guard rejects a reference introduced during creation: lease ' . $at_lease);
  check((bool) h5p_themer_default_selection_load()['errors'], 'Concurrent missing default is never implicitly repaired');
}

require __DIR__ . '/add-theme-requests.php';
printf("Add theme snapshot regressions: %d passed\n", $passed - $add_before);
if ($add_standalone) {
  require __DIR__ . '/visibility-publication.php';
  printf("PHP %s focused admin/Add/publication: %d passed, %d failed\n", PHP_VERSION, $passed, $failed);
  exit($failed ? 1 : 0);
}
