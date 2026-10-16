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
