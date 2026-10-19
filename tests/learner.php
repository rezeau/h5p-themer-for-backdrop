<?php
/**
 * @file
 * Parent-only learner UI attachment with in-memory Config and asset collectors.
 * SPDX-License-Identifier: GPL-2.0-or-later.
 */
function learner_reset($enabled = FALSE) {
  admin_reset();
  seed('h5p_themer.settings', array('schema_version' => 2, 'default_theme' => NULL, 'learner_selection_enabled' => $enabled));
  backdrop_static_reset('h5p_themer_learner_attach');
  $GLOBALS['learner_test_path'] = 'node/7'; $GLOBALS['learner_test_admin_path'] = FALSE;
  $GLOBALS['learner_js'] = array(); $GLOBALS['learner_css'] = array();
}
$learner_storage_root = sys_get_temp_dir() . '/h5p-themer-learner-' . bin2hex(random_bytes(12));
if (!mkdir($learner_storage_root, 0700)) { throw new RuntimeException('Cannot create private learner fixture.'); }
$runtime_public_root = realpath($learner_storage_root); $runtime_public_relative = 'files';
$runtime_wrapper_missing = FALSE; $runtime_public_exception = FALSE;
try {
foreach (array(FALSE, TRUE) as $enabled) {
  learner_reset($enabled); $bytes = H5PThemerMemoryStorage::$raw; $writes = H5PThemerMemoryStorage::$writes; $invalidations = $runtime_cache_invalidations;
  $original = array((object) array('path' => 'existing-player.js', 'version' => '?v=1'));
  $scripts = $original; h5p_themer_h5p_scripts_alter($scripts, array(), 'div');
  check($scripts === $original, 'Learner hook never modifies player script list');
  check(count($learner_js) === ($enabled ? 3 : 0) && count($learner_css) === ($enabled ? 3 : 0), 'Parent-only assets attached only when enabled');
  check(H5PThemerMemoryStorage::$raw === $bytes && H5PThemerMemoryStorage::$writes === $writes && $runtime_cache_invalidations === $invalidations, 'Attachment makes no storage/cache writes');
  $styles = array(); h5p_themer_h5p_styles_alter($styles, array(), 'div');
  check(count($styles) === ($enabled ? 1 : 0), 'Enabled Core uses conditional bundle; disabled Core adds no CSS');
}
learner_reset(TRUE);
foreach (array('div', 'iframe', 'div') as $mode) { $scripts = array(); h5p_themer_h5p_scripts_alter($scripts, array(), $mode); }
check(count($learner_js) === 3 && count($learner_css) === 3, 'Multiple players/repeated modes attach settings and parent assets once');
check($learner_js[0]['options'] === 'setting' && $learner_js[1]['data'] === 'modules/h5p_themer/js/h5p_themer.learner-picker.js' && $learner_js[2]['data'] === 'modules/h5p_themer/js/h5p_themer.learner.js', 'Native Backdrop settings and fixed JS path');
check($learner_css[0]['data'] === 'modules/h5p_themer/css/h5p_themer.learner.css' && $learner_css[1]['data'] === 'modules/h5p_themer/css/h5p_themer.learner-picker.css', 'Fixed learner stylesheet path');
$data = json_decode($learner_js[0]['data']['h5pThemerLearner']['payload'], TRUE);
check($data['label'] === 'H5P theme' && $data['enabled'] === TRUE && strpos($data['message'], 'browser tab') !== FALSE && strpos($data['message'], 'does not save H5P answers') !== FALSE, 'Label and conditional tab-session scope supplied');
check(array_column($data['options'], 'value') === array('site_default', 'core', 'builtin:daylight', 'builtin:lavender', 'builtin:mint', 'builtin:sunset', 'builtin:dark'), 'Complete ordered base catalog');
check(array_column($data['options'], 'label') === array('Site default', 'H5P Core defaults', 'Daylight', 'Lavender', 'Mint', 'Sunset', 'Dark'), 'Exact base option labels');
check(array_keys($data['options'][0]) === array('value', 'label') && !isset($data['options'][0]['colors']), 'No resolved colors/admin metadata transported');
foreach (array('external', 'editor', 'preview', '', NULL, array()) as $mode) {
  learner_reset(TRUE); $scripts = array(); h5p_themer_h5p_scripts_alter($scripts, array(), $mode);
  check(!$learner_js && !$learner_css && !$scripts, 'Unsupported/editor/external mode excluded');
}
learner_reset(TRUE); $scripts = NULL; h5p_themer_h5p_scripts_alter($scripts, array(), 'div');
check(!$learner_js && !$learner_css && $scripts === NULL, 'Malformed hook collection ignored safely');
foreach (array('admin', 'admin/config/content/h5p-themer/presets/mint/version', 'node/add/h5p_content', 'node/7/edit', 'node/7/delete', 'h5p/embed/7') as $path) {
  learner_reset(TRUE); $learner_test_path = $path; $scripts = array(); h5p_themer_h5p_scripts_alter($scripts, array(), 'div');
  check(!$learner_js && !$learner_css, 'Administrative/editor page excluded: ' . $path);
}
learner_reset(TRUE); $learner_test_admin_path = TRUE; h5p_themer_learner_attach();
check(!$learner_js, 'Backdrop administrative classification also excludes custom admin paths');
learner_reset(TRUE); $h5p_enabled = FALSE; h5p_themer_learner_attach();
check(!$learner_js && !$learner_css, 'Incompatible integration receives no learner assets');
learner_reset(TRUE); H5PCore::$coreApi['minorVersion'] = 29; h5p_themer_learner_attach(); H5PCore::$coreApi['minorVersion'] = 28;
check(!$learner_js, 'Unsupported API excluded');
learner_reset(TRUE); $GLOBALS['config']['h5p_themer.settings'] = array('learner_selection_enabled' => FALSE); h5p_themer_learner_attach();
check(!$learner_js, 'Effective false override prevents attachment');
learner_reset(FALSE); $GLOBALS['config']['h5p_themer.settings'] = array('learner_selection_enabled' => TRUE); h5p_themer_learner_attach();
check(count($learner_js) === 3, 'Effective true override permits UI');
learner_reset(FALSE); H5PThemerMemoryStorage::$failList = TRUE; h5p_themer_learner_attach();
check(!$learner_js && h5p_themer_learner_ui_settings() === NULL, 'Disabled feature does not even enumerate themes');
learner_reset(TRUE); H5PThemerMemoryStorage::$failList = TRUE; h5p_themer_learner_attach();
check(!$learner_js && !$learner_css, 'Catalog failure adds no misleading or partial UI');
learner_reset(TRUE);
$legacy = fixture_theme('daylight'); $legacy['label'] = '<script>custom</script>'; $legacy['description'] = 'PRIVATE DESCRIPTION'; seed('h5p_themer.theme.daylight', $legacy);
$pinned = fixture_pinned_theme('mint_copy', 'mint'); seed('h5p_themer.theme.mint_copy', $pinned);
$bad = fixture_theme('broken'); $bad['colors'] = array('--h5p-theme-background' => 'red'); seed('h5p_themer.theme.broken', $bad);
$data = h5p_themer_learner_ui_settings(); $values = array_column($data['options'], 'value');
check(count($values) === 9 && in_array('custom:daylight', $values, TRUE) && in_array('custom:mint_copy', $values, TRUE) && !in_array('custom:broken', $values, TRUE), 'Both custom schemas included, invalid theme excluded by shared catalog');
check($data['options'][array_search('custom:daylight', $values, TRUE)]['label'] === '<script>custom</script>' && strpos(json_encode($data), 'PRIVATE DESCRIPTION') === FALSE, 'Labels transported as text with no private description');
check(in_array('core', $values, TRUE) && in_array('builtin:daylight', $values, TRUE) && in_array('custom:daylight', $values, TRUE), 'Core and colliding built-in/custom identities remain distinct');
$bytes = H5PThemerMemoryStorage::$raw; $writes = H5PThemerMemoryStorage::$writes; h5p_themer_learner_ui_settings();
check(H5PThemerMemoryStorage::$raw === $bytes && H5PThemerMemoryStorage::$writes === $writes, 'Settings projection never saves/migrates definitions');

// Enabled option changes need a page/layout invalidation, not CSS regeneration.
learner_reset(TRUE); $before = _h5p_themer_admin_cache_snapshot(); $n = $runtime_cache_invalidations;
_h5p_themer_admin_cache_invalidate($before); check($runtime_cache_invalidations === $n, 'No-op UI/cache snapshot does not invalidate');
$theme = fixture_theme(); seed('h5p_themer.theme.ocean', $theme); _h5p_themer_admin_cache_invalidate($before);
check($runtime_cache_invalidations === $n + 1, 'Enabled catalog additions invalidate cached UI');
$before = _h5p_themer_admin_cache_snapshot(); config('h5p_themer.theme.ocean')->set('label', 'Updated')->save(); _h5p_themer_admin_cache_invalidate($before);
check($runtime_cache_invalidations === $n + 2, 'Enabled label changes invalidate cached options');
$before = _h5p_themer_admin_cache_snapshot(); config('h5p_themer.theme.ocean')->set('colors', array('--h5p-theme-background' => '#abc'))->save(); _h5p_themer_admin_cache_invalidate($before);
check($runtime_cache_invalidations === $n + 3, 'Selectable color-only edit invalidates enabled palette bundle');
$before = _h5p_themer_admin_cache_snapshot(); config('h5p_themer.theme.ocean')->delete(); _h5p_themer_admin_cache_invalidate($before);
check($runtime_cache_invalidations === $n + 4, 'Enabled catalog deletion invalidates cached options');
learner_reset(TRUE); seed('h5p_themer.theme.ocean', fixture_theme()); $n = $runtime_cache_invalidations;
$result = admin_post(admin_form('delete', 'ocean'), array('confirm' => '1'));
check(!$result['errors'] && $runtime_cache_invalidations === $n + 1, 'Real deletion handler invalidates enabled UI once');
learner_reset(TRUE); $n = $runtime_cache_invalidations;
$result = admin_post(admin_form('theme'), array('id' => 'new_ui', 'label' => 'New UI', 'description' => '', 'density' => 'large', 'colors' => array()));
check(!$result['errors'] && $runtime_cache_invalidations === $n + 1, 'Real creation handler invalidates enabled catalog once');
learner_reset(TRUE); $n = $runtime_cache_invalidations;
$result = admin_post(admin_form('default'), array('default_theme' => 'builtin:mint', 'learner_selection_enabled' => '1'));
check(!$result['errors'] && $runtime_cache_invalidations === $n + 1, 'Global plus UI change has one combined invalidation');
learner_reset(FALSE); $before = _h5p_themer_admin_cache_snapshot(); $n = $runtime_cache_invalidations;
seed('h5p_themer.theme.ocean', fixture_theme()); _h5p_themer_admin_cache_invalidate($before);
check($runtime_cache_invalidations === $n, 'Disabled UI leaves existing unselected-theme cache behavior unchanged');
check(h5p_themer_runtime_css()['data'] === '', 'UI stage leaves Core serializer output empty');
admin_reset();

} finally { runtime_fixture_remove($learner_storage_root, $learner_storage_root); }
