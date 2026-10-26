<?php
/**
 * @file
 * Opt-in native Density. Trusted H5P reads only; no CSS/configuration writes.
 * SPDX-License-Identifier: GPL-3.0-or-later.
 */

/** Absent, false and non-boolean settings all leave v0.2.1 behavior intact. */
function h5p_themer_native_density_enabled() {
  return settings_get('h5p_themer_native_density_enabled', FALSE) === TRUE;
}

/** Internal qualification list, deliberately exact and not administratively editable. */
function _h5p_themer_native_density_registry() {
  return array('H5P.TrueFalse' => array(1, 8, 24));
}

function _h5p_themer_native_density_integer($value) {
  return (is_int($value) || is_string($value)) && preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/', (string) $value);
}

/** Read the installed library through the integration API, never client/node claims. */
function _h5p_themer_native_density_library($api, $name) {
  $registry = _h5p_themer_native_density_registry();
  if (!isset($registry[$name]) || $api->isInDevMode()) { return NULL; }
  list($major, $minor, $patch) = $registry[$name];
  $library = $api->loadLibrary($name, $major, $minor);
  if (!is_array($library) || !isset($library['machineName']) || $library['machineName'] !== $name) { return NULL; }
  foreach (array('majorVersion' => $major, 'minorVersion' => $minor, 'patchVersion' => $patch) as $field => $expected) {
    if (!isset($library[$field]) || !_h5p_themer_native_density_integer($library[$field]) || (int) $library[$field] !== $expected) { return NULL; }
  }
  if (!isset($library['libraryId']) || !_h5p_themer_native_density_integer($library['libraryId']) || (int) $library['libraryId'] < 1) { return NULL; }
  return array('library' => $name . ' ' . $major . '.' . $minor, 'patch' => $patch, 'libraryId' => (string) $library['libraryId']);
}

/** API object has no constructor side effects; do not construct Core services. */
function _h5p_themer_native_density_api() {
  if (!h5p_themer_native_density_enabled() || !h5p_themer_compatibility()['compatible'] ||
      config_get('h5p.settings', 'h5p_library_development')) { return NULL; }
  return new H5PBackdrop();
}

/** Content ID comes from H5P; main-library identity is re-read server-side. */
function h5p_themer_native_density_player($node) {
  if (!is_object($node) || !isset($node->type) || $node->type !== 'h5p_content' || isset($node->in_preview)) { return NULL; }
  try {
    $api = _h5p_themer_native_density_api();
    if ($api === NULL) { return NULL; }
    $id = h5p_get_content_id($node);
    if (!_h5p_themer_native_density_integer($id) || (int) $id < 1) { return NULL; }
    $content = $api->loadContent($id);
    if (!is_array($content) || !isset($content['id'], $content['libraryName'], $content['libraryId'])) { return NULL; }
    if (!_h5p_themer_native_density_integer($content['id']) || (string) $content['id'] !== (string) $id) { return NULL; }
    $qualified = _h5p_themer_native_density_library($api, $content['libraryName']);
    if ($qualified === NULL || !_h5p_themer_native_density_integer($content['libraryId']) || (string) $content['libraryId'] !== $qualified['libraryId'] ||
        !isset($content['libraryMajorVersion'], $content['libraryMinorVersion']) ||
        !_h5p_themer_native_density_integer($content['libraryMajorVersion']) || (int) $content['libraryMajorVersion'] !== 1 ||
        !_h5p_themer_native_density_integer($content['libraryMinorVersion']) || (int) $content['libraryMinorVersion'] !== 8 ||
        !isset($content['embedType'], $content['libraryEmbedTypes']) ||
        !is_string($content['embedType']) || !is_string($content['libraryEmbedTypes']) ||
        H5PCore::determineEmbedType($content['embedType'], $content['libraryEmbedTypes']) !== 'iframe') { return NULL; }
    return array('id' => (string) $id, 'library' => $qualified['library'], 'patch' => $qualified['patch']);
  }
  catch (Throwable $e) { return NULL; }
}

/** NULL means preserve/restore the player's original native profile. */
function _h5p_themer_native_density_profile(array $theme) {
  return $theme['kind'] === 'custom' ? $theme['density'] : NULL;
}

/** Pure theme metadata. The selectable catalog matters even when initial Core is null. */
function h5p_themer_native_density_settings() {
  if (!h5p_themer_native_density_enabled()) { return NULL; }
  $global = h5p_themer_runtime_theme();
  if ($global['errors']) { return NULL; }
  $learner = h5p_themer_learner_selection_enabled();
  $profiles = array(); $bundle_id = NULL;
  if ($learner) {
    $bundle = h5p_themer_learner_bundle(); $catalog = h5p_themer_learner_catalog();
    if ($bundle['errors'] || $catalog['errors']) { return NULL; }
    $bundle_id = $bundle['data']['id'];
    foreach ($bundle['data']['keys'] as $token => $key) {
      if (!isset($catalog['data'][$token])) { return NULL; }
      $resolved = h5p_themer_selection_resolve($catalog['data'][$token]['reference']);
      if ($resolved['errors']) { return NULL; }
      $profiles[$key] = _h5p_themer_native_density_profile($resolved['data']);
    }
    ksort($profiles);
  }
  $profile = _h5p_themer_native_density_profile($global['data']);
  // Learner capability must also be available for Core/built-in-only catalogs.
  // Global-only mode still needs an actual saved profile before attaching.
  if (!$learner && $profile === NULL) { return NULL; }
  return array('version' => 2, 'learner' => $learner, 'global' => $profile, 'bundle' => $bundle_id, 'profiles' => (object) $profiles);
}

/** Route-independent metadata, policy and installed-patch cache dependencies. */
function h5p_themer_native_density_cache_snapshot() {
  if (!h5p_themer_native_density_enabled()) { return hash('sha256', 'disabled'); }
  $libraries = array();
  try {
    $api = _h5p_themer_native_density_api();
    if ($api !== NULL) {
      foreach (_h5p_themer_native_density_registry() as $name => $version) { $libraries[$name] = _h5p_themer_native_density_library($api, $name); }
    }
  } catch (Throwable $e) { $libraries = array(); }
  return hash('sha256', serialize(array(_h5p_themer_native_density_registry(), $libraries, h5p_themer_native_density_settings())));
}

/** Parent only, via node_view after the H5P type has built its playback. */
function h5p_themer_native_density_attach($node = NULL) {
  if (!h5p_themer_native_density_enabled()) { return; }
  $path = current_path();
  if (path_is_admin($path) || preg_match('~\A(?:admin(?:/|$)|node/(?:add(?:/|$)|[0-9]+/(?:edit|delete)(?:/|$))|h5p/embed(?:/|$))~', $path)) { return; }
  $player = h5p_themer_native_density_player($node);
  if ($player === NULL) { return; }
  $attached = &backdrop_static(__FUNCTION__, array());
  if (isset($attached[$player['id']])) { return; }
  $data = h5p_themer_native_density_settings();
  if ($data === NULL) { return; }
  if (!$attached) {
    backdrop_add_http_header('Cache-Control', 'no-cache, must-revalidate');
    backdrop_add_js(array('h5pThemerNativeDensity' => array('payload' => backdrop_json_encode($data))), 'setting');
    backdrop_add_js(backdrop_get_path('module', 'h5p_themer') . '/js/h5p_themer.native-density.js');
  }
  backdrop_add_js(array('h5pThemerNativeDensity' => array('players' => array('cid-' . $player['id'] => $player))), 'setting');
  $attached[$player['id']] = TRUE;
}
