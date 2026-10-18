<?php
/**
 * @file
 * Read-only configuration access and validation.
 * SPDX-License-Identifier: GPL-2.0-or-later.
 */

require_once __DIR__ . '/h5p_themer.presets.inc';

/**
 * Returns the explicit color-property allowlist for the inspected Core 1.28.0.
 *
 * Source: library/styles/h5p-theme-variables.css, upstream 1.28.0 commit
 * 2aeb0b83fa603e331381b3a6b8bf42c3773ba140. Do not derive this at runtime.
 * Typography, dimensions, spacing, and arbitrary CSS are intentionally excluded.
 */
function h5p_themer_color_properties() {
  return array(
    '--h5p-theme-main-cta-base',
    '--h5p-theme-secondary-cta-base',
    '--h5p-theme-alternative-base',
    '--h5p-theme-background',
    '--h5p-theme-focus',
    '--h5p-theme-main-cta-light',
    '--h5p-theme-main-cta-dark',
    '--h5p-theme-contrast-cta',
    '--h5p-theme-contrast-cta-white',
    '--h5p-theme-contrast-cta-light',
    '--h5p-theme-secondary-cta-light',
    '--h5p-theme-secondary-cta-dark',
    '--h5p-theme-secondary-contrast-cta',
    '--h5p-theme-secondary-contrast-cta-hover',
    '--h5p-theme-alternative-light',
    '--h5p-theme-alternative-dark',
    '--h5p-theme-alternative-darker',
    '--h5p-theme-text-primary',
    '--h5p-theme-text-secondary',
    '--h5p-theme-text-third',
    '--h5p-theme-ui-base',
    '--h5p-theme-stroke-1',
    '--h5p-theme-stroke-2',
    '--h5p-theme-stroke-3',
    '--h5p-theme-feedback-correct-main',
    '--h5p-theme-feedback-correct-secondary',
    '--h5p-theme-feedback-correct-third',
    '--h5p-theme-feedback-incorrect-main',
    '--h5p-theme-feedback-incorrect-secondary',
    '--h5p-theme-feedback-incorrect-third',
    '--h5p-theme-feedback-neutral-main',
    '--h5p-theme-feedback-neutral-secondary',
    '--h5p-theme-feedback-neutral-third',
  );
}

/**
 * Tests a stable machine ID (1-64 ASCII characters, starting with a letter).
 */
function h5p_themer_valid_theme_id($id) {
  return is_string($id) && preg_match('/\A[a-z][a-z0-9_]{0,63}\z/', $id) === 1;
}

/**
 * Returns validation errors without normalizing or modifying the supplied data.
 *
 * Schema 1 accepts only opaque #RGB and #RRGGBB colors. Sparse maps, including
 * empty maps, are allowed: omitted values retain the Core defaults in a future
 * renderer. A sparse map is not a generated or accessibility-qualified palette.
 */
function h5p_themer_config_errors($name, $data) {
  $fields = h5p_themer_config_field_errors($name, $data);
  return $fields ? array_merge(...array_values($fields)) : array();
}

/**
 * Returns the same schema errors keyed by Form API field names.
 *
 * The flat Phase 1A API above remains unchanged; forms share these exact rules.
 */
function h5p_themer_config_field_errors($name, $data) {
  $errors = array();
  $add_error = function ($field, $message) use (&$errors) {
    $errors[$field][] = $message;
  };
  $settings = $name === 'h5p_themer.settings';
  $prefix = 'h5p_themer.theme.';
  $theme = is_string($name) && strpos($name, $prefix) === 0;
  if (!$settings && !$theme) {
    return array('_form' => array(t('Unrecognized H5P Themer configuration name.')));
  }
  if (!is_array($data)) {
    return array('_form' => array(t('H5P Themer configuration must be an object.')));
  }
  $required = $settings ? array('schema_version', 'default_theme') : array('schema_version', 'id', 'label', 'density', 'colors');
  if ($theme && isset($data['schema_version']) && $data['schema_version'] === 2) {
    $required[] = 'base_preset';
  }
  $allowed = array_merge($required, array('_config_name', '_config_static'), $theme ? array('description') : array('learner_selection_enabled'));
  foreach ($required as $key) {
    if (!array_key_exists($key, $data)) {
      $add_error($key, t('Missing H5P Themer configuration field: @key.', array('@key' => $key)));
    }
  }
  foreach ($data as $key => $value) {
    if (!in_array($key, $allowed, TRUE)) {
      $add_error('_form', t('Unsupported H5P Themer configuration field: @key.', array('@key' => $key)));
    }
  }
  if (!isset($data['schema_version']) || !in_array($data['schema_version'], array(1, 2), TRUE)) {
    $add_error('schema_version', t('H5P Themer configuration schema_version must be integer 1 or 2.'));
  }
  if (array_key_exists('_config_name', $data) && $data['_config_name'] !== $name) {
    $add_error('_form', t('The embedded configuration name must match its storage name.'));
  }
  if (array_key_exists('_config_static', $data) && !is_bool($data['_config_static'])) {
    $add_error('_form', t('The _config_static flag must be boolean.'));
  }
  if ($settings) {
    if (array_key_exists('learner_selection_enabled', $data) && !is_bool($data['learner_selection_enabled'])) {
      $add_error('learner_selection_enabled', t('Learner theme selection must be boolean.'));
    }
    if (array_key_exists('default_theme', $data)) {
      $reference = h5p_themer_selection_reference($data['default_theme'], isset($data['schema_version']) ? $data['schema_version'] : NULL);
      foreach ($reference['errors'] as $error) {
        $add_error('default_theme', $error);
      }
    }
    return $errors;
  }

  if (isset($data['schema_version']) && $data['schema_version'] === 2 && array_key_exists('base_preset', $data)) {
    foreach (_h5p_themer_preset_reference_errors($data['base_preset']) as $error) {
      $add_error('base_preset', $error);
    }
  }

  $id = substr($name, strlen($prefix));
  if (!h5p_themer_valid_theme_id($id) || !isset($data['id']) || $data['id'] !== $id) {
    $add_error('id', t('The theme ID must be valid and exactly match the configuration name suffix.'));
  }
  if (!isset($data['label']) || !is_string($data['label']) || trim($data['label']) === '' ||
      strlen($data['label']) > 255 || preg_match('//u', $data['label']) !== 1 ||
      preg_match('/[\x00-\x1F\x7F]/', $data['label'])) {
    $add_error('label', t('The theme label must be nonempty UTF-8 text of at most 255 bytes without control characters.'));
  }
  if (array_key_exists('description', $data) && (!is_string($data['description']) ||
      strlen($data['description']) > 4096 || preg_match('//u', $data['description']) !== 1 ||
      preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $data['description']))) {
    $add_error('description', t('The optional description must be UTF-8 text of at most 4096 bytes without unsafe control characters.'));
  }
  if (!isset($data['density']) || !in_array($data['density'], array('large', 'medium', 'small'), TRUE)) {
    $add_error('density', t('Theme density must be large, medium, or small.'));
  }
  if (!isset($data['colors']) || !is_array($data['colors'])) {
    $add_error('colors', t('Theme colors must be a map of supported CSS custom properties to hexadecimal colors.'));
  }
  else {
    $properties = h5p_themer_color_properties();
    foreach ($data['colors'] as $key => $value) {
      if (!in_array($key, $properties, TRUE)) {
        $add_error('colors', t('Unsupported H5P color custom property: @key.', array('@key' => $key)));
      }
      if (!is_string($value) || preg_match('/\A#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})\z/', $value) !== 1) {
        $field = in_array($key, $properties, TRUE) ? 'colors][' . $key : 'colors';
        $add_error($field, t('Color values must use #RGB or #RRGGBB; CSS expressions and declarations are not accepted.'));
      }
    }
  }
  return $errors;
}

/**
 * Loads and validates configuration without saving, repairing, or deleting it.
 *
 * @param string $name
 *   A settings or theme configuration name. Validated before storage access.
 * @param string $type
 *   Either active or staging.
 * @param bool $original
 *   TRUE for import validation, ignoring settings.php overrides. Default FALSE
 *   reads each top-level field through Config::get(), including overrides.
 *   Override colors as a whole map, not as individual dotted child keys.
 *
 * @return array
 *   data (validated array or NULL) and errors (translated strings).
 */
function h5p_themer_config_load($name, $type = 'active', $original = FALSE) {
  if (!is_string($name) || ($name !== 'h5p_themer.settings' &&
      (strpos($name, 'h5p_themer.theme.') !== 0 || !h5p_themer_valid_theme_id(substr($name, strlen('h5p_themer.theme.'))))) ||
      !in_array($type, array('active', 'staging'), TRUE)) {
    return array('data' => NULL, 'errors' => array(t('Invalid H5P Themer configuration name or storage type.')));
  }
  try {
    $config = config($name, $type);
    if ($config->isNew()) {
      return array('data' => NULL, 'errors' => array(t('The H5P Themer configuration @name does not exist.', array('@name' => $name))));
    }
    $data = $config->getOriginal();
    if (!$original) {
      // Config::get() without a key does not merge field-level overrides.
      $keys = $name === 'h5p_themer.settings' ? array('schema_version', 'default_theme', 'learner_selection_enabled') : array('schema_version', 'id', 'label', 'description', 'density', 'colors', 'base_preset');
      foreach ($keys as $key) {
        $value = $config->get($key);
        if (array_key_exists($key, $data) || $value !== NULL) {
          $data[$key] = $value;
        }
      }
    }
  }
  catch (ConfigException $e) {
    return array('data' => NULL, 'errors' => array(t('The H5P Themer configuration @name could not be read.', array('@name' => $name))));
  }
  catch (TypeError $e) {
    // Core's JSON decoder may accept a scalar, then fail its array contract.
    return array('data' => NULL, 'errors' => array(t('The H5P Themer configuration @name is not an object.', array('@name' => $name))));
  }
  $errors = h5p_themer_config_errors($name, $data);
  return array('data' => $errors ? NULL : $data, 'errors' => $errors);
}

/**
 * Loads a theme by stable identity, independently of its editable label.
 */
function h5p_themer_theme_load($id) {
  if (!h5p_themer_valid_theme_id($id)) {
    return array('data' => NULL, 'errors' => array(t('Invalid H5P theme ID.')));
  }
  return h5p_themer_config_load('h5p_themer.theme.' . $id);
}

/**
 * Resolves the global default; NULL data means no usable selected theme.
 *
 * No selection has no errors. Missing/invalid settings or a broken reference
 * has errors. Compatibility is deliberately separate so definitions remain
 * inspectable when H5P is unavailable. This function never applies a theme.
 */
function h5p_themer_default_theme_load() {
  $settings = h5p_themer_config_load('h5p_themer.settings');
  if ($settings['errors']) {
    return array('data' => NULL, 'errors' => $settings['errors']);
  }
  $reference = h5p_themer_selection_reference($settings['data']['default_theme'], $settings['data']['schema_version']);
  if ($reference['errors'] || $reference['data']['kind'] === 'core') {
    return array('data' => NULL, 'errors' => $reference['errors']);
  }
  if ($reference['data']['kind'] === 'builtin') {
    return h5p_themer_preset_load($reference['data']['id'], $reference['data']['version']);
  }
  // Keep the legacy definition-loader contract: colors remain stored overrides.
  return h5p_themer_theme_load($reference['data']['id']);
}

/**
 * Validates an exact base identity, not arbitrary CSS or catalog metadata.
 */
function _h5p_themer_preset_reference_errors($reference) {
  if (!is_array($reference) || count($reference) !== 2 ||
      !array_key_exists('id', $reference) || !array_key_exists('version', $reference)) {
    return array(t('A base preset must contain exactly an ID and pinned version.'));
  }
  return h5p_themer_preset_load($reference['id'], $reference['version'])['errors'];
}

/**
 * Decodes a stored selection into a typed in-memory identity (data/errors).
 *
 * Does not rewrite configuration. Schema 1 strings always denote custom IDs.
 * Schema 2 accepts NULL or exact builtin/custom objects, never UI token strings.
 * Custom existence belongs to resolution/import checks, not shape validation.
 */
function h5p_themer_selection_reference($selection, $schema_version = 2) {
  $error = array('data' => NULL, 'errors' => array(t('Invalid H5P theme selection reference.')));
  if (!in_array($schema_version, array(1, 2), TRUE)) {
    return $error;
  }
  if ($selection === NULL) {
    return array('data' => array('kind' => 'core'), 'errors' => array());
  }
  if ($schema_version === 1) {
    return h5p_themer_valid_theme_id($selection)
      ? array('data' => array('kind' => 'custom', 'id' => $selection), 'errors' => array()) : $error;
  }
  if (!is_array($selection) || !isset($selection['kind'], $selection['id'])) {
    return $error;
  }
  if ($selection['kind'] === 'custom' && count($selection) === 2 && h5p_themer_valid_theme_id($selection['id'])) {
    return array('data' => $selection, 'errors' => array());
  }
  if ($selection['kind'] === 'builtin' && count($selection) === 3 && array_key_exists('version', $selection)) {
    $preset = h5p_themer_preset_load($selection['id'], $selection['version']);
    return array('data' => $preset['errors'] ? NULL : $selection, 'errors' => $preset['errors']);
  }
  return $error;
}

/**
 * Resolves a custom palette without changing its stored definition or base.
 *
 * Stored density is independent of the base's colors. Unsupported inherited
 * properties are retained and identified; resolution makes no runtime claim.
 */
function h5p_themer_theme_resolve($id, $type = 'active', $original = FALSE) {
  if (!h5p_themer_valid_theme_id($id)) {
    return array('data' => NULL, 'errors' => array(t('Invalid H5P theme ID.')));
  }
  $theme = h5p_themer_config_load('h5p_themer.theme.' . $id, $type, $original);
  if ($theme['errors']) {
    return $theme;
  }
  $definition = $theme['data'];
  $colors = $definition['colors'];
  $base = NULL;
  $unsupported = array();
  if ($definition['schema_version'] === 2) {
    $base = $definition['base_preset'];
    $preset = h5p_themer_preset_load($base['id'], $base['version']);
    $colors = array_replace($preset['data']['colors'], $colors);
    $unsupported = $preset['data']['unsupported_properties'];
  }
  return array('data' => array(
    'kind' => 'custom', 'id' => $definition['id'], 'label' => $definition['label'],
    'density' => $definition['density'], 'colors' => $colors,
    'base_preset' => $base, 'unsupported_properties' => $unsupported,
  ), 'errors' => array());
}

/**
 * Resolves Core/built-in/custom selections for future administration consumers.
 *
 * Core is an explicit typed result with no overrides. Errors return NULL data,
 * not a Core or Daylight fallback. No compatibility gate, writes or assets run.
 */
function h5p_themer_selection_resolve($selection, $schema_version = 2, $type = 'active', $original = FALSE) {
  if (!in_array($type, array('active', 'staging'), TRUE)) {
    return array('data' => NULL, 'errors' => array(t('Invalid H5P configuration storage type.')));
  }
  $reference = h5p_themer_selection_reference($selection, $schema_version);
  if ($reference['errors']) {
    return $reference;
  }
  $identity = $reference['data'];
  if ($identity['kind'] === 'core') {
    return array('data' => array('kind' => 'core', 'id' => NULL,
      'label' => t('H5P Core defaults'), 'density' => NULL, 'colors' => array(),
      'unsupported_properties' => array()), 'errors' => array());
  }
  if ($identity['kind'] === 'builtin') {
    $preset = h5p_themer_preset_load($identity['id'], $identity['version']);
    return array('data' => array('kind' => 'builtin') + $preset['data'], 'errors' => array());
  }
  return h5p_themer_theme_resolve($identity['id'], $type, $original);
}

/**
 * Loads the effective global selection, including settings.php overrides.
 */
function h5p_themer_default_selection_load() {
  $settings = h5p_themer_config_load('h5p_themer.settings');
  if ($settings['errors']) {
    return $settings;
  }
  return h5p_themer_selection_resolve($settings['data']['default_theme'], $settings['data']['schema_version']);
}

/**
 * Reads the optional administrator toggle; absent or invalid settings fail closed.
 *
 * Available to later consumers only. Playback does not consult this setting.
 * Missing keys remain missing in configuration; this read never saves/migrates.
 */
function h5p_themer_learner_selection_enabled() {
  $settings = h5p_themer_config_load('h5p_themer.settings');
  return !$settings['errors'] && isset($settings['data']['learner_selection_enabled']) &&
    $settings['data']['learner_selection_enabled'] === TRUE;
}
