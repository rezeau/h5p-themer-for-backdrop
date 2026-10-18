<?php
/**
 * @file
 * Phase 2A: memory Config/cache plus private temporary public-storage mapping.
 * SPDX-License-Identifier: GPL-2.0-or-later.
 */

// Never bootstrap a site or map writes to BACKDROP_ROOT/files.
class H5PThemerTestPublicWrapper {
  public function getDirectoryPath() { return $GLOBALS['runtime_public_relative']; }
  public function realpath() {
    if ($GLOBALS['runtime_public_exception']) { throw new RuntimeException('Simulated unavailable storage.'); }
    return $GLOBALS['runtime_public_root'];
  }
}
function file_stream_wrapper_get_instance_by_uri($uri) {
  if ($uri !== 'public://') { throw new LogicException('Unexpected storage URI.'); }
  $GLOBALS['runtime_wrapper_calls']++;
  return $GLOBALS['runtime_wrapper_missing'] ? FALSE : new H5PThemerTestPublicWrapper();
}
function runtime_fixture_css(array $colors) {
  $result = ".h5p-content {\n";
  foreach (h5p_themer_color_properties() as $property) {
    if (isset($colors[$property])) { $result .= '  ' . $property . ': ' . $colors[$property] . ";\n"; }
  }
  return $result . "}\n";
}
function runtime_fixture_select($id) {
  seed('h5p_themer.settings', fixture_typed_settings(array('kind' => 'builtin', 'id' => $id, 'version' => 'moodle-478fed30d2a4')));
}
function runtime_fixture_remove($directory, $root) {
  $real = realpath($directory);
  if ($real === FALSE || strpos(str_replace('\\', '/', $real) . '/', str_replace('\\', '/', $root) . '/') !== 0 || is_link($directory)) {
    throw new LogicException('Refusing cleanup outside this test fixture.');
  }
  foreach (new DirectoryIterator($directory) as $item) {
    if ($item->isDot()) { continue; }
    if ($item->isLink()) { unlink($item->getPathname()); }
    elseif ($item->isDir()) { runtime_fixture_remove($item->getPathname(), $root); }
    else { chmod($item->getPathname(), 0666); unlink($item->getPathname()); }
  }
  rmdir($directory);
}

$runtime_test_root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'h5p-themer-runtime-' . bin2hex(random_bytes(12));
if (file_exists($runtime_test_root) || !mkdir($runtime_test_root, 0700)) { throw new RuntimeException('Cannot create isolated test storage.'); }
$runtime_test_root = realpath($runtime_test_root);
$runtime_public_root = $runtime_test_root . DIRECTORY_SEPARATOR . 'public';
mkdir($runtime_public_root, 0700);
$runtime_public_relative = 'files';
$runtime_wrapper_calls = 0;
$runtime_wrapper_missing = FALSE;
$runtime_public_exception = FALSE;
$version = 'moodle-478fed30d2a4';
$api_before = H5PCore::$coreApi;
$catalog_before = h5p_themer_presets($version)['data'];

try {
  admin_reset();
  $writes = H5PThemerMemoryStorage::$writes; $bytes = H5PThemerMemoryStorage::$raw; $invalidations = $runtime_cache_invalidations;
  foreach (array('div', 'iframe', 'external') as $mode) {
    $styles = array(); h5p_themer_h5p_styles_alter($styles, array(), $mode);
    check($styles === array(), 'Core adds no stylesheet: ' . $mode);
  }
  check($runtime_wrapper_calls === 0 && !file_exists($runtime_public_root . '/h5p_themer'), 'Core does not even inspect public storage or create a directory');
  check(H5PThemerMemoryStorage::$raw === $bytes && H5PThemerMemoryStorage::$writes === $writes && $runtime_cache_invalidations === $invalidations, 'Core playback makes no configuration or cache writes');
  $empty = fixture_theme('empty'); $empty['colors'] = array(); seed('h5p_themer.theme.empty', $empty); seed('h5p_themer.settings', fixture_settings('empty'));
  $styles = array(); h5p_themer_h5p_styles_alter($styles, array(), 'iframe');
  check($styles === array() && $runtime_wrapper_calls === 0, 'Schema 1 empty sparse colors generate no file (density is ignored)');

  $mint_css = NULL;
  foreach (array('daylight', 'lavender', 'mint', 'sunset', 'dark') as $id) {
    runtime_fixture_select($id);
    $fixture_colors = $fixture['themes'][$id]['values'];
    $css = h5p_themer_runtime_css();
    check(!$css['errors'] && $css['data'] === runtime_fixture_css($fixture_colors), 'Exact independent catalog CSS: ' . $id);
    check(substr_count($css['data'], '  --h5p-theme-') === 33 && strpos($css['data'], '--h5p-theme-contrast-cta-dark:') === FALSE, 'Exactly supported declarations, no extra catalog token: ' . $id);
    check(strpos($css['data'], $fixture_colors['--h5p-theme-contrast-cta-light']) !== FALSE, 'Exact inherited expression preserved: ' . $id);
    $theme = h5p_themer_default_selection_load()['data']; $theme['label'] = '</style><script>alert(1)</script>'; $theme['density'] = 'small';
    check(h5p_themer_runtime_serialize($theme)['data'] === $css['data'], 'Labels and density do not enter CSS: ' . $id);
    $forged = $theme; $forged['colors']['--h5p-theme-background'] = '#123';
    check((bool) h5p_themer_runtime_serialize($forged)['errors'], 'Forged builtin colors rejected: ' . $id);
    if ($id === 'mint') { $mint_css = $css['data']; }
  }
  $legacy = fixture_theme('legacy_runtime');
  $legacy['colors'] = array('--h5p-theme-background' => '#aBc', '--h5p-theme-main-cta-base' => '#Aa00fF');
  seed('h5p_themer.theme.legacy_runtime', $legacy); seed('h5p_themer.settings', fixture_settings('legacy_runtime'));
  $legacy_css = ".h5p-content {\n  --h5p-theme-main-cta-base: #Aa00fF;\n  --h5p-theme-background: #aBc;\n}\n";
  check(h5p_themer_runtime_css()['data'] === $legacy_css, 'Schema 1 emits only exact sparse hex strings in allowlist order');
  $resolved = h5p_themer_default_selection_load()['data']; $resolved['colors'] = array_reverse($resolved['colors'], TRUE);
  check(h5p_themer_runtime_serialize($resolved)['data'] === $legacy_css, 'Input map ordering does not affect serialization');
  foreach (array('', '#12', '#abc; color:red', "#abc\n", ' #abc', 'rgba(1,2,3,.5)', 'color-mix(in srgb,red,blue)', 'var(--x)', 'url(https://example.invalid)', '</style><script>', NULL, FALSE, array()) as $value) {
    $bad = $resolved; $bad['colors']['--h5p-theme-background'] = $value;
    check((bool) h5p_themer_runtime_serialize($bad)['errors'], 'Unsupported/injected custom value rejected');
  }
  foreach (array('--h5p-theme-unknown', '--h5p-theme-background; color', '--h5p-theme-contrast-cta-dark', '--h5p-theme-font-name', 'body') as $property) {
    $bad = $resolved; $bad['colors'][$property] = '#123';
    check((bool) h5p_themer_runtime_serialize($bad)['errors'], 'Unapproved selector/property rejected: ' . $property);
  }
  $bad = $resolved; $bad['kind'] = 'untrusted';
  check((bool) h5p_themer_runtime_serialize($bad)['errors'], 'Unknown resolved kind rejected');
  $bad = $resolved; $bad['base_preset'] = array('id' => 'mint', 'version' => 'latest');
  check((bool) h5p_themer_runtime_serialize($bad)['errors'], 'Unknown catalog provenance rejected');

  $pinned = fixture_pinned_theme('pinned_runtime', 'mint'); $pinned['colors'] = array('--h5p-theme-main-cta-base' => '#AbC');
  seed('h5p_themer.theme.pinned_runtime', $pinned); seed('h5p_themer.settings', fixture_settings('pinned_runtime'));
  $expected_colors = array_replace($fixture['themes']['mint']['values'], $pinned['colors']);
  check(h5p_themer_runtime_css()['data'] === runtime_fixture_css($expected_colors), 'Schema 2 emits exact inherited values plus sparse override');
  check(h5p_themer_theme_load('pinned_runtime')['data'] === $pinned, 'Runtime never flattens or migrates stored schema 2 colors');
  $bad = h5p_themer_default_selection_load()['data']; $bad['colors']['--h5p-theme-contrast-cta-light'] = 'rgba(1,2,3,.5)';
  check((bool) h5p_themer_runtime_serialize($bad)['errors'], 'Noncatalog inherited expression rejected even with a valid base');
  $bad = h5p_themer_default_selection_load()['data']; $bad['colors']['--h5p-theme-contrast-cta-dark'] = '#123';
  check((bool) h5p_themer_runtime_serialize($bad)['errors'], 'Modified unsupported catalog property rejected');
  $invalid_definition = $pinned; $invalid_definition['colors'] = array('--h5p-theme-contrast-cta-light' => $fixture['themes']['mint']['values']['--h5p-theme-contrast-cta-light']);
  seed('h5p_themer.theme.pinned_runtime', $invalid_definition);
  $styles = array(); h5p_themer_h5p_styles_alter($styles, array(), 'iframe');
  check($styles === array(), 'A stored custom expression is rejected before runtime even when it equals its base expression');
  seed('h5p_themer.theme.pinned_runtime', $pinned);

  // Effective environment overrides use the existing validated resolver.
  $GLOBALS['config']['h5p_themer.settings'] = fixture_typed_settings(array('kind' => 'builtin', 'id' => 'dark', 'version' => $version));
  check(h5p_themer_runtime_css()['data'] === runtime_fixture_css($fixture['themes']['dark']['values']), 'Runtime respects effective settings overrides');
  $GLOBALS['config'] = array();
  runtime_fixture_select('mint');
  foreach (array('editor', 'preview', '', NULL, array()) as $mode) {
    $calls = $runtime_wrapper_calls; $styles = array(); h5p_themer_h5p_styles_alter($styles, array(), $mode);
    check($styles === array() && $runtime_wrapper_calls === $calls, 'Unsupported/editor mode does not touch storage');
  }
  $writes = H5PThemerMemoryStorage::$writes; $bytes = H5PThemerMemoryStorage::$raw; $invalidations = $runtime_cache_invalidations;
  foreach (array('div', 'iframe', 'external') as $mode) {
    $original = (object) array('path' => 'existing.css', 'version' => '?ver=1');
    $styles = array($original); h5p_themer_h5p_styles_alter($styles, array('H5P.GuessIt' => array('majorVersion' => 1, 'minorVersion' => 9)), $mode);
    check(count($styles) === 2 && $styles[0] === $original && $styles[1]->version === '', 'Appends one local stylesheet without altering dependencies: ' . $mode);
    check($styles[1]->path === 'files/h5p_themer/colors-v1-' . hash('sha256', $mint_css) . '.css', 'Serializer version/hash in the real path: ' . $mode);
    $target = $runtime_public_root . '/h5p_themer/' . basename($styles[1]->path);
    check(file_get_contents($target) === $mint_css && !is_link($target), 'Real private fixture file has exact CSS: ' . $mode);
    touch($target, 946684800); clearstatcache(TRUE, $target);
    $mtime = filemtime($target);
    h5p_themer_h5p_styles_alter($styles, array(), $mode); clearstatcache(TRUE, $target);
    check(count($styles) === 2 && filemtime($target) === $mtime, 'Repeated hook reuses immutable file and deduplicates attachment: ' . $mode);
  }
  check(H5PThemerMemoryStorage::$raw === $bytes && H5PThemerMemoryStorage::$writes === $writes && $runtime_cache_invalidations === $invalidations, 'Themed playback writes no configuration or page caches');
  foreach (array('daylight', 'lavender', 'mint', 'sunset', 'dark') as $id) {
    runtime_fixture_select($id); $palette_css = runtime_fixture_css($fixture['themes'][$id]['values']);
    $asset = h5p_themer_runtime_asset(h5p_themer_runtime_css()['data']);
    check(!$asset['errors'] && file_get_contents($runtime_public_root . '/h5p_themer/' . basename($asset['data']->path)) === $palette_css, 'Every builtin materializes its exact palette: ' . $id);
  }
  runtime_fixture_select('mint');

  $core = (new ReflectionClass('H5PCore'))->newInstanceWithoutConstructor();
  $url = new ReflectionProperty('H5PCore', 'url'); $url->setAccessible(TRUE); $url->setValue($core, '/subdir/');
  check($core->getAssetsUrls(array($styles[1])) === array('/subdir/' . $styles[1]->path), 'Native Core prefixes the asset correctly in a subdirectory installation');

  // Existing invalid bytes are never overwritten or attached.
  file_put_contents($target, 'unexpected');
  $styles = array(); h5p_themer_h5p_styles_alter($styles, array(), 'iframe');
  check($styles === array() && file_get_contents($target) === 'unexpected', 'Corrupt existing CSS fails closed and is preserved');
  file_put_contents($target, $mint_css);
  $css2 = str_replace('#187571', '#aBc', $mint_css);
  check($css2 !== $mint_css, 'Changed-color fixture differs from Mint');
  $target2 = $runtime_public_root . '/h5p_themer/colors-v1-' . hash('sha256', $css2) . '.css';
  $lock_path = $runtime_public_root . '/h5p_themer/.write.lock';
  $contender = fopen($lock_path, 'r+b'); flock($contender, LOCK_EX);
  $busy = h5p_themer_runtime_asset($css2);
  check((bool) $busy['errors'] && !file_exists($target2), 'Contending writer does not publish partial data or block playback');
  file_put_contents($target2, $css2); // Simulate the successful lock owner publishing.
  flock($contender, LOCK_UN); fclose($contender);
  $winner = h5p_themer_runtime_asset($css2);
  check(!$winner['errors'] && basename($winner['data']->path) === basename($target2), 'Next request reuses the concurrent winner without rewriting it');
  check(file_get_contents($target2) === $css2 && $winner['data']->path !== 'files/h5p_themer/' . basename($target), 'Different bytes produce a distinct immutable identity');

  // Two independent CLI processes use the production publication algorithm.
  $concurrent_root = $runtime_test_root . '/concurrent'; mkdir($concurrent_root);
  $processes = array();
  for ($worker = 0; $worker < 2; $worker++) {
    $pipes = array();
    $process = proc_open(array(PHP_BINARY, '-n', __DIR__ . '/runtime-worker.php', $concurrent_root, base64_encode($mint_css)), array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
    if (!is_resource($process)) { throw new RuntimeException('Cannot launch private runtime worker.'); }
    fclose($pipes[0]); $processes[] = array($process, $pipes);
  }
  file_put_contents($concurrent_root . '/start', 'start');
  $results = array();
  foreach ($processes as $process) {
    $output = stream_get_contents($process[1][1]); $error = stream_get_contents($process[1][2]);
    fclose($process[1][1]); fclose($process[1][2]);
    $exit = proc_close($process[0]); $data = json_decode($output, TRUE);
    check($exit === 0 && $error === '' && is_array($data) && empty($data['errors']) && isset($data['data']['path']), 'Independent filesystem worker succeeds');
    $results[] = $data;
  }
  check($results[0]['data']['path'] === $results[1]['data']['path'] && file_get_contents($concurrent_root . '/h5p_themer/' . basename($results[0]['data']['path'])) === $mint_css, 'Concurrent requests converge on one exact immutable stylesheet');
  $temporary_files = array_filter(scandir($concurrent_root . '/h5p_themer'), function ($name) { return strpos($name, '.h5pt-') === 0 || substr($name, -4) === '.tmp'; });
  check($temporary_files === array(), 'Concurrent publication leaves no partial temporary stylesheets');


  foreach (array('../files', '/files', 'public://files', 'http://example.invalid', 'C:/files', 'files//cache', 'files/./cache', 'files/../cache', 'files/"bad', 'files/?bad') as $relative) {
    $runtime_public_relative = $relative;
    check((bool) h5p_themer_runtime_asset($mint_css)['errors'], 'Unsafe/nonlocal public path rejected: ' . $relative);
  }
  $runtime_public_relative = 'files';
  $runtime_wrapper_missing = TRUE;
  check((bool) h5p_themer_runtime_asset($mint_css)['errors'], 'Missing public wrapper fails closed');
  $runtime_wrapper_missing = FALSE; $runtime_public_exception = TRUE;
  check((bool) h5p_themer_runtime_asset($mint_css)['errors'], 'Storage exceptions do not escape into H5P playback');
  $runtime_public_exception = FALSE;
  // A read-only lock models an unwritable storage failure on this Windows host.
  chmod($lock_path, 0444); clearstatcache(TRUE, $lock_path);
  if (!is_writable($lock_path)) {
    $failure_css = str_replace('#187571', '#123456', $mint_css);
    check((bool) h5p_themer_runtime_asset($failure_css)['errors'], 'Read-only storage lock fails gracefully with no attachment');
  }
  else {
    // Some privileged POSIX environments ignore mode bits; still test blocked IO.
    unlink($lock_path); mkdir($lock_path);
    check((bool) h5p_themer_runtime_asset(str_replace('#187571', '#123456', $mint_css))['errors'], 'Unwritable lock target fails gracefully with no attachment');
    rmdir($lock_path);
  }
  if (is_file($lock_path)) { chmod($lock_path, 0666); }
  $blocked_root = $runtime_test_root . '/blocked'; mkdir($blocked_root); file_put_contents($blocked_root . '/h5p_themer', 'user data');
  $saved_root = $runtime_public_root; $runtime_public_root = $blocked_root;
  $styles = array(); h5p_themer_h5p_styles_alter($styles, array(), 'external');
  check($styles === array() && file_get_contents($blocked_root . '/h5p_themer') === 'user data', 'Occupied cache directory is not replaced');
  $runtime_public_root = $saved_root;

  foreach (array(fixture_settings('missing'), array('schema_version' => 2, 'default_theme' => array('kind' => 'builtin', 'id' => 'mint', 'version' => 'unknown')), array('schema_version' => 2, 'default_theme' => 'mint')) as $bad_settings) {
    seed('h5p_themer.settings', $bad_settings); $bytes = H5PThemerMemoryStorage::$raw; $calls = $runtime_wrapper_calls;
    $styles = array(); h5p_themer_h5p_styles_alter($styles, array(), 'div');
    check($styles === array() && $runtime_wrapper_calls === $calls && H5PThemerMemoryStorage::$raw === $bytes, 'Missing/invalid selection never attaches, touches storage or repairs configuration');
  }
  reset_storage(); $styles = array(); $calls = $runtime_wrapper_calls;
  h5p_themer_h5p_styles_alter($styles, array(), 'iframe');
  check($styles === array() && $runtime_wrapper_calls === $calls, 'Missing settings fail closed');
  runtime_fixture_select('mint');
  foreach (array('disabled', 'old', 'new', 'malformed') as $failure) {
    if ($failure === 'disabled') { $h5p_enabled = FALSE; }
    else { H5PCore::$coreApi = $failure === 'malformed' ? array('majorVersion' => '1', 'minorVersion' => 28) : array('majorVersion' => 1, 'minorVersion' => $failure === 'old' ? 27 : 29); }
    $styles = array(); $calls = $runtime_wrapper_calls; h5p_themer_h5p_styles_alter($styles, array(), 'iframe');
    check($styles === array() && $runtime_wrapper_calls === $calls, 'Compatibility gate precedes storage: ' . $failure);
    $h5p_enabled = TRUE; H5PCore::$coreApi = $api_before;
  }

  // Only successful relevant UI saves invalidate page/layout caches.
  admin_reset(); $runtime_cache_invalidations = 0;
  $result = admin_post(admin_form('default'), array('default_theme' => 'builtin:mint'));
  check(!$result['errors'] && $runtime_cache_invalidations === 1, 'Selecting Mint invalidates stale Core page references');
  $result = admin_post(admin_form('default'), array('default_theme' => 'builtin:mint'));
  check(!$result['errors'] && $runtime_cache_invalidations === 1, 'Resaving the same global reference adds no cache invalidation');
  $result = admin_post(admin_form('default'), array('default_theme' => 'core'));
  check(!$result['errors'] && $runtime_cache_invalidations === 2 && h5p_themer_runtime_css()['data'] === '', 'Returning to Core invalidates cached themed references and emits no CSS');
  $selected = fixture_theme('cache_selected'); seed('h5p_themer.theme.cache_selected', $selected);
  $other = fixture_theme('cache_other'); seed('h5p_themer.theme.cache_other', $other);
  $result = admin_post(admin_form('default'), array('default_theme' => 'custom:cache_selected'));
  check(!$result['errors'] && $runtime_cache_invalidations === 3, 'Selecting a custom theme invalidates cached pages');
  $input = array_replace($selected, array('label' => 'Changed metadata', 'density' => 'small')); unset($input['schema_version']);
  $result = admin_post(admin_form('theme', 'edit', 'cache_selected'), $input);
  check(!$result['errors'] && $runtime_cache_invalidations === 3, 'Metadata/density-only edit does not invalidate colors-only output');
  $input['colors']['--h5p-theme-main-cta-base'] = '#aBc';
  $result = admin_post(admin_form('theme', 'edit', 'cache_selected'), $input);
  check(!$result['errors'] && $runtime_cache_invalidations === 4, 'Selected custom color edit invalidates cached pages');
  $other_input = $input; $other_input['id'] = 'cache_other';
  $result = admin_post(admin_form('theme', 'edit', 'cache_other'), $other_input);
  check(!$result['errors'] && $runtime_cache_invalidations === 4, 'Unselected custom edit does not invalidate playback caches');
  $copy = $input; $copy['id'] = 'cache_copy';
  $result = admin_post(admin_form('theme', 'duplicate', 'cache_selected'), $copy);
  check(!$result['errors'] && $runtime_cache_invalidations === 4, 'Custom duplication does not invalidate the selected source');
  $bad_input = $input; $bad_input['colors']['--h5p-theme-main-cta-base'] = 'red';
  $bytes = H5PThemerMemoryStorage::$raw;
  $result = admin_post(admin_form('theme', 'edit', 'cache_selected'), $bad_input);
  check((bool) $result['errors'] && $runtime_cache_invalidations === 4 && H5PThemerMemoryStorage::$raw === $bytes, 'Rejected edits do not invalidate caches or write configuration');
  H5PThemerMemoryStorage::$failWrite = TRUE; $input['colors']['--h5p-theme-main-cta-base'] = '#123456';
  $result = admin_post(admin_form('theme', 'edit', 'cache_selected'), $input);
  check((bool) $result['errors'] && $runtime_cache_invalidations === 4 && H5PThemerMemoryStorage::$raw === $bytes, 'Storage-failed saves do not invalidate caches');
  H5PThemerMemoryStorage::$failWrite = FALSE;
  $write_locked = TRUE;
  $result = admin_post(admin_form('default'), array('default_theme' => 'builtin:dark'));
  check((bool) $result['errors'] && $runtime_cache_invalidations === 4 && H5PThemerMemoryStorage::$raw === $bytes, 'Lock-rejected default save does not invalidate caches');
  $write_locked = FALSE;
  check(h5p_themer_presets($version)['data'] === $catalog_before, 'Every runtime attempt preserves all exact catalog data');
  check(count(h5p_themer_menu()) === 9 && function_exists('h5p_themer_h5p_scripts_alter'), 'No new routes; parent-only learner UI hook exists');
}
finally {
  H5PCore::$coreApi = $api_before; $h5p_enabled = TRUE; $GLOBALS['config'] = array();
  runtime_fixture_remove($runtime_test_root, $runtime_test_root);
}
