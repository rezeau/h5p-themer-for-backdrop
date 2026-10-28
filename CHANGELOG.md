# Changelog

## v0.4.3 development (unreleased)

- Add exact official H5P.MultiChoice 1.16.27 native Density qualification alongside
  H5P.TrueFalse 1.8.24, preserving Core API 1.28 and top-level internal iframe scope.
- Reuse the existing learner controls, version-3 transport, tab-session preference
  and per-instance resize lifecycle. Other libraries remain excluded.
- Add server and browser-controller regressions. The development build is deployed
  on local backdrop2. The user reports all six MultiChoice Firefox acceptance tests
  and mixed-player isolation passed. See [acceptance and limits](docs/v0.4.3-readiness.md)
  and the [draft release notes](docs/release-notes-v0.4.3.md).

## v0.4.2 — published baseline

- Add an administrator Density checkbox with explicit PHP override precedence.
- Make native Density learner-only; preserve original spacing before a choice.
- Remove theme Density inputs/overview column and the learner reset button.
- Retain legacy theme values, session preferences and the internal reset API.
- Use version-3 Density metadata without saved-theme profiles.
- Preserve exact qualification, visibility safeguards and historical release evidence.


SPDX-License-Identifier: GPL-3.0-or-later.

## v0.4.1 — development, not published

Adds independent learner Small / Medium / Large Density buttons beside the theme
picker, with Use theme Density reset and a separate installation/tab-session
preference. Learner overrides survive theme/color switches and take precedence over
saved theme Density. Strict opt-in and qualified top-level TrueFalse 1.8.24 iframe
scope remain unchanged. No saved schema, H5P content or library changes.

Checkpoint 2D's 11 user-reported Firefox tests passed, including the accepted compact
toolbar refinement. Forced colors and screen reader were not tested. Live Density
Preview remains deferred. See [v0.4.1 readiness](docs/v0.4.1-readiness.md).
Prepared [v0.4.1 release notes](docs/release-notes-v0.4.1.md) describe the release
scope; package construction and upgrade rehearsal remain unexecuted.

The v0.4.0 entry below is historical; its deferral statements describe that release.

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
