<?php
/** Schema/state/update API activation tests; no live bootstrap. GPL-3.0-or-later. */
$activation_standalone = !function_exists('check');
if ($activation_standalone) {
  require __DIR__ . '/bootstrap.php'; require __DIR__ . '/admin-bootstrap.php';
  require_once $h5p_root . '/h5p.module';
  $mapping = h5p_autoload_info(); require_once $h5p_root . '/' . $mapping['H5PCore'];
}

function visibility_activation_fixture($schema = 0) {
  admin_reset();
  $GLOBALS['visibility_test_lease_mode'] = TRUE;
  H5PVisibilityNativeFixture::$schema = $schema;
  $GLOBALS['runtime_cache_invalidations'] = 0;
  seed('h5p_themer.settings', array('schema_version' => 2, 'default_theme' => NULL, 'learner_selection_enabled' => TRUE));
  seed('h5p_themer.theme.legacy', fixture_theme('legacy'));
  $custom = fixture_theme('pinned'); $custom['schema_version'] = 2;
  $custom['base_preset'] = array('id' => 'mint', 'version' => 'moodle-478fed30d2a4');
  seed('h5p_themer.theme.pinned', $custom);
}
function visibility_definitions() {
  return array_filter(H5PThemerMemoryStorage::$raw['./active'], function ($name) { return strpos($name, 'h5p_themer.theme.') === 0; }, ARRAY_FILTER_USE_KEY);
}
function visibility_barrier_fixture($expected = TRUE) {
  $context = array('finished' => 1, 'results' => array(), 'sandbox' => array());
  update_do_one('h5p_themer', 1000, array(), $context);
  check(empty($context['results']['#abort']) === $expected, 'Native update runner records expected barrier success/failure');
  return $context;
}
function visibility_assert_closed($label) {
  check(array_keys(h5p_themer_learner_catalog()['data']) === array('site_default', 'core', 'builtin:daylight'), $label);
}
$key = 'h5p_themer.visibility_activation';
visibility_activation_fixture();
$all = h5p_themer_theme_catalog();
$raw = H5PThemerMemoryStorage::$raw; $sw = H5PVisibilityNativeFixture::$stateWrites;
check(h5p_themer_visibility_activation()['state'] === 'legacy', 'Persisted pre-barrier version proves legacy state');
check(h5p_themer_learner_catalog() === $all, 'Never-activated legacy site preserves all nine choices');
check(H5PThemerMemoryStorage::$raw === $raw && H5PVisibilityNativeFixture::$stateWrites === $sw, 'Legacy reads write neither policy nor journal');
$defs = visibility_definitions();
visibility_barrier_fixture();
check(H5PVisibilityNativeFixture::$schema === 1000 && H5PVisibilityNativeFixture::$schemaWrites === 1, 'Actual setter persists barrier before initialization hook');
check(config('h5p_themer.learner_visibility')->isNew(), 'Barrier preparation never installs policy');
$prepared = state_get($key);
check($prepared['status'] === 'prepared' && count($prepared['snapshot']['themes']) === 6, 'Prepared snapshot freezes four optional builtins and both custom schemas');
visibility_assert_closed('Prepared barrier without policy exposes only fixed choices');
$result = h5p_themer_visibility_initialize();
check($result['ok'], 'Guarded initializer succeeds from frozen prepared snapshot');
check(h5p_themer_learner_catalog() === $all, 'Upgrade initialization preserves all existing valid learner choices exactly');
check(visibility_definitions() === $defs, 'Initialization preserves exact custom-theme definition bytes');
check($runtime_cache_invalidations === 1, 'Successful activation invalidates only native page/layout output once');
check(state_get($key)['status'] === 'complete' && state_get($key)['cache_pending'] === FALSE, 'Completion includes verified cache refresh');
check(H5PVisibilityNativeFixture::$schema === 1000, 'Initializer never changes module schema itself');
$context = array('finished' => 1, 'results' => array(), 'sandbox' => array());
update_do_one('h5p_themer', 1001, array(), $context);
check(H5PVisibilityNativeFixture::$schema === 1001, 'Actual update runner persists second version after successful completion');

$policy = config('h5p_themer.learner_visibility')->getOriginal();
$policy['themes']['builtin:mint'] = FALSE; $policy['themes']['custom:legacy'] = FALSE;
seed('h5p_themer.learner_visibility', $policy);
$writes = H5PThemerMemoryStorage::$writes; $states = H5PVisibilityNativeFixture::$stateWrites; $cache_count = $runtime_cache_invalidations;
check(h5p_themer_visibility_initialize()['code'] === 'already_complete', 'Completed initialization is idempotent');
check(H5PThemerMemoryStorage::$writes === $writes && H5PVisibilityNativeFixture::$stateWrites === $states && $runtime_cache_invalidations === $cache_count, 'Completed retry performs no persistence or invalidation');
check(!isset(h5p_themer_learner_catalog()['data']['builtin:mint']) && !isset(h5p_themer_learner_catalog()['data']['custom:legacy']), 'Retry never republishes hidden themes');
config('h5p_themer.settings')->set('default_theme', array('kind' => 'builtin', 'id' => 'mint', 'version' => 'moodle-478fed30d2a4'))->save();
$bundle = h5p_themer_learner_bundle()['data'];
$selector = 'html:not([data-h5p-themer-learner]) .h5p-content, html[data-h5p-themer-learner] .h5p-content:not([data-h5p-themer-eligible])';
check(!isset($bundle['keys']['builtin:mint']) && strpos($bundle['css'], str_replace('.h5p-content', $selector, h5p_themer_runtime_css()['data'])) !== FALSE, 'Hidden global Mint remains effective through Site default after activation');
$writes = H5PThemerMemoryStorage::$writes;
$journal = state_get($key); $journal['cache_pending'] = TRUE; state_set($key, $journal);
check(h5p_themer_visibility_initialize()['ok'], 'Interrupted cache completion can be retried separately');
check(H5PThemerMemoryStorage::$writes === $writes && config('h5p_themer.learner_visibility')->getOriginal('themes')['builtin:mint'] === FALSE, 'Cache-only recovery preserves later hidden choices');
$before = H5PThemerMemoryStorage::$raw; $states = H5PVisibilityNativeFixture::$stateWrites; $schema_writes = H5PVisibilityNativeFixture::$schemaWrites; $read_cache_count = $runtime_cache_invalidations;
h5p_themer_learner_catalog(); h5p_themer_visibility_activation(); h5p_themer_visibility_policy_load();
check(H5PThemerMemoryStorage::$raw === $before && H5PVisibilityNativeFixture::$stateWrites === $states && H5PVisibilityNativeFixture::$schemaWrites === $schema_writes && $runtime_cache_invalidations === $read_cache_count, 'Completed ordinary reads make no application writes or output invalidations');

state_del($key);
visibility_assert_closed('Journal deletion after barrier never restores legacy visibility');
$writes = H5PThemerMemoryStorage::$writes;
check(!h5p_themer_visibility_initialize()['ok'] && !_h5p_themer_visibility_prepare('upgrade'), 'Lost journal is not recaptured or reconstructed after barrier');
check(H5PThemerMemoryStorage::$writes === $writes, 'Lost-journal retry does not overwrite policy');

visibility_activation_fixture();
visibility_barrier_fixture();
$journal = state_get($key); $journal['status'] = 'pending'; state_set($key, $journal);
visibility_assert_closed('Pending journal and absent policy remain closed');
check(h5p_themer_visibility_initialize()['ok'], 'Interrupted pending operation resumes its frozen snapshot');
config('h5p_themer.learner_visibility')->delete();
visibility_assert_closed('Deleted policy after completion cannot restore legacy availability');
$writes = H5PThemerMemoryStorage::$writes;
check(!h5p_themer_visibility_initialize()['ok'] && H5PThemerMemoryStorage::$writes === $writes, 'Completed missing policy requires explicit recovery rather than republication');

foreach (array(NULL, FALSE, 'invalid', array(), array('schema_version' => 1)) as $bad) {
  visibility_activation_fixture(1000); state_set($key, $bad);
  visibility_assert_closed('Malformed persisted journal fails closed');
  check(!h5p_themer_visibility_initialize()['ok'], 'Malformed journal cannot initialize or recover');
}
visibility_activation_fixture();
state_set($key, NULL);
check(state_get($key, 'absent') === 'absent', 'Actual state_get demonstrates stored-NULL/default ambiguity');
visibility_assert_closed('Journal reader distinguishes present NULL even before barrier');
foreach (array(NULL, -1, 'bad', FALSE, 1.5, 1, 500) as $version) {
  visibility_activation_fixture($version);
  check((bool) h5p_themer_visibility_witness()['errors'], 'Missing or malformed installed-schema witness is uncertain');
  visibility_assert_closed('Uncertain schema never proves never-activated legacy status');
}
visibility_activation_fixture(); visibility_barrier_fixture();
$valid_journal = state_get($key);
foreach (array('schema_version' => '1', 'status' => 'wrong', 'mode' => 'other', 'cache_pending' => 0, 'snapshot_hash' => str_repeat('0', 64), 'unexpected' => TRUE) as $field => $value) {
  $bad_journal = $valid_journal; $bad_journal[$field] = $value; state_set($key, $bad_journal);
  check(!h5p_themer_visibility_journal_valid($bad_journal), 'Journal schema/hash rejects malformed field: ' . $field);
  visibility_assert_closed('Malformed journal field cannot restore optional availability');
}
visibility_activation_fixture('1000');
check(h5p_themer_visibility_witness()['version'] === 1000, 'Native database integer-string schema is supported');
visibility_assert_closed('String-valued persisted barrier still closes missing policy');
H5PVisibilityNativeFixture::$fault = 'witness_read';
visibility_assert_closed('Schema read failure fails closed');
H5PVisibilityNativeFixture::$fault = '';
H5PVisibilityNativeFixture::$cache = array();
H5PVisibilityNativeFixture::$fault = 'state_read';
visibility_assert_closed('State API read failure fails closed');
H5PVisibilityNativeFixture::$fault = '';

visibility_activation_fixture();
H5PVisibilityNativeFixture::$fault = 'prepare_write';
$context = visibility_barrier_fixture(FALSE);
check(H5PVisibilityNativeFixture::$schema === 0, 'Failed barrier preparation never records activation-required version');
H5PVisibilityNativeFixture::$fault = '';
check(h5p_themer_visibility_activation()['state'] === 'legacy', 'Failed preparation before persisted barrier leaves never-activated legacy status');

foreach (array('pending_write', 'policy_save', 'policy_save_after', 'policy_verify', 'complete_write', 'final_write', 'final_write_after', 'output_cache') as $fault) {
  visibility_activation_fixture(); $defs = visibility_definitions(); visibility_barrier_fixture();
  H5PVisibilityNativeFixture::$fault = $fault;
  check(!h5p_themer_visibility_initialize()['ok'], 'Persistence failure is reported: ' . $fault);
  visibility_assert_closed('Interrupted persistence never publishes optional themes: ' . $fault);
  check(!$write_locked, 'Failure releases administration lock: ' . $fault);
  check(visibility_definitions() === $defs, 'Failure never rewrites custom definitions: ' . $fault);
  H5PVisibilityNativeFixture::$fault = '';
  if ($fault === 'policy_verify') {
    $writes = H5PThemerMemoryStorage::$writes;
    check(!h5p_themer_visibility_initialize()['ok'] && H5PThemerMemoryStorage::$writes === $writes, 'Malformed partial policy is not automatically repaired or republished');
  }
  else {
    check(h5p_themer_visibility_initialize()['ok'], 'Safe frozen/cache recovery succeeds: ' . $fault);
  }
}

visibility_activation_fixture(); visibility_barrier_fixture();
$journal = state_get($key);
seed('h5p_themer.theme.new_after_snapshot', fixture_theme('new_after_snapshot'));
$writes = H5PThemerMemoryStorage::$writes;
check(!h5p_themer_visibility_initialize()['ok'], 'Concurrent catalog addition rejects frozen initialization');
check(H5PThemerMemoryStorage::$writes === $writes && state_get($key) === $journal, 'Concurrent drift is not recaptured and snapshot stays unchanged');
visibility_assert_closed('Concurrent new theme cannot become public during recovery');

visibility_activation_fixture(); visibility_barrier_fixture();
seed('h5p_themer.learner_visibility', array('schema_version' => 1, 'themes' => array()));
$writes = H5PThemerMemoryStorage::$writes;
check(!h5p_themer_visibility_initialize()['ok'] && H5PThemerMemoryStorage::$writes === $writes, 'Changed hidden policy is never replaced with frozen visible map');
visibility_assert_closed('Recovery conflict retains hidden choices');
foreach (array('schema_version' => 1, 'themes' => array(), '_config_static' => FALSE) as $field => $value) {
  visibility_activation_fixture(); visibility_barrier_fixture();
  $GLOBALS['config']['h5p_themer.learner_visibility'] = array($field => $value);
  $writes = H5PThemerMemoryStorage::$writes;
  check(!h5p_themer_visibility_initialize()['ok'] && H5PThemerMemoryStorage::$writes === $writes, 'Conflicting policy override blocks initialization: ' . $field);
}
visibility_activation_fixture(); visibility_barrier_fixture();
state_set('config_sync', 1);
check(!h5p_themer_visibility_initialize()['ok'], 'Active native config synchronization blocks initialization');
visibility_activation_fixture(); visibility_barrier_fixture();
$write_locked = TRUE;
check(h5p_themer_visibility_initialize()['code'] === 'locked', 'Lock contention does not initialize policy');
$write_locked = FALSE;
$visibility_test_lock_calls = 0; $visibility_test_lease_loss = 2;
check(!h5p_themer_visibility_initialize()['ok'], 'Lost lease is checked before persistence');
visibility_assert_closed('Lease failure retains only fixed choices');

visibility_activation_fixture(1003); $defs = visibility_definitions();
h5p_themer_install();
check(h5p_themer_visibility_activation()['state'] === 'complete', 'Fresh install completes with pre-established native witness');
$map = config('h5p_themer.learner_visibility')->getOriginal('themes');
check(count(array_filter($map)) === 4 && $map['custom:legacy'] === FALSE && $map['custom:pinned'] === FALSE, 'Fresh install initially publishes optional builtins, not orphan custom definitions');
check(visibility_definitions() === $defs, 'Fresh installation never rewrites existing definitions');
$writes = H5PThemerMemoryStorage::$writes; h5p_themer_install();
check(H5PThemerMemoryStorage::$writes === $writes, 'Repeated genuine install callback with valid completion does not recapture');
visibility_activation_fixture(1003);
H5PVisibilityNativeFixture::$fault = 'policy_save';
try { h5p_themer_install(); check(FALSE, 'Fresh failure must be reported'); }
catch (ConfigException $e) { check(TRUE, 'Fresh initialization failure is reported'); }
check(H5PVisibilityNativeFixture::$schema === 1003, 'Fresh failure leaves its independent schema witness persisted');
visibility_assert_closed('Fresh failure cannot appear never-activated');
H5PVisibilityNativeFixture::$fault = '';
check(h5p_themer_visibility_initialize()['ok'], 'Fresh failed operation resumes its original snapshot');

visibility_activation_fixture(1003);
H5PVisibilityNativeFixture::$fault = 'prepare_write';
try { h5p_themer_install(); check(FALSE, 'Fresh preparation failure must be reported'); }
catch (ConfigException $e) { check(TRUE, 'Fresh preparation failure is reported'); }
H5PVisibilityNativeFixture::$fault = '';
visibility_assert_closed('Fresh journal-write failure cannot remove the pre-existing schema barrier');
check(!h5p_themer_visibility_initialize()['ok'], 'Fresh missing snapshot requires recovery review, never fresh capture');
visibility_activation_fixture(); visibility_barrier_fixture();
$GLOBALS['config']['h5p_themer.theme.pinned'] = array('colors' => array('--h5p-theme-main-cta-base' => '#ABCDEF'));
check(!h5p_themer_visibility_initialize()['ok'], 'Effective palette drift rejects the frozen initialization');
visibility_assert_closed('Palette drift never republishes optional themes');

visibility_activation_fixture(); visibility_barrier_fixture();
state_del($key);
$context = array('finished' => 1, 'results' => array(), 'sandbox' => array());
update_do_one('h5p_themer', 1001, array(), $context);
check(!empty($context['results']['#abort']) && H5PVisibilityNativeFixture::$schema === 1000, 'Actual failed initialization update never advances beyond barrier');
check(!is_file(dirname(__DIR__) . '/config/h5p_themer.learner_visibility.json'), 'No early default policy template is shipped');

// Retired imports never initialize, reset or delete old state on any upgrade.
visibility_activation_fixture(); visibility_barrier_fixture();
$context = array('finished' => 1, 'results' => array(), 'sandbox' => array());
update_do_one('h5p_themer', 1001, array(), $context);
$policy = config('h5p_themer.learner_visibility')->getOriginal();
$policy['themes']['custom:legacy'] = FALSE;
config('h5p_themer.learner_visibility')->setData($policy)->save();
$obsolete = 'h5p_themer.import_security';
foreach (array(NULL, array('status' => 'ready'), array('status' => 'applying', 'cache_pending' => TRUE), 'damaged') as $history) {
  H5PVisibilityNativeFixture::$schema = 1001;
  state_set($obsolete, $history);
  $raw = H5PThemerMemoryStorage::$raw;
  $states = H5PVisibilityNativeFixture::$states;
  $invalidations = $runtime_cache_invalidations;
  foreach (array(1002, 1003) as $number) {
    $context = array('finished' => 1, 'results' => array(), 'sandbox' => array());
    update_do_one('h5p_themer', $number, array(), $context);
    check(empty($context['results']['#abort']) && H5PVisibilityNativeFixture::$schema === $number, 'Native runner advances through retired update ' . $number);
  }
  check(h5p_themer_visibility_initialize()['ok'] && !_h5p_themer_admin_guard(), 'Existing 1003 installation works independently of obsolete history');
  $choices = h5p_themer_learner_catalog()['data'];
  check(!isset($choices['custom:legacy']) && isset($choices['custom:pinned']), '1003 preserves hidden and published choices');
  check(H5PThemerMemoryStorage::$raw === $raw && H5PVisibilityNativeFixture::$states === $states && $runtime_cache_invalidations === $invalidations, 'Retired updates and completed reads preserve all configuration, journal bytes and caches');
}
state_del($obsolete);
check(h5p_themer_visibility_policy_load()['state'] === 'initialized' && !_h5p_themer_admin_guard(), 'Missing obsolete history at 1003 does not block visibility or administration');
$visibility_test_lease_mode = FALSE;
admin_reset();
if ($activation_standalone) {
  printf("PHP %s focused activation: %d passed, %d failed\n", PHP_VERSION, $passed, $failed);
  exit($failed ? 1 : 0);
}
