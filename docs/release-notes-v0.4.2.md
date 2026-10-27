# H5P Themer for Backdrop 0.4.2

Release candidate; not yet committed, tagged or published.

## Changes from 0.4.1

- Global settings adds **Enable native Density controls**, disabled by default.
  A present PHP `h5p_themer_native_density_enabled` overrides it: boolean TRUE
  enables, FALSE disables, and any other value, including NULL, disables with
  an administrator diagnostic. The checkbox explains the override and is locked
  while PHP controls it. Removing the override restores checkbox control.
- Density is independent of visual themes. Qualified learners have exactly
  **Small, Medium and Large** buttons. Before a choice, each activity keeps its
  original spacing. Large is not a synonym for original H5P spacing.
- Explicit choices survive visual-theme switches and same-tab reload/navigation.
  The v0.4.1 installation-scoped sessionStorage key and enum preferences remain
  compatible. No preference is stored in a learner account.
- The visible **Use theme Density** button is removed. The internal validated
  `resetOverride()` API remains available to clear the preference and restore
  original spacing. There is no learner-facing reset during the tab session.
- Add, Edit, Duplicate and overview no longer expose theme Density. Existing
  schema-1/2 values remain validated and preserved for legacy/import/export round
  trips, but never supply a learner playback profile. No theme migration occurs.
- Density metadata uses transport version 3, with no saved-theme profile maps.
  Native buttons, aria-pressed, keyboard activation, visible focus, responsive
  wrapping and a visually hidden polite status region remain in place.

These are simplifications of v0.4.1's existing learner controller and toolbar.
The native controller remains the sole owner of Density classes, original-profile
restoration, instance resize, override state and session persistence.

## Qualification and compatibility

Native Density requires learner theme selection, exact **H5P Core API 1.28** and
ready, qualified **top-level H5P.TrueFalse 1.8.24 internal iframe players**.
Unsupported libraries/versions and nested, external, DIV or ambiguous players
retain original Density. Broader library support is not introduced. Visual theme
selection retains its separate compatibility rules. Visibility activation and
publication safeguards remain unchanged. Live Density Preview remains deferred.

## Upgrade and rollback

See the [current upgrade procedure](installation.md#current-v041-to-v042-upgrade-and-rollback).
Preserve code, settings.php, active configuration, database, custom themes and H5P
files in a matching verified backup before replacement. Do not uninstall/reinstall,
copy defaults into active storage, replay updates or visibility activation, or
change H5P content/libraries. Deploy PHP/JS/CSS together and refresh only the
authorized site's deployment caches; reload existing playback pages.

The optional strict boolean `native_density_enabled` does not require a schema
migration. Once saved, v0.4.1's validator rejects it: rollback must restore matching
pre-upgrade Themer settings and settings.php with the old code. Code-only rollback
is insufficient. A full database restore must be separately authorized, never
automatic. Preserve later site work before any restore.

## Verification and limitations

Automated regression covers PHP 7.4.33, 8.3.18 and 8.4.22, JavaScript behavior,
legacy definitions, precedence, original profiles, storage failures, subscriptions,
qualification and lifecycle/resize safeguards. Historical v0.4.0/v0.4.1 assertions
and expected hashes are preserved and run against their pinned source.

A controlled local v0.4.1-to-v0.4.2 deployment on Backdrop 1.35.2 passed installed-file
verification. Administrator checks passed through the installed Backdrop Form API
and rendered HTML, including TRUE/FALSE/NULL/invalid overrides and checkbox persistence.
Language Icons, French, Colorblind Friendly, visibility and existing H5P data were preserved.

**Manual Firefox results reported by the user:** Small/Medium/Large; theme switches
without changing Density; persistence across reloads and activities; TrueFalse
scoring, feedback and Retry; keyboard navigation and visible focus; no console
errors; 200% zoom; and a 375 px viewport. These are user-reported passes, not
agent-executed Firefox tests. Agent Firefox automation was blocked before execution.

**NOT TESTED in real-browser acceptance:** internal reset/original-class inspection,
controller snapshot inspection, explicit class-accumulation measurements, active
attempt preservation under styling changes, unsupported-content and same-page
multiple-player fixtures, BFCache/iframe replacement, Windows forced colors and
screen-reader announcements. Console warnings were not included in the user's
manual report. Full upgrade-and-restore and rollback rehearsals remain NOT TESTED.
Other browsers and accessibility conformance are not certified.
