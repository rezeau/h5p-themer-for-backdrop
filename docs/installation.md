# Installation and first use

> v0.4.3 release metadata is prepared, not packaged or published. Native Density
> qualifies only standalone official H5P.TrueFalse 1.8.24, H5P.MultiChoice 1.16.27,
> H5P.Blanks 1.14.37 and H5P.MarkTheWords 1.11.24 internal iframe players on Core
> API exactly 1.28. Other versions, PapiJo and embedded compound activities remain
> excluded. Colour-theme eligibility is separate from Density qualification.
> The permitted-colour selector and theme-only **Use site default theme** Reset
> preserve independent Small/Medium/Large preferences. The current administrator
> default is followed when no explicit learner colour preference exists.
> See [release scope and upgrade information](release-notes-v0.4.3.md) and
> [19 user-reported Firefox passes](v0.4.3-markthewords.md).
>
> Historical v0.4.2 contract: [independent Density and earlier migration rules](v0.4.2-development.md).
> Native Density retains its existing checkbox/PHP override and has no visible
> Density reset button; the separate theme Reset clears only the theme preference.
> The older version-specific material below remains historical.


SPDX-License-Identifier: GPL-2.0-or-later.

## v0.4.2 to v0.4.3 upgrade and rollback

No new configuration key, schema migration or update hook is introduced; schema
remains 1003. Retain administrator settings, custom themes and authored H5P content.
For a separately authorized upgrade, back up the matching code/configuration/data,
deploy coherent module files, refresh relevant caches and reload pages. Existing
open pages retain their loaded code. Do not replace H5P libraries or content and
do not uninstall/reinstall. Rollback to matching v0.4.2 code needs no new reverse
conversion. A package-based upgrade/rollback rehearsal remains recommended before publication;
no such operation is authorized by these instructions.

## v0.4.3 release packaging preparation

A release archive must contain one `h5p_themer/` module root, with regular-file
entries and no second wrapper or explicit directory entries. The reviewed
allowlist and frozen manifest define the exact deliverable inventory.

Ship the module's PHP/INC files, `h5p_themer.info`, `h5p_themer.install`, the
`config/h5p_themer.settings.json` default, and all five `js/` and three `css/`
assets identified in the reviewed release inventory. Preserve README, CHANGELOG,
approved user documentation and all five license/notice files: `LICENSE`,
`LICENSE-GPL-3.0.md`, `LICENSE-MIT-H5P-THEME-PICKER.txt`, `LICENSE.txt` and
`THIRD-PARTY-NOTICES.md`. The production
inventory must be explicit; neither a recursive filesystem ZIP nor a repository
source download is a validated release package.

Exclude tests/fixtures, developer tooling, local artifacts, Git/editor/agent
metadata, dependencies, credentials/settings.php, dumps, logs, generated site
assets, caches and backups. Verify archive paths, exact inventory, regular-file
types, collision/traversal rejection, CRC, sizes and SHA-256 hashes against frozen
source bytes; verify embedded metadata says 0.4.3 and dependencies remain unchanged.
Archive construction, package validation and publication require separate approval.
No v0.4.3 archive is created by this documentation phase.

<a id="current-v041-to-v042-upgrade-and-rollback"></a>

## Historical v0.4.1 to v0.4.2 upgrade and rollback

Use the [v0.4.2 release notes](release-notes-v0.4.2.md) and
[current Density contract](v0.4.2-development.md). The older version-specific
procedures below are historical and must not be replayed for this upgrade.

1. Verify the exact target site and take a matching database/filesystem backup,
   including active configuration, settings.php, custom themes and H5P content/libraries.
   Verify hashes and retain the existing module for rollback.
2. Replace only the module with a complete matching v0.4.2 distribution. Do not
   uninstall/reinstall, copy default config into active storage, replay update hooks,
   change schema 1003 or replay visibility activation. Existing settings may omit
   the new optional boolean; no theme-schema migration is required.
3. Refresh deployment caches using supported APIs scoped to that site, then reload
   playback so PHP, JavaScript, CSS and version-3 metadata are coherent. Do not
   modify H5P content/libraries or assume old open pages update automatically.
4. Existing PHP TRUE/FALSE overrides retain precedence. NULL or another invalid
   present value disables with a diagnostic. Without the PHP override, a missing
   checkbox key defaults to disabled. Save the administrator checkbox to enable it.
5. Verify themes, visibility, qualification, playback and persistence. Legacy saved
   theme Density is preserved but no longer affects playback. Original spacing
   precedes the learner's first choice; there is no visible reset button.

**Rollback is settings-aware.** Once `native_density_enabled` has been saved,
v0.4.1 rejects that new key. Restore matching pre-upgrade Themer settings,
settings.php and code through a separately reviewed recovery procedure; code-only
rollback is insufficient. Preserve other work performed since the backup. If a
full restore is required, use the matching database/filesystem pair, verify the
target and hashes, and obtain explicit authorization rather than restoring
automatically. Do not use uninstall/reinstall or guarded theme/policy imports as
a recovery shortcut. No full restore rehearsal has been completed.

The backdrop2 local deployment, administrator checkbox conversion and user-reported
Firefox checks passed. These results do not qualify every site or a full rollback.

## Historical installation and checkpoint guidance

This guide covers the v0.2.1 baseline and **v0.4.0**. v0.3.0 was withdrawn.
For uncommitted v0.4.1 learner Density development, see
[current readiness and testing](v0.4.1-readiness.md). The existing activation and
cache guidance still applies; no new schema migration or automatic deployment is
introduced. v0.4.1 package/upgrade acceptance remains a separate checkpoint.
The [v0.4.0 to v0.4.1 disposable rehearsal procedure](v0.4.1-upgrade-rehearsal.md)
is a plan only; do not treat prepared metadata as authorization to deploy.
Historical phase/checkpoint records preserve their
original evidence; they are not current installation instructions. See the
[v0.4.0 readiness note](v0.4.0-readiness.md) for accepted evidence, remaining release preparation and qualification limits.

## 1. Prepare Backdrop and the H5P integration

Backdrop **1.x** is the metadata declaration. Actual source inspection and local
verification used **Backdrop 1.35.2**, H5P Core API **1.28**, and PHP **7.4.33**,
**8.3.18** and **8.4.22**. The accepted Checkpoint 3C Stage 1 baseline passed 3,793 PHP assertions on each
version and 470 tests on Node.js **22.23.1**. Later focused verification passed
46 Checkpoint 3C Lite assertions and 85 module lifecycle checks; Lite also passed
all seven manual Firefox acceptance tests. The complete suites use isolated
collaborators; the later clone checks cover specific database-backed deployment
and module operations. Neither establishes universal installation or browser
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

Native Density starts disabled unless the strict site setting below is enabled.

Do not invoke update hooks manually on a fresh installation. Backdrop records
the installed schema level and runs the install hook through its normal module
installation lifecycle. Verify that the overview has no activation diagnostics.

The existing local public-files directory must be writable and use a browser-safe
relative path for global-theme CSS. Remote/absolute public-path mappings are not
supported by this runtime. Preview itself does not publish runtime CSS.

<a id="v021-to-v040-development-upgrade-and-rollback"></a>

### v0.2.1 to v0.4.0 upgrade and rollback

**Verified in an independent disposable clone; not an executed production upgrade.**
Accepted Checkpoint 3C Lite supersedes the planned full Stage 2/3 rehearsal. It
verified the cache-warmed v0.2.1 upgrade, hook/cache procedure and Density-setting
OFF/ON, with 46 focused assertions and all seven manual Firefox acceptance tests.
A separate clone subsequently passed 85 actual module disable/re-enable checks
without uninstalling. Full code rollback was not rehearsed; the procedure below
remains recovery guidance, not a tested rollback claim. Preserve all accepted
Checkpoint 2B, 3B and 3C Lite environments. Verify the intended deployment target
and backups; metadata 0.4.0 alone does not authorize deployment.

1. Confirm the intended site/root/database, installed Themer version and schema,
   Core API exactly 1.28, and actual installed H5P library versions. Only trusted
   server-qualified **H5P.TrueFalse 1.8.24**, top-level iframe playback, supports
   Density. Development-mode libraries, other versions/types, nested/DIV/external
   players and ambiguous embeddings are excluded. Do not edit version declarations.
2. Back up complete module code, settings.php, active configuration and database
   recovery state. Record checksums and a site-specific restore plan. Preserve
   custom themes, saved Density, preset pins, Global Default, visibility and learner
   preferences/configuration. Pause theme administration/config synchronization.
3. Keep native Density disabled for initial deployment: leave its setting absent
   or set it to boolean FALSE. Deploy the complete v0.4.0 module coherently, including
   the new native-density PHP include and JS controller. Never deploy a mixture of
   versions or uninstall/reinstall. For existing v0.2.1 at schema 1003, there is no
   new database update or configuration migration. Do not replay update hooks.
4. **Refresh hook discovery**, including the new `hook_node_view`, and perform
   supported cache rebuilding covering module metadata, output and JS/CSS aggregates.
   The following are native PHP API calls, not shell/Drush commands. Run only inside
   a supported, fully bootstrapped CLI context that has verified the intended site:

   ```php
   module_implements_reset();
   backdrop_flush_all_caches();
   ```

   Source was verified in Backdrop 1.35.2: `core/includes/module.inc` defines the
   hook reset; `core/includes/common.inc` defines the rebuild, including module data,
   JS/CSS caches and bootstrap/output bins. **No-argument `cache_clear_all()` alone
   does not reset hook discovery.** This sequence was verified in the cache-warmed
   3C Lite clone; adapt and verify the guarded bootstrap for each intended target. Do not expose a cache-clearing web endpoint or run this as
   unbootstrapped PHP. Respect any site-specific opcode-cache deployment process.
5. With Density still OFF, verify existing colors, Global Default, hidden themes,
   learner choices and saved/live color preview. Compare configuration with backup;
   existing schema-1/2 customs and saved Density must remain unchanged.
6. If qualified content exists and the administrator opts in, add:

   ```php
   $settings['h5p_themer_native_density_enabled'] = TRUE;
   ```

   Only boolean TRUE enables it: strings, 1, arrays, NULL and FALSE do not. This is an
   installation setting, not a new form control or exported configuration field.
7. Refresh relevant output and JS assets in the intended site and reload playback.
   Existing native API calls for this narrower refresh are `backdrop_clear_js_cache();`
   and `cache_clear_all();` in the same guarded full CLI context. These do not replace
   step 4 when module hooks change. Check actual delivered assets and parent metadata.
8. Verify native Small/Medium/Large; retained answers/feedback/Retry; Core restoring
   original; built-ins restoring original; Site default following effective Global
   Default, including a hidden custom default. Unsupported content must retain its
   original Density while existing color behavior continues under its own rules.

**Disable:** set the installation switch FALSE or remove it, refresh relevant caches
and reload. Already-open pages retain their supplied settings until reloaded; this
is not a live remote kill switch. Refresh qualification/output after library changes
too; a newer patch is not automatically qualified.

**Module disable/re-enable:** use the normal checkbox and Save configuration at
`admin/modules`. Do not uninstall or delete configuration. This is separate from
the Density installation switch above. On the independent lifecycle clone,
disabling removed Themer playback hooks/assets on fresh pages while H5P still
played and scored. Re-enabling restored hooks, colors, the learner picker and
qualified Density. Schema 1003, all eight saved configuration hashes and Global
Default `fresh_source` were preserved; the Density opt-in remained TRUE throughout.
Reload playback after either module operation. This does not qualify every site
or imply that answers persist across navigation/reload.

**Code rollback to v0.2.1 (not rehearsed):** restore the complete known-good v0.2.1 module and the
previous site setting (or remove the Density switch). Repeat the hook-discovery
reset and supported asset/output rebuild, then reload and verify colors, learner
selection and preview. Preserve theme configuration and saved Density; no reverse
schema migration or routine database restore is necessary. Older v0.2.1 code ignores
this new installation setting. Existing open v0.4.0 pages require reload.

**Partial deployment failure:** keep the site out of active use while restoring a
complete known-good module; do not try to repair missing includes with mixed code.
Use the recorded site-specific recovery plan. Restore a database snapshot only
when independently needed to undo test/data changes, never over unrelated or later
production data. No upgrade or rollback is authorized by this documentation.

<a id="upgrading-an-existing-installation"></a>

### Older installation/schema history

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
that guarantee. Native Density has a narrower, opt-in scope: qualified top-level TrueFalse 1.8.24 iframe playback only; see the activation procedure above.

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

For a code upgrade adding/removing hooks, first follow the [hook-discovery and full rebuild procedure](#v021-to-v040-development-upgrade-and-rollback) above. The narrower operations below describe existing asset/header corrections; they are not a complete v0.2.1 upgrade or rollback.

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
