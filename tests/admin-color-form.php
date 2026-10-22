<?php
/** Repository-local form callback checks; no Backdrop bootstrap or storage. */
if (PHP_SAPI !== 'cli') { exit('CLI only.'); }
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
  throw new ErrorException($message, 0, $severity, $file, $line);
});

// Read-only collaborators isolate form construction from native Form API/state.
function t($text, array $args = array()) { return strtr($text, $args); }
function check_plain($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function h5p_themer_admin_access() { return TRUE; }
function backdrop_get_path($type, $name) { return 'modules/' . $name; }
function _h5p_themer_publication_form(&$state, $building = FALSE) { return array(); }
function _h5p_themer_admin_check_original(...$args) { return array(); }
function _h5p_themer_admin_actions($form, ...$args) { return $form; }
function _h5p_themer_admin_palette_table(...$args) { return array(); }
function h5p_themer_preview(...$args) { return array(); }
function config_get_config_storage() { throw new LogicException('Storage access is forbidden.'); }
function state_set(...$args) { throw new LogicException('State writes are forbidden.'); }
require dirname(__DIR__) . '/h5p_themer.config.inc';

// Execute the two unmodified production function bodies with the collaborators
// above. Other functions in the admin include require native framework services.
$names = array('_h5p_themer_admin_color_groups', '_h5p_themer_admin_add_context_valid', 'h5p_themer_theme_form');
$tokens = token_get_all(file_get_contents(dirname(__DIR__) . '/h5p_themer.admin.inc'));
$found = array();
for ($i = 0; $i < count($tokens); $i++) {
  if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) { continue; }
  $j = $i + 1;
  while ($j < count($tokens) && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) { $j++; }
  if (!is_array($tokens[$j]) || !in_array($tokens[$j][1], $names, TRUE)) { continue; }
  $name = $tokens[$j][1]; $body = ''; $depth = 0; $started = FALSE;
  for ($j = $i; $j < count($tokens); $j++) {
    $token = $tokens[$j]; $body .= is_array($token) ? $token[1] : $token;
    if ($token === '{') { $depth++; $started = TRUE; }
    if ($token === '}') { $depth--; }
    if ($started && $depth === 0) { break; }
  }
  eval($body); $found[] = $name; $i = $j;
}
if ($found !== $names) { throw new RuntimeException('Production form extraction failed.'); }
$passed = 0;
function form_check($condition, $message) {
  if (!$condition) { throw new RuntimeException($message); }
  $GLOBALS['passed']++;
}
$pinned = array('schema_version' => 2, 'label' => 'Pinned', 'density' => 'large', 'base_preset' => array('id' => 'mint', 'version' => 'moodle-478fed30d2a4'), 'colors' => array());
$legacy = array('schema_version' => 1, 'label' => 'Legacy', 'density' => 'large', 'colors' => array('--h5p-theme-background' => '#AbC'));
foreach (array(array('add', $legacy), array('add', $pinned), array('edit', $legacy), array('duplicate', $legacy), array('edit', $pinned), array('duplicate', $pinned), array('preset_duplicate', $pinned)) as $case) {
  list($operation, $source) = $case;
  $state = array('h5p_themer' => array('operation' => $operation, 'original' => $source, 'id' => 'example'));
  if ($operation === 'add') {
    $preset = isset($source['base_preset']) ? h5p_themer_preset_load('mint', 'moodle-478fed30d2a4')['data'] : NULL;
    $palette = array('label' => $source['label'], 'colors' => array_replace($preset !== NULL ? $preset['colors'] : array(), $source['colors']));
    $state['h5p_themer']['add_snapshot'] = array('seed' => $source, 'fingerprint' => str_repeat('a', 64), 'preset' => $preset, 'palette' => $palette, 'fallback' => FALSE);
  }
  $form = h5p_themer_theme_form(array(), $state);
  if ($operation === 'preset_duplicate') {
    form_check(!isset($form['colors']), 'Initial preset duplication must remain read-only.'); continue;
  }
  $fields = 0;
  foreach ($form['colors'] as $group => $elements) {
    if ($group[0] === '#') { continue; }
    foreach ($elements as $property => $element) {
      if ($property[0] === '#') { continue; }
      $fields++;
      $attributes = $element['#attributes'];
      $unresolved = $operation === 'add' && !isset($palette['colors'][$property]);
      form_check($unresolved ? isset($attributes['data-h5p-themer-picker-initial']) && $attributes['data-h5p-themer-picker-initial'] === '#000000' : !isset($attributes['data-h5p-themer-picker-initial']), 'Only Add fields without a resolved starting color may supply a picker seed.');
      $expected = $operation !== 'add' && isset($source['colors'][$property]) ? $source['colors'][$property] : '';
      form_check($element['#default_value'] === $expected, 'The picker seed must not populate a color textfield.');
      if (isset($source['base_preset'])) {
        $base = h5p_themer_preset_load('mint', 'moodle-478fed30d2a4');
        form_check($attributes['data-h5p-themer-inherited'] === $base['data']['colors'][$property], 'Existing inheritance must remain exact.');
      }
      if ($operation === 'add') {
        form_check($attributes['data-h5p-themer-inherited'] === (isset($palette['colors'][$property]) ? $palette['colors'][$property] : ''), 'Add swatches show captured starting colors without filling overrides.');
      }
    }
  }
  form_check($fields === count(h5p_themer_color_properties()), 'Every allowed color field must be checked.');
  form_check($state['h5p_themer']['original'] === $source, 'Form construction must not change the source definition.');
}
printf("PHP %s local admin color form: %d passed\n", PHP_VERSION, $passed);
