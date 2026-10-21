<?php
/**
 * @file
 * Isolated native Form API collaborators. GPL-2.0-or-later.
 */

require_once $backdrop_root . '/core/includes/menu.inc';
require_once $backdrop_root . '/core/includes/form.inc';
require_once $backdrop_root . '/core/includes/ajax.inc';
require_once $backdrop_root . '/core/modules/system/system.module';
require_once dirname(__DIR__) . '/h5p_themer.admin.inc';

// Load selected native common helpers without including common.inc's competing
// service functions. Token parsing is test-only; the original bodies run as-is.
function load_native_helpers($path, array $names) {
  $tokens = token_get_all(file_get_contents($path));
  $found = array();
  for ($i = 0; $i < count($tokens); $i++) {
    if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
      continue;
    }
    $j = $i + 1;
    while ($j < count($tokens) && (!is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING)) { $j++; }
    $name = $j < count($tokens) ? $tokens[$j][1] : '';
    if (!in_array($name, $names, TRUE)) { continue; }
    $body = '';
    $depth = 0;
    $started = FALSE;
    for ($j = $i; $j < count($tokens); $j++) {
      $token = $tokens[$j];
      $body .= is_array($token) ? $token[1] : $token;
      if ($token === '{') { $depth++; $started = TRUE; }
      if ($token === '}') { $depth--; }
      if ($started && $depth === 0) { break; }
    }
    eval($body);
    $found[] = $name;
    $i = $j;
  }
  if (count($found) !== count($names)) { throw new RuntimeException('Native helper extraction incomplete.'); }
}
load_native_helpers($backdrop_root . '/core/includes/common.inc', array('element_children', 'element_set_attributes', 'backdrop_array_get_nested_value', 'backdrop_array_set_nested_value', 'backdrop_array_nested_key_exists', 'backdrop_attributes', 'base_path'));
$GLOBALS['base_path'] = '/';

$user = (object) array('uid' => 1);
$admin_allowed = TRUE;
$messages = array();
$write_locked = FALSE;
define('PASS_THROUGH', -1);
define('WATCHDOG_ERROR', 3);
function user_access($permission) {
  return $permission === 'administer h5p themes' && $GLOBALS['admin_allowed'];
}
function backdrop_static_reset($name) {
  backdrop_static($name, NULL, TRUE);
}
function get_t() { return 't'; }
function backdrop_strlen($value) { return strlen($value); }
function request_uri() { return '/admin/config/content/h5p-themer'; }
function backdrop_random_key() { return bin2hex(random_bytes(12)); }
function backdrop_html_id($value) { return preg_replace('/[^a-z0-9_-]/i', '-', $value); }
function backdrop_get_token($value = '') { return hash_hmac('sha256', $value, 'isolated-test-session'); }
function backdrop_valid_token($token, $value = '', $skip_anonymous = FALSE) { return is_string($token) && hash_equals(backdrop_get_token($value), $token); }
function backdrop_set_title($title, $output = NULL) { $GLOBALS['test_title'] = $title; }
function backdrop_set_message($message, $type = 'status', $repeat = TRUE) { $GLOBALS['messages'][] = array($type, $message); }
function watchdog($type, $message, $variables, $severity) { /* No logger or database. */ }
function lock_acquire($name, $timeout = 30) {
  if ($name === 'state_init') { return TRUE; }
  if ($name !== 'h5p_themer_admin_write') { throw new LogicException('Unexpected lock.'); }
  if (isset($GLOBALS['visibility_test_on_lock'])) {
    $callback = $GLOBALS['visibility_test_on_lock']; unset($GLOBALS['visibility_test_on_lock']); $callback();
  }
  $GLOBALS['visibility_test_lock_calls']++;
  if (!empty($GLOBALS['visibility_test_lease_loss']) && $GLOBALS['visibility_test_lock_calls'] >= $GLOBALS['visibility_test_lease_loss']) {
    $GLOBALS['visibility_test_lock_owned'] = FALSE; return FALSE;
  }
  if ($GLOBALS['write_locked'] && empty($GLOBALS['visibility_test_lock_owned'])) { return FALSE; }
  $GLOBALS['write_locked'] = TRUE;
  $GLOBALS['visibility_test_lock_owned'] = !empty($GLOBALS['visibility_test_lease_mode']);
  return TRUE;
}
function lock_release($name) {
  if ($name === 'state_init') { return; }
  $GLOBALS['write_locked'] = FALSE; $GLOBALS['visibility_test_lock_owned'] = FALSE;
}
function element_info($type) {
  static $types;
  if (!isset($types)) { $types = system_element_info(); }
  return isset($types[$type]) ? $types[$type] + array('#type' => $type) : array();
}

/**
 * Builds our real form callback; native prepare/builder/validation run below.
 */
/** Explicit test setup; production form construction never activates policy. */
function admin_publication_fixture() {
  $invalidations = $GLOBALS['runtime_cache_invalidations'];
  $GLOBALS['visibility_test_lease_mode'] = TRUE;
  if (!_h5p_themer_visibility_prepare('upgrade')) { throw new RuntimeException('Cannot prepare administration fixture.'); }
  H5PVisibilityNativeFixture::$schema = 1000;
  if (!h5p_themer_visibility_initialize()['ok']) { throw new RuntimeException('Cannot activate administration fixture.'); }
  $GLOBALS['runtime_cache_invalidations'] = $invalidations;
}

function admin_form($kind, $operation = 'add', $id = NULL, $version = NULL) {
  form_clear_error();
  backdrop_static_reset('form_set_error:limit_validation_errors');
  $state = form_state_defaults();
  $state['build_info'] = array('args' => array());
  $form_id = $kind === 'visibility' ? 'h5p_themer_overview' : 'h5p_themer_' . $kind . '_form';
  if ($kind === 'visibility') { $form = h5p_themer_overview(array(), $state); }
  elseif ($kind === 'theme') { $form = h5p_themer_theme_form(array(), $state, $operation, $id, $version); }
  elseif ($kind === 'delete') { $form = h5p_themer_delete_form(array(), $state, $operation); }
  else { $form = h5p_themer_default_form(array(), $state); }
  return array($form_id, $form, $state);
}

/**
 * Native Form API processing with a synthesized browser POST and memory Config.
 */
function admin_post(array $bundle, array $input, $token = TRUE, $submit = TRUE) {
  list($form_id, $form, $state) = $bundle;
  form_clear_error();
  backdrop_static_reset('backdrop_validate_form');
  backdrop_static_reset('form_set_error:limit_validation_errors');
  $state['input'] = $input + array('form_id' => $form_id, 'form_token' => $token ? backdrop_get_token($form_id) : 'wrong-token', 'op' => $form['actions']['submit']['#value']);
  $state['values'] = array();
  backdrop_prepare_form($form_id, $form, $state);
  $form = form_builder($form_id, $form, $state);
  backdrop_validate_form($form_id, $form, $state);
  if ($submit && !form_get_errors() && $state['submitted']) { form_execute_handlers('submit', $form, $state); }
  return array('form' => $form, 'state' => $state, 'errors' => form_get_errors());
}

function admin_reset() {
  reset_storage();
  form_clear_error();
  $GLOBALS['admin_allowed'] = TRUE;
  $GLOBALS['user']->uid = 1;
  $GLOBALS['h5p_enabled'] = TRUE;
  $GLOBALS['write_locked'] = FALSE;
  $GLOBALS['visibility_test_lease_mode'] = TRUE;
  seed('h5p_themer.settings', fixture_settings());
}
