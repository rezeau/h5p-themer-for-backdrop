# Development roadmap

SPDX-License-Identifier: GPL-2.0-or-later.

## Current v0.4.0 development status — NOT YET RELEASED

v0.2.1 is the latest published stable release; v0.3.0 was withdrawn. Checkpoint 3B
native Density is implemented and user-confirmed Firefox PASS: qualified top-level
TrueFalse 1.8.24 iframe players on Core API 1.28 only, disabled by default. Existing
colors and learner selection retain their own broader compatibility rules.
Checkpoint 3C Lite superseded the full Stage 2/3 plan: its cache-warmed upgrade,
hook/cache refresh and Density-setting OFF/ON passed 46 focused assertions and all
seven manual Firefox tests. A separate actual module disable/re-enable verification
passed 85 focused checks without uninstalling, preserving schema 1003 and all eight
saved configuration hashes. Full code rollback was not rehearsed. The complete
automated baseline remains 3,793 PHP assertions on each of PHP 7.4.33, 8.3.18 and
8.4.22, plus 470 Node.js 22.23.1 tests.

Independent learner Density buttons are deferred to v0.4.1. Live Density Preview
(Checkpoint 3D) is deferred unless separately authorized; today's preview is
representative and color-only. Neither enhancement is part of v0.4.0. See
[readiness/evidence](v0.4.0-readiness.md) and [upgrade instructions](installation.md#v021-to-v040-development-upgrade-and-rollback).

## Implemented baseline

Phase 1B administration, Phase 1C built-in presets and custom inheritance,
Phase 2A global color runtime, and Phase 2B saved and unsaved-color previews are
implemented. The current user guide is [installation](installation.md) and
[administration](administration.md). Earlier phase records retain historical
test totals and investigation findings; they do not override current guidance.

The five existing built-ins remain Daylight, Lavender, Mint, Sunset and Dark.
Core defaults remain a separate option. No roadmap item changes existing schemas,
preset catalog values, custom definitions or the current global selection.

## Phase 2C — IMPLEMENTED: learner-controlled site-wide theme selection

The completed functionality includes:

- One administrator Boolean, **Allow learners to select their H5P theme**, false
  by default and optional in schemas 1 and 2. No migration or per-theme approval
  checkboxes are required.
- A shared validated catalog: Site default follows the configured global theme;
  explicit H5P Core defaults is independent of it; Daylight, Lavender, Mint,
  Sunset, Dark and valid saved custom themes retain their distinct identities.
- One native selector for eligible DIV and validated same-origin internal-iframe
  activities, including multiple independent activities on one page. Switching
  changes styling without H5P restart, iframe navigation or answer/scoring changes.
- Session-only preference persistence using stable catalog tokens in
  sessionStorage. Site default removes the preference; Core remains explicit.
  Disabled or incompatible-only pages do not access or clear it. Storage failures
  leave current-page switching usable. No learner-account, cross-device or H5P
  answer persistence is added. Opener copies and browser session restoration can
  retain a choice, so a new/restored tab is not guaranteed to start empty.
- Compatibility-based visibility using the native player's own direct
  `.h5p-container.h5p-theme` and matching stylesheet readiness. Unmarked legacy
  activities do not trigger a selector; ineligible siblings retain global styling.
  Delayed players remain discoverable. Editors, administrator previews and
  external/cross-origin embeds are excluded. This conservative marker convention
  is not universal library certification.

Controlled transfer and user Firefox functional acceptance are complete:

| Verification | Result |
| --- | --- |
| PHP 7.4.33 | 1,854/1,854 PASS |
| PHP 8.4.22 | 1,854/1,854 PASS |
| Node.js 22.23.1 | 291/291 PASS |
| User-confirmed Firefox Step 5E | 10/10 PASS |
| User-confirmed Firefox Step 6E | 9/9 PASS |

Step 6E covered True/False selection/switching, legacy Image Choice Rounds selector
hiding, session persistence, multiple activities, answer continuity, Core versus
Site default, Copy of Mint and no new Console errors. No additional browser tests
were performed by publication corrections. Historical [switching](phase-2c-step-4a.md),
[session](phase-2c-step-5.md) and [compatibility](phase-2c-step-6.md) records retain
their original checkpoint observations and pending-test statements.

The following Phase 2C results are historical checkpoint evidence, not current
release publication. Current release/development status is recorded above.
Source/tests check native select/label association. Keyboard-only acceptance,
screen-reader announcements, focus on control removal, narrow/mobile layout,
RTL/long translated labels, Chromium, Safari, restricted-storage environments,
broader libraries and external/nested contexts remain incompletely qualified.
These limits do not invalidate the completed Firefox checks, and no WCAG or
universal browser guarantee is claimed.

Deployment must keep the controller and content-addressed CSS synchronized; use
the [verified targeted cache procedure](installation.md#deployment-cache-refresh)
and check actual browser delivery. Open pages retain their supplied catalog until
normal refresh/settings reattachment; there is no polling for administrator changes.

## Two-color learner-selector refinement — independent integration

The approved name-and-icon dropdown is integrated and regression-tested in an
independent Git clone. It has not been deployed to the original Backdrop site or
published. A select-only combobox/listbox uses two solid decorative blocks:
resolved main-cta-base and alternative-darker. The native select remains canonical
and is the fallback.
No theme-specific custom list, palette changes or second persistence path exists.

Automated contracts cover keyboard exploration/commit, restoration, lifecycle,
metadata validation and player isolation. Phase 2D passed **12/12 user-confirmed
initial Firefox functional checks** in the disposable test environment. Compact
spacing was subsequently accepted in Firefox, with all nine themes visible
without scrolling at normal zoom. Desktop option rows have a 32px minimum;
coarse-pointer option rows retain a 44px minimum. NVDA, touch, forced-colors, RTL,
high zoom and other-browser verification remain limited. See the [implementation
record](phase-2d-learner-icons.md). Earlier Phase 2C Firefox results describe the
previous native control; they are separate from the Phase 2D acceptance.

## Phase 2D — PLANNED: accessibility-oriented themes

These proposed built-in presets are not shipped. The site-tested Colorblind
Friendly and High Contrast Light definitions remain ordinary editable custom
themes; this refinement does not add them to the built-in catalog:

- High Contrast Light.
- High Contrast Dark.
- Color-Vision Friendly.
- Low Glare.

Their colors and behavior must be designed and tested before addition. Consider
text, focus, borders, feedback and non-color cues across representative libraries
and states, with keyboard and assistive-technology testing. A theme name or an
upstream WCAG subtitle does not certify the entire module or arbitrary authored
H5P content. No approximate palette or accessibility guarantee is introduced here.

## Existing limitations and further investigation

Native Density is deliberately limited to the qualified TrueFalse scope above.
Per-content assignments, comprehensive external/nested qualification and broad
library/browser coverage are not implemented. Current previews are representative samples rather than running
activities. The administration/preview interface was usable but cramped at 375px
in Firefox; Checkpoint 3B Firefox acceptance covered narrow layouts and 200% zoom for its
fixtures; broader mobile, keyboard-only and assistive-technology coverage remains limited.
