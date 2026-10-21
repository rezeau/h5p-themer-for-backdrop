<?php
/**
 * @file
 * Read-only learner visibility policy. SPDX-License-Identifier: GPL-3.0-or-later.
 */

require_once __DIR__ . '/h5p_themer.catalog.inc';
require_once __DIR__ . '/h5p_themer.visibility-activation.inc';

/** Fixed identities are independent of labels, palettes and permissions. */
function h5p_themer_visibility_fixed_tokens() {
  return array('site_default', 'core', 'builtin:daylight');
}

/** Canonical token or NULL; never trim, fold case or accept arbitrary names. */
function h5p_themer_visibility_token($token) {
  if (!is_string($token)) { return NULL; }
  if (in_array($token, h5p_themer_visibility_fixed_tokens(), TRUE)) { return $token; }
  if (!preg_match('/\A(?:builtin|custom):([a-z][a-z0-9_]{0,63})\z/', $token, $match)) { return NULL; }
  return h5p_themer_valid_theme_id($match[1]) ? $token : NULL;
}

/** Strict object validation, with constant diagnostics and no supplied values. */
function h5p_themer_visibility_errors($data) {
  $invalid = array(t('The learner theme visibility policy is invalid. Optional learner themes are hidden; repair the policy through authorized administration.'));
  if (!is_array($data) || !isset($data['schema_version']) || $data['schema_version'] !== 1 ||
      !array_key_exists('themes', $data) || !is_array($data['themes'])) { return $invalid; }
  foreach ($data as $field => $value) {
    if (!in_array($field, array('schema_version', 'themes', '_config_name', '_config_static'), TRUE)) { return $invalid; }
  }
  if ((isset($data['_config_name']) && $data['_config_name'] !== 'h5p_themer.learner_visibility') ||
      (array_key_exists('_config_name', $data) && !is_string($data['_config_name'])) ||
      (array_key_exists('_config_static', $data) && !is_bool($data['_config_static']))) { return $invalid; }
  foreach ($data['themes'] as $token => $visible) {
    // Fixed choices do not belong to the editable policy map.
    if (h5p_themer_visibility_token($token) === NULL || !is_bool($visible) ||
        in_array($token, h5p_themer_visibility_fixed_tokens(), TRUE)) { return $invalid; }
  }
  return array();
}

/**
 * Returns absent, initialized or malformed. No reads initialize or repair data.
 *
 * Effective top-level overrides follow the existing Config reader convention.
 * Missing stored policy stays legacy even if overrides exist: activation must
 * explicitly capture existing choices before introducing the stored object.
 * Raw storage reader for the guarded initializer. Public consumers use the
 * activation-gated h5p_themer_visibility_policy_load().
 */
function _h5p_themer_visibility_policy_read($effective = TRUE) {
  $failure = array('state' => 'malformed', 'data' => NULL, 'errors' => h5p_themer_visibility_errors(NULL));
  try {
    $name = 'h5p_themer.learner_visibility';
    $storage = config_get_config_storage();
    $exists = $storage->exists($name);
    if ($exists === FALSE) { return array('state' => 'absent', 'data' => NULL, 'errors' => array()); }
    if ($exists !== TRUE) { return $failure; }
    // Core decodes JSON false to FALSE, which Config::load() treats as missing.
    // Confirm existence independently, then read a fresh object rather than a
    // possibly stale request-static Config. A failed/disappearing read is closed.
    $policy = new Config($name, $storage);
    $policy->load();
    if ($policy->isNew()) { return $failure; }
    $data = $policy->getOriginal();
    // Effective overrides must not turn malformed stored bytes into a policy.
    if (!is_array($data) || h5p_themer_visibility_errors($data)) { return $failure; }
    if ($effective) {
      foreach (array('schema_version', 'themes') as $field) { $data[$field] = $policy->get($field); }
    }
    $errors = h5p_themer_visibility_errors($data);
    return array('state' => $errors ? 'malformed' : 'initialized', 'data' => $errors ? NULL : $data, 'errors' => $errors);
  }
  catch (ConfigException $e) { return $failure; }
  catch (TypeError $e) { return $failure; }
}

/** Enforce the independent persisted witness and activation journal. */
function h5p_themer_visibility_policy_load() {
  $policy = _h5p_themer_visibility_policy_read();
  $activation = h5p_themer_visibility_activation();
  if ($activation['state'] === 'legacy') { return $policy; }
  if ($activation['state'] === 'complete' && $policy['state'] === 'initialized') { return $policy; }
  return array('state' => 'blocked', 'data' => NULL, 'errors' => array(t('H5P theme visibility activation is incomplete or cannot be verified. Optional learner themes are hidden.')));
}

/**
 * Filters the validated full catalog, leaving global/admin resolution intact.
 *
 * Malformed policy fails closed only for optional choices. Diagnostics belong
 * to the existing authenticated overview, not learner metadata. Unknown/stale
 * tokens never create catalog entries. A creation transaction must
 * reset any reused custom token to FALSE before publishing its new definition;
 * a Boolean map alone cannot identify deletion/recreation between two reads.
 */
function h5p_themer_learner_catalog() {
  $catalog = h5p_themer_theme_catalog();
  if ($catalog['errors']) { return $catalog; }
  $policy = h5p_themer_visibility_policy_load();
  if ($policy['state'] === 'absent') { return $catalog; }
  foreach ($catalog['data'] as $token => $entry) {
    if (!in_array($token, h5p_themer_visibility_fixed_tokens(), TRUE) &&
        ($policy['state'] !== 'initialized' || !isset($policy['data']['themes'][$token]) || $policy['data']['themes'][$token] !== TRUE)) {
      unset($catalog['data'][$token]);
    }
  }
  return $catalog;
}
