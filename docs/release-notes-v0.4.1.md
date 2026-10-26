# H5P Themer for Backdrop v0.4.1 — prepared release notes

SPDX-License-Identifier: GPL-3.0-or-later.

Prepared during Checkpoint 3B Stage 1 from uncommitted development. These notes do
not assert a release ZIP, completed package upgrade, Git commit/tag or publication.
Published baseline: v0.4.0, commit `af26fe38bfe9c6097c64f45d701e59cabe8b2ff5`.

## New in v0.4.1

- Independent learner Density controls beside the existing theme picker: three
  progressively sized T buttons select Small (compact), Medium (intermediate) and
  Large (spacious). They use H5P's native profiles; container width and three
  distinct activity text sizes are not promised.
- **Use theme Density** resets the learner choice. Precedence is explicit learner
  Density override > current theme's saved Density > each player's original native
  Density. An override survives theme/color switching, including Core and immutable
  built-ins. Reset resolves the current theme, not the theme active at override time.
- One independent installation/browser-tab sessionStorage preference applies across
  qualified activities, including multiple distinct players on a page. Reload and
  same-tab navigation restore it when usable playback is ready. Reset removes only
  its own key. Missing/invalid values use theme mode; denied storage leaves current
  page controls usable, but an older stored value can remain after a failed write.
- Core/built-in-only catalogs with all-null Density metadata can offer learner
  controls. This does not change the saved-theme schema or the theme-color catalog.
- Compact, wrapping controls retain native button keyboard activation, accessible
  names, aria-pressed, visible focus and non-color selection cues. Mode/scope text
  and polite state announcements are visually hidden after the accepted refinement.

## Preserved from v0.4.0

v0.4.0 introduced saved custom-theme native Density and the guarded native
controller. v0.4.1 adds learner overrides; it does not broaden library support.
The native controller remains the sole owner of Density classes, originals,
instance-specific resize, preference state and persistence. H5P instances, answers,
scores and feedback are not recreated by styling changes. Unsaved answers are not
persisted across reload/navigation by Themer.

The decorative theme picker, native-select fallback, existing theme selection and
colors retain their own compatibility rules. While following theme Density,
custom themes supply their saved profile; Core/built-ins restore originals; Site
default follows effective Global Default, including a hidden custom default.
Unsupported H5P content retains original Density. On mixed pages only qualified
players change; on unsupported-only pages Density controls are hidden while
existing theme-picker behavior is preserved. Duplicate embeddings of one content
ID, nested/DIV/external players and unqualified versions remain excluded.

## Activation and upgrade boundary

Native Density is disabled by default. Only exact boolean TRUE enables it:

```php
$settings['h5p_themer_native_density_enabled'] = TRUE;
```

Strings, integer 1, arrays, FALSE and NULL do not enable it. Learner controls also
require the existing learner theme-selection feature. Qualification remains
**H5P Core API exactly 1.28 and qualified top-level internal-iframe
H5P.TrueFalse 1.8.24 players**. No H5P Core/library edits are introduced.

Existing v0.4.0 installations at schema 1003 need no new migration. Saved schema-1/2
themes, Density values, preset pins, Global Default, learner toggle and visibility
must be preserved. Replace the module coherently without uninstalling/reinstalling
or replaying update hooks. Back up code/settings/configuration/database, refresh
hook discovery and caches in a verified site context, handle candidate-specific
opcode caches, and reload playback. Existing open pages retain their supplied code
and metadata until reload. See the [planned rehearsal](v0.4.1-upgrade-rehearsal.md)
and [existing cache guidance](installation.md#v021-to-v040-development-upgrade-and-rollback).

## Verification and limits

Checkpoint 2D: the user reported all 11 Firefox manual tests PASS, including the
accepted toolbar refinement, playback/scoring/Retry, two-player independence,
unsupported isolation, all-null configuration, same-tab persistence, keyboard,
200% zoom/narrow viewport and no console errors. **Windows forced colors and screen
reader were not tested.** Firefox version and exact viewport were not supplied.
No broader browser, accessibility or library certification is claimed.

Checkpoint 3A: 573 JavaScript tests and 3,867 PHP assertions on each of PHP 7.4.33,
8.3.18 and 8.4.22 passed with zero failures. Historical v0.4.0 assertions/hashes are
preserved; explicit v0.4.1 checks are separate. These are prior accepted results,
not a claim that an as-yet-unbuilt release archive passed installation testing.
See [current readiness](v0.4.1-readiness.md).

**Live Density Preview remains deferred.** Saved and unsaved-color previews are
representative color-only samples. Package/upgrade and code rollback rehearsal are
pending; existing module disable/re-enable evidence is not code rollback proof.
