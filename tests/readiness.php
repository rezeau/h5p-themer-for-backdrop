<?php
/**
 * @file
 * Text-only v0.4.0 readiness contracts. SPDX-License-Identifier: GPL-2.0-or-later.
 */

/** Ignores translated description literals, retaining all executable tokens. */
function readiness_executable_signature($source) {
  $tokens = array();
  foreach (token_get_all(str_replace("\r\n", "\n", $source)) as $token) {
    if (is_array($token) && in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), TRUE)) {
      continue;
    }
    $tokens[] = $token;
  }
  $parts = array();
  foreach ($tokens as $index => $token) {
    if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING && $index >= 2
      && $tokens[$index - 1] === '(' && is_array($tokens[$index - 2])
      && $tokens[$index - 2][0] === T_STRING && $tokens[$index - 2][1] === 't') {
      $token[1] = "'TRANSLATED_TEXT'";
    }
    $parts[] = is_array($token) ? $token[1] : $token;
  }
  // PHP 7/8 tokenize qualified names differently; compare their literal text.
  return hash('sha256', implode('', $parts));
}

$readiness_start = $passed;
$readiness_root = dirname(__DIR__);
$readiness_baseline = json_decode(file_get_contents(__DIR__ . '/fixtures/v040-readiness-baseline.json'), TRUE);
foreach ($readiness_baseline['immutable_lf_sha256'] as $path => $expected) {
  $source = str_replace("\r\n", "\n", file_get_contents($readiness_root . '/' . $path));
  check(hash('sha256', $source) === $expected, 'Accepted runtime/schema/preset/preview/license/Checkpoint 1 unchanged: ' . $path);
}
foreach ($readiness_baseline['translated_text_only_signature'] as $path => $expected) {
  check(readiness_executable_signature(file_get_contents($readiness_root . '/' . $path)) === $expected,
    'Only translated descriptions changed from accepted 3B: ' . $path);
}
$info = parse_ini_file($readiness_root . '/h5p_themer.info', FALSE, INI_SCANNER_RAW);
check($info['version'] === '0.4.0' && strpos($info['description'], 'Unreleased development version.') === 0,
  'Planned metadata version is explicitly unreleased');
foreach (array('type' => 'module', 'name' => 'H5P Themer', 'package' => 'H5P', 'backdrop' => '1.x',
  'dependencies' => array('h5p'), 'configure' => 'admin/config/content/h5p-themer') as $key => $value) {
  check($info[$key] === $value, 'Metadata identity, compatibility and dependency unchanged: ' . $key);
}
foreach (array('h5p_themer.admin.inc', 'h5p_themer.module', 'h5p_themer.install') as $path) {
  $source = file_get_contents($readiness_root . '/' . $path);
  check(strpos($source, 'disabled by default') !== FALSE && strpos($source, 'H5P.TrueFalse 1.8.24 iframe players') !== FALSE,
    'Administrator scope and default activation accurately described: ' . $path);
  check(!preg_match('/density (?:is (?:only stored|never applied)|has no runtime effect|is not supported)/i', $source),
    'No obsolete blanket Density claim: ' . $path);
}
$admin_source = file_get_contents($readiness_root . '/h5p_themer.admin.inc');
check(strpos($admin_source, "'large' => t('Large (spacious)'), 'medium' => t('Medium'), 'small' => t('Small (compact)')") !== FALSE,
  'Density machine values retained with accurate text labels');
check(strpos($admin_source, 'even when that theme is hidden') !== FALSE
  && strpos($admin_source, 'Core and built-ins restore') !== FALSE, 'Administration explains original versus effective hidden Global Default');
$preview_source = file_get_contents($readiness_root . '/h5p_themer.preview.inc');
check(substr_count($preview_source, 'representative color-only preview') === 2
  && substr_count($preview_source, 'deferred to Checkpoint 3D') === 2, 'Both saved and editable preview descriptions retain color-only scope');
$guide = file_get_contents($readiness_root . '/docs/installation.md');
foreach (array('module_implements_reset();', 'backdrop_flush_all_caches();', 'backdrop_clear_js_cache();', 'cache_clear_all();') as $call) {
  check(strpos($guide, $call) !== FALSE, 'Upgrade documents verified native API: ' . $call);
}
// Read definitions only: these functions are never called by this test.
foreach (array(array('module.inc', 'module_implements_reset'), array('common.inc', 'backdrop_flush_all_caches'),
  array('common.inc', 'backdrop_clear_js_cache'), array('cache.inc', 'cache_clear_all')) as $definition) {
  list($file, $function) = $definition;
  $source = file_get_contents($backdrop_root . '/core/includes/' . $file);
  check(preg_match('/function\s+' . $function . '\s*\(/', $source) === 1, 'Documented API exists in inspected Backdrop source: ' . $function);
}
check(strpos($guide, 'does not reset hook discovery') !== FALSE
  && strpos($guide, 'Accepted Checkpoint 3C Lite supersedes the planned full Stage 2/3 rehearsal') !== FALSE
  && strpos($guide, 'Full code rollback was not rehearsed') !== FALSE
  && strpos($guide, '85 actual module disable/re-enable checks') !== FALSE,
  'Guide separates hook reset and output refresh and records accepted Lite/module evidence with rollback limits');
$readme = file_get_contents($readiness_root . '/README.md');
check(strpos($readme, 'NOT YET RELEASED') !== FALSE && strpos($readme, 'latest published stable release; v0.3.0 was withdrawn') !== FALSE,
  'Release history is truthful');
printf("Readiness text and preservation: %d assertions\n", $passed - $readiness_start);
