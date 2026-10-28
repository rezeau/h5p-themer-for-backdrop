<?php
/**
 * @file
 * Native theme administration. SPDX-License-Identifier: GPL-2.0-or-later.
 */

require_once __DIR__ . '/h5p_themer.preview.inc';
require_once __DIR__ . '/h5p_themer.visibility-publication.inc';
require_once __DIR__ . '/h5p_themer.visibility-admin.inc';

/**
 * Reads stored definitions in predictable label/ID order, including diagnostics.
 */
function _h5p_themer_admin_collection() {
  $result = array('themes' => array(), 'invalid' => array(), 'errors' => array());
  try {
    foreach (config_get_names_with_prefix('h5p_themer.theme.') as $name) {
      $theme = h5p_themer_config_load($name, 'active', TRUE);
      if ($theme['errors']) {
        $result['invalid'][$name] = $theme['errors'];
      }
      else {
        $result['themes'][$theme['data']['id']] = $theme['data'];
      }
    }
  }
  catch (ConfigException $e) {
    $result['errors'][] = t('The stored H5P theme collection could not be read.');
  }
  uasort($result['themes'], function ($a, $b) {
    $order = strnatcasecmp($a['label'], $b['label']);
    return $order ? $order : strcmp($a['id'], $b['id']);
  });
  ksort($result['invalid']);
  return $result;
}

/**
 * Administrative overview. Never repairs configuration as a side effect.
 */
function h5p_themer_overview($form = array(), &$form_state = NULL) {
  if ($form_state === NULL) { $form_state = array(); }
  if (!h5p_themer_admin_access()) {
    return array('#markup' => t('Access denied.'));
  }
  $base = 'admin/config/content/h5p-themer';
  $compatibility = h5p_themer_compatibility();
  $choices = _h5p_themer_admin_default_choices();
  $settings = h5p_themer_config_load('h5p_themer.settings', 'active', TRUE);
  $effective = h5p_themer_config_load('h5p_themer.settings');
  $default = h5p_themer_default_selection_load();
  $visibility = h5p_themer_visibility_policy_load();
  $warnings = array_merge($choices['errors'], $settings['errors'], $effective['errors'], $default['errors'], $visibility['errors']);
  $stored_token = $settings['errors'] ? NULL : _h5p_themer_admin_selection_token($settings['data'], $choices['references']);
  $effective_token = $effective['errors'] || $default['errors'] ? NULL : _h5p_themer_admin_selection_token($effective['data'], $choices['references']);
  if (!$settings['errors'] && !$effective['errors'] && $stored_token !== $effective_token) {
    $warnings[] = t('The effective default differs from the stored selection because of configuration overrides. These forms manage stored configuration.');
  }
  if (!$compatibility['compatible']) {
    $warnings[] = $compatibility['message'] . ' ' . t('Theme management is read-only until compatibility is restored.');
  }
  $build['help'] = array('#markup' => '<p>' . t('Manage stored theme definitions and global colors for compatible H5P playback. Optional native Density is disabled by default and supports only qualified top-level H5P.TrueFalse 1.8.24 and H5P.MultiChoice 1.16.27 iframe players on H5P Core API 1.28. Other players retain their original Density.') . '</p>');
  if ($warnings) {
    $build['warnings'] = array('#markup' => '<p role="alert">' . implode(' ', array_unique($warnings)) . '</p>');
  }
  $build['default_link'] = array('#type' => 'link', '#title' => t('Select or clear the Global Default'), '#href' => $base . '/default');
  $build['core_heading'] = array('#markup' => '<h2>' . t('H5P Core defaults') . '</h2>');
  $build['core'] = array('#theme' => 'table',
    '#header' => array(t('Theme'), t('Description'), t('Stored Global Default'), t('Effective Global Default')),
    '#rows' => array(array(t('H5P Core defaults'), t('Original H5P colors without visual theme overrides. Density is an independent learner choice. Distinct from Daylight.'),
      $stored_token === 'core' ? t('Yes') : t('No'), $effective_token === 'core' ? t('Yes') : t('No'))));
  $build['builtins_heading'] = array('#markup' => '<h2>' . t('Built-in themes') . '</h2>');
  $rows = array();
  foreach ($choices['presets'] as $id => $preset) {
    $path = $base . '/presets/' . $id . '/' . $preset['version'];
    $links = array('view' => array('title' => t('View'), 'href' => $path));
    if ($compatibility['compatible']) {
      $links['duplicate'] = array('title' => t('Duplicate'), 'href' => $path . '/duplicate');
    }
    $token = 'builtin:' . $id;
    $rows[] = array(check_plain($preset['label']), check_plain($preset['version']),
      check_plain(_h5p_themer_admin_preset_description($id)),
      $stored_token === $token ? t('Yes') : t('No'), $effective_token === $token ? t('Yes') : t('No'),
      array('data' => array('#type' => 'operations', '#links' => $links)));
  }
  $build['builtins'] = array('#theme' => 'table',
    '#header' => array(t('Theme'), t('Catalog version'), t('Description'), t('Stored Global Default'), t('Effective Global Default'), t('Operations')),
    '#rows' => $rows);
  $build['custom_heading'] = array('#markup' => '<h2>' . t('Custom themes') . '</h2>');
  $rows = array();
  foreach ($choices['collection']['themes'] as $id => $theme) {
    $links = array('edit' => array('title' => t('Edit'), 'href' => $base . '/' . $id . '/edit'));
    if ($compatibility['compatible']) {
      $links['duplicate'] = array('title' => t('Duplicate'), 'href' => $base . '/' . $id . '/duplicate');
      $links['delete'] = array('title' => t('Delete'), 'href' => $base . '/' . $id . '/delete');
    }
    $token = 'custom:' . $id;
    $rows[] = array(check_plain($theme['label']), check_plain($id),
      $stored_token === $token ? t('Yes') : t('No'), $effective_token === $token ? t('Yes') : t('No'),
      array('data' => array('#type' => 'operations', '#links' => $links)));
  }
  foreach ($choices['collection']['invalid'] as $name => $errors) {
    $rows[] = array(t('Invalid theme definition'), check_plain(substr($name, strlen('h5p_themer.theme.'))), t('Unknown'), t('Unknown'), implode(' ', $errors));
  }
  $build['themes'] = array('#theme' => 'table',
    '#header' => array(t('Theme'), t('Machine ID'), t('Stored Global Default'), t('Effective Global Default'), t('Operations')),
    '#rows' => $rows, '#empty' => t('No custom themes have been saved. Use Add theme or duplicate a built-in.'));
  return _h5p_themer_visibility_admin_build($build, $form_state, $choices);
}

/**
 * Logical groups for exactly the Phase 1A color allowlist.
 */
function _h5p_themer_admin_color_groups() {
  return array(
    'main' => array('title' => t('Main controls'), 'fields' => array(
      'main-cta-base' => t('Main control base'), 'main-cta-light' => t('Main control light'), 'main-cta-dark' => t('Main control dark'),
      'contrast-cta' => t('Main control contrast'), 'contrast-cta-white' => t('Contrast on white'), 'contrast-cta-light' => t('Light contrast surface'), 'focus' => t('Focus (not currently used by Core)'),
    )),
    'secondary' => array('title' => t('Secondary controls'), 'fields' => array(
      'secondary-cta-base' => t('Secondary control base'), 'secondary-cta-light' => t('Secondary control light'), 'secondary-cta-dark' => t('Secondary control dark'),
      'secondary-contrast-cta' => t('Secondary control contrast'), 'secondary-contrast-cta-hover' => t('Secondary contrast on hover'),
    )),
    'surfaces' => array('title' => t('Background and surfaces'), 'fields' => array(
      'background' => t('Content background'), 'ui-base' => t('Interface base'), 'alternative-base' => t('Alternative base'),
      'alternative-light' => t('Alternative light'), 'alternative-dark' => t('Alternative dark'), 'alternative-darker' => t('Alternative darker'),
    )),
    'text' => array('title' => t('Text and borders'), 'fields' => array(
      'text-primary' => t('Primary text'), 'text-secondary' => t('Secondary text'), 'text-third' => t('Third text'),
      'stroke-1' => t('Border 1'), 'stroke-2' => t('Border 2'), 'stroke-3' => t('Border 3'),
    )),
    'feedback' => array('title' => t('Feedback colors'), 'fields' => array(
      'feedback-correct-main' => t('Correct: main'), 'feedback-correct-secondary' => t('Correct: secondary'), 'feedback-correct-third' => t('Correct: third'),
      'feedback-incorrect-main' => t('Incorrect: main'), 'feedback-incorrect-secondary' => t('Incorrect: secondary'), 'feedback-incorrect-third' => t('Incorrect: third'),
      'feedback-neutral-main' => t('Neutral: main'), 'feedback-neutral-secondary' => t('Neutral: secondary'), 'feedback-neutral-third' => t('Neutral: third'),
    )),
  );
}

/**
 * Fresh original data for a trusted object name; no environment overrides saved.
 */
function _h5p_themer_admin_original($name) {
  try {
    config($name)->load();
  }
  catch (ConfigException $e) {
    return array('data' => NULL, 'errors' => array(t('The stored configuration could not be read.')));
  }
  catch (TypeError $e) {
    return array('data' => NULL, 'errors' => array(t('The stored configuration is malformed.')));
  }
  return h5p_themer_config_load($name, 'active', TRUE);
}

/**
 * Disallows mutation when access/compatibility changes, including after build.
 */
function _h5p_themer_admin_guard() {
  if (!h5p_themer_admin_access()) {
    return array(t('You do not have permission to administer H5P themes.'));
  }
  $compatibility = h5p_themer_compatibility();
  if (!$compatibility['compatible']) { return array($compatibility['message'] . ' ' . t('Theme management is read-only.')); }
  return array();
}

/**
 * Detects stale forms and prevents editing configuration with active overrides.
 */
function _h5p_themer_admin_check_original($name, array $original, $overrides = TRUE) {
  $current = _h5p_themer_admin_original($name);
  if ($current['errors'] || $current['data'] !== $original) {
    return array(t('This configuration changed or disappeared. Reload the form before saving.'));
  }
  if ($overrides) {
    foreach (array_unique(array_merge(array_keys($original), array('description', 'default_theme', 'colors', 'base_preset', 'learner_selection_enabled', 'native_density_enabled'))) as $key) {
      if (config($name)->isOverridden($key)) {
        return array(t('This configuration has settings.php overrides. Remove those overrides before changing it here.'));
      }
    }
  }
  return array();
}

/**
 * Common form actions and read-only diagnostics. Native Form API supplies CSRF.
 */
function _h5p_themer_admin_actions(array $form, $submit_label, array $extra_errors = array()) {
  $errors = array_merge(_h5p_themer_admin_guard(), $extra_errors);
  if ($errors) {
    $form['diagnostics'] = array('#markup' => '<p role="alert">' . implode(' ', $errors) . '</p>');
    foreach (array('general', 'id', 'label', 'description', 'colors', 'default_theme', 'learner_selection_enabled', 'native_density_enabled') as $key) {
      if (isset($form[$key])) {
        $form[$key]['#disabled'] = TRUE;
      }
    }
  }
  $form['#method'] = 'post';
  $form['actions'] = array('#type' => 'actions');
  $form['actions']['submit'] = array('#type' => 'submit', '#value' => $submit_label, '#disabled' => (bool) $errors);
  $form['actions']['cancel'] = array('#type' => 'link', '#title' => t('Cancel'), '#href' => 'admin/config/content/h5p-themer');
  return $form;
}

/**
 * Fresh effective input plus a dependency fingerprint, including invalid data.
 *
 * Invalid defaults may seed Daylight, but must not silently become a different
 * default during a cached form's lifetime. Never persist environment metadata.
 */
function _h5p_themer_admin_add_read($name) {
  try {
    $record = config($name);
    $record->load();
    $effective = array();
    $keys = $name === 'h5p_themer.settings'
      ? array('schema_version', 'default_theme')
      : array('schema_version', 'id', 'label', 'density', 'colors', 'base_preset');
    foreach ($keys as $key) {
      $effective[$key] = $record->get($key);
    }
    $loaded = h5p_themer_config_load($name);
    return array('loaded' => $loaded,
      'fingerprint' => hash('sha256', serialize(array($record->isNew(), $effective, (bool) $loaded['errors']))));
  }
  catch (ConfigException $e) {
    return array('loaded' => array('data' => NULL, 'errors' => array(t('The starting configuration could not be read.'))), 'fingerprint' => 'unreadable');
  }
  catch (TypeError $e) {
    return array('loaded' => array('data' => NULL, 'errors' => array(t('The starting configuration is malformed.'))), 'fingerprint' => 'malformed');
  }
}

/**
 * Captures an Add-only independent seed using the existing definition schemas.
 *
 * Resolved colors are display data only. The seed copies validated hex overrides
 * and retains the exact preset pin, never flattening trusted CSS expressions.
 */
function _h5p_themer_admin_add_snapshot() {
  $settings = _h5p_themer_admin_add_read('h5p_themer.settings');
  $dependencies = array($settings['fingerprint']);
  $selection = $settings['loaded'];
  $definition = NULL;
  $resolved = array('data' => NULL, 'errors' => array());
  if (!$selection['errors']) {
    $reference = h5p_themer_selection_reference($selection['data']['default_theme'], $selection['data']['schema_version']);
    if (!$reference['errors'] && $reference['data']['kind'] === 'custom') {
      $theme = _h5p_themer_admin_add_read('h5p_themer.theme.' . $reference['data']['id']);
      $dependencies[] = $theme['fingerprint'];
      $definition = $theme['loaded']['data'];
      $resolved = $theme['loaded']['errors'] ? $theme['loaded'] : h5p_themer_selection_resolve($selection['data']['default_theme'], $selection['data']['schema_version']);
    }
    else {
      $resolved = h5p_themer_selection_resolve($selection['data']['default_theme'], $selection['data']['schema_version']);
    }
  }
  $fallback = $resolved['errors'] || $resolved['data'] === NULL || $resolved['data']['kind'] === 'core';
  if ($fallback) {
    $resolved = h5p_themer_selection_resolve(array('kind' => 'builtin', 'id' => 'daylight', 'version' => 'moodle-478fed30d2a4'));
  }
  if ($resolved['errors']) {
    throw new ConfigException('The pinned starting preset is unavailable.');
  }
  $palette = $resolved['data'];
  $seed = array('schema_version' => 2, 'label' => $palette['label'], 'description' => '', 'density' => 'large', 'colors' => array());
  if ($palette['kind'] === 'custom') {
    foreach (array('schema_version', 'density', 'colors', 'base_preset') as $key) {
      if (array_key_exists($key, $definition)) { $seed[$key] = $definition[$key]; }
    }
  }
  else {
    $seed['base_preset'] = array('id' => $palette['id'], 'version' => $palette['version']);
  }
  $preset = isset($seed['base_preset']) ? h5p_themer_preset_load($seed['base_preset']['id'], $seed['base_preset']['version'])['data'] : NULL;
  return array('seed' => $seed, 'palette' => $palette, 'preset' => $preset, 'fallback' => $fallback,
    'fingerprint' => hash('sha256', serialize(array($dependencies, $seed, $palette, $preset, $fallback))));
}

/** Validates cached Add context without replacing it or reading a new source. */
function _h5p_themer_admin_add_context_valid($context) {
  if (!is_array($context) || !isset($context['operation'], $context['original'], $context['add_snapshot']) || $context['operation'] !== 'add') { return FALSE; }
  $snapshot = $context['add_snapshot'];
  if (!is_array($snapshot) || !isset($snapshot['seed'], $snapshot['palette'], $snapshot['fingerprint']) ||
      !is_array($snapshot['seed']) || $context['original'] !== $snapshot['seed'] ||
      !is_string($snapshot['fingerprint']) || preg_match('/\A[a-f0-9]{64}\z/', $snapshot['fingerprint']) !== 1 ||
      !isset($snapshot['palette']['label'], $snapshot['palette']['colors']) ||
      !is_string($snapshot['palette']['label']) || !is_array($snapshot['palette']['colors']) ||
      !array_key_exists('preset', $snapshot) || !array_key_exists('fallback', $snapshot) || !is_bool($snapshot['fallback'])) { return FALSE; }
  $definition = $snapshot['seed'];
  $definition['id'] = 'add_snapshot';
  if (h5p_themer_config_errors('h5p_themer.theme.add_snapshot', $definition)) { return FALSE; }
  $preset = NULL; $colors = $definition['colors'];
  if ($definition['schema_version'] === 2) {
    $preset = h5p_themer_preset_load($definition['base_preset']['id'], $definition['base_preset']['version'])['data'];
    $colors = array_replace($preset['colors'], $colors);
  }
  return $snapshot['preset'] === $preset && $snapshot['palette']['colors'] === $colors && $snapshot['palette']['label'] === $definition['label'];
}

/** A cache miss on POST must never initialize a replacement source snapshot. */
function _h5p_themer_admin_add_expired_validate($form, &$form_state) {
  form_set_error('_form', t('The original Add theme form is missing, expired or invalid. Reload Add theme before saving.'));
}

/**
 * Persist trusted originals in native session-bound Form API tempstore.
 * A cache miss must never turn POST (or a rebuild) into a new initial capture.
 * Submit errors keep rebuild FALSE: Core skips rebuilding forms with errors,
 * and otherwise would discard the submitted cache without storing a correction.
 */
function _h5p_themer_admin_cached_form(&$form_state, $key, $valid) {
  $form_state['cache'] = TRUE;
  $present = array_key_exists($key, $form_state);
  $submission = !empty($form_state['input']) || !empty($form_state['programmed']) || !empty($form_state['rebuild']) ||
    (isset($_SERVER['REQUEST_METHOD']) && !in_array($_SERVER['REQUEST_METHOD'], array('GET', 'HEAD'), TRUE));
  if (($present && !$valid) || (!$present && $submission)) {
    $form_state['cache'] = FALSE;
    $form_state['no_cache'] = TRUE;
    return FALSE;
  }
  return TRUE;
}

/** Invalid/missing cached context is terminal until a new initial GET. */
function _h5p_themer_admin_expired_form() {
  return array('#markup' => '<p role="alert">' . t('The original administration form is missing, expired or invalid. Reload the page before saving.') . '</p>',
    '#validate' => array('_h5p_themer_admin_expired_validate'), '#submit' => array());
}

function _h5p_themer_admin_expired_validate($form, &$form_state) {
  form_set_error('_form', t('The original administration form is missing, expired or invalid. Reload the page before saving.'));
}

/** Validate the cached shape before accessing it, including on cache-hit POSTs. */
function _h5p_themer_admin_theme_context_valid($context, $operation) {
  if (!is_array($context) || !isset($context['id'], $context['original']) ||
      !h5p_themer_valid_theme_id($context['id']) || !is_array($context['original'])) { return FALSE; }
  $original = $context['original'];
  if ($operation !== 'delete' && (!isset($context['operation']) || $context['operation'] !== $operation ||
      !in_array($operation, array('edit', 'duplicate', 'preset_duplicate'), TRUE))) { return FALSE; }
  if ($operation === 'preset_duplicate') {
    if (!isset($context['preset'], $original['base_preset']['id'], $original['base_preset']['version']) ||
        $original['base_preset']['id'] !== $context['id']) { return FALSE; }
    $preset = h5p_themer_preset_load($context['id'], $original['base_preset']['version']);
    if ($preset['errors'] || $preset['data'] !== $context['preset']) { return FALSE; }
    $original['id'] = $context['id'];
  }
  return !h5p_themer_config_errors('h5p_themer.theme.' . $context['id'], $original);
}

function _h5p_themer_admin_settings_context_valid($context) {
  return is_array($context) && !h5p_themer_config_errors('h5p_themer.settings', $context);
}

/** Recheck before validation and immediately before persistence under the lock. */
function _h5p_themer_admin_add_check(array $context) {
  try {
    if (_h5p_themer_admin_add_context_valid($context) && $context['add_snapshot'] === _h5p_themer_admin_add_snapshot()) {
      return array();
    }
  }
  catch (Throwable $e) { /* An unavailable dependency cannot validate a copy. */ }
  return array(t('The global default or starting theme changed or could not be verified. Reload Add theme before saving.'));
}

/** Protect unresolved stored and effective defaults on every creation path. */
function _h5p_themer_admin_creation_default_errors($id) {
  try {
    $record = config('h5p_themer.settings');
    $record->load();
    if ($record->isNew()) { return array(); }
    $stored = $record->getOriginal();
    $effective = array('schema_version' => $record->get('schema_version'), 'default_theme' => $record->get('default_theme'));
    foreach (array($stored, $effective) as $settings) {
      if (!is_array($settings) || !isset($settings['schema_version']) || !array_key_exists('default_theme', $settings)) { continue; }
      $reference = h5p_themer_selection_reference($settings['default_theme'], $settings['schema_version']);
      if (!$reference['errors'] && $reference['data']['kind'] === 'custom' && $reference['data']['id'] === $id) {
        return array(t('This machine ID is already referenced by the stored or effective global default. Choose another ID; repair the global default separately to avoid activating this new theme automatically.'));
      }
    }
    return array();
  }
  catch (TypeError $e) {
    // A non-object settings record cannot contain a resolvable reference.
    return array();
  }
  catch (Throwable $e) {
    return array(t('Global default references could not be verified. Reload before creating a theme.'));
  }
}

/**
 * Shared create/edit/duplicate form. Rename means editing only the label.
 */
function h5p_themer_theme_form($form, &$form_state, $operation = 'add', $id = NULL, $version = NULL) {
  if (!h5p_themer_admin_access()) {
    return array('#markup' => t('Access denied.'));
  }
  $add_request = isset($form_state['h5p_themer']['operation']) ? $form_state['h5p_themer']['operation'] === 'add' : $operation === 'add';
  if ($add_request) {
    // Native Form API caches the unprocessed form and trusted state in tempstore,
    // with session-token access checks and the configured form-cache expiration.
    $form_state['cache'] = TRUE;
    $has_context = array_key_exists('h5p_themer', $form_state);
    $submission = !empty($form_state['input']) || !empty($form_state['programmed']) || !empty($form_state['rebuild']) ||
      (isset($_SERVER['REQUEST_METHOD']) && !in_array($_SERVER['REQUEST_METHOD'], array('GET', 'HEAD'), TRUE));
    if (($has_context && !_h5p_themer_admin_add_context_valid($form_state['h5p_themer'])) || (!$has_context && $submission)) {
      $form_state['cache'] = FALSE;
      $form_state['no_cache'] = TRUE;
      return array('#markup' => '<p role="alert">' . t('The original Add theme form is missing, expired or invalid. Reload Add theme before saving.') . '</p>',
        '#validate' => array('_h5p_themer_admin_add_expired_validate'), '#submit' => array());
    }
  }
  else {
    $context = isset($form_state['h5p_themer']) ? $form_state['h5p_themer'] : NULL;
    // Rebuilds use trusted cached context, never submitted operation/source data.
    $cached_operation = isset($context['operation']) ? $context['operation'] : $operation;
    if (!_h5p_themer_admin_cached_form($form_state, 'h5p_themer', _h5p_themer_admin_theme_context_valid($context, $cached_operation))) {
      return _h5p_themer_admin_expired_form();
    }
  }
  if (!isset($form_state['h5p_themer'])) {
    if (!in_array($operation, array('add', 'edit', 'duplicate', 'preset_duplicate'), TRUE) ||
        ($operation !== 'add' && !h5p_themer_valid_theme_id($id))) {
      return array('#markup' => t('Invalid theme request.'));
    }
    if ($operation === 'preset_duplicate') {
      $preset = h5p_themer_preset_load($id, $version);
      if ($preset['errors']) {
        return array('#markup' => '<p>' . implode(' ', $preset['errors']) . '</p>');
      }
      $source = array('data' => array('schema_version' => 2, 'label' => $preset['data']['label'],
        'description' => '', 'density' => 'large', 'base_preset' => array('id' => $id, 'version' => $version), 'colors' => array()), 'errors' => array());
    }
    elseif ($operation === 'add') {
      try {
        $snapshot = _h5p_themer_admin_add_snapshot();
        $source = array('data' => $snapshot['seed'], 'errors' => array());
      }
      catch (Throwable $e) {
        return array('#markup' => '<p role="alert">' . t('The starting theme could not be loaded. No changes were made.') . '</p>');
      }
    }
    else {
      $source = _h5p_themer_admin_original('h5p_themer.theme.' . $id);
    }
    if ($source['errors']) {
      return array('#markup' => '<p>' . t('The theme is missing or invalid. No changes were made.') . ' ' . implode(' ', $source['errors']) . '</p>');
    }
    $form_state['h5p_themer'] = array('operation' => $operation, 'id' => $id, 'original' => $source['data']);
    if ($operation === 'add') {
      $form_state['h5p_themer']['add_snapshot'] = $snapshot;
    }
    if ($operation === 'preset_duplicate') {
      $form_state['h5p_themer']['preset'] = $preset['data'];
    }
  }
  $context = $form_state['h5p_themer'];
  $source = $context['original'];
  $editing = $context['operation'] === 'edit';
  $adding = $context['operation'] === 'add';
  $preset_copy = $context['operation'] === 'preset_duplicate';
  $inherited = $adding ? $context['add_snapshot']['preset'] : (isset($source['schema_version']) && $source['schema_version'] === 2
    ? h5p_themer_preset_load($source['base_preset']['id'], $source['base_preset']['version'])['data'] : NULL);
  $form['help'] = array('#markup' => '<p>' . t('Blank color fields mean no override. Enter #RGB or #RRGGBB for an explicit color. Colors are independent: no derived palette or accessibility certification is generated. Global colors apply to compatible H5P playback. Visual themes supply colors only. The representative preview shows colors only.') . '</p>');
  if ($adding) {
    $form['help']['#markup'] = '<p><strong>' . t('Starting theme:') . '</strong> ' . check_plain($context['add_snapshot']['palette']['label']) . '. ' .
      ($context['add_snapshot']['fallback'] ? t('Daylight fallback is used because the global default is Core, unset, missing, invalid or unresolvable.') . ' ' : '') .
      t('The new theme starts from these captured colors. Blank fields retain the starting colors. Enter #RGB or #RRGGBB, or use an available color picker, to override a color. Save creates an independent custom theme and does not modify its source or the global default. Later changes to the source custom theme do not affect this copy. Visual themes supply colors only. The representative preview shows colors only.') . '</p>';
  }
  elseif ($editing) {
    $form['help']['#markup'] .= '<p>' . t('Fields contain saved overrides. Clearing an override restores the pinned preset color, or the Core fallback for a theme without a pinned preset. A previous custom copy source is not recorded; no live inheritance from another custom theme is implied.') . '</p>';
  }
  $form['id'] = array('#type' => 'textfield', '#title' => t('Machine ID'), '#required' => TRUE, '#maxlength' => 64,
    '#default_value' => $editing ? $context['id'] : '', '#disabled' => $editing,
    '#description' => t('Start with a lowercase letter; use lowercase letters, digits, and underscores. This ID cannot change after creation.'));
  $form['label'] = array('#type' => 'textfield', '#title' => t('Display label'), '#required' => TRUE, '#maxlength' => 255,
    '#default_value' => isset($source['label']) ? (!$editing ? t('Copy of') . ' ' . $source['label'] : $source['label']) : '',
    '#description' => t('Renaming this label preserves the machine ID and default reference.'));
  $form['description'] = array('#type' => 'textarea', '#title' => t('Description'), '#default_value' => isset($source['description']) ? $source['description'] : '', '#description' => t('Optional plain text, at most 4096 bytes.'));
  $form['general'] = array('#type' => 'fieldset', '#title' => t('General settings'), '#tree' => FALSE, '#collapsible' => TRUE, '#collapsed' => FALSE);
  foreach (array('id', 'label', 'description') as $key) {
    // Group visually without changing the strict submitted field names.
    $form['general'][$key] = $form[$key] + array('#parents' => array($key));
    unset($form[$key]);
  }
  if ($inherited !== NULL) {
    $form['base_info'] = array('#markup' => '<p><strong>' . t('Pinned base (read-only):') . '</strong> ' .
      check_plain($inherited['label']) . ' — <code>' . check_plain($inherited['id']) . '</code>, <code>' . check_plain($inherited['version']) . '</code>. ' .
      ($adding ? t('This exact preset version is retained in the new theme. Blank Add fields retain the captured starting colors, including copied custom overrides.') : t('Blank overrides inherit this exact preset version. Inherited expressions are retained, not copied into the override fields.')) . '</p>');
    $form['extra_property'] = array('#markup' => '<p><code>--h5p-theme-contrast-cta-dark</code>: <code>' .
      check_plain($inherited['colors']['--h5p-theme-contrast-cta-dark']) . '</code>. ' .
      t('Inherited, read-only catalog property. Runtime support in Core 1.28 is not established.') . '</p>');
  }
  if ($preset_copy) {
    $form['inheritance'] = _h5p_themer_admin_palette_table($inherited['colors']);
    $form['copy_help'] = array('#markup' => '<p>' . t('Create the custom copy with no color overrides. After saving, use Edit to customize its hexadecimal overrides.') . '</p>');
  }
  else {
    $form['colors'] = array('#type' => 'fieldset', '#title' => t('Optional color overrides'), '#tree' => TRUE);
    foreach (_h5p_themer_admin_color_groups() as $group => $definition) {
      $form['colors'][$group] = array('#type' => 'fieldset', '#title' => $definition['title'], '#collapsible' => TRUE, '#collapsed' => TRUE);
      foreach ($definition['fields'] as $suffix => $label) {
        $property = '--h5p-theme-' . $suffix;
        $description = '<code>' . check_plain($property) . '</code>. ' . t('Blank: inherit Core default. Explicit override: #RGB or #RRGGBB.');
        if ($inherited !== NULL) {
          $description = '<code>' . check_plain($property) . '</code>. <strong>' .
            (isset($source['colors'][$property]) ? t('Saved administrator override.') : t('Inherited; no saved override.')) . '</strong> ' .
            t('Inherited preset value:') . ' <code>' . check_plain($inherited['colors'][$property]) . '</code>. ' .
            t('Blank: inherit the pinned preset. Explicit override: #RGB or #RRGGBB.');
        }
        elseif (!$adding && isset($source['colors'][$property])) {
          $description .= ' <strong>' . t('Saved administrator override. Clearing restores the Core fallback.') . '</strong>';
        }
        $starting = $adding && isset($context['add_snapshot']['palette']['colors'][$property]) ? $context['add_snapshot']['palette']['colors'][$property] : '';
        if ($adding) {
          $description = '<code>' . check_plain($property) . '</code>. ' .
            ($starting !== '' ? t('Starting color:') . ' <code>' . check_plain($starting) . '</code>. ' : t('Starting color uses the Core fallback; no resolved swatch is available.') . ' ') .
            t('Blank retains this starting color. Enter #RGB or #RRGGBB, or use an available picker, for an override.');
        }
        $form['colors'][$group][$property] = array('#type' => 'textfield', '#title' => $label,
          '#parents' => array('colors', $property), '#size' => 12, '#maxlength' => 7,
          '#default_value' => !$adding && isset($source['colors'][$property]) ? $source['colors'][$property] : '', '#description' => $description,
          '#attributes' => array('class' => array('h5p-themer-color-input'),
            'data-h5p-themer-inherited' => $adding ? $starting : ($inherited !== NULL ? $inherited['colors'][$property] : '')));
        if ($adding) { $form['colors'][$group][$property]['#attributes']['data-h5p-themer-starting'] = 'true'; }
        if ($adding && $starting === '') {
          // Display-only seed for the unnamed companion, never a saved override.
          $form['colors'][$group][$property]['#attributes']['data-h5p-themer-picker-initial'] = '#000000';
        }
      }
    }
    $form['#attached']['js'][] = backdrop_get_path('module', 'h5p_themer') . '/js/h5p_themer.admin.js';
  }
  if ($editing) {
    $form['saved_preview'] = h5p_themer_preview(array('kind' => 'custom', 'id' => $context['id']), 2, TRUE, TRUE);
  }
  $form['#validate'] = array('h5p_themer_theme_form_validate');
  $form['#submit'] = array('h5p_themer_theme_form_submit');
  $form['#h5p_themer_operation'] = $context['operation'];
  if (!$adding) { $form['#h5p_themer_source_id'] = $context['id']; }
  $errors = $editing ? _h5p_themer_admin_check_original('h5p_themer.theme.' . $context['id'], $context['original']) : array();
  $errors = array_merge($errors, _h5p_themer_publication_form($form_state, TRUE));
  return _h5p_themer_admin_actions($form, in_array($context['operation'], array('duplicate', 'preset_duplicate'), TRUE) ? t('Create duplicate') : t('Save theme'), $errors);
}

/**
 * Reports shared schema errors against their actual Form API fields.
 */
function _h5p_themer_admin_errors(array $fields) {
  foreach ($fields as $field => $messages) {
    form_set_error($field, implode(' ', $messages));
  }
}

/**
 * Validation handler; also rerun under the submit lock before any write.
 */
function h5p_themer_theme_form_validate($form, &$form_state) {
  unset($form_state['h5p_themer_candidate']);
  if (isset($form['#h5p_themer_operation']) && $form['#h5p_themer_operation'] === 'add' &&
      !_h5p_themer_admin_add_context_valid(isset($form_state['h5p_themer']) ? $form_state['h5p_themer'] : NULL)) {
    _h5p_themer_admin_add_expired_validate($form, $form_state);
    return;
  }
  if (isset($form['#h5p_themer_operation']) && $form['#h5p_themer_operation'] !== 'add' &&
      (!_h5p_themer_admin_theme_context_valid(isset($form_state['h5p_themer']) ? $form_state['h5p_themer'] : NULL, $form['#h5p_themer_operation']) ||
       $form_state['h5p_themer']['id'] !== $form['#h5p_themer_source_id'])) {
    _h5p_themer_admin_expired_validate($form, $form_state);
    return;
  }
  $guard = array_merge(_h5p_themer_admin_guard(), _h5p_themer_publication_form($form_state));
  if ($guard || empty($form_state['h5p_themer'])) {
    _h5p_themer_admin_errors(array('_form' => $guard ?: array(t('Invalid theme form context.'))));
    return;
  }
  $context = $form_state['h5p_themer'];
  $values = $form_state['values'];
  $input = isset($form_state['input']) ? $form_state['input'] : array();
  if (!is_array($input)) {
    form_set_error('_form', t('Invalid theme form input.'));
    return;
  }
  if (!in_array($context['operation'], array('add', 'edit', 'duplicate', 'preset_duplicate'), TRUE)) {
    form_set_error('_form', t('Invalid theme operation.'));
    return;
  }
  $editing = $context['operation'] === 'edit';
  $preset_copy = $context['operation'] === 'preset_duplicate';
  if ($preset_copy) {
    $base = $context['original']['base_preset'];
    $preset = h5p_themer_preset_load($base['id'], $base['version']);
    if ($preset['errors'] || $preset['data'] !== $context['preset']) {
      form_set_error('_form', t('The built-in definition changed or disappeared. Reload before copying.'));
      return;
    }
  }
  elseif ($context['operation'] === 'add') {
    $errors = _h5p_themer_admin_add_check($context);
    if ($errors) {
      _h5p_themer_admin_errors(array('_form' => $errors));
      return;
    }
  }
  else {
    $errors = _h5p_themer_admin_check_original('h5p_themer.theme.' . $context['id'], $context['original'], $editing);
    if ($errors) {
      _h5p_themer_admin_errors(array('_form' => $errors));
      return;
    }
  }
  foreach (array_keys($input) as $key) {
    if (!in_array($key, array('id', 'label', 'description', 'colors', 'form_id', 'form_build_id', 'form_token', 'op'), TRUE)) {
      form_set_error('_form', t('Unexpected theme form field: @field.', array('@field' => $key)));
    }
  }
  if ($editing && ((isset($values['id']) && $values['id'] !== $context['id']) || (isset($form_state['input']['id']) && $form_state['input']['id'] !== $context['id']))) {
    form_set_error('id', t('The machine ID cannot change. Edit the display label to rename this theme.'));
  }
  $candidate = $context['original'];
  $candidate['schema_version'] = isset($candidate['schema_version']) ? $candidate['schema_version'] : 1;
  $candidate['id'] = $editing ? $context['id'] : (isset($values['id']) ? $values['id'] : NULL);
  foreach (array('label', 'description') as $key) {
    $candidate[$key] = isset($values[$key]) ? $values[$key] : ($key === 'description' ? '' : NULL);
  }
  $candidate['colors'] = $preset_copy ? array() : (isset($values['colors']) ? $values['colors'] : array());
  if (is_array($candidate['colors'])) {
    $candidate['colors'] = array_filter($candidate['colors'], function ($value) { return $value !== ''; });
    if ($context['operation'] === 'add') {
      $candidate['colors'] = array_replace($context['original']['colors'], $candidate['colors']);
    }
  }
  unset($candidate['_config_name']);
  $name = 'h5p_themer.theme.' . (is_string($candidate['id']) ? $candidate['id'] : '');
  _h5p_themer_admin_errors(h5p_themer_config_field_errors($name, $candidate));
  // Native Form API ignores undeclared fields; explicitly reject forged color
  // keys/non-hex input rather than silently dropping it during input processing.
  if (array_key_exists('colors', $input)) {
    $raw = $candidate;
    $raw['colors'] = $input['colors'];
    if (is_array($raw['colors'])) {
      foreach ($raw['colors'] as $property => $value) {
        if (!in_array($property, h5p_themer_color_properties(), TRUE)) {
          form_set_error('colors', t('Unsupported H5P color custom property: @key.', array('@key' => $property)));
        }
        // Form API inserts NULL input placeholders for omitted declared fields.
        if (!$preset_copy && $value === NULL && in_array($property, h5p_themer_color_properties(), TRUE)) {
          unset($raw['colors'][$property]);
        }
      }
      $raw['colors'] = array_filter($raw['colors'], function ($value) { return $value !== ''; });
    }
    _h5p_themer_admin_errors(h5p_themer_config_field_errors($name, $raw));
    if ($preset_copy && $raw['colors'] !== array()) {
      form_set_error('colors', t('Create the preset copy before adding color overrides.'));
    }
  }
  if (!$editing && h5p_themer_valid_theme_id($candidate['id'])) {
    $default_errors = _h5p_themer_admin_creation_default_errors($candidate['id']);
    if ($default_errors) { _h5p_themer_admin_errors(array('id' => $default_errors)); }
    try {
      if (config_get_config_storage()->exists($name)) {
        form_set_error('id', t('This machine ID is already in use. Choose a new ID.'));
      }
    }
    catch (ConfigException $e) {
      form_set_error('id', t('Theme ID availability could not be checked.'));
    }
  }
  if (!form_get_errors()) {
    $form_state['h5p_themer_candidate'] = $candidate;
  }
}

/**
 * Submission handler: permissions, compatibility, identity and schema rechecked.
 */
function h5p_themer_theme_form_submit($form, &$form_state) {
  if (form_get_errors()) {
    return;
  }
  if (!lock_acquire('h5p_themer_admin_write')) {
    form_set_error('_form', t('Another theme operation is in progress. Try saving again.'));
    $form_state['rebuild'] = $form_state['h5p_themer']['operation'] === 'add';
    return;
  }
  try {
    h5p_themer_theme_form_validate($form, $form_state);
    if (form_get_errors()) {
      $form_state['rebuild'] = $form_state['h5p_themer']['operation'] === 'add';
      return;
    }
    $candidate = $form_state['h5p_themer_candidate'];
    $record = new Config('h5p_themer.theme.' . $candidate['id'], config_get_config_storage());
    $record->setData($candidate);
    $record->validateData();
    $runtime_before = _h5p_themer_admin_cache_snapshot();
    $publication = $form_state['h5p_themer_publication'];
    if ($form_state['h5p_themer']['operation'] !== 'edit') {
      $publication = _h5p_themer_publication_hide($candidate['id'], $publication);
    }
    _h5p_themer_visibility_lease();
    _h5p_themer_publication_state($publication);
    $context = $form_state['h5p_themer'];
    if ($context['operation'] !== 'edit' && config_get_config_storage()->exists('h5p_themer.theme.' . $candidate['id']) !== FALSE) { throw new ConfigException('Destination is no longer absent.'); }
    if ($context['operation'] !== 'edit' && _h5p_themer_admin_creation_default_errors($candidate['id'])) { throw new ConfigException('Destination became a global default reference.'); }
    if ($context['operation'] === 'add' && _h5p_themer_admin_add_check($context)) { throw new ConfigException('Starting theme changed before persistence.'); }
    if (in_array($context['operation'], array('edit', 'duplicate'), TRUE) && _h5p_themer_admin_check_original('h5p_themer.theme.' . $context['id'], $context['original'], $context['operation'] === 'edit')) { throw new ConfigException('Source changed before persistence.'); }
    $record->save();
    _h5p_themer_publication_verify_theme($candidate['id'], $candidate);
    _h5p_themer_publication_state($publication);
    _h5p_themer_admin_cache_invalidate($runtime_before);
    unset($runtime_before);
    backdrop_set_message(t('Saved theme @label.', array('@label' => $candidate['label'])));
    $form_state['redirect'] = 'admin/config/content/h5p-themer';
  }
  catch (Throwable $e) {
    form_set_error('_form', t('The theme save could not be verified. A restrictive visibility entry or theme definition may have been saved. Inspect stored state before retrying; visibility has not been automatically restored.'));
    $form_state['rebuild'] = $form_state['h5p_themer']['operation'] === 'add';
  }
  finally {
    if (isset($runtime_before)) {
      try { _h5p_themer_admin_cache_invalidate($runtime_before); }
      catch (Throwable $e) { form_set_error('_form', t('Output cache invalidation could not be verified. Restrict learner access until a targeted refresh is authorized.')); $form_state['rebuild'] = $form_state['h5p_themer']['operation'] === 'add'; }
    }
    lock_release('h5p_themer_admin_write');
  }
}

/**
 * Separate default-selection form. Broken references require an explicit choice.
 */
function h5p_themer_default_form($form, &$form_state) {
  if (!h5p_themer_admin_access()) {
    return array('#markup' => t('Access denied.'));
  }
  if (!_h5p_themer_admin_cached_form($form_state, 'h5p_themer_settings',
      _h5p_themer_admin_settings_context_valid(isset($form_state['h5p_themer_settings']) ? $form_state['h5p_themer_settings'] : NULL))) {
    return _h5p_themer_admin_expired_form();
  }
  if (!isset($form_state['h5p_themer_settings'])) {
    $settings = _h5p_themer_admin_original('h5p_themer.settings');
    if ($settings['errors']) {
      return array('#markup' => '<p>' . t('Settings are missing or malformed. Repair them through configuration import before using this form.') . ' ' . implode(' ', $settings['errors']) . '</p>');
    }
    $form_state['h5p_themer_settings'] = $settings['data'];
  }
  $settings = $form_state['h5p_themer_settings'];
  $density_control = h5p_themer_native_density_control();
  if (!array_key_exists('h5p_themer_density_control', $form_state)) {
    $form_state['h5p_themer_density_control'] = $density_control;
  }
  $choices = _h5p_themer_admin_default_choices();
  $options = $choices['options'];
  $selected = _h5p_themer_admin_selection_token($settings, $choices['references']);
  if ($selected === NULL) {
    $options['__missing__'] = t('Missing or invalid selected theme. Choose a theme or Core defaults.');
    $selected = '__missing__';
    $form['warning'] = array('#markup' => '<p role="alert">' . t('The stored default reference is broken. Nothing has been changed. Choose a replacement or explicitly clear the default.') . '</p>');
  }
  if ($choices['errors']) {
    $form['collection_warning'] = array('#markup' => '<p role="alert">' . implode(' ', $choices['errors']) . '</p>');
  }
  $form['default_theme'] = array('#type' => 'select', '#title' => t('Global Default theme'), '#options' => $options, '#default_value' => $selected,
    '#description' => t('Core, built-in and custom choices supply colors only. Reload playback after saving. Density is independent of the visual theme; before an explicit learner choice, original activity spacing is preserved.'));
  $form['learner_selection_enabled'] = array('#type' => 'checkbox',
    '#title' => t('Allow learners to select their H5P theme'),
    '#default_value' => isset($settings['learner_selection_enabled']) && $settings['learner_selection_enabled'] === TRUE ? 1 : 0,
    '#description' => t('Optional: when enabled, eligible H5P activities display one learner theme selector per page. With native Density enabled, ready qualified TrueFalse and MultiChoice players also offer independent Small, Medium and Large Density buttons. Unsupported activities do not trigger a selector. Choices change styling without changing learner answers or scoring. Where browser session storage is available, theme and Density preferences independently follow the current tab session and are not saved to a learner account.'));
  $form['native_density_enabled'] = array('#type' => 'checkbox', '#title' => t('Enable native Density controls'),
    '#default_value' => $density_control['overridden'] ? (int) $density_control['enabled'] : (!empty($settings['native_density_enabled']) ? 1 : 0),
    '#disabled' => $density_control['overridden'],
    '#description' => $density_control['overridden']
      ? ($density_control['valid']
        ? t('Overridden by the PHP h5p_themer_native_density_enabled site setting. The saved checkbox preference is unchanged. Remove that override to use this checkbox.')
        : t('The PHP h5p_themer_native_density_enabled override is invalid: Density is disabled. Use a boolean TRUE or FALSE, or remove the override to use this checkbox.'))
      : t('Requires learner theme selection and ready top-level TrueFalse 1.8.24 or MultiChoice 1.16.27 internal iframe players on Core API 1.28. Before a learner choice, original activity spacing is preserved. Choices persist in this tab independently of visual themes.'));
  $form['#validate'] = array('h5p_themer_default_form_validate');
  $form['#submit'] = array('h5p_themer_default_form_submit');
  return _h5p_themer_admin_actions($form, t('Save global settings'), _h5p_themer_admin_check_original('h5p_themer.settings', $settings));
}

/**
 * Validates settings and the selected definition using Phase 1A safeguards.
 */
function h5p_themer_default_form_validate($form, &$form_state) {
  unset($form_state['h5p_themer_candidate']);
  if (!_h5p_themer_admin_settings_context_valid(isset($form_state['h5p_themer_settings']) ? $form_state['h5p_themer_settings'] : NULL)) {
    _h5p_themer_admin_expired_validate($form, $form_state);
    return;
  }
  $errors = _h5p_themer_admin_guard();
  if (!$errors && isset($form_state['h5p_themer_settings'])) {
    $errors = _h5p_themer_admin_check_original('h5p_themer.settings', $form_state['h5p_themer_settings']);
  }
  elseif (!$errors) {
    $errors[] = t('Invalid default-selection form context.');
  }
  if ($errors) {
    _h5p_themer_admin_errors(array('_form' => $errors));
    return;
  }
  $candidate = $form_state['h5p_themer_settings'];
  $density_control = h5p_themer_native_density_control();
  if (!isset($form_state['h5p_themer_density_control']) || $form_state['h5p_themer_density_control'] !== $density_control) {
    form_set_error('native_density_enabled', t('Density control settings changed. Reload this form before saving.'));
    return;
  }
  $density_enabled = isset($form_state['values']['native_density_enabled']) ? $form_state['values']['native_density_enabled'] : 0;
  $density_input = isset($form_state['input']['native_density_enabled']) ? $form_state['input']['native_density_enabled'] : NULL;
  if (!in_array($density_enabled, array(0, 1), TRUE) || ($density_input !== NULL && !in_array($density_input, array('1', 1), TRUE))) {
    form_set_error('native_density_enabled', t('Invalid native Density checkbox value.'));
    return;
  }
  if (!$density_control['overridden'] && ($density_enabled === 1 || array_key_exists('native_density_enabled', $candidate))) {
    $candidate['native_density_enabled'] = $density_enabled === 1;
  }
  // Native checkbox processing returns integer 0/1. Persist only real booleans.
  $enabled = isset($form_state['values']['learner_selection_enabled']) ? $form_state['values']['learner_selection_enabled'] : 0;
  $input = isset($form_state['input']['learner_selection_enabled']) ? $form_state['input']['learner_selection_enabled'] : NULL;
  if (!in_array($enabled, array(0, 1), TRUE) || ($input !== NULL && !in_array($input, array('1', 1), TRUE))) {
    form_set_error('learner_selection_enabled', t('Invalid learner theme selection checkbox value.'));
    return;
  }
  // Preserve legacy absence on an unchecked save; no migration is necessary.
  if ($enabled === 1 || array_key_exists('learner_selection_enabled', $candidate)) {
    $candidate['learner_selection_enabled'] = $enabled === 1;
  }
  $value = isset($form_state['values']['default_theme']) ? $form_state['values']['default_theme'] : FALSE;
  $choices = _h5p_themer_admin_default_choices();
  if (!is_string($value) || !array_key_exists($value, $choices['references']) || $choices['errors']) {
    form_set_error('default_theme', t('Select an available built-in, valid custom theme, or Core defaults.'));
    return;
  }
  $reference = $choices['references'][$value];
  if ($reference !== NULL && $reference['kind'] === 'custom') {
    // Refresh cached source before reference validation and locked submission.
    $theme = _h5p_themer_admin_original('h5p_themer.theme.' . $reference['id']);
    if ($theme['errors']) {
      form_set_error('default_theme', t('Select an existing, valid custom theme.'));
      return;
    }
  }
  if ($reference !== NULL && $reference['kind'] === 'builtin') {
    $candidate['schema_version'] = 2;
  }
  $candidate['default_theme'] = $candidate['schema_version'] === 1 && $reference !== NULL ? $reference['id'] : $reference;
  _h5p_themer_admin_errors(h5p_themer_config_field_errors('h5p_themer.settings', $candidate));
  if (form_get_errors()) {
    return;
  }
  try {
    $record = new Config('h5p_themer.settings', config_get_config_storage());
    $record->setData($candidate);
    $record->validateData();
    _h5p_themer_validate_default_reference($record, NULL);
    $form_state['h5p_themer_candidate'] = $candidate;
  }
  catch (ConfigException $e) {
    form_set_error('default_theme', t('The global default could not be validated. Select a valid theme or Core defaults.'));
  }
}

/**
 * Saves global settings after a fresh locked validation.
 */
function h5p_themer_default_form_submit($form, &$form_state) {
  if (form_get_errors()) {
    return;
  }
  if (!lock_acquire('h5p_themer_admin_write')) {
    form_set_error('_form', t('Another theme operation is in progress. Try saving again.'));
    $form_state['rebuild'] = FALSE;
    return;
  }
  try {
    h5p_themer_default_form_validate($form, $form_state);
    if (form_get_errors()) {
      $form_state['rebuild'] = FALSE;
      return;
    }
    $record = new Config('h5p_themer.settings', config_get_config_storage());
    $record->setData($form_state['h5p_themer_candidate']);
    $record->validateData();
    _h5p_themer_validate_default_reference($record, NULL);
    $runtime_before = _h5p_themer_admin_cache_snapshot();
    $record->save();
    _h5p_themer_admin_cache_invalidate($runtime_before);
    backdrop_set_message(t('The global settings were saved. Reload H5P playback to apply these settings. If learner selection is enabled, eligible activities offer session-scoped styling choices; learner answers and scoring are unchanged. Preferences are not saved to learner accounts. When native Density is enabled, explicit learner choices survive theme changes. Without an explicit choice, original activity spacing is preserved. Saved theme Density is retained only for legacy compatibility. Reload playback after changing Density activation; already-open pages are not remotely updated.'));
    $form_state['redirect'] = 'admin/config/content/h5p-themer';
  }
  catch (ConfigException $e) {
    form_set_error('_form', t('The global default could not be saved. Theme definitions were not changed.'));
    $form_state['rebuild'] = FALSE;
  }
  finally {
    lock_release('h5p_themer_admin_write');
  }
}

/**
 * Shared deletion checks, including stored and effective default references.
 */
function _h5p_themer_admin_delete_errors(array $context) {
  $name = 'h5p_themer.theme.' . $context['id'];
  $errors = _h5p_themer_admin_check_original($name, $context['original']);
  if ($errors) {
    return $errors;
  }
  $stored_settings = _h5p_themer_admin_original('h5p_themer.settings');
  if ($stored_settings['errors']) {
    return array(t('Repair the stored default settings before deleting a theme.'));
  }
  $effective = h5p_themer_config_load('h5p_themer.settings');
  $reference = $effective['errors'] ? NULL : h5p_themer_selection_reference($effective['data']['default_theme'], $effective['data']['schema_version']);
  if ($effective['errors'] || $reference['errors'] || ($reference['data']['kind'] === 'custom' && $reference['data']['id'] === $context['id'])) {
    return array(t('This theme may be the effective global default. Clear or change the default first, including any settings.php override.'));
  }
  try {
    _h5p_themer_validate_theme_deletion(config($name), NULL);
  }
  catch (ConfigValidateException $e) {
    return array($e->getMessage());
  }
  return array();
}

/**
 * Native confirmation form. Visiting a link never performs deletion.
 */
function h5p_themer_delete_form($form, &$form_state, $id) {
  if (!h5p_themer_admin_access()) {
    return array('#markup' => t('Access denied.'));
  }
  if (!_h5p_themer_admin_cached_form($form_state, 'h5p_themer',
      _h5p_themer_admin_theme_context_valid(isset($form_state['h5p_themer']) ? $form_state['h5p_themer'] : NULL, 'delete'))) {
    return _h5p_themer_admin_expired_form();
  }
  if (!isset($form_state['h5p_themer'])) {
    if (!h5p_themer_valid_theme_id($id)) {
      return array('#markup' => t('Invalid theme ID.'));
    }
    $theme = _h5p_themer_admin_original('h5p_themer.theme.' . $id);
    if ($theme['errors']) {
      return array('#markup' => t('This theme is missing or invalid. No theme was deleted.'));
    }
    $form_state['h5p_themer'] = array('id' => $id, 'original' => $theme['data']);
  }
  $context = $form_state['h5p_themer'];
  $form['#h5p_themer_source_id'] = $context['id'];
  $form['#validate'] = array('h5p_themer_delete_form_validate');
  $form['#submit'] = array('h5p_themer_delete_form_submit');
  $form['#method'] = 'post';
  $form = confirm_form($form, t('Delete theme @label (@id)?', array('@label' => $context['original']['label'], '@id' => $context['id'])),
    'admin/config/content/h5p-themer', t('This deletes only this theme definition. It cannot be undone. A global default must be cleared or changed first.'), t('Delete theme'));
  $errors = array_merge(_h5p_themer_admin_guard(), _h5p_themer_admin_delete_errors($context), _h5p_themer_publication_form($form_state, TRUE));
  if ($errors) {
    $form['diagnostics'] = array('#markup' => '<p role="alert">' . implode(' ', $errors) . '</p>');
    $form['actions']['submit']['#disabled'] = TRUE;
  }
  return $form;
}

/**
 * Deletion validation; the confirmation is supplied by native confirm_form().
 */
function h5p_themer_delete_form_validate($form, &$form_state) {
  if (!_h5p_themer_admin_theme_context_valid(isset($form_state['h5p_themer']) ? $form_state['h5p_themer'] : NULL, 'delete') ||
      $form_state['h5p_themer']['id'] !== $form['#h5p_themer_source_id']) {
    _h5p_themer_admin_expired_validate($form, $form_state);
    return;
  }
  $errors = array_merge(_h5p_themer_admin_guard(), _h5p_themer_publication_form($form_state));
  if (empty($form_state['h5p_themer']) || empty($form_state['values']['confirm'])) {
    $errors[] = t('Explicit confirmation is required.');
  }
  elseif (!$errors) {
    $errors = _h5p_themer_admin_delete_errors($form_state['h5p_themer']);
  }
  if ($errors) {
    _h5p_themer_admin_errors(array('_form' => $errors));
  }
}

/**
 * Deletes exactly one object after rechecking the default under the write lock.
 */
function h5p_themer_delete_form_submit($form, &$form_state) {
  if (form_get_errors()) {
    return;
  }
  if (!lock_acquire('h5p_themer_admin_write')) {
    form_set_error('_form', t('Another theme operation is in progress. Try again.'));
    $form_state['rebuild'] = FALSE;
    return;
  }
  try {
    h5p_themer_delete_form_validate($form, $form_state);
    if (form_get_errors()) {
      $form_state['rebuild'] = FALSE;
      return;
    }
    $runtime_before = _h5p_themer_admin_cache_snapshot();
    $id = $form_state['h5p_themer']['id'];
    $publication = _h5p_themer_publication_hide($id, $form_state['h5p_themer_publication']);
    _h5p_themer_visibility_lease();
    _h5p_themer_publication_state($publication);
    if (_h5p_themer_admin_delete_errors($form_state['h5p_themer'])) { throw new ConfigException('Deletion protection changed before persistence.'); }
    // Content/policy can be identical after recreation. Retire old visibility
    // forms before deletion so they cannot authorize publication of a new ID use.
    _h5p_themer_visibility_admin_invalidate();
    _h5p_themer_visibility_lease();
    _h5p_themer_publication_state($publication);
    if (_h5p_themer_admin_delete_errors($form_state['h5p_themer'])) { throw new ConfigException('Deletion protection changed during form invalidation.'); }
    $storage = config_get_config_storage();
    (new Config('h5p_themer.theme.' . $id, $storage))->delete();
    if ($storage->exists('h5p_themer.theme.' . $id) !== FALSE) { throw new ConfigException('Deletion verification failed.'); }
    _h5p_themer_publication_state($publication);
    _h5p_themer_admin_cache_invalidate($runtime_before);
    unset($runtime_before);
    backdrop_set_message(t('The theme was deleted and its hidden visibility tombstone was retained.'));
    $form_state['redirect'] = 'admin/config/content/h5p-themer';
  }
  catch (Throwable $e) {
    form_set_error('_form', t('The deletion could not be verified. A restrictive visibility entry may have been saved and the definition may still exist. Inspect stored state before retrying; visibility has not been automatically restored.'));
    $form_state['rebuild'] = FALSE;
  }
  finally {
    if (isset($runtime_before)) {
      try { _h5p_themer_admin_cache_invalidate($runtime_before); }
      catch (Throwable $e) { form_set_error('_form', t('Output cache invalidation could not be verified. Restrict learner access until a targeted refresh is authorized.')); $form_state['rebuild'] = FALSE; }
    }
    lock_release('h5p_themer_admin_write');
  }
}


/**
 * Current administrative catalog is explicitly pinned; no mutable latest alias.
 */
function _h5p_themer_admin_catalog() {
  return h5p_themer_presets('moodle-478fed30d2a4');
}

/**
 * Builds current UI tokens and exact typed identities; never writes settings.
 */
function _h5p_themer_admin_default_choices() {
  $collection = _h5p_themer_admin_collection();
  $catalog = _h5p_themer_admin_catalog();
  $result = array('options' => array('core' => t('H5P Core defaults (no overrides)')),
    'references' => array('core' => NULL), 'collection' => $collection,
    'presets' => $catalog['errors'] ? array() : $catalog['data'],
    'errors' => array_merge($collection['errors'], $catalog['errors']));
  foreach ($result['presets'] as $id => $preset) {
    // Select labels must be raw text: native select rendering escapes them.
    $result['options']['builtin:' . $id] = t('Built-in:') . ' ' . $preset['label'];
    $result['references']['builtin:' . $id] = array('kind' => 'builtin', 'id' => $id, 'version' => $preset['version']);
  }
  foreach ($collection['themes'] as $id => $theme) {
    $result['options']['custom:' . $id] = t('Custom:') . ' ' . $theme['label'] . ' (' . $id . ')';
    $result['references']['custom:' . $id] = array('kind' => 'custom', 'id' => $id);
  }
  return $result;
}

/**
 * Matches logical identity, including version, independently of stored schema.
 */
function _h5p_themer_admin_selection_token(array $settings, array $references) {
  $selection = h5p_themer_selection_reference($settings['default_theme'], $settings['schema_version']);
  if (!$selection['errors']) {
    foreach ($references as $token => $reference) {
      $candidate = h5p_themer_selection_reference($reference, 2);
      if (!$candidate['errors'] && $candidate['data'] == $selection['data']) {
        return $token;
      }
    }
  }
  return NULL;
}

/**
 * Short administrative descriptions; no preview or accessibility certification.
 */
function _h5p_themer_admin_preset_description($id) {
  $descriptions = array(
    'daylight' => t('Default built-in palette; distinct from original H5P Core styling.'),
    'lavender' => t('Upstream Lavender palette with purple controls.'),
    'mint' => t('Upstream Mint palette with teal controls.'),
    'sunset' => t('Upstream Sunset palette with warm controls.'),
    'dark' => t('Upstream Dark palette with dark surfaces.'),
  );
  return isset($descriptions[$id]) ? $descriptions[$id] : t('Immutable built-in palette.');
}

/**
 * Renders exact strings as escaped text, never executable CSS or HTML.
 */
function _h5p_themer_admin_palette_table(array $colors) {
  $rows = array();
  foreach ($colors as $property => $value) {
    $rows[] = array('<code>' . check_plain($property) . '</code>', '<code>' . check_plain($value) . '</code>');
  }
  return array('#theme' => 'table', '#header' => array(t('Color property'), t('Exact inherited value')), '#rows' => $rows);
}

/**
 * Read-only built-in inspection. Has no mutation form or edit/delete links.
 */
function h5p_themer_preset_view($id, $version) {
  if (!h5p_themer_admin_access()) {
    return array('#markup' => t('Access denied.'));
  }
  $preset = h5p_themer_preset_load($id, $version);
  if ($preset['errors']) {
    return array('#markup' => '<p role="alert">' . implode(' ', $preset['errors']) . '</p>');
  }
  $data = $preset['data'];
  $build['details'] = array('#theme' => 'table', '#header' => array(t('Setting'), t('Value')), '#rows' => array(
    array(t('Display name'), check_plain($data['label'])),
    array(t('Preset ID'), check_plain($data['id'])),
    array(t('Catalog version'), check_plain($data['version'])),
    array(t('Source repository'), check_plain($data['provenance']['repository'])),
    array(t('Source revision'), check_plain($data['provenance']['revision'])),
    array(t('Source file and lines'), check_plain($data['provenance']['path'] . ':' . $data['provenance']['lines'])),
    array(t('Attribution'), check_plain($data['provenance']['copyright'])),
    array(t('License'), check_plain($data['provenance']['license'])),
  ));
  $build['help'] = array('#markup' => '<p>' . t('This built-in is immutable. Duplicate it to create an editable custom theme. Selected global colors apply to compatible H5P playback after reload. Independent learner Density controls preserve original activity spacing until a learner chooses Small, Medium or Large. Visual theme changes do not change that choice.') . '</p>');
  $build['saved_preview'] = h5p_themer_preview(array('kind' => 'builtin', 'id' => $id, 'version' => $version));
  $build['colors'] = _h5p_themer_admin_palette_table($data['colors']);
  $build['compatibility'] = array('#markup' => '<p><code>--h5p-theme-contrast-cta-dark</code>: ' . t('Preserved catalog data; runtime support in Core 1.28 is not established.') . '</p>');
  if (h5p_themer_compatibility()['compatible']) {
    $build['duplicate'] = array('#type' => 'link', '#title' => t('Duplicate'),
      '#href' => 'admin/config/content/h5p-themer/presets/' . $data['id'] . '/' . $data['version'] . '/duplicate');
  }
  return $build;
}

/** Combines existing global runtime and currently enabled selector dependencies. */
function _h5p_themer_admin_cache_snapshot() {
  return array(h5p_themer_runtime_cache_snapshot(), h5p_themer_learner_ui_cache_snapshot(), h5p_themer_native_density_cache_snapshot());
}

/** One native page/layout invalidation, only when delivered output changes. */
function _h5p_themer_admin_cache_invalidate($before) {
  if ($before !== _h5p_themer_admin_cache_snapshot()) { cache_clear_all(); }
}
