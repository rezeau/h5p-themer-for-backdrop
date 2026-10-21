<?php
/**
 * @file
 * Step 2B forms through native Form API and isolated in-memory configuration.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

admin_reset(); admin_publication_fixture();
$version = 'moodle-478fed30d2a4';
$ids = array('daylight', 'lavender', 'mint', 'sunset', 'dark');
$base = 'admin/config/content/h5p-themer';
$catalog_before = h5p_themer_presets($version)['data'];
$bytes = H5PThemerMemoryStorage::$raw; $writes = H5PThemerMemoryStorage::$writes;
$overview = h5p_themer_overview();
check(isset($overview['core'], $overview['builtins'], $overview['themes']), 'Three distinct overview categories');
check(count($overview['core']['#rows']) === 1 && count($overview['builtins']['#rows']) === 5 && $overview['themes']['#rows'] === array(), 'Core, five built-ins, initially no custom objects');
check($overview['core']['#rows'][0][2] === 'Yes' && $overview['core']['#rows'][0][3] === 'Yes', 'Core stored/effective indicators initially selected');
$routes = h5p_themer_menu();
check($routes[$base . '/presets/%/%']['page arguments'] === array(5, 6), 'View route takes explicit ID and version');
check($routes[$base . '/presets/%/%/duplicate']['page arguments'] === array('h5p_themer_theme_form', 'preset_duplicate', 5, 6), 'Duplicate route takes explicit ID and version');
check(!isset($routes[$base . '/presets/%/%/edit'], $routes[$base . '/presets/%/%/delete']), 'No built-in edit/delete routes');
foreach ($ids as $index => $id) {
  $row = $overview['builtins']['#rows'][$index];
  check(array_keys($row[5]['data']['#links']) === array('view', 'duplicate'), 'Built-in has only View and Duplicate: ' . $id);
  check($row[5]['data']['#links']['view']['href'] === $base . '/presets/' . $id . '/' . $version, 'Versioned built-in URL: ' . $id);
  $view = h5p_themer_preset_view($id, $version);
  check(count($view['colors']['#rows']) === 34, 'Read-only view exposes all 34 colors: ' . $id);
  $expected = array();
  foreach ($catalog_before[$id]['colors'] as $key => $value) {
    $expected[] = array('<code>' . check_plain($key) . '</code>', '<code>' . check_plain($value) . '</code>');
  }
  check($view['colors']['#rows'] === $expected, 'Read-only view shows every exact escaped source value: ' . $id);
  check(isset($view['duplicate']) && !isset($view['edit'], $view['delete'], $view['actions']), 'Read-only view has no mutation form: ' . $id);
  check(strpos($view['compatibility']['#markup'], 'contrast-cta-dark') !== FALSE && strpos($view['compatibility']['#markup'], 'not established') !== FALSE, 'Unsupported token explained: ' . $id);
}
check(!isset(h5p_themer_preset_view('unknown', $version)['colors']), 'Unknown preset view rejected');
check(!isset(h5p_themer_preset_view('dark', 'latest')['colors']), 'Unknown version view rejected');
$escaped = _h5p_themer_admin_palette_table(array('--x<script>' => '</code><script>alert(1)</script>'));
check(strpos($escaped['#rows'][0][0], '<script>') === FALSE && strpos($escaped['#rows'][0][1], '<script>') === FALSE && strpos($escaped['#rows'][0][1], '&lt;script&gt;') !== FALSE, 'Palette renderer escapes both property and value');
check(H5PThemerMemoryStorage::$raw === $bytes && H5PThemerMemoryStorage::$writes === $writes, 'Overview and view never create catalog configuration');

// All five presets can be copied with exact pinning, independent identity and
// no inherited CSS strings flattened into editable configuration.
foreach ($ids as $id) {
  $bundle = admin_form('theme', 'preset_duplicate', $id, $version);
  check($bundle[1]['general']['density']['#default_value'] === 'large' && $bundle[1]['general']['id']['#default_value'] === '', 'Preset copy starts with large density and new ID: ' . $id);
  check(!isset($bundle[1]['colors']) && count($bundle[1]['inheritance']['#rows']) === 34, 'Preset copy initially shows inherited data without override inputs: ' . $id);
  $input = array('id' => $id, 'label' => 'Custom ' . $id, 'description' => 'Independent copy', 'density' => 'large');
  $settings_before = config('h5p_themer.settings')->getOriginal();
  $result = admin_post($bundle, $input);
  check(!$result['errors'], 'Create preset copy through native form: ' . $id);
  $copy = h5p_themer_theme_load($id)['data'];
  check($copy['schema_version'] === 2 && $copy['id'] === $id && $copy['base_preset'] === array('id' => $id, 'version' => $version), 'Pinned schema 2 copy may share built-in ID: ' . $id);
  check($copy['colors'] === array() && $copy['density'] === 'large' && $copy['description'] === 'Independent copy', 'Copy stores only sparse overrides and editable metadata: ' . $id);
  check(h5p_themer_theme_resolve($id)['data']['colors'] === $catalog_before[$id]['colors'], 'Copy resolves exact 34 inherited values: ' . $id);
  check(config('h5p_themer.settings')->getOriginal() === $settings_before && h5p_themer_presets($version)['data'] === $catalog_before, 'Copy changes neither default nor source: ' . $id);
  $bytes = H5PThemerMemoryStorage::$raw;
  $collision = admin_post(admin_form('theme', 'preset_duplicate', $id, $version), $input);
  check(isset($collision['errors']['id']) && H5PThemerMemoryStorage::$raw === $bytes, 'Custom namespace collision rejected: ' . $id);
}
check(!isset(admin_form('theme', 'preset_duplicate', 'unknown', $version)[1]['actions']), 'Unknown preset duplication rejected');
check(!isset(admin_form('theme', 'preset_duplicate', 'dark', 'latest')[1]['actions']), 'Unknown version duplication rejected');
$bytes = H5PThemerMemoryStorage::$raw;
$result = admin_post(admin_form('theme', 'preset_duplicate', 'dark', $version), array('id' => 'no_preflatten', 'label' => 'Bad', 'density' => 'large', 'colors' => array('--h5p-theme-background' => '#abc')));
check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Preset-copy POST cannot insert an initial override map');

// Schema 2 edit retains the exact base and permits only sparse hex overrides.
$edit = admin_form('theme', 'edit', 'dark');
check(isset($edit[1]['base_info'], $edit[1]['extra_property']) && !isset($edit[1]['base_preset']), 'Pinned base and extra property displayed read-only');
check(strpos($edit[1]['colors']['main']['--h5p-theme-contrast-cta-light']['#description'], 'rgba(59, 130, 246, 0.15)') !== FALSE, 'Inherited non-hex value displayed without prefilling override input');
$fields = array();
foreach ($edit[1]['colors'] as $key => $group) {
  if ($key[0] === '#') { continue; }
  check($group['#collapsed'] === TRUE, 'Color group initially collapsed: ' . $key);
  foreach ($group as $property => $element) {
    if ($property[0] === '#') { continue; }
    $fields[] = $property;
    check($element['#type'] === 'textfield' && $element['#parents'] === array('colors', $property) && $element['#default_value'] === '', 'Schema 2 uses existing blank hex textfields: ' . $property);
  }
}
sort($fields); $allowed = h5p_themer_color_properties(); sort($allowed);
check($fields === $allowed, 'Schema 2 exposes exactly the existing 33 override properties');
$input = array('id' => 'dark', 'label' => 'Edited dark', 'description' => 'Keep base', 'density' => 'small', 'colors' => array('--h5p-theme-background' => '#aBc', '--h5p-theme-main-cta-base' => '#Aa00fF'));
$result = admin_post($edit, $input);
check(!$result['errors'], 'Schema 2 edits save through native validation');
$saved = h5p_themer_theme_load('dark')['data'];
check($saved['schema_version'] === 2 && $saved['base_preset'] === array('id' => 'dark', 'version' => $version), 'Editing preserves schema and exact base version');
check($saved['colors'] == $input['colors'] && $saved['density'] === 'small', 'Editing stores sparse unnormalized overrides and density');
$edit = admin_form('theme', 'edit', 'dark');
check(strpos($edit[1]['colors']['surfaces']['--h5p-theme-background']['#description'], 'Saved administrator override') !== FALSE, 'Saved override visually distinguished from inheritance');
$clear = $input; $clear['colors'] = array('--h5p-theme-background' => '', '--h5p-theme-main-cta-base' => '');
$result = admin_post($edit, $clear);
check(!$result['errors'] && h5p_themer_theme_load('dark')['data']['colors'] === array(), 'Clearing overrides preserves an empty sparse map');
check(h5p_themer_theme_resolve('dark')['data']['colors'] === $catalog_before['dark']['colors'], 'Clearing restores exact inherited palette including RGBA and extra token');
foreach (array(
  array('base_preset', array('id' => 'mint', 'version' => $version)),
  array('schema_version', 1), array('custom_css', 'body{}'),
  array('colors', array('--h5p-theme-contrast-cta-light' => 'rgba(1,2,3,.5)')),
  array('colors', array('--h5p-theme-contrast-cta-light' => 'color-mix(in srgb, red, blue)')),
  array('colors', array('--h5p-theme-contrast-cta-dark' => '#abc')),
  array('colors', array('--h5p-theme-unknown' => '')),
  array('colors', array('--h5p-theme-background' => 'url(x)')),
) as $invalid) {
  $bad = $input; $bad[$invalid[0]] = $invalid[1];
  $bytes = H5PThemerMemoryStorage::$raw;
  $result = admin_post(admin_form('theme', 'edit', 'dark'), $bad);
  check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Forged field/non-hex override rejected without mutation: ' . $invalid[0]);
}
admin_post(admin_form('theme', 'edit', 'dark'), $input);
$source = h5p_themer_theme_load('dark')['data'];
$copy = $input; $copy['id'] = 'dark_copy'; $copy['label'] = 'Dark copy';
$result = admin_post(admin_form('theme', 'duplicate', 'dark'), $copy);
$copied = h5p_themer_theme_load('dark_copy')['data'];
check(!$result['errors'] && $copied['schema_version'] === 2 && $copied['base_preset'] === $source['base_preset'], 'Schema 2 custom-to-custom copy retains exact schema/base');
check($copied['colors'] === $source['colors'] && $copied['density'] === $source['density'] && $copied['description'] === $source['description'], 'Schema 2 duplication preserves sparse colors and editable metadata');
check(h5p_themer_theme_load('dark')['data'] === $source && config('h5p_themer.settings')->getOriginal() === fixture_settings(), 'Custom duplication preserves source and global default');
seed('h5p_themer.theme.legacy', array_replace(fixture_theme('legacy'), array('colors' => array('--h5p-theme-background' => '#bCd'))));
$legacy = h5p_themer_theme_load('legacy')['data'];
$legacy_input = array('id' => 'legacy_copy', 'label' => 'Legacy copy', 'description' => '', 'density' => 'large', 'colors' => $legacy['colors']);
$result = admin_post(admin_form('theme', 'duplicate', 'legacy'), $legacy_input);
check(!$result['errors'] && h5p_themer_theme_load('legacy_copy')['data']['schema_version'] === 1 && !isset(h5p_themer_theme_load('legacy_copy')['data']['base_preset']), 'Schema 1 duplication remains schema 1');
$legacy_input['id'] = 'legacy'; $legacy_input['label'] = 'Legacy renamed';
$result = admin_post(admin_form('theme', 'edit', 'legacy'), $legacy_input);
check(!$result['errors'] && h5p_themer_theme_load('legacy')['data']['colors'] === $legacy['colors'] && h5p_themer_theme_load('legacy')['data']['schema_version'] === 1, 'Schema 1 editing preserves sparse hex and schema');
$overview = h5p_themer_overview();
check(count($overview['themes']['#rows']) === 8, 'Schema 1 and schema 2 customs listed alongside five built-ins');

// Selection tokens are UI-only; explicit user saves determine storage format.
$bytes = H5PThemerMemoryStorage::$raw;
$default = admin_form('default');
$options = $default[1]['default_theme']['#options'];
check(isset($options['core'], $options['builtin:daylight'], $options['custom:daylight']), 'Unambiguous Core/built-in/custom tokens coexist');
check($default[1]['default_theme']['#default_value'] === 'core' && H5PThemerMemoryStorage::$raw === $bytes, 'Building selector does not migrate settings or select Daylight');
$result = admin_post($default, array('default_theme' => 'core'));
check(!$result['errors'] && config('h5p_themer.settings')->getOriginal() === fixture_settings(), 'Schema 1 Core save avoids upgrade');
$result = admin_post(admin_form('default'), array('default_theme' => 'custom:daylight'));
check(!$result['errors'] && config('h5p_themer.settings')->getOriginal() === fixture_settings('daylight'), 'Schema 1 selection remains custom string even for schema 2 target');
check(admin_form('default')[1]['default_theme']['#default_value'] === 'custom:daylight', 'Legacy daylight default preselects custom token');
$overview = h5p_themer_overview();
$custom_rows = array_column($overview['themes']['#rows'], NULL, 1);
check($custom_rows['daylight'][3] === 'Yes' && $overview['builtins']['#rows'][0][3] === 'No' && $overview['core']['#rows'][0][2] === 'No', 'Same-name custom default indicator never marks built-in or Core');
foreach ($ids as $index => $id) {
  $result = admin_post(admin_form('default'), array('default_theme' => 'builtin:' . $id));
  $expected_settings = fixture_typed_settings(array('kind' => 'builtin', 'id' => $id, 'version' => $version));
  check(!$result['errors'] && config('h5p_themer.settings')->getOriginal() === $expected_settings, 'Selecting built-in explicitly stores exact typed schema 2 reference: ' . $id);
  check(admin_form('default')[1]['default_theme']['#default_value'] === 'builtin:' . $id, 'Built-in selection preloads token: ' . $id);
  $overview = h5p_themer_overview();
  check($overview['builtins']['#rows'][$index][3] === 'Yes' && $overview['builtins']['#rows'][$index][4] === 'Yes' && !isset($overview['warnings']), 'Typed built-in stored/effective indicators agree without false override warning: ' . $id);
}
$result = admin_post(admin_form('default'), array('default_theme' => 'custom:dark'));
check(!$result['errors'] && config('h5p_themer.settings')->getOriginal() === fixture_typed_settings(array('kind' => 'custom', 'id' => 'dark')), 'Schema 2 settings select custom with typed reference');
$result = admin_post(admin_form('default'), array('default_theme' => 'core'));
check(!$result['errors'] && config('h5p_themer.settings')->getOriginal() === fixture_typed_settings(), 'Clearing typed settings retains schema 2 and stores null');
foreach (array('daylight', 'builtin:missing', 'custom:missing', '__missing__', '', array('kind' => 'builtin', 'id' => 'dark', 'version' => $version)) as $token) {
  $bytes = H5PThemerMemoryStorage::$raw;
  $result = admin_post(admin_form('default'), array('default_theme' => $token));
  check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Unlisted/raw reference token rejected without writes');
}
seed('h5p_themer.settings', fixture_typed_settings(array('kind' => 'custom', 'id' => 'missing')));
check(admin_form('default')[1]['default_theme']['#default_value'] === '__missing__', 'Broken typed custom reference remains explicit invalid selection');
$result = admin_post(admin_form('default'), array('default_theme' => 'core'));
check(!$result['errors'], 'Broken typed custom can be explicitly cleared');
$vanishing = admin_form('default'); config('h5p_themer.theme.legacy_copy')->delete();
$bytes = H5PThemerMemoryStorage::$raw;
$result = admin_post($vanishing, array('default_theme' => 'custom:legacy_copy'));
check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Token revalidated against current collection after target disappears');

// Protect both original and effective selected-custom references at build/POST.
foreach (array(fixture_settings('dark'), fixture_typed_settings(array('kind' => 'custom', 'id' => 'dark'))) as $settings) {
  seed('h5p_themer.settings', $settings);
  $delete = admin_form('delete', 'dark');
  $bytes = H5PThemerMemoryStorage::$raw;
  $result = admin_post($delete, array('confirm' => 1));
  check($delete[1]['actions']['submit']['#disabled'] === TRUE && (bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Stored selected custom protected under either settings schema');
}
seed('h5p_themer.settings', fixture_typed_settings());
$GLOBALS['config']['h5p_themer.settings'] = array('default_theme' => array('kind' => 'custom', 'id' => 'dark'));
check(admin_form('delete', 'dark')[1]['actions']['submit']['#disabled'] === TRUE, 'Effective typed custom override protects deletion');
$overview = h5p_themer_overview(); $custom_rows = array_column($overview['themes']['#rows'], NULL, 1);
check(isset($overview['warnings']) && $custom_rows['dark'][3] === 'No' && $custom_rows['dark'][4] === 'Yes', 'Overview distinguishes effective override from stored Core');
$bytes = H5PThemerMemoryStorage::$raw;
$result = admin_post(admin_form('default'), array('default_theme' => 'builtin:dark'));
check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Default write cannot persist or override settings.php selection');
$GLOBALS['config'] = array();
$delete = admin_form('delete', 'dark');
$GLOBALS['config']['h5p_themer.settings'] = array('default_theme' => array('kind' => 'custom', 'id' => 'dark'));
$bytes = H5PThemerMemoryStorage::$raw;
$result = admin_post($delete, array('confirm' => 1));
check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Post-build effective default override rechecked under lock');
$GLOBALS['config'] = array();
$delete = admin_form('delete', 'dark');
seed('h5p_themer.settings', fixture_typed_settings(array('kind' => 'custom', 'id' => 'dark')));
$bytes = H5PThemerMemoryStorage::$raw;
$result = admin_post($delete, array('confirm' => 1));
check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Post-build typed default selection protects deletion');
seed('h5p_themer.settings', fixture_typed_settings(array('kind' => 'builtin', 'id' => 'dark', 'version' => $version)));
$result = admin_post(admin_form('delete', 'dark'), array('confirm' => 1));
check(!$result['errors'] && config('h5p_themer.theme.dark')->isNew(), 'Built-in selection does not prevent deleting independent custom dark');
check(h5p_themer_presets($version)['data'] === $catalog_before, 'Custom deletion cannot delete or alter built-in');

// Authentication, compatibility, CSRF, stale sources, forged requests and locks.
$input = array('id' => 'security_copy', 'label' => 'Security copy', 'description' => '', 'density' => 'large');
$bundle = admin_form('theme', 'preset_duplicate', 'dark', $version);
$bytes = H5PThemerMemoryStorage::$raw;
$result = admin_post($bundle, $input, FALSE);
check(isset($result['errors']['form_token']) && H5PThemerMemoryStorage::$raw === $bytes, 'Preset duplication rejects invalid CSRF');
$bundle = admin_form('default');
$result = admin_post($bundle, array('default_theme' => 'core'), FALSE);
check(isset($result['errors']['form_token']) && H5PThemerMemoryStorage::$raw === $bytes, 'Typed default form retains native CSRF');
$bundle = admin_form('theme', 'preset_duplicate', 'dark', $version);
$admin_allowed = FALSE;
check(!isset(h5p_themer_preset_view('dark', $version)['colors']) && !isset(h5p_themer_overview()['builtins']), 'Unprivileged read routes denied');
check(!isset(admin_form('theme', 'preset_duplicate', 'dark', $version)[1]['actions']), 'Unprivileged preset form denied');
$result = admin_post($bundle, $input);
check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Revoked permission blocks previously built preset form');
$admin_allowed = TRUE;
foreach (array('disabled', 'newer') as $failure) {
  $bundle = admin_form('theme', 'preset_duplicate', 'dark', $version);
  $api_before = H5PCore::$coreApi;
  if ($failure === 'disabled') { $h5p_enabled = FALSE; }
  else { H5PCore::$coreApi['minorVersion'] = 29; }
  check(!isset(h5p_themer_preset_view('dark', $version)['duplicate']), 'Incompatible built-in remains inspectable without mutation link: ' . $failure);
  check(admin_form('theme', 'preset_duplicate', 'dark', $version)[1]['actions']['submit']['#disabled'] === TRUE, 'Incompatible copy form read-only: ' . $failure);
  $result = admin_post($bundle, $input);
  check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Post-build compatibility failure prevents copy: ' . $failure);
  $h5p_enabled = TRUE; H5PCore::$coreApi = $api_before;
}
$bundle = admin_form('theme', 'preset_duplicate', 'dark', $version); $write_locked = TRUE;
$result = admin_post($bundle, $input);
check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Busy write lock prevents preset copy');
$write_locked = FALSE;
$bundle = admin_form('theme', 'preset_duplicate', 'dark', $version);
$bundle[2]['h5p_themer']['preset']['colors']['--h5p-theme-background'] = '#000';
$result = admin_post($bundle, $input);
check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Stale catalog snapshot rejected');
$bundle = admin_form('theme', 'duplicate', 'dark_copy');
config('h5p_themer.theme.dark_copy')->set('label', 'Changed source')->save();
$bytes = H5PThemerMemoryStorage::$raw;
$result = admin_post($bundle, array_replace($input, array('colors' => array())));
check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Stale schema 2 duplicate source rejected');
$bundle = admin_form('theme', 'edit', 'dark_copy');
config('h5p_themer.theme.dark_copy')->set('base_preset', array('id' => 'mint', 'version' => $version))->save();
$bytes = H5PThemerMemoryStorage::$raw;
$result = admin_post($bundle, array_replace($copy, array('id' => 'dark_copy')));
check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Stale schema 2 edit cannot overwrite a changed pinned base');
$bundle = admin_form('default'); seed('h5p_themer.settings', fixture_typed_settings());
$bytes = H5PThemerMemoryStorage::$raw;
$result = admin_post($bundle, array('default_theme' => 'custom:dark_copy'));
check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Stale typed settings snapshot rejected');
$GLOBALS['config']['h5p_themer.theme.dark_copy'] = array('base_preset' => array('id' => 'mint', 'version' => $version));
check(admin_form('theme', 'edit', 'dark_copy')[1]['actions']['submit']['#disabled'] === TRUE, 'Overridden pinned base makes edit read-only');
$GLOBALS['config'] = array();
// A malformed effective reference is never treated as an unselected default.
$delete = admin_form('delete', 'dark_copy');
$GLOBALS['config']['h5p_themer.settings'] = array('default_theme' => array('kind' => 'unknown', 'id' => 'dark_copy'));
$bytes = H5PThemerMemoryStorage::$raw;
$result = admin_post($delete, array('confirm' => 1));
check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Malformed effective settings block deletion without fallback');
$GLOBALS['config'] = array();
foreach (array('builtin:dark', '../dark') as $forged) {
  check(!isset(admin_form('theme', 'edit', $forged)[1]['actions']), 'Forged built-in edit identity rejected');
  check(!isset(admin_form('delete', $forged)[1]['actions']), 'Forged built-in delete identity rejected');
}
check(!isset(admin_form('theme', 'edit', 'dark')[1]['actions']) && !isset(admin_form('delete', 'dark')[1]['actions']), 'Uncreated custom dark cannot address built-in by bare ID');

// Malformed schema 2 data yields diagnostics, not a crash or silent repair.
$invalid = fixture_pinned_theme('invalid_two'); $invalid['base_preset']['version'] = 'latest';
seed('h5p_themer.theme.invalid_two', $invalid);
$bytes = H5PThemerMemoryStorage::$raw;
check(isset(_h5p_themer_admin_collection()['invalid']['h5p_themer.theme.invalid_two']), 'Malformed schema 2 listed diagnostically');
check(!isset(admin_form('theme', 'edit', 'invalid_two')[1]['actions']) && !isset(admin_form('theme', 'duplicate', 'invalid_two')[1]['actions']), 'Malformed schema 2 has no edit/duplicate action');
check(!isset(admin_form('delete', 'invalid_two')[1]['actions']), 'Malformed schema 2 cannot be deleted accidentally');
check(H5PThemerMemoryStorage::$raw === $bytes, 'Inspecting invalid schema 2 never repairs it');
seed('h5p_themer.settings', fixture_typed_settings(array('kind' => 'builtin', 'id' => 'dark', 'version' => 'latest')));
$bytes = H5PThemerMemoryStorage::$raw;
check(isset(h5p_themer_overview()['warnings']) && !isset(admin_form('default')[1]['actions']), 'Malformed typed settings warn and require validated repair');
check(H5PThemerMemoryStorage::$raw === $bytes, 'Malformed typed settings never silently reset to Core');
seed('h5p_themer.settings', fixture_typed_settings());
config('h5p_themer.theme.dark_copy')->set('label', '<script>alert("x")</script>')->save();
$overview = h5p_themer_overview(); $rows = array_column($overview['themes']['#rows'], NULL, 1);
check(strpos($rows['dark_copy'][0], '<script>') === FALSE && strpos($rows['dark_copy'][0], '&lt;script&gt;') !== FALSE, 'Schema 2 custom label escaped in overview');
$select = admin_form('default')[1]['default_theme']; $select['#value'] = 'core';
$markup = form_select_options($select);
check(strpos($markup, '<script>') === FALSE && strpos($markup, '&lt;script&gt;') !== FALSE, 'Schema 2 label safely escaped by native selector renderer');
$bundle = admin_form('theme', 'preset_duplicate', 'dark', $version);
$bytes = H5PThemerMemoryStorage::$raw; H5PThemerMemoryStorage::$failWrite = TRUE;
$result = admin_post($bundle, $input);
check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes && !$write_locked, 'Preset copy storage failure preserves data and releases lock');
H5PThemerMemoryStorage::$failWrite = FALSE;
check(h5p_themer_presets($version)['data'] === $catalog_before, 'All administration attempts leave authoritative catalog unchanged');
// Revalidation in submit must also protect new paths if invoked internally.
$bundle = admin_form('theme', 'preset_duplicate', 'dark', $version);
$state = $bundle[2]; $state['values'] = array_replace($input, array('density' => 'unsafe'));
form_clear_error(); $bytes = H5PThemerMemoryStorage::$raw;
form_execute_handlers('submit', $bundle[1], $state);
check((bool) form_get_errors() && H5PThemerMemoryStorage::$raw === $bytes && !$write_locked, 'Preset submit cannot bypass schema validation or leak lock');
$bundle = admin_form('theme', 'preset_duplicate', 'dark', $version);
seed('h5p_themer.theme.security_copy', fixture_theme('security_copy'));
$bytes = H5PThemerMemoryStorage::$raw;
$result = admin_post($bundle, $input);
check(isset($result['errors']['id']) && H5PThemerMemoryStorage::$raw === $bytes, 'Preset target ID collision after build is rechecked before write');
check(function_exists('h5p_themer_h5p_scripts_alter') && function_exists('h5p_themer_h5p_styles_alter'), 'Global colors and parent-only learner attachment hooks exist');
