<?php
/** Separate native Form API requests against private memory-backed site fixtures. */
$lifecycle_standalone = !function_exists('check');
if ($lifecycle_standalone) {
  require __DIR__ . '/bootstrap.php'; require __DIR__ . '/admin-bootstrap.php';
  require_once $h5p_root . '/h5p.module';
  $mapping = h5p_autoload_info(); require_once $h5p_root . '/' . $mapping['H5PCore'];
}
function lifecycle_request($file, array $request) {
  global $backdrop_root, $h5p_root;
  $process = proc_open(array(PHP_BINARY, '-n', __DIR__ . '/admin-form-request.php', '--backdrop-root=' . $backdrop_root,
    '--h5p-root=' . $h5p_root, '--fixture=' . $file), array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, NULL, NULL, array('bypass_shell' => TRUE));
  if (!is_resource($process)) { throw new RuntimeException('Cannot start isolated administration request.'); }
  fwrite($pipes[0], json_encode($request)); fclose($pipes[0]);
  $output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
  $code = proc_close($process); $result = json_decode($output, TRUE);
  if ($code !== 0 || !is_array($result)) { throw new RuntimeException('Request failed: ' . $output . $errors); }
  return $result;
}
function lifecycle_fixture($file, $schema = 1) {
  admin_reset(); $theme = fixture_theme('source');
  $theme['colors'] = array('--h5p-theme-background' => '#abc');
  if ($schema === 2) { $theme['schema_version'] = 2; $theme['base_preset'] = array('id' => 'mint', 'version' => 'moodle-478fed30d2a4'); }
  seed('h5p_themer.theme.source', $theme); admin_publication_fixture();
  seed('system.core', array('form_cache_expiration' => 600, 'cache' => FALSE));
  file_put_contents($file, json_encode(array('raw' => H5PThemerMemoryStorage::$raw, 'writes' => H5PThemerMemoryStorage::$writes,
    'schema' => H5PVisibilityNativeFixture::$schema, 'states' => H5PVisibilityNativeFixture::$states, 'overrides' => $GLOBALS['config'], 'tempstore' => array())));
}
function lifecycle_post($kind, array $get, array $values = array()) {
  if ($kind === 'visibility') {
    $input = array('visibility' => array());
    foreach ($get['context']['h5p_themer_visibility_snapshot']['publication']['policy']['themes'] as $token => $visible) {
      if ($visible && !in_array($token, h5p_themer_visibility_fixed_tokens(), TRUE)) { $input['visibility'][$token] = '1'; }
    }
  }
  elseif ($kind === 'default') { $input = array('default_theme' => 'core'); }
  elseif ($kind === 'delete') { $input = array('confirm' => '1'); }
  else { $input = array('id' => $kind === 'edit' ? 'source' : 'copied', 'label' => 'Submitted label', 'colors' => $kind === 'preset_duplicate' ? array() : array('--h5p-theme-background' => '#abc')); }
  return array('kind' => $kind, 'method' => 'POST', 'input' => $values + $input + array('form_id' => $get['form_id'],
    'form_build_id' => $get['build_id'], 'form_token' => $get['token'], 'op' => $get['op']));
}
function lifecycle_change($file, $name, array $changes) {
  $server = json_decode(file_get_contents($file), TRUE);
  $data = json_decode($server['raw']['./active'][$name], TRUE);
  $server['raw']['./active'][$name] = json_encode(array_replace($data, $changes));
  file_put_contents($file, json_encode($server));
}
$lifecycle_before = $passed;
$lifecycle_root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'h5p-themer-admin-requests-' . bin2hex(random_bytes(12));
if (!mkdir($lifecycle_root, 0700)) { throw new RuntimeException('Cannot create private administration fixture.'); }
$lifecycle_file = $lifecycle_root . DIRECTORY_SEPARATOR . 'server.json';
try {
  foreach (array('edit', 'duplicate', 'default', 'delete', 'visibility') as $kind) {
    lifecycle_fixture($lifecycle_file); $get = lifecycle_request($lifecycle_file, array('kind' => $kind));
    $post = lifecycle_post($kind, $get);
    if ($kind === 'default') { lifecycle_change($lifecycle_file, 'h5p_themer.settings', array('learner_selection_enabled' => TRUE)); }
    elseif ($kind === 'visibility') {
      $server = json_decode(file_get_contents($lifecycle_file), TRUE);
      $policy = json_decode($server['raw']['./active']['h5p_themer.learner_visibility'], TRUE);
      $policy['themes']['custom:source'] = FALSE;
      lifecycle_change($lifecycle_file, 'h5p_themer.learner_visibility', $policy);
    }
    else { lifecycle_change($lifecycle_file, 'h5p_themer.theme.source', array('colors' => array('--h5p-theme-background' => '#def'))); }
    $before = json_decode(file_get_contents($lifecycle_file), TRUE);
    $result = lifecycle_request($lifecycle_file, $post); $after = json_decode(file_get_contents($lifecycle_file), TRUE);
    check((bool) $result['errors'] && $result['redirect'] === NULL && $after['raw'] === $before['raw'] && $after['writes'] === $before['writes'], 'Separate-request stale submission rejected without writes: ' . $kind);
  }
  foreach (array('edit', 'duplicate', 'preset_duplicate', 'default', 'delete', 'visibility') as $kind) {
    $key = $kind === 'visibility' ? 'h5p_themer_visibility_snapshot' : ($kind === 'default' ? 'h5p_themer_settings' : 'h5p_themer');
    foreach (array('form_missing', 'state_missing', 'expired', 'context_missing', 'context_invalid', 'context_partial', 'wrong_session', 'missing_id', 'forged_id', 'bad_csrf', 'no_permission') as $scenario) {
      lifecycle_fixture($lifecycle_file); $get = lifecycle_request($lifecycle_file, array('kind' => $kind));
      $server = json_decode(file_get_contents($lifecycle_file), TRUE); $post = lifecycle_post($kind, $get);
      check($get['cache'] && isset($server['tempstore']['form'][$get['build_id']], $server['tempstore']['form_state'][$get['build_id']]), 'Native GET caches trusted context: ' . $kind . '/' . $scenario);
      if ($scenario === 'form_missing') { unset($server['tempstore']['form']); }
      elseif ($scenario === 'state_missing') { unset($server['tempstore']['form_state']); }
      elseif ($scenario === 'expired') { $post['now'] = 1000601; }
      elseif ($scenario === 'context_missing') {
        unset($server['tempstore']['form_state'][$get['build_id']]['data'][$key]);
        $post['input'][$key] = $get['context'][$key]; // Client copies cannot replace trusted server state.
      }
      elseif ($scenario === 'context_invalid') { $server['tempstore']['form_state'][$get['build_id']]['data'][$key] = 'invalid'; }
      elseif ($scenario === 'context_partial') {
        unset($server['tempstore']['form_state'][$get['build_id']]['data'][$key][$kind === 'visibility' ? 'choices' : ($kind === 'default' ? 'schema_version' : 'original')]);
      }
      elseif ($scenario === 'wrong_session') { $post['session'] = 'other-session'; }
      elseif ($scenario === 'missing_id') { unset($post['input']['form_build_id']); }
      elseif ($scenario === 'forged_id') { $post['input']['form_build_id'] = 'form-forged'; }
      elseif ($scenario === 'bad_csrf') { $post['input']['form_token'] = 'forged'; }
      elseif ($scenario === 'no_permission') { $post['no_permission'] = TRUE; }
      file_put_contents($lifecycle_file, json_encode($server));
      $result = lifecycle_request($lifecycle_file, $post); $after = json_decode(file_get_contents($lifecycle_file), TRUE);
      check((bool) $result['errors'] && $result['redirect'] === NULL, 'Separate POST fails closed: ' . $kind . '/' . $scenario);
      check($after['raw'] === $server['raw'] && $after['writes'] === $server['writes'], 'Rejected request cannot write configuration: ' . $kind . '/' . $scenario);
      if (in_array($scenario, array('form_missing', 'state_missing', 'expired', 'wrong_session', 'missing_id', 'forged_id'), TRUE)) {
        check(!isset($result['context'][$key]), 'No replacement POST snapshot: ' . $kind . '/' . $scenario);
      }
    }
    lifecycle_fixture($lifecycle_file); $get = lifecycle_request($lifecycle_file, array('kind' => $kind));
    $rebuilt = lifecycle_request($lifecycle_file, array('kind' => $kind, 'rebuild_id' => $get['build_id']));
    check($rebuilt['build_id'] !== $get['build_id'] && $rebuilt['context'] === $get['context'], 'Native rebuild preserves original context: ' . $kind);
    // Submit errors must keep the cached original available for correction.
    $get = $rebuilt;
    $post = lifecycle_post($kind, $get); $post['locked'] = TRUE;
    $bad = lifecycle_request($lifecycle_file, $post);
    check((bool) $bad['errors'] && $bad['context'] === $get['context'], 'Lock failure preserves original context: ' . $kind);
    $good = lifecycle_request($lifecycle_file, lifecycle_post($kind, $bad));
    check(!$good['errors'] && $good['redirect'] !== NULL, 'Legitimate correction after native rebuild saves: ' . $kind);
    lifecycle_fixture($lifecycle_file); $empty = lifecycle_request($lifecycle_file, array('kind' => $kind, 'method' => 'POST'));
    check(!isset($empty['context'][$key]) && strpos($empty['markup'], 'missing, expired or invalid') !== FALSE, 'Empty direct POST never captures state: ' . $kind);
  }
  foreach (array(1, 2) as $schema) {
    lifecycle_fixture($lifecycle_file, $schema); $get = lifecycle_request($lifecycle_file, array('kind' => 'edit'));
    $source = $get['context']['h5p_themer']['original'];
    $before = json_decode(file_get_contents($lifecycle_file), TRUE);
    $unchanged = lifecycle_request($lifecycle_file, lifecycle_post('edit', $get, array('label' => $source['label'], 'description' => $source['description'], 'colors' => $source['colors'])));
    $after = json_decode(file_get_contents($lifecycle_file), TRUE);
    check(!$unchanged['errors'] && $unchanged['redirect'] !== NULL && $before['raw'] === $after['raw'], 'Unchanged schema-' . $schema . ' Edit remains legitimate');
    foreach (array('edit', 'duplicate') as $kind) {
      lifecycle_fixture($lifecycle_file, $schema); $get = lifecycle_request($lifecycle_file, array('kind' => $kind));
      $post = lifecycle_post($kind, $get, array('colors' => array('--h5p-theme-background' => '#12')));
      $bad = lifecycle_request($lifecycle_file, $post);
      check(isset($bad['errors']['colors][--h5p-theme-background']) && $bad['values']['colors']['--h5p-theme-background'] === '#12' && $bad['context'] === $get['context'], 'Hex validation preserves input and original: ' . $schema . '/' . $kind);
      $before = json_decode(file_get_contents($lifecycle_file), TRUE);
      $good = lifecycle_request($lifecycle_file, lifecycle_post($kind, $bad, array('colors' => array('--h5p-theme-background' => ''))));
      $after = json_decode(file_get_contents($lifecycle_file), TRUE); $id = $kind === 'edit' ? 'source' : 'copied';
      $saved = json_decode($after['raw']['./active']['h5p_themer.theme.' . $id], TRUE);
      check(!$good['errors'] && $saved['colors'] === array() && $saved['schema_version'] === $schema && $saved['density'] === $get['context']['h5p_themer']['original']['density'], 'Edit/Duplicate blank fields clear overrides without migration: ' . $schema . '/' . $kind);
      check($saved['schema_version'] !== 2 || $saved['base_preset'] === $get['context']['h5p_themer']['original']['base_preset'], 'Pinned expressions remain inherited: ' . $schema . '/' . $kind);
      if ($kind === 'duplicate') {
        $policy = json_decode($after['raw']['./active']['h5p_themer.learner_visibility'], TRUE);
        check($policy['themes']['custom:copied'] === FALSE && $after['raw']['./active']['h5p_themer.theme.source'] === $before['raw']['./active']['h5p_themer.theme.source'], 'Duplicate is hidden and preserves source');
      }
      check($after['raw']['./active']['h5p_themer.settings'] === $before['raw']['./active']['h5p_themer.settings'], 'Edit/Duplicate cannot change global settings');
      // A correction must still refer to the first GET after an intervening edit.
      lifecycle_fixture($lifecycle_file, $schema); $get = lifecycle_request($lifecycle_file, array('kind' => $kind));
      $bad = lifecycle_request($lifecycle_file, lifecycle_post($kind, $get, array('label' => '')));
      lifecycle_change($lifecycle_file, 'h5p_themer.theme.source', array('description' => 'Concurrent administrator edit'));
      $result = lifecycle_request($lifecycle_file, lifecycle_post($kind, $bad));
      check((bool) $result['errors'] && $result['context'] === $get['context'], 'Validation correction cannot rebase onto changed source: ' . $schema . '/' . $kind);
    }
  }
  foreach (array('default', 'visibility', 'preset_duplicate') as $kind) {
    lifecycle_fixture($lifecycle_file); $get = lifecycle_request($lifecycle_file, array('kind' => $kind));
    $invalid = $kind === 'default' ? array('default_theme' => 'forged') : ($kind === 'visibility' ? array('visibility' => 'invalid') : array('id' => 'INVALID'));
    $bad = lifecycle_request($lifecycle_file, lifecycle_post($kind, $get, $invalid));
    check((bool) $bad['errors'] && $bad['context'] === $get['context'], 'Ordinary validation retains original context: ' . $kind);
    $good = lifecycle_request($lifecycle_file, lifecycle_post($kind, $bad));
    check(!$good['errors'] && $good['redirect'] !== NULL, 'Corrected ordinary validation succeeds: ' . $kind);
  }
  foreach (array('visibility', 'delete', 'edit', 'duplicate') as $kind) {
    foreach (array('deleted', 'recreated') as $scenario) {
      lifecycle_fixture($lifecycle_file); $get = lifecycle_request($lifecycle_file, array('kind' => $kind));
      // Use actual independent native Delete/Add requests, including tombstones.
      $delete = lifecycle_request($lifecycle_file, array('kind' => 'delete'));
      $deleted = lifecycle_request($lifecycle_file, lifecycle_post('delete', $delete));
      check(!$deleted['errors'], 'Concurrent native deletion succeeds');
      if ($scenario === 'recreated') {
        $copy = lifecycle_request($lifecycle_file, array('kind' => 'preset_duplicate'));
        $created = lifecycle_request($lifecycle_file, lifecycle_post('preset_duplicate', $copy, array('id' => 'source')));
        check(!$created['errors'], 'Concurrent same-ID recreation succeeds');
      }
      $before = json_decode(file_get_contents($lifecycle_file), TRUE);
      $result = lifecycle_request($lifecycle_file, lifecycle_post($kind, $get));
      $after = json_decode(file_get_contents($lifecycle_file), TRUE);
      check((bool) $result['errors'] && $before['raw'] === $after['raw'] && $before['writes'] === $after['writes'], 'Old form cannot act on deleted/recreated identity: ' . $kind . '/' . $scenario);
      $policy = json_decode($after['raw']['./active']['h5p_themer.learner_visibility'], TRUE);
      check($policy['themes']['custom:source'] === FALSE, 'Old form cannot republish tombstoned identity: ' . $kind . '/' . $scenario);
    }
  }
  // Even a hidden, byte-identical replacement is a new publication identity.
  lifecycle_fixture($lifecycle_file);
  $delete = lifecycle_request($lifecycle_file, array('kind' => 'delete'));
  lifecycle_request($lifecycle_file, lifecycle_post('delete', $delete));
  $copy = lifecycle_request($lifecycle_file, array('kind' => 'preset_duplicate'));
  lifecycle_request($lifecycle_file, lifecycle_post('preset_duplicate', $copy, array('id' => 'source')));
  $get = lifecycle_request($lifecycle_file, array('kind' => 'visibility'));
  $post = lifecycle_post('visibility', $get); $post['input']['visibility']['custom:source'] = '1';
  $original = json_decode(file_get_contents($lifecycle_file), TRUE);
  $delete = lifecycle_request($lifecycle_file, array('kind' => 'delete'));
  lifecycle_request($lifecycle_file, lifecycle_post('delete', $delete));
  $copy = lifecycle_request($lifecycle_file, array('kind' => 'preset_duplicate'));
  lifecycle_request($lifecycle_file, lifecycle_post('preset_duplicate', $copy, array('id' => 'source')));
  $before = json_decode(file_get_contents($lifecycle_file), TRUE);
  check($before['raw'] === $original['raw'], 'Native replacement reproduces identical hidden definition/policy/settings bytes');
  $result = lifecycle_request($lifecycle_file, $post); $after = json_decode(file_get_contents($lifecycle_file), TRUE);
  check((bool) $result['errors'] && $after['raw'] === $before['raw'] && $after['writes'] === $before['writes'], 'Old visibility form cannot publish an identical hidden replacement');
  foreach (array('generation_write', 'generation_verify') as $fault) {
    lifecycle_fixture($lifecycle_file); $get = lifecycle_request($lifecycle_file, array('kind' => 'delete'));
    $before = json_decode(file_get_contents($lifecycle_file), TRUE); $post = lifecycle_post('delete', $get); $post['fault'] = $fault;
    $result = lifecycle_request($lifecycle_file, $post); $after = json_decode(file_get_contents($lifecycle_file), TRUE);
    $policy = json_decode($after['raw']['./active']['h5p_themer.learner_visibility'], TRUE);
    check((bool) $result['errors'] && $result['redirect'] === NULL && $after['raw']['./active']['h5p_themer.theme.source'] === $before['raw']['./active']['h5p_themer.theme.source'] &&
      $policy['themes']['custom:source'] === FALSE, 'Failed visibility-form invalidation prevents deletion and retains restrictive policy: ' . $fault);
  }
}
finally { if (is_file($lifecycle_file)) { unlink($lifecycle_file); } rmdir($lifecycle_root); }
printf("Administration request lifecycle: %d passed\n", $passed - $lifecycle_before);
if ($lifecycle_standalone) { printf("PHP %s administration requests: %d passed, %d failed\n", PHP_VERSION, $passed, $failed); exit($failed ? 1 : 0); }
