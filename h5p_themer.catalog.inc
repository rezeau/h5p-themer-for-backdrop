<?php
/**
 * @file
 * Read-only choices shared by future learner consumers, independent of admin UI.
 * SPDX-License-Identifier: GPL-2.0-or-later.
 */

require_once __DIR__ . '/h5p_themer.config.inc';

/**
 * Returns validated, effective choices as data/errors without private metadata.
 *
 * Data is keyed by stable UI tokens. Each entry contains only label/reference.
 * The site_default reference is an in-memory sentinel, never a stored global
 * selection or a snapshot of its colors. Core's reference is NULL; Daylight is
 * a distinct pinned built-in. Other references use the existing schema-2 shape
 * and h5p_themer_selection_resolve() remains the only palette resolver.
 *
 * All valid saved custom themes are included, with effective configuration
 * overrides. Invalid/unresolvable themes are skipped without leaking storage
 * diagnostics. Labels are plain text; consumers must escape for their context.
 * No static/persistent cache, assets, writes, compatibility gate or UI runs here.
 */
function h5p_themer_theme_catalog() {
  $result = array('data' => array(
    'site_default' => array('label' => t('Site default'), 'reference' => array('kind' => 'site_default')),
    'core' => array('label' => t('H5P Core defaults'), 'reference' => NULL),
  ), 'errors' => array());
  $presets = h5p_themer_presets('moodle-478fed30d2a4');
  if ($presets['errors']) {
    return array('data' => NULL, 'errors' => array(t('The H5P theme catalog could not be read.')));
  }
  foreach ($presets['data'] as $id => $preset) {
    $reference = array('kind' => 'builtin', 'id' => $id, 'version' => $preset['version']);
    $resolved = h5p_themer_selection_resolve($reference);
    if (!$resolved['errors']) {
      $result['data']['builtin:' . $id] = array('label' => $resolved['data']['label'], 'reference' => $reference);
    }
  }
  $customs = array();
  try {
    foreach (config_get_names_with_prefix('h5p_themer.theme.') as $name) {
      $id = substr($name, strlen('h5p_themer.theme.'));
      $reference = array('kind' => 'custom', 'id' => $id);
      $resolved = h5p_themer_selection_resolve($reference);
      if (!$resolved['errors']) {
        $customs[$id] = array('label' => $resolved['data']['label'], 'reference' => $reference);
      }
    }
  }
  catch (ConfigException $e) {
    return array('data' => NULL, 'errors' => array(t('The H5P theme catalog could not be read.')));
  }
  uksort($customs, function ($a, $b) use ($customs) {
    $order = strnatcasecmp($customs[$a]['label'], $customs[$b]['label']);
    return $order ? $order : strcmp($a, $b);
  });
  foreach ($customs as $id => $entry) {
    $result['data']['custom:' . $id] = $entry;
  }
  return $result;
}
