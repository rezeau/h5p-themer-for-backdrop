<?php
/**
 * @file
 * Explicit native visibility administration; no import coordination.
 * SPDX-License-Identifier: GPL-3.0-or-later.
 */

/** Fresh read-only policy, lifecycle and catalog snapshot for stale-form checks. */
function _h5p_themer_visibility_admin_snapshot() {
  $publication = _h5p_themer_publication_state();
  $catalog = h5p_themer_theme_catalog();
  if ($catalog['errors']) { throw new ConfigException('Catalog unavailable.'); }
  foreach (h5p_themer_visibility_fixed_tokens() as $token) {
    if (!isset($catalog['data'][$token])) { throw new ConfigException('Fixed catalog choice unavailable.'); }
  }
  // A visibility form must not present an overridden catalog as stored truth.
  $names = array_merge(array('h5p_themer.settings'), config_get_names_with_prefix('h5p_themer.theme.'));
  $fields = array('schema_version', 'id', 'label', 'description', 'density', 'colors', 'base_preset', 'default_theme', 'learner_selection_enabled', '_config_static');
  foreach ($names as $name) {
    $record = new Config($name, config_get_config_storage());
    foreach ($fields as $field) {
      if ($record->isOverridden($field)) { throw new ConfigException('Catalog override active.'); }
    }
  }
  // Existing capture includes original definitions (also invalid objects),
  // references, resolved colors and effective settings, without writing them.
  $capture = _h5p_themer_visibility_capture('upgrade');
  $generation = _h5p_themer_visibility_admin_generation();
  return array('fingerprint' => hash('sha256', serialize(array($publication['fingerprint'], $capture['hash'], $generation))),
    'publication' => $publication, 'choices' => $catalog['data'], 'catalog_hash' => $capture['hash'], 'generation' => $generation);
}

/** Environment-local identity marker; never exported with theme configuration. */
function _h5p_themer_visibility_admin_generation() {
  $states = state_initialize();
  if (!is_array($states)) { throw new ConfigException('Unknown administration state.'); }
  $key = 'h5p_themer.visibility_form_generation';
  if (!array_key_exists($key, $states)) { return NULL; }
  if (!is_string($states[$key]) || preg_match('/\A[a-f0-9]{64}\z/', $states[$key]) !== 1) {
    throw new ConfigException('Invalid visibility form generation.');
  }
  return $states[$key];
}

/** Called under the publication lock, before deleting any theme definition. */
function _h5p_themer_visibility_admin_invalidate() {
  $generation = bin2hex(random_bytes(32));
  state_set('h5p_themer.visibility_form_generation', $generation);
  if (_h5p_themer_visibility_admin_generation() !== $generation) { throw new ConfigException('Visibility form invalidation failed.'); }
}

/** Add processed Form API children to their table cells exactly once. */
function h5p_themer_visibility_table_pre_render($table) {
  foreach ($table['#visibility_rows'] as $key => $row) {
    $table['#rows'][$row][] = array('data' => $table[$key]);
    unset($table[$key]);
  }
  $table['#visibility_rows'] = array();
  return $table;
}

/** Decorate the existing overview without reordering its names or operations. */
function _h5p_themer_visibility_admin_build(array $build, &$form_state, array $overview_choices) {
  if (!_h5p_themer_admin_cached_form($form_state, 'h5p_themer_visibility_snapshot',
      _h5p_themer_visibility_context_valid(isset($form_state['h5p_themer_visibility_snapshot']) ? $form_state['h5p_themer_visibility_snapshot'] : NULL))) {
    return _h5p_themer_admin_expired_form();
  }
  $errors = array();
  try {
    $current = _h5p_themer_visibility_admin_snapshot();
    if ($overview_choices !== _h5p_themer_admin_default_choices()) { throw new ConfigException('Catalog changed during form build.'); }
    if (!array_key_exists('h5p_themer_visibility_snapshot', $form_state)) {
      $form_state['h5p_themer_visibility_snapshot'] = $current;
    }
    if ($form_state['h5p_themer_visibility_snapshot'] === NULL ||
        $form_state['h5p_themer_visibility_snapshot']['fingerprint'] !== $current['fingerprint']) {
      throw new ConfigException('Stale visibility form.');
    }
  }
  catch (Throwable $e) {
    if (!array_key_exists('h5p_themer_visibility_snapshot', $form_state)) { $form_state['h5p_themer_visibility_snapshot'] = NULL; }
    $errors[] = t('Visibility is read-only: verify activation, policy and catalog integrity, remove conflicting overrides or synchronization, then reload this page.');
  }
  $policy = h5p_themer_visibility_policy_load();
  $build['visibility_help'] = array('#markup' => '<p>' . t('Available to learners controls explicit choices only. Hidden themes remain defined, editable and previewable. A hidden global theme still applies through Site default. Checkbox changes are saved only with Save visibility.') . '</p>', '#weight' => -1);
  $build['site_default'] = array('#theme' => 'table', '#weight' => -0.5,
    '#header' => array(t('Theme'), t('Description')),
    '#rows' => array(array(t('Site default'), t('Uses the effective global theme, including when that theme is hidden as an explicit learner choice.'))));
  $tables = array('site_default' => array('site_default'), 'core' => array('core'), 'builtins' => array(), 'themes' => array());
  $presets = array('data' => $overview_choices['presets']);
  foreach ($presets['data'] as $id => $preset) { $tables['builtins'][] = 'builtin:' . $id; }
  $collection = $overview_choices['collection'];
  foreach ($collection['themes'] as $id => $theme) { $tables['themes'][] = 'custom:' . $id; }
  foreach ($tables as $name => $tokens) {
    $build[$name]['#header'][] = t('Available to learners');
    $build[$name]['#pre_render'][] = 'h5p_themer_visibility_table_pre_render';
    $build[$name]['#visibility_rows'] = array();
    foreach ($tokens as $index => $token) {
      $fixed = in_array($token, h5p_themer_visibility_fixed_tokens(), TRUE);
      $usable = isset($current['choices'][$token]);
      $label = $fixed && $token === 'site_default' ? t('Site default') : ($token === 'core' ? t('H5P Core defaults') : ($name === 'builtins' ? $presets['data'][substr($token, 8)]['label'] : $collection['themes'][substr($token, 7)]['label']));
      if (!$fixed && !$usable && !$errors) { $build[$name]['#rows'][$index][] = t('Unavailable: theme cannot be resolved.'); continue; }
      $key = 'visibility_' . $index;
      $build[$name][$key] = array('#type' => 'checkbox',
        '#title' => t('Available to learners: @theme (@identity)', array('@theme' => $label, '@identity' => $token)),
        '#parents' => array('visibility', $token), '#return_value' => 1, '#title_display' => 'invisible',
        '#default_value' => $fixed || ($policy['state'] === 'initialized' && isset($policy['data']['themes'][$token]) && $policy['data']['themes'][$token] === TRUE) ? 1 : 0,
        '#disabled' => $fixed || (bool) $errors || !$usable,
        '#description' => $fixed ? t('Always available.') : (!$usable ? t('This theme is not currently resolvable for learners.') : ''));
      $build[$name]['#visibility_rows'][$key] = $index;
    }
    // Diagnostic rows remain intact and cannot become publication controls.
    for ($i = count($tokens); $i < count($build[$name]['#rows']); $i++) { $build[$name]['#rows'][$i][] = t('Unavailable: invalid theme.'); }
  }
  if ($errors) { $build['visibility_diagnostics'] = array('#markup' => '<p role="alert">' . implode(' ', $errors) . '</p>'); }
  $build['actions'] = array('#type' => 'actions', '#weight' => 20);
  $build['actions']['submit'] = array('#type' => 'submit', '#value' => t('Save visibility'), '#disabled' => (bool) $errors);
  $build['#method'] = 'post';
  $build['#process'][] = 'h5p_themer_visibility_form_process';
  $build['#validate'] = array('h5p_themer_visibility_form_validate');
  $build['#submit'] = array('h5p_themer_visibility_form_submit');
  return $build;
}

/** No cached form may validate using absent or malformed trusted state. */
function _h5p_themer_visibility_context_valid($snapshot) {
  return is_array($snapshot) && isset($snapshot['fingerprint'], $snapshot['publication'], $snapshot['choices'], $snapshot['catalog_hash']) &&
    array_key_exists('generation', $snapshot) && ($snapshot['generation'] === NULL ||
      (is_string($snapshot['generation']) && preg_match('/\A[a-f0-9]{64}\z/', $snapshot['generation']) === 1)) &&
    is_string($snapshot['fingerprint']) && preg_match('/\A[a-f0-9]{64}\z/', $snapshot['fingerprint']) === 1 &&
    is_string($snapshot['catalog_hash']) && preg_match('/\A[a-f0-9]{64}\z/', $snapshot['catalog_hash']) === 1 &&
    is_array($snapshot['publication']) && is_array($snapshot['choices']);
}

/** Keep malformed scalar groups from failing inside Core before validation. */
function h5p_themer_visibility_form_process($element, &$form_state) {
  // This flag describes only the current request, not a previous failed POST.
  unset($form_state['h5p_themer_visibility_bad_input']);
  if (isset($form_state['input']) && is_array($form_state['input']) && array_key_exists('visibility', $form_state['input']) && !is_array($form_state['input']['visibility'])) {
    $form_state['h5p_themer_visibility_bad_input'] = TRUE;
    $form_state['input']['visibility'] = array();
  }
  return $element;
}

/** Reject forged raw values even if native checkbox processing normalizes them. */
function h5p_themer_visibility_form_validate($form, &$form_state) {
  unset($form_state['h5p_themer_visibility_candidate']);
  try {
    if (!empty($form_state['h5p_themer_visibility_bad_input'])) { throw new ConfigException('Malformed checkbox group.'); }
    $snapshot = isset($form_state['h5p_themer_visibility_snapshot']) ? $form_state['h5p_themer_visibility_snapshot'] : NULL;
    if (!_h5p_themer_visibility_context_valid($snapshot)) { throw new ConfigException('Missing or invalid form context.'); }
    $current = _h5p_themer_visibility_admin_snapshot();
    if ($snapshot !== $current) { throw new ConfigException('Stale or unverified form.'); }
    $input = isset($form_state['input']) ? $form_state['input'] : array();
    if (!is_array($input) || array_diff(array_keys($input), array('visibility', 'form_id', 'form_build_id', 'form_token', 'op'))) { throw new ConfigException('Unexpected form input.'); }
    $raw = array_key_exists('visibility', $input) ? $input['visibility'] : array();
    $values = isset($form_state['values']['visibility']) ? $form_state['values']['visibility'] : array();
    if (!is_array($raw) || !is_array($values) || array_diff(array_keys($raw), array_keys($snapshot['choices'])) ||
        array_diff(array_keys($values), array_keys($snapshot['choices']))) { throw new ConfigException('Unexpected visibility tokens.'); }
    $candidate = $current['publication']['policy'];
    foreach ($snapshot['choices'] as $token => $entry) {
      // Native Form API inserts NULL placeholders for unchecked/disabled fields.
      $submitted = isset($raw[$token]) ? $raw[$token] : NULL;
      $fixed = in_array($token, h5p_themer_visibility_fixed_tokens(), TRUE);
      if (($submitted !== NULL && !in_array($submitted, array('1', 1), TRUE)) ||
          !isset($values[$token]) || !in_array($values[$token], array(0, 1), TRUE) ||
          $values[$token] !== ($fixed || $submitted !== NULL ? 1 : 0)) { throw new ConfigException('Invalid checkbox value.'); }
      if (!$fixed && ($values[$token] === 1 || array_key_exists($token, $candidate['themes']))) { $candidate['themes'][$token] = $values[$token] === 1; }
    }
    $record = new Config('h5p_themer.learner_visibility', config_get_config_storage());
    $record->setData($candidate); $record->validateData();
    $form_state['h5p_themer_visibility_candidate'] = $candidate;
  }
  catch (Throwable $e) {
    form_set_error('_form', t('Visibility could not be validated. Verify activation and overrides, reload stale forms, and submit only the displayed checkbox choices. Nothing was saved by this validation.'));
  }
}

/** One explicit, locked policy write, followed by native storage read-back. */
function h5p_themer_visibility_form_submit($form, &$form_state) {
  if (form_get_errors()) { return; }
  if (!lock_acquire('h5p_themer_admin_write', 120)) {
    form_set_error('_form', t('Another H5P theme operation is in progress. Try again.'));
    $form_state['rebuild'] = FALSE; return;
  }
  try {
    h5p_themer_visibility_form_validate($form, $form_state);
    if (form_get_errors()) { $form_state['rebuild'] = FALSE; return; }
    $current = _h5p_themer_visibility_admin_snapshot();
    $candidate = $form_state['h5p_themer_visibility_candidate'];
    if ($candidate === $current['publication']['policy']) {
      backdrop_set_message(t('Visibility is unchanged. No configuration was written.'));
      $form_state['redirect'] = 'admin/config/content/h5p-themer'; return;
    }
    $runtime_before = _h5p_themer_admin_cache_snapshot();
    _h5p_themer_visibility_lease();
    $before = _h5p_themer_visibility_admin_snapshot();
    if ($before['fingerprint'] !== $form_state['h5p_themer_visibility_snapshot']['fingerprint']) { throw new ConfigException('Concurrent catalog/policy change.'); }
    $record = new Config('h5p_themer.learner_visibility', config_get_config_storage());
    $record->setData($candidate); $record->validateData();
    if ($record->getStorage()->write($record->getName(), $candidate) !== TRUE) { throw new ConfigException('Policy write failed.'); }
    $after = _h5p_themer_visibility_admin_snapshot();
    if ($after['publication']['policy'] !== $candidate ||
        $after['publication']['lifecycle'] !== $before['publication']['lifecycle'] ||
        $after['choices'] !== $before['choices'] ||
        $after['catalog_hash'] !== $before['catalog_hash']) {
      throw new ConfigException('Policy read-back failed.');
    }
    _h5p_themer_admin_cache_invalidate($runtime_before); unset($runtime_before);
    backdrop_set_message(t('Learner theme visibility was saved. Hidden global themes still apply through Site default.'));
    $form_state['redirect'] = 'admin/config/content/h5p-themer';
  }
  catch (Throwable $e) {
    form_set_error('_form', t('The visibility save could not be verified. Policy data may have been written; no automatic visibility restoration was attempted. Inspect activation diagnostics and stored policy before retrying.'));
    $form_state['rebuild'] = FALSE;
  }
  finally {
    if (isset($runtime_before)) {
      try { _h5p_themer_admin_cache_invalidate($runtime_before); }
      catch (Throwable $e) { form_set_error('_form', t('Learner output cache invalidation could not be verified. Restrict learner access until a targeted refresh is authorized.')); $form_state['rebuild'] = FALSE; }
    }
    lock_release('h5p_themer_admin_write');
  }
}
