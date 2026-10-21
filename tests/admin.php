<?php
/**
 * @file
 * Phase 1B administration and native Form API checks. GPL-2.0-or-later.
 */

require __DIR__ . '/admin-bootstrap.php';
$base = 'admin/config/content/h5p-themer';
admin_reset(); admin_publication_fixture();
$routes = h5p_themer_menu();
check(count($routes) === 9, 'Nine protected theme administration routes');
foreach (array('import-definition', 'import-definition/delete', 'import-definition/resume') as $removed) {
  check(!isset($routes[$base . '/' . $removed]), 'Removed import route is absent: ' . $removed);
}
foreach ($routes as $path => $route) {
  check($route['access callback'] === 'h5p_themer_admin_access' && $route['file'] === 'h5p_themer.admin.inc', 'Protected administration route ' . $path);
}
check($routes[$base . '/%/edit']['page arguments'] === array('h5p_themer_theme_form', 'edit', 4), 'Correct edit route parameter');
check($routes[$base . '/%/duplicate']['page arguments'] === array('h5p_themer_theme_form', 'duplicate', 4), 'Correct duplicate route parameter');
check($routes[$base . '/%/delete']['page arguments'] === array('h5p_themer_delete_form', 4), 'Correct delete route parameter');
check(h5p_themer_permission()['administer h5p themes']['restrict access'] === TRUE, 'Dedicated restricted permission');
check(h5p_themer_admin_access(), 'Authorized administrator access');
$admin_allowed = FALSE;
check(!h5p_themer_admin_access(), 'Unprivileged access denied');
check(!isset(h5p_themer_overview()['themes']), 'Overview denied without permission');
$admin_allowed = TRUE;
$user->uid = 0;
check(!h5p_themer_admin_access(), 'Anonymous access denied even with assigned permission');
$user->uid = 1;
$overview = h5p_themer_overview();
check($overview['themes']['#rows'] === array() && !empty($overview['themes']['#empty']), 'Empty collection overview');
check($overview['default_link']['#href'] === $base . '/default', 'Separate default control');
$bundle = admin_form('theme');
$form = $bundle[1];
$properties = array();
foreach ($form['colors'] as $key => $group) {
  if ($key[0] === '#') { continue; }
  foreach ($group as $property => $element) {
    if ($property[0] === '#') { continue; }
    $properties[] = $property;
    check($element['#parents'] === array('colors', $property) && $element['#default_value'] === '' && empty($element['#required']), 'Optional blank color field ' . $property);
  }
}
$expected = h5p_themer_color_properties(); sort($expected); sort($properties);
check($properties === $expected, 'Exactly all 33 supported properties grouped once');

$input = array('id' => 'ocean', 'label' => 'Ocean', 'description' => 'Original description', 'density' => 'medium', 'colors' => array('--h5p-theme-background' => '#abc', '--h5p-theme-text-primary' => ''));
$bytes = H5PThemerMemoryStorage::$raw;
$bad_csrf = admin_post($bundle, $input, FALSE);
check(isset($bad_csrf['errors']['form_token']), 'Native Form API rejects invalid CSRF token');
check(H5PThemerMemoryStorage::$raw === $bytes, 'CSRF rejection makes no writes');
$created = admin_post(admin_form('theme'), $input);
check(!$created['errors'] && $created['state']['redirect'] === $base, 'Create success through native Form API');
check(isset($created['form']['form_token']) && !empty($created['form']['#token']), 'Native preparation adds token control');
$ocean = h5p_themer_theme_load('ocean')['data'];
check($ocean['colors'] === array('--h5p-theme-background' => '#abc'), 'Blank omitted and explicit color preserved');
check($ocean['density'] === 'medium' && $ocean['description'] === 'Original description', 'Create stores validated fields');
check(h5p_themer_default_theme_load()['data'] === NULL, 'Creation does not choose default');
$duplicate_id = admin_post(admin_form('theme'), $input);
check(isset($duplicate_id['errors']['id']), 'Creation rejects duplicate ID');

foreach (array(array('id', '../bad'), array('label', ''), array('density', 'wide'), array('colors', array('--h5p-theme-background' => 'red'))) as $invalid) {
  $candidate = $input; $candidate['id'] = 'invalid_candidate'; $candidate[$invalid[0]] = $invalid[1];
  $bytes = H5PThemerMemoryStorage::$raw;
  $rejected = admin_post(admin_form('theme'), $candidate);
  check((bool) $rejected['errors'], 'Invalid create rejected: ' . $invalid[0]);
  check(H5PThemerMemoryStorage::$raw === $bytes, 'Invalid create preserves stored bytes: ' . $invalid[0]);
  check($rejected['state']['values']['label'] === $candidate['label'], 'Submitted label retained after error');
}
$invalid_color = $input; $invalid_color['id'] = 'bad_color'; $invalid_color['colors']['--h5p-theme-background'] = '#12';
$result = admin_post(admin_form('theme'), $invalid_color);
check(isset($result['errors']['colors][--h5p-theme-background']), 'Color error attached to precise nested field');
check($result['form']['colors']['surfaces']['--h5p-theme-background']['#value'] === '#12', 'Invalid color retained in built field');

$selected = admin_post(admin_form('default'), array('default_theme' => 'custom:ocean'));
check(!$selected['errors'] && config('h5p_themer.settings')->getOriginal('default_theme') === 'ocean', 'Select global default by stable ID');
$edit_bundle = admin_form('theme', 'edit', 'ocean');
check($edit_bundle[1]['general']['id']['#disabled'] === TRUE, 'Edit ID disabled');
check($edit_bundle[1]['colors']['surfaces']['--h5p-theme-background']['#default_value'] === '#abc', 'Existing color defaults preserved');
$renamed = $input; $renamed['label'] = 'Renamed Ocean'; $renamed['colors']['--h5p-theme-background'] = '#ABCDEF';
$result = admin_post($edit_bundle, $renamed);
check(!$result['errors'] && h5p_themer_theme_load('ocean')['data']['label'] === 'Renamed Ocean', 'Label rename saved');
check(h5p_themer_default_theme_load()['data']['id'] === 'ocean', 'Edit preserves identity and default reference');
$tamper = $renamed; $tamper['id'] = 'changed_id';
$bytes = H5PThemerMemoryStorage::$raw;
$result = admin_post(admin_form('theme', 'edit', 'ocean'), $tamper);
check(isset($result['errors']['id']) && H5PThemerMemoryStorage::$raw === $bytes, 'Tampered immutable ID rejected without writes');
$source = h5p_themer_theme_load('ocean')['data'];
$copy_bundle = admin_form('theme', 'duplicate', 'ocean');
check($copy_bundle[1]['general']['id']['#default_value'] === '', 'Duplicate requires a new ID');
check($copy_bundle[1]['general']['density']['#default_value'] === $source['density'], 'Duplicate copies density');
$copy = $renamed; $copy['id'] = 'ocean_copy'; $copy['label'] = 'Ocean copy';
$result = admin_post($copy_bundle, $copy);
check(!$result['errors'] && h5p_themer_theme_load('ocean_copy')['data']['colors'] === $source['colors'], 'Duplicate copies palette');
check(h5p_themer_theme_load('ocean')['data'] === $source && config('h5p_themer.settings')->getOriginal('default_theme') === 'ocean', 'Duplication leaves source and default untouched');
$collision = admin_post(admin_form('theme', 'duplicate', 'ocean'), $renamed);
check(isset($collision['errors']['id']), 'Duplicate rejects existing ID');

$delete_default = admin_form('delete', 'ocean');
check($delete_default[1]['actions']['submit']['#disabled'] === TRUE, 'Default deletion visibly blocked');
$bytes = H5PThemerMemoryStorage::$raw;
$result = admin_post($delete_default, array('confirm' => 1));
check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Default cannot be deleted by crafted POST');
$cleared = admin_post(admin_form('default'), array('default_theme' => 'core'));
check(!$cleared['errors'] && config('h5p_themer.settings')->getOriginal('default_theme') === NULL, 'Clear default to Core defaults');
$delete = admin_form('delete', 'ocean_copy');
check($delete[1]['#theme'] === 'confirm_form' && $delete[1]['confirm']['#value'] === 1, 'Native confirmation form used');
$bytes = H5PThemerMemoryStorage::$raw;
check($bytes === H5PThemerMemoryStorage::$raw, 'Building deletion form makes no writes');
$result = admin_post($delete, array('confirm' => 1));
check(!$result['errors'] && config('h5p_themer.theme.ocean_copy')->isNew(), 'Confirmed deletion removes selected object');
check(h5p_themer_theme_load('ocean')['data'] === $source, 'Deletion preserves other themes');
check(!isset(admin_form('delete', 'ocean_copy')[1]['actions']), 'Already deleted theme handled without action');
check(!isset(admin_form('theme', 'edit', 'missing')[1]['actions']), 'Missing edit target handled safely');

seed('h5p_themer.settings', fixture_settings('missing'));
$bytes = H5PThemerMemoryStorage::$raw;
check(isset(h5p_themer_overview()['warnings']), 'Broken default warning on overview');
$broken = admin_form('default');
check($broken[1]['default_theme']['#default_value'] === '__missing__', 'Broken default never silently selects Core defaults');
$result = admin_post($broken, array('default_theme' => '__missing__'));
check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Unchanged broken selection rejected without repair');
$result = admin_post(admin_form('default'), array('default_theme' => 'not_saved'));
check(isset($result['errors']['default_theme']), 'Unknown default rejected');
$result = admin_post(admin_form('default'), array('default_theme' => 'core'));
check(!$result['errors'] && h5p_themer_default_theme_load()['data'] === NULL, 'Broken reference explicitly cleared');

// Output safety through native option renderer and escaped overview values.
seed('h5p_themer.theme.zeta', array_replace(fixture_theme('zeta'), array('label' => 'same')));
seed('h5p_themer.theme.alpha', array_replace(fixture_theme('alpha'), array('label' => 'same')));
seed('h5p_themer.theme.ocean', array_replace($source, array('label' => '<script>alert(1)</script>')));
$collection = _h5p_themer_admin_collection();
$keys = array_keys($collection['themes']);
check(array_search('alpha', $keys) < array_search('zeta', $keys), 'Equal labels sort by ID');
$overview = h5p_themer_overview();
check(strpos($overview['themes']['#rows'][0][0], '<script>') === FALSE && strpos($overview['themes']['#rows'][0][0], '&lt;script&gt;') !== FALSE, 'Overview escapes stored labels');
$select = admin_form('default')[1]['default_theme']; $select['#value'] = '';
$options_markup = form_select_options($select);
check(strpos($options_markup, '<script>') === FALSE && strpos($options_markup, '&lt;script&gt;') !== FALSE, 'Native select renderer escapes stored labels');
admin_form('delete', 'ocean');
check(strpos($GLOBALS['test_title'], '<script>') === FALSE, 'Confirmation title escapes label');

// Post-build changes are rechecked, not trusted from an earlier validation.
$stale = admin_form('theme', 'edit', 'ocean');
config('h5p_themer.theme.ocean')->set('label', 'Concurrent change')->save();
$bytes = H5PThemerMemoryStorage::$raw;
$result = admin_post($stale, $renamed);
check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Stale edit cannot overwrite newer data');
$stale_default = admin_form('default'); seed('h5p_themer.settings', fixture_settings('alpha'));
$bytes = H5PThemerMemoryStorage::$raw;
$result = admin_post($stale_default, array('default_theme' => 'custom:ocean'));
check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Stale default form cannot overwrite newer choice');
seed('h5p_themer.settings', fixture_settings());
$delete = admin_form('delete', 'ocean'); seed('h5p_themer.settings', fixture_settings('ocean'));
$bytes = H5PThemerMemoryStorage::$raw;
$result = admin_post($delete, array('confirm' => 1));
check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Theme selected as default after confirmation build is protected');

$api_before = H5PCore::$coreApi;
foreach (array('disabled', 'newer') as $failure) {
  seed('h5p_themer.settings', fixture_settings());
  $edit = admin_form('theme', 'edit', 'ocean');
  $default = admin_form('default');
  $delete = admin_form('delete', 'ocean');
  if ($failure === 'disabled') { $h5p_enabled = FALSE; }
  else { H5PCore::$coreApi['minorVersion'] = 29; }
  $bytes = H5PThemerMemoryStorage::$raw;
  check(isset(h5p_themer_overview()['warnings']), 'Incompatible overview diagnostic: ' . $failure);
  check(admin_form('theme')[1]['actions']['submit']['#disabled'] === TRUE, 'Incompatible forms disabled: ' . $failure);
  foreach (array(array($edit, $renamed), array($default, array('default_theme' => 'custom:ocean')), array($delete, array('confirm' => 1))) as $attempt) {
    $result = admin_post($attempt[0], $attempt[1]);
    check((bool) $result['errors'], 'Incompatible post-build submission rejected: ' . $failure);
  }
  check(H5PThemerMemoryStorage::$raw === $bytes, 'Compatibility failure preserves all stored bytes: ' . $failure);
  $h5p_enabled = TRUE; H5PCore::$coreApi = $api_before;
}
$denied = admin_form('theme'); $admin_allowed = FALSE;
$bytes = H5PThemerMemoryStorage::$raw;
$result = admin_post($denied, $input);
check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Permission revoked after build blocks submission');
$admin_allowed = TRUE;
$locked = admin_form('theme'); $write_locked = TRUE;
$candidate = $input; $candidate['id'] = 'lock_test';
$result = admin_post($locked, $candidate);
check((bool) $result['errors'] && !config_get_config_storage()->exists('h5p_themer.theme.lock_test'), 'Busy write lock blocks mutation');
$write_locked = FALSE;

// Force a submit callback without validation: it must still reject bad data.
form_clear_error();
list($form_id, $form, $state) = admin_form('theme');
$state['values'] = array_replace($input, array('id' => 'unchecked', 'density' => 'unsafe'));
$bytes = H5PThemerMemoryStorage::$raw;
form_execute_handlers('submit', $form, $state);
check((bool) form_get_errors() && H5PThemerMemoryStorage::$raw === $bytes, 'Submit callback cannot bypass shared schema validation');
check(!$write_locked, 'Write lock released after validation failure');
form_clear_error();
$GLOBALS['config']['h5p_themer.settings'] = array('default_theme' => 'ocean');
$delete = admin_form('delete', 'ocean');
check($delete[1]['actions']['submit']['#disabled'] === TRUE, 'Effective settings override protects default deletion');
$GLOBALS['config'] = array();
seed('h5p_themer.theme.invalid', array_replace(fixture_theme('invalid'), array('density' => 'invalid')));
$overview = h5p_themer_overview();
check(count($overview['themes']['#rows']) === count(_h5p_themer_admin_collection()['themes']) + 1, 'Invalid saved definitions remain visible');
check(!isset(admin_form('theme', 'edit', 'invalid')[1]['actions']), 'Malformed definitions preserved for configuration repair');

// Guard writes even if the validation callback is bypassed by another caller.
form_clear_error();
list($form_id, $form, $state) = admin_form('default');
$state['values'] = array('default_theme' => 'missing');
$bytes = H5PThemerMemoryStorage::$raw;
form_execute_handlers('submit', $form, $state);
check((bool) form_get_errors() && H5PThemerMemoryStorage::$raw === $bytes, 'Default submit cannot bypass reference validation');
form_clear_error();
list($form_id, $form, $state) = admin_form('delete', 'ocean');
$state['values'] = array('confirm' => 0);
form_execute_handlers('submit', $form, $state);
check((bool) form_get_errors() && H5PThemerMemoryStorage::$raw === $bytes, 'Delete submit requires confirmation');

$GLOBALS['config']['h5p_themer.theme.ocean'] = array('density' => 'small');
$overridden = admin_form('theme', 'edit', 'ocean');
check($overridden[1]['actions']['submit']['#disabled'] === TRUE && isset($overridden[1]['diagnostics']), 'Overridden definition is visibly read-only');
$GLOBALS['config'] = array();
seed('h5p_themer.settings', fixture_settings());
$vanishing = admin_form('default');
config('h5p_themer.theme.alpha')->delete();
$result = admin_post($vanishing, array('default_theme' => 'custom:alpha'));
check((bool) $result['errors'], 'Default target deleted after form build is rejected');
$collision = admin_form('theme'); seed('h5p_themer.theme.race', fixture_theme('race'));
$candidate = $input; $candidate['id'] = 'race';
$result = admin_post($collision, $candidate);
check(isset($result['errors']['id']) && h5p_themer_theme_load('race')['data']['label'] === 'Ocean', 'Post-build ID collision preserves existing definition');
$result = admin_post(admin_form('theme', 'edit', 'ocean'), array_replace($renamed, array('label' => '<b>Quoted "label"</b>')), TRUE, FALSE);
$markup = theme_textfield(array('element' => $result['form']['general']['label']));
check(strpos($markup, '<b>Quoted') === FALSE && strpos($markup, '&lt;b&gt;Quoted') !== FALSE, 'Native textfield rendering escapes label input');

foreach (array('theme', 'default', 'delete') as $kind) {
  $bundle = $kind === 'theme' ? admin_form('theme') : ($kind === 'delete' ? admin_form('delete', 'ocean') : admin_form('default'));
  $input_for_kind = $kind === 'theme' ? array_replace($input, array('id' => 'storage_failure')) : ($kind === 'delete' ? array('confirm' => 1) : array('default_theme' => 'custom:ocean'));
  $bytes = H5PThemerMemoryStorage::$raw;
  H5PThemerMemoryStorage::$failWrite = $kind !== 'delete';
  H5PThemerMemoryStorage::$failDelete = $kind === 'delete';
  $result = admin_post($bundle, $input_for_kind);
  check((bool) $result['errors'] && H5PThemerMemoryStorage::$raw === $bytes, 'Unavailable storage fails safely: ' . $kind);
  check(!$write_locked, 'Write lock released after storage failure: ' . $kind);
  H5PThemerMemoryStorage::$failWrite = FALSE;
  H5PThemerMemoryStorage::$failDelete = FALSE;
}
