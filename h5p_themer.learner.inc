<?php
/**
 * @file
 * Parent-page learner control. Browser tab-session preference only; no endpoints.
 * SPDX-License-Identifier: GPL-2.0-or-later.
 */

require_once __DIR__ . '/h5p_themer.learner-icons.inc';

/** Pure public metadata; no private admin fields or unvalidated CSS/URLs. */
function h5p_themer_learner_ui_settings() {
  if (!h5p_themer_learner_selection_enabled()) { return NULL; }
  $bundle = h5p_themer_learner_bundle();
  if ($bundle['errors']) { return NULL; }
  return array('version' => 2, 'enabled' => TRUE, 'label' => t('H5P theme'),
    'message' => t('Your theme choice is remembered for this browser tab when session storage is available. This does not save H5P answers.'),
    'applied' => t('Theme applied to supported activities.'),
    'partial' => t('Some activities are not ready or cannot be themed. Their playback is unchanged.'),
    'unavailable' => t('Theme styles are not ready. Site default remains in use until matching styles are available.'),
    'fallback' => t('That theme is unavailable. Using Site default.'),
    'bundle' => $bundle['data']['id'], 'keys' => $bundle['data']['keys'], 'options' => $bundle['data']['options'], 'icons' => h5p_themer_learner_icons());
}

/** Attach once through Backdrop; fixed external files, no additional inline code. */
function h5p_themer_learner_attach() {
  if (!h5p_themer_learner_context_allowed()) { return; }
  $attached = &backdrop_static(__FUNCTION__, FALSE);
  if ($attached) { return; }
  $settings = h5p_themer_learner_ui_settings();
  if ($settings === NULL) { return; }
  $asset = h5p_themer_learner_asset();
  if ($asset['errors'] || $asset['data'] === NULL) { return; }
  $module = backdrop_get_path('module', 'h5p_themer');
  // Atomic string prevents jQuery deep-merging deleted choices back into AJAX settings.
  backdrop_add_js(array('h5pThemerLearner' => array('payload' => backdrop_json_encode($settings))), 'setting');
  backdrop_add_js($module . '/js/h5p_themer.learner-picker.js');
  backdrop_add_js($module . '/js/h5p_themer.learner.js');
  backdrop_add_css($module . '/css/h5p_themer.learner.css');
  backdrop_add_css($module . '/css/h5p_themer.learner-picker.css');
  backdrop_add_css($asset['data']->path);
  $attached = TRUE;
}

/** All enabled palettes affect the immutable bundle; disabled behavior is unchanged. */
function h5p_themer_learner_ui_cache_snapshot() {
  return hash('sha256', serialize(h5p_themer_learner_ui_settings()));
}
