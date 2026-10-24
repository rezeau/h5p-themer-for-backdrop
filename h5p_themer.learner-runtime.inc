<?php
/**
 * @file
 * Immutable learner palettes, using the existing catalog/resolver/serializer.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/** Only ordinary Backdrop playback pages participate. */
function h5p_themer_playback_context_allowed() {
  if (!h5p_themer_compatibility()['compatible']) { return FALSE; }
  $path = current_path();
  return !path_is_admin($path) && !preg_match('~\A(?:admin(?:/|$)|node/(?:add(?:/|$)|[0-9]+/(?:edit|delete)(?:/|$))|h5p/embed(?:/|$))~', $path);
}

/** Optional picker eligibility is independent of playback capability. */
function h5p_themer_learner_context_allowed() {
  return h5p_themer_learner_selection_enabled() && h5p_themer_playback_context_allowed();
}

/** Same renderer and publisher in either mode; global mode never reads a catalog. */
function h5p_themer_playback_bundle() {
  if (h5p_themer_learner_selection_enabled()) { return h5p_themer_learner_bundle(); }
  // Conditional global roots also suppress stale learner assets after a toggle.
  $theme = h5p_themer_runtime_theme();
  if ($theme['errors']) { return $theme; }
  $rules = h5p_themer_runtime_rules($theme['data']);
  if ($rules['errors']) { return $rules; }
  $body = _h5p_themer_runtime_render_rules($rules['data'], array('html:not([data-h5p-themer-learner]) .h5p-content'));
  if ($body === NULL) { return array('data' => NULL, 'errors' => array(t('Global playback styles could not be prepared.'))); }
  // A newly loaded global Core/sparse theme must neutralize stale learner CSS.
  $reset = "html:not([data-h5p-themer-learner]) .h5p-content {\n";
  foreach (h5p_themer_color_properties() as $property) { $reset .= '  ' . $property . ": inherit;\n"; }
  foreach (h5p_themer_density_tokens('medium') as $property => $value) { $reset .= '  ' . $property . ": initial;\n"; }
  $body = $reset . "  --h5p-themer-density-evaluation-row-factor: initial;\n}\n" . $body;
  $id = 't' . hash('sha256', 'global-v1' . "\n" . $body);
  return array('data' => array('id' => $id, 'css' => 'html { --h5p-themer-learner-bundle: ' . $id . "; }\n" . $body), 'errors' => array());
}

/** Parent and internal frames consume the same immutable readiness identity. */
function h5p_themer_playback_asset() {
  if (!h5p_themer_playback_context_allowed()) { return array('data' => NULL, 'errors' => array()); }
  $bundle = h5p_themer_playback_bundle();
  if ($bundle['errors']) { return $bundle; }
  return h5p_themer_runtime_asset($bundle['data']['css'], h5p_themer_learner_selection_enabled() ? 'learner-v1' : 'global-v1');
}

/**
 * Pure build: fixed selectors, opaque keys, canonical validated declarations.
 * No Core palette is copied: an explicit selection disables the global rule,
 * leaving native :root variables as the fallback for every missing property.
 */
function h5p_themer_learner_bundle() {
  $failure = array('data' => NULL, 'errors' => array(t('Learner theme palettes could not be prepared.')));
  $catalog = h5p_themer_learner_catalog();
  $global = h5p_themer_runtime_theme();
  if ($catalog['errors'] || $global['errors']) { return $failure; }
  $global_rules = h5p_themer_runtime_rules($global['data']);
  if ($global_rules['errors']) { return $failure; }
  $global_roots = array('html:not([data-h5p-themer-learner]) .h5p-content', 'html[data-h5p-themer-learner] .h5p-content:not([data-h5p-themer-eligible])');
  $body = _h5p_themer_runtime_render_rules($global_rules['data'], $global_roots);
  if ($body === NULL) { return $failure; }
  // Neutralize any stale legacy .h5p-content global asset (e.g. an AJAX page
  // assembled before the toggle). Inherit the real root values, never copies.
  // Ineligible siblings keep the administrator global palette; only positively
  // classified native roots receive learner neutralization and overrides.
  $body .= "html[data-h5p-themer-learner] .h5p-content[data-h5p-themer-eligible] {\n";
  foreach (h5p_themer_color_properties() as $property) { $body .= '  ' . $property . ": inherit;\n"; }
  // A Core/built-in selection must not retain a stale global custom profile.
  // Initial makes these unregistered properties absent, including in nested DOM.
  foreach (h5p_themer_density_tokens('medium') as $property => $value) { $body .= '  ' . $property . ": initial;\n"; }
  $body .= "  --h5p-themer-density-evaluation-row-factor: initial;\n";
  $body .= "}\n";
  $options = array(); $keys = array(); $rules = array();
  foreach ($catalog['data'] as $token => $entry) {
    if ($token !== 'site_default') {
      $resolved = h5p_themer_selection_resolve($entry['reference']);
      if ($resolved['errors']) { continue; }
      $compiled = h5p_themer_runtime_rules($resolved['data']);
      if ($compiled['errors']) { continue; }
      $canonical = _h5p_themer_runtime_render_rules($compiled['data'], array('.h5p-content'));
      if ($canonical === NULL) { return $failure; }
      $key = 't' . hash('sha256', $token . "\n" . $canonical);
      $rule = _h5p_themer_runtime_render_rules($compiled['data'], array('html[data-h5p-themer-learner="' . $key . '"] .h5p-content[data-h5p-themer-eligible]'));
      if ($rule === NULL) { return $failure; }
      $keys[$token] = $key;
      $rules[$token] = $rule;
    }
    $options[] = array('value' => $token, 'label' => $token === 'builtin:daylight' ? t('Daylight') : $entry['label']);
  }
  if (!isset($catalog['data']['site_default'], $keys['core'], $keys['builtin:daylight']) || count($options) < 3) { return $failure; }
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
