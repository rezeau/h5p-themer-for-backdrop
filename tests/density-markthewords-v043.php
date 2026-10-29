<?php
// SPDX-License-Identifier: GPL-3.0-or-later.
/** Official MarkTheWords qualification, with existing libraries retained. */
$markthewords_start = $passed;
function markthewords_density_fixture() {
  native_density_fixture(); native_density_multichoice_fixture(); native_density_blanks_fixture(); native_density_markthewords_fixture();
}
learner_switch_fixture(); markthewords_density_fixture();
$GLOBALS['native_density_test_enabled'] = TRUE;
$node = (object) array('type' => 'h5p_content', 'nid' => 11, 'vid' => 11);
$expected = array('id' => '11', 'library' => 'H5P.MarkTheWords 1.11', 'patch' => 24);
$bytes = H5PThemerMemoryStorage::$raw; $writes = H5PThemerMemoryStorage::$writes;
$css = h5p_themer_runtime_css(); $bundle = h5p_themer_learner_bundle();
check(h5p_themer_native_density_player($node) === $expected, 'MarkTheWords 1.11.24 qualifies using native iframe fallback');
foreach (array(6 => array('H5P.TrueFalse 1.8', 24), 9 => array('H5P.MultiChoice 1.16', 27), 10 => array('H5P.Blanks 1.14', 37)) as $id => $version) {
  check(h5p_themer_native_density_player((object) array('type' => 'h5p_content', 'nid' => $id, 'vid' => $id)) ===
    array('id' => (string) $id, 'library' => $version[0], 'patch' => $version[1]), 'Existing exact library remains qualified: ' . $version[0]);
}
check($bytes === H5PThemerMemoryStorage::$raw && $writes === H5PThemerMemoryStorage::$writes &&
  $css === h5p_themer_runtime_css() && $bundle === h5p_themer_learner_bundle(), 'MarkTheWords qualification preserves config and colors');
foreach (array('patchVersion' => array(23, 25, '24.0', TRUE, NULL), 'minorVersion' => array(10, 12),
  'majorVersion' => array(0, 2), 'machineName' => array('H5P.MarkTheWordsPapiJo', 'H5P.Blanks'), 'libraryId' => array(0, 18)) as $field => $values) {
  foreach ($values as $value) {
    markthewords_density_fixture(); H5PBackdrop::$libraries['H5P.MarkTheWords'][$field] = $value;
    check(h5p_themer_native_density_player($node) === NULL, 'MarkTheWords installed identity mismatch rejected: ' . $field);
  }
}
foreach (array('libraryName' => array('H5P.MarkTheWordsPapiJo', 'H5P.Column', 'H5P.QuestionSet', 'H5P.InteractiveBook'),
  'libraryId' => array(17, 18), 'libraryMajorVersion' => array(2), 'libraryMinorVersion' => array(8, 16),
  'id' => array(9), 'libraryEmbedTypes' => array('div')) as $field => $values) {
  foreach ($values as $value) {
    markthewords_density_fixture(); H5PBackdrop::$contents[11][$field] = $value;
    check(h5p_themer_native_density_player($node) === NULL, 'MarkTheWords content identity/embed mismatch rejected: ' . $field);
  }
}
markthewords_density_fixture(); $before = h5p_themer_native_density_cache_snapshot();
H5PBackdrop::$libraries['H5P.MarkTheWords']['patchVersion'] = 25;
check($before !== h5p_themer_native_density_cache_snapshot(), 'MarkTheWords patch changes capability cache identity');
markthewords_density_fixture(); $learner_test_path = 'node/11'; $learner_js = array();
backdrop_static('h5p_themer_native_density_attach', array(), TRUE);
foreach (array(11, 10, 9, 6, 11) as $id) { h5p_themer_node_view((object) array('type' => 'h5p_content', 'nid' => $id, 'vid' => $id)); }
check(count($learner_js) === 6 && $learner_js[2]['data']['h5pThemerNativeDensity']['players']['cid-11'] === $expected,
  'Four distinct libraries share one controller/payload and deduplicate attachment');
$api = H5PCore::$coreApi; H5PCore::$coreApi['minorVersion'] = 29;
check(h5p_themer_native_density_player($node) === NULL, 'MarkTheWords requires exactly Core API 1.28'); H5PCore::$coreApi = $api;
config('h5p.settings')->set('h5p_library_development', TRUE)->save();
check(h5p_themer_native_density_player($node) === NULL, 'MarkTheWords development mode is excluded');
config('h5p.settings')->set('h5p_library_development', FALSE)->save();
$node->in_preview = TRUE;
check(h5p_themer_native_density_player($node) === NULL, 'MarkTheWords preview is excluded');
unset($GLOBALS['native_density_test_enabled']); backdrop_static('h5p_themer_native_density_attach', array(), TRUE);
printf("v0.4.3 MarkTheWords Density: %d passed\n", $passed - $markthewords_start);
