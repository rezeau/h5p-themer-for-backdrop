<?php
/**
 * @file
 * Step 2A catalog/config checks; only the existing in-memory site-free harness.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

$version = 'moodle-478fed30d2a4';
$revision = '478fed30d2a4af58638f2ddbea3603db1d4d8c5f';
// A checked-in source extraction, independent of the PHP catalog implementation.
$fixture = json_decode(file_get_contents(__DIR__ . '/fixtures/moodle-presets-478fed30d2a4.json'), TRUE);
$ids = array('daylight', 'lavender', 'mint', 'sunset', 'dark');
admin_reset();
$bytes = H5PThemerMemoryStorage::$raw;
$writes = H5PThemerMemoryStorage::$writes;
$catalog = h5p_themer_presets($version);
check($catalog['errors'] === array() && array_keys($catalog['data']) === $ids, 'Exactly five stable built-in IDs');
check(h5p_themer_preset_versions() === array($version), 'Only explicit pinned catalog versions');
$keys = array_merge(h5p_themer_color_properties(), array('--h5p-theme-contrast-cta-dark'));
sort($keys);
foreach ($ids as $id) {
  $preset = h5p_themer_preset_load($id, $version)['data'];
  $source = $fixture['themes'][$id];
  check(count($preset['colors']) === 34, '34 properties: ' . $id);
  $actual_keys = array_keys($preset['colors']); sort($actual_keys);
  check($actual_keys === $keys, 'Exact property set: ' . $id);
  check($preset['colors'] === $source['values'], 'All exact upstream values, spelling, case and order: ' . $id);
  check($preset['id'] === $id && $preset['version'] === $version && $preset['label'] === $source['label'], 'Preset identity/label/version: ' . $id);
  check($preset['density'] === NULL, 'Palette has no intrinsic density: ' . $id);
  $presentation = $source; unset($presentation['label'], $presentation['values']);
  check($preset['presentation'] === $presentation, 'Presentation separate and exact: ' . $id);
  check($preset['provenance']['revision'] === $revision && $fixture['revision'] === $revision, 'Pinned upstream revision: ' . $id);
  check($preset['provenance']['repository'] === 'Matheu46/moodle-local_h5pthemer', 'Source repository: ' . $id);
  check($preset['provenance']['path'] === ($id === 'dark' ? 'amd/src/presets.js' : 'js/h5p-theme-picker.js'), 'Source file: ' . $id);
  check($preset['provenance']['blob_sha'] === $fixture[$id === 'dark' ? 'dark_blob' : 'picker_blob'], 'Verified source blob: ' . $id);
  check($preset['provenance']['license'] === ($id === 'dark' ? 'GPL-3.0-or-later' : 'MIT'), 'Source license retained: ' . $id);
  check($preset['provenance']['copyright'] === ($id === 'dark' ? '2026 Matheus Mathias' : '2026 Oliver Tacke'), 'Source attribution: ' . $id);
  check($preset['unsupported_properties'] === array('--h5p-theme-contrast-cta-dark'), 'Extra inherited token explicitly annotated: ' . $id);
  check($preset['colors']['--h5p-theme-contrast-cta-light'] === ($id === 'dark' ? 'rgba(59, 130, 246, 0.15)' : 'color-mix(in srgb, var(--h5p-theme-main-cta-base), transparent 90%)'), 'Exact non-hex source value: ' . $id);
}
// Deep mutations of individual and enumerated results cannot alter authority.
$preset['colors']['--h5p-theme-background'] = 'tampered';
$preset['provenance']['revision'] = 'tampered';
$catalog['data']['daylight']['presentation']['color'] = 'tampered';
$catalog['data']['dark']['colors'] = array();
check(h5p_themer_preset_load('dark', $version)['data']['colors'] === $fixture['themes']['dark']['values'], 'Lookup returns no nested mutable authority');
check(h5p_themer_preset_load('daylight', $version)['data']['presentation']['color'] === 'rgb(0, 111, 191)', 'Enumeration returns no nested mutable authority');
foreach (array('latest', '', 'moodle-other', NULL, 1, array(), new stdClass()) as $bad) {
  check(h5p_themer_presets($bad)['data'] === NULL && (bool) h5p_themer_presets($bad)['errors'], 'Unknown or malformed version explicitly rejected');
}
foreach (array('Daylight', 'unknown', '', '../dark', NULL, 1, array(), new stdClass()) as $bad) {
  check(h5p_themer_preset_load($bad, $version)['data'] === NULL && (bool) h5p_themer_preset_load($bad, $version)['errors'], 'Unknown or malformed preset ID explicitly rejected');
}
check(H5PThemerMemoryStorage::$raw === $bytes && H5PThemerMemoryStorage::$writes === $writes, 'Catalog reads install nothing and perform zero writes');
check(count(config_get_names_with_prefix('h5p_themer.theme.')) === 0, 'No editable built-in config objects created');

// Legacy bytes, shorthand/case, IDs, density and default semantics survive reads.
$legacy = array_replace(fixture_theme('daylight'), array('label' => 'My existing Daylight', 'density' => 'small', 'colors' => array('--h5p-theme-background' => '#aBc', '--h5p-theme-main-cta-base' => '#Aa00fF')));
seed('h5p_themer.theme.daylight', $legacy);
seed('h5p_themer.settings', fixture_settings('daylight'));
$bytes = H5PThemerMemoryStorage::$raw; $writes = H5PThemerMemoryStorage::$writes;
check(h5p_themer_theme_load('daylight')['data'] === $legacy, 'Schema 1 loader contract and exact data preserved');
check(h5p_themer_default_theme_load()['data'] === $legacy, 'Legacy default loader returns original custom definition');
$resolved = h5p_themer_default_selection_load();
check($resolved['data']['kind'] === 'custom' && $resolved['data']['colors'] === $legacy['colors'] && $resolved['data']['density'] === 'small', 'Legacy daylight remains custom with sparse, unnormalized colors');
check(h5p_themer_selection_resolve(array('kind' => 'builtin', 'id' => 'daylight', 'version' => $version))['data']['kind'] === 'builtin', 'Built-in daylight remains a distinct identity');
check(h5p_themer_selection_resolve('daylight', 1)['data']['label'] === 'My existing Daylight', 'Legacy strings never inferred as built-ins');
check(H5PThemerMemoryStorage::$raw === $bytes && H5PThemerMemoryStorage::$writes === $writes, 'Legacy reads do not migrate, normalize or save');
seed('h5p_themer.settings', fixture_settings());
check(h5p_themer_default_theme_load() === array('data' => NULL, 'errors' => array()), 'Legacy null retains exact old loader contract');
check(h5p_themer_default_selection_load()['data']['kind'] === 'core', 'Typed resolver represents legacy null explicitly as Core');

// Every pinned base resolves losslessly; editable values remain strict hex.
function fixture_pinned_theme($id = 'my_dark', $preset = 'dark') {
  return array_replace(fixture_theme($id), array('schema_version' => 2,
    'base_preset' => array('id' => $preset, 'version' => 'moodle-478fed30d2a4'), 'colors' => array()));
}
function fixture_typed_settings($reference = NULL) {
  return array('schema_version' => 2, 'default_theme' => $reference);
}
foreach ($ids as $id) {
  $custom_id = 'copy_' . $id;
  $theme = fixture_pinned_theme($custom_id, $id);
  check(h5p_themer_config_errors('h5p_themer.theme.' . $custom_id, $theme) === array(), 'Valid pinned-base definition: ' . $id);
  seed('h5p_themer.theme.' . $custom_id, $theme);
  $bytes = H5PThemerMemoryStorage::$raw; $writes = H5PThemerMemoryStorage::$writes;
  $resolved = h5p_themer_theme_resolve($custom_id);
  check($resolved['errors'] === array() && $resolved['data']['colors'] === $fixture['themes'][$id]['values'], 'Exact effective pinned palette: ' . $id);
  check($resolved['data']['id'] === $custom_id && $resolved['data']['kind'] === 'custom', 'Custom identity independent of base: ' . $id);
  check($resolved['data']['unsupported_properties'] === array('--h5p-theme-contrast-cta-dark'), 'Resolved extra token annotated: ' . $id);
  check(h5p_themer_theme_load($custom_id)['data'] === $theme, 'Custom loader returns original overrides, not flattened base: ' . $id);
  check(H5PThemerMemoryStorage::$raw === $bytes && H5PThemerMemoryStorage::$writes === $writes, 'Resolution does not save or migrate: ' . $id);
}
$theme = fixture_pinned_theme();
$theme['density'] = 'medium';
$theme['colors'] = array('--h5p-theme-main-cta-base' => '#AbC', '--h5p-theme-background' => '#123456');
seed('h5p_themer.theme.my_dark', $theme);
$resolved = h5p_themer_theme_resolve('my_dark')['data'];
$expected = array_replace($fixture['themes']['dark']['values'], $theme['colors']);
check($resolved['colors'] === $expected && count($resolved['colors']) === 34, 'Sparse hex overlay preserves all inherited values');
check($resolved['density'] === 'medium' && $resolved['base_preset'] === $theme['base_preset'], 'Custom density and pinned identity retained');
check($resolved['colors']['--h5p-theme-contrast-cta-light'] === 'rgba(59, 130, 246, 0.15)', 'RGBA remains inherited after other edits');
$resolved['colors']['--h5p-theme-contrast-cta-light'] = '#fff';
check(h5p_themer_theme_resolve('my_dark')['data']['colors'] === $expected, 'Resolved palette mutations do not change stored data');
check(h5p_themer_preset_load('dark', $version)['data']['colors'] === $fixture['themes']['dark']['values'], 'Custom overrides do not mutate source preset');
foreach (h5p_themer_color_properties() as $property) {
  $candidate = $theme; $candidate['colors'] = array($property => '#aBc');
  check(h5p_themer_config_errors('h5p_themer.theme.my_dark', $candidate) === array(), 'Schema 2 permits existing editable property: ' . $property);
}
foreach (array('rgba(59, 130, 246, 0.15)', 'color-mix(in srgb, var(--h5p-theme-main-cta-base), transparent 90%)', 'var(--h5p-theme-background)', 'transparent', 'red', 'hsl(0, 0%, 0%)', '#1234', '#12345678', '#123;display:none', 'url(x)', '</style><script>', "#abc\n", ' #abc', '', NULL, 123, array()) as $color) {
  $candidate = $theme; $candidate['colors'] = array('--h5p-theme-contrast-cta-light' => $color);
  check((bool) h5p_themer_config_errors('h5p_themer.theme.my_dark', $candidate), 'Schema 2 rejects unsafe/non-hex user input');
}
foreach (array('--h5p-theme-contrast-cta-dark', '--h5p-theme-unknown', '--h5p-theme-font-name', 'background', 0) as $property) {
  $candidate = $theme; $candidate['colors'] = array($property => '#abc');
  check((bool) h5p_themer_config_errors('h5p_themer.theme.my_dark', $candidate), 'Schema 2 rejects unsupported override property');
}
foreach (array(NULL, 'dark', array(), array('id' => 'dark'), array('version' => $version), array('id' => 'unknown', 'version' => $version), array('id' => 'dark', 'version' => 'latest'), array('id' => array(), 'version' => $version), array('id' => 'dark', 'version' => 2), array('id' => 'dark', 'version' => $version, 'colors' => array()), array('id' => 'dark', 'version' => $version, 'kind' => 'builtin')) as $base) {
  $candidate = $theme; $candidate['base_preset'] = $base;
  check(isset(h5p_themer_config_field_errors('h5p_themer.theme.my_dark', $candidate)['base_preset']), 'Malformed/unknown pinned base rejected at base field');
}
foreach (array(array('schema_version', '2'), array('schema_version', 3), array('id', 'dark'), array('label', ''), array('density', 'wide'), array('description', "\x00"), array('colors', NULL), array('extra', TRUE)) as $invalid) {
  $candidate = $theme; $candidate[$invalid[0]] = $invalid[1];
  check((bool) h5p_themer_config_errors('h5p_themer.theme.my_dark', $candidate), 'Schema 2 retains common strict shape validation: ' . $invalid[0]);
}
$candidate = $theme; unset($candidate['base_preset']);
check((bool) h5p_themer_config_errors('h5p_themer.theme.my_dark', $candidate), 'Schema 2 requires pinned base');
$candidate = $theme; $candidate['schema_version'] = 1;
check((bool) h5p_themer_config_errors('h5p_themer.theme.my_dark', $candidate), 'Schema 1 never silently accepts a new base field');

// Typed defaults: strictly bounded object shapes, no fallback or implicit cast.
$builtin = array('kind' => 'builtin', 'id' => 'daylight', 'version' => $version);
$custom = array('kind' => 'custom', 'id' => 'my_dark');
foreach (array(NULL, $builtin, $custom) as $reference) {
  $settings = fixture_typed_settings($reference);
  check(h5p_themer_config_errors('h5p_themer.settings', $settings) === array(), 'Valid typed settings shape');
  seed('h5p_themer.settings', $settings);
  $bytes = H5PThemerMemoryStorage::$raw; $writes = H5PThemerMemoryStorage::$writes;
  $selected = h5p_themer_default_selection_load();
  $kind = $reference === NULL ? 'core' : $reference['kind'];
  check($selected['errors'] === array() && $selected['data']['kind'] === $kind, 'Global typed selection resolves ' . $kind);
  if ($kind === 'core') {
    check($selected['data']['colors'] === array() && $selected['data']['density'] === NULL && h5p_themer_default_theme_load()['data'] === NULL, 'Core supplies no overrides and old loader returns NULL');
  }
  elseif ($kind === 'builtin') {
    check($selected['data']['colors'] === $fixture['themes']['daylight']['values'] && $selected['data']['density'] === NULL, 'Built-in selection uses exact catalog without custom lookup');
    check(h5p_themer_default_theme_load()['data']['id'] === 'daylight', 'Legacy default accessor can inspect built-in label/identity');
  }
  else {
    check($selected['data']['colors'] === $expected && h5p_themer_default_theme_load()['data'] === $theme, 'Typed default resolves effective custom while preserving definition accessor');
  }
  check(H5PThemerMemoryStorage::$raw === $bytes && H5PThemerMemoryStorage::$writes === $writes, 'Typed default read does not save or migrate');
}
foreach (array('daylight', '', FALSE, 1, array(), new stdClass(), array('kind' => 'core'), array('kind' => 'custom'), array('id' => 'my_dark'), array('kind' => 'custom', 'id' => '../dark'), array('kind' => 'custom', 'id' => 'my_dark', 'version' => $version), array('kind' => 'Builtin', 'id' => 'daylight', 'version' => $version), array('kind' => 'builtin', 'id' => 'unknown', 'version' => $version), array('kind' => 'builtin', 'id' => 'daylight'), array('kind' => 'builtin', 'id' => 'daylight', 'version' => 'latest'), array('kind' => 'builtin', 'id' => 'daylight', 'version' => $version, 'colors' => array())) as $reference) {
  check((bool) h5p_themer_config_errors('h5p_themer.settings', fixture_typed_settings($reference)), 'Malformed/unknown typed selection rejected');
  $result = h5p_themer_selection_resolve($reference);
  check($result['data'] === NULL && (bool) $result['errors'], 'Malformed selection never falls back to Core or Daylight');
}
check((bool) h5p_themer_config_errors('h5p_themer.settings', fixture_settings($builtin)), 'Schema 1 rejects typed object rather than migrating it');
check(h5p_themer_selection_resolve(array('kind' => 'custom', 'id' => 'missing'))['data'] === NULL, 'Missing typed custom fails resolution');
check(h5p_themer_selection_resolve('dark', 1)['data'] === NULL, 'Missing legacy custom dark never resolves to built-in Dark');
check(h5p_themer_selection_resolve(NULL, '2')['errors'] !== array(), 'Schema version type is strict in reference API');
check(h5p_themer_selection_resolve(NULL, 2, 'unknown')['errors'] !== array(), 'Selection resolver rejects unknown storage');
check(h5p_themer_theme_resolve('../dark')['errors'] !== array(), 'Effective custom resolver validates ID before lookup');

// Effective overrides are validated, remain read-only, and are excluded from imports.
seed('h5p_themer.settings', fixture_typed_settings($custom));
$GLOBALS['config']['h5p_themer.theme.my_dark'] = array('base_preset' => array('id' => 'mint', 'version' => $version), 'colors' => array('--h5p-theme-background' => '#AbC'));
$effective = h5p_themer_default_selection_load()['data'];
check($effective['colors'] === array_replace($fixture['themes']['mint']['values'], array('--h5p-theme-background' => '#AbC')), 'Effective reads honor whole-map color and pinned-base overrides');
check(h5p_themer_config_load('h5p_themer.theme.my_dark', 'active', TRUE)['data'] === $theme, 'Original read excludes base and color overrides');
check((bool) _h5p_themer_admin_check_original('h5p_themer.theme.my_dark', $theme), 'Existing write guard detects schema 2 top-level base overrides');
$GLOBALS['config']['h5p_themer.theme.my_dark']['base_preset']['version'] = 'latest';
check(h5p_themer_default_selection_load()['data'] === NULL, 'Invalid effective base fails safely');
$GLOBALS['config'] = array('h5p_themer.settings' => array('default_theme' => $builtin));
check(h5p_themer_default_selection_load()['data']['kind'] === 'builtin', 'Typed effective default override is respected');
check((bool) _h5p_themer_admin_check_original('h5p_themer.settings', fixture_typed_settings($custom)), 'Existing write guard protects typed settings overrides');
$GLOBALS['config'] = array();
$legacy_before = h5p_themer_theme_load('daylight')['data'];
$GLOBALS['config']['h5p_themer.theme.daylight'] = array('base_preset' => array('id' => 'dark', 'version' => $version));
check(h5p_themer_theme_load('daylight')['data'] === NULL, 'New base override cannot bypass schema 1 field restrictions');
check(h5p_themer_config_load('h5p_themer.theme.daylight', 'active', TRUE)['data'] === $legacy_before, 'Invalid effective override leaves original legacy data intact');
$GLOBALS['config'] = array();

// Native single/bulk import validation, with all storage still in memory.
reset_storage();
seed('h5p_themer.settings', fixture_settings());
$bytes = H5PThemerMemoryStorage::$raw;
rejects(function () use ($custom) { config_sync_validate_file('h5p_themer.settings', 'update', NULL, fixture_typed_settings($custom)); }, 'Single typed import rejects missing custom');
rejects(function () use ($version) { config_sync_validate_file('h5p_themer.settings', 'update', NULL, fixture_typed_settings(array('kind' => 'builtin', 'id' => 'dark', 'version' => 'latest'))); }, 'Import rejects unknown catalog version');
$bad = fixture_pinned_theme(); $bad['base_preset']['version'] = 'latest';
rejects(function () use ($bad) { config_sync_validate_file('h5p_themer.theme.my_dark', 'create', NULL, $bad); }, 'Import rejects invalid pinned base');
$bad = fixture_pinned_theme(); $bad['colors'] = array('--h5p-theme-background' => 'rgba(0,0,0,1)');
rejects(function () use ($bad) { config_sync_validate_file('h5p_themer.theme.my_dark', 'create', NULL, $bad); }, 'Import rejects non-hex custom override');
rejects(function () { config_sync_validate_file('h5p_themer.preset.dark', 'create', NULL, array()); }, 'Catalog cannot be created or overwritten as editable config');
check(H5PThemerMemoryStorage::$raw === $bytes, 'Rejected imports perform zero active writes');
config_sync_validate_file('h5p_themer.settings', 'update', NULL, fixture_typed_settings($builtin));
config_sync_file('h5p_themer.settings', 'update', fixture_typed_settings($builtin));
check(h5p_themer_default_selection_load()['data']['kind'] === 'builtin' && config_get_names_with_prefix('h5p_themer.theme.') === array(), 'Built-in default imports without installing theme objects');
seed('h5p_themer.theme.daylight', fixture_theme('daylight'));
config_sync_validate_file('h5p_themer.theme.daylight', 'delete');
check(TRUE, 'Built-in default does not protect unrelated custom daylight');
seed('h5p_themer.theme.my_dark', $theme, 'staging');
seed('h5p_themer.settings', fixture_typed_settings($custom), 'staging');
$changes = array('h5p_themer.settings' => 'update', 'h5p_themer.theme.my_dark' => 'create');
config_sync_validate_file('h5p_themer.settings', 'update', $changes);
config_sync_validate_file('h5p_themer.theme.my_dark', 'create', $changes);
check(config('h5p_themer.theme.my_dark')->isNew(), 'Typed bulk reference validated from staging before active custom exists');
config_sync_file('h5p_themer.settings', 'update');
check(h5p_themer_default_selection_load()['errors'] !== array(), 'Intermediate nontransactional import reports missing target without fallback');
config_sync_file('h5p_themer.theme.my_dark', 'create');
check(h5p_themer_default_selection_load()['data']['colors'] === $expected, 'Settings-first typed import resolves exact schema 2 palette');
check(h5p_themer_theme_load('daylight')['data']['schema_version'] === 1, 'Mixed schema objects coexist after import');
foreach (array('h5p_themer.settings', 'h5p_themer.theme.my_dark', 'h5p_themer.theme.daylight') as $name) {
  $original = config($name)->getOriginal();
  $exported = config_get_config_storage()->encode($original);
  $round_trip = json_decode($exported, TRUE);
  check($round_trip === $original, 'Native JSON export preserves representation: ' . $name);
  config_sync_validate_file($name, 'update', NULL, $round_trip);
  config_sync_file($name, 'update', $round_trip);
  check(config($name)->getOriginal() === $original, 'Native import round trip preserves schema and data: ' . $name);
}
rejects(function () { config_sync_validate_file('h5p_themer.theme.my_dark', 'delete'); }, 'Typed selected custom protected from single deletion');
rejects(function () use ($custom) { config_sync_validate_file('h5p_themer.settings', 'update', array('h5p_themer.theme.my_dark' => 'delete'), fixture_typed_settings($custom)); }, 'Typed default cannot select a deleted custom');
seed('h5p_themer.settings', fixture_typed_settings($builtin), 'staging');
$changes = array('h5p_themer.settings' => 'update', 'h5p_themer.theme.my_dark' => 'delete');
config_sync_validate_file('h5p_themer.theme.my_dark', 'delete', $changes);
config_sync_validate_file('h5p_themer.settings', 'update', $changes);
config_sync_file('h5p_themer.settings', 'update');
config_sync_file('h5p_themer.theme.my_dark', 'delete');
check(h5p_themer_default_selection_load()['data']['kind'] === 'builtin' && config('h5p_themer.theme.my_dark')->isNew(), 'Bulk replacement with built-in releases selected custom');
seed('h5p_themer.theme.daylight', fixture_pinned_theme('daylight', 'dark'));
seed('h5p_themer.settings', fixture_settings('daylight'));
check(h5p_themer_default_selection_load()['data']['kind'] === 'custom', 'Legacy settings may reference a schema 2 custom with colliding built-in name');
rejects(function () { config_sync_validate_file('h5p_themer.theme.daylight', 'delete'); }, 'Legacy selected-custom protection retained for schema 2 target');
$bad = fixture_pinned_theme('future', 'dark'); $bad['base_preset']['id'] = 'unknown';
seed('h5p_themer.theme.future', $bad, 'staging');
seed('h5p_themer.settings', fixture_typed_settings(array('kind' => 'custom', 'id' => 'future')), 'staging');
$changes = array('h5p_themer.settings' => 'update', 'h5p_themer.theme.future' => 'create', 'h5p_themer.theme.daylight' => 'delete');
rejects(function () use ($changes) { config_sync_validate_file('h5p_themer.settings', 'update', $changes); }, 'Typed reference rejects malformed staged target');
rejects(function () use ($changes) { config_sync_validate_file('h5p_themer.theme.future', 'create', $changes); }, 'Malformed staged target rejects its own import');
// Imports validate originals even where effective overrides intentionally differ.
$GLOBALS['config']['h5p_themer.settings'] = array('schema_version' => 2, 'default_theme' => $builtin);
rejects(function () { config_sync_validate_file('h5p_themer.theme.daylight', 'delete'); }, 'Effective built-in override cannot release stored legacy custom reference');
$GLOBALS['config'] = array();
seed('h5p_themer.settings', fixture_typed_settings());
config_sync_validate_file('h5p_themer.theme.daylight', 'delete');
check(TRUE, 'Explicit schema 2 null releases custom reference');
config_sync_validate_file('h5p_themer.theme.daylight', 'delete', array('h5p_themer.settings' => 'delete'));
check(TRUE, 'Explicit settings deletion still releases reference');

// Boundaries: read APIs and requirement checks never emit assets or write data.
$bytes = H5PThemerMemoryStorage::$raw; $writes = H5PThemerMemoryStorage::$writes;
$api_before = H5PCore::$coreApi; H5PCore::$coreApi['minorVersion'] = 29;
ob_start();
h5p_themer_presets($version);
h5p_themer_selection_resolve($builtin);
h5p_themer_default_selection_load();
h5p_themer_requirements('runtime');
$output = ob_get_clean();
H5PCore::$coreApi = $api_before;
check($output === '', 'Read APIs emit no CSS, JavaScript or other output');
check(H5PThemerMemoryStorage::$raw === $bytes && H5PThemerMemoryStorage::$writes === $writes, 'Catalog, resolution and compatibility failure make zero config writes');
check(!function_exists('h5p_themer_install') && !function_exists('h5p_themer_update_1000'), 'No automatic catalog installation or migration hook');
check(function_exists('h5p_themer_h5p_scripts_alter') && function_exists('h5p_themer_h5p_styles_alter'), 'Global colors and parent-only learner attachment hooks exist');
check(count(h5p_themer_menu()) === 9, 'Only built-in view and duplicate routes added; no runtime routes');
$shipped = json_decode(file_get_contents(dirname(__DIR__) . '/config/h5p_themer.settings.json'), TRUE);
check($shipped['schema_version'] === 1 && $shipped['default_theme'] === NULL, 'Shipped schema/default remain unchanged');
