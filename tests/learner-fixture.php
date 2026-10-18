<?php
/** Shared memory-only scenario for PHP and the production-JS contract tests. GPL-3.0-or-later. */
function learner_switch_fixture() {
  reset_storage();
  seed('h5p_themer.settings', array('schema_version' => 2,
    'default_theme' => array('kind' => 'builtin', 'id' => 'mint', 'version' => 'moodle-478fed30d2a4'),
    'learner_selection_enabled' => TRUE));
  $legacy = fixture_theme('legacy'); $legacy['label'] = 'Legacy sparse';
  $legacy['colors'] = array('--h5p-theme-main-cta-base' => '#ABC');
  seed('h5p_themer.theme.legacy', $legacy);
  $pinned = fixture_theme('checkpoint_test'); $pinned['schema_version'] = 2;
  $pinned['label'] = 'Copy of Mint'; $pinned['base_preset'] = array('id' => 'mint', 'version' => 'moodle-478fed30d2a4');
  $pinned['colors'] = array('--h5p-theme-main-cta-base' => '#3366CC');
  seed('h5p_themer.theme.checkpoint_test', $pinned);
}
