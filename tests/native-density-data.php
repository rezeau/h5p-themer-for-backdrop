<?php
/** Site-free PHP metadata consumed by the native Density JavaScript tests. */
require __DIR__ . '/bootstrap.php';
require_once $h5p_root . '/h5p.module';
$mapping = h5p_autoload_info();
require_once $h5p_root . '/' . $mapping['H5PCore'];
require __DIR__ . '/learner-fixture.php';
learner_switch_fixture();
$GLOBALS['native_density_test_enabled'] = TRUE;
native_density_fixture();
native_density_multichoice_fixture();
native_density_blanks_fixture();
native_density_markthewords_fixture();
config('h5p_themer.theme.legacy')->set('density', 'small')->save();
$data = h5p_themer_native_density_settings();
$data['players'] = array('cid-7' => h5p_themer_native_density_player((object) array('type' => 'h5p_content', 'nid' => 7, 'vid' => 7)));
$data['players']['cid-9'] = h5p_themer_native_density_player((object) array('type' => 'h5p_content', 'nid' => 9, 'vid' => 9));
$data['players']['cid-10'] = h5p_themer_native_density_player((object) array('type' => 'h5p_content', 'nid' => 10, 'vid' => 10));
$data['players']['cid-11'] = h5p_themer_native_density_player((object) array('type' => 'h5p_content', 'nid' => 11, 'vid' => 11));
echo json_encode($data);
