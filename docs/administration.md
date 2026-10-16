# Theme administration

SPDX-License-Identifier: GPL-2.0-or-later.

The implemented module provides immutable built-ins, both custom-theme schemas,
native picker companions, global color runtime and saved/unsaved theme previews.
Stored density is not applied. See [installation](installation.md) for setup.

## Routes and access

All paths start with `admin/config/content/h5p-themer`:

| Suffix | Purpose |
| --- | --- |
| (none), `/list` | Core, built-in and custom overview |
| `/add` | Create a schema-1 custom definition |
| `/<custom-id>/edit` | Edit custom text, density and sparse overrides |
| `/<custom-id>/duplicate` | Copy a custom theme under a new custom ID |
| `/<custom-id>/delete` | Native custom deletion confirmation |
| `/default` | Select or clear the global default |
| `/presets/<preset-id>/<version>` | Read-only built-in inspection |
| `/presets/<preset-id>/<version>/duplicate` | Create a pinned schema-2 custom copy |

The administrative catalog is explicitly pinned to `moodle-478fed30d2a4`.
Preset routes validate both the exact ID and version. There are no built-in
Edit or Delete routes. A path such as `/daylight/edit` always addresses the
custom namespace; it never edits the built-in. It is valid only if that
custom object exists. Existing custom IDs, including `presets` and
`daylight`, are not reserved away by the new route layout.

Every route requires an authenticated account with the restricted
`administer h5p themes` permission. Callbacks also check access. GET requests
never mutate definitions or defaults. Native Form API supplies session-bound
CSRF tokens. There is no competing token scheme or custom AJAX workflow;
the Step 2C script enhances only forms with editable color fields.
Step 2C adds no menu routes and performs no cache or menu rebuild.

## Overview and built-in inspection

The overview has three separate sections:

1. **H5P Core defaults:** original styling with no Themer color/density
   overrides. This is distinct from Daylight.
2. **Built-in themes:** Daylight (default preset), Lavender, Mint, Sunset and
   Dark. These appear directly from the catalog, without editable config
   objects. Compatible installations offer View and Duplicate only.
3. **Custom themes:** valid schema-1 and schema-2 definitions, sorted naturally
   by label then ID, with Edit, Duplicate and Delete operations.

Tables distinguish stored and effective global defaults. A settings.php
override can make these differ; the overview warns and shows both indicators.
Invalid custom objects appear as diagnostic rows with no unsafe operations.
Missing/invalid defaults never silently select Core or Daylight.

A built-in View page shows its label, ID, version, density information,
provenance, license and all 34 exact values. Expressions such as `color-mix()`
and `rgba()` are escaped text inside code elements on the inspection page. Runtime delivery
uses exact trusted catalog values only when a global theme is selected.
Saved samples and a Core comparison are available. The extra `--h5p-theme-contrast-cta-dark` value is
preserved and explicitly marked as lacking established Core 1.28 runtime
support. The palette itself defines no density override.

## Creating and editing custom themes

Add theme retains the schema-1 behavior: a unique stable ID, editable label,
description, density and optional sparse hex overrides. IDs match
`[a-z][a-z0-9_]{0,63}`. Editing cannot change the machine ID. Label renaming
preserves all references.

Schema-1 editing preserves schema 1, sparse maps and exact hex spelling/case.
No migration or palette derivation occurs.

Schema-2 forms display the pinned base ID/version as read-only information.
Each of the 33 existing override fields separately identifies the inherited
value and whether a saved administrator override exists. Inputs contain only
stored overrides, never the effective palette. Blank means inherit the pinned
base; clearing a saved override restores inheritance after Save.

The inherited extra property is displayed read-only, and inherited non-hex
expressions remain exact. Only opaque `#RGB` or `#RRGGBB` can be entered as
custom overrides. Whitespace, alpha hex, functions, declarations, unknown
properties and injected top-level fields are rejected server-side.
Form API's NULL placeholders for omitted declared fields remain unset;
unknown submitted color keys are rejected even when their values are blank.

Saved colors are never silently normalized. Schema, pinned base and native
metadata come from trusted stored form context rather than posted fields.
Forms edit originals, not environment-specific effective values. Existing
objects with settings.php overrides are read-only until the overrides are
resolved. Duplicating an overridden custom source copies its stored original
without modifying that source or persisting its effective overrides.

General settings use a native collapsible fieldset, expanded initially. The
five color groups use native collapsible fieldsets, collapsed initially.
Backdrop opens groups containing validation errors. Submitted names and error
associations remain unchanged; grouping introduces no general settings object.

### Native color picker companions

With JavaScript and native color-input support, every editable hex field gets
an adjacent color picker with a distinct accessible label. The textfield is
the only submitted authority. The picker has no name and creates no extra
configuration property. Initializing or reattaching behaviors never fills or
rewrites the textfield.

Typing valid hex updates the picker display. Three-digit hex expands to six
digits for the picker only: typed shorthand and letter case remain exact.
Choosing a picker color writes an explicit six-digit hex override into the
textfield. Clearing means deleting the text and saving; there is no separate
Clear override button. Invalid or incomplete text remains untouched and is
still subject to strict server validation.

For schema 2, blank text inherits the exact pinned preset. Representable
inherited hex appears in the picker without becoming a saved override.
The exact inherited value remains visible in the existing description.
Inherited rgba() and color-mix() expressions are neither approximated nor
copied into editable fields. If no representable inherited hex exists, a blank
or invalid text value disables the picker until valid hex is typed. Its disabled
swatch is explicitly identified as not being a preview. If inherited hex is
available while text is invalid, the picker shows that inherited hex and
explains that choosing a color will replace the invalid text.

Schema-1 blank retains Core/no-override semantics. No preset is assigned or
substituted. Since the administration form has no resolved Core-color map,
its picker stays disabled until an explicit valid hex is entered.

Disabled/read-only fields remain guarded. Without JavaScript, or without native
color-input support, ordinary hex textfields and existing server validation
continue to work. With JavaScript disabled, native collapse CSS leaves every
fieldset visible. No external picker, custom CSS or additional dependency is
required. Initial built-in duplication stays metadata-only with a read-only
palette; color pickers appear when editing the saved custom copy.

## Duplicating a built-in

Select Duplicate from its overview row or View page. Supply a new custom ID,
label and description. Density initially is `large` and can be chosen in the
form. The form shows the inherited palette as read-only information.

Save creates exactly one schema-2 custom definition with the exact
`base_preset: {id, version}` reference and an empty `colors` map. To customize
colors, save this initial copy, then use its Edit operation. A forged initial
override map is rejected. All 34 base properties are available through
effective resolution without flattening them into stored custom colors.

The new custom ID may match a built-in ID, but must not collide with any
existing custom config object, including malformed objects. The source
catalog and global default remain unchanged; the copy is never selected
automatically. The exact immutable catalog snapshot is checked again during
validation and locked submission.

## Duplicating a custom theme

A schema-1 source produces a schema-1 copy. A schema-2 source produces a
schema-2 copy with the same exact base/version and sparse override map.
Description, density and editable values are prefilled and may be changed.
Every copy requires a new independent custom ID. Saving affects only the new
object, leaving the source and default unchanged. Stale/changed sources and
post-build ID collisions are rejected.

## Selecting and clearing the global default

The selector lists Core, five built-ins and all valid customs. Internal
selection tokens such as `core`, `builtin:daylight` and `custom:daylight`
are form-only keys. They are checked against the current allowed collection
during validation and again under the write lock; they are never persisted
as machine IDs or raw configuration references.

| Existing settings | Explicit choice | Saved representation |
| --- | --- | --- |
| Schema 1 | Core | Schema 1, null |
| Schema 1 | Custom | Schema 1, original-style custom ID string |
| Schema 1 | Built-in | Schema 2, typed built-in ID and exact catalog version |
| Schema 2 | Core | Schema 2, null |
| Schema 2 | Custom | Schema 2, typed custom ID |
| Schema 2 | Built-in | Schema 2, typed built-in ID and exact catalog version |

A legacy string `daylight` always preselects the custom theme. It never means
the built-in. Merely viewing the selector does not save, migrate or select
anything. Choosing a built-in is the only necessary schema upgrade in this
form; selecting Core/custom under schema 1 retains schema 1.

A broken custom reference remains an invalid selection until an administrator
explicitly clears or replaces it. Malformed settings, including an unknown
catalog version, produce a clear diagnostic and require validated configuration
repair. Settings.php overrides retain precedence and block UI settings writes.

## Deletion, failures and concurrency

Built-ins are immutable code data and have no administration deletion path.
Custom deletion uses native confirmation, permission/CSRF checks and the
module write lock. It is rejected if the object is selected by either the
stored or effective global default, whether the reference is legacy or typed.
Invalid effective settings block deletion conservatively. A built-in default
does not protect an unrelated same-name custom object.

Source snapshots, current references and overrides are rechecked on submit,
including under the lock. A default selected or overridden after a confirmation
form was built remains protected. Malformed custom definitions stay visible
for validated repair; forms do not silently repair or delete them.

When H5P is missing/disabled or its API is not exactly 1.28, definitions and
built-ins remain inspectable while mutations are disabled and rejected at
submit time. Config failures or busy locks yield errors without success
redirects. Locks are released on validation/storage failures.

The lock coordinates these administration mutations, not concurrent native
imports or manual filesystem edits. Native Config storage remains responsible
for persistence. Multi-object import is not transactional, and no atomic
cross-process behavior or real filesystem concurrency is claimed from unit
tests. Existing final-state import reference validation remains active.

## Verification and limitations

The full `tests/run.php` runner uses native Backdrop Config/import and Form API
definitions with memory-only storage, permissions, sessions, locks and caches.
It does not bootstrap the site or connect to a database. Warnings/deprecations
fail the run. Coverage includes both schemas, exact preset inspection and copies,
typed defaults, sparse inheritance, forged fields, tokens, stale forms, overrides,
selected-theme deletion protection and storage/lock failures.

Node tests exercise the production picker and preview behaviors with DOM/event
doubles and installed Backdrop once/collapse scripts. They are not a browser
emulator. Set `BACKDROP_SOURCE_ROOT` as described in the [README](../README.md).
The verified totals are 1,554 passed with PHP 7.4.33, 1,554 with PHP 8.4.22, and
80 with Node.js 22.23.1, all with zero failures.

The user separately confirmed manual Firefox PASS results for saved previews,
unsaved live edits, invalid input handling, inheritance/reset, multiple overrides,
native picker synchronization and JavaScript-disabled fallback. A 375px viewport
was usable but cramped. This is local manual evidence, not comprehensive
browser, library or accessibility certification. No browser checks were run
during publication cleanup. Historical phase records retain their earlier
checkpoint-specific totals and pending-test statements.

## Global runtime colors

Choosing a global theme makes its supported colors available to compatible H5P
playback after reload. Core/null means no Themer overrides, with no generated
asset or public-directory creation. Built-ins use exact catalog values; schema-1
customs use stored sparse overrides; schema-2 customs use the pinned base and
sparse overrides. Invalid references, incompatible APIs and storage failures
attach no theme CSS and do not silently repair configuration.

CSS is scoped to `.h5p-content`, delivered through the H5P styles hook in div,
iframe and external playback modes, and excluded from editor mode. Only variables
consumed by a library affect it; authored/hardcoded colors can remain unchanged.
External embeds must be served through the compatible integration on the
Themer-enabled site. The parent page cannot theme an unrelated remote provider.
Multiple/nested players, cross-origin boundaries, fullscreen and independent
caches need context-specific qualification.

Generated public CSS is immutable derived cache data named with serializer
version and CSS hash. Verified files are reused; lock contention may leave a
request temporarily unthemed. Successful global-reference or selected-custom
color changes invalidate native page/layout caches. Metadata-only, unselected,
no-op and rejected saves do not. Open players must be reloaded. Existing imports
retain native cache handling; direct programmatic changes require caller-managed
invalidation. Stored density remains editable but is not applied.

## Saved Theme Preview

Built-in View and existing custom Edit pages show administrator-only representative
samples in a script-free sandboxed iframe. A separate Core-defaults comparison
uses the actual installed Core variable stylesheet with no Themer overrides.
Built-ins resolve their immutable preset values; custom previews resolve saved
stored data from either schema. The unsupported extra catalog property is not
emitted. No actual H5P activity is created or edited by this sample.

The frame embeds trusted fixed Core/sample CSS, not remote fonts or activity
assets, with a restrictive CSP and no `allow-scripts`. Rendering does not write
configuration, content, caches or runtime CSS files. Density is not applied.
The appearance is representative and cannot certify every library's rendering,
contrast, keyboard behavior or accessibility.

## Live Preview of unsaved custom colors

Custom Edit starts from the saved theme. Parent-side JavaScript updates only
the dedicated override style block as valid authoritative hex fields change.
Native picker changes synchronize through those same textfields. Multiple valid
overrides combine; labels distinguish saved colors from an unsaved draft.
The iframe document, Core/sample styles, Core comparison, CSP and sandbox do not
change for a keystroke.

Blank restores exact pinned inheritance for schema 2, including trusted
expressions; schema-1 blank removes the declaration for the actual Core cascade.
Invalid/incomplete input is left in its textfield, retains the field's last valid
preview color and displays an explanatory message. Server validation still
rejects invalid values on Save.

Reverting colors or native form reset restores the corresponding form defaults.
Reset is observed; the module does not add a separate reset button. A cancelled
reset remains cancelled. Preview updates do not submit, save, fetch or publish
runtime CSS. Only the normal explicit **Save** action can persist validated edits.

With JavaScript disabled, the saved preview and Core comparison remain available.
Hex textfields and fieldsets stay accessible, and PHP still validates and saves.
Unsaved live updates require JavaScript. Native color inputs are enhancements,
not required submission values.

See the [installation guide](installation.md), [preview development record](phase-2b-preview.md)
and [PLANNED Phase 2C/2D roadmap](roadmap.md).
