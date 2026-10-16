<?php
/**
 * @file
 * Global color runtime; no density, content changes, or managed file records.
 * SPDX-License-Identifier: GPL-2.0-or-later.
 */

/** Returns a compatible, validated effective selection without generating files. */
function h5p_themer_runtime_theme() {
  $compatibility = h5p_themer_compatibility();
  return $compatibility['compatible'] ? h5p_themer_default_selection_load()
    : array('data' => NULL, 'errors' => array($compatibility['message']));
}

/**
 * Serializes resolved data, never arbitrary CSS, selectors, or catalog metadata.
 *
 * Empty CSS means no color overrides. Catalog-only properties are omitted only
 * when their exact provenance is established. Custom input stays hex-only.
 */
function h5p_themer_runtime_serialize(array $theme) {
  $invalid = array('data' => NULL, 'errors' => array(t('Invalid runtime theme colors.')));
  if (!isset($theme['kind'], $theme['colors']) || !is_array($theme['colors']) ||
      !in_array($theme['kind'], array('core', 'builtin', 'custom'), TRUE)) {
    return $invalid;
  }
  if ($theme['kind'] === 'core') {
    return $theme['colors'] === array() ? array('data' => '', 'errors' => array()) : $invalid;
  }
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
  return array('data' => $declarations === '' ? '' : ".h5p-content {\n" . $declarations . "}\n", 'errors' => array());
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
function h5p_themer_runtime_asset($css) {
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
    $filename = 'colors-v1-' . hash('sha256', $css) . '.css';
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

/** Captures only runtime identity/colors; density/labels do not affect output. */
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
