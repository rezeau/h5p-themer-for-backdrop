<?php
// SPDX-License-Identifier: GPL-2.0-or-later.
/** Historical checks stay intact; new release behavior is tested independently. */
$historical_commit = '02900d937e37319e4023f7016e0d03a1d2b8d5ca';
$historical_temp = sys_get_temp_dir() . '/h5p-themer-v041-history-' . bin2hex(random_bytes(12));
if (!mkdir($historical_temp, 0700)) { throw new RuntimeException('Cannot create historical fixture.'); }
$historical_temp = str_replace('\\', '/', realpath($historical_temp));
$historical_tar = $historical_temp . '/source.tar';
$historical_git = getenv('GIT_DIR');
try {
  $process = proc_open(array('git', '-C', dirname(__DIR__), 'archive', '--format=tar', $historical_commit),
    array(0 => array('pipe', 'r'), 1 => array('file', $historical_tar, 'wb'), 2 => array('pipe', 'w')), $pipes);
  fclose($pipes[0]); $error = stream_get_contents($pipes[2]); fclose($pipes[2]);
  if (proc_close($process) !== 0) { throw new RuntimeException('Pinned history unavailable: ' . $error); }
  $archive = new PharData($historical_tar);
  foreach (new RecursiveIteratorIterator($archive) as $entry) {
    if ($entry->isLink()) { throw new RuntimeException('Unexpected historical link.'); }
  }
  $archive->extractTo($historical_temp);
  unset($archive);
  foreach (array('h5p_themer.presets.inc', 'h5p_themer.runtime.inc', 'h5p_themer.import.inc',
    'h5p_themer.visibility.inc', 'h5p_themer.visibility-activation.inc', 'h5p_themer.visibility-publication.inc',
    'h5p_themer.visibility-admin.inc', 'h5p_themer.learner-runtime.inc',
    // Picker JS now has explicitly approved presentation changes, covered by
    // current behavior suites; pinned historical sources still run unchanged.
    'css/h5p_themer.learner-picker.css',
    'tests/readiness.php', 'tests/readiness-v041.php', 'tests/fixtures/v040-readiness-baseline.json',
    'tests/fixtures/v041-readiness.json', 'docs/release-notes-v0.4.0.md', 'docs/release-notes-v0.4.1.md',
    'docs/v0.4.0-readiness.md', 'docs/v0.4.1-readiness.md') as $protected_path) {
    check(hash_file('sha256', dirname(__DIR__) . '/' . $protected_path) === hash_file('sha256', $historical_temp . '/' . $protected_path),
      'Current immutable schema/runtime/import/visibility/history unchanged: ' . $protected_path);
  }
  $git_process = proc_open(array('git', '-C', dirname(__DIR__), 'rev-parse', '--absolute-git-dir'),
    array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
  fclose($pipes[0]); $git_dir = trim(stream_get_contents($pipes[1])); fclose($pipes[1]); fclose($pipes[2]);
  if (proc_close($git_process) !== 0 || !is_dir($git_dir)) { throw new RuntimeException('Git evidence unavailable.'); }
  putenv('GIT_DIR=' . $git_dir);
  $process = proc_open(array(PHP_BINARY, '-n', __DIR__ . '/historical-readiness.php',
    '--historical-root=' . $historical_temp, '--backdrop-root=' . $backdrop_root, '--h5p-root=' . $h5p_root),
    array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
  fclose($pipes[0]); $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
  $error = stream_get_contents($pipes[2]); fclose($pipes[2]); $status = proc_close($process);
  echo $output;
  check($status === 0 && preg_match('/HISTORICAL_RESULT:(\{[^\n]+\})/', $output, $matches), 'Unmodified historical v0.4.1/v0.4.0 readiness passes on pinned source');
  if ($status !== 0) { throw new RuntimeException('Historical readiness failed: ' . $error); }
  $historical_counts = json_decode($matches[1], TRUE);
  $passed += $historical_counts['passed']; $failed += $historical_counts['failed'];
}
finally {
  $historical_git === FALSE ? putenv('GIT_DIR') : putenv('GIT_DIR=' . $historical_git);
  // Remove only enumerated entries within our verified private temporary root.
  $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($historical_temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
  foreach ($iterator as $entry) {
    $resolved = str_replace('\\', '/', $entry->getRealPath());
    if (strpos($resolved, $historical_temp . '/') !== 0 || $entry->isLink()) { throw new RuntimeException('Historical cleanup escaped fixture root.'); }
    $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
  }
  rmdir($historical_temp);
}
check(parse_ini_file(dirname(__DIR__) . '/h5p_themer.info', FALSE, INI_SCANNER_RAW)['version'] === '0.4.3', 'Prepared module release version is 0.4.3');
$selector_contract = h5p_themer_learner_bundle();
check(!$selector_contract['errors'] && $selector_contract['data']['options'][0]['value'] === 'site_default' &&
  isset($selector_contract['data']['keys']['core'], $selector_contract['data']['keys']['builtin:daylight']),
  'Colour-only presentation preserves the full internal special-state metadata contract');
