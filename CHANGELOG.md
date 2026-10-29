# Changelog

## v0.4.3 — prepared, not yet packaged or published

- Phase 5 adds exact standalone official H5P.MarkTheWords 1.11.24 native Density
  qualification alongside H5P.TrueFalse 1.8.24, H5P.MultiChoice 1.16.27 and
  H5P.Blanks 1.14.37. Core API exactly 1.28, iframe ownership and variant/compound
  exclusions remain unchanged; no custom Density CSS is added.
- Final implementation verification: 733 JavaScript tests and 4,072 PHP assertions
  on each of PHP 7.4.33, 8.3.18 and 8.4.22 passed. The user reports all 19 Phase 5
  Firefox acceptance tests passed. Generous long-text line spacing is a non-blocking
  possible future refinement, not a confirmed defect or requested CSS change.
  See [Phase 5 acceptance](docs/v0.4.3-markthewords.md) and the
  [prepared release notes](docs/release-notes-v0.4.3.md). These are the tested
  implementation results; release-preparation checks are reported separately.

- Selector simplification removes Site default/Core from selectable
  rows while retaining their internal states and truthful placeholders. Add the
  accessible theme-only Reset icon. No catalog, configuration, token, persistence
  key or Density changes. The toolbar collision and duplicate visible placeholder
  are corrected; all six Firefox acceptance tests are user-reported passes.
  See [selector acceptance and limitations](docs/v0.4.3-selector.md).
- Phase 4 adds exact official H5P.Blanks 1.14.37 qualification. The development
  build is deployed on backdrop2; the user reports all six Blanks Firefox
  acceptance tests passed. See [Blanks implementation, acceptance and limits](docs/v0.4.3-blanks.md).
- Add exact official H5P.MultiChoice 1.16.27 native Density qualification alongside
  H5P.TrueFalse 1.8.24, preserving Core API 1.28 and top-level internal iframe scope.
- Reuse the existing learner controls, version-3 transport, tab-session preference
  and per-instance resize lifecycle. Other libraries remain excluded.
- Add server and browser-controller regressions. The MultiChoice milestone is deployed
  on local backdrop2. The user reports all six MultiChoice Firefox acceptance tests
  and mixed-player isolation passed. See [acceptance and limits](docs/v0.4.3-readiness.md)
  and the [prepared release notes](docs/release-notes-v0.4.3.md).

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
