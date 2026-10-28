<?php
// SPDX-License-Identifier: GPL-3.0-or-later.
/** Exact multi-library qualification and delivery, site-free memory services. */
$density_v043_start = $passed;
learner_switch_fixture(); native_density_fixture(); native_density_multichoice_fixture();
$GLOBALS['native_density_test_enabled'] = TRUE;
$mc_node = (object) array('type' => 'h5p_content', 'nid' => 9, 'vid' => 9);
$expected_mc = array('id' => '9', 'library' => 'H5P.MultiChoice 1.16', 'patch' => 27);
$bytes = H5PThemerMemoryStorage::$raw; $writes = H5PThemerMemoryStorage::$writes;
$colors = h5p_themer_runtime_css(); $bundle = h5p_themer_learner_bundle();
check(h5p_themer_native_density_player($mc_node) === $expected_mc, 'Exact MultiChoice 1.16.27 main iframe qualifies');
check(h5p_themer_native_density_player((object) array('type' => 'h5p_content', 'nid' => 6, 'vid' => 6)) ===
  array('id' => '6', 'library' => 'H5P.TrueFalse 1.8', 'patch' => 24), 'TrueFalse remains exactly qualified alongside MultiChoice');
check($bytes === H5PThemerMemoryStorage::$raw && $writes === H5PThemerMemoryStorage::$writes &&
  $colors === h5p_themer_runtime_css() && $bundle === h5p_themer_learner_bundle(), 'MultiChoice qualification leaves configuration and all color output unchanged');
foreach (array('patchVersion' => array(26, 28, '27.0', TRUE, NULL), 'minorVersion' => array(15, 17),
  'majorVersion' => array(0, 2), 'machineName' => array('H5P.TrueFalse', 'H5P.MultiChoicePapiJo'),
  'libraryId' => array(0, NULL, 17)) as $field => $values) {
  foreach ($values as $value) {
    native_density_fixture(); native_density_multichoice_fixture();
    H5PBackdrop::$libraries['H5P.MultiChoice'][$field] = $value;
    check(h5p_themer_native_density_player($mc_node) === NULL, 'MultiChoice rejects installed identity/version mismatch: ' . $field);
  }
}
foreach (array('libraryName' => array('H5P.Blanks', 'H5P.QuestionSet', 'H5P.InteractiveBook', 'H5P.MultiChoicePapiJo'),
  'libraryId' => array(17, 99), 'libraryMajorVersion' => array(2), 'libraryMinorVersion' => array(8, 15, 17),
  'id' => array(7), 'libraryEmbedTypes' => array('div')) as $field => $values) {
  foreach ($values as $value) {
    native_density_fixture(); native_density_multichoice_fixture();
    H5PBackdrop::$contents[9][$field] = $value;
    $mc_node->main_library = array('name' => 'H5P.MultiChoice', 'patchVersion' => 27);
    check(h5p_themer_native_density_player($mc_node) === NULL, 'Node claims cannot bypass MultiChoice main identity/embed validation: ' . $field);
  }
}
native_density_fixture(); native_density_multichoice_fixture();
$snapshot = h5p_themer_native_density_cache_snapshot();
H5PBackdrop::$libraries['H5P.MultiChoice']['patchVersion'] = 28;
check($snapshot !== h5p_themer_native_density_cache_snapshot(), 'MultiChoice installed patch changes invalidate capability cache');
native_density_multichoice_fixture();
$learner_test_path = 'node/9'; $learner_js = array(); $learner_headers = array();
backdrop_static('h5p_themer_native_density_attach', array(), TRUE);
h5p_themer_node_view($mc_node);
h5p_themer_node_view((object) array('type' => 'h5p_content', 'nid' => 6, 'vid' => 6));
h5p_themer_node_view($mc_node);
check(count($learner_js) === 4 && count($learner_headers) === 1, 'Mixed qualified libraries share one controller and atomic capability payload');
check($learner_js[2]['data']['h5pThemerNativeDensity']['players']['cid-9'] === $expected_mc &&
  $learner_js[3]['data']['h5pThemerNativeDensity']['players']['cid-6']['patch'] === 24, 'Each supported library supplies its own exact player identity');
$api_before = H5PCore::$coreApi;
H5PCore::$coreApi = array('majorVersion' => 1, 'minorVersion' => 29);
check(h5p_themer_native_density_player($mc_node) === NULL, 'MultiChoice does not relax the Core API boundary');
H5PCore::$coreApi = $api_before;
config('h5p.settings')->set('h5p_library_development', TRUE)->save();
check(h5p_themer_native_density_player($mc_node) === NULL, 'MultiChoice development mode is excluded');
config('h5p.settings')->set('h5p_library_development', FALSE)->save();
$mc_node->in_preview = TRUE;
check(h5p_themer_native_density_player($mc_node) === NULL, 'MultiChoice node preview is excluded');
unset($GLOBALS['native_density_test_enabled']);
backdrop_static('h5p_themer_native_density_attach', array(), TRUE);
printf("v0.4.3 multi-library Density: %d passed\n", $passed - $density_v043_start);
