# H5P Themer for Backdrop v0.3.0 — release candidate

SPDX-License-Identifier: GPL-3.0-or-later.

Unpublished candidate based on v0.2.1. Version metadata is prepared; no commit,
release package, tag or publication is created by this preparation.

## New since v0.2.1

- Functional **Small / Medium / Large** Density for custom themes. Small uses
  20% less supported spacing than Medium; Large uses 25% more.
- Saved and live representative previews show Density spacing. Later color edits
  preserve the selected Density; only Save persists changes.
- One guarded experimental playback target: vertical evaluation-row spacing in
  top-level themed **H5P.TrueFalse** content. Medium preserves its native baseline.
- Global Default custom-theme Density works with learner switching enabled or
  disabled. Explicit learner-selected custom themes supply their own Density;
  Site default follows Global Default. Theme selection remains page-wide across
  eligible players.
- Effective spacing changes request bounded, instance-specific native H5P
  resizing without recreating players. Tested answers and scores remain intact.
  Same-spacing mode changes and color-only stylesheet replacements request no
  unnecessary resize. Density changes also refresh generated stylesheet identities
  and relevant caches.

## Compatibility and upgrading

Requirements remain Backdrop 1.x with a compatible integration exposing exactly
**H5P Core API 1.28**. Verification used Backdrop 1.35.2. No H5P Core or content
library modifications are required.

No configuration migration, schema change or new database update is introduced.
Existing schema-1 and schema-2 custom themes retain their Density values,
including imported themes. Exact schema-2 preset pins, sparse color inheritance,
Global Default, learner visibility and tab-session theme preferences are preserved.

Existing Small/Large values that were stored without effect in v0.2.1 now change
supported preview and playback spacing. Large themes can therefore appear more
spacious without editing their configuration. Review representative activities
on a disposable copy before deployment. Back up the site, replace code without
uninstalling and follow the existing
[deployment cache refresh procedure](installation.md#deployment-cache-refresh).
Reload open forms and playback pages after deployment.

## Scope and limitations

- Guarded playback Density requires JavaScript. Without it, colors remain
  available and playback spacing stays native; saved previews remain available.
- Core and built-in palette selections retain native playback spacing, as do
  unsupported, nested, external or ambiguously identified players.
- This is not universal H5P Density support. Only the guarded top-level
  True/False evaluation-row gap is changed in playback. Button padding/hit areas,
  typography, authored geometry and media dimensions are unchanged. Preview
  samples are representative HTML, not running H5P activities.
- Current browser evidence covers True/False 1.8.24 / Question 1.5.56 in in-app
  Chromium, including narrow/mobile widths and multiple independent iframes.
  Native DIV fixtures were also exercised; normal Backdrop True/False delivery
  uses iframes.
- Final-source evaluated DIV handoffs, a fresh native-import-to-playback browser
  sequence, actual cancellation/document-replacement races, physical touch,
  other browser engines and broader fullscreen/hidden-player cases were not all
  browser-qualified. Automated coverage and earlier browser evidence are distinct;
  no universal browser or accessibility certification is claimed.
- An administrator MutationObserver error also reproduced on the restored
  original module. Checked playback pages captured no warning/error entries;
  the administrator error's origin remains unresolved.

## Verification reused for this candidate

The existing complete suites passed **3,852 checks per PHP version** on 7.4.33,
8.3.18 and 8.4.22, and **455 tests on Node.js 22.23.1**. Recorded syntax checks
passed. Phase 2C7 targeted browser qualification resolved both unnecessary-resize
defects: zero requests for equal-spacing mode changes and color-only replacement;
one targeted request per affected iframe for genuine Small/Large changes, with
fitting heights and retained answers/scores.

Runtime and test source hashes match that qualified candidate. This preparation
changes metadata/documentation only and does not repeat the complete suites or
browser deployment. Package contents and ZIP integrity remain to be verified
when packaging is separately authorized. See [Density scope](density-phase2a.md)
and the [installation guide](installation.md) for details.
