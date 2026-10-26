<?php
/**
 * @file
 * Saved, representative previews; no player bootstrap or persistent writes.
 * SPDX-License-Identifier: GPL-2.0-or-later.
 */

/**
 * Builds an administrator-only preview of an explicit saved selection.
 *
 * The default reads effective values, as playback does. Edit forms pass TRUE
 * for $original to show stored values and keep settings.php overrides separate.
 * Submitted form values, URLs, selectors and raw CSS are never accepted here.
 * Core comparisons use the same renderer with NULL, never the Daylight palette.
 * The internal $live flag is enabled only by custom Edit; it adds trusted parent
 * metadata/behavior while this server-rendered frame still contains saved data.
 */
function h5p_themer_preview($selection, $schema_version = 2, $original = FALSE, $live = FALSE) {
  if (!h5p_themer_admin_access()) {
    return array('#markup' => '<p role="alert">' . t('Access denied.') . '</p>');
  }
  $compatibility = h5p_themer_compatibility();
  if (!$compatibility['compatible']) {
    return array('#markup' => '<p role="alert">' . t('Saved theme preview is unavailable. H5P Core API 1.28 is required.') . '</p>');
  }
  $resolved = h5p_themer_selection_resolve($selection, $schema_version, 'active', $original);
  if ($resolved['errors']) {
    return array('#markup' => '<p role="alert">' . t('Saved theme preview is unavailable: the theme reference or definition is invalid. No fallback was selected.') . '</p>');
  }
  $theme = $resolved['data'];
  $serialized = h5p_themer_runtime_serialize($theme);
  if ($serialized['errors']) {
    return array('#markup' => '<p role="alert">' . t('Saved theme preview is unavailable: the theme colors could not be validated.') . '</p>');
  }
  $styles = _h5p_themer_preview_styles();
  if ($styles === NULL) {
    return array('#markup' => '<p role="alert">' . t('Saved theme preview assets are unavailable. The exact palette remains available in the theme settings.') . '</p>');
  }

  // Preserve trusted local CSS bytes in raw-text blocks; escape the complete
  // document only at the srcdoc attribute boundary. No iframe CSS requests rely
  // on Firefox interpreting 'self' in a srcdoc meta policy. Core stays isolated.
  $lang = isset($GLOBALS['language']->langcode) ? $GLOBALS['language']->langcode : 'en';
  $title = t('Saved theme preview') . ': ' . $theme['label'];
  $policy = "default-src 'none'; style-src 'self' 'unsafe-inline'; form-action 'none'; base-uri 'none'";
  $document = '<!doctype html><html lang="' . check_plain($lang) . '"><head><meta charset="utf-8">' .
    '<meta name="viewport" content="width=device-width, initial-scale=1">' .
    '<meta http-equiv="Content-Security-Policy" content="' . check_plain($policy) . '">' .
    '<title>' . check_plain($title) . '</title>' .
    '<style id="h5p-themer-preview-core">' . $styles['core'] . '</style>' .
    '<style id="h5p-themer-preview-sample">' . $styles['sample'] . '</style>' .
    '<style id="h5p-themer-preview-overrides">' . $serialized['data'] . '</style></head><body class="h5p-themer-preview-document">' .
    '<main class="h5p-content h5p-themer-preview-sample">' .
    '<h2>' . check_plain($theme['label']) . '</h2>' .
    _h5p_themer_preview_examples() . '</main></body></html>';

  $build = array(
    '#prefix' => '<section class="h5p-themer-saved-preview" aria-label="' . check_plain($title) . '">',
    '#suffix' => '</section>',
    '#attached' => array('css' => array(backdrop_get_path('module', 'h5p_themer') . '/css/h5p_themer.preview.css')),
    'description' => array('#markup' => '<h3>' . t('Saved theme preview') . '</h3><p>' .
      t('Preview-only examples, not an H5P activity. Libraries and authored styles may look different. This preview does not change the global default. Unsaved form edits are not shown. Density is not applied in this representative color-only preview. Native Density playback support is separate; live Density preview is deferred to Checkpoint 3D.') . '</p>'),
    'sample' => array('#markup' => '<iframe' . backdrop_attributes(array(
      'class' => array('h5p-themer-preview-frame'), 'title' => $title,
      'sandbox' => 'allow-same-origin', 'height' => 640, 'srcdoc' => $document,
    )) . '></iframe>'),
  );
  if ($original && $theme['kind'] === 'custom') {
    $build['stored_notice'] = array('#markup' => '<p>' . t('Showing the current stored theme definition. Any settings.php overrides are excluded from this editing preview.') . '</p>');
  }
  if ($theme['kind'] !== 'core') {
    $build['core_comparison'] = array('#type' => 'fieldset', '#title' => t('Compare with H5P Core defaults'),
      '#collapsible' => TRUE, '#collapsed' => TRUE,
      'preview' => h5p_themer_preview(NULL));
  }
  if ($live && $original && $theme['kind'] === 'custom') {
    $definition = h5p_themer_config_load('h5p_themer.theme.' . $theme['id'], 'active', TRUE);
    if (!$definition['errors']) {
      $inherited = array();
      if ($definition['data']['schema_version'] === 2) {
        $base = $definition['data']['base_preset'];
        $inherited = h5p_themer_preset_load($base['id'], $base['version'])['data']['colors'];
      }
      $properties = h5p_themer_color_properties();
      $metadata = array('properties' => $properties, 'saved' => (object) $definition['data']['colors'],
        'inherited' => (object) array_intersect_key($inherited, array_flip($properties)));
      $build['#prefix'] = '<section' . backdrop_attributes(array('class' => array('h5p-themer-saved-preview', 'h5p-themer-live-preview'),
        'aria-label' => $title, 'data-h5p-themer-preview' => backdrop_json_encode($metadata))) . '>';
      $build['description']['#markup'] = '<h3 class="h5p-themer-preview-heading">' . t('Saved theme preview') . '</h3><p>' .
        t('With JavaScript, valid color edits update this preview immediately. Changes are not saved until Save is clicked. Without JavaScript, the saved preview remains available. Density is not applied in this representative color-only preview. Native Density playback support is separate; live Density preview is deferred to Checkpoint 3D.') . '</p>';
      $build['live_status'] = array('#markup' => '<p class="h5p-themer-preview-status" role="status" aria-live="polite" aria-atomic="true">' .
        t('Saved colors. Changes are not saved until Save is clicked.') . '</p>');
      $build['#attached']['js'][] = backdrop_get_path('module', 'h5p_themer') . '/js/h5p_themer.preview.js';
    }
  }
  return $build;
}

/** Reads two fixed application assets; no request/configuration supplies paths. */
function _h5p_themer_preview_styles() {
  $h5p = backdrop_get_path('module', 'h5p');
  if (!is_string($h5p) || preg_match('~\A[a-zA-Z0-9_.-]+(?:/[a-zA-Z0-9_.-]+)*\z~', $h5p) !== 1) { return NULL; }
  foreach (explode('/', $h5p) as $segment) {
    if ($segment === '.' || $segment === '..') { return NULL; }
  }
  $core = _h5p_themer_preview_read_css(BACKDROP_ROOT . '/' . $h5p . '/library/styles/h5p-theme-variables.css');
  $sample = _h5p_themer_preview_read_css(__DIR__ . '/css/h5p_themer.preview.css');
  return $core === NULL || $sample === NULL ? NULL : array('core' => $core, 'sample' => $sample);
}

/**
 * I/O for renderer-owned local paths only; never pass administrator input.
 *
 * CSS is raw text in HTML. Reject potential style-end tags (case-insensitive,
 * including unusual delimiters), invalid UTF-8 and NUL; never HTML-escape or
 * rewrite the stylesheet. Missing/read failures cannot produce a partial frame.
 */
function _h5p_themer_preview_read_css($path) {
  try {
    if (!is_file($path) || !is_readable($path)) { return NULL; }
    $css = @file_get_contents($path);
    if (!is_string($css) || trim($css) === '' || preg_match('//u', $css) !== 1 ||
        strpos($css, "\0") !== FALSE || stripos($css, '</style') !== FALSE) {
      return NULL;
    }
    return $css;
  }
  catch (Throwable $e) {
    return NULL;
  }
}

/** Original semantic sample markup; translated text is escaped as plain text. */
function _h5p_themer_preview_examples() {
  $text = function ($value) { return check_plain($value); };
  return '<p>' . $text(t('Preview only. Buttons demonstrate appearance and do not perform actions.')) . '</p>' .
    '<div class="h5p-themer-preview-grid"><section class="h5p-themer-preview-surface">' .
    '<h3>' . $text(t('Example heading')) . '</h3><p>' . $text(t('Example body text on a surface.')) . '</p>' .
    '<p class="h5p-themer-preview-secondary-text">' . $text(t('Secondary text')) . '</p>' .
    '<p class="h5p-themer-preview-muted-text">' . $text(t('Muted text')) . '</p>' .
    '<p class="h5p-themer-preview-selected">' . $text(t('Selected answer (example)')) . '</p>' .
    '<div class="h5p-themer-preview-actions"><button type="button" class="h5p-themer-preview-primary">' . $text(t('Primary action example')) . '</button>' .
    '<button type="button" class="h5p-themer-preview-secondary">' . $text(t('Secondary action example')) . '</button></div></section>' .
    '<section class="h5p-themer-preview-surface"><h3>' . $text(t('Feedback examples')) . '</h3>' .
    '<p class="h5p-themer-preview-correct"><strong>' . $text(t('Correct:')) . '</strong> ' . $text(t('Example correct feedback.')) . '</p>' .
    '<p class="h5p-themer-preview-incorrect"><strong>' . $text(t('Incorrect:')) . '</strong> ' . $text(t('Example incorrect feedback.')) . '</p>' .
    '<div class="h5p-themer-preview-progress" role="progressbar" aria-label="' . $text(t('Example progress')) .
    '" aria-valuemin="0" aria-valuemax="5" aria-valuenow="2">2 / 5</div>' .
    '<ul class="h5p-themer-preview-borders"><li>' . $text(t('Border 1')) . '</li><li>' . $text(t('Border 2')) . '</li><li>' . $text(t('Border 3')) . '</li></ul>' .
    '</section></div>';
}
