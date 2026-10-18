# Installation and first use

SPDX-License-Identifier: GPL-2.0-or-later.

This is current installation guidance. Historical phase/checkpoint records are
development evidence, not installation instructions.

## 1. Prepare Backdrop and the H5P integration

Backdrop 1.x is declared compatible; actual inspection and local verification
used Backdrop **1.35.2**. PHP **7.4.33** and **8.4.22** passed the automated suite.
Other versions have not been established by those results.

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

## 2. Install H5P Themer

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

The existing local public-files directory must be writable and use a browser-safe
relative path for global-theme CSS. Remote/absolute public-path mappings are not
supported by this runtime. Preview itself does not publish runtime CSS.

## 3. Grant administration access

At `admin/people/permissions`, grant **Administer H5P themes** only to trusted
administrator roles. This restricted permission controls theme administration,
not a learner selector. Log in with an authorized account and open
`admin/config/content/h5p-themer`.

## 4. Select a global theme

Open **Global Default** at `admin/config/content/h5p-themer/default`.
Choose H5P Core defaults, one of the five built-ins, or an existing custom theme,
then save. A new installation starts with **Core/null**, which adds no overrides.
Daylight is a separate preset and is never automatically substituted for Core.

Reload existing activity pages after saving. Only libraries that consume the
relevant variables will visibly change. Embedded playback served by this site
can receive the selected global colors through its integration; unrelated remote
providers, existing open frames and independently cached host pages are outside
that guarantee. Density is not applied.

## 5. Create or edit a custom theme

Use **Add theme** to create a schema-1 custom theme with a unique machine ID,
label, density metadata and optional sparse hex overrides. Or use **Duplicate**
on a built-in to create an editable schema-2 custom theme pinned to that preset's
exact version. Built-ins themselves are read-only.

Open the custom theme's **Edit** action. The machine ID is stable. Enter
`#RGB` or `#RRGGBB` in the authoritative textfields, or use their native picker
companions. Blank retains pinned inheritance for schema 2 and Core's cascade for
schema 1. Existing schema-1 data is not migrated merely by editing.

General settings are expanded initially; color groups start collapsed. Groups
containing server validation errors expand automatically. A custom theme selected
as the stored or effective global default is protected against deletion.

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
[planned roadmap](roadmap.md) for details.

## 7. Optional learner theme selection

Global Default offers **Allow learners to select their H5P theme**, false by
default. To enable it, open `admin/config/content/h5p-themer/default`, check the
setting, save, and reload an existing H5P playback page. One native selector is
shown above the first eligible activity and controls all eligible activities on
that page without H5P reinitialization, iframe navigation or answer/scoring changes.

Choose Site default to follow the administrator's configured global theme, or
explicit H5P Core defaults to use Core independently of that setting. Daylight is
a distinct built-in palette; Lavender, Mint, Sunset, Dark and all valid saved
custom themes are also available. Existing custom inheritance remains intact.

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

To stop future Themer overrides, choose Core as the administrator-global default
and disable learner selection, then save/reload playback pages. Global Core alone
may still attach learner assets while selection is enabled. Explicit Core in a
learner selector affects only that learner's choice, not administrator settings.

Phase 2C functionality passed user-confirmed Firefox Step 5E 10/10 and Step 6E
9/9 acceptance checks. Source/tests check the native select and associated label.
Keyboard-only acceptance, screen-reader announcements, focus on removal,
narrow/mobile layout, RTL/long translated labels, Chromium, Safari, restricted
storage and broader library support are not fully qualified. No WCAG certification
or stable-production guarantee is made. See [the current roadmap](roadmap.md).

## Deployment cache refresh

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
CSS clearing or forced stylesheet regeneration is needed for this correction.
Preserve generated CSS, H5P dependencies, hook discovery and authored content.
Do not manually edit cache database entries or delete generated stylesheets.

Verify actual browser delivery after invalidation: inspect the loaded controller
and matching CSS/settings, confirm eligibility on supported content roots, and
test theme switching while answers remain intact. Do not infer delivery merely
from a successful CLI call. No cache operation or browser request is performed
as part of the publication text corrections.
