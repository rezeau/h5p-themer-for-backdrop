<?php
/**
 * @file
 * Focused Phase 1A checks. SPDX-License-Identifier: GPL-2.0-or-later.
 */

require __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);
$info = parse_ini_file($root . '/h5p_themer.info', FALSE, INI_SCANNER_RAW);
check($info['type'] === 'module', 'Module metadata type');
check($info['backdrop'] === '1.x', 'Backdrop API declaration');
check($info['dependencies'] === array('h5p'), 'H5P dependency declaration');
check(strpos($info['description'], 'Global color overrides') !== FALSE, 'Complete metadata description');
check($info['configure'] === 'admin/config/content/h5p-themer', 'Administration configure route advertised');
check(function_exists('h5p_themer_menu') && !function_exists('h5p_themer_h5p_scripts_alter') && function_exists('h5p_themer_h5p_styles_alter'), 'Administration hooks and only the global color styles hook exist');
$definitions = h5p_themer_config_info();
check(isset($definitions['h5p_themer.settings']), 'Settings registered');
check($definitions['h5p_themer.theme']['name_key'] === 'id' && $definitions['h5p_themer.theme']['label_key'] === 'label', 'Identity separate from label');
check(h5p_themer_requirements('update') === array(), 'No update-phase side effects');

// Test unavailable states before loading the real integration and Core class.
check(h5p_themer_compatibility()['code'] === 'h5p_unavailable', 'Enabled flag without actual H5P integration is unavailable');
require_once $h5p_root . '/h5p.module';
$h5p_enabled = FALSE;
check(h5p_themer_compatibility()['code'] === 'h5p_unavailable', 'Disabled H5P rejected');
check(h5p_themer_requirements('install')['h5p_themer_core_api']['severity'] === REQUIREMENT_ERROR, 'Unavailable dependency blocks installation check');
$h5p_enabled = TRUE;
check(h5p_themer_compatibility()['code'] === 'core_unavailable', 'Missing Core class rejected safely');
$broken_loader = function ($class) {
  if ($class === 'H5PCore') {
    throw new RuntimeException('Simulated damaged autoload mapping.');
  }
};
spl_autoload_register($broken_loader);
check(h5p_themer_compatibility()['code'] === 'core_unavailable', 'Autoload failure rejected safely');
spl_autoload_unregister($broken_loader);

// Read the actual H5P mapping. Load only its mapped Core definitions, never a
// site bootstrap or _h5p_get_instance(). Leave mapping to other classes unused.
$mapping = h5p_autoload_info();
check($mapping['H5PCore'] === 'library/h5p.classes.php', 'Actual integration Core mapping');
spl_autoload_register(function ($class) use ($mapping, $h5p_root) {
  if ($class === 'H5PCore') {
    require_once $h5p_root . '/' . $mapping[$class];
  }
});
check(h5p_themer_compatibility()['compatible'] === TRUE, 'Actual Core API 1.28 detected through integration mapping');
$api = H5PCore::$coreApi;
check($api === array('majorVersion' => 1, 'minorVersion' => 28), 'Actual installed API exactly 1.28');
foreach (array(array(1, 27), array(1, 26), array(1, 29), array(2, 28), array(0, 28)) as $version) {
  H5PCore::$coreApi = array('majorVersion' => $version[0], 'minorVersion' => $version[1]);
  check(h5p_themer_compatibility()['code'] === 'api_incompatible', 'Reject API ' . implode('.', $version));
}
foreach (array(NULL, FALSE, '1.28', 128, new stdClass(), array(), array('majorVersion' => 1), array('minorVersion' => 28), array('majorVersion' => '1', 'minorVersion' => 28), array('majorVersion' => 1, 'minorVersion' => '28'), array('majorVersion' => 1.0, 'minorVersion' => 28), array('majorVersion' => 1, 'minorVersion' => NULL), array('majorVersion' => -1, 'minorVersion' => 28)) as $invalid) {
  H5PCore::$coreApi = $invalid;
  check(h5p_themer_compatibility()['code'] === 'api_malformed', 'Malformed API rejected');
}
H5PCore::$coreApi = $api;
check(h5p_themer_requirements('install')['h5p_themer_core_api']['severity'] === REQUIREMENT_OK, 'Compatible installation status');

// Verify allowlist against the actual stylesheet, using parsing in tests only.
$css = file_get_contents($h5p_root . '/library/styles/h5p-theme-variables.css');
preg_match_all('/^\s*(--h5p-theme-[a-z0-9-]+):\s*(?:#[a-f0-9]+|color-mix\([^;]+)\s*;/mi', $css, $matches);
$source_properties = $matches[1];
$allowed = h5p_themer_color_properties();
sort($source_properties);
sort($allowed);
check($allowed === $source_properties, 'Allowlist exactly matches Core 1.28 color declarations');
check(count($allowed) === count(array_unique($allowed)), 'No duplicate allowlist entries');
foreach (h5p_themer_color_properties() as $property) {
  $theme = fixture_theme();
  $theme['colors'] = array($property => '#AbC');
  check(h5p_themer_config_errors('h5p_themer.theme.ocean', $theme) === array(), 'Supported color: ' . $property);
}
foreach (array('ocean', 'ocean_2', 'a', str_repeat('a', 64)) as $id) {
  check(h5p_themer_valid_theme_id($id), 'Valid theme ID: ' . $id);
}
foreach (array('', 'Ocean', '2ocean', 'ocean-blue', 'a.b', '../ocean', "ocean\n", 'océan', str_repeat('a', 65), NULL, 1, array(), new stdClass()) as $id) {
  check(!h5p_themer_valid_theme_id($id), 'Invalid theme ID');
  check(h5p_themer_theme_load($id)['data'] === NULL, 'Invalid ID cannot access storage');
}
foreach (array('large', 'medium', 'small') as $density) {
  $theme = fixture_theme();
  $theme['density'] = $density;
  check(h5p_themer_config_errors('h5p_themer.theme.ocean', $theme) === array(), 'Valid density ' . $density);
}
foreach (array('', 'wide', 'compact', 'h5p-large', 'LARGE', 'large ', 0, NULL, array()) as $density) {
  $theme = fixture_theme();
  $theme['density'] = $density;
  check((bool) h5p_themer_config_errors('h5p_themer.theme.ocean', $theme), 'Invalid density');
}
foreach (array('#abc', '#ABC', '#123456', '#aB90fF') as $color) {
  $theme = fixture_theme();
  $theme['colors']['--h5p-theme-main-cta-base'] = $color;
  check(h5p_themer_config_errors('h5p_themer.theme.ocean', $theme) === array(), 'Valid color ' . $color);
}
foreach (array('', '#12', '#1234', '#12345678', '#gggggg', 'red', 'rgb(1,2,3)', 'var(--x)', 'color-mix(in srgb, red, blue)', '#123;display:none', 'url(https://example.invalid)', 'expression(alert(1))', '</style><script>', "#123\n", ' #123', 123, NULL, array()) as $color) {
  $theme = fixture_theme();
  $theme['colors']['--h5p-theme-main-cta-base'] = $color;
  check((bool) h5p_themer_config_errors('h5p_themer.theme.ocean', $theme), 'Unsafe or unsupported color rejected');
}
foreach (array('--h5p-theme-unknown', '--h5p-theme-font-name', '--h5p-theme-spacing-l', '--h5p-theme-border-radius-small', '--h5p-theme-contrast-cta-dark', 'color', 0) as $key) {
  $theme = fixture_theme();
  $theme['colors'] = array($key => '#123');
  check((bool) h5p_themer_config_errors('h5p_themer.theme.ocean', $theme), 'Unsupported property rejected');
}
foreach (array('schema_version', 'id', 'label', 'density', 'colors') as $key) {
  $theme = fixture_theme();
  unset($theme[$key]);
  check((bool) h5p_themer_config_errors('h5p_themer.theme.ocean', $theme), 'Required field ' . $key);
}
foreach (array(array('schema_version', '1'), array('schema_version', 2), array('id', 'other'), array('label', ''), array('label', "a\n"), array('label', str_repeat('a', 256)), array('label', "\xFF"), array('description', array()), array('description', "\x00"), array('description', str_repeat('a', 4097)), array('colors', 'red'), array('colors', array('#123')), array('custom_css', 'body{}'), array('_config_name', 'other.name'), array('_config_static', 'true')) as $invalid) {
  $theme = fixture_theme();
  $theme[$invalid[0]] = $invalid[1];
  check((bool) h5p_themer_config_errors('h5p_themer.theme.ocean', $theme), 'Malformed theme field ' . $invalid[0]);
}
$theme = fixture_theme();
unset($theme['description']);
$theme['colors'] = array();
check(h5p_themer_config_errors('h5p_themer.theme.ocean', $theme) === array(), 'Optional description and empty palette accepted');
foreach (array(NULL, FALSE, 'broken', 1, new stdClass(), array()) as $data) {
  check((bool) h5p_themer_config_errors('h5p_themer.theme.ocean', $data), 'Malformed theme object');
}
check((bool) h5p_themer_config_errors('h5p_themer.settings.extra', fixture_settings()), 'Unknown configuration object rejected');
foreach (array(array(), array('schema_version' => 1), array('schema_version' => 3, 'default_theme' => NULL), array('schema_version' => 1, 'default_theme' => ''), array('schema_version' => 1, 'default_theme' => '../ocean'), array('schema_version' => 1, 'default_theme' => FALSE)) as $data) {
  check((bool) h5p_themer_config_errors('h5p_themer.settings', $data), 'Malformed settings rejected');
}
$shipped = json_decode(file_get_contents($root . '/config/h5p_themer.settings.json'), TRUE);
check(h5p_themer_config_errors('h5p_themer.settings', $shipped) === array(), 'Shipped settings validate');
check($shipped['default_theme'] === NULL, 'Installation starts without a selected theme');

// Exercise native Config objects, JSON round trips, and import validation hooks.
reset_storage();
check(h5p_themer_default_theme_load()['errors'] !== array(), 'Missing settings reported');
seed('h5p_themer.settings', fixture_settings());
check(h5p_themer_default_theme_load() === array('data' => NULL, 'errors' => array()), 'Explicitly unset default is safe');
seed('h5p_themer.settings', fixture_settings('ocean'));
check(h5p_themer_default_theme_load()['errors'] !== array(), 'Missing referenced theme reported');
seed('h5p_themer.theme.ocean', fixture_theme());
check(h5p_themer_default_theme_load()['data']['id'] === 'ocean', 'Default resolved through native Config');
$before = h5p_themer_theme_load('ocean')['data'];
config('h5p_themer.theme.ocean')->set('label', 'Renamed label')->save();
$after = h5p_themer_default_theme_load()['data'];
check($after['id'] === $before['id'] && $after['label'] === 'Renamed label', 'Label edit preserves identity and references');
check(config_get_names_with_prefix('h5p_themer.theme.') === array('h5p_themer.theme.ocean'), 'Native prefix enumeration');
$GLOBALS['config']['h5p_themer.theme.ocean'] = array('density' => 'small');
check(h5p_themer_theme_load('ocean')['data']['density'] === 'small', 'Effective read respects field-level override');
check(h5p_themer_config_load('h5p_themer.theme.ocean', 'active', TRUE)['data']['density'] === 'large', 'Import reads original data without overrides');
$GLOBALS['config'] = array();
$writes = H5PThemerMemoryStorage::$writes;
$bytes = H5PThemerMemoryStorage::$raw;
$h5p_enabled = FALSE;
check(!h5p_themer_compatibility()['compatible'], 'Compatibility failure after configuration exists');
check(h5p_themer_default_theme_load()['data']['id'] === 'ocean', 'Stored definitions remain inspectable on dependency failure');
$requirements = h5p_themer_requirements('runtime');
check($requirements['h5p_themer_core_api']['severity'] === REQUIREMENT_ERROR, 'Runtime reports disabled dependency');
check(H5PThemerMemoryStorage::$raw === $bytes && H5PThemerMemoryStorage::$writes === $writes, 'Dependency failure preserves exact stored bytes with zero writes');
$h5p_enabled = TRUE;
H5PCore::$coreApi['minorVersion'] = 29;
h5p_themer_requirements('runtime');
check(H5PThemerMemoryStorage::$raw === $bytes && H5PThemerMemoryStorage::$writes === $writes, 'Changed API preserves data with zero writes');
H5PCore::$coreApi = $api;
foreach (array('{broken', 'null', '"scalar"', '123', 'true', '[]', '{}', '{"schema_version":1,"default_theme":"missing"}') as $raw) {
  H5PThemerMemoryStorage::$raw['./active']['h5p_themer.settings'] = $raw;
  $result = h5p_themer_default_theme_load();
  check($result['data'] === NULL && $result['errors'] !== array(), 'Malformed stored JSON/config fails safely');
  check(H5PThemerMemoryStorage::$raw['./active']['h5p_themer.settings'] === $raw, 'Malformed data not silently repaired');
}
check(h5p_themer_config_load('../unsafe')['data'] === NULL, 'Unsafe configuration path rejected');
check(h5p_themer_config_load('h5p_themer.settings', 'other')['data'] === NULL, 'Unknown storage type rejected');

reset_storage();
seed('h5p_themer.settings', fixture_settings());
$invalid = fixture_theme();
$invalid['density'] = 'unsafe';
$bytes = H5PThemerMemoryStorage::$raw;
rejects(function () use ($invalid) { config_sync_validate_file('h5p_themer.theme.ocean', 'create', NULL, $invalid); }, 'Native single-import rejects invalid definition');
rejects(function () { config_sync_validate_file('h5p_themer.settings', 'update', NULL, fixture_settings('missing')); }, 'Single import rejects missing default');
check(H5PThemerMemoryStorage::$raw === $bytes, 'Rejected imports make no writes');
seed('h5p_themer.theme.ocean', fixture_theme(), 'staging');
seed('h5p_themer.settings', fixture_settings('ocean'), 'staging');
$changes = array('h5p_themer.settings' => 'update', 'h5p_themer.theme.ocean' => 'create');
config_sync_validate_file('h5p_themer.settings', 'update', $changes);
config_sync_validate_file('h5p_themer.theme.ocean', 'create', $changes);
check(config('h5p_themer.theme.ocean')->isNew(), 'Bulk reference validates before theme exists in active storage');
// Apply settings first deliberately: validation uses final staged data.
config_sync_file('h5p_themer.settings', 'update');
config_sync_file('h5p_themer.theme.ocean', 'create');
check(h5p_themer_default_theme_load()['data']['id'] === 'ocean', 'Native bulk import round trip');
$export = config_get_config_storage()->encode(config('h5p_themer.theme.ocean')->getOriginal());
check(json_decode($export, TRUE)['id'] === 'ocean', 'Native configuration JSON export');
rejects(function () { config_sync_validate_file('h5p_themer.theme.ocean', 'delete'); }, 'Single import cannot delete default theme');
rejects(function () { config_sync_validate_file('h5p_themer.settings', 'update', array('h5p_themer.theme.ocean' => 'delete'), fixture_settings('ocean')); }, 'Bulk import cannot select deleted theme');
$bad_identity = fixture_theme();
$bad_identity['id'] = 'renamed_id';
rejects(function () use ($bad_identity) { config_sync_validate_file('h5p_themer.theme.ocean', 'update', NULL, $bad_identity); }, 'Import cannot change identity under existing filename');
seed('h5p_themer.settings', fixture_settings(), 'staging');
$changes = array('h5p_themer.settings' => 'update', 'h5p_themer.theme.ocean' => 'delete');
config_sync_validate_file('h5p_themer.theme.ocean', 'delete', $changes);
config_sync_validate_file('h5p_themer.settings', 'update', $changes);
config_sync_file('h5p_themer.settings', 'update');
config_sync_file('h5p_themer.theme.ocean', 'delete');
check(h5p_themer_default_theme_load() === array('data' => NULL, 'errors' => array()), 'Clear default and delete theme in same bulk import');
check(config('h5p_themer.theme.ocean')->isNew(), 'Native import deletion applied');

// Qualify reference edge cases against the actual native import validator.
seed('h5p_themer.theme.ocean', fixture_theme());
seed('h5p_themer.settings', fixture_settings('ocean'));
$bad_theme = fixture_theme('forest');
$bad_theme['colors'] = array('--h5p-theme-background' => 'url(unsafe)');
seed('h5p_themer.theme.forest', $bad_theme, 'staging');
seed('h5p_themer.settings', fixture_settings('forest'), 'staging');
$swap = array('h5p_themer.settings' => 'update', 'h5p_themer.theme.ocean' => 'delete', 'h5p_themer.theme.forest' => 'create');
rejects(function () use ($swap) { config_sync_validate_file('h5p_themer.settings', 'update', $swap); }, 'Default cannot reference a malformed staged theme');
seed('h5p_themer.theme.forest', fixture_theme('forest'), 'staging');
config_sync_validate_file('h5p_themer.settings', 'update', $swap);
config_sync_validate_file('h5p_themer.theme.ocean', 'delete', $swap);
config_sync_validate_file('h5p_themer.theme.forest', 'create', $swap);
config_sync_file('h5p_themer.theme.forest', 'create');
config_sync_file('h5p_themer.settings', 'update');
config_sync_file('h5p_themer.theme.ocean', 'delete');
check(h5p_themer_default_theme_load()['data']['id'] === 'forest', 'Replace default and delete previous theme in one import');
$bytes = H5PThemerMemoryStorage::$raw;
check(h5p_themer_theme_load('forest')['data']['id'] === 'forest', 'Repeated reads preserve definition');
check(H5PThemerMemoryStorage::$raw === $bytes, 'Configuration reads make no writes');
config('h5p_themer.theme.forest')->set('label', '<b>Forest</b>')->save();
check(h5p_themer_requirements('runtime')['h5p_themer_configuration']['value'] === '&lt;b&gt;Forest&lt;/b&gt;', 'Status escapes user-controlled label');
config('h5p_themer.theme.forest')->set('density', 'invalid')->save();
check(h5p_themer_default_theme_load()['errors'] !== array(), 'Malformed referenced stored theme fails safely');
check(h5p_themer_requirements('runtime')['h5p_themer_configuration']['severity'] === REQUIREMENT_ERROR, 'Status reports malformed selected theme');
config_sync_validate_file('h5p_themer.theme.forest', 'delete', array('h5p_themer.settings' => 'delete', 'h5p_themer.theme.forest' => 'delete'));
check(TRUE, 'Explicit settings deletion releases default reference');
config_sync_validate_file('h5p_themer.settings', 'delete');
config_sync_file('h5p_themer.settings', 'delete');
check(h5p_themer_requirements('runtime')['h5p_themer_configuration']['severity'] === REQUIREMENT_ERROR, 'Deleted settings reported without recreation');
rejects(function () { config_sync_validate_file('h5p_themer.theme.forest', 'delete'); }, 'Missing settings require explicit repair/deletion before theme deletion');
config_sync_validate_file('h5p_themer.settings', 'create', NULL, fixture_settings());
config_sync_file('h5p_themer.settings', 'create', fixture_settings());
check(h5p_themer_default_theme_load() === array('data' => NULL, 'errors' => array()), 'Native settings create import');

require __DIR__ . '/admin.php';
require __DIR__ . '/config-compatibility.php';
require __DIR__ . '/preset-admin.php';
require __DIR__ . '/form-enhancements.php';
require __DIR__ . '/runtime.php';
require __DIR__ . '/preview.php';
require __DIR__ . '/preview-live.php';
printf("PHP %s: %d passed, %d failed\n", PHP_VERSION, $passed, $failed);
exit($failed ? 1 : 0);
