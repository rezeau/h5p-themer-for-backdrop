<?php
/**
 * @file
 * Immutable preset data extracted on 2026-10-16; no upstream executable code.
 *
 * Daylight/Lavender/Mint/Sunset: Copyright (c) 2026 Oliver Tacke, MIT.
 * Dark: Copyright 2026 Matheus Mathias, GPL-3.0-or-later.
 * Adaptation: literal PHP catalog, separate presentation/provenance metadata.
 * See THIRD-PARTY-NOTICES.md, LICENSE-MIT-H5P-THEME-PICKER.txt and
 * LICENSE-GPL-3.0.md. No accessibility or runtime support claim is implied.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Returns the available immutable versions. Callers must choose one explicitly.
 */
function h5p_themer_preset_versions() {
  return array('moodle-478fed30d2a4');
}

/**
 * Enumerates a pinned catalog. Returns data/errors; never installs configuration.
 *
 * PHP arrays are returned by value (copy on write), never by reference. The
 * private static contains arrays/scalars only, so nested caller edits are safe.
 */
function h5p_themer_presets($version) {
  if (!is_string($version) || !in_array($version, h5p_themer_preset_versions(), TRUE)) {
    return array('data' => NULL, 'errors' => array(t('Unknown H5P preset catalog version.')));
  }
  // A version is append-only: never replace these values under the same key.
  static $catalog = array(
    'daylight' => array(
      'id' => 'daylight',
      'version' => 'moodle-478fed30d2a4',
      'label' => 'Daylight (default)',
      'density' => NULL,
      'colors' => array(
        '--h5p-theme-ui-base' => '#FFFFFF',
        '--h5p-theme-text-primary' => '#101729',
        '--h5p-theme-text-secondary' => '#354054',
        '--h5p-theme-text-third' => '#737373',
        '--h5p-theme-stroke-1' => '#C7D7EF',
        '--h5p-theme-stroke-2' => '#E7EDF6',
        '--h5p-theme-stroke-3' => '#F6F7FA',
        '--h5p-theme-feedback-correct-main' => '#256D1D',
        '--h5p-theme-feedback-correct-secondary' => '#f3fcf0',
        '--h5p-theme-feedback-correct-third' => '#cff1c2',
        '--h5p-theme-feedback-incorrect-main' => '#a13236',
        '--h5p-theme-feedback-incorrect-secondary' => '#faf0f4',
        '--h5p-theme-feedback-incorrect-third' => '#f6dce7',
        '--h5p-theme-feedback-neutral-main' => '#E6C81D',
        '--h5p-theme-feedback-neutral-secondary' => '#5E4817',
        '--h5p-theme-feedback-neutral-third' => '#F0EBCB',
        '--h5p-theme-main-cta-base' => '#006FBF',
        '--h5p-theme-secondary-cta-base' => '#202122',
        '--h5p-theme-alternative-base' => '#F1F5FB',
        '--h5p-theme-background' => '#F9FBFF',
        '--h5p-theme-focus' => '#006FBF',
        '--h5p-theme-main-cta-light' => '#0D74C0',
        '--h5p-theme-main-cta-dark' => '#005FA3',
        '--h5p-theme-contrast-cta' => '#EBF7FF',
        '--h5p-theme-contrast-cta-white' => '#006FBF',
        '--h5p-theme-contrast-cta-light' => 'color-mix(in srgb, var(--h5p-theme-main-cta-base), transparent 90%)',
        '--h5p-theme-contrast-cta-dark' => '#0597FF',
        '--h5p-theme-secondary-cta-light' => '#222324',
        '--h5p-theme-secondary-cta-dark' => '#1F1F20',
        '--h5p-theme-secondary-contrast-cta' => '#E3E9F1',
        '--h5p-theme-secondary-contrast-cta-hover' => '#FFFFFF',
        '--h5p-theme-alternative-light' => '#F7F9FD',
        '--h5p-theme-alternative-dark' => '#DBE5F5',
        '--h5p-theme-alternative-darker' => '#C7D7EF',
      ),
      'presentation' => array(
        'subLabel' => 'WCAG 2.0 AA',
        'backgroundColor' => 'rgb(249, 251, 255)',
        'color' => 'rgb(0, 111, 191)',
      ),
      'unsupported_properties' => array(
        '--h5p-theme-contrast-cta-dark',
      ),
      'provenance' => array(
        'repository' => 'Matheu46/moodle-local_h5pthemer',
        'revision' => '478fed30d2a4af58638f2ddbea3603db1d4d8c5f',
        'path' => 'js/h5p-theme-picker.js',
        'lines' => '1',
        'blob_sha' => '0cd5829b33050f1ee3314fe603911c3644134f2e',
        'license' => 'MIT',
        'copyright' => '2026 Oliver Tacke',
        'picker_version' => '0.0.13',
      ),
    ),
    'lavender' => array(
      'id' => 'lavender',
      'version' => 'moodle-478fed30d2a4',
      'label' => 'Lavender',
      'density' => NULL,
      'colors' => array(
        '--h5p-theme-ui-base' => '#FFFFFF',
        '--h5p-theme-text-primary' => '#101729',
        '--h5p-theme-text-secondary' => '#1b1346',
        '--h5p-theme-text-third' => '#737373',
        '--h5p-theme-stroke-1' => '#ead8f6',
        '--h5p-theme-stroke-2' => '#E7EDF6',
        '--h5p-theme-stroke-3' => '#F6F7FA',
        '--h5p-theme-feedback-correct-main' => '#256D1D',
        '--h5p-theme-feedback-correct-secondary' => '#f3fcf0',
        '--h5p-theme-feedback-correct-third' => '#cff1c2',
        '--h5p-theme-feedback-incorrect-main' => '#a13236',
        '--h5p-theme-feedback-incorrect-secondary' => '#faf0f4',
        '--h5p-theme-feedback-incorrect-third' => '#f6dce7',
        '--h5p-theme-feedback-neutral-main' => '#E6C81D',
        '--h5p-theme-feedback-neutral-secondary' => '#5E4817',
        '--h5p-theme-feedback-neutral-third' => '#F0EBCB',
        '--h5p-theme-main-cta-base' => '#834DD5',
        '--h5p-theme-secondary-cta-base' => '#000000',
        '--h5p-theme-alternative-base' => '#f3edff',
        '--h5p-theme-background' => '#F3EEFA',
        '--h5p-theme-focus' => '#F1EBFA',
        '--h5p-theme-main-cta-light' => '#8347DD',
        '--h5p-theme-main-cta-dark' => '#692EC2',
        '--h5p-theme-contrast-cta' => '#ffffff',
        '--h5p-theme-contrast-cta-white' => '#834DD5',
        '--h5p-theme-contrast-cta-light' => 'color-mix(in srgb, var(--h5p-theme-main-cta-base), transparent 90%)',
        '--h5p-theme-contrast-cta-dark' => '#A983E2',
        '--h5p-theme-secondary-cta-light' => '#333333',
        '--h5p-theme-secondary-cta-dark' => '#311B50',
        '--h5p-theme-secondary-contrast-cta' => '#F3F1F5',
        '--h5p-theme-secondary-contrast-cta-hover' => '#ffffff',
        '--h5p-theme-alternative-light' => '#f8f5ff',
        '--h5p-theme-alternative-dark' => '#e8d9ff',
        '--h5p-theme-alternative-darker' => '#ddc7ff',
      ),
      'presentation' => array(
        'subLabel' => 'WCAG 2.0 AA',
        'backgroundColor' => 'rgb(243, 238, 250)',
        'color' => 'rgb(131, 77, 213)',
      ),
      'unsupported_properties' => array(
        '--h5p-theme-contrast-cta-dark',
      ),
      'provenance' => array(
        'repository' => 'Matheu46/moodle-local_h5pthemer',
        'revision' => '478fed30d2a4af58638f2ddbea3603db1d4d8c5f',
        'path' => 'js/h5p-theme-picker.js',
        'lines' => '1',
        'blob_sha' => '0cd5829b33050f1ee3314fe603911c3644134f2e',
        'license' => 'MIT',
        'copyright' => '2026 Oliver Tacke',
        'picker_version' => '0.0.13',
      ),
    ),
    'mint' => array(
      'id' => 'mint',
      'version' => 'moodle-478fed30d2a4',
      'label' => 'Mint',
      'density' => NULL,
      'colors' => array(
        '--h5p-theme-ui-base' => '#FFFFFF',
        '--h5p-theme-text-primary' => '#101729',
        '--h5p-theme-text-secondary' => '#05252e',
        '--h5p-theme-text-third' => '#737373',
        '--h5p-theme-stroke-1' => '#cceae8',
        '--h5p-theme-stroke-2' => '#E7EDF6',
        '--h5p-theme-stroke-3' => '#F6F7FA',
        '--h5p-theme-feedback-correct-main' => '#256D1D',
        '--h5p-theme-feedback-correct-secondary' => '#f3fcf0',
        '--h5p-theme-feedback-correct-third' => '#cff1c2',
        '--h5p-theme-feedback-incorrect-main' => '#a13236',
        '--h5p-theme-feedback-incorrect-secondary' => '#faf0f4',
        '--h5p-theme-feedback-incorrect-third' => '#f6dce7',
        '--h5p-theme-feedback-neutral-main' => '#E6C81D',
        '--h5p-theme-feedback-neutral-secondary' => '#5E4817',
        '--h5p-theme-feedback-neutral-third' => '#F0EBCB',
        '--h5p-theme-main-cta-base' => '#187571',
        '--h5p-theme-secondary-cta-base' => '#000000',
        '--h5p-theme-alternative-base' => '#FAF4E6',
        '--h5p-theme-background' => '#FCF9F2',
        '--h5p-theme-focus' => '#F1EBFA',
        '--h5p-theme-main-cta-light' => '#1C7E79',
        '--h5p-theme-main-cta-dark' => '#004D45',
        '--h5p-theme-contrast-cta' => '#ffffff',
        '--h5p-theme-contrast-cta-white' => '#17948b',
        '--h5p-theme-contrast-cta-light' => 'color-mix(in srgb, var(--h5p-theme-main-cta-base), transparent 90%)',
        '--h5p-theme-contrast-cta-dark' => '#1AA89E',
        '--h5p-theme-secondary-cta-light' => '#333333',
        '--h5p-theme-secondary-cta-dark' => '#311B50',
        '--h5p-theme-secondary-contrast-cta' => '#F3F1F5',
        '--h5p-theme-secondary-contrast-cta-hover' => '#ffffff',
        '--h5p-theme-alternative-light' => '#FCF9F2',
        '--h5p-theme-alternative-dark' => '#F2E3BF',
        '--h5p-theme-alternative-darker' => '#EEDAAA',
      ),
      'presentation' => array(
        'subLabel' => 'WCAG 2.0 AA',
        'backgroundColor' => 'rgb(252, 249, 242)',
        'color' => 'rgb(23, 148, 139)',
      ),
      'unsupported_properties' => array(
        '--h5p-theme-contrast-cta-dark',
      ),
      'provenance' => array(
        'repository' => 'Matheu46/moodle-local_h5pthemer',
        'revision' => '478fed30d2a4af58638f2ddbea3603db1d4d8c5f',
        'path' => 'js/h5p-theme-picker.js',
        'lines' => '1',
        'blob_sha' => '0cd5829b33050f1ee3314fe603911c3644134f2e',
        'license' => 'MIT',
        'copyright' => '2026 Oliver Tacke',
        'picker_version' => '0.0.13',
      ),
    ),
    'sunset' => array(
      'id' => 'sunset',
      'version' => 'moodle-478fed30d2a4',
      'label' => 'Sunset',
      'density' => NULL,
      'colors' => array(
        '--h5p-theme-ui-base' => '#FFFFFF',
        '--h5p-theme-text-primary' => '#101729',
        '--h5p-theme-text-secondary' => '#2c0f03',
        '--h5p-theme-text-third' => '#737373',
        '--h5p-theme-stroke-1' => '#f2dbd4',
        '--h5p-theme-stroke-2' => '#E7EDF6',
        '--h5p-theme-stroke-3' => '#F6F7FA',
        '--h5p-theme-feedback-correct-main' => '#256D1D',
        '--h5p-theme-feedback-correct-secondary' => '#f3fcf0',
        '--h5p-theme-feedback-correct-third' => '#cff1c2',
        '--h5p-theme-feedback-incorrect-main' => '#a13236',
        '--h5p-theme-feedback-incorrect-secondary' => '#faf0f4',
        '--h5p-theme-feedback-incorrect-third' => '#f6dce7',
        '--h5p-theme-feedback-neutral-main' => '#E6C81D',
        '--h5p-theme-feedback-neutral-secondary' => '#5E4817',
        '--h5p-theme-feedback-neutral-third' => '#F0EBCB',
        '--h5p-theme-main-cta-base' => '#D63A0A',
        '--h5p-theme-secondary-cta-base' => '#172326',
        '--h5p-theme-alternative-base' => '#FFF2ED',
        '--h5p-theme-background' => '#FFF2ED',
        '--h5p-theme-focus' => '#F1EBFA',
        '--h5p-theme-main-cta-light' => '#DC3A06',
        '--h5p-theme-main-cta-dark' => '#AF320B',
        '--h5p-theme-contrast-cta' => '#ffffff',
        '--h5p-theme-contrast-cta-white' => '#D63A0A',
        '--h5p-theme-contrast-cta-light' => 'color-mix(in srgb, var(--h5p-theme-main-cta-base), transparent 90%)',
        '--h5p-theme-contrast-cta-dark' => '#F66437',
        '--h5p-theme-secondary-cta-light' => '#263A3F',
        '--h5p-theme-secondary-cta-dark' => '#121A1D',
        '--h5p-theme-secondary-contrast-cta' => '#F3F1F5',
        '--h5p-theme-secondary-contrast-cta-hover' => '#ffffff',
        '--h5p-theme-alternative-light' => '#FDF8F7',
        '--h5p-theme-alternative-dark' => '#F2DBD4',
        '--h5p-theme-alternative-darker' => '#FFCFBA',
      ),
      'presentation' => array(
        'subLabel' => 'WCAG 2.0 AA',
        'backgroundColor' => 'rgb(255, 242, 237)',
        'color' => 'rgb(214, 58, 10)',
      ),
      'unsupported_properties' => array(
        '--h5p-theme-contrast-cta-dark',
      ),
      'provenance' => array(
        'repository' => 'Matheu46/moodle-local_h5pthemer',
        'revision' => '478fed30d2a4af58638f2ddbea3603db1d4d8c5f',
        'path' => 'js/h5p-theme-picker.js',
        'lines' => '1',
        'blob_sha' => '0cd5829b33050f1ee3314fe603911c3644134f2e',
        'license' => 'MIT',
        'copyright' => '2026 Oliver Tacke',
        'picker_version' => '0.0.13',
      ),
    ),
    'dark' => array(
      'id' => 'dark',
      'version' => 'moodle-478fed30d2a4',
      'label' => 'Dark',
      'density' => NULL,
      'colors' => array(
        '--h5p-theme-background' => '#16191d',
        '--h5p-theme-ui-base' => '#21252b',
        '--h5p-theme-alternative-base' => '#2a2f37',
        '--h5p-theme-alternative-light' => '#323842',
        '--h5p-theme-alternative-dark' => '#1a1d22',
        '--h5p-theme-alternative-darker' => '#121417',
        '--h5p-theme-text-primary' => '#f0f3f6',
        '--h5p-theme-text-secondary' => '#c9d1d9',
        '--h5p-theme-text-third' => '#8b949e',
        '--h5p-theme-stroke-1' => '#3b434f',
        '--h5p-theme-stroke-2' => '#2e343e',
        '--h5p-theme-stroke-3' => '#22272e',
        '--h5p-theme-main-cta-base' => '#3b82f6',
        '--h5p-theme-main-cta-light' => '#60a5fa',
        '--h5p-theme-main-cta-dark' => '#2563eb',
        '--h5p-theme-contrast-cta' => '#ffffff',
        '--h5p-theme-contrast-cta-white' => '#3b82f6',
        '--h5p-theme-contrast-cta-light' => 'rgba(59, 130, 246, 0.15)',
        '--h5p-theme-contrast-cta-dark' => '#93c5fd',
        '--h5p-theme-secondary-cta-base' => '#374151',
        '--h5p-theme-secondary-cta-light' => '#4b5563',
        '--h5p-theme-secondary-cta-dark' => '#1f2937',
        '--h5p-theme-secondary-contrast-cta' => '#f3f4f6',
        '--h5p-theme-secondary-contrast-cta-hover' => '#ffffff',
        '--h5p-theme-feedback-correct-main' => '#34d399',
        '--h5p-theme-feedback-correct-secondary' => '#064e3b',
        '--h5p-theme-feedback-correct-third' => '#047857',
        '--h5p-theme-feedback-incorrect-main' => '#f87171',
        '--h5p-theme-feedback-incorrect-secondary' => '#450a0a',
        '--h5p-theme-feedback-incorrect-third' => '#991b1b',
        '--h5p-theme-feedback-neutral-main' => '#fbbf24',
        '--h5p-theme-feedback-neutral-secondary' => '#451a03',
        '--h5p-theme-feedback-neutral-third' => '#78350f',
        '--h5p-theme-focus' => '#60a5fa',
      ),
      'presentation' => array(
        'backgroundColor' => '#f1f5f9',
        'color' => '#1e293b',
      ),
      'unsupported_properties' => array(
        '--h5p-theme-contrast-cta-dark',
      ),
      'provenance' => array(
        'repository' => 'Matheu46/moodle-local_h5pthemer',
        'revision' => '478fed30d2a4af58638f2ddbea3603db1d4d8c5f',
        'path' => 'amd/src/presets.js',
        'lines' => '33-71',
        'blob_sha' => '2529338c1ffdec331d84045772fa67e3bdf3c11a',
        'license' => 'GPL-3.0-or-later',
        'copyright' => '2026 Matheus Mathias',
      ),
    ),
  );
  return array('data' => $catalog, 'errors' => array());
}

/**
 * Looks up an exact built-in identity without a fallback or storage access.
 */
function h5p_themer_preset_load($id, $version) {
  $catalog = h5p_themer_presets($version);
  if ($catalog['errors']) {
    return $catalog;
  }
  if (!is_string($id) || !array_key_exists($id, $catalog['data'])) {
    return array('data' => NULL, 'errors' => array(t('Unknown H5P built-in preset ID.')));
  }
  return array('data' => $catalog['data'][$id], 'errors' => array());
}
