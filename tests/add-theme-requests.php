<?php
/** Separate PHP processes for GET, POST and corrections; no live site services. */
function add_request_call($file, array $request) {
  global $backdrop_root, $h5p_root;
  $process = proc_open(array(PHP_BINARY, '-n', __DIR__ . '/add-theme-request.php', '--backdrop-root=' . $backdrop_root,
    '--h5p-root=' . $h5p_root, '--fixture=' . $file), array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, NULL, NULL, array('bypass_shell' => TRUE));
  if (!is_resource($process)) { throw new RuntimeException('Cannot start isolated request.'); }
  fwrite($pipes[0], json_encode($request)); fclose($pipes[0]);
  $output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
  $code = proc_close($process); $result = json_decode($output, TRUE);
  if ($code !== 0 || !is_array($result)) { throw new RuntimeException('Request failed: ' . $output . $errors); }
  return $result;
}
function add_request_server($file) {
  seed('system.core', array('form_cache_expiration' => 600, 'cache' => FALSE));
  file_put_contents($file, json_encode(array('raw' => H5PThemerMemoryStorage::$raw, 'writes' => H5PThemerMemoryStorage::$writes,
    'schema' => H5PVisibilityNativeFixture::$schema, 'states' => H5PVisibilityNativeFixture::$states,
    'overrides' => $GLOBALS['config'], 'tempstore' => array())));
}
function add_request_post(array $get, array $values = array()) {
  return array('method' => 'POST', 'input' => $values + array('id' => 'request_copy', 'label' => 'Request copy', 'density' => 'small', 'colors' => array(),
    'form_id' => 'h5p_themer_theme_form', 'form_build_id' => $get['build_id'], 'form_token' => $get['token'], 'op' => 'Save theme'));
}
function add_request_change($file, $name, array $changes) {
  $server = json_decode(file_get_contents($file), TRUE);
  $data = json_decode($server['raw']['./active'][$name], TRUE);
  $server['raw']['./active'][$name] = json_encode(array_replace($data, $changes));
  file_put_contents($file, json_encode($server));
}
$request_root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'h5p-themer-add-requests-' . bin2hex(random_bytes(12));
if (!mkdir($request_root, 0700)) { throw new RuntimeException('Cannot create private request fixture.'); }
$request_file = $request_root . DIRECTORY_SEPARATOR . 'server.json';
try {
  foreach (array(1, 2) as $schema) {
    add_theme_fixture($schema); add_request_server($request_file);
    $get = add_request_call($request_file, array());
    $server = json_decode(file_get_contents($request_file), TRUE);
    check($get['cache'] && isset($server['tempstore']['form'][$get['build_id']], $server['tempstore']['form_state'][$get['build_id']]), 'Native GET persists form and trusted Add snapshot in tempstore');
    $bad = add_request_call($request_file, add_request_post($get, array('colors' => array('--h5p-theme-background' => '#12'))));
    check(isset($bad['errors']['colors][--h5p-theme-background']) && $bad['values']['colors']['--h5p-theme-background'] === '#12', 'Separate POST retains ordinary validation errors and input');
    $good = add_request_call($request_file, add_request_post($bad));
    check(!$good['errors'] && $good['redirect'] !== NULL && $get['snapshot_hash'] === $bad['snapshot_hash'] && $bad['snapshot_hash'] === $good['snapshot_hash'], 'Separate correction preserves the original snapshot and saves');
    $after = json_decode(file_get_contents($request_file), TRUE);
    $saved = json_decode($after['raw']['./active']['h5p_themer.theme.request_copy'], TRUE);
    $policy = json_decode($after['raw']['./active']['h5p_themer.learner_visibility'], TRUE);
    check($saved['schema_version'] === $schema && $saved['colors'] === array('--h5p-theme-background' => '#aBc', '--h5p-theme-text-primary' => '#123456') && $saved['density'] === 'small', 'Separate requests preserve sparse starting colors, schema and density');
    check($policy['themes']['custom:request_copy'] === FALSE && $after['raw']['./active']['h5p_themer.settings'] === $server['raw']['./active']['h5p_themer.settings'] &&
      $after['raw']['./active']['h5p_themer.theme.daylight'] === $server['raw']['./active']['h5p_themer.theme.daylight'], 'Separate-request save stays hidden and preserves source/default bytes');
  }
  foreach (array('form_missing', 'state_missing', 'expired', 'invalid_state', 'seed_changed', 'palette_changed', 'wrong_session', 'missing_id', 'forged_id', 'bad_csrf', 'forged_snapshot', 'source_changed', 'default_changed', 'description_only', 'toggle_only') as $scenario) {
    add_theme_fixture(); add_request_server($request_file); $get = add_request_call($request_file, array());
    $server = json_decode(file_get_contents($request_file), TRUE); $post = add_request_post($get);
    if ($scenario === 'form_missing') { unset($server['tempstore']['form']); }
    elseif ($scenario === 'state_missing') { unset($server['tempstore']['form_state']); }
    elseif ($scenario === 'expired') { $post['now'] = 1000601; }
    elseif ($scenario === 'invalid_state') { unset($server['tempstore']['form_state'][$get['build_id']]['data']['h5p_themer']['add_snapshot']); }
    elseif ($scenario === 'seed_changed') { $server['tempstore']['form_state'][$get['build_id']]['data']['h5p_themer']['original']['colors'] = array(); }
    elseif ($scenario === 'palette_changed') { $server['tempstore']['form_state'][$get['build_id']]['data']['h5p_themer']['add_snapshot']['palette']['colors'] = array(); }
    elseif ($scenario === 'wrong_session') { $post['session'] = 'another-session'; }
    elseif ($scenario === 'missing_id') { unset($post['input']['form_build_id']); }
    elseif ($scenario === 'forged_id') { $post['input']['form_build_id'] = 'form-forged'; }
    elseif ($scenario === 'bad_csrf') { $post['input']['form_token'] = 'invalid'; }
    elseif ($scenario === 'forged_snapshot') { $post['input']['add_snapshot'] = array('fingerprint' => str_repeat('f', 64)); }
    file_put_contents($request_file, json_encode($server));
    if ($scenario === 'source_changed') { add_request_change($request_file, 'h5p_themer.theme.daylight', array('colors' => array('--h5p-theme-background' => '#fff'))); }
    if ($scenario === 'default_changed') { add_request_change($request_file, 'h5p_themer.settings', array('default_theme' => NULL)); }
    if ($scenario === 'description_only') { add_request_change($request_file, 'h5p_themer.theme.daylight', array('description' => 'Unrelated description')); }
    if ($scenario === 'toggle_only') { add_request_change($request_file, 'h5p_themer.settings', array('learner_selection_enabled' => TRUE)); }
    $before = json_decode(file_get_contents($request_file), TRUE); $result = add_request_call($request_file, $post);
    $after = json_decode(file_get_contents($request_file), TRUE);
    if (in_array($scenario, array('description_only', 'toggle_only'), TRUE)) {
      check(!$result['errors'] && $result['snapshot_hash'] === $get['snapshot_hash'], 'Unrelated changes do not stale the captured appearance: ' . $scenario);
    }
    else {
      check((bool) $result['errors'] && $result['redirect'] === NULL, 'Separate POST fails closed: ' . $scenario);
      check($after['raw'] === $before['raw'] && $after['writes'] === $before['writes'], 'Rejected separate POST makes no configuration writes: ' . $scenario);
      if (in_array($scenario, array('form_missing', 'state_missing', 'expired', 'wrong_session', 'missing_id', 'forged_id'), TRUE)) {
        check($result['snapshot_hash'] === NULL, 'Unavailable cached state is never replaced by a new POST snapshot: ' . $scenario);
      }
    }
  }
  foreach (array('legacy', 'typed', 'stored_shadowed', 'effective_legacy', 'effective_typed', 'builtin_same_id') as $scenario) {
    add_theme_fixture();
    if ($scenario === 'legacy' || $scenario === 'effective_legacy') { seed('h5p_themer.settings', fixture_settings($scenario === 'legacy' ? 'missing_default' : NULL)); }
    else { seed('h5p_themer.settings', fixture_typed_settings(in_array($scenario, array('typed', 'stored_shadowed'), TRUE) ? array('kind' => 'custom', 'id' => 'missing_default') : NULL)); }
    if ($scenario === 'stored_shadowed' || $scenario === 'builtin_same_id') {
      $GLOBALS['config']['h5p_themer.settings'] = array('default_theme' => array('kind' => 'builtin', 'id' => 'mint', 'version' => 'moodle-478fed30d2a4'));
    }
    elseif ($scenario === 'effective_legacy') { $GLOBALS['config']['h5p_themer.settings'] = array('default_theme' => 'missing_default'); }
    elseif ($scenario === 'effective_typed') { $GLOBALS['config']['h5p_themer.settings'] = array('default_theme' => array('kind' => 'custom', 'id' => 'missing_default')); }
    add_request_server($request_file); $get = add_request_call($request_file, array());
    $before = json_decode(file_get_contents($request_file), TRUE);
    $result = add_request_call($request_file, add_request_post($get, array('id' => $scenario === 'builtin_same_id' ? 'mint' : 'missing_default')));
    $after = json_decode(file_get_contents($request_file), TRUE);
    if ($scenario === 'builtin_same_id') {
      check(!$result['errors'], 'Built-in identity does not reserve the same custom ID');
    }
    else {
      check(isset($result['errors']['id']) && strpos($result['errors']['id'], 'global default') !== FALSE, 'Separate-request creation rejects reserved missing default ID: ' . $scenario);
      check($after['raw'] === $before['raw'] && $after['writes'] === $before['writes'], 'Stored/effective default-ID rejection preserves all definition/policy/settings bytes: ' . $scenario);
      $result = add_request_call($request_file, add_request_post($result, array('id' => 'unrelated')));
      check(!$result['errors'], 'Correcting the ID permits an unrelated hidden copy: ' . $scenario);
    }
  }
  // Direct POST may have no recognizable form input at all; never seed on POST.
  add_theme_fixture(); add_request_server($request_file);
  $result = add_request_call($request_file, array('method' => 'POST'));
  check($result['snapshot_hash'] === NULL && strpos($result['markup'], 'missing, expired or invalid') !== FALSE, 'Empty direct POST cannot create a new snapshot');
}
finally {
  if (is_file($request_file)) { unlink($request_file); }
  rmdir($request_root);
}
