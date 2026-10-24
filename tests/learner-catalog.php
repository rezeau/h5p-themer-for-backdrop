<?php
/**
 * @file
 * Phase 2C Step 2A: native Config/Form API with memory-only collaborators.
 * SPDX-License-Identifier: GPL-2.0-or-later.
 */

$version = 'moodle-478fed30d2a4';
$builtin = array('kind' => 'builtin', 'id' => 'mint', 'version' => $version);
$shipped = json_decode(file_get_contents(dirname(__DIR__) . '/config/h5p_themer.settings.json'), TRUE);
check($shipped['learner_selection_enabled'] === FALSE, 'Learner selection ships disabled');
check($shipped['schema_version'] === 1 && $shipped['default_theme'] === NULL, 'Optional flag does not change shipped schema/Core default');
foreach (array(1, 2) as $schema) {
  foreach (array('missing', FALSE, TRUE) as $flag) {
    admin_reset();
    $settings = array('schema_version' => $schema, 'default_theme' => NULL);
    if ($flag !== 'missing') { $settings['learner_selection_enabled'] = $flag; }
    seed('h5p_themer.settings', $settings);
    $bytes = H5PThemerMemoryStorage::$raw; $writes = H5PThemerMemoryStorage::$writes;
    check(h5p_themer_config_errors('h5p_themer.settings', $settings) === array(), 'Optional Boolean valid in schema ' . $schema);
    check(h5p_themer_learner_selection_enabled() === ($flag === TRUE), 'Missing/FALSE/TRUE read correctly in schema ' . $schema);
    $form = admin_form('default');
    check($form[1]['learner_selection_enabled']['#type'] === 'checkbox' && $form[1]['learner_selection_enabled']['#default_value'] === ($flag === TRUE ? 1 : 0), 'Native checkbox initial state in schema ' . $schema);
    check($form[1]['learner_selection_enabled']['#title'] === 'Allow learners to select their H5P theme', 'Exact administrator label');
    check(H5PThemerMemoryStorage::$raw === $bytes && H5PThemerMemoryStorage::$writes === $writes, 'Flag reads/form build never migrate or save');
  }
  foreach (array(NULL, 0, 1, '0', '1', 'true', 'false', '', array(), new stdClass(), 0.0) as $invalid) {
    $settings = array('schema_version' => $schema, 'default_theme' => NULL, 'learner_selection_enabled' => $invalid);
    check(isset(h5p_themer_config_field_errors('h5p_themer.settings', $settings)['learner_selection_enabled']), 'Non-Boolean setting rejected in schema ' . $schema);
    seed('h5p_themer.settings', $settings);
    check(!h5p_themer_learner_selection_enabled(), 'Invalid setting fails closed');
    rejects(function () use ($settings) { config_sync_validate_file('h5p_themer.settings', 'update', NULL, $settings); }, 'Native import rejects invalid Boolean');
  }
}
admin_reset();
$theme = fixture_theme(); $theme['learner_selection_enabled'] = TRUE;
check((bool) h5p_themer_config_errors('h5p_themer.theme.ocean', $theme), 'Boolean is not a custom theme field');
reset_storage();
check(!h5p_themer_learner_selection_enabled(), 'Missing settings fail closed without recreation');

// Config::get() supplies effective top-level overrides, including absent fields.
foreach (array('missing', FALSE, TRUE) as $stored) {
  foreach (array(FALSE, TRUE) as $override) {
    admin_reset();
    $settings = fixture_settings();
    if ($stored !== 'missing') { $settings['learner_selection_enabled'] = $stored; }
    seed('h5p_themer.settings', $settings);
    $GLOBALS['config']['h5p_themer.settings'] = array('learner_selection_enabled' => $override);
    $bytes = H5PThemerMemoryStorage::$raw;
    check(h5p_themer_learner_selection_enabled() === $override, 'Effective Boolean override takes precedence');
    check(h5p_themer_config_load('h5p_themer.settings', 'active', TRUE)['data'] === $settings, 'Original reader does not persist effective override');
    $bundle = admin_form('default');
    check($bundle[1]['learner_selection_enabled']['#disabled'] && $bundle[1]['default_theme']['#disabled'] && $bundle[1]['actions']['submit']['#disabled'], 'Override guards both settings even if new key was absent');
    $result = admin_post($bundle, array('default_theme' => 'core', 'learner_selection_enabled' => '1'));
    check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Overridden settings cannot be written');
  }
}
admin_reset();
$GLOBALS['config']['h5p_themer.settings'] = array('learner_selection_enabled' => 'true');
check(h5p_themer_config_load('h5p_themer.settings')['errors'] !== array() && !h5p_themer_learner_selection_enabled(), 'Invalid effective override rejected');
admin_reset();
$bundle = admin_form('default'); $bytes = H5PThemerMemoryStorage::$raw;
$GLOBALS['config']['h5p_themer.settings'] = array('learner_selection_enabled' => TRUE);
$result = admin_post($bundle, array('default_theme' => 'core'));
check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Override introduced after build blocks locked submission');

// Toggle-only saves preserve exact global reference and schema, no playback flush.
foreach (array(fixture_settings(), fixture_settings('ocean'), fixture_typed_settings(), fixture_typed_settings($builtin), fixture_typed_settings(array('kind' => 'custom', 'id' => 'ocean'))) as $settings) {
  admin_reset(); seed('h5p_themer.theme.ocean', fixture_theme()); seed('h5p_themer.settings', $settings);
  $token = admin_form('default')[1]['default_theme']['#default_value'];
  $css = h5p_themer_runtime_css(); $snapshot = h5p_themer_runtime_cache_snapshot(); $invalidations = $runtime_cache_invalidations;
  $result = admin_post(admin_form('default'), array('default_theme' => $token, 'learner_selection_enabled' => '1'));
  $expected = $settings + array('learner_selection_enabled' => TRUE);
  check(!$result['errors'] && config('h5p_themer.settings')->getOriginal() === $expected, 'Enable preserves global identity and schema');
  check(h5p_themer_learner_selection_enabled(), 'Saved enable is real Boolean TRUE');
  check(h5p_themer_runtime_css() === $css && h5p_themer_runtime_cache_snapshot() === $snapshot && $runtime_cache_invalidations === $invalidations + 1, 'Enable leaves playback CSS unchanged and invalidates cached UI');
  $result = admin_post(admin_form('default'), array('default_theme' => $token));
  $expected['learner_selection_enabled'] = FALSE;
  check(!$result['errors'] && config('h5p_themer.settings')->getOriginal() === $expected, 'Unchecked saves real Boolean FALSE and preserves global selection');
  check(!h5p_themer_learner_selection_enabled() && h5p_themer_runtime_css() === $css && $runtime_cache_invalidations === $invalidations + 2, 'Disable leaves playback unchanged and invalidates cached UI');
}
admin_reset();
$bytes = H5PThemerMemoryStorage::$raw;
$result = admin_post(admin_form('default'), array('default_theme' => 'core'));
check(!$result['errors'] && config('h5p_themer.settings')->getOriginal() === fixture_settings(), 'Legacy unchecked save retains absent flag/schema');
$bytes = H5PThemerMemoryStorage::$raw;
$result = admin_post(admin_form('default'), array('default_theme' => 'core', 'learner_selection_enabled' => '1'), FALSE);
check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Toggle retains native CSRF protection');
foreach (array('true', '0', 'false', 'unexpected') as $invalid) {
  $result = admin_post(admin_form('default'), array('default_theme' => 'core', 'learner_selection_enabled' => $invalid));
  check(isset($result['errors']['learner_selection_enabled']) && H5PThemerMemoryStorage::$raw === $bytes, 'Forged checkbox value rejected before Boolean conversion');
}
admin_reset();
$bundle = admin_form('default');
$settings = fixture_settings(); $settings['learner_selection_enabled'] = TRUE; seed('h5p_themer.settings', $settings);
$bytes = H5PThemerMemoryStorage::$raw;
$result = admin_post($bundle, array('default_theme' => 'core'));
check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Stale toggle cannot overwrite concurrent settings');
admin_reset();
$GLOBALS['admin_allowed'] = FALSE;
check(!isset(admin_form('default')[1]['learner_selection_enabled']), 'Unauthorised viewer gets no administrator toggle');
admin_reset(); $GLOBALS['h5p_enabled'] = FALSE;
$bundle = admin_form('default'); $bytes = H5PThemerMemoryStorage::$raw;
check($bundle[1]['learner_selection_enabled']['#disabled'], 'Incompatible H5P disables toggle');
$result = admin_post($bundle, array('default_theme' => 'core', 'learner_selection_enabled' => '1'));
check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Incompatible submit makes no writes');

// Catalog is read-only, unpersonalized, and independent of admin form metadata.
admin_reset();
$bytes = H5PThemerMemoryStorage::$raw; $writes = H5PThemerMemoryStorage::$writes; $invalidations = $runtime_cache_invalidations;
$catalog = h5p_themer_theme_catalog();
$keys = array('site_default', 'core', 'builtin:daylight', 'builtin:lavender', 'builtin:mint', 'builtin:sunset', 'builtin:dark');
check($catalog['errors'] === array() && array_keys($catalog['data']) === $keys, 'Catalog includes Site default, Core, five built-ins in order');
check($catalog['data']['site_default']['reference'] === array('kind' => 'site_default') && $catalog['data']['core']['reference'] === NULL, 'Site default sentinel and explicit Core are distinct');
check(h5p_themer_selection_reference($catalog['data']['site_default']['reference'])['errors'] !== array(), 'Site default sentinel cannot be stored as global selection');
check($catalog['data']['builtin:daylight']['reference'] !== NULL, 'Daylight is never Core');
foreach ($catalog['data'] as $token => $entry) {
  check(array_keys($entry) === array('label', 'reference') && is_string($entry['label']), 'Catalog exposes labels/references only: ' . $token);
  if ($token !== 'site_default') {
    $resolved = h5p_themer_selection_resolve($entry['reference']);
    check(!$resolved['errors'] && ($token !== 'core' || $resolved['data']['colors'] === array()), 'Existing resolver handles catalog choice: ' . $token);
  }
}
check(H5PThemerMemoryStorage::$raw === $bytes && H5PThemerMemoryStorage::$writes === $writes && $runtime_cache_invalidations === $invalidations, 'Catalog reads never save/invalidate/create assets');
$legacy = fixture_theme('daylight'); $legacy['label'] = 'Shared label'; $legacy['description'] = 'PRIVATE ADMIN DESCRIPTION';
$pinned = fixture_pinned_theme('mint_copy', 'mint'); $pinned['label'] = 'Shared label'; $pinned['colors'] = array('--h5p-theme-main-cta-base' => '#3366CC');
seed('h5p_themer.theme.daylight', $legacy); seed('h5p_themer.theme.mint_copy', $pinned);
$catalog = h5p_themer_theme_catalog();
check(count($catalog['data']) === 9 && array_slice(array_keys($catalog['data']), -2) === array('custom:daylight', 'custom:mint_copy'), 'Both custom schemas included and tie sorted by ID');
check($catalog['data']['custom:daylight']['reference'] === array('kind' => 'custom', 'id' => 'daylight'), 'Custom identity can collide with built-in without conflation');
$resolved = h5p_themer_selection_resolve($catalog['data']['custom:mint_copy']['reference']);
$expected = h5p_themer_preset_load('mint', $version)['data']['colors']; $expected['--h5p-theme-main-cta-base'] = '#3366CC';
check($resolved['data']['colors'] === $expected, 'Catalog reference resolves pinned inheritance and saved override exactly');
check(h5p_themer_selection_resolve($catalog['data']['custom:daylight']['reference'])['data']['colors'] === $legacy['colors'], 'Legacy sparse hex values remain exact');
check(strpos(json_encode($catalog), 'PRIVATE ADMIN DESCRIPTION') === FALSE && !isset($catalog['data']['custom:mint_copy']['base_preset']), 'No admin description/provenance/config metadata exposed');
$GLOBALS['config']['h5p_themer.theme.mint_copy'] = array('label' => '<b>Effective name</b>', 'colors' => array('--h5p-theme-main-cta-base' => '#AbC'));
$catalog = h5p_themer_theme_catalog();
check($catalog['data']['custom:mint_copy']['label'] === '<b>Effective name</b>', 'Catalog uses effective labels as raw text for context escaping');
check(h5p_themer_selection_resolve($catalog['data']['custom:mint_copy']['reference'])['data']['colors']['--h5p-theme-main-cta-base'] === '#AbC', 'Catalog references reuse effective color override resolution');
$GLOBALS['config']['h5p_themer.theme.mint_copy']['colors'] = array('--h5p-theme-main-cta-base' => 'url(unsafe)');
check(!isset(h5p_themer_theme_catalog()['data']['custom:mint_copy']), 'Invalid effective custom excluded');
$GLOBALS['config'] = array();
foreach (array('color', 'base', 'identity') as $case) {
  $invalid = fixture_pinned_theme('bad_' . $case, 'mint');
  if ($case === 'color') { $invalid['colors'] = array('--h5p-theme-background' => 'red'); }
  if ($case === 'base') { $invalid['base_preset']['version'] = 'unknown'; }
  if ($case === 'identity') { $invalid['id'] = 'wrong'; }
  seed('h5p_themer.theme.bad_' . $case, $invalid);
}
H5PThemerMemoryStorage::$raw['./active']['h5p_themer.theme.bad_json'] = '{broken';
H5PThemerMemoryStorage::$raw['./active']['h5p_themer.theme.bad_scalar'] = '123';
seed('h5p_themer.theme.bad-id', fixture_theme('bad-id'));
$bytes = H5PThemerMemoryStorage::$raw; $writes = H5PThemerMemoryStorage::$writes;
$catalog = h5p_themer_theme_catalog();
check(count($catalog['data']) === 9 && !$catalog['errors'], 'Malformed JSON/scalar/color/base/identity/unsafe-ID themes excluded individually');
check(H5PThemerMemoryStorage::$raw === $bytes && H5PThemerMemoryStorage::$writes === $writes, 'Malformed definitions remain byte-for-byte untouched');
config('h5p_themer.theme.mint_copy')->set('label', 'New name')->save();
check(h5p_themer_theme_catalog()['data']['custom:mint_copy']['label'] === 'New name', 'Uncached catalog reflects saved label immediately');
config('h5p_themer.theme.mint_copy')->delete();
check(!isset(h5p_themer_theme_catalog()['data']['custom:mint_copy']), 'Uncached catalog reflects deletion without cache flush');
seed('h5p_themer.theme.new_theme', fixture_theme('new_theme'));
check(isset(h5p_themer_theme_catalog()['data']['custom:new_theme']), 'Uncached catalog reflects added theme');
$site_default = h5p_themer_theme_catalog()['data']['site_default'];
seed('h5p_themer.settings', fixture_typed_settings($builtin));
check(h5p_themer_theme_catalog()['data']['site_default'] === $site_default && h5p_themer_default_selection_load()['data']['id'] === 'mint', 'Site default stays a dynamic sentinel, not a Core/Daylight/Mint snapshot');
H5PThemerMemoryStorage::$failList = TRUE;
$bytes = H5PThemerMemoryStorage::$raw;
$failure = h5p_themer_theme_catalog();
check($failure['data'] === NULL && count($failure['errors']) === 1 && strpos($failure['errors'][0], 'bad_json') === FALSE, 'Enumeration failure returns generic error, never partial catalog/private diagnostics');
check(H5PThemerMemoryStorage::$raw === $bytes, 'Listing failure makes no repairs');
H5PThemerMemoryStorage::$failList = FALSE;
$catalog = h5p_themer_theme_catalog();
$settings = fixture_typed_settings($builtin); $settings['learner_selection_enabled'] = TRUE; seed('h5p_themer.settings', $settings);
check(h5p_themer_theme_catalog() === $catalog, 'Catalog is reusable and independent of enabled state');
$routes = h5p_themer_menu();
$base = 'admin/config/content/h5p-themer';
check(function_exists('h5p_themer_h5p_scripts_alter') && array_keys($routes) === array($base, $base . '/list', $base . '/add', $base . '/default', $base . '/%/edit', $base . '/%/duplicate', $base . '/%/delete', $base . '/presets/%/%', $base . '/presets/%/%/duplicate'), 'Parent-only learner hook and exact protected administration routes');
foreach (array(FALSE, TRUE) as $enabled) {
  admin_reset();
  $settings = fixture_settings(); $settings['learner_selection_enabled'] = $enabled; seed('h5p_themer.settings', $settings);
  $calls = $runtime_wrapper_calls;
  foreach (array('div', 'iframe', 'external', 'editor') as $mode) {
    $styles = array(array('path' => 'existing-library.css'));
    h5p_themer_h5p_styles_alter($styles, array(), $mode);
    check($styles === array(array('path' => 'existing-library.css')), 'Toggle keeps Core playback/editor asset attachment unchanged: ' . $mode);
  }
  check($runtime_wrapper_calls > $calls, 'Both modes attempt readiness publication and retain native Core when storage is unavailable');
}
admin_reset();
