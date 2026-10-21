<?php
/**
 * @file
 * Step 2C isolated Form API regressions. GPL-2.0-or-later.
 */
admin_reset(); admin_publication_fixture();
$writes = H5PThemerMemoryStorage::$writes;
$bytes = H5PThemerMemoryStorage::$raw;
$form = admin_form('theme')[1];
check($form['general']['#type'] === 'fieldset' && $form['general']['#collapsible'] === TRUE && $form['general']['#collapsed'] === FALSE, 'General uses a native initially expanded fieldset');
foreach (array('id', 'label', 'description', 'density') as $key) {
  check($form['general'][$key]['#parents'] === array($key) && !isset($form[$key]), 'General preserves flat parent: ' . $key);
}
check($form['#attached']['js'] === array('modules/h5p_themer/js/h5p_themer.admin.js'), 'Only the editable form attaches the picker asset');
$groups = 0;
foreach ($form['colors'] as $key => $group) {
  if ($key[0] === '#') { continue; }
  $groups++;
  check($group['#type'] === 'fieldset' && $group['#collapsible'] === TRUE && $group['#collapsed'] === TRUE, 'Native initially collapsed color group: ' . $key);
  foreach ($group as $property => $element) {
    if ($property[0] === '#') { continue; }
    check($element['#type'] === 'textfield' && $element['#parents'] === array('colors', $property) && $element['#attributes']['data-h5p-themer-inherited'] === '', 'Schema 1 metadata keeps Core unrepresented: ' . $property);
  }
}
check($groups === 5, 'Exactly five collapsed color groups');
$version = h5p_themer_preset_versions()[0];
foreach (h5p_themer_presets($version)['data'] as $id => $preset) {
  seed('h5p_themer.theme.picker_' . $id, fixture_pinned_theme('picker_' . $id, $id));
}
$writes = H5PThemerMemoryStorage::$writes;
$bytes = H5PThemerMemoryStorage::$raw;
foreach (h5p_themer_presets($version)['data'] as $id => $preset) {
  $form = admin_form('theme', 'edit', 'picker_' . $id)[1];
  foreach ($form['colors'] as $key => $group) {
    if ($key[0] === '#') { continue; }
    foreach ($group as $property => $element) {
      if ($property[0] === '#') { continue; }
      check($element['#default_value'] === '' && $element['#attributes']['data-h5p-themer-inherited'] === $preset['colors'][$property], 'Exact inheritance metadata without a saved override: ' . $id . ' ' . $property);
    }
  }
  $copy = admin_form('theme', 'preset_duplicate', $id, $version)[1];
  check(!isset($copy['colors']) && !isset($copy['#attached']['js']) && count($copy['inheritance']['#rows']) === 34, 'Initial preset duplication remains read-only without picker assets: ' . $id);
  check(!isset(h5p_themer_preset_view($id, $version)['#attached']['js']), 'Read-only preset view has no picker asset: ' . $id);
}
check(!isset(admin_form('default')[1]['#attached']['js']), 'Global-default form has no picker asset');
check(H5PThemerMemoryStorage::$raw === $bytes && H5PThemerMemoryStorage::$writes === $writes, 'Building every picker and read-only form makes zero configuration writes');
$input = array('id' => 'picker_mint', 'label' => '', 'description' => 'Unchanged', 'density' => 'small', 'colors' => array('--h5p-theme-background' => '#12', '--h5p-theme-main-cta-base' => ' #aBc'));
$result = admin_post(admin_form('theme', 'edit', 'picker_mint'), $input);
check(isset($result['errors']['label'], $result['errors']['colors][--h5p-theme-background'], $result['errors']['colors][--h5p-theme-main-cta-base']), 'General and multiple color errors retain original keys');
check($result['form']['general']['label']['#name'] === 'label' && $result['form']['general']['density']['#name'] === 'density', 'Native builder retains flat General input names');
check($result['form']['colors']['surfaces']['--h5p-theme-background']['#name'] === 'colors[--h5p-theme-background]', 'Native builder retains color input name');
foreach (array(array('general', 'label'), array('colors', 'surfaces', '--h5p-theme-background'), array('colors', 'main', '--h5p-theme-main-cta-base')) as $keys) {
  $element = $result['form']; foreach ($keys as $key) { $element = $element[$key]; }
  $markup = theme_textfield(array('element' => $element));
  check(preg_match('/class="[^"]*\berror\b/', $markup) === 1, 'Native renderer supplies error class for collapse.js: ' . implode('/', $keys));
  check(strpos($markup, 'type="text"') !== FALSE && strpos($markup, 'type="color"') === FALSE, 'No-JS authoritative textfield remains available: ' . implode('/', $keys));
}
check($result['form']['colors']['main']['--h5p-theme-main-cta-base']['#value'] === ' #aBc', 'Invalid text is never trimmed before validation');
foreach (array('main', 'secondary', 'surfaces', 'text', 'feedback') as $group) {
  check(in_array(array('system', 'backdrop.collapse'), $result['form']['colors'][$group]['#attached']['library'], TRUE), 'Native collapse library attached by Backdrop: ' . $group);
}
check(H5PThemerMemoryStorage::$raw === $bytes && H5PThemerMemoryStorage::$writes === $writes, 'Failed enhanced form makes zero configuration writes');
// Rebuild the real callback and native builder using retained submitted input.
$state = $result['state']; $state['values'] = array();
$rebuilt = h5p_themer_theme_form(array(), $state);
backdrop_prepare_form('h5p_themer_theme_form', $rebuilt, $state);
$rebuilt = form_builder('h5p_themer_theme_form', $rebuilt, $state);
check($rebuilt['colors']['surfaces']['--h5p-theme-background']['#value'] === '#12' && $rebuilt['general']['label']['#value'] === '', 'Rebuilt enhanced form retains submitted invalid input');
check($rebuilt['colors']['surfaces']['--h5p-theme-background']['#attributes']['data-h5p-themer-inherited'] === h5p_themer_preset_load('mint', $version)['data']['colors']['--h5p-theme-background'], 'Rebuild keeps exact pinned inheritance metadata');
foreach (array(array('picker' => '#123456'), array('general' => array('label' => 'Forged')), array('colors' => array('picker' => '#123456'))) as $forged) {
  $candidate = array_replace($input, array('label' => 'Valid', 'colors' => array()), $forged);
  $result = admin_post(admin_form('theme', 'edit', 'picker_mint'), $candidate);
  check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes && H5PThemerMemoryStorage::$writes === $writes, 'Forged picker/group input rejected with zero writes');
}
$h5p_enabled = FALSE;
$disabled = admin_form('theme')[1];
check($disabled['general']['#disabled'] === TRUE && $disabled['colors']['#disabled'] === TRUE, 'Compatibility guard disables General and color containers');
$result = admin_post(admin_form('theme'), array('id' => 'disabled_copy', 'label' => 'Disabled', 'density' => 'small', 'colors' => array('--h5p-theme-background' => '#123')));
check($result['form']['general']['label']['#disabled'] && $result['form']['colors']['surfaces']['--h5p-theme-background']['#disabled'], 'Native builder propagates disabled state to authoritative controls');
$h5p_enabled = TRUE;
check(H5PThemerMemoryStorage::$raw === $bytes && H5PThemerMemoryStorage::$writes === $writes, 'Disabled form cannot write');
$css = file_get_contents($backdrop_root . '/core/modules/system/css/system.css');
check(strpos($css, '.js fieldset.collapsed .fieldset-wrapper') !== FALSE, 'Native collapse hiding is conditional on JavaScript; no-JS fields stay visible');
