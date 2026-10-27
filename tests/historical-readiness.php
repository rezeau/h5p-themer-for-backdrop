<?php
// SPDX-License-Identifier: GPL-2.0-or-later.
/** Run the unmodified v0.4.1 readiness suite against its pinned Git source. */
$arguments = getopt('', array('historical-root:', 'backdrop-root:', 'h5p-root:'));
$historical_root = realpath($arguments['historical-root']);
if (!$historical_root) { throw new RuntimeException('Historical source unavailable.'); }
require $historical_root . '/tests/bootstrap.php';
require $historical_root . '/tests/readiness-v041.php';
$future_settings = fixture_settings(); $future_settings['native_density_enabled'] = TRUE;
check((bool) h5p_themer_config_errors('h5p_themer.settings', $future_settings), 'Pinned v0.4.1 rejects the v0.4.2 key: rollback requires pre-upgrade settings');
echo 'HISTORICAL_RESULT:' . json_encode(array('passed' => $passed, 'failed' => $failed)) . "\n";
exit($failed ? 1 : 0);
