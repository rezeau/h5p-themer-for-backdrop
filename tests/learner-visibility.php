<?php
/** Read-only visibility foundation, memory-only Config. GPL-3.0-or-later. */
learner_reset(TRUE);
seed('h5p_themer.theme.legacy', fixture_theme('legacy'));
seed('h5p_themer.theme.pinned', fixture_pinned_theme('pinned', 'mint'));
$full = h5p_themer_theme_catalog();
$bytes = H5PThemerMemoryStorage::$raw; $writes = H5PThemerMemoryStorage::$writes;
$invalidations = $runtime_cache_invalidations;
check(h5p_themer_visibility_policy_load()['state'] === 'absent', 'Missing policy is the legacy upgrade state');
check(h5p_themer_learner_catalog() === $full, 'Absent policy preserves every existing validated choice');
check(config('h5p_themer.learner_visibility')->isNew(), 'Legacy reads do not initialize policy');
check(H5PThemerMemoryStorage::$raw === $bytes && H5PThemerMemoryStorage::$writes === $writes, 'Legacy reads preserve all exact configuration bytes');
check(!is_file(dirname(__DIR__) . '/config/h5p_themer.learner_visibility.json'), 'No shipped template can activate restrictive policy before upgrade capture');
$fixed = array('site_default', 'core', 'builtin:daylight');
foreach (array_merge($fixed, array('builtin:mint', 'custom:legacy', 'custom:a_2')) as $token) {
  check(h5p_themer_visibility_token($token) === $token, 'Stable token accepted: ' . $token);
}
foreach (array(NULL, 1, array(), 'Mint', 'builtin:Mint', 'custom:../x', 'custom:a.b', "custom:a\n", ' builtin:mint', 'custom:', 'custom:2x', 'custom:' . str_repeat('a', 65)) as $token) {
  check(h5p_themer_visibility_token($token) === NULL, 'Unsafe or noncanonical policy token rejected');
}
$policy = array('schema_version' => 1, 'themes' => array());
check(!h5p_themer_visibility_errors($policy), 'Empty initialized policy is valid');
foreach (array(TRUE, FALSE) as $value) {
  check(!h5p_themer_visibility_errors(array('schema_version' => 1, 'themes' => array('builtin:mint' => $value, 'custom:legacy' => $value))), 'Only actual Boolean policy values accepted');
}
foreach (array(NULL, 0, 1, 'true', 'false', '', array(), new stdClass()) as $value) {
  check((bool) h5p_themer_visibility_errors(array('schema_version' => 1, 'themes' => array('custom:legacy' => $value))), 'Non-Boolean policy entry rejected');
}
foreach (array(NULL, FALSE, 'secret-value', array(), array('schema_version' => '1', 'themes' => array()), array('schema_version' => 2, 'themes' => array()), array('schema_version' => 1, 'themes' => FALSE), $policy + array('extra' => '<script>secret</script>'), $policy + array('_config_name' => NULL), $policy + array('_config_static' => 'true')) as $bad) {
  check((bool) h5p_themer_visibility_errors($bad), 'Malformed policy shape rejected');
  check(strpos(implode(' ', h5p_themer_visibility_errors($bad)), 'secret') === FALSE, 'Diagnostics contain no supplied private values');
}
foreach ($fixed as $token) {
  seed('h5p_themer.learner_visibility', array('schema_version' => 1, 'themes' => array($token => FALSE, 'builtin:mint' => TRUE)));
  check(h5p_themer_visibility_policy_load()['state'] === 'malformed', 'Fixed choice cannot be configured away: ' . $token);
  check(array_keys(h5p_themer_learner_catalog()['data']) === $fixed, 'Malformed policy keeps fixed identities and closes optional choices');
}
seed('h5p_themer.learner_visibility', $policy);
check(h5p_themer_visibility_policy_load()['state'] === 'initialized', 'Present valid policy is initialized');
check(array_keys(h5p_themer_learner_catalog()['data']) === $fixed, 'All optional themes hidden leaves exactly three choices');
$unfiltered = h5p_themer_learner_bundle()['data'];
// Obtain the hidden palette keys from the original canonical token/serializer.
foreach ($full['data'] as $token => $entry) {
  if (in_array($token, $fixed, TRUE)) { continue; }
  $resolved = h5p_themer_selection_resolve($entry['reference']);
  $key = 't' . hash('sha256', $token . "\n" . h5p_themer_runtime_serialize($resolved['data'])['data']);
  check(strpos($unfiltered['css'], 'html[data-h5p-themer-learner="' . $key . '"]') === FALSE, 'Hidden explicit-choice CSS absent: ' . $token);
  check(!isset($unfiltered['keys'][$token]), 'Hidden selection key absent: ' . $token);
  check(!in_array($token, array_column($unfiltered['options'], 'value'), TRUE), 'Hidden option absent: ' . $token);
  check(!isset(h5p_themer_learner_icons()[$token]), 'Hidden icon absent: ' . $token);
}
$ui = h5p_themer_learner_ui_settings();
check(count($ui['options']) === 3 && array_keys($ui['icons']) === $fixed, 'Three-choice UI includes exactly matching icons');
check(array_keys($ui['keys']) === array('builtin:daylight', 'core'), 'Three-choice keys retain Core versus Daylight identities');
check(h5p_themer_theme_catalog() === $full, 'Full administrative catalog remains unfiltered');
check(h5p_themer_theme_load('legacy')['data']['schema_version'] === 1, 'Hidden schema1 definition still loads unchanged');
check(h5p_themer_selection_resolve(array('kind' => 'custom', 'id' => 'pinned'))['data']['colors'] === h5p_themer_selection_resolve($full['data']['custom:pinned']['reference'])['data']['colors'], 'Hidden built-in remains a valid custom inheritance source');
$policy['themes'] = array('custom:pinned' => TRUE, 'builtin:mint' => FALSE, 'custom:legacy' => FALSE);
seed('h5p_themer.learner_visibility', $policy);
$visible = h5p_themer_learner_catalog();
check(isset($visible['data']['custom:pinned']) && !isset($visible['data']['builtin:mint']), 'Visible custom may inherit hidden Mint without exposing Mint as a choice');
check(h5p_themer_learner_icons()['custom:pinned'] === array('#187571', '#EEDAAA'), 'Visible custom icon still uses unchanged resolver and inherited second color');
foreach (array(TRUE, FALSE) as $allowed) {
  $admin_allowed = $allowed; $user->uid = $allowed ? 1 : 0;
  check(h5p_themer_learner_catalog() === $visible, 'Public filtering does not depend on administrative permission');
}
$admin_allowed = TRUE; $user->uid = 1;
$policy['themes']['builtin:mint'] = TRUE;
seed('h5p_themer.learner_visibility', $policy);
check(isset(h5p_themer_learner_catalog()['data']['builtin:mint']), 'Explicit Boolean true publishes a valid optional built-in');
$GLOBALS['config']['h5p_themer.learner_visibility'] = array('themes' => array());
check(array_keys(h5p_themer_learner_catalog()['data']) === $fixed, 'Effective whole-map override is honored');
$GLOBALS['config']['h5p_themer.learner_visibility'] = array('themes' => array('builtin:mint' => 'true'));
check(h5p_themer_visibility_policy_load()['state'] === 'malformed', 'Malformed effective override fails closed');
$GLOBALS['config'] = array();
$policy['themes'] = array('custom:deleted' => TRUE, 'builtin:future' => TRUE);
seed('h5p_themer.learner_visibility', $policy);
check(array_keys(h5p_themer_learner_catalog()['data']) === $fixed, 'Unknown or stale policy entries do not manufacture choices');
seed('h5p_themer.theme.new_theme', fixture_theme('new_theme'));
check(!isset(h5p_themer_learner_catalog()['data']['custom:new_theme']), 'Unlisted new custom is hidden after initialization without changing creation code');
$policy['themes']['custom:new_theme'] = TRUE;
seed('h5p_themer.learner_visibility', $policy);
check(isset(h5p_themer_learner_catalog()['data']['custom:new_theme']), 'Explicit true exposes a defined valid custom');
config('h5p_themer.theme.new_theme')->delete();
check(!isset(h5p_themer_learner_catalog()['data']['custom:new_theme']), 'Deleted custom absent despite stale true');
// Model the required later lifecycle reset; this stage does not hook creation.
$policy['themes']['custom:new_theme'] = FALSE;
seed('h5p_themer.learner_visibility', $policy);
seed('h5p_themer.theme.new_theme', fixture_theme('new_theme'));
check(!isset(h5p_themer_learner_catalog()['data']['custom:new_theme']), 'Explicit lifecycle reset prevents reused ID publication');
$broken = fixture_theme('broken'); $broken['colors'] = array('--h5p-theme-background' => 'url(evil)');
seed('h5p_themer.theme.broken', $broken);
$policy['themes']['custom:broken'] = TRUE; seed('h5p_themer.learner_visibility', $policy);
check(!isset(h5p_themer_learner_catalog()['data']['custom:broken']), 'True cannot bypass the existing theme resolver validation');
foreach (array(array('kind' => 'builtin', 'id' => 'mint', 'version' => 'moodle-478fed30d2a4'), array('kind' => 'custom', 'id' => 'pinned')) as $global) {
  config('h5p_themer.settings')->set('default_theme', $global)->save();
  $catalog = h5p_themer_learner_catalog(); $data = h5p_themer_learner_bundle()['data'];
  $global_css = h5p_themer_runtime_css()['data'];
  $selector = 'html:not([data-h5p-themer-learner]) .h5p-content, html[data-h5p-themer-learner] .h5p-content:not([data-h5p-themer-eligible])';
  preg_match_all('/([^{}]+)\{([^{}]*)\}/', $global_css, $global_blocks, PREG_SET_ORDER);
  $expected_global = '';
  foreach ($global_blocks as $block) {
    $branches = array();
    foreach (explode(', ', $selector) as $root) { $branches[] = str_replace('.h5p-content', $root, trim($block[1])); }
    $expected_global .= implode(', ', $branches) . ' {' . $block[2] . "}\n";
  }
  check(strpos($data['css'], $expected_global) !== FALSE, 'Hidden global remains effective through conditional Site default CSS');
  check(!isset($catalog['data'][$global['kind'] . ':' . $global['id']]), 'Hidden global has no explicit learner choice');
  $pair = h5p_themer_learner_icons()['site_default'];
  check($pair === array('#187571', '#EEDAAA'), 'Site default icon describes hidden global without publishing its explicit token');
}
$bytes = H5PThemerMemoryStorage::$raw; $writes = H5PThemerMemoryStorage::$writes;
$invalidations = $runtime_cache_invalidations;
foreach (array('null', 'true', '[]', '{}', '{broken') as $raw) {
  H5PThemerMemoryStorage::$raw['./active']['h5p_themer.learner_visibility'] = $raw;
  backdrop_static_reset('config');
  check(h5p_themer_visibility_policy_load()['state'] === 'malformed', 'Malformed stored JSON/object fails closed');
  check(array_keys(h5p_themer_learner_catalog()['data']) === $fixed, 'Malformed storage retains three fixed choices');
  $overview = h5p_themer_overview();
  check(isset($overview['warnings']) && strpos($overview['warnings']['#markup'], 'visibility policy is invalid') !== FALSE, 'Existing authorized overview reports safe policy diagnostics');
  check(strpos(json_encode(h5p_themer_learner_ui_settings()), 'visibility policy') === FALSE, 'Diagnostics are absent from public settings');
}
check(H5PThemerMemoryStorage::$writes === $writes && $runtime_cache_invalidations === $invalidations, 'Policy, overview and public projection reads make zero writes or invalidations');
foreach ($bytes['./active'] as $name => $raw) {
  if ($name !== 'h5p_themer.learner_visibility') { check(H5PThemerMemoryStorage::$raw['./active'][$name] === $raw, 'Non-policy configuration bytes unchanged: ' . $name); }
}
H5PCore::$coreApi['minorVersion'] = 27;
check(!h5p_themer_learner_context_allowed(), 'Pre-1.28 integration receives no learner attachment');
H5PCore::$coreApi['minorVersion'] = 28;
H5PThemerMemoryStorage::$failList = TRUE;
check((bool) h5p_themer_learner_catalog()['errors'], 'Unavailable full validated catalog fails closed instead of inventing fixed entries');
H5PThemerMemoryStorage::$failList = FALSE;
admin_reset();
