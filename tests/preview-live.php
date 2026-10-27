<?php
/** Live-preview metadata/Form API checks; memory services only. GPL-2.0-or-later. */
function preview_live_metadata(array $build) {
  if (!preg_match('/data-h5p-themer-preview="([^"]*)"/', $build['#prefix'], $m)) { throw new RuntimeException('Missing live-preview metadata.'); }
  return json_decode(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'), TRUE);
}
admin_reset(); admin_publication_fixture();
$version = h5p_themer_preset_versions()[0];
$theme = fixture_pinned_theme('checkpoint_test', 'mint');
$theme['label'] = 'Copy of Mint'; $theme['colors'] = array('--h5p-theme-main-cta-base' => '#3366CC');
seed('h5p_themer.theme.checkpoint_test', $theme);
$before = preview_services_snapshot(); $routes = h5p_themer_menu();
$bundle = admin_form('theme', 'edit', 'checkpoint_test'); $build = $bundle[1]['saved_preview'];
$meta = preview_live_metadata($build);
check($meta['properties'] === h5p_themer_color_properties() && count($meta['properties']) === 33, 'Client receives the existing canonical 33-property allowlist');
check($meta['saved'] === $theme['colors'] && strpos(preview_css($build), '--h5p-theme-main-cta-base: #3366CC;') !== FALSE, 'Confirmed Copy of Mint starts from saved #3366CC');
foreach (h5p_themer_color_properties() as $property) {
  check($meta['inherited'][$property] === h5p_themer_preset_load('mint', $version)['data']['colors'][$property], 'Exact pinned inheritance supplied for clearing: ' . $property);
}
check(!isset($meta['inherited']['--h5p-theme-contrast-cta-dark']) && count($meta['inherited']) === 33, 'Unsupported catalog-only property never enters live metadata');
check(preview_css($build['core_comparison']['preview']) === '' && !isset($build['core_comparison']['preview']['#attached']['js']), 'Core comparison has no live behavior or overrides');
check(strpos($build['live_status']['#markup'], 'role="status" aria-live="polite" aria-atomic="true"') !== FALSE && strpos($build['description']['#markup'], 'not saved until Save') !== FALSE, 'Accessible status and explicit unsaved explanation');
check(strpos($build['description']['#markup'], 'Without JavaScript') !== FALSE && strpos($build['sample']['#markup'], 'srcdoc=') !== FALSE, 'Complete saved fallback exists before any JavaScript runs');
check(strpos(preview_document($build), '<script') === FALSE && strpos(preview_document($build), '<form') === FALSE && strpos($build['sample']['#markup'], 'sandbox="allow-same-origin"') !== FALSE, 'Live enhancement introduces no iframe script/form/permission');
check($build['#attached']['js'] === array('modules/h5p_themer/js/h5p_themer.preview.js') && $bundle[1]['#attached']['js'] === array('modules/h5p_themer/js/h5p_themer.admin.js'), 'Only Edit adds the new parent asset; native picker asset stays unchanged');
check($bundle[1]['general']['#collapsed'] === FALSE && $bundle[1]['colors']['main']['#collapsed'] === TRUE, 'Native fieldset defaults stay unchanged');
$input = array('id' => 'checkpoint_test', 'label' => 'Copy of Mint', 'description' => '', 'colors' => array('--h5p-theme-main-cta-base' => '#B03060'));
$result = admin_post($bundle, $input, TRUE, FALSE);
check(!$result['errors'] && $result['form']['colors']['main']['--h5p-theme-main-cta-base']['#value'] === '#B03060', 'Native validation accepts a valid draft without saving');
check(h5p_themer_theme_load('checkpoint_test')['data']['colors'] === array('--h5p-theme-main-cta-base' => '#3366CC') && h5p_themer_default_selection_load()['data']['kind'] === 'core', 'Draft cannot persist over confirmed #3366CC or select a global default');
$input['colors']['--h5p-theme-main-cta-base'] = '#12'; $result = admin_post(admin_form('theme', 'edit', 'checkpoint_test'), $input, TRUE, FALSE);
check(isset($result['errors']['colors][--h5p-theme-main-cta-base']) && $result['form']['colors']['main']['--h5p-theme-main-cta-base']['#value'] === '#12', 'Incomplete text remains authoritative and rejected server-side');
check(preview_live_metadata($result['form']['saved_preview'])['saved'] === $theme['colors'], 'Rebuilt enhancement retains real saved data, never draft colors as its saved baseline');
check(preview_services_snapshot() === $before, 'Valid/incomplete drafts and all initial live metadata preserve exact config bytes, default, write count, caches and public-storage calls');
$legacy = fixture_theme('live_legacy'); $legacy['colors'] = array('--h5p-theme-background' => '#aBc'); seed('h5p_themer.theme.live_legacy', $legacy);
$before = preview_services_snapshot(); $legacy_build = admin_form('theme', 'edit', 'live_legacy')[1]['saved_preview']; $legacy_meta = preview_live_metadata($legacy_build);
check($legacy_meta['saved'] === $legacy['colors'] && $legacy_meta['inherited'] === array(), 'Schema 1 supplies sparse saved colors and no synthetic inherited palette');
check(strpos($legacy_build['#prefix'], '&quot;inherited&quot;:{}') !== FALSE, 'Empty inheritance is a JSON object, not an array');
foreach (array('daylight','lavender','mint','sunset','dark') as $id) {
  check(!isset(h5p_themer_preset_view($id, $version)['saved_preview']['#attached']['js']), 'Immutable built-in remains saved-only: ' . $id);
}
check(!isset(admin_form('default')[1]['saved_preview']) && h5p_themer_menu() === $routes, 'Default selection and all routes remain unchanged');
check(preview_services_snapshot() === $before, 'Building enhancements makes zero config/cache/runtime-public/content operations');
check(h5p_themer_theme_load('checkpoint_test')['data'] === $theme, 'Confirmed saved custom configuration is unchanged after all draft checks');
