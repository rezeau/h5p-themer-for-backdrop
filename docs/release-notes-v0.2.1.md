# H5P Themer for Backdrop v0.2.1 — release candidate

SPDX-License-Identifier: GPL-3.0-or-later.

Local candidate only, based on stable v0.2.0. No v0.2.1 tag, GitHub Release or
asset publication is created by this preparation. Install the candidate ZIP as
a Backdrop module, in `h5p_themer`, rather than as an H5P content package.

## New since v0.2.0

- Create one schema-2 custom theme through Backdrop's existing Configuration
  manager → Single import/export → Import. No separate import interface.
- Preserve the exact ID, label, description, density, base-preset pin and sparse
  color overrides. JSON formatting and object key order need not be identical.
- Persist and verify hidden learner visibility before saving the definition.
  Imported themes require an explicit later publication action.
- Reject duplicate IDs, including destinations containing malformed data,
  without overwriting the existing object.

## Safety

Both native configuration synchronization and H5P theme administration permissions
are required. Existing theme update, deletion, bulk import/synchronization and
learner-visibility policy import restrictions remain. Existing themes, unrelated
policy entries and Global Default are protected; importing a missing theme cannot
silently activate an unresolved stored or effective default reference.

Validation is repeated under the existing administration write lock. Saved
visibility and definition data are read back before success is reported. Partial
failures retain restrictive visibility and report uncertainty. Writes are not an
atomic multi-object transaction; there is no automatic rollback or visibility
restoration. Low-level external writers do not share this lock.

## Compatibility and deployment

Requirements remain Backdrop 1.x, a compatible Backdrop H5P integration exposing
exactly H5P Core API 1.28, and the existing supported PHP environment. Source and
manual qualification used Backdrop 1.35.2. No additional compatibility is claimed.

Theme schema 2, schema-1 read support, preset pins, configuration defaults and
Density runtime behavior are unchanged. Density is stored but is not applied to
H5P playback or the representative previews. No new database update is introduced;
sites already at Themer schema level 1003 must not replay visibility activation.

Back up code, database, configuration and files; pause theme administration and
configuration synchronization; replace code without uninstalling, follow the
normal pending-update workflow and the existing
[deployment cache refresh procedure](installation.md#deployment-cache-refresh).
Use a separate fresh request after refreshing caches to qualify native import
hook discovery. Reload old administration forms before testing.

## Verification

The verified feature commit is `3797c79d132222ecddf12a035938a66176bb1dc1`.
Complete existing suites passed 3,673 PHP checks on each of PHP 7.4.33, 8.3.18 and
8.4.22, plus 387 tests on Node.js 22.23.1. Eighteen changed-PHP syntax checks and
diff formatting checks passed. Filesystem fixtures ran outside the restricted
sandbox, which otherwise denied atomic temporary-file rename operations even
for unchanged v0.2.0 code.

Manual Backdrop checks passed native import, parsed-JSON import/export round trip,
duplicate rejection, imported colors in Edit and hidden learner visibility.
Cleanup removed only the disposable test theme, retained its hidden tombstone
and visibility-form generation, and restored original module files and guards.
Release preparation does not modify a Backdrop installation or claim another
browser test run. Existing executable/test verification is reused where unchanged;
the candidate ZIP is checked separately for layout, metadata, contents and integrity.

## Limitations

- No overwrite/update or bulk theme import through native configuration import.
- Density remains stored but is not applied at runtime.
- Some negative paths, including permission failures, policy/update/delete
  restrictions, unrelated-import delegation and partial write/cache failures,
  are covered by automated fixtures rather than independent live browser tests.
- The imported theme's embedded Edit preview was not independently tested.
  There is no dedicated Preview action, so that action check is not applicable.
- Existing playback, concurrency, browser and accessibility qualification limits
  from [v0.2.0](release-notes-v0.2.0.md) remain; this candidate does not broaden them.
