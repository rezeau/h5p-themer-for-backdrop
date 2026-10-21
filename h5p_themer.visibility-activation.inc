<?php
/**
 * @file
 * Explicit activation lifecycle; no writes from policy/catalog reads.
 * SPDX-License-Identifier: GPL-3.0-or-later.
 */

function h5p_themer_visibility_witness() {
  try {
    if (!function_exists('backdrop_get_installed_schema_version')) { require_once BACKDROP_ROOT . '/core/includes/install.inc'; }
    $value = backdrop_get_installed_schema_version('h5p_themer', TRUE);
    if (is_string($value) && preg_match('/\A(?:0|[1-9][0-9]{0,8})\z/', $value)) { $value = (int) $value; }
    if (!is_int($value) || $value < 0 || ($value !== 0 && $value < 999)) { throw new RuntimeException('Unknown witness.'); }
    return array('version' => $value, 'required' => $value >= 1000, 'errors' => array());
  }
  catch (Throwable $e) {
    return array('version' => NULL, 'required' => TRUE, 'errors' => array(t('H5P theme activation metadata could not be verified. Optional learner themes are hidden.')));
  }
}

/** Validate the complete frozen journal; never echo supplied values. */
function h5p_themer_visibility_journal_valid($data) {
  $fields = array('schema_version', 'status', 'mode', 'operation', 'snapshot', 'snapshot_hash', 'catalog_hash', 'cache_pending', 'failure');
  if (!is_array($data) || array_diff($fields, array_keys($data)) || array_diff(array_keys($data), $fields)) { return FALSE; }
  if ($data['schema_version'] !== 1 || !in_array($data['status'], array('prepared', 'pending', 'complete', 'failed'), TRUE) ||
      !in_array($data['mode'], array('upgrade', 'fresh'), TRUE) || !is_bool($data['cache_pending']) ||
      !in_array($data['failure'], array('', 'interrupted'), TRUE)) { return FALSE; }
  foreach (array('operation' => 32, 'snapshot_hash' => 64, 'catalog_hash' => 64) as $field => $length) {
    if (!is_string($data[$field]) || !preg_match('/\A[a-f0-9]{' . $length . '}\z/', $data[$field])) { return FALSE; }
  }
  if (h5p_themer_visibility_errors($data['snapshot']) || array_keys($data['snapshot']) !== array('schema_version', 'themes') ||
      !hash_equals($data['snapshot_hash'], hash('sha256', serialize($data['snapshot'])))) { return FALSE; }
  return $data['status'] === 'complete' || $data['cache_pending'] === TRUE;
}

/** state_initialize() preserves present NULL entries, unlike state_get(default). */
function h5p_themer_visibility_activation() {
  $witness = h5p_themer_visibility_witness();
  try {
    $states = state_initialize();
    if (!is_array($states)) { throw new RuntimeException('Unknown state.'); }
    $exists = array_key_exists('h5p_themer.visibility_activation', $states);
    $journal = $exists ? $states['h5p_themer.visibility_activation'] : NULL;
    if ($witness['errors']) { return array('state' => 'uncertain', 'journal' => NULL); }
    if (!$exists) { return array('state' => $witness['required'] ? 'required' : 'legacy', 'journal' => NULL); }
    if (!h5p_themer_visibility_journal_valid($journal)) { throw new RuntimeException('Invalid journal.'); }
    if (!$witness['required']) { return array('state' => 'incomplete', 'journal' => $journal); }
    return array('state' => $journal['status'] === 'complete' && !$journal['cache_pending'] ? 'complete' : 'incomplete', 'journal' => $journal);
  }
  catch (Throwable $e) { return array('state' => 'uncertain', 'journal' => NULL); }
}

function _h5p_themer_visibility_policy_value(array $data) {
  return array('schema_version' => $data['schema_version'], 'themes' => $data['themes']);
}

/** Frozen original definitions plus effective catalog detect concurrent changes. */
function _h5p_themer_visibility_capture($mode) {
  $catalog = h5p_themer_theme_catalog();
  if ($catalog['errors']) { throw new ConfigException('Catalog unavailable.'); }
  $policy = array('schema_version' => 1, 'themes' => array()); $resolved = array(); $references = array();
  foreach ($catalog['data'] as $token => $entry) {
    $theme = $token === 'site_default' ? h5p_themer_default_selection_load() : h5p_themer_selection_resolve($entry['reference']);
    if ($theme['errors']) { throw new ConfigException('Theme resolution failed.'); }
    $resolved[$token] = $theme['data']['colors'];
    $references[$token] = $entry['reference'];
    if (!in_array($token, h5p_themer_visibility_fixed_tokens(), TRUE)) {
      $policy['themes'][$token] = $mode === 'upgrade' || strpos($token, 'builtin:') === 0;
    }
  }
  ksort($policy['themes']);
  $storage = config_get_config_storage(); $definitions = array();
  $names = config_get_names_with_prefix('h5p_themer.theme.'); sort($names);
  foreach ($names as $name) { $definitions[$name] = $storage->read($name); }
  return array('policy' => $policy, 'hash' => hash('sha256', serialize(array($references, $definitions, $resolved, h5p_themer_config_load('h5p_themer.settings')['data']))));
}

function _h5p_themer_visibility_lease() {
  if (!lock_acquire('h5p_themer_admin_write', 120)) { throw new ConfigException('Write lock lost.'); }
}

function _h5p_themer_visibility_write_guard() {
  $cache = &backdrop_static('config');
  if (isset($cache['active']) && is_array($cache['active'])) {
    foreach (array_keys($cache['active']) as $name) { if (strpos($name, 'h5p_themer.') === 0) { unset($cache['active'][$name]); } }
  }
  $witness = h5p_themer_visibility_witness();
  if ($witness['errors']) { throw new ConfigException('Unknown witness.'); }
  $states = state_initialize();
  if (!is_array($states) || !empty($states['config_sync'])) { throw new ConfigException('Configuration sync is active.'); }
  $record = new Config('h5p_themer.learner_visibility', config_get_config_storage());
  foreach (array('schema_version', 'themes', '_config_static') as $field) {
    if ($record->isOverridden($field)) { throw new ConfigException('Policy override is active.'); }
  }
}

function _h5p_themer_visibility_journal_save(array $journal) {
  if (!h5p_themer_visibility_journal_valid($journal)) { throw new ConfigException('Invalid journal candidate.'); }
  _h5p_themer_visibility_lease();
  _h5p_themer_visibility_write_guard();
  $before = state_initialize();
  if (array_key_exists('h5p_themer.visibility_activation', $before)) {
    $current = $before['h5p_themer.visibility_activation'];
    if (!h5p_themer_visibility_journal_valid($current) || $current['operation'] !== $journal['operation']) { throw new ConfigException('Activation journal changed.'); }
    if ($current['status'] === 'complete' && $journal['status'] !== 'complete') { throw new ConfigException('Cannot downgrade completion.'); }
  }
  elseif ($journal['status'] !== 'prepared') { throw new ConfigException('Activation journal disappeared.'); }
  state_set('h5p_themer.visibility_activation', $journal);
  $states = state_initialize();
  if (!is_array($states) || !isset($states['h5p_themer.visibility_activation']) ||
      $states['h5p_themer.visibility_activation'] !== $journal) { throw new ConfigException('Journal verification failed.'); }
}

/**
 * Prepare before Core records the upgrade barrier, or in the genuine install
 * hook. This never saves a visibility policy or rewrites a custom definition.
 */
function _h5p_themer_visibility_prepare($mode) {
  if (!lock_acquire('h5p_themer_admin_write', 120)) { return FALSE; }
  try {
    _h5p_themer_visibility_write_guard();
    $activation = h5p_themer_visibility_activation(); $witness = h5p_themer_visibility_witness();
    if ($activation['journal'] !== NULL) {
      return h5p_themer_visibility_journal_valid($activation['journal']) && $activation['journal']['mode'] === $mode;
    }
    if (($mode === 'upgrade' && $witness['required']) || $activation['state'] === 'uncertain') { return FALSE; }
    if (_h5p_themer_visibility_policy_read(FALSE)['state'] !== 'absent') { return FALSE; }
    $capture = _h5p_themer_visibility_capture($mode);
    $journal = array('schema_version' => 1, 'status' => 'prepared', 'mode' => $mode,
      'operation' => bin2hex(random_bytes(16)), 'snapshot' => $capture['policy'],
      'snapshot_hash' => hash('sha256', serialize($capture['policy'])), 'catalog_hash' => $capture['hash'],
      'cache_pending' => TRUE, 'failure' => '');
    _h5p_themer_visibility_journal_save($journal);
    return TRUE;
  }
  catch (Throwable $e) { return FALSE; }
  finally { lock_release('h5p_themer_admin_write'); }
}

/** Resume only a frozen operation. Missing journals are never recaptured. */
function h5p_themer_visibility_initialize() {
  $failure = array('ok' => FALSE, 'code' => 'incomplete', 'errors' => array(t('H5P theme visibility activation is incomplete. Optional learner themes remain hidden; inspect the activation journal before recovery.')));
  if (!lock_acquire('h5p_themer_admin_write', 120)) { return array('ok' => FALSE, 'code' => 'locked', 'errors' => array(t('Another H5P theme operation is in progress.'))); }
  $journal = NULL;
  try {
    _h5p_themer_visibility_write_guard();
    $witness = h5p_themer_visibility_witness(); $activation = h5p_themer_visibility_activation();
    $journal = $activation['journal'];
    if (!$witness['required'] || $journal === NULL || !h5p_themer_visibility_journal_valid($journal)) { return $failure; }
    $stored = _h5p_themer_visibility_policy_read(FALSE);
    if ($journal['status'] === 'complete') {
      if ($stored['state'] !== 'initialized') { return $failure; }
      if (!$journal['cache_pending']) { return array('ok' => TRUE, 'code' => 'already_complete', 'errors' => array()); }
      // Cache-only retry never rewrites a valid, possibly now hidden, policy.
    }
    else {
      $capture = _h5p_themer_visibility_capture($journal['mode']);
      if (!hash_equals($journal['catalog_hash'], $capture['hash']) || $capture['policy'] !== $journal['snapshot']) { return $failure; }
      if ($stored['state'] !== 'absent' && ($stored['state'] !== 'initialized' ||
          _h5p_themer_visibility_policy_value($stored['data']) !== $journal['snapshot'])) { return $failure; }
      $journal['status'] = 'pending'; $journal['failure'] = '';
      _h5p_themer_visibility_journal_save($journal);
      if ($stored['state'] === 'absent') {
        _h5p_themer_visibility_lease();
        _h5p_themer_visibility_write_guard();
        $record = new Config('h5p_themer.learner_visibility', config_get_config_storage());
        $record->setData($journal['snapshot']); $record->validateData(); $record->save();
      }
      $verify = _h5p_themer_visibility_policy_read(FALSE);
      if ($verify['state'] !== 'initialized' || _h5p_themer_visibility_policy_value($verify['data']) !== $journal['snapshot'] ||
          _h5p_themer_visibility_capture($journal['mode'])['hash'] !== $journal['catalog_hash']) { throw new ConfigException('Policy verification failed.'); }
      $journal['status'] = 'complete';
      _h5p_themer_visibility_journal_save($journal);
    }
    _h5p_themer_visibility_lease(); cache_clear_all();
    $journal['cache_pending'] = FALSE;
    _h5p_themer_visibility_journal_save($journal);
    return array('ok' => TRUE, 'code' => 'completed', 'errors' => array());
  }
  catch (Throwable $e) {
    // No automatic rollback to visible. Failed failure-record writes may leave
    // prepared/pending data, which the persisted barrier also keeps closed.
    if ($journal !== NULL && $journal['status'] !== 'complete') {
      try {
        _h5p_themer_visibility_lease(); $current = h5p_themer_visibility_activation()['journal'];
        if ($current !== NULL && $current['operation'] === $journal['operation'] && $current['status'] !== 'complete') {
          $current['status'] = 'failed'; $current['failure'] = 'interrupted';
          _h5p_themer_visibility_journal_save($current);
        }
      }
      catch (Throwable $ignored) {}
    }
    return $failure;
  }
  finally { lock_release('h5p_themer_admin_write'); }
}
