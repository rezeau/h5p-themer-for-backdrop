<?php
/** CLI-only JSON contract export. Loads definitions, never a site bootstrap. GPL-3.0-or-later. */
require __DIR__ . '/bootstrap.php';
require_once $h5p_root . '/h5p.module';
$mapping = h5p_autoload_info();
require_once $h5p_root . '/' . $mapping['H5PCore'];
require __DIR__ . '/learner-fixture.php';
learner_switch_fixture();
$bundle = h5p_themer_learner_bundle();
if ($bundle['errors']) { throw new RuntimeException('Cannot build test contract.'); }
$palettes = array();
foreach (h5p_themer_theme_catalog()['data'] as $token => $entry) {
  $palettes[$token] = $token === 'site_default' ? h5p_themer_runtime_theme()['data']['colors'] : h5p_themer_selection_resolve($entry['reference'])['data']['colors'];
}
echo json_encode(array('settings' => h5p_themer_learner_ui_settings(), 'bundle' => $bundle['data'], 'palettes' => $palettes));
