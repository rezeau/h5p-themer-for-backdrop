<?php
/** Explicit current contracts plus unchanged historical assertions. GPL-2.0-or-later. */
$v041_root = dirname(__DIR__);
$v041_manifest = json_decode(file_get_contents(__DIR__ . '/fixtures/v041-readiness.json'), TRUE);
$v041_commit = 'af26fe38bfe9c6097c64f45d701e59cabe8b2ff5';
check($v041_manifest['baseline_commit'] === $v041_commit, 'v0.4.1 readiness pins the published v0.4.0 commit');

/** Read only a fixed path from the exact baseline Git object; no checkout/ref writes. */
function readiness_v041_baseline_source($path) {
  $process = proc_open(array('git', '-C', dirname(__DIR__), 'show',
    'af26fe38bfe9c6097c64f45d701e59cabe8b2ff5:' . $path),
    array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
  if (!is_resource($process)) { throw new RuntimeException('Cannot read pinned v0.4.0 Git evidence.'); }
  fclose($pipes[0]); $source = stream_get_contents($pipes[1]); fclose($pipes[1]);
  $error = stream_get_contents($pipes[2]); fclose($pipes[2]);
  if (proc_close($process) !== 0) { throw new RuntimeException('Pinned v0.4.0 evidence unavailable: ' . $path . ': ' . $error); }
  return $source;
}

$v041_old_json = readiness_v041_baseline_source('tests/fixtures/v040-readiness-baseline.json');
$v041_old = json_decode($v041_old_json, TRUE);
$v041_lf = function ($source) { return str_replace("\r\n", "\n", $source); };
check($v041_lf(file_get_contents(__DIR__ . '/fixtures/v040-readiness-baseline.json')) === $v041_lf($v041_old_json),
  'Historical v0.4.0 expected hashes remain byte-for-byte unchanged (LF normalized)');
check($v041_lf(file_get_contents(__DIR__ . '/readiness.php')) === $v041_lf(readiness_v041_baseline_source('tests/readiness.php')),
  'Original historical readiness assertions remain unchanged');

// Run the original suite in its original source context, not against future bytes.
// A private source-only snapshot avoids changing main or protected installations.
$v041_files = array_unique(array_merge(array_keys($v041_old['immutable_lf_sha256']),
  array_keys($v041_old['translated_text_only_signature']), array('h5p_themer.info',
    'README.md', 'docs/installation.md', 'tests/readiness.php', 'tests/fixtures/v040-readiness-baseline.json')));
$v041_temp = sys_get_temp_dir() . '/h5p-themer-v040-readiness-' . bin2hex(random_bytes(12));
if (!mkdir($v041_temp, 0700)) { throw new RuntimeException('Cannot create private historical source snapshot.'); }
$v041_temp = str_replace('\\', '/', realpath($v041_temp));
$v041_written = array(); $v041_dirs = array($v041_temp);
try {
  foreach ($v041_files as $v041_path) {
    if (!preg_match('~\A[a-zA-Z0-9_./-]+\z~', $v041_path) || strpos($v041_path, '..') !== FALSE || $v041_path[0] === '/') {
      throw new RuntimeException('Unexpected historical fixture path.');
    }
    $v041_dest = $v041_temp . '/' . $v041_path;
    $v041_parent = dirname($v041_dest);
    if (!is_dir($v041_parent)) { mkdir($v041_parent, 0700, TRUE); }
    for ($v041_dir = $v041_parent; $v041_dir !== $v041_temp; $v041_dir = dirname($v041_dir)) { $v041_dirs[] = $v041_dir; }
    file_put_contents($v041_dest, readiness_v041_baseline_source($v041_path));
    $v041_written[] = $v041_dest;
  }
  require $v041_temp . '/tests/readiness.php';
}
finally {
  // Delete only our enumerated files/directories within the verified private root.
  foreach ($v041_written as $v041_dest) {
    if (strpos(str_replace('\\', '/', realpath($v041_dest)), $v041_temp . '/') !== 0) { throw new RuntimeException('Historical cleanup escaped fixture root.'); }
    unlink($v041_dest);
  }
  $v041_dirs = array_unique($v041_dirs);
  usort($v041_dirs, function ($a, $b) { return strlen($b) - strlen($a); });
  foreach ($v041_dirs as $v041_dir) {
    $v041_resolved = str_replace('\\', '/', realpath($v041_dir));
    if ($v041_resolved !== $v041_temp && strpos($v041_resolved, $v041_temp . '/') !== 0) { throw new RuntimeException('Historical directory cleanup escaped fixture root.'); }
    rmdir($v041_dir);
  }
}

$v041_start = $passed;
$v041_changed = array('h5p_themer.native-density.inc', 'js/h5p_themer.native-density.js', 'js/h5p_themer.learner.js', 'css/h5p_themer.learner.css');
check(array_keys($v041_manifest['intentional_runtime_lf_sha256']) === $v041_changed, 'Current runtime hash exceptions are an explicit four-file allowlist');
foreach ($v041_manifest['intentional_runtime_lf_sha256'] as $v041_path => $v041_hash) {
  $v041_actual = hash('sha256', $v041_lf(file_get_contents($v041_root . '/' . $v041_path)));
  check($v041_actual === $v041_hash, 'Explicit reviewed v0.4.1 runtime: ' . $v041_path);
  if (isset($v041_old['immutable_lf_sha256'][$v041_path])) {
    check($v041_hash !== $v041_old['immutable_lf_sha256'][$v041_path], 'Intentional current runtime change is distinct from historical bytes: ' . $v041_path);
  }
}
foreach ($v041_old['immutable_lf_sha256'] as $v041_path => $v041_hash) {
  if (in_array($v041_path, $v041_changed, TRUE)) { continue; }
  check(hash('sha256', $v041_lf(file_get_contents($v041_root . '/' . $v041_path))) === $v041_hash,
    'Current schema/color/picker/preview/license/proof invariant preserved: ' . $v041_path);
}
foreach ($v041_old['translated_text_only_signature'] as $v041_path => $v041_hash) {
  check(readiness_executable_signature(file_get_contents($v041_root . '/' . $v041_path)) === $v041_hash,
    'Current PHP executable contract unchanged except translated descriptions: ' . $v041_path);
}
$v041_readme = file_get_contents($v041_root . '/README.md');
check(strpos($v041_readme, '**H5P Themer v0.4.1 development**') !== FALSE && strpos($v041_readme, 'Published baseline: v0.4.0') !== FALSE,
  'Current README distinguishes development from the published baseline');
check(parse_ini_file($v041_root . '/h5p_themer.info', FALSE, INI_SCANNER_RAW)['version'] === '0.4.1',
  'Prepared v0.4.1 module metadata agrees with current readiness without asserting publication');
foreach (array('README.md', 'docs/administration.md', 'docs/configuration.md', 'docs/v0.4.1-readiness.md') as $v041_path) {
  $v041_text = file_get_contents($v041_root . '/' . $v041_path);
  foreach (array('Use theme Density', 'session', 'TrueFalse', 'Live Density Preview') as $v041_term) {
    check(stripos($v041_text, $v041_term) !== FALSE, 'Current guide covers ' . $v041_term . ': ' . $v041_path);
  }
  check(!preg_match('/(?:independent learner Density|learner Density (?:buttons|controls))[^.\n]*\bdeferred\b/i', $v041_text),
    'Current guide does not defer implemented learner Density: ' . $v041_path);
}
$v041_admin = file_get_contents($v041_root . '/h5p_themer.admin.inc');
check(strpos($v041_admin, 'learner Density override') !== FALSE && strpos($v041_admin, 'Use theme Density') !== FALSE,
  'Admin descriptions explain learner precedence and reset');
printf("v0.4.1 readiness: %d passed\n", $passed - $v041_start);
