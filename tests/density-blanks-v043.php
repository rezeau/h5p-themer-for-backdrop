<?php
// SPDX-License-Identifier: GPL-3.0-or-later.
/** Official Blanks qualification, with existing libraries retained. */
$blanks_start = $passed;
function blanks_density_fixture() {
  native_density_fixture(); native_density_multichoice_fixture(); native_density_blanks_fixture();
}
learner_switch_fixture(); blanks_density_fixture();
$GLOBALS['native_density_test_enabled'] = TRUE;
$node = (object) array('type' => 'h5p_content', 'nid' => 10, 'vid' => 10);
$expected = array('id' => '10', 'library' => 'H5P.Blanks 1.14', 'patch' => 37);
$bytes = H5PThemerMemoryStorage::$raw; $writes = H5PThemerMemoryStorage::$writes;
$css = h5p_themer_runtime_css(); $bundle = h5p_themer_learner_bundle();
check(h5p_themer_native_density_player($node) === $expected, 'Blanks 1.14.37 qualifies using native iframe fallback');
foreach (array(6 => array('H5P.TrueFalse 1.8', 24), 9 => array('H5P.MultiChoice 1.16', 27)) as $id => $version) {
  check(h5p_themer_native_density_player((object) array('type' => 'h5p_content', 'nid' => $id, 'vid' => $id)) ===
    array('id' => (string) $id, 'library' => $version[0], 'patch' => $version[1]), 'Existing exact library remains qualified: ' . $version[0]);
}
check($bytes === H5PThemerMemoryStorage::$raw && $writes === H5PThemerMemoryStorage::$writes &&
  $css === h5p_themer_runtime_css() && $bundle === h5p_themer_learner_bundle(), 'Blanks qualification preserves config and colors');
foreach (array('patchVersion' => array(36, 38, '37.0', TRUE, NULL), 'minorVersion' => array(13, 15),
  'majorVersion' => array(0, 2), 'machineName' => array('H5P.FillInTheBlanks', 'H5P.BlanksPapiJo'), 'libraryId' => array(0, 18)) as $field => $values) {
  foreach ($values as $value) {
    blanks_density_fixture(); H5PBackdrop::$libraries['H5P.Blanks'][$field] = $value;
    check(h5p_themer_native_density_player($node) === NULL, 'Blanks installed identity mismatch rejected: ' . $field);
  }
}
foreach (array('libraryName' => array('H5P.MarkTheWords', 'H5P.QuestionSet', 'H5P.InteractiveBook'),
  'libraryId' => array(17, 18), 'libraryMajorVersion' => array(2), 'libraryMinorVersion' => array(8, 16),
  'id' => array(9), 'libraryEmbedTypes' => array('div')) as $field => $values) {
  foreach ($values as $value) {
    blanks_density_fixture(); H5PBackdrop::$contents[10][$field] = $value;
    check(h5p_themer_native_density_player($node) === NULL, 'Blanks content identity/embed mismatch rejected: ' . $field);
  }
}
blanks_density_fixture(); $before = h5p_themer_native_density_cache_snapshot();
H5PBackdrop::$libraries['H5P.Blanks']['patchVersion'] = 38;
check($before !== h5p_themer_native_density_cache_snapshot(), 'Blanks patch changes capability cache identity');
blanks_density_fixture(); $learner_test_path = 'node/10'; $learner_js = array();
backdrop_static('h5p_themer_native_density_attach', array(), TRUE);
foreach (array(10, 9, 6, 10) as $id) { h5p_themer_node_view((object) array('type' => 'h5p_content', 'nid' => $id, 'vid' => $id)); }
check(count($learner_js) === 5 && $learner_js[2]['data']['h5pThemerNativeDensity']['players']['cid-10'] === $expected,
  'Three distinct libraries share one controller/payload and deduplicate attachment');
$api = H5PCore::$coreApi; H5PCore::$coreApi['minorVersion'] = 29;
check(h5p_themer_native_density_player($node) === NULL, 'Blanks requires exactly Core API 1.28'); H5PCore::$coreApi = $api;
config('h5p.settings')->set('h5p_library_development', TRUE)->save();
check(h5p_themer_native_density_player($node) === NULL, 'Blanks development mode is excluded');
config('h5p.settings')->set('h5p_library_development', FALSE)->save();
$node->in_preview = TRUE;
check(h5p_themer_native_density_player($node) === NULL, 'Blanks preview is excluded');
unset($GLOBALS['native_density_test_enabled']); backdrop_static('h5p_themer_native_density_attach', array(), TRUE);
printf("v0.4.3 Blanks Density: %d passed\n", $passed - $blanks_start);
