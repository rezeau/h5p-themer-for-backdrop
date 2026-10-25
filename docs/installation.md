# Installation and first use

SPDX-License-Identifier: GPL-2.0-or-later.

This guide covers the v0.2.0 baseline and the local v0.2.1 release candidate.
Read the [candidate notes](release-notes-v0.2.1.md) and
[v0.2.0 release notes](release-notes-v0.2.0.md) before upgrading. Historical phase/checkpoint
records preserve development evidence; they are not installation instructions.

## 1. Prepare Backdrop and the H5P integration

Backdrop **1.x** is the metadata declaration. Actual source inspection and local
verification used **Backdrop 1.35.2**, H5P Core API **1.28**, and PHP **7.4.33**,
**8.3.18** and **8.4.22**. The v0.2.1 feature PHP suite passed 3,673 assertions on each version;
the JavaScript suite passed 387 tests on Node.js **22.23.1**. These are isolated
automated tests, not database-backed installation or universal browser
certification. Other Backdrop/PHP versions and arbitrary H5P integration releases
are not established as compatible by this evidence.

Back up your own site and configuration using your normal recovery process
before installing modules. Install the existing Backdrop-compatible H5P module
package under your site's modules directory, normally `modules/h5p`. If H5P is
already installed, inspect that installation rather than replacing it. This
repository does not bundle or upgrade H5P.

The H5P integration must expose **Core API exactly 1.28**. Do not assume that an
arbitrary download, an older API or a newer API is supported. In its mapped
H5P Core class (normally `library/h5p.classes.php` within the H5P module), inspect
`H5PCore::$coreApi`: `majorVersion` must be integer `1` and `minorVersion`
integer `28`. Inspect source; do not edit the declaration to bypass compatibility.

At `admin/modules`, enable H5P first. Enable the packaged H5P Editor submodule
when your site's authors need its editing interface. Themer's dependency is H5P;
it does not modify the Editor. Confirm that existing H5P playback works before
adding Themer.

## 2. Fresh installation

Place the module's files in a directory named **h5p_themer**, normally:

```text
<Backdrop-root>/modules/h5p_themer/h5p_themer.info
```

Rename an extracted GitHub archive directory if necessary: the repository's
hyphenated name is not the installation directory name. Do not nest the module
inside an extra archive directory.

Open `admin/modules` and enable H5P Themer after H5P has been enabled. Themer's
installation check rejects unavailable H5P and APIs other than 1.28. If it rejects
the integration, resolve the dependency/version issue; do not alter source or
stored compatibility data to force installation. Check the status report at
`admin/reports/status`.

Fresh installation runs the module's install hook and initializes learner
visibility. Site default, Core and Daylight are fixed choices. The remaining
built-in presets start available; any custom definitions present during fresh
initialization start hidden. Custom themes subsequently created through Add,
Duplicate or Preset Duplicate also start hidden. The learner-selection setting
starts disabled, and Global Default starts at
**H5P Core defaults (no overrides)** (`null`).

Do not invoke update hooks manually on a fresh installation. Backdrop records
the installed schema level and runs the install hook through its normal module
installation lifecycle. Verify that the overview has no activation diagnostics.

The existing local public-files directory must be writable and use a browser-safe
relative path for global-theme CSS. Remote/absolute public-path mappings are not
supported by this runtime. Preview itself does not publish runtime CSS.

### Upgrading an existing installation

1. Back up code, the database (including module schema and activation state),
   configuration and files using the site's established recovery procedure.
   Test the upgrade on a disposable copy first. Record Global Default, the
   learner-selection setting and existing visibility choices.
2. Pause theme administration and configuration synchronization for deployment.
   If upgrading an experimental build with custom import coordination, inspect
   and resolve incomplete operations and pending output-cache refreshes first.
   Removing that subsystem cannot repair partially applied data.
3. Replace the existing module code in the same `h5p_themer` directory. Do not
   uninstall/reinstall: uninstallation can remove module configuration.
4. Run Backdrop's normal database update workflow at `update.php`, or the site's
   supported database-update command. Apply all pending updates. Selecting or
   replacing files does not run database updates.
5. Refresh menu registrations with the site's supported cache-rebuild procedure,
   then follow [Deployment cache refresh](#deployment-cache-refresh) to verify
   JavaScript/output freshness. Reload administration forms opened before deployment.
6. Check the status report and Themes overview. Verify Global Default, existing
   theme data, learner-selection setting and **Available to learners** choices.
   Test playback and publication with a fresh page before reopening administration.

The update behavior depends on the installed schema level, not merely the
release filename:

| Existing installation | Database-update behavior |
| --- | --- |
| v0.1.0 or an earlier build without visibility activation | Update 1000 freezes the existing catalog; 1001 initializes existing resolvable optional choices as available. This does not enable learner selection. |
| Earlier v0.2.0 build with completed activation | Apply only pending updates. Preserve the stored visibility policy, including hidden entries; do not rerun activation. |
| Already at schema level 1003 | These changes add no later database update. Checking for pending updates is still appropriate; never lower the schema level or replay 1000/1001. |

Updates 1002/1003 are retained no-ops from the removed custom-import subsystem.
Theme schemas 1 and 2, saved preset pins, Global Default and the learner-selection
setting need no data migration. Existing hidden choices are not reset by a
completed activation retry. Policy and activation state are separate from theme
definitions and must be preserved in backups.

After the recorded activation barrier, missing, damaged or incomplete activation
or policy state hides optional learner choices and blocks theme publication.
Opening a page does not repair or initialize it. Investigate the diagnostics and
[activation record](r2-r3-activation.md); do not delete policy/journal data or edit
installed update numbers to force a retry. Do not assume older code is a safe
rollback: releases without visibility enforcement can expose hidden themes.

## 3. Grant administration access

At `admin/people/permissions`, grant **Administer H5P themes** only to trusted
administrator roles. This restricted permission controls theme administration,
not a learner selector. Log in with an authorized account and open
`admin/config/content/h5p-themer`.

## 4. Select a global theme

Open **Global Default** at `admin/config/content/h5p-themer/default`.
Choose **H5P Core defaults (no overrides)**, one of the five built-ins, or an
existing custom theme, then save. Core is stored as `null` and adds no global
overrides. Daylight is a separate preset. Selecting Core does not substitute
Daylight during playback; Add's starting-theme fallback is described below.

Global Default uses effective configuration, including validated settings.php
overrides. The overview distinguishes stored and effective selections. Overridden
settings are read-only in the form. Broken references do not silently select or
repair a default; explicitly clear/repoint a valid settings object, or use a
validated settings-only repair if the settings object itself is malformed.

Reload existing activity pages after saving. Only libraries that consume the
relevant variables will visibly change. Embedded playback served by this site
can receive the selected global colors through its integration; unrelated remote
providers, existing open frames and independently cached host pages are outside
that guarantee. Density is not applied.

## 5. Create or edit a custom theme

**Add theme** starts from the effective Global Default and identifies its actual
name. If Global Default is **H5P Core defaults (no overrides)**, unset, missing,
invalid or unresolvable, Add clearly identifies the **Daylight fallback**, pinned
to `moodle-478fed30d2a4`. This changes only the new theme's starting definition;
it never changes Global Default or the source.

| Starting source | New custom definition |
| --- | --- |
| Built-in or Daylight fallback | Schema 2, exact preset/version, empty override map |
| Schema-2 custom | Same pinned preset, effective sparse hexadecimal overrides and density |
| Schema-1 custom | Schema 1, effective sparse hexadecimal overrides and density |

On Add, blank color fields retain captured starting colors, including copied
custom overrides. Enter `#RGB` or `#RRGGBB`, or use an available native color
picker, for an explicit override. Preset expressions remain inherited through
the pinned preset; they are not saved as custom colors. Save creates an
independent custom theme with a unique machine ID, hidden from learner choices.
Later changes to the source custom theme do not change the copy. Missing/expired
form state or relevant source/default changes require reloading Add. An ID
referenced by a missing stored or effective custom default cannot be reused;
repair that default separately or choose another ID.

**Duplicate** on a custom theme copies its stored original data under a new ID,
preserving its schema and preset pin. **Preset Duplicate** means the built-in's
**Duplicate** action: it creates schema 2 with the exact pin and no overrides;
save that initial copy before editing colors. Neither action selects a new
Global Default. Both create hidden custom themes; built-ins remain read-only.

Open the custom theme's **Edit** action. The machine ID is stable. Enter
`#RGB` or `#RRGGBB` in the authoritative textfields, or use their native picker
companions. Unlike Add, clearing an Edit override removes it: blank restores
the pinned preset for schema 2 or Core's cascade for schema 1. Edit identifies
saved overrides and the exact preset/version where applicable, without inventing
an earlier custom source. Existing saved themes are not migrated merely by editing.

General settings are expanded initially; color groups start collapsed. Groups
containing server validation errors expand automatically. A custom theme selected
as the stored or effective Global Default is protected against deletion.

## 6. Use Theme Preview and Live Preview

Built-in **View** and custom **Edit** pages show saved representative samples and a
separate Core comparison. This HTML is not an actual running H5P activity.

On custom Edit, valid unsaved colors update the preview immediately when JavaScript
is available. Multiple edits combine. Invalid or incomplete strings show a message
and retain the last valid preview color; blank restores inheritance.
Reverting or native form reset restores the corresponding defaults. Only explicit
**Save**, after server validation, changes persistent configuration. Leaving or
reloading an unsaved form does not persist the draft.

With JavaScript disabled, the saved preview and ordinary textfields remain usable.
Unsaved live updates are unavailable, while valid saves and invalid-input rejection
continue through PHP.

The user confirmed Firefox saved/live previews, validation, inheritance/reset,
multiple overrides, picker synchronization and JavaScript-disabled operation.
At 375px the interface was usable but cramped. These observations are not
comprehensive browser or accessibility certification.

See [administration](administration.md), [configuration](configuration.md) and the
[historical roadmap](roadmap.md) for development context.

## 7. Optional learner theme selection

**Global Default** offers **Allow learners to select their H5P theme**, false by
default. To enable it, open `admin/config/content/h5p-themer/default`, check the
setting, save, and reload an existing H5P playback page. One native selector is
shown above the first eligible activity and controls all eligible activities on
that page without H5P reinitialization, iframe navigation or answer/scoring changes.

Choose **Site default** to follow the effective Global Default, including when
that theme is hidden as an explicit learner choice. The learner's **H5P Core
defaults** option uses Core independently; it corresponds to the administrator's
**H5P Core defaults (no overrides)** selection. Daylight remains a distinct palette.

On the Themes overview, use **Available to learners** and **Save visibility** to
publish optional built-in/custom choices. Site default, Core and Daylight are
always available when the validated learner catalog is usable. Other built-ins
and custom themes require a saved true visibility entry. New, duplicated and
recreated custom IDs start hidden. Hiding a theme does not delete it or prevent
its use as Global Default. Existing custom inheritance remains intact.

Visibility uses trusted cached form state. A stale form cannot republish a theme
hidden by another administrator or authorize a replacement after deletion, even
if the replacement has identical data. Reload after stale/expired-form errors;
normal validation corrections preserve the original snapshot.

The selector restores a valid browser tab-session preference, otherwise Site
default. With usable sessionStorage, choices survive same-tab navigation/reload;
Site default clears the stored choice. This is not permanent learner-account or
cross-device persistence and does not save answers. Opener-created tabs may copy
a preference, and browser session restoration can retain it. Storage failures
leave page-only switching available; disabled or incompatible-only pages ignore
the preference without reading or clearing it.

Eligibility requires the native player's own direct `.h5p-container.h5p-theme`
and matching stylesheet readiness. Unsupported legacy/unmarked activities do not
trigger a selector. In mixed pages, ineligible activities retain normal global
styling. This marker convention does not establish universal H5P compatibility.
Editors, administrator previews and external/cross-origin embeds are excluded.

To stop future Themer overrides, choose **H5P Core defaults (no overrides)** in
**Global Default** and disable learner selection, then save/reload playback pages.
Global Core alone may still attach learner assets while selection is enabled. Explicit Core in a
learner selector affects only that learner's choice, not administrator settings.

Phase 2C functionality passed user-confirmed Firefox Step 5E 10/10 and Step 6E
9/9 acceptance checks. Source/tests check the native select and associated label.
Keyboard-only acceptance, screen-reader announcements, focus on removal,
narrow/mobile layout, RTL/long translated labels, Chromium, Safari, restricted
storage and broader library support are not fully qualified. No WCAG certification
is made. See the [release qualification](release-notes-v0.2.0.md#compatibility-and-verification).

## Configuration import and recovery

Native Backdrop create/update/delete imports of custom theme definitions and
learner visibility policy are rejected, including mixed synchronization plans
that mutate those objects. There is no custom importer or privileged import
bypass. Manage themes and publication through administration.

Validated settings-only and unrelated configuration imports remain supported;
selected custom references must resolve. Normal configuration export remains
available, but a theme/policy export is not a supported import-based restore
procedure. Preserve database activation/deletion state as well as configuration
in site backups. Plan recovery for malformed definitions/policy separately;
do not bypass the guards with direct files or low-level saves. See the
[configuration contract](configuration.md#native-configuration-validation-and-importexport).

## Deployment cache refresh

When deploying the learner HTML cache-header correction, existing Backdrop
page-cache entries must be refreshed, even if no JavaScript has changed. Older
entries can still carry the previous browser cache policy. Use the native
`cache_clear_all()` operation within a supported local CLI bootstrap that reaches
`BACKDROP_BOOTSTRAP_FULL` for the intended site. This refreshes page/layout-path
output in the verified Backdrop 1.35.2 environment. The correction alone does not
require clearing JavaScript aggregates or deleting generated CSS or H5P assets.

HTML containing learner settings now requires HTTP revalidation through
`Cache-Control: no-cache, must-revalidate`, including Backdrop page-cache HIT
responses. Existing browser copies advertised before deployment cannot be
retroactively invalidated. Already-open pages are not automatically updated;
they retain their supplied catalog until refresh/settings reattachment. There is
no live polling or automatic page refresh.

The Step 6C/6D deployment showed that Backdrop can reuse a JavaScript aggregate
when source bytes change at the same filename. New content-addressed learner CSS
can then be combined with the old controller, which never sets the eligibility
attribute required by the new CSS. A selector may appear and accept choices
without visibly changing an activity. A browser hard reload alone does not
invalidate that server-side mapping.

After a deployment that changes learner JavaScript, use a supported local CLI
bootstrap that reaches `BACKDROP_BOOTSTRAP_FULL` for the intended site, then invoke
these verified native operations exactly once in this order:

```php
// Inside an already fully bootstrapped Backdrop environment for the target site.
backdrop_clear_js_cache();
cache_clear_all();
```

These are not standalone PHP commands: do not run this snippet with a bare PHP
interpreter or expose it as a web script. The no-argument `cache_clear_all()`
flushes page/layout-path output in the inspected Backdrop 1.35.2 installation;
the JavaScript call invalidates the aggregate mapping through supported APIs.
Verify API behavior and use the site's established bootstrap procedure on other
versions. This is a targeted deployment operation, not a full cache rebuild.

A subsequent fresh playback request can build the JavaScript aggregate from the
current source. It can reuse the verified learner CSS; changed CSS bytes already
select a different content-addressed filename and aggregation key. No separate
CSS clearing or forced stylesheet regeneration is needed solely to update JS.
Preserve generated CSS, H5P dependencies, hook discovery and authored content.
Do not manually edit cache database entries or delete generated stylesheets.

Verify actual browser delivery after invalidation: inspect the loaded controller
and matching CSS/settings, confirm eligibility on supported content roots, and
test theme switching while answers remain intact. Do not infer delivery merely
from a successful CLI call. No cache operation or browser request is performed
as part of preparing these release documents.
