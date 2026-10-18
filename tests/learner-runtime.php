<?php
/** Enabled cascade and immutable delivery; private fixtures only. GPL-3.0-or-later. */
require __DIR__ . '/learner-fixture.php';
learner_switch_fixture();
$bytes = H5PThemerMemoryStorage::$raw; $writes = H5PThemerMemoryStorage::$writes; $n = $runtime_cache_invalidations;
$bundle = h5p_themer_learner_bundle(); $data = $bundle['data'];
$global_selector = 'html:not([data-h5p-themer-learner]) .h5p-content, html[data-h5p-themer-learner] .h5p-content:not([data-h5p-themer-eligible])';
check(!$bundle['errors'] && count($data['options']) === 9, 'Bundle covers Core, five builtins, both custom schemas and Site default');
check(preg_match('/\At[a-f0-9]{64}\z/', $data['id']) === 1, 'Opaque bundle readiness ID');
check(strpos($data['css'], $global_selector . ' {') !== FALSE, 'Global palette is conditional, never an unconditional content rule');
check(strpos($data['css'], 'html[data-h5p-themer-learner="' . $data['keys']['core'] . '"]') === FALSE, 'Explicit Core has no override declarations: native root fallback');
check(substr_count($data['css'], ': inherit;') === count(h5p_themer_color_properties()), 'Every supported color resets to real inherited Core when an explicit theme suppresses stale global CSS');
check($data['keys']['core'] !== $data['keys']['builtin:daylight'], 'Core never aliases Daylight');
check(strpos($data['css'], 'html[data-h5p-themer-learner] .h5p-content:not([data-h5p-themer-eligible])') !== FALSE, 'Ineligible DIV siblings retain global palette during explicit learner selection');
check(strpos($data['css'], 'html[data-h5p-themer-learner] .h5p-content[data-h5p-themer-eligible] {') !== FALSE, 'Neutralization requires controller eligibility');
check(strpos($data['css'], 'html[data-h5p-themer-learner] .h5p-content {') === FALSE, 'No unscoped learner reset can alter ineligible content');
check(strpos($data['css'], '"] .h5p-content {') === FALSE, 'No unscoped learner palette can alter ineligible content');
foreach (h5p_themer_theme_catalog()['data'] as $token => $entry) {
  if ($token === 'site_default') { continue; }
  $resolved = h5p_themer_selection_resolve($entry['reference']);
  $canonical = h5p_themer_runtime_serialize($resolved['data'])['data'];
  $selector = 'html[data-h5p-themer-learner="' . $data['keys'][$token] . '"] .h5p-content[data-h5p-themer-eligible]';
  $expected = str_replace('.h5p-content', $selector, $canonical);
  check(preg_match('/\At[a-f0-9]{64}\z/', $data['keys'][$token]) === 1 && ($canonical === '' || strpos($data['css'], $expected) !== FALSE), 'Exact canonical palette with fixed safe selector: ' . $token);
}
$legacy_selector = 'html[data-h5p-themer-learner="' . $data['keys']['custom:legacy'] . '"] .h5p-content[data-h5p-themer-eligible]';
check(strpos($data['css'], $legacy_selector . " {\n  --h5p-theme-main-cta-base: #ABC;\n}\n") !== FALSE, 'Schema1 emits only authored sparse override, no global colors');
check(strpos($data['css'], '--h5p-theme-contrast-cta-dark') === FALSE && strpos($data['css'], 'url(') === FALSE, 'Unsupported property/URLs excluded from all palettes');
check(H5PThemerMemoryStorage::$raw === $bytes && H5PThemerMemoryStorage::$writes === $writes && $runtime_cache_invalidations === $n, 'Pure bundle makes no storage/cache writes or migrations');
$settings = h5p_themer_learner_ui_settings();
check(!isset($settings['css']) && !isset($settings['reference']) && strpos(json_encode($settings), '#3366CC') === FALSE, 'Transport contains opaque keys, no CSS or private theme metadata');
config('h5p_themer.theme.legacy')->set('label', '<script>unsafe label</script>')->save();
$renamed = h5p_themer_learner_bundle()['data'];
check($renamed['id'] === $data['id'] && $renamed['css'] === $data['css'], 'Labels never enter CSS or safe selectors');
config('h5p_themer.theme.legacy')->set('colors', array('--h5p-theme-main-cta-base' => '#123456'))->save();
$changed = h5p_themer_learner_bundle()['data'];
check($changed['id'] !== $data['id'] && $changed['keys']['custom:legacy'] !== $data['keys']['custom:legacy'] && $changed['keys']['core'] === $data['keys']['core'], 'Palette changes rotate content identity, unaffected keys remain stable');
$bad = fixture_theme('bad'); $bad['colors'] = array('--h5p-theme-background' => 'url(https://invalid)'); seed('h5p_themer.theme.bad', $bad);
check(!isset(h5p_themer_learner_bundle()['data']['keys']['custom:bad']), 'Malformed custom cannot create a CSS option');
config('h5p_themer.theme.legacy')->delete();
check(!isset(h5p_themer_learner_bundle()['data']['keys']['custom:legacy']), 'Deleted themes absent from next server snapshot');
H5PThemerMemoryStorage::$failList = TRUE;
check(h5p_themer_learner_ui_settings() === NULL && (bool) h5p_themer_learner_bundle()['errors'], 'Catalog failure disables selector metadata');
H5PThemerMemoryStorage::$failList = FALSE;
foreach (array(NULL, array('kind' => 'builtin', 'id' => 'dark', 'version' => 'moodle-478fed30d2a4'), array('kind' => 'custom', 'id' => 'checkpoint_test')) as $global) {
  config('h5p_themer.settings')->set('default_theme', $global)->save();
  $global_css = h5p_themer_runtime_css()['data']; $next_bundle = h5p_themer_learner_bundle()['data'];
  $expected_global = str_replace('.h5p-content', $global_selector, $global_css);
  check($expected_global === '' || strpos($next_bundle['css'], $expected_global) !== FALSE, 'Site default uses exact effective global for Core/builtin/custom');
  check($next_bundle['keys']['core'] === $data['keys']['core'], 'Explicit Core identity does not depend on administrator palette');
}
config('h5p_themer.settings')->set('default_theme', array('kind' => 'custom', 'id' => 'missing'))->save();
check((bool) h5p_themer_learner_bundle()['errors'] && h5p_themer_learner_ui_settings() === NULL, 'Unresolvable global configuration cannot create a misleading enabled selector');
$root = sys_get_temp_dir() . '/h5p-themer-switch-' . bin2hex(random_bytes(12));
if (!mkdir($root, 0700)) { throw new RuntimeException('Cannot create private switch fixture.'); }
$runtime_public_root = realpath($root); $runtime_public_relative = 'files'; $runtime_wrapper_missing = FALSE; $runtime_public_exception = FALSE;
try {
  learner_switch_fixture(); $data = h5p_themer_learner_bundle()['data'];
  $asset = h5p_themer_learner_asset();
  $target = $root . '/h5p_themer/' . basename($asset['data']->path);
  check(!$asset['errors'] && basename($target) === 'learner-v1-' . hash('sha256', $data['css']) . '.css' && file_get_contents($target) === $data['css'], 'Enabled bundle published atomically with exact hash in private storage');
  $before_files = scandir(dirname($target)); $mtime = filemtime($target);
  check(h5p_themer_learner_asset() == $asset && scandir(dirname($target)) === $before_files && filemtime($target) === $mtime, 'Repeated playback reuses immutable asset');
  foreach (array('div', 'iframe') as $mode) {
    $styles = array(); h5p_themer_h5p_styles_alter($styles, array(), $mode); h5p_themer_h5p_styles_alter($styles, array(), $mode);
    check(count($styles) === 1 && $styles[0]->path === $asset['data']->path, 'Same bundle passed once to actual H5P style hook: ' . $mode);
  }
  $styles = array(); h5p_themer_h5p_styles_alter($styles, array(), 'external');
  check(count($styles) === 1 && strpos($styles[0]->path, 'colors-v1-') !== FALSE, 'Standalone external embed retains original global path');
  $runtime_wrapper_missing = TRUE;
  check(h5p_themer_learner_asset()['data'] === NULL && h5p_themer_learner_asset()['errors'], 'Unavailable publisher fails closed');
  $runtime_wrapper_missing = FALSE;
  file_put_contents($target, 'unexpected bytes');
  check((bool) h5p_themer_learner_asset()['errors'] && file_get_contents($target) === 'unexpected bytes', 'Unexpected immutable file is never overwritten');
  check((bool) h5p_themer_runtime_asset($data['css'], '../unsafe')['errors'], 'Publisher namespace is a strict enum');
  config('h5p_themer.settings')->set('learner_selection_enabled', FALSE)->save();
  $styles = array(); h5p_themer_h5p_styles_alter($styles, array(), 'div');
  $legacy_css = h5p_themer_runtime_css()['data'];
  check(count($styles) === 1 && file_get_contents($root . '/h5p_themer/' . basename($styles[0]->path)) === $legacy_css && strpos($legacy_css, '.h5p-content {') === 0, 'Disabled feature retains canonical pre-selector global CSS');
  config('h5p_themer.settings')->set('default_theme', NULL)->save();
  $calls = $runtime_wrapper_calls; $styles = array(); h5p_themer_h5p_styles_alter($styles, array(), 'div');
  check(!$styles && $runtime_wrapper_calls === $calls, 'Disabled Core still does not access public storage');
} finally { runtime_fixture_remove($root, $root); admin_reset(); }
