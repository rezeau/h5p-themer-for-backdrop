<?php
/** One isolated native Form API request; server fixture is private test storage. */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/admin-bootstrap.php';
require_once $h5p_root . '/h5p.module';
$mapping = h5p_autoload_info(); require_once $h5p_root . '/' . $mapping['H5PCore'];
$options = getopt('', array('backdrop-root:', 'h5p-root:', 'fixture:'));
$fixture = isset($options['fixture']) ? realpath($options['fixture']) : FALSE;
if (!$fixture || is_link($options['fixture']) || basename($fixture) !== 'server.json' ||
    preg_match('/\Ah5p-themer-add-requests-[a-f0-9]{24}\z/', basename(dirname($fixture))) !== 1 ||
    strcasecmp(str_replace('\\', '/', dirname(dirname($fixture))), str_replace('\\', '/', realpath(sys_get_temp_dir()))) !== 0) {
  throw new LogicException('Invalid private request fixture.');
}
$server = json_decode(file_get_contents($fixture), TRUE);
$request = json_decode(stream_get_contents(STDIN), TRUE);
if (!is_array($server) || !is_array($request)) { throw new LogicException('Invalid request data.'); }
define('MAINTENANCE_MODE', 'isolated-form-request');
define('REQUEST_TIME', isset($request['now']) ? $request['now'] : 1000000);
$_SERVER['REQUEST_METHOD'] = isset($request['method']) ? $request['method'] : 'GET';
$GLOBALS['admin_test_session_key'] = isset($request['session']) ? $request['session'] : 'isolated-test-session';
H5PThemerMemoryStorage::$raw = $server['raw'];
H5PThemerMemoryStorage::$writes = $server['writes'];
H5PVisibilityNativeFixture::$schema = $server['schema'];
H5PVisibilityNativeFixture::$states = $server['states'];
H5PVisibilityNativeFixture::$cache = array('states' => array_map('unserialize', $server['states']));
backdrop_static('states', NULL, TRUE);
$GLOBALS['config'] = $server['overrides'];
$GLOBALS['visibility_test_lease_mode'] = TRUE;

// Native form_get_cache/form_set_cache run unchanged. Only tempstore persistence
// and the unrelated framework services below are isolated collaborators.
function tempstore_get($collection, $key) {
  $entry = isset($GLOBALS['server']['tempstore'][$collection][$key]) ? $GLOBALS['server']['tempstore'][$collection][$key] : NULL;
  return $entry !== NULL && $entry['expires'] > REQUEST_TIME ? $entry['data'] : NULL;
}
function tempstore_set($collection, $key, $data, $expires) { $GLOBALS['server']['tempstore'][$collection][$key] = array('data' => $data, 'expires' => $expires); }
function tempstore_clear($collection, $key) { unset($GLOBALS['server']['tempstore'][$collection][$key]); }
function backdrop_html_class($value) { return preg_replace('/[^a-z0-9_-]/i', '-', $value); }

$state = array('input' => isset($request['input']) ? $request['input'] : array(), 'no_redirect' => TRUE,
  'build_info' => array('args' => array('add'), 'files' => array()));
$form = backdrop_build_form('h5p_themer_theme_form', $state);
$server['raw'] = H5PThemerMemoryStorage::$raw;
$server['writes'] = H5PThemerMemoryStorage::$writes;
$server['schema'] = H5PVisibilityNativeFixture::$schema;
$server['states'] = H5PVisibilityNativeFixture::$states;
if (file_put_contents($fixture, json_encode($server)) === FALSE) { throw new RuntimeException('Cannot persist server fixture.'); }
echo json_encode(array('errors' => form_get_errors() ?: array(), 'cache' => $state['cache'],
  'build_id' => isset($form['#build_id']) ? $form['#build_id'] : NULL,
  'token' => isset($form['form_token']['#value']) ? $form['form_token']['#value'] : NULL,
  'snapshot_hash' => isset($state['h5p_themer']['add_snapshot']) ? hash('sha256', serialize($state['h5p_themer']['add_snapshot'])) : NULL,
  'values' => $state['values'], 'redirect' => $state['redirect'], 'markup' => isset($form['#markup']) ? $form['#markup'] : ''));
