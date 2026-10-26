# Changelog

SPDX-License-Identifier: GPL-3.0-or-later.

<a id="v040--unreleased"></a>

## v0.4.0

Adds disabled-by-default native Density for qualified top-level TrueFalse 1.8.24
iframe players on H5P Core API 1.28, with explicit boolean TRUE opt-in. Unsupported
players retain original Density. Existing colors, learner picker and saved-theme
schemas are preserved. Updates administration/help and deployment/cache guidance.

Checkpoint 3C Lite passed 46 focused assertions and all seven manual Firefox
tests. Separate actual module disable/re-enable passed 85 focused checks without
uninstalling. The complete baseline is 3,793 PHP assertions on each of three PHP
versions and 470 Node tests. Full code rollback was not rehearsed.

Independent learner Density buttons are deferred to v0.4.1; Live Density Preview
is deferred unless separately authorized. See the [v0.4.0 release notes](docs/release-notes-v0.4.0.md).

## Earlier versions

- v0.3.0 — withdrawn.
- v0.2.1 — earlier stable baseline; [historical release notes](docs/release-notes-v0.2.1.md).
- v0.2.0 — [historical release notes](docs/release-notes-v0.2.0.md).
