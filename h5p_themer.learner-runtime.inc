<?php
/**
 * @file
 * Immutable learner palettes, using the existing catalog/resolver/serializer.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/** Only ordinary Backdrop playback pages participate. */
function h5p_themer_learner_context_allowed() {
  if (!h5p_themer_learner_selection_enabled() || !h5p_themer_compatibility()['compatible']) { return FALSE; }
  $path = current_path();
  return !path_is_admin($path) && !preg_match('~\A(?:admin(?:/|$)|node/(?:add(?:/|$)|[0-9]+/(?:edit|delete)(?:/|$))|h5p/embed(?:/|$))~', $path);
}

/**
 * Pure build: fixed selectors, opaque keys, canonical validated declarations.
 * No Core palette is copied: an explicit selection disables the global rule,
 * leaving native :root variables as the fallback for every missing property.
 */
function h5p_themer_learner_bundle() {
  $failure = array('data' => NULL, 'errors' => array(t('Learner theme palettes could not be prepared.')));
  $catalog = h5p_themer_theme_catalog();
  $global = h5p_themer_runtime_css();
  if ($catalog['errors'] || $global['errors']) { return $failure; }
  $wrap = function ($css, $selector) {
    if ($css === '') { return ''; }
    $prefix = ".h5p-content {\n";
    if (substr($css, 0, strlen($prefix)) !== $prefix || substr($css, -2) !== "}\n") { return NULL; }
    return $selector . " {\n" . substr($css, strlen($prefix));
  };
  $global_selector = 'html:not([data-h5p-themer-learner]) .h5p-content, html[data-h5p-themer-learner] .h5p-content:not([data-h5p-themer-eligible])';
  $body = $wrap($global['data'], $global_selector);
  if ($body === NULL) { return $failure; }
  // Neutralize any stale legacy .h5p-content global asset (e.g. an AJAX page
  // assembled before the toggle). Inherit the real root values, never copies.
  // Ineligible siblings keep the administrator global palette; only positively
  // classified native roots receive learner neutralization and overrides.
  $body .= "html[data-h5p-themer-learner] .h5p-content[data-h5p-themer-eligible] {\n";
  foreach (h5p_themer_color_properties() as $property) { $body .= '  ' . $property . ": inherit;\n"; }
  $body .= "}\n";
  $options = array(); $keys = array(); $rules = array();
  foreach ($catalog['data'] as $token => $entry) {
    if ($token !== 'site_default') {
      $resolved = h5p_themer_selection_resolve($entry['reference']);
      if ($resolved['errors']) { continue; }
      $serialized = h5p_themer_runtime_serialize($resolved['data']);
      if ($serialized['errors']) { continue; }
      $key = 't' . hash('sha256', $token . "\n" . $serialized['data']);
      $rule = $wrap($serialized['data'], 'html[data-h5p-themer-learner="' . $key . '"] .h5p-content[data-h5p-themer-eligible]');
      if ($rule === NULL) { return $failure; }
      $keys[$token] = $key;
      $rules[$token] = $rule;
    }
    $options[] = array('value' => $token, 'label' => $token === 'builtin:daylight' ? t('Daylight') : $entry['label']);
  }
  if (!isset($keys['core']) || count($options) < 7) { return $failure; }
  // Presentation sorting must not rotate CSS when only labels change.
  ksort($rules); ksort($keys);
  $body .= implode('', $rules);
  // A fixed, non-H5P property detects matching stylesheet readiness, including
  // H5P aggregation. It carries no colors and cannot alter activity styling.
  $id = 't' . hash('sha256', $body . serialize($keys));
  $css = 'html { --h5p-themer-learner-bundle: ' . $id . "; }\n" . $body;
  return array('data' => array('css' => $css, 'id' => $id, 'keys' => $keys, 'options' => $options), 'errors' => array());
}

/** Reuse the atomic, hash-verified publisher with an explicit format namespace. */
function h5p_themer_learner_asset() {
  if (!h5p_themer_learner_context_allowed()) { return array('data' => NULL, 'errors' => array()); }
  $bundle = h5p_themer_learner_bundle();
  if ($bundle['errors']) { return $bundle; }
  return h5p_themer_runtime_asset($bundle['data']['css'], 'learner-v1');
}
