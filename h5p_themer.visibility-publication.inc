<?php
/**
 * @file
 * Locked administration publication safeguards; no import coordinator.
 * SPDX-License-Identifier: GPL-3.0-or-later.
 */

/** Read-only guard and stale-form fingerprint; never assumes legacy is safe. */
function _h5p_themer_publication_state($expected = NULL) {
  if (_h5p_themer_admin_guard()) { throw new ConfigException('Administration access is unavailable.'); }
  _h5p_themer_visibility_write_guard();
  $witness = h5p_themer_visibility_witness();
  $activation = h5p_themer_visibility_activation();
  $policy = _h5p_themer_visibility_policy_read(FALSE);
  $effective = _h5p_themer_visibility_policy_read(TRUE);
  if ($witness['errors'] || !$witness['required'] || $activation['state'] !== 'complete' ||
      $activation['journal']['failure'] !== '' ||
      $policy['state'] !== 'initialized' || $effective['state'] !== 'initialized' ||
      $policy['data'] !== $effective['data']) {
    throw new ConfigException('Verified visibility activation is required.');
  }
  // The journal snapshot is historical, not the current editable policy.
  // Require its valid completion, never equality with its original visible map.
  $lifecycle = hash('sha256', serialize(array($witness['version'], $activation['journal'])));
  $fingerprint = hash('sha256', serialize(array($lifecycle, $policy['data'])));
  if ($expected !== NULL && (!is_string($expected) || !hash_equals($fingerprint, $expected))) {
    throw new ConfigException('Publication state changed; reload the form.');
  }
  return array('fingerprint' => $fingerprint, 'lifecycle' => $lifecycle, 'policy' => $policy['data']);
}

/** Capture once in native cached form state; a failed build never gains trust. */
function _h5p_themer_publication_form(&$form_state, $building = FALSE) {
  try {
    if ($building && !array_key_exists('h5p_themer_publication', $form_state)) {
      $form_state['h5p_themer_publication'] = NULL;
      $form_state['h5p_themer_publication'] = _h5p_themer_publication_state()['fingerprint'];
    }
    if (empty($form_state['h5p_themer_publication'])) { throw new ConfigException('Unverified form.'); }
    _h5p_themer_publication_state($form_state['h5p_themer_publication']);
    return array();
  }
  catch (Throwable $e) {
    return array(t('Theme publication safety could not be verified. Complete visibility activation, resolve overrides or synchronization conflicts, and reload the form. No theme mutation was started by this validation.'));
  }
}

/** Under the shared renewable lock, persist and verify a restrictive tombstone. */
function _h5p_themer_publication_hide($id, $expected) {
  if (!h5p_themer_valid_theme_id($id)) { throw new ConfigException('Invalid destination.'); }
  _h5p_themer_visibility_lease();
  $before = _h5p_themer_publication_state($expected);
  $data = $before['policy'];
  $data['themes']['custom:' . $id] = FALSE;
  $record = new Config('h5p_themer.learner_visibility', config_get_config_storage());
  $record->setData($data); $record->validateData();
  // Config::save() ignores the backend return value. The public storage API
  // lets us require success even when the previous entry was already false.
  if ($record->getStorage()->write($record->getName(), $data) !== TRUE) { throw new ConfigException('Restrictive policy write failed.'); }
  // The guard refreshes request-local Config objects before read-back.
  $after = _h5p_themer_publication_state();
  if ($after['policy'] !== $data || $after['lifecycle'] !== $before['lifecycle']) { throw new ConfigException('Restrictive policy verification failed.'); }
  return $after['fingerprint'];
}

/** Native Config save/delete are not transactions; verify persistent bytes. */
function _h5p_themer_publication_verify_theme($id, array $candidate) {
  $record = new Config('h5p_themer.theme.' . $id, config_get_config_storage());
  $record->load();
  $actual = $record->getOriginal();
  unset($actual['_config_name'], $candidate['_config_name']);
  if ($record->isNew() || $actual !== $candidate) { throw new ConfigException('Theme read-back verification failed.'); }
}
