<?php
/** Optional presentation metadata; memory-only configuration. GPL-3.0-or-later. */
learner_reset(TRUE);
$before = h5p_themer_learner_bundle()['data'];
$bytes = H5PThemerMemoryStorage::$raw; $writes = H5PThemerMemoryStorage::$writes;
$icons = h5p_themer_learner_icons();
$expected = array('site_default' => array('#006FBF', '#C7D7EF'), 'core' => array('#006FBF', '#C7D7EF'),
  'builtin:daylight' => array('#006FBF', '#C7D7EF'), 'builtin:lavender' => array('#834DD5', '#DDC7FF'),
  'builtin:mint' => array('#187571', '#EEDAAA'), 'builtin:sunset' => array('#D63A0A', '#FFCFBA'),
  'builtin:dark' => array('#3B82F6', '#121417'));
foreach ($expected as $token => $pair) { check($icons[$token] === $pair, 'Automatic pair from installed/pinned source: ' . $token); }
check(array_keys($icons) === array_column($before['options'], 'value'), 'Presentation has exactly catalog tokens and ordering');
check(H5PThemerMemoryStorage::$raw === $bytes && H5PThemerMemoryStorage::$writes === $writes, 'Icon projection makes no configuration writes');
check(h5p_themer_learner_bundle()['data'] === $before, 'Icon generation does not rotate CSS keys or alter serialized palettes');
foreach (array('red', '#abcd', '#12345678', '#123456;', 'url(x)', '<script>', "#123456\n", NULL, array()) as $bad) {
  check(_h5p_themer_learner_icon_hex($bad) === NULL, 'Reject arbitrary/non-opaque icon value');
}
check(_h5p_themer_learner_icon_hex('#abc') === '#AABBCC', 'Sparse legacy shorthand normalized for display only');
$css = ':root { --h5p-theme-main-cta-base: #123; --h5p-theme-alternative-darker: #456789; }';
check(_h5p_themer_learner_icon_core_parse($css) === array('#112233', '#456789'), 'Only literal fixed root declarations read');
foreach (array('', str_replace('#123', 'var(--evil)', $css), str_replace('#123', '#123 !important', $css),
  str_replace(':root', '.other', $css), str_replace('--h5p-theme-main-cta-base', '--other', $css),
  $css . $css, str_replace('#123;', '#123; --h5p-theme-main-cta-base: #456;', $css), $css . "\0", str_repeat(' ', 262145)) as $bad) {
  check(_h5p_themer_learner_icon_core_parse($bad) === NULL, 'Ambiguous/missing/unsafe Core metadata rejected');
}
check(_h5p_themer_learner_icon_core_parse('/* comment */' . $css) === array('#112233', '#456789'), 'Core comments are not interpreted as declarations');
$theme = fixture_pinned_theme('future', 'mint'); $theme['colors'] = array('--h5p-theme-main-cta-base' => '#3366CC');
seed('h5p_themer.theme.future', $theme);
check(h5p_themer_learner_icons()['custom:future'] === array('#3366CC', '#EEDAAA'), 'Future custom uses override plus pinned inheritance');
$legacy = fixture_theme('sparse'); $legacy['colors'] = array(); seed('h5p_themer.theme.sparse', $legacy);
check(h5p_themer_learner_icons()['custom:sparse'] === $expected['core'], 'Empty schema 1 has installed Core fallback');
$legacy['colors'] = array('--h5p-theme-main-cta-base' => '#abc'); seed('h5p_themer.theme.sparse', $legacy);
check(h5p_themer_learner_icons()['custom:sparse'] === array('#AABBCC', '#C7D7EF'), 'Sparse schema 1 retains own color and native missing value');
check(h5p_themer_theme_load('sparse')['data']['colors'] === $legacy['colors'], 'Schema 1 bytes are not normalized in storage');
foreach (array('colorblind_friendly' => array('#164A83', '#D0E2F5'), 'high_contrast_light' => array('#000000', '#FFF2A8')) as $id => $pair) {
  $custom = fixture_pinned_theme($id, 'daylight'); $custom['colors'] = array('--h5p-theme-main-cta-base' => $pair[0], '--h5p-theme-alternative-darker' => $pair[1]); seed('h5p_themer.theme.' . $id, $custom);
  check(h5p_themer_learner_icons()['custom:' . $id] === $pair, 'Arbitrary saved custom pair: ' . $id);
}
$bundle = h5p_themer_learner_bundle()['data'];
config('h5p_themer.theme.future')->set('label', '<b>Renamed</b>')->save();
check(h5p_themer_learner_icons()['custom:future'] === array('#3366CC', '#EEDAAA'), 'Renaming preserves presentation identity');
check(h5p_themer_learner_bundle()['data']['keys'] === $bundle['keys'], 'Renaming has no CSS key changes');
$GLOBALS['config']['h5p_themer.theme.future'] = array('colors' => array('--h5p-theme-main-cta-base' => '#def'));
check(h5p_themer_learner_icons()['custom:future'] === array('#DDEEFF', '#EEDAAA'), 'Effective custom overrides respected');
$GLOBALS['config']['h5p_themer.settings'] = array('default_theme' => array('kind' => 'custom', 'id' => 'future'));
check(h5p_themer_learner_icons()['site_default'] === array('#DDEEFF', '#EEDAAA'), 'Site default follows effective global custom');
$GLOBALS['config'] = array();
config('h5p_themer.theme.future')->delete();
check(!isset(h5p_themer_learner_icons()['custom:future']), 'Deleted theme has no stale presentation metadata');
$bad = fixture_theme('broken_icon'); $bad['colors'] = array('--h5p-theme-main-cta-base' => 'url(evil)'); seed('h5p_themer.theme.broken_icon', $bad);
check(!isset(h5p_themer_learner_icons()['custom:broken_icon']), 'Invalid custom excluded by shared resolver');
check(!isset(h5p_themer_learner_ui_settings()['icons']['custom:broken_icon']), 'Invalid custom cannot enter UI metadata');
admin_reset();
