<?php
/**
 * @file
 * Color and reserved density tokens; no content changes or managed file records.
 * SPDX-License-Identifier: GPL-2.0-or-later.
 */

/** Returns a compatible, validated effective selection without generating files. */
function h5p_themer_runtime_theme() {
  $compatibility = h5p_themer_compatibility();
  return $compatibility['compatible'] ? h5p_themer_default_selection_load()
    : array('data' => NULL, 'errors' => array($compatibility['message']));
}

/** Fixed module-owned spacing, independent of colors and native H5P aliases. */
function h5p_themer_density_tokens($density) {
  $values = array(
    'small' => array('1.92rem', '1.28rem', '0.96rem', '0.64rem', '0.416rem', '0.32rem'),
    'medium' => array('2.4rem', '1.6rem', '1.2rem', '0.8rem', '0.52rem', '0.4rem'),
    'large' => array('3rem', '2rem', '1.5rem', '1rem', '0.65rem', '0.5rem'),
  );
  if (!is_string($density) || !isset($values[$density])) { return NULL; }
  $tokens = array();
  foreach (array('xl', 'l', 'm', 's', 'xs', 'xxs') as $i => $suffix) {
    $tokens['--h5p-themer-density-space-' . $suffix] = $values[$density][$i];
  }
  return $tokens;
}

/** Trusted density declarations; Core/built-ins never acquire a profile. */
function _h5p_themer_density_declarations(array $theme) {
  if ($theme['kind'] !== 'custom') { return ''; }
  $tokens = h5p_themer_density_tokens(isset($theme['density']) ? $theme['density'] : NULL);
  if ($tokens === NULL) { return NULL; }
  $css = '';
  foreach ($tokens as $property => $value) { $css .= '  ' . $property . ': ' . $value . ";\n"; }
  return $css;
}

/**
 * Compiles resolved data into trusted rules, never administrator-supplied CSS.
 *
 * Empty rules mean no overrides. Catalog-only properties are omitted only
 * when their exact provenance is established. Custom input stays hex-only.
 */
function h5p_themer_runtime_rules(array $theme, $playback = TRUE) {
  $invalid = array('data' => NULL, 'errors' => array(t('Invalid runtime theme colors.')));
  if (!isset($theme['kind'], $theme['colors']) || !is_array($theme['colors']) ||
      !in_array($theme['kind'], array('core', 'builtin', 'custom'), TRUE)) {
    return $invalid;
  }
  if ($theme['kind'] === 'core') {
    return $theme['colors'] === array() ? array('data' => array(), 'errors' => array()) : $invalid;
  }
  $density = _h5p_themer_density_declarations($theme);
  if ($density === NULL) { return $invalid; }
  $preset = NULL;
  if ($theme['kind'] === 'builtin') {
    $loaded = h5p_themer_preset_load(isset($theme['id']) ? $theme['id'] : NULL, isset($theme['version']) ? $theme['version'] : NULL);
    if ($loaded['errors']) { return $invalid; }
    $preset = $loaded['data'];
    if (count($theme['colors']) !== count($preset['colors'])) { return $invalid; }
    foreach ($preset['colors'] as $property => $value) {
      if (!array_key_exists($property, $theme['colors']) || $theme['colors'][$property] !== $value) { return $invalid; }
    }
  }
  elseif (isset($theme['base_preset'])) {
    if (_h5p_themer_preset_reference_errors($theme['base_preset'])) { return $invalid; }
    $preset = h5p_themer_preset_load($theme['base_preset']['id'], $theme['base_preset']['version'])['data'];
  }
  $properties = h5p_themer_color_properties();
  foreach ($theme['colors'] as $property => $value) {
    if (!in_array($property, $properties, TRUE)) {
      if ($property === '--h5p-theme-contrast-cta-dark' && $preset !== NULL && $value === $preset['colors'][$property]) {
        continue;
      }
      return $invalid;
    }
    if (!is_string($value)) { return $invalid; }
    if (preg_match('/\A#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})\z/', $value) !== 1 &&
        ($preset === NULL || $value !== $preset['colors'][$property])) {
      return $invalid;
    }
  }
  $declarations = '';
  foreach ($properties as $property) {
    if (array_key_exists($property, $theme['colors'])) {
      $declarations .= '  ' . $property . ': ' . $theme['colors'][$property] . ";\n";
    }
  }
  $declarations .= $density;
  $rules = array();
  if ($playback && $theme['kind'] === 'custom') {
    // A unitless, fixed profile lets the consumer read its own native baseline.
    // Resetting this property also neutralizes a stale global consumer rule.
    $factors = array('small' => '0.8', 'medium' => '1', 'large' => '1.25');
    $declarations .= '  --h5p-themer-density-evaluation-row-factor: ' . $factors[$theme['density']] . ";\n";
  }
  if ($declarations !== '') { $rules[] = array('paths' => array(array()), 'declarations' => $declarations); }
  if ($playback && $theme['kind'] === 'custom') {
    $rules[] = array('density_gate' => TRUE, 'paths' => array(array('.h5p-container.h5p-question.h5p-theme.h5p-true-false', '.h5p-question-evaluation-container.evaluation-mode')),
      'declarations' => "  row-gap: calc(var(--h5p-theme-spacing-s) * var(--h5p-themer-density-evaluation-row-factor, 1));\n");
  }
  return array('data' => $rules, 'errors' => array());
}

/**
 * Renders module-owned rules against explicit trusted roots, never parsed CSS.
 *
 * Each path is a chain of direct-child compound class selectors. Empty paths
 * target the root. Every branch is rooted separately; sibling combinators,
 * selector lists and arbitrary ancestor selectors cannot escape that root.
 * Roots and declarations are internal compiled data, never configuration input.
 * Descendant consumers are fixed module-owned paths, not configurable selectors.
 */
function _h5p_themer_runtime_render_rules(array $rules, array $roots) {
  if (!$roots) { return NULL; }
  foreach ($roots as $root) {
    if (!is_string($root) || $root === '' || preg_match('/[,{};\r\n]/', $root)) { return NULL; }
  }
  $css = '';
  foreach ($rules as $rule) {
    if (!isset($rule['paths'], $rule['declarations']) || !is_array($rule['paths']) || !$rule['paths'] ||
        !is_string($rule['declarations']) || preg_match('/[{}]/', $rule['declarations'])) { return NULL; }
    $selectors = array();
    foreach ($roots as $root) {
      foreach ($rule['paths'] as $path) {
        if (!is_array($path)) { return NULL; }
        if (isset($rule['density_gate']) && $rule['density_gate'] !== TRUE) { return NULL; }
        $selector = $root . (!empty($rule['density_gate']) ? '[data-h5p-themer-density-ready]' : '');
        foreach ($path as $component) {
          if (!is_string($component) || !preg_match('/\A(?:\.[a-zA-Z_][a-zA-Z0-9_-]*)+\z/', $component)) { return NULL; }
          $selector .= ' > ' . $component;
        }
        $selectors[] = $selector;
      }
    }
    if ($rule['declarations'] !== '') { $css .= implode(', ', $selectors) . " {\n" . $rule['declarations'] . "}\n"; }
  }
  return $css;
}

/** Canonical global CSS uses the same structured rules as learner scopes. */
function h5p_themer_runtime_serialize(array $theme, $playback = TRUE) {
  $rules = h5p_themer_runtime_rules($theme, $playback);
  if ($rules['errors']) { return $rules; }
  $css = _h5p_themer_runtime_render_rules($rules['data'], array('.h5p-content'));
  return $css === NULL ? array('data' => NULL, 'errors' => array(t('Invalid runtime rules.'))) : array('data' => $css, 'errors' => array());
}

/** Produces CSS without file, cache, or configuration writes. */
function h5p_themer_runtime_css() {
  $theme = h5p_themer_runtime_theme();
  return $theme['errors'] ? $theme : h5p_themer_runtime_serialize($theme['data']);
}

/** Compares filesystem paths, including Windows drive-letter case differences. */
function _h5p_themer_runtime_same_path($a, $b) {
  $a = rtrim(str_replace('\\', '/', $a), '/');
  $b = rtrim(str_replace('\\', '/', $b), '/');
  return DIRECTORY_SEPARATOR === '\\' ? strcasecmp($a, $b) === 0 : $a === $b;
}

/** 1 = exact immutable file; 0 = absent; -1 = unexpected contents/type/link. */
function _h5p_themer_runtime_existing($path, $css) {
  clearstatcache(TRUE, $path);
  if (is_link($path)) { return -1; }
  if (!file_exists($path)) { return 0; }
  return is_file($path) && _h5p_themer_runtime_same_path(realpath($path), $path) && @file_get_contents($path) === $css ? 1 : -1;
}

/**
 * Materializes canonical serializer output in local public storage.
 *
 * The public wrapper owns the physical/browser mapping. Relative path segments
 * are restricted to browser-safe ASCII; remote/absolute public paths fail closed.
 * An advisory filesystem lock coordinates our writers without database locks.
 * Temporary bytes are verified before rename; existing bad files are not repaired.
 */
function h5p_themer_runtime_asset($css, $format = 'colors-v1') {
  if (!in_array($format, array('colors-v1', 'learner-v1', 'global-v1'), TRUE)) {
    return array('data' => NULL, 'errors' => array(t('Unsupported theme asset format.')));
  }
  if ($css === '') { return array('data' => NULL, 'errors' => array()); }
  $failure = array('data' => NULL, 'errors' => array(t('Runtime stylesheet storage is unavailable or inconsistent.')));
  if (!is_string($css)) { return $failure; }
  $lock = NULL;
  $temporary = NULL;
  try {
    $wrapper = file_stream_wrapper_get_instance_by_uri('public://');
    if (!$wrapper) { return $failure; }
    $relative = $wrapper->getDirectoryPath();
    if (!is_string($relative) || preg_match('/\A[A-Za-z0-9_.\/-]+\z/', $relative) !== 1) { return $failure; }
    foreach (explode('/', $relative) as $segment) {
      if ($segment === '' || $segment === '.' || $segment === '..') { return $failure; }
    }
    $public = $wrapper->realpath();
    if (!is_string($public) || !is_dir($public) || is_link($public)) { return $failure; }
    $public = realpath($public);
    $directory = $public . DIRECTORY_SEPARATOR . 'h5p_themer';
    $filename = $format . '-' . hash('sha256', $css) . '.css';
    $target = $directory . DIRECTORY_SEPARATOR . $filename;
    $asset = array('data' => (object) array('path' => $relative . '/h5p_themer/' . $filename, 'version' => ''), 'errors' => array());
    if (is_link($directory) || (file_exists($directory) && !is_dir($directory))) { return $failure; }
    if (!is_dir($directory) && !@mkdir($directory, 0755) && !is_dir($directory)) { return $failure; }
    if (!_h5p_themer_runtime_same_path(realpath($directory), $directory)) { return $failure; }
    $existing = _h5p_themer_runtime_existing($target, $css);
    if ($existing !== 0) { return $existing === 1 ? $asset : $failure; }
    if (!is_writable($directory)) { return $failure; }
    $lock_path = $directory . DIRECTORY_SEPARATOR . '.write.lock';
    if (is_link($lock_path) || (file_exists($lock_path) && !is_file($lock_path))) { return $failure; }
    $lock = @fopen($lock_path, 'c+b');
    if (!$lock || !@flock($lock, LOCK_EX | LOCK_NB)) {
      return _h5p_themer_runtime_existing($target, $css) === 1 ? $asset : $failure;
    }
    if (is_link($lock_path) || !_h5p_themer_runtime_same_path(realpath($lock_path), $lock_path)) { return $failure; }
    // Another request may have published while we waited for the lock.
    $existing = _h5p_themer_runtime_existing($target, $css);
    if ($existing !== 0) { return $existing === 1 ? $asset : $failure; }
    $temporary = @tempnam($directory, '.h5pt-');
    if (!$temporary || !_h5p_themer_runtime_same_path(dirname($temporary), $directory)) { return $failure; }
    if (@file_put_contents($temporary, $css) !== strlen($css) || @file_get_contents($temporary) !== $css) { return $failure; }
    if (!@chmod($temporary, 0644)) { return $failure; }
    // Never knowingly replace an unexpected file, even while holding our lock.
    $existing = _h5p_themer_runtime_existing($target, $css);
    if ($existing !== 0) { return $existing === 1 ? $asset : $failure; }
    if (!@rename($temporary, $target)) { return $failure; }
    $temporary = NULL;
    return _h5p_themer_runtime_existing($target, $css) === 1 ? $asset : $failure;
  }
  catch (Throwable $e) {
    // Filesystem warnings converted to exceptions also leave playback unmodified.
    return $failure;
  }
  finally {
    if (is_string($temporary) && is_file($temporary)) {
      try { @unlink($temporary); } catch (Throwable $e) { /* Best-effort private temporary cleanup. */ }
    }
    if (is_resource($lock)) {
      try { @flock($lock, LOCK_UN); fclose($lock); } catch (Throwable $e) { /* No database lock to release. */ }
    }
  }
}

/** Captures runtime identity and generated color/density output, excluding labels. */
function h5p_themer_runtime_cache_snapshot() {
  $selection = h5p_themer_runtime_theme();
  if ($selection['errors']) { return 'invalid'; }
  $theme = $selection['data'];
  $css = h5p_themer_runtime_serialize($theme);
  if ($css['errors']) { return 'invalid'; }
  $identity = array($theme['kind'], $theme['id'], isset($theme['version']) ? $theme['version'] : NULL,
    isset($theme['base_preset']) ? $theme['base_preset'] : NULL);
  return hash('sha256', serialize(array($identity, $css['data'])));
}

/** Called only after a successful relevant administration save, never playback. */
function h5p_themer_runtime_invalidate($before) {
  if ($before !== h5p_themer_runtime_cache_snapshot()) {
    // Native default invalidation flushes page and layout_path bins, not assets.
    cache_clear_all();
  }
}
