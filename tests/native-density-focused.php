<?php
/** Site-free native Density PHP checks. SPDX-License-Identifier: GPL-2.0-or-later. */
require __DIR__ . '/bootstrap.php';
require_once $h5p_root . '/h5p.module';
$mapping = h5p_autoload_info();
require_once $h5p_root . '/' . $mapping['H5PCore'];
require_once dirname(__DIR__) . '/h5p_themer.admin.inc';
require __DIR__ . '/learner-fixture.php';
require __DIR__ . '/native-density.php';
require __DIR__ . '/density-v043.php';
require __DIR__ . '/density-blanks-v043.php';
require __DIR__ . '/density-markthewords-v043.php';
printf("Focused native Density: %d passed, %d failed\n", $passed, $failed);
exit($failed ? 1 : 0);
