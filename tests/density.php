<?php
/** Phase 2A density contracts using existing memory/native fixtures. */
$expected_density = array(
  'small' => array('1.92rem', '1.28rem', '0.96rem', '0.64rem', '0.416rem', '0.32rem'),
  'medium' => array('2.4rem', '1.6rem', '1.2rem', '0.8rem', '0.52rem', '0.4rem'),
  'large' => array('3rem', '2rem', '1.5rem', '1rem', '0.65rem', '0.5rem'),
);
$density_properties = array_map(function ($suffix) { return '--h5p-themer-density-space-' . $suffix; }, array('xl', 'l', 'm', 's', 'xs', 'xxs'));
foreach (array(NULL, FALSE, 1, array(), 'wide', 'Medium', 'small;', "small\n") as $invalid_density) {
  check(h5p_themer_density_tokens($invalid_density) === NULL, 'Unsupported Density cannot become CSS');
}
foreach ($expected_density as $density => $values) {
  check(h5p_themer_density_tokens($density) === array_combine($density_properties, $values), 'Exact ordered spacing tokens: ' . $density);
  foreach (array(1, 2) as $schema) {
    admin_reset(); admin_publication_fixture();
    $theme = $schema === 1 ? fixture_theme('density_test') : fixture_pinned_theme('density_test');
    $theme['density'] = $density; $theme['colors'] = array();
    seed('h5p_themer.theme.density_test', $theme); seed('h5p_themer.settings', fixture_settings('density_test'));
    $stored = H5PThemerMemoryStorage::$raw;
    $css = h5p_themer_runtime_css();
    foreach (array_combine($density_properties, $values) as $property => $value) {
      check(strpos($css['data'], $property . ': ' . $value . ';') !== FALSE, 'Generated exact density: ' . $schema . '/' . $density . '/' . $property);
    }
    preg_match_all('/^  ([^:]+):/m', $css['data'], $declarations);
    check(!$css['errors'] && !array_diff($declarations[1], array_merge(h5p_themer_color_properties(), $density_properties, array('--h5p-themer-density-evaluation-row-factor', 'row-gap'))), 'No protected CSS declaration or native spacing alias introduced');
    check(substr_count($css['data'], '{') === 2 && substr_count($css['data'], '  row-gap:') === 1, 'Exactly one playback spacing consumer alongside the root block');
    $factor = array('small' => '0.8', 'medium' => '1', 'large' => '1.25');
    check(strpos($css['data'], '--h5p-themer-density-evaluation-row-factor: ' . $factor[$density] . ';') !== FALSE, 'Exact relative native baseline factor: ' . $density);
    $expected_path = '.h5p-content[data-h5p-themer-density-ready] > .h5p-container.h5p-question.h5p-theme.h5p-true-false > .h5p-question-evaluation-container.evaluation-mode';
    check(strpos($css['data'], $expected_path . " {\n  row-gap: calc(var(--h5p-theme-spacing-s) * var(--h5p-themer-density-evaluation-row-factor, 1));\n}\n") !== FALSE, 'Exact top-level True/False consumer CSS');
    foreach (array(0.64, 0.8, 1, 1.25) as $native_baseline) {
      if ($density === 'medium') { check(abs($native_baseline * (float) $factor[$density] - $native_baseline) < 0.000001, 'Medium preserves any native baseline'); }
    }
    $build = h5p_themer_preview('density_test', 1, TRUE, TRUE);
    $density_css = preview_style_block($build, 'density');
    check(strpos($density_css, '--h5p-themer-density-space-s: ' . $values[3]) !== FALSE, 'Saved preview owns independent Density block');
    check(strpos(preview_css($build), 'density-space') === FALSE, 'Color preview block never owns Density');
    $metadata = preview_live_metadata($build);
    check($metadata['density'] === $density && $metadata['density_values'][$density] === h5p_themer_density_tokens($density), 'Live metadata matches the server mapping');
    check(H5PThemerMemoryStorage::$raw === $stored, 'Rendering makes no schema, import, visibility or settings writes');
    $before = h5p_themer_runtime_cache_snapshot();
    config('h5p_themer.theme.density_test')->set('density', $density === 'small' ? 'large' : 'small')->save();
    check(h5p_themer_runtime_cache_snapshot() !== $before && h5p_themer_runtime_css()['data'] !== $css['data'], 'Density-only change changes immutable CSS identity');
  }
}
foreach (array(NULL, array('kind' => 'builtin', 'id' => 'mint', 'version' => 'moodle-478fed30d2a4')) as $reference) {
  $resolved = h5p_themer_selection_resolve($reference);
  check(strpos(h5p_themer_runtime_serialize($resolved['data'])['data'], 'density-space') === FALSE, 'Core/built-in has no Density overrides');
  check(strpos(h5p_themer_runtime_serialize($resolved['data'])['data'], 'row-gap') === FALSE && strpos(h5p_themer_runtime_serialize($resolved['data'])['data'], 'evaluation-row-factor') === FALSE, 'Core/built-in has no playback consumer or multiplier');
}
learner_switch_fixture();
$first = h5p_themer_learner_bundle()['data'];
config('h5p_themer.theme.checkpoint_test')->set('density', 'small')->save();
$second = h5p_themer_learner_bundle()['data'];
check($first['keys']['custom:checkpoint_test'] !== $second['keys']['custom:checkpoint_test'] && $first['id'] !== $second['id'], 'Selectable Density-only changes rotate bundle and custom key');
check($first['keys']['core'] === $second['keys']['core'] && $first['keys']['builtin:mint'] === $second['keys']['builtin:mint'], 'Reverting to Core or built-in keeps its independent no-density identity');
foreach ($density_properties as $property) {
  check(strpos($second['css'], $property . ': initial;') !== FALSE, 'Learner neutralization removes stale global Density');
}
$preview_styles = file_get_contents(dirname(__DIR__) . '/css/h5p_themer.preview.css');
check(strpos($preview_styles, 'padding: var(--h5p-theme-spacing-xs) var(--h5p-theme-spacing-s);') !== FALSE, 'Native preview button padding is unchanged');
foreach (array('s' => 0.64, 'xs' => 0.416, 'xxs' => 0.32) as $suffix => $baseline) {
  check(abs((float) h5p_themer_density_tokens('medium')['--h5p-themer-density-space-' . $suffix] * 0.8 - $baseline) < 0.000001, 'Medium matches inspected unclassified preview baseline: ' . $suffix);
}

// Synthetic rules exercise future scoping without enabling a playback consumer.
$scope_rules = array(
  array('paths' => array(array()), 'declarations' => "  --h5p-themer-density-space-s: 0.8rem;\n"),
  array('paths' => array(array('.sample', '.actions.visible'), array('.other')), 'declarations' => "  row-gap: 0.8rem;\n"),
);
$scope_roots = array('.player-a', '.player-b');
$scoped = _h5p_themer_runtime_render_rules($scope_rules, $scope_roots);
check($scoped === ".player-a, .player-b {\n  --h5p-themer-density-space-s: 0.8rem;\n}\n.player-a > .sample > .actions.visible, .player-a > .other, .player-b > .sample > .actions.visible, .player-b > .other {\n  row-gap: 0.8rem;\n}\n", 'Every root and descendant branch is scoped deterministically');
check(_h5p_themer_runtime_render_rules($scope_rules, array('.player-a')) !== _h5p_themer_runtime_render_rules($scope_rules, array('.player-b')), 'Instance scope is explicit and changes rendered identity');
check(_h5p_themer_runtime_render_rules(array(), $scope_roots) === '', 'Core empty rules remain empty for every scope');
foreach (array('.child, .escape', '.child + .sibling', '.child ~ .sibling', ':root', 'body .child', '.child{}', '*', '.child\n') as $unsafe_path) {
  $unsafe_rules = $scope_rules;
  $unsafe_rules[1]['paths'] = array(array($unsafe_path));
  check(_h5p_themer_runtime_render_rules($unsafe_rules, $scope_roots) === NULL, 'Invalid selector path fails closed: ' . $unsafe_path);
}
check(_h5p_themer_runtime_render_rules($scope_rules, array('.player-a, body')) === NULL, 'Root lists must be explicit branches, never comma-joined strings');
check(_h5p_themer_runtime_render_rules($scope_rules, array()) === NULL, 'Missing root cannot emit unscoped rules');
learner_switch_fixture();
$scope_bundle = h5p_themer_learner_bundle()['data'];
check(strpos($scope_bundle['css'], '.sample') === FALSE && strpos($scope_bundle['css'], '.actions.visible') === FALSE, 'Synthetic scope fixtures never become production consumers');
check(strpos($scope_bundle['css'], '--h5p-themer-density-evaluation-row-factor: initial;') !== FALSE, 'Core/built-in restoration neutralizes a stale global row multiplier');
$key = $scope_bundle['keys']['custom:checkpoint_test'];
$scoped_path = 'html[data-h5p-themer-learner="' . $key . '"] .h5p-content[data-h5p-themer-eligible][data-h5p-themer-density-ready] > .h5p-container.h5p-question.h5p-theme.h5p-true-false > .h5p-question-evaluation-container.evaluation-mode';
check(strpos($scope_bundle['css'], $scoped_path . ' {') !== FALSE, 'Consumer requires selected key, eligible root and exact direct-child chain');
check(strpos($scope_bundle['css'], '.h5p-content .h5p-true-false') === FALSE, 'No broad nested True/False descendant selector');
