<?php
/**
 * @file
 * Optional, read-only learner presentation metadata. GPL-3.0-or-later.
 */

/** Accept only opaque hex; normalize for presentation, never for storage. */
function _h5p_themer_learner_icon_hex($value) {
  if (!is_string($value) || !preg_match('/\A#(?:[a-fA-F0-9]{3}|[a-fA-F0-9]{6})\z/', $value)) { return NULL; }
  if (strlen($value) === 4) { $value = '#' . $value[1] . $value[1] . $value[2] . $value[2] . $value[3] . $value[3]; }
  return strtoupper($value);
}

/** Read only the two fixed literal properties from Core's root rule. */
function _h5p_themer_learner_icon_core_parse($css) {
  if (!is_string($css) || strlen($css) > 262144 || strpos($css, "\0") !== FALSE) { return NULL; }
  $css = preg_replace('~/\*.*?\*/~s', '', $css);
  if (preg_match_all('/:root\s*\{([^{}]*)\}/', $css, $root) !== 1) { return NULL; }
  $pair = array();
  foreach (array('--h5p-theme-main-cta-base', '--h5p-theme-alternative-darker') as $property) {
    if (preg_match_all('/(?:^|;)\s*' . preg_quote($property, '/') . '\s*:\s*([^;{}]+)\s*(?=;|$)/', $root[1][0], $matches) !== 1) { return NULL; }
    $color = _h5p_themer_learner_icon_hex(trim($matches[1][0]));
    if ($color === NULL) { return NULL; }
    $pair[] = $color;
  }
  return $pair;
}

/** Missing/unreadable Core or any invalid metadata leaves a native selector. */
function h5p_themer_learner_icons() {
  try {
    $path = BACKDROP_ROOT . '/' . backdrop_get_path('module', 'h5p') . '/library/styles/h5p-theme-variables.css';
    if (!is_file($path) || !is_readable($path) || filesize($path) > 262144) { return NULL; }
    $core = _h5p_themer_learner_icon_core_parse(file_get_contents($path));
    $catalog = h5p_themer_theme_catalog();
    if ($core === NULL || $catalog['errors']) { return NULL; }
    $icons = array();
    foreach ($catalog['data'] as $token => $entry) {
      $resolved = $token === 'site_default' ? h5p_themer_default_selection_load() : h5p_themer_selection_resolve($entry['reference']);
      if ($resolved['errors']) { return NULL; }
      $pair = array();
      foreach (array('--h5p-theme-main-cta-base', '--h5p-theme-alternative-darker') as $i => $property) {
        $colors = $resolved['data']['colors'];
        $color = array_key_exists($property, $colors) ? _h5p_themer_learner_icon_hex($colors[$property]) : $core[$i];
        if ($color === NULL) { return NULL; }
        $pair[] = $color;
      }
      $icons[$token] = $pair;
    }
    return $icons;
  }
  catch (Exception $e) { return NULL; }
}
