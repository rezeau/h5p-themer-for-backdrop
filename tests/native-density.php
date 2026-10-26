<?php
/** Native Density metadata and real admin-cache contracts, memory only. */
$native_density_start = $passed;
learner_switch_fixture();
unset($GLOBALS['native_density_test_enabled']);
native_density_fixture();
check(!h5p_themer_native_density_enabled() && h5p_themer_native_density_settings() === NULL, 'Native Density is disabled by default');
foreach (array(FALSE, 0, 1, 'true', '1', array(), array(6), NULL) as $value) {
  $GLOBALS['native_density_test_enabled'] = $value;
  check(!h5p_themer_native_density_enabled() && h5p_themer_native_density_settings() === NULL, 'Only boolean true enables Density');
}
$GLOBALS['native_density_test_enabled'] = TRUE;
check(h5p_themer_native_density_enabled(), 'Explicit true enables Density');
$settings_before = H5PThemerMemoryStorage::$raw; $writes_before = H5PThemerMemoryStorage::$writes;
$bundle_before = h5p_themer_learner_bundle()['data']; $css_before = h5p_themer_runtime_css();
$data = h5p_themer_native_density_settings();
check($data['version'] === 2 && $data['learner'] === TRUE && $data['bundle'] === $bundle_before['id'], 'Metadata shares existing color bundle readiness without changing it');
$profiles = (array) $data['profiles'];
foreach ($bundle_before['keys'] as $token => $key) {
  check(array_key_exists($key, $profiles) && $profiles[$key] === (strpos($token, 'custom:') === 0 ? 'large' : NULL), 'Custom saved profile versus Core/builtin restoration: ' . $token);
}
check($data['global'] === NULL, 'Builtin Global Default has no Density override');
check($settings_before === H5PThemerMemoryStorage::$raw && $writes_before === H5PThemerMemoryStorage::$writes && $css_before === h5p_themer_runtime_css() && $bundle_before === h5p_themer_learner_bundle()['data'], 'Metadata generation preserves configuration, exact color CSS, keys and bundles');

seed('h5p_themer.learner_visibility', array('schema_version' => 1, 'themes' => array('custom:checkpoint_test' => FALSE)));
config('h5p_themer.theme.checkpoint_test')->set('density', 'medium')->save();
config('h5p_themer.settings')->set('default_theme', array('kind' => 'custom', 'id' => 'checkpoint_test'))->save();
$data = h5p_themer_native_density_settings();
check($data['global'] === 'medium', 'Site default follows hidden custom Global Default');
check(!isset(h5p_themer_learner_bundle()['data']['keys']['custom:checkpoint_test']), 'Hidden Global Default is not published as an explicit choice');
$before = h5p_themer_native_density_cache_snapshot();
config('h5p_themer.theme.checkpoint_test')->set('density', 'small')->save();
check(h5p_themer_native_density_settings()['global'] === 'small' && h5p_themer_native_density_cache_snapshot() !== $before, 'Hidden Global Default Density-only change updates metadata dependency');

config('h5p_themer.settings')->set('default_theme', NULL)->save();
$before = h5p_themer_native_density_cache_snapshot();
config('h5p_themer.theme.checkpoint_test')->set('density', 'large')->save();
check(h5p_themer_native_density_settings() === NULL && h5p_themer_native_density_cache_snapshot() === $before, 'Hidden unselected Density does not invalidate delivered metadata');
config('h5p_themer.settings')->set('learner_selection_enabled', FALSE)->save();
config('h5p_themer.settings')->set('default_theme', array('kind' => 'custom', 'id' => 'checkpoint_test'))->save();
H5PThemerMemoryStorage::$failList = TRUE;
$data = h5p_themer_native_density_settings();
check($data['learner'] === FALSE && $data['global'] === 'large' && $data['bundle'] === NULL && (array) $data['profiles'] === array(), 'Global-only Density needs no catalog, learner CSS, or selector');
H5PThemerMemoryStorage::$failList = FALSE;
foreach (array('small', 'medium', 'large') as $density) {
  config('h5p_themer.theme.checkpoint_test')->set('density', $density)->save();
  check(h5p_themer_native_density_settings()['global'] === $density, 'Existing saved Density enum resolves: ' . $density);
}
config('h5p_themer.theme.checkpoint_test')->set('density', 'unsafe')->save();
check(h5p_themer_native_density_settings() === NULL, 'Invalid configuration cannot publish a native class');

// Exercise actual administration invalidation without changing the color snapshot.
learner_switch_fixture(); $GLOBALS['native_density_test_enabled'] = TRUE;
$cache_before = $runtime_cache_invalidations;
$before = _h5p_themer_admin_cache_snapshot(); $color = h5p_themer_runtime_cache_snapshot(); $ui = h5p_themer_learner_ui_cache_snapshot();
config('h5p_themer.theme.legacy')->set('density', 'small')->save();
check($color === h5p_themer_runtime_cache_snapshot() && $ui === h5p_themer_learner_ui_cache_snapshot(), 'Density-only selectable edit preserves existing color/cache identities');
_h5p_themer_admin_cache_invalidate($before);
check($runtime_cache_invalidations === $cache_before + 1, 'Density-only selectable save invalidates stale metadata once');
$before = _h5p_themer_admin_cache_snapshot(); _h5p_themer_admin_cache_invalidate($before);
check($runtime_cache_invalidations === $cache_before + 1, 'No-op metadata does not invalidate');
config('h5p_themer.settings')->set('learner_selection_enabled', FALSE)->save();
config('h5p_themer.settings')->set('default_theme', array('kind' => 'custom', 'id' => 'legacy'))->save();
$before = _h5p_themer_admin_cache_snapshot(); $color = h5p_themer_runtime_cache_snapshot();
config('h5p_themer.theme.legacy')->set('density', 'medium')->save();
_h5p_themer_admin_cache_invalidate($before);
check($color === h5p_themer_runtime_cache_snapshot() && $runtime_cache_invalidations === $cache_before + 2, 'Global-only Density invalidates HTML without changing color CSS');

foreach (array('node/6', 'admin', 'node/6/edit', 'h5p/embed/6') as $route) {
  backdrop_static('h5p_themer_native_density_attach', array(), TRUE);
  $learner_test_path = $route; $learner_js = array(); $learner_headers = array();
  $node = (object) array('type' => 'h5p_content', 'nid' => 6, 'vid' => 6);
  h5p_themer_node_view($node); h5p_themer_node_view($node);
  check(count($learner_js) === ($route === 'node/6' ? 3 : 0), 'Parent attachment once, excluded admin/editor/external routes: ' . $route);
  if ($route === 'node/6') {
    check($learner_js[1]['data'] === 'modules/h5p_themer/js/h5p_themer.native-density.js', 'Fixed parent controller asset, no H5P library mutation');
    check(count($learner_headers) === 1 && $learner_js[2]['data']['h5pThemerNativeDensity']['players']['cid-6']['patch'] === 24, 'Atomic settings payload and browser HTML revalidation');
  }
}
$learner_test_path = 'node/6';
$node = (object) array('type' => 'h5p_content', 'nid' => 6, 'vid' => 6);
check(h5p_themer_native_density_player($node)['patch'] === 24, 'Exact server API patch qualifies');
foreach (array('patchVersion' => array(23, 25, NULL, TRUE), 'minorVersion' => array(7, 9), 'majorVersion' => array(0, 2), 'machineName' => array('H5P.Other'), 'libraryId' => array(NULL, 0)) as $field => $values) {
  foreach ($values as $value) {
    native_density_fixture(); H5PBackdrop::$libraries['H5P.TrueFalse'][$field] = $value;
    check(h5p_themer_native_density_player($node) === NULL, 'Unqualified installed library rejected: ' . $field);
  }
}
native_density_fixture();
foreach (array('libraryName' => 'H5P.QuestionSet', 'libraryId' => 99, 'id' => 7, 'libraryMinorVersion' => 7, 'libraryMajorVersion' => 2, 'libraryEmbedTypes' => 'div') as $field => $value) {
  $before = H5PBackdrop::$contents[6]; H5PBackdrop::$contents[6][$field] = $value;
  check(h5p_themer_native_density_player($node) === NULL, 'Content main identity/rendering rejected: ' . $field);
  H5PBackdrop::$contents[6] = $before;
}
// A qualified dependency/node claim never overrides the trusted composite main library.
H5PBackdrop::$contents[6]['libraryName'] = 'H5P.QuestionSet'; $node->main_library = array('name' => 'H5P.TrueFalse', 'patchVersion' => 24);
$learner_js = array(); h5p_themer_node_view($node);
check(!$learner_js, 'Unsupported composite with a qualified dependency/claim attaches nothing');
native_density_fixture();
$before = h5p_themer_native_density_cache_snapshot(); H5PBackdrop::$libraries['H5P.TrueFalse']['patchVersion'] = 25;
check($before !== h5p_themer_native_density_cache_snapshot(), 'Installed patch qualification changes cache dependency');
native_density_fixture(); $before = h5p_themer_native_density_cache_snapshot();
$GLOBALS['native_density_test_enabled'] = FALSE;
check($before !== h5p_themer_native_density_cache_snapshot(), 'Installation switch changes cache dependency');
$reads = H5PBackdrop::$reads; $learner_js = array(); h5p_themer_node_view($node);
check(!$learner_js && H5PBackdrop::$reads === $reads, 'Disabled feature avoids assets and trusted API reads');
$GLOBALS['native_density_test_enabled'] = TRUE; H5PBackdrop::$fail = TRUE;
check(h5p_themer_native_density_player($node) === NULL, 'Unavailable trusted API fails closed');
native_density_fixture(); backdrop_static('h5p_themer_native_density_attach', array(), TRUE); $learner_js = array();
h5p_themer_node_view($node); $node->nid = $node->vid = 7; h5p_themer_node_view($node); h5p_themer_node_view($node);
check(count($learner_js) === 4 && isset($learner_js[2]['data']['h5pThemerNativeDensity']['players']['cid-6'], $learner_js[3]['data']['h5pThemerNativeDensity']['players']['cid-7']), 'Independent trusted records merge with one shared controller/payload');
config('h5p_themer.settings')->set('learner_selection_enabled', FALSE)->set('default_theme', NULL)->save();
check(h5p_themer_native_density_settings() === NULL, 'No global or selectable override needs no controller');
config('h5p_themer.settings')->set('learner_selection_enabled', TRUE)->save();
check(h5p_themer_native_density_settings() !== NULL, 'Core initial selection retains selectable custom activation');
unset($GLOBALS['native_density_test_enabled']); $learner_test_path = 'node/7';
backdrop_static('h5p_themer_native_density_attach', array(), TRUE);
printf("Native Density metadata/cache: %d passed\n", $passed - $native_density_start);
