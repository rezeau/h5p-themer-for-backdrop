<?php
// SPDX-License-Identifier: GPL-2.0-or-later.
/** v0.4.2 administration, legacy compatibility and learner-only capability. */
$density_v042_start = $passed;
learner_switch_fixture(); native_density_fixture();
unset($GLOBALS['native_density_test_enabled'], $GLOBALS['settings']['h5p_themer_native_density_enabled']);
check(!h5p_themer_native_density_enabled(), 'Missing config checkbox and PHP override default to disabled');
foreach (array(FALSE, TRUE) as $configured) {
  config('h5p_themer.settings')->set('native_density_enabled', $configured)->save();
  check(h5p_themer_native_density_enabled() === $configured, 'Absent PHP override uses boolean config checkbox');
  foreach (array(TRUE, FALSE, NULL, 1, 0, 'TRUE', 'FALSE', array()) as $override) {
    $GLOBALS['settings']['h5p_themer_native_density_enabled'] = $override;
    $control = h5p_themer_native_density_control();
    check($control === array('enabled' => $override === TRUE, 'overridden' => TRUE, 'valid' => is_bool($override)), 'Explicit PHP override precedence and strict diagnostic type');
    $bundle = admin_form('default'); $field = $bundle[1]['native_density_enabled'];
    check($field['#disabled'] && $field['#default_value'] === (int) ($override === TRUE), 'Overridden checkbox shows effective state and cannot be edited');
    check(strpos($field['#description'], is_bool($override) ? 'Overridden' : 'invalid') !== FALSE, 'Override diagnostic distinguishes invalid PHP values');
    $result = admin_post($bundle, array('default_theme' => 'core', 'learner_selection_enabled' => 1, 'native_density_enabled' => 1));
    check(!$result['errors'] && config_get('h5p_themer.settings', 'native_density_enabled') === $configured, 'Forced checkbox cannot overwrite portable saved preference');
  }
  unset($GLOBALS['settings']['h5p_themer_native_density_enabled']);
}
foreach (array(NULL, 0, 1, 'true', array()) as $bad) {
  $candidate = fixture_typed_settings(NULL); $candidate['native_density_enabled'] = $bad;
  check((bool) h5p_themer_config_errors('h5p_themer.settings', $candidate), 'Invalid config checkbox types are rejected');
}
learner_switch_fixture();
$bundle = admin_form('default');
check(!$bundle[1]['native_density_enabled']['#disabled'] && $bundle[1]['native_density_enabled']['#default_value'] === 0, 'Legacy settings without the optional key show unchecked editable checkbox');
$result = admin_post($bundle, array('default_theme' => 'core', 'learner_selection_enabled' => 1, 'native_density_enabled' => 1));
check(!$result['errors'] && config_get('h5p_themer.settings', 'native_density_enabled') === TRUE, 'Native Form API persists checkbox as strict TRUE');
native_density_fixture();
check(h5p_themer_native_density_settings()['version'] === 3, 'Configuration checkbox enables version-3 capability without PHP changes');
$encoded = config_get_config_storage()->encode(config('h5p_themer.settings')->getOriginal());
check(json_decode($encoded, TRUE)['native_density_enabled'] === TRUE, 'Settings export preserves boolean checkbox');
$result = admin_post(admin_form('default'), array('default_theme' => 'core', 'learner_selection_enabled' => 1));
check(!$result['errors'] && config_get('h5p_themer.settings', 'native_density_enabled') === FALSE, 'Unchecked save persists strict FALSE');
foreach (array('true', '0', array('1')) as $bad) {
  $bytes = H5PThemerMemoryStorage::$raw;
  $result = admin_post(admin_form('default'), array('default_theme' => 'core', 'native_density_enabled' => $bad));
  check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Forged checkbox input cannot mutate settings');
}
$bundle = admin_form('default'); $bytes = H5PThemerMemoryStorage::$raw;
$GLOBALS['settings']['h5p_themer_native_density_enabled'] = TRUE;
$result = admin_post($bundle, array('default_theme' => 'core', 'native_density_enabled' => 1));
check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Changed PHP override rejects stale settings form');
unset($GLOBALS['settings']['h5p_themer_native_density_enabled']);
config('h5p_themer.settings')->set('native_density_enabled', FALSE)->save();
$GLOBALS['config']['h5p_themer.settings'] = array('native_density_enabled' => TRUE);
check(h5p_themer_native_density_enabled(), 'Effective configuration overrides are read through Config');
$bundle = admin_form('default');
check($bundle[1]['actions']['submit']['#disabled'], 'Configuration override cannot be presented as editable stored settings');
unset($GLOBALS['config']['h5p_themer.settings']);
$bundle = admin_form('default'); $bytes = H5PThemerMemoryStorage::$raw;
$GLOBALS['visibility_test_on_lock'] = function () { $GLOBALS['settings']['h5p_themer_native_density_enabled'] = TRUE; };
$result = admin_post($bundle, array('default_theme' => 'core', 'native_density_enabled' => 1));
check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Override changed under shared lock rejects stale checkbox save');
unset($GLOBALS['visibility_test_on_lock'], $GLOBALS['settings']['h5p_themer_native_density_enabled']);
learner_switch_fixture(); admin_publication_fixture();
foreach (array('small', 'medium', 'large') as $legacy) {
  config('h5p_themer.theme.legacy')->set('density', $legacy)->save();
  $original = h5p_themer_theme_load('legacy')['data'];
  $bundle = admin_form('theme', 'edit', 'legacy');
  check(!isset($bundle[1]['general']['density']), 'Edit has no Density input');
  $input = array('id' => 'legacy', 'label' => 'Legacy changed', 'description' => '', 'colors' => $original['colors']);
  $result = admin_post($bundle, $input);
  check(!$result['errors'] && h5p_themer_theme_load('legacy')['data']['density'] === $legacy, 'Color edit retains every legacy Density value internally');
  $export = config_get_config_storage()->encode(config('h5p_themer.theme.legacy')->getOriginal());
  check(json_decode($export, TRUE)['density'] === $legacy, 'Theme export retains legacy Density');
  $before = H5PThemerMemoryStorage::$raw; $input['density'] = 'large';
  $result = admin_post(admin_form('theme', 'edit', 'legacy'), $input);
  check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $before, 'Removed Density input cannot be forged');
}
$GLOBALS['native_density_test_enabled'] = TRUE; native_density_fixture();
seed('h5p_themer.theme.malformed', array('schema_version' => 1));
$overview = admin_form('visibility')[1];
foreach ($overview['themes']['#rows'] as $row) {
  if ($row[0] === 'Invalid theme definition') {
    check(count($row) === count($overview['themes']['#header']), 'Malformed-theme diagnostic row stays aligned after removing Density column');
  }
}
$before = h5p_themer_native_density_settings();
config('h5p_themer.theme.legacy')->set('density', 'medium')->save();
check(h5p_themer_native_density_settings() === $before, 'Legacy theme Density does not affect transport');
config('h5p_themer.settings')->set('learner_selection_enabled', FALSE)->save();
check(h5p_themer_native_density_settings() === NULL, 'Enabled Density still requires learner theme picker');
unset($GLOBALS['native_density_test_enabled']);
$core_density_css = file_get_contents($h5p_root . '/library/styles/h5p-theme-variables.css');
check(strpos($core_density_css, '--h5p-theme-spacing-xl: var(--h5p-theme-spacing-xl-primary-small)') !== FALSE &&
  strpos($core_density_css, '.h5p-content.h5p-large') !== FALSE, 'Original Core profile cannot be assumed to be Large');
printf("v0.4.2 Density behavior: %d passed\n", $passed - $density_v042_start);
