<?php
/**
 * @file
 * Create-only theme handling on Backdrop's native single configuration form.
 * SPDX-License-Identifier: GPL-3.0-or-later.
 */

/** Implements hook_form_FORM_ID_alter(). Other handlers retain their ordering. */
function h5p_themer_form_config_import_single_form_alter(&$form, &$form_state) {
  foreach (array('validate', 'submit') as $phase) {
    foreach ($form['#' . $phase] as &$handler) {
      if ($handler === 'config_import_single_form_' . $phase) {
        $handler = 'h5p_themer_config_import_single_form_' . $phase;
      }
    }
    unset($handler);
  }
}

/** Decode through the native backend; a single import needs its export name. */
function _h5p_themer_import_decode(array $form_state) {
  $json = isset($form_state['values']['import']) ? $form_state['values']['import'] : NULL;
  if (!is_string($json)) {
    throw new ConfigValidateException(t('Paste a single JSON configuration object.'));
  }
  $data = config_get_config_storage()->decode($json);
  if (!is_array($data) || !isset($data['_config_name']) || !is_string($data['_config_name']) || $data['_config_name'] === '') {
    throw new ConfigValidateException(t('A single configuration object with a string _config_name is required.'));
  }
  return $data;
}

/** Read-only checks, repeated under the shared lock immediately before writes. */
function _h5p_themer_import_check(array $data, $expected = NULL) {
  require_once __DIR__ . '/h5p_themer.admin.inc';
  if (!user_access('synchronize configuration') || _h5p_themer_admin_guard()) {
    throw new ConfigValidateException(t('Importing H5P themes requires configuration synchronization and H5P theme administration access, with a compatible H5P installation.'));
  }
  $name = $data['_config_name'];
  if (strpos($name, 'h5p_themer.theme.') !== 0 || !isset($data['schema_version']) || $data['schema_version'] !== 2) {
    throw new ConfigValidateException(t('Only individual schema-2 custom theme creations are supported.'));
  }
  $record = new Config($name, config_get_config_storage());
  $record->setData($data);
  $record->validateData();
  if ($record->getOriginal() !== $data) {
    throw new ConfigValidateException(t('Theme validation changed the supplied definition. Import was rejected.'));
  }
  foreach (array('schema_version', 'id', 'label', 'description', 'density', 'colors', 'base_preset', '_config_static') as $field) {
    if ($record->isOverridden($field)) {
      throw new ConfigValidateException(t('Remove destination theme overrides before importing.'));
    }
  }
  // A malformed file can decode to FALSE, so never use read() or isNew() here.
  if ($record->getStorage()->exists($name) !== FALSE) {
    throw new ConfigValidateException(t('This theme ID already exists. Imports cannot overwrite existing themes.'));
  }
  $errors = _h5p_themer_admin_creation_default_errors($data['id']);
  if ($errors) { throw new ConfigValidateException(implode(' ', $errors)); }
  return array('record' => $record, 'publication' => _h5p_themer_publication_state($expected));
}

/** Native validator dispatcher; generic import guards are never relaxed. */
function h5p_themer_config_import_single_form_validate(&$form, &$form_state) {
  unset($form_state['h5p_themer_import_candidate'], $form_state['h5p_themer_import_publication']);
  try {
    $data = _h5p_themer_import_decode($form_state);
    if (strpos($data['_config_name'], 'h5p_themer.theme.') !== 0) {
      config_import_single_form_validate($form, $form_state);
      return;
    }
    $publication = _h5p_themer_import_check($data);
    $form_state['config_data'] = $data;
    $form_state['config_change_type'] = 'create';
    $form_state['h5p_themer_import_candidate'] = $data;
    $form_state['h5p_themer_import_publication'] = $publication['publication']['fingerprint'];
  }
  catch (Throwable $e) {
    form_set_error('import', $e instanceof ConfigException ? $e->getMessage() : t('The configuration could not be validated. Nothing was imported.'));
  }
}

/** Own the complete theme write so lock cleanup and verification surround save. */
function h5p_themer_config_import_single_form_submit(&$form, &$form_state) {
  if (form_get_errors()) { return; }
  if (!isset($form_state['h5p_themer_import_candidate'])) {
    config_import_single_form_submit($form, $form_state);
    return;
  }
  if (!lock_acquire('h5p_themer_admin_write', 120)) {
    form_set_error('import', t('Another H5P theme operation is in progress. Try importing again.'));
    return;
  }
  try {
    $data = $form_state['h5p_themer_import_candidate'];
    if (_h5p_themer_import_decode($form_state) !== $data || $form_state['config_data'] !== $data ||
        $form_state['config_change_type'] !== 'create' || empty($form_state['h5p_themer_import_publication'])) {
      throw new ConfigValidateException(t('The validated import changed. Submit the original definition again.'));
    }
    $publication = _h5p_themer_import_check($data, $form_state['h5p_themer_import_publication']);
    $runtime_before = _h5p_themer_admin_cache_snapshot();
    $fingerprint = _h5p_themer_publication_hide($data['id'], $publication['publication']['fingerprint']);
    _h5p_themer_visibility_lease();
    $checked = _h5p_themer_import_check($data, $fingerprint);
    $checked['record']->save();
    // Native save moves _config_* metadata first. Verify its expected output
    // rather than treating JSON object member order as a value change.
    _h5p_themer_publication_verify_theme($data['id'], $checked['record']->getOriginal());
    _h5p_themer_publication_state($fingerprint);
    // Config::save() clears this object's request caches. Reuse the existing
    // output invalidator: a hidden, unselected creation normally changes none.
    _h5p_themer_admin_cache_invalidate($runtime_before); unset($runtime_before);
    backdrop_set_message(t('Imported theme @label (@id). It is hidden from learners until explicitly made available through H5P theme administration.', array('@label' => $data['label'], '@id' => $data['id'])));
  }
  catch (Throwable $e) {
    $message = isset($runtime_before)
      ? t('The theme import could not be verified. A restrictive visibility entry or theme definition may have been saved. Inspect stored state before retrying; visibility has not been automatically restored.')
      : ($e instanceof ConfigException ? $e->getMessage() : t('The theme import could not be validated. Nothing was imported.'));
    form_set_error('import', $message);
    $form_state['rebuild'] = FALSE;
  }
  finally {
    if (isset($runtime_before)) {
      try { _h5p_themer_admin_cache_invalidate($runtime_before); }
      catch (Throwable $e) { form_set_error('import_cache', t('Output cache invalidation could not be verified. Restrict learner access until a targeted refresh is authorized.')); }
    }
    lock_release('h5p_themer_admin_write');
  }
}
